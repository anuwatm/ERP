<?php

namespace Tests\Feature;

use App\Models\BillingNote;
use App\Models\CreditDebitNote;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase9CommercialDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_credit_note_reduces_invoice_balance_and_blocks_excess_credit(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['credit_debit_notes.create', 'tax_reports.view']);
        $invoice = $this->invoice($finance, 1070);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('credit-debit-notes.store'), [
                'invoice_id' => $invoice->id,
                'type' => 'credit',
                'issue_date' => '2026-08-25',
                'items' => [['description' => 'Discount after invoice', 'quantity' => '1', 'unit_price' => '100', 'tax_rate' => '7']],
            ])
            ->assertRedirect();

        $this->assertSame('963.00', $invoice->fresh()->balance_due);
        $note = CreditDebitNote::firstOrFail();
        $this->assertSame('credit', $note->type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'credit_debit_note.create', 'entity_id' => $note->id]);
        $csv = $this->actingAsOrgUser($finance)
            ->get(route('tax-reports.export', ['type' => 'sales', 'date_from' => '2026-08-01', 'date_to' => '2026-08-31']))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('credit_note', $csv);
        $this->assertStringContainsString('-107.00', $csv);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('credit-debit-notes.store'), [
                'invoice_id' => $invoice->id,
                'type' => 'credit',
                'issue_date' => '2026-08-25',
                'items' => [['description' => 'Too much', 'quantity' => '1', 'unit_price' => '1000', 'tax_rate' => '7']],
            ])
            ->assertStatus(422);
    }

    public function test_billing_note_groups_customer_invoices_only(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['billing_notes.create', 'billing_notes.view']);
        $invoiceA = $this->invoice($finance, 1000);
        $invoiceB = $this->invoice($finance, 500, $invoiceA->customer_id);
        $other = User::factory()->create();
        $hidden = $this->invoice($other, 300);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('billing-notes.store'), [
                'customer_id' => $invoiceA->customer_id,
                'invoice_ids' => [$invoiceA->id, $invoiceB->id],
                'issue_date' => '2026-08-25',
            ])
            ->assertRedirect();

        $billing = BillingNote::with('lines')->firstOrFail();
        $this->assertSame('1500.00', $billing->total);
        $this->assertCount(2, $billing->lines);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('billing-notes.store'), [
                'customer_id' => $invoiceA->customer_id,
                'invoice_ids' => [$hidden->id],
                'issue_date' => '2026-08-25',
            ])
            ->assertStatus(302);
    }

    public function test_delivery_order_posts_outbound_stock_when_delivered(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['delivery_orders.create']);
        $product = Product::create(['org_id' => $finance->org_id, 'sku' => 'DO-001', 'name' => 'Deliverable', 'type' => 'product', 'unit' => 'pcs', 'price' => 100, 'cost' => 50, 'is_active' => true]);
        $invoice = $this->invoice($finance, 100);
        $invoice->items()->create(['org_id' => $finance->org_id, 'product_id' => $product->id, 'description' => 'Deliverable', 'quantity' => 2, 'unit' => 'pcs', 'unit_price' => 50, 'line_total' => 100, 'sort_order' => 0]);
        StockMovement::create(['org_id' => $finance->org_id, 'product_id' => $product->id, 'movement_type' => 'adjustment_in', 'movement_date' => '2026-08-24', 'quantity' => 5, 'unit_cost' => 50, 'total_cost' => 250]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('delivery-orders.store'), [
                'invoice_id' => $invoice->id,
                'delivery_date' => '2026-08-25',
                'status' => 'delivered',
                'receiver_name' => 'Customer Receiver',
            ])
            ->assertRedirect();

        $this->assertSame('delivered', DeliveryOrder::firstOrFail()->status);
        $this->assertSame('3.0000', number_format((float) StockMovement::where('product_id', $product->id)->sum('quantity'), 4, '.', ''));

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('delivery-orders.store'), [
                'invoice_id' => $invoice->id, 'delivery_date' => '2026-08-25', 'status' => 'delivered',
            ])->assertStatus(422);
        $this->assertSame(1, DeliveryOrder::count());
    }

    public function test_purchase_request_approval_converts_to_purchase_order(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['purchase_requests.create', 'purchase_requests.approve']);
        $supplier = Supplier::create(['org_id' => $finance->org_id, 'supplier_code' => '000001', 'name' => 'PR Supplier', 'status' => 'active']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('purchase-requests.store'), [
                'supplier_id' => $supplier->id,
                'request_date' => '2026-08-25',
                'items' => [['description' => 'Requested item', 'quantity' => '2', 'unit_price' => '100']],
            ])
            ->assertRedirect();

        $pr = PurchaseRequest::firstOrFail();
        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('purchase-requests.approve', $pr))
            ->assertRedirect();
        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('purchase-requests.convert-to-po', $pr->fresh()))
            ->assertRedirect();

        $this->assertSame('converted', $pr->fresh()->status);
        $this->assertSame('200.00', PurchaseOrder::firstOrFail()->total);
    }

    public function test_voucher_can_be_issued_and_commercial_index_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['vouchers.create', 'billing_notes.view']);
        Voucher::create(['org_id' => $other->org_id, 'voucher_no' => 'HIDDEN', 'type' => 'payment', 'voucher_date' => '2026-08-25', 'amount' => 1]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('vouchers.store'), [
                'type' => 'receipt',
                'voucher_date' => '2026-08-25',
                'amount' => '500.00',
                'partner_name' => 'Voucher Partner',
            ])
            ->assertRedirect();

        $this->actingAsOrgUser($finance)->get(route('commercial-documents.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/CommercialDocuments')
                ->has('vouchers', 1)
                ->where('vouchers.0.partner_name', 'Voucher Partner')
            );

        $voucher = Voucher::where('org_id', $finance->org_id)->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'voucher.create', 'entity_id' => $voucher->id]);
        $this->actingAsOrgUser($finance)
            ->get(route('commercial-documents.print', ['type' => 'voucher', 'id' => $voucher->id]))
            ->assertOk()
            ->assertSee('Receipt Voucher');
    }

    private function invoice(User $user, float $total, ?string $customerId = null): Invoice
    {
        $customerId ??= Customer::create(['org_id' => $user->org_id, 'customer_code' => Str::random(6), 'company_name' => 'Phase 9 Customer', 'owner_id' => $user->id])->id;

        return Invoice::create(['org_id' => $user->org_id, 'invoice_no' => Str::upper(Str::random(6)), 'customer_id' => $customerId, 'status' => 'sent', 'tax_mode' => 'exclusive', 'issue_date' => '2026-08-25', 'subtotal' => $total, 'tax_amount' => 0, 'total' => $total, 'balance_due' => $total]);
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => $code, 'name' => Str::headline($code), 'is_system' => true]);
        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            $permission = Permission::firstOrCreate(['code' => $permissionCode], ['module' => $parts[0], 'action' => $parts[count($parts) - 1]]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);

        return $role;
    }
}
