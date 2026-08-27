<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase11GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_posting_is_balanced_idempotent_and_reversible(): void
    {
        $user = User::factory()->create();
        $service = app(JournalPostingService::class);
        $sourceId = (string) Str::uuid();
        $entry = $service->post($user->org_id, $user->id, 'test_source', $sourceId, 'issued', '2026-08-27', 'Test journal', [
            ['account_code' => '1100', 'debit' => '100.00'],
            ['account_code' => '3100', 'credit' => '100.00'],
        ]);
        $same = $service->post($user->org_id, $user->id, 'test_source', $sourceId, 'issued', '2026-08-27', 'Test journal', [
            ['account_code' => '1100', 'debit' => '100.00'],
            ['account_code' => '3100', 'credit' => '100.00'],
        ]);

        $this->assertSame($entry->id, $same->id);
        $this->assertSame(1, JournalEntry::count());
        $this->assertEquals(100.00, $entry->lines->sum('debit'));
        $this->assertEquals(100.00, $entry->lines->sum('credit'));

        $reversal = $service->reverse($entry, $user->id, '2026-08-27', 'Correction', 'voided');
        $this->assertSame('reversed', $entry->fresh()->status);
        $this->assertSame($entry->id, $reversal->reversal_of_id);
        $this->assertEquals(100.00, $reversal->lines->sum('debit'));
        $this->assertEquals(100.00, $reversal->lines->sum('credit'));
    }

    public function test_unbalanced_and_closed_period_postings_are_rejected(): void
    {
        $user = User::factory()->create();
        $service = app(JournalPostingService::class);

        try {
            $service->post($user->org_id, $user->id, 'test_source', (string) Str::uuid(), 'unbalanced', '2026-08-27', 'Unbalanced', [
                ['account_code' => '1100', 'debit' => '100.00'],
                ['account_code' => '3100', 'credit' => '99.00'],
            ]);
            $this->fail('Unbalanced journal was posted.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        AccountingPeriod::create([
            'org_id' => $user->org_id,
            'name' => 'Closed FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'closed',
        ]);
        try {
            $service->post($user->org_id, $user->id, 'test_source', (string) Str::uuid(), 'closed', '2026-08-27', 'Closed period', [
                ['account_code' => '1100', 'debit' => '100.00'],
                ['account_code' => '3100', 'credit' => '100.00'],
            ]);
            $this->fail('Closed period accepted a posting.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_accounting_pages_are_permission_and_organization_scoped(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'finance', ['accounting.chart_accounts.view', 'accounting.periods.view', 'accounting.journals.view', 'accounting.reports.view']);

        $this->actingAsOrgUser($user)->get(route('accounting.chart-of-accounts.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Accounting/ChartOfAccounts'));
        $this->actingAsOrgUser($user)->get(route('accounting.periods.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Accounting/Periods'));
        $this->actingAsOrgUser($user)->get(route('accounting.journals.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Accounting/Journals'));
        $this->actingAsOrgUser($user)->get(route('accounting.reports.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Accounting/Reports'));
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
