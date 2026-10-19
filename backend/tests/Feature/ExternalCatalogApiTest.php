<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Division;
use App\Models\IntegrationApiKey;
use App\Models\InventoryLot;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExternalCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(5, 0));
    }

    private function credential(User $user, array $scopes): string
    {
        $prefix = 'erp_'.bin2hex(random_bytes(12));
        $secret = $prefix.'.'.bin2hex(random_bytes(32));
        IntegrationApiKey::create(['org_id' => $user->org_id, 'created_by' => $user->id,
            'name' => 'Catalog fixture', 'prefix' => $prefix, 'secret_hash' => hash('sha256', $secret),
            'scopes' => $scopes, 'expires_at' => now()->addDays(30)]);

        return $secret;
    }

    private function product(User $user, array $values = []): Product
    {
        return Product::create(array_merge(['org_id' => $user->org_id, 'name' => 'Fixture product',
            'type' => 'product', 'unit' => 'pcs', 'price' => '123.45', 'cost' => '67.89',
            'track_inventory' => true, 'reorder_point' => '5.0000'], $values));
    }

    private function movement(User $user, Product $product, string $quantity, array $values = []): void
    {
        StockMovement::create(array_merge(['org_id' => $user->org_id, 'product_id' => $product->id,
            'movement_type' => 'adjustment', 'movement_date' => now()->toDateString(),
            'quantity' => $quantity, 'unit_cost' => '67.89', 'total_cost' => '67.89'], $values));
    }

    public function test_products_require_explicit_scope_and_never_leak_cost_or_foreign_rows(): void
    {
        $user = User::factory()->create();
        $mine = $this->product($user);
        $foreign = $this->product(User::factory()->create());
        $summary = $this->credential($user, ['summary:sales:read']);
        $this->withToken($summary)->getJson('/api/v1/products')->assertForbidden();
        $token = $this->credential($user, ['products:read']);
        $this->withToken($token)->getJson('/api/v1/products')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonMissingPath('data.0.cost')->assertJsonMissingPath('data.0.price')
            ->assertJsonMissingPath('data.0.org_id')->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/products/'.$foreign->id)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->getJson('/api/v1/products/'.$mine->id)->assertOk()->assertJsonMissingPath('data.cost');
        $token = $this->credential($user, ['products:read', 'products:prices:read', 'products:costs:read']);
        $this->withToken($token)->getJson('/api/v1/products/'.$mine->id)->assertOk()
            ->assertJsonPath('data.price', '123.45')->assertJsonPath('data.cost', '67.89');
        $mine->delete();
        $this->getJson('/api/v1/products/'.$mine->id)->assertNotFound();
    }

    public function test_pagination_is_stable_scoped_and_expires_with_delta_tombstones(): void
    {
        $user = User::factory()->create();
        $a = $this->product($user);
        $b = $this->product($user);
        $token = $this->credential($user, ['products:read']);
        $first = $this->withToken($token)->getJson('/api/v1/products?limit=1')->assertOk()->assertJsonCount(1, 'data');
        $cursor = $first->json('meta.next_cursor');
        $this->assertIsString($cursor);
        $next = $this->getJson('/api/v1/products?limit=1&cursor='.urlencode($cursor))->assertOk()->assertJsonCount(1, 'data');
        $this->assertNotSame($first->json('data.0.id'), $next->json('data.0.id'));
        $this->getJson('/api/v1/products?limit=2&cursor='.urlencode($cursor))->assertUnprocessable();
        $this->getJson('/api/v1/products?limit=1&cursor=corrupt')->assertUnprocessable();
        $foreign = $this->credential(User::factory()->create(), ['products:read']);
        $this->withToken($foreign)->getJson('/api/v1/products?limit=1&cursor='.urlencode($cursor))->assertUnprocessable();
        $this->withToken($token);
        $this->travel(31)->minutes();
        $this->getJson('/api/v1/products?limit=1&cursor='.urlencode($cursor))->assertUnprocessable();
        $a->delete();
        $response = $this->getJson('/api/v1/products?updated_since=2026-10-01T00:00:00Z')->assertOk();
        $deleted = collect($response->json('data'))->firstWhere('id', $a->id);
        $this->assertArrayHasKey('deleted_at', $deleted);
        $this->assertArrayNotHasKey('name', $deleted);
        $this->assertArrayNotHasKey('cost', $deleted);
        $this->assertSame($b->id, collect($response->json('data'))->firstWhere('id', $b->id)['id']);
    }

    public function test_sync_watermark_stays_fixed_and_changes_during_pagination_are_recovered_by_delta(): void
    {
        $user = User::factory()->create();
        $this->product($user);
        $this->product($user);
        $token = $this->credential($user, ['products:read']);
        $first = $this->withToken($token)->getJson('/api/v1/products?limit=1')->assertOk();
        $watermark = $first->json('meta.sync_watermark');
        $this->travel(2)->seconds();
        $changed = Product::where('org_id', $user->org_id)->where('id', '!=', $first->json('data.0.id'))->firstOrFail();
        $changed->update(['name' => 'Changed during pagination']);
        $next = $this->getJson('/api/v1/products?limit=1&cursor='.urlencode($first->json('meta.next_cursor')))->assertOk();
        $this->assertSame($watermark, $next->json('meta.sync_watermark'));
        $next->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?updated_since='.urlencode($watermark))->assertOk()
            ->assertJsonFragment(['id' => $changed->id, 'name' => 'Changed during pagination'])
            ->assertJsonPath('meta.delta_supported', true);
        $this->getJson('/api/v1/products?updated_since=2099-01-01T00:00:00Z')->assertUnprocessable();
    }

    public function test_reference_registry_rejects_dynamic_tables_and_foreign_parent_links(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $branch = Branch::create(['org_id' => $foreign->org_id, 'code' => 'F', 'name' => 'Foreign', 'status' => 'active']);
        $corrupt = Division::create(['org_id' => $user->org_id, 'branch_id' => $branch->id, 'code' => 'BAD', 'name' => 'Corrupt', 'status' => 'active']);
        $ownBranch = Branch::create(['org_id' => $user->org_id, 'code' => 'OWN', 'name' => 'Own', 'status' => 'active']);
        Division::create(['org_id' => $user->org_id, 'branch_id' => $ownBranch->id, 'code' => 'GOOD', 'name' => 'Visible', 'status' => 'active']);
        $token = $this->credential($user, ['reference:organization:read']);
        $this->withToken($token)->getJson('/api/v1/reference/divisions')->assertOk()
            ->assertJsonFragment(['code' => 'GOOD'])->assertJsonMissing(['id' => $corrupt->id]);
        $this->getJson('/api/v1/reference/users')->assertNotFound();
        $this->getJson('/api/v1/reference/units')->assertNotFound();
        $this->getJson('/api/v1/reference/warehouses')->assertForbidden();
    }

    public function test_inventory_signed_quantities_zero_stock_and_corrupt_parents_are_isolated(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $product = $this->product($user);
        $zero = $this->product($user);
        $negative = $this->product($user);
        $this->product($user, ['is_active' => false]);
        $this->product($user, ['track_inventory' => false]);
        $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'MAIN', 'name' => 'Main']);
        $otherWarehouse = Warehouse::create(['org_id' => $foreign->org_id, 'code' => 'OTHER', 'name' => 'Other']);
        $bin = WarehouseBin::create(['org_id' => $user->org_id, 'warehouse_id' => $warehouse->id, 'code' => 'B1']);
        $lot = InventoryLot::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'lot_no' => 'LOT', 'expires_at' => '2027-01-01']);
        $placement = ['warehouse_id' => $warehouse->id, 'warehouse_bin_id' => $bin->id, 'inventory_lot_id' => $lot->id];
        $this->movement($user, $product, '10.1250', $placement);
        $this->movement($user, $product, '-7.2500', $placement);
        $this->movement($user, $product, '500', ['warehouse_id' => $otherWarehouse->id]);
        $this->movement($user, $negative, '-1.5000');
        $this->movement($foreign, $product, '9999');
        $this->movement($user, $negative, '9999', ['inventory_lot_id' => $lot->id]);
        $token = $this->credential($user, ['inventory:read', 'summary:inventory:read']);
        $response = $this->withToken($token)->getJson('/api/v1/inventory/balances')->assertOk()->assertJsonCount(2, 'data');
        $balance = collect($response->json('data'))->firstWhere('product_id', $product->id);
        $this->assertSame('2.8750', $balance['on_hand']);
        $this->assertSame('LOT', $balance['lot_no']);
        $this->assertArrayNotHasKey('available', $balance);
        $this->assertArrayNotHasKey('unit_cost', $balance);
        $this->getJson('/api/v1/summaries/inventory')->assertOk()
            ->assertJsonPath('data.snapshot.active_tracked_products', 3)
            ->assertJsonPath('data.snapshot.low_stock_products', 3)
            ->assertJsonPath('data.snapshot.negative_stock_products', 1);
        $this->getJson('/api/v1/inventory/balances?warehouse_id='.$otherWarehouse->id)->assertUnprocessable();
        $this->getJson('/api/v1/inventory/balances?limit=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_invalid_filters_and_revoked_keys_keep_common_security_contract(): void
    {
        $user = User::factory()->create();
        $token = $this->credential($user, ['products:read']);
        foreach (['org_id=foreign', 'limit=101', 'sort=cost', 'include=creator', 'search='.str_repeat('x', 101)] as $query) {
            $this->withToken($token)->getJson('/api/v1/products?'.$query)->assertUnprocessable()->assertJsonPath('code', 'invalid_filters');
        }
        IntegrationApiKey::query()->update(['revoked_at' => now()]);
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_key_managers_cannot_grant_new_domain_scopes_without_domain_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'key_manager', 'name' => 'Keys']);
        $role->permissions()->attach(Permission::whereIn('code', ['api_keys.view', 'api_keys.manage'])->pluck('id'));
        $user->roles()->attach($role);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('settings.api-access.store'), ['name' => 'Denied', 'scopes' => ['products:read']])->assertUnprocessable();
        $role->permissions()->attach(Permission::where('code', 'products.view')->firstOrFail());
        $this->postJson(route('settings.api-access.store'), ['name' => 'Allowed', 'scopes' => ['products:read']])->assertCreated();
        $this->postJson(route('settings.api-access.store'), ['name' => 'Costs denied', 'scopes' => ['products:read', 'products:costs:read']])->assertUnprocessable();
    }
}
