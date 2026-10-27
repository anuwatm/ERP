<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_staff', fn (Blueprint $t) => $t->boolean('pharmacist_qualified')->default(false));
        Schema::create('pos_return_inspections', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('branch_id')->constrained('pos_branches');
            $t->foreignUuid('refund_id')->constrained('pos_refunds');
            $t->uuid('source_line_id');
            $t->foreignUuid('grant_id')->constrained('pos_grants');
            $t->decimal('base_quantity', 18, 4);
            $t->string('decision', 20);
            $t->foreignUuid('inspector_id')->constrained('pos_staff');
            $t->string('request_key', 100);
            $t->char('request_hash', 64);
            $t->json('snapshot');
            $t->timestamps();
            $t->unique(['branch_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_return_inspections');
        Schema::table('pos_staff', fn (Blueprint $t) => $t->dropColumn('pharmacist_qualified'));
    }
};
