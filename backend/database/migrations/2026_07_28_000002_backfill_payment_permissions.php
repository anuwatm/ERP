<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['code' => 'payments.view', 'module' => 'payments', 'action' => 'view', 'description' => 'View invoice payments'],
            ['code' => 'payments.create', 'module' => 'payments', 'action' => 'create', 'description' => 'Record invoice payment receipts'],
            ['code' => 'payments.reverse', 'module' => 'payments', 'action' => 'reverse', 'description' => 'Reverse invoice payment receipts'],
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
            'owner' => ['payments.view', 'payments.create', 'payments.reverse'],
            'admin' => ['payments.view', 'payments.create', 'payments.reverse'],
            'finance' => ['payments.view', 'payments.create', 'payments.reverse'],
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
        $codes = ['payments.view', 'payments.create', 'payments.reverse'];
        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
