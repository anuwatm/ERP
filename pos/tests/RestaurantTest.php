<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\CheckoutService;
use Pos\Services\EntitlementAllocator;
use Pos\Services\ManagerApproval;
use Pos\Services\ProvisioningService;
use Pos\Services\PublicMenuService;
use Pos\Services\RestaurantService;
use Pos\Support\CanonicalJson;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class RestaurantTest extends TestCase
{
    use RefreshDatabase;

    private static string $keyPath;

    private string $branch;

    private string $terminal;

    private string $menu;

    private string $ingredient;

    private Staff $manager;

    private Staff $cashier;

    private Staff $kitchen;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$keyPath = tempnam(sys_get_temp_dir(), 'pos-restaurant-key-');
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $config = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (file_exists($config)) {
            $options['config'] = $config;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);
        file_put_contents(self::$keyPath, $pem);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$keyPath) && file_exists(self::$keyPath)) {
            unlink(self::$keyPath);
        } parent::tearDownAfterClass();
    }

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
        $this->menu = (string) Str::uuid();
        $this->ingredient = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $this->branch, 'code' => 'REST', 'name' => 'Synthetic kitchen', 'business_type' => 'restaurant']);
        DB::table('pos_terminals')->insert(['id' => $this->terminal, 'branch_id' => $this->branch, 'code' => 'R1']);
        foreach (['manager', 'cashier', 'kitchen'] as $role) {
            $this->$role = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $this->branch, 'name' => $role, 'email' => $role.'@restaurant.test', 'password' => 'synthetic-manager-password', 'role' => $role, 'active' => true]);
        }
        config(['pos.instance_id' => (string) Str::uuid(), 'pos.signing_key_id' => 'synthetic-key', 'pos.signing_key_path' => self::$keyPath, 'pos.connector_branch_ids' => [$this->branch], 'pos.local_restaurant_enabled' => true, 'pos.local_quota_recovery_enabled' => true]);
        $recipe = ['kind' => 'recipe', 'recipe_id' => (string) Str::uuid(), 'recipe_version' => 'recipe-1', 'yield_quantity' => '1.0000', 'yield_unit' => 'plate', 'ingredients' => [['product_id' => $this->ingredient, 'unit' => 'kg', 'quantity' => '0.5000']]];
        $recipe['recipe_hash'] = hash('sha256', CanonicalJson::encode($recipe));
        app(ProvisioningService::class)->catalog('menu-v1', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $this->menu, 'name' => 'Synthetic plate', 'unit' => 'plate', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true, 'restaurant' => $recipe]]]);
        app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $this->branch, 'product_id' => $this->ingredient, 'epoch' => 1, 'quantity' => '2.0000']);
        app(CheckoutService::class)->openShift($this->cashier, $this->terminal, '100.00');
    }

    private function order(string $key, int $quantity = 2): array
    {
        return app(RestaurantService::class)->createOrder($this->cashier, ['terminal_id' => $this->terminal, 'catalog_version' => 'menu-v1', 'request_key' => $key, 'channel' => 'takeaway', 'items' => [['product_id' => $this->menu, 'quantity' => $quantity]]]);
    }

    private function served(string $id): void
    {
        $s = app(RestaurantService::class);
        $s->advance($this->cashier, $id, 'sent');
        $s->advance($this->kitchen, $id, 'acknowledged');
        $s->advance($this->kitchen, $id, 'preparing');
        $s->advance($this->kitchen, $id, 'ready');
        $s->advance($this->cashier, $id, 'served');
    }

    public function test_reservation_blocks_other_consumers_and_freeze_until_manager_releases(): void
    {
        $s = app(RestaurantService::class);
        $order = $this->order('reserve');
        $this->assertEquals($order, $this->order('reserve'));
        $this->assertSame(1.0, (float) DB::table('pos_grants')->sum('reserved_quantity'));
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        try {
            DB::transaction(fn () => app(EntitlementAllocator::class)->take($this->branch, $this->ingredient, '2.0000', 'Asia/Bangkok'));
            $this->fail('Reserved stock sold twice.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        try {
            app(ProvisioningService::class)->freezeGrant(DB::table('pos_grants')->value('id'), 1);
            $this->fail('Reserved grant frozen.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        foreach ($order['line_ids'] as $id) {
            $cmd = $s->cancelCommand(['line_id' => $id, 'reason' => 'customer_cancel', 'physical_waste' => false]);
            $approval = ManagerApproval::verify($this->manager, 'synthetic-manager-password', $cmd);
            $s->cancel($this->manager, $cmd, $approval);
            $s->cancel($this->manager, $cmd, $approval);
        }
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('reserved_quantity'));
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        $this->assertDatabaseCount('pos_outbox', 0);
    }

    public function test_kitchen_consumes_once_and_split_merge_checkout_never_consumes_again(): void
    {
        $s = app(RestaurantService::class);
        $order = $this->order('split');
        foreach ($order['line_ids'] as $id) {
            $this->served($id);
            $s->advance($this->kitchen, $id, 'preparing');
        }
        $this->assertSame(1.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        $this->assertDatabaseCount('pos_outbox', 2);
        foreach ($order['line_ids'] as $index => $id) {
            $command = ['terminal_id' => $this->terminal, 'request_key' => 'bill-'.$index, 'cash_tendered' => '30.00', 'kitchen_line_ids' => [$id]];
            $sale = app(CheckoutService::class)->checkout($this->cashier, $s->billingInput($this->cashier, $command));
            $this->assertSame('10.70', $sale['total']);
            $this->assertSame([], $sale['lines'][0]['allocations']);
            $this->assertSame('prepared', $sale['lines'][0]['stock_effect']);
            $this->assertSame($sale['sale_id'], app(CheckoutService::class)->checkout($this->cashier, $s->billingInput($this->cashier, $command))['sale_id']);
        }
        $one = $this->order('merge-one', 1);
        $two = $this->order('merge-two', 1);
        $ids = array_merge($one['line_ids'], $two['line_ids']);
        foreach ($ids as $id) {
            $this->served($id);
        }
        $sale = app(CheckoutService::class)->checkout($this->cashier, $s->billingInput($this->cashier, ['terminal_id' => $this->terminal, 'request_key' => 'merged', 'cash_tendered' => '30.00', 'kitchen_line_ids' => $ids]));
        $this->assertSame('21.40', $sale['total']);
        $this->assertCount(2, $sale['lines'][0]['kitchen_refs']);
        $this->assertSame(2.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        $this->assertDatabaseCount('pos_orders', 3);
        $this->assertDatabaseCount('pos_outbox', 7);
    }

    public function test_size_modifier_sku_uses_frozen_recipe_price_and_settles_after_catalog_expiry(): void
    {
        $parent = json_decode(DB::table('pos_catalogs')->value('items'), true)[0];
        $variant = $parent;
        $variant['product_id'] = (string) Str::uuid();
        $variant['name'] = 'Large plate with egg';
        $variant['price'] = '15.00';
        $variant['restaurant_variant'] = ['parent_product_id' => $this->menu, 'label' => 'Large + egg', 'size' => 'large', 'modifiers' => ['egg']];
        unset($variant['restaurant']['recipe_hash']);
        $variant['restaurant']['ingredients'][0]['quantity'] = '1.0000';
        $variant['restaurant']['recipe_version'] = 'large-egg-v1';
        $variant['restaurant']['recipe_hash'] = hash('sha256', CanonicalJson::encode($variant['restaurant']));
        app(ProvisioningService::class)->catalog('options-v1', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [$parent, $variant]]);
        $service = app(RestaurantService::class);
        $order = $service->createOrder($this->cashier, ['terminal_id' => $this->terminal, 'catalog_version' => 'options-v1', 'request_key' => 'option-order', 'channel' => 'takeaway', 'items' => [['product_id' => $variant['product_id'], 'quantity' => 1]]]);
        $this->served($order['line_ids'][0]);
        DB::table('pos_catalogs')->where('version', 'options-v1')->update(['valid_until' => now()->subSecond()]);
        $sale = app(CheckoutService::class)->checkout($this->cashier, $service->billingInput($this->cashier, ['terminal_id' => $this->terminal, 'request_key' => 'option-bill', 'cash_tendered' => '20.00', 'kitchen_line_ids' => $order['line_ids']]));
        $this->assertSame('16.05', $sale['total']);
        $this->assertSame(1.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        $consumed = json_decode(DB::table('pos_outbox')->where('event_type', 'kitchen.consumed')->value('envelope'), true);
        $this->assertSame('1.0000', $consumed['payload']['ingredients'][0]['quantity']);
        $this->assertSame($variant['restaurant']['recipe_hash'], $consumed['payload']['recipe_hash']);
    }

    public function test_table_menu_request_is_reserved_once_and_old_table_qr_cannot_order_again(): void
    {
        config(['pos.local_public_menu_enabled' => true, 'app.url' => 'http://127.0.0.1:8128']);
        $product = json_decode(DB::table('pos_catalogs')->value('items'), true)[0];
        $product['online_visible'] = true;
        app(ProvisioningService::class)->catalog('online-v1', ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [$product]]);
        $service = app(RestaurantService::class);
        $menu = app(PublicMenuService::class);
        $table = $service->createTable($this->manager, 'Synthetic table');
        $session = $service->openTable($this->cashier, $table);
        $qr = $menu->issue($this->manager, 60, $session);
        $request = $menu->submit($qr['token'], 'request-one', [['product_id' => $this->menu, 'quantity' => '1']]);
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('reserved_quantity'));
        $menu->accept($this->cashier, $request['id']);
        $order = $service->fromCustomerRequest($this->cashier, $request['id'], $this->terminal);
        $this->assertEquals($order, $service->fromCustomerRequest($this->cashier, $request['id'], $this->terminal));
        $this->assertSame(0.5, (float) DB::table('pos_grants')->sum('reserved_quantity'));
        $this->assertDatabaseCount('pos_kitchen_orders', 1);
        $cmd = $service->cancelCommand(['line_id' => $order['line_ids'][0], 'reason' => 'customer_cancel', 'physical_waste' => false]);
        $service->cancel($this->manager, $cmd, ManagerApproval::verify($this->manager, 'synthetic-manager-password', $cmd));
        $this->assertSame('cancelled', $menu->status($qr['token'], $request['id'])['status']);
        $service->closeTable($this->cashier, $session);
        $service->openTable($this->cashier, $table);
        $this->postJson('/menu/catalog', ['token' => $qr['token']])->assertNotFound();
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('reserved_quantity'));
    }

    public function test_kitchen_passive_poll_does_not_extend_staff_idle_timeout(): void
    {
        $lastSeen = time() - 30;
        $this->actingAs($this->kitchen)->withSession(['pos_login_at' => time(), 'pos_last_seen' => $lastSeen])->withHeaders(['X-POS-Passive' => '1'])->getJson('/state')->assertOk()->assertSessionHas('pos_last_seen', $lastSeen);
        $this->withHeaders(['X-POS-Passive' => '0'])->getJson('/state')->assertOk()->assertSessionHas('pos_last_seen', fn ($value) => $value > $lastSeen);
    }

    public function test_waste_before_and_after_preparation_use_one_stock_path(): void
    {
        $s = app(RestaurantService::class);
        $order = $this->order('waste');
        $first = $order['line_ids'][0];
        $second = $order['line_ids'][1];
        $cmd = $s->cancelCommand(['line_id' => $first, 'reason' => 'spoiled', 'physical_waste' => true]);
        $s->cancel($this->manager, $cmd, ManagerApproval::verify($this->manager, 'synthetic-manager-password', $cmd));
        $s->advance($this->cashier, $second, 'sent');
        $s->advance($this->kitchen, $second, 'acknowledged');
        $s->advance($this->kitchen, $second, 'preparing');
        $cmd = $s->cancelCommand(['line_id' => $second, 'reason' => 'preparation_error', 'physical_waste' => true]);
        $approval = ManagerApproval::verify($this->manager, 'synthetic-manager-password', $cmd);
        $s->cancel($this->manager, $cmd, $approval);
        $s->cancel($this->manager, $cmd, $approval);
        $this->assertSame(1.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
        $this->assertSame(0.0, (float) DB::table('pos_grants')->sum('reserved_quantity'));
        $this->assertDatabaseCount('pos_outbox', 3);
        $this->assertDatabaseCount('pos_kitchen_waste', 2);
        $this->assertDatabaseCount('pos_orders', 0);
        $types = DB::table('pos_outbox')->pluck('event_type')->all();
        $this->assertSame(['kitchen.consumed', 'kitchen.consumed', 'kitchen.wasted'], $types);
    }
}
