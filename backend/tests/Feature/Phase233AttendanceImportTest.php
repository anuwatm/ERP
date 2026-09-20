<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceRawLog;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\EmployeeShiftSchedule;
use App\Models\Holiday;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class Phase233AttendanceImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_download_streams_valid_csv(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.attendance.import']);

        $response = $this->withoutMiddleware()->actingAsOrgUser($actor)->get(route('hr.attendance-import.template'));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('employee_code,punch_time', $response->streamedContent());
    }

    public function test_csv_upload_parses_punches_and_persists_raw_logs(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.attendance.import']);

        $emp = Employee::create([
            'org_id' => $actor->org_id,
            'employee_code' => 'EMP-PUNCH-1',
            'first_name' => 'Somchai',
            'employment_type' => 'daily',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        $csv = "employee_code,punch_time,punch_type,device_id\n"
            ."EMP-PUNCH-1,2026-10-01 08:50:00,in,BIO-1\n"
            ."EMP-PUNCH-1,2026-10-01 18:10:00,out,BIO-1\n"
            ."UNKNOWN,2026-10-01 09:00:00,in,BIO-1\n";

        $file = UploadedFile::fake()->createWithContent('punches.csv', $csv);

        $this->withoutMiddleware()->actingAsOrgUser($actor)->post(route('hr.attendance-import.store'), [
            'file' => $file,
        ])->assertRedirect();

        $this->assertSame(2, AttendanceRawLog::where('org_id', $actor->org_id)->where('employee_id', $emp->id)->count());
    }

    public function test_reconciliation_calculates_late_early_leave_ot_and_absent(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.attendance.import']);

        $shift = EmployeeShift::create([
            'org_id' => $actor->org_id,
            'code' => 'STD',
            'name' => 'Standard Shift',
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'break_minutes' => 60,
            'work_minutes_per_day' => 480,
            'grace_period_minutes' => 10,
            'overtime_starts_after_minutes' => 0,
        ]);

        $empLate = Employee::create([
            'org_id' => $actor->org_id,
            'employee_code' => 'EMP-LATE',
            'first_name' => 'Late Guy',
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'bank_transfer',
        ]);

        $empAbsent = Employee::create([
            'org_id' => $actor->org_id,
            'employee_code' => 'EMP-ABSENT',
            'first_name' => 'Absent Guy',
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'bank_transfer',
        ]);

        // Schedule both on 2026-10-01 (Thursday, normal workday)
        EmployeeShiftSchedule::create([
            'org_id' => $actor->org_id,
            'employee_id' => $empLate->id,
            'employee_shift_id' => $shift->id,
            'schedule_date' => '2026-10-01',
            'is_day_off' => false,
        ]);

        EmployeeShiftSchedule::create([
            'org_id' => $actor->org_id,
            'employee_id' => $empAbsent->id,
            'employee_shift_id' => $shift->id,
            'schedule_date' => '2026-10-01',
            'is_day_off' => false,
        ]);

        // Create raw punches for empLate: in at 09:30 (late 30m vs 09:00), out at 19:30 (OT 90m vs 18:00)
        AttendanceRawLog::create([
            'org_id' => $actor->org_id,
            'employee_code' => $empLate->employee_code,
            'employee_id' => $empLate->id,
            'punch_time' => '2026-10-01 09:30:00',
            'punch_type' => 'in',
            'status' => 'raw',
        ]);
        AttendanceRawLog::create([
            'org_id' => $actor->org_id,
            'employee_code' => $empLate->employee_code,
            'employee_id' => $empLate->id,
            'punch_time' => '2026-10-01 19:30:00',
            'punch_type' => 'out',
            'status' => 'raw',
        ]);

        // Run reconciliation
        $service = app(AttendanceReconciliationService::class);
        $stats = $service->reconcilePeriod($actor->org_id, '2026-10-01', '2026-10-01');

        $this->assertSame(1, $stats['late_records']);
        $this->assertSame(1, $stats['ot_records']);
        $this->assertSame(1, $stats['absent_records']);

        $lateAttendance = Attendance::where('org_id', $actor->org_id)
            ->where('employee_id', $empLate->id)
            ->whereDate('work_date', '2026-10-01')
            ->firstOrFail();

        $this->assertSame(30, $lateAttendance->late_minutes);
        $this->assertSame(90, $lateAttendance->ot_normal_minutes);
        $this->assertFalse($lateAttendance->is_absent);
        $this->assertTrue($lateAttendance->is_reconciled);

        $absentAttendance = Attendance::where('org_id', $actor->org_id)
            ->where('employee_id', $empAbsent->id)
            ->whereDate('work_date', '2026-10-01')
            ->firstOrFail();

        $this->assertTrue($absentAttendance->is_absent);
    }

    public function test_attendance_correction_records_reason_and_audit(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.attendance.import']);

        $emp = Employee::create([
            'org_id' => $actor->org_id,
            'employee_code' => 'EMP-CORRECT',
            'first_name' => 'Correction Test',
            'employment_type' => 'daily',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        $attendance = Attendance::create([
            'org_id' => $actor->org_id,
            'employee_id' => $emp->id,
            'work_date' => '2026-10-02',
            'clock_in_at' => null,
            'clock_out_at' => null,
            'is_absent' => true,
        ]);

        $this->withoutMiddleware()->actingAsOrgUser($actor)->post(route('hr.attendance-import.correct', $attendance), [
            'clock_in_at' => '2026-10-02 08:30:00',
            'clock_out_at' => '2026-10-02 17:30:00',
            'is_absent' => false,
            'correction_reason' => 'Fingerprint sensor malfunctioned at gate 2',
        ])->assertRedirect();

        $fresh = $attendance->fresh();
        $this->assertFalse($fresh->is_absent);
        $this->assertSame('Fingerprint sensor malfunctioned at gate 2', $fresh->correction_reason);
        $this->assertSame($actor->id, $fresh->corrected_by);

        $this->assertTrue(AuditLog::where('org_id', $actor->org_id)->where('action', 'hr.attendance.correct')->exists());
    }

    public function test_excel_xml_upload_parses_punches_correctly(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.attendance.import']);

        $emp = Employee::create([
            'org_id' => $actor->org_id,
            'employee_code' => 'EMP-EXCEL-1',
            'first_name' => 'Somkid',
            'employment_type' => 'daily',
            'employment_status' => 'active',
            'payment_method' => 'cash',
        ]);

        $xml = '<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="Sheet1">
  <Table>
   <Row>
    <Cell><Data ss:Type="String">employee_code</Data></Cell>
    <Cell><Data ss:Type="String">punch_time</Data></Cell>
    <Cell><Data ss:Type="String">punch_type</Data></Cell>
    <Cell><Data ss:Type="String">device_id</Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">EMP-EXCEL-1</Data></Cell>
    <Cell><Data ss:Type="String">2026-10-01 08:55:00</Data></Cell>
    <Cell><Data ss:Type="String">in</Data></Cell>
    <Cell><Data ss:Type="String">BIO-EXCEL</Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">EMP-EXCEL-1</Data></Cell>
    <Cell><Data ss:Type="String">2026-10-01 18:05:00</Data></Cell>
    <Cell><Data ss:Type="String">out</Data></Cell>
    <Cell><Data ss:Type="String">BIO-EXCEL</Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>';

        $file = UploadedFile::fake()->createWithContent('punches.xml', $xml);

        $this->withoutMiddleware()->actingAsOrgUser($actor)->post(route('hr.attendance-import.store'), [
            'file' => $file,
        ])->assertRedirect();

        $this->assertSame(2, AttendanceRawLog::where('org_id', $actor->org_id)->where('employee_id', $emp->id)->count());
    }

    public function test_overnight_shift_reconciliation(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.attendance.import']);

        // Overnight shift: 22:00 -> 06:00 (next day)
        $nightShift = EmployeeShift::create([
            'org_id' => $actor->org_id,
            'code' => 'NIGHT',
            'name' => 'Night Shift',
            'starts_at' => '22:00',
            'ends_at' => '06:00',
            'break_minutes' => 60,
            'work_minutes_per_day' => 420, // 8h span - 1h break = 7h (420 mins)
            'grace_period_minutes' => 10,
            'overtime_starts_after_minutes' => 0,
        ]);

        $emp = Employee::create([
            'org_id' => $actor->org_id,
            'employee_code' => 'EMP-NIGHT-1',
            'first_name' => 'Nocturnal',
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'bank_transfer',
        ]);

        EmployeeShiftSchedule::create([
            'org_id' => $actor->org_id,
            'employee_id' => $emp->id,
            'employee_shift_id' => $nightShift->id,
            'schedule_date' => '2026-10-01',
            'is_day_off' => false,
        ]);

        // Punches:
        // Clock In: 2026-10-01 22:15:00 (15 mins late vs 22:00)
        // Clock Out: 2026-10-02 06:30:00 (30 mins OT vs 06:00)
        AttendanceRawLog::create([
            'org_id' => $actor->org_id,
            'employee_code' => $emp->employee_code,
            'employee_id' => $emp->id,
            'punch_time' => '2026-10-01 22:15:00',
            'punch_type' => 'in',
            'status' => 'raw',
        ]);
        AttendanceRawLog::create([
            'org_id' => $actor->org_id,
            'employee_code' => $emp->employee_code,
            'employee_id' => $emp->id,
            'punch_time' => '2026-10-02 06:30:00',
            'punch_type' => 'out',
            'status' => 'raw',
        ]);

        $service = app(AttendanceReconciliationService::class);
        $service->reconcilePeriod($actor->org_id, '2026-10-01', '2026-10-01');

        $attendance = Attendance::where('org_id', $actor->org_id)
            ->where('employee_id', $emp->id)
            ->whereDate('work_date', '2026-10-01')
            ->firstOrFail();

        $this->assertSame(15, $attendance->late_minutes);
        $this->assertSame(30, $attendance->ot_normal_minutes);
        $this->assertFalse($attendance->is_absent);
        $this->assertTrue($attendance->is_reconciled);
    }

    public function test_public_holiday_3x_overtime_calculation(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, ['hr.attendance.import']);

        $shift = EmployeeShift::create([
            'org_id' => $actor->org_id,
            'code' => 'DAY',
            'name' => 'Day Shift',
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'break_minutes' => 60,
            'work_minutes_per_day' => 480,
            'grace_period_minutes' => 10,
            'overtime_starts_after_minutes' => 0,
            'ot_rate_normal' => 1.5,
            'ot_rate_holiday' => 2.0,
            'ot_rate_special_holiday' => 3.0,
        ]);

        $emp = Employee::create([
            'org_id' => $actor->org_id,
            'employee_code' => 'EMP-HOL-1',
            'first_name' => 'Holiday Worker',
            'employment_type' => 'monthly',
            'employment_status' => 'active',
            'payment_method' => 'bank_transfer',
        ]);

        Holiday::create([
            'org_id' => $actor->org_id,
            'holiday_date' => '2026-10-23',
            'name' => 'Chulalongkorn Day',
        ]);

        // Scheduled on a public holiday:
        EmployeeShiftSchedule::create([
            'org_id' => $actor->org_id,
            'employee_id' => $emp->id,
            'employee_shift_id' => $shift->id,
            'schedule_date' => '2026-10-23',
            'is_day_off' => false,
        ]);

        // Worked 09:00 to 20:00 (11 hrs span - 1 hr break = 10 hrs / 600 mins worked)
        // Regular scheduled holiday time = 480 mins (2.0x holiday)
        // Overtime beyond shift on holiday = 120 mins (3.0x special holiday)
        AttendanceRawLog::create([
            'org_id' => $actor->org_id,
            'employee_code' => $emp->employee_code,
            'employee_id' => $emp->id,
            'punch_time' => '2026-10-23 09:00:00',
            'punch_type' => 'in',
            'status' => 'raw',
        ]);
        AttendanceRawLog::create([
            'org_id' => $actor->org_id,
            'employee_code' => $emp->employee_code,
            'employee_id' => $emp->id,
            'punch_time' => '2026-10-23 20:00:00',
            'punch_type' => 'out',
            'status' => 'raw',
        ]);

        $service = app(AttendanceReconciliationService::class);
        $service->reconcilePeriod($actor->org_id, '2026-10-23', '2026-10-23');

        $attendance = Attendance::where('org_id', $actor->org_id)
            ->where('employee_id', $emp->id)
            ->whereDate('work_date', '2026-10-23')
            ->firstOrFail();

        $this->assertSame(480, $attendance->ot_holiday_minutes);
        $this->assertSame(120, $attendance->ot_special_minutes);
        $this->assertSame(0, $attendance->late_minutes);
        $this->assertFalse($attendance->is_absent);
    }

    private function grant(User $user, array $codes): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'attendance_import_tester'], ['name' => 'Attendance import tester', 'is_system' => true]);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => 'hr', 'action' => 'manage']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
