<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\AuditLog;
use App\Models\EmployeeShift;
use App\Models\EmployeeWorkProfile;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\HrAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HrController extends Controller
{
    public function index(Request $request, HrAttendanceService $hr): Response
    {
        $user = $request->user();
        $canManage = $user->hasPermissionCode('hr.manage');
        $teamIds = EmployeeWorkProfile::where('org_id', $user->org_id)->where('manager_user_id', $user->id)->pluck('user_id');
        $visibleUserIds = $canManage ? User::where('org_id', $user->org_id)->pluck('id') : collect([$user->id])->merge($user->hasPermissionCode('hr.team.view') ? $teamIds : []);

        return Inertia::render('Hr/Index', [
            'workProfile' => EmployeeWorkProfile::where('org_id', $user->org_id)->where('user_id', $user->id)->with(['shift:id,name,starts_at,ends_at,work_minutes_per_day', 'manager:id,name'])->first(),
            'attendance' => Attendance::where('org_id', $user->org_id)->whereIn('user_id', $visibleUserIds)->with('user:id,name', 'shift:id,name,work_minutes_per_day')->latest('clock_in_at')->limit(100)->get(),
            'leaveBalances' => LeaveBalance::where('org_id', $user->org_id)->where('user_id', $user->id)->with('leaveType:id,name,is_paid')->orderByDesc('leave_year')->get(),
            'leaveRequests' => LeaveRequest::where('org_id', $user->org_id)->whereIn('user_id', $visibleUserIds)->with(['user:id,name', 'leaveType:id,name,is_paid'])->latest('starts_on')->limit(100)->get(),
            'summaries' => AttendanceSummary::where('org_id', $user->org_id)->whereIn('user_id', $visibleUserIds)->with('user:id,name')->latest('period_end')->limit(100)->get(),
            'shifts' => EmployeeShift::where('org_id', $user->org_id)->orderBy('code')->get(),
            'leaveTypes' => LeaveType::where('org_id', $user->org_id)->where('is_active', true)->orderBy('name')->get(),
            'holidays' => Holiday::where('org_id', $user->org_id)->orderBy('holiday_date')->get(),
            'users' => $canManage ? User::where('org_id', $user->org_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']) : [],
            'attendancePolicy' => $hr->attendancePolicy($user->org_id),
            'can' => ['manage' => $canManage, 'summaryManage' => $user->hasPermissionCode('hr.summary.manage'), 'teamView' => $user->hasPermissionCode('hr.team.view')],
        ]);
    }

    public function clockIn(Request $request, HrAttendanceService $hr): RedirectResponse
    {
        $data = $request->validate($this->attendanceRules());
        $attendance = $hr->clockIn($request->user(), $data, $request->ip());
        $this->audit($request, 'attendance.clock_in', 'attendance', $attendance->id, ['work_date' => $attendance->work_date?->toDateString()]);

        return back()->with('success', 'Clock-in recorded.');
    }

    public function clockOut(Request $request, HrAttendanceService $hr): RedirectResponse
    {
        $data = $request->validate($this->attendanceRules());
        $attendance = $hr->clockOut($request->user(), $data, $request->ip());
        $this->audit($request, 'attendance.clock_out', 'attendance', $attendance->id, ['work_date' => $attendance->work_date?->toDateString()]);

        return back()->with('success', 'Clock-out recorded.');
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30'], 'name' => ['required', 'string', 'max:100'], 'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i'], 'break_minutes' => ['required', 'integer', 'min:0', 'max:720'], 'work_minutes_per_day' => ['required', 'integer', 'min:1', 'max:1440'], 'is_active' => ['boolean']]);
        $shift = EmployeeShift::updateOrCreate(['org_id' => $request->user()->org_id, 'code' => $data['code']], array_merge($data, ['org_id' => $request->user()->org_id, 'is_active' => $request->boolean('is_active', true)]));
        $this->audit($request, 'hr.shift.upsert', 'employee_shift', $shift->id, $shift->only(['code', 'name']));

        return back()->with('success', 'Shift saved.');
    }

    public function storeWorkProfile(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate(['user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('org_id', $orgId)], 'employee_shift_id' => ['nullable', 'uuid', Rule::exists('employee_shifts', 'id')->where('org_id', $orgId)], 'manager_user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('org_id', $orgId)], 'employee_code' => ['nullable', 'string', 'max:50'], 'employment_type' => ['required', Rule::in(['employee', 'contractor', 'intern'])], 'started_on' => ['nullable', 'date'], 'ended_on' => ['nullable', 'date', 'after_or_equal:started_on']]);
        if (($data['manager_user_id'] ?? null) === $data['user_id']) {
            return back()->withErrors(['manager_user_id' => 'An employee cannot be their own manager.']);
        }
        $profile = EmployeeWorkProfile::updateOrCreate(['org_id' => $orgId, 'user_id' => $data['user_id']], array_merge($data, ['org_id' => $orgId]));
        $this->audit($request, 'hr.work_profile.upsert', 'employee_work_profile', $profile->id, $profile->only(['user_id', 'employee_code']));

        return back()->with('success', 'Employee work profile saved.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $data = $request->validate(['holiday_date' => ['required', 'date'], 'name' => ['required', 'string', 'max:150']]);
        $holiday = Holiday::updateOrCreate(['org_id' => $request->user()->org_id, 'holiday_date' => $data['holiday_date']], array_merge($data, ['org_id' => $request->user()->org_id]));
        $this->audit($request, 'hr.holiday.upsert', 'holiday', $holiday->id, $holiday->only(['holiday_date', 'name']));

        return back()->with('success', 'Holiday saved.');
    }

    public function storeLeaveType(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30'], 'name' => ['required', 'string', 'max:100'], 'annual_entitlement_days' => ['required', 'numeric', 'min:0', 'max:366'], 'carry_over_limit_days' => ['required', 'numeric', 'min:0', 'max:366'], 'is_paid' => ['boolean'], 'is_active' => ['boolean']]);
        $type = LeaveType::updateOrCreate(['org_id' => $request->user()->org_id, 'code' => $data['code']], array_merge($data, ['org_id' => $request->user()->org_id, 'is_paid' => $request->boolean('is_paid'), 'is_active' => $request->boolean('is_active', true)]));
        $this->audit($request, 'hr.leave_type.upsert', 'leave_type', $type->id, $type->only(['code', 'name', 'is_paid']));

        return back()->with('success', 'Leave type saved.');
    }

    public function storeLeaveBalance(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate(['user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('org_id', $orgId)], 'leave_type_id' => ['required', 'uuid', Rule::exists('leave_types', 'id')->where('org_id', $orgId)], 'leave_year' => ['required', 'integer', 'min:2000', 'max:2100'], 'opening_days' => ['required', 'numeric', 'min:0'], 'accrued_days' => ['required', 'numeric', 'min:0'], 'carry_over_days' => ['required', 'numeric', 'min:0']]);
        $balance = LeaveBalance::updateOrCreate(['org_id' => $orgId, 'user_id' => $data['user_id'], 'leave_type_id' => $data['leave_type_id'], 'leave_year' => $data['leave_year']], array_merge($data, ['org_id' => $orgId]));
        $this->audit($request, 'hr.leave_balance.upsert', 'leave_balance', $balance->id, $balance->only(['user_id', 'leave_type_id', 'leave_year']));

        return back()->with('success', 'Leave balance saved.');
    }

    public function storeLeaveRequest(Request $request, HrAttendanceService $hr): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate(['leave_type_id' => ['required', 'uuid', Rule::exists('leave_types', 'id')->where('org_id', $orgId)->where('is_active', true)], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $days = $hr->businessDays($orgId, $data['starts_on'], $data['ends_on']);
        if ($days <= 0) {
            return back()->withErrors(['starts_on' => 'The leave range has no working days.']);
        }
        $leave = LeaveRequest::create(array_merge($data, ['org_id' => $orgId, 'user_id' => $request->user()->id, 'total_days' => $days, 'status' => 'draft']));
        $this->audit($request, 'hr.leave_request.create', 'leave_request', $leave->id, $leave->only(['starts_on', 'ends_on', 'total_days']));

        return back()->with('success', 'Leave request saved as draft.');
    }

    public function submitLeaveRequest(Request $request, LeaveRequest $leaveRequest, HrAttendanceService $hr): RedirectResponse
    {
        $this->assertOwn($request, $leaveRequest);
        $leave = $hr->submitLeave($leaveRequest);
        $this->audit($request, 'hr.leave_request.submit', 'leave_request', $leave->id, ['total_days' => $leave->total_days]);

        return back()->with('success', 'Leave request submitted. Approval is handled in Phase 20.');
    }

    public function cancelLeaveRequest(Request $request, LeaveRequest $leaveRequest, HrAttendanceService $hr): RedirectResponse
    {
        $this->assertOwn($request, $leaveRequest);
        $leave = $hr->cancelLeave($leaveRequest);
        $this->audit($request, 'hr.leave_request.cancel', 'leave_request', $leave->id, []);

        return back()->with('success', 'Leave request cancelled and balance restored.');
    }

    public function storeSummary(Request $request, HrAttendanceService $hr): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate(['user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('org_id', $orgId)], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'cutoff_date' => ['required', 'date', 'after_or_equal:period_start']]);
        $summary = $hr->createSummary($orgId, $data['user_id'], $data['period_start'], $data['period_end'], $data['cutoff_date']);
        $this->audit($request, 'hr.attendance_summary.create', 'attendance_summary', $summary->id, $summary->only(['period_start', 'period_end', 'cutoff_date']));

        return back()->with('success', 'Attendance summary created as draft.');
    }

    public function lockSummary(Request $request, AttendanceSummary $attendanceSummary): RedirectResponse
    {
        $this->assertOrg($request, $attendanceSummary->org_id);
        if ($attendanceSummary->status !== 'draft') {
            return back()->withErrors(['summary' => 'Only draft summaries can be locked.']);
        }
        $attendanceSummary->update(['status' => 'locked', 'locked_by' => $request->user()->id, 'locked_at' => now()]);
        $this->audit($request, 'hr.attendance_summary.lock', 'attendance_summary', $attendanceSummary->id, ['cutoff_date' => $attendanceSummary->cutoff_date?->toDateString()]);

        return back()->with('success', 'Summary locked. It is ready for a future payroll import and has not posted payroll or GL.');
    }

    public function reverseSummary(Request $request, AttendanceSummary $attendanceSummary, HrAttendanceService $hr): RedirectResponse
    {
        $this->assertOrg($request, $attendanceSummary->org_id);
        if ($attendanceSummary->status !== 'locked') {
            return back()->withErrors(['summary' => 'Only locked summaries can be reversed.']);
        }

        return DB::transaction(function () use ($request, $attendanceSummary, $hr): RedirectResponse {
            $attendanceSummary->update(['status' => 'reversed']);
            $replacement = $hr->createSummary($attendanceSummary->org_id, $attendanceSummary->user_id, $attendanceSummary->period_start->toDateString(), $attendanceSummary->period_end->toDateString(), $attendanceSummary->cutoff_date->toDateString());
            $replacement->update(['reversal_of_id' => $attendanceSummary->id]);
            $this->audit($request, 'hr.attendance_summary.reverse', 'attendance_summary', $attendanceSummary->id, ['replacement_id' => $replacement->id]);

            return back()->with('success', 'Locked summary reversed. A replacement draft was created.');
        });
    }

    private function attendanceRules(): array
    {
        return ['source' => ['nullable', Rule::in(['web', 'mobile'])], 'privacy_consent' => ['nullable', 'boolean'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'gps_accuracy_meters' => ['nullable', 'integer', 'min:0', 'max:100000']];
    }

    private function assertOwn(Request $request, LeaveRequest $leave): void
    {
        $this->assertOrg($request, $leave->org_id);
        abort_unless($leave->user_id === $request->user()->id, 403);
    }

    private function assertOrg(Request $request, string $orgId): void
    {
        abort_unless($orgId === $request->user()->org_id, 404);
    }

    private function audit(Request $request, string $action, string $type, string $id, array $after): void
    {
        AuditLog::create(['org_id' => $request->user()->org_id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'after_json' => $after, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'request_id' => (string) Str::uuid()]);
    }
}
