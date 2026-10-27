# 12 — HR, Attendance, Roster และ Payroll

Snapshot 2026-10-24, HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` รวม working tree
อ่าน implementation และ test assertions; ไม่ได้รัน tests ในการจัดทำเอกสาร

## หน้าที่และการแบ่งตัวตน

- Employee master, ตำแหน่ง, โครงสร้างองค์กร และ import พนักงาน
- ตารางกะรายวัน, raw punches, reconciliation, ลงเวลา self service และลา
- คำนวณเงินเดือนรายเดือน/รายวัน/รายชั่วโมง, OT, สาย, ภาษี/ประกันสังคม, payslip และ GL
- `Employee` คือบุคลากร HR; `User` คือบัญชีเข้าใช้ระบบ; พนักงานไม่จำเป็นต้องมี user
- `users.person_id` เป็นข้อมูลเลขประจำตัวแบบ char(13) ใน users schema ไม่ใช่ Employee ID หรือ relation ไป Person model
- Legacy attendance/leave/summary บางเส้นทางยังอิง user_id; payroll รุ่นใหม่รองรับ employee_id และ nullable user_id

## Routes และ permissions

ดู [web.php](../../backend/routes/web.php) สำหรับชื่อ route และ middleware เต็ม

| กลุ่ม route/actions | Permission |
|---|---|
| GET `/hr` และ POST clock-in/out, leave-requests, submit/cancel | `hr.self.view` |
| GET `/hr/employees`, GET `/hr/positions` | `hr.employees.view` |
| employee store/update/linkAccount/template/import, position store | `hr.employees.manage` |
| GET `/hr/roster`, POST schedules/batch | `hr.shifts.manage` |
| attendance-import index/template/import/reconcile/correct | `hr.attendance.import` |
| POST `/hr/shifts`, `/work-profiles`, `/holidays`, `/leave-types`, `/leave-balances` | `hr.manage` |
| POST `/hr/summaries`, `/{id}/lock`, `/{id}/reverse` | `hr.summary.manage` |
| GET `/payroll` | `payroll.view` |
| POST `/payroll/profiles`, `/policies`, `/runs`, run calculate | `payroll.manage` |
| POST `/payroll/runs/{id}/approve` | `payroll.approve` |
| POST `/payroll/runs/{id}/pay` | `payroll.pay` |
| GET `/payroll/runs/{id}/exports/{type}` | `payroll.export` |
| POST `/payroll/payslips/{id}/email` | `payroll.manage` |

Print/PDF payslip routes ตรวจ self หรือ `payroll.view` ใน controller
Mutation ฝั่ง admin ส่วนมากต้อง password confirm + throttle; position create มี throttle 30/minute
Create/import/batch บาง routes ใช้ `EnsureFormSubmission`

## โค้ดหลัก

- [EmployeeController](../../backend/app/Http/Controllers/Hr/EmployeeController.php), [PositionController](../../backend/app/Http/Controllers/Hr/PositionController.php)
- [EmployeeImportService](../../backend/app/Services/Hr/EmployeeImportService.php): parse และ import/update employees
- [RosterController](../../backend/app/Http/Controllers/Hr/RosterController.php): daily schedule และ batch assign
- [AttendanceImportController](../../backend/app/Http/Controllers/Hr/AttendanceImportController.php): file import/reconcile/manual correction
- [AttendanceReconciliationService](../../backend/app/Services/AttendanceReconciliationService.php): raw logs → daily attendance/late/OT/absent
- [HrController](../../backend/app/Http/Controllers/HrController.php), [HrAttendanceService](../../backend/app/Services/HrAttendanceService.php): self clock, leave, summaries
- [HrDashboardController](../../backend/app/Http/Controllers/HrDashboardController.php): HR dashboard
- [PayrollController](../../backend/app/Http/Controllers/Finance/PayrollController.php), [PayrollService](../../backend/app/Services/PayrollService.php), [PayslipMailService](../../backend/app/Services/PayslipMailService.php)

## Entities และ relations

- [Employee](../../backend/app/Models/Employee.php): branch/division/department/position/manager, optional user, dates/payment data
- Employee → manager Employee; 1:N reports, attendances, schedules; 1:1 workProfile/payrollProfile
- `Position` เชื่อม optional department; `EmployeeWorkProfile` เชื่อม default `EmployeeShift`
- `EmployeeShiftSchedule`: employee + schedule_date + shift/day-off/public-holiday flags
- `AttendanceRawLog`: employee, punch time/type/source/hash/batch/status
- `Attendance`: work_date, employee/user/shift, punches, worked/late/early/OT minutes, absence/correction metadata
- `Holiday`, `LeaveType`, `LeaveBalance`, `LeaveRequest`, `AttendanceSummary`
- `EmployeePayrollProfile` เชื่อม employee/user, wage rates, fixed allowance/deduction, tax allowance/payment
- `PayrollRun` 1:N `PayrollItem`; run เชื่อม tax/social policies, bank account และ audit actors
- `PayrollTaxPolicy`, `SocialSecurityPolicy` มี effective dates; item เก็บ calculation snapshot
- National ID และ bank account number ของ Employee ใช้ encrypted casts

## Employee master/import

- Employment status: `probation`, `active`, `suspended`, `resigned`, `terminated`
- Employment status แยกจาก account status `active`, `inactive`, `invited` และ linked/unlinked
- `linkAccount()` กัน user account ที่ถูกผูก employee อื่นแล้ว
- Validate branch/division/department chain ต้องตรงกัน; manager ห้ามเป็นตัวเอง
- `resigned`/`terminated` ทำให้ linked user inactive และเปลี่ยน remember token
- Import รองรับ CSV/TXT/TSV/XLSX/XML และ `.xls` ที่เป็น XML; อย่าสมมติว่ารองรับ binary XLS เต็มรูปแบบ
- Required headers/ข้อมูลสำคัญ `employee_code`, `first_name`
- `importEmployees()` preload reference maps ต่อ org; option `auto_create_position` default true, `update_existing` default false
- Duplicate employee code ไม่ update หากไม่ได้เปิด option; รายงาน created/updated/errors และ batch ID
- Scope lookup จำกัดองค์กร; ผล import ไม่ใช่การสร้าง login account อัตโนมัติ

## Roster และ attendance

1. Assign schedules รายคนหรือ batch สำหรับ employee `probation`/`active`
2. Import raw punches ด้วย employee_code/punch_time → `raw`; duplicate guard อยู่ใน service
3. Reconcile เลือก schedule ของวันก่อน default shift จาก work profile
4. ถ้าไม่มี schedule ใช้ weekend เป็นวันหยุด; public holiday มาจาก Holiday หรือ schedule flag
5. กะที่ end<=start เป็น overnight; ช่วงค้น punch เริ่มก่อนกะ 4 ชม. และหลังจบกะ 6 ชม.
6. คำนวณ worked/late/early leave และ OT normal/holiday/special; raw logs → `reconciled`
7. วันทำงานไม่มี punch อาจสร้าง absent record; การตรวจ leave ในเส้นทางนี้อิง linked user และ status `submitted`
8. Correction route บันทึก reason/audit; ใช้แก้ missing punches และเวลาผิด

`clockIn()` ห้ามมี open attendance ซ้ำ; `clockOut()` ต้องมี open clock-in
Privacy policy ควบคุม optional location/photo data; consent จำเป็นเมื่อเก็บ optional attendance data
อย่าสมมติว่า imported attendance ต้องมี user เพราะ service ใช้ employee_id เป็นหลัก

## Leave และ summary

- Leave สร้าง `draft`; `submitLeave()` ตรวจ working days, submitted overlap และ leave balance → `submitted`
- `cancelLeave()` ยอมเฉพาะ submitted → `cancelled`; คืน balance ตาม service
- Self actions ตรวจว่า leave เป็นของผู้ใช้
- Business days ของ self service ใช้ weekend/holiday; แยกจาก per-employee roster reconciliation
- `createSummary()` cutoff ต้องไม่เกิน period end; รวม attendance และ leave `submitted`/`approved` → `draft`
- `lockSummary()` draft → `locked`; `reverseSummary()` locked → `reversed` และสร้าง summary ใหม่ตาม controller/service
- Payroll implementation อ่าน attendance records โดยตรง ไม่ได้บังคับใช้ locked AttendanceSummary

## Payroll workflow และสูตรจริง

1. สร้าง active payroll profile และ effective-dated policies
2. สร้าง run `draft`, currency `THB`, เก็บ tax/social policy IDs ตาม payment date
3. `calculate()` รับ draft/calculated; lock run, ลบ items เก่าแล้วคำนวณทุก active profile → `calculated`
4. `approve()` รับ calculated, post payroll accrual GL → `approved`
5. `markPaid()` รับ approved, post payment GL → `paid`

- Attendance query ใช้ employee_id ก่อน user_id; รองรับพนักงานไม่มี login
- Monthly ใช้ monthly salary; daily ใช้ worked days × daily rate; hourly ใช้ worked minutes/60 × hourly rate
- ถ้า daily/hourly ไม่มีเวลา service fallback เป็น monthly_salary
- Default daily rate=monthly/30, hourly=daily/8 หรือ monthly/240
- OT ใช้อัตราจาก attendance shift/default shift; fallback normal 1.5, holiday 2, special 3
- Late deduction = late minutes/60 × hourly rate
- Gross สำหรับคำนวณ tax/social = salary+allowance+OT−late deduction จำกัดขั้นต่ำ 0
- Social security ใช้ min(gross,wage ceiling) × policy rate
- Annual PIT ประเมินจาก gross×12 หัก expense cap, annual allowance, social×12 แล้ว progressive tax/12
- ไม่ใช่ YTD cumulative tax settlement; policy defaults ใน code ไม่ใช่การยืนยันกฎหมายปัจจุบัน
- Run `gross_amount` รวม salary+allowance+OT โดยไม่ลบ late แม้ item tax/net ใช้ gross หลัง late; ควรตรวจความหมาย field ตอนทำรายงาน
- Export types คือ `pnd1`, `social-security`; payslip print/PDF/email รองรับ non-user employee

## UI และ schema

- [Hr pages](../../backend/resources/js/Pages/Hr): `Employees`, `Partials/EmployeeImportDialog`, `Roster`, `AttendanceImport`, `Index`, `HrDialogs`, `Dashboard`
- [Payroll pages](../../backend/resources/js/Pages/Finance/Payroll.tsx), [PayrollDialogs](../../backend/resources/js/Pages/Finance/PayrollDialogs.tsx)
- [Payroll tables](../../backend/database/migrations/2026_08_29_000003_create_payroll_tables.php), [permissions](../../backend/database/migrations/2026_08_29_000004_add_payroll_permissions.php)
- [HR tables](../../backend/database/migrations/2026_09_07_000001_create_phase19_hr_tables.php)
- [Employee bridge](../../backend/database/migrations/2026_09_20_000001_create_employees_and_bridge_legacy_hr_profiles.php)
- [Schedules/raw logs](../../backend/database/migrations/2026_09_20_000002_create_shift_schedules_and_raw_attendance_logs.php)
- [Position master](../../backend/database/migrations/2026_09_22_000001_create_positions_and_link_employees_table.php)

## Tests ที่อ่านและจุดต้องระวัง

- [EmployeeBulkImportTest](../../backend/tests/Feature/EmployeeBulkImportTest.php): CSV/XML, duplicate/update option, required fields, tenant
- [EmployeePositionMasterTest](../../backend/tests/Feature/EmployeePositionMasterTest.php), [Phase233EmployeeMasterTest](../../backend/tests/Feature/Phase233EmployeeMasterTest.php)
- [Phase233RosterAndShiftTest](../../backend/tests/Feature/Phase233RosterAndShiftTest.php): roster/shift contracts
- [Phase233AttendanceImportTest](../../backend/tests/Feature/Phase233AttendanceImportTest.php): late/early/OT/absent, correction audit, overnight, public holiday 3x
- [Phase19HrAttendanceTest](../../backend/tests/Feature/Phase19HrAttendanceTest.php): self service/leave/summary
- [Phase16PayrollTest](../../backend/tests/Feature/Phase16PayrollTest.php): calculate→GL→pay/export, own payslip, policy binding, invalid transition, scope
- [Phase233NonUserPayrollTest](../../backend/tests/Feature/Phase233NonUserPayrollTest.php): non-user wages/OT/late, PDF/email, custom shift rates
- Tests ยังไม่ได้รัน; payroll mark paid เป็นบัญชีภายใน ไม่ใช่ bank payout integration
- Legacy user-centric leave/summary และ employee-centric import ต้องตรวจเชื่อมต่อใน use case จริง
- ดู [Accounting](09_accounting_fx_assets.md) และ [Treasury](10_treasury_banking.md) สำหรับ GL/ธนาคาร
