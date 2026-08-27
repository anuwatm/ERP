<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PettyCashFund;
use App\Models\PettyCashRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase10TreasuryOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_petty_cash_request_requires_independent_approval_and_can_be_paid_and_reimbursed(): void
    {
        $manager = User::factory()->create();
        $requester = User::factory()->create([
            'org_id' => $manager->org_id,
            'branch_id' => $manager->branch_id,
            'division_id' => $manager->division_id,
            'department_id' => $manager->department_id,
        ]);
        $this->attachRole($manager, 'finance', ['petty_cash.view', 'petty_cash.manage', 'petty_cash.approve']);
        $this->attachRole($requester, 'requester', ['petty_cash.manage', 'petty_cash.approve']);
        $account = $this->account($manager);

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('petty-cash.funds.store'), ['custodian_user_id' => $requester->id, 'bank_account_id' => $account->id, 'imprest_amount' => '500.00'])
            ->assertRedirect();
        $fund = PettyCashFund::firstOrFail();

        $this->actingAsOrgUser($requester)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('petty-cash.requests.store'), ['petty_cash_fund_id' => $fund->id, 'amount' => '125.00', 'expense_date' => '2026-08-26', 'purpose' => 'Office supplies'])
            ->assertRedirect();
        $cashRequest = PettyCashRequest::firstOrFail();

        $this->actingAsOrgUser($requester)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('petty-cash.requests.approve', $cashRequest))
            ->assertStatus(422);

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('petty-cash.requests.approve', $cashRequest))
            ->assertRedirect();
        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('petty-cash.requests.pay', $cashRequest))
            ->assertRedirect();
        $this->assertSame('paid', $cashRequest->fresh()->status);

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('petty-cash.reimbursements.store'), ['petty_cash_fund_id' => $fund->id, 'bank_account_id' => $account->id, 'amount' => '125.00', 'reimbursed_at' => '2026-08-26'])
            ->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'petty_cash_reimbursement.create']);
    }

    public function test_cheque_can_only_clear_against_matching_statement_line(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['cheques.view', 'cheques.manage']);
        $account = $this->account($finance);
        $statement = BankStatement::create(['org_id' => $finance->org_id, 'bank_account_id' => $account->id, 'statement_date_from' => '2026-08-26', 'statement_date_to' => '2026-08-26', 'line_count' => 1, 'status' => 'open']);
        $line = BankStatementLine::create(['org_id' => $finance->org_id, 'bank_statement_id' => $statement->id, 'bank_account_id' => $account->id, 'transaction_date' => '2026-08-26', 'amount_signed' => '250.00', 'row_fingerprint' => hash('sha256', 'cheque-line'), 'status' => 'unreconciled']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('cheques.store'), $this->chequePayload($account))
            ->assertRedirect();
        $cheque = Cheque::firstOrFail();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('cheques.transition', $cheque), ['status' => 'cleared'])
            ->assertStatus(422);
        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('cheques.transition', $cheque), ['status' => 'cleared', 'bank_statement_line_id' => $line->id])
            ->assertRedirect();

        $this->assertSame('cleared', $cheque->fresh()->status);
        $this->assertNotNull($cheque->fresh()->cleared_at);
    }

    public function test_treasury_report_is_organization_scoped_and_summarizes_account_activity(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['treasury.reports.view']);
        $account = $this->account($finance, '1000.00');
        $customer = Customer::create(['org_id' => $finance->org_id, 'customer_code' => 'CUST-TR-001', 'company_name' => 'Treasury Customer', 'customer_type' => 'company', 'status' => 'active']);
        $invoice = Invoice::create(['org_id' => $finance->org_id, 'invoice_no' => '000001', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => '2026-08-26', 'subtotal' => '500.00', 'total' => '500.00', 'balance_due' => '500.00', 'currency' => 'THB']);
        Payment::create(['org_id' => $finance->org_id, 'invoice_id' => $invoice->id, 'bank_account_id' => $account->id, 'entry_type' => 'receipt', 'amount' => '500.00', 'payment_date' => '2026-08-26', 'payment_method' => 'bank_transfer', 'idempotency_key' => (string) Str::uuid()]);
        Expense::create(['org_id' => $finance->org_id, 'expense_no' => 'EXP-TR-001', 'category' => 'office', 'title' => 'Office', 'amount' => '200.00', 'expense_date' => '2026-08-26', 'bank_account_id' => $account->id, 'status' => 'paid', 'paid_at' => '2026-08-26']);

        $this->actingAsOrgUser($finance)->get(route('treasury-reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/TreasuryReports')
                ->where('accounts.0.expected_balance', 1300));
    }

    private function account(User $user, string $openingBalance = '0.00'): BankAccount
    {
        return BankAccount::create(['org_id' => $user->org_id, 'bank_name' => 'Example Bank', 'account_name' => 'Operating', 'account_number' => '1234567890', 'account_number_hash' => hash('sha256', '1234567890'), 'account_type' => 'savings', 'currency' => 'THB', 'status' => 'active', 'opening_balance' => $openingBalance]);
    }

    private function chequePayload(BankAccount $account): array
    {
        return ['bank_account_id' => $account->id, 'direction' => 'received', 'cheque_no' => 'CHQ-001', 'bank_name' => 'Example Bank', 'drawer_or_payee' => 'Customer', 'amount' => '250.00', 'issue_date' => '2026-08-20', 'due_date' => '2026-08-26'];
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
