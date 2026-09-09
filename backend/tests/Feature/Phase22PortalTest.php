<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PortalSession;
use App\Models\PortalUser;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBillSubmission;
use App\Services\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase22PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_is_single_use_and_creates_a_separate_portal_session(): void
    {
        $owner = User::factory()->create();
        $customer = $this->customer($owner->org_id, $owner->id, 'CUST-MAGIC');
        $portal = PortalUser::create(['org_id' => $owner->org_id, 'party_type' => 'customer', 'party_id' => $customer->id, 'email' => 'magic@portal.test', 'is_active' => true]);
        $service = app(PortalAccessService::class);
        $token = $service->issueMagicLink($portal);
        $request = Request::create('/portal/access', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'Portal test']);

        $this->assertNotNull($service->consumeMagicLink($token, $request));
        $this->assertNull($service->consumeMagicLink($token, $request));
        $this->assertDatabaseCount('portal_sessions', 1);
    }

    public function test_customer_portal_is_scoped_and_acceptance_is_audited(): void
    {
        $owner = User::factory()->create();
        $customer = $this->customer($owner->org_id, $owner->id, 'CUST-P1');
        $other = $this->customer($owner->org_id, $owner->id, 'CUST-P2');
        $portal = $this->portal($customer->org_id, 'customer', $customer->id);
        $quote = Quotation::create(['org_id' => $customer->org_id, 'quotation_no' => 'Q-PORTAL-1', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => today(), 'valid_until' => today()->addWeek(), 'subtotal' => 100, 'total' => 100, 'currency' => 'THB']);
        Quotation::create(['org_id' => $customer->org_id, 'quotation_no' => 'Q-OTHER-1', 'customer_id' => $other->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => today(), 'subtotal' => 200, 'total' => 200, 'currency' => 'THB']);

        $this->withCookie('erp_portal_session', $portal['token'])->get(route('portal.dashboard'))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Portal/Dashboard')->has('quotations', 1));
        $this->withCookie('erp_portal_session', $portal['token'])->post(route('portal.quotations.accept', $quote), ['accepted_by_name' => 'Customer Signer'])->assertRedirect();

        $this->assertSame('converted', $quote->fresh()->status);
        $this->assertDatabaseHas('invoices', ['quotation_id' => $quote->id, 'status' => 'draft']);
        $this->assertDatabaseHas('quotation_acceptances', ['quotation_id' => $quote->id, 'portal_user_id' => $portal['user']->id, 'accepted_by_name' => 'Customer Signer']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'portal_quotation.accepted', 'entity_id' => $quote->id]);
    }

    public function test_supplier_upload_is_quarantined_until_scan_and_portal_document_scope_is_enforced(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $supplier = Supplier::create(['org_id' => $owner->org_id, 'supplier_code' => 'SUP-P', 'name' => 'Portal Supplier', 'status' => 'active']);
        $portal = $this->portal($supplier->org_id, 'supplier', $supplier->id);
        $this->withCookie('erp_portal_session', $portal['token'])->post(route('portal.vendor-bills.store'), ['vendor_invoice_no' => 'V-100', 'invoice_date' => today()->toDateString(), 'amount' => 100, 'currency' => 'THB', 'file' => UploadedFile::fake()->create('bill.pdf', 12, 'application/pdf')])->assertRedirect();
        $submission = VendorBillSubmission::firstOrFail();
        $this->assertSame('pending_scan', $submission->status);
        $this->assertSame('pending_scan', $submission->document->currentVersion->scan_status);
        $this->withCookie('erp_portal_session', $portal['token'])->get(route('portal.documents.download', $submission->document->currentVersion))->assertNotFound();
    }

    private function portal(string $orgId, string $type, string $partyId): array
    {
        $user = PortalUser::create(['org_id' => $orgId, 'party_type' => $type, 'party_id' => $partyId, 'email' => $type.'@portal.test', 'name' => 'Portal User', 'is_active' => true]);
        $token = str()->random(80);
        PortalSession::create(['portal_user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);

        return ['user' => $user, 'token' => $token];
    }

    private function customer(string $orgId, string $ownerId, string $code): Customer
    {
        return Customer::create(['org_id' => $orgId, 'owner_id' => $ownerId, 'customer_code' => $code, 'company_name' => $code, 'customer_type' => 'company', 'status' => 'active']);
    }
}
