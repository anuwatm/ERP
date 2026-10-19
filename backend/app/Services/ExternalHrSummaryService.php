<?php

namespace App\Services;

use App\Models\EmployeeShiftSchedule;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\ExternalEmployeeAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExternalHrSummaryService
{
    public function summarize(Request $request, string $domain, string $start, string $end, CarbonImmutable $now): array
    {
        return match ($domain) {
            'hr' => $this->headcount($request, $start, $end, $now),
            'attendance' => $this->attendance($request, $start, $end),
            'leaves' => $this->leaves($request, $start, $end),
            'roster' => $this->roster($request, $start, $end),
            default => throw new \LogicException('Unsupported HR summary.'),
        };
    }

    private function headcount(Request $request, string $start, string $end, CarbonImmutable $now): array
    {
        $base = ExternalEmployeeAccess::query($request);
        $groups = (clone $base)->select('employment_status', 'employment_type')->selectRaw('COUNT(*) as records_count')
            ->groupBy('employment_status', 'employment_type')->get()->map(fn ($row) => [
                'employment_status' => $row->employment_status, 'employment_type' => $row->employment_type, 'count' => (int) $row->records_count,
            ])->all();
        $employed = "employment_status IN ('probation', 'active') AND (started_on IS NULL OR started_on <= ?) AND (resigned_on IS NULL OR resigned_on >= ?)";
        $counts = (clone $base)->selectRaw('COUNT(*) as total, SUM(CASE WHEN started_on BETWEEN ? AND ? THEN 1 ELSE 0 END) as joined, SUM(CASE WHEN resigned_on BETWEEN ? AND ? THEN 1 ELSE 0 END) as departed, SUM(CASE WHEN '.$employed.' AND probation_ends_on BETWEEN ? AND ? THEN 1 ELSE 0 END) as probation, SUM(CASE WHEN '.$employed.' THEN 1 ELSE 0 END) as active_count',
            [$start, $end, $start, $end, $now->toDateString(), $now->toDateString(), $now->toDateString(), $now->addDays(30)->toDateString(), $now->toDateString(), $now->toDateString()])->first();
        $units = [];
        foreach (['branch_id', 'division_id', 'department_id', 'position_id'] as $field) {
            $rows = (clone $base)->select($field)->selectRaw('COUNT(*) as records_count')->groupBy($field)->orderBy($field)->limit(101)->get();
            $units[$field] = ['truncated' => $rows->count() > 100, 'groups' => $rows->take(100)->map(fn ($row) => [
                'id' => $row->$field, 'count' => $row->records_count >= 5 ? (int) $row->records_count : null, 'suppressed' => $row->records_count < 5,
            ])->all()];
        }

        return ['period' => ['employees_started' => (int) $counts->joined, 'employees_departed' => (int) $counts->departed],
            'snapshot' => ['total_employees' => (int) $counts->total, 'active_employees' => (int) $counts->active_count, 'by_status_and_type' => $groups,
                'probation_ends_within_30_days' => (int) $counts->probation, 'org_units' => $units, 'small_group_threshold' => 5]];
    }

    private function attendance(Request $request, string $start, string $end): array
    {
        $key = $request->attributes->get('integration_key');
        $employees = ExternalEmployeeAccess::query($request)->select('id', 'user_id');
        $rows = DB::table('attendances as a')->joinSub($employees, 'e', function ($join) {
            $join->on(function ($on) {
                $on->on('a.employee_id', '=', 'e.id')->orOn(function ($legacy) {
                    $legacy->whereNull('a.employee_id')->on('a.user_id', '=', 'e.user_id');
                });
            });
        })->where('a.org_id', $key->org_id)->whereBetween('a.work_date', [$start, $end])
            ->where(fn ($q) => $q->whereNull('a.user_id')->orWhereColumn('a.user_id', 'e.user_id'))
            ->select('a.*', 'e.id as resolved_employee_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY e.id, a.work_date ORDER BY a.is_reconciled DESC, a.updated_at DESC, a.id DESC) as row_priority');
        $sql = 'COUNT(*) as employee_workdays, COUNT(DISTINCT resolved_employee_id) as distinct_employees';
        $conditions = [
            'present_workdays' => 'is_reconciled = 1 AND is_absent = 0 AND clock_in_at IS NOT NULL AND clock_out_at IS NOT NULL',
            'late_workdays' => 'is_reconciled = 1 AND late_minutes > 0',
            'early_leave_workdays' => 'is_reconciled = 1 AND early_leave_minutes > 0',
            'confirmed_absent_workdays' => 'is_reconciled = 1 AND is_absent = 1',
            'pending_workdays' => 'is_reconciled = 0',
            'incomplete_workdays' => 'is_absent = 0 AND (clock_in_at IS NULL OR clock_out_at IS NULL)',
        ];
        foreach ($conditions as $alias => $condition) {
            $sql .= ", SUM(CASE WHEN $condition THEN 1 ELSE 0 END) as $alias";
            $sql .= ", COUNT(DISTINCT CASE WHEN $condition THEN resolved_employee_id ELSE NULL END) as {$alias}_employees";
        }
        foreach (['worked_minutes', 'late_minutes', 'early_leave_minutes', 'ot_normal_minutes', 'ot_holiday_minutes', 'ot_special_minutes'] as $field) {
            $sql .= ", SUM(CASE WHEN is_reconciled = 1 THEN $field ELSE 0 END) as $field";
        }
        $row = DB::query()->fromSub($rows, 'days')->where('row_priority', 1)->selectRaw($sql)->first();

        return ['period' => array_map(fn ($value) => (int) $value, (array) $row),
            'coverage' => ['basis' => 'employee_workdays', 'legacy_user_bridge' => true, 'missing_punch_is_absence' => false, 'minutes_basis' => 'reconciled_only']];
    }

    private function leaves(Request $request, string $start, string $end): array
    {
        $org = $request->attributes->get('integration_key')->org_id;
        $employees = ExternalEmployeeAccess::query($request);
        $linked = (clone $employees)->whereNotNull('user_id');
        $base = LeaveRequest::where('org_id', $org)->whereIn('user_id', (clone $linked)->select('user_id'))
            ->whereIn('leave_type_id', LeaveType::where('org_id', $org)->select('id'))
            ->where('starts_on', '<=', $end)->where('ends_on', '>=', $start);
        $counts = (clone $base)->select('status')->selectRaw('COUNT(*) as records_count')->groupBy('status')->get()
            ->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->records_count])->all();
        $approved = (clone $base)->where('status', 'approved');
        $distinct = (clone $approved)->distinct()->count('user_id');
        $holidays = Holiday::where('org_id', $org)->whereBetween('holiday_date', [$start, $end])->pluck('holiday_date')->map(fn ($date) => $date->toDateString())->all();
        $calendar = null;
        for ($day = CarbonImmutable::parse($start); $day->toDateString() <= $end; $day = $day->addDay()) {
            if ($day->isWeekend() || in_array($day->toDateString(), $holidays, true)) {
                continue;
            }
            $one = DB::query()->selectRaw('? as work_date', [$day->toDateString()]);
            if (! $calendar) {
                $calendar = $one;
            } else {
                $calendar->unionAll($one);
            }
        }
        $days = 0;
        if ($calendar) {
            $employeeDays = DB::query()->fromSub($calendar, 'calendar')->joinSub((clone $approved)->select('user_id', 'starts_on', 'ends_on'), 'requests', function ($join) {
                $join->on('calendar.work_date', '>=', 'requests.starts_on')->on('calendar.work_date', '<=', 'requests.ends_on');
            })->select('calendar.work_date', 'requests.user_id')->distinct();
            $days = DB::query()->fromSub($employeeDays, 'employee_days')->count();
        }

        return ['period' => ['requests_overlapping_by_status' => $counts, 'approved_distinct_employees' => $distinct,
            'approved_leave_employee_workdays' => number_format($days, 2, '.', '')],
            'coverage' => ['linked_employees' => (clone $linked)->count(), 'unsupported_non_user_employees' => (clone $employees)->whereNull('user_id')->count(),
                'day_policy' => 'weekdays_excluding_organization_holidays', 'type_breakdown' => 'withheld', 'overlap_deduplication' => 'employee_date']];
    }

    private function roster(Request $request, string $start, string $end): array
    {
        $org = $request->attributes->get('integration_key')->org_id;
        $employees = ExternalEmployeeAccess::query($request)->where(fn ($q) => $q->whereNull('started_on')->orWhere('started_on', '<=', $end))
            ->where(fn ($q) => $q->whereNull('resigned_on')->orWhere('resigned_on', '>=', $start))
            ->where(fn ($q) => $q->whereNotIn('employment_status', ['resigned', 'terminated'])->orWhereNotNull('resigned_on'));
        $query = EmployeeShiftSchedule::where('employee_shift_schedules.org_id', $org)->whereIn('employee_id', (clone $employees)->select('id'))
            ->whereBetween('schedule_date', [$start, $end])
            ->whereHas('employee', fn ($employee) => $employee->where(fn ($q) => $q->whereNull('started_on')->orWhereColumn('started_on', '<=', 'employee_shift_schedules.schedule_date'))
                ->where(fn ($q) => $q->whereNull('resigned_on')->orWhereColumn('resigned_on', '>=', 'employee_shift_schedules.schedule_date')))
            ->where(fn ($q) => $q->whereHas('shift', fn ($shift) => $shift->where('org_id', $org))
                ->orWhere(fn ($off) => $off->where('is_day_off', true)->whereNull('employee_shift_id')));
        $counts = (clone $query)->selectRaw('COUNT(*) as allocated_days, COUNT(DISTINCT employee_id) as scheduled_employees, SUM(CASE WHEN is_day_off = 1 THEN 1 ELSE 0 END) as day_off_allocations')->first();
        $eligible = (clone $employees)->count();
        $departments = (clone $query)->join('employees as e', 'e.id', '=', 'employee_shift_schedules.employee_id')->where('e.org_id', $org)
            ->select('e.department_id')->selectRaw('COUNT(*) as allocated_days, COUNT(DISTINCT employee_id) as scheduled_employees')
            ->groupBy('e.department_id')->orderBy('e.department_id')->limit(101)->get();
        $departmentCounts = $departments->take(100)->map(fn ($row) => ['department_id' => $row->department_id,
            'allocated_days' => $row->scheduled_employees >= 5 ? (int) $row->allocated_days : null,
            'scheduled_employees' => $row->scheduled_employees >= 5 ? (int) $row->scheduled_employees : null,
            'suppressed' => $row->scheduled_employees < 5]);

        return ['period' => ['allocated_days' => (int) $counts->allocated_days, 'day_off_allocations' => (int) $counts->day_off_allocations,
            'scheduled_employees' => (int) $counts->scheduled_employees, 'eligible_employees' => $eligible,
            'employees_without_any_schedule' => max(0, $eligible - (int) $counts->scheduled_employees),
            'missing_schedule_workdays' => null, 'department_allocations' => $departmentCounts, 'departments_truncated' => $departments->count() > 100],
            'coverage' => ['eligibility_basis' => 'current_status_and_recorded_employment_dates', 'missing_workdays_reason' => 'No authoritative per-employee required-workday calendar; missing schedule is not absence.']];
    }
}
