<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_import_sales', function (Blueprint $t) {
            $t->uuid('discount_approval_id')->nullable();
            $t->unique(['pos_instance_id', 'discount_approval_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_import_sales', function (Blueprint $t) {
            $t->dropUnique(['pos_instance_id', 'discount_approval_id']);
            $t->dropColumn('discount_approval_id');
        });
    }
};
