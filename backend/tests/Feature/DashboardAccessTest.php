<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DashboardAccess;
use App\Support\DashboardLanding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_scope_uses_effective_permissions_not_role_name(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'owner', ['dashboard.view']);

        $this->assertTrue(DashboardAccess::canViewScope($user, 'admin'));
        $this->assertFalse(DashboardAccess::canViewScope($user, 'executive'));
        $this->assertFalse(DashboardAccess::canViewWidget($user, 'finance.revenue'));
    }

    public function test_finance_widget_requires_its_own_source_permission(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'custom_finance', ['dashboard.view', 'invoices.view']);

        $this->assertFalse(DashboardAccess::canViewScope($user, 'finance'));
        $this->assertTrue(DashboardAccess::canViewWidget($user, 'finance.revenue'));
        $this->assertTrue(DashboardAccess::canViewWidget($user, 'finance.ar'));
        $this->assertFalse(DashboardAccess::canViewWidget($user, 'finance.cash_in'));
        $this->assertFalse(DashboardAccess::canViewWidget($user, 'finance.expense'));
    }

    public function test_delivery_scope_requires_dashboard_and_source_permission(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'project_viewer', ['projects.view']);

        $this->assertFalse(DashboardAccess::canViewScope($user, 'delivery'));

        $this->attachPermissions($user, 'dashboard_viewer', ['dashboard.view']);

        $this->assertTrue(DashboardAccess::canViewScope($user, 'delivery'));
        $this->assertTrue(DashboardAccess::canViewWidget($user, 'delivery.projects'));
        $this->assertFalse(DashboardAccess::canViewWidget($user, 'delivery.tasks'));
    }

    public function test_finance_widget_payload_queries_only_authorized_sources(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'limited_finance', [
            'dashboard.view',
            'expenses.view',
            'invoices.view',
        ]);

        $this->actingAs($user)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('dashboardWidgets.finance.revenue')
                ->has('dashboardWidgets.finance.ar')
                ->has('dashboardWidgets.finance.expense')
                ->missing('dashboardWidgets.finance.cash_in')
                ->where('dashboardWidgets.finance.cash_flow_trend.11.cash_in', null)
                ->where('financeSummary', null));
    }

    public function test_admin_widget_payload_does_not_require_or_query_other_admin_sources(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'limited_admin', [
            'dashboard.view',
            'users.view',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('dashboardWidgets.admin.users')
                ->missing('dashboardWidgets.admin.roles')
                ->missing('dashboardWidgets.admin.audit')
                ->where('summary', null)
                ->where('securityAlerts', null));
    }

    public function test_admin_widgets_and_drilldowns_are_scoped_to_the_organization(): void
    {
        $admin = User::factory()->create();
        $this->attachPermissions($admin, 'admin_viewer', ['dashboard.view', 'users.view', 'roles.view', 'audit.view']);
        $memberRole = Role::create(['org_id' => $admin->org_id, 'code' => 'team_member', 'name' => 'Team Member', 'is_system' => false]);
        $invite = User::factory()->invited()->create([
            'org_id' => $admin->org_id,
            'invite_expires_at' => now()->subDay(),
        ]);
        $invite->roles()->attach($memberRole->id);
        $outsider = User::factory()->invited()->create(['invite_expires_at' => now()->subDay()]);
        $audit = AuditLog::create([
            'org_id' => $admin->org_id,
            'actor_user_id' => $admin->id,
            'action' => 'user.disable',
            'entity_type' => 'user',
            'entity_id' => $invite->id,
        ]);
        AuditLog::create([
            'org_id' => $outsider->org_id,
            'action' => 'user.disable',
            'entity_type' => 'user',
            'entity_id' => $outsider->id,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardWidgets.admin.users.invited', 0)
                ->where('dashboardWidgets.admin.users.expired_invites', 1)
                ->where('dashboardWidgets.admin.roles.distribution.1.id', $memberRole->id)
                ->where('dashboardWidgets.admin.audit.today', 1)
                ->where('dashboardWidgets.admin.audit.sensitive_24h', 1)
                ->where('dashboardWidgets.admin.recent_audits.0.id', $audit->id));

        $this->actingAs($admin)->get(route('users.index', ['status' => 'invited', 'expired' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('users', 1)->where('users.0.id', $invite->id));
        $this->actingAs($admin)->get(route('users.index', ['role_id' => $memberRole->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('users', 1)->where('users.0.id', $invite->id));
        $this->actingAs($admin)->get(route('users.index', ['user_id' => $invite->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('users', 1)->where('users.0.id', $invite->id));
        $this->actingAs($admin)->get(route('roles.index', ['role_id' => $memberRole->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('roles', 1)->where('roles.0.id', $memberRole->id));
        $this->actingAs($admin)->get(route('audit-logs.index', ['audit_id' => $audit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('auditLogs', 1)->where('auditLogs.0.id', $audit->id));
        $this->actingAs($admin)->get(route('audit-logs.index', ['window' => '24h', 'sensitive' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('auditLogs', 1)->where('auditLogs.0.id', $audit->id));
    }

    public function test_procurement_dashboard_scopes_po_payload_and_filtered_destination(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'po_viewer', ['purchase_orders.view']);
        $supplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => 'SUP-1', 'name' => 'Local supplier', 'status' => 'active']);
        $local = PurchaseOrder::create(['org_id' => $user->org_id, 'supplier_id' => $supplier->id, 'po_no' => 'PO-LOCAL', 'status' => 'approved', 'order_date' => today(), 'total' => 120, 'currency' => 'THB']);
        $otherUser = User::factory()->create();
        $otherSupplier = Supplier::create(['org_id' => $otherUser->org_id, 'supplier_code' => 'SUP-2', 'name' => 'Other supplier', 'status' => 'active']);
        PurchaseOrder::create(['org_id' => $otherUser->org_id, 'supplier_id' => $otherSupplier->id, 'po_no' => 'PO-OTHER', 'status' => 'approved', 'order_date' => today(), 'total' => 999, 'currency' => 'THB']);

        $this->actingAs($user)->get(route('procurement.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('openPoCount', 1)
                ->where('openPos.0.id', $local->id)
                ->where('supplierValues.0.total', 120)
                ->where('receipts', null)
                ->where('pendingApprovals', null));
        $this->actingAs($user)->get(route('purchase-orders.index', ['po_id' => $local->id]))
            ->assertOk()->assertInertia(fn ($page) => $page->has('purchaseOrders', 1)->where('purchaseOrders.0.id', $local->id));
    }

    public function test_procurement_dashboard_requires_a_source_permission(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'unrelated', ['dashboard.view']);
        $this->actingAs($user)->get(route('procurement.dashboard'))->assertForbidden();
    }

    public function test_low_stock_counts_tracked_product_warehouse_locations_and_drilldown_matches(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'inventory_viewer', ['inventory.view']);
        $lowWarehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'LOW', 'name' => 'Low warehouse']);
        $fullWarehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'FULL', 'name' => 'Full warehouse']);
        $tracked = Product::create(['org_id' => $user->org_id, 'name' => 'Tracked', 'type' => 'product', 'track_inventory' => true, 'reorder_point' => 5]);
        $untracked = Product::create(['org_id' => $user->org_id, 'name' => 'Untracked', 'type' => 'product', 'track_inventory' => false, 'reorder_point' => 5]);
        foreach ([
            [$tracked->id, $lowWarehouse->id, 3],
            [$tracked->id, $fullWarehouse->id, 9],
            [$untracked->id, $lowWarehouse->id, 1],
            [$tracked->id, null, -100],
        ] as [$productId, $warehouseId, $quantity]) {
            StockMovement::create(['org_id' => $user->org_id, 'product_id' => $productId, 'warehouse_id' => $warehouseId, 'movement_type' => 'adjustment', 'movement_date' => today(), 'quantity' => $quantity]);
        }
        $outsider = User::factory()->create();
        $otherWarehouse = Warehouse::create(['org_id' => $outsider->org_id, 'code' => 'OTHER', 'name' => 'Other warehouse']);
        $otherProduct = Product::create(['org_id' => $outsider->org_id, 'name' => 'Other', 'type' => 'product', 'track_inventory' => true, 'reorder_point' => 5]);
        StockMovement::create(['org_id' => $outsider->org_id, 'product_id' => $otherProduct->id, 'warehouse_id' => $otherWarehouse->id, 'movement_type' => 'adjustment', 'movement_date' => today(), 'quantity' => 1]);

        $this->actingAs($user)->get(route('procurement.dashboard'))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->where('lowStockCount', 1)
            ->has('lowStock', 1)
            ->where('lowStock.0.product_id', $tracked->id)
            ->where('lowStock.0.warehouse_id', $lowWarehouse->id));
        $this->actingAs($user)->get(route('inventory-operations.index', ['product_id' => $tracked->id, 'warehouse_id' => $lowWarehouse->id]))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->has('lowStock', 1)
            ->where('lowStock.0.product_id', $tracked->id)
            ->where('lowStock.0.warehouse_id', $lowWarehouse->id));
        $this->actingAs($user)->get(route('inventory-operations.index', ['product_id' => $tracked->id, 'warehouse_id' => $fullWarehouse->id]))
            ->assertOk()->assertInertia(fn ($page) => $page->has('lowStock', 0));
    }

    public function test_low_stock_dashboard_query_count_stays_bounded_with_many_movements(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'inventory_viewer', ['inventory.view']);
        $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'BULK', 'name' => 'Bulk warehouse']);
        $product = Product::create(['org_id' => $user->org_id, 'name' => 'Bulk tracked', 'type' => 'product', 'track_inventory' => true, 'reorder_point' => 10]);
        StockMovement::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'adjustment', 'movement_date' => today(), 'quantity' => 1]);

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });
        $this->actingAs($user)->get(route('procurement.dashboard'))
            ->assertOk()->assertInertia(fn ($page) => $page->where('lowStockCount', 1)->has('lowStock', 1));
        $baselineQueries = $queryCount;

        $now = now();
        for ($batch = 0; $batch < 12; $batch++) {
            $rows = [];
            for ($item = 0; $item < 50; $item++) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'org_id' => $user->org_id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'movement_type' => 'adjustment',
                    'movement_date' => today()->toDateString(),
                    'quantity' => 0.01,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('stock_movements')->insert($rows);
        }

        $queryCount = 0;
        $this->get(route('procurement.dashboard'))
            ->assertOk()->assertInertia(fn ($page) => $page->where('lowStockCount', 1)->has('lowStock', 1));
        $this->assertLessThanOrEqual($baselineQueries + 2, $queryCount);
    }

    public function test_hr_dashboard_keeps_hr_payload_out_of_payroll_only_view(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'payroll_viewer', ['payroll.view']);

        $this->actingAs($user)->get(route('hr.dashboard'))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->where('can.hr', false)
            ->where('can.payroll', true)
            ->where('pendingLeave', null)
            ->where('onLeaveToday', null)
            ->where('pendingLeaveCount', null));
    }

    public function test_permission_based_landing_does_not_send_module_viewer_to_forbidden_dashboard(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'task_viewer', ['tasks.view']);

        $this->assertSame(route('my-work.dashboard', absolute: false), DashboardLanding::routeFor($user));
        $this->actingAs($user)->get(DashboardLanding::routeFor($user))->assertOk();
    }

    /** @param list<string> $codes */
    public function test_request_permission_snapshot_is_fresh_after_revocation_and_actor_switch(): void
    {
        $user = User::factory()->create();
        $this->attachPermissions($user, 'finance_reader', ['dashboard.view', 'expenses.view', 'invoices.view']);
        $this->actingAs($user)->get(route('finance.dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('dashboardWidgets.finance.revenue'));

        $invoicePermission = Permission::where('code', 'invoices.view')->firstOrFail();
        $user->roles()->firstOrFail()->permissions()->detach($invoicePermission->id);
        $this->get(route('finance.dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->missing('dashboardWidgets.finance.revenue')
                ->where('dashboardCapabilities.finance_invoices', false));

        $other = User::factory()->create();
        $this->attachPermissions($other, 'dashboard_only', ['dashboard.view']);
        $this->actingAs($other)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->missing('dashboardWidgets.finance')
                ->where('dashboardCapabilities.finance_expenses', false));
        $this->get(route('finance.dashboard'))->assertForbidden();

        $user->roles()->firstOrFail()->permissions()->detach();
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    private function attachPermissions(User $user, string $roleCode, array $codes): void
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => $roleCode,
            'name' => str($roleCode)->headline()->toString(),
            'is_system' => false,
        ]);

        foreach ($codes as $code) {
            $parts = explode('.', $code);
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                ['module' => $parts[0], 'action' => $parts[array_key_last($parts)], 'description' => $code],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
