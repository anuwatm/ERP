<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase3FilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    public function test_payment_receipt_can_upload_attachment_and_download_with_parent_permission(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create', 'payments.view']);
        $invoice = $this->invoiceFor($finance, 1000);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'attachment' => UploadedFile::fake()->create('bank-slip.pdf', 20, 'application/pdf'),
            ]))->assertRedirect();

        $payment = Payment::firstOrFail();
        $file = StoredFile::firstOrFail();
        $this->assertSame($file->id, $payment->attachment_file_id);
        $this->assertSame('payment', $file->entity_type);
        $this->assertSame($payment->id, $file->entity_id);
        $this->assertStringStartsWith('tenants/'.$finance->org_id.'/', $file->storage_key);
        $this->assertStringNotContainsString('bank-slip', $file->storage_key);
        Storage::disk('local')->assertExists($file->storage_key);
        $this->assertTrue(AuditLog::where('action', 'file.upload')->where('entity_id', $file->id)->exists());

        $this->actingAsOrgUser($finance)->get(route('files.download', $file))
            ->assertOk();
        $this->assertTrue(AuditLog::where('action', 'file.download')->where('entity_id', $file->id)->exists());
    }

    public function test_expense_receipt_can_upload_and_download_with_parent_permission(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.create', 'expenses.view']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), $this->expensePayload([
                'receipt' => UploadedFile::fake()->create('receipt.jpg', 10, 'image/jpeg'),
            ]))->assertRedirect();

        $expense = Expense::firstOrFail();
        $file = StoredFile::firstOrFail();
        $this->assertSame($file->id, $expense->receipt_file_id);
        $this->assertSame('expense', $file->entity_type);
        $this->assertSame($expense->id, $file->entity_id);

        $this->actingAsOrgUser($finance)->get(route('files.download', $file))
            ->assertOk();
    }

    public function test_replacing_expense_receipt_removes_old_file_record_and_storage_object(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.create', 'expenses.update', 'expenses.view']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), $this->expensePayload([
                'receipt' => UploadedFile::fake()->create('old-receipt.pdf', 10, 'application/pdf'),
            ]))->assertRedirect();

        $expense = Expense::firstOrFail();
        $oldFile = StoredFile::firstOrFail();
        Storage::disk('local')->assertExists($oldFile->storage_key);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('expenses.update', $expense), $this->expensePayload([
                'title' => 'Updated hosting bill',
                'receipt' => UploadedFile::fake()->create('new-receipt.pdf', 10, 'application/pdf'),
            ]))->assertRedirect();

        $expense->refresh();
        $newFile = StoredFile::whereKey($expense->receipt_file_id)->firstOrFail();
        $this->assertNotSame($oldFile->id, $newFile->id);
        $this->assertSoftDeleted('files', ['id' => $oldFile->id]);
        Storage::disk('local')->assertMissing($oldFile->storage_key);
        Storage::disk('local')->assertExists($newFile->storage_key);
    }

    public function test_file_download_requires_parent_permission(): void
    {
        $finance = User::factory()->create();
        $viewer = User::factory()->create(['org_id' => $finance->org_id]);
        $this->attachRole($finance, 'finance', ['expenses.create', 'expenses.view']);
        $this->attachRole($viewer, 'viewer', ['dashboard.view']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), $this->expensePayload([
                'receipt' => UploadedFile::fake()->create('receipt.png', 10, 'image/png'),
            ]))->assertRedirect();

        $file = StoredFile::firstOrFail();

        $this->actingAsOrgUser($viewer)->get(route('files.download', $file))
            ->assertForbidden();
    }

    public function test_file_download_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.create', 'expenses.view']);
        $this->attachRole($other, 'finance', ['expenses.view']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), $this->expensePayload([
                'receipt' => UploadedFile::fake()->create('receipt.webp', 10, 'image/webp'),
            ]))->assertRedirect();

        $file = StoredFile::firstOrFail();

        $this->actingAsOrgUser($other)->get(route('files.download', $file))
            ->assertForbidden();
    }

    public function test_attachment_rejects_invalid_file_type(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $invoice = $this->invoiceFor($finance, 1000);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'attachment' => UploadedFile::fake()->create('shell.php', 1, 'text/x-php'),
            ]))->assertSessionHasErrors('attachment');

        $this->assertSame(0, Payment::count());
        $this->assertSame(0, StoredFile::count());
    }

    public function test_download_rejects_file_not_canonical_on_parent(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.view']);
        $invoice = $this->invoiceFor($finance, 1000);
        $payment = Payment::create([
            'org_id' => $finance->org_id,
            'invoice_id' => $invoice->id,
            'entry_type' => 'receipt',
            'amount' => '100.00',
            'payment_date' => '2026-07-28',
            'payment_method' => 'bank_transfer',
            'created_by' => $finance->id,
        ]);
        $file = StoredFile::create([
            'org_id' => $finance->org_id,
            'storage_key' => 'tenants/'.$finance->org_id.'/2026/07/'.Str::uuid().'.pdf',
            'file_name' => 'wrong.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'category' => 'receipt',
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
            'uploaded_by' => $finance->id,
        ]);
        Storage::disk('local')->put($file->storage_key, 'fake');

        $this->actingAsOrgUser($finance)->get(route('files.download', $file))
            ->assertForbidden();
    }

    private function invoiceFor(User $user, int $total): Invoice
    {
        $customer = Customer::create([
            'org_id' => $user->org_id,
            'customer_code' => '000001',
            'company_name' => 'File Customer',
            'owner_id' => $user->id,
        ]);

        return Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => '000001',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'tax_mode' => 'no_tax',
            'issue_date' => '2026-07-28',
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => 0,
            'balance_due' => $total,
        ]);
    }

    private function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => '100.00',
            'payment_date' => '2026-07-28',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'REF-FILE',
            'note' => 'Manual receipt',
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }

    private function expensePayload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'hosting',
            'title' => 'Hosting bill',
            'amount' => '1200.00',
            'expense_date' => '2026-07-28',
            'project_id' => null,
            'supplier_id' => null,
            'note' => 'Cloud host',
        ], $overrides);
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => $code,
            'name' => Str::headline($code),
            'is_system' => true,
        ]);

        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => $parts[0], 'action' => $parts[count($parts) - 1]]
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $role;
    }
}
