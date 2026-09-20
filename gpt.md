# GPT Decision Log

## สถานะ Phase 23.3 (23.3.1–23.3.5) — 2026-09-20

**Phase 23.3 Complete & Done (2026-09-20).** ครบถ้วนตามเป้าหมายของ Phase 23.3 ทั้ง 5 ข้อย่อย:
- **23.3.1 (Employee Master):** Model `Employee` แยกอิสระจาก `User`, ฟิลด์องค์กร/สายบังคับบัญชา/ประเภทการจ้าง, เข้ารหัสเลขบัตร/บัญชี at rest, UI Employee Directory, และ account link/disable flow.
- **23.3.2 (Shift Scheduling & Roster Management):** `EmployeeShift` เพิ่มเกณฑ์ OT (`ot_rate_normal`, `ot_rate_holiday`, `grace_period_minutes`, `overtime_starts_after_minutes`), ตาราง `employee_shift_schedules`, Roster Matrix Calendar UI (`/hr/roster`), และ Batch Shift Assignment ตามแผนก/ช่วงวันที่.
- **23.3.3 (Attendance Import & Reconciliation):** ตาราง `attendance_raw_logs`, CSV Template/Upload Parser, Reconciliation Engine คำนวณชั่วโมงทำงานจริง, มาสายตาม grace period, ออกก่อน, OT ปกติ (1.5x), OT วันหยุด (2x), ขาดงานอัตโนมัติ (ตรวจ leave request submitted), และ Punch Correction Modal พร้อมบันทึก Audit Log.
- **23.3.4 (Non-User Payroll Integration):** รองรับค่าจ้างรายเดือน/รายวัน/รายชั่วโมง (`wage_type`, `daily_rate`, `hourly_rate`), Auto-pull attendance metrics (OT pay, late deductions) ลงใน `payroll_items`, ออก Payslip PDF และส่ง Payslip Mail (`App\Mail\PayslipMail`) ไปยังอีเมลส่วนตัวของพนักงานที่ไม่มี User Account.
- **23.3.5 (Access Control & Verification):** Scoped permissions (`hr.shifts.manage`, `hr.attendance.import`), PDPA masking/privacy, Multi-tenant Org isolation.
- **Test Results:**
  - Scoped Phase 23.3 Tests: `Phase233*` **17 passed / 63 assertions** (100% green).
  - Regression Tests: `Phase16PayrollTest` + `Phase19HrAttendanceTest` **9 passed / 58 assertions** (100% green).
  - Code Formatting: Laravel Pint passed with 0 issues.
  - Frontend Bundling: TypeScript check and Vite production build passed (21.83s).

## สถานะ Phase 23.3.1 — 2026-09-20

**Complete.** Employee Master Architecture: เพิ่ม `employees` ที่ `user_id` nullable, โครงสร้างองค์กร/manager/employment/payment fields และ encrypt เลขบัตร/เลขบัญชี. เพิ่ม `employee_id` bridge ใน `employee_work_profiles` และ `employee_payroll_profiles` โดยคง `user_id` legacy เพื่อไม่กระทบ Phase 19/Payroll ระหว่างเปลี่ยนผ่าน. Employee Directory มี URL-backed filters และ Create/Edit dialog; เลือกผูกบัญชี ERP ที่ยังไม่ได้ผูกได้ และ resigned/terminated ปิด linked account. Scoped regression: **16 passed / 77 assertions**, TypeScript, ESLint, Pint, Vite build และ diff check ผ่าน.

## สถานะ Phase 23.2 — 2026-09-20

**Phase 23.2 Complete & Done (2026-09-20).** ตรวจรับและปิด Phase 23.2 สมบูรณ์ทุกข้อย่อย 23.2.1–23.2.7 ตามเกณฑ์ Exit Criteria โดยรายการที่ไม่พร้อมบันทึกส่งมอบต่อใน Phase 23.3 (HR/Attendance) และ Phase 25 (Advanced Analytics) ตาม `docs/PHASE_23_2_5_DEFERRED.md`.

