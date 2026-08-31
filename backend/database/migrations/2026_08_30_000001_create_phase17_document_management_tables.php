<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->unsignedInteger('minimum_retention_days')->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('legal_hold_required')->default(false);
            $table->timestamps();
            $table->unique(['org_id', 'code', 'effective_from']);
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('retention_policy_id')->nullable()->constrained('retention_policies')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('default_sensitivity', 40)->default('org_internal');
            $table->boolean('expiry_tracking_enabled')->default(false);
            $table->unsignedSmallInteger('default_renewal_alert_days')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['org_id', 'code']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('document_categories')->nullOnDelete();
            $table->foreignUuid('retention_policy_id')->nullable()->constrained('retention_policies')->nullOnDelete();
            $table->foreignUuid('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_no', 50);
            $table->string('title', 255);
            $table->string('sensitivity', 40)->default('org_internal');
            $table->string('status', 30)->default('active');
            $table->date('expires_at')->nullable();
            $table->unsignedSmallInteger('renewal_alert_days')->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->timestamp('retention_until')->nullable();
            $table->uuid('current_version_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['org_id', 'document_no']);
            $table->index(['org_id', 'status', 'expires_at']);
            $table->index(['org_id', 'sensitivity']);
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('storage_key', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('scan_status', 30)->default('pending_scan');
            $table->string('change_note', 500)->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'version_no']);
            $table->index(['org_id', 'checksum_sha256']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('document_versions')->nullOnDelete();
        });

        Schema::create('document_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('linkable_type', 80);
            $table->uuid('linkable_id');
            $table->string('role', 30)->default('supporting');
            $table->foreignUuid('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'linkable_type', 'linkable_id', 'role']);
            $table->index(['org_id', 'linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
        Schema::table('documents', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('retention_policies');
    }
};
