<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'manage'] as $action) {
            DB::table('permissions')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => 'pos.'.$action, 'module' => 'pos', 'action' => $action, 'description' => 'POS '.$action, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('code', ['pos.view', 'pos.manage'])->delete();
    }
};