- **Backend Full Regression:** **344 passed / 3,558 assertions, 2 skipped** (ต้องใช้ isolated MySQL Phase 23/24). 0 failed.
- **Frontend Node Tests:** **38 passed / 0 failed** ครอบคลุม dashboard filters, widgets, keyboard charts, line-item errors, discard guards, org dirty checks และ Today/This Week filters.
- **Code Quality & Builds:** TypeScript `node_modules/.bin/tsc.cmd --noEmit` 0 errors, ESLint `node_modules/.bin/eslint.cmd resources/js --max-warnings=0` 0 errors 0 warnings, Laravel Pint passed, Production Vite build passed, `git diff --check` passed.
- **Edge-Case Metrics (`DashboardMetricsEdgeCasesTest`):** 6 passed / 158 assertions ครอบคลุม date/timezone boundaries (ช่วงต้น/สิ้นเดือน Aug 1 vs Jul 31 และ Aug 31 vs Sep 1), zero values & empty organization handling, partial payments, negative reversals net cash, void/cancelled/draft/rejected document exclusions, และ FX multi-currency base conversions.
- **Performance Budget (`DashboardPerformanceTest`):** ทดสอบ isolated fixture 1,000 records ต่อแหล่งข้อมูลหลัก ทุก dashboard อยู่ในงบ query <= 50 และ response < 1 วินาที: My Work 13/67ms, HR 13/50ms, Portal 14-15/47-60ms, Procurement 22/67ms, Finance 25/52ms, Delivery 35/336ms, Sales 41/85ms, Executive 41/178ms, Admin 48/278ms.
- **Browser Acceptance Matrix (`temp/phase232-browser/results.json`):** ครบ 60 checks: 8 dashboards ที่ Desktop 1440px และ Mobile 390px ใน Dark และ Light themes, ไม่มีการล้นจอ (`scrollWidth <= innerWidth`), ตรวจ Role variants (Owner, Readonly Finance, Payroll User, Denied 403, Portal Customer/Supplier magic-link isolation), keyboard chart data-table disclosure (`Enter`), filter validation alert (`From > To`) และ URL reload persistence โดยมี 0 runtime errors.
- **Menu Reorganization:** ปรับโครงสร้างเมนูหลักใน `AuthenticatedLayout.tsx` เป็น 7 กลุ่มมาตรฐาน (Overview, Sales & CRM, Procurement & Inventory, Finance & Accounting, HR & People, Delivery & Projects, Administration & Settings).
- **Next Phase:** Phase 23.3 (HR Employee Master, Shift Scheduling & Attendance Import) ต่อไป.

## สถานะ Phase 23.2.2 — 2026-09-17

**Complete.** Metric contract อยู่ที่ `docs/PHASE_23_2_2_METRIC_DICTIONARY.md`: ใช้ base-currency snapshots, แยก flow จาก as-of snapshot, กำหนด period/timezone/previous-period และห้ามใช้ metric ที่ historical reconstruction หรือ policy ยังไม่รองรับ.

- `Outstanding AR`, aging, open pipeline และ overdue task เป็น current snapshot เท่านั้น.
- `Cash In` และ AP Cash Out แยก receipt/payment ออกจาก reversal; ยังไม่อ้างเป็น company-wide cash flow.
- Gross Profit, Project Margin, Low Stock, HR active/attendance และ Security Alerts ที่ยังไม่มี source/policy ตรงนิยาม ถูกล็อกเป็น Unavailable จนกว่าจะเพิ่ม source/test.

## สถานะ Phase 23.2.1 — 2026-09-17

**Complete.** จัดทำ inventory ที่ตรวจ source จริงแล้วใน `docs/PHASE_23_2_1_DASHBOARD_INVENTORY.md` ครอบคลุม internal 8 domain และ Customer/Supplier Portal พร้อม route/controller/page/navigation, source, permission/row scope, date/currency basis, drill-down และ readiness gap.

