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
        Schema::create('ai_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('org_id');
            $table->string('provider_type', 50)->default('ollama'); // ollama, lm_studio, openrouter, openai, gemini, custom
            $table->string('base_url')->default('http://localhost:11434/v1');
            $table->text('api_key')->nullable(); // encrypted at rest
            $table->string('model_name', 100)->default('qwen2.5-vl:7b');
            $table->decimal('temperature', 3, 2)->default(0.10);
            $table->unsignedInteger('timeout_seconds')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // success, failed
            $table->text('last_test_message')->nullable();
            $table->timestamps();

            $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['org_id', 'is_active']);
        });

        Schema::create('ocr_extractions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('org_id');
            $table->uuid('stored_file_id');
            $table->string('status', 30)->default('pending'); // pending, processing, extracted, converted, rejected, failed
            $table->string('provider_code', 50)->default('universal_openai');
            $table->string('document_type', 50)->default('receipt'); // receipt, tax_invoice, bank_slip, unknown
            $table->decimal('confidence_score', 4, 3)->default(0.000); // 0.000 - 1.000
            $table->longText('raw_response_text')->nullable();
            $table->json('parsed_data_json')->nullable();
            $table->string('target_type', 50)->nullable(); // expense, vendor_bill
            $table->uuid('target_id')->nullable();
            $table->boolean('math_mismatch')->default(false);
            $table->boolean('tax_id_valid')->nullable();
            $table->text('error_message')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('stored_file_id')->references('id')->on('files')->cascadeOnDelete();
            $table->index(['org_id', 'status']);
            $table->index(['org_id', 'created_at']);
        });

        Schema::create('ocr_field_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('org_id');
            $table->uuid('ocr_extraction_id');
            $table->string('field_key', 50); // tax_id, subtotal, vat_rate, vat_amount, total_amount, invoice_no, document_date, vendor_name
            $table->text('extracted_value')->nullable();
            $table->decimal('confidence', 4, 3)->default(0.000);
            $table->boolean('is_corrected_by_user')->default(false);
            $table->timestamps();

            $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('ocr_extraction_id')->references('id')->on('ocr_extractions')->cascadeOnDelete();
            $table->index(['ocr_extraction_id', 'field_key']);
        });

        Schema::create('ocr_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('org_id');
            $table->string('vendor_name');
            $table->string('vendor_tax_id', 20)->nullable();
            $table->string('default_category', 50)->nullable(); // Expense::CATEGORIES
            $table->uuid('default_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('org_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['org_id', 'vendor_name']);
        });

        // Seed Phase 26 Permissions
        $permissions = [
            ['code' => 'ocr.view', 'module' => 'ocr', 'action' => 'view', 'description' => 'View OCR scanned documents and history'],
            ['code' => 'ocr.upload', 'module' => 'ocr', 'action' => 'upload', 'description' => 'Upload documents for AI OCR processing'],
            ['code' => 'ocr.convert', 'module' => 'ocr', 'action' => 'convert', 'description' => 'Convert OCR extractions to expenses or bills'],
            ['code' => 'ai_settings.manage', 'module' => 'settings', 'action' => 'manage', 'description' => 'Manage organization AI configuration and models'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $permission['code']],
                array_merge($permission, [
                    'id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_column($permissions, 'code'))
            ->pluck('id', 'code');

        $rolePermissions = [
            'owner' => array_column($permissions, 'code'),
            'admin' => array_column($permissions, 'code'),
            'finance' => ['ocr.view', 'ocr.upload', 'ocr.convert'],
        ];

        foreach ($rolePermissions as $roleCode => $codes) {
            $roleIds = DB::table('roles')->where('code', $roleCode)->pluck('id');
            foreach ($roleIds as $roleId) {
                foreach ($codes as $code) {
                    if (isset($permissionIds[$code])) {
                        DB::table('role_permissions')->updateOrInsert([
                            'role_id' => $roleId,
                            'permission_id' => $permissionIds[$code],
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $codes = ['ocr.view', 'ocr.upload', 'ocr.convert', 'ai_settings.manage'];
        $permissionIds = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('code', $codes)->delete();

        Schema::dropIfExists('ocr_templates');
        Schema::dropIfExists('ocr_field_results');
        Schema::dropIfExists('ocr_extractions');
        Schema::dropIfExists('ai_configurations');
    }
};
