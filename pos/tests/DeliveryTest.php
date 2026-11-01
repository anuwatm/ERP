<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Pos\Models\Delivery;
use Pos\Models\Staff;
use Pos\Services\CheckoutService;
use Pos\Services\EntitlementAllocator;
use Pos\Services\ShiftCashService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private static string $keyPath;

    private string $terminal;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$keyPath = tempnam(sys_get_temp_dir(), 'delivery-test-key-');
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $path = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($path)) {
            $options['config'] = $path;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);
        file_put_contents(self::$keyPath, $pem);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$keyPath) && is_file(self::$keyPath)) {
            unlink(self::$keyPath);
        }
        parent::tearDownAfterClass();
    }

    private Staff $staff;

    private string $product;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.local_delivery_enabled' => true, 'pos.instance_id' => (string) Str::uuid(), 'pos.signing_key_id' => 'delivery-test', 'pos.signing_key_path' => self::$keyPath]);
        $branch = (string) Str::uuid();
        $this->product = (string) Str::uuid();
        $this->terminal = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => 'D1', 'name' => 'Synthetic delivery', 'business_type' => 'retail']);
        DB::table('pos_catalogs')->insert(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'version' => 'v1', 'payload_hash' => str_repeat('a', 64), 'valid_until' => now()->addDay(), 'items' => json_encode([['product_id' => $this->product, 'name' => 'Synthetic item', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]])]);
        DB::table('pos_terminals')->insert(['id' => $this->terminal, 'branch_id' => $branch, 'code' => 'COD']);
        DB::table('pos_grants')->insert(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'product_id' => $this->product, 'epoch' => 1, 'granted_quantity' => '100.0000', 'payload_hash' => str_repeat('b', 64), 'status' => 'active']);
        $this->staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Synthetic cashier', 'email' => 'delivery@fixture.test', 'password' => 'synthetic-password', 'role' => 'cashier', 'active' => true]);
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()]);
    }

    private function draft(): array
    {
        return ['request_key' => (string) Str::uuid(), 'settlement_mode' => 'cod', 'recipient' => 'Synthetic recipient', 'phone' => '000-TEST', 'address' => 'Synthetic private address', 'catalog_version' => 'v1', 'items' => [['product_id' => $this->product, 'quantity' => '1']]];
    }

    private function action(string $id, string $action, int $version, array $extra = []): TestResponse
    {
        return $this->postJson('/deliveries/'.$id.'/transition', $extra + ['request_key' => (string) Str::uuid(), 'version' => $version, 'action' => $action]);
    }

    private function sale(): string
    {
        $terminal = (string) Str::uuid();
        $shift = (string) Str::uuid();
        $sale = (string) Str::uuid();
        DB::table('pos_terminals')->insert(['id' => $terminal, 'branch_id' => $this->staff->branch_id, 'code' => 'T1']);
        DB::table('pos_shifts')->insert(['id' => $shift, 'terminal_id' => $terminal, 'staff_id' => $this->staff->id, 'status' => 'closed', 'opening_cash' => '0']);
        $snapshot = ['receipt_no' => 'D1-T1-TEST', 'lines' => [['product_id' => $this->product, 'name' => 'Paid item', 'unit' => 'piece', 'quantity' => '1']]];
        DB::table('pos_orders')->insert(['id' => $sale, 'branch_id' => $this->staff->branch_id, 'terminal_id' => $terminal, 'shift_id' => $shift, 'staff_id' => $this->staff->id, 'request_key' => 'sale', 'request_hash' => str_repeat('a', 64), 'receipt_no' => 'D1-T1-TEST', 'status' => 'completed', 'catalog_version' => 'v1', 'currency' => 'THB', 'subtotal' => '10', 'tax_amount' => '0.70', 'total' => '10.70', 'snapshot' => json_encode($snapshot)]);
        DB::table('pos_payments')->insert(['id' => (string) Str::uuid(), 'order_id' => $sale, 'method' => 'cash', 'amount' => '10.70', 'tendered' => '20', 'change' => '9.30']);

        return $sale;
    }

    public function test_cod_intake_is_encrypted_idempotent_and_has_no_financial_or_stock_effect(): void
    {
        $input = $this->draft();
        $id = $this->postJson('/deliveries', $input)->assertCreated()->json('id');
        $this->postJson('/deliveries', $input)->assertCreated()->assertJsonPath('id', $id);
        $this->postJson('/deliveries', array_replace($input, ['address' => 'Changed']))->assertConflict();
        $this->assertDatabaseCount('pos_deliveries', 1);
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('pos_payments', 0);
        $this->assertSame(0, DB::table('pos_outbox')->whereIn('event_type', ['sale.completed', 'sale.refunded', 'cash.moved'])->count());
        $this->assertSame('0', (string) DB::table('pos_grants')->value('consumed_quantity'));
        $this->assertStringNotContainsString('Synthetic private address', DB::table('pos_deliveries')->value('contact'));
        $this->assertStringNotContainsString('Synthetic recipient', DB::table('pos_audit')->value('metadata'));
        $this->assertArrayNotHasKey('contact', Delivery::findOrFail($id)->toArray());
        $this->get('/deliveries')->assertOk()->assertSee('Synthetic recipient')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/deliveries/'.$id.'/slip')->assertOk()->assertSee('Synthetic item')->assertSee('Synthetic private address')->assertHeader('Cache-Control', 'no-store, private');
        $this->action($id, 'ready', 1, ['terminal_id' => $this->terminal])->assertOk();
        $this->action($id, 'dispatch', 2, ['courier_id' => $this->staff->id])->assertOk();
        $this->assertDatabaseHas('pos_deliveries', ['id' => $id, 'status' => 'dispatched', 'version' => 3]);
    }

    public function test_prepaid_workflow_and_replay_do_not_duplicate_sale_payment_or_outbox(): void
    {
        $sale = $this->sale();
        $input = array_replace($this->draft(), ['settlement_mode' => 'prepaid_cash', 'sale_id' => $sale]);
        $id = $this->postJson('/deliveries', $input)->assertCreated()->json('id');
        $this->action($id, 'deliver', 1)->assertConflict();
        $command = ['request_key' => (string) Str::uuid(), 'version' => 1, 'action' => 'ready'];
        $this->postJson('/deliveries/'.$id.'/transition', $command)->assertOk()->assertJsonPath('version', 2);
        $this->postJson('/deliveries/'.$id.'/transition', $command)->assertOk();
        $this->action($id, 'dispatch', 1, ['courier_id' => $this->staff->id])->assertConflict();
        $this->action($id, 'dispatch', 2, ['courier_id' => $this->staff->id])->assertOk();
        $this->action($id, 'deliver', 3)->assertOk()->assertJsonPath('status', 'delivered');
        $this->assertDatabaseCount('pos_orders', 1);
        $this->assertDatabaseCount('pos_payments', 1);
        $this->assertSame(0, DB::table('pos_outbox')->whereIn('event_type', ['sale.completed', 'sale.refunded', 'cash.moved'])->count());
        $this->assertDatabaseCount('pos_delivery_actions', 3);
        $this->assertDatabaseHas('pos_shifts', ['status' => 'closed', 'opening_cash' => '0']);
        $this->postJson('/deliveries', array_replace($input, ['request_key' => (string) Str::uuid()]))->assertConflict();
    }

    public function test_failed_delivery_retains_financial_evidence_and_requires_reason(): void
    {
        $id = $this->postJson('/deliveries', array_replace($this->draft(), ['settlement_mode' => 'prepaid_cash', 'sale_id' => $this->sale()]))->assertCreated()->json('id');
        $this->action($id, 'ready', 1)->assertOk();
        $this->action($id, 'dispatch', 2, ['courier_id' => $this->staff->id])->assertOk();
        $this->action($id, 'fail', 3)->assertUnprocessable();
        $this->action($id, 'fail', 3, ['reason' => 'recipient_absent'])->assertOk();
        $this->assertDatabaseCount('pos_refunds', 0);
        $this->assertDatabaseCount('pos_payments', 1);
        $this->assertSame(0, DB::table('pos_outbox')->whereIn('event_type', ['sale.completed', 'sale.refunded', 'cash.moved'])->count());
        $this->action($id, 'dispatch', 4, ['courier_id' => $this->staff->id])->assertConflict();
    }

    public function test_access_is_branch_scoped_local_and_pharmacy_is_blocked(): void
    {
        $id = $this->postJson('/deliveries', $this->draft())->assertCreated()->json('id');
        $other = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $other, 'code' => 'D2', 'name' => 'Other branch', 'business_type' => 'restaurant']);
        $this->staff->update(['branch_id' => $other]);
        $this->get('/deliveries/'.$id.'/slip')->assertNotFound();
        $this->action($id, 'ready', 1)->assertNotFound();
        DB::table('pos_branches')->where('id', $other)->update(['business_type' => 'pharmacy']);
        foreach (['cashier', 'manager', 'pharmacist', 'kitchen'] as $role) {
            $this->staff->update(['role' => $role]);
            $this->get('/deliveries')->assertForbidden();
            $this->get('/deliveries/'.$id.'/slip')->assertForbidden();
            $this->postJson('/deliveries', $this->draft())->assertForbidden();
        }
        $this->staff->update(['role' => 'cashier']);
        DB::table('pos_branches')->where('id', $other)->update(['business_type' => 'retail']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('/deliveries')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        config(['pos.local_delivery_enabled' => false]);
        $this->get('/deliveries')->assertStatus(503);
    }

    public function test_invalid_catalog_quantities_and_unauthorized_roles_are_rejected(): void
    {
        $this->postJson('/deliveries', $this->draft() + ['delivery_fee' => '25.00'])->assertUnprocessable();
        $this->postJson('/deliveries', array_replace($this->draft(), ['items' => [['product_id' => $this->product, 'quantity' => '0']]]))->assertUnprocessable();
        $this->postJson('/deliveries', array_replace($this->draft(), ['catalog_version' => 'stale']))->assertUnprocessable();
        $this->staff->update(['role' => 'kitchen']);
        $this->get('/deliveries')->assertForbidden();
        $this->staff->update(['role' => 'cashier']);
        app()->instance('env', 'production');
        $this->get('/deliveries')->assertStatus(503);
    }

    public function test_cancel_is_a_workflow_action_not_a_cash_refund_and_requires_reason(): void
    {
        $id = $this->postJson('/deliveries', $this->draft())->assertCreated()->json('id');
        $this->action($id, 'cancel', 1)->assertUnprocessable();
        $this->action($id, 'cancel', 1, ['reason' => 'customer_cancel'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->action($id, 'ready', 2)->assertConflict();
        $this->assertDatabaseCount('pos_refunds', 0);
    }

    public function test_restaurant_uses_same_intake_and_contact_is_html_escaped(): void
    {
        DB::table('pos_branches')->where('id', $this->staff->branch_id)->update(['business_type' => 'restaurant']);
        $id = $this->postJson('/deliveries', array_replace($this->draft(), ['settlement_mode' => 'prepaid_cash', 'sale_id' => $this->sale(), 'recipient' => '<script>alert(1)</script>']))->assertCreated()->json('id');
        $this->get('/deliveries/'.$id.'/slip')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    private function codDispatched(): string
    {
        $id = $this->postJson('/deliveries', $this->draft())->assertCreated()->json('id');
        $this->action($id, 'ready', 1, ['terminal_id' => $this->terminal])->assertOk();
        $this->action($id, 'dispatch', 2, ['courier_id' => $this->staff->id])->assertOk();

        return $id;
    }

    public function test_cod_collection_remittance_and_shift_close_are_custody_not_revenue(): void
    {
        $id = $this->codDispatched();
        $this->action($id, 'deliver', 3)->assertOk();
        $this->action($id, 'collect', 4, ['amount' => '1.00'])->assertConflict();
        $key = (string) Str::uuid();
        $this->action($id, 'collect', 4, ['request_key' => $key, 'amount' => '10.70'])->assertOk();
        $this->action($id, 'collect', 4, ['request_key' => $key, 'amount' => '10.70'])->assertOk();
        $this->assertDatabaseCount('pos_delivery_cash', 0);
        $this->staff->update(['role' => 'manager']);
        $checkout = app(CheckoutService::class);
        $oldShift = $checkout->openShift($this->staff, $this->terminal, '0');
        $checkout->closeShift($this->staff, $oldShift, '0');
        $shift = $checkout->openShift($this->staff, $this->terminal, '0');
        $this->action($id, 'remit', 5, ['terminal_id' => $this->terminal, 'manager_password' => 'synthetic-password'])->assertOk();
        $this->assertSame('10.70', app(ShiftCashService::class)->balance(DB::table('pos_shifts')->find($shift)));
        $this->assertSame('0.00', app(ShiftCashService::class)->balance(DB::table('pos_shifts')->find($oldShift)));
        $this->action($id, 'remit', 6, ['terminal_id' => $this->terminal, 'manager_password' => 'synthetic-password'])->assertConflict();
        $this->assertSame('0.00', $checkout->closeShift($this->staff, $shift, '10.70')['over_short']);
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('pos_payments', 0);
        $this->assertDatabaseCount('pos_delivery_cash', 1);
        $this->assertSame(0, DB::table('pos_outbox')->where('event_type', 'sale.completed')->count());
    }

    public function test_failed_cod_never_releases_stock_until_manager_inspects_and_retry_is_idempotent(): void
    {
        $id = $this->codDispatched();
        $this->action($id, 'fail', 3, ['reason' => 'recipient_absent'])->assertOk();
        $this->assertEquals(1, DB::table('pos_grants')->value('reserved_quantity'));
        $this->action($id, 'retry', 4, ['manager_password' => 'synthetic-password'])->assertForbidden();
        $this->staff->update(['role' => 'manager']);
        $this->action($id, 'retry', 4, ['manager_password' => 'synthetic-password'])->assertOk();
        $this->action($id, 'dispatch', 5, ['courier_id' => $this->staff->id])->assertOk();
        $this->action($id, 'fail', 6, ['reason' => 'address_issue'])->assertOk();
        $this->action($id, 'receive', 7)->assertOk();
        $this->action($id, 'inspect', 8, ['decision' => 'restock', 'manager_password' => 'synthetic-password'])->assertUnprocessable();
        $this->action($id, 'inspect', 8, ['decision' => 'restock', 'condition_verified' => true, 'manager_password' => 'synthetic-password'])->assertOk();
        $this->assertEquals(0, DB::table('pos_grants')->value('reserved_quantity'));
        $this->assertEquals(0, DB::table('pos_grants')->value('consumed_quantity'));
        $this->assertDatabaseCount('pos_refunds', 0);
    }

    public function test_fees_need_manager_and_edit_uses_server_price_and_clears_old_fee_quote(): void
    {
        $id = $this->postJson('/deliveries', $this->draft())->assertCreated()->json('id');
        $fees = ['delivery_fee' => '5.00', 'delivery_tax_rate' => '7.00', 'packaging_fee' => '2.00', 'packaging_tax_rate' => '0.00', 'manager_password' => 'synthetic-password'];
        $this->action($id, 'fees', 1, $fees)->assertForbidden();
        $this->staff->update(['role' => 'manager']);
        $this->action($id, 'fees', 1, $fees)->assertOk();
        $this->assertSame('7.35', Delivery::find($id)->snapshot['fees_total']);
        $this->action($id, 'fees', 2, array_replace($fees, ['delivery_tax_rate' => '99.00']))->assertUnprocessable();
        $this->action($id, 'edit', 2, ['recipient' => 'Changed recipient', 'phone' => '000-TEST', 'address' => 'Changed address', 'catalog_version' => 'v1', 'items' => [['product_id' => $this->product, 'quantity' => '2', 'price' => '0.01']]])->assertOk();
        $snapshot = Delivery::find($id)->snapshot;
        $this->assertSame('21.40', $snapshot['total']);
        $this->assertSame('0.00', $snapshot['fees_total']);
        $this->assertStringNotContainsString('Changed address', DB::table('pos_outbox')->latest('sequence')->value('envelope'));
    }

    public function test_stock_reservation_blocks_other_checkout_consumers_and_cancel_releases_only_once(): void
    {
        $id = $this->postJson('/deliveries', $this->draft())->assertCreated()->json('id');
        $this->action($id, 'ready', 1, ['terminal_id' => $this->terminal])->assertOk();
        try {
            DB::transaction(fn () => app(EntitlementAllocator::class)->take($this->staff->branch_id, $this->product, '100', 'Asia/Bangkok'));
            $this->fail('Reserved quota must not be sold twice.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $key = (string) Str::uuid();
        $this->action($id, 'cancel', 2, ['request_key' => $key, 'reason' => 'customer_cancel'])->assertOk();
        $this->action($id, 'cancel', 2, ['request_key' => $key, 'reason' => 'customer_cancel'])->assertOk();
        $this->assertEquals(0, DB::table('pos_grants')->value('reserved_quantity'));
        $this->assertEquals(0, DB::table('pos_grants')->value('consumed_quantity'));
    }

    public function test_cod_return_refunds_courier_custody_without_fabricating_a_sale_or_drawer_payment(): void
    {
        $id = $this->codDispatched();
        $this->action($id, 'deliver', 3)->assertOk();
        $this->action($id, 'collect', 4, ['amount' => '10.70'])->assertOk();
        $this->action($id, 'receive', 5)->assertOk();
        $this->staff->update(['role' => 'manager']);
        $this->action($id, 'refund', 6, ['manager_password' => 'synthetic-password'])->assertOk();
        $this->action($id, 'inspect', 7, ['decision' => 'destroy', 'condition_verified' => true, 'manager_password' => 'synthetic-password'])->assertOk();
        $this->assertSame('10.70', Delivery::find($id)->snapshot['cash_refunded']);
        $this->assertEquals(1, DB::table('pos_grants')->value('consumed_quantity'));
        $this->assertDatabaseCount('pos_delivery_cash', 0);
        $this->assertDatabaseCount('pos_payments', 0);
        $this->assertDatabaseCount('pos_refunds', 0);
    }
}