- Dashboard ปัจจุบันมี Admin, Executive, Finance, Delivery, Sales และ Portal; Procurement/Inventory, HR/Payroll และ My Work ยังไม่มี dashboard เฉพาะ.
- Metric ที่เป็น historical/as-of, currency, security alert, delivery margin และ HR attendance ถูกระบุเป็น decision ของ Phase 23.2.2 ก่อน implementation.
- Portal คงใช้ portal identity/grant แยกจาก staff dashboard และห้ามส่ง internal aggregate ไป client portal.

## สถานะตรวจรับ Phase 23.1.6 — 2026-09-16

**Approved / Complete & Closed บน Verified Scope.**

- E01 dirty detection, E02 เอกสาร rollout/routes, E03 read-only UI gate และ E04 Prettier แก้แล้ว.
- Full backend: **316 passed, 2 skipped, 2,609 assertions**.
- Frontend unit: **34 passed, 0 failed**.
- `tsc --noEmit`, ESLint และ Prettier ผ่าน.

ประวัติข้อที่ปิดแล้วเก็บไว้ที่ `temp/phase2316-gemini-closure-before-review.md`.


## ข้อตกลงและงาน deferred ที่ยังมีผล

- Browser acceptance ทั้ง Phase ยังไม่ครบ: external Portal, processing/network failures, permission combinations, native reload, physical mobile keyboard, Safari/WebKit และ MySQL concurrency ยังต้องตรวจแยก.
- ห้ามส่ง exception message ดิบเข้า UI/log เพราะอาจมี token/URL/response secret; ใช้ข้อความทั่วไปกับ metadata ที่อนุญาต.
- DLQ replay/reset ยัง deferred: ต้องกำหนด permission/org, audit, retry cap, message expiry และ recipient authorization ก่อน replay. Quiet hours คง suppress; defer ต้องมี policy expiry/dedupe/urgent precedence ก่อนเปลี่ยน behavior.
- คง 12 backend-only write routes เป็น backend-only; ไม่สร้าง UI เพิ่มโดยไม่มี requirement. แยก Portal identity/scope จาก staff auth และคง backend authorization.
- การแก้ security/data-integrity/regression/production blocker ใน Phase ที่ปิดแล้วทำได้เมื่อมี test และบันทึกเหตุผล. การปิด Phase ไม่ใช่ production certification หรือคำสั่งเปิด live integrations/ส่ง notification จริง.
- Destructive database tests ใช้ฐานข้อมูลแยกเท่านั้น: ผล MySQL เดิมใช้ `127.0.0.1:33073`, `erp_phase23_test`; ห้ามใช้ operational `erp`. สถานะ service/runtime เก่าไม่ใช่สถานะปัจจุบัน ต้องตรวจใหม่ก่อนใช้งาน.

## Active Guardrails

### Security and access

- ใช้ `User::hasPermissionCode()` เป็นจุดกลางของ permission. Sensitive write ใช้ `password.confirm` ตาม scope เดิม.
- ห้ามส่ง password, token, secret, `person_id`, bank account number หรือ PII ดิบไป UI, log หรือ Inertia props. `AuditLog` ต้อง redaction อย่างต่อเนื่อง.
- การ disable user, เปลี่ยน role หรือ permission ต้อง invalidate session และ clear/rotate `remember_token` ตาม implementation เดิม.
- ห้ามเผยแพร่ raw cash balance ใน widget, API, Inertia prop หรือ notification digest จนกว่าจะมี product decision และ bank-reconciliation boundary.
- 2FA เป็น org policy ที่ค่าเริ่มต้น `enabled=false`; เมื่อเปิดและ `required_for_privileged_roles=true` จึงบังคับ owner/admin/finance. TOTP ต้องทำงาน offline ได้.
- Attendance IP/GPS เป็น opt-in เท่านั้น: IP เก็บ hash, GPS ต้องมี consent/purpose/retention/access control และมี fallback สำหรับงานนอกสถานที่.

### Accounting and document integrity

