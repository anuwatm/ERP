<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3InvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_existing_finance_roles_receive_invoice_permissions_from_migration(): void
    {
        $finance = User::factory()->create();
        $role = Role::create([
            'org_id' => $finance->org_id,
            'code' => 'finance',
            'name' => 'Finance',
            'is_system' => true,
        ]);
        $finance->roles()->attach($role->id);

        (require database_path('migrations/2026_07_27_000004_backfill_invoice_permissions.php'))->up();

        $this->actingAsOrgUser($finance)->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Finance/Invoices'));
    }

    public function test_finance_user_can_create_manual_invoice_with_server_calculated_totals(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.create']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000001', 'company_name' => 'Invoice Customer', 'owner_id' => $finance->id]);
        $product = Product::create(['org_id' => $finance->org_id, 'sku' => 'SVC-001', 'name' => 'Service Item', 'type' => 'service', 'price' => 1000]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'deal_id' => '',
                'status' => 'draft',
                'tax_mode' => 'exclusive',
                'issue_date' => '2026-07-27',
                'due_date' => '2026-08-10',
                'discount_amount' => '100.00',
                'currency' => 'THB',
                'items' => [[
                    'product_id' => $product->id,
                    'description' => 'Implementation service',
                    'quantity' => '2',
                    'unit' => 'job',
                    'unit_price' => '1000.00',
                    'discount_amount' => '0.00',
                    'tax_rate' => '7.00',
                ]],
            ])->assertRedirect();

        $invoice = Invoice::with('items')->where('org_id', $finance->org_id)->firstOrFail();
        $this->assertSame('000001', $invoice->invoice_no);
        $this->assertSame('2000.00', $invoice->subtotal);
        $this->assertSame('100.00', $invoice->discount_amount);
        $this->assertSame('133.00', $invoice->tax_amount);
        $this->assertSame('2033.00', $invoice->total);
        $this->assertSame('2033.00', $invoice->balance_due);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertTrue(AuditLog::where('action', 'invoice.create')->where('entity_id', $invoice->id)->exists());
    }

    public function test_header_discount_is_allocated_before_inclusive_vat_display(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.create']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000001', 'company_name' => 'VAT Customer', 'owner_id' => $finance->id]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'deal_id' => '',
                'status' => 'draft',
                'tax_mode' => 'inclusive',
                'issue_date' => '2026-07-27',
                'discount_amount' => '107.00',
                'currency' => 'THB',
                'items' => [[
                    'description' => 'VAT inclusive service',
                    'quantity' => '1',
                    'unit_price' => '1070.00',
                    'discount_amount' => '0.00',
                    'tax_rate' => '7.00',
                ]],
            ])->assertRedirect();

        $invoice = Invoice::where('org_id', $finance->org_id)->firstOrFail();
        $this->assertSame('1070.00', $invoice->subtotal);
        $this->assertSame('107.00', $invoice->discount_amount);
        $this->assertSame('63.00', $invoice->tax_amount);
        $this->assertSame('963.00', $invoice->total);
        $this->assertSame('963.00', $invoice->balance_due);
    }

    public function test_invoice_list_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.view']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000001', 'company_name' => 'Visible Customer', 'owner_id' => $finance->id]);
        $otherCustomer = Customer::create(['org_id' => $otherUser->org_id, 'customer_code' => '000001', 'company_name' => 'Hidden Customer', 'owner_id' => $otherUser->id]);
        Invoice::create(['org_id' => $finance->org_id, 'invoice_no' => '000001', 'customer_id' => $customer->id, 'status' => 'draft', 'tax_mode' => 'no_tax', 'issue_date' => '2026-07-27', 'total' => 100, 'balance_due' => 100]);
        Invoice::create(['org_id' => $otherUser->org_id, 'invoice_no' => '000001', 'customer_id' => $otherCustomer->id, 'status' => 'draft', 'tax_mode' => 'no_tax', 'issue_date' => '2026-07-27', 'total' => 200, 'balance_due' => 200]);

        $this->actingAsOrgUser($finance)->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Invoices')
                ->has('invoices', 1)
                ->where('invoices.0.customer.company_name', 'Visible Customer')
            );
    }

    public function test_invoice_list_keeps_soft_deleted_customer_and_product_history(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.view']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000001', 'company_name' => 'Historical Customer', 'owner_id' => $finance->id]);
        $product = Product::create(['org_id' => $finance->org_id, 'sku' => 'OLD-001', 'name' => 'Historical Product', 'type' => 'service', 'price' => 100]);
        $invoice = Invoice::create(['org_id' => $finance->org_id, 'invoice_no' => '000001', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => '2026-07-29', 'total' => 100, 'balance_due' => 100]);
        $invoice->items()->create(['org_id' => $finance->org_id, 'product_id' => $product->id, 'description' => 'Historical Product', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100, 'sort_order' => 0]);
        $customer->delete();
        $product->delete();

        $this->actingAsOrgUser($finance)->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Invoices')
                ->where('invoices.0.customer.company_name', 'Historical Customer')
                ->where('invoices.0.items.0.product.name', 'Historical Product')
            );
    }

    public function test_invoice_deal_must_belong_to_selected_customer(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.create']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000001', 'company_name' => 'Right Customer', 'owner_id' => $finance->id]);
        $otherCustomer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000002', 'company_name' => 'Other Customer', 'owner_id' => $finance->id]);
        $deal = Deal::create(['org_id' => $finance->org_id, 'title' => 'Other Deal', 'customer_id' => $otherCustomer->id, 'stage' => 'won', 'owner_id' => $finance->id]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'deal_id' => $deal->id,
                'status' => 'draft',
                'tax_mode' => 'no_tax',
                'issue_date' => '2026-07-27',
                'discount_amount' => '0.00',
                'currency' => 'THB',
                'items' => [[
                    'description' => 'Invalid chain',
                    'quantity' => '1',
                    'unit_price' => '1000.00',
                ]],
            ])->assertStatus(422);
    }

    public function test_invoice_update_is_blocked_after_payment_amount_exists(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.update']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000001', 'company_name' => 'Paid Customer', 'owner_id' => $finance->id]);
        $invoice = Invoice::create(['org_id' => $finance->org_id, 'invoice_no' => '000001', 'customer_id' => $customer->id, 'status' => 'partially_paid', 'tax_mode' => 'no_tax', 'issue_date' => '2026-07-27', 'subtotal' => 1000, 'total' => 1000, 'paid_amount' => 100, 'balance_due' => 900]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('invoices.update', $invoice), [
                'customer_id' => $customer->id,
                'status' => 'draft',
                'tax_mode' => 'no_tax',
                'issue_date' => '2026-07-27',
                'discount_amount' => '0.00',
                'currency' => 'THB',
                'items' => [[
                    'description' => 'Blocked',
                    'quantity' => '1',
                    'unit_price' => '1000.00',
                ]],
            ])->assertStatus(422);
    }

    public function test_invoice_can_be_voided_before_payment(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.void']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => '000001', 'company_name' => 'Void Customer', 'owner_id' => $finance->id]);
        $invoice = Invoice::create(['org_id' => $finance->org_id, 'invoice_no' => '000001', 'customer_id' => $customer->id, 'status' => 'draft', 'tax_mode' => 'no_tax', 'issue_date' => '2026-07-27', 'total' => 100, 'balance_due' => 100]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('invoices.void', $invoice))->assertRedirect();

        $this->assertSame('void', $invoice->refresh()->status);
        $this->assertNotNull($invoice->voided_at);
        $this->assertTrue(AuditLog::where('action', 'invoice.void')->where('entity_id', $invoice->id)->exists());
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
