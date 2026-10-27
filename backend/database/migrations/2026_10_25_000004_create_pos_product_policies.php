<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_product_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->json('pharmacy');
            $table->timestamps();
            $table->unique(['org_id', 'product_id']);
        });
        Schema::create('pos_staff_authorities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $table->uuid('source_staff_id');
            $table->string('role', 30);
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->unique(['pos_instance_id', 'source_staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_staff_authorities');
        Schema::dropIfExists('pos_product_policies');
    }
};
