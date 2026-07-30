<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3DealInvoiceReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_invoice_index_accepts_deal_source_for_prefill(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.view']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Deal Customer',
            'owner_id' => $finance->id,
        ]);
        $deal = Deal::create([
            'org_id' => $finance->org_id,
            'title' => 'Won implementation',
            'customer_id' => $customer->id,
            'stage' => 'won',
            'value_amount' => '1000.00',
            'currency' => 'THB',
            'probability' => 100,
            'owner_id' => $finance->id,
        ]);

        $this->actingAsOrgUser($finance)->get(route('invoices.index', ['deal_id' => $deal->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Invoices')
                ->where('sourceDeal.id', $deal->id)
                ->where('sourceDeal.customer_id', $customer->id)
            );
    }

    public function test_deal_list_marks_needs_sales_review_after_void_without_active_invoice(): void
    {
        $sales = User::factory()->create();
        $this->attachRole($sales, 'sales', ['deals.view']);
        $customer = Customer::create([
            'org_id' => $sales->org_id,
            'customer_code' => '000001',
            'company_name' => 'Review Customer',
            'owner_id' => $sales->id,
        ]);
        $deal = Deal::create([
            'org_id' => $sales->org_id,
            'title' => 'Review Deal',
            'customer_id' => $customer->id,
            'stage' => 'won',
            'value_amount' => '1500.00',
            'currency' => 'THB',
            'probability' => 100,
            'owner_id' => $sales->id,
        ]);
        $this->invoice($sales, $customer, $deal, '000001', 'void');

        $this->actingAsOrgUser($sales)->get(route('deals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Deals')
                ->where('deals.0.id', $deal->id)
                ->where('deals.0.needs_sales_review', true)
            );
    }

    public function test_needs_sales_review_is_false_when_deal_has_active_invoice(): void
    {
        $sales = User::factory()->create();
        $this->attachRole($sales, 'sales', ['deals.view']);
        $customer = Customer::create([
            'org_id' => $sales->org_id,
            'customer_code' => '000001',
            'company_name' => 'Active Customer',
            'owner_id' => $sales->id,
        ]);
        $deal = Deal::create([
            'org_id' => $sales->org_id,
            'title' => 'Active Deal',
            'customer_id' => $customer->id,
            'stage' => 'won',
            'value_amount' => '1500.00',
            'currency' => 'THB',
            'probability' => 100,
            'owner_id' => $sales->id,
        ]);
        $this->invoice($sales, $customer, $deal, '000001', 'void');
        $this->invoice($sales, $customer, $deal, '000002', 'sent');

        $this->actingAsOrgUser($sales)->get(route('deals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deals.0.needs_sales_review', false)
            );
    }

    public function test_invoice_list_marks_void_deal_invoice_needs_sales_review(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.view']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Invoice Review Customer',
            'owner_id' => $finance->id,
        ]);
        $deal = Deal::create([
            'org_id' => $finance->org_id,
            'title' => 'Invoice Review Deal',
            'customer_id' => $customer->id,
            'stage' => 'won',
            'value_amount' => '1500.00',
            'currency' => 'THB',
            'probability' => 100,
            'owner_id' => $finance->id,
        ]);
        $invoice = $this->invoice($finance, $customer, $deal, '000001', 'void');

        $this->actingAsOrgUser($finance)->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Invoices')
                ->where('invoices.0.id', $invoice->id)
                ->where('invoices.0.needs_sales_review', true)
            );
    }

    private function invoice(User $user, Customer $customer, Deal $deal, string $invoiceNo, string $status): Invoice
    {
        return Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => $invoiceNo,
            'customer_id' => $customer->id,
            'deal_id' => $deal->id,
            'status' => $status,
            'tax_mode' => 'no_tax',
            'issue_date' => '2026-07-29',
            'subtotal' => '1000.00',
            'total' => '1000.00',
            'balance_due' => $status === 'paid' ? '0.00' : '1000.00',
            'paid_amount' => $status === 'paid' ? '1000.00' : '0.00',
        ]);
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
