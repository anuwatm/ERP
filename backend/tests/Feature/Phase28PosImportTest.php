<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BillOfMaterials;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\InventoryLot;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ChartOfAccountProvisioner;
use App\Services\InventoryMovementService;
use App\Services\Pos\PosCheckpointService;
use App\Services\Pos\PosImportService;
use App\Services\Pos\PosQuotaRecoveryService;
use App\Services\Pos\PosRecipeSnapshotService;
use App\Services\Pos\PosReconciliationService;
use App\Services\Pos\PosTransport;
use App\Support\PosCanonicalJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase28PosImportTest extends TestCase
{
    use RefreshDatabase;

    private string $instance;

    private string $pem = '';

    private array $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $user = User::factory()->create();
        $branch = Branch::create(['org_id' => $user->org_id, 'code' => 'POS', 'name' => 'POS', 'status' => 'active']);
        $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'POS', 'name' => 'POS']);
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'POS', 'company_name' => 'Walk-in', 'owner_id' => $user->id]);
        $product = Product::create(['org_id' => $user->org_id, 'sku' => 'POS', 'name' => 'Product', 'type' => 'product', 'unit' => 'pcs', 'price' => '10.00', 'is_active' => true]);
        StockMovement::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'adjustment_in', 'movement_date' => now()->toDateString(), 'quantity' => '10.0000', 'base_unit_cost' => '3.00', 'base_total_cost' => '30.00']);
        app(ChartOfAccountProvisioner::class)->ensure($user->org_id);
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $path = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (file_exists($path)) {
            $options['config'] = $path;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $this->pem, null, $options);
        $this->instance = (string) Str::uuid();
        $terminal = (string) Str::uuid();
        $grant = (string) Str::uuid();
        DB::table('pos_instances')->insert(['id' => $this->instance, 'org_id' => $user->org_id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id, 'actor_id' => $user->id, 'name' => 'Pilot', 'active' => true, 'terminal_ids' => json_encode([$terminal]), 'signing_keys' => json_encode(['k1' => ['active' => true, 'public_key' => openssl_pkey_get_details($key)['key']]]), 'account_codes' => json_encode(['drawer' => '1100', 'receivable' => '1120', 'revenue' => '4100', 'vat' => '2130', 'inventory' => '1140', 'cogs' => '5100'])]);
        // 5100 is an explicit per-org mapping in this fixture, never a runtime fallback.
        ChartOfAccount::firstOrCreate(['org_id' => $user->org_id, 'code' => '5100'], ['name' => 'COGS', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_postable' => true, 'status' => 'active']);
        DB::table('pos_stock_entitlements')->insert(['id' => $grant, 'org_id' => $user->org_id, 'pos_instance_id' => $this->instance, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'epoch' => 1, 'granted_quantity' => '6.0000', 'consumed_quantity' => '0.0000', 'status' => 'active']);
        $catalog = ['branch_id' => $branch->id, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $product->id, 'name' => 'Product', 'unit' => 'pcs', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]]];
        DB::table('pos_catalog_publications')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $this->instance, 'version' => 'v1', 'payload' => PosCanonicalJson::encode($catalog), 'payload_hash' => hash('sha256', PosCanonicalJson::encode($catalog)), 'valid_until' => now()->addDay(), 'created_at' => now()->subMinute(), 'updated_at' => now()]);
        $sale = (string) Str::uuid();
        $occurred = now()->utc()->toIso8601String();
        $payload = ['sale_id' => $sale, 'branch_id' => $branch->id, 'terminal_id' => $terminal, 'shift_id' => (string) Str::uuid(), 'staff_id' => (string) Str::uuid(), 'receipt_no' => 'B1-T1-0001', 'catalog_version' => 'v1', 'currency' => $user->organization->currency, 'subtotal' => '20.00', 'tax_amount' => '1.40', 'total' => '21.40', 'cash_tendered' => '30.00', 'change' => '8.60', 'occurred_at' => $occurred, 'business_timezone' => 'Asia/Bangkok', 'lines' => [['line_id' => (string) Str::uuid(), 'product_id' => $product->id, 'name' => 'Product', 'unit' => 'pcs', 'quantity' => '2.0000', 'unit_price' => '10.00', 'tax_rate' => '7.00', 'subtotal' => '20.00', 'tax_amount' => '1.40', 'total' => '21.40', 'allocations' => [['grant_id' => $grant, 'epoch' => 1, 'lot_id' => null, 'quantity' => '2.0000']]]]];
        $this->event = ['domain' => 'company-os.pos-event.v1', 'schema_version' => 1, 'instance_id' => $this->instance, 'event_id' => (string) Str::uuid(), 'aggregate_id' => $sale, 'aggregate_version' => 1, 'source_sequence' => 1, 'event_type' => 'sale.completed', 'occurred_at' => $occurred, 'key_id' => 'k1', 'payload_hash' => hash('sha256', PosCanonicalJson::encode($payload)), 'payload' => $payload];
        $this->sign();
    }

    public function test_import_and_new_event_for_same_sale_post_stock_payment_and_gl_once(): void
    {
        $s = app(PosImportService::class);
        $s->receive($this->instance, [$this->event], 1);
        $this->assertSame('accepted', $s->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame('accepted', $s->process($this->instance, $this->event['event_id'])['status']);
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 2;
        $this->sign();
        $s->receive($this->instance, [$this->event], 2);
        $this->assertSame('accepted', $s->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(3, JournalEntry::count());
        $this->assertSame(1, StockMovement::where('movement_type', 'pos_sale')->count());
        $this->assertSame(8.0, (float) StockMovement::sum('quantity'));
    }

    public function test_stock_shortage_keeps_financial_evidence_and_retry_finishes_inventory_only(): void
    {
        $s = app(PosImportService::class);
        StockMovement::query()->delete();
        $s->receive($this->instance, [$this->event], 1);
        $this->assertSame('inventory_pending', $s->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, JournalEntry::count());
        $i = DB::table('pos_instances')->first();
        $grant = DB::table('pos_stock_entitlements')->first();
        StockMovement::create(['org_id' => $i->org_id, 'product_id' => $grant->product_id, 'warehouse_id' => $i->warehouse_id, 'movement_type' => 'adjustment_in', 'movement_date' => now()->toDateString(), 'quantity' => '10.0000', 'base_unit_cost' => '3.00', 'base_total_cost' => '30.00']);
        $this->assertSame('accepted', $s->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(1, Payment::count());
        $this->assertSame(3, JournalEntry::count());
    }

    public function test_tampered_signature_and_forged_price_never_create_documents(): void
    {
        $this->event['payload']['total'] = '0.01';
        $s = app(PosImportService::class);
        $s->receive($this->instance, [$this->event], 1);
        $this->assertSame('needs_review', $s->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(0, Invoice::count());
    }

    public function test_same_event_changed_bytes_preserves_original_evidence(): void
    {
        $s = app(PosImportService::class);
        $s->receive($this->instance, [$this->event], 1);
        $original = DB::table('pos_sync_inbox')->value('received_bytes');
        $this->event['payload']['total'] = '0.01';
        $s->receive($this->instance, [$this->event], 1);
        $this->assertSame($original, DB::table('pos_sync_inbox')->value('received_bytes'));
        $this->assertDatabaseCount('pos_sync_conflicts', 1);
    }

    public function test_even_a_valid_signature_cannot_authorize_a_forged_catalog_price(): void
    {
        $this->event['payload']['lines'][0]['unit_price'] = '0.01';
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($this->event['payload']));
        $this->sign();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_malformed_event_does_not_block_following_valid_event(): void
    {
        $service = app(PosImportService::class);
        $service->receive($this->instance, [['malformed' => true], $this->event], 1);
        $this->assertDatabaseCount('pos_sync_conflicts', 1);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
    }

    public function test_delivery_order_for_imported_sale_does_not_deduct_again(): void
    {
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $result = $service->process($this->instance, $this->event['event_id']);
        $user = User::findOrFail(DB::table('pos_instances')->value('actor_id'));
        $this->permission($user, ['delivery_orders.create']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('delivery-orders.store'), ['invoice_id' => $result['invoice_id'], 'delivery_date' => now()->toDateString(), 'status' => 'delivered'])->assertRedirect();
        $this->assertSame(1, StockMovement::where('movement_type', 'pos_sale')->count());
        $this->assertSame(0, StockMovement::where('movement_type', 'deliver_to_customer')->count());
        $this->assertSame('20.00', Invoice::firstOrFail()->items()->firstOrFail()->line_total);
    }

    public function test_monitor_is_tenant_scoped_and_replay_requires_authorization(): void
    {
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $other = User::factory()->create();
        $this->permission($other, ['pos.view', 'pos.manage', 'inventory.adjust']);
        $row = DB::table('pos_sync_inbox')->first();
        $this->actingAsOrgUser($other)->get(route('pos.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Pos')->has('instances', 0)->has('events', 0));
        $this->actingAsOrgUser($other)->withSession(['auth.password_confirmed_at' => time()])->post(route('pos.replay', $row->id), ['reason' => 'Local test'])->assertNotFound();
        $this->assertSame('pending', DB::table('pos_sync_inbox')->value('status'));
    }

    public function test_lost_ack_retries_only_delivery_not_financial_or_stock_postings(): void
    {
        $event = $this->event;
        $transport = \Mockery::mock(PosTransport::class);
        $transport->shouldReceive('request')->once()->with($this->instance, 'events', ['cursor' => 0, 'limit' => 100])->andReturn(['events' => [$event], 'next_cursor' => 1]);
        $transport->shouldReceive('request')->once()->with($this->instance, 'result', \Mockery::type('array'), $event['event_id'])->andThrow(new \RuntimeException('Simulated lost local acknowledgement.'));
        $this->app->instance(PosTransport::class, $transport);
        $this->artisan('pos:sync', ['instance' => $this->instance])->assertExitCode(1);
        $this->assertNull(DB::table('pos_sync_inbox')->value('acknowledged_at'));
        $this->assertSame(1, Invoice::count());
        $this->assertNotNull(DB::table('pos_instances')->value('next_sync_at'));
        $this->artisan('pos:sync', ['instance' => $this->instance])->assertExitCode(1);

        $transport = \Mockery::mock(PosTransport::class);
        $transport->shouldReceive('request')->once()->with($this->instance, 'events', ['cursor' => 1, 'limit' => 100])->andReturn(['events' => [], 'next_cursor' => 1]);
        $transport->shouldReceive('request')->once()->with($this->instance, 'result', \Mockery::type('array'), $event['event_id'])->andReturn(['status' => 'recorded']);
        $this->app->instance(PosTransport::class, $transport);
        $this->artisan('pos:sync', ['instance' => $this->instance, '--force' => true])->assertExitCode(0);
        $this->assertNull(DB::table('pos_instances')->value('next_sync_at'));
        $this->assertNotNull(DB::table('pos_instances')->value('last_sync_at'));
        $this->assertNotNull(DB::table('pos_sync_inbox')->value('acknowledged_at'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(3, JournalEntry::count());
    }

    public function test_wrong_account_type_cannot_fall_back_to_default_drawer(): void
    {
        $mapping = json_decode(DB::table('pos_instances')->value('account_codes'), true);
        $mapping['vat'] = '1100';
        DB::table('pos_instances')->update(['account_codes' => json_encode($mapping)]);
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_converted_unit_requires_registered_pharmacist_and_posts_base_stock(): void
    {
        $catalog = DB::table('pos_catalog_publications')->first();
        $payload = json_decode($catalog->payload, true);
        $payload['items'][0]['pharmacy'] = ['requires_pharmacist' => true, 'base_step' => '1.0000', 'units' => [['unit' => 'strip', 'factor' => '2.0000', 'price' => '10.00', 'conversion_version' => 'unit-v1']]];
        DB::table('pos_catalog_publications')->update(['payload' => PosCanonicalJson::encode($payload), 'payload_hash' => hash('sha256', PosCanonicalJson::encode($payload))]);
        $line = &$this->event['payload']['lines'][0];
        $line['unit'] = 'strip';
        $line['base_quantity'] = '4.0000';
        $line['conversion_factor'] = '2.0000';
        $line['conversion_version'] = 'unit-v1';
        $line['pharmacist_id'] = $this->event['payload']['staff_id'];
        $line['allocations'][0]['quantity'] = '4.0000';
        unset($line);
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($this->event['payload']));
        $this->sign();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('invoices', 0);
        DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $this->instance, 'source_staff_id' => $this->event['payload']['staff_id'], 'role' => 'pharmacist', 'active' => true]);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame('-4.0000', StockMovement::where('movement_type', 'pos_sale')->firstOrFail()->quantity);
        $this->assertSame('strip', Invoice::firstOrFail()->items()->firstOrFail()->unit);
    }

    public function test_quota_reclaim_waits_for_imported_counters_and_is_idempotent(): void
    {
        config(['pos.local_quota_recovery_enabled' => true]);
        $user = User::findOrFail(DB::table('pos_instances')->value('actor_id'));
        $this->permission($user, ['pos.manage', 'inventory.adjust']);
        $grant = DB::table('pos_stock_entitlements')->first();
        $proof = ['grant_id' => $grant->id, 'epoch' => 1, 'status' => 'frozen', 'granted_quantity' => '6.0000', 'consumed_quantity' => '2.0000', 'reserved_quantity' => '0.0000', 'source_sequence' => 1];
        $transport = \Mockery::mock(PosTransport::class);
        $transport->shouldReceive('request')->times(3)->with($this->instance, 'freeze-grant', ['epoch' => 1], $grant->id)->andReturn($proof);
        $this->app->instance(PosTransport::class, $transport);
        $import = app(PosImportService::class);
        $import->receive($this->instance, [$this->event], 1);
        $recovery = app(PosQuotaRecoveryService::class);
        try {
            $recovery->close($user, $this->instance, $grant->id, 'Synthetic recovery');
            $this->fail('Unimported consumption must block reclaim.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame('active', DB::table('pos_stock_entitlements')->value('status'));
        $this->assertSame('accepted', $import->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame('4.0000', $recovery->close($user, $this->instance, $grant->id, 'Synthetic recovery'));
        $this->assertSame('4.0000', $recovery->close($user, $this->instance, $grant->id, 'Synthetic recovery retry'));
        $this->assertSame(1, AuditLog::where('action', 'pos.local_quota_reclaim')->count());
        $this->assertSame('8.0000', app(InventoryMovementService::class)->available($user->org_id, $grant->product_id, $grant->warehouse_id));
    }

    public function test_pharmacy_inspection_waits_for_refund_then_restores_original_cost_once_without_quota(): void
    {
        config(['pos.local_refunds_enabled' => true, 'pos.local_return_inspections_enabled' => true]);
        $instance = DB::table('pos_instances')->first();
        DB::table('pos_instances')->where('id', $instance->id)->update(['business_type' => 'pharmacy']);
        $source = $this->event['payload'];
        $line = $source['lines'][0];
        $lot = InventoryLot::create(['org_id' => $instance->org_id, 'product_id' => $line['product_id'], 'lot_no' => 'SYNTHETIC-RETURN', 'expires_at' => now()->addMonth()]);
        DB::table('pos_stock_entitlements')->update(['inventory_lot_id' => $lot->id]);
        StockMovement::query()->update(['inventory_lot_id' => $lot->id]);
        $this->event['payload']['lines'][0]['allocations'][0]['lot_id'] = $lot->id;
        $this->event['payload']['lines'][0]['conversion_factor'] = '1.0000';
        $this->event['payload']['lines'][0]['conversion_version'] = 'base';
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($this->event['payload']));
        $this->sign();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instance->id, 'source_staff_id' => $source['staff_id'], 'role' => 'manager', 'active' => true, 'pharmacist_qualified' => true]);
        $refundId = (string) Str::uuid();
        $inspectionId = (string) Str::uuid();
        $inspection = ['inspection_id' => $inspectionId, 'sale_id' => $source['sale_id'], 'refund_id' => $refundId, 'source_line_id' => $line['line_id'], 'grant_id' => $line['allocations'][0]['grant_id'], 'base_quantity' => '1.0000', 'decision' => 'restock', 'packaging_intact' => true, 'storage_verified' => true, 'lot_verified' => true, 'reason' => 'Synthetic intact return', 'branch_id' => $source['branch_id'], 'staff_id' => $source['staff_id'], 'product_id' => $line['product_id'], 'lot_id' => $lot->id, 'occurred_at' => $source['occurred_at'], 'business_timezone' => 'Asia/Bangkok'];
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 2, 'event_type' => 'return.inspected', 'aggregate_id' => $inspectionId, 'payload' => $inspection, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($inspection))]);
        $this->sign();
        $inspectionEvent = $this->event;
        $service->receive($this->instance, [$inspectionEvent], 2);
        $this->assertSame('dependency_pending', $service->process($this->instance, $inspectionEvent['event_id'])['status']);
        $refund = ['refund_id' => $refundId, 'sale_id' => $source['sale_id'], 'branch_id' => $source['branch_id'], 'terminal_id' => $source['terminal_id'], 'shift_id' => $source['shift_id'], 'staff_id' => $source['staff_id'], 'currency' => $source['currency'], 'reason' => 'customer_return', 'subtotal' => '20.00', 'tax_amount' => '1.40', 'total' => '21.40', 'occurred_at' => $source['occurred_at'], 'business_timezone' => 'Asia/Bangkok', 'lines' => [['source_line_id' => $line['line_id'], 'product_id' => $line['product_id'], 'quantity' => '2.0000', 'returned_quantity' => '2.0000', 'subtotal' => '20.00', 'tax_amount' => '1.40', 'total' => '21.40', 'return_status' => 'quarantined']]];
        $this->event = array_merge($inspectionEvent, ['event_id' => (string) Str::uuid(), 'source_sequence' => 3, 'event_type' => 'sale.refunded', 'aggregate_id' => $refundId, 'payload' => $refund, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($refund))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 3);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->mock(PosTransport::class, function ($mock) {
            $mock->shouldReceive('request')->with($this->instance, 'events', \Mockery::type('array'))->andReturn(['events' => [], 'next_cursor' => 3]);
            $mock->shouldReceive('request')->with($this->instance, 'result', \Mockery::type('array'), \Mockery::type('string'))->andReturn(['status' => 'recorded']);
        });
        $this->artisan('pos:sync', ['instance' => $this->instance])->assertExitCode(0);
        $this->assertSame('accepted', DB::table('pos_sync_inbox')->where('event_id', $inspectionEvent['event_id'])->value('status'));
        $this->event = $inspectionEvent;
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 4;
        $this->sign();
        $service->receive($this->instance, [$this->event], 4);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('pos_import_return_inspections', 1);
        $this->assertSame(1, StockMovement::where('movement_type', 'pos_return')->count());
        $this->assertSame('3.00', StockMovement::where('movement_type', 'pos_return')->firstOrFail()->base_total_cost);
        $this->assertSame(2.0, (float) DB::table('pos_stock_entitlements')->value('consumed_quantity'));
        $this->assertSame(6, JournalEntry::count());
        $inspection['inspection_id'] = (string) Str::uuid();
        $inspection['decision'] = 'destroy';
        $this->event = array_merge($inspectionEvent, ['event_id' => (string) Str::uuid(), 'source_sequence' => 5, 'aggregate_id' => $inspection['inspection_id'], 'payload' => $inspection, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($inspection))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 5);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(1, StockMovement::where('movement_type', 'pos_return')->count());
        $this->assertSame(6, JournalEntry::count());
        $inspection['inspection_id'] = (string) Str::uuid();
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 6, 'aggregate_id' => $inspection['inspection_id'], 'payload' => $inspection, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($inspection))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 6);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('pos_import_return_inspections', 2);
    }

    public function test_signed_checkpoint_checks_source_and_ledger_then_fences_restored_watermark(): void
    {
        config(['pos.local_checkpoints_enabled' => true]);
        DB::table('pos_instances')->where('id', $this->instance)->update(['recovery_generation' => 'synthetic-generation-one']);
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $entries = [['sequence' => 1, 'event_id' => $this->event['event_id'], 'event_type' => 'sale.completed', 'envelope_hash' => hash('sha256', PosCanonicalJson::encode($this->event))]];
        $body = ['domain' => 'company-os.pos-checkpoint.v1', 'schema_version' => 1, 'checkpoint_id' => (string) Str::uuid(), 'instance_id' => $this->instance, 'key_id' => 'k1', 'branch_id' => $this->event['payload']['branch_id'], 'recovery_generation' => 'synthetic-generation-one', 'after_sequence' => 0, 'through_sequence' => 1, 'high_watermark' => 1, 'entries' => $entries, 'digest' => hash('sha256', PosCanonicalJson::encode($entries)), 'occurred_at' => now()->toIso8601String()];
        $sign = function (array $p): array {
            unset($p['signature']);
            openssl_sign(PosCanonicalJson::encode($p), $signature, $this->pem, OPENSSL_ALGO_SHA256);

            return $p + ['signature' => base64_encode($signature)];
        };
        $checker = app(PosCheckpointService::class);
        $verified = $checker->verify($this->instance, $sign($body));
        $this->assertSame('verified', $verified['status']);
        $this->assertSame(1, (int) DB::table('pos_instances')->value('checkpoint_cursor'));
        StockMovement::where('movement_type', 'pos_sale')->delete();
        $this->assertSame('needs_review', $checker->verify($this->instance, $sign($body))['status']);
        $body['checkpoint_id'] = (string) Str::uuid();
        $body['through_sequence'] = 0;
        $body['high_watermark'] = 0;
        $body['entries'] = [];
        $body['digest'] = hash('sha256', PosCanonicalJson::encode([]));
        $this->assertSame('needs_review', $checker->verify($this->instance, $sign($body))['status']);
        $this->assertNotNull(DB::table('pos_instances')->value('recovery_fenced_at'));
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 2;
        $this->sign();
        $service->receive($this->instance, [$this->event], 2);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(1, Invoice::count());
    }

    public function test_authorized_recovery_replays_pending_evidence_from_a_complete_paged_chain(): void
    {
        config(['pos.local_checkpoints_enabled' => true]);
        $instance = DB::table('pos_instances')->where('id', $this->instance)->firstOrFail();
        $actor = User::findOrFail($instance->actor_id);
        $this->permission($actor, ['pos.manage', 'inventory.adjust']);
        DB::table('pos_instances')->where('id', $this->instance)->update(['recovery_generation' => 'synthetic-generation-one', 'recovery_fenced_at' => now()->subSecond()]);
        $service = app(PosImportService::class);
        $events = [$this->event];
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 2;
        $this->event['aggregate_id'] = $this->event['payload']['sale_id'] = (string) Str::uuid();
        $this->event['payload']['receipt_no'] = 'B1-T1-0002';
        $this->event['payload']['lines'][0]['line_id'] = (string) Str::uuid();
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($this->event['payload']));
        $this->sign();
        $events[] = $this->event;
        $service->receive($this->instance, $events, 2);
        $this->assertSame('needs_review', $service->process($this->instance, $events[0]['event_id'])['status']);
        $pages = [];
        foreach ($events as $index => $event) {
            $entries = [['sequence' => $index + 1, 'event_id' => $event['event_id'], 'event_type' => 'sale.completed', 'envelope_hash' => hash('sha256', PosCanonicalJson::encode($event))]];
            $body = ['domain' => 'company-os.pos-checkpoint.v1', 'schema_version' => 1, 'checkpoint_id' => (string) Str::uuid(), 'instance_id' => $this->instance, 'key_id' => 'k1', 'branch_id' => $event['payload']['branch_id'], 'recovery_generation' => 'synthetic-generation-one', 'after_sequence' => $index, 'through_sequence' => $index + 1, 'high_watermark' => 2, 'entries' => $entries, 'digest' => hash('sha256', PosCanonicalJson::encode($entries)), 'occurred_at' => now()->toIso8601String()];
            openssl_sign(PosCanonicalJson::encode($body), $signature, $this->pem, OPENSSL_ALGO_SHA256);
            $pages[] = $body + ['signature' => base64_encode($signature)];
        }
        // Last page alone cannot prove the complete source history.
        app(PosCheckpointService::class)->verify($this->instance, $pages[1]);
        $this->actingAsOrgUser($actor)->withSession(['auth.password_confirmed_at' => time()]);
        $this->postJson(route('pos.recover', $this->instance), ['checkpoint_id' => $pages[1]['checkpoint_id'], 'reason' => 'Synthetic restore'])->assertConflict();
        $this->assertSame(0, Invoice::count());
        app(PosCheckpointService::class)->verify($this->instance, $pages[0]);
        $this->post(route('pos.recover', $this->instance), ['checkpoint_id' => $pages[1]['checkpoint_id'], 'reason' => 'Synthetic restore'])->assertRedirect();
        $this->assertNull(DB::table('pos_instances')->where('id', $this->instance)->value('recovery_fenced_at'));
        $this->assertSame(2, Invoice::count());
        $this->assertSame(2, Payment::count());
        $this->assertSame(2, StockMovement::where('movement_type', 'pos_sale')->count());
        $this->assertSame(2, DB::table('pos_source_checkpoints')->where('status', 'verified')->count());
        $this->postJson(route('pos.recover', $this->instance), ['checkpoint_id' => $pages[1]['checkpoint_id'], 'reason' => 'Duplicate recovery'])->assertConflict();
        $this->assertSame(2, Invoice::count());
    }

    public function test_abb_full_tax_import_is_correlated_without_new_financial_or_inventory_postings(): void
    {
        config(['pos.local_tax_documents_enabled' => true]);
        DB::table('pos_instances')->where('id', $this->instance)->update(['branch_code' => 'B1', 'terminal_codes' => json_encode([$this->event['payload']['terminal_id'] => 'T1'])]);
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $source = json_decode(DB::table('pos_import_sales')->value('source_snapshot'), true);
        $issuer = ['version' => 'issuer-v1', 'branch_id' => $source['branch_id'], 'seller_name' => 'Synthetic seller', 'seller_tax_id' => '0000000000000', 'seller_address' => 'Synthetic address', 'seller_branch_no' => '00000'];
        DB::table('pos_tax_profile_publications')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $this->instance, 'version' => 'issuer-v1', 'snapshot' => PosCanonicalJson::encode($issuer), 'payload_hash' => hash('sha256', PosCanonicalJson::encode($issuer)), 'created_at' => now()->subMinute(), 'updated_at' => now()]);
        $id = (string) Str::uuid();
        $day = now('Asia/Bangkok')->format('Ymd');
        $p = ['document_id' => $id, 'document_no' => 'ABB-B1-T1-'.$day.'-00000001', 'kind' => 'ABB', 'sale_id' => $source['sale_id'], 'receipt_no' => $source['receipt_no'], 'branch_id' => $source['branch_id'], 'terminal_id' => $source['terminal_id'], 'staff_id' => $source['staff_id'], 'replaces_document_id' => null, 'replaces_document_no' => null, 'issuer' => $issuer, 'buyer' => null, 'currency' => 'THB', 'subtotal' => $source['subtotal'], 'tax_amount' => $source['tax_amount'], 'total' => $source['total'], 'lines' => $source['lines'], 'occurred_at' => now()->toIso8601String(), 'business_timezone' => 'Asia/Bangkok'];
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 2, 'event_type' => 'tax.document_issued', 'aggregate_id' => $id, 'occurred_at' => $p['occurred_at'], 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 2);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $p['document_id'] = (string) Str::uuid();
        $p['document_no'] = 'FULL-B1-T1-'.$day.'-00000001';
        $p['kind'] = 'FULL';
        $p['replaces_document_id'] = $id;
        $p['replaces_document_no'] = 'ABB-B1-T1-'.$day.'-00000001';
        $p['buyer'] = ['name' => 'Synthetic buyer', 'tax_id' => '0000000000000', 'branch_no' => '00000', 'address' => 'Synthetic address'];
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 3, 'aggregate_id' => $p['document_id'], 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 3);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 4;
        $this->sign();
        $service->receive($this->instance, [$this->event], 4);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('pos_import_tax_documents', 2);
        $this->assertSame(1, DB::table('pos_import_tax_documents')->whereNotNull('current_slot')->count());
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(3, JournalEntry::count());
        $this->assertSame(1, StockMovement::where('movement_type', 'pos_sale')->count());
        $p['document_id'] = (string) Str::uuid();
        $p['total'] = '999.00';
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 5, 'aggregate_id' => $p['document_id'], 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 5);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('pos_import_tax_documents', 2);
    }

    public function test_prepared_sale_after_catalog_expiry_uses_verified_kitchen_snapshot(): void
    {
        $f = $this->kitchenFixture();
        DB::table('pos_catalog_publications')->where('pos_instance_id', $this->instance)->update(['valid_until' => $f['kitchen']['occurred_at']]);
        $this->event = $f['sale'];
        $this->event['occurred_at'] = $this->event['payload']['occurred_at'] = now()->addMinute()->toIso8601String();
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($this->event['payload']));
        $this->sign();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$f['kitchen'], $this->event], 2);
        $this->assertSame('accepted', $service->process($this->instance, $f['kitchen']['event_id'])['status']);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, StockMovement::where('movement_type', 'recipe_consumption')->count());
    }

    public function test_instance_admin_register_activate_authority_and_key_revocation_keep_scope(): void
    {
        $instance = DB::table('pos_instances')->where('id', $this->instance)->firstOrFail();
        $actor = User::findOrFail($instance->actor_id);
        $this->permission($actor, ['pos.manage', 'inventory.adjust']);
        $public = openssl_pkey_get_details(openssl_pkey_get_private($this->pem))['key'];
        $next = (string) Str::uuid();
        $terminal = (string) Str::uuid();
        $input = ['id' => $next, 'name' => 'Synthetic registered POS', 'branch_id' => $instance->branch_id, 'branch_code' => 'B1', 'warehouse_id' => $instance->warehouse_id, 'customer_id' => $instance->customer_id, 'actor_id' => $actor->id, 'business_type' => 'retail', 'terminals' => [['id' => $terminal, 'code' => 'T1']], 'key_id' => 'new-key', 'public_key' => $public, 'recovery_generation' => 'synthetic-generation-next', 'account_codes' => json_decode($instance->account_codes, true)];
        $this->actingAsOrgUser($actor)->withSession(['auth.password_confirmed_at' => time()])->post(route('pos.register'), $input)->assertRedirect();
        $this->assertDatabaseHas('pos_instances', ['id' => $next, 'active' => false]);
        $this->postJson(route('pos.activate', $next))->assertConflict();
        $this->post(route('pos.pause', $this->instance), ['reason' => 'Synthetic replacement'])->assertRedirect();
        $this->post(route('pos.activate', $next))->assertRedirect();
        $this->assertDatabaseHas('pos_instances', ['id' => $next, 'active' => true]);
        $sourceStaff = (string) Str::uuid();
        $this->post(route('pos.authority', $next), ['source_staff_id' => $sourceStaff, 'role' => 'manager', 'active' => true, 'pharmacist_qualified' => true])->assertRedirect();
        $this->assertDatabaseHas('pos_staff_authorities', ['pos_instance_id' => $next, 'source_staff_id' => $sourceStaff, 'pharmacist_qualified' => true]);
        $this->post(route('pos.signing-key', $next), ['key_id' => 'new-key', 'operation' => 'revoke', 'reason' => 'Synthetic revocation'])->assertRedirect();
        $this->assertDatabaseHas('pos_instances', ['id' => $next, 'active' => false]);
        $this->postJson(route('pos.activate', $next))->assertUnprocessable();
        $this->assertSame(6.0, (float) DB::table('pos_stock_entitlements')->where('pos_instance_id', $this->instance)->value('granted_quantity'));
        $other = User::factory()->create();
        $this->permission($other, ['pos.manage', 'inventory.adjust']);
        $this->actingAs($other)->postJson(route('pos.authority', $next), ['source_staff_id' => $sourceStaff, 'role' => 'manager', 'active' => true, 'pharmacist_qualified' => true])->assertNotFound();
    }

    private function permission(User $user, array $codes): void
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'pos-test', 'name' => 'POS Test']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'pos', 'action' => 'manage']);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);
    }

    public function test_partial_refunds_import_once_without_restock_or_double_tax_subtraction(): void
    {
        config(['pos.local_refunds_enabled' => true]);
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $source = $this->event['payload'];
        // Later provisioning must not redirect reversals to a different revenue account.
        $mapping = json_decode(DB::table('pos_instances')->value('account_codes'), true);
        ChartOfAccount::create(['org_id' => DB::table('pos_instances')->value('org_id'), 'code' => '4199', 'name' => 'Later revenue', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'is_postable' => true, 'status' => 'active']);
        $mapping['revenue'] = '4199';
        DB::table('pos_instances')->update(['account_codes' => json_encode($mapping)]);
        DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $this->instance, 'source_staff_id' => $source['staff_id'], 'role' => 'manager', 'active' => true]);
        $refund = ['refund_id' => (string) Str::uuid(), 'sale_id' => $source['sale_id'], 'branch_id' => $source['branch_id'], 'terminal_id' => $source['terminal_id'], 'shift_id' => $source['shift_id'], 'staff_id' => $source['staff_id'], 'currency' => $source['currency'], 'reason' => 'customer_return', 'subtotal' => '10.00', 'tax_amount' => '0.70', 'total' => '10.70', 'occurred_at' => $source['occurred_at'], 'business_timezone' => 'Asia/Bangkok', 'lines' => [['source_line_id' => $source['lines'][0]['line_id'], 'product_id' => $source['lines'][0]['product_id'], 'quantity' => '1.0000', 'returned_quantity' => '1.0000', 'subtotal' => '10.00', 'tax_amount' => '0.70', 'total' => '10.70', 'return_status' => 'quarantined']]];
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 2, 'event_type' => 'sale.refunded', 'aggregate_id' => $refund['refund_id'], 'payload' => $refund, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($refund))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 2);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 3;
        $this->sign();
        $service->receive($this->instance, [$this->event], 3);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('pos_import_refunds', 1);
        $this->assertDatabaseCount('credit_debit_notes', 1);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(5, JournalEntry::count());
        $this->assertSame(0, JournalLine::whereHas('account', fn ($query) => $query->where('code', '4199'))->count());
        $invoice = Invoice::first();
        $this->assertSame('10.70', $invoice->total);
        $this->assertSame('10.70', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame(8.0, (float) StockMovement::sum('quantity'));
        $this->assertSame(2.0, (float) DB::table('pos_stock_entitlements')->sum('consumed_quantity'));
        $user = User::find(DB::table('pos_instances')->value('actor_id'));
        $this->permission($user, ['tax_reports.view', 'payments.reverse', 'invoices.void', 'invoices.view']);
        $this->actingAs($user)->get('/tax-reports')->assertOk()->assertInertia(fn ($page) => $page->has('salesRows', 2)->where('salesRows.0.total', '21.40')->where('salesRows.1.total', '-10.70'));
        $this->withSession(['auth.password_confirmed_at' => time()])->postJson('/payments/'.DB::table('pos_import_sales')->value('payment_id').'/reverse')->assertUnprocessable();
        $this->get('/invoices/'.$invoice->id.'/print')->assertOk()->assertViewHas('document', fn ($document) => $document['title'] === 'POS Ledger Copy' && $document['total'] === '21.40');
        $refund['refund_id'] = (string) Str::uuid();
        $refund['lines'][0]['quantity'] = '2.0000';
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 4, 'aggregate_id' => $refund['refund_id'], 'payload' => $refund, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($refund))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 4);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('pos_import_refunds', 1);
        $refund['refund_id'] = (string) Str::uuid();
        $refund['lines'][0]['quantity'] = '1.0000';
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 5, 'aggregate_id' => $refund['refund_id'], 'payload' => $refund, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($refund))]);
        $this->sign();
        $period = AccountingPeriod::where('org_id', $user->org_id)->firstOrFail();
        $period->update(['status' => 'closed']);
        $service->receive($this->instance, [$this->event], 5);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('credit_debit_notes', 1);
        $this->assertDatabaseCount('payments', 2);
        $period->update(['status' => 'open']);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame('0.00', $invoice->fresh()->total);
        $this->assertDatabaseCount('pos_import_refunds', 2);
        $this->patchJson('/invoices/'.$invoice->id.'/void')->assertUnprocessable();
        $date = $invoice->issue_date->toDateString();
        $report = app(PosReconciliationService::class)->report($user->org_id, $this->instance, $date, $date);
        $this->assertSame([], $report['issues']);
        $this->assertSame('21.40', $report['gross_sales']);
        $this->assertSame('21.40', $report['refunds']);
        $this->assertSame('0.00', $report['source_cash_flow']);
    }

    public function test_refund_before_sale_waits_and_requires_registered_manager(): void
    {
        config(['pos.local_refunds_enabled' => true]);
        $saleEvent = $this->event;
        $source = $saleEvent['payload'];
        $refund = ['refund_id' => (string) Str::uuid(), 'sale_id' => $source['sale_id'], 'branch_id' => $source['branch_id'], 'terminal_id' => $source['terminal_id'], 'shift_id' => $source['shift_id'], 'staff_id' => $source['staff_id'], 'currency' => $source['currency'], 'reason' => 'sale_error', 'subtotal' => '20.00', 'tax_amount' => '1.40', 'total' => '21.40', 'occurred_at' => $source['occurred_at'], 'business_timezone' => 'Asia/Bangkok', 'lines' => [['source_line_id' => $source['lines'][0]['line_id'], 'product_id' => $source['lines'][0]['product_id'], 'quantity' => '2.0000', 'returned_quantity' => '0.0000', 'subtotal' => '20.00', 'tax_amount' => '1.40', 'total' => '21.40', 'return_status' => 'not_returned']]];
        $this->event = array_merge($saleEvent, ['event_id' => (string) Str::uuid(), 'source_sequence' => 2, 'event_type' => 'sale.refunded', 'aggregate_id' => $refund['refund_id'], 'payload' => $refund, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($refund))]);
        $this->sign();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 2);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $this->instance, 'source_staff_id' => $source['staff_id'], 'role' => 'manager', 'active' => true]);
        $this->assertSame('dependency_pending', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
        $service->receive($this->instance, [$saleEvent], 2);
        $this->assertSame('accepted', $service->process($this->instance, $saleEvent['event_id'])['status']);
        $this->mock(PosTransport::class, function ($mock) {
            $mock->shouldReceive('request')->with($this->instance, 'events', \Mockery::type('array'))->once()->andReturn(['events' => [], 'next_cursor' => 2]);
            $mock->shouldReceive('request')->with($this->instance, 'result', \Mockery::type('array'), \Mockery::type('string'))->andReturn(['status' => 'recorded']);
        });
        $this->artisan('pos:sync', ['instance' => $this->instance])->assertExitCode(0);
        $this->assertSame('accepted', DB::table('pos_sync_inbox')->where('event_id', $this->event['event_id'])->value('status'));
        $this->assertSame('0.00', Invoice::first()->total);
        $this->assertSame('0.00', Invoice::first()->paid_amount);
        $this->assertDatabaseCount('pos_import_refunds', 1);
    }

    private function sign(): void
    {
        unset($this->event['signature']);
        openssl_sign(PosCanonicalJson::encode($this->event), $signature, $this->pem, OPENSSL_ALGO_SHA256);
        $this->event['signature'] = base64_encode($signature);
    }

    public function test_cash_movement_requires_explicit_clearing_and_never_creates_revenue_or_stock(): void
    {
        config(['pos.local_cash_movements_enabled' => true]);
        $source = $this->event['payload'];
        DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $this->instance, 'source_staff_id' => $source['staff_id'], 'role' => 'manager', 'active' => true]);
        $p = ['cash_movement_id' => (string) Str::uuid(), 'branch_id' => $source['branch_id'], 'terminal_id' => $source['terminal_id'], 'shift_id' => $source['shift_id'], 'staff_id' => $source['staff_id'], 'currency' => $source['currency'], 'direction' => 'in', 'amount' => '25.00', 'reason' => 'float_added', 'reference' => 'SYNTHETIC-FLOAT', 'occurred_at' => $source['occurred_at'], 'business_timezone' => 'Asia/Bangkok'];
        $this->event = array_merge($this->event, ['event_type' => 'cash.moved', 'aggregate_id' => $p['cash_movement_id'], 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(0, JournalEntry::count());
        $org = DB::table('pos_instances')->value('org_id');
        ChartOfAccount::create(['org_id' => $org, 'code' => '1199', 'name' => 'Synthetic cash transfer clearing', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_postable' => true, 'status' => 'active']);
        $mapping = json_decode(DB::table('pos_instances')->value('account_codes'), true);
        $mapping['cash_clearing'] = '1199';
        DB::table('pos_instances')->update(['account_codes' => json_encode($mapping)]);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 2;
        $this->sign();
        $service->receive($this->instance, [$this->event], 2);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('pos_import_cash_movements', 1);
        $p['cash_movement_id'] = (string) Str::uuid();
        $p['amount'] = '5.00';
        $p['direction'] = 'out';
        $p['reason'] = 'cash_drop';
        $p['reference'] = 'SYNTHETIC-DROP';
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 3, 'aggregate_id' => $p['cash_movement_id'], 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 3);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertDatabaseCount('pos_import_cash_movements', 2);
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, JournalLine::whereHas('account', fn ($q) => $q->where('account_type', '!=', 'asset'))->count());
        $this->assertSame(10.0, (float) StockMovement::sum('quantity'));
        $this->assertSame(0.0, (float) DB::table('pos_stock_entitlements')->sum('consumed_quantity'));
        $date = Carbon::parse($source['occurred_at'])->setTimezone('Asia/Bangkok')->toDateString();
        $report = app(PosReconciliationService::class)->report($org, $this->instance, $date, $date);
        $this->assertSame([], $report['issues']);
        $this->assertSame('20.00', $report['source_cash_flow']);
    }

    public function test_ledger_reconciliation_detects_drift_and_is_permission_and_tenant_scoped(): void
    {
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $invoice = Invoice::first();
        $date = $invoice->issue_date->toDateString();
        $user = User::find($invoice->created_by);
        $reconcile = app(PosReconciliationService::class);
        $report = $reconcile->report($user->org_id, $this->instance, $date, $date);
        $this->assertSame([], $report['issues']);
        $this->assertSame('21.40', $report['gross_sales']);
        $this->assertSame('not_available', $report['source_checkpoint']);
        DB::table('payments')->where('id', DB::table('pos_import_sales')->value('payment_id'))->update(['amount' => '0.01']);
        $report = $reconcile->report($user->org_id, $this->instance, $date, $date);
        $this->assertContains('receipt_amount_mismatch', array_column($report['issues'], 'code'));
        $url = '/pos-integration/'.$this->instance.'/reconciliation?from='.$date.'&to='.$date;
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->permission($user, ['pos.reconcile']);
        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Admin/PosReconciliation')->where('report.source_checkpoint', 'not_available')->has('report.issues', 1));
        $other = User::factory()->create();
        $this->permission($other, ['pos.reconcile']);
        $this->actingAs($other)->get($url)->assertNotFound();
    }

    public function test_discount_requires_published_policy_registered_manager_and_unique_approval(): void
    {
        $catalog = json_decode(DB::table('pos_catalog_publications')->value('payload'), true);
        $catalog['items'][0]['max_manager_discount_pct'] = '20.00';
        DB::table('pos_catalog_publications')->update(['payload' => PosCanonicalJson::encode($catalog), 'payload_hash' => hash('sha256', PosCanonicalJson::encode($catalog))]);
        $p = $this->event['payload'];
        $manager = (string) Str::uuid();
        $p['discount_approval_id'] = (string) Str::uuid();
        $p['discount_approved_by'] = $manager;
        $p['discount_approved_at'] = $p['occurred_at'];
        $p['lines'][0]['discount_amount'] = '2.00';
        $p['lines'][0]['subtotal'] = $p['subtotal'] = '18.00';
        $p['lines'][0]['tax_amount'] = $p['tax_amount'] = '1.26';
        $p['lines'][0]['total'] = $p['total'] = '19.26';
        $p['change'] = '10.74';
        $this->event['payload'] = $p;
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($p));
        $this->sign();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        config(['pos.local_discounts_enabled' => true]);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $this->instance, 'source_staff_id' => $manager, 'role' => 'manager', 'active' => true]);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame('19.26', Invoice::first()->total);
        $this->assertSame('2.00', Invoice::first()->items()->first()->discount_amount);
        $p['sale_id'] = (string) Str::uuid();
        $p['receipt_no'] = 'B1-T1-0002';
        $p['lines'][0]['line_id'] = (string) Str::uuid();
        $this->event = array_merge($this->event, ['event_id' => (string) Str::uuid(), 'source_sequence' => 2, 'aggregate_id' => $p['sale_id'], 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 2);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(8.0, (float) StockMovement::sum('quantity'));
    }

    private function kitchenFixture(): array
    {
        config(['pos.local_restaurant_enabled' => true]);
        $sale = $this->event;
        $base = $sale['payload'];
        $instance = DB::table('pos_instances')->first();
        $raw = Product::create(['org_id' => $instance->org_id, 'sku' => 'SYNTHETIC-RAW', 'name' => 'Synthetic ingredient', 'type' => 'product', 'unit' => 'kg', 'price' => '0.00', 'is_active' => true]);
        StockMovement::create(['org_id' => $instance->org_id, 'product_id' => $raw->id, 'warehouse_id' => $instance->warehouse_id, 'movement_type' => 'adjustment_in', 'movement_date' => now()->toDateString(), 'quantity' => '4.0000', 'base_unit_cost' => '2.00', 'base_total_cost' => '8.00']);
        $grant = (string) Str::uuid();
        DB::table('pos_stock_entitlements')->insert(['id' => $grant, 'org_id' => $instance->org_id, 'pos_instance_id' => $instance->id, 'warehouse_id' => $instance->warehouse_id, 'product_id' => $raw->id, 'epoch' => 1, 'granted_quantity' => '4.0000', 'consumed_quantity' => '0.0000', 'status' => 'active']);
        $menu = Product::findOrFail($base['lines'][0]['product_id']);
        $bom = BillOfMaterials::create(['org_id' => $instance->org_id, 'bom_no' => 'SYNTHETIC-RECIPE', 'product_id' => $menu->id, 'name' => 'Synthetic recipe', 'version' => 'v1', 'quantity' => '1.0000', 'unit' => $menu->unit, 'is_active' => true, 'created_by' => $instance->actor_id]);
        $bom->items()->create(['org_id' => $instance->org_id, 'product_id' => $raw->id, 'quantity' => '0.5000', 'unit' => 'kg', 'waste_percent' => '0.00']);
        $recipe = app(PosRecipeSnapshotService::class)->build($instance->org_id, $menu, $bom->id);
        $catalog = json_decode(DB::table('pos_catalog_publications')->value('payload'), true);
        $catalog['items'][0]['restaurant'] = $recipe;
        DB::table('pos_catalog_publications')->update(['payload' => PosCanonicalJson::encode($catalog), 'payload_hash' => hash('sha256', PosCanonicalJson::encode($catalog))]);
        $codes = json_decode($instance->account_codes, true);
        $codes['waste'] = '5997';
        ChartOfAccount::firstOrCreate(['org_id' => $instance->org_id, 'code' => '5997'], ['name' => 'Synthetic food waste', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_postable' => true, 'status' => 'active']);
        DB::table('pos_instances')->where('id', $instance->id)->update(['business_type' => 'restaurant', 'account_codes' => json_encode($codes)]);
        $chef = (string) Str::uuid();
        $manager = (string) Str::uuid();
        foreach ([$chef => 'kitchen', $manager => 'manager'] as $staff => $role) {
            DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instance->id, 'source_staff_id' => $staff, 'role' => $role, 'active' => true]);
        }
        $line = (string) Str::uuid();
        $p = ['consumption_id' => $line, 'order_id' => (string) Str::uuid(), 'product_id' => $menu->id, 'branch_id' => $base['branch_id'], 'terminal_id' => $base['terminal_id'], 'staff_id' => $chef, 'catalog_version' => 'v1', 'recipe_hash' => $recipe['recipe_hash'], 'purpose' => 'preparation', 'quantity' => '1.0000', 'unit' => $menu->unit, 'currency' => $base['currency'], 'occurred_at' => $base['occurred_at'], 'business_timezone' => 'Asia/Bangkok', 'ingredients' => [['product_id' => $raw->id, 'quantity' => '0.5000', 'allocations' => [['grant_id' => $grant, 'epoch' => 1, 'lot_id' => null, 'quantity' => '0.5000']]]]];
        $this->event = array_merge($sale, ['event_type' => 'kitchen.consumed', 'aggregate_id' => $line, 'event_id' => (string) Str::uuid(), 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();
        $kitchen = $this->event;
        $p = $base;
        $p['subtotal'] = $p['lines'][0]['subtotal'] = '10.00';
        $p['tax_amount'] = $p['lines'][0]['tax_amount'] = '0.70';
        $p['total'] = $p['lines'][0]['total'] = '10.70';
        $p['cash_tendered'] = '20.00';
        $p['change'] = '9.30';
        $p['lines'][0]['quantity'] = '1.0000';
        $p['lines'][0]['allocations'] = [];
        $p['lines'][0]['stock_effect'] = 'prepared';
        $p['lines'][0]['kitchen_refs'] = [['line_id' => $line, 'consumption_hash' => $kitchen['payload_hash']]];
        $this->event = array_merge($sale, ['source_sequence' => 2, 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();

        return ['kitchen' => $kitchen, 'sale' => $this->event, 'ingredient' => $raw->id, 'grant' => $grant, 'manager' => $manager, 'instance' => $instance];
    }

    public function test_prepared_sale_waits_for_kitchen_then_posts_money_without_second_stock_or_cogs(): void
    {
        $f = $this->kitchenFixture();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$f['sale']], 2);
        $this->assertSame('dependency_pending', $service->process($this->instance, $f['sale']['event_id'])['status']);
        $this->assertSame(0, Invoice::count());
        $this->assertDatabaseCount('pos_sync_dependencies', 1);
        $service->receive($this->instance, [$f['kitchen']], 2);
        $this->assertSame('accepted', $service->process($this->instance, $f['kitchen']['event_id'])['status']);
        $this->mock(PosTransport::class, function ($mock) {
            $mock->shouldReceive('request')->with($this->instance, 'events', \Mockery::type('array'))->andReturn(['events' => [], 'next_cursor' => 2]);
            $mock->shouldReceive('request')->with($this->instance, 'result', \Mockery::type('array'), \Mockery::type('string'))->andReturn(['status' => 'recorded']);
        });
        $this->artisan('pos:sync', ['instance' => $this->instance])->assertExitCode(0);
        $this->assertSame('accepted', DB::table('pos_sync_inbox')->where('event_id', $f['sale']['event_id'])->value('status'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(3, JournalEntry::count());
        $this->assertDatabaseCount('pos_import_kitchen_sales', 1);
        $this->assertSame(1, StockMovement::where('movement_type', 'recipe_consumption')->count());
        $this->assertSame(0, StockMovement::where('movement_type', 'pos_sale')->count());
        $this->assertSame(3.5, (float) StockMovement::where('product_id', $f['ingredient'])->sum('quantity'));
        $this->assertDatabaseCount('work_orders', 0);
        $this->event = $f['sale'];
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 3;
        $this->event['aggregate_id'] = $this->event['payload']['sale_id'] = (string) Str::uuid();
        $this->event['payload']['receipt_no'] = 'DUPLICATE-PORTION';
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($this->event['payload']));
        $this->sign();
        $service->receive($this->instance, [$this->event], 3);
        $this->assertSame('needs_review', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(1, Invoice::count());
    }

    public function test_prepared_inventory_shortage_preserves_money_and_retry_finishes_only_kitchen_cost(): void
    {
        $f = $this->kitchenFixture();
        StockMovement::where('product_id', $f['ingredient'])->delete();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$f['kitchen'], $f['sale']], 2);
        $this->assertSame('inventory_pending', $service->process($this->instance, $f['kitchen']['event_id'])['status']);
        $this->assertSame('inventory_pending', $service->process($this->instance, $f['sale']['event_id'])['status']);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(2, JournalEntry::count());
        $this->assertNull(DB::table('pos_import_kitchen_consumptions')->value('base_cost'));
        StockMovement::create(['org_id' => $f['instance']->org_id, 'product_id' => $f['ingredient'], 'warehouse_id' => $f['instance']->warehouse_id, 'movement_type' => 'adjustment_in', 'movement_date' => now()->toDateString(), 'quantity' => '4.0000', 'base_unit_cost' => '2.00', 'base_total_cost' => '8.00']);
        $this->assertSame('accepted', $service->process($this->instance, $f['kitchen']['event_id'])['status']);
        $this->assertSame('accepted', $service->process($this->instance, $f['sale']['event_id'])['status']);
        $this->assertSame(3, JournalEntry::count());
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, StockMovement::where('movement_type', 'recipe_consumption')->count());
    }

    public function test_prepared_reconciliation_detects_missing_ingredient_stock_and_kitchen_journal(): void
    {
        $f = $this->kitchenFixture();
        $service = app(PosImportService::class);
        $service->receive($this->instance, [$f['kitchen'], $f['sale']], 2);
        $this->assertSame('accepted', $service->process($this->instance, $f['kitchen']['event_id'])['status']);
        $this->assertSame('accepted', $service->process($this->instance, $f['sale']['event_id'])['status']);
        $date = now('Asia/Bangkok')->toDateString();
        $reporter = app(PosReconciliationService::class);
        $this->assertSame([], $reporter->report($f['instance']->org_id, $this->instance, $date, $date)['issues']);
        StockMovement::where('movement_type', 'recipe_consumption')->delete();
        JournalEntry::where('source_type', 'pos_kitchen_consumption')->update(['status' => 'reversed']);
        $issues = array_column($reporter->report($f['instance']->org_id, $this->instance, $date, $date)['issues'], 'code');
        $this->assertContains('kitchen_stock_source_mismatch', $issues);
        $this->assertContains('kitchen_cost_mismatch', $issues);
        $this->assertContains('journal_missing_or_reversed', $issues);
    }

    public function test_kitchen_waste_before_consumes_once_and_after_only_reclassifies_cost(): void
    {
        $f = $this->kitchenFixture();
        $service = app(PosImportService::class);
        $this->event = $f['kitchen'];
        $this->event['payload']['staff_id'] = $f['manager'];
        $this->event['payload']['purpose'] = 'waste_before_preparation';
        $this->event['payload']['waste_id'] = (string) Str::uuid();
        $this->event['payload']['reason'] = 'spoiled';
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($this->event['payload']));
        $this->sign();
        $service->receive($this->instance, [$this->event], 1);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->event = $f['kitchen'];
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 2;
        $this->event['aggregate_id'] = $this->event['payload']['consumption_id'] = (string) Str::uuid();
        $this->event['payload_hash'] = hash('sha256', PosCanonicalJson::encode($this->event['payload']));
        $this->sign();
        $prepared = $this->event;
        $service->receive($this->instance, [$this->event], 2);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $id = (string) Str::uuid();
        $p = ['waste_id' => $id, 'consumption_id' => $prepared['aggregate_id'], 'consumption_hash' => $prepared['payload_hash'], 'branch_id' => $prepared['payload']['branch_id'], 'terminal_id' => $prepared['payload']['terminal_id'], 'staff_id' => $f['manager'], 'reason' => 'preparation_error', 'quantity' => '1.0000', 'currency' => $prepared['payload']['currency'], 'occurred_at' => $prepared['occurred_at'], 'business_timezone' => 'Asia/Bangkok'];
        $this->event = array_merge($prepared, ['event_type' => 'kitchen.wasted', 'event_id' => (string) Str::uuid(), 'source_sequence' => 3, 'aggregate_id' => $id, 'payload' => $p, 'payload_hash' => hash('sha256', PosCanonicalJson::encode($p))]);
        $this->sign();
        $service->receive($this->instance, [$this->event], 3);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->event['event_id'] = (string) Str::uuid();
        $this->event['source_sequence'] = 4;
        $this->sign();
        $service->receive($this->instance, [$this->event], 4);
        $this->assertSame('accepted', $service->process($this->instance, $this->event['event_id'])['status']);
        $this->assertSame(3, JournalEntry::count());
        $this->assertSame(1, StockMovement::where('movement_type', 'food_waste_spoilage')->count());
        $this->assertSame(1, StockMovement::where('movement_type', 'recipe_consumption')->count());
        $this->assertSame(3.0, (float) StockMovement::where('product_id', $f['ingredient'])->sum('quantity'));
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Payment::count());
    }
}
