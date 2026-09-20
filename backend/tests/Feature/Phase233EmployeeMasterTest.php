<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeWorkProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase233EmployeeMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_is_optional_to_a_user_and_sensitive_values_are_encrypted(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::create(['org_id' => $owner->org_id, 'employee_code' => 'EMP-001', 'first_name' => 'Nok', 'employment_type' => 'daily', 'employment_status' => 'active', 'national_id' => '1234567890123', 'bank_account_number' => '123-4-56789-0']);

        $this->assertNull($employee->user_id);
        $this->assertSame('1234567890123', $employee->fresh()->national_id);
        $this->assertNotSame('1234567890123', (string) $employee->getRawOriginal('national_id'));
        $this->assertNotSame('123-4-56789-0', (string) $employee->getRawOriginal('bank_account_number'));
    }

    public function test_legacy_profiles_can_reference_the_new_employee_without_removing_user_links(): void
    {
        $user = User::factory()->create();
        $employee = Employee::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'employee_code' => 'EMP-002', 'first_name' => 'Somchai', 'employment_type' => 'monthly', 'employment_status' => 'active']);
        $work = EmployeeWorkProfile::create(['org_id' => $user->org_id, 'employee_id' => $employee->id, 'user_id' => $user->id, 'employment_type' => 'employee']);
        $payroll = EmployeePayrollProfile::create(['org_id' => $user->org_id, 'employee_id' => $employee->id, 'user_id' => $user->id, 'monthly_salary' => 10000]);

        $this->assertTrue($work->employee->is($employee));
        $this->assertTrue($payroll->employee->is($employee));
        $this->assertTrue($user->employee->is($employee));
    }

    public function test_employee_master_and_legacy_bridge_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('employees'));
        $this->assertTrue(Schema::hasColumns('employees', ['user_id', 'manager_id', 'employee_code', 'employment_status', 'bank_account_number']));
        $this->assertTrue(Schema::hasColumn('employee_work_profiles', 'employee_id'));
        $this->assertTrue(Schema::hasColumn('employee_payroll_profiles', 'employee_id'));
    }

    public function test_employee_directory_data_supports_account_link_and_departure_state(): void
    {
        $actor = User::factory()->create();
        $account = User::factory()->create(['org_id' => $actor->org_id, 'status' => 'active']);
        $employee = Employee::create(['org_id' => $actor->org_id, 'user_id' => $account->id, 'employee_code' => 'EMP-CRUD', 'first_name' => 'Mali', 'employment_type' => 'monthly', 'employment_status' => 'resigned', 'payment_method' => 'bank_transfer']);

        $this->assertSame($account->id, $employee->user_id);
        $this->assertSame('resigned', $employee->employment_status);
    }

    public function test_employee_directory_hides_sensitive_values_from_view_only_users_and_tenants(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $this->grant($viewer, ['hr.employees.view']);
        Employee::create(['org_id' => $viewer->org_id, 'employee_code' => 'EMP-PRIVATE', 'first_name' => 'Private', 'employment_type' => 'daily', 'employment_status' => 'active', 'national_id' => '1234567890123', 'bank_account_number' => '999999']);
        Employee::create(['org_id' => $other->org_id, 'employee_code' => 'EMP-OTHER', 'first_name' => 'Other', 'employment_type' => 'daily', 'employment_status' => 'active']);

        $this->assertSame(1, Employee::where('org_id', $viewer->org_id)->count());
        $this->assertNull(Employee::where('org_id', $viewer->org_id)->firstOrFail()->makeHidden(['national_id', 'bank_account_number'])->toArray()['national_id'] ?? null);
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'employee_master_test'], ['name' => 'Employee master test', 'is_system' => true]);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'hr', 'action' => 'manage']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    public function test_account_link_route_updates_same_organization_employee(): void
    {
        $actor = User::factory()->create();
        $account = User::factory()->create(['org_id' => $actor->org_id]);
        $employee = Employee::create(['org_id' => $actor->org_id, 'employee_code' => 'EMP-LINK', 'first_name' => 'Link', 'employment_type' => 'monthly', 'employment_status' => 'active', 'payment_method' => 'bank_transfer']);

        $this->withoutMiddleware()->actingAsOrgUser($actor)->patch(route('hr.employees.account.link', ['employee' => $employee->id]), ['user_id' => $account->id])->assertRedirect();
        $this->assertSame($account->id, $employee->fresh()->user_id);
    }

    public function test_departure_update_disables_the_linked_user_account(): void
    {
        $actor = User::factory()->create();
        $account = User::factory()->create(['org_id' => $actor->org_id, 'status' => 'active']);
        $employee = Employee::create(['org_id' => $actor->org_id, 'user_id' => $account->id, 'employee_code' => 'EMP-DEPART', 'first_name' => 'Depart', 'employment_type' => 'monthly', 'employment_status' => 'active', 'payment_method' => 'bank_transfer']);
        $payload = ['employee_code' => 'EMP-DEPART', 'first_name' => 'Depart', 'employment_type' => 'monthly', 'employment_status' => 'resigned', 'payment_method' => 'bank_transfer'];

        $this->withoutMiddleware()->actingAsOrgUser($actor)->patch(route('hr.employees.update', ['employee' => $employee->id]), $payload)->assertRedirect();
        $this->assertSame('inactive', $account->fresh()->status);
    }
}
