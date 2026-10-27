<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryMovementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase28InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_replay_does_not_deduct_again_or_revalue_at_current_cost(): void
    {
        [$user, $product, $warehouse] = $this->pool();
        $service = app(InventoryMovementService::class);
        $movement = $service->deductStockForSale($user->org_id, $product->id, '2.0000', $warehouse->id, '2026-10-25', 'THB', $user->id, 'sale:one');
        $this->assertSame('25.00', $movement->base_unit_cost);
        $replay = $service->deductStockForSale($user->org_id, $product->id, '2', $warehouse->id, '2026-10-25', 'THB', $user->id, 'sale:one');
        $this->assertSame($movement->id, $replay->id);
        $this->assertSame('8.0000', $service->available($user->org_id, $product->id, $warehouse->id));
        $this->expectException(HttpException::class);
        $service->deductStockForSale($user->org_id, $product->id, '3', $warehouse->id, '2026-10-25', 'THB', $user->id, 'sale:one');
    }

    public function test_erp_cannot_consume_unacknowledged_pos_entitlement(): void
    {
        [$user, $product, $warehouse] = $this->pool();
        DB::table('pos_stock_entitlements')->insert(['id' => (string) Str::uuid(), 'org_id' => $user->org_id, 'pos_instance_id' => (string) Str::uuid(), 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'granted_quantity' => '6', 'consumed_quantity' => '0', 'status' => 'pending']);
        $service = app(InventoryMovementService::class);
        $this->assertSame('4.0000', $service->available($user->org_id, $product->id, $warehouse->id));
        $service->deductStockForSale($user->org_id, $product->id, '4', $warehouse->id, '2026-10-25', 'THB', $user->id, 'erp:one');
        $this->expectException(HttpException::class);
        $service->deductStockForSale($user->org_id, $product->id, '0.0001', $warehouse->id, '2026-10-25', 'THB', $user->id, 'erp:two');
    }

    public function test_empty_pool_cannot_be_sold_and_transaction_rolls_back(): void
    {
        [$user, $product, $warehouse] = $this->pool();
        StockMovement::query()->delete();
        try {
            app(InventoryMovementService::class)->deductStockForSale($user->org_id, $product->id, '1', $warehouse->id, '2026-10-25', 'THB', $user->id, 'empty');
            $this->fail('Empty stock must reject.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_foreign_warehouse_cannot_receive_stock(): void
    {
        [$user, $product] = $this->pool();
        $other = User::factory()->create();
        $warehouse = Warehouse::create(['org_id' => $other->org_id, 'code' => 'OTHER', 'name' => 'Other']);
        $this->expectException(ModelNotFoundException::class);
        app(InventoryMovementService::class)->record(['org_id' => $user->org_id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'adjustment_in', 'movement_date' => '2026-10-25', 'quantity' => '1']);
    }

    private function pool(): array
    {
        $user = User::factory()->create();
        $product = Product::create(['org_id' => $user->org_id, 'sku' => 'P28', 'name' => 'Product', 'type' => 'product', 'unit' => 'pcs', 'price' => 100, 'is_active' => true]);
        $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'SHOP', 'name' => 'Shop']);
        StockMovement::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'adjustment_in', 'movement_date' => '2026-10-25', 'quantity' => '10', 'base_total_cost' => '250', 'base_unit_cost' => '25']);

        return [$user, $product, $warehouse];
    }
}
