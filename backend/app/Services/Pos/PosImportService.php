<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinancialJournalService;
use App\Services\InventoryMovementService;
use App\Services\JournalPostingService;
use App\Services\NumberSequenceService;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PosImportService
{
    public function receive(string $instanceId, array $events, int $nextCursor): void
    {
        abort_if(count($events) > config('pos.max_batch_events') || $nextCursor < 0, 422, 'POS batch limits exceeded.');
        DB::transaction(function () use ($instanceId, $events, $nextCursor) {
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->lockForUpdate()->firstOrFail();
            foreach ($events as $event) {
                $bytes = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                if (strlen($bytes) > config('pos.max_event_bytes') || ! is_array($event) || ! is_string($event['event_id'] ?? null) || ! Str::isUuid($event['event_id']) || ! is_int($event['source_sequence'] ?? null) || $event['source_sequence'] < 1) {
                    DB::table('pos_sync_conflicts')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instanceId, 'event_id' => (is_array($event) && is_string($event['event_id'] ?? null) && Str::isUuid($event['event_id'])) ? $event['event_id'] : (string) Str::uuid(), 'received_hash' => hash('sha256', $bytes), 'received_bytes' => $bytes, 'created_at' => now(), 'updated_at' => now()]);

                    continue;
                }
                abort_if($event['source_sequence'] > $nextCursor, 422, 'POS cursor precedes event.');
                $hash = hash('sha256', $bytes);
                $existing = DB::table('pos_sync_inbox')->where('pos_instance_id', $instanceId)->where(fn ($q) => $q->where('event_id', $event['event_id'])->orWhere('source_sequence', $event['source_sequence']))->first();
                if ($existing) {
                    if (! hash_equals($existing->received_hash, $hash)) {
                        DB::table('pos_sync_conflicts')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instanceId, 'event_id' => $event['event_id'], 'received_hash' => $hash, 'received_bytes' => $bytes, 'created_at' => now(), 'updated_at' => now()]);
                    }

                    continue;
                }
                DB::table('pos_sync_inbox')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instanceId, 'event_id' => $event['event_id'], 'source_sequence' => $event['source_sequence'], 'received_hash' => $hash, 'received_bytes' => $bytes, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('pos_instances')->where('id', $instanceId)->update(['durable_cursor' => max($instance->durable_cursor, $nextCursor), 'updated_at' => now()]);
        });
    }

    public function process(string $instanceId, string $eventId, ?User $recoveryActor = null): array
    {
        if ($recoveryActor) {
            abort_unless(app()->environment(['local', 'testing']) && $recoveryActor->status === 'active' && $recoveryActor->hasPermissionCode('pos.manage') && $recoveryActor->hasPermissionCode('inventory.adjust'), 403, 'Authorized local recovery actor required.');
        }
        try {
            return DB::transaction(function () use ($instanceId, $eventId, $recoveryActor) {
                $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->lockForUpdate()->firstOrFail();
                abort_if($instance->recovery_fenced_at !== null && (! $recoveryActor || $recoveryActor->org_id !== $instance->org_id), 423, 'POS instance is recovery-fenced. Evidence must be reconciled before posting.');
                $inbox = DB::table('pos_sync_inbox')->where('pos_instance_id', $instanceId)->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
                if ($inbox->status === 'accepted') {
                    return json_decode($inbox->result, true);
                }
                abort_if($inbox->attempts >= config('pos.max_attempts'), 422, 'POS retry limit reached; authorized replay required.');
                $event = json_decode($inbox->received_bytes, true, flags: JSON_THROW_ON_ERROR);
                if (in_array($event['event_type'] ?? null, ['sale.refunded', 'cash.moved', 'kitchen.consumed', 'kitchen.wasted', 'return.inspected', 'tax.document_issued'], true)) {
                    $owner = match ($event['event_type']) {
                        'sale.refunded' => PosRefundImportService::class, 'cash.moved' => PosCashMovementImportService::class, 'return.inspected' => PosReturnInspectionImportService::class, 'tax.document_issued' => PosTaxDocumentImportService::class, default => PosKitchenImportService::class
                    };
                    $result = app($owner)->import($instance, $event);
                    $oldResult = $inbox->result ? json_decode($inbox->result, true) : [];
                    $result['revision'] = ($oldResult['revision'] ?? 0) + 1;
                    DB::table('pos_sync_inbox')->where('id', $inbox->id)->update(['status' => $result['status'], 'dependency_source_sale_id' => $result['status'] === 'dependency_pending' && $event['event_type'] === 'sale.refunded' ? $event['payload']['sale_id'] : null, 'result' => json_encode($result), 'reason_code' => $result['reason_code'], 'attempts' => $inbox->attempts + 1, 'acknowledged_at' => null, 'updated_at' => now()]);

                    return $result;
                }
                $payload = app(PosSaleValidator::class)->validate($instance, $event);
                $date = Carbon::parse($payload['occurred_at'])->setTimezone($payload['business_timezone'])->toDateString();
                $sale = DB::table('pos_import_sales')->where('pos_instance_id', $instanceId)->where('source_sale_id', $payload['sale_id'])->first();
                if ($sale) {
                    abort_unless(hash_equals($sale->payload_hash, $event['payload_hash']), 409, 'POS sale source conflict.');
                    $mapping = PosAccountMapping::forSale($instanceId, $sale->id);
                } else {
                    $mapping = PosAccountMapping::forInstance($instanceId);
                    abort_if(isset($payload['discount_approval_id']) && DB::table('pos_import_sales')->where('pos_instance_id', $instanceId)->where('discount_approval_id', $payload['discount_approval_id'])->exists(), 409, 'POS discount approval already used by another sale.');
                    $sale = $this->postFinancial($instance, $payload, $event['payload_hash'], $date, $mapping);
                }
                $inventoryStatus = $sale->inventory_status;
                if ($inventoryStatus !== 'posted') {
                    try {
                        DB::transaction(function () use ($instance, $payload, $date, $mapping, $sale) {
                            $inventory = app(InventoryMovementService::class);
                            $inventory->lockProducts($instance->org_id, array_column($payload['lines'], 'product_id'));
                            $cost = '0.00';
                            foreach ($payload['lines'] as $line) {
                                if (($line['stock_effect'] ?? null) === 'prepared') {
                                    app(PosKitchenSalePolicy::class)->assertPosted($instance, $line);

                                    continue;
                                }
                                foreach ($line['allocations'] as $allocation) {
                                    $source = 'pos:'.$instance->id.':'.$payload['sale_id'].':'.$line['line_id'].':'.$allocation['grant_id'];
                                    $movement = $inventory->consumePosEntitlement($instance->id, $allocation['grant_id'], $allocation['epoch'], $allocation['quantity'], $date, $source);
                                    $cost = bcsub($cost, $movement->base_total_cost, 2);
                                }
                            }
                            if (bccomp($cost, '0', 2) > 0) {
                                app(JournalPostingService::class)->post($instance->org_id, $instance->actor_id, 'pos_sale', $sale->id, 'inventory', $date, 'POS inventory cost', [['account_code' => $mapping->code('cogs', $instance->org_id), 'debit' => $cost], ['account_code' => $mapping->code('inventory', $instance->org_id), 'credit' => $cost]]);
                            }
                            DB::table('pos_import_sales')->where('id', $sale->id)->update(['inventory_status' => 'posted', 'updated_at' => now()]);
                        });
                        $inventoryStatus = 'posted';
                    } catch (HttpExceptionInterface $e) {
                        if ($e->getStatusCode() !== 422) {
                            throw $e;
                        }
                        $inventoryStatus = 'pending';
                    }
                }
                $oldResult = $inbox->result ? json_decode($inbox->result, true) : [];
                $result = ['status' => $inventoryStatus === 'posted' ? 'accepted' : 'inventory_pending', 'revision' => ($oldResult['revision'] ?? 0) + 1, 'reason_code' => $inventoryStatus === 'posted' ? null : 'inventory_discrepancy', 'invoice_id' => $sale->invoice_id];
                DB::table('pos_sync_inbox')->where('id', $inbox->id)->update(['status' => $result['status'], 'result' => json_encode($result), 'reason_code' => $result['reason_code'], 'attempts' => $inbox->attempts + 1, 'acknowledged_at' => null, 'updated_at' => now()]);

                return $result;
            });
        } catch (\Throwable $e) {
            if (! ($e instanceof HttpExceptionInterface || $e instanceof ValidationException || $e instanceof \InvalidArgumentException || $e instanceof PosDependencyPending)) {
                throw $e;
            }

            return DB::transaction(function () use ($instanceId, $eventId, $e) {
                DB::table('pos_instances')->where('id', $instanceId)->lockForUpdate()->firstOrFail();
                $row = DB::table('pos_sync_inbox')->where('pos_instance_id', $instanceId)->where('event_id', $eventId)->lockForUpdate()->firstOrFail();
                if ($row->status === 'accepted') {
                    return json_decode($row->result, true);
                }
                $old = $row->result ? json_decode($row->result, true) : [];
                $pending = $e instanceof PosDependencyPending;
                if ($pending) {
                    foreach (array_unique($e->sourceIds) as $sourceId) {
                        DB::table('pos_sync_dependencies')->insertOrIgnore(['id' => (string) Str::uuid(), 'pos_instance_id' => $instanceId, 'inbox_id' => $row->id, 'source_kind' => $e->sourceKind, 'source_id' => $sourceId, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
                $result = ['status' => $pending ? 'dependency_pending' : 'needs_review', 'revision' => ($old['revision'] ?? 0) + 1, 'reason_code' => $pending ? 'source_dependency_unavailable' : 'validation_or_policy_failure'];
                DB::table('pos_sync_inbox')->where('id', $row->id)->update(['status' => $result['status'], 'reason_code' => $result['reason_code'], 'result' => json_encode($result), 'attempts' => $row->attempts + 1, 'acknowledged_at' => null, 'updated_at' => now()]);

                return $result;
            });
        }
    }

    private function postFinancial(object $i, array $p, string $hash, string $date, PosAccountMapping $mapping): object
    {
        $invoice = Invoice::create(['org_id' => $i->org_id, 'branch_id' => $i->branch_id, 'invoice_no' => app(NumberSequenceService::class)->next($i->org_id, 'invoice'), 'customer_id' => $i->customer_id, 'status' => 'paid', 'tax_mode' => 'exclusive', 'issue_date' => $date, 'due_date' => $date, 'subtotal' => $p['subtotal'], 'tax_amount' => $p['tax_amount'], 'total' => $p['total'], 'paid_amount' => $p['total'], 'balance_due' => '0', 'currency' => $p['currency'], 'base_currency' => $p['currency'], 'exchange_rate' => 1, 'base_subtotal' => $p['subtotal'], 'base_tax_amount' => $p['tax_amount'], 'base_total' => $p['total'], 'base_paid_amount' => $p['total'], 'base_balance_due' => '0', 'paid_at' => now(), 'created_by' => $i->actor_id, 'notes' => 'POS source '.$p['receipt_no']]);
        foreach ($p['lines'] as $index => $line) {
            $invoice->items()->create(['org_id' => $i->org_id, 'product_id' => $line['product_id'], 'description' => $line['name'], 'quantity' => $line['quantity'], 'unit' => $line['unit'], 'unit_price' => $line['unit_price'], 'tax_rate' => $line['tax_rate'], 'line_total' => $line['subtotal'], 'discount_amount' => $line['discount_amount'] ?? '0.00', 'sort_order' => $index]);
        }
        $payment = Payment::create(['org_id' => $i->org_id, 'invoice_id' => $invoice->id, 'entry_type' => 'receipt', 'amount' => $p['total'], 'currency' => $p['currency'], 'base_currency' => $p['currency'], 'exchange_rate' => 1, 'base_amount' => $p['total'], 'invoice_base_amount' => $p['total'], 'payment_date' => $date, 'payment_method' => 'cash', 'reference_no' => $p['receipt_no'], 'idempotency_key' => 'pos:'.$i->id.':'.$p['sale_id'], 'created_by' => $i->actor_id]);
        app(FinancialJournalService::class)->postInvoice($invoice, $i->actor_id, $mapping);
        app(FinancialJournalService::class)->postPayment($payment, $i->actor_id, $mapping);
        $id = (string) Str::uuid();
        DB::table('pos_import_sales')->insert(['id' => $id, 'pos_instance_id' => $i->id, 'source_sale_id' => $p['sale_id'], 'discount_approval_id' => $p['discount_approval_id'] ?? null, 'payload_hash' => $hash, 'source_snapshot' => PosCanonicalJson::encode($p), 'account_snapshot' => PosCanonicalJson::encode($mapping->snapshot($i->org_id)), 'invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'inventory_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        foreach ($p['lines'] as $line) {
            foreach ($line['kitchen_refs'] ?? [] as $ref) {
                DB::table('pos_import_kitchen_sales')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $i->id, 'source_kitchen_line_id' => $ref['line_id'], 'sale_id' => $id, 'source_sale_line_id' => $line['line_id'], 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        AuditLog::create(['org_id' => $i->org_id, 'actor_user_id' => $i->actor_id, 'action' => 'pos.sale.import', 'entity_type' => 'invoice', 'entity_id' => $invoice->id, 'after_json' => ['instance_id' => $i->id, 'sale_id' => $p['sale_id']], 'request_id' => (string) Str::uuid()]);

        return DB::table('pos_import_sales')->where('id', $id)->firstOrFail();
    }
}
