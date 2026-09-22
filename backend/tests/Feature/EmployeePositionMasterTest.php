<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeePositionMasterTest extends TestCase
{
    use RefreshDatabase;

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(
            ['org_id' => $user->org_id, 'code' => 'pos_master_test_'.substr(md5(implode(',', $codes)), 0, 8)],
            ['name' => 'Position Master Test Role', 'is_system' => true]
        );
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'hr', 'action' => 'manage']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    public function test_positions_table_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('positions'));
        $this->assertTrue(Schema::hasColumns('positions', [
            'id', 'org_id', 'department_id', 'code', 'name', 'description', 'status', 'created_at', 'updated_at', 'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumn('employees', 'position_id'));
    }

    public function test_can_quick_create_position_via_endpoint(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        $response = $this->actingAsOrgUser($user)->postJson(route('hr.positions.store'), [
            'name' => 'Senior Frontend Engineer',
            'code' => 'ENG-SR-FE',
            'description' => 'Leads frontend UI/UX development',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('position.name', 'Senior Frontend Engineer');
        $response->assertJsonPath('position.code', 'ENG-SR-FE');

        $this->assertDatabaseHas('positions', [
            'org_id' => $user->org_id,
            'name' => 'Senior Frontend Engineer',
            'code' => 'ENG-SR-FE',
            'status' => 'active',
        ]);
    }

    public function test_position_name_must_be_unique_per_organization(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        Position::create([
            'org_id' => $user->org_id,
            'name' => 'Accountant',
            'status' => 'active',
        ]);

        $response = $this->actingAsOrgUser($user)->postJson(route('hr.positions.store'), [
            'name' => 'Accountant',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_can_create_employee_with_position_id_and_syncs_position_title(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        $position = Position::create([
            'org_id' => $user->org_id,
            'name' => 'HR Specialist',
            'code' => 'HR-SPEC',
            'status' => 'active',
        ]);

        $payload = [
            'employee_code' => 'EMP-POS-01',
            'first_name' => 'Somying',
            'last_name' => 'Jaidee',
            'position_id' => $position->id,
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'bank_transfer',
        ];

        $response = $this->withoutMiddleware()->actingAsOrgUser($user)->post(route('hr.employees.store'), $payload);
        $response->assertRedirect();

        $employee = Employee::where('org_id', $user->org_id)->where('employee_code', 'EMP-POS-01')->firstOrFail();
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame('HR Specialist', $employee->position_title);
        $this->assertTrue($employee->position->is($position));
    }

    public function test_updating_employee_position_id_updates_position_title(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.manage']);

        $pos1 = Position::create(['org_id' => $user->org_id, 'name' => 'Junior Developer', 'status' => 'active']);
        $pos2 = Position::create(['org_id' => $user->org_id, 'name' => 'Mid Developer', 'status' => 'active']);

        $employee = Employee::create([
            'org_id' => $user->org_id,
            'employee_code' => 'EMP-DEV-02',
            'first_name' => 'Kittipong',
            'position_id' => $pos1->id,
            'position_title' => $pos1->name,
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'bank_transfer',
        ]);

        $payload = [
            'employee_code' => 'EMP-DEV-02',
            'first_name' => 'Kittipong',
            'position_id' => $pos2->id,
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'bank_transfer',
        ];

        $this->withoutMiddleware()->actingAsOrgUser($user)->patch(route('hr.employees.update', ['employee' => $employee->id]), $payload);

        $this->assertSame($pos2->id, $employee->fresh()->position_id);
        $this->assertSame('Mid Developer', $employee->fresh()->position_title);
    }

    public function test_tenant_isolation_cannot_assign_position_from_different_org(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->grant($user1, ['hr.employees.manage']);

        $foreignPos = Position::create([
            'org_id' => $user2->org_id,
            'name' => 'Secret Agent',
            'status' => 'active',
        ]);

        $payload = [
            'employee_code' => 'EMP-INTRUDER',
            'first_name' => 'Intruder',
            'position_id' => $foreignPos->id,
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'bank_transfer',
        ];

        $response = $this->withoutMiddleware()->actingAsOrgUser($user1)->postJson(route('hr.employees.store'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('position_id');
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-INTRUDER']);
    }

    public function test_employee_directory_returns_active_positions_list(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['hr.employees.view']);

        Position::create(['org_id' => $user->org_id, 'name' => 'Sales Representative', 'status' => 'active']);
        Position::create(['org_id' => $user->org_id, 'name' => 'Deprecated Position', 'status' => 'inactive']);

        $response = $this->actingAsOrgUser($user)->get(route('hr.employees.index'));
        $response->assertOk();

        $positions = $response->viewData('page')['props']['positions'];
        $this->assertCount(1, $positions);
        $this->assertSame('Sales Representative', $positions[0]['name']);
    }
}
