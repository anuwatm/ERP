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

class Phase8TaxReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_tax_report_page_shows_sales_and_purchase_tax_rows(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['tax_reports.view']);
        $branch = Branch::where('org_id', $finance->org_id)->firstOrFail();
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Tax Customer',
            'tax_id' => '0105559000101',
            'owner_id' => $finance->id,
        ]);
        $supplier = Supplier::create([
            'org_id' => $finance->org_id,
            'supplier_code' => '000001',
            'name' => 'Tax Supplier',
            'tax_id' => '0105559000202',
            'status' => 'active',
        ]);

        Invoice::create([
            'org_id' => $finance->org_id,
            'branch_id' => $branch->id,
            'invoice_no' => 'INV-TAX-001',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'tax_mode' => 'exclusive',
            'issue_date' => '2026-08-10',
            'subtotal' => 1000,
            'tax_amount' => 70,
            'total' => 1070,
            'balance_due' => 1070,
            'currency' => 'THB',
        ]);
        Invoice::create([
            'org_id' => $finance->org_id,
            'branch_id' => $branch->id,
            'invoice_no' => 'INV-DRAFT',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'tax_mode' => 'exclusive',
            'issue_date' => '2026-08-11',
            'subtotal' => 1000,
            'tax_amount' => 70,
            'total' => 1070,
            'balance_due' => 1070,
        ]);
        PurchaseOrder::create([
            'org_id' => $finance->org_id,
            'supplier_id' => $supplier->id,
            'po_no' => 'PO-TAX-001',
            'status' => 'approved',
            'order_date' => '2026-08-12',
            'tax_mode' => 'inclusive',
            'subtotal' => 1070,
            'tax_amount' => 70,
            'total' => 1070,
            'currency' => 'THB',
        ]);

        $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/TaxReports')
                ->has('salesRows', 1)
                ->where('salesRows.0.document_no', 'INV-TAX-001')
                ->where('salesRows.0.taxable_base', '1000.00')
                ->where('salesRows.0.tax_amount', '70.00')
                ->has('purchaseRows', 1)
                ->where('purchaseRows.0.document_no', 'PO-TAX-001')
            );
    }

    public function test_tax_report_export_is_csv_and_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['tax_reports.view']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Visible Customer',
            'tax_id' => '0105559000101',
            'owner_id' => $finance->id,
        ]);
        $otherCustomer = Customer::create([
            'org_id' => $other->org_id,
            'customer_code' => '000001',
            'company_name' => 'Hidden Customer',
            'owner_id' => $other->id,
        ]);
        $secondCustomer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000002',
            'company_name' => 'Other Visible Customer',
            'owner_id' => $finance->id,
        ]);
        Invoice::create([
            'org_id' => $finance->org_id,
            'invoice_no' => 'INV-EXPORT',
            'customer_id' => $customer->id,
            'status' => 'paid',
            'tax_mode' => 'exclusive',
            'issue_date' => '2026-08-10',
            'tax_amount' => 70,
            'total' => 1070,
            'balance_due' => 0,
        ]);
        Invoice::create([
            'org_id' => $finance->org_id,
            'invoice_no' => 'INV-FILTERED',
            'customer_id' => $secondCustomer->id,
            'status' => 'sent',
            'tax_mode' => 'exclusive',
            'issue_date' => '2026-08-10',
            'tax_amount' => 70,
            'total' => 1070,
            'balance_due' => 1070,
        ]);
        Invoice::create([
            'org_id' => $other->org_id,
            'invoice_no' => 'INV-HIDDEN',
            'customer_id' => $otherCustomer->id,
            'status' => 'paid',
            'tax_mode' => 'exclusive',
            'issue_date' => '2026-08-10',
            'tax_amount' => 70,
            'total' => 1070,
            'balance_due' => 0,
        ]);

        $response = $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.export', ['type' => 'sales', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=sales-tax-report.csv');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('INV-EXPORT', $csv);
        $this->assertStringNotContainsString('INV-HIDDEN', $csv);

        $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'customer_id' => $customer->id, 'status' => 'paid']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('salesRows', 1)
                ->where('salesRows.0.document_no', 'INV-EXPORT')
            );

        $excel = $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.excel', ['type' => 'sales', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=sales-report.xls')
            ->streamedContent();
        $this->assertStringContainsString('<table', $excel);
        $this->assertStringContainsString('INV-EXPORT', $excel);
        $this->assertStringNotContainsString('INV-HIDDEN', $excel);
    }

    public function test_tax_report_page_shows_ar_and_ap_aging_buckets(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['tax_reports.view']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Aging Customer',
            'owner_id' => $finance->id,
        ]);
        $supplier = Supplier::create([
            'org_id' => $finance->org_id,
            'supplier_code' => '000001',
            'name' => 'Aging Supplier',
            'status' => 'active',
        ]);

        Invoice::create([
            'org_id' => $finance->org_id,
            'invoice_no' => 'INV-AGING-001',
            'customer_id' => $customer->id,
            'status' => 'overdue',
            'tax_mode' => 'no_tax',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
            'total' => 500,
            'paid_amount' => 100,
            'balance_due' => 400,
        ]);
        Invoice::create([
            'org_id' => $finance->org_id,
            'invoice_no' => 'INV-PAID',
            'customer_id' => $customer->id,
            'status' => 'paid',
            'tax_mode' => 'no_tax',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
            'total' => 500,
            'paid_amount' => 500,
            'balance_due' => 0,
        ]);
        PurchaseOrder::create([
            'org_id' => $finance->org_id,
            'supplier_id' => $supplier->id,
            'po_no' => 'PO-AGING-001',
            'status' => 'approved',
            'order_date' => '2026-06-01',
            'expected_date' => '2026-06-15',
            'tax_mode' => 'no_tax',
            'total' => 900,
        ]);

        $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/TaxReports')
                ->has('arAgingRows', 1)
                ->where('arAgingRows.0.document_no', 'INV-AGING-001')
                ->where('arAgingRows.0.bucket', '31-60')
                ->where('arAgingRows.0.amount', '400.00')
                ->has('apAgingRows', 1)
                ->where('apAgingRows.0.document_no', 'PO-AGING-001')
                ->where('apAgingRows.0.bucket', '61-90')
                ->where('apAgingRows.0.amount', '900.00')
            );
    }

    public function test_withholding_tax_report_uses_approved_expenses(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['tax_reports.view']);
        $supplier = Supplier::create([
            'org_id' => $finance->org_id,
            'supplier_code' => '000001',
            'name' => 'WHT Supplier',
            'tax_id' => '0105559000303',
            'status' => 'active',
        ]);
        Expense::create([
            'org_id' => $finance->org_id,
            'expense_no' => 'EXP-WHT-001',
            'category' => 'contractor',
            'title' => 'Consulting',
            'amount' => 10000,
            'withholding_tax_rate' => 3,
            'withholding_tax_amount' => 300,
            'withholding_tax_form' => 'pnd53',
            'expense_date' => '2026-08-15',
            'supplier_id' => $supplier->id,
            'status' => 'approved',
        ]);
        Expense::create([
            'org_id' => $finance->org_id,
            'expense_no' => 'EXP-DRAFT-WHT',
            'category' => 'contractor',
            'title' => 'Draft Consulting',
            'amount' => 10000,
            'withholding_tax_rate' => 3,
            'withholding_tax_amount' => 300,
            'withholding_tax_form' => 'pnd53',
            'expense_date' => '2026-08-15',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
        ]);

        $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.index', ['date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/TaxReports')
                ->has('withholdingRows', 1)
                ->where('withholdingRows.0.document_no', 'EXP-WHT-001')
                ->where('withholdingRows.0.form', 'pnd53')
                ->where('withholdingRows.0.wht_amount', '300.00')
            );

        $response = $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.export', ['type' => 'withholding', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('EXP-WHT-001', $csv);
        $this->assertStringNotContainsString('EXP-DRAFT-WHT', $csv);

        $excel = $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.excel', ['type' => 'withholding', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=withholding-report.xls')
            ->streamedContent();
        $this->assertStringContainsString('EXP-WHT-001', $excel);
        $this->assertStringNotContainsString('EXP-DRAFT-WHT', $excel);
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
