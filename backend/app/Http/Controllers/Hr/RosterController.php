<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\EmployeeShiftSchedule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RosterController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $canManage = $actor->hasPermissionCode('hr.shifts.manage');

        $monthStr = $request->query('month', now()->format('Y-m'));
        try {
            $month = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();
        } catch (\Throwable) {
            $month = now()->startOfMonth();
            $monthStr = $month->format('Y-m');
        }

        $startDate = $month->copy()->startOfMonth()->toDateString();
        $endDate = $month->copy()->endOfMonth()->toDateString();

        $branchId = $request->query('branch_id');
        $departmentId = $request->query('department_id');

        $employees = Employee::where('org_id', $actor->org_id)
            ->whereIn('employment_status', ['probation', 'active'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with(['branch:id,name', 'department:id,name', 'workProfile.shift:id,code,name,starts_at,ends_at'])
            ->orderBy('employee_code')
            ->get();

        $schedules = EmployeeShiftSchedule::where('org_id', $actor->org_id)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('schedule_date', [$startDate, $endDate])
            ->with('shift:id,code,name,starts_at,ends_at')
            ->get()
            ->groupBy('employee_id');

        $shifts = EmployeeShift::where('org_id', $actor->org_id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'starts_at', 'ends_at', 'break_minutes', 'work_minutes_per_day', 'ot_rate_normal', 'ot_rate_holiday', 'grace_period_minutes', 'overtime_starts_after_minutes']);

        return Inertia::render('Hr/Roster', [
            'month' => $monthStr,
            'daysInMonth' => $month->daysInMonth,
            'employees' => $employees->map(function (Employee $emp) use ($schedules) {
                $empSchedules = $schedules->get($emp->id, collect())->keyBy(fn ($s) => Carbon::parse($s->schedule_date)->toDateString());

                return [
                    'id' => $emp->id,
                    'code' => $emp->employee_code,
                    'name' => trim("{$emp->first_name} {$emp->last_name}"),
                    'position' => $emp->position_title,
                    'department' => $emp->department?->name,
                    'branch' => $emp->branch?->name,
                    'default_shift' => $emp->workProfile?->shift,
                    'schedules' => $empSchedules,
                ];
            }),
            'shifts' => $shifts,
            'branches' => Branch::where('org_id', $actor->org_id)->where('status', 'active')->orderBy('code')->get(['id', 'name']),
            'departments' => Department::where('org_id', $actor->org_id)->where('status', 'active')->orderBy('code')->get(['id', 'branch_id', 'name']),
            'filters' => [
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'month' => $monthStr,
            ],
            'canManage' => $canManage,
        ]);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'employee_id' => ['required', 'uuid', Rule::exists('employees', 'id')->where('org_id', $orgId)],
            'schedule_date' => ['required', 'date'],
            'employee_shift_id' => ['nullable', 'uuid', Rule::exists('employee_shifts', 'id')->where('org_id', $orgId)],
            'is_day_off' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $scheduleDate = Carbon::parse($data['schedule_date'])->toDateString();
        $schedule = EmployeeShiftSchedule::where('org_id', $orgId)
            ->where('employee_id', $data['employee_id'])
            ->whereDate('schedule_date', $scheduleDate)
            ->first();

        $payload = [
            'employee_shift_id' => $data['is_day_off'] ? null : ($data['employee_shift_id'] ?? null),
            'is_day_off' => $request->boolean('is_day_off', false),
            'note' => $data['note'] ?? null,
        ];

        if ($schedule) {
            $schedule->update($payload);
        } else {
            $schedule = EmployeeShiftSchedule::create($payload + [
                'org_id' => $orgId,
                'employee_id' => $data['employee_id'],
                'schedule_date' => $scheduleDate,
            ]);
        }

        $this->audit($request, 'hr.roster.set_schedule', $schedule, ['schedule_date' => Carbon::parse($schedule->schedule_date)->toDateString()]);

        return back()->with('success', 'Schedule updated.');
    }

    public function batchAssign(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'branch_id' => ['nullable', 'uuid', Rule::exists('branches', 'id')->where('org_id', $orgId)],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')->where('org_id', $orgId)],
            'employee_shift_id' => ['nullable', 'uuid', Rule::exists('employee_shifts', 'id')->where('org_id', $orgId)],
            'is_day_off' => ['boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'], // 0=Sunday, 6=Saturday
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $employees = Employee::where('org_id', $orgId)
            ->whereIn('employment_status', ['probation', 'active'])
            ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($data['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->pluck('id');

        abort_if($employees->isEmpty(), 422, 'No active employees matched the filter criteria.');

        $isDayOff = $request->boolean('is_day_off', false);
        $shiftId = $isDayOff ? null : ($data['employee_shift_id'] ?? null);
        $daysOfWeek = $data['days_of_week'] ?? null;
        $period = CarbonPeriod::create($data['start_date'], $data['end_date']);

        DB::transaction(function () use ($orgId, $employees, $period, $daysOfWeek, $shiftId, $isDayOff, $data) {
            foreach ($period as $date) {
                if ($daysOfWeek !== null && ! in_array($date->dayOfWeek, $daysOfWeek, true)) {
                    continue;
                }
                $dateStr = $date->toDateString();
                foreach ($employees as $employeeId) {
                    $existing = EmployeeShiftSchedule::where('org_id', $orgId)
                        ->where('employee_id', $employeeId)
                        ->whereDate('schedule_date', $dateStr)
                        ->first();
                    $schedPayload = [
                        'employee_shift_id' => $shiftId,
                        'is_day_off' => $isDayOff,
                        'note' => $data['note'] ?? 'Batch roster assignment',
                    ];
                    if ($existing) {
                        $existing->update($schedPayload);
                    } else {
                        EmployeeShiftSchedule::create($schedPayload + [
                            'org_id' => $orgId,
                            'employee_id' => $employeeId,
                            'schedule_date' => $dateStr,
                        ]);
                    }
                }
            }
        });

        AuditLog::create([
            'org_id' => $orgId,
            'actor_user_id' => $request->user()->id,
            'action' => 'hr.roster.batch_assign',
            'entity_type' => 'employee_shift_schedule',
            'entity_id' => (string) Str::uuid(),
            'after_json' => [
                'employee_count' => $employees->count(),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'shift_id' => $shiftId,
                'is_day_off' => $isDayOff,
            ],
            'request_id' => (string) Str::uuid(),
        ]);

        return back()->with('success', "Batch roster assigned for {$employees->count()} employees.");
    }

    private function audit(Request $request, string $action, EmployeeShiftSchedule $schedule, array $after): void
    {
        AuditLog::create([
            'org_id' => $schedule->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'employee_shift_schedule',
            'entity_id' => $schedule->id,
            'after_json' => $after,
            'request_id' => (string) Str::uuid(),
        ]);
    }
}
