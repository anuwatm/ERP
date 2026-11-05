<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_patients', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('branch_id')->index();
            $t->string('code', 40);
            $t->unsignedBigInteger('version');
            $t->longText('payload');
            $t->char('name_lookup', 64);
            $t->char('phone_lookup', 64)->nullable();
            $t->uuid('created_by');
            $t->uuid('updated_by');
            $t->timestamps();
            $t->unique(['branch_id', 'code'], 'patient_branch_code_unique');
            $t->index(['branch_id', 'name_lookup'], 'patient_name_lookup_index');
            $t->index(['branch_id', 'phone_lookup'], 'patient_phone_lookup_index');
        });
        Schema::create('pos_patient_revisions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('branch_id');
            $t->uuid('patient_id')->index();
            $t->unsignedBigInteger('version');
            $t->string('operation', 24);
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->longText('payload');
            $t->uuid('actor_id');
            $t->timestamps();
            $t->unique(['patient_id', 'version'], 'patient_revision_version_unique');
            $t->unique(['branch_id', 'request_key'], 'patient_revision_request_unique');
        });
        Schema::create('pos_patient_visits', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('branch_id');
            $t->uuid('patient_id')->index();
            $t->uuid('request_key');
            $t->char('request_hash', 64);
            $t->uuid('supersedes_id')->nullable();
            $t->longText('payload');
            $t->uuid('actor_id');
            $t->timestamps();
            $t->unique(['branch_id', 'request_key'], 'patient_visit_request_unique');
            $t->unique('supersedes_id', 'patient_visit_correction_unique');
        });
        Schema::create('pos_patient_dispensing', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('branch_id');
            $t->uuid('patient_id')->index();
            $t->char('order_lookup', 64)->unique();
            $t->longText('payload');
            $t->uuid('actor_id');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pos_patient_dispensing', 'pos_patient_visits', 'pos_patient_revisions', 'pos_patients'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
