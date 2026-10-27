<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sync_inbox', fn (Blueprint $t) => $t->uuid('dependency_source_sale_id')->nullable()->index());
        Schema::table('pos_import_sales', function (Blueprint $t) {
            $t->json('source_snapshot')->nullable();
            $t->json('account_snapshot')->nullable();
        });
        Schema::create('pos_import_refunds', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('pos_instance_id')->constrained('pos_instances')->restrictOnDelete();
            $t->uuid('source_refund_id');
            $t->foreignUuid('sale_id')->constrained('pos_import_sales')->restrictOnDelete();
            $t->char('payload_hash', 64);
            $t->json('source_snapshot');
            $t->foreignUuid('credit_note_id')->constrained('credit_debit_notes')->restrictOnDelete();
            $t->foreignUuid('payment_id')->constrained('payments')->restrictOnDelete();
            $t->foreignUuid('original_payment_id')->constrained('payments')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['pos_instance_id', 'source_refund_id']);
            $t->unique('payment_id');
            $t->unique('credit_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_import_refunds');
        Schema::table('pos_import_sales', fn (Blueprint $t) => $t->dropColumn(['source_snapshot', 'account_snapshot']));
        Schema::table('pos_sync_inbox', fn (Blueprint $t) => $t->dropColumn('dependency_source_sale_id'));
    }
};
