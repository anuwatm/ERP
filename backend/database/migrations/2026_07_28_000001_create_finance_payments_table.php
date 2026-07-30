<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('entry_type', 20)->default('receipt');
            $table->foreignUuid('reversal_of_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('payment_date');
            $table->string('payment_method', 30);
            $table->string('reference_no', 100)->nullable();
            $table->uuid('attachment_file_id')->nullable();
            $table->text('note')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['org_id', 'idempotency_key']);
            $table->index(['org_id', 'invoice_id', 'entry_type']);
            $table->index(['org_id', 'payment_date']);
            $table->unique(['org_id', 'reversal_of_payment_id'], 'payments_one_reversal_per_receipt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
