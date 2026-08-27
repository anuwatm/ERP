<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\Expense;
use App\Models\Permission;
use App\Models\Reconciliation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase10BankStatementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_finance_user_can_import_statement_and_duplicate_rows_are_rejected(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['treasury.reconciliation.view', 'treasury.reconciliation.manage']);
        $account = $this->account($finance);
        $payload = ['bank_account_id' => $account->id, 'statement' => $this->csv()];

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-statements.import'), $payload)->assertRedirect();

        $statement = BankStatement::firstOrFail();
        $this->assertSame(2, $statement->line_count);
        $this->assertSame(2, BankStatementLine::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'bank_statement.import', 'entity_id' => $statement->id]);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-statements.import'), ['bank_account_id' => $account->id, 'statement' => $this->csv()])
            ->assertStatus(422);
        $this->assertSame(1, BankStatement::count());
    }

    public function test_statement_import_rejects_other_organization_account_and_invalid_headers(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['treasury.reconciliation.manage']);
        $account = $this->account($other);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-statements.import'), ['bank_account_id' => $account->id, 'statement' => $this->csv()])
            ->assertSessionHasErrors('bank_account_id');

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-statements.import'), ['bank_account_id' => $this->account($finance)->id, 'statement' => UploadedFile::fake()->createWithContent('bad.csv', "date,amount\n2026-08-01,10\n")])
            ->assertStatus(422);
    }

    public function test_statement_line_can_match_unmatch_and_preserve_audit_history(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['treasury.reconciliation.manage']);
        $account = $this->account($finance);
        $statement = BankStatement::create(['org_id' => $finance->org_id, 'bank_account_id' => $account->id, 'statement_date_from' => '2026-08-01', 'statement_date_to' => '2026-08-01', 'line_count' => 1, 'status' => 'open']);
        $line = BankStatementLine::create(['org_id' => $finance->org_id, 'bank_statement_id' => $statement->id, 'bank_account_id' => $account->id, 'transaction_date' => '2026-08-01', 'amount_signed' => '-100.00', 'row_fingerprint' => hash('sha256', 'line-1'), 'status' => 'unreconciled']);
        $expense = Expense::create(['org_id' => $finance->org_id, 'expense_no' => 'EXP-001', 'category' => 'office', 'title' => 'Office rent', 'amount' => '100.00', 'expense_date' => '2026-08-01', 'bank_account_id' => $account->id, 'status' => 'paid', 'paid_at' => '2026-08-01']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-statement-lines.match', $line), ['reconcilable_type' => 'expense', 'reconcilable_id' => $expense->id])
            ->assertRedirect();
        $this->assertSame('reconciled', $line->fresh()->status);
        $this->assertSame(1, Reconciliation::count());

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-statement-lines.unmatch', $line), ['note' => 'Statement correction'])
            ->assertRedirect();
        $this->assertSame('unreconciled', $line->fresh()->status);
        $this->assertNotNull(Reconciliation::firstOrFail()->unmatched_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reconciliation.unmatch']);
    }

    public function test_reconciliation_rejects_candidate_with_different_amount_or_account(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['treasury.reconciliation.manage']);
        $account = $this->account($finance);
        $otherAccount = BankAccount::create(['org_id' => $finance->org_id, 'bank_name' => 'Other', 'account_name' => 'Other', 'account_number' => '99990000', 'account_number_hash' => hash('sha256', '99990000'), 'account_type' => 'savings', 'currency' => 'THB', 'status' => 'active']);
        $statement = BankStatement::create(['org_id' => $finance->org_id, 'bank_account_id' => $account->id, 'statement_date_from' => '2026-08-01', 'statement_date_to' => '2026-08-01', 'line_count' => 1, 'status' => 'open']);
        $line = BankStatementLine::create(['org_id' => $finance->org_id, 'bank_statement_id' => $statement->id, 'bank_account_id' => $account->id, 'transaction_date' => '2026-08-01', 'amount_signed' => '-100.00', 'row_fingerprint' => hash('sha256', 'line-2'), 'status' => 'unreconciled']);
        $expense = Expense::create(['org_id' => $finance->org_id, 'expense_no' => 'EXP-002', 'category' => 'office', 'title' => 'Wrong account', 'amount' => '100.00', 'expense_date' => '2026-08-01', 'bank_account_id' => $otherAccount->id, 'status' => 'paid', 'paid_at' => '2026-08-01']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-statement-lines.match', $line), ['reconcilable_type' => 'expense', 'reconcilable_id' => $expense->id])
            ->assertStatus(422);
    }

    private function csv(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('statement.csv', "transaction_date,amount,description,reference_no,balance_after\n2026-08-01,1000.00,Customer payment,REF-001,1000.00\n2026-08-02,-250.00,Office rent,REF-002,750.00\n");
    }

    private function account(User $user): BankAccount
    {
        return BankAccount::create(['org_id' => $user->org_id, 'bank_name' => 'Example Bank', 'account_name' => 'Operating', 'account_number' => '1234567890', 'account_number_hash' => hash('sha256', '1234567890'), 'account_type' => 'savings', 'currency' => 'THB', 'status' => 'active']);
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
