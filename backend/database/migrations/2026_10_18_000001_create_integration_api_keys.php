<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('prefix', 32)->unique();
            $table->string('secret_hash', 64);
            $table->json('scopes');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'revoked_at', 'expires_at']);
        });

        foreach (['view' => 'View integration API keys', 'manage' => 'Manage integration API keys'] as $action => $description) {
            $code = 'api_keys.'.$action;
            $id = (string) Str::orderedUuid();
            DB::table('permissions')->insert([
                'id' => $id, 'code' => $code, 'module' => 'api_keys', 'action' => $action,
                'description' => $description, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (DB::table('roles')->whereIn('code', ['owner', 'admin'])->pluck('id') as $roleId) {
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('code', ['api_keys.view', 'api_keys.manage'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('integration_api_keys');
    }
};
