<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceRawLog;
use App\Models\Employee;
use App\Models\EmployeeShiftSchedule;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceReconciliationService
{
    /**
     * Import raw punch lines from CSV or array.
     *
     * @param  array<int, array{employee_code: string, punch_time: string, punch_type?: string, device_id?: string}>  $rows
     * @return array{batch_id: string, total: int, valid: int, errors: array<int, string>}
     */
    public function importRawLogs(string $orgId, array $rows, ?string $batchId = null): array
    {
        $batchId = $batchId ?: (string) Str::uuid();
        $employees = Employee::where('org_id', $orgId)->pluck('id', 'employee_code')->all();
        $valid = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $code = trim((string) ($row['employee_code'] ?? ''));
            $punchTimeStr = trim((string) ($row['punch_time'] ?? ''));

            if ($code === '' || $punchTimeStr === '') {
                $errors[] = 'Row #'.($index + 1).': Employee code and punch time are required.';

                continue;
            }

            try {
                $punchTime = Carbon::parse($punchTimeStr);
            } catch (\Throwable $e) {
                $errors[] = 'Row #'.($index + 1).": Invalid date format for punch time [{$punchTimeStr}].";

                continue;
            }

            $empId = $employees[$code] ?? null;
            if (! $empId) {
                $errors[] = 'Row #'.($index + 1).": Employee code [{$code}] not found in organization.";

                continue;
            }

            AttendanceRawLog::create([
                'org_id' => $orgId,
                'batch_id' => $batchId,
                'employee_code' => $code,
                'employee_id' => $empId,
                'punch_time' => $punchTime,
                'punch_type' => $row['punch_type'] ?? 'auto',
                'device_id' => $row['device_id'] ?? null,
                'status' => 'raw',
            ]);
            $valid++;
        }

        return [
            'batch_id' => $batchId,
            'total' => count($rows),
            'valid' => $valid,
            'errors' => $errors,
        ];
    }

    /**
     * Parse uploaded file (CSV, TSV, XLSX, XLS/XML) into rows.
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if ($ext === 'xlsx') {
            return $this->parseXlsx($file->getRealPath());
        }

        $content = (string) file_get_contents($file->getRealPath());
        if ($ext === 'xml' || ($ext === 'xls' && str_contains($content, '<?xml'))) {
            return $this->parseXmlSpreadsheet($content);
        }

        return $this->parseCsv($content);
    }

    /**
     * Parse XLSX file into rows.
     */
    public function parseXlsx(string $path): array
    {
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xlsx_'.uniqid();
        @mkdir($tempDir, 0777, true);

        try {
            if (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive;
                if ($zip->open($path) === true) {
                    $zip->extractTo($tempDir);
                    $zip->close();
                }
            } else {
                @exec('tar -xf "'.escapeshellarg($path).'" -C "'.escapeshellarg($tempDir).'" 2>&1');
            }

            $sharedStringsFile = $tempDir.DIRECTORY_SEPARATOR.'xl'.DIRECTORY_SEPARATOR.'sharedStrings.xml';
            $sheetFile = $tempDir.DIRECTORY_SEPARATOR.'xl'.DIRECTORY_SEPARATOR.'worksheets'.DIRECTORY_SEPARATOR.'sheet1.xml';

            if (! file_exists($sheetFile)) {
                if (! class_exists(\ZipArchive::class)) {
                    throw new \RuntimeException('PHP extension "zip" is required to parse XLSX files when extraction fallback is unavailable.');
                }

                return [];
            }

            $sharedStrings = [];
            if (file_exists($sharedStringsFile)) {
                $xml = simplexml_load_file($sharedStringsFile);
                if ($xml) {
                    foreach ($xml->si as $si) {
                        if (isset($si->t)) {
                            $sharedStrings[] = (string) $si->t;
                        } elseif (isset($si->r)) {
                            $text = '';
                            foreach ($si->r as $r) {
                                $text .= (string) $r->t;
                            }
                            $sharedStrings[] = $text;
                        } else {
                            $sharedStrings[] = '';
                        }
                    }
                }
            }

            $sheetXml = simplexml_load_file($sheetFile);
            if (! $sheetXml || ! isset($sheetXml->sheetData->row)) {
                return [];
            }

            $rows = [];
            $header = [];
            foreach ($sheetXml->sheetData->row as $row) {
                $rowCells = [];
                foreach ($row->c as $c) {
                    $val = (string) $c->v;
                    $type = (string) $c['t'];
                    if ($type === 's' && isset($sharedStrings[(int) $val])) {
                        $val = $sharedStrings[(int) $val];
                    } elseif ($type === 'inlineStr' && isset($c->is->t)) {
                        $val = (string) $c->is->t;
                    }
                    $rowCells[] = trim($val);
                }

                if (empty($header)) {
                    $header = array_map(fn ($col) => strtolower(trim((string) $col)), $rowCells);

                    continue;
                }

                $codeIdx = array_search('employee_code', $header, true);
                if ($codeIdx === false) {
                    $codeIdx = array_search('code', $header, true);
                }
                $timeIdx = array_search('punch_time', $header, true);
                if ($timeIdx === false) {
                    $timeIdx = array_search('time', $header, true);
                }
                $typeIdx = array_search('punch_type', $header, true);
                $deviceIdx = array_search('device_id', $header, true);

                if ($codeIdx === false || $timeIdx === false) {
                    continue;
                }

                $code = $rowCells[$codeIdx] ?? '';
                $time = $rowCells[$timeIdx] ?? '';
                if ($code !== '' && $time !== '') {
                    $rows[] = [
                        'employee_code' => $code,
                        'punch_time' => $time,
                        'punch_type' => $typeIdx !== false ? ($rowCells[$typeIdx] ?? 'auto') : 'auto',
                        'device_id' => $deviceIdx !== false ? ($rowCells[$deviceIdx] ?? null) : null,
                    ];
                }
            }

            return $rows;
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    /**
     * Parse XML Spreadsheet (SpreadsheetML) into rows.
     */
    public function parseXmlSpreadsheet(string $content): array
    {
        $xml = @simplexml_load_string($content);
        if (! $xml) {
            return [];
        }

        $xml->registerXPathNamespace('ss', 'urn:schemas-microsoft-com:office:spreadsheet');
        $xmlRows = $xml->xpath('//ss:Row') ?: $xml->xpath('//Row');

        if (empty($xmlRows)) {
            return [];
        }

        $header = [];
        $rows = [];
        foreach ($xmlRows as $row) {
            $cells = [];
            foreach ($row->children() as $cell) {
                $data = $cell->Data ?: $cell->children('ss', true)->Data;
                $cells[] = trim((string) $data);
            }

            if (empty($header)) {
                $header = array_map(fn ($col) => strtolower(trim((string) $col)), $cells);

                continue;
            }

            $codeIdx = array_search('employee_code', $header, true);
            if ($codeIdx === false) {
                $codeIdx = array_search('code', $header, true);
            }
            $timeIdx = array_search('punch_time', $header, true);
            if ($timeIdx === false) {
                $timeIdx = array_search('time', $header, true);
            }
            $typeIdx = array_search('punch_type', $header, true);
            $deviceIdx = array_search('device_id', $header, true);

            if ($codeIdx === false || $timeIdx === false) {
                continue;
            }

            $code = $cells[$codeIdx] ?? '';
            $time = $cells[$timeIdx] ?? '';
            if ($code !== '' && $time !== '') {
                $rows[] = [
                    'employee_code' => $code,
                    'punch_time' => $time,
                    'punch_type' => $typeIdx !== false ? ($cells[$typeIdx] ?? 'auto') : 'auto',
                    'device_id' => $deviceIdx !== false ? ($cells[$deviceIdx] ?? null) : null,
                ];
            }
        }

        return $rows;
    }

    /**
     * Parse uploaded CSV file content into rows.
     */
    public function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($col) => strtolower(trim((string) $col)), $header);

        $codeIdx = array_search('employee_code', $header, true);
        if ($codeIdx === false) {
            $codeIdx = array_search('code', $header, true);
        }
        $timeIdx = array_search('punch_time', $header, true);
        if ($timeIdx === false) {
            $timeIdx = array_search('time', $header, true);
        }
        $typeIdx = array_search('punch_type', $header, true);
        $deviceIdx = array_search('device_id', $header, true);

        if ($codeIdx === false || $timeIdx === false) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $rows[] = [
                'employee_code' => $cols[$codeIdx] ?? '',
                'punch_time' => $cols[$timeIdx] ?? '',
                'punch_type' => $typeIdx !== false ? ($cols[$typeIdx] ?? 'auto') : 'auto',
                'device_id' => $deviceIdx !== false ? ($cols[$deviceIdx] ?? null) : null,
            ];
        }

        return $rows;
    }

    /**
     * Reconcile attendance for an organization within a date range.
     *
     * @return array{reconciled_days: int, late_records: int, early_leave_records: int, ot_records: int, absent_records: int}
     */
    public function reconcilePeriod(string $orgId, string $startDate, string $endDate, ?string $departmentId = null): array
    {
        return DB::transaction(function () use ($orgId, $startDate, $endDate, $departmentId): array {
            $employees = Employee::where('org_id', $orgId)
                ->whereIn('employment_status', ['probation', 'active'])
                ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
                ->with(['workProfile.shift', 'user'])
                ->get();

            $holidays = Holiday::where('org_id', $orgId)
                ->whereDate('holiday_date', '>=', $startDate)
                ->whereDate('holiday_date', '<=', $endDate)
                ->pluck('holiday_date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->flip()
                ->all();

            $period = CarbonPeriod::create($startDate, $endDate);
            $stats = [
                'reconciled_days' => 0,
                'late_records' => 0,
                'early_leave_records' => 0,
                'ot_records' => 0,
                'absent_records' => 0,
            ];

            foreach ($employees as $employee) {
                // Preload shift schedules for period
                $schedules = EmployeeShiftSchedule::where('org_id', $orgId)
                    ->where('employee_id', $employee->id)
                    ->whereDate('schedule_date', '>=', $startDate)
                    ->whereDate('schedule_date', '<=', $endDate)
                    ->with('shift')
                    ->get()
                    ->keyBy(fn ($s) => Carbon::parse($s->schedule_date)->toDateString());

                foreach ($period as $date) {
                    $dateStr = $date->toDateString();
                    $schedule = $schedules->get($dateStr);
                    $shift = $schedule?->shift ?: $employee->workProfile?->shift;
                    $isDayOff = $schedule ? $schedule->is_day_off : $date->isWeekend();
                    $isHoliday = isset($holidays[$dateStr]) || (bool) ($schedule?->is_public_holiday);

                    $isOvernight = false;
                    $shiftStart = null;
                    $shiftEnd = null;

                    if ($shift) {
                        $shiftStart = Carbon::parse($dateStr.' '.$shift->starts_at);
                        $shiftEnd = Carbon::parse($dateStr.' '.$shift->ends_at);
                        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                            $shiftEnd->addDay();
                            $isOvernight = true;
                        }
                    }

                    if ($isOvernight && $shiftStart && $shiftEnd) {
                        $windowStart = $shiftStart->copy()->subHours(4)->toDateTimeString();
                        $windowEnd = $shiftEnd->copy()->addHours(6)->toDateTimeString();
                    } else {
                        $windowStart = $date->copy()->startOfDay()->toDateTimeString();
                        $windowEnd = $date->copy()->endOfDay()->toDateTimeString();
                    }

                    // Fetch punches for this employee on this date
                    $punches = AttendanceRawLog::where('org_id', $orgId)
                        ->where('employee_id', $employee->id)
                        ->whereBetween('punch_time', [$windowStart, $windowEnd])
                        ->orderBy('punch_time')
                        ->get();

                    if ($punches->isEmpty()) {
                        // If it's a scheduled workday and not a public holiday
                        if (! $isDayOff && ! $isHoliday) {
                            // Check if has approved leave request
                            $hasLeave = false;
                            if ($employee->user_id) {
                                $hasLeave = LeaveRequest::where('org_id', $orgId)
                                    ->where('user_id', $employee->user_id)
                                    ->where('status', 'submitted')
                                    ->whereDate('starts_on', '<=', $dateStr)
                                    ->whereDate('ends_on', '>=', $dateStr)
                                    ->exists();
                            }

                            if (! $hasLeave) {
                                Attendance::updateOrCreate(
                                    ['org_id' => $orgId, 'employee_id' => $employee->id, 'work_date' => $dateStr],
                                    [
                                        'user_id' => $employee->user_id,
                                        'employee_shift_id' => $shift?->id,
                                        'clock_in_at' => null,
                                        'clock_out_at' => null,
                                        'is_absent' => true,
                                        'is_reconciled' => true,
                                        'late_minutes' => 0,
                                        'early_leave_minutes' => 0,
                                        'worked_minutes' => 0,
                                        'ot_normal_minutes' => 0,
                                        'ot_holiday_minutes' => 0,
                                        'ot_special_minutes' => 0,
                                    ]
                                );
                                $stats['absent_records']++;
                                $stats['reconciled_days']++;
                            }
                        }

                        continue;
                    }

                    // We have punches
                    $clockIn = Carbon::parse($punches->first()->punch_time);
                    $clockOut = $punches->count() > 1 ? Carbon::parse($punches->last()->punch_time) : null;

                    $lateMinutes = 0;
                    $earlyMinutes = 0;
                    $workedMinutes = 0;
                    $otNormalMinutes = 0;
                    $otHolidayMinutes = 0;
                    $otSpecialMinutes = 0;

                    if ($clockOut) {
                        $rawWorked = abs((int) $clockOut->diffInMinutes($clockIn));
                        $break = $shift?->break_minutes ?: 0;
                        $workedMinutes = max(0, $rawWorked - $break);
                    }

                    if ($shift) {
                        $shiftStart = $shiftStart ?: Carbon::parse($dateStr.' '.$shift->starts_at);
                        $shiftEnd = $shiftEnd ?: Carbon::parse($dateStr.' '.$shift->ends_at);
                        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
                            $shiftEnd->addDay();
                        }
                        $grace = (int) ($shift->grace_period_minutes ?? 0);
                        $otThreshold = (int) ($shift->overtime_starts_after_minutes ?? 0);

                        // Late detection
                        if ($clockIn->gt($shiftStart->copy()->addMinutes($grace))) {
                            $lateMinutes = abs((int) $clockIn->diffInMinutes($shiftStart));
                        }

                        // Early leave detection
                        if ($clockOut && $clockOut->lt($shiftEnd)) {
                            $earlyMinutes = abs((int) $shiftEnd->diffInMinutes($clockOut));
                        }

                        if ($isHoliday) {
                            // Public Holiday: work up to shift end = holiday OT (2x), excess = special holiday OT (3x)
                            if ($clockOut && $clockOut->gt($shiftEnd->copy()->addMinutes($otThreshold))) {
                                $otSpecialMinutes = abs((int) $clockOut->diffInMinutes($shiftEnd));
                                $otHolidayMinutes = max(0, $workedMinutes - $otSpecialMinutes);
                            } else {
                                $otHolidayMinutes = $workedMinutes;
                            }
                        } elseif ($isDayOff) {
                            // Weekly Day Off: all worked time = holiday OT (2x)
                            $otHolidayMinutes = $workedMinutes;
                        } else {
                            // Normal workday OT (1.5x)
                            if ($clockOut && $clockOut->gt($shiftEnd->copy()->addMinutes($otThreshold))) {
                                $otNormalMinutes = abs((int) $clockOut->diffInMinutes($shiftEnd));
                            }
                        }
                    } elseif ($isHoliday) {
                        // Public holiday without shift: 8h (480 mins) = 2.0x, excess = 3.0x
                        if ($workedMinutes > 480) {
                            $otSpecialMinutes = $workedMinutes - 480;
                            $otHolidayMinutes = 480;
                        } else {
                            $otHolidayMinutes = $workedMinutes;
                        }
                    } elseif ($isDayOff) {
                        $otHolidayMinutes = $workedMinutes;
                    }

                    if ($lateMinutes > 0) {
                        $stats['late_records']++;
                    }
                    if ($earlyMinutes > 0) {
                        $stats['early_leave_records']++;
                    }
                    if ($otNormalMinutes > 0 || $otHolidayMinutes > 0 || $otSpecialMinutes > 0) {
                        $stats['ot_records']++;
                    }

                    Attendance::updateOrCreate(
                        ['org_id' => $orgId, 'employee_id' => $employee->id, 'work_date' => $dateStr],
                        [
                            'user_id' => $employee->user_id,
                            'employee_shift_id' => $shift?->id,
                            'clock_in_at' => $clockIn,
                            'clock_out_at' => $clockOut,
                            'late_minutes' => $lateMinutes,
                            'early_leave_minutes' => $earlyMinutes,
                            'worked_minutes' => $workedMinutes,
                            'ot_normal_minutes' => $otNormalMinutes,
                            'ot_holiday_minutes' => $otHolidayMinutes,
                            'ot_special_minutes' => $otSpecialMinutes,
                            'is_reconciled' => true,
                            'is_absent' => false,
                        ]
                    );

                    // Mark punches reconciled
                    AttendanceRawLog::whereIn('id', $punches->pluck('id'))->update(['status' => 'reconciled']);
                    $stats['reconciled_days']++;
                }
            }

            return $stats;
        });
    }

    private function deleteDirectory(string $dir): void
    {
        if (! file_exists($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
