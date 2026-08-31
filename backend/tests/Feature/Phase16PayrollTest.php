<?php

namespace Tests\Feature;

use App\Models\EmployeePayrollProfile;
use App\Models\JournalEntry;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase16PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_payroll_run_calculates_posts_gl_pays_and_exports_workpapers(): void
    {
        $finance = User::factory()->create();
        $employee = User::factory()->create(['org_id' => $finance->org_id, 'branch_id' => $finance->branch_id, 'division_id' => $finance->division_id, 'department_id' => $finance->department_id, 'person_id' => '1234567890123']);
        $this->grant($finance, ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.pay', 'payroll.export']);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.profiles.store'), ['user_id' => $employee->id, 'monthly_salary' => 30000, 'fixed_allowance' => 1000, 'fixed_deduction' => 250, 'annual_tax_allowance' => 60000, 'tax_id' => '1234567890123', 'social_security_enabled' => true, 'payment_method' => 'bank_transfer', 'status' => 'active'])->assertRedirect();
        $profile = EmployeePayrollProfile::where('user_id', $employee->id)->firstOrFail();
        $this->assertTrue($profile->social_security_enabled);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.store'), ['period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'payment_date' => '2026-08-31'])->assertRedirect();
        $run = PayrollRun::firstOrFail();
        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.calculate', $run))->assertRedirect();
        $run->refresh();
        $this->assertSame('calculated', $run->status);
        $item = PayrollItem::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertEquals(875, $item->employee_social_security_amount);
        $this->assertGreaterThan(0, (float) $item->withholding_tax_amount);

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.approve', $run))->assertRedirect();
        $this->assertSame('approved', $run->fresh()->status);
        $approval = JournalEntry::where('source_type', 'payroll_run')->where('source_id', $run->id)->where('posting_event', 'approved')->firstOrFail();
        $this->assertEquals(31000, $approval->lines()->whereHas('account', fn ($query) => $query->where('code', '5500'))->value('debit'));
        $this->assertEquals(875, $approval->lines()->whereHas('account', fn ($query) => $query->where('code', '5510'))->value('debit'));

        $this->actingAsOrgUser($finance)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.pay', $run))->assertRedirect();
        $this->assertSame('paid', $run->fresh()->status);
        $this->assertDatabaseHas('journal_entries', ['source_type' => 'payroll_run', 'source_id' => $run->id, 'posting_event' => 'paid']);
        $this->actingAsOrgUser($finance)->get(route('payroll.runs.export', [$run, 'pnd1']))->assertOk()->assertDownload("pnd1-{$run->run_no}.csv");
        $this->actingAsOrgUser($finance)->get(route('payroll.runs.export', [$run, 'social-security']))->assertOk()->assertDownload("social-security-{$run->run_no}.csv");
    }

    public function test_employee_can_only_download_own_payslip(): void
    {
        $owner = User::factory()->create();
        $employee = User::factory()->create(['org_id' => $owner->org_id, 'branch_id' => $owner->branch_id, 'division_id' => $owner->division_id, 'department_id' => $owner->department_id]);
        $other = User::factory()->create(['org_id' => $owner->org_id, 'branch_id' => $owner->branch_id, 'division_id' => $owner->division_id, 'department_id' => $owner->department_id]);
        $this->grant($owner, ['payroll.manage', 'payroll.view']);
        EmployeePayrollProfile::create(['org_id' => $owner->org_id, 'user_id' => $employee->id, 'monthly_salary' => 10000, 'annual_tax_allowance' => 60000, 'social_security_enabled' => false, 'payment_method' => 'cash', 'status' => 'active']);
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.store'), ['period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'payment_date' => '2026-08-31']);
        $run = PayrollRun::firstOrFail();
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.calculate', $run));
        $item = PayrollItem::firstOrFail();
        $this->actingAsOrgUser($employee)->get(route('payroll.payslips.pdf', $item))->assertOk();
        $this->actingAsOrgUser($other)->get(route('payroll.payslips.pdf', $item))->assertForbidden();
    }

    public function test_new_effective_dated_policy_is_locked_to_new_run(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['payroll.manage']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.policies.store'), [
            'effective_from' => '2026-09-01', 'employment_expense_rate' => 40, 'employment_expense_cap' => 90000,
            'brackets_json' => json_encode([['up_to' => null, 'rate' => 1]]), 'employee_rate' => 4, 'employer_rate' => 4, 'wage_ceiling' => 20000,
            'source_url' => 'https://www.rd.go.th/26218.html',
        ])->assertRedirect();
        EmployeePayrollProfile::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'monthly_salary' => 10000, 'annual_tax_allowance' => 60000, 'social_security_enabled' => true, 'payment_method' => 'cash', 'status' => 'active']);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.store'), ['period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'payment_date' => '2026-09-30'])->assertRedirect();
        $run = PayrollRun::firstOrFail();
        $this->assertEquals('40.00', $run->taxPolicy->employment_expense_rate);
        $this->assertEquals('4.00', $run->socialSecurityPolicy->employee_rate);
    }

    public function test_payroll_run_enforces_org_isolation(): void
    {
        $org1User = User::factory()->create();
        $org2User = User::factory()->create();
        $this->grant($org1User, ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.pay', 'payroll.export']);
        $this->grant($org2User, ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.pay', 'payroll.export']);

        EmployeePayrollProfile::create(['org_id' => $org1User->org_id, 'user_id' => $org1User->id, 'monthly_salary' => 20000, 'annual_tax_allowance' => 60000, 'social_security_enabled' => true, 'payment_method' => 'cash', 'status' => 'active']);
        $this->actingAsOrgUser($org1User)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.store'), ['period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'payment_date' => '2026-08-31']);
        $run = PayrollRun::firstOrFail();

        // Org 2 user cannot calculate, approve, pay or export Org 1 run
        $this->actingAsOrgUser($org2User)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.calculate', $run))->assertNotFound();
        $this->actingAsOrgUser($org2User)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.approve', $run))->assertNotFound();
        $this->actingAsOrgUser($org2User)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.pay', $run))->assertNotFound();
        $this->actingAsOrgUser($org2User)->get(route('payroll.runs.export', [$run, 'pnd1']))->assertNotFound();
    }

    public function test_cannot_approve_draft_or_pay_unapproved_run(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.pay']);
        EmployeePayrollProfile::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'monthly_salary' => 20000, 'annual_tax_allowance' => 60000, 'social_security_enabled' => true, 'payment_method' => 'cash', 'status' => 'active']);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.store'), ['period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'payment_date' => '2026-08-31']);
        $run = PayrollRun::firstOrFail();

        // Draft run cannot be approved
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.approve', $run))->assertSessionHasErrors('payroll');

        // Draft run cannot be paid
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.pay', $run))->assertSessionHasErrors('payroll');
    }

    public function test_wage_ceiling_and_allowance_deduction_boundaries(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['payroll.view', 'payroll.manage']);
        // High salary (100,000) should cap social security at 17,500 * 5% = 875
        $profile = EmployeePayrollProfile::create([
            'org_id' => $user->org_id,
            'user_id' => $user->id,
            'monthly_salary' => 100000,
            'fixed_allowance' => 5000,
            'fixed_deduction' => 1000,
            'annual_tax_allowance' => 60000,
            'social_security_enabled' => true,
            'payment_method' => 'bank_transfer',
            'status' => 'active',
        ]);

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.store'), ['period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'payment_date' => '2026-08-31']);
        $run = PayrollRun::firstOrFail();
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('payroll.runs.calculate', $run));

        $item = PayrollItem::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertEquals(875.00, (float) $item->employee_social_security_amount);
        $this->assertEquals(875.00, (float) $item->employer_social_security_amount);
        $this->assertEquals(5000.00, (float) $item->allowance_amount);
        $this->assertEquals(1000.00, (float) $item->other_deduction_amount);
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'payroll_finance'], ['name' => 'Payroll Finance', 'is_system' => true]);
        foreach ($codes as $code) {
            $parts = explode('.', $code);
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'payroll', 'action' => end($parts)]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
