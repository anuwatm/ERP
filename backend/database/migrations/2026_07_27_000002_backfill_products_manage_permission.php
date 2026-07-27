<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'products.manage')->value('id');

        if (! $permissionId) {
            $permissionId = (string) Str::orderedUuid();
            DB::table('permissions')->insert([
                'id' => $permissionId,
                'code' => 'products.manage',
                'module' => 'products',
                'action' => 'manage',
                'description' => 'Manage products and services',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('code', ['owner', 'admin', 'finance'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'products.manage')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
