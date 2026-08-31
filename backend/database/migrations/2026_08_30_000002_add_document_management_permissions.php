<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $codes = ['documents.view' => 'view', 'documents.manage' => 'manage', 'documents.download' => 'download', 'documents.retention.manage' => 'retention_manage'];
        foreach ($codes as $code => $action) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['id' => (string) Str::orderedUuid(), 'module' => 'documents', 'action' => $action, 'description' => 'Document management '.$action, 'created_at' => now(), 'updated_at' => now()]);
        }
        $ids = DB::table('permissions')->whereIn('code', array_keys($codes))->pluck('id');
        foreach (DB::table('roles')->whereIn('code', ['owner', 'admin', 'finance'])->pluck('id') as $roleId) {
            foreach ($ids as $id) {
                DB::table('role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $id], []);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('module', 'documents')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
