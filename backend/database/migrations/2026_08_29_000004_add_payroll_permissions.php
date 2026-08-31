<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $codes = ['payroll.view' => 'view', 'payroll.manage' => 'manage', 'payroll.approve' => 'approve', 'payroll.pay' => 'pay', 'payroll.export' => 'export'];
        foreach ($codes as $code => $action) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['id' => (string) Str::orderedUuid(), 'module' => 'payroll', 'action' => $action, 'description' => 'Payroll '.$action, 'created_at' => now(), 'updated_at' => now()]);
        }
        $permissionIds = DB::table('permissions')->whereIn('code', array_keys($codes))->pluck('id');
        $roles = DB::table('roles')->whereIn('code', ['owner', 'admin', 'finance'])->pluck('id');
        foreach ($roles as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], []);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('module', 'payroll')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
