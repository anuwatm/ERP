<?php

namespace Tests\Feature;

use App\Jobs\SubmitETaxDocument;
use App\Models\Customer;
use App\Models\ETaxDocument;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase12ETaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_finance_user_generates_and_downloads_org_scoped_etax_xml(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['e_tax.view', 'e_tax.manage']);
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'CUS-ETAX-001', 'company_name' => 'Customer Co.', 'tax_id' => '0105557777777', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $user->org_id, 'invoice_no' => 'INV-ETAX-001', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'exclusive', 'issue_date' => '2026-08-28', 'subtotal' => 100, 'tax_amount' => 7, 'total' => 107, 'paid_amount' => 0, 'balance_due' => 107, 'currency' => 'THB']);
        InvoiceItem::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'description' => 'Implementation service', 'quantity' => 1, 'unit_price' => 100, 'discount_amount' => 0, 'tax_rate' => 7, 'line_total' => 100, 'sort_order' => 0]);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('e-tax.documents.generate'), ['source_type' => 'invoice', 'source_id' => $invoice->id, 'document_type' => 'tax_invoice'])->assertSessionHas('success');
        $document = ETaxDocument::firstOrFail();
        $this->assertSame('generated', $document->status);
        Storage::disk('local')->assertExists($document->xml_storage_path);
        $this->assertStringContainsString('INV-ETAX-001', Storage::disk('local')->get($document->xml_storage_path));
        $this->actingAsOrgUser($user)->get(route('e-tax.documents.download', $document))->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8');

        $outsider = User::factory()->create();
        $this->grant($outsider, ['e_tax.view']);
        $this->actingAsOrgUser($outsider)->get(route('e-tax.documents.download', $document))->assertForbidden();
    }

    public function test_submitted_or_accepted_document_cannot_be_regenerated(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['e_tax.manage']);
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'CUS-ETAX-LOCK', 'company_name' => 'Customer Co.', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $user->org_id, 'invoice_no' => 'INV-ETAX-LOCK', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'exclusive', 'issue_date' => '2026-08-28', 'subtotal' => 100, 'tax_amount' => 7, 'total' => 107, 'paid_amount' => 0, 'balance_due' => 107, 'currency' => 'THB']);
        InvoiceItem::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'description' => 'Locked service', 'quantity' => 1, 'unit_price' => 100, 'discount_amount' => 0, 'tax_rate' => 7, 'line_total' => 100, 'sort_order' => 0]);
        $document = ETaxDocument::create(['org_id' => $user->org_id, 'source_type' => Invoice::class, 'source_id' => $invoice->id, 'document_type' => 'tax_invoice', 'document_no' => $invoice->invoice_no, 'status' => 'accepted', 'xml_storage_path' => 'e-tax/locked.xml', 'xml_sha256' => hash('sha256', 'locked'), 'payload_json' => []]);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('e-tax.documents.generate'), [
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'document_type' => 'tax_invoice',
        ])->assertUnprocessable();

        $this->assertSame('accepted', $document->fresh()->status);
    }

    public function test_rd_prep_draft_export_is_form_scoped_and_queued_submission_is_audited(): void
    {
        $user = User::factory()->create();
        $user->organization->update(['tax_id' => '0105559999999']);
        $this->grant($user, ['e_tax.view', 'e_tax.submit']);
        $supplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => 'SUP-001', 'name' => 'Vendor Co.', 'tax_id' => '0105558888888', 'status' => 'active']);
        Expense::create(['org_id' => $user->org_id, 'expense_no' => 'EXP-001', 'category' => 'service', 'title' => 'Withholding service', 'amount' => 1000, 'tax_mode' => 'no_tax', 'withholding_tax_rate' => 3, 'withholding_tax_amount' => 30, 'withholding_tax_form' => 'pnd53', 'expense_date' => '2026-08-28', 'supplier_id' => $supplier->id, 'status' => 'approved']);
        $this->actingAsOrgUser($user)->get(route('e-tax.rd-prep', ['form' => 'pnd53']))->assertOk()->assertSee('ERP_RD_PREP_DRAFT_V1')->assertSee('EXP-001');

        Bus::fake();
        $document = ETaxDocument::create(['org_id' => $user->org_id, 'source_type' => Invoice::class, 'source_id' => (string) Str::uuid(), 'document_type' => 'tax_invoice', 'document_no' => 'INV-Q-001', 'status' => 'generated', 'xml_storage_path' => 'e-tax/test.xml', 'xml_sha256' => hash('sha256', 'test'), 'payload_json' => []]);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('e-tax.documents.submit', $document))->assertSessionHas('success');
        Bus::assertDispatched(SubmitETaxDocument::class, fn (SubmitETaxDocument $job) => $job->documentId === $document->id);
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'finance', 'name' => 'Finance', 'is_system' => true]);
        foreach ($codes as $code) {
            $parts = explode('.', $code);
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => $parts[0], 'action' => end($parts)]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);
    }
}
