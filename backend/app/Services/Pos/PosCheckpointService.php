<?php

namespace App\Services\Pos;

use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosCheckpointService
{
    public function verify(string $instanceId, array $body): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_checkpoints_enabled') === true, 403);

        return DB::transaction(function () use ($instanceId, $body) {
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->lockForUpdate()->firstOrFail();
            app(PosSignedDocumentVerifier::class)->verify($instance, $body, 'company-os.pos-checkpoint.v1');
            $p = validator($body, ['schema_version' => 'required|integer|in:1', 'checkpoint_id' => 'required|uuid', 'branch_id' => 'required|uuid', 'recovery_generation' => 'required|string|min:8|max:100', 'after_sequence' => 'required|integer|min:0', 'through_sequence' => 'required|integer|min:0', 'high_watermark' => 'required|integer|min:0', 'digest' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'occurred_at' => 'required|date', 'entries' => 'present|array|max:1000', 'entries.*.sequence' => 'required|integer|min:1|distinct', 'entries.*.event_id' => 'required|uuid|distinct', 'entries.*.event_type' => 'required|string|max:80', 'entries.*.envelope_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']])->validate();
            abort_unless($p['branch_id'] === $instance->branch_id && $p['after_sequence'] <= $p['through_sequence'] && $p['through_sequence'] <= $p['high_watermark'] && hash_equals($p['digest'], hash('sha256', PosCanonicalJson::encode($p['entries']))) && ! Carbon::parse($p['occurred_at'])->gt(now()->addMinutes(5)), 422, 'Checkpoint bounds or digest invalid.');
            $existing = DB::table('pos_source_checkpoints')->where('pos_instance_id', $instanceId)->where('source_checkpoint_id', $p['checkpoint_id'])->first();
            abort_if($existing && $existing->snapshot !== PosCanonicalJson::encode($body), 409, 'Checkpoint identity conflict.');
            $issues = [];
            if (! $instance->recovery_generation || ! hash_equals($instance->recovery_generation, $p['recovery_generation']) || $p['high_watermark'] < $instance->checkpoint_cursor || (! $instance->recovery_fenced_at && $p['after_sequence'] > $instance->checkpoint_cursor)) {
                DB::table('pos_instances')->where('id', $instanceId)->update(['recovery_fenced_at' => now()]);
                $issues[] = ['code' => 'source_generation_or_watermark_conflict'];
            }
            if ($instance->recovery_fenced_at) {
                $issues[] = ['code' => 'instance_recovery_fenced'];
            }
            $last = (int) $p['after_sequence'];
            $days = [];
            foreach ($p['entries'] as $entry) {
                abort_unless($entry['sequence'] > $last && $entry['sequence'] <= $p['through_sequence'], 422, 'Checkpoint entries are not ordered.');
                $last = $entry['sequence'];
                $row = DB::table('pos_sync_inbox')->where('pos_instance_id', $instanceId)->where('source_sequence', $entry['sequence'])->where('event_id', $entry['event_id'])->first();
                if (! $row) {
                    $issues[] = ['code' => 'source_event_not_accepted', 'event_id' => $entry['event_id']];

                    continue;
                }
                $event = json_decode($row->received_bytes, true, flags: JSON_THROW_ON_ERROR);
                if (($event['payload']['branch_id'] ?? null) !== $instance->branch_id || ($event['event_type'] ?? null) !== $entry['event_type'] || ! hash_equals($entry['envelope_hash'], hash('sha256', PosCanonicalJson::encode($event)))) {
                    $issues[] = ['code' => 'source_event_hash_mismatch', 'event_id' => $entry['event_id']];

                    continue;
                }
                if ($row->status !== 'accepted') {
                    $issues[] = ['code' => 'source_event_not_accepted', 'event_id' => $entry['event_id']];

                    continue;
                }
                $this->auditEvent($instance, $event, $issues);
                $kind = match ($entry['event_type']) {
                    'sale.completed' => 'sales', 'sale.refunded' => 'refunds', 'cash.moved' => 'cash', default => null
                };
                if ($kind) {
                    $days[Carbon::parse($event['occurred_at'])->setTimezone('Asia/Bangkok')->toDateString()][$kind][] = $event['aggregate_id'];
                }
                if (in_array($entry['event_type'], ['sale.refunded', 'tax.document_issued', 'return.inspected'], true)) {
                    $original = DB::table('pos_import_sales')->where('pos_instance_id', $instanceId)->where('source_sale_id', $event['payload']['sale_id'])->first();
                    if ($original) {
                        $origin = json_decode($original->source_snapshot, true);
                        $days[Carbon::parse($origin['occurred_at'])->setTimezone('Asia/Bangkok')->toDateString()]['sales'][] = $origin['sale_id'];
                    }
                }
            }
            abort_unless($last === (int) $p['through_sequence'], 422, 'Checkpoint does not cover its through sequence.');
            $known = DB::table('pos_sync_inbox')->where('pos_instance_id', $instanceId)->where('source_sequence', '>', $p['after_sequence'])->where('source_sequence', '<=', $p['through_sequence'])->count();
            if ($known !== count($p['entries'])) {
                $issues[] = ['code' => 'source_manifest_count_mismatch'];
            }
            foreach ($days as $day => $types) {
                foreach ($types as $kind => $ids) {
                    foreach (array_chunk(array_values(array_unique($ids)), 100) as $chunk) {
                        $report = app(PosReconciliationService::class)->report($instance->org_id, $instanceId, $day, $day, [$kind => $chunk]);
                        foreach ($report['issues'] as $issue) {
                            $issues[] = $issue;
                        }
                    }
                }
            }
            $status = $issues === [] ? 'verified' : 'needs_review';
            DB::table('pos_source_checkpoints')->updateOrInsert(['pos_instance_id' => $instanceId, 'source_checkpoint_id' => $p['checkpoint_id']], ['id' => $existing?->id ?? (string) Str::uuid(), 'after_sequence' => $p['after_sequence'], 'through_sequence' => $p['through_sequence'], 'recovery_generation' => $p['recovery_generation'], 'digest' => $p['digest'], 'snapshot' => PosCanonicalJson::encode($body), 'status' => $status, 'issues' => json_encode($issues), 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]);
            if ($status === 'verified') {
                DB::table('pos_instances')->where('id', $instanceId)->update(['checkpoint_cursor' => max($instance->checkpoint_cursor, $p['through_sequence'])]);
            }

            return ['status' => $status, 'issues' => $issues, 'checkpoint_id' => $p['checkpoint_id'], 'digest' => $p['digest'], 'through_sequence' => $p['through_sequence'], 'high_watermark' => $p['high_watermark']];
        });
    }

    private function auditEvent(object $instance, array $event, array &$issues): void
    {
        $p = $event['payload'];
        $table = match ($event['event_type']) {
            'sale.completed' => 'pos_import_sales', 'sale.refunded' => 'pos_import_refunds', 'cash.moved' => 'pos_import_cash_movements', 'kitchen.consumed' => 'pos_import_kitchen_consumptions', 'kitchen.wasted' => 'pos_import_kitchen_waste', 'return.inspected' => 'pos_import_return_inspections', 'tax.document_issued' => 'pos_import_tax_documents', default => null
        };
        $key = match ($event['event_type']) {
            'sale.completed' => 'source_sale_id', 'sale.refunded' => 'source_refund_id', 'cash.moved' => 'source_cash_movement_id', 'kitchen.consumed' => 'source_line_id', 'kitchen.wasted' => 'source_waste_id', 'return.inspected' => 'source_inspection_id', 'tax.document_issued' => 'source_document_id', default => null
        };
        if (! $table) {
            $issues[] = ['code' => 'unrecognized_event_type', 'event_id' => $event['event_id']];

            return;
        }
        $source = DB::table($table)->where('pos_instance_id', $instance->id)->where($key, $event['aggregate_id'])->first();
        if (! $source || ! hash_equals($source->payload_hash, $event['payload_hash'])) {
            $issues[] = ['code' => 'import_source_mismatch', 'event_id' => $event['event_id']];

            return;
        }
        if ($event['event_type'] === 'tax.document_issued') {
            $sale = DB::table('pos_import_sales')->where('id', $source->sale_id)->where('pos_instance_id', $instance->id)->first();
            if (! $sale || $source->document_no !== $p['document_no'] || $source->kind !== $p['kind'] || $source->replaces_source_id !== $p['replaces_document_id'] || ! hash_equals($source->payload_hash, hash('sha256', PosCanonicalJson::encode(json_decode($source->source_snapshot, true))))) {
                $issues[] = ['code' => 'tax_document_evidence_mismatch', 'event_id' => $event['event_id']];
            }
        }
        if ($event['event_type'] === 'kitchen.consumed') {
            $sum = '0.00';
            foreach ($p['ingredients'] as $ingredient) {
                foreach ($ingredient['allocations'] as $allocation) {
                    $movement = DB::table('stock_movements')->where('org_id', $instance->org_id)->where('source_key', 'pos-kitchen:'.$instance->id.':'.$p['consumption_id'].':'.$ingredient['product_id'].':'.$allocation['grant_id'])->first();
                    if (! $movement || $movement->product_id !== $ingredient['product_id'] || $movement->warehouse_id !== $instance->warehouse_id || $movement->inventory_lot_id !== ($allocation['lot_id'] ?? null) || bccomp((string) $movement->quantity, bcmul($allocation['quantity'], '-1', 4), 4) !== 0) {
                        $issues[] = ['code' => 'kitchen_stock_mismatch', 'event_id' => $event['event_id']];
                    }
                    if ($movement) {
                        $sum = bcsub($sum, (string) $movement->base_total_cost, 2);
                    }
                }
            }
            if ($source->inventory_status !== 'posted' || $source->base_cost === null || bccomp($sum, (string) $source->base_cost, 2) !== 0) {
                $issues[] = ['code' => 'kitchen_cost_mismatch', 'event_id' => $event['event_id']];
            }
            $this->journal($instance->org_id, 'pos_kitchen_consumption', $source->id, 'inventory', $sum, $issues);
        }
        if ($event['event_type'] === 'kitchen.wasted') {
            $cost = DB::table('pos_import_kitchen_consumptions')->where('id', $source->consumption_id)->value('base_cost');
            if ($cost === null) {
                $issues[] = ['code' => 'waste_cost_unavailable', 'event_id' => $event['event_id']];
            } else {
                $this->journal($instance->org_id, 'pos_kitchen_waste', $source->id, 'reclassified', (string) $cost, $issues);
            }
        }
        if ($event['event_type'] === 'return.inspected' && $source->decision === 'restock') {
            $movement = DB::table('stock_movements')->where('org_id', $instance->org_id)->where('source_key', 'pos-inspection:'.$instance->id.':'.$event['aggregate_id'])->first();
            if (! $movement || $movement->product_id !== $p['product_id'] || $movement->warehouse_id !== $instance->warehouse_id || $movement->inventory_lot_id !== $p['lot_id'] || bccomp((string) $movement->quantity, (string) $source->base_quantity, 4) !== 0 || bccomp((string) $movement->base_total_cost, (string) $source->base_cost, 2) !== 0) {
                $issues[] = ['code' => 'inspection_stock_mismatch', 'event_id' => $event['event_id']];
            }
            $this->journal($instance->org_id, 'pos_return_inspection', $source->id, 'restocked', (string) $source->base_cost, $issues);
        }
    }

    private function journal(string $org, string $type, string $id, string $posting, string $amount, array &$issues): void
    {
        if (bccomp($amount, '0', 2) <= 0) {
            return;
        }
        $entry = DB::table('journal_entries')->where('org_id', $org)->where('source_type', $type)->where('source_id', $id)->where('posting_event', $posting)->where('status', 'posted')->first();
        if (! $entry) {
            $issues[] = ['code' => 'journal_missing_or_reversed', 'source_id' => $id];

            return;
        }
        $lines = DB::table('journal_lines')->where('journal_entry_id', $entry->id)->where('org_id', $org)->get();
        $debit = $lines->reduce(fn ($sum, $l) => bcadd($sum, (string) $l->debit, 2), '0.00');
        $credit = $lines->reduce(fn ($sum, $l) => bcadd($sum, (string) $l->credit, 2), '0.00');
        if (bccomp($debit, $amount, 2) !== 0 || bccomp($credit, $amount, 2) !== 0 || $lines->count() < 2) {
            $issues[] = ['code' => 'journal_amount_mismatch', 'source_id' => $id];
        }
    }
}
