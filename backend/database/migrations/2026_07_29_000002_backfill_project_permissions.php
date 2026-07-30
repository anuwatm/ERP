<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['code' => 'projects.view', 'module' => 'projects', 'action' => 'view', 'description' => 'View delivery projects'],
            ['code' => 'projects.create', 'module' => 'projects', 'action' => 'create', 'description' => 'Create delivery projects'],
            ['code' => 'projects.update', 'module' => 'projects', 'action' => 'update', 'description' => 'Update delivery projects'],
            ['code' => 'projects.reassign', 'module' => 'projects', 'action' => 'reassign', 'description' => 'Reassign project owner'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                array_merge($permission, [
                    'id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_column($permissions, 'code'))
            ->pluck('id', 'code');

        $rolePermissions = [
            'owner' => array_column($permissions, 'code'),
            'admin' => array_column($permissions, 'code'),
            'project_manager' => ['projects.view', 'projects.create', 'projects.update'],
            'member' => ['projects.view'],
        ];

        foreach ($rolePermissions as $roleCode => $codes) {
            $roleIds = DB::table('roles')->where('code', $roleCode)->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($codes as $code) {
                    DB::table('role_permissions')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionIds[$code],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $codes = ['projects.view', 'projects.create', 'projects.update', 'projects.reassign'];
        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
