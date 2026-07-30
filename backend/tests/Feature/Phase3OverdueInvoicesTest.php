<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3OverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_only_open_past_due_invoices_overdue(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create([
            'org_id' => $user->org_id,
            'customer_code' => '000001',
            'company_name' => 'Overdue Customer',
            'owner_id' => $user->id,
        ]);
        $sent = $this->invoice($user, $customer, '000001', 'sent', 500, now()->subDay()->toDateString());
        $partial = $this->invoice($user, $customer, '000002', 'partially_paid', 200, now()->subDays(2)->toDateString());
        $future = $this->invoice($user, $customer, '000003', 'sent', 300, now()->addDay()->toDateString());
        $paid = $this->invoice($user, $customer, '000004', 'paid', 0, now()->subDay()->toDateString());
        $void = $this->invoice($user, $customer, '000005', 'void', 100, now()->subDay()->toDateString());

        $this->artisan('invoices:mark-overdue')
            ->expectsOutput('Marked 2 invoice(s) overdue.')
            ->assertSuccessful();

        $this->assertSame('overdue', $sent->refresh()->status);
        $this->assertSame('overdue', $partial->refresh()->status);
        $this->assertSame('sent', $future->refresh()->status);
        $this->assertSame('paid', $paid->refresh()->status);
        $this->assertSame('void', $void->refresh()->status);
        $this->assertSame(2, AuditLog::where('action', 'invoice.mark_overdue')->count());
    }

    private function invoice(User $user, Customer $customer, string $invoiceNo, string $status, int $balance, string $dueDate): Invoice
    {
        return Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => $invoiceNo,
            'customer_id' => $customer->id,
            'status' => $status,
            'tax_mode' => 'no_tax',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => $dueDate,
            'subtotal' => '1000.00',
            'total' => '1000.00',
            'paid_amount' => 1000 - $balance,
            'balance_due' => $balance,
        ]);
    }
}
