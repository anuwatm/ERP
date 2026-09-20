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
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('employee_code', 50);
            $table->string('title', 30)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('nickname', 100)->nullable();
            $table->text('national_id')->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('position_title', 150)->nullable();
            $table->string('employment_type', 20)->default('monthly');
            $table->string('employment_status', 20)->default('probation');
            $table->date('started_on')->nullable();
            $table->date('probation_ends_on')->nullable();
            $table->date('resigned_on')->nullable();
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->string('bank_name', 100)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->timestamps();
            $table->unique(['org_id', 'employee_code']);
            $table->unique(['org_id', 'user_id']);
            $table->index(['org_id', 'branch_id', 'department_id', 'employment_status'], 'employees_directory_filter_idx');
            $table->index(['org_id', 'employment_type']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignUuid('manager_id')->nullable()->after('department_id')->constrained('employees')->nullOnDelete();
            $table->index(['org_id', 'manager_id']);
        });

        Schema::table('employee_work_profiles', function (Blueprint $table) {
            $table->foreignUuid('employee_id')->nullable()->after('org_id')->constrained('employees')->nullOnDelete();
            $table->unique(['org_id', 'employee_id'], 'employee_work_profiles_org_employee_unique');
        });

        Schema::table('employee_payroll_profiles', function (Blueprint $table) {
            $table->foreignUuid('employee_id')->nullable()->after('org_id')->constrained('employees')->nullOnDelete();
            $table->unique(['org_id', 'employee_id'], 'employee_payroll_profiles_org_employee_unique');
        });

        $legacyUsers = DB::table('employee_work_profiles')->select('org_id', 'user_id', 'employee_code', 'employment_type', 'started_on', 'ended_on')->get()
            ->merge(DB::table('employee_payroll_profiles')->select('org_id', 'user_id', DB::raw('NULL as employee_code'), DB::raw("'monthly' as employment_type"), DB::raw('NULL as started_on'), DB::raw('NULL as ended_on'))->get())
            ->unique(fn (object $row) => $row->org_id.'|'.$row->user_id);

        foreach ($legacyUsers as $legacy) {
            $user = DB::table('users')->where('id', $legacy->user_id)->where('org_id', $legacy->org_id)->first();
            if (! $user) {
                continue;
            }
            $employeeId = (string) Str::orderedUuid();
            $name = trim((string) $user->name);
            $parts = preg_split('/\s+/u', $name, 2) ?: [];
            $code = $legacy->employee_code ?: 'LEGACY-'.Str::upper(Str::substr(str_replace('-', '', $user->id), 0, 10));
            DB::table('employees')->updateOrInsert(
                ['org_id' => $legacy->org_id, 'user_id' => $legacy->user_id],
                ['id' => $employeeId, 'employee_code' => $code, 'first_name' => $parts[0] ?: 'Legacy', 'last_name' => $parts[1] ?? null, 'email' => $user->email, 'phone' => $user->phone, 'position_title' => $user->position, 'branch_id' => $user->branch_id, 'division_id' => $user->division_id, 'department_id' => $user->department_id, 'employment_type' => in_array($legacy->employment_type, ['monthly', 'daily', 'hourly', 'contractor'], true) ? $legacy->employment_type : 'monthly', 'employment_status' => $legacy->ended_on ? 'resigned' : 'active', 'started_on' => $legacy->started_on, 'resigned_on' => $legacy->ended_on, 'payment_method' => 'bank_transfer', 'created_at' => now(), 'updated_at' => now()]
            );
            $employeeId = DB::table('employees')->where('org_id', $legacy->org_id)->where('user_id', $legacy->user_id)->value('id');
            DB::table('employee_work_profiles')->where('org_id', $legacy->org_id)->where('user_id', $legacy->user_id)->update(['employee_id' => $employeeId]);
            DB::table('employee_payroll_profiles')->where('org_id', $legacy->org_id)->where('user_id', $legacy->user_id)->update(['employee_id' => $employeeId]);
        }

        foreach ([
            ['code' => 'hr.employees.view', 'action' => 'employees_view', 'description' => 'View employee directory'],
            ['code' => 'hr.employees.manage', 'action' => 'employees_manage', 'description' => 'Manage employee master records and account links'],
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
        Schema::table('employee_payroll_profiles', function (Blueprint $table) {
            $table->dropUnique('employee_payroll_profiles_org_employee_unique');
            $table->dropConstrainedForeignId('employee_id');
        });
        Schema::table('employee_work_profiles', function (Blueprint $table) {
            $table->dropUnique('employee_work_profiles_org_employee_unique');
            $table->dropConstrainedForeignId('employee_id');
        });
        Schema::dropIfExists('employees');
    }
};
