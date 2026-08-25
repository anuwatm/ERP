<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase9QuotationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_sales_user_can_create_quotation_with_server_calculated_totals(): void
    {
        $sales = User::factory()->create();
        $this->attachRole($sales, 'sales', ['quotations.create', 'quotations.view']);
        $customer = Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000001', 'company_name' => 'Quote Customer', 'owner_id' => $sales->id]);
        $product = Product::create(['org_id' => $sales->org_id, 'sku' => 'Q-001', 'name' => 'Quoted Service', 'type' => 'service', 'price' => 1000]);

        $this->actingAsOrgUser($sales)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('quotations.store'), [
                'customer_id' => $customer->id,
                'deal_id' => '',
                'status' => 'sent',
                'tax_mode' => 'exclusive',
                'issue_date' => '2026-08-25',
                'valid_until' => '2026-09-25',
                'discount_amount' => '100.00',
                'currency' => 'THB',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Implementation',
                    'quantity' => '2',
                    'unit' => 'job',
                    'unit_price' => '1000.00',
                    'discount_amount' => '0.00',
                    'tax_rate' => '7.00',
                ]],
            ])->assertRedirect();

        $quotation = Quotation::with('items')->where('org_id', $sales->org_id)->firstOrFail();
        $this->assertSame('000001', $quotation->quotation_no);
        $this->assertSame('sent', $quotation->status);
        $this->assertSame('2000.00', $quotation->subtotal);
        $this->assertSame('100.00', $quotation->discount_amount);
        $this->assertSame('133.00', $quotation->tax_amount);
        $this->assertSame('2033.00', $quotation->total);
        $this->assertSame(1, $quotation->items()->count());
        $this->assertTrue(AuditLog::where('action', 'quotation.create')->where('entity_id', $quotation->id)->exists());
    }

    public function test_quotation_list_is_org_scoped(): void
    {
        $sales = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($sales, 'sales', ['quotations.view']);
        $customer = Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000001', 'company_name' => 'Visible Customer', 'owner_id' => $sales->id]);
        $otherCustomer = Customer::create(['org_id' => $other->org_id, 'customer_code' => '000001', 'company_name' => 'Hidden Customer', 'owner_id' => $other->id]);

        Quotation::create(['org_id' => $sales->org_id, 'quotation_no' => '000001', 'customer_id' => $customer->id, 'status' => 'draft', 'tax_mode' => 'no_tax', 'issue_date' => '2026-08-25', 'total' => 100]);
        Quotation::create(['org_id' => $other->org_id, 'quotation_no' => '000001', 'customer_id' => $otherCustomer->id, 'status' => 'draft', 'tax_mode' => 'no_tax', 'issue_date' => '2026-08-25', 'total' => 200]);

        $this->actingAsOrgUser($sales)->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Quotations')
                ->has('quotations', 1)
                ->where('quotations.0.customer.company_name', 'Visible Customer')
            );
    }

    public function test_quotation_deal_must_belong_to_selected_customer(): void
    {
        $sales = User::factory()->create();
        $this->attachRole($sales, 'sales', ['quotations.create']);
        $customer = Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000001', 'company_name' => 'Right Customer', 'owner_id' => $sales->id]);
        $otherCustomer = Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000002', 'company_name' => 'Other Customer', 'owner_id' => $sales->id]);
        $deal = Deal::create(['org_id' => $sales->org_id, 'title' => 'Other Deal', 'customer_id' => $otherCustomer->id, 'stage' => 'proposal', 'owner_id' => $sales->id]);

        $this->actingAsOrgUser($sales)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('quotations.store'), [
                'customer_id' => $customer->id,
                'deal_id' => $deal->id,
                'status' => 'draft',
                'tax_mode' => 'no_tax',
                'issue_date' => '2026-08-25',
                'discount_amount' => '0.00',
                'currency' => 'THB',
                'items' => [[
                    'description' => 'Invalid chain',
                    'quantity' => '1',
                    'unit_price' => '1000.00',
                ]],
            ])->assertStatus(422);
    }

    public function test_approved_quotation_can_convert_to_invoice_once(): void
    {
        $sales = User::factory()->create();
        $this->attachRole($sales, 'sales', ['quotations.approve', 'quotations.convert', 'invoices.view']);
        $customer = Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000001', 'company_name' => 'Convert Customer', 'owner_id' => $sales->id]);
        $quotation = Quotation::create([
            'org_id' => $sales->org_id,
            'branch_id' => $sales->branch_id,
            'quotation_no' => '000001',
            'customer_id' => $customer->id,
            'status' => 'approved',
            'tax_mode' => 'exclusive',
            'issue_date' => '2026-08-25',
            'subtotal' => 1000,
            'tax_amount' => 70,
            'total' => 1070,
            'currency' => 'THB',
        ]);
        $quotation->items()->create(['org_id' => $sales->org_id, 'description' => 'Converted line', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 7, 'line_total' => 1000, 'sort_order' => 0]);

        $this->actingAsOrgUser($sales)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('quotations.convert-to-invoice', $quotation))
            ->assertRedirect(route('invoices.index', ['search' => '000001']));

        $invoice = Invoice::with('items')->where('quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame('000001', $invoice->invoice_no);
        $this->assertSame('1070.00', $invoice->balance_due);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame($invoice->id, $quotation->fresh()->converted_invoice_id);

        $this->actingAsOrgUser($sales)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('quotations.convert-to-invoice', $quotation->fresh()))
            ->assertStatus(422);
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
