<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['projects', 'tasks'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->index(['org_id', 'created_at']));
        }
    }

    public function down(): void
    {
        foreach (['projects', 'tasks'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropIndex(['org_id', 'created_at']));
        }
    }
};
