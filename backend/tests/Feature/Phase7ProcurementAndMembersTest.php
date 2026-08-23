<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7ProcurementAndMembersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_supplier_crud_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete']);
        Supplier::create(['org_id' => $other->org_id, 'supplier_code' => '000001', 'name' => 'Hidden', 'status' => 'active']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('suppliers.store'), ['name' => 'Visible Supplier', 'status' => 'active'])
            ->assertRedirect();

        $supplier = Supplier::where('org_id', $finance->org_id)->firstOrFail();
        $this->assertSame('000001', $supplier->supplier_code);

        $this->actingAsOrgUser($finance)->get(route('suppliers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Suppliers')
                ->has('suppliers', 1)
                ->where('suppliers.0.name', 'Visible Supplier')
            );
    }

    public function test_purchase_order_totals_and_status_rules(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.approve', 'purchase_orders.cancel']);
        $supplier = Supplier::create(['org_id' => $finance->org_id, 'supplier_code' => '000001', 'name' => 'PO Supplier', 'status' => 'active']);

        $payload = [
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'order_date' => '2026-08-22',
            'tax_mode' => 'exclusive',
            'discount_amount' => '100.00',
            'currency' => 'THB',
            'items' => [[
                'description' => 'Service',
                'quantity' => '2',
                'unit_price' => '1000.00',
                'discount_amount' => '0.00',
                'tax_rate' => '7.00',
            ]],
        ];

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('purchase-orders.store'), $payload)
            ->assertRedirect();

        $po = PurchaseOrder::where('org_id', $finance->org_id)->firstOrFail();
        $this->assertSame('2000.00', $po->subtotal);
        $this->assertSame('100.00', $po->discount_amount);
        $this->assertSame('133.00', $po->tax_amount);
        $this->assertSame('2033.00', $po->total);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('purchase-orders.approve', $po))
            ->assertRedirect();
        $this->assertSame('approved', $po->fresh()->status);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('purchase-orders.update', $po), $payload)
            ->assertStatus(422);
    }

    public function test_expense_supplier_purchase_order_chain_is_validated(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.create']);
        $supplier = Supplier::create(['org_id' => $finance->org_id, 'supplier_code' => '000001', 'name' => 'Supplier A', 'status' => 'active']);
        $otherSupplier = Supplier::create(['org_id' => $finance->org_id, 'supplier_code' => '000002', 'name' => 'Supplier B', 'status' => 'active']);
        $po = PurchaseOrder::create(['org_id' => $finance->org_id, 'supplier_id' => $supplier->id, 'po_no' => '000001', 'status' => 'approved', 'order_date' => '2026-08-22', 'tax_mode' => 'no_tax', 'total' => 100]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), [
                'category' => 'contractor',
                'title' => 'Wrong supplier',
                'amount' => '100.00',
                'expense_date' => '2026-08-22',
                'supplier_id' => $otherSupplier->id,
                'purchase_order_id' => $po->id,
            ])
            ->assertSessionHasErrors('purchase_order_id');
    }

    public function test_project_members_can_view_project_and_be_removed(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['org_id' => $owner->org_id]);
        $this->attachRole($owner, 'project_manager', ['projects.view', 'projects.update']);
        $this->attachRole($member, 'member', ['projects.view']);
        $customer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => '000001', 'company_name' => 'Customer', 'owner_id' => $owner->id]);
        $project = Project::create(['org_id' => $owner->org_id, 'project_code' => '000001', 'name' => 'Member Project', 'customer_id' => $customer->id, 'owner_id' => $owner->id, 'status' => 'active', 'progress_percent' => 10, 'budget_amount' => 1000, 'currency' => 'THB']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('projects.members.store', $project), ['user_id' => $member->id, 'role' => 'member'])
            ->assertRedirect();

        $this->actingAsOrgUser($member)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Delivery/Projects')
                ->has('projects', 1)
                ->where('projects.0.name', 'Member Project')
            );

        $projectMember = ProjectMember::where('project_id', $project->id)->firstOrFail();
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('projects.members.destroy', [$project, $projectMember]))
            ->assertRedirect();
        $this->assertFalse(ProjectMember::whereKey($projectMember->id)->exists());
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
