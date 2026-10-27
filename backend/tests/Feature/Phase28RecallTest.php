<?php

namespace Tests\Feature;

use App\Models\InventoryLot;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryMovementService;
use App\Services\Pos\PosRecallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class Phase28RecallTest extends TestCase
{
    use RefreshDatabase;

    public function test_recall_requires_permission_scope_and_stops_shared_stock_service(): void
    {
        config(['pos.local_recalls_enabled' => true]);
        $user = User::factory()->create();
        $product = Product::create(['org_id' => $user->org_id, 'sku' => 'RECALL', 'name' => 'Synthetic product', 'type' => 'product', 'unit' => 'pcs', 'price' => '10', 'is_active' => true]);
        $lot = InventoryLot::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'lot_no' => 'RECALL']);
        $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'R', 'name' => 'R']);
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'recall', 'name' => 'Recall']);
        $role->permissions()->attach(Permission::where('code', 'pos.manage')->firstOrFail()->id);
        $user->roles()->attach($role->id);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);
        $this->post(route('pos.recalls'), ['lot_id' => $lot->id, 'reason' => 'Synthetic recall'])->assertRedirect();
        $service = app(PosRecallService::class);
        $first = $service->recall($user, $lot->id, 'Retry');
        $this->assertSame($first, DB::table('pos_lot_recalls')->value('id'));
        $this->assertDatabaseCount('pos_lot_recalls', 1);
        $other = User::factory()->create();
        $otherRole = Role::create(['org_id' => $other->org_id, 'code' => 'recall', 'name' => 'Recall']);
        $otherRole->permissions()->attach(Permission::where('code', 'pos.manage')->firstOrFail()->id);
        $other->roles()->attach($otherRole->id);
        $this->actingAs($other)->postJson(route('pos.recalls'), ['lot_id' => $lot->id, 'reason' => 'Foreign'])->assertNotFound();
        $attrs = ['org_id' => $user->org_id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'inventory_lot_id' => $lot->id, 'movement_type' => 'pos_return', 'movement_date' => now()->toDateString(), 'quantity' => '1.0000', 'base_total_cost' => '1.00'];
        try {
            app(InventoryMovementService::class)->record($attrs);
            $this->fail('Recalled lot restocked.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
