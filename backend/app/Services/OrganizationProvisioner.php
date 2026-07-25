<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\NumberSequence;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrganizationProvisioner
{
    public function registerOwner(array $data, Request $request): User
    {
        return DB::transaction(function () use ($data, $request): User {
            $organization = Organization::create([
                'name' => $data['organization_name'],
                'currency' => 'THB',
                'timezone' => 'Asia/Bangkok',
                'status' => 'active',
            ]);

            $branch = Branch::create([
                'org_id' => $organization->id,
                'code' => '000001',
                'name' => 'Head Office',
                'is_head_office' => true,
                'status' => 'active',
            ]);

            $division = Division::create([
                'org_id' => $organization->id,
                'branch_id' => $branch->id,
                'code' => '000001',
                'name' => 'Default Division',
                'status' => 'active',
            ]);

            $department = Department::create([
                'org_id' => $organization->id,
                'branch_id' => $branch->id,
                'division_id' => $division->id,
                'code' => '000001',
                'name' => 'Default Department',
                'status' => 'active',
            ]);

            $user = User::create([
                'org_id' => $organization->id,
                'branch_id' => $branch->id,
                'division_id' => $division->id,
                'department_id' => $department->id,
                'name' => $data['name'],
                'display_name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'auth_provider' => 'local',
                'status' => 'active',
            ]);

            $this->seedPermissions();
            $roles = $this->seedRoles($organization);
            $this->syncRolePermissions($roles);

            $ownerRole = $roles['owner'];
            $user->roles()->attach($ownerRole->id, [
                'assigned_at' => now(),
                'assigned_by' => $user->id,
            ]);

            $this->seedSettings($organization);
            $this->seedNumberSequences($organization, $branch);

            AuditLog::create([
                'org_id' => $organization->id,
                'actor_user_id' => $user->id,
                'action' => 'auth.register',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'after_json' => [
                    'organization_id' => $organization->id,
                    'email' => $user->email,
                    'role' => 'owner',
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->headers->get('X-Request-Id'),
            ]);

            return $user;
        });
    }

    private function seedPermissions(): void
    {
        foreach (PermissionCatalog::permissions() as $permission) {
            Permission::firstOrCreate(['code' => $permission['code']], $permission);
        }
    }

    private function seedRoles(Organization $organization): array
    {
        $roles = [];

        foreach (PermissionCatalog::roleNames() as $code => $name) {
            $roles[$code] = Role::firstOrCreate(
                ['org_id' => $organization->id, 'code' => $code],
                ['name' => $name, 'is_system' => true]
            );
        }

        return $roles;
    }

    private function syncRolePermissions(array $roles): void
    {
        $permissions = Permission::whereIn('code', array_column(PermissionCatalog::permissions(), 'code'))->get()->keyBy('code');

        foreach (PermissionCatalog::defaults() as $roleCode => $permissionCodes) {
            $roles[$roleCode]->permissions()->sync(
                collect($permissionCodes)->map(fn (string $code) => $permissions[$code]->id)->all()
            );
        }
    }

    private function seedSettings(Organization $organization): void
    {
        Setting::create([
            'org_id' => $organization->id,
            'key' => 'organization.profile',
            'value_json' => [
                'currency' => 'THB',
                'timezone' => 'Asia/Bangkok',
            ],
        ]);
    }

    private function seedNumberSequences(Organization $organization, Branch $branch): void
    {
        foreach (['branch', 'division', 'department'] as $docType) {
            NumberSequence::create([
                'org_id' => $organization->id,
                'branch_id' => $docType === 'branch' ? null : $branch->id,
                'branch_key' => $docType === 'branch' ? '00000000-0000-0000-0000-000000000000' : $branch->id,
                'doc_type' => $docType,
                'year' => null,
                'year_key' => 0,
                'last_number' => 1,
            ]);
        }
    }
}
