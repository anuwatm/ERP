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
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('break_minutes')->default(60);
            $table->unsignedInteger('work_minutes_per_day')->default(480);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['org_id', 'code']);
        });

        Schema::create('employee_work_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('employee_shift_id')->nullable()->constrained('employee_shifts')->nullOnDelete();
            $table->foreignUuid('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 50)->nullable();
            $table->string('employment_type', 30)->default('employee');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'user_id']);
            $table->unique(['org_id', 'employee_code']);
            $table->index(['org_id', 'manager_user_id']);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name', 150);
            $table->timestamps();
            $table->unique(['org_id', 'holiday_date']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('employee_shift_id')->nullable()->constrained('employee_shifts')->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->string('clock_in_source', 20)->default('web');
            $table->string('clock_out_source', 20)->nullable();
            $table->string('clock_in_ip_hash', 64)->nullable();
            $table->string('clock_out_ip_hash', 64)->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('clock_in_gps_accuracy_meters')->nullable();
            $table->unsignedInteger('clock_out_gps_accuracy_meters')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'user_id', 'work_date']);
            $table->index(['org_id', 'clock_out_at']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_entitlement_days', 8, 2)->default(0);
            $table->decimal('carry_over_limit_days', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['org_id', 'code']);
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('leave_year');
            $table->decimal('opening_days', 8, 2)->default(0);
            $table->decimal('accrued_days', 8, 2)->default(0);
            $table->decimal('carry_over_days', 8, 2)->default(0);
            $table->decimal('used_days', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['org_id', 'user_id', 'leave_type_id', 'leave_year'], 'leave_balance_org_user_type_year_unique');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('total_days', 8, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'user_id', 'starts_on', 'ends_on']);
        });

        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reversal_of_id')->nullable()->constrained('attendance_summaries')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('cutoff_date');
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->decimal('paid_leave_days', 8, 2)->default(0);
            $table->decimal('unpaid_leave_days', 8, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignUuid('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->index(['org_id', 'user_id', 'period_start', 'period_end'], 'attendance_summary_period_idx');
        });

        $permissions = [
            ['code' => 'hr.self.view', 'action' => 'view', 'description' => 'View own HR attendance and leave data'],
            ['code' => 'hr.team.view', 'action' => 'team_view', 'description' => 'View direct report HR attendance and leave data'],
            ['code' => 'hr.manage', 'action' => 'manage', 'description' => 'Manage HR profiles, shifts, leave and attendance'],
            ['code' => 'hr.summary.manage', 'action' => 'summary_manage', 'description' => 'Create and lock HR payroll summaries'],
        ];
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(['code' => $permission['code']], array_merge($permission, [
                'id' => DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid(),
                'module' => 'hr', 'created_at' => now(), 'updated_at' => now(),
            ]));
        }
        $ids = DB::table('permissions')->whereIn('code', array_column($permissions, 'code'))->pluck('id', 'code');
        foreach (DB::table('roles')->whereIn('code', ['owner', 'admin', 'finance'])->pluck('id') as $roleId) {
            foreach ($ids as $id) {
                DB::table('role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $id], []);
            }
        }
        foreach (DB::table('roles')->whereIn('code', ['project_manager'])->pluck('id') as $roleId) {
            foreach (['hr.self.view', 'hr.team.view'] as $code) {
                DB::table('role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $ids[$code]], []);
            }
        }
        foreach (DB::table('roles')->pluck('id') as $roleId) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $ids['hr.self.view']], []);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('module', 'hr')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        Schema::dropIfExists('attendance_summaries');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_work_profiles');
        Schema::dropIfExists('employee_shifts');
    }
};
