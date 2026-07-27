<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('sku', 50)->nullable();
            $table->string('name');
            $table->string('type', 20);
            $table->string('category', 100)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('cost', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->boolean('track_inventory')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['org_id', 'sku']);
            $table->index(['org_id', 'type', 'is_active']);
            $table->index(['org_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
