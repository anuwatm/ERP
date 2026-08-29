<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase15InventoryOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_transfer_keeps_total_stock_and_uses_organization_currency(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, ['inventory.adjust']);
        $source = Warehouse::create(['org_id' => $user->org_id, 'code' => 'MAIN', 'name' => 'Main']);
        $destination = Warehouse::create(['org_id' => $user->org_id, 'code' => 'SHOP', 'name' => 'Shop']);
        $product = $this->product($user, 'WH-001');
        $this->movement($user, $product, $source, 10, 25);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('stock-transfers.store'), [
            'product_id' => $product->id, 'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id, 'quantity' => 4, 'transfer_date' => '2026-08-29',
        ])->assertRedirect();

        $this->assertSame('6.0000', number_format((float) StockMovement::where('warehouse_id', $source->id)->sum('quantity'), 4, '.', ''));
        $this->assertSame('4.0000', number_format((float) StockMovement::where('warehouse_id', $destination->id)->sum('quantity'), 4, '.', ''));
        $this->assertSame($user->organization->currency, StockMovement::where('warehouse_id', $destination->id)->firstOrFail()->currency);
    }

    public function test_transfer_rejects_stock_from_another_lot_or_insufficient_source_stock(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, ['inventory.adjust']);
        $source = Warehouse::create(['org_id' => $user->org_id, 'code' => 'MAIN', 'name' => 'Main']);
        $destination = Warehouse::create(['org_id' => $user->org_id, 'code' => 'SHOP', 'name' => 'Shop']);
        $product = $this->product($user, 'WH-002');

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('stock-transfers.store'), [
            'product_id' => $product->id, 'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id, 'quantity' => 1, 'transfer_date' => '2026-08-29',
        ])->assertStatus(422);
    }

    public function test_stock_count_posts_only_the_difference(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, ['inventory.adjust']);
        $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'MAIN', 'name' => 'Main']);
        $product = $this->product($user, 'WH-003');
        $this->movement($user, $product, $warehouse, 10, 25);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('stock-counts.store'), [
            'warehouse_id' => $warehouse->id, 'count_date' => '2026-08-29',
            'items' => [['product_id' => $product->id, 'counted_quantity' => 7]],
        ])->assertRedirect();

        $this->assertSame(1, \DB::table('stock_counts')->count());
        $this->assertSame('7.0000', number_format((float) StockMovement::where('warehouse_id', $warehouse->id)->sum('quantity'), 4, '.', ''));
        $this->assertSame(1, StockMovement::where('movement_type', 'stock_count_out')->count());
    }

    public function test_product_barcode_is_unique_per_organization(): void
    {
        $user = User::factory()->create();
        $this->product($user, 'BC-001', '885000000001');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->product($user, 'BC-002', '885000000001');
    }

    private function product(User $user, string $sku, ?string $barcode = null): Product
    {
        return Product::create(['org_id' => $user->org_id, 'sku' => $sku, 'barcode' => $barcode, 'name' => $sku, 'type' => 'product', 'unit' => 'pcs', 'price' => 50, 'cost' => 25, 'is_active' => true]);
    }

    private function movement(User $user, Product $product, Warehouse $warehouse, float $quantity, float $unitCost): void
    {
        $currency = $user->organization->currency;
        StockMovement::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'adjustment_in', 'movement_date' => '2026-08-28', 'quantity' => $quantity, 'unit_cost' => $unitCost, 'total_cost' => $quantity * $unitCost, 'currency' => $currency, 'base_currency' => $currency, 'exchange_rate' => 1, 'base_unit_cost' => $unitCost, 'base_total_cost' => $quantity * $unitCost, 'created_by' => $user->id]);
    }

    private function attachPermissions(User $user, array $permissions): void
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'warehouse', 'name' => 'Warehouse', 'is_system' => true]);
        $user->roles()->attach($role->id);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'inventory', 'action' => 'adjust', 'description' => $code]);
            $role->permissions()->attach($permission->id);
        }
    }
}
