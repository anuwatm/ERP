<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\CashMovementService;
use Pos\Services\CheckoutService;
use Pos\Services\DiscountService;
use Pos\Services\ManagerApproval;
use Pos\Services\PharmacyPolicy;
use Pos\Services\PrescriptionScanner;
use Pos\Services\PrescriptionService;
use Pos\Services\ProvisioningService;
use Pos\Services\PublicMenuService;
use Pos\Services\RefundService;
use Pos\Services\ReturnInspectionService;
use Pos\Services\TaxDocumentService;
use Pos\Support\CanonicalJson;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private string $keyPath;

    private string $branch;

    private string $terminal;

    private string $product;

    private Staff $staff;

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
        $this->branch = (string) Str::uuid();
        $this->terminal = (string) Str::uuid();
        $this->product = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $this->branch, 'code' => 'B1', 'name' => 'Shop', 'business_type' => 'retail']);
        DB::table('pos_terminals')->insert(['id' => $this->terminal, 'branch_id' => $this->branch, 'code' => 'T1']);
        $this->staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch, 'name' => 'Cashier', 'email' => 'cash@example.test', 'password' => 'long-test-password', 'role' => 'cashier', 'active' => true]);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'pos-test-key-');
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $opensslConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (file_exists($opensslConfig)) {
            $options['config'] = $opensslConfig;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);
        file_put_contents($this->keyPath, $pem);
        config(['pos.instance_id' => (string) Str::uuid(), 'pos.signing_key_id' => 'test-key', 'pos.signing_key_path' => $this->keyPath, 'pos.connector_branch_ids' => [$this->branch]]);
        app(ProvisioningService::class)->catalog('v1', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $this->product, 'name' => 'Test product', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]]]);
        app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $this->branch, 'product_id' => $this->product, 'epoch' => 1, 'quantity' => '2.0000']);
        app(CheckoutService::class)->openShift($this->staff, $this->terminal, '100.00');
    }

    protected function tearDown(): void
    {
        if (isset($this->keyPath) && file_exists($this->keyPath)) {
            unlink($this->keyPath);
        }
        parent::tearDown();
    }

    public function test_cash_checkout_replay_and_signed_outbox_are_atomic(): void
    {
        $input = $this->input();
        $sale = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->assertSame('21.40', $sale['total']);
        $this->assertSame('8.60', $sale['change']);
        $again = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->assertSame($sale['sale_id'], $again['sale_id']);
        $this->assertDatabaseCount('pos_orders', 1);
        $this->assertDatabaseCount('pos_payments', 1);
        $this->assertDatabaseCount('pos_outbox', 1);
        $event = json_decode(DB::table('pos_outbox')->value('envelope'), true);
        $signature = base64_decode($event['signature'], true);
        unset($event['signature']);
        $public = openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents($this->keyPath)))['key'];
        $this->assertSame(1, openssl_verify(CanonicalJson::encode($event), $signature, $public, OPENSSL_ALGO_SHA256));
        $this->assertSame(hash('sha256', CanonicalJson::encode($event['payload'])), $event['payload_hash']);
    }

    public function test_partial_refunds_are_source_linked_deduplicated_and_quarantined(): void
    {
        config(['pos.local_refunds_enabled' => true]);
        $sale = app(CheckoutService::class)->checkout($this->staff, $this->input());
        $this->staff->update(['role' => 'manager']);
        $service = app(RefundService::class);
        $command = $service->command(['order_id' => $sale['sale_id'], 'terminal_id' => $this->terminal, 'request_key' => 'return-one', 'reason' => 'customer_return', 'items' => [['line_id' => $sale['lines'][0]['line_id'], 'quantity' => '1', 'returned_quantity' => '1']]]);
        $approval = ManagerApproval::verify($this->staff, 'long-test-password', $command);
        $refund = $service->refund($this->staff, $command, $approval);
        $this->assertSame('10.70', $refund['total']);
        $this->assertSame('quarantined', $refund['lines'][0]['return_status']);
        $this->assertSame($refund['refund_id'], $service->refund($this->staff, $command, $approval)['refund_id']);
        $this->assertDatabaseCount('pos_refunds', 1);
        $this->assertSame(2.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        $command['request_key'] = 'return-two';
        $approval = ManagerApproval::verify($this->staff, 'long-test-password', $command);
        $service->refund($this->staff, $command, $approval);
        $shift = DB::table('pos_shifts')->value('id');
        $this->assertSame('100.00', app(CheckoutService::class)->closeShift($this->staff, $shift, '100.00')['expected_cash']);
        $this->assertDatabaseCount('pos_refunds', 2);
        $this->assertDatabaseCount('pos_outbox', 3);
    }

    public function test_refund_wrong_password_changed_command_and_over_return_are_denied(): void
    {
        config(['pos.local_refunds_enabled' => true]);
        $sale = app(CheckoutService::class)->checkout($this->staff, $this->input());
        $this->staff->update(['role' => 'manager']);
        $service = app(RefundService::class);
        $command = $service->command(['order_id' => $sale['sale_id'], 'terminal_id' => $this->terminal, 'request_key' => 'return', 'reason' => 'sale_error', 'items' => [['line_id' => $sale['lines'][0]['line_id'], 'quantity' => '3']]]);
        foreach (['wrong-password', 'long-test-password'] as $password) {
            try {
                $service->refund($this->staff, $command, ManagerApproval::verify($this->staff, $password, $command));
                $this->fail('Invalid refund accepted.');
            } catch (HttpExceptionInterface $e) {
                $this->assertContains($e->getStatusCode(), [403, 422]);
            }
        }
        $command['items'][0]['quantity'] = '1.0000';
        $approval = ManagerApproval::verify($this->staff, 'long-test-password', $command);
        $command['reason'] = 'damaged';
        try {
            $service->refund($this->staff, $command, $approval);
            $this->fail('Changed approval accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('pos_refunds', 0);
        $this->assertDatabaseCount('pos_outbox', 1);
    }

    public function test_pharmacy_refund_preserves_original_conversion_price_and_precision(): void
    {
        $this->pharmacyCatalog();
        config(['pos.local_refunds_enabled' => true]);
        $this->staff->update(['role' => 'pharmacist']);
        $input = $this->input();
        $input['catalog_version'] = 'pharmacy-v1';
        $input['items'][0] = ['product_id' => $this->product, 'quantity' => '1', 'unit' => 'strip'];
        $input['cash_tendered'] = '100.00';
        $sale = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->staff->update(['role' => 'manager']);
        app(ProvisioningService::class)->catalog('pharmacy-v2', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $this->product, 'name' => 'Synthetic product', 'unit' => 'piece', 'price' => '20.00', 'tax_rate' => '7.00', 'active' => true, 'pharmacy' => ['requires_pharmacist' => true, 'base_step' => '1.0000', 'units' => [['unit' => 'strip', 'factor' => '12.0000', 'price' => '120.00', 'conversion_version' => 'conversion-v2']]]]]]);
        $service = app(RefundService::class);
        $command = $service->command(['order_id' => $sale['sale_id'], 'terminal_id' => $this->terminal, 'request_key' => 'precision', 'reason' => 'customer_return', 'items' => [['line_id' => $sale['lines'][0]['line_id'], 'quantity' => '0.05', 'returned_quantity' => '0.05']]]);
        try {
            $service->refund($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
            $this->fail('Fractional tablet refund accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $command['items'][0]['quantity'] = $command['items'][0]['returned_quantity'] = '0.1000';
        $this->assertSame('8.56', $service->refund($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command))['total']);
        $command['request_key'] = 'remaining';
        $command['items'][0]['quantity'] = $command['items'][0]['returned_quantity'] = '0.9000';
        $this->assertSame('77.04', $service->refund($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command))['total']);
        $this->assertSame(10.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
    }

    public function test_refunds_fail_closed_in_production_and_without_a_signer(): void
    {
        $sale = app(CheckoutService::class)->checkout($this->staff, $this->input());
        $this->staff->update(['role' => 'manager']);
        $service = app(RefundService::class);
        $command = $service->command(['order_id' => $sale['sale_id'], 'terminal_id' => $this->terminal, 'request_key' => 'guarded', 'reason' => 'sale_error', 'items' => [['line_id' => $sale['lines'][0]['line_id'], 'quantity' => '1']]]);
        $approval = ManagerApproval::verify($this->staff, 'long-test-password', $command);
        foreach (['testing', 'production'] as $environment) {
            config(['pos.local_refunds_enabled' => $environment === 'production']);
            app()->detectEnvironment(fn () => $environment);
            try {
                $service->refund($this->staff, $command, $approval);
                $this->fail('Closed refund gate bypassed.');
            } catch (HttpExceptionInterface $e) {
                $this->assertSame(503, $e->getStatusCode());
            }
        }
        app()->detectEnvironment(fn () => 'testing');
        config(['pos.local_refunds_enabled' => true, 'pos.signing_key_path' => null]);
        try {
            $service->refund($this->staff, $command, $approval);
            $this->fail('Unsigned refund committed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('POS event signing is not configured.', $e->getMessage());
        }
        $this->assertDatabaseCount('pos_refunds', 0);
        $this->assertDatabaseCount('pos_refund_lines', 0);
        $this->assertDatabaseCount('pos_outbox', 1);
    }

    public function test_missing_signer_rolls_back_cash_sale_and_entitlement(): void
    {
        config(['pos.signing_key_path' => null]);
        try {
            app(CheckoutService::class)->checkout($this->staff, $this->input());
            $this->fail('Missing signer must fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('POS event signing is not configured.', $e->getMessage());
        }
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('pos_outbox', 0);
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
    }

    public function test_manager_cash_movements_dedupe_and_close_shift_without_creating_sales(): void
    {
        config(['pos.local_cash_movements_enabled' => true]);
        $this->staff->update(['role' => 'manager']);
        $service = app(CashMovementService::class);
        $command = $service->command(['terminal_id' => $this->terminal, 'request_key' => 'float', 'direction' => 'in', 'amount' => '20', 'reference' => 'SYNTHETIC-FLOAT-1', 'reason' => 'float_added']);
        $in = $service->move($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
        $this->assertSame('20.00', $in['amount']);
        $command = $service->command(['terminal_id' => $this->terminal, 'request_key' => 'drop', 'direction' => 'out', 'amount' => '30', 'reference' => 'SYNTHETIC-DROP-1', 'reason' => 'cash_drop']);
        $approval = ManagerApproval::verify($this->staff, 'long-test-password', $command);
        $out = $service->move($this->staff, $command, $approval);
        $this->assertSame($out['cash_movement_id'], $service->move($this->staff, $command, $approval)['cash_movement_id']);
        $command['request_key'] = 'too-much';
        $command['amount'] = '90.01';
        try {
            $service->move($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
            $this->fail('Drawer overdrawn.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('pos_cash_movements', 2);
        $this->assertDatabaseCount('pos_outbox', 2);
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('pos_payments', 0);
        $this->assertSame('90.00', app(CheckoutService::class)->closeShift($this->staff, DB::table('pos_shifts')->value('id'), '90.00')['expected_cash']);
    }

    public function test_manager_discount_is_cart_bound_single_use_and_refunds_original_net_amount(): void
    {
        config(['pos.local_discounts_enabled' => true, 'pos.local_refunds_enabled' => true]);
        $manager = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch, 'name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'long-test-password', 'role' => 'manager', 'active' => true]);
        app(ProvisioningService::class)->catalog('discount-v1', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $this->product, 'name' => 'Test product', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true, 'max_manager_discount_pct' => '20.00']]]);
        $service = app(DiscountService::class);
        $command = $service->command($this->staff, ['terminal_id' => $this->terminal, 'catalog_version' => 'discount-v1', 'request_key' => 'discount-one', 'items' => [['product_id' => $this->product, 'quantity' => '2', 'unit' => 'piece', 'discount_amount' => '2.00']]]);
        $approved = $service->approve($this->staff, $manager, $command, ManagerApproval::verify($manager, 'long-test-password', $command));
        $this->assertSame('19.26', $approved['discounted_total']);
        $input = $this->input();
        $input['catalog_version'] = 'discount-v1';
        $input['discount_approval_id'] = $approved['id'];
        $manager->update(['role' => 'cashier']);
        try {
            app(CheckoutService::class)->checkout($this->staff, $input);
            $this->fail('Revoked manager approval accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $manager->update(['role' => 'manager']);
        config(['pos.signing_key_path' => null]);
        try {
            app(CheckoutService::class)->checkout($this->staff, $input);
            $this->fail('Unsigned discounted sale committed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('POS event signing is not configured.', $e->getMessage());
        }
        $this->assertNull(DB::table('pos_discount_approvals')->where('id', $approved['id'])->value('sale_id'));
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        config(['pos.signing_key_path' => $this->keyPath]);
        $changed = $input;
        $changed['items'][0]['quantity'] = '1';
        try {
            app(CheckoutService::class)->checkout($this->staff, $changed);
            $this->fail('Changed discounted cart accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $sale = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->assertSame('19.26', $sale['total']);
        $this->assertSame($manager->id, $sale['discount_approved_by']);
        $this->assertSame($sale['sale_id'], app(CheckoutService::class)->checkout($this->staff, $input)['sale_id']);
        $input['request_key'] = 'duplicate-discount';
        try {
            app(CheckoutService::class)->checkout($this->staff, $input);
            $this->fail('Discount approval reused.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        app(CheckoutService::class)->closeShift($this->staff, DB::table('pos_shifts')->where('status', 'open')->value('id'), '119.26');
        app(CheckoutService::class)->openShift($manager, $this->terminal, '100.00');
        $refundService = app(RefundService::class);
        $refundCommand = $refundService->command(['order_id' => $sale['sale_id'], 'terminal_id' => $this->terminal, 'request_key' => 'discounted-refund', 'reason' => 'customer_return', 'items' => [['line_id' => $sale['lines'][0]['line_id'], 'quantity' => '1']]]);
        $this->assertSame('9.63', $refundService->refund($manager, $refundCommand, ManagerApproval::verify($manager, 'long-test-password', $refundCommand))['total']);
        $this->assertDatabaseCount('pos_orders', 1);
        $this->assertDatabaseCount('pos_discount_approvals', 1);
    }

    public function test_discount_requires_an_explicit_published_ceiling(): void
    {
        config(['pos.local_discounts_enabled' => true]);
        $this->staff->update(['role' => 'manager']);
        $service = app(DiscountService::class);
        $command = $service->command($this->staff, ['terminal_id' => $this->terminal, 'catalog_version' => 'v1', 'request_key' => 'not-allowed', 'items' => [['product_id' => $this->product, 'quantity' => '2', 'unit' => 'piece', 'discount_amount' => '0.01']]]);
        try {
            $service->approve($this->staff, $this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
            $this->fail('Unpublished discount allowed.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('pos_discount_approvals', 0);
        $this->assertDatabaseCount('pos_orders', 0);
    }

    public function test_out_of_quota_second_sale_does_not_take_cash(): void
    {
        app(CheckoutService::class)->checkout($this->staff, $this->input());
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $this->input('second'))->assertUnprocessable();
        $this->assertDatabaseCount('pos_orders', 1);
    }

    public function test_client_price_is_ignored_and_conflicting_retry_rejected(): void
    {
        $input = $this->input();
        $input['items'][0]['price'] = '0.01';
        $sale = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->assertSame('21.40', $sale['total']);
        $input['cash_tendered'] = '100.00';
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $input)->assertConflict();
    }

    public function test_foreign_branch_and_unverified_connector_are_denied(): void
    {
        $this->putJson('/connector/v1/grants/'.Str::uuid(), ['branch_id' => $this->branch])->assertUnauthorized();
        $this->withServerVariables(['SSL_CLIENT_VERIFY' => 'SUCCESS', 'SSL_CLIENT_FINGERPRINT' => 'abc'])->withHeader('X-POS-Instance', config('pos.instance_id'));
        config(['pos.connector_fingerprint' => 'abc', 'pos.connector_scopes' => ['grants:manage']]);
        $this->putJson('/connector/v1/grants/'.Str::uuid(), ['branch_id' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_kitchen_cannot_checkout(): void
    {
        $this->staff->update(['role' => 'kitchen']);
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $this->input())->assertForbidden();
    }

    public function test_stale_catalog_and_expired_grant_are_blocked(): void
    {
        DB::table('pos_catalogs')->update(['valid_until' => now()->subMinute()]);
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $this->input())->assertUnprocessable();
        DB::table('pos_catalogs')->update(['valid_until' => now()->addDay()]);
        DB::table('pos_grants')->update(['expires_at' => now()->subDay()->toDateString()]);
        $this->postJson('/checkout', $this->input())->assertUnprocessable();
        $this->assertDatabaseCount('pos_orders', 0);
    }

    public function test_second_terminal_uses_the_same_branch_entitlement(): void
    {
        app(CheckoutService::class)->checkout($this->staff, $this->input());
        $terminal = (string) Str::uuid();
        DB::table('pos_terminals')->insert(['id' => $terminal, 'branch_id' => $this->branch, 'code' => 'T2']);
        app(CheckoutService::class)->openShift($this->staff, $terminal, '0.00');
        $input = $this->input('other-terminal');
        $input['terminal_id'] = $terminal;
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $input)->assertUnprocessable();
        $this->assertDatabaseCount('pos_orders', 1);
    }

    public function test_production_checkout_is_closed_before_release_acceptance(): void
    {
        $this->app['env'] = 'production';
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time(), '_token' => 'production-gate-test-token'])->withHeader('X-CSRF-TOKEN', 'production-gate-test-token')->postJson('/checkout', $this->input())->assertStatus(503);
        $this->assertDatabaseCount('pos_orders', 0);
    }

    public function test_pharmacy_selling_unit_uses_base_quantity_and_pharmacist_gate(): void
    {
        $this->pharmacyCatalog();
        $input = $this->input('pharmacy-strip');
        $input['catalog_version'] = 'pharmacy-v1';
        $input['items'][0]['unit'] = 'strip';
        $input['items'][0]['quantity'] = '1';
        $input['cash_tendered'] = '100.00';
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $input)->assertForbidden();
        $this->staff->update(['role' => 'pharmacist']);
        $sale = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->assertSame('85.60', $sale['total']);
        $this->assertSame('10.0000', $sale['lines'][0]['base_quantity']);
        $this->assertSame('conversion-v1', $sale['lines'][0]['conversion_version']);
        $this->assertSame($this->staff->id, $sale['lines'][0]['pharmacist_id']);
        $this->assertSame(10.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
    }

    public function test_fractional_tablet_is_rejected_but_ordinary_pharmacy_goods_need_no_pharmacist(): void
    {
        $this->pharmacyCatalog();
        $this->staff->update(['role' => 'pharmacist']);
        $input = $this->input('fractional-tablet');
        $input['catalog_version'] = 'pharmacy-v1';
        $input['items'][0]['quantity'] = '0.5';
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $input)->assertUnprocessable();
        $this->staff->update(['role' => 'cashier']);
        $input['catalog_version'] = 'v1';
        $input['items'][0]['quantity'] = '1';
        $input['request_key'] = 'ordinary-goods';
        $this->postJson('/checkout', $input)->assertOk();
    }

    public function test_freeze_stops_new_consumption_and_duplicate_freeze_returns_same_evidence(): void
    {
        config(['pos.local_quota_recovery_enabled' => true]);
        $grant = DB::table('pos_grants')->first();
        $proof = app(ProvisioningService::class)->freezeGrant($grant->id, 1);
        $this->assertSame('frozen', $proof['status']);
        $this->assertSame('0.0000', $proof['consumed_quantity']);
        $this->assertSame(CanonicalJson::encode($proof), CanonicalJson::encode(app(ProvisioningService::class)->freezeGrant($grant->id, 1)));
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $this->input())->assertUnprocessable();
        $this->assertDatabaseCount('pos_orders', 0);
    }

    public function test_liquid_precision_is_exact_without_integer_only_assumption(): void
    {
        config(['pos.local_pharmacy_enabled' => true]);
        $product = ['unit' => 'ml', 'price' => '1.00', 'pharmacy' => ['requires_pharmacist' => false, 'base_step' => '0.001', 'units' => []]];
        $line = app(PharmacyPolicy::class)->resolve($this->staff, 'pharmacy', $product, ['quantity' => '0.125']);
        $this->assertSame('0.1250', $line['base_quantity']);
    }

    public function test_optional_prescription_quarantine_rolls_back_checkout_until_scanned(): void
    {
        $this->pharmacyCatalog();
        $this->staff->update(['role' => 'pharmacist']);
        Storage::fake('prescription_fixtures');
        $content = "%PDF-1.4\n% SYNTHETIC CHECKOUT FIXTURE ONLY\n%%EOF";
        config(['pos.synthetic_prescriptions_enabled' => true, 'pos.prescription_retention_days' => 30, 'pos.synthetic_prescription_hashes' => [hash('sha256', $content)]]);
        $fixture = app(PrescriptionService::class)->upload($this->staff, UploadedFile::fake()->createWithContent('synthetic.pdf', $content));
        $input = $this->input('with-prescription');
        $input['prescription_ids'] = [$fixture['id']];
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $input)->assertUnprocessable();
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('pos_outbox', 0);
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        $scanner = \Mockery::mock(PrescriptionScanner::class);
        $scanner->shouldReceive('isClean')->once()->andReturn(true);
        $this->app->instance(PrescriptionScanner::class, $scanner);
        app(PrescriptionService::class)->scan($fixture['id']);
        $sale = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->assertSame($sale['sale_id'], DB::table('pos_prescriptions')->value('order_id'));
    }

    public function test_public_request_is_deduplicated_and_does_not_take_cash_before_acceptance(): void
    {
        $menu = $this->menuCatalog();
        $service = app(PublicMenuService::class);
        $request = $service->submit($menu['token'], 'customer-one', [['product_id' => $this->product, 'quantity' => '2', 'price' => '0.01']]);
        $this->assertSame($request, $service->submit($menu['token'], 'customer-one', [['product_id' => $this->product, 'quantity' => '2.0000']]));
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        $catalog = $service->catalog($menu['token']);
        $this->assertEqualsCanonicalizing(['product_id', 'name', 'unit', 'price', 'tax_rate'], array_keys($catalog['items'][0]));
        $this->assertSame('10.00', $catalog['items'][0]['price']);
        $this->assertStringStartsWith('data:image/svg+xml', $menu['qr_data_uri']);
        $input = $this->input('public-sale');
        $input['catalog_version'] = 'menu-v1';
        $input['customer_request_id'] = $request['id'];
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $input)->assertConflict();
        $service->accept($this->staff, $request['id']);
        $sale = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->assertSame('21.40', $sale['total']);
        $this->assertDatabaseHas('pos_customer_requests', ['id' => $request['id'], 'sale_id' => $sale['sale_id'], 'status' => 'completed']);
        $input['request_key'] = 'different-key';
        $this->postJson('/checkout', $input)->assertConflict();
        $this->assertDatabaseCount('pos_orders', 1);
    }

    public function test_public_token_revocation_and_catalog_change_require_new_staff_review(): void
    {
        $menu = $this->menuCatalog();
        $service = app(PublicMenuService::class);
        $request = $service->submit($menu['token'], 'customer-one', [['product_id' => $this->product, 'quantity' => '2']]);
        $service->accept($this->staff, $request['id']);
        app(ProvisioningService::class)->catalog('menu-v2', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $this->product, 'name' => 'New price', 'unit' => 'piece', 'price' => '11.00', 'tax_rate' => '7.00', 'active' => true, 'online_visible' => true]]]);
        $input = $this->input('stale-request');
        $input['catalog_version'] = 'menu-v1';
        $input['customer_request_id'] = $request['id'];
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/checkout', $input)->assertConflict();
        $service->accept($this->staff, $request['id']);
        $input['catalog_version'] = 'menu-v2';
        $sale = app(CheckoutService::class)->checkout($this->staff, $input);
        $this->assertSame('23.54', $sale['total']);
        $this->staff->update(['role' => 'manager']);
        $service->revoke($this->staff, $menu['id']);
        $this->postJson('/menu/catalog', ['token' => $menu['token']])->assertNotFound();
        $this->app['env'] = 'production';
        $this->get('/menu')->assertStatus(503);
    }

    public function test_public_hidden_product_cross_branch_and_expired_token_are_denied(): void
    {
        $menu = $this->menuCatalog();
        $service = app(PublicMenuService::class);
        $this->postJson('/menu/requests', ['token' => $menu['token'], 'request_key' => 'foreign', 'items' => [['product_id' => (string) Str::uuid(), 'quantity' => '1']]])->assertUnprocessable();
        $request = $service->submit($menu['token'], 'one', [['product_id' => $this->product, 'quantity' => '1']]);
        $otherBranch = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $otherBranch, 'code' => 'OTHER', 'name' => 'Other', 'business_type' => 'retail']);
        $other = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $otherBranch, 'name' => 'Other', 'email' => 'other@menu.test', 'password' => 'fixture-password-only', 'role' => 'cashier', 'active' => true]);
        $this->actingAs($other)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])->postJson('/customer-requests/'.$request['id'].'/accept')->assertNotFound();
        $service->reject($this->staff, $request['id']);
        $this->assertSame('rejected', $service->status($menu['token'], $request['id'])['status']);
        DB::table('pos_menu_tokens')->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/menu/catalog', ['token' => $menu['token']])->assertNotFound();
        $this->assertDatabaseCount('pos_orders', 0);
    }

    private function menuCatalog(): array
    {
        config(['pos.local_public_menu_enabled' => true, 'app.url' => 'http://localhost']);
        app(ProvisioningService::class)->catalog('menu-v1', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $this->product, 'name' => 'Public product', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true, 'online_visible' => true, 'cost' => 'PRIVATE']]]);
        $this->staff->update(['role' => 'manager']);
        $menu = app(PublicMenuService::class)->issue($this->staff, 60);
        $this->staff->update(['role' => 'cashier']);

        return $menu;
    }

    private function pharmacyCatalog(): void
    {
        config(['pos.local_pharmacy_enabled' => true]);
        DB::table('pos_branches')->where('id', $this->branch)->update(['business_type' => 'pharmacy']);
        app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $this->branch, 'product_id' => $this->product, 'epoch' => 1, 'quantity' => '20.0000']);
        app(ProvisioningService::class)->catalog('pharmacy-v1', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $this->product, 'name' => 'Synthetic product', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true, 'pharmacy' => ['requires_pharmacist' => true, 'base_step' => '1.0000', 'units' => [['unit' => 'strip', 'factor' => '10.0000', 'price' => '80.00', 'conversion_version' => 'conversion-v1']]]]]]);
    }

    public function test_pharmacist_manager_inspection_is_bounded_atomic_and_does_not_restore_quota(): void
    {
        config(['pos.local_pharmacy_enabled' => true, 'pos.local_refunds_enabled' => true, 'pos.local_return_inspections_enabled' => true]);
        DB::table('pos_branches')->where('id', $this->branch)->update(['business_type' => 'pharmacy']);
        $this->staff->forceFill(['role' => 'manager', 'pharmacist_qualified' => true])->save();
        $grant = DB::table('pos_grants')->first();
        DB::table('pos_grants')->where('id', $grant->id)->update(['lot_id' => (string) Str::uuid(), 'expires_at' => now()->addMonth()->toDateString()]);
        $sale = app(CheckoutService::class)->checkout($this->staff, $this->input());
        $refundService = app(RefundService::class);
        $refundCommand = $refundService->command(['order_id' => $sale['sale_id'], 'terminal_id' => $this->terminal, 'request_key' => 'return-inspect', 'reason' => 'customer_return', 'items' => [['line_id' => $sale['lines'][0]['line_id'], 'quantity' => '2.0000', 'returned_quantity' => '2.0000']]]);
        $refund = $refundService->refund($this->staff, $refundCommand, ManagerApproval::verify($this->staff, 'long-test-password', $refundCommand));
        $service = app(ReturnInspectionService::class);
        $command = $service->command(['refund_id' => $refund['refund_id'], 'source_line_id' => $sale['lines'][0]['line_id'], 'grant_id' => $grant->id, 'base_quantity' => '1', 'decision' => 'restock', 'packaging_intact' => true, 'storage_verified' => true, 'lot_verified' => true, 'request_key' => 'inspection-one', 'reason' => 'Synthetic inspection']);
        $this->staff->forceFill(['pharmacist_qualified' => false])->save();
        try {
            $service->inspect($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
            $this->fail('Unqualified manager accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->staff->forceFill(['pharmacist_qualified' => true])->save();
        DB::table('pos_grants')->where('id', $grant->id)->update(['expires_at' => now()->subDay()->toDateString()]);
        try {
            $service->inspect($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
            $this->fail('Expired medicine restocked.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        DB::table('pos_grants')->where('id', $grant->id)->update(['expires_at' => now()->addMonth()->toDateString()]);
        $unverified = array_replace($command, ['storage_verified' => false]);
        try {
            $service->inspect($this->staff, $unverified, ManagerApproval::verify($this->staff, 'long-test-password', $unverified));
            $this->fail('Unverified storage accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        config(['pos.signing_key_path' => 'missing-synthetic-key']);
        try {
            $service->inspect($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
            $this->fail('Unsigned inspection persisted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('signing', $e->getMessage());
        }
        $this->assertDatabaseCount('pos_return_inspections', 0);
        config(['pos.signing_key_path' => $this->keyPath]);
        $result = $service->inspect($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
        $this->assertEquals($result, $service->inspect($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command)));
        $command['request_key'] = 'inspection-two';
        $command['decision'] = 'destroy';
        $service->inspect($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
        $command['request_key'] = 'inspection-over';
        try {
            $service->inspect($this->staff, $command, ManagerApproval::verify($this->staff, 'long-test-password', $command));
            $this->fail('Over-inspection accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('pos_return_inspections', 2);
        $this->assertSame('2', (string) DB::table('pos_grants')->value('consumed_quantity'));
        $this->assertDatabaseCount('pos_outbox', 4);
    }

    public function test_abb_full_replacement_uses_one_sale_keeps_source_and_pending_provider(): void
    {
        config(['pos.local_tax_documents_enabled' => true]);
        $service = app(TaxDocumentService::class);
        $service->provision(['branch_id' => $this->branch, 'version' => 'issuer-v1', 'seller_name' => 'Synthetic seller', 'seller_tax_id' => '0000000000000', 'seller_address' => 'Synthetic address', 'seller_branch_no' => '00000']);
        $sale = app(CheckoutService::class)->checkout($this->staff, $this->input());
        $abb = DB::table('pos_tax_documents')->firstOrFail();
        $this->assertStringStartsWith('ABB-B1-T1-', $abb->document_no);
        $this->assertSame('awaiting_provider', DB::table('pos_etax_queue')->value('status'));
        $input = ['sale_id' => $sale['sale_id'], 'terminal_id' => $this->terminal, 'kind' => 'FULL', 'request_key' => 'full-one', 'buyer' => ['name' => 'Synthetic buyer', 'tax_id' => '0000000000000', 'branch_no' => '00000', 'address' => 'Synthetic address']];
        $originalSnapshot = $abb->snapshot;
        config(['pos.signing_key_path' => 'missing-key']);
        try {
            $service->issue($this->staff, $input);
            $this->fail('Unsigned Full persisted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('signing', $e->getMessage());
        }
        $this->assertDatabaseCount('pos_tax_documents', 1);
        $this->assertSame('issued', DB::table('pos_tax_documents')->value('status'));
        config(['pos.signing_key_path' => $this->keyPath]);
        $full = $service->issue($this->staff, $input);
        $this->assertEquals($full, $service->issue($this->staff, $input));
        $this->assertSame($abb->id, $full['replaces_document_id']);
        $this->assertSame('21.40', $full['total']);
        $this->assertSame($originalSnapshot, DB::table('pos_tax_documents')->where('id', $abb->id)->value('snapshot'));
        $this->assertSame('superseded', DB::table('pos_tax_documents')->where('id', $abb->id)->value('status'));
        $this->assertDatabaseCount('pos_orders', 1);
        $this->assertDatabaseCount('pos_payments', 1);
        $this->assertDatabaseCount('pos_tax_documents', 2);
        $this->assertDatabaseCount('pos_outbox', 3);
        $this->assertSame(0, DB::table('pos_etax_queue')->where('status', 'accepted')->count());
        $input['request_key'] = 'duplicate-full';
        try {
            $service->issue($this->staff, $input);
            $this->fail('Second Full original issued.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_paid_void_is_a_full_source_refund_with_manager_reauthentication_and_no_restock(): void
    {
        config(['pos.local_refunds_enabled' => true]);
        $this->staff->forceFill(['role' => 'manager'])->save();
        $sale = app(CheckoutService::class)->checkout($this->staff, $this->input());
        $this->actingAs($this->staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()]);
        $command = ['order_id' => $sale['sale_id'], 'terminal_id' => $this->terminal, 'request_key' => 'void-sale-one', 'manager_password' => 'incorrect-password'];
        $this->postJson('/local-voids', $command)->assertForbidden();
        $command['manager_password'] = 'long-test-password';
        $this->postJson('/local-voids', $command)->assertOk()->assertJsonPath('total', '21.40');
        $this->postJson('/local-voids', $command)->assertOk()->assertJsonPath('total', '21.40');
        $this->assertDatabaseCount('pos_refunds', 1);
        $this->assertDatabaseCount('pos_orders', 1);
        $this->assertSame(2.0, (float) DB::table('pos_grants')->value('consumed_quantity'));
        $this->assertSame(1, DB::table('pos_audit')->where('action', 'sale.void')->count());
        $this->assertSame('not_returned', DB::table('pos_refund_lines')->value('return_status'));
    }

    private function input(string $key = 'checkout-one'): array
    {
        return ['terminal_id' => $this->terminal, 'request_key' => $key, 'catalog_version' => 'v1', 'items' => [['product_id' => $this->product, 'quantity' => '2.0000']], 'cash_tendered' => '30.00'];
    }
}
