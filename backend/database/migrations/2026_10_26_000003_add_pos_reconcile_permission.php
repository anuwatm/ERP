<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore(['id' => (string) Str::uuid(), 'code' => 'pos.reconcile', 'module' => 'pos', 'action' => 'reconcile', 'description' => 'POS ledger reconciliation', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('code', 'pos.reconcile')->delete();
    }
};
