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
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->decimal('ot_rate_normal', 4, 2)->default(1.50)->after('work_minutes_per_day');
            $table->decimal('ot_rate_holiday', 4, 2)->default(2.00)->after('ot_rate_normal');
            $table->decimal('ot_rate_special_holiday', 4, 2)->default(3.00)->after('ot_rate_holiday');
            $table->unsignedInteger('grace_period_minutes')->default(0)->after('ot_rate_special_holiday');
            $table->unsignedInteger('overtime_starts_after_minutes')->default(0)->after('grace_period_minutes');
        });

        Schema::create('employee_shift_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('employee_shift_id')->nullable()->constrained('employee_shifts')->nullOnDelete();
            $table->date('schedule_date');
            $table->boolean('is_day_off')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['org_id', 'employee_id', 'schedule_date'], 'shift_schedules_org_emp_date_unique');
            $table->index(['org_id', 'schedule_date'], 'shift_schedules_org_date_idx');
        });

        Schema::create('attendance_raw_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->uuid('batch_id')->nullable()->index();
            $table->string('employee_code', 50);
            $table->foreignUuid('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('punch_time');
            $table->string('punch_type', 20)->default('auto');
            $table->string('device_id', 100)->nullable();
            $table->string('status', 30)->default('raw');
            $table->string('error_reason')->nullable();
            $table->timestamps();

            $table->index(['org_id', 'employee_code', 'punch_time'], 'raw_punch_lookup_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->change();
            $table->foreignUuid('employee_id')->nullable()->after('user_id')->constrained('employees')->nullOnDelete();
            $table->unsignedInteger('late_minutes')->default(0)->after('clock_out_at');
            $table->unsignedInteger('early_leave_minutes')->default(0)->after('late_minutes');
            $table->unsignedInteger('worked_minutes')->default(0)->after('early_leave_minutes');
            $table->unsignedInteger('ot_normal_minutes')->default(0)->after('worked_minutes');
            $table->unsignedInteger('ot_holiday_minutes')->default(0)->after('ot_normal_minutes');
            $table->unsignedInteger('ot_special_minutes')->default(0)->after('ot_holiday_minutes');
            $table->boolean('is_reconciled')->default(false)->after('ot_special_minutes');
            $table->boolean('is_absent')->default(false)->after('is_reconciled');
            $table->text('correction_reason')->nullable()->after('is_absent');
            $table->foreignUuid('corrected_by')->nullable()->after('correction_reason')->constrained('users')->nullOnDelete();

            $table->index(['org_id', 'employee_id', 'work_date'], 'attendances_org_emp_date_idx');
        });

        Schema::table('employee_payroll_profiles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->change();
            $table->string('wage_type', 20)->default('monthly')->after('monthly_salary');
            $table->decimal('daily_rate', 15, 2)->nullable()->after('wage_type');
            $table->decimal('hourly_rate', 15, 2)->nullable()->after('daily_rate');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->change();
            $table->foreignUuid('employee_id')->nullable()->after('user_id')->constrained('employees')->nullOnDelete();
            $table->decimal('ot_amount', 15, 2)->default(0)->after('allowance_amount');
            $table->decimal('late_deduction_amount', 15, 2)->default(0)->after('ot_amount');
            $table->foreignUuid('attendance_summary_id')->nullable()->after('calculation_snapshot')->constrained('attendance_summaries')->nullOnDelete();
        });

        // Link existing attendances to employees where possible
        $employees = DB::table('employees')->whereNotNull('user_id')->pluck('id', 'user_id');
        foreach ($employees as $userId => $empId) {
            DB::table('attendances')->where('user_id', $userId)->whereNull('employee_id')->update(['employee_id' => $empId]);
            DB::table('payroll_items')->where('user_id', $userId)->whereNull('employee_id')->update(['employee_id' => $empId]);
        }

        foreach ([
            ['code' => 'hr.shifts.manage', 'action' => 'shifts_manage', 'description' => 'Manage shift rosters and schedules'],
            ['code' => 'hr.attendance.import', 'action' => 'attendance_import', 'description' => 'Import time punches and run attendance reconciliation'],
        ] as $permission) {
            $id = DB::table('permissions')->where('code', $permission['code'])->value('id') ?: (string) Str::orderedUuid();
            DB::table('permissions')->updateOrInsert(['code' => $permission['code']], $permission + ['id' => $id, 'module' => 'hr', 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('roles')->whereIn('code', ['owner', 'admin'])->pluck('id') as $roleId) {
                DB::table('role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $id], []);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_summary_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn(['ot_amount', 'late_deduction_amount']);
        });

        Schema::table('employee_payroll_profiles', function (Blueprint $table) {
            $table->dropColumn(['wage_type', 'daily_rate', 'hourly_rate']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_org_emp_date_idx');
            $table->dropConstrainedForeignId('corrected_by');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn([
                'late_minutes', 'early_leave_minutes', 'worked_minutes',
                'ot_normal_minutes', 'ot_holiday_minutes', 'ot_special_minutes', 'is_reconciled',
                'is_absent', 'correction_reason',
            ]);
        });

        Schema::dropIfExists('attendance_raw_logs');
        Schema::dropIfExists('employee_shift_schedules');

        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropColumn([
                'ot_rate_normal', 'ot_rate_holiday', 'ot_rate_special_holiday', 'grace_period_minutes',
                'overtime_starts_after_minutes',
            ]);
        });
    }
};
