<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('party_type', 20);
            $table->uuid('party_id');
            $table->string('email');
            $table->string('name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'party_type', 'party_id', 'email']);
            $table->index(['org_id', 'email', 'is_active']);
        });

        Schema::create('portal_access_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('portal_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quotation_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignUuid('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->string('accepted_by_name', 150);
            $table->string('accepted_by_email');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique('quotation_id');
        });

        Schema::create('vendor_bill_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('portal_user_id')->constrained('portal_users')->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('vendor_invoice_no', 100);
            $table->date('invoice_date');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3)->default('THB');
            $table->string('status', 30)->default('pending_scan');
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['org_id', 'supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bill_submissions');
        Schema::dropIfExists('quotation_acceptances');
        Schema::dropIfExists('portal_sessions');
        Schema::dropIfExists('portal_access_tokens');
        Schema::dropIfExists('portal_users');
    }
};
