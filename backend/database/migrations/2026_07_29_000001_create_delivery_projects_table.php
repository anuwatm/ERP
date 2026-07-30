<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->char('project_code', 6);
            $table->string('name');
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('planning');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->decimal('budget_amount', 18, 2)->default(0);
            $table->char('currency', 3)->default('THB');
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['org_id', 'project_code']);
            $table->unique(['org_id', 'deal_id']);
            $table->index(['org_id', 'owner_id', 'status']);
            $table->index(['org_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