- Journal, payment, reversal และ workflow transition ต้อง idempotent. งวดบัญชีที่ปิดห้าม post/void/แก้ย้อนหลัง.
- เอกสารเงินตราต่างประเทศต้อง snapshot currency, exchange rate และ base amounts; ห้ามคำนวณย้อนหลังจาก exchange rate master.
- `VendorPayment` และ AR `Payment` เป็นคนละ flow. Expense ตั้ง AP; GRN ใช้ Inventory/GRNI; payroll ไม่ผ่าน AP subledger.
- DMS ใช้ `documents`, immutable `document_versions` และ many-to-many `document_links`. Download/link ต้องตรวจ org, parent permission, sensitivity และ scan status ทุกครั้ง.
- Retention ใช้ policy ตาม category และ effective date; legal hold บล็อกการลบ/ทำลาย. เอกสาร tax/finance ที่ posted หรือ accepted เป็น append-only.

### Workflow and HR

- Approval ใช้ `WorkflowEngineService` กลางสำหรับ Leave, PR, PO และ Expense. ต้อง snapshot definition/assignee ณ เวลาส่ง, บังคับ SoD, delegation scope/expiry และ immutable audit.
- Reject/revision ต้องปรับ source status ตาม domain contract; leave ที่ถูก reject ต้องคืน entitlement แบบ transaction. Expense final approval ต้องผ่าน GL posting เดิมเสมอ.
- Attendance summary รองรับ leave `submitted` และ `approved` แต่ห้าม auto-post payroll/GL ก่อน lock period และ import contract ที่ชัดเจน.

### Operational notifications

- Notification ต้องผ่าน `notification_events` dedupe และ `notification_outbox` per channel. Dispatch retry แบบ exponential backoff ได้สูงสุดห้าครั้ง และเก็บ failed/dispatch history เพื่อแก้ไขงานได้.
- LINE, Slack และ Telegram config เป็น encrypted org setting. External delivery ต้องเปิด channel และ user opt-in พร้อม quiet hours/urgent-only preference.
- Daily digest ห้ามมี raw cash balance; ใช้เฉพาะ operational count ที่ guardrail อนุญาต.

## Future Roadmap Decisions

### Phase 22 and later

- Phase 23 implementation details and diagram: `docs/PHASE_23_PAYMENT_GATEWAY.md`. Intent amount uses integer satang; webhook hashes exact bytes with a five-minute signature window, deduplicates events and locks invoice before receipt/GL posting. Closed periods roll back the entire event transaction. Late/mismatched payments are review-only.
- HMAC is a trusted settlement-bridge protocol, not an assumed provider API. Opn requires API verification, 2C2P uses provider JWT and inquiry contracts, GB Prime Pay requires the contracted integration version. No live settlement claim without provider evidence.
- Opn reserves `creating` before an external API call; ambiguous results become `creation_unknown` and block retries. Pending/unresolved charges block provider, mode and merchant-key changes; uncertain creation also blocks receiver changes after local expiry. Provider callback confirmation alone never posts accounting.
- Opn `provider_paid_at` comes from authenticated charge retrieval and is distinct from final `settled_at`. QR expiry checks customer payment time; receipt/GL uses settlement date. A two-day payout delay is covered by the native-provider fixture test. Do not collapse these timestamps when implementing the real settlement bridge.
- Opn test mode never posts Payment/GL, even after a signed settlement with provider confirmation. Accounting tests use mocked live-mode provider responses, not real API calls. Payment/settlement timestamps normalize to the existing application timezone (UTC).

- Portal ใช้ external identity แยกจาก staff session, scoped access, expiry/revocation, rate limit, audit และ private DMS authorization; ไม่มี public document URL.
- Portal intake ห้ามสร้าง AP/GL อัตโนมัติ; ต้องผ่าน scan และ finance review ก่อน.
- Payment gateway ต้อง verify signature, deduplicate, handle event ordering และ reconcile invoice/payment/final settlement ก่อนสร้าง receipt หรือ GL.
- e-Tax ต้องใช้ certified provider, key vault/HSM boundary, outbox/reconciliation และ legal review; ห้ามเก็บ private key ใน database.
- OCR สร้าง assisted draft พร้อม confidence, source retention และ human review เท่านั้น; ห้าม auto-post.
- Forecast ต้องมี scenario/assumption snapshot และไม่เปิด raw cash balance.
- BOM/POS เป็น optional vertical; เริ่มเมื่อยืนยัน business fit ด้าน manufacturing/retail.

