<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase4FinanceProjectLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_invoice_can_link_to_project_for_same_customer(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.create']);
        $customer = $this->customer($finance, '000001', 'Invoice Project Customer');
        $project = $this->project($finance, $customer, '000001', 'Invoice Project');
        $product = Product::create(['org_id' => $finance->org_id, 'sku' => 'SVC-001', 'name' => 'Service Item', 'type' => 'service', 'price' => 1000]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.store'), $this->invoicePayload($customer, $product, ['project_id' => $project->id]))
            ->assertRedirect();

        $invoice = Invoice::where('org_id', $finance->org_id)->firstOrFail();
        $this->assertSame($project->id, $invoice->project_id);
    }

    public function test_invoice_project_must_belong_to_selected_customer(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.create']);
        $customer = $this->customer($finance, '000001', 'Right Customer');
        $otherCustomer = $this->customer($finance, '000002', 'Other Customer');
        $project = $this->project($finance, $otherCustomer, '000001', 'Other Project');
        $product = Product::create(['org_id' => $finance->org_id, 'sku' => 'SVC-001', 'name' => 'Service Item', 'type' => 'service', 'price' => 1000]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.store'), $this->invoicePayload($customer, $product, ['project_id' => $project->id]))
            ->assertStatus(422);
    }

    public function test_expense_can_link_to_project_and_reject_cross_org_project(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.create']);
        $customer = $this->customer($finance, '000001', 'Expense Customer');
        $project = $this->project($finance, $customer, '000001', 'Expense Project');
        $otherProject = $this->project($other, $this->customer($other, '000001', 'Other Customer'), '000001', 'Hidden Project');

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), $this->expensePayload(['project_id' => $project->id]))
            ->assertRedirect();
        $this->assertSame($project->id, Expense::where('org_id', $finance->org_id)->firstOrFail()->project_id);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), $this->expensePayload(['project_id' => $otherProject->id]))
            ->assertSessionHasErrors('project_id');
    }

    public function test_project_actual_cost_is_dynamic_from_approved_and_paid_expenses_only(): void
    {
        $manager = User::factory()->create();
        $this->attachRole($manager, 'project_manager', ['projects.view']);
        $customer = $this->customer($manager, '000001', 'Cost Customer');
        $project = $this->project($manager, $customer, '000001', 'Cost Project', ['budget_amount' => '5000.00']);
        $this->expense($manager, $project, 'approved', '1200.00', '000001');
        $this->expense($manager, $project, 'paid', '800.00', '000002');
        $this->expense($manager, $project, 'draft', '700.00', '000003');
        $this->expense($manager, $project, 'rejected', '300.00', '000004');

        $this->actingAsOrgUser($manager)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Projects')
                ->where('projects.0.actual_cost', '2000.00')
                ->where('projects.0.gross_margin', '3000.00')
            );
    }

    private function customer(User $user, string $code, string $name): Customer
    {
        return Customer::create(['org_id' => $user->org_id, 'customer_code' => $code, 'company_name' => $name, 'owner_id' => $user->id]);
    }

    private function project(User $user, Customer $customer, string $code, string $name, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'org_id' => $user->org_id,
            'project_code' => $code,
            'name' => $name,
            'customer_id' => $customer->id,
            'owner_id' => $user->id,
            'status' => 'planning',
            'progress_percent' => 0,
            'budget_amount' => '0.00',
            'currency' => 'THB',
        ], $overrides));
    }

    private function expense(User $user, Project $project, string $status, string $amount, string $no): Expense
    {
        return Expense::create([
            'org_id' => $user->org_id,
            'expense_no' => $no,
            'category' => 'hosting',
            'title' => 'Project cost '.$no,
            'amount' => $amount,
            'expense_date' => '2026-07-30',
            'project_id' => $project->id,
            'status' => $status,
            'created_by' => $user->id,
        ]);
    }

    private function invoicePayload(Customer $customer, Product $product, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $customer->id,
            'deal_id' => '',
            'project_id' => '',
            'status' => 'draft',
            'tax_mode' => 'no_tax',
            'issue_date' => '2026-07-30',
            'discount_amount' => '0.00',
            'currency' => 'THB',
            'items' => [[
                'product_id' => $product->id,
                'description' => 'Implementation service',
                'quantity' => '1',
                'unit_price' => '1000.00',
            ]],
        ], $overrides);
    }

    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'hosting',
            'title' => 'Hosting bill',
            'amount' => '1200.00',
            'expense_date' => '2026-07-30',
            'project_id' => null,
            'supplier_id' => null,
            'note' => 'Cloud host',
        ], $overrides);
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => $code,
            'name' => Str::headline($code),
            'is_system' => true,
        ]);

        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => $parts[0], 'action' => $parts[count($parts) - 1]]
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $role;
    }
}
