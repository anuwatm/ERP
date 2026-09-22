<?php

namespace Tests\Feature;

use App\Jobs\SubmitETaxDocument;
use App\Models\Customer;
use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ETax\ETaxDocumentService;
use App\Services\ETax\ImmutableETaxDocumentException;
use App\Services\ETax\MockETaxSubmissionAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase24ETaxFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Bus::fake();
        Http::preventStrayRequests();
    }

    private function scenario(): array
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'finance', 'name' => 'Finance']);
        $role->permissions()->attach(Permission::whereIn('code', ['e_tax.view', 'e_tax.manage', 'e_tax.submit'])->pluck('id'));
        $user->roles()->attach($role);
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'ETX001', 'company_name' => 'Buyer', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $user->org_id, 'customer_id' => $customer->id, 'invoice_no' => 'ETX001', 'status' => 'sent', 'tax_mode' => 'exclusive', 'issue_date' => today(), 'currency' => 'THB', 'subtotal' => 100, 'tax_amount' => 7, 'total' => 107, 'balance_due' => 107]);
        InvoiceItem::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'discount_amount' => 0, 'tax_rate' => 7, 'line_total' => 100, 'sort_order' => 0]);
        $config = ETaxConfig::create(['org_id' => $user->org_id, 'mode' => 'mock', 'signature_mode' => 'none']);
        $document = app(ETaxDocumentService::class)->generate($invoice, 'tax_invoice', $user->id);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);

        return [$user, $invoice, $document, $config];
    }

    private function runJob(ETaxDocument $document): void
    {
        app()->call([new SubmitETaxDocument($document->id), 'handle']);
    }

    public function test_mock_is_opt_in_and_live_config_is_rejected(): void
    {
        [$user, $invoice, $document, $config] = $this->scenario();
        $config->update(['mode' => 'disabled']);
        $this->post(route('e-tax.documents.submit', $document))->assertUnprocessable();
        $this->patch(route('e-tax.config.update'), ['mode' => 'invalid_mode', 'provider_code' => 'arbitrary', 'signature_mode' => 'external'])->assertSessionHasErrors('mode');
        $this->assertSame('disabled', $config->fresh()->mode);
        $this->patch(route('e-tax.config.update'), ['mode' => 'mock', 'signature_mode' => 'external'])->assertSessionHasNoErrors();
        $this->assertSame('mock', $config->fresh()->mode);
        $this->assertSame('none', $config->fresh()->signature_mode);
        Bus::assertNothingDispatched();
    }

    public function test_duplicate_queue_and_worker_only_simulate_once_without_accounting_or_network(): void
    {
        [$user, $invoice, $document] = $this->scenario();
        $this->post(route('e-tax.documents.submit', $document))->assertRedirect();
        $this->post(route('e-tax.documents.submit', $document))->assertRedirect();
        Bus::assertDispatchedTimes(SubmitETaxDocument::class, 1);
        $this->runJob($document);
        $this->runJob($document);
        $this->assertSame('simulated', $document->fresh()->status);
        $this->assertNull($document->fresh()->submitted_at);
        $this->assertNull($document->fresh()->accepted_at);
        $this->assertDatabaseCount('e_tax_submission_attempts', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertSame('sent', $invoice->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_tampered_xml_fails_and_retry_preserves_snapshot(): void
    {
        [$user, $invoice, $document] = $this->scenario();
        $xml = app(ETaxDocumentService::class)->xml($document);
        $this->post(route('e-tax.documents.submit', $document))->assertRedirect();
        Storage::disk('local')->put($document->xml_storage_path, $xml.'tampered');
        $this->runJob($document);
        $this->assertSame('error', $document->fresh()->status);
        Storage::disk('local')->put($document->xml_storage_path, $xml);
        $this->post(route('e-tax.documents.submit', $document))->assertRedirect();
        $this->runJob($document);
        $this->assertSame('simulated', $document->fresh()->status);
        $this->assertSame($document->xml_sha256, $document->fresh()->xml_sha256);
        $this->assertDatabaseCount('e_tax_submission_attempts', 2);
    }

    public function test_retry_is_bounded_and_driver_exception_is_redacted(): void
    {
        [$user, $invoice, $document] = $this->scenario();
        $this->app->instance(MockETaxSubmissionAdapter::class, new class extends MockETaxSubmissionAdapter
        {
            public function submit(ETaxConfig $config, ETaxDocument $document, string $xml): array
            {
                throw new \RuntimeException('secret-token-and-personal-data');
            }
        });
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('e-tax.documents.submit', $document))->assertRedirect();
            $this->runJob($document);
        }
        $this->post(route('e-tax.documents.submit', $document))->assertUnprocessable();
        $this->assertDatabaseCount('e_tax_submission_attempts', 3);
        $this->assertStringNotContainsString('secret-token', $document->fresh()->last_error);
        $this->get(route('e-tax.index'))->assertOk()->assertDontSee('secret-token');
    }

    public function test_queue_freezes_document_and_xml_path_uses_id(): void
    {
        [$user, $invoice, $document] = $this->scenario();
        $this->assertStringEndsWith('/'.$document->id.'.xml', $document->xml_storage_path);
        $this->post(route('e-tax.documents.submit', $document))->assertRedirect();
        $invoice->update(['total' => 500]);
        $this->expectException(ImmutableETaxDocumentException::class);
        app(ETaxDocumentService::class)->generate($invoice, 'tax_invoice', $user->id);
    }

    public function test_permissions_org_scope_and_private_payload(): void
    {
        [$user, $invoice, $document, $config] = $this->scenario();
        $this->assertArrayNotHasKey('payload_json', $document->toArray());
        $this->assertArrayNotHasKey('xml_storage_path', $document->toArray());
        $this->assertArrayNotHasKey('provider_settings', $config->toArray());
        $outsider = User::factory()->create();
        $this->actingAsOrgUser($outsider)->get(route('e-tax.index'))->assertForbidden();
        $outsider->roles()->attach($user->roles()->first());
        $outsider->unsetRelation('roles');
        $this->actingAsOrgUser($outsider)->withSession(['auth.password_confirmed_at' => time()])->post(route('e-tax.documents.submit', $document))->assertForbidden();
    }

    public function test_disabling_config_after_queue_prevents_simulation(): void
    {
        [$user, $invoice, $document, $config] = $this->scenario();
        $this->post(route('e-tax.documents.submit', $document))->assertRedirect();
        $config->update(['mode' => 'disabled']);
        $this->runJob($document);
        $this->assertSame('error', $document->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_pending_recovery_command_only_dispatches_mock_documents(): void
    {
        [$user, $invoice, $document, $config] = $this->scenario();
        $document->update(['status' => 'queued']);
        $this->artisan('etax:dispatch-pending', ['--limit' => 1])->assertSuccessful();
        Bus::assertDispatchedTimes(SubmitETaxDocument::class, 1);
        $config->update(['mode' => 'disabled']);
        $this->artisan('etax:dispatch-pending')->assertSuccessful();
        Bus::assertDispatchedTimes(SubmitETaxDocument::class, 1);
        $this->artisan('etax:dispatch-pending', ['--limit' => 0])->assertFailed();
    }
}
