<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3ExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_existing_finance_roles_receive_expense_permissions_from_migration(): void
    {
        $finance = User::factory()->create();
        $role = Role::create([
            'org_id' => $finance->org_id,
            'code' => 'finance',
            'name' => 'Finance',
            'is_system' => true,
        ]);
        $finance->roles()->attach($role->id);

        (require database_path('migrations/2026_07_28_000004_backfill_expense_permissions.php'))->up();

        $this->actingAsOrgUser($finance)->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Finance/Expenses'));
    }

    public function test_finance_user_can_create_draft_expense_without_supplier(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.create']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), $this->expensePayload([
                'supplier_id' => null,
            ]))->assertRedirect();

        $expense = Expense::firstOrFail();
        $this->assertSame('000001', $expense->expense_no);
        $this->assertSame('draft', $expense->status);
        $this->assertNull($expense->supplier_id);
        $this->assertTrue(AuditLog::where('action', 'expense.create')->where('entity_id', $expense->id)->exists());
    }

    public function test_supplier_id_is_uuid_but_not_foreign_key_for_mvp(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.create']);
        $supplierId = (string) Str::orderedUuid();

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.store'), $this->expensePayload([
                'supplier_id' => $supplierId,
            ]))->assertRedirect();

        $this->assertSame($supplierId, Expense::firstOrFail()->supplier_id);
    }

    public function test_finance_user_can_update_only_draft_expense(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.update']);
        $expense = $this->expenseFor($finance);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('expenses.update', $expense), $this->expensePayload([
                'title' => 'Updated hosting bill',
            ]))->assertRedirect();

        $this->assertSame('Updated hosting bill', $expense->refresh()->title);

        $expense->update(['status' => 'approved']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('expenses.update', $expense), $this->expensePayload([
                'title' => 'Should not save',
            ]))->assertStatus(422);

        $this->assertSame('Updated hosting bill', $expense->refresh()->title);
    }

    public function test_finance_user_can_approve_draft_expense(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.approve']);
        $expense = $this->expenseFor($finance);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.approve', $expense))->assertRedirect();

        $expense->refresh();
        $this->assertSame('approved', $expense->status);
        $this->assertSame($finance->id, $expense->approved_by);
        $this->assertNotNull($expense->approved_at);
        $this->assertTrue(AuditLog::where('action', 'expense.approve')->where('entity_id', $expense->id)->exists());
    }

    public function test_approved_expense_can_be_paid(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.pay']);
        $expense = $this->expenseFor($finance, ['status' => 'approved', 'note' => 'Hosting bill note']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.pay', $expense), [
                'paid_at' => '2026-07-28',
                'note' => 'Paid by transfer',
            ])->assertRedirect();

        $expense->refresh();
        $this->assertSame('paid', $expense->status);
        $this->assertSame('2026-07-28', $expense->paid_at->toDateString());
        $this->assertStringContainsString('Hosting bill note', $expense->note);
        $this->assertStringContainsString('[Paid '.now()->toDateString().']: Paid by transfer', $expense->note);
        $this->assertTrue(AuditLog::where('action', 'expense.pay')->where('entity_id', $expense->id)->exists());
    }

    public function test_draft_expense_cannot_be_paid(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.pay']);
        $expense = $this->expenseFor($finance);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.pay', $expense), [
                'paid_at' => '2026-07-28',
            ])->assertStatus(422);

        $this->assertSame('draft', $expense->refresh()->status);
        $this->assertNull($expense->paid_at);
    }

    public function test_draft_or_approved_expense_can_be_rejected_with_reason(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.reject']);
        $expense = $this->expenseFor($finance, ['status' => 'approved', 'note' => 'Hosting bill note']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.reject', $expense), [
                'note' => 'Receipt mismatch',
            ])->assertRedirect();

        $expense->refresh();
        $this->assertSame('rejected', $expense->status);
        $this->assertStringContainsString('Hosting bill note', $expense->note);
        $this->assertStringContainsString('[Rejected '.now()->toDateString().']: Receipt mismatch', $expense->note);
        $this->assertTrue(AuditLog::where('action', 'expense.reject')->where('entity_id', $expense->id)->exists());
    }

    public function test_reject_requires_reason(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.reject']);
        $expense = $this->expenseFor($finance);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.reject', $expense), [
                'note' => '',
            ])->assertSessionHasErrors('note');

        $this->assertSame('draft', $expense->refresh()->status);
    }

    public function test_expense_list_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.view']);
        $visible = $this->expenseFor($finance, ['title' => 'Visible expense']);
        $hidden = $this->expenseFor($other, ['expense_no' => '000099', 'title' => 'Hidden expense']);

        $this->actingAsOrgUser($finance)->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Expenses')
                ->has('expenses', 1)
                ->where('expenses.0.id', $visible->id)
            );

        $this->assertNotSame($visible->org_id, $hidden->org_id);
    }

    public function test_expense_approve_requires_permission(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['expenses.view']);
        $expense = $this->expenseFor($finance);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('expenses.approve', $expense))->assertForbidden();
    }

    private function expenseFor(User $user, array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'org_id' => $user->org_id,
            'expense_no' => '000001',
            'category' => 'hosting',
            'title' => 'Hosting bill',
            'amount' => '1200.00',
            'expense_date' => '2026-07-28',
            'status' => 'draft',
            'created_by' => $user->id,
        ], $overrides));
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
            'receipt_file_id' => null,
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
