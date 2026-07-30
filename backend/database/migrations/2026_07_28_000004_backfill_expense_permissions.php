<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['code' => 'expenses.view', 'module' => 'expenses', 'action' => 'view', 'description' => 'View expenses'],
            ['code' => 'expenses.create', 'module' => 'expenses', 'action' => 'create', 'description' => 'Create expense drafts'],
            ['code' => 'expenses.update', 'module' => 'expenses', 'action' => 'update', 'description' => 'Update expense drafts'],
            ['code' => 'expenses.approve', 'module' => 'expenses', 'action' => 'approve', 'description' => 'Approve expenses'],
            ['code' => 'expenses.pay', 'module' => 'expenses', 'action' => 'pay', 'description' => 'Mark approved expenses paid'],
            ['code' => 'expenses.reject', 'module' => 'expenses', 'action' => 'reject', 'description' => 'Reject expenses'],
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
            'finance' => array_column($permissions, 'code'),
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
        $codes = ['expenses.view', 'expenses.create', 'expenses.update', 'expenses.approve', 'expenses.pay', 'expenses.reject'];
        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
