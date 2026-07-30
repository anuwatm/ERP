<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->char('expense_no', 6);
            $table->string('category', 50);
            $table->string('title');
            $table->decimal('amount', 18, 2);
            $table->date('expense_date');
            $table->uuid('project_id')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->string('status', 20)->default('draft');
            $table->uuid('receipt_file_id')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['org_id', 'expense_no']);
            $table->index(['org_id', 'status', 'expense_date']);
            $table->index(['org_id', 'paid_at']);
            $table->index(['org_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
