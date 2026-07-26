<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->char('customer_code', 6);
            $table->string('company_name');
            $table->string('tax_id', 50)->nullable();
            $table->string('customer_type', 30)->default('lead');
            $table->string('status', 20)->default('active');
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('line_id', 100)->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('source', 100)->nullable();
            $table->json('tags')->nullable();
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['org_id', 'customer_code']);
            $table->index(['org_id', 'owner_id', 'created_at']);
            $table->index(['org_id', 'status']);
            $table->index(['org_id', 'company_name']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->uuid('supplier_id')->nullable();
            $table->string('name');
            $table->string('position', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('line_id', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['org_id', 'customer_id']);
            $table->index(['org_id', 'customer_id', 'is_primary']);
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title');
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('stage', 30)->default('new');
            $table->decimal('value_amount', 18, 2)->default(0);
            $table->char('currency', 3)->default('THB');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->date('expected_close_date')->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 100)->nullable();
            $table->text('lost_reason')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['org_id', 'stage', 'owner_id', 'expected_close_date']);
            $table->index(['org_id', 'customer_id']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('entity_type', 30);
            $table->uuid('entity_id');
            $table->string('activity_type', 30);
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('activity_at');
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'entity_type', 'entity_id']);
            $table->index(['org_id', 'follow_up_at', 'completed_at', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('customers');
    }
};
