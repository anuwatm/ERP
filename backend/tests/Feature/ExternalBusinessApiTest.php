<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\IntegrationApiKey;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExternalBusinessApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(5, 0));
    }

    private function issuer(string $roleCode = 'admin'): User
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => $roleCode, 'name' => 'Issuer']);
        $codes = ['api_keys.view', 'api_keys.manage', 'customers.view', 'suppliers.view', 'invoices.view', 'quotations.view', 'delivery_orders.view', 'payments.view', 'purchase_orders.view', 'inventory.view', 'projects.view', 'tasks.view'];
        foreach ($codes as $code) {
            Permission::firstOrCreate(['code' => $code], ['module' => 'fixture', 'action' => 'view']);
        }
        $role->permissions()->attach(Permission::whereIn('code', $codes)->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    private function credential(User $user, array $scopes, array $policy = []): string
    {
        $prefix = 'erp_'.bin2hex(random_bytes(12));
        $secret = $prefix.'.'.bin2hex(random_bytes(32));
        IntegrationApiKey::create(['org_id' => $user->org_id, 'created_by' => $user->id,
            'name' => 'Business fixture', 'prefix' => $prefix, 'secret_hash' => hash('sha256', $secret),
            'scopes' => $scopes, 'access_policy' => $policy, 'expires_at' => now()->addDays(30)]);

        return $secret;
    }

    private function customer(User $user, string $code, array $values = []): Customer
    {
        return Customer::create(array_merge(['org_id' => $user->org_id, 'customer_code' => $code, 'company_name' => 'Company '.$code,
            'tax_id' => 'PRIVATE_TAX', 'phone' => 'PRIVATE_CONTACT', 'note' => 'PRIVATE_NOTE'], $values));
    }

    private function invoice(User $user, Customer $customer, string $number, string $status = 'sent'): Invoice
    {
        return Invoice::create(['org_id' => $user->org_id, 'customer_id' => $customer->id, 'invoice_no' => $number,
            'status' => $status, 'issue_date' => '2026-10-01', 'currency' => 'THB', 'total' => '123.45', 'balance_due' => '100.00', 'notes' => 'PRIVATE_NOTE']);
    }

    public function test_customer_credentials_read_only_their_party_and_published_documents(): void
    {
        $user = $this->issuer();
        $a = $this->customer($user, 'A');
        $b = $this->customer($user, 'B');
        $invoice = $this->invoice($user, $a, 'INV-A');
        $draft = $this->invoice($user, $a, 'DRAFT-A', 'draft');
        $foreign = $this->invoice($user, $b, 'INV-B');
        $this->invoice($user, $this->customer(User::factory()->create(), 'OUT'), 'CORRUPT');
        DeliveryOrder::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'do_no' => 'DO-A', 'status' => 'delivered', 'delivery_date' => '2026-10-19']);
        DeliveryOrder::create(['org_id' => $user->org_id, 'invoice_id' => $foreign->id, 'do_no' => 'DO-B', 'status' => 'delivered', 'delivery_date' => '2026-10-19']);
        $token = $this->credential($user, ['customers:read', 'invoices:read', 'delivery-orders:read', 'summary:sales:read'], ['principal' => 'customer', 'party_id' => $a->id]);
        $this->withToken($token)->getJson('/api/v1/customers')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id)
            ->assertJsonMissingPath('data.0.tax_id')->assertJsonMissingPath('data.0.phone');
        $this->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $invoice->id);
        $this->getJson('/api/v1/invoices/'.$foreign->id)->assertNotFound();
        $this->getJson('/api/v1/invoices/'.$draft->id)->assertNotFound();
        $this->getJson('/api/v1/delivery-orders')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/summaries/sales')->assertForbidden();
    }

    public function test_sales_document_lines_are_bounded_and_filter_foreign_org_relations(): void
    {
        $user = $this->issuer();
        $invoice = $this->invoice($user, $this->customer($user, 'A'), 'INV-A');
        $foreign = User::factory()->create();
        $product = Product::create(['org_id' => $foreign->org_id, 'name' => 'Foreign product', 'type' => 'product']);
        InvoiceItem::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'description' => 'Safe line', 'quantity' => '1.2500', 'unit_price' => '10.00', 'line_total' => '12.50']);
        InvoiceItem::create(['org_id' => $user->org_id, 'invoice_id' => $invoice->id, 'product_id' => $product->id, 'description' => 'Foreign product', 'quantity' => '1', 'unit_price' => '1', 'line_total' => '1']);
        InvoiceItem::create(['org_id' => $foreign->org_id, 'invoice_id' => $invoice->id, 'description' => 'Foreign line', 'quantity' => '1', 'unit_price' => '1', 'line_total' => '1']);
        $token = $this->credential($user, ['invoices:read']);
        $this->withToken($token)->getJson('/api/v1/invoices/'.$invoice->id)->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.total', '123.45')->assertJsonPath('data.items.0.quantity', '1.2500')
            ->assertJsonMissingPath('data.notes')->assertJsonMissingPath('data.items.0.description');
        $this->getJson('/api/v1/invoices/'.$invoice->id.'/items?limit=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_integration_visibility_never_exceeds_issuer_current_role_and_row_access(): void
    {
        $user = $this->issuer('sales');
        $visible = $this->customer($user, 'OWN', ['owner_id' => $user->id]);
        $hidden = $this->customer($user, 'HIDDEN');
        $this->invoice($user, $visible, 'VISIBLE');
        $otherInvoice = $this->invoice($user, $hidden, 'HIDDEN');
        $token = $this->credential($user, ['customers:read', 'invoices:read']);
        $this->withToken($token)->getJson('/api/v1/customers')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/invoices/'.$otherInvoice->id)->assertNotFound();
        $user->roles()->detach();
        $this->getJson('/api/v1/customers')->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    public function test_party_credential_creation_rejects_foreign_party_and_org_wide_scopes(): void
    {
        $user = $this->issuer();
        $party = $this->customer($user, 'A');
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('settings.api-access.store'), ['name' => 'Customer app', 'scopes' => ['customers:read', 'invoices:read'],
                'access_policy' => ['principal' => 'customer', 'party_id' => $party->id]])->assertCreated();
        $this->postJson(route('settings.api-access.store'), ['name' => 'Too broad', 'scopes' => ['summary:sales:read'],
            'access_policy' => ['principal' => 'customer', 'party_id' => $party->id]])->assertUnprocessable();
        $foreign = $this->customer(User::factory()->create(), 'OUT');
        $this->postJson(route('settings.api-access.store'), ['name' => 'Foreign', 'scopes' => ['customers:read'],
            'access_policy' => ['principal' => 'customer', 'party_id' => $foreign->id]])->assertUnprocessable();
    }

    public function test_procurement_uses_posted_receipt_quantities_and_keeps_units_separate(): void
    {
        $user = $this->issuer();
        $supplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => 'SUP', 'name' => 'Supplier']);
        $otherSupplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => 'OTHER', 'name' => 'Other']);
        $order = PurchaseOrder::create(['org_id' => $user->org_id, 'supplier_id' => $supplier->id, 'po_no' => 'PO1', 'status' => 'approved', 'order_date' => '2026-10-01', 'currency' => 'THB', 'total' => '100.00']);
        $otherOrder = PurchaseOrder::create(['org_id' => $user->org_id, 'supplier_id' => $otherSupplier->id, 'po_no' => 'PO2', 'status' => 'approved', 'order_date' => '2026-10-01', 'currency' => 'USD', 'total' => '50.00']);
        $item = PurchaseOrderItem::create(['org_id' => $user->org_id, 'purchase_order_id' => $order->id, 'description' => 'PCS', 'quantity' => '10.1250', 'unit' => 'pcs', 'unit_price' => '1.00', 'line_total' => '10.13']);
        PurchaseOrderItem::create(['org_id' => $user->org_id, 'purchase_order_id' => $order->id, 'description' => 'KG', 'quantity' => '2.5000', 'unit' => 'kg', 'unit_price' => '1.00', 'line_total' => '2.50']);
        PurchaseOrderItem::create(['org_id' => $user->org_id, 'purchase_order_id' => $order->id, 'description' => 'Distinct case-sensitive unit', 'quantity' => '9.0000', 'unit' => 'PCS', 'unit_price' => '1.00', 'line_total' => '9.00']);
        $receipt = GoodsReceipt::create(['org_id' => $user->org_id, 'purchase_order_id' => $order->id, 'grn_no' => 'GR1', 'received_date' => '2026-10-05', 'status' => 'posted']);
        GoodsReceiptItem::create(['org_id' => $user->org_id, 'goods_receipt_id' => $receipt->id, 'purchase_order_item_id' => $item->id, 'description' => 'PCS', 'quantity' => '4.5000', 'unit' => 'pcs']);
        $wrongParent = GoodsReceipt::create(['org_id' => $user->org_id, 'purchase_order_id' => $otherOrder->id, 'grn_no' => 'BAD', 'received_date' => '2026-10-05', 'status' => 'posted']);
        GoodsReceiptItem::create(['org_id' => $user->org_id, 'goods_receipt_id' => $wrongParent->id, 'purchase_order_item_id' => $item->id, 'description' => 'Wrong parent', 'quantity' => '99.0000', 'unit' => 'pcs']);
        $token = $this->credential($user, ['purchase-orders:read', 'goods-receipts:read', 'summary:procurement:read']);
        $response = $this->withToken($token)->getJson('/api/v1/purchase-orders/'.$order->id)->assertOk();
        $line = collect($response->json('data.items'))->firstWhere('id', $item->id);
        $this->assertSame('4.5000', $line['received_quantity']);
        $this->assertSame('5.6250', $line['outstanding_quantity']);
        $summary = $this->getJson('/api/v1/summaries/procurement')->assertOk()->assertJsonCount(2, 'data.period.orders_by_status_and_currency');
        $units = collect($summary->json('data.snapshot.open_order_quantities_by_unit'));
        $this->assertSame('5.6250', $units->firstWhere('unit', 'pcs')['outstanding_quantity']);
        $this->assertSame('2.5000', $units->firstWhere('unit', 'kg')['outstanding_quantity']);
        $this->assertSame('9.0000', $units->firstWhere('unit', 'PCS')['outstanding_quantity']);
        $this->assertCount(3, $units);
        $this->withToken($this->credential($user, ['purchase-orders:read'], ['principal' => 'supplier', 'party_id' => $supplier->id]))
            ->getJson('/api/v1/purchase-orders')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_project_and_task_read_scopes_hide_financial_fields_and_corrupt_parents(): void
    {
        $user = $this->issuer();
        $project = Project::create(['org_id' => $user->org_id, 'project_code' => 'P1', 'name' => 'Project', 'owner_id' => $user->id, 'budget_amount' => '999.00']);
        $task = Task::create(['org_id' => $user->org_id, 'project_id' => $project->id, 'title' => 'Visible', 'description' => 'PRIVATE_NOTE']);
        $foreign = User::factory()->create();
        $foreignProject = Project::create(['org_id' => $foreign->org_id, 'project_code' => 'P2', 'name' => 'Foreign', 'owner_id' => $foreign->id]);
        Task::create(['org_id' => $user->org_id, 'project_id' => $foreignProject->id, 'title' => 'Corrupt']);
        $token = $this->credential($user, ['projects:read', 'tasks:read']);
        $this->withToken($token)->getJson('/api/v1/projects/'.$project->id)->assertOk()->assertJsonMissingPath('data.budget_amount')->assertJsonMissingPath('data.owner_id');
        $this->getJson('/api/v1/projects/'.$project->id.'/tasks')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $task->id);
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.description');
        $this->withToken($this->credential($user, ['summary:projects:read']))->getJson('/api/v1/tasks')->assertForbidden();
        $this->withToken($this->credential($user, ['tasks:read']))->getJson('/api/v1/projects/'.$project->id.'/tasks')->assertForbidden();
    }

    public function test_all_external_v1_business_routes_are_read_only(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            $this->assertSame([], array_values(array_diff($route->methods(), ['GET', 'HEAD'])), $route->uri());
        }
        $this->postJson('/api/v1/products', ['name' => 'Forbidden write'])->assertStatus(405);
        $this->postJson('/api/v1/tasks', ['title' => 'Forbidden write'])->assertStatus(405);
        $this->assertSame(0, Task::count());
    }
}
