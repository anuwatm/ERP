<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\EmployeeShift;
use App\Models\EmployeeWorkProfile;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase19HrAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_in_respects_opt_in_consent_and_hashes_ip(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.self.view']);
        Setting::create(['org_id' => $user->org_id, 'key' => 'hr.attendance_privacy', 'value_json' => ['capture_ip' => true, 'capture_gps' => false, 'require_employee_consent' => true, 'retention_days' => 365]]);

        $this->actingAsOrgUser($user)->post(route('hr.clock-in'), ['source' => 'web'])->assertSessionHasErrors('privacy_consent');
        $this->actingAsOrgUser($user)->post(route('hr.clock-in'), ['source' => 'web', 'privacy_consent' => true])->assertSessionHas('success');

        $attendance = Attendance::firstOrFail();
        $this->assertSame(hash('sha256', '127.0.0.1'), $attendance->clock_in_ip_hash);
        $this->assertNull($attendance->clock_in_latitude);
        $this->actingAsOrgUser($user)->post(route('hr.clock-out'), ['source' => 'web', 'privacy_consent' => true])->assertSessionHas('success');
    }

    public function test_leave_submit_deducts_balance_and_cancel_restores_it(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.self.view']);
        $type = LeaveType::create(['org_id' => $user->org_id, 'code' => 'ANNUAL', 'name' => 'Annual', 'is_paid' => true, 'annual_entitlement_days' => 10, 'carry_over_limit_days' => 0]);
        $balance = LeaveBalance::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'leave_type_id' => $type->id, 'leave_year' => 2026, 'opening_days' => 0, 'accrued_days' => 5, 'carry_over_days' => 0, 'used_days' => 0]);

        $this->actingAsOrgUser($user)->post(route('hr.leave-requests.store'), ['leave_type_id' => $type->id, 'starts_on' => '2026-09-07', 'ends_on' => '2026-09-08'])->assertSessionHas('success');
        $leave = LeaveRequest::firstOrFail();
        $this->actingAsOrgUser($user)->post(route('hr.leave-requests.submit', $leave))->assertSessionHas('success');
        $this->assertSame('submitted', $leave->fresh()->status);
        $this->assertEquals(2, $balance->fresh()->used_days);
        $this->actingAsOrgUser($user)->post(route('hr.leave-requests.cancel', $leave))->assertSessionHas('success');
        $this->assertEquals(0, $balance->fresh()->used_days);
    }

    public function test_summary_lock_and_reversal_do_not_auto_post_payroll(): void
    {
        $manager = User::factory()->create();
        $employee = User::factory()->create(['org_id' => $manager->org_id, 'branch_id' => $manager->branch_id, 'division_id' => $manager->division_id, 'department_id' => $manager->department_id]);
        $this->grant($manager, ['hr.self.view', 'hr.summary.manage']);
        $shift = EmployeeShift::create(['org_id' => $manager->org_id, 'code' => 'DAY', 'name' => 'Day', 'starts_at' => '09:00', 'ends_at' => '18:00', 'break_minutes' => 60, 'work_minutes_per_day' => 480]);
        EmployeeWorkProfile::create(['org_id' => $manager->org_id, 'user_id' => $employee->id, 'employee_shift_id' => $shift->id, 'employment_type' => 'employee']);
        Attendance::create(['org_id' => $manager->org_id, 'user_id' => $employee->id, 'employee_shift_id' => $shift->id, 'work_date' => '2026-09-07', 'clock_in_at' => '2026-09-07 09:00:00', 'clock_out_at' => '2026-09-07 18:00:00']);

        $session = ['auth.password_confirmed_at' => time()];
        $this->actingAsOrgUser($manager)->withSession($session)->post(route('hr.summaries.store'), ['user_id' => $employee->id, 'period_start' => '2026-09-07', 'period_end' => '2026-09-07', 'cutoff_date' => '2026-09-07'])->assertSessionHas('success');
        $summary = AttendanceSummary::firstOrFail();
        $this->assertSame(540, $summary->worked_minutes);
        $this->assertSame(480, $summary->scheduled_minutes);
        $this->assertSame(60, $summary->overtime_minutes);
        $this->actingAsOrgUser($manager)->withSession($session)->post(route('hr.summaries.lock', $summary))->assertSessionHas('success');
        $this->assertSame('locked', $summary->fresh()->status);
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->actingAsOrgUser($manager)->withSession($session)->post(route('hr.summaries.reverse', $summary))->assertSessionHas('success');
        $this->assertSame('reversed', $summary->fresh()->status);
        $this->assertDatabaseHas('attendance_summaries', ['reversal_of_id' => $summary->id, 'status' => 'draft']);
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'hr_test'], ['name' => 'HR test', 'is_system' => true]);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'hr', 'action' => 'view']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
