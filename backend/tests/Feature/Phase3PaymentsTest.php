<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Phase3PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_existing_finance_roles_receive_payment_permissions_from_migration(): void
    {
        $finance = User::factory()->create();
        $role = Role::create([
            'org_id' => $finance->org_id,
            'code' => 'finance',
            'name' => 'Finance',
            'is_system' => true,
        ]);
        $finance->roles()->attach($role->id);

        (require database_path('migrations/2026_07_28_000002_backfill_payment_permissions.php'))->up();
        $this->attachRole($finance, 'invoice_viewer', ['invoices.view']);

        $this->actingAsOrgUser($finance)->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Finance/Invoices'));
    }

    public function test_finance_user_can_record_partial_payment_receipt(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $invoice = $this->invoiceFor($finance, 1000);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'amount' => '400.00',
                'idempotency_key' => 'partial-001',
            ]))->assertRedirect();

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->paid_amount);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertTrue(AuditLog::where('action', 'payment.receipt')->where('entity_id', Payment::first()->id)->exists());
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $invoice = $this->invoiceFor($finance, 1000);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'amount' => '1000.00',
                'idempotency_key' => 'full-001',
            ]))->assertRedirect();

        $invoice->refresh();
        $this->assertSame('1000.00', $invoice->paid_amount);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_partial_payment_on_past_due_invoice_keeps_overdue_status(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $invoice = $this->invoiceFor($finance, 1000);
        $invoice->update([
            'status' => 'overdue',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'amount' => '400.00',
                'idempotency_key' => 'overdue-partial-001',
            ]))->assertRedirect();

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->paid_amount);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertSame('overdue', $invoice->status);
    }

    public function test_payment_receipt_cannot_overpay_invoice_balance(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $invoice = $this->invoiceFor($finance, 1000);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'amount' => '400.00',
                'idempotency_key' => 'overpay-001',
            ]))->assertRedirect();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'amount' => '700.00',
                'idempotency_key' => 'overpay-002',
            ]))->assertStatus(422);

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->paid_amount);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_payment_receipt_idempotency_key_prevents_duplicate_receipt(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $invoice = $this->invoiceFor($finance, 1000);
        $payload = $this->paymentPayload([
            'amount' => '300.00',
            'idempotency_key' => 'same-key-001',
        ]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $payload)
            ->assertRedirect();
        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $payload)
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('300.00', $invoice->paid_amount);
        $this->assertSame('700.00', $invoice->balance_due);
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_payment_receipt_idempotency_key_cannot_be_reused_for_different_payload(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $firstInvoice = $this->invoiceFor($finance, 1000);
        $secondInvoice = $this->invoiceFor($finance, 800, '000002', '000002');
        $key = 'cross-invoice-key-001';

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $firstInvoice), $this->paymentPayload([
                'amount' => '300.00',
                'idempotency_key' => $key,
            ]))
            ->assertRedirect();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $secondInvoice), $this->paymentPayload([
                'amount' => '200.00',
                'idempotency_key' => $key,
            ]))
            ->assertStatus(422);

        $this->assertSame(1, Payment::where('idempotency_key', $key)->count());
        $this->assertSame('0.00', $secondInvoice->refresh()->paid_amount);
        $this->assertSame('800.00', $secondInvoice->balance_due);
    }

    public function test_payment_receipt_idempotency_key_rejects_same_invoice_different_amount(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $invoice = $this->invoiceFor($finance, 1000);
        $key = 'same-invoice-different-amount';

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'amount' => '300.00',
                'idempotency_key' => $key,
            ]))->assertRedirect();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'amount' => '200.00',
                'idempotency_key' => $key,
            ]))->assertStatus(422);

        $invoice->refresh();
        $this->assertSame('300.00', $invoice->paid_amount);
        $this->assertSame('700.00', $invoice->balance_due);
        $this->assertSame(1, Payment::where('idempotency_key', $key)->count());
    }

    public function test_finance_user_can_reverse_payment_receipt(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create', 'payments.reverse']);
        $invoice = $this->invoiceFor($finance, 1000);
        $receipt = $this->recordReceipt($finance, $invoice, '400.00', 'reverse-001');

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $receipt), [
                'note' => 'Wrong receipt',
                'idempotency_key' => 'reverse-key-001',
            ])->assertRedirect();

        $invoice->refresh();
        $reversal = Payment::where('reversal_of_payment_id', $receipt->id)->firstOrFail();
        $this->assertSame('reversal', $reversal->entry_type);
        $this->assertSame('400.00', $reversal->amount);
        $this->assertSame(now()->toDateString(), $reversal->payment_date->toDateString());
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('1000.00', $invoice->balance_due);
        $this->assertSame('sent', $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertTrue(AuditLog::where('action', 'payment.reversal')->where('entity_id', $reversal->id)->exists());
    }

    public function test_payment_receipt_can_only_be_reversed_once(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create', 'payments.reverse']);
        $invoice = $this->invoiceFor($finance, 1000);
        $receipt = $this->recordReceipt($finance, $invoice, '400.00', 'reverse-once-001');

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $receipt), [
                'idempotency_key' => 'reverse-once-key-001',
            ])->assertRedirect();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $receipt), [
                'idempotency_key' => 'reverse-once-key-002',
            ])->assertStatus(422);

        $this->assertSame(1, Payment::where('reversal_of_payment_id', $receipt->id)->count());
    }

    public function test_payment_reversal_idempotency_key_prevents_duplicate_reversal(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create', 'payments.reverse']);
        $invoice = $this->invoiceFor($finance, 1000);
        $receipt = $this->recordReceipt($finance, $invoice, '400.00', 'reverse-idem-001');
        $payload = ['idempotency_key' => 'reverse-idem-key-001'];

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $receipt), $payload)->assertRedirect();
        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $receipt), $payload)->assertRedirect();

        $invoice->refresh();
        $this->assertSame(1, Payment::where('reversal_of_payment_id', $receipt->id)->count());
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('1000.00', $invoice->balance_due);
    }

    public function test_payment_reversal_idempotency_key_cannot_be_reused_for_another_receipt(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create', 'payments.reverse']);
        $invoice = $this->invoiceFor($finance, 1000);
        $firstReceipt = $this->recordReceipt($finance, $invoice, '200.00', 'reverse-mismatch-001');
        $secondReceipt = $this->recordReceipt($finance, $invoice, '200.00', 'reverse-mismatch-002');
        $key = 'reverse-mismatch-key-001';

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $firstReceipt), [
                'idempotency_key' => $key,
            ])->assertRedirect();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $secondReceipt), [
                'idempotency_key' => $key,
            ])->assertStatus(422);

        $this->assertSame(1, Payment::where('idempotency_key', $key)->count());
        $this->assertSame(0, Payment::where('reversal_of_payment_id', $secondReceipt->id)->count());
    }

    public function test_database_unique_constraint_prevents_duplicate_reversal_target(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create', 'payments.reverse']);
        $invoice = $this->invoiceFor($finance, 1000);
        $receipt = $this->recordReceipt($finance, $invoice, '400.00', 'reverse-db-001');

        Payment::create([
            'org_id' => $receipt->org_id,
            'invoice_id' => $receipt->invoice_id,
            'entry_type' => 'reversal',
            'reversal_of_payment_id' => $receipt->id,
            'amount' => $receipt->amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => $receipt->payment_method,
            'idempotency_key' => 'reverse-db-key-001',
            'created_by' => $finance->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Payment::create([
            'org_id' => $receipt->org_id,
            'invoice_id' => $receipt->invoice_id,
            'entry_type' => 'reversal',
            'reversal_of_payment_id' => $receipt->id,
            'amount' => $receipt->amount,
            'payment_date' => now()->toDateString(),
            'payment_method' => $receipt->payment_method,
            'idempotency_key' => 'reverse-db-key-002',
            'created_by' => $finance->id,
        ]);
    }

    public function test_concurrent_payment_receipts_cannot_overpay_invoice_balance(): void
    {
        $originalDefaultConnection = config('database.default');
        $databasePath = storage_path('framework/testing/concurrent-payment-'.Str::uuid().'.sqlite');
        $startFile = storage_path('framework/testing/concurrent-payment-'.Str::uuid().'.start');

        if (! is_dir(dirname($databasePath))) {
            mkdir(dirname($databasePath), 0777, true);
        }

        touch($databasePath);

        config([
            'database.default' => 'concurrent_payment',
            'database.connections.concurrent_payment' => [
                'driver' => 'sqlite',
                'database' => $databasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('concurrent_payment');
        DB::connection('concurrent_payment')->getPdo()->exec('PRAGMA busy_timeout = 5000');
        Artisan::call('migrate:fresh', ['--database' => 'concurrent_payment', '--force' => true]);

        try {
            $finance = User::factory()->create();
            $this->attachRole($finance, 'finance', ['payments.create']);
            $invoice = $this->invoiceFor($finance, 1000);
            $worker = base_path('tests/Support/concurrent_payment_worker.php');
            $first = $this->paymentRaceProcess($worker, $databasePath, $finance, $invoice, '600.00', 'race-001', $startFile, 600000);
            $second = $this->paymentRaceProcess($worker, $databasePath, $finance, $invoice, '600.00', 'race-002', $startFile, 600000);

            $first->start();
            $second->start();
            usleep(200000);
            touch($startFile);
            $first->wait();
            $second->wait();

            $invoice->refresh();
            $receiptCount = Payment::where('invoice_id', $invoice->id)->where('entry_type', 'receipt')->count();
            $successfulWorkers = collect([$first, $second])->filter(fn (Process $process) => $process->isSuccessful())->count();

            $this->assertSame(1, $successfulWorkers, $this->raceOutput($first, $second));
            $this->assertSame(1, $receiptCount, $this->raceOutput($first, $second));
            $this->assertSame('600.00', $invoice->paid_amount);
            $this->assertSame('400.00', $invoice->balance_due);
            $this->assertSame('partially_paid', $invoice->status);
            $this->assertLessThanOrEqual((float) $invoice->total, (float) $invoice->paid_amount);
        } finally {
            config(['database.default' => $originalDefaultConnection]);
            DB::purge('concurrent_payment');

            if (file_exists($startFile)) {
                unlink($startFile);
            }

            if (file_exists($databasePath)) {
                unlink($databasePath);
            }
        }
    }

    public function test_payment_reverse_requires_reverse_permission(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['payments.create']);
        $invoice = $this->invoiceFor($finance, 1000);
        $receipt = $this->recordReceipt($finance, $invoice, '400.00', 'reverse-perm-001');

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('payments.reverse', $receipt), [
                'idempotency_key' => 'reverse-perm-key-001',
            ])->assertForbidden();
    }

    private function paymentRaceProcess(string $worker, string $databasePath, User $user, Invoice $invoice, string $amount, string $key, string $startFile, int $holdMicros): Process
    {
        return new Process([
            PHP_BINARY,
            '-c',
            base_path('.php/php.ini'),
            $worker,
            $databasePath,
            $user->id,
            $invoice->id,
            $amount,
            $key,
            $startFile,
            (string) $holdMicros,
        ], base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $databasePath,
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ], null, 15);
    }

    private function raceOutput(Process $first, Process $second): string
    {
        return implode(PHP_EOL, [
            'first exit: '.($first->getExitCode() ?? 'running'),
            trim($first->getOutput()),
            trim($first->getErrorOutput()),
            'second exit: '.($second->getExitCode() ?? 'running'),
            trim($second->getOutput()),
            trim($second->getErrorOutput()),
        ]);
    }

    private function invoiceFor(User $user, int $total, string $invoiceNo = '000001', string $customerCode = '000001'): Invoice
    {
        $customer = Customer::create([
            'org_id' => $user->org_id,
            'customer_code' => $customerCode,
            'company_name' => 'Payment Customer',
            'owner_id' => $user->id,
        ]);

        return Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => $invoiceNo,
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

    private function recordReceipt(User $user, Invoice $invoice, string $amount, string $key): Payment
    {
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('invoices.payments.store', $invoice), $this->paymentPayload([
                'amount' => $amount,
                'idempotency_key' => $key,
            ]))->assertRedirect();

        return Payment::where('invoice_id', $invoice->id)
            ->where('entry_type', 'receipt')
            ->where('idempotency_key', $key)
            ->firstOrFail();
    }

    private function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => '100.00',
            'payment_date' => '2026-07-28',
            'payment_method' => 'bank_transfer',
            'reference_no' => 'REF-001',
            'note' => 'Manual receipt',
            'idempotency_key' => (string) Str::uuid(),
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
