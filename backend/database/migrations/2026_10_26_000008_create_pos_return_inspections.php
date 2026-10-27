<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_staff_authorities', fn (Blueprint $t) => $t->boolean('pharmacist_qualified')->default(false));
        Schema::create('pos_import_return_inspections', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('source_inspection_id');
            $t->foreignUuid('refund_id')->constrained('pos_import_refunds')->restrictOnDelete();
            $t->foreignUuid('sale_id')->constrained('pos_import_sales')->restrictOnDelete();
            $t->uuid('source_line_id');
            $t->foreignUuid('grant_id')->constrained('pos_stock_entitlements')->restrictOnDelete();
            $t->decimal('base_quantity', 18, 4);
            $t->decimal('base_cost', 18, 2);
            $t->string('decision', 20);
            $t->char('payload_hash', 64);
            $t->json('source_snapshot');
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_inspection_id'], 'pos_return_inspection_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_import_return_inspections');
        Schema::table('pos_staff_authorities', fn (Blueprint $t) => $t->dropColumn('pharmacist_qualified'));
    }
};