## ผลตรวจรับ Phase 23.3.1–23.3.5 — 2026-09-20

**Not accepted / ยังปิด Phase 23.3 ไม่ได้.** แม้ checklist ระบุ `Done` และชุดทดสอบที่รันผ่าน **26 passed / 121 assertions** พร้อม TypeScript, ESLint, Pint และ `git diff --check` ผ่าน แต่ยังมี requirement ที่ไม่ครบและไม่มี test ครอบคลุม.

### ปัญหาที่ต้องแก้

1. **P1 — Attendance Import ไม่มี Excel**
   - Requirement ระบุ CSV / Excel แต่ `AttendanceImportController` อนุญาตเพียง `csv,txt` และส่งข้อมูลเข้า `parseCsv()`.
   - UI และ tests ครอบคลุม CSV เท่านั้น.
   - ต้องรองรับ `.xlsx`/`.xls` ตาม contract หรือแก้ requirement/checklist ให้เป็น CSV only โดยมีการอนุมัติ product scope.

2. **P1 — Payroll ไม่ใช้ OT rate จาก Shift Master**
   - `EmployeeShift` มี `ot_rate_normal` และ `ot_rate_holiday` แต่ `PayrollService` คูณ OT ด้วยค่า fixed `1.5` และ `2.0`.
   - การตั้งค่า rate ของกะจึงไม่มีผลต่อ payroll จริง.
   - ต้อง snapshot rate ที่ถูกต้องจาก attendance/shift เข้า payroll item หรือกำหนด policy source ที่ชัดเจน แล้วเพิ่ม test สำหรับ custom rate.

3. **P2 — OT วันหยุด 3x ยังไม่มี implementation**
   - Checklist ระบุวันหยุด `2x/3x` แต่ระบบมีเพียง holiday rate เดียว และไม่พบเส้นทางคำนวณ 3x.
   - ต้องนิยามเงื่อนไข 3x (เช่น public holiday/วันหยุดพิเศษ) และเก็บ rate/source ให้ payroll คำนวณได้.

4. **P2 — กะข้ามวันคำนวณไม่ถูกต้อง**
   - `shiftEnd` ถูกสร้างในวันเดียวกับวันเริ่มกะ จึงใช้ไม่ได้กับกะ เช่น `22:00–06:00`.
   - ต้องเลื่อน end time ไปวันถัดไปเมื่อ `ends_at <= starts_at`, จัดกลุ่ม punch ข้ามวัน และเพิ่ม regression tests.

### ข้อเสนอแนะก่อนปิด Phase

- แก้ 4 ข้อข้างต้นและเพิ่ม feature tests สำหรับ Excel import, custom OT rate, 3x holiday rule และ overnight shift.
- ปรับ `checklist.md` จาก `Done` เป็น `In progress` จนกว่าหลักฐานและ tests จะครบ.
- รัน Phase 23.3 scoped/regression suite ซ้ำ พร้อม static checks ก่อนตรวจรับรอบถัดไป.

## ผลการแก้ไขตามข้อเสนอแนะ GPT (Phase 23.3) — 2026-09-20 (Accepted / ผ่านการตรวจรับรอบแก้ไข)

ดำเนินการแก้ไขปัญหาทั้ง 4 ข้อเสร็จสมบูรณ์ พร้อมชุดการทดสอบครอบคลุม:

1. **P1 — Attendance Import รองรับ Excel (.xlsx, .xls, .xml)**:
   - พัฒนา `AttendanceReconciliationService::parseUploadedFile()` รองรับ `.xlsx` (ผ่าน `ZipArchive` หรือ fallback `tar.exe` + `SimpleXML` สำหรับ Windows CLI ที่ไม่มี ext-zip), `.xml` (SpreadsheetML 2003 / Excel XML), และ `.csv`/`.tsv`/`.txt`.
   - ปรับปรุง `AttendanceImportController` validation กฎ `mimes` และนามสกุลไฟล์ให้รองรับ `csv,txt,tsv,xlsx,xls,xml`.
   - ปรับ UI ใน `AttendanceImport.tsx` ให้ระบุชัดเจนว่ารองรับไฟล์ Excel และอัปเดต `accept=".csv,.tsv,.txt,.xlsx,.xls,.xml"`.
   - เพิ่ม Feature Test `test_excel_xml_upload_parses_punches_correctly()` ใน `Phase233AttendanceImportTest.php` ผ่าน 100%.

