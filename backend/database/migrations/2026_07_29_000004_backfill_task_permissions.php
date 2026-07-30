<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['code' => 'tasks.view', 'module' => 'tasks', 'action' => 'view', 'description' => 'View delivery tasks'],
            ['code' => 'tasks.create', 'module' => 'tasks', 'action' => 'create', 'description' => 'Create delivery tasks'],
            ['code' => 'tasks.update', 'module' => 'tasks', 'action' => 'update', 'description' => 'Update delivery tasks'],
            ['code' => 'tasks.comment', 'module' => 'tasks', 'action' => 'comment', 'description' => 'Comment on delivery tasks'],
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

        $permissionIds = DB::table('permissions')->whereIn('code', array_column($permissions, 'code'))->pluck('id', 'code');
        $rolePermissions = [
            'owner' => array_column($permissions, 'code'),
            'admin' => array_column($permissions, 'code'),
            'project_manager' => array_column($permissions, 'code'),
            'member' => ['tasks.view', 'tasks.update', 'tasks.comment'],
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
        $codes = ['tasks.view', 'tasks.create', 'tasks.update', 'tasks.comment'];
        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
