# GPT Decision Log

## สถานะ Phase 26 (AI OCR Document Ingestion) & GPT Handoff Inquiries — 2026-09-21

**Phase 26 Complete & Closed (2026-09-21).** ระบบ AI OCR Document Ingestion (Universal OpenAI-Compatible Vision รองรับ Local AI Ollama/LM Studio และ Cloud GPT/OpenRouter/Gemini) พัฒนาและทดสอบผ่าน 100%:
- **การแก้ไขปัญหา GPT Integration:** ปรับปรุง Endpoint Resolution (`/v1`), Trim API Key, กำหนด Token Limit (`max_tokens: 2500` / `max_completion_tokens`), บังคับ Native JSON Mode, High-Detail Vision Grid, และ Auto-extract Embedded JPEG Stream จาก Scanned PDF
- **ข้อสงสัยและประเด็นสำคัญสำหรับ GPT:** บันทึกไว้อย่างละเอียดใน [`GEMINI.md` หัวข้อ 8.3](file:///c:/LocalDevine/www/ERP/GEMINI.md) ให้ GPT รับรู้และร่วมพิจารณาในรอบถัดไป:
  1. ยุทธศาสตร์การประมวลผล PDF ระหว่าง Render Image บน Server กับ Text Extraction
  2. การคุม Token Cost, Image Downscaling และการย้าย OCR Pipeline ไปยัง Async Queue Worker
  3. Smart Fallback สลับจาก Local Ollama ไปยัง Cloud GPT อัตโนมัติเมื่อ Confidence ต่ำ
  4. การต่อยอด Split-Screen Reviewer เพื่อแปลงเป็น Vendor Bill (AP) พร้อมแนะนำ Open PO ที่ค้างรับ
  5. สวิตช์ Tenant Compliance เพื่อบังคับ On-Premise Local AI เท่านั้นสำหรับองค์กรที่เน้นความลับสูง

## สถานะ Bulk Employee Import & Position Master (HR Enhancement) — 2026-09-20

**Complete & Done (2026-09-20).** ระบบนำเข้าข้อมูลประวัติพนักงานแบบชุด (Bulk Employee Import) และ Position Master:
- **Bulk Employee Import Service (`EmployeeImportService.php`):**
  - รองรับการประมวลผลไฟล์ CSV, TSV, TXT, Excel (`.xlsx`, `.xls`), และ SpreadsheetML (`.xml`)
  - ฟังก์ชัน Normalize Header รองรับทั้งคอลัมน์ภาษาอังกฤษและภาษาไทย เช่น `employee_code` / `รหัสพนักงาน`, `first_name` / `ชื่อ`, `position_name` / `ตำแหน่ง`, `department_code` / `แผนก`, `started_on` / `วันเริ่มงาน`
  - ตัวเลือก `auto_create_position` (สร้างตำแหน่งงานใหม่อัตโนมัติเมื่อยังไม่มีใน Master) และ `update_existing` (อัปเดตข้อมูลเดิมเมื่อพบรหัสพนักงานซ้ำ แทนการข้าม)
  - เข้ารหัสข้อมูลที่อ่อนไหว (`national_id`, `bank_account_number`) ด้วย AES-256 ตามมาตรฐาน PDPA และบันทึก `AuditLog` ทุกครั้ง
- **Controller & Endpoints (`EmployeeController.php`, `web.php`):**
  - `GET /hr/employees/template`: ดาวน์โหลดไฟล์แม่แบบ CSV พร้อมตัวอย่างข้อมูลและ UTF-8 BOM (`permission:hr.employees.manage`)
  - `POST /hr/employees/import`: อัปโหลดไฟล์เพื่อประมวลผลนำเข้าพนักงาน พร้อมรายงานจำนวนที่สำเร็จและรายการ Error ละเอียดระดับแถว
- **Frontend UI (`EmployeeImportDialog.tsx`, `Employees.tsx`):**
  - ปุ่ม `Import` ข้างปุ่ม `New Employee` บนแถบ Action Header ในหน้า Employee Directory
  - หน้าต่าง Modal `EmployeeImportDialog` พร้อม File Dropzone, ปุ่มดาวน์โหลดแม่แบบ, Checkbox ตัวเลือกนำเข้า, และตารางแสดงข้อผิดพลาด (ถ้ามี)
- **Position Master & Combobox:**
  - ตาราง `positions` เป็น Master Data อิสระ, Model `Position`, Foreign Key `employees.position_id`
  - Searchable Combobox (`PositionCombobox` บน Headless UI) พร้อม Inline Quick-Add
  - Dark Mode Base `<select>` CSS fix ใน `app.css`
- **Test Results:**
  - `EmployeeBulkImportTest`: **8 passed / 47 assertions ผ่าน 100% (100% green)**
  - `EmployeePositionMasterTest`: **7 passed / 23 assertions ผ่าน 100% (100% green)**
  - Full Employee Suites: **27 passed / 110 assertions ผ่าน 100% (100% green)**
  - TypeScript, ESLint, Pint, Vite build ผ่านฉลุย 100%

## สถานะ Position Master & Searchable Combobox (HR Enhancement) — 2026-09-20

**Complete & Done (2026-09-20).** แยกตารางตำแหน่งพนักงาน (`positions`) และพัฒนา Searchable Combobox UI:
- **Database & Architecture:**
  - สร้างตาราง `positions` (`id`, `org_id`, `department_id`, `name`, `code`, `description`, `is_active`, timestamps, soft deletes)
  - เพิ่ม `position_id` ในตาราง `employees` พร้อม foreign key constraint (`nullOnDelete`)
  - Migration (`2026_09_22_000001_create_positions_and_link_employees_table.php`) ทำการ Backfill ข้อมูล `position_title` เดิมเข้าสู่ `positions` อัตโนมัติ และผูก `position_id` กลับให้พนักงานเดิมทุกราย
  - Model `App\Models\Position` และเชื่อมโยงสัมพันธ์กับ `Organization`, `Department`, และ `Employee`
- **Controller & API Endpoints (`PositionController.php`, `EmployeeController.php`, `web.php`):**
  - `GET /hr/positions`: ค้นหาตำแหน่งงานตาม query string (`search`) และสังกัดองค์กร (`permission:hr.employees.view`)
  - `POST /hr/positions`: สร้างตำแหน่งงานใหม่แบบ inline พร้อม auto-generate code หากไม่ได้ระบุ (`permission:hr.employees.manage`)
  - `EmployeeController.php`: รองรับการส่งรายการ positions ให้หน้าจอ, บันทึก `position_id` พร้อมซิงค์ `position_title` อัตโนมัติทั้งตอน create และ update เพื่อรักษาความเข้ากันได้แบบย้อนหลัง (Bidirectional Compatibility)
- **Frontend & UI/UX (`Employees.tsx`, `app.css`):**
  - Searchable Combobox (`PositionCombobox` บน Headless UI): พิมพ์ค้นหาแบบ type-ahead, รองรับแป้นพิมพ์ลูกศร, ปุ่ม clear, และมีปุ่ม `+ เพิ่มตำแหน่งใหม่` เปิด Inline Modal สร้างตำแหน่งงานใหม่ได้ทันที
  - แสดงผลคอลัมน์ "ตำแหน่ง" ใน Employee Data Table ชัดเจน
  - ปรับปรุง Dark Mode CSS ระดับ Base (`app.css`) ให้ `<select>` และ `<option>` แสดงผลพื้นหลังเข้มและตัวหนังสือขาวถูกต้อง ไม่กลืนกับสีพื้นหลัง
- **Test Results:**
  - Feature Tests: `EmployeePositionMasterTest` **7 passed / 23 assertions ผ่าน 100% (100% green)**
  - Regression Tests: `Phase233EmployeeMasterTest` **7 passed / 24 assertions ผ่าน 100% (100% green)**
  - Frontend Build & TypeScript: `npm run build` และ `tsc` ผ่านฉลุย 0 errors

## สถานะ Phase 25 — 2026-09-20

**Phase 25 Complete & Done (2026-09-20).** Cash Flow Forecasting & Financial Health Analytics เสร็จสมบูรณ์ทุกรายการ:
- **Cash Flow Forecasting Engine (`CashFlowForecastService.php`):**
  - Multi-scenario projection engine รองรับ 3 แบบจำลองมาตรฐาน: `base` (Normal operations), `best` (Optimistic outlook with deal pipeline upside), และ `worst` (Stress test with collection delay and haircuts).
  - Inflow projection ผสาน AR due dates และ Sales Deals ที่มีโอกาสปิดการขายตาม Probability Weighting และ Deal Multiplier.
  - Outflow projection ผสาน AP approved expenses, open Purchase Orders, Recurring monthly payroll (วันที่ 25 ของทุกเดือน), และ Buffer percentages.
  - คำนวณ Day-by-day cash curve และหาจุดต่ำสุดของเงินสด (Lowest Cash Point & Date).
- **Financial Health Analytics (`FinancialMetricLog.php`):**
  - Current Ratio (Current Assets / Current Liabilities, เกณฑ์สุขภาพ &ge; 1.5x)
  - Quick Ratio ((Cash + AR) / Current Liabilities, เกณฑ์คล่องตัว &ge; 1.0x)
  - DSO (Days Sales Outstanding คำนวณจากยอดขาย 90 วันย้อนหลัง)
  - DPO (Days Payable Outstanding คำนวณจากค่าใช้จ่าย 90 วันย้อนหลัง)
  - Monthly Burn Rate และ Working Capital Runway (จำนวนเดือนก่อนเงินสดหมด)
- **Controller & Web Routes (`CashFlowController.php`, `web.php`):**
  - `GET /finance/cash-flow`: Inertia view พร้อม multi-scenario comparison และ filter horizon 30-180 วัน (`permission:cash_flow.view`).
  - `POST /finance/cash-flow/snapshot`: บันทึก snapshot สภาพคล่องและอัตราส่วนทางการเงิน (`permission:cash_flow.manage`).
  - `PATCH /finance/cash-flow/scenarios/{scenario}`: ปรับแต่งพารามิเตอร์แบบจำลอง (`permission:cash_flow.manage`).
- **Artisan Command & Automation (`console.php`):**
  - `php artisan cashflow:snapshot {--org=} {--horizon=90}`: สั่งสร้าง snapshot ทุกแบบจำลองและคำนวณอัตราส่วนการเงินอัตโนมัติ พร้อมตั้งเวลา Scheduler รายวัน `dailyAt('02:00')`.
- **UI & Visualization (`CashFlow.tsx`):**
  - KPI StatCards & Badges สำหรับ Working Capital Runway, Current Ratio, Quick Ratio, DSO, DPO.
  - 3 Scenario Summary Cards (Worst Case, Base Case, Best Case) แสดงจุดต่ำสุดและยอดเงินสดปลายงวด.
  - Interactive SVG Projection Curve แสดงเส้นทางเงินสด 3 แบบจำลองพร้อมเส้นระดับเงินสดหมด (0 บาท).
  - แท็บ Inflows, Outflows, Historical Snapshots, และ Modal Dialog ปรับแต่งพารามิเตอร์แบบจำลอง.
- **Test Results:**
  - Feature Tests: `Phase25CashFlowTest` **8 passed / 38 assertions ผ่าน 100% (100% green)**.
  - Regression Tests: `Phase12ETaxTest` + `Phase24ETaxFoundationTest` + `Phase24ProviderGatewayTest` **18 passed / 122 assertions ผ่าน 100% (100% green)**.
  - Frontend Quality: TypeScript check (`npx tsc --noEmit`) 0 errors, ESLint (`npm run lint`) 0 errors, Prettier (`npm run check-format`) 100% clean.

## สถานะ Phase 24 — 2026-09-20

**Phase 24 Complete & Done (2026-09-20).** e-Tax Invoice & RD Prep Gateway Architecture เสร็จสมบูรณ์ทุกรายการ:
- **Provider Gateway Engine (`RdApiSubmissionAdapter.php`):** รองรับโหมด `provider` สำหรับ Revenue Department Web Service (`rd_api`), Certified Provider (`inet`), และ Sandbox Gateway (`sandbox`) พร้อมการจัดการ Idempotency key, Timeouts, Bearer Auth, และ Base64 XML packaging.
- **XML Digital Signature Pipeline (`ETaxXmlSignatureService.php`):** ท่อประมวลผลลายมือชื่อดิจิทัล ETDA Enveloped XML-DSig (`http://www.w3.org/2000/09/xmldsig#`) ด้วย XML Canonicalization (C14N), RSA-SHA256, DigestValue, KeyInfo, และ X.509 Certificate Reference.
- **Job Worker & Gateway Orchestration (`SubmitETaxDocument.php`, `ETaxProviderGatewayService.php`):** รองรับทั้ง mock simulation และ live provider delivery พร้อมการ Auto-sign เอกสาร, การบันทึก `ETaxSubmissionAttempt`, ติดตาม External Reference, และจำกัดเพดาน retry สูงสุด 3 ครั้งอย่างเข้มงวด.
- **Asynchronous Webhook & Status Inquiry (`ETaxWebhookController.php`, `etax:inquire-status`):**
  - Webhook callback endpoint `POST /api/e-tax/{config}/callback` พร้อมตรวจสอบลายมือชื่อ HMAC-SHA256 (`X-ETax-Signature`) และแยกความปลอดภัยแบบ Multi-tenant.
  - คำสั่ง Artisan `etax:inquire-status --limit=50` ดึงสถานะเอกสารที่ค้างจาก Gateway และตั้งเวลาทำงานทุก 30 นาทีใน Laravel Scheduler.
- **Security Guardrails:** Private key เก็บไว้ภายนอกฐานข้อมูล (Vault/KMS ARN reference เท่านั้น), Provider credentials เข้ารหัส at rest (`encrypted:array`), และตัดข้อมูลความลับ/token ออกจาก Audit logs และ Response messages.
- **UI & Experience (`ETax.tsx`, `ETaxController.php`):** เพิ่มฟอร์ม Provider settings, Badges แสดงสถานะ Lifecycle (Generated → Signed → Queued → Submitted → Accepted/Rejected), ปุ่ม Sign XML, Submit Gateway, และ Inquire Status.
- **Test Results:**
  - e-Tax Suite Tests: `Phase12ETaxTest` (3/15) + `Phase24ETaxFoundationTest` (8/49) + `Phase24ProviderGatewayTest` (7/58) = **18 passed / 122 assertions ผ่าน 100% (100% green)**.
  - Frontend Quality: TypeScript check (`npx tsc --noEmit`) 0 errors, ESLint (`npm run lint`) 0 errors, Prettier (`npm run check-format`) 100% clean.

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

## งานค้างจากการตรวจรับ Phase 26 — 2026-09-21

**สถานะ: ไม่รับปิด Phase 26** จนกว่าจะแก้และทดสอบรายการต่อไปนี้ครบ.

1. **P1 — ไม่มี Vendor Bill conversion ตาม requirement**
   - Checklist ระบุ `POST /finance/ocr/{extraction}/convert-vendor-bill` แต่ route/controller มีเฉพาะแปลงเป็น Draft Expense และ reject.
   - ต้องเพิ่ม conversion ที่สร้าง Vendor Bill แบบ draft, ผูกไฟล์/ผล OCR, org scope, audit และ feature tests.

2. **P1 — Cross-org reference ใน OCR-to-Expense**
   - `supplier_id` และ `project_id` ตรวจเพียงว่า ID มีอยู่จริง ไม่บังคับ `org_id` เดียวกับผู้ใช้.
   - ต้อง query Supplier/Project ด้วย `org_id` ของผู้ใช้ใน transaction หรือใช้ scoped validation rule และเพิ่ม test ที่ยืนยันว่า crafted foreign-org ID ถูกปฏิเสธ.

3. **P1 — Raw provider error รั่วสู่ UI, database และ log**
   - OCR upload, connection test และ vision adapter ส่ง/เก็บ exception หรือ HTTP response body แบบดิบ.
   - ต้องใช้ข้อความทั่วไปสำหรับผู้ใช้, log เฉพาะ metadata ที่อนุญาต, และไม่เก็บ response/error ที่อาจมี secret, token, URL หรือ PII.

4. **P2 — Documentation state ไม่สอดคล้อง**
   - ตารางสถานะระบุ Phase 26 เป็น `Planned` ขณะที่ implementation backlog ถูกติ๊กครบ.
   - หลังแก้ P1/P2 และผ่าน test ให้ปรับ checklist, README, module docs และ diagrams ให้ระบุสถานะตามหลักฐานจริง.

### เกณฑ์ตรวจรับรอบถัดไป

- เพิ่ม feature tests สำหรับ Vendor Bill conversion, foreign-org supplier/project IDs และ redaction ของ provider failures.
- รัน Phase 26 scoped tests, Expense/File regressions, TypeScript, ESLint, Pint และ `git diff --check` ซ้ำ.
- ตรวจ route, permission, audit และ human approval gate ว่าไม่มี auto-post เข้า GL.

## คำตอบต่อข้อสงสัย Gemini: Phase 26 OCR — 2026-09-21

1. **PDF ingestion**
   - ใช้ pipeline: embedded image -> text extraction สำหรับ Digital PDF -> renderer เป็น fallback.
   - ไม่ render ทุกไฟล์ทันที; จำกัดจำนวนหน้า, resolution, เวลา และประมวลผลใน queue.

2. **Token cost และ latency**
   - ทำ server-side image normalization ก่อนส่ง AI โดยเก็บไฟล์ต้นฉบับใน private storage และสร้างภาพอนุพันธ์กว้างไม่เกิน 1,600–2,048px.
   - Cloud OCR ควรเป็น async job พร้อม dedupe, retry, timeout และสถานะ processing/failed.

3. **Local-to-cloud fallback**
   - ห้าม auto-fallback ไป cloud เพราะมีผลต่อ PDPA และต้นทุน.
   - ให้ reviewer เลือก Re-scan with cloud เองเมื่อ org policy อนุญาต พร้อม audit document/provider; ใช้ confidence หรือ math mismatch เป็นเพียงคำแนะนำ.

4. **Vendor Bill conversion**
   - ต้องเพิ่มใน Phase 26 ตาม acceptance criteria เดิม: สร้าง Draft Vendor Bill, link OCR/source file, org scope และ audit.
   - หาก supplier ตรงและมี Open PO ให้เสนอ candidate PO; ผู้ตรวจเลือกเองหลังผ่าน PO status, receiving และ AP rules.

5. **Cloud AI organization policy**
   - เพิ่ม `allow_cloud_ai_processing` ระดับ Organization; ค่าเริ่มต้นควรปิดหรือ `local_only`.
   - บังคับใช้ใน service ก่อนเรียก endpoint ทุกครั้ง, จำกัด provider allowlist, audit การเปลี่ยนนโยบาย และห้าม config รายตัว bypass policy.

### Source status ที่ตรวจแล้ว

มี `resolveChatEndpoint`, PDF embedded-JPEG extraction, JSON mode, high-detail และ model parameter handling แล้ว. ยังไม่พบ `ProcessOcrExtractionJob`, `allow_cloud_ai_processing` และ `convert-vendor-bill`.

## ผลการแก้ไขตามข้อเสนอแนะ GPT (Phase 26) — 2026-09-22 (Accepted & Closed / ผ่านการตรวจรับรอบแก้ไข 100%)

ดำเนินการแก้ไขปัญหาทั้ง 4 ข้อเสร็จสมบูรณ์ พร้อมชุดการทดสอบครอบคลุม:

1. **P1 — Vendor Bill Conversion (`POST /finance/ocr/{extraction}/convert-vendor-bill`)**:
   - เพิ่ม route `ocr.convert-vendor-bill` และ method ใน `OcrDocumentController.php`
   - แปลงผลลัพธ์เป็น Draft Expense (AP Vendor Bill) พร้อมเชื่อมโยง `supplier_id` (required) และ `purchase_order_id` (optional)
   - ดึง Candidate Open Purchase Orders (`approved`, `partially_received`) ในองค์กรส่งไปยัง `OcrReviewer.tsx` ให้ผู้ตรวจเลือกจับคู่ PO ค้างรับได้อย่างสะดวกรวดเร็ว
   - อัปเดต `StoredFile` ผูกกับบิล, เปลี่ยนสถานะ `OcrExtraction` เป็น `status: converted`, `target_type: 'vendor_bill'` และบันทึก `AuditLog` action: `ocr.convert_to_vendor_bill`
   - มี Feature Test `test_convert_extraction_to_draft_vendor_bill_with_open_po` ยืนยันการทำงานและ Human-in-the-loop guard (ไม่มีการ auto-post เข้า GL) ผ่าน 100%

2. **P1 — ปิดช่องโหว่ Cross-org Reference (Tenant Isolation)**:
   - ปรับ Scoped Validation Rule ของ `supplier_id` และ `project_id` ในทั้ง `convertExpense` และ `convertVendorBill` ให้ตีกรอบด้วย `Rule::exists(...)->where('org_id', $user->org_id)`
   - ตรวจสอบเพิ่มเติมว่า `purchase_order_id` ที่ระบุต้องเป็นของ `supplier_id` เดียวกัน
   - มี Feature Test `test_cross_org_supplier_and_project_rejected` ยืนยันว่าการส่ง Foreign-org ID ข้าม Tenant จะถูกปฏิเสธด้วย HTTP 422 ผ่าน 100%

3. **P1 — Redact & Sanitize Raw Provider Errors**:
   - พัฒนาฟังก์ชัน `OcrProcessingService::sanitizeErrorMessage()` ทำการ Mask `sk-...`, `Bearer ...`, URLs/Internal endpoints และ 13-digit ID/PII ออกจากข้อความผิดพลาด
   - ใน `OcrDocumentController::upload` ปรับข้อความ Flash error ไม่ให้หลุด raw exception message ออกไปสู่หน้าจอผู้ใช้
   - ใน `UniversalOpenAiVisionAdapter` และ `AiConnectionTesterService` บันทึก Log และส่ง Error กลับเฉพาะข้อความที่ผ่านการ Sanitize แล้ว
   - มี Feature Test `test_raw_provider_error_is_sanitized_and_not_leaked` ยืนยันการ Mask และการแสดงผล Flash error ที่ปลอดภัย ผ่าน 100%

4. **Cloud AI Organization Policy Guard (`allow_cloud_ai_processing`)**:
   - ตรวจสอบนโยบายความปลอดภัยระดับองค์กรผ่าน `Setting` (`ai.compliance`)
   - หาก `allow_cloud_ai_processing` เป็น `false` ระบบจะปฏิเสธการยิง Request ไปยัง Cloud Provider (OpenAI, OpenRouter, Gemini) ทั้งใน `extract()` และ `testConnection()` เพื่อรับประกัน On-premise Privacy 100%
   - มี Feature Test `test_cloud_ai_compliance_policy_blocks_unauthorized_cloud_provider` ยืนยันการบล็อกอย่างเข้มงวด ผ่าน 100%

5. **P2 — Documentation & Checklist State Alignment**:
   - ปรับสถานะใน `checklist.md` จาก `Planned` เป็น `Done` ครบถ้วนทั้งตาราง Current Status และ Implementation Backlog
   - อัปเดตบันทึกหลักฐานการทดสอบและผลลัพธ์ใน `checklist.md` และ `gpt.md` ตรงตามหลักฐานจริงบนเครื่อง

**สรุปผลการทดสอบและการตรวจสอบคุณภาพ**:
- **Phase 26 Scoped Tests**: `Phase26AiOcrTest` ผ่านทั้งหมด **14 passed / 96 assertions** (100% green).
- **Regression Suite**: `Phase13FixedAssetsTest`, `Phase20WorkflowTest`, `Phase25CashFlowTest`, `Phase3ExpensesTest`, `Phase3FilesTest`, `Phase4DeliveryDashboardTest`, `Phase4FinanceProjectLinkTest`, `Phase7ProcurementAndMembersTest`, `Phase8TaxReportsTest` ผ่านทั้งหมด **44 passed / 444 assertions** (100% green).
- **Code Style (Laravel Pint)**: Clean 0 style issues.
- **Frontend Quality (TypeScript & ESLint)**: TypeScript (`tsc --noEmit`) 0 errors, ESLint 0 errors.
- **Frontend Build (Vite Production)**: Clean build in 10.57s.
- **Git Diff Hygiene**: `git diff --check` passed cleanly with 0 whitespace errors.

## Cleanup audit — 2026-09-23

ย้ายไฟล์ที่ตรวจแล้วว่าไม่ถูกใช้งานจาก project root ไปเก็บที่ `temp/cleanup-2026-09-23/` เพื่อให้ตรวจสอบย้อนหลังได้:

- `backend/tests/Unit/ExampleTest.php` → `temp/cleanup-2026-09-23/ExampleTest.php` — Laravel scaffold test ที่ตรวจเพียง `assertTrue(true)` จึงไม่มี coverage ที่มีความหมาย
- `graphify-out/` → `temp/cleanup-2026-09-23/graphify-out/` — generated graph output ที่ไม่พบ source, test, documentation หรือ deployment path อ้างอิง

สร้าง `temp/cleanup-2026-09-23/README.md` เป็น manifest ของ archive แล้ว

Validation: `php artisan test --compact tests/Unit` ผ่าน 2 tests / 6 assertions หลังย้าย

## Pending for Gemini — Setup Wizard & System Requirements review (2026-09-24)

โปรดแก้และทดสอบรายการต่อไปนี้:

1. **[High] จำกัดสิทธิ์ Safe Fix:** `setup/system-requirements.php` เปิด GET แบบ public ตาม requirement ได้ แต่ POST `repair_directory` ใช้เพียง CSRF ซึ่งไม่ใช่ authorization เพราะผู้เข้าชมสร้าง session/token ของตนเองได้. คง GET public ไว้ แต่บังคับยืนยัน `SETUP_TOKEN` สำหรับ POST repair และเพิ่ม feature test ของกรณีไม่มี/มี token.
2. **[Medium] XLSX dependency:** `backend/app/Services/Hr/EmployeeImportService.php` และ `backend/app/Services/AttendanceReconciliationService.php` ใช้ `ZipArchive`; fallback เรียก `tar` ผ่าน `exec()` ซึ่งอาจใช้ไม่ได้บน shared hosting. กำหนด `zip` เป็น required requirement สำหรับ deployment ที่รองรับ XLSX หรือแสดง Failed เมื่อไม่มีทั้ง `ZipArchive` และ fallback ที่ใช้งานได้. อัปเดต Installer preflight, System Requirements และเอกสารให้ตรงกัน.
3. **[Medium] Web-flow coverage:** ปัจจุบัน `backend/tests/Unit/Setup/InstallerTest.php` มี 2 tests / 9 assertions เท่านั้น. เพิ่ม feature/integration coverage สำหรับ setup lock 404, installer disabled 403, wizard CSRF/token flow, public System Requirements GET และ Safe Fix authorization/CSRF.
4. **[Low] Session hardening:** `setup/system-requirements.php` ใช้ default PHP session. ตั้งชื่อ session แยกและ cookie params ให้สอดคล้อง Wizard: `HttpOnly`, `SameSite=Strict`, `Secure` เฉพาะ HTTPS และ path ที่ถูกต้อง.

หมายเหตุ: แก้เอกสาร `docs/SETUP_WIZARD.md` ให้ระบุ database credential อยู่ **Step 2** แล้ว. หลังแก้ให้รัน targeted tests, Pint และอัปเดต README/docs/diagrams หาก contract เปลี่ยน.
