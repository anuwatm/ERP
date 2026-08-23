<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8OfficialDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_invoice_print_view_is_org_scoped_and_includes_baht_text(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.view']);
        $finance->organization->update([
            'legal_name' => 'ERP Legal Co., Ltd.',
            'tax_id' => '0105559000001',
            'address' => 'Bangkok',
            'phone' => '021234567',
            'email' => 'finance@example.test',
            'logo_url' => 'logos/org.png',
        ]);
        $branch = Branch::where('org_id', $finance->org_id)->firstOrFail();
        $branch->update(['name' => 'Bangkok HQ', 'address' => 'Branch Address', 'phone' => '029999999']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Customer Tax Co.',
            'tax_id' => '0105559000002',
            'address' => 'Customer Address',
            'owner_id' => $finance->id,
        ]);
        $invoice = Invoice::create([
            'org_id' => $finance->org_id,
            'branch_id' => $branch->id,
            'invoice_no' => 'INV-202608-00001',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'tax_mode' => 'exclusive',
            'issue_date' => '2026-08-23',
            'due_date' => '2026-09-07',
            'subtotal' => 1900,
            'discount_amount' => 0,
            'tax_amount' => 133,
            'total' => 2033,
            'balance_due' => 2033,
            'currency' => 'THB',
        ]);
        $invoice->items()->create([
            'org_id' => $finance->org_id,
            'description' => 'Implementation service',
            'quantity' => 2,
            'unit' => 'job',
            'unit_price' => 950,
            'tax_rate' => 7,
            'line_total' => 1900,
            'sort_order' => 0,
        ]);

        $this->actingAsOrgUser($finance)
            ->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('ERP Legal Co., Ltd.')
            ->assertSee('/storage/logos/org.png')
            ->assertSee('Branch: Head Office')
            ->assertSee('Branch Address')
            ->assertSee('Customer Tax Co.')
            ->assertSee('INV-202608-00001')
            ->assertSee('สองพันสามสิบสามบาทถ้วน')
            ->assertSee('Print / Save PDF');
    }

    public function test_purchase_order_print_view_marks_cancelled_documents_void(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['purchase_orders.view']);
        $supplier = Supplier::create([
            'org_id' => $finance->org_id,
            'supplier_code' => '000001',
            'name' => 'Supplier Tax Co.',
            'tax_id' => '0105559000003',
            'status' => 'active',
        ]);
        $po = PurchaseOrder::create([
            'org_id' => $finance->org_id,
            'supplier_id' => $supplier->id,
            'po_no' => 'PO-202608-00001',
            'status' => 'cancelled',
            'order_date' => '2026-08-23',
            'tax_mode' => 'inclusive',
            'subtotal' => 1070,
            'discount_amount' => 0,
            'tax_amount' => 70,
            'total' => 1070,
            'currency' => 'THB',
        ]);
        $po->items()->create([
            'org_id' => $finance->org_id,
            'description' => 'Inventory item',
            'quantity' => 1,
            'unit' => 'unit',
            'unit_price' => 1070,
            'tax_rate' => 7,
            'line_total' => 1070,
            'sort_order' => 0,
        ]);

        $this->actingAsOrgUser($finance)
            ->get(route('purchase-orders.print', $po))
            ->assertOk()
            ->assertSee('Purchase Order')
            ->assertSee('PO-202608-00001')
            ->assertSee('Supplier Tax Co.')
            ->assertSee('VOID')
            ->assertSee('หนึ่งพันเจ็ดสิบบาทถ้วน');
    }

    public function test_invoice_and_purchase_order_pdf_downloads_are_binary(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.view', 'purchase_orders.view']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'PDF Customer',
            'owner_id' => $finance->id,
        ]);
        $invoice = Invoice::create([
            'org_id' => $finance->org_id,
            'invoice_no' => 'INV-PDF',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'tax_mode' => 'no_tax',
            'issue_date' => '2026-08-23',
            'total' => 100,
            'balance_due' => 100,
            'currency' => 'THB',
        ]);
        $invoice->items()->create(['org_id' => $finance->org_id, 'description' => 'PDF line', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100, 'sort_order' => 0]);
        $supplier = Supplier::create(['org_id' => $finance->org_id, 'supplier_code' => '000001', 'name' => 'PDF Supplier', 'status' => 'active']);
        $po = PurchaseOrder::create(['org_id' => $finance->org_id, 'supplier_id' => $supplier->id, 'po_no' => 'PO-PDF', 'status' => 'sent', 'order_date' => '2026-08-23', 'tax_mode' => 'no_tax', 'total' => 100, 'currency' => 'THB']);
        $po->items()->create(['org_id' => $finance->org_id, 'description' => 'PDF PO line', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100, 'sort_order' => 0]);

        $invoiceResponse = $this->actingAsOrgUser($finance)->get(route('invoices.pdf', $invoice));
        $invoiceResponse->assertOk();
        $this->assertStringStartsWith('%PDF', $invoiceResponse->getContent());

        $poResponse = $this->actingAsOrgUser($finance)->get(route('purchase-orders.pdf', $po));
        $poResponse->assertOk();
        $this->assertStringStartsWith('%PDF', $poResponse->getContent());
    }

    public function test_withholding_certificate_pdf_download_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.view']);
        $supplier = Supplier::create(['org_id' => $finance->org_id, 'supplier_code' => '000001', 'name' => 'WHT Supplier', 'tax_id' => '0105559000303', 'status' => 'active']);
        $expense = Expense::create([
            'org_id' => $finance->org_id,
            'expense_no' => 'EXP-WHT-PDF',
            'category' => 'contractor',
            'title' => 'Consulting',
            'amount' => 10000,
            'withholding_tax_rate' => 3,
            'withholding_tax_amount' => 300,
            'withholding_tax_form' => 'pnd53',
            'expense_date' => '2026-08-23',
            'supplier_id' => $supplier->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAsOrgUser($finance)->get(route('expenses.withholding-certificate', $expense));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAsOrgUser($other)
            ->get(route('expenses.withholding-certificate', $expense))
            ->assertForbidden();
    }

    public function test_print_view_blocks_cross_org_documents(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.view']);
        $customer = Customer::create([
            'org_id' => $other->org_id,
            'customer_code' => '000001',
            'company_name' => 'Hidden Customer',
            'owner_id' => $other->id,
        ]);
        $invoice = Invoice::create([
            'org_id' => $other->org_id,
            'invoice_no' => 'INV-HIDDEN',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'tax_mode' => 'no_tax',
            'issue_date' => '2026-08-23',
            'total' => 100,
            'balance_due' => 100,
        ]);

        $this->actingAsOrgUser($finance)
            ->get(route('invoices.print', $invoice))
            ->assertForbidden();
        $this->actingAsOrgUser($finance)
            ->get(route('invoices.pdf', $invoice))
            ->assertForbidden();
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
