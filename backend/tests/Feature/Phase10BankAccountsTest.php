<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase10BankAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_finance_user_can_create_masked_bank_account(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['treasury.accounts.view', 'treasury.accounts.manage']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-accounts.store'), $this->payload())
            ->assertRedirect();

        $account = BankAccount::firstOrFail();
        $this->assertSame('1234567890', $account->account_number);
        $this->assertNotSame('1234567890', $account->getRawOriginal('account_number'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'bank_account.create', 'entity_id' => $account->id]);

        $this->actingAsOrgUser($finance)->get(route('bank-accounts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/BankAccounts')
                ->where('accounts.0.account_number_masked', '******7890')
                ->missing('accounts.0.account_number')
            );
    }

    public function test_bank_account_number_is_unique_per_organization_and_cross_org_update_is_hidden(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['treasury.accounts.view', 'treasury.accounts.manage']);
        $this->attachRole($other, 'finance', ['treasury.accounts.manage']);
        $account = BankAccount::create(array_merge($this->payload(), [
            'org_id' => $finance->org_id,
            'account_number_hash' => hash('sha256', '1234567890'),
            'created_by' => $finance->id,
        ]));

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('bank-accounts.store'), $this->payload())
            ->assertStatus(422);

        $this->actingAsOrgUser($other)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('bank-accounts.update', $account), array_merge($this->payload(), ['account_number' => '99990000']))
            ->assertNotFound();
    }

    public function test_account_can_be_disabled_without_reentering_number_and_is_audited(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['treasury.accounts.manage']);
        $account = BankAccount::create(array_merge($this->payload(), [
            'org_id' => $finance->org_id,
            'account_number_hash' => hash('sha256', '1234567890'),
            'created_by' => $finance->id,
        ]));

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('bank-accounts.update', $account), array_merge($this->payload(['account_number' => null, 'status' => 'inactive'])))
            ->assertRedirect();

        $this->assertSame('inactive', $account->fresh()->status);
        $this->assertSame('1234567890', $account->fresh()->account_number);
        $this->assertTrue(AuditLog::where('action', 'bank_account.update')->where('entity_id', $account->id)->exists());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'branch_id' => null,
            'bank_name' => 'Example Bank',
            'bank_code' => 'EXB',
            'branch_name' => 'Head Office',
            'account_name' => 'Operating Account',
            'account_number' => '1234567890',
            'account_type' => 'savings',
            'currency' => 'THB',
            'is_cash_account' => false,
            'status' => 'active',
            'opening_balance' => '1500.00',
            'opening_balance_date' => '2026-08-26',
        ], $overrides);
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
