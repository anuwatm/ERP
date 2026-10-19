<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\EmployeeShiftSchedule;
use App\Models\Holiday;
use App\Models\IntegrationApiKey;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExternalHrApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(5, 0));
    }

    private function credential(User $user, array $scopes, ?array $policy = ['hr_all_branches' => true]): string
    {
        $prefix = 'erp_'.bin2hex(random_bytes(12));
        $secret = $prefix.'.'.bin2hex(random_bytes(32));
        IntegrationApiKey::create(['org_id' => $user->org_id, 'created_by' => $user->id,
            'name' => 'HR fixture', 'prefix' => $prefix, 'secret_hash' => hash('sha256', $secret),
            'scopes' => $scopes, 'access_policy' => $policy, 'expires_at' => now()->addDays(30)]);

        return $secret;
    }

    private function employee(User $user, array $values = []): Employee
    {
        return Employee::create(array_merge(['org_id' => $user->org_id, 'employee_code' => bin2hex(random_bytes(6)),
            'first_name' => 'Private first', 'last_name' => 'Private last', 'employment_type' => 'monthly', 'employment_status' => 'active',
            'started_on' => '2026-10-01', 'probation_ends_on' => '2026-10-31', 'national_id' => 'PRIVATE_ID',
            'bank_account_number' => 'PRIVATE_BANK', 'email' => 'private@example.test', 'phone' => 'PRIVATE_PHONE'], $values));
    }

    public function test_directory_and_summaries_have_constant_query_budgets_as_employee_count_grows(): void
    {
        $user = User::factory()->create();
        $this->employee($user);
        $token = $this->credential($user, ['employees:read', 'summary:hr:read', 'summary:attendance:read', 'summary:leaves:read', 'summary:roster:read']);
        $measure = function (string $path) use ($token): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $this->withToken($token)->getJson('/api/v1/'.$path)->assertOk();

                return count(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
        };
        $paths = ['employees', 'summaries/hr', 'summaries/attendance', 'summaries/leaves', 'summaries/roster'];
        $baseline = [];
        foreach ($paths as $path) {
            $baseline[$path] = $measure($path);
            $this->assertLessThanOrEqual(20, $baseline[$path], $path.' exceeded query budget');
        }
        for ($i = 0; $i < 20; $i++) {
            $this->employee($user);
        }
        foreach ($paths as $path) {
            $this->assertSame($baseline[$path], $measure($path), $path.' queries grew per employee');
        }
    }

    public function test_directory_is_branch_scoped_supports_non_users_and_hides_private_fields_and_manager(): void
    {
        $user = User::factory()->create();
        $branch = Branch::create(['org_id' => $user->org_id, 'code' => 'REMOTE', 'name' => 'Remote', 'status' => 'active']);
        $manager = $this->employee($user, ['branch_id' => $branch->id]);
        $mine = $this->employee($user, ['branch_id' => $user->branch_id, 'manager_id' => $manager->id]);
        $foreign = $this->employee(User::factory()->create());
        $token = $this->credential($user, ['employees:read'], ['hr_branch_ids' => [$user->branch_id]]);
        $response = $this->withToken($token)->getJson('/api/v1/employees')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)->assertJsonPath('data.0.manager_id', null);
        foreach (['national_id', 'tax_id', 'bank_account_number', 'email', 'phone', 'date_of_birth', 'user_id', 'payment_method'] as $field) {
            $response->assertJsonMissingPath('data.0.'.$field);
        }
        $this->assertStringNotContainsString('PRIVATE_BANK', $response->getContent());
        $this->getJson('/api/v1/employees/'.$foreign->id)->assertNotFound();
        $this->getJson('/api/v1/employees/'.$manager->id)->assertNotFound();
        $this->getJson('/api/v1/employees/'.$mine->id)->assertOk()->assertJsonPath('data.started_on', '2026-10-01');
        $this->withToken($this->credential($user, ['employees:read'], null))->getJson('/api/v1/employees')->assertOk()->assertJsonCount(0, 'data');
        $this->withToken($this->credential($user, ['summary:hr:read']))->getJson('/api/v1/employees')->assertForbidden();
    }

    public function test_hr_summary_uses_employees_and_real_employment_dates_with_small_group_suppression(): void
    {
        $user = User::factory()->create();
        $this->employee($user);
        $this->employee($user, ['employment_status' => 'resigned', 'resigned_on' => '2026-10-10', 'started_on' => '2025-01-01']);
        $this->employee(User::factory()->create());
        $token = $this->credential($user, ['summary:hr:read']);
        $this->withToken($token)->getJson('/api/v1/summaries/hr?start_date=2026-10-01&end_date=2026-10-31')->assertOk()
            ->assertJsonPath('data.snapshot.total_employees', 2)->assertJsonPath('data.period.employees_started', 1)
            ->assertJsonPath('data.snapshot.active_employees', 1)
            ->assertJsonPath('data.period.employees_departed', 1)->assertJsonPath('data.snapshot.probation_ends_within_30_days', 1)
            ->assertJsonPath('data.snapshot.org_units.branch_id.groups.0.suppressed', true);
        $this->getJson('/api/v1/summaries/hr?start_date=2026-10-01')->assertUnprocessable();
        $this->getJson('/api/v1/summaries/hr?start_date=2025-01-01&end_date=2026-10-31')->assertUnprocessable();
    }

    public function test_attendance_reconciled_minutes_pending_and_non_user_overnight_records(): void
    {
        $user = User::factory()->create();
        $employee = $this->employee($user);
        Attendance::create(['org_id' => $user->org_id, 'employee_id' => $employee->id, 'work_date' => '2026-10-18',
            'clock_in_at' => '2026-10-18 15:00:00', 'clock_out_at' => '2026-10-18 23:00:00', 'worked_minutes' => 480,
            'late_minutes' => 5, 'ot_normal_minutes' => 60, 'is_reconciled' => true, 'correction_reason' => 'PRIVATE_REASON']);
        Attendance::create(['org_id' => $user->org_id, 'employee_id' => $employee->id, 'work_date' => '2026-10-19',
            'clock_in_at' => '2026-10-19 01:00:00', 'worked_minutes' => 999, 'is_reconciled' => false]);
        $token = $this->credential($user, ['summary:attendance:read', 'employees:attendance:read']);
        $this->withToken($token)->getJson('/api/v1/summaries/attendance')->assertOk()
            ->assertJsonPath('data.period.employee_workdays', 2)->assertJsonPath('data.period.distinct_employees', 1)
            ->assertJsonPath('data.period.worked_minutes', 480)->assertJsonPath('data.period.ot_normal_minutes', 60)
            ->assertJsonPath('data.period.confirmed_absent_workdays', 0)->assertJsonPath('data.period.pending_workdays', 1);
        $response = $this->getJson('/api/v1/employees/'.$employee->id.'/attendance')->assertOk()->assertJsonCount(2, 'data');
        $this->assertStringNotContainsString('PRIVATE_REASON', $response->getContent());
        $response->assertJsonMissingPath('data.0.clock_in_ip_hash')->assertJsonMissingPath('data.0.clock_in_latitude');
    }

    public function test_leave_overlap_excludes_weekends_holidays_and_non_user_coverage_is_explicit(): void
    {
        $user = User::factory()->create();
        $linked = $this->employee($user, ['user_id' => $user->id]);
        $nonUser = $this->employee($user);
        $type = LeaveType::create(['org_id' => $user->org_id, 'code' => 'PRIVATE', 'name' => 'PRIVATE_HEALTH']);
        LeaveRequest::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'leave_type_id' => $type->id,
            'starts_on' => '2026-09-28', 'ends_on' => '2026-10-06', 'total_days' => '7.00', 'status' => 'approved', 'reason' => 'PRIVATE_REASON']);
        Holiday::create(['org_id' => $user->org_id, 'holiday_date' => '2026-10-02', 'name' => 'Holiday']);
        LeaveBalance::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'leave_type_id' => $type->id,
            'leave_year' => 2026, 'opening_days' => '1.25', 'accrued_days' => '10.00', 'carry_over_days' => '0.50', 'used_days' => '7.00']);
        $token = $this->credential($user, ['summary:leaves:read', 'employees:leaves:read']);
        $response = $this->withToken($token)->getJson('/api/v1/summaries/leaves?start_date=2026-10-01&end_date=2026-10-06')->assertOk()
            ->assertJsonPath('data.period.approved_leave_employee_workdays', '3.00')
            ->assertJsonPath('data.coverage.unsupported_non_user_employees', 1);
        $this->assertStringNotContainsString('PRIVATE_HEALTH', $response->getContent());
        $this->getJson('/api/v1/employees/'.$nonUser->id.'/leave-balances')->assertStatus(409)->assertJsonPath('code', 'unsupported_employee_coverage');
        $this->getJson('/api/v1/employees/'.$linked->id.'/leave-balances')->assertOk()->assertJsonPath('data.0.remaining_days', '4.75');
        $response = $this->getJson('/api/v1/employees/'.$linked->id.'/leave-requests')->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonMissingPath('data.0.reason')->assertJsonMissingPath('data.0.leave_type_id');
    }

    public function test_roster_hides_rates_and_includes_day_off_without_inventing_missing_workdays(): void
    {
        $user = User::factory()->create();
        $employee = $this->employee($user);
        $shift = EmployeeShift::create(['org_id' => $user->org_id, 'code' => 'NIGHT', 'name' => 'Night', 'starts_at' => '22:00', 'ends_at' => '06:00', 'break_minutes' => 60]);
        EmployeeShiftSchedule::create(['org_id' => $user->org_id, 'employee_id' => $employee->id, 'employee_shift_id' => $shift->id, 'schedule_date' => '2026-10-19', 'is_day_off' => false]);
        EmployeeShiftSchedule::create(['org_id' => $user->org_id, 'employee_id' => $employee->id, 'schedule_date' => '2026-10-20', 'is_day_off' => true]);
        $token = $this->credential($user, ['summary:roster:read', 'employees:roster:read']);
        $this->withToken($token)->getJson('/api/v1/summaries/roster')->assertOk()->assertJsonPath('data.period.allocated_days', 2)
            ->assertJsonPath('data.period.day_off_allocations', 1)->assertJsonPath('data.period.missing_schedule_workdays', null);
        $this->getJson('/api/v1/employees/'.$employee->id.'/roster')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.shift.ot_rate_normal');
    }

    public function test_hr_key_policy_requires_domain_authority_and_explicit_visibility(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'hr_keys', 'name' => 'HR keys']);
        $role->permissions()->attach(Permission::whereIn('code', ['api_keys.view', 'api_keys.manage', 'hr.employees.manage'])->pluck('id'));
        $user->roles()->attach($role);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('settings.api-access.store'), ['name' => 'No policy', 'scopes' => ['employees:read']])->assertUnprocessable();
        $this->postJson(route('settings.api-access.store'), ['name' => 'Scoped', 'scopes' => ['employees:read'],
            'access_policy' => ['hr_branch_ids' => [$user->branch_id]]])->assertCreated();
        $foreign = User::factory()->create();
        $this->postJson(route('settings.api-access.store'), ['name' => 'Foreign policy', 'scopes' => ['employees:read'],
            'access_policy' => ['hr_branch_ids' => [$foreign->branch_id]]])->assertUnprocessable();
        $this->assertSame(1, DB::table('integration_api_keys')->count());
    }
}
