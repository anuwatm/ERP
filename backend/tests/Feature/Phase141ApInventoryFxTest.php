<?php

namespace Tests\Feature;

use App\Http\Controllers\Finance\BankTransferController;
use App\Http\Controllers\Finance\VendorPaymentController;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\FinancialJournalService;
use App\Services\FxRateService;
use App\Services\FxRevaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class Phase141ApInventoryFxTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_payment_settles_ap_and_posts_realized_fx(): void
    {
        $user = User::factory()->create();
        $this->rate($user, '2026-08-20', '37.000000');
        $expense = Expense::create(['org_id' => $user->org_id, 'expense_no' => 'AP-0001', 'category' => 'software', 'title' => 'USD subscription', 'amount' => 100, 'currency' => 'USD', 'base_currency' => 'THB', 'exchange_rate' => 36, 'base_amount' => 3600, 'tax_mode' => 'no_tax', 'tax_amount' => 0, 'base_tax_amount' => 0, 'payable_total' => 100, 'base_payable_total' => 3600, 'balance_due' => 100, 'base_balance_due' => 3600, 'expense_date' => '2026-08-01', 'status' => 'approved', 'created_by' => $user->id]);

        $request = Request::create('/', 'POST', ['amount' => 100, 'payment_date' => '2026-08-20']);
        $request->setUserResolver(fn () => $user);
        app(VendorPaymentController::class)->store($request, $expense, app(FinancialJournalService::class), app(FxRateService::class));

        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertSame('0.00', $expense->fresh()->balance_due);
        $this->assertSame(1, VendorPayment::count());
        $entry = JournalEntry::where('source_type', 'vendor_payment')->firstOrFail()->load('lines.account');
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '5400' && (float) $line->debit === 100.0));
    }

    public function test_payable_revaluation_is_idempotent_and_reversible(): void
    {
        $user = User::factory()->create();
        $this->rate($user, '2026-08-31', '37.000000');
        $expense = Expense::create(['org_id' => $user->org_id, 'expense_no' => 'AP-0002', 'category' => 'software', 'title' => 'USD payable', 'amount' => 100, 'currency' => 'USD', 'base_currency' => 'THB', 'exchange_rate' => 36, 'base_amount' => 3600, 'tax_mode' => 'no_tax', 'tax_amount' => 0, 'base_tax_amount' => 0, 'payable_total' => 100, 'base_payable_total' => 3600, 'balance_due' => 100, 'base_balance_due' => 3600, 'expense_date' => '2026-08-01', 'status' => 'approved', 'created_by' => $user->id]);
        $fx = app(FxRevaluationService::class);
        $this->assertSame(1, $fx->revaluePayables($user->org_id, '2026-08', $user->id));
        $this->assertSame(0, $fx->revaluePayables($user->org_id, '2026-08', $user->id));
        $this->assertSame('3700.00', $expense->fresh()->base_balance_due);
        $this->assertSame(1, $fx->reverseMonth($user->org_id, '2026-08', $user->id));
        $this->assertSame('3600.00', $expense->fresh()->base_balance_due);
    }

    public function test_partial_vendor_payment_can_be_reversed_without_changing_ar_payment_flow(): void
    {
        $user = User::factory()->create();
        $expense = Expense::create(['org_id' => $user->org_id, 'expense_no' => 'AP-0003', 'category' => 'software', 'title' => 'THB payable', 'amount' => 1000, 'currency' => 'THB', 'base_currency' => 'THB', 'exchange_rate' => 1, 'base_amount' => 1000, 'tax_mode' => 'no_tax', 'tax_amount' => 0, 'base_tax_amount' => 0, 'payable_total' => 1000, 'base_payable_total' => 1000, 'balance_due' => 1000, 'base_balance_due' => 1000, 'expense_date' => '2026-08-01', 'status' => 'approved', 'created_by' => $user->id]);
        $request = Request::create('/', 'POST', ['amount' => 400, 'payment_date' => '2026-08-20', 'idempotency_key' => '0e7c4264-0d96-47ae-b99a-17c95b92c4ea']);
        $request->setUserResolver(fn () => $user);
        $controller = app(VendorPaymentController::class);
        $controller->store($request, $expense, app(FinancialJournalService::class), app(FxRateService::class));
        $this->assertSame('partially_paid', $expense->fresh()->status);
        $this->assertSame('600.00', $expense->fresh()->balance_due);

        $reverse = Request::create('/', 'POST', ['idempotency_key' => 'd9979b88-71ff-4fbb-9671-9336c0d77dbe']);
        $reverse->setUserResolver(fn () => $user);
        $controller->reverse($reverse, VendorPayment::firstOrFail(), app(FinancialJournalService::class));
        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertSame('1000.00', $expense->fresh()->balance_due);
        $this->assertSame(2, VendorPayment::count());
    }

    public function test_bank_transfer_between_fcd_and_thb_posts_balanced_realized_fx_journal(): void
    {
        $user = User::factory()->create();
        $this->rate($user, '2026-08-20', '37.000000');
        $chart1 = ChartOfAccount::create(['org_id' => $user->org_id, 'code' => '1113', 'name' => 'USD Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_postable' => true, 'status' => 'active']);
        $chart2 = ChartOfAccount::create(['org_id' => $user->org_id, 'code' => '1114', 'name' => 'THB Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_postable' => true, 'status' => 'active']);

        $usdAccount = BankAccount::create(['org_id' => $user->org_id, 'bank_name' => 'KBANK', 'account_name' => 'USD Operating', 'account_number' => '1234567890', 'account_number_hash' => hash('sha256', '1234567890'), 'account_type' => 'current', 'currency' => 'USD', 'chart_of_account_id' => $chart1->id, 'created_by' => $user->id]);
        $thbAccount = BankAccount::create(['org_id' => $user->org_id, 'bank_name' => 'SCB', 'account_name' => 'THB Main', 'account_number' => '0987654321', 'account_number_hash' => hash('sha256', '0987654321'), 'account_type' => 'current', 'currency' => 'THB', 'chart_of_account_id' => $chart2->id, 'created_by' => $user->id]);

        $request = Request::create('/', 'POST', [
            'source_bank_account_id' => $usdAccount->id,
            'destination_bank_account_id' => $thbAccount->id,
            'transfer_date' => '2026-08-20',
            'source_amount' => 100, // 100 USD = 3700 THB base
            'destination_amount' => 3650, // Received 3650 THB (Loss 50 THB)
        ]);
        $request->setUserResolver(fn () => $user);

        app(BankTransferController::class)->store($request, app(FinancialJournalService::class), app(FxRateService::class));

        $entry = JournalEntry::where('source_type', 'bank_transfer')->firstOrFail()->load('lines.account');
        $this->assertSame(round((float) $entry->lines->sum('debit'), 2), round((float) $entry->lines->sum('credit'), 2));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '5400' && (float) $line->debit === 50.0));
    }

    private function rate(User $user, string $date, string $rate): void
    {
        ExchangeRate::create(['org_id' => $user->org_id, 'base_currency' => 'THB', 'quote_currency' => 'USD', 'rate_date' => $date, 'rate' => $rate, 'created_by' => $user->id]);
    }
}
