<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['code' => 'products.view', 'module' => 'products', 'action' => 'view', 'description' => 'View products and services'],
            ['code' => 'invoices.view', 'module' => 'invoices', 'action' => 'view', 'description' => 'View invoices'],
            ['code' => 'invoices.create', 'module' => 'invoices', 'action' => 'create', 'description' => 'Create invoices'],
            ['code' => 'invoices.update', 'module' => 'invoices', 'action' => 'update', 'description' => 'Update invoices'],
            ['code' => 'invoices.void', 'module' => 'invoices', 'action' => 'void', 'description' => 'Void invoices'],
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
            'owner' => array_keys($permissions = array_column($permissions, 'code', 'code')),
            'admin' => array_keys($permissions),
            'finance' => array_keys($permissions),
            'sales' => ['products.view', 'invoices.view'],
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
        $codes = ['products.view', 'invoices.view', 'invoices.create', 'invoices.update', 'invoices.void'];
        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
