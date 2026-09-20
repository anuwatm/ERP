<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $canManage = $actor->hasPermissionCode('hr.employees.manage');
        $filters = $request->validate([
            'branch_id' => ['nullable', 'uuid'], 'department_id' => ['nullable', 'uuid'],
            'employment_status' => ['nullable', Rule::in(['probation', 'active', 'suspended', 'resigned', 'terminated'])],
            'employment_type' => ['nullable', Rule::in(['monthly', 'daily', 'hourly', 'contractor'])],
            'account_status' => ['nullable', Rule::in(['linked', 'unlinked', 'active', 'inactive', 'invited'])],
        ]);
        $employees = Employee::where('org_id', $actor->org_id)
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['employment_status'] ?? null, fn ($q, $value) => $q->where('employment_status', $value))
            ->when($filters['employment_type'] ?? null, fn ($q, $value) => $q->where('employment_type', $value))
            ->when(($filters['account_status'] ?? null) === 'linked', fn ($q) => $q->whereNotNull('user_id'))
            ->when(($filters['account_status'] ?? null) === 'unlinked', fn ($q) => $q->whereNull('user_id'))
            ->when(in_array($filters['account_status'] ?? null, ['active', 'inactive', 'invited'], true), fn ($q) => $q->whereHas('user', fn ($user) => $user->where('status', $filters['account_status'])))
            ->with(['branch:id,code,name', 'division:id,code,name', 'department:id,code,name', 'manager:id,employee_code,first_name,last_name', 'user:id,name,email,status'])
            ->orderBy('employee_code')->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id, 'user_id' => $employee->user_id, 'employee_code' => $employee->employee_code,
                'title' => $employee->title, 'first_name' => $employee->first_name,
                'national_id' => $canManage ? $employee->national_id : null, 'tax_id' => $canManage ? $employee->tax_id : null,
                'date_of_birth' => $canManage ? $employee->date_of_birth?->toDateString() : null, 'gender' => $canManage ? $employee->gender : null, 'last_name' => $employee->last_name,
                'nickname' => $employee->nickname, 'email' => $employee->email, 'phone' => $employee->phone,
                'position_title' => $employee->position_title, 'branch_id' => $employee->branch_id,
                'division_id' => $employee->division_id, 'department_id' => $employee->department_id, 'manager_id' => $employee->manager_id,
                'employment_type' => $employee->employment_type, 'employment_status' => $employee->employment_status,
                'started_on' => $employee->started_on?->toDateString(), 'probation_ends_on' => $employee->probation_ends_on?->toDateString(),
                'resigned_on' => $employee->resigned_on?->toDateString(), 'payment_method' => $employee->payment_method,
                'bank_name' => $canManage ? $employee->bank_name : null, 'bank_account_number' => $canManage ? $employee->bank_account_number : null, 'branch' => $employee->branch, 'division' => $employee->division,
                'department' => $employee->department, 'manager' => $employee->manager, 'user' => $employee->user,
            ]);

        return Inertia::render('Hr/Employees', [
            'employees' => $employees, 'filters' => $filters,
            'branches' => Branch::where('org_id', $actor->org_id)->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']),
            'divisions' => Division::where('org_id', $actor->org_id)->where('status', 'active')->orderBy('code')->get(['id', 'branch_id', 'code', 'name']),
            'departments' => Department::where('org_id', $actor->org_id)->where('status', 'active')->orderBy('code')->get(['id', 'branch_id', 'division_id', 'code', 'name']),
            'accounts' => User::where('org_id', $actor->org_id)->whereDoesntHave('employee')->orderBy('name')->get(['id', 'name', 'email', 'status']),
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = Employee::create($this->validated($request));
        $this->audit($request, 'hr.employee.create', $employee, ['employee_code', 'employment_status']);

        return back()->with('success', 'Employee created.');
    }

    public function update(Request $request, string $employee): RedirectResponse
    {
        $employee = Employee::where('org_id', $request->user()->org_id)->findOrFail($employee);
        $before = $employee->only(['employee_code', 'employment_status', 'user_id']);
        $employee->update($this->validated($request, $employee));
        $this->disableAccountWhenDeparted($employee, $request->user()->id);
        $this->audit($request, 'hr.employee.update', $employee, $before);

        return back()->with('success', 'Employee updated.');
    }

    public function linkAccount(Request $request, string $employee): RedirectResponse
    {
        $employee = Employee::where('org_id', $request->user()->org_id)->findOrFail($employee);
        $data = $request->validate(['user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('org_id', $request->user()->org_id)]]);
        abort_if(Employee::where('org_id', $employee->org_id)->where('user_id', $data['user_id'])->whereKeyNot($employee->id)->exists(), 422, 'This user account is already linked to an employee.');
        $employee->update(['user_id' => $data['user_id']]);
        $this->audit($request, 'hr.employee.account_link', $employee, ['user_id']);

        return back()->with('success', 'User account linked.');
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->where('org_id', $orgId)->ignore($employee?->id)],
            'title' => ['nullable', 'string', 'max:30'], 'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'], 'nickname' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:50'], 'tax_id' => ['nullable', 'string', 'max:20'], 'date_of_birth' => ['nullable', 'date'], 'gender' => ['nullable', Rule::in(['female', 'male', 'non_binary', 'unspecified'])],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'position_title' => ['nullable', 'string', 'max:150'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('org_id', $orgId)->where('status', 'active')],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')->where('org_id', $orgId)->where('status', 'active')],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('org_id', $orgId)->where('status', 'active')],
            'manager_id' => ['nullable', Rule::exists('employees', 'id')->where('org_id', $orgId)],
            'employment_type' => ['required', Rule::in(['monthly', 'daily', 'hourly', 'contractor'])],
            'employment_status' => ['required', Rule::in(['probation', 'active', 'suspended', 'resigned', 'terminated'])],
            'started_on' => ['nullable', 'date'], 'probation_ends_on' => ['nullable', 'date', 'after_or_equal:started_on'], 'resigned_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'payment_method' => ['required', Rule::in(['bank_transfer', 'cash'])], 'bank_name' => ['nullable', 'string', 'max:100'], 'bank_account_number' => ['nullable', 'string', 'max:100'],
        ]);
        abort_if(($data['manager_id'] ?? null) !== null && ($data['manager_id'] ?? null) === $employee?->id, 422, 'An employee cannot manage themselves.');
        $this->validateHierarchy($data, $orgId);

        return $data + ['org_id' => $orgId];
    }

    private function validateHierarchy(array $data, string $orgId): void
    {
        if (! ($data['branch_id'] ?? null) || ! ($data['division_id'] ?? null) || ! ($data['department_id'] ?? null)) {
            return;
        }
        $division = Division::where('org_id', $orgId)->findOrFail($data['division_id']);
        $department = Department::where('org_id', $orgId)->findOrFail($data['department_id']);
        abort_unless($division->branch_id === $data['branch_id'] && $department->branch_id === $data['branch_id'] && $department->division_id === $data['division_id'], 422, 'Organization structure references do not match.');
    }

    private function disableAccountWhenDeparted(Employee $employee, string $actorId): void
    {
        if (! $employee->user_id || ! in_array($employee->employment_status, ['resigned', 'terminated'], true)) {
            return;
        }
        $user = $employee->user;
        if ($user && $user->status !== 'inactive') {
            $user->forceFill(['status' => 'inactive', 'remember_token' => Str::random(60), 'updated_by' => $actorId])->save();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        }
    }

    private function owned(Request $request, Employee $employee): void
    {
        abort_unless($employee->org_id === $request->user()->org_id, 404);
    }

    private function audit(Request $request, string $action, Employee $employee, array $after): void
    {
        AuditLog::create(['org_id' => $employee->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'entity_type' => 'employee', 'entity_id' => $employee->id, 'after_json' => $after, 'request_id' => (string) Str::uuid()]);
    }
}
