<?php

namespace Tests\Feature;

use App\Models\AiConfiguration;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\OcrExtraction;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ocr\AiConnectionTesterService;
use App\Services\Ocr\MockOcrAdapter;
use App\Services\Ocr\OcrProcessingService;
use App\Services\Ocr\OcrProviderInterface;
use App\Services\Ocr\ThaiTaxIdValidator;
use App\Services\Ocr\UniversalOpenAiVisionAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase26AiOcrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        MockOcrAdapter::reset();
    }

    protected function tearDown(): void
    {
        MockOcrAdapter::reset();
        parent::tearDown();
    }

    private function setupOrgUser(array $permissions = ['ocr.view', 'ocr.upload', 'ocr.convert', 'ai_settings.manage']): array
    {
        $user = User::factory()->create();
        $org = Organization::findOrFail($user->org_id);
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => 'finance_lead',
            'name' => 'Finance Lead',
        ]);

        $permissionModels = Permission::whereIn('code', $permissions)->get();
        $role->permissions()->attach($permissionModels->pluck('id'));
        $user->roles()->attach($role);

        return [$user, $org];
    }

    public function test_thai_tax_id_validator_modulo_11(): void
    {
        // Valid Thai Tax IDs (0107544000108 - PTT PCL)
        $this->assertTrue(ThaiTaxIdValidator::isValid('0107544000108'));
        $this->assertTrue(ThaiTaxIdValidator::isValid('0-1075-44000-10-8'));

        // Invalid Thai Tax IDs
        $this->assertFalse(ThaiTaxIdValidator::isValid('0107544000109')); // Wrong check digit
        $this->assertFalse(ThaiTaxIdValidator::isValid('12345')); // Wrong length
        $this->assertFalse(ThaiTaxIdValidator::isValid('0000000000000')); // Repetitive dummy
        $this->assertFalse(ThaiTaxIdValidator::isValid(null));

        // Format test
        $this->assertSame('0-1075-44000-10-8', ThaiTaxIdValidator::format('0107544000108'));
    }

    public function test_ai_configuration_can_be_saved_and_encrypted(): void
    {
        [$user, $org] = $this->setupOrgUser();

        $response = $this->actingAs($user)->post(route('settings.ai-configuration.store'), [
            'provider_type' => 'ollama',
            'base_url' => 'http://localhost:11434/v1',
            'api_key' => 'super_secret_ai_token_xyz',
            'model_name' => 'qwen2.5-vl:7b',
            'temperature' => 0.1,
            'timeout_seconds' => 60,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $config = AiConfiguration::where('org_id', $org->id)->first();
        $this->assertNotNull($config);
        $this->assertSame('ollama', $config->provider_type);
        $this->assertSame('qwen2.5-vl:7b', $config->model_name);

        // Verify that in memory it decrypts cleanly
        $this->assertSame('super_secret_ai_token_xyz', $config->api_key);

        // Verify that raw database value is NOT stored as plaintext
        $rawRow = DB::table('ai_configurations')->where('id', $config->id)->first();
        $this->assertNotSame('super_secret_ai_token_xyz', $rawRow->api_key);
        $this->assertStringStartsWith('eyJ', $rawRow->api_key); // Laravel AES-256 payload prefix
    }

    public function test_ai_connection_tester_endpoint(): void
    {
        [$user, $org] = $this->setupOrgUser();

        Http::fake([
            'http://localhost:11434/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'pong',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson(route('settings.ai-configuration.test'), [
            'provider_type' => 'ollama',
            'base_url' => 'http://localhost:11434/v1',
            'model_name' => 'qwen2.5-vl:7b',
            'timeout_seconds' => 10,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertArrayHasKey('latency_ms', $response->json());
    }

    public function test_ocr_upload_and_extraction_pipeline(): void
    {
        [$user, $org] = $this->setupOrgUser();

        // Create a matching supplier in this org
        $supplier = Supplier::create([
            'org_id' => $org->id,
            'supplier_code' => 'SUP-001',
            'name' => 'บริษัท ปตท. น้ำมันและการค้าปลีก จำกัด (มหาชน)',
            'tax_id' => '0107544000108',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->create('fuel_receipt.jpg', 10, 'image/jpeg');

        $response = $this->actingAs($user)->post(route('ocr.upload'), [
            'document' => $file,
        ]);

        $response->assertRedirect();

        $extraction = OcrExtraction::where('org_id', $org->id)->latest()->first();
        $this->assertNotNull($extraction);
        $this->assertSame(OcrExtraction::STATUS_EXTRACTED, $extraction->status);
        $this->assertSame('tax_invoice', $extraction->document_type);
        $this->assertTrue($extraction->tax_id_valid);
        $this->assertFalse($extraction->math_mismatch);

        // Check field results
        $this->assertDatabaseHas('ocr_field_results', [
            'ocr_extraction_id' => $extraction->id,
            'field_key' => 'tax_id',
            'extracted_value' => '0107544000108',
        ]);

        $this->assertDatabaseHas('ocr_field_results', [
            'ocr_extraction_id' => $extraction->id,
            'field_key' => 'total_amount',
            'extracted_value' => '1070.00',
        ]);

        // Verify supplier auto-matched
        $parsed = $extraction->parsed_data_json;
        $this->assertSame($supplier->id, $parsed['matched_supplier_id']);
    }

    public function test_math_reconciliation_flags_mismatch(): void
    {
        [$user, $org] = $this->setupOrgUser();

        // Inject response with inconsistent math ($1000 + $70 != $1200)
        MockOcrAdapter::setCustomResponse([
            'document_type' => 'receipt',
            'vendor_name' => 'ร้านค้าทดสอบ',
            'tax_id' => '0107544000108',
            'invoice_no' => 'REC-001',
            'document_date' => '2026-09-21',
            'subtotal' => 1000.00,
            'vat_rate' => 7.00,
            'vat_amount' => 70.00,
            'total_amount' => 1200.00, // Mismatched total!
            'currency' => 'THB',
            'confidence_score' => 0.88,
            'field_confidences' => [],
            'line_items' => [],
            'raw_text' => '{}',
        ]);

        $file = UploadedFile::fake()->create('mismatched.png', 10, 'image/png');

        $response = $this->actingAs($user)->post(route('ocr.upload'), [
            'document' => $file,
        ]);
        $response->assertRedirect();

        $extraction = OcrExtraction::where('org_id', $org->id)->latest()->first();
        $this->assertNotNull($extraction);
        $this->assertTrue($extraction->math_mismatch);
    }

    public function test_human_in_the_loop_guard_prevents_auto_gl_posting(): void
    {
        [$user, $org] = $this->setupOrgUser();

        $file = UploadedFile::fake()->create('receipt.jpg', 10, 'image/jpeg');

        $response = $this->actingAs($user)->post(route('ocr.upload'), [
            'document' => $file,
        ]);
        $response->assertRedirect();

        // Guard Check: No Expenses or Journal Entries should exist at this stage
        $this->assertSame(0, Expense::where('org_id', $org->id)->count());
        $this->assertSame(0, JournalEntry::where('org_id', $org->id)->count());

        $extraction = OcrExtraction::where('org_id', $org->id)->first();
        $this->assertNotNull($extraction);
        $this->assertNull($extraction->target_id);
        $this->assertSame(OcrExtraction::STATUS_EXTRACTED, $extraction->status);
    }

    public function test_convert_extraction_to_draft_expense(): void
    {
        [$user, $org] = $this->setupOrgUser();

        $file = UploadedFile::fake()->create('fuel_bill.jpg', 10, 'image/jpeg');
        $this->actingAs($user)->post(route('ocr.upload'), [
            'document' => $file,
        ]);

        $extraction = OcrExtraction::where('org_id', $org->id)->first();
        $this->assertNotNull($extraction);

        // Convert to Expense via reviewer form submit
        $response = $this->actingAs($user)->post(route('ocr.convert-expense', $extraction->id), [
            'title' => 'ค่าน้ำมันรถยนต์ส่วนกลาง',
            'category' => 'travel',
            'amount' => 1070.00,
            'currency' => 'THB',
            'expense_date' => '2026-09-21',
            'tax_mode' => 'include',
            'tax_invoice_no' => 'INV-2026-9988',
            'tax_amount' => 70.00,
            'note' => 'Approved draft from OCR review',
        ]);

        $response->assertRedirect(route('expenses.index'));

        // Verify Expense created with status 'draft'
        $expense = Expense::where('org_id', $org->id)->first();
        $this->assertNotNull($expense);
        $this->assertSame('draft', $expense->status);
        $this->assertSame('travel', $expense->category);
        $this->assertEquals(1070.00, (float) $expense->amount);
        $this->assertSame($extraction->stored_file_id, $expense->receipt_file_id);

        // Verify Extraction status transitioned to 'converted'
        $extraction->refresh();
        $this->assertSame(OcrExtraction::STATUS_CONVERTED, $extraction->status);
        $this->assertSame('expense', $extraction->target_type);
        $this->assertSame($expense->id, $extraction->target_id);
        $this->assertSame($user->id, $extraction->reviewed_by);
    }

    public function test_multi_tenant_isolation(): void
    {
        [$userA, $orgA] = $this->setupOrgUser();
        [$userB, $orgB] = $this->setupOrgUser();

        $file = UploadedFile::fake()->create('org_a_doc.jpg', 10, 'image/jpeg');
        $this->actingAs($userA)->post(route('ocr.upload'), [
            'document' => $file,
        ]);

        $extractionA = OcrExtraction::where('org_id', $orgA->id)->first();
        $this->assertNotNull($extractionA);

        // User B cannot view Org A's extraction
        $resShow = $this->actingAs($userB)->get(route('ocr.show', $extractionA->id));
        $resShow->assertForbidden();

        // User B cannot convert Org A's extraction
        $resConvert = $this->actingAs($userB)->post(route('ocr.convert-expense', $extractionA->id), [
            'title' => 'Hacked title',
            'category' => 'office',
            'amount' => 500,
            'currency' => 'THB',
            'expense_date' => '2026-09-21',
            'tax_mode' => 'none',
        ]);
        $resConvert->assertForbidden();
    }

    public function test_openai_endpoint_resolution_and_gpt_adapter(): void
    {
        // 1. Test endpoint resolver
        $this->assertEquals(
            'https://api.openai.com/v1/chat/completions',
            AiConnectionTesterService::resolveChatEndpoint('https://api.openai.com')
        );
        $this->assertEquals(
            'https://api.openai.com/v1/chat/completions',
            AiConnectionTesterService::resolveChatEndpoint('https://api.openai.com/v1')
        );
        $this->assertEquals(
            'http://localhost:11434/v1/chat/completions',
            AiConnectionTesterService::resolveChatEndpoint('http://localhost:11434')
        );

        // 2. Test OpenAI adapter with simulated response
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'document_type' => 'tax_invoice',
                                'vendor_name' => 'OpenAI Partner Thailand',
                                'tax_id' => '0107544000108',
                                'invoice_no' => 'GPT-2026-001',
                                'document_date' => '2026-09-21',
                                'subtotal' => 1000.00,
                                'vat_rate' => 7.00,
                                'vat_amount' => 70.00,
                                'total_amount' => 1070.00,
                                'currency' => 'THB',
                                'confidence_score' => 0.98,
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        [$user, $org] = $this->setupOrgUser();
        $config = AiConfiguration::create([
            'org_id' => $org->id,
            'provider_type' => 'openai',
            'base_url' => 'https://api.openai.com',
            'api_key' => 'sk-test-openai-key',
            'model_name' => 'gpt-4o-mini',
            'temperature' => 0.1,
            'timeout_seconds' => 30,
        ]);

        $adapter = new UniversalOpenAiVisionAdapter;
        $dummyImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $result = $adapter->extract($dummyImage, 'image/png', $config);

        $this->assertEquals('tax_invoice', $result['document_type']);
        $this->assertEquals('OpenAI Partner Thailand', $result['vendor_name']);
        $this->assertEquals('0107544000108', $result['tax_id']);
        $this->assertEquals(1070.00, $result['total_amount']);
    }

    public function test_openai_detailed_error_handling(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota, please check your plan and billing details.',
                    'type' => 'insufficient_quota',
                ],
            ], 429),
        ]);

        [$user, $org] = $this->setupOrgUser();
        $config = AiConfiguration::create([
            'org_id' => $org->id,
            'provider_type' => 'openai',
            'base_url' => 'https://api.openai.com',
            'api_key' => 'sk-test-openai-key',
            'model_name' => 'gpt-4o-mini',
            'timeout_seconds' => 15,
        ]);

        $tester = new AiConnectionTesterService;
        $testRes = $tester->testConnection($config);

        $this->assertFalse($testRes['success']);
        $this->assertStringContainsString('You exceeded your current quota', $testRes['message']);
    }

    public function test_convert_extraction_to_draft_vendor_bill_with_open_po(): void
    {
        [$user, $org] = $this->setupOrgUser();

        $supplier = Supplier::create([
            'org_id' => $org->id,
            'supplier_code' => 'SUP-BILL-01',
            'name' => 'Supplier Office Depot Ltd',
            'tax_id' => '0107544000108',
            'status' => 'active',
        ]);

        $po = PurchaseOrder::create([
            'org_id' => $org->id,
            'supplier_id' => $supplier->id,
            'po_no' => 'PO-2026-0001',
            'status' => 'approved',
            'order_date' => '2026-09-20',
            'subtotal' => 2000.00,
            'total' => 2140.00,
            'currency' => 'THB',
        ]);

        $file = UploadedFile::fake()->create('vendor_bill.jpg', 10, 'image/jpeg');
        $this->actingAs($user)->post(route('ocr.upload'), [
            'document' => $file,
        ]);

        $extraction = OcrExtraction::where('org_id', $org->id)->first();
        $this->assertNotNull($extraction);

        $response = $this->actingAs($user)->post(route('ocr.convert-vendor-bill', $extraction->id), [
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'title' => 'Vendor Bill: INV-DEPOT-999',
            'category' => 'office',
            'amount' => 2140.00,
            'currency' => 'THB',
            'expense_date' => '2026-09-21',
            'tax_mode' => 'include',
            'tax_invoice_no' => 'INV-DEPOT-999',
            'tax_amount' => 140.00,
            'note' => 'Matched with PO-2026-0001',
        ]);

        $response->assertRedirect(route('expenses.index'));

        // Verify Expense created as Draft Vendor Bill
        $expense = Expense::where('org_id', $org->id)->where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($expense);
        $this->assertSame('draft', $expense->status);
        $this->assertSame($supplier->id, $expense->supplier_id);
        $this->assertSame($po->id, $expense->purchase_order_id);
        $this->assertSame('Vendor Bill: INV-DEPOT-999', $expense->title);
        $this->assertEquals(2140.00, (float) $expense->amount);
        $this->assertSame($extraction->stored_file_id, $expense->receipt_file_id);

        // Verify Extraction status transitioned to 'converted' with target_type 'vendor_bill'
        $extraction->refresh();
        $this->assertSame(OcrExtraction::STATUS_CONVERTED, $extraction->status);
        $this->assertSame('vendor_bill', $extraction->target_type);
        $this->assertSame($expense->id, $extraction->target_id);
        $this->assertSame($user->id, $extraction->reviewed_by);

        // Guard Check: No auto-posting to GL
        $this->assertSame(0, JournalEntry::where('org_id', $org->id)->count());

        // Verify AuditLog
        $this->assertDatabaseHas('audit_logs', [
            'org_id' => $org->id,
            'action' => 'ocr.convert_to_vendor_bill',
            'entity_type' => 'expense',
            'entity_id' => $expense->id,
        ]);
    }

    public function test_cross_org_supplier_and_project_rejected(): void
    {
        [$userA, $orgA] = $this->setupOrgUser();
        [$userB, $orgB] = $this->setupOrgUser();

        // Foreign entities in Org B
        $foreignSupplier = Supplier::create([
            'org_id' => $orgB->id,
            'supplier_code' => 'SUP-FOREIGN',
            'name' => 'Foreign Supplier',
            'status' => 'active',
        ]);

        $foreignProject = Project::create([
            'org_id' => $orgB->id,
            'project_code' => 'PRJ-FOREIGN',
            'name' => 'Foreign Project',
            'status' => 'planning',
        ]);

        // Extraction in Org A
        $file = UploadedFile::fake()->create('org_a_receipt.jpg', 10, 'image/jpeg');
        $this->actingAs($userA)->post(route('ocr.upload'), ['document' => $file]);
        $extraction = OcrExtraction::where('org_id', $orgA->id)->first();

        // 1. Attempt convert-expense with foreign supplier
        $resExpenseSupplier = $this->actingAs($userA)->post(route('ocr.convert-expense', $extraction->id), [
            'title' => 'Test Expense',
            'category' => 'office',
            'amount' => 100,
            'currency' => 'THB',
            'expense_date' => '2026-09-21',
            'tax_mode' => 'none',
            'supplier_id' => $foreignSupplier->id,
        ]);
        $resExpenseSupplier->assertSessionHasErrors(['supplier_id']);

        // 2. Attempt convert-expense with foreign project
        $resExpenseProject = $this->actingAs($userA)->post(route('ocr.convert-expense', $extraction->id), [
            'title' => 'Test Expense',
            'category' => 'office',
            'amount' => 100,
            'currency' => 'THB',
            'expense_date' => '2026-09-21',
            'tax_mode' => 'none',
            'project_id' => $foreignProject->id,
        ]);
        $resExpenseProject->assertSessionHasErrors(['project_id']);

        // 3. Attempt convert-vendor-bill with foreign supplier
        $resBillSupplier = $this->actingAs($userA)->post(route('ocr.convert-vendor-bill', $extraction->id), [
            'supplier_id' => $foreignSupplier->id,
            'amount' => 500,
            'currency' => 'THB',
            'expense_date' => '2026-09-21',
            'tax_mode' => 'none',
        ]);
        $resBillSupplier->assertSessionHasErrors(['supplier_id']);
    }

    public function test_raw_provider_error_is_sanitized_and_not_leaked(): void
    {
        // 1. Test OcrProcessingService sanitizer
        $rawLeak = 'Failed connecting to https://internal-api.secret.corp:8443/v1 with Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9 and key sk-998877665544332211 for citizen 1234567890123';
        $sanitized = OcrProcessingService::sanitizeErrorMessage($rawLeak);

        $this->assertStringNotContainsString('sk-998877665544332211', $sanitized);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $sanitized);
        $this->assertStringNotContainsString('https://internal-api.secret.corp:8443/v1', $sanitized);
        $this->assertStringNotContainsString('1234567890123', $sanitized);
        $this->assertStringContainsString('[REDACTED_API_KEY]', $sanitized);
        $this->assertStringContainsString('[REDACTED_TOKEN]', $sanitized);
        $this->assertStringContainsString('[ENDPOINT_URL]', $sanitized);
        $this->assertStringContainsString('[REDACTED_ID]', $sanitized);

        // 2. Test that upload failure in controller redirects with safe flash error
        [$user, $org] = $this->setupOrgUser();

        // Simulate adapter failure by forcing an invalid file storage key
        $file = UploadedFile::fake()->create('broken.jpg', 10, 'image/jpeg');

        // Swap to an adapter that throws a raw leaky error
        $brokenService = new OcrProcessingService(new class implements OcrProviderInterface
        {
            public function extract(string $fileContents, string $mimeType, AiConfiguration $config): array
            {
                throw new \RuntimeException('Leaked URL https://api.openai.com/v1/auth?token=secret_123');
            }
        });
        $this->app->instance(OcrProcessingService::class, $brokenService);

        $response = $this->actingAs($user)->post(route('ocr.upload'), [
            'document' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'AI OCR processing failed. Please verify AI provider configuration or retry.');
    }

    public function test_cloud_ai_compliance_policy_blocks_unauthorized_cloud_provider(): void
    {
        [$user, $org] = $this->setupOrgUser();

        // Disable cloud AI processing for this organization via compliance policy
        Setting::create([
            'org_id' => $org->id,
            'key' => 'ai.compliance',
            'value_json' => ['allow_cloud_ai_processing' => false],
        ]);

        $config = AiConfiguration::create([
            'org_id' => $org->id,
            'provider_type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test-secret',
            'model_name' => 'gpt-4o-mini',
            'timeout_seconds' => 15,
        ]);

        // 1. Connection tester must block cloud request
        $tester = new AiConnectionTesterService;
        $res = $tester->testConnection($config);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Cloud AI processing is disabled for this organization by compliance policy', $res['message']);

        // 2. Vision adapter must throw exception
        $adapter = new UniversalOpenAiVisionAdapter;
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cloud AI processing is disabled for this organization');
        $adapter->extract('dummy', 'image/jpeg', $config);
    }
}
