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
use Pos\Services\DeliveryService;
use Pos\Services\EntitlementAllocator;
use Pos\Services\ManagerApproval;
use Pos\Services\MembershipService;
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

    public function test_cod_delivery_uses_existing_kitchen_and_waste_never_consumes_ingredients_twice(): void
    {
        config(['pos.local_delivery_enabled' => true]);
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->cashier, ['request_key' => (string) Str::uuid(), 'settlement_mode' => 'cod', 'recipient' => 'Synthetic diner', 'phone' => '000-TEST', 'address' => 'Synthetic address', 'catalog_version' => 'menu-v1', 'items' => [['product_id' => $this->menu, 'quantity' => '1']]]);
        $step = function (Staff $actor, string $action, array $data = []) use ($service, &$delivery) {
            $delivery = $service->transition($actor, $delivery->id, $data + ['request_key' => (string) Str::uuid(), 'action' => $action, 'version' => $delivery->version]);
        };
        $step($this->cashier, 'ready', ['terminal_id' => $this->terminal]);
        $line = $delivery->snapshot['kitchen_line_ids'][0];
        $this->served($line);
        $consumed = DB::table('pos_grants')->value('consumed_quantity');
        $step($this->cashier, 'dispatch', ['courier_id' => $this->cashier->id]);
        $step($this->cashier, 'fail', ['reason' => 'recipient_absent']);
        $step($this->cashier, 'receive');
        $step($this->manager, 'inspect', ['decision' => 'destroy', 'condition_verified' => true, 'manager_password' => 'synthetic-manager-password']);
        $this->assertEquals($consumed, DB::table('pos_grants')->value('consumed_quantity'));
        $this->assertSame(1, DB::table('pos_outbox')->where('event_type', 'kitchen.consumed')->count());
        $this->assertSame(1, DB::table('pos_outbox')->where('event_type', 'kitchen.wasted')->count());
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertSame('wasted', DB::table('pos_kitchen_lines')->where('id', $line)->value('status'));
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

    public function test_kitchen_queue_keeps_acceptance_order_in_the_same_second_and_on_replay(): void
    {
        $this->freezeTime();
        $first = $this->order('first-queue', 1);
        $second = $this->order('second-queue', 1);
        $this->assertSame(1, $first['queue_number']);
        $this->assertSame(2, $second['queue_number']);
        $this->assertEquals($first, $this->order('first-queue', 1));

        $this->actingAs($this->kitchen)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()])
            ->getJson('/state')->assertOk()
            ->assertJsonPath('restaurant_lines.0.order_id', $first['order_id'])
            ->assertJsonPath('restaurant_lines.0.queue_number', 1)
            ->assertJsonPath('restaurant_lines.1.order_id', $second['order_id'])
            ->assertJsonPath('restaurant_lines.1.queue_number', 2);
    }

    public function test_kitchen_passive_poll_does_not_extend_staff_idle_timeout(): void
    {
        $lastSeen = time() - 30;
        $this->actingAs($this->kitchen)->withSession(['pos_login_at' => time(), 'pos_last_seen' => $lastSeen])->withHeaders(['X-POS-Passive' => '1'])->getJson('/state')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSessionHas('pos_last_seen', $lastSeen);
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

    public function test_restaurant_promotion_uses_frozen_bill_and_never_consumes_ingredients_twice(): void
    {
        config(['pos.local_membership_enabled' => true]);
        $p = ['id' => (string) Str::uuid(), 'version' => 1, 'mode' => 'fixed', 'value' => '2.00', 'currency' => 'THB', 'starts_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'ends_at' => now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'branch_ids' => [$this->branch], 'business_types' => ['restaurant'], 'member_required' => false, 'member_tiers' => [], 'product_ids' => [], 'minimum_spend' => '0.00', 'minimum_eligible_only' => true, 'minimum_components' => ['merchandise'], 'discount_components' => ['merchandise'], 'max_uses' => null, 'per_member_limit' => null, 'combination' => 'exclusive', 'refund_entitlement' => 'full_only'];
        $pub = ['id' => (string) Str::uuid(), 'instance_id' => config('pos.instance_id'), 'branch_id' => $this->branch, 'version' => 1, 'created_at' => now()->utc()->toIso8601String(), 'valid_until' => now()->addMinutes(15)->utc()->toIso8601String(), 'members' => [], 'policies' => [['name' => 'Synthetic kitchen promotion', 'policy' => $p]], 'rights' => []];
        app(MembershipService::class)->provision($pub['id'], $pub);
        $order = $this->order('membership-kitchen', 1);
        $this->served($order['line_ids'][0]);
        $before = DB::table('pos_grants')->value('consumed_quantity');
        $service = app(RestaurantService::class);
        $bill = $service->billingInput($this->cashier, ['terminal_id' => $this->terminal, 'kitchen_line_ids' => $order['line_ids'], 'request_key' => 'member-bill', 'cash_tendered' => '20.00', 'membership_publication_id' => $pub['id'], 'promotion_id' => $p['id']]);
        $sale = app(CheckoutService::class)->checkout($this->cashier, $bill);
        $this->assertSame('8.56', $sale['total']);
        $this->assertSame($before, DB::table('pos_grants')->value('consumed_quantity'));
        $this->assertSame('prepared', $sale['lines'][0]['stock_effect']);
        $this->assertDatabaseCount('pos_promotion_claims', 1);
    }

    private function loginRestaurant(Staff $staff): void
    {
        $this->actingAs($staff)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()]);
    }

    public function test_seating_reservation_window_fifo_idempotent_assignment_and_branch_gate(): void
    {
        $this->freezeTime();
        $service = app(RestaurantService::class);
        $small = $service->createTable($this->manager, 'Small', 2);
        $large = $service->createTable($this->manager, 'Large', 6);
        $this->loginRestaurant($this->cashier);
        $book = ['request_key' => (string) Str::uuid(), 'kind' => 'reservation', 'party_size' => 2, 'table_id' => $small, 'booked_at' => now()->addMinutes(10)->utc()->format('Y-m-d\TH:i:s.v\Z'), 'contact' => 'synthetic-only'];
        $reservation = $this->postJson('/restaurant/seating', $book)->assertOk()->json('id');
        $this->postJson('/restaurant/seating', $book)->assertOk()->assertJsonPath('id', $reservation);
        $this->postJson('/restaurant/seating', array_replace($book, ['request_key' => (string) Str::uuid(), 'booked_at' => now()->addMinutes(80)->utc()->format('Y-m-d\TH:i:s.v\Z')]))->assertConflict();
        $this->assertStringNotContainsString('synthetic-only', DB::table('pos_seating_entries')->where('id', $reservation)->value('contact'));
        $this->getJson('/restaurant/seating')->assertOk()->assertJsonPath('entries.0.contact', 'synthetic-only');
        $first = $this->postJson('/restaurant/seating', ['request_key' => (string) Str::uuid(), 'kind' => 'walk_in', 'party_size' => 4])->assertOk()->json('id');
        $second = $this->postJson('/restaurant/seating', ['request_key' => (string) Str::uuid(), 'kind' => 'walk_in', 'party_size' => 2])->assertOk()->json('id');
        $this->postJson("/restaurant/seating/{$second}/seat", ['table_id' => $large])->assertConflict();
        $this->postJson("/restaurant/seating/{$second}/seat", ['table_id' => $small])->assertConflict();
        $this->postJson("/restaurant/tables/{$small}/open")->assertConflict();
        $this->postJson("/restaurant/seating/{$reservation}/no_show")->assertConflict();
        $this->travel(25)->minutes();
        $this->postJson("/restaurant/seating/{$reservation}/no_show")->assertOk();
        $session = $this->postJson("/restaurant/seating/{$second}/seat", ['table_id' => $small])->assertOk()->json('session_id');
        $this->postJson("/restaurant/seating/{$second}/seat", ['table_id' => $small])->assertOk()->assertJsonPath('session_id', $session);
        $this->postJson("/restaurant/seating/{$second}/seat", ['table_id' => $large])->assertConflict();
        $this->postJson("/restaurant/seating/{$first}/seat", ['table_id' => $small])->assertConflict();
        $largeSession = $this->postJson("/restaurant/seating/{$first}/seat", ['table_id' => $large])->assertOk()->json('session_id');
        $this->assertDatabaseCount('pos_table_sessions', 2);
        $this->postJson("/restaurant/sessions/{$largeSession}/close")->assertOk();
        $arrival = $this->postJson('/restaurant/seating', array_replace($book, [
            'request_key' => (string) Str::uuid(), 'table_id' => $large, 'booked_at' => now()->addMinutes(10)->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ]))->assertOk()->json('id');
        $this->postJson("/restaurant/seating/{$arrival}/arrive")->assertOk()->assertJsonPath('status', 'arrived');
        $this->postJson("/restaurant/seating/{$arrival}/seat", ['table_id' => $large])->assertConflict();
        $this->travel(10)->minutes();
        $this->postJson("/restaurant/seating/{$arrival}/seat", ['table_id' => $large])->assertOk()->assertJsonPath('status', 'seated');
        $this->postJson("/restaurant/sessions/{$session}/close")->assertOk();
        $this->assertNull(DB::table('pos_table_sessions')->where('id', $session)->value('active_slot'));
        $this->loginRestaurant($this->kitchen);
        $this->getJson('/restaurant/seating')->assertForbidden();
        DB::table('pos_branches')->where('id', $this->branch)->update(['business_type' => 'retail']);
        $this->loginRestaurant($this->cashier);
        $this->getJson('/restaurant/seating')->assertNotFound();
    }

    public function test_manager_hold_and_skip_are_audited_and_kitchen_cannot_override_fifo(): void
    {
        $this->freezeTime();
        $first = $this->order('first', 1);
        $second = $this->order('second', 1);
        $third = $this->order('third', 1);
        $line = $second['line_ids'][0];
        $lastLine = $third['line_ids'][0];
        $service = app(RestaurantService::class);
        foreach ([$line, $lastLine] as $id) {
            $service->advance($this->cashier, $id, 'sent');
            $service->advance($this->kitchen, $id, 'acknowledged');
        }
        $this->loginRestaurant($this->kitchen);
        $this->postJson("/restaurant/lines/{$line}/preparing")->assertConflict();
        $this->postJson("/restaurant/lines/{$line}/preparing", ['reason' => 'not permitted'])->assertForbidden();
        $this->postJson("/restaurant/orders/{$first['order_id']}/hold", ['held' => true, 'reason' => 'not permitted'])->assertForbidden();
        $this->loginRestaurant($this->manager);
        $this->postJson("/restaurant/orders/{$first['order_id']}/hold", ['held' => true, 'reason' => 'customer asked to wait'])->assertOk();
        $this->postJson("/restaurant/lines/{$lastLine}/preparing", ['reason' => 'approved priority'])->assertOk();
        $this->postJson("/restaurant/lines/{$lastLine}/preparing", ['reason' => 'approved priority'])->assertOk();
        $this->assertSame(1, DB::table('pos_audit')->where('action', 'restaurant.queue.skip')->count());
        $this->loginRestaurant($this->kitchen);
        $this->postJson("/restaurant/lines/{$line}/preparing")->assertOk();
        $this->assertSame(1.0, (float) DB::table('pos_grants')->sum('consumed_quantity'));
    }

    public function test_restaurant_qr_uses_reviewed_catalog_and_accepts_once_with_progress(): void
    {
        config(['pos.local_public_menu_enabled' => true, 'app.url' => 'http://127.0.0.1:8128']);
        $product = json_decode(DB::table('pos_catalogs')->value('items'), true)[0];
        $product['online_visible'] = true;
        $catalog = ['branch_id' => $this->branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [$product]];
        $provision = app(ProvisioningService::class);
        $provision->catalog('qr-v1', $catalog);
        $service = app(RestaurantService::class);
        $session = $service->openTable($this->cashier, $service->createTable($this->manager, 'QR Table'));
        $menu = app(PublicMenuService::class);
        $token = $menu->issue($this->manager, 60, $session)['token'];
        $line = ['product_id' => $this->menu, 'quantity' => '1'];
        $request = $menu->submit($token, 'old-menu', [$line], 'qr-v1');
        $catalog['items'][0]['price'] = '12.00';
        $provision->catalog('qr-v2', $catalog);
        $this->postJson('/menu/requests', ['token' => $token, 'request_key' => 'stale', 'catalog_version' => 'qr-v1', 'items' => [$line]])->assertConflict();
        $this->assertSame($request, $menu->submit($token, 'old-menu', [$line], 'qr-v1'));
        $this->assertSame('menu_changed', $menu->status($token, $request['id'])['review']);
        $this->loginRestaurant($this->cashier);
        $terminal = ['terminal_id' => $this->terminal];
        $this->postJson("/restaurant/customer-requests/{$request['id']}", $terminal)->assertConflict();
        $this->assertDatabaseCount('pos_kitchen_orders', 0);
        $current = $menu->submit($token, 'current-menu', [$line], 'qr-v2');
        $order = $this->postJson("/restaurant/customer-requests/{$current['id']}", $terminal)->assertOk()->json();
        $this->postJson("/restaurant/customer-requests/{$current['id']}", $terminal)->assertOk()->assertJsonPath('order_id', $order['order_id']);
        $this->assertDatabaseCount('pos_kitchen_orders', 1);
        $this->assertSame(['sent' => 1], $menu->status($token, $current['id'])['progress']);
        $this->assertSame(1, $menu->status($token, $current['id'])['queue_number']);
        $this->postJson('/menu/requests', ['token' => $token, 'request_key' => 'too-many', 'catalog_version' => 'qr-v2', 'items' => [array_replace($line, ['quantity' => '4'])]])->assertUnprocessable();
    }

    public function test_pos_login_caps_attempts_across_multiple_source_ips(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $email = $attempt % 2 === 0 ? 'CASHIER@restaurant.test' : 'cashier@restaurant.test';
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->postJson('/login', ['email' => $email, 'password' => 'wrong-password'])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->postJson('/login', ['email' => 'cashier@restaurant.test', 'password' => 'wrong-password'])->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_private_lan_menu_requires_opt_in_https_and_exact_private_origin(): void
    {
        config(['pos.local_public_menu_enabled' => true, 'app.url' => 'https://192.168.1.20:8128']);
        $menu = app(PublicMenuService::class);
        $this->assertFalse($menu->allowsPrivateLan('https://192.168.1.20:8128', '192.168.1.30'));
        config(['pos.local_menu_private_lan_enabled' => true]);
        $this->assertTrue($menu->allowsPrivateLan('https://192.168.1.20:8128', '192.168.1.30'));
        $this->assertFalse($menu->allowsPrivateLan('https://192.168.1.20:8128', '203.0.113.1'));
        $this->assertFalse($menu->allowsPrivateLan('https://192.168.1.20:9999', '192.168.1.30'));
        config(['app.url' => 'http://192.168.1.20:8128']);
        $this->assertFalse($menu->allowsPrivateLan('http://192.168.1.20:8128', '192.168.1.30'));
    }
}
