<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceRawLog;
use App\Models\AuditLog;
use App\Models\Department;
use App\Services\AttendanceReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceImportController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $canManage = $actor->hasPermissionCode('hr.attendance.import');

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'department_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(['all', 'missing_punch', 'late', 'ot', 'absent', 'reconciled'])],
        ]);

        $date = $filters['date'] ?? now()->toDateString();
        $status = $filters['status'] ?? 'all';

        $attendances = Attendance::where('org_id', $actor->org_id)
            ->whereDate('work_date', $date)
            ->when($filters['department_id'] ?? null, fn ($q, $deptId) => $q->whereHas('employee', fn ($emp) => $emp->where('department_id', $deptId)))
            ->when($status === 'missing_punch', fn ($q) => $q->whereNotNull('clock_in_at')->whereNull('clock_out_at'))
            ->when($status === 'late', fn ($q) => $q->where('late_minutes', '>', 0))
            ->when($status === 'ot', fn ($q) => $q->where(fn ($sub) => $sub->where('ot_normal_minutes', '>', 0)->orWhere('ot_holiday_minutes', '>', 0)))
            ->when($status === 'absent', fn ($q) => $q->where('is_absent', true))
            ->when($status === 'reconciled', fn ($q) => $q->where('is_reconciled', true))
            ->with(['employee:id,employee_code,first_name,last_name,department_id', 'employee.department:id,name', 'shift:id,code,name', 'correctedByUser:id,name'])
            ->orderBy('work_date')
            ->get();

        $recentLogs = AttendanceRawLog::where('org_id', $actor->org_id)
            ->latest('punch_time')
            ->limit(50)
            ->get();

        return Inertia::render('Hr/AttendanceImport', [
            'attendances' => $attendances,
            'recentLogs' => $recentLogs,
            'departments' => Department::where('org_id', $actor->org_id)->where('status', 'active')->orderBy('code')->get(['id', 'name']),
            'filters' => [
                'date' => $date,
                'department_id' => $filters['department_id'] ?? '',
                'status' => $status,
            ],
            'canManage' => $canManage,
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['employee_code', 'punch_time', 'punch_type', 'device_id']);
            fputcsv($out, ['EMP-001', Carbon::now()->format('Y-m-d 08:55:00'), 'in', 'BIOMETRIC-01']);
            fputcsv($out, ['EMP-001', Carbon::now()->format('Y-m-d 18:05:00'), 'out', 'BIOMETRIC-01']);
            fclose($out);
        }, 'attendance_import_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, AttendanceReconciliationService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // 20MB limit
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $allowed = ['csv', 'txt', 'tsv', 'xlsx', 'xls', 'xml'];
        abort_unless(in_array($ext, $allowed, true), 422, 'The file must be a file of type: csv, txt, tsv, xlsx, xls, xml.');

        $rows = $service->parseUploadedFile($file);

        abort_if(empty($rows), 422, 'Uploaded file is empty or missing required headers (employee_code, punch_time).');

        $result = $service->importRawLogs($request->user()->org_id, $rows);

        AuditLog::create([
            'org_id' => $request->user()->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => 'hr.attendance.import_raw',
            'entity_type' => 'attendance_raw_log',
            'entity_id' => $result['batch_id'],
            'after_json' => [
                'total' => $result['total'],
                'valid' => $result['valid'],
                'errors_count' => count($result['errors']),
            ],
            'request_id' => (string) Str::uuid(),
        ]);

        $message = "Imported {$result['valid']} punch records.";
        if (! empty($result['errors'])) {
            return back()->with('success', $message)->withErrors(['import_errors' => array_slice($result['errors'], 0, 5)]);
        }

        return back()->with('success', $message);
    }

    public function reconcile(Request $request, AttendanceReconciliationService $service): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')->where('org_id', $orgId)],
        ]);

        $stats = $service->reconcilePeriod($orgId, $data['start_date'], $data['end_date'], $data['department_id'] ?? null);

        AuditLog::create([
            'org_id' => $orgId,
            'actor_user_id' => $request->user()->id,
            'action' => 'hr.attendance.reconcile',
            'entity_type' => 'attendance',
            'entity_id' => (string) Str::uuid(),
            'after_json' => $data + $stats,
            'request_id' => (string) Str::uuid(),
        ]);

        return back()->with('success', "Reconciled {$stats['reconciled_days']} records: {$stats['late_records']} late, {$stats['ot_records']} OT, {$stats['absent_records']} absent.");
    }

    public function correct(Request $request, Attendance|string $attendance): RedirectResponse
    {
        $attendance = $attendance instanceof Attendance ? $attendance : Attendance::findOrFail($attendance);
        abort_unless($attendance->org_id === $request->user()->org_id, 404);

        $data = $request->validate([
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'correction_reason' => ['required', 'string', 'max:500'],
            'is_absent' => ['boolean'],
        ]);

        $before = $attendance->only(['clock_in_at', 'clock_out_at', 'is_absent', 'late_minutes', 'early_leave_minutes']);

        $clockIn = $data['clock_in_at'] ? Carbon::parse($data['clock_in_at']) : null;
        $clockOut = $data['clock_out_at'] ? Carbon::parse($data['clock_out_at']) : null;
        $isAbsent = $request->boolean('is_absent', false);

        $lateMinutes = 0;
        $earlyMinutes = 0;
        $workedMinutes = 0;
        $otNormalMinutes = 0;

        if ($clockIn && $clockOut && ! $isAbsent) {
            $shift = $attendance->shift;
            $break = $shift?->break_minutes ?: 0;
            $raw = abs((int) $clockOut->diffInMinutes($clockIn));
            $workedMinutes = max(0, $raw - $break);

            if ($shift) {
                $dateStr = Carbon::parse($attendance->work_date)->toDateString();
                $shiftStart = Carbon::parse($dateStr.' '.$shift->starts_at);
                $shiftEnd = Carbon::parse($dateStr.' '.$shift->ends_at);

                if ($clockIn->gt($shiftStart)) {
                    $lateMinutes = abs((int) $clockIn->diffInMinutes($shiftStart));
                }
                if ($clockOut->lt($shiftEnd)) {
                    $earlyMinutes = abs((int) $shiftEnd->diffInMinutes($clockOut));
                }
                if ($clockOut->gt($shiftEnd)) {
                    $otNormalMinutes = abs((int) $clockOut->diffInMinutes($shiftEnd));
                }
            }
        }

        $attendance->update([
            'clock_in_at' => $clockIn,
            'clock_out_at' => $clockOut,
            'is_absent' => $isAbsent,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyMinutes,
            'worked_minutes' => $workedMinutes,
            'ot_normal_minutes' => $otNormalMinutes,
            'correction_reason' => $data['correction_reason'],
            'corrected_by' => $request->user()->id,
            'is_reconciled' => true,
        ]);

        AuditLog::create([
            'org_id' => $attendance->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => 'hr.attendance.correct',
            'entity_type' => 'attendance',
            'entity_id' => $attendance->id,
            'after_json' => ['before' => $before, 'after' => $attendance->fresh()->toArray(), 'reason' => $data['correction_reason']],
            'request_id' => (string) Str::uuid(),
        ]);

        return back()->with('success', 'Attendance record corrected.');
    }
}