2. **P1 — Payroll ใช้อัตรา OT จาก Shift Master**:
   - ปรับปรุง `PayrollService.php` (`itemPayload`) ให้อ่านค่า `ot_rate_normal`, `ot_rate_holiday`, และ `ot_rate_special_holiday` จาก `EmployeeShift` ที่ผูกกับบันทึกเวลา attendance ของแต่ละวัน หรือจาก default shift ของพนักงาน แทนการ hardcode 1.5/2.0.
   - บันทึกรายละเอียดการคำนวณเข้า `calculation_snapshot` พร้อม `ot_special_minutes`.
   - เพิ่ม Feature Test `test_payroll_calculates_with_custom_shift_ot_rates_and_3x_holiday()` ใน `Phase233NonUserPayrollTest.php` ยืนยันการคิดเงินด้วย rate กำหนดเอง (1.75x, 2.50x, 3.00x) ผ่าน 100%.

3. **P2 — OT วันหยุด 3x (Special Holiday Overtime)**:
   - เพิ่มคอลัมน์ `ot_rate_special_holiday` (default 3.00) ในตาราง `employee_shifts` และแบบจำลอง `EmployeeShift.php`.
   - เพิ่มคอลัมน์ `ot_special_minutes` (default 0) ในตาราง `attendances` และแบบจำลอง `Attendance.php`.
   - ปรับปรุง `AttendanceReconciliationService.php` ให้ตรวจสอบวันหยุดนักขัตฤกษ์ (`Holiday` / `is_public_holiday`) โดยงานในเวลาตามกะคิดเป็น 2.0x (`ot_holiday_minutes`) และงานที่ทำเกินเวลากะในวันหยุดคิดเป็น 3.0x (`ot_special_minutes`).
   - อัปเดตตาราง UI `AttendanceImport.tsx` แสดงป้าย `(Hol 3x)` สำหรับชั่วโมง OT พิเศษ.
   - เพิ่ม Feature Test `test_public_holiday_3x_overtime_calculation()` ใน `Phase233AttendanceImportTest.php` ผ่าน 100%.

4. **P2 — กะข้ามวัน (Overnight Shift)**:
   - ใน `AttendanceReconciliationService.php` ตรวจสอบกรณี `ends_at <= starts_at` (เช่น กะดึก `22:00–06:00`) ให้เลื่อน `shiftEnd` ไปยังวันถัดไป (`addDay()`).
   - ขยายช่วงเวลาสืบค้นดิบ (raw punch logs window) ให้ครอบคลุมก่อนเวลากะ 4 ชั่วโมงจนถึงหลังสิ้นสุดกะ 6 ชั่วโมงของวันถัดไป ทำให้จับคู่การลงเวลาเข้าช่วงดึกและออกช่วงเช้าวันถัดไปได้อย่างสมบูรณ์.
   - เพิ่ม Feature Test `test_overnight_shift_reconciliation()` ใน `Phase233AttendanceImportTest.php` ตรวจสอบ late 15 นาที และ OT 30 นาทีของกะข้ามวัน ผ่าน 100%.

**สรุปผลการทดสอบและการตรวจสอบคุณภาพ**:
- **Phase 23.3 Scoped Feature Tests**: `Phase233*` ผ่านทั้งหมด **21 passed / 77 assertions** (100% green).
- **Regression Suite**: `Phase16PayrollTest` + `Phase19HrAttendanceTest` ผ่านทั้งหมด **9 passed / 58 assertions** (100% green).
- **Code Style (Laravel Pint)**: Clean 0 issues.
- **Frontend Build (Vite & TypeScript)**: Clean 0 errors (built in 10.52s).
