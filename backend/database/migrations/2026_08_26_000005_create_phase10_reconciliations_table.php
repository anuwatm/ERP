<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('bank_statement_line_id')->constrained('bank_statement_lines')->cascadeOnDelete();
            $table->string('reconcilable_type', 30);
            $table->uuid('reconcilable_id');
            $table->string('match_method', 20)->default('manual');
            $table->text('note')->nullable();
            $table->uuid('matched_by')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->uuid('unmatched_by')->nullable();
            $table->timestamp('unmatched_at')->nullable();
            $table->text('unmatch_note')->nullable();
            $table->timestamps();
            $table->index(['bank_statement_line_id', 'unmatched_at']);
            $table->index(['org_id', 'reconcilable_type', 'reconcilable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};
