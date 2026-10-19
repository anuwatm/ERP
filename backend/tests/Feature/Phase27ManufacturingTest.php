<?php

namespace Tests\Feature;

use App\Models\BillOfMaterials;
use App\Models\BomItem;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ChartOfAccountProvisioner;
use App\Services\ProductionCostingService;
use App\Support\PermissionCatalog;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase27ManufacturingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function attachRole(User $user, string $code, array $permissions = []): Role
    {
        $role = Role::firstOrCreate(
            ['org_id' => $user->org_id, 'code' => $code],
            ['name' => ucfirst($code), 'is_system' => true]
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        if (empty($permissions)) {
            $permissions = PermissionCatalog::defaults()[$code] ?? [];
        }

        foreach ($permissions as $permissionCode) {
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => str($permissionCode)->before('.')->toString(), 'action' => str($permissionCode)->after('.')->toString(), 'description' => $permissionCode]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    public function test_can_create_bill_of_materials_and_calculate_costs(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');
        $this->ensureChartOfAccounts($user->org_id);

        $wood = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'MAT-WOOD',
            'name' => 'Oak Wood Planks',
            'type' => 'raw_material',
            'unit' => 'plank',
            'cost' => 150.00,
            'price' => 200.00,
            'is_active' => true,
        ]);

        $screws = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'MAT-SCREW',
            'name' => 'Steel Screws',
            'type' => 'raw_material',
            'unit' => 'pcs',
            'cost' => 2.50,
            'price' => 5.00,
            'is_active' => true,
        ]);

        $tableProduct = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'FG-TABLE-01',
            'name' => 'Oak Dining Table',
            'type' => 'finished_good',
            'unit' => 'unit',
            'cost' => 0.00,
            'price' => 3500.00,
            'is_active' => true,
        ]);

        $response = $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.boms.store'), [
                'product_id' => $tableProduct->id,
                'name' => 'Standard Oak Dining Table BOM',
                'version' => '1.0',
                'quantity' => 1,
                'unit' => 'unit',
                'routing_notes' => 'Cut wood -> Sanding -> Assembly -> Lacquer polish',
                'labor_cost' => 400.00,
                'overhead_cost' => 150.00,
                'is_active' => true,
                'items' => [
                    [
                        'product_id' => $wood->id,
                        'quantity' => 4,
                        'unit' => 'plank',
                        'waste_percent' => 5, // 4 * 1.05 * 150 = 630.00
                        'unit_cost' => 150.00,
                        'note' => '4 planks oak wood with 5% cut waste',
                    ],
                    [
                        'product_id' => $screws->id,
                        'quantity' => 16,
                        'unit' => 'pcs',
                        'waste_percent' => 0, // 16 * 2.50 = 40.00
                        'unit_cost' => 2.50,
                        'note' => 'Heavy duty assembly screws',
                    ],
                ],
            ]);

        $response->assertRedirect();

        $bom = BillOfMaterials::where('org_id', $user->org_id)->firstOrFail();
        $this->assertSame('Standard Oak Dining Table BOM', $bom->name);
        $this->assertSame('1.0', $bom->version);
        $this->assertCount(2, $bom->items);

        // Material cost: 630.00 + 40.00 = 670.00
        // Total cost: 670.00 + 400.00 (labor) + 150.00 (overhead) = 1220.00
        $this->assertEquals(1220.00, (float) $bom->total_cost);
    }

    public function test_multi_level_bom_explosion_and_flattening(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $costing = app(ProductionCostingService::class);

        // Level 2 raw materials
        $steelRod = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'MAT-STEEL',
            'name' => 'Steel Rod',
            'type' => 'product',
            'unit' => 'm',
            'cost' => 50.00,
            'is_active' => true,
        ]);

        $rubberTip = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'MAT-RUBBER',
            'name' => 'Rubber Foot Tip',
            'type' => 'product',
            'unit' => 'pcs',
            'cost' => 10.00,
            'is_active' => true,
        ]);

        // Level 1 sub-assembly: Table Leg Assembly
        $tableLeg = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'SA-LEG',
            'name' => 'Finished Table Leg Assembly',
            'type' => 'product',
            'unit' => 'pcs',
            'cost' => 0.00,
            'is_active' => true,
        ]);

        $legBom = BillOfMaterials::create([
            'org_id' => $user->org_id,
            'bom_no' => 'BOM-LEG-01',
            'product_id' => $tableLeg->id,
            'name' => 'Single Table Leg Sub-assembly',
            'version' => '1.0',
            'quantity' => 1,
            'unit' => 'pcs',
            'labor_cost' => 25.00,
            'overhead_cost' => 5.00,
            'total_cost' => 0,
            'is_active' => true,
        ]);

        BomItem::create([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $legBom->id,
            'product_id' => $steelRod->id,
            'quantity' => 1.5, // 1.5m * 50 = 75
            'unit' => 'm',
            'unit_cost' => 50.00,
            'waste_percent' => 0,
            'total_cost' => 75.00,
        ]);

        BomItem::create([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $legBom->id,
            'product_id' => $rubberTip->id,
            'quantity' => 1, // 1 * 10 = 10
            'unit' => 'pcs',
            'unit_cost' => 10.00,
            'waste_percent' => 0,
            'total_cost' => 10.00,
        ]);

        // Calculate Leg BOM cost: 75 + 10 + 25 + 5 = 115.00
        $costing->calculateBomCost($legBom);
        $this->assertEquals(115.00, (float) $legBom->total_cost);

        // Level 0 finished good: Conference Table (requires 4 legs + 1 tabletop)
        $tableTop = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'MAT-TOP',
            'name' => 'Glass Table Top',
            'type' => 'product',
            'unit' => 'sheet',
            'cost' => 1500.00,
            'is_active' => true,
        ]);

        $confTable = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'FG-CONF-TABLE',
            'name' => 'Modern Glass Conference Table',
            'type' => 'product',
            'unit' => 'unit',
            'is_active' => true,
        ]);

        $mainBom = BillOfMaterials::create([
            'org_id' => $user->org_id,
            'bom_no' => 'BOM-CONF-01',
            'product_id' => $confTable->id,
            'name' => 'Conference Table Master BOM',
            'version' => '1.0',
            'quantity' => 1,
            'unit' => 'unit',
            'labor_cost' => 200.00,
            'overhead_cost' => 100.00,
            'total_cost' => 0,
            'is_active' => true,
        ]);

        // 4 legs (sub-assembly linked via sub_bom_id)
        BomItem::create([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $mainBom->id,
            'product_id' => $tableLeg->id,
            'sub_bom_id' => $legBom->id,
            'quantity' => 4,
            'unit' => 'pcs',
            'unit_cost' => 115.00, // from sub-BOM
            'waste_percent' => 0,
            'total_cost' => 460.00,
        ]);

        // 1 tabletop
        BomItem::create([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $mainBom->id,
            'product_id' => $tableTop->id,
            'quantity' => 1,
            'unit' => 'sheet',
            'unit_cost' => 1500.00,
            'waste_percent' => 0,
            'total_cost' => 1500.00,
        ]);

        $costing->calculateBomCost($mainBom);
        // Total = 460 + 1500 + 200 (labor) + 100 (overhead) = 2260.00
        $this->assertEquals(2260.00, (float) $mainBom->total_cost);

        // Explode for 2 conference tables
        $explosion = $costing->explode($mainBom, 2.0);
        $this->assertSame(2.0, (float) $explosion['batch_quantity']);
        $this->assertCount(2, $explosion['items']);

        // Find the leg sub-assembly node
        $legNode = collect($explosion['items'])->firstWhere('is_subassembly', true);
        $this->assertNotNull($legNode);
        $this->assertSame(8.0, (float) $legNode['required_quantity']); // 4 * 2
        $this->assertNotNull($legNode['sub_bom']);
        $this->assertCount(2, $legNode['sub_bom']['items']); // Steel rod and rubber tip

        // Flatten materials
        $flattened = $costing->flattenExplodedMaterials($explosion);
        $this->assertCount(3, $flattened); // Steel Rod, Rubber Tip, Glass Table Top

        $steelSummary = collect($flattened)->firstWhere('product_id', $steelRod->id);
        $this->assertEquals(12.0, (float) $steelSummary['required_quantity']); // 1.5 * 8 = 12m

        $rubberSummary = collect($flattened)->firstWhere('product_id', $rubberTip->id);
        $this->assertEquals(8.0, (float) $rubberSummary['required_quantity']); // 1 * 8 = 8pcs

        $topSummary = collect($flattened)->firstWhere('product_id', $tableTop->id);
        $this->assertEquals(2.0, (float) $topSummary['required_quantity']); // 1 * 2 = 2 sheets
    }

    public function test_circular_bom_dependency_throws_domain_exception(): void
    {
        $user = User::factory()->create();
        $costing = app(ProductionCostingService::class);

        $prodA = Product::create(['org_id' => $user->org_id, 'sku' => 'A', 'name' => 'Product A', 'type' => 'product', 'is_active' => true]);
        $prodB = Product::create(['org_id' => $user->org_id, 'sku' => 'B', 'name' => 'Product B', 'type' => 'product', 'is_active' => true]);

        $bomA = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-A', 'product_id' => $prodA->id, 'name' => 'A', 'quantity' => 1, 'unit' => 'pcs']);
        $bomB = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-B', 'product_id' => $prodB->id, 'name' => 'B', 'quantity' => 1, 'unit' => 'pcs']);

        // BOM A has BOM B as sub-assembly
        BomItem::create([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $bomA->id,
            'product_id' => $prodB->id,
            'sub_bom_id' => $bomB->id,
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        // BOM B has BOM A as sub-assembly (Circular cycle!)
        BomItem::create([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $bomB->id,
            'product_id' => $prodA->id,
            'sub_bom_id' => $bomA->id,
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Circular BOM dependency detected');

        $costing->explode($bomA);
    }

    public function test_work_order_creation_from_bom_with_scaling(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $rawMaterial = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'RAW-01',
            'name' => 'Raw Material 1',
            'type' => 'product',
            'cost' => 50.00,
            'is_active' => true,
        ]);

        $finishedGood = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'FG-01',
            'name' => 'Finished Good 1',
            'type' => 'product',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'org_id' => $user->org_id,
            'code' => 'WH-MAIN',
            'name' => 'Main Warehouse',
            'status' => 'active',
        ]);

        $bom = BillOfMaterials::create([
            'org_id' => $user->org_id,
            'bom_no' => 'BOM-SCALE-01',
            'product_id' => $finishedGood->id,
            'name' => 'Batch 10 BOM',
            'quantity' => 10, // batch output = 10
            'unit' => 'pcs',
            'labor_cost' => 200.00,
            'overhead_cost' => 50.00,
            'is_active' => true,
        ]);

        BomItem::create([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $bom->id,
            'product_id' => $rawMaterial->id,
            'quantity' => 20, // 2 raw per finished good
            'unit' => 'kg',
            'waste_percent' => 10, // 10% waste: 20 * 1.10 = 22 kg per batch of 10
            'unit_cost' => 50.00,
            'total_cost' => 1100.00,
        ]);

        $costing = app(ProductionCostingService::class);
        $costing->calculateBomCost($bom);

        // Plan Work Order for 50 finished goods (5x batch)
        $workOrder = $costing->createWorkOrder([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $bom->id,
            'planned_quantity' => 50,
            'warehouse_id' => $warehouse->id,
            'source_warehouse_id' => $warehouse->id,
        ], $user->id);

        $this->assertSame('draft', $workOrder->status);
        $this->assertEquals(50.0, (float) $workOrder->planned_quantity);
        $this->assertCount(1, $workOrder->items);

        $item = $workOrder->items->first();
        // 22 kg per 10 finished goods * 5 = 110 kg
        $this->assertEquals(110.0, (float) $item->planned_quantity);
        // Material cost: 110 * 50 = 5500.00
        $this->assertEquals(5500.00, (float) $workOrder->material_cost);
        // Labor: 200 * 5 = 1000.00
        $this->assertEquals(1000.00, (float) $workOrder->labor_cost);
        // Overhead: 50 * 5 = 250.00
        $this->assertEquals(250.00, (float) $workOrder->overhead_cost);
        // Total cost: 5500 + 1000 + 250 = 6750.00
        $this->assertEquals(6750.00, (float) $workOrder->total_cost);
        // Unit cost: 6750 / 50 = 135.00
        $this->assertEquals(135.00, (float) $workOrder->unit_cost);
    }

    public function test_work_order_lifecycle_flow_stock_movements_and_gl_postings(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');
        $this->ensureChartOfAccounts($user->org_id);

        $rawMaterial = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'RAW-METAL',
            'name' => 'Raw Metal Sheet',
            'type' => 'product',
            'cost' => 100.00,
            'is_active' => true,
        ]);

        $finishedGood = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'FG-CABINET',
            'name' => 'Metal Cabinet',
            'type' => 'product',
            'is_active' => true,
        ]);

        $rawWarehouse = Warehouse::create([
            'org_id' => $user->org_id,
            'code' => 'WH-RAW',
            'name' => 'Raw Materials Warehouse',
            'status' => 'active',
        ]);

        $fgWarehouse = Warehouse::create([
            'org_id' => $user->org_id,
            'code' => 'WH-FG',
            'name' => 'Finished Goods Warehouse',
            'status' => 'active',
        ]);

        $bom = BillOfMaterials::create([
            'org_id' => $user->org_id,
            'bom_no' => 'BOM-CABINET-01',
            'product_id' => $finishedGood->id,
            'name' => 'Metal Cabinet BOM',
            'quantity' => 1,
            'unit' => 'unit',
            'labor_cost' => 300.00,
            'overhead_cost' => 100.00,
            'is_active' => true,
        ]);

        BomItem::create([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $bom->id,
            'product_id' => $rawMaterial->id,
            'quantity' => 3, // 3 sheets * 100 = 300
            'unit' => 'sheet',
            'waste_percent' => 0,
            'unit_cost' => 100.00,
            'total_cost' => 300.00,
        ]);

        $costing = app(ProductionCostingService::class);
        $costing->calculateBomCost($bom);

        $workOrder = $costing->createWorkOrder([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $bom->id,
            'planned_quantity' => 2, // 2 units
            'warehouse_id' => $fgWarehouse->id,
            'source_warehouse_id' => $rawWarehouse->id,
        ], $user->id);

        $this->assertSame('draft', $workOrder->status);

        // Step 1: Draft -> Planned
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.plan', $workOrder->id), [
                'start_date' => '2026-09-25',
                'due_date' => '2026-09-28',
            ])
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertSame('planned', $workOrder->status);

        // Seed stock for raw materials before release
        StockMovement::create([
            'org_id' => $user->org_id,
            'product_id' => $rawMaterial->id,
            'warehouse_id' => $rawWarehouse->id,
            'movement_type' => 'opening_balance',
            'movement_date' => '2026-09-01',
            'quantity' => 100,
            'unit_cost' => 100.00,
            'total_cost' => 10000.00,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_unit_cost' => 100.00,
            'base_total_cost' => 10000.00,
        ]);

        // Step 2: Planned -> In Progress (Release & Issue raw materials)
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.release', $workOrder->id))
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertSame('in_progress', $workOrder->status);

        // Stock movement out for raw materials
        $issueMovement = StockMovement::where('work_order_id', $workOrder->id)
            ->where('movement_type', 'work_order_issue')
            ->firstOrFail();

        $this->assertEquals(-6.0, (float) $issueMovement->quantity); // 3 * 2 = 6 sheets
        $this->assertEquals(-600.00, (float) $issueMovement->total_cost);
        $this->assertSame($rawWarehouse->id, $issueMovement->warehouse_id);

        // GL Posting for raw material issue: Dr. 1160 (WIP) / Cr. 1140 (Raw Materials)
        $issueJournal = JournalEntry::where('org_id', $user->org_id)
            ->where('source_type', 'work_order')
            ->where('source_id', $workOrder->id)
            ->where('posting_event', 'material_issued')
            ->with('lines.account')
            ->firstOrFail();

        $wipLine = $issueJournal->lines->first(fn ($l) => $l->account->code === '1160');
        $rawLine = $issueJournal->lines->first(fn ($l) => $l->account->code === '1140');
        $this->assertNotNull($wipLine);
        $this->assertNotNull($rawLine);
        $this->assertEquals(600.00, (float) $wipLine->debit);
        $this->assertEquals(600.00, (float) $rawLine->credit);

        // Step 3: In Progress -> Quality Check
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.quality-check', $workOrder->id), [
                'actual_quantity' => 2,
                'scrap_quantity' => 0,
                'qc_notes' => 'Passed visual inspection and dimensional tolerances',
                'qc_passed' => true,
            ])
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertSame('quality_check', $workOrder->status);
        $this->assertEquals(2.0, (float) $workOrder->actual_quantity);
        $this->assertTrue($workOrder->qc_passed);

        // Step 4: Quality Check -> Completed (Receive Finished Goods)
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.complete', $workOrder->id), [
                'completed_date' => '2026-09-26',
            ])
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertSame('completed', $workOrder->status);
        $this->assertNotNull($workOrder->completed_date);

        // Stock movement in for finished goods
        $receiptMovement = StockMovement::where('work_order_id', $workOrder->id)
            ->where('movement_type', 'work_order_receipt')
            ->firstOrFail();

        $this->assertEquals(2.0, (float) $receiptMovement->quantity);
        $this->assertSame($fgWarehouse->id, $receiptMovement->warehouse_id);

        // Total cost: 600 (mat) + 600 (labor) + 200 (overhead) = 1400.00
        $this->assertEquals(1400.00, (float) $workOrder->total_cost);
        $this->assertEquals(700.00, (float) $workOrder->unit_cost);

        // GL Posting for Completion: Dr. 1140 (Finished Goods) 1400 / Cr. 1160 (WIP) 600 / Cr. 5500 (Labor) 600 / Cr. 5200 (Overhead) 200
        $completeJournal = JournalEntry::where('org_id', $user->org_id)
            ->where('source_type', 'work_order')
            ->where('source_id', $workOrder->id)
            ->where('posting_event', 'completed')
            ->with('lines.account')
            ->firstOrFail();

        $fgGlLine = $completeJournal->lines->first(fn ($l) => $l->account->code === '1140');
        $wipGlLine = $completeJournal->lines->first(fn ($l) => $l->account->code === '1160');
        $laborGlLine = $completeJournal->lines->first(fn ($l) => $l->account->code === '5500');
        $overheadGlLine = $completeJournal->lines->first(fn ($l) => $l->account->code === '5200');

        $this->assertNotNull($fgGlLine);
        $this->assertNotNull($wipGlLine);
        $this->assertEquals(1400.00, (float) $fgGlLine->debit);
        $this->assertEquals(600.00, (float) $wipGlLine->credit);
        $this->assertEquals(600.00, (float) $laborGlLine->credit);
        $this->assertEquals(200.00, (float) $overheadGlLine->credit);
    }

    public function test_work_order_cancellation_reverses_stock_and_gl_if_issued(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');
        $this->ensureChartOfAccounts($user->org_id);

        $mat = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-1', 'name' => 'Material', 'type' => 'product', 'cost' => 20, 'is_active' => true]);
        $fg = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-1', 'name' => 'Finished Good', 'type' => 'product', 'is_active' => true]);
        $wh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'WH', 'name' => 'WH', 'status' => 'active']);

        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-CANC', 'product_id' => $fg->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $mat->id, 'quantity' => 5, 'unit' => 'pcs', 'unit_cost' => 20]);

        $costing = app(ProductionCostingService::class);
        $costing->calculateBomCost($bom);

        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1, 'source_warehouse_id' => $wh->id], $user->id);

        StockMovement::create([
            'org_id' => $user->org_id,
            'product_id' => $mat->id,
            'warehouse_id' => $wh->id,
            'movement_type' => 'opening_balance',
            'movement_date' => '2026-09-01',
            'quantity' => 50,
            'unit_cost' => 20.00,
            'total_cost' => 1000.00,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_unit_cost' => 20.00,
            'base_total_cost' => 1000.00,
        ]);

        // Release to issue materials
        $costing->transitionStatus($wo, 'in_progress', [], $user->id);
        $this->assertSame(1, StockMovement::where('work_order_id', $wo->id)->count());

        // Now cancel
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.cancel', $wo->id))
            ->assertRedirect();

        $wo->refresh();
        $this->assertSame('cancelled', $wo->status);

        // Check return stock movement
        $returnMovement = StockMovement::where('work_order_id', $wo->id)
            ->where('movement_type', 'work_order_return')
            ->firstOrFail();

        $this->assertEquals(5.0, (float) $returnMovement->quantity);

        // Check GL reversal entry
        $reversal = JournalEntry::where('org_id', $user->org_id)
            ->where('source_type', 'work_order')
            ->where('source_id', $wo->id)
            ->where('posting_event', 'cancelled')
            ->first();

        $this->assertNotNull($reversal);
    }

    public function test_production_cost_recording_adjusts_total_and_unit_cost(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $prod = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-COST', 'name' => 'Product Cost Test', 'type' => 'product', 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-COST', 'product_id' => $prod->id, 'name' => 'BOM', 'quantity' => 2, 'unit' => 'pcs']);
        $costing = app(ProductionCostingService::class);

        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 2], $user->id);

        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.costs.store', $wo->id), [
                'cost_type' => 'labor',
                'description' => 'Overtime Assembly Technician',
                'amount' => 150.00,
            ])
            ->assertRedirect();

        $wo->refresh();
        $this->assertEquals(150.00, (float) $wo->labor_cost);
        $this->assertEquals(150.00, (float) $wo->total_cost);
        $this->assertEquals(75.00, (float) $wo->unit_cost);
    }

    public function test_manufacturing_permissions_and_multi_tenant_isolation(): void
    {
        $orgAUser = User::factory()->create();
        $this->attachRole($orgAUser, 'owner');

        $orgBUser = User::factory()->create();
        $this->attachRole($orgBUser, 'owner');

        $prodA = Product::create(['org_id' => $orgAUser->org_id, 'sku' => 'A-01', 'name' => 'Prod A', 'type' => 'product', 'is_active' => true]);
        $bomA = BillOfMaterials::create(['org_id' => $orgAUser->org_id, 'bom_no' => 'BOM-A-01', 'product_id' => $prodA->id, 'name' => 'BOM A', 'quantity' => 1, 'unit' => 'pcs']);

        // Org B attempts to update Org A's BOM -> 403 Forbidden
        $this->actingAsOrgUser($orgBUser)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('manufacturing.boms.update', $bomA->id), [
                'product_id' => $prodA->id,
                'name' => 'Hacked BOM',
                'quantity' => 1,
                'unit' => 'pcs',
                'items' => [],
            ])
            ->assertForbidden();

        // Org B queries BOM index -> cannot see Org A's BOM
        $response = $this->actingAsOrgUser($orgBUser)
            ->get(route('manufacturing.boms.index'))
            ->assertOk();

        $bomsInProp = $response->viewData('page')['props']['boms'];
        $this->assertEmpty($bomsInProp);
    }

    public function test_release_fails_if_source_warehouse_missing(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $fg = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-NOWH', 'name' => 'FG No WH', 'type' => 'product', 'is_active' => true]);
        $mat = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-NOWH', 'name' => 'Mat', 'type' => 'product', 'cost' => 10, 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-NOWH', 'product_id' => $fg->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $mat->id, 'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 10]);

        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1], $user->id);

        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.release', $wo->id))
            ->assertSessionHasErrors(['source_warehouse_id']);

        $wo->refresh();
        $this->assertSame('draft', $wo->status);
    }

    public function test_release_fails_with_negative_or_zero_quantity(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $wh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'WH-VAL', 'name' => 'WH', 'status' => 'active']);
        $fg = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-VAL', 'name' => 'FG', 'type' => 'product', 'is_active' => true]);
        $mat = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-VAL', 'name' => 'Mat', 'type' => 'product', 'cost' => 10, 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-VAL', 'product_id' => $fg->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $mat->id, 'quantity' => 2, 'unit' => 'pcs', 'unit_cost' => 10]);

        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1, 'source_warehouse_id' => $wh->id], $user->id);
        $item = $wo->items->first();

        // Release with 0 quantity
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.release', $wo->id), [
                'issued_items' => [$item->id => 0],
            ])
            ->assertSessionHasErrors();

        // Release with negative quantity
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.release', $wo->id), [
                'issued_items' => [$item->id => -5],
            ])
            ->assertSessionHasErrors();

        $wo->refresh();
        $this->assertSame('draft', $wo->status);
    }

    public function test_release_fails_with_foreign_item_id(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $wh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'WH-FOR', 'name' => 'WH', 'status' => 'active']);
        $fg = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-FOR', 'name' => 'FG', 'type' => 'product', 'is_active' => true]);
        $mat = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-FOR', 'name' => 'Mat', 'type' => 'product', 'cost' => 10, 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-FOR', 'product_id' => $fg->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $mat->id, 'quantity' => 2, 'unit' => 'pcs', 'unit_cost' => 10]);

        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1, 'source_warehouse_id' => $wh->id], $user->id);

        $fakeItemId = (string) Str::uuid();

        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.release', $wo->id), [
                'issued_items' => [$fakeItemId => 5],
            ])
            ->assertSessionHasErrors(['issued_items']);

        $wo->refresh();
        $this->assertSame('draft', $wo->status);
    }

    public function test_release_fails_with_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $wh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'WH-STOCK', 'name' => 'WH', 'status' => 'active']);
        $fg = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-STOCK', 'name' => 'FG', 'type' => 'product', 'is_active' => true]);
        $mat = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-STOCK', 'name' => 'Mat', 'type' => 'product', 'cost' => 10, 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-STOCK', 'product_id' => $fg->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $mat->id, 'quantity' => 10, 'unit' => 'pcs', 'unit_cost' => 10]);

        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1, 'source_warehouse_id' => $wh->id], $user->id);

        // Only seed 3 units on hand, but 10 needed
        StockMovement::create([
            'org_id' => $user->org_id,
            'product_id' => $mat->id,
            'warehouse_id' => $wh->id,
            'movement_type' => 'opening_balance',
            'movement_date' => '2026-09-01',
            'quantity' => 3,
            'unit_cost' => 10.00,
            'total_cost' => 30.00,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_unit_cost' => 10.00,
            'base_total_cost' => 30.00,
        ]);

        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.release', $wo->id))
            ->assertSessionHasErrors(['stock']);

        $wo->refresh();
        $this->assertSame('draft', $wo->status);
    }

    public function test_cannot_complete_work_order_without_quality_check(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');
        $this->ensureChartOfAccounts($user->org_id);

        $wh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'WH-QC', 'name' => 'WH', 'status' => 'active']);
        $fg = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-QC', 'name' => 'FG', 'type' => 'product', 'is_active' => true]);
        $mat = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-QC', 'name' => 'Mat', 'type' => 'product', 'cost' => 10, 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-QC', 'product_id' => $fg->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $mat->id, 'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 10]);

        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1, 'source_warehouse_id' => $wh->id, 'warehouse_id' => $wh->id], $user->id);

        StockMovement::create([
            'org_id' => $user->org_id,
            'product_id' => $mat->id,
            'warehouse_id' => $wh->id,
            'movement_type' => 'opening_balance',
            'movement_date' => '2026-09-01',
            'quantity' => 10,
            'unit_cost' => 10.00,
            'total_cost' => 100.00,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_unit_cost' => 10.00,
            'base_total_cost' => 100.00,
        ]);

        $costing->transitionStatus($wo, 'in_progress', [], $user->id);

        // Attempting to directly complete from in_progress must throw DomainException
        $this->expectException(DomainException::class);
        $costing->transitionStatus($wo, 'completed', [], $user->id);
    }

    public function test_cannot_complete_work_order_if_quality_check_failed(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');
        $this->ensureChartOfAccounts($user->org_id);

        $wh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'WH-FAIL', 'name' => 'WH', 'status' => 'active']);
        $fg = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-FAIL', 'name' => 'FG', 'type' => 'product', 'is_active' => true]);
        $mat = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-FAIL', 'name' => 'Mat', 'type' => 'product', 'cost' => 10, 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-FAIL', 'product_id' => $fg->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $mat->id, 'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 10]);

        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1, 'source_warehouse_id' => $wh->id, 'warehouse_id' => $wh->id], $user->id);

        StockMovement::create([
            'org_id' => $user->org_id,
            'product_id' => $mat->id,
            'warehouse_id' => $wh->id,
            'movement_type' => 'opening_balance',
            'movement_date' => '2026-09-01',
            'quantity' => 10,
            'unit_cost' => 10.00,
            'total_cost' => 100.00,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_unit_cost' => 10.00,
            'base_total_cost' => 100.00,
        ]);

        $costing->transitionStatus($wo, 'in_progress', [], $user->id);
        $costing->transitionStatus($wo, 'quality_check', ['qc_passed' => false, 'qc_notes' => 'Defects detected'], $user->id);

        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.complete', $wo->id))
            ->assertSessionHasErrors(['qc']);

        $wo->refresh();
        $this->assertSame('quality_check', $wo->status);
    }

    public function test_can_rework_failed_quality_check_and_complete(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');
        $this->ensureChartOfAccounts($user->org_id);

        $wh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'WH-REWORK', 'name' => 'WH', 'status' => 'active']);
        $fg = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-REWORK', 'name' => 'FG', 'type' => 'product', 'is_active' => true]);
        $mat = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-REWORK', 'name' => 'Mat', 'type' => 'product', 'cost' => 10, 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-REWORK', 'product_id' => $fg->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'product_id' => $mat->id, 'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 10]);

        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1, 'source_warehouse_id' => $wh->id, 'warehouse_id' => $wh->id], $user->id);

        StockMovement::create([
            'org_id' => $user->org_id,
            'product_id' => $mat->id,
            'warehouse_id' => $wh->id,
            'movement_type' => 'opening_balance',
            'movement_date' => '2026-09-01',
            'quantity' => 10,
            'unit_cost' => 10.00,
            'total_cost' => 100.00,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_unit_cost' => 10.00,
            'base_total_cost' => 100.00,
        ]);

        $costing->transitionStatus($wo, 'in_progress', [], $user->id);
        $costing->transitionStatus($wo, 'quality_check', ['qc_passed' => false, 'qc_notes' => 'First QC failed'], $user->id);

        // Transition back to in_progress for rework
        $costing->transitionStatus($wo, 'in_progress', [], $user->id);
        $wo->refresh();
        $this->assertSame('in_progress', $wo->status);

        // Perform QC again and pass
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.quality-check', $wo->id), [
                'actual_quantity' => 1,
                'scrap_quantity' => 0,
                'qc_notes' => 'Rework successful',
                'qc_passed' => true,
            ])
            ->assertRedirect();

        $wo->refresh();
        $this->assertTrue($wo->qc_passed);

        // Complete successfully
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.complete', $wo->id))
            ->assertRedirect();

        $wo->refresh();
        $this->assertSame('completed', $wo->status);
    }

    public function test_cannot_add_invalid_cost_type(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $prod = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-INVCOST', 'name' => 'FG', 'type' => 'product', 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-INVCOST', 'product_id' => $prod->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1], $user->id);

        // Material is not allowed via addCost
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.costs.store', $wo->id), [
                'cost_type' => 'material',
                'description' => 'Unabsorbed material cost',
                'amount' => 50.00,
            ])
            ->assertSessionHasErrors(['cost_type']);

        // Other is not allowed via addCost
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('manufacturing.work-orders.costs.store', $wo->id), [
                'cost_type' => 'other',
                'description' => 'Miscellaneous cost',
                'amount' => 20.00,
            ])
            ->assertSessionHasErrors(['cost_type']);
    }

    public function test_work_order_costs_and_release_enforce_idempotency(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');

        $prod = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-IDEMP', 'name' => 'FG', 'type' => 'product', 'is_active' => true]);
        $bom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-IDEMP', 'product_id' => $prod->id, 'name' => 'BOM', 'quantity' => 1, 'unit' => 'pcs']);
        $costing = app(ProductionCostingService::class);
        $wo = $costing->createWorkOrder(['org_id' => $user->org_id, 'bill_of_materials_id' => $bom->id, 'planned_quantity' => 1], $user->id);

        $submissionKey = (string) Str::uuid();

        // First cost post
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withHeader('X-Form-Submission', $submissionKey)
            ->post(route('manufacturing.work-orders.costs.store', $wo->id), [
                'cost_type' => 'labor',
                'description' => 'Technician Wage',
                'amount' => 100.00,
            ])
            ->assertRedirect();

        $wo->refresh();
        $this->assertEquals(100.00, (float) $wo->labor_cost);
        $this->assertCount(1, $wo->productionCosts);

        // Second cost post with identical key and payload -> idempotency protects, no duplicate cost recorded
        $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->withHeader('X-Form-Submission', $submissionKey)
            ->post(route('manufacturing.work-orders.costs.store', $wo->id), [
                'cost_type' => 'labor',
                'description' => 'Technician Wage',
                'amount' => 100.00,
            ])
            ->assertRedirect();

        $wo->refresh();
        $this->assertEquals(100.00, (float) $wo->labor_cost);
        $this->assertCount(1, $wo->productionCosts);
    }

    public function test_can_create_work_order_with_flattened_multi_level_materials(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner');
        $costing = app(ProductionCostingService::class);

        // Subassembly: Motor Module
        $rawWire = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-WIRE', 'name' => 'Copper Wire', 'type' => 'product', 'cost' => 20.00, 'is_active' => true]);
        $subMotor = Product::create(['org_id' => $user->org_id, 'sku' => 'SUB-MOTOR', 'name' => 'Motor Subassembly', 'type' => 'product', 'is_active' => true]);
        $motorBom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-MOTOR', 'product_id' => $subMotor->id, 'name' => 'Motor BOM', 'quantity' => 1, 'unit' => 'pcs']);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $motorBom->id, 'product_id' => $rawWire->id, 'quantity' => 2, 'unit' => 'm', 'unit_cost' => 20.00]);
        $costing->calculateBomCost($motorBom);

        // Master FG: Electric Fan (requires 1 Motor + 1 Plastic Housing)
        $rawPlastic = Product::create(['org_id' => $user->org_id, 'sku' => 'MAT-PLASTIC', 'name' => 'Plastic Resin', 'type' => 'product', 'cost' => 50.00, 'is_active' => true]);
        $fanProduct = Product::create(['org_id' => $user->org_id, 'sku' => 'FG-FAN', 'name' => 'Electric Fan', 'type' => 'product', 'is_active' => true]);
        $fanBom = BillOfMaterials::create(['org_id' => $user->org_id, 'bom_no' => 'BOM-FAN', 'product_id' => $fanProduct->id, 'name' => 'Fan Master BOM', 'quantity' => 1, 'unit' => 'pcs']);

        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $fanBom->id, 'product_id' => $subMotor->id, 'sub_bom_id' => $motorBom->id, 'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 40.00]);
        BomItem::create(['org_id' => $user->org_id, 'bill_of_materials_id' => $fanBom->id, 'product_id' => $rawPlastic->id, 'quantity' => 1, 'unit' => 'kg', 'unit_cost' => 50.00]);
        $costing->calculateBomCost($fanBom);

        // Create work order for 5 fans with flatten_materials = true
        $wo = $costing->createWorkOrder([
            'org_id' => $user->org_id,
            'bill_of_materials_id' => $fanBom->id,
            'planned_quantity' => 5,
            'flatten_materials' => true,
        ], $user->id);

        $this->assertCount(2, $wo->items);
        $wireItem = $wo->items->firstWhere('product_id', $rawWire->id);
        $plasticItem = $wo->items->firstWhere('product_id', $rawPlastic->id);

        $this->assertNotNull($wireItem);
        $this->assertNotNull($plasticItem);
        // 2m * 5 = 10m copper wire
        $this->assertEquals(10.0, (float) $wireItem->planned_quantity);
        // 1kg * 5 = 5kg plastic
        $this->assertEquals(5.0, (float) $plasticItem->planned_quantity);
    }

    private function ensureChartOfAccounts(string $orgId): void
    {
        app(ChartOfAccountProvisioner::class)->ensure($orgId);
    }
}
