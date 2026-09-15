<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2315CompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_customer_and_product_roundtrip_with_thai_and_optional_field_clearing(): void
    {
        $user = $this->createAdminUser(['customers.view', 'customers.create', 'customers.update', 'products.manage']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);

        // 1. Create customer with Thai unicode, long name, and all optional fields
        $thaiCompanyName = 'บริษัท นวัตกรรมดิจิทัลพัฒนาการค้าไทย จำกัด (มหาชน) - สำนักงานใหญ่';
        $createCustomerPayload = [
            'customer_code' => 'TH-CUST-001',
            'company_name' => $thaiCompanyName,
            'customer_type' => 'active',
            'status' => 'active',
            'tax_id' => '0105559876543',
            'email' => 'contact@thaicompany.example.com',
            'phone' => '02-123-4567',
            'address' => '999/99 อาคารดิจิทัลคอมเพล็กซ์ ชั้น 24 ถนนสุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพมหานคร 10110',
            'credit_limit' => '1500000.00',
            'payment_term_days' => 45,
            'note' => 'หมายเหตุสำหรับลูกค้ารายใหญ่พิเศษ ประจำปี 2026',
        ];

        $submissionKey = (string) Str::uuid();
        $this->withHeader('X-Form-Submission', $submissionKey)
            ->post(route('customers.store'), $createCustomerPayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $customer = Customer::where('org_id', $user->org_id)->where('company_name', $thaiCompanyName)->firstOrFail();
        $this->assertNotEmpty($customer->customer_code);
        $this->assertSame($thaiCompanyName, $customer->company_name);
        $this->assertSame('0105559876543', $customer->tax_id);
        $this->assertSame('active', $customer->customer_type);
        $this->assertSame('หมายเหตุสำหรับลูกค้ารายใหญ่พิเศษ ประจำปี 2026', $customer->note);

        // 2. Edit customer: clear optional fields (note, address, phone) without losing primary fields
        $updateCustomerPayload = [
            'company_name' => $thaiCompanyName,
            'customer_type' => 'active',
            'status' => 'active',
            'tax_id' => '0105559876543',
            'email' => 'contact@thaicompany.example.com',
            'phone' => '',
            'address' => '',
            'credit_limit' => '2000000.00',
            'payment_term_days' => 60,
            'note' => '',
        ];

        $this->patch(route('customers.update', $customer), $updateCustomerPayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertNull($customer->note);
        $this->assertNull($customer->address);
        $this->assertNull($customer->phone);
        $this->assertSame($thaiCompanyName, $customer->company_name);

        // 3. Product roundtrip: create with Thai description, then update clearing description
        $createProductPayload = [
            'name' => 'อุปกรณ์ตรวจวัดสัญญาณอัจฉริยะ IoT Sensor Pro',
            'type' => 'product',
            'sku' => 'SKU-IOT-001',
            'barcode' => '8851234567890',
            'price' => '4500.50',
            'cost' => '2300.25',
            'description' => 'รองรับโปรโตคอล MQTT / LoRaWAN สำหรับคลังสินค้า',
            'reorder_point' => '10.00',
            'is_active' => true,
        ];

        $this->withHeader('X-Form-Submission', (string) Str::uuid())
            ->post(route('products.store'), $createProductPayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $product = Product::where('org_id', $user->org_id)->where('sku', 'SKU-IOT-001')->firstOrFail();
        $this->assertSame('4500.50', (string) $product->price);
        $this->assertSame('2300.25', (string) $product->cost);

        $this->patch(route('products.update', $product), array_merge($createProductPayload, [
            'description' => '',
            'price' => '4999.99',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertNull($product->description);
        $this->assertSame('4999.99', (string) $product->price);
        $this->assertSame('2300.25', (string) $product->cost);
    }

    public function test_multi_tenant_org_isolation_and_permission_enforcement(): void
    {
        $orgAUser = $this->createAdminUser(['customers.view', 'customers.update', 'suppliers.view', 'suppliers.update']);
        $orgBUser = User::factory()->create();

        $foreignCustomer = Customer::create([
            'org_id' => $orgBUser->org_id,
            'customer_code' => 'FOREIGN-CUST',
            'company_name' => 'Foreign Corp',
            'owner_id' => $orgBUser->id,
            'status' => 'active',
        ]);

        $foreignSupplier = Supplier::create([
            'org_id' => $orgBUser->org_id,
            'supplier_code' => 'FOREIGN-SUPP',
            'name' => 'Foreign Supplier',
            'status' => 'active',
        ]);

        $this->actingAsOrgUser($orgAUser)->withSession(['auth.password_confirmed_at' => time()]);

        // Trying to update cross-org customer or supplier must return 404
        $this->patch(route('customers.update', $foreignCustomer), [
            'company_name' => 'Attempted hijack',
        ])->assertNotFound();

        $this->patch(route('suppliers.update', $foreignSupplier), [
            'name' => 'Attempted hijack supplier',
        ])->assertNotFound();

        // User without permission gets 403 Forbidden
        $viewerOnly = User::factory()->create(['org_id' => $orgAUser->org_id]);
        $viewRole = Role::create(['org_id' => $orgAUser->org_id, 'code' => 'viewer_only', 'name' => 'Viewer Only', 'is_system' => false]);
        $viewPermission = Permission::firstOrCreate(['code' => 'customers.view'], ['module' => 'sales', 'action' => 'view']);
        $viewRole->permissions()->attach($viewPermission);
        $viewerOnly->roles()->attach($viewRole);

        $localCustomer = Customer::create([
            'org_id' => $orgAUser->org_id,
            'customer_code' => 'LOCAL-CUST',
            'company_name' => 'Local Corp',
            'owner_id' => $orgAUser->id,
            'status' => 'active',
        ]);

        $this->actingAsOrgUser($viewerOnly)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('customers.update', $localCustomer), [
                'company_name' => 'Unauthorized update',
            ])->assertForbidden();
    }

    public function test_validation_422_preserves_submission_key_and_idempotency_retry(): void
    {
        $user = $this->createAdminUser(['projects.create']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);

        $submissionKey = (string) Str::uuid();

        // 1. Send invalid project payload (missing required name)
        $invalidPayload = [
            'name' => '',
            'status' => 'active',
            'progress_percent' => 10,
            'currency' => 'THB',
        ];

        // 1a. Standard session form request redirects back with validation errors
        $this->withHeader('X-Form-Submission', $submissionKey)
            ->post(route('projects.store'), $invalidPayload)
            ->assertSessionHasErrors('name');

        // 1b. JSON/API request explicitly returns HTTP 422 Unprocessable Entity
        $this->withHeader('X-Form-Submission', $submissionKey)
            ->postJson(route('projects.store'), $invalidPayload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        // Form submission record should NOT be created for validation/422 error
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('form_submissions', 0);

        // 2. Correct the payload with the SAME submission key -> must succeed
        $validPayload = [
            'name' => 'โครงการระบบคลังสินค้าอัจฉริยะ',
            'status' => 'active',
            'progress_percent' => 10,
            'currency' => 'THB',
            'budget_amount' => '2500000.00',
            'note' => 'โครงการติดตั้งระบบแทร็กกิ้งสินค้า',
        ];

        $this->withHeader('X-Form-Submission', $submissionKey)
            ->post(route('projects.store'), $validPayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('form_submissions', 1);

        // 3. Retry same submission key (simulating network timeout / double-click)
        // Must return redirect without creating a duplicate record
        $this->withHeader('X-Form-Submission', $submissionKey)
            ->post(route('projects.store'), $validPayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('projects', 1);
        $this->assertSame(1, Project::where('org_id', $user->org_id)->where('name', 'โครงการระบบคลังสินค้าอัจฉริยะ')->count());
    }

    public function test_line_item_math_and_tax_mode_authoritative_calculation(): void
    {
        $user = $this->createAdminUser(['quotations.create', 'quotations.view']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);

        $customer = Customer::create([
            'org_id' => $user->org_id,
            'customer_code' => 'CUST-MATH',
            'company_name' => 'Math Test Customer',
            'owner_id' => $user->id,
            'status' => 'active',
        ]);

        $product1 = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'SKU-MATH-01',
            'name' => 'Subscription Service',
            'type' => 'service',
            'price' => '1000.00',
            'cost' => '300.00',
            'is_active' => true,
        ]);

        $product2 = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'SKU-MATH-02',
            'name' => 'Setup & Onboarding Package',
            'type' => 'service',
            'price' => '500.00',
            'cost' => '150.00',
            'is_active' => true,
        ]);

        // Multi-line payload with 2 distinct line items
        $payload = [
            'customer_id' => $customer->id,
            'deal_id' => '',
            'status' => 'draft',
            'tax_mode' => 'exclusive',
            'issue_date' => '2026-09-15',
            'valid_until' => '2026-10-15',
            'discount_amount' => '100.00',
            'currency' => 'THB',
            'items' => [
                [
                    'product_id' => $product1->id,
                    'description' => 'Implementation & Support',
                    'quantity' => '2',
                    'unit' => 'job',
                    'unit_price' => '1000.00',
                    'discount_amount' => '0.00',
                    'tax_rate' => '7.00',
                ],
                [
                    'product_id' => $product2->id,
                    'description' => 'User Training & Configuration',
                    'quantity' => '3',
                    'unit' => 'session',
                    'unit_price' => '500.00',
                    'discount_amount' => '0.00',
                    'tax_rate' => '7.00',
                ],
            ],
        ];

        $this->withHeader('X-Form-Submission', (string) Str::uuid())
            ->post(route('quotations.store'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $quotation = Quotation::where('org_id', $user->org_id)->firstOrFail();
        // Line 1: 2 * 1000.00 = 2000.00
        // Line 2: 3 * 500.00 = 1500.00
        // Subtotal = 2000.00 + 1500.00 = 3500.00
        // Document discount: 100.00 -> Taxable = 3500.00 - 100.00 = 3400.00
        // Tax 7% = 3400.00 * 0.07 = 238.00
        // Total = 3400.00 + 238.00 = 3638.00
        $this->assertSame('3500.00', (string) $quotation->subtotal);
        $this->assertSame('238.00', (string) $quotation->tax_amount);
        $this->assertSame('3638.00', (string) $quotation->total);
        $this->assertCount(2, $quotation->items);
    }

    public function test_closed_accounting_period_blocks_journal_posting(): void
    {
        $user = $this->createAdminUser(['accounting.journals.post']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);

        // Create a closed period for August 2026
        AccountingPeriod::create([
            'org_id' => $user->org_id,
            'name' => 'August 2026',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'closed',
        ]);

        $journalPayload = [
            'posting_date' => '2026-08-15',
            'description' => 'Attempted backdated entry in closed period',
            'idempotency_key' => (string) Str::uuid(),
            'lines' => [
                ['account_code' => '1110', 'debit' => '500.00', 'credit' => ''],
                ['account_code' => '3100', 'debit' => '', 'credit' => '500.00'],
            ],
        ];

        $this->post(route('accounting.journals.store'), $journalPayload)
            ->assertSessionHasErrors('posting_date');

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_stock_transfer_enforces_source_balance_guard(): void
    {
        $user = $this->createAdminUser(['inventory.adjust']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);

        $sourceWh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'SRC-WH', 'name' => 'Source Warehouse']);
        $destWh = Warehouse::create(['org_id' => $user->org_id, 'code' => 'DST-WH', 'name' => 'Destination Warehouse']);
        $product = Product::create([
            'org_id' => $user->org_id,
            'sku' => 'SKU-STOCK-01',
            'name' => 'Stock Item',
            'type' => 'product',
            'price' => '100.00',
            'cost' => '50.00',
            'is_active' => true,
        ]);

        // Attempt transfer with 0 stock in source warehouse -> must be rejected with 422
        $this->post(route('stock-transfers.store'), [
            'product_id' => $product->id,
            'source_warehouse_id' => $sourceWh->id,
            'destination_warehouse_id' => $destWh->id,
            'quantity' => '10',
            'transfer_date' => '2026-09-15',
        ])->assertStatus(422);

        $this->assertDatabaseCount('stock_movements', 0);
    }

    private function createAdminUser(array $permissionCodes): User
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'tester_role', 'name' => 'Tester Role', 'is_system' => true]);

        foreach ($permissionCodes as $code) {
            [$module, $action] = explode('.', $code, 2);
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user;
    }
}
