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
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('subject_type', 40);
            $table->unsignedInteger('version')->default(1);
            $table->decimal('amount_min', 18, 2)->nullable();
            $table->decimal('amount_max', 18, 2)->nullable();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'code', 'version']);
            $table->index(['org_id', 'subject_type', 'is_active']);
        });
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->unsignedInteger('step_no');
            $table->string('assignment_type', 20);
            $table->uuid('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_role_code', 50)->nullable();
            $table->string('execution_mode', 20)->default('sequential');
            $table->timestamps();
            $table->unique(['workflow_definition_id', 'step_no']);
        });
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('workflow_definition_id')->constrained('workflow_definitions')->restrictOnDelete();
            $table->string('subject_type', 40);
            $table->uuid('subject_id');
            $table->foreignUuid('requester_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('current_step_no')->default(1);
            $table->json('definition_snapshot');
            $table->json('subject_snapshot');
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'subject_type', 'subject_id']);
            $table->index(['org_id', 'status']);
        });
        Schema::create('workflow_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->unsignedInteger('step_no');
            $table->foreignUuid('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('delegated_from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('queued');
            $table->foreignUuid('acted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_instance_id', 'step_no', 'assigned_user_id'], 'workflow_approval_assignee_unique');
            $table->index(['assigned_user_id', 'status']);
        });
        Schema::create('workflow_delegations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('delegator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('delegate_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 40)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['org_id', 'delegator_user_id', 'delegate_user_id'], 'workflow_delegation_lookup_idx');
        });
        $codes = ['workflows.view' => 'view', 'workflows.manage' => 'manage', 'workflows.approve' => 'approve'];
        foreach ($codes as $code => $action) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['id' => DB::table('permissions')->where('code', $code)->value('id') ?: (string) Str::orderedUuid(), 'module' => 'workflows', 'action' => $action, 'description' => 'Workflow '.$action, 'created_at' => now(), 'updated_at' => now()]);
        }
        $ids = DB::table('permissions')->whereIn('code', array_keys($codes))->pluck('id', 'code');
        foreach (DB::table('roles')->whereIn('code', ['owner', 'admin', 'finance'])->pluck('id') as $role) {
            foreach ($ids as $id) {
                DB::table('role_permissions')->updateOrInsert(['role_id' => $role, 'permission_id' => $id], []);
            }
        }
        foreach (DB::table('roles')->whereIn('code', ['project_manager'])->pluck('id') as $role) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $role, 'permission_id' => $ids['workflows.approve']], []);
        }
        foreach (DB::table('roles')->pluck('id') as $role) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $role, 'permission_id' => $ids['workflows.view']], []);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('module', 'workflows')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('workflow_delegations');
        Schema::dropIfExists('workflow_approvals');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_definitions');
    }
};
