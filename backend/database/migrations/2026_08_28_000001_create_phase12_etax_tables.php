<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_tax_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('mode', 30)->default('disabled');
            $table->string('provider_code', 50)->nullable();
            $table->string('certificate_reference', 255)->nullable();
            $table->timestamp('certificate_expires_at')->nullable();
            $table->string('signature_mode', 30)->default('external');
            $table->json('provider_settings')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('org_id');
        });

        Schema::create('e_tax_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('source_type', 50);
            $table->uuid('source_id');
            $table->string('document_type', 30);
            $table->string('document_no', 100);
            $table->string('status', 30)->default('generated');
            $table->string('xml_storage_path', 500);
            $table->char('xml_sha256', 64);
            $table->json('payload_json');
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['org_id', 'source_type', 'source_id', 'document_type'], 'e_tax_document_source_unique');
            $table->index(['org_id', 'status', 'created_at']);
        });

        Schema::create('e_tax_submission_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('e_tax_document_id')->constrained('e_tax_documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('status', 30)->default('queued');
            $table->string('provider_code', 50)->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['e_tax_document_id', 'attempt_no']);
        });

        $permissions = [
            ['code' => 'e_tax.view', 'module' => 'e_tax', 'action' => 'view', 'description' => 'View e-Tax documents and RD Prep exports'],
            ['code' => 'e_tax.manage', 'module' => 'e_tax', 'action' => 'manage', 'description' => 'Configure and generate e-Tax documents'],
            ['code' => 'e_tax.submit', 'module' => 'e_tax', 'action' => 'submit', 'description' => 'Queue e-Tax provider submissions'],
        ];
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['code' => $permission['code']], array_merge($permission, ['id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(), 'created_at' => now(), 'updated_at' => now()]));
        }
        $ids = DB::table('permissions')->whereIn('code', array_column($permissions, 'code'))->pluck('id');
        foreach (['owner', 'admin', 'finance'] as $roleCode) {
            DB::table('roles')->where('code', $roleCode)->get(['id'])->each(function ($role) use ($ids): void {
                foreach ($ids as $id) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $id]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('e_tax_submission_attempts');
        Schema::dropIfExists('e_tax_documents');
        Schema::dropIfExists('e_tax_configs');
    }
};
