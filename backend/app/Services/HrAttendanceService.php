<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\EmployeeWorkProfile;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrAttendanceService
{
    public function attendancePolicy(string $orgId): array
    {
        $defaults = ['capture_ip' => false, 'capture_gps' => false, 'require_employee_consent' => true, 'retention_days' => 365];
        $stored = Setting::where('org_id', $orgId)->where('key', 'hr.attendance_privacy')->value('value_json') ?? [];

        return array_replace($defaults, $stored);
    }

    public function clockIn(User $user, array $data, string $ip): Attendance
    {
        return DB::transaction(function () use ($user, $data, $ip): Attendance {
            $existing = Attendance::where('org_id', $user->org_id)->where('user_id', $user->id)->whereNull('clock_out_at')->lockForUpdate()->first();
            if ($existing) {
                throw ValidationException::withMessages(['clock_in' => 'Clock-in is already open. Clock out before starting another attendance record.']);
            }
            $policy = $this->attendancePolicy($user->org_id);
            $this->guardConsent($policy, $data);
            $profile = EmployeeWorkProfile::where('org_id', $user->org_id)->where('user_id', $user->id)->first();

            return Attendance::create(array_merge($this->locationData('clock_in', $policy, $data, $ip), [
                'org_id' => $user->org_id, 'user_id' => $user->id, 'employee_shift_id' => $profile?->employee_shift_id,
                'work_date' => now()->toDateString(), 'clock_in_at' => now(), 'clock_in_source' => $data['source'] ?? 'web',
            ]));
        });
    }

    public function clockOut(User $user, array $data, string $ip): Attendance
    {
        return DB::transaction(function () use ($user, $data, $ip): Attendance {
            $attendance = Attendance::where('org_id', $user->org_id)->where('user_id', $user->id)->whereNull('clock_out_at')->latest('clock_in_at')->lockForUpdate()->first();
            if (! $attendance) {
                throw ValidationException::withMessages(['clock_out' => 'No open clock-in record was found.']);
            }
            $policy = $this->attendancePolicy($user->org_id);
            $this->guardConsent($policy, $data);
            $attendance->update(array_merge($this->locationData('clock_out', $policy, $data, $ip), ['clock_out_at' => now(), 'clock_out_source' => $data['source'] ?? 'web']));

            return $attendance->fresh();
        });
    }

    public function businessDays(string $orgId, string $startsOn, string $endsOn): float
    {
        $holidays = Holiday::where('org_id', $orgId)->whereBetween('holiday_date', [$startsOn, $endsOn])->pluck('holiday_date')->map(fn ($date) => $date->toDateString())->all();
        $days = 0;
        for ($date = Carbon::parse($startsOn); $date->lte(Carbon::parse($endsOn)); $date->addDay()) {
            if (! $date->isWeekend() && ! in_array($date->toDateString(), $holidays, true)) {
                $days++;
            }
        }

        return (float) $days;
    }

    public function submitLeave(LeaveRequest $request): LeaveRequest
    {
        return DB::transaction(function () use ($request): LeaveRequest {
            $request = LeaveRequest::lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft leave requests can be submitted.']);
            }
            $type = LeaveType::where('org_id', $request->org_id)->lockForUpdate()->findOrFail($request->leave_type_id);
            $days = $this->businessDays($request->org_id, $request->starts_on->toDateString(), $request->ends_on->toDateString());
            if ($days <= 0) {
                throw ValidationException::withMessages(['starts_on' => 'The leave range has no working days.']);
            }
            $overlap = LeaveRequest::where('org_id', $request->org_id)->where('user_id', $request->user_id)->where('status', 'submitted')->whereDate('starts_on', '<=', $request->ends_on)->whereDate('ends_on', '>=', $request->starts_on)->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => 'A submitted leave request already overlaps this range.']);
            }
            if ($type->is_paid) {
                $balance = LeaveBalance::firstOrCreate(['org_id' => $request->org_id, 'user_id' => $request->user_id, 'leave_type_id' => $type->id, 'leave_year' => $request->starts_on->year], ['opening_days' => 0, 'accrued_days' => $type->annual_entitlement_days, 'carry_over_days' => 0, 'used_days' => 0]);
                $balance = LeaveBalance::lockForUpdate()->findOrFail($balance->id);
                $available = (float) $balance->opening_days + (float) $balance->accrued_days + (float) $balance->carry_over_days - (float) $balance->used_days;
                if ($available < $days) {
                    throw ValidationException::withMessages(['leave_type_id' => 'Insufficient leave balance for this request.']);
                }
                $balance->increment('used_days', $days);
            }
            $request->update(['total_days' => $days, 'status' => 'submitted', 'submitted_at' => now()]);

            return $request->fresh();
        });
    }

    public function cancelLeave(LeaveRequest $request): LeaveRequest
    {
        return DB::transaction(function () use ($request): LeaveRequest {
            $request = LeaveRequest::lockForUpdate()->findOrFail($request->id);
            if ($request->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Only submitted leave requests can be cancelled in Phase 19.']);
            }
            $type = LeaveType::where('org_id', $request->org_id)->findOrFail($request->leave_type_id);
            if ($type->is_paid) {
                $balance = LeaveBalance::where(['org_id' => $request->org_id, 'user_id' => $request->user_id, 'leave_type_id' => $type->id, 'leave_year' => $request->starts_on->year])->lockForUpdate()->firstOrFail();
                $balance->decrement('used_days', $request->total_days);
            }
            $request->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return $request->fresh();
        });
    }

    public function createSummary(string $orgId, string $userId, string $periodStart, string $periodEnd, string $cutoffDate): AttendanceSummary
    {
        if ($cutoffDate > $periodEnd) {
            throw ValidationException::withMessages(['cutoff_date' => 'Cutoff date must be on or before period end.']);
        }
        $attendances = Attendance::where('org_id', $orgId)->where('user_id', $userId)->whereDate('work_date', '>=', $periodStart)->whereDate('work_date', '<=', $periodEnd)->whereNotNull('clock_out_at')->with('shift')->get();
        $worked = $attendances->sum(fn (Attendance $a) => abs($a->clock_in_at->diffInMinutes($a->clock_out_at)));
        $scheduled = $attendances->sum(fn (Attendance $a) => $a->shift?->work_minutes_per_day ?? 0);
        $leaves = LeaveRequest::where('org_id', $orgId)->where('user_id', $userId)->whereIn('status', ['submitted', 'approved'])->whereDate('starts_on', '<=', $periodEnd)->whereDate('ends_on', '>=', $periodStart)->with('leaveType')->get();

        return AttendanceSummary::create(['org_id' => $orgId, 'user_id' => $userId, 'period_start' => $periodStart, 'period_end' => $periodEnd, 'cutoff_date' => $cutoffDate, 'scheduled_minutes' => $scheduled, 'worked_minutes' => $worked, 'overtime_minutes' => max(0, $worked - $scheduled), 'paid_leave_days' => $leaves->where('leaveType.is_paid', true)->sum('total_days'), 'unpaid_leave_days' => $leaves->where('leaveType.is_paid', false)->sum('total_days'), 'status' => 'draft']);
    }

    private function guardConsent(array $policy, array $data): void
    {
        if (($policy['capture_ip'] || $policy['capture_gps']) && $policy['require_employee_consent'] && empty($data['privacy_consent'])) {
            throw ValidationException::withMessages(['privacy_consent' => 'Employee consent is required before optional attendance data is collected.']);
        }
        if ($policy['capture_gps'] && (! isset($data['latitude'], $data['longitude']))) {
            throw ValidationException::withMessages(['latitude' => 'Location is required by this organization attendance policy.']);
        }
    }

    private function locationData(string $prefix, array $policy, array $data, string $ip): array
    {
        $values = [];
        if ($policy['capture_ip']) {
            $values["{$prefix}_ip_hash"] = hash('sha256', $ip);
        }
        if ($policy['capture_gps']) {
            $values["{$prefix}_latitude"] = $data['latitude'];
            $values["{$prefix}_longitude"] = $data['longitude'];
            $values["{$prefix}_gps_accuracy_meters"] = $data['gps_accuracy_meters'] ?? null;
        }

        return $values;
    }
}
