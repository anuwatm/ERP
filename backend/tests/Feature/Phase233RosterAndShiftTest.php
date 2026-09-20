<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\EmployeeShiftSchedule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase233RosterAndShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_model_supports_ot_thresholds_and_grace_periods(): void
    {
        $owner = User::factory()->create();
        $shift = EmployeeShift::create([
            'org_id' => $owner->org_id,
            'code' => 'FACTORY-M',
            'name' => 'Morning Shift',
            'starts_at' => '08:00',
            'ends_at' => '17:00',
            'break_minutes' => 60,
            'work_minutes_per_day' => 480,
            'ot_rate_normal' => 1.5,
            'ot_rate_holiday' => 2.0,
            'grace_period_minutes' => 15,
            'overtime_starts_after_minutes' => 30,
        ]);

        $this->assertSame(1.5, $shift->fresh()->ot_rate_normal);
        $this->assertSame(2.0, $shift->fresh()->ot_rate_holiday);
        $this->assertSame(15, $shift->fresh()->grace_period_minutes);
        $this->assertSame(30, $shift->fresh()->overtime_starts_after_minutes);
    }

    public function test_single_schedule_can_be_assigned_and_updated_per_employee(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.shifts.manage']);

        $shift = EmployeeShift::create([
            'org_id' => $actor->org_id,
            'code' => 'DAY',
            'name' => 'Day Shift',
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'break_minutes' => 60,
            'work_minutes_per_day' => 480,
        ]);

        $employee = Employee::create([
            'org_id' => $actor->org_id,
            'employee_code' => 'EMP-ROSTER-1',
            'first_name' => 'Kittisak',
            'employment_type' => 'daily',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        $this->withoutMiddleware()->actingAsOrgUser($actor)->post(route('hr.roster.schedules.store'), [
            'employee_id' => $employee->id,
            'schedule_date' => '2026-10-01',
            'employee_shift_id' => $shift->id,
            'is_day_off' => false,
            'note' => 'Regular morning assignment',
        ])->assertRedirect();

        $schedule = EmployeeShiftSchedule::where('org_id', $actor->org_id)
            ->where('employee_id', $employee->id)
            ->whereDate('schedule_date', '2026-10-01')
            ->firstOrFail();

        $this->assertSame($shift->id, $schedule->employee_shift_id);
        $this->assertFalse($schedule->is_day_off);
        $this->assertSame('Regular morning assignment', $schedule->note);

        // Update to day off
        $this->withoutMiddleware()->actingAsOrgUser($actor)->post(route('hr.roster.schedules.store'), [
            'employee_id' => $employee->id,
            'schedule_date' => '2026-10-01',
            'is_day_off' => true,
        ])->assertRedirect();

        $this->assertTrue($schedule->fresh()->is_day_off);
        $this->assertNull($schedule->fresh()->employee_shift_id);
    }

    public function test_batch_roster_assignment_applies_shifts_across_date_range(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.shifts.manage']);

        $branch = Branch::create(['org_id' => $actor->org_id, 'code' => 'BKK', 'name' => 'Bangkok HQ', 'status' => 'active']);
        $division = Division::create(['org_id' => $actor->org_id, 'branch_id' => $branch->id, 'code' => 'OPS', 'name' => 'Operations', 'status' => 'active']);
        $department = Department::create(['org_id' => $actor->org_id, 'branch_id' => $branch->id, 'division_id' => $division->id, 'code' => 'PROD', 'name' => 'Production', 'status' => 'active']);

        $shift = EmployeeShift::create([
            'org_id' => $actor->org_id,
            'code' => 'SHIFT-A',
            'name' => 'Shift A',
            'starts_at' => '07:00',
            'ends_at' => '15:00',
            'break_minutes' => 60,
            'work_minutes_per_day' => 480,
        ]);

        $emp1 = Employee::create([
            'org_id' => $actor->org_id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'employee_code' => 'PROD-01',
            'first_name' => 'Operator 1',
            'employment_type' => 'daily',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        $emp2 = Employee::create([
            'org_id' => $actor->org_id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'employee_code' => 'PROD-02',
            'first_name' => 'Operator 2',
            'employment_type' => 'daily',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        // Batch assign for Monday to Friday: 2026-10-05 (Mon) to 2026-10-09 (Fri) = 5 days * 2 emps = 10 schedules
        $this->withoutMiddleware()->actingAsOrgUser($actor)->post(route('hr.roster.batch.store'), [
            'department_id' => $department->id,
            'employee_shift_id' => $shift->id,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-09',
            'days_of_week' => [1, 2, 3, 4, 5],
            'note' => 'Production Batch Q4',
        ])->assertRedirect();

        $count = EmployeeShiftSchedule::where('org_id', $actor->org_id)
            ->whereIn('employee_id', [$emp1->id, $emp2->id])
            ->where('employee_shift_id', $shift->id)
            ->count();

        $this->assertSame(10, $count);
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'shift_roster_tester'], ['name' => 'Shift roster tester', 'is_system' => true]);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'hr', 'action' => 'manage']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
