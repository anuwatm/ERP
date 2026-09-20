<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeShift;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\PayrollTaxPolicy;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SocialSecurityPolicy;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase233NonUserPayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_calculates_correctly_for_non_user_employee_with_ot_and_late(): void
    {
        $owner = User::factory()->create();

        PayrollTaxPolicy::create([
            'org_id' => $owner->org_id,
            'name' => 'Tax 2026',
            'effective_from' => '2026-01-01',
            'employment_expense_rate' => 50,
            'employment_expense_cap' => 100000,
            'brackets_json' => [['up_to' => 150000, 'rate' => 0], ['up_to' => null, 'rate' => 10]],
        ]);

        SocialSecurityPolicy::create([
            'org_id' => $owner->org_id,
            'name' => 'SS 2026',
            'effective_from' => '2026-01-01',
            'employee_rate' => 5,
            'employer_rate' => 5,
            'wage_ceiling' => 17500,
        ]);

        // Employee without user account
        $employee = Employee::create([
            'org_id' => $owner->org_id,
            'user_id' => null,
            'employee_code' => 'FACTORY-001',
            'first_name' => 'Prasert',
            'last_name' => 'Factoryman',
            'employment_type' => 'daily',
            'employment_status' => 'active',
            'email' => 'prasert@factory.local',
        ]);

        // Payroll profile: daily rate 500 THB, hourly rate 62.50 THB
        $profile = EmployeePayrollProfile::create([
            'org_id' => $owner->org_id,
            'employee_id' => $employee->id,
            'user_id' => null,
            'wage_type' => 'daily',
            'daily_rate' => 500,
            'hourly_rate' => 62.50,
            'monthly_salary' => 15000,
            'fixed_allowance' => 1000,
            'fixed_deduction' => 0,
            'annual_tax_allowance' => 60000,
            'social_security_enabled' => true,
            'payment_method' => 'cash',
            'status' => 'active',
        ]);

        // 2 attendance records in the period:
        // Day 1: worked 8 hrs, 120 mins OT (2 hrs OT * 62.5 * 1.5 = 187.50 THB)
        Attendance::create([
            'org_id' => $owner->org_id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-01',
            'clock_in_at' => '2026-10-01 08:00:00',
            'clock_out_at' => '2026-10-01 19:00:00',
            'worked_minutes' => 480,
            'ot_normal_minutes' => 120,
            'late_minutes' => 0,
            'is_absent' => false,
        ]);

        // Day 2: worked 8 hrs, 60 mins late (1 hr late * 62.5 = 62.50 THB deduction)
        Attendance::create([
            'org_id' => $owner->org_id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-02',
            'clock_in_at' => '2026-10-02 09:00:00',
            'clock_out_at' => '2026-10-02 17:00:00',
            'worked_minutes' => 480,
            'ot_normal_minutes' => 0,
            'late_minutes' => 60,
            'is_absent' => false,
        ]);

        $run = PayrollRun::create([
            'org_id' => $owner->org_id,
            'run_no' => 'RUN-2026-10',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'payment_date' => '2026-10-31',
            'status' => 'draft',
            'payroll_tax_policy_id' => PayrollTaxPolicy::where('org_id', $owner->org_id)->value('id'),
            'social_security_policy_id' => SocialSecurityPolicy::where('org_id', $owner->org_id)->value('id'),
        ]);

        $service = app(PayrollService::class);
        $calculatedRun = $service->calculate($run);

        $item = PayrollItem::where('payroll_run_id', $run->id)->firstOrFail();

        $this->assertSame($employee->id, $item->employee_id);
        $this->assertNull($item->user_id);
        // Worked 2 days @ 500 = 1000
        $this->assertSame(1000.0, $item->salary_amount);
        $this->assertSame(1000.0, $item->allowance_amount);
        $this->assertSame(187.5, $item->ot_amount);
        $this->assertSame(62.5, $item->late_deduction_amount);

        // Gross = 1000 + 1000 + 187.50 - 62.50 = 2125
        // Social Security 5% of 2125 = 106.25
        $this->assertSame(106.25, $item->employee_social_security_amount);
        $this->assertGreaterThan(0, $item->net_pay_amount);
    }

    public function test_payslip_pdf_renders_for_non_user_employee(): void
    {
        $owner = User::factory()->create();
        $this->grant($owner, ['payroll.view', 'payroll.manage']);

        $employee = Employee::create([
            'org_id' => $owner->org_id,
            'employee_code' => 'EMP-PDF',
            'first_name' => 'Wichai',
            'last_name' => 'Nonuser',
            'employment_type' => 'monthly',
            'employment_status' => 'active',
        ]);

        $policies = $this->createPolicies($owner->org_id);

        $profile = EmployeePayrollProfile::create([
            'org_id' => $owner->org_id,
            'employee_id' => $employee->id,
            'user_id' => null,
            'wage_type' => 'monthly',
            'monthly_salary' => 20000,
            'fixed_allowance' => 1500,
            'status' => 'active',
        ]);

        $run = PayrollRun::create([
            'org_id' => $owner->org_id,
            'run_no' => 'RUN-PDF',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'payment_date' => '2026-10-31',
            'status' => 'draft',
            'payroll_tax_policy_id' => $policies['payroll_tax_policy_id'],
            'social_security_policy_id' => $policies['social_security_policy_id'],
        ]);

        $item = PayrollItem::create([
            'org_id' => $owner->org_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_payroll_profile_id' => $profile->id,
            'user_id' => null,
            'salary_amount' => 20000,
            'allowance_amount' => 1500,
            'ot_amount' => 500,
            'late_deduction_amount' => 100,
            'employee_social_security_amount' => 750,
            'employer_social_security_amount' => 750,
            'withholding_tax_amount' => 200,
            'net_pay_amount' => 20950,
            'calculation_snapshot' => ['salary' => 20000],
        ]);

        $response = $this->withoutMiddleware()->actingAsOrgUser($owner)->get(route('payroll.payslips.pdf', $item));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_email_payslip_sends_email_to_employee_personal_address(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $this->grant($owner, ['payroll.manage']);

        $employee = Employee::create([
            'org_id' => $owner->org_id,
            'employee_code' => 'EMP-MAIL',
            'first_name' => 'Siriporn',
            'email' => 'siriporn@company.local',
            'employment_type' => 'monthly',
            'employment_status' => 'active',
        ]);

        $profile = EmployeePayrollProfile::create([
            'org_id' => $owner->org_id,
            'employee_id' => $employee->id,
            'user_id' => null,
            'wage_type' => 'monthly',
            'monthly_salary' => 25000,
            'status' => 'active',
        ]);

        $policies = $this->createPolicies($owner->org_id);

        $run = PayrollRun::create([
            'org_id' => $owner->org_id,
            'run_no' => 'RUN-MAIL',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'payment_date' => '2026-10-31',
            'status' => 'approved',
            'payroll_tax_policy_id' => $policies['payroll_tax_policy_id'],
            'social_security_policy_id' => $policies['social_security_policy_id'],
        ]);

        $item = PayrollItem::create([
            'org_id' => $owner->org_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'employee_payroll_profile_id' => $profile->id,
            'salary_amount' => 25000,
            'allowance_amount' => 0,
            'net_pay_amount' => 23750,
            'calculation_snapshot' => ['salary' => 25000],
        ]);

        $this->withoutMiddleware()->actingAsOrgUser($owner)->post(route('payroll.payslips.email', $item))->assertRedirect();

        Mail::assertSentCount(1);
    }

    public function test_payroll_calculates_with_custom_shift_ot_rates_and_3x_holiday(): void
    {
        $owner = User::factory()->create();
        $policies = $this->createPolicies($owner->org_id);

        $shift = EmployeeShift::create([
            'org_id' => $owner->org_id,
            'code' => 'CUSTOM-OT',
            'name' => 'Custom OT Shift',
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'break_minutes' => 60,
            'work_minutes_per_day' => 480,
            'ot_rate_normal' => 1.75,
            'ot_rate_holiday' => 2.50,
            'ot_rate_special_holiday' => 3.00,
        ]);

        $employee = Employee::create([
            'org_id' => $owner->org_id,
            'employee_code' => 'EMP-OT-RATE',
            'first_name' => 'Kitti',
            'employment_type' => 'daily',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        $profile = EmployeePayrollProfile::create([
            'org_id' => $owner->org_id,
            'employee_id' => $employee->id,
            'wage_type' => 'hourly',
            'hourly_rate' => 100.00,
            'monthly_salary' => 0,
            'fixed_allowance' => 0,
            'fixed_deduction' => 0,
            'annual_tax_allowance' => 60000,
            'social_security_enabled' => false,
            'payment_method' => 'cash',
            'status' => 'active',
        ]);

        // Attendance 1: normal workday, 120 mins normal OT (2 hrs * 100 * 1.75 = 350 THB)
        Attendance::create([
            'org_id' => $owner->org_id,
            'employee_id' => $employee->id,
            'employee_shift_id' => $shift->id,
            'work_date' => '2026-10-01',
            'clock_in_at' => '2026-10-01 08:00:00',
            'clock_out_at' => '2026-10-01 19:00:00',
            'worked_minutes' => 480,
            'ot_normal_minutes' => 120,
            'ot_holiday_minutes' => 0,
            'ot_special_minutes' => 0,
            'is_absent' => false,
        ]);

        // Attendance 2: public holiday, 480 mins regular holiday work (8 hrs * 100 * 2.50 = 2000 THB)
        // plus 120 mins special holiday OT (2 hrs * 100 * 3.00 = 600 THB)
        Attendance::create([
            'org_id' => $owner->org_id,
            'employee_id' => $employee->id,
            'employee_shift_id' => $shift->id,
            'work_date' => '2026-10-23',
            'clock_in_at' => '2026-10-23 08:00:00',
            'clock_out_at' => '2026-10-23 19:00:00',
            'worked_minutes' => 480,
            'ot_normal_minutes' => 0,
            'ot_holiday_minutes' => 480,
            'ot_special_minutes' => 120,
            'is_absent' => false,
        ]);

        $run = PayrollRun::create([
            'org_id' => $owner->org_id,
            'run_no' => 'RUN-CUSTOM-OT',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'payment_date' => '2026-10-31',
            'status' => 'draft',
            'payroll_tax_policy_id' => $policies['payroll_tax_policy_id'],
            'social_security_policy_id' => $policies['social_security_policy_id'],
        ]);

        $service = app(PayrollService::class);
        $service->calculate($run);

        $item = PayrollItem::where('payroll_run_id', $run->id)->firstOrFail();

        // OT amount = 350 + 2000 + 600 = 2950.00 THB
        $this->assertSame(2950.0, $item->ot_amount);
        $this->assertSame(120, $item->calculation_snapshot['ot_special_minutes']);
        $this->assertSame(480, $item->calculation_snapshot['ot_holiday_minutes']);
        $this->assertSame(120, $item->calculation_snapshot['ot_normal_minutes']);
    }

    private function createPolicies(string $orgId): array
    {
        $tax = PayrollTaxPolicy::firstOrCreate(['org_id' => $orgId, 'name' => 'Default Tax'], [
            'effective_from' => '2026-01-01',
            'employment_expense_rate' => 50,
            'employment_expense_cap' => 100000,
            'brackets_json' => [['up_to' => 150000, 'rate' => 0], ['up_to' => null, 'rate' => 10]],
        ]);

        $ss = SocialSecurityPolicy::firstOrCreate(['org_id' => $orgId, 'name' => 'Default SS'], [
            'effective_from' => '2026-01-01',
            'employee_rate' => 5,
            'employer_rate' => 5,
            'wage_ceiling' => 17500,
        ]);

        return ['payroll_tax_policy_id' => $tax->id, 'social_security_policy_id' => $ss->id];
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'payroll_nonuser_tester'], ['name' => 'Payroll nonuser tester', 'is_system' => true]);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'payroll', 'action' => 'manage']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
