<?php

namespace Tests\Feature;

use App\Models\GoodsReceiptItem;
use App\Models\InventoryLot;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_goods_receipt_posts_stock_movement_and_partially_receives_po(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['inventory.view', 'inventory.receive']);
        [$po, $poItem] = $this->purchaseOrderWithItem($finance, 10);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('goods-receipts.store'), [
                'purchase_order_id' => $po->id,
                'received_date' => '2026-08-23',
                'items' => [[
                    'purchase_order_item_id' => $poItem->id,
                    'quantity' => '4',
                ]],
            ])
            ->assertRedirect();

        $this->assertSame('partially_received', $po->fresh()->status);
        $this->assertSame('4.0000', GoodsReceiptItem::where('purchase_order_item_id', $poItem->id)->firstOrFail()->quantity);
        $movement = StockMovement::where('purchase_order_id', $po->id)->firstOrFail();
        $this->assertSame('receive_from_po', $movement->movement_type);
        $this->assertSame('4.0000', $movement->quantity);
        $this->assertSame('400.00', $movement->total_cost);
    }

    public function test_goods_receipt_blocks_over_receive_and_marks_po_received_when_complete(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['inventory.receive']);
        [$po, $poItem] = $this->purchaseOrderWithItem($finance, 5);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('goods-receipts.store'), [
                'purchase_order_id' => $po->id,
                'received_date' => '2026-08-23',
                'items' => [[
                    'purchase_order_item_id' => $poItem->id,
                    'quantity' => '6',
                ]],
            ])
            ->assertStatus(422);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('goods-receipts.store'), [
                'purchase_order_id' => $po->id,
                'received_date' => '2026-08-23',
                'items' => [[
                    'purchase_order_item_id' => $poItem->id,
                    'quantity' => '5',
                ]],
            ])
            ->assertRedirect();

        $this->assertSame('received', $po->fresh()->status);
    }

    public function test_goods_receipt_rejects_lot_that_belongs_to_another_product(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['inventory.receive']);
        [$po, $poItem] = $this->purchaseOrderWithItem($finance, 5);
        $otherProduct = Product::create(['org_id' => $finance->org_id, 'sku' => 'LOT-OTHER', 'name' => 'Other lot product', 'type' => 'product', 'unit' => 'pcs', 'price' => 10, 'cost' => 10, 'is_active' => true]);
        $lot = InventoryLot::create(['org_id' => $finance->org_id, 'product_id' => $otherProduct->id, 'lot_no' => 'OTHER-LOT']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('goods-receipts.store'), [
                'purchase_order_id' => $po->id, 'received_date' => '2026-08-23',
                'items' => [['purchase_order_item_id' => $poItem->id, 'quantity' => '1', 'inventory_lot_id' => $lot->id]],
            ])->assertStatus(422);

        $this->assertSame(0, GoodsReceiptItem::count());
    }

    public function test_goods_receipt_index_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['inventory.view']);
        [$visiblePo] = $this->purchaseOrderWithItem($finance, 2);
        [$hiddenPo] = $this->purchaseOrderWithItem($other, 2);

        $visiblePo->goodsReceipts()->create([
            'org_id' => $finance->org_id,
            'grn_no' => '000001',
            'received_date' => '2026-08-23',
            'status' => 'posted',
        ]);
        $hiddenPo->goodsReceipts()->create([
            'org_id' => $other->org_id,
            'grn_no' => '000001',
            'received_date' => '2026-08-23',
            'status' => 'posted',
        ]);

        $this->actingAsOrgUser($finance)
            ->get(route('goods-receipts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/GoodsReceipts')
                ->has('goodsReceipts', 1)
                ->where('goodsReceipts.0.purchase_order.po_no', $visiblePo->po_no)
            );
    }

    public function test_stock_adjustment_return_and_average_cost(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['inventory.view', 'inventory.adjust']);
        $product = Product::create(['org_id' => $finance->org_id, 'sku' => 'ADJ-001', 'name' => 'Adjusted Product', 'type' => 'product', 'unit' => 'pcs', 'price' => 120, 'cost' => 100, 'is_active' => true]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('stock-movements.store'), [
                'product_id' => $product->id,
                'movement_type' => 'adjustment_in',
                'movement_date' => '2026-08-23',
                'quantity' => '10',
                'unit_cost' => '100',
            ])
            ->assertRedirect();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('stock-movements.store'), [
                'product_id' => $product->id,
                'movement_type' => 'return_to_supplier',
                'movement_date' => '2026-08-24',
                'quantity' => '4',
            ])
            ->assertRedirect();

        $this->assertSame('6.0000', number_format((float) StockMovement::where('product_id', $product->id)->sum('quantity'), 4, '.', ''));
        $this->assertSame('600.00', number_format((float) StockMovement::where('product_id', $product->id)->sum('total_cost'), 2, '.', ''));

        $this->actingAsOrgUser($finance)
            ->get(route('goods-receipts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stockSummary.0.on_hand', '6.0000')
                ->where('stockSummary.0.inventory_value', '600.00')
                ->where('stockSummary.0.average_cost', '100.00')
                ->has('stockMovements', 2)
            );
    }

    public function test_stock_adjustment_blocks_negative_on_hand(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['inventory.adjust']);
        $product = Product::create(['org_id' => $finance->org_id, 'sku' => 'NEG-001', 'name' => 'Negative Guard Product', 'type' => 'product', 'unit' => 'pcs', 'price' => 120, 'cost' => 100, 'is_active' => true]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('stock-movements.store'), [
                'product_id' => $product->id,
                'movement_type' => 'adjustment_out',
                'movement_date' => '2026-08-23',
                'quantity' => '1',
            ])
            ->assertStatus(422);
    }

    private function purchaseOrderWithItem(User $user, int $quantity): array
    {
        $supplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => '000001', 'name' => 'Inventory Supplier', 'status' => 'active']);
        $product = Product::create(['org_id' => $user->org_id, 'sku' => 'INV-001', 'name' => 'Inventory Product', 'type' => 'product', 'unit' => 'pcs', 'price' => 120, 'cost' => 100, 'is_active' => true]);
        $po = PurchaseOrder::create(['org_id' => $user->org_id, 'supplier_id' => $supplier->id, 'po_no' => 'PO-'.str()->random(6), 'status' => 'approved', 'order_date' => '2026-08-23', 'tax_mode' => 'no_tax', 'total' => $quantity * 100]);
        $item = $po->items()->create(['org_id' => $user->org_id, 'product_id' => $product->id, 'description' => 'Inventory Product', 'quantity' => $quantity, 'unit' => 'pcs', 'unit_price' => 100, 'line_total' => $quantity * 100, 'sort_order' => 0]);

        return [$po, $item];
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => $code, 'name' => ucfirst($code), 'is_system' => true]);
        $user->roles()->attach($role->id);

        foreach ($permissions as $permissionCode) {
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => str($permissionCode)->before('.')->toString(), 'action' => str($permissionCode)->after('.')->toString(), 'description' => $permissionCode]
            );
            $role->permissions()->attach($permission->id);
        }

        return $role;
    }
}
