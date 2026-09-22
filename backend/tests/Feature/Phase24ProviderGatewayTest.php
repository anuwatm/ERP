<?php

namespace Tests\Feature;

use App\Jobs\SubmitETaxDocument;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase24ProviderGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_provider_mode_configuration_encrypts_secrets_and_sanitizes_audit_logs(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['e_tax.view', 'e_tax.manage']);

        $response = $this->actingAsOrgUser($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('e-tax.config.update'), [
                'mode' => 'provider',
                'provider_code' => 'rd_api',
                'api_url' => 'https://etax.rd.go.th/api/v1',
                'client_id' => 'CLIENT-001',
                'client_secret' => 'SUPER_CONFIDENTIAL_SECRET_12345',
                'webhook_secret' => 'HMAC_KEY_987654321',
                'certificate_reference' => 'arn:aws:kms:ap-southeast-1:etax-cert',
                'certificate_expires_at' => '2027-12-31',
                'signature_mode' => 'xml_dsig',
            ]);

        $response->assertSessionHas('success');

        $config = ETaxConfig::where('org_id', $user->org_id)->firstOrFail();
        $this->assertSame('provider', $config->mode);
        $this->assertSame('rd_api', $config->provider_code);
        $this->assertSame('xml_dsig', $config->signature_mode);

        // Decrypted model accessor returns values
        $this->assertSame('SUPER_CONFIDENTIAL_SECRET_12345', $config->provider_settings['client_secret']);
        $this->assertSame('HMAC_KEY_987654321', $config->provider_settings['webhook_secret']);

        // Raw database column is encrypted and does not contain plaintext secrets
        $rawRow = DB::table('e_tax_configs')->where('id', $config->id)->first();
        $this->assertStringNotContainsString('SUPER_CONFIDENTIAL_SECRET_12345', (string) $rawRow->provider_settings);
        $this->assertStringNotContainsString('HMAC_KEY_987654321', (string) $rawRow->provider_settings);

        // Audit Log does NOT contain secrets in after_json
        $audit = AuditLog::where('org_id', $user->org_id)->where('action', 'e_tax.config_update')->firstOrFail();
        $auditString = json_encode($audit->after_json);
        $this->assertStringNotContainsString('SUPER_CONFIDENTIAL_SECRET_12345', $auditString);
        $this->assertStringNotContainsString('HMAC_KEY_987654321', $auditString);
        $this->assertSame('https://etax.rd.go.th/api/v1', $audit->after_json['api_url']);
    }

    public function test_xml_dsig_enveloped_signing_pipeline(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['e_tax.view', 'e_tax.manage']);
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'CUS-SIG-01', 'company_name' => 'Acme Corp', 'tax_id' => '0105551111111', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $user->org_id, 'invoice_no' => 'INV-SIG-001', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'exclusive', 'issue_date' => '2026-09-20', 'subtotal' => 1000, 'tax_amount' => 70, 'total' => 1070, 'paid_amount' => 0, 'balance_due' => 1070, 'currency' => 'THB']);
        InvoiceItem::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'discount_amount' => 0, 'tax_rate' => 7, 'line_total' => 1000, 'sort_order' => 0]);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('e-tax.documents.generate'), [
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'document_type' => 'tax_invoice',
            ])->assertSessionHas('success');

        $document = ETaxDocument::where('org_id', $user->org_id)->firstOrFail();
        $this->assertSame('generated', $document->status);
        $this->assertNull($document->signed_at);

        // Sign XML
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('e-tax.documents.sign', $document))
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('signed', $document->status);
        $this->assertNotNull($document->signed_at);

        $signedXml = Storage::disk('local')->get($document->xml_storage_path);
        $this->assertStringContainsString('ds:Signature', $signedXml);
        $this->assertStringContainsString('ds:SignedInfo', $signedXml);
        $this->assertStringContainsString('ds:CanonicalizationMethod', $signedXml);
        $this->assertStringContainsString('ds:SignatureMethod', $signedXml);
        $this->assertStringContainsString('ds:DigestValue', $signedXml);
        $this->assertStringContainsString('ds:SignatureValue', $signedXml);
        $this->assertStringContainsString('ds:KeyInfo', $signedXml);
        $this->assertStringContainsString('ds:X509Data', $signedXml);

        // Verify SHA256 matches the stored signed content
        $this->assertSame(hash('sha256', $signedXml), $document->xml_sha256);
    }

    public function test_provider_gateway_submission_and_response_lifecycle(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['e_tax.view', 'e_tax.submit', 'e_tax.manage']);

        ETaxConfig::create([
            'org_id' => $user->org_id,
            'mode' => 'provider',
            'provider_code' => 'rd_api',
            'signature_mode' => 'xml_dsig',
            'provider_settings' => [
                'api_url' => 'https://etax-mock.rd.go.th/api/v1',
                'client_id' => 'CLIENT-RD-01',
                'client_secret' => 'SECRET-RD-01',
            ],
        ]);

        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'CUS-SUB-01', 'company_name' => 'Submit Corp', 'tax_id' => '0105552222222', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $user->org_id, 'invoice_no' => 'INV-SUB-001', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'exclusive', 'issue_date' => '2026-09-20', 'subtotal' => 200, 'tax_amount' => 14, 'total' => 214, 'paid_amount' => 0, 'balance_due' => 214, 'currency' => 'THB']);
        InvoiceItem::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'description' => 'Delivery', 'quantity' => 1, 'unit_price' => 200, 'discount_amount' => 0, 'tax_rate' => 7, 'line_total' => 200, 'sort_order' => 0]);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('e-tax.documents.generate'), [
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'document_type' => 'tax_invoice',
            ])->assertSessionHas('success');

        $document = ETaxDocument::where('org_id', $user->org_id)->firstOrFail();

        Http::fake([
            'https://etax-mock.rd.go.th/api/v1/documents/submit' => Http::response([
                'reference_id' => 'RD-REF-2026-0001',
                'status' => 'submitted',
                'message' => 'Document received by RD gateway',
            ], 200),
        ]);

        // Submit via web endpoint (sync queue executes the job upon commit)
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('e-tax.documents.submit', $document))
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame('submitted', $document->status);
        $this->assertNotNull($document->submitted_at);
        $this->assertNotNull($document->signed_at); // Auto-signed by gateway service!

        $attempt = $document->attempts()->firstOrFail();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame('RD-REF-2026-0001', $attempt->external_reference);
        $this->assertSame('rd_api', $attempt->provider_code);
    }

    public function test_provider_gateway_retry_ceiling_and_error_handling(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['e_tax.view', 'e_tax.submit']);

        ETaxConfig::create([
            'org_id' => $user->org_id,
            'mode' => 'provider',
            'provider_code' => 'rd_api',
            'signature_mode' => 'none',
            'provider_settings' => [
                'api_url' => 'https://etax-mock.rd.go.th/api/v1',
            ],
        ]);

        $document = ETaxDocument::create([
            'org_id' => $user->org_id,
            'source_type' => Invoice::class,
            'source_id' => (string) Str::uuid(),
            'document_type' => 'tax_invoice',
            'document_no' => 'INV-FAIL-001',
            'status' => 'queued',
            'xml_storage_path' => 'e-tax/'.$user->org_id.'/test.xml',
            'xml_sha256' => hash('sha256', '<xml>valid</xml>'),
            'payload_json' => ['document_no' => 'INV-FAIL-001'],
        ]);
        Storage::disk('local')->put($document->xml_storage_path, '<xml>valid</xml>');

        Http::fake([
            'https://etax-mock.rd.go.th/api/v1/documents/submit' => Http::response(['message' => 'Provider 503 Service Unavailable'], 503),
        ]);

        // Attempt 1
        SubmitETaxDocument::dispatchSync($document->id);
        $document->refresh();
        $this->assertSame('error', $document->status);
        $this->assertStringContainsString('503 Service Unavailable', $document->last_error);
        $this->assertSame(1, $document->attempts()->count());

        // Attempt 2
        $document->update(['status' => 'queued']);
        SubmitETaxDocument::dispatchSync($document->id);
        $document->refresh();
        $this->assertSame('error', $document->status);
        $this->assertSame(2, $document->attempts()->count());

        // Attempt 3
        $document->update(['status' => 'queued']);
        SubmitETaxDocument::dispatchSync($document->id);
        $document->refresh();
        $this->assertSame('error', $document->status);
        $this->assertSame(3, $document->attempts()->count());

        // 4th attempt should be blocked by retry limit and marked error
        $document->update(['status' => 'queued']);
        SubmitETaxDocument::dispatchSync($document->id);
        $document->refresh();
        $this->assertSame(3, $document->attempts()->count());
        $this->assertSame('error', $document->status);
        $this->assertStringContainsString('Max submission retry attempts', $document->last_error);
    }

    public function test_webhook_callback_hmac_verification_and_state_transition(): void
    {
        $user = User::factory()->create();
        $config = ETaxConfig::create([
            'org_id' => $user->org_id,
            'mode' => 'provider',
            'provider_code' => 'rd_api',
            'signature_mode' => 'xml_dsig',
            'provider_settings' => [
                'webhook_secret' => 'TOP_SECRET_WEBHOOK_KEY_123',
            ],
        ]);

        $document = ETaxDocument::create([
            'org_id' => $user->org_id,
            'source_type' => Invoice::class,
            'source_id' => (string) Str::uuid(),
            'document_type' => 'tax_invoice',
            'document_no' => 'INV-HOOK-001',
            'status' => 'submitted',
            'submitted_at' => now()->subMinutes(10),
            'xml_storage_path' => 'e-tax/'.$user->org_id.'/hook.xml',
            'xml_sha256' => hash('sha256', '<xml>hook</xml>'),
            'payload_json' => ['document_no' => 'INV-HOOK-001'],
        ]);
        Storage::disk('local')->put($document->xml_storage_path, '<xml>hook</xml>');

        $attempt = $document->attempts()->create([
            'org_id' => $user->org_id,
            'attempt_no' => 1,
            'status' => 'submitted',
            'provider_code' => 'rd_api',
            'external_reference' => 'EXT-REF-999',
            'started_at' => now()->subMinutes(10),
        ]);

        $payload = [
            'external_reference' => 'EXT-REF-999',
            'status' => 'approved',
            'message' => 'ETDA RD signature verification passed',
        ];

        // 1. Missing signature -> 401
        $this->postJson(route('etax.webhook', $config->id), $payload)
            ->assertStatus(401);

        // 2. Invalid signature -> 401
        $this->postJson(route('etax.webhook', $config->id), $payload, ['X-ETax-Signature' => 'invalid_signature_hash'])
            ->assertStatus(401);

        // 3. Valid signature -> 200 OK
        $validSignature = hash_hmac('sha256', json_encode($payload), 'TOP_SECRET_WEBHOOK_KEY_123');
        $response = $this->postJson(route('etax.webhook', $config->id), $payload, [
            'X-ETax-Signature' => $validSignature,
        ]);

        $response->assertOk();

        $document->refresh();
        $this->assertSame('accepted', $document->status);
        $this->assertNotNull($document->accepted_at);

        $attempt->refresh();
        $this->assertSame('accepted', $attempt->status);
        $this->assertStringContainsString('WEBHOOK', (string) $attempt->response_message);
    }

    public function test_webhook_callback_tenant_isolation(): void
    {
        $orgA = User::factory()->create()->org_id;
        $orgB = User::factory()->create()->org_id;

        $configA = ETaxConfig::create([
            'org_id' => $orgA,
            'mode' => 'provider',
            'provider_settings' => ['webhook_secret' => 'SECRET_A'],
        ]);

        // Document belongs to Org B
        $docB = ETaxDocument::create([
            'org_id' => $orgB,
            'source_type' => Invoice::class,
            'source_id' => (string) Str::uuid(),
            'document_type' => 'tax_invoice',
            'document_no' => 'INV-TENANT-B',
            'status' => 'submitted',
            'xml_storage_path' => 'e-tax/test.xml',
            'xml_sha256' => hash('sha256', 'test'),
            'payload_json' => [],
        ]);

        $payload = ['document_no' => 'INV-TENANT-B', 'status' => 'approved'];
        $sig = hash_hmac('sha256', json_encode($payload), 'SECRET_A');

        // Webhook targeting Config A cannot update docB
        $response = $this->postJson(route('etax.webhook', $configA->id), $payload, [
            'X-ETax-Signature' => $sig,
        ]);

        $response->assertStatus(422);
        $this->assertSame('submitted', $docB->fresh()->status);
    }

    public function test_status_inquiry_artisan_command_and_web_trigger(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['e_tax.view', 'e_tax.submit']);

        ETaxConfig::create([
            'org_id' => $user->org_id,
            'mode' => 'provider',
            'provider_code' => 'rd_api',
            'signature_mode' => 'none',
            'provider_settings' => [
                'api_url' => 'https://etax-mock.rd.go.th/api/v1',
            ],
        ]);

        $document = ETaxDocument::create([
            'org_id' => $user->org_id,
            'source_type' => Invoice::class,
            'source_id' => (string) Str::uuid(),
            'document_type' => 'tax_invoice',
            'document_no' => 'INV-INQ-001',
            'status' => 'submitted',
            'submitted_at' => now()->subMinutes(30),
            'xml_storage_path' => 'e-tax/'.$user->org_id.'/inq.xml',
            'xml_sha256' => hash('sha256', '<xml>inq</xml>'),
            'payload_json' => ['document_no' => 'INV-INQ-001'],
        ]);
        Storage::disk('local')->put($document->xml_storage_path, '<xml>inq</xml>');

        $document->attempts()->create([
            'org_id' => $user->org_id,
            'attempt_no' => 1,
            'status' => 'submitted',
            'provider_code' => 'rd_api',
            'external_reference' => 'RD-REF-INQ-001',
            'started_at' => now()->subMinutes(30),
        ]);

        Http::fake([
            'https://etax-mock.rd.go.th/api/v1/documents/status/RD-REF-INQ-001' => Http::response([
                'status' => 'approved',
                'message' => 'Verified and stamped by Revenue Department',
            ], 200),
        ]);

        // 1. Test Artisan Command
        $this->artisan('etax:inquire-status --limit=10')
            ->expectsOutput('Inquired 1 e-Tax document(s).')
            ->assertExitCode(0);

        $this->assertSame('accepted', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->accepted_at);

        // 2. Test Web Endpoint
        $this->actingAsOrgUser($user)
            ->post(route('e-tax.documents.inquire', $document))
            ->assertSessionHas('success');
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'finance_'.Str::random(5), 'name' => 'Finance', 'is_system' => true]);
        foreach ($codes as $code) {
            $parts = explode('.', $code);
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => $parts[0], 'action' => end($parts)]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);
    }
}
