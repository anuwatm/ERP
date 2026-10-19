<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeShiftSchedule;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\ExternalApiResponse;
use App\Support\ExternalEmployeeAccess;
use App\Support\ExternalPeriod;
use App\Support\ExternalReadPagination;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExternalEmployeeController extends Controller
{
    private const FIELDS = ['id', 'employee_code', 'first_name', 'last_name', 'nickname', 'branch_id', 'division_id', 'department_id',
        'position_id', 'manager_id', 'employment_type', 'employment_status', 'started_on', 'probation_ends_on', 'resigned_on', 'updated_at'];

    public function index(Request $request): JsonResponse
    {
        $org = $request->attributes->get('integration_key')->org_id;
        $rules = ['search' => ['sometimes', 'string', 'max:100'],
            'employment_status' => ['sometimes', Rule::in(['probation', 'active', 'suspended', 'resigned', 'terminated'])],
            'employment_type' => ['sometimes', Rule::in(['monthly', 'daily', 'hourly', 'contractor'])]];
        foreach (['branch_id' => 'branches', 'division_id' => 'divisions', 'department_id' => 'departments', 'position_id' => 'positions'] as $field => $table) {
            $rules[$field] = ['sometimes', 'uuid', Rule::exists($table, 'id')->where('org_id', $org)->whereNull('deleted_at')];
        }
        $filters = ExternalReadPagination::filters($request, $rules);
        $query = $this->directory($request);
        foreach (array_diff(array_keys($rules), ['search']) as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['search'])) {
            $query->where(fn ($q) => $q->where('employee_code', 'like', '%'.$filters['search'].'%')->orWhere('first_name', 'like', '%'.$filters['search'].'%')->orWhere('last_name', 'like', '%'.$filters['search'].'%'));
        }

        return ExternalReadPagination::page($request, $query, self::FIELDS, $filters, fn ($employee) => $this->profile($employee));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        ExternalReadPagination::filters($request, ['limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited']]);
        $employee = $this->directory($request)->whereKey($id)->firstOrFail(self::FIELDS);

        return $this->response($request, $this->profile($employee));
    }

    public function operational(Request $request, string $id, string $resource): JsonResponse
    {
        $employee = ExternalEmployeeAccess::query($request)->whereKey($id)->firstOrFail(['id', 'user_id', 'org_id']);
        if (str_starts_with($resource, 'leave-') && ! $employee->user_id) {
            return ExternalApiResponse::error($request, 409, 'unsupported_employee_coverage', 'Leave records require a linked ERP account. No zero balance is implied.');
        }
        if ($resource === 'leave-balances') {
            return $this->balances($request, $employee);
        }
        $filters = ExternalReadPagination::filters($request, array_merge(ExternalPeriod::rules(), ['updated_since' => ['prohibited']]));
        [$start, $end] = ExternalPeriod::resolve($request, $filters);
        if ($resource === 'attendance') {
            $query = Attendance::where('org_id', $employee->org_id)->whereBetween('work_date', [$start, $end])
                ->where(fn ($q) => $q->where('employee_id', $employee->id)->orWhere(fn ($legacy) => $legacy->whereNull('employee_id')->whereNotNull('user_id')->where('user_id', $employee->user_id)))
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $employee->user_id));
            $fields = ['id', 'work_date', 'clock_in_at', 'clock_out_at', 'late_minutes', 'early_leave_minutes', 'worked_minutes',
                'ot_normal_minutes', 'ot_holiday_minutes', 'ot_special_minutes', 'is_reconciled', 'is_absent', 'updated_at'];

            return ExternalReadPagination::page($request, $query, $fields, $filters, null, false);
        }
        if ($resource === 'roster') {
            $query = EmployeeShiftSchedule::where('org_id', $employee->org_id)->where('employee_id', $employee->id)->whereBetween('schedule_date', [$start, $end])
                ->where(fn ($q) => $q->whereHas('shift', fn ($shift) => $shift->where('org_id', $employee->org_id))
                    ->orWhere(fn ($off) => $off->where('is_day_off', true)->whereNull('employee_shift_id')))
                ->with(['shift' => fn ($q) => $q->where('org_id', $employee->org_id)->select('id', 'starts_at', 'ends_at', 'break_minutes')]);
            $fields = ['id', 'employee_shift_id', 'schedule_date', 'is_day_off', 'updated_at'];

            return ExternalReadPagination::page($request, $query, $fields, $filters, fn ($row) => ExternalReadPagination::fields($row, ['id', 'schedule_date', 'is_day_off', 'updated_at']) + [
                'shift' => $row->shift?->only(['id', 'starts_at', 'ends_at', 'break_minutes']),
            ], false);
        }
        abort_unless($resource === 'leave-requests', 404);
        $query = LeaveRequest::where('org_id', $employee->org_id)->where('user_id', $employee->user_id)
            ->where('starts_on', '<=', $end)->where('ends_on', '>=', $start)
            ->whereIn('leave_type_id', LeaveType::where('org_id', $employee->org_id)->select('id'));

        return ExternalReadPagination::page($request, $query, ['id', 'starts_on', 'ends_on', 'total_days', 'status', 'updated_at'], $filters,
            fn ($row) => ExternalReadPagination::fields($row, ['id', 'starts_on', 'ends_on', 'status', 'updated_at']) + [
                'total_days' => (string) BigDecimal::of((string) $row->total_days)->toScale(2, RoundingMode::HalfUp),
            ], false);
    }

    private function balances(Request $request, Employee $employee): JsonResponse
    {
        $filters = ExternalReadPagination::filters($request, ['updated_since' => ['prohibited']]);
        $year = now($request->attributes->get('integration_key')->organization->timezone ?: config('app.timezone'))->year;
        $query = LeaveBalance::where('org_id', $employee->org_id)->where('user_id', $employee->user_id)->where('leave_year', $year)
            ->whereIn('leave_type_id', LeaveType::where('org_id', $employee->org_id)->select('id'));
        $fields = ['id', 'leave_type_id', 'leave_year', 'opening_days', 'accrued_days', 'carry_over_days', 'used_days', 'updated_at'];

        return ExternalReadPagination::page($request, $query, $fields, $filters, function ($row) {
            $data = $row->only(['id', 'leave_type_id', 'leave_year', 'updated_at']);
            $remaining = BigDecimal::zero();
            foreach (['opening_days', 'accrued_days', 'carry_over_days', 'used_days'] as $field) {
                $value = BigDecimal::of((string) $row->$field)->toScale(2, RoundingMode::HalfUp);
                $data[$field] = (string) $value;
                $remaining = $field === 'used_days' ? $remaining->minus($value) : $remaining->plus($value);
            }
            $data['remaining_days'] = (string) $remaining->toScale(2);
            $data['as_of'] = now()->toIso8601String();

            return $data;
        }, false);
    }

    private function directory(Request $request)
    {
        return ExternalEmployeeAccess::query($request)->with(['manager' => fn ($q) => $q->whereIn('id', ExternalEmployeeAccess::query($request)->select('id'))->select('id')]);
    }

    private function profile(Employee $employee): array
    {
        $data = $employee->only(self::FIELDS);
        $data['manager_id'] = $employee->manager?->id;
        foreach (['started_on', 'probation_ends_on', 'resigned_on'] as $field) {
            $data[$field] = $employee->$field?->toDateString();
        }

        return $data;
    }

    private function response(Request $request, array $data): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => ['api_version' => 'v1',
            'request_id' => ExternalApiResponse::requestId($request), 'generated_at' => now()->toIso8601String(), 'visibility' => 'credential_hr_policy']]);
    }
}
