# Gemini Review & Architecture Reference

Last updated: 2026-09-21 (Post Phase 26 AI OCR Document Ingestion Complete & Closed)

Purpose: บันทึกข้อมูลอ้างอิงสถาปัตยกรรม (Architecture Reference), Security Guardrails, ข้อกำหนดเชิงระบบที่ยังมีผลต่อการพัฒนา (Decisions That Still Matter), และแนวทางการพัฒนาต่อยอดสำหรับ Phase ที่ยังไม่เริ่ม. รายละเอียดงานย้อนหลัง Phase 1 - 26 ดูได้ที่ `checklist.md`, `README.md`, `gpt.md`, `docs/` และ Git history.

---

## 1. สถานะโครงการปัจจุบัน (Current Status)

**สถานะล่าสุด (2026-09-21): Phase 26 (AI OCR Document Ingestion — Local AI & OpenAI-Compatible Cloud Hub) เสร็จสมบูรณ์และผ่านการตรวจรับ 100% (Complete & Closed) พร้อมสถาปัตยกรรมเชื่อมต่อ GPT/OpenAI แบบ Bulletproof**

- **Phase 1 - 26 (รวม Phase 18.1, 23.1, 23.2, 23.3, 24, 25, 26): เสร็จสมบูรณ์และผ่านการตรวจรับรอง 100% (Complete & Closed)**
  - Core MVP, CRM/Sales, Finance, Delivery, Multi-role Dashboards, VAT/Number Sequences, Suppliers & POs (Phase 1 - 7)
  - Commercial Documents, Tax Reports/Aging/WHT, Treasury, Encrypted Bank Accounts, Bank Reconciliation, Petty Cash, Cheques/PDC (Phase 8 - 10)
  - General Ledger & Double-Entry Accounting, E-Tax XML/RD Prep, Fixed Assets & Straight-Line Depreciation (Phase 11 - 13)
  - Multi-Currency & Rate Snapshot, AP Subledger (`VendorPayment`), FCD Accounts, Inventory FX Bridge (Phase 14 - 14.1)
  - Advanced Inventory, Warehouses, Bins, Lots & Expiry, Barcode/QR Scanning, Stock Differencing (Phase 15)
  - Payroll, Social Security, Progressive Tax, Direct Settlement, Payslip PDF & Privacy Guard, ภ.ง.ด. 1 Workpapers (Phase 16B)
  - Enterprise Document Management (DMS), Central Repository, Immutable Versioning, 5-Level Sensitivity RBAC, Retention Policies (Phase 17)
  - Security 2FA / Offline RFC 6238 TOTP, Encrypted Secrets, Single-Use Recovery Codes, Trusted Devices, Owner Reset (Phase 18)
  - DMS Security Remediation, Parent-Permission Authorization (9 Modules), Automated Retention & Legal Hold (Phase 18.1)
  - HR Core, Attendance Privacy (Opt-in SHA-256 Hashed IP / Optional GPS), Leave Entitlement, Locked Attendance Summary (Phase 19)
  - Central Dynamic Approval Workflow Engine, Immutable Snapshots, SoD Deadlock Guard, Execution Modes, Delegations, Multi-Step Builder, Source Module Triggers, Leave Refund & Expense Double-Entry GL Posting (Phase 20)
  - Operational Notifications & Outbox Engine, Encrypted Channel Vault (LINE OA / Slack / Telegram), Transactional Outbox, Exponential Backoff & Dead-Letter Log, User Quiet Hours & Urgency Filtering, Safe Daily Operations Digest (Phase 21)
  - Customer & Supplier Self-Service Portals, External Identity Isolation, Passwordless Magic Link, Scoped Portal Sessions, Quotation Acceptance Audit, Vendor Bill Submission Intake & Scanner Quarantine Gate (Phase 22)
  - PromptPay Thai QR & Payment Gateway Settlement, Exact Satang (Tag 29/30), Opn API Checkout & Event Verification, HMAC-SHA256 Signed Settlement Bridge, Replay/Duplicate Guard, Atomic Payment & Double-Entry GL (Phase 23 - Live Bridge Deferred)
  - System-wide Master Dialog & Single Active Editor Standard (Phase 23.1 - Complete & Closed): มาตรฐาน Modal Dialog 60 หน้า 92 ไฟล์, `useEditorCoordinator`, dirty detection ครบทุก field (รวม `orgStructureDirty.ts` สำหรับ Org Structure), idempotent submission keys, discard guard
  - System-wide Dashboard Redesign & Reorganized Navigation (Phase 23.2 - Complete & Closed): แดชบอร์ด 8 โดเมนภายใน (Executive, Sales, Finance, Delivery, Procurement, HR, Admin, My Work) และ 2 พอร์ทัลภายนอก, ปฏิบัติตาม `Metric Dictionary v1` เคร่งครัด, Native Accessible SVG Charts, FilterBar URL state persistence, Widget-level authorization ผ่าน `DashboardAccess`, 0 raw cash balance leak, ปรับโครงสร้างเมนู 7 หมวดหมู่มาตรฐาน
  - HR Employee Master, Shift Scheduling, Attendance Reconciliation & Non-User Payroll (Phase 23.3 - Complete & Closed): แบบจำลอง `Employee` แยกอิสระจาก `User`, เข้ารหัสเลขบัตร ปชช./บัญชีธนาคาร at rest, Roster matrix calendar, Attendance Import รองรับ Excel (`.xlsx`, `.xls`, `.xml`) และ CSV/TSV, Reconciliation Engine คำนวณชั่วโมงทำงานจริง กะข้ามวัน (Overnight Shift) OT ปกติ 1.5x / วันหยุด 2.0x / วันหยุดพิเศษ 3.0x และหักมาสายตาม grace period, Non-user payroll รองรับค่าจ้างรายเดือน/รายวัน/รายชั่วโมง และส่งสลิปเงินเดือนทางอีเมล (`PayslipMail`)
  - e-Tax Invoice & RD Prep Gateway Architecture (Phase 24 - Complete & Closed): สถาปัตยกรรม Gateway เต็มรูปแบบรองรับโหมด disabled, manual_export, mock, provider (RD Web Service, Certified Provider INET, Sandbox Gateway), ท่อประมวลผลลายมือชื่อดิจิทัล ETDA XML-DSig (RSA-SHA256, C14N canonicalization, DigestValue, KeyInfo, X.509 cert reference), SubmitETaxDocument queue worker พร้อมเพดาน retry 3 ครั้ง, Webhook callback controller พร้อมการตรวจสอบ HMAC-SHA256 signature และคำสั่ง artisan etax:inquire-status สำหรับดึงสถานะเอกสารอัตโนมัติ, เข้ารหัส provider credentials at rest (AES-256) และแยก Certificate private keys ไว้นอกฐานข้อมูลอย่างเคร่งครัด
  - Cash Flow Forecasting & Financial Health Analytics (Phase 25 - Complete & Closed): ระบบพยากรณ์กระแสเงินสดล่วงหน้า 30-180 วัน ด้วย 3 สถานการณ์ (Base, Best, Worst), Engine คำนวณ Inflow (AR Aging + Deal Pipeline Weighted Probability) และ Outflow (AP Expenses + Open Purchase Orders + Scheduled Monthly Payroll), คำนวณอัตราส่วนสภาพคล่อง Current Ratio, Quick Ratio, DSO, DPO, และ Working Capital Runway, Interactive SVG Projection Trajectory Curve, Scenario Parameter Tuning, และ Artisan Snapshot Command `cashflow:snapshot`
  - HR Employee Position Master & Searchable Combobox Enhancement (2026-09-20): แยกตารางตำแหน่งพนักงานเป็น Master Table อิสระ (`positions`), Eloquent Model `Position`, รองรับ multi-tenant (`org_id`), soft deletes, เชื่อมต่อกับ `employees.position_id` พร้อมการ backfill ข้อมูลเก่าและ synchronize `position_title` แบบ bidirectional สำหรับ backward compatibility. ฝั่งหน้าบ้านพัฒนา Searchable & Filterable Combobox (`PositionCombobox` บน Headless UI) พร้อม Inline Quick-Add modal ในหน้า `Employees.tsx`, ปรับแต่ง Base CSS สำหรับ Dark Mode ของ Combobox และ `<select>` ทั่วทั้งระบบ
  - HR Bulk Employee Import Enhancement (2026-09-20): พัฒนาระบบนำเข้าประวัติพนักงานแบบชุด (`EmployeeImportService`) รองรับ CSV, TSV, XLSX, XLS/XML พร้อมไฟล์แม่แบบ (`employee_import_template.csv`), ระบบตรวจสอบข้อมูลและจับคู่ตำแหน่ง/แผนก/สาขาอัตโนมัติ, ตัวเลือกสร้างตำแหน่งใหม่อัตโนมัติ (Auto-create Position) หรืออัปเดตพนักงานเดิมเมื่อรหัสซ้ำ (Update Existing), เข้ารหัสเลขบัตร ปชช./เลขบัญชีธนาคาร at rest ตามเกณฑ์ PDPA, และหน้าจอ `EmployeeImportDialog` ในหน้า `Employees.tsx`
- **สถานะการทดสอบระบบ (Validation Snapshot — 2026-09-20):**
  - Phase 25 Feature Tests: **8 Tests Passed (38 Assertions / 0 Failures)** (`Phase25CashFlowTest` ผ่าน 100%)
  - e-Tax Suite Tests: **18 Tests Passed (122 Assertions / 0 Failures)** (`Phase12ETaxTest`, `Phase24ETaxFoundationTest`, `Phase24ProviderGatewayTest` ผ่าน 100%)
  - Employee Master & Import Suite Tests: **22 Tests Passed (94 Assertions / 0 Failures)** (`EmployeeBulkImportTest` 8 passed / 47 assertions, `EmployeePositionMasterTest` 7 passed / 23 assertions, `Phase233EmployeeMasterTest` 7 passed / 24 assertions ผ่าน 100%)
  - Full Backend Regression Tests: **380+ Tests Passed / 2 Skipped (3,680+ Assertions / 0 Failures)**
  - Code Style & Formatting: **Prettier Clean (`npm run check-format` exit 0)**, **Laravel Pint Clean (0 issues)**
  - Static Code Analysis: **ESLint Clean (0 warnings / 0 errors)**, **TypeScript `tsc` Clean (0 errors)**
  - Frontend Build: **Vite Production Build Clean (0 errors)**
- **สถานะงานปัจจุบัน:**
  - **Phase 25: Cash Flow Forecasting & Financial Health Analytics (Complete & Closed):** เสร็จสมบูรณ์ทั้ง Service, Controllers, Views, Artisan Scheduling, และ Feature Tests ครบ 100%
  - **Phase 26: AI OCR Document Ingestion (Complete & Closed):** Universal OpenAI-Compatible Vision รองรับ Local AI (Ollama/LM Studio) และ Cloud GPT/OpenRouter/Gemini, Thai Tax ID Modulo 11, Math Reconciliation, Split-Screen Reviewer, และ Assisted Draft Guard เสร็จสมบูรณ์ 100%
  - **Setup Wizard Security & Bug Fixes (Complete & Verified):** แก้ไขประเด็น `isEnabled()` รองรับ `SETUP_TOKEN`, Preflight clean release check, และเพิ่ม Unit Test `InstallerTest` ผ่าน 100%
- **Closed Phase Governance:** งานที่เป็น Security Vulnerability, Data Integrity Bug, Regression Fix หรือ Production Blocker ใน Phase ที่ปิดแล้ว (Phase 1-26) สามารถแก้ไขได้โดยต้องมี Feature Test ครอบคลุมและบันทึกเหตุผลชัดเจน ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-26 โดยไม่มี Decision/Checklist ใหม่

---

## 2. ข้อมูลอ้างอิงการควบคุมความปลอดภัย (Active Security Guardrails Reference)

รายการควบคุมความปลอดภัยที่ต้องคงอยู่ในการพัฒนาต่อยอดทุกส่วน:

| หัวข้อความปลอดภัย | รายละเอียดการควบคุมความปลอดภัย (Security Control) |
| :--- | :--- |
| **Directory Traversal Protection** | ตรวจสอบ Canonical Path ด้วย `realpath()` และ `str_starts_with($fullPath, $basePath)` ป้องกันการเข้าถึงไฟล์นอกพื้นที่จัดเก็บไฟล์สาธารณะอย่างสมบูรณ์ |
| **Invite Token Protection** | ซ่อนและไม่ส่ง Plain Invite Token และ URL ใน Flash Session หากอยู่ในสภาพแวดล้อมที่เป็น `production` |
| **Audit Logs Redaction** | ทำงานผ่าน Central Redaction Guard ใน Eloquent `saving` event เพื่อเซนเซอร์คำสำคัญ (password, token, secret) และทำ Masking เลขประจำตัวประชาชน (`person_id`, `citizen_id`) อย่างต่อเนื่อง |
| **Upload & Attachment Security** | ตรวจสอบนามสกุลและ MIME Type ของไฟล์แนบอย่างเข้มงวด ป้องกันไฟล์ Executable/Polyglot และจัดเก็บใน storage key ฝั่ง server ใต้ `tenants/{org_id}/...` เท่านั้น |
| **Session Invalidation** | เมื่อมีการ Disable บัญชีผู้ใช้ หรือมีการเปลี่ยนแปลง Role หรือปรับปรุงระดับสิทธิ์ (Permissions) หรือพนักงานลาออก (`resigned`/`terminated`) ระบบจะทำลายเซสชันเก่าในตาราง `sessions` และล้าง `remember_token` ของผู้ใช้รายนั้นทันทีเพื่อบังคับให้ออกจากระบบ |
| **Financial Isolation & Privacy** | ข้อมูลการเงินรวมทั้งหมดบน Dashboard แสดงผลเฉพาะผู้ที่มีสิทธิ์ `executive.dashboard.view` หรือผู้ดูแลระบบ (Owner/Admin) เท่านั้น พนักงานปกติจะไม่มีการดึงหรือเห็นข้อมูลสรุปการเงิน |
| **Bank Account & Key Data Protection** | เลขบัญชีธนาคารจัดเก็บแบบเข้ารหัส (`encrypted` cast) พร้อม `account_number_hash` (SHA-256) และ Certificate ของ e-Tax เก็บเฉพาะ Vault Reference/Expiry Date เท่านั้น (ห้ามเก็บ Private Key ใน Database) |
| **Payslip & Payroll Privacy Guard** | พนักงานแต่ละคนสามารถเข้าถึงและดาวน์โหลด Payslip PDF ได้เฉพาะรายการที่เป็นของตนเองเท่านั้น (`user_id === auth()->id()`) เว้นแต่เป็นผู้ถือสิทธิ์ `payroll.view` ในองค์กรเดียวกัน และห้ามข้ามองค์กร 100% |
| **DMS Private Download & Scan Guard** | ไฟล์เอกสารส่วนกลางดาวน์โหลดผ่าน Private Download Controller ที่ตรวจสอบ Session, `org_id`, สิทธิ์การเข้าถึง, Parent Permission, ความลับ (Sensitivity 5 ระดับ), และสถานะความปลอดภัยของไฟล์ (`scan_status === 'clean'`) ทุก Request 100% |
| **Two-Factor Authentication & Secret Guard** | `two_factor_secret` และ `two_factor_recovery_codes` ถูกเข้ารหัสด้วย Eloquent `encrypted` cast, ซ่อนจาก JSON serialization (`#[Hidden]`), Recovery Codes ถูกแฮชด้วย `Hash::make` และเป็น Single-Use, Trusted Device Tokens เก็บเฉพาะ SHA-256 Hash ผูกกับ User-Agent, และการรีเซ็ต 2FA สงวนสิทธิ์เฉพาะ Owner ในองค์กรเดียวกัน |
| **Attendance Privacy & Consent Guard** | การจัดเก็บ IP และ GPS ในการลงเวลาทำงานต้องเป็นไปตามหลักความยินยอม (Opt-in Consent) ภายใต้ `hr.attendance_privacy` โดยค่าเริ่มต้นคือ `capture_ip=false`, `capture_gps=false`, และ `require_employee_consent=true` หากเปิดการบันทึก IP ระบบจะจัดเก็บเฉพาะ SHA-256 Hash และห้ามจัดเก็บ Raw IP เด็ดขาด |
| **No Cash Balance Guard** | ห้ามสร้าง UI Widget, JSON/Inertia Prop หรือ API เผยแพร่ยอด `Cash Balance` ดิบหรือยอดเงินสดรวมใน Dashboard หรือ Daily Digest Notification จนกว่าจะมี Product Decision และ Bank Reconciliation Boundary ที่ชัดเจน |
| **AI OCR & Automation Guard** | โมดูล AI / OCR สกัดข้อมูลสำหรับร่างเอกสาร (Assisted Draft) พร้อมแนบค่า Confidence และหลักฐานต้นทางเท่านั้น ห้าม Auto-post รายจ่ายหรือบันทึกบัญชี GL โดยปราศจาก Human Review |
| **Workflow SoD & Immutable Snapshot Guard** | ห้ามผู้ขออนุมัติ (Requester) อนุมัติตนเองทุกกรณี (Strict SoD) โดยระบบกรอง Requester ออกตั้งแต่ระดับ Assignee Snapshot และตรวจบล็อกซ้ำใน Action Evaluation พร้อมจับภาพ Snapshot ลำดับขั้นตอน ณ วันยื่นคำขอเพื่อป้องกันผลกระทบจากการปรับผังองค์กรย้อนหลัง |
| **Notification Outbox & Secret Protection Guard** | Credentials ของช่องทางภายนอก (`notification_channels.config` เช่น Webhook URL, Access Token, Bot Token) ต้องจัดเก็บแบบ `encrypted:array` เสมอ ห้ามรั่วไหลไปยัง Audit Logs หรือส่งไปยัง Inertia Client Props เด็ดขาด และสงวนสิทธิ์การจัดการเฉพาะผู้ถือสิทธิ์ `settings.organization.update` |
| **Portal Identity & Quarantine Guard** | External Portal แยก Identity และ Session (`portal_users`, `portal_sessions`) ออกจากระบบสตาฟฟ์ 100%, Magic Link แบบ Single-use (30 นาที) เก็บเฉพาะ SHA-256 Hash, ไฟล์ใบวางบิลจากคู่ค้าถูกกักกันด้วยสถานะ `pending_scan` ห้ามแปลงเป็น AP Invoice หรือลง GL อัตโนมัติเด็ดขาดจนกว่าจะผ่านการ Scan และ Finance Staff ตรวจสอบยืนยัน |
| **Payment Gateway & Settlement Bridge Guard** | `recipient_id`, `webhook_secret`, และ `provider_secret` ต้องเข้ารหัสแบบ `encrypted` และซ่อนใน `$hidden` เสมอ, การบันทึก Payment และ Double-Entry GL เกิดขึ้นได้เฉพาะเมื่อมี Settlement Webhook ที่ลงลายมือชื่อ HMAC-SHA256 ถูกต้องพร้อม Timestamp Window ไม่เกิน 300s, การรับ Webhook ของ Opn บันทึกเพียงสถานะ `provider_confirmed` (ห้ามลงบัญชี GL), Opn Test Mode และกรณี Timeout ก้ำกึ่ง (`creation_unknown`) ห้ามลงบัญชีเด็ดขาด, และการเชื่อมต่อ Settlement Bridge จริงถือเป็น Deferred Live Integration จนกว่าจะมีการขอเชื่อมต่อจริง |
| **Single Active Editor & Discard Guard** | ป้องกันข้อมูลสูญหาย (Data Loss) จากการปิด dialog หรือสลับ editor โดยไม่ตั้งใจ: หน้าจอ Master ที่มีหลาย modal editor (Inventory, Payroll) ต้องควบคุมผ่าน Guarded Switch Coordinator (`switchEditor` + `onRequestCloseRef`) เสมอ หาก draft dirty ต้องถามยืนยัน Discard Changes ก่อนสลับ และบล็อกการสลับขณะ processing 100% |
| **Dashboard Scoped Authorization & Metrics Guard** | การแสดงผล Dashboard และ Metrics ทุกตัวต้องผ่าน Effective Permissions (`DashboardAccess`) ห้ามใช้ Role Name ในการ bypass, แยก Period Flow ออกจาก Current Snapshot, คำนวณขอบเขตวัน/เวลาอิงตาม Timezone ขององค์กร และห้ามเผยแพร่ Unreconciled figures ในฐานะ Accounting facts |
| **Employee Master & PDPA Guard** | ข้อมูลส่วนบุคคลของพนักงาน (`citizen_id`, `tax_id`, `bank_account_number`) ต้องเข้ารหัสแบบ Eloquent `encrypted` cast และทำ Masking เมื่อส่งออก UI/Logs, เมื่อพนักงานลาออกหรือถูกเลิกจ้าง (`resigned`/`terminated`) ระบบจะตัดสิทธิ์และ Disable บัญชี User ที่ผูกไว้พร้อมทำลาย Session ทันที |
| **Shift & Attendance Reconciliation Guard** | การคำนวณเวลาทำงาน OT (1.5x, 2x, 3x) และหักมาสายต้อง Snapshot อัตราและเกณฑ์จาก `EmployeeShift` ของกะนั้นๆ เสมอ และรองรับช่วงเวลาสืบค้นกะข้ามวัน (Overnight Shift), การแก้ไขเวลา Punch ด้วยมือต้องบันทึกเหตุผลและ Audit Log เสมอ |
| **Non-User Payroll & Payslip Delivery Guard** | สลิปเงินเดือนของพนักงานที่ไม่มี User Account ในระบบ ให้จัดส่งทางอีเมลส่วนตัว (`personal_email`) ผ่าน `PayslipMail` ภายใต้การควบคุมสิทธิ์ `payroll.manage` โดยต้องไม่เปิดเผยข้อมูลเงินเดือนแก่พนักงานอื่น |

---

## 3. โครงสร้างและการเข้าถึงข้อมูล (Active Architecture & Access Scope)

- **การคำนวณราคาทุนจริงโครงการ (Dynamic Project Cost):** คำนวณแบบ Dynamic บน Memory จากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`) และห้ามมีคอลัมน์ `actual_cost` ในตาราง `projects`
- **การจำกัดสิทธิ์พนักงาน (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
- **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ
- **ความถูกต้องทางภาษีและการค้า (Tax & Commercial Integrity):** ห้ามใช้วิธี Void กับใบกำกับภาษีหรือเอกสารการค้าทางการที่ส่งมอบหรือยื่นรอบบัญชีแล้ว ให้ใช้วิธีออก Credit Note / Debit Note เพื่อปรับยอดหนี้และกระทบยอดภาษีขายตามกฎหมายสรรพากร
- **ระบบบัญชีคู่และงวดบัญชี (GL & Period Lock):** บันทึก Journal Entries อัตโนมัติจากเอกสารต้นทาง (Invoice, Payment, VendorPayment, Expense, Stock, CN/DN, PV/RV, Bank, Petty Cash, Fixed Asset, FX, Payroll, Workflow Approved Expense) มี Idempotency Guard ป้องกันการลงซ้ำ และงวดบัญชีที่ปิด (`closed`) ห้าม Post/Void/แก้ไขย้อนหลังเด็ดขาด
- **Multi-Currency & Rate Snapshot:** เอกสารทางการค้า/จัดซื้อ/การเงิน ทำการ Snapshot `currency`, `exchange_rate` (`DECIMAL(18,6)`), และ `base_*` amounts (`DECIMAL(18,2)`) เสมอ ห้ามทำ Dynamic Join กับ `exchange_rates` ในการคำนวณย้อนหลัง
- **AP & Inventory Costing Boundary:** `Expense` (Vendor Invoice) เป็นแหล่งตั้งหนี้ AP (`2110`) เท่านั้น ส่วน `GRN` บันทึกเป็น Inventory เข้าบัญชีพักสินค้า/GRNI (`1150`) และบันทึก `base_unit_cost` / `base_total_cost` บน `StockMovement` เป็น Single Source of Truth
- **Warehouse & Storage Scope:** ทุกการเคลื่อนไหวสต็อก (Stock Movement) และเอกสารคลัง (GRN, Transfer, Stock Count, DO) ต้องตรวจสอบว่า `warehouse_bin_id` อยู่ใน `warehouse_id` และ `inventory_lot_id` อยู่ใน `product_id` ขององค์กรเดียวกันอย่างเคร่งครัด
- **Payroll & AP Boundary:** การจ่ายเงินเดือนพนักงาน (`PayrollRun`) บันทึกผ่านบัญชีค้างจ่าย `2140` (Payroll Payable) และตัดจ่ายเข้าบัญชีเงินสด/ธนาคารโดยตรง โดยไม่ผ่านตาราง `VendorPayment` (AP Subledger) และจำกัด Initial Scope เป็นสกุลเงิน THB เท่านั้น
- **DMS Central Repository & Cross-Module Linking:** จัดเก็บเอกสารองค์กรแบบรวมศูนย์ (`documents`, `document_versions`) พร้อมเชื่อมโยง Many-to-Many ผ่าน `document_links` มีระบบตรวจอายุเอกสารตามหมวดหมู่ และบังคับใช้นโยบายการเก็บรักษาเอกสารภาษี/บัญชี 5-7 ปี (Retention Window) แบบ Append-Only พร้อมคำสั่ง `documents:enforce-retention [--purge]`
- **DMS Parent-Permission Authorization:** การเข้าถึง ดาวน์โหลด หรือสร้าง `document_links` ผูกกับเอนทิตีภายนอก (Customer, Supplier, PO, Deal, Project, Task, Fixed Asset, Bank Account, Expense) ต้องผ่านการตรวจสอบสิทธิ์ Parent Entity Permission และ Record Scope เสมอ
- **Two-Factor Policy & Privileged Role Enforcement:** นโยบายความปลอดภัย 2FA ควบคุมระดับองค์กรผ่าน `security.two_factor` บังคับให้บทบาทที่มีสิทธิ์เข้าถึงข้อมูลอ่อนไหว (`owner`, `admin`, `finance`) ต้อง Enroll 2FA ก่อนเข้าถึงระบบ โดยมี Middleware `EnsureTwoFactorEnrollment` ควบคุมทุก Web Request
- **HR Attendance, Leave & Payroll Bridge Boundary:** บันทึกเวลาและคำนวณวันลาตัดวันหยุด/เสาร์-อาทิตย์ พร้อมตัด/คืนวันลาแบบ Atomic Transaction (`lockForUpdate`), Attendance Summary รองรับ Cutoff Date และมีสถานะ `draft`, `locked`, `reversed` (ด้วย `reversal_of_id`) โดย**ห้ามบันทึกอัตโนมัติ (No Auto-Posting)** ไปยัง `payroll_runs`, `payroll_items`, หรือ General Ledger ก่อนได้รับการอนุมัติและ Lock รอบบัญชีเงินเดือน
- **Central Dynamic Approval Engine:** ระบบอนุมัติเอกสารรวมศูนย์ (`WorkflowEngineService`) จัดเก็บประวัติขั้นตอนเป็น Snapshot แบบคงที่ บังคับใช้ Segregation of Duties (ห้ามผู้อนุมัติตนเอง) รองรับ Sequential และ Parallel (First-to-Approve), ระบบ Delegation แสดงผลใน Inbox และเมื่อ Expense ผ่านการอนุมัติขั้นสุดท้ายจะเชื่อมต่อเข้ากับ Double-entry GL อัตโนมัติ ส่วน Leave Request ที่ถูก Rejected จะคืนยอดวันลาเข้า `leave_balances` เสมอ
- **Operational Notification Outbox Architecture:** ส่งข้อความแจ้งเตือนผ่าน Transactional Outbox Pattern (`notification_outbox`) แยกช่องทาง (In-App, Email, LINE, Slack, Telegram) มี Idempotency Key ป้องกันการส่งซ้ำระดับ DB, ระบบ Exponential Backoff ($2^{\text{attempt}} \times 60$ วินาที) สูงสุด 5 ครั้งก่อนเข้าสถานะ Dead-Letter (`failed`), เคารพ User Quiet Hours ข้ามคืน และคำสั่ง Daily Executive Digest ห้ามรวมยอดเงินสดเด็ดขาด
- **Master Modal/Dialog Standard (Phase 23.1):** การจัดการฟอร์มสร้าง/แก้ไขข้อมูลหลัก ใช้มาตรฐาน Single Active Modal Editor แยกชัดเจนระหว่าง Create และ Edit โหมด, จัดการ double-submit ด้วย idempotent submission key, ตรวจสอบ dirty state ละเอียดทุก field, และกักกันการปิด/เปลี่ยนหน้าด้วย `useDiscardGuard`
- **Dashboard Architecture & Metric Contract (Phase 23.2):** ระบบแดชบอร์ดใช้ `DashboardAccess` ในการคัดกรอง widget ตาม permissions จริง, แยก Period Flow ออกจาก As-of Snapshot, แสดงกราฟด้วย Native Accessible SVG ที่มีคีย์บอร์ดนำทางและตารางข้อมูลสำรอง (Data Table Disclosure), มี URL-backed period state รองรับ Today/This Week/This Month/Custom Range
- **Employee Master & Attendance Engine Architecture (Phase 23.3):**
  - แบบจำลอง `Employee` เป็นอิสระจากบัญชีผู้ใช้ระบบ (`user_id` เป็น nullable foreign key 1:optional-1) รองรับพนักงานที่ไม่มีความจำเป็นต้องเข้าใช้งานระบบ ERP
  - เชื่อมโยงสายการบังคับบัญชา (`manager_id`), โครงสร้างแผนก/สาขา/ฝ่าย, ประเภทการจ้าง (monthly, daily, hourly, contractor)
  - ตารางกะรายวัน/รายเดือน (`employee_shift_schedules`), Roster Matrix Calendar, และ Batch Shift Assignment
  - นำเข้าเวลาสแกนนิ้ว/บัตร (`attendance_raw_logs`) ทั้งรูปแบบ Excel (`.xlsx`, `.xls`, `.xml`) และ CSV/TSV
  - Reconciliation Engine คำนวณชั่วโมงทำงานจริง, ปรับกะข้ามวัน (Overnight Shift), คิดอัตราล่วงเวลาจาก Shift Master (1.5x, 2.0x, 3.0x), หักมาสาย/ออกก่อนตาม Grace Period, และตัดยอดขาดงานอัตโนมัติเมื่อไม่มีใบลา
  - Payroll Integration สำหรับพนักงานทั่วไปและพนักงานที่ไม่ใช่ User: ดึง attendance metrics เข้า `payroll_items`, จัดพิมพ์ Payslip PDF และจัดส่งสลิปเงินเดือนผ่าน `PayslipMail` ไปยังอีเมลส่วนตัว
  - **Employee Position Master (`positions` table & `Position` model):**
    - แยกโครงสร้างตำแหน่งงานเป็น Relational Master Data (`positions`: `id`, `org_id`, `department_id`, `name`, `code`, `description`, `is_active`, `deleted_at`) แทนข้อความ freeform text เดิม
    - ฟิลด์ `employees.position_id` เป็น Foreign Key ชี้ไปยัง `positions.id` (`onDelete('set null')`) โดยยังคงคอลัมน์ `position_title` ไว้เพื่อความเข้ากันได้แบบ Backward Compatibility (ซิงค์ชื่อตำแหน่งอัตโนมัติทั้งตอนสร้างและอัปเดตแบบ Bidirectional)
    - รองรับ API Endpoints `GET /hr/positions` และ `POST /hr/positions` ภายใต้การควบคุมสิทธิ์ `hr.employees.view` และ `hr.employees.manage`
    - Frontend Searchable & Filterable Combobox (`PositionCombobox` ขับเคลื่อนด้วย Headless UI) รองรับการพิมพ์ค้นหาแบบ Type-ahead, คีย์บอร์ดนำทาง (ลูกศรขึ้น/ลง, Enter, Esc), ปุ่ม Clear และ Inline Quick-Add Position Modal โดยไม่ต้องสลับหน้าจอ
  - **Dark Mode UI Standard สำหรับ Forms, Comboboxes & Select Elements:**
    - กำหนด `@layer base` ใน `resources/css/app.css` บังคับสไตล์ของ `<select>` และ `<option>` เมื่ออยู่ใน Dark Mode ให้ใช้พื้นหลังเข้ม (`#1e293b` slate-800) และตัวอักษรสีสว่าง (`#f1f5f9` slate-100) ป้องกันปัญหาสีกลืนกับพื้นหลังของ OS/Browser
    - ปรับปรุงการแสดงผลของ Combobox Dropdowns ใน `EditorFields.tsx`, `CustomerDialog.tsx`, และ `Employees.tsx` ให้มี contrast ที่คมชัดและอ่านง่ายในทุก Theme
  - **Bulk Employee Import Architecture (`EmployeeImportService`):**
    - ท่อประมวลผลนำเข้าไฟล์ประวัติพนักงานแบบรวมศูนย์ รองรับ `.csv`, `.tsv`, `.txt`, `.xlsx`, `.xls`, `.xml` (SpreadsheetML) โดยใช้ Lightweight native parsers (ZipArchive + SimpleXML)
    - ระบบ Header Normalization ยืดหยุ่น รองรับทั้งชื่อฟิลด์ภาษาอังกฤษและภาษาไทย
    - Auto-link & Resolution: เชื่อมโยงรหัส/ชื่อแผนก, สาขา, ฝ่าย และตำแหน่งงานอัตโนมัติ พร้อมสวิตช์ `auto_create_position` บันทึกตำแหน่งใหม่ลง `positions` ทันที และสวิตช์ `update_existing` เพื่ออัปเดตข้อมูลเมื่อพบรหัสพนักงานซ้ำ
    - PDPA & Security Guard: เลขประจำตัวประชาชน (`national_id`) และเลขที่บัญชีธนาคาร (`bank_account_number`) ถูกเข้ารหัสแบบ AES-256 (`encrypted` cast) ลงฐานข้อมูลเสมอ พร้อมบันทึก `AuditLog` ในการนำเข้าทุกครั้ง
    - ดาวน์โหลดไฟล์แม่แบบผ่าน Endpoint `GET /hr/employees/template` (UTF-8 BOM ป้องกันภาษาไทยเพี้ยนใน Excel) และนำเข้าผ่าน `POST /hr/employees/import` ควบคุมสิทธิ์ด้วย `hr.employees.manage`



---

## 4. ข้อแนะนำการเตรียมความพร้อมขึ้น Production (Production Deployment & Server Readiness)

1. **Background Workers & Scheduler:**
   - **Laravel Scheduler (Cron):** ตั้งค่า `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1` บนเซิร์ฟเวอร์เพื่อให้คำสั่งต่างๆ ทำงานอัตโนมัติ:
     - `notifications:dispatch --limit=100`: รันทุกนาที (everyMinute, withoutOverlapping)
     - `notifications:daily-digest`: รันทุกวันเวลา 08:45 น. (dailyAt 08:45)
     - `invoices:mark-overdue` / `invoices:notify-due-soon`: รันทุกวันเวลา 08:00 น.
     - `assets:depreciate`: รันทุกวันที่ 1 เวลา 01:00 น.
     - `fx:reverse-revaluations`: รันทุกวันที่ 1 เวลา 01:15 น.
     - `fx:revalue`: รันวันสุดท้ายของเดือนเวลา 23:30 น.
     - `documents:check-expiry`: รันทุกวันเวลา 08:15 น.
     - `documents:enforce-retention`: รันทุกวันเวลา 08:30 น.
   - **Queue Worker (Supervisor/Systemd):** รัน `php artisan queue:work --tries=3` เพื่อให้ระบบส่งอีเมลแจ้งเตือน, อีเมลสลิปเงินเดือน (`PayslipMail`) และคิวส่งเอกสาร e-Tax ทำงานเบื้องหลังโดยไม่บล็อก Web Request
2. **PHP Extensions & Excel Parser:**
   - เซิร์ฟเวอร์ควรเปิดใช้งานส่วนขยาย `ext-zip` และ `ext-simplexml` เพื่อรองรับการนำเข้าไฟล์บันทึกเวลาทำงาน Excel (`.xlsx`, `.xml`) อย่างเต็มประสิทธิภาพ (มี fallback รองรับ command-line archive tool)
3. **Thai Fonts for DomPDF:**
   - เซิร์ฟเวอร์ Linux Production ต้องติดตั้ง TrueType Fonts ภาษาไทย (เช่น Sarabun หรือ THSarabunNew) ใน `storage/fonts` หรือฝังผ่าน `@font-face` เพื่อป้องกันปัญหาสระลอยหรือภาษาไทยแสดงเป็น `???` ในไฟล์ PDF ทั้ง Invoice, PO, WHT และ Payslip
4. **Uploads & File Storage:**
   - รัน `php artisan storage:link` และตรวจสอบการตั้งค่า `upload_max_filesize` / `post_max_size` (PHP) และ `client_max_body_size` (Nginx) ให้รองรับการอัปโหลดไฟล์แนบ สลิป เอกสาร DMS และไฟล์เวลาทำงาน
5. **Environment Security & Disaster Recovery:**
   - Production `.env` ต้องตั้งค่า `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
   - ตรวจสอบ `APP_KEY` ให้คงที่เสมอ เนื่องจากการเข้ารหัส `two_factor_secret`, `two_factor_recovery_codes`, Bank Accounts, Employee Personal Info (`citizen_id`, `tax_id`), และ `notification_channels.config` พึ่งพาคีย์นี้ หาก `APP_KEY` เปลี่ยนจะทำให้ถอดรหัสข้อมูลเดิมไม่ได้
   - ตั้งค่า Automated Daily Database Backup และทดสอบ Restore Drill อย่างน้อย 1 รอบก่อนเปิดใช้งานจริง

---

## 5. แผนงานพัฒนาต่อยอดและ Guardrails (Future Roadmap & Architectural Guardrails)

### แผนผังลำดับการพัฒนาที่เห็นชอบร่วมกัน (Consensus Roadmap Phase 1 - 28):

```
[Phase 1 - 21: Core, Financials, Treasury, Inventory, Payroll, DMS, 2FA, HR, Workflows, Notifications] (Closed)
       ↓
[Phase 22: Customer & Supplier Self-Service Portals] (Closed)
       ↓
[Phase 23: Payment Gateway Bridge (Live Bridge Deferred); Phase 23.1: Master Dialog Standard] (Closed)
       ↓
[Phase 23.2: System-wide Dashboard Redesign & Reorganized Navigation] (Closed)
       ↓
[Phase 23.3: HR Employee Master, Shift Scheduling, Attendance Import & Non-User Payroll] (Closed)
       ↓
[Phase 24: Basic e-Tax Foundation (Complete) / Live Provider Gateway (Deferred)]
       ↓
[Phase 25: Cash Flow Forecasting & Financial Health Analytics] (Closed)
       ↓
[Phase 26: AI OCR Document Ingestion — Local AI & Cloud Hub] (Closed)
       ↓
[Phase 27 - 28 (Optional Verticals): Manufacturing BOM & Point of Sale (POS)] (Next Gate)
```

### ข้อกำหนดและ Guardrails สำหรับ Phase ถัดไป:

1. **Phase 24: Basic e-Tax Foundation (Complete) & Live Provider Integration (Deferred):**
   - ส่วน Foundation ทำงานแบบ opt-in mock mode และ internal provider-mapping-v1 XML สมบูรณ์แล้ว
   - การเชื่อมต่อ Live Provider จริงในอนาคตต้องเชื่อมต่อผ่าน Certified Provider หรือ RD API พร้อมระบบจัดการ Private Key ผ่าน HSM / Cloud Key Vault (ห้ามเก็บ Private Key ใน Database)
   - มี Outbox & Reconciliation State Machine, XML/PDF Sign Engine และจัดเก็บ CAdES-BES / XAdES Signature
   - Dedicated `etax` queue worker พร้อม retry, backoff และ failover monitoring
2. **Phase 25: Cash Flow Forecasting & Financial Health Analytics (Next Gate):**
   - แสดงการประมาณการกระแสเงินสดล่วงหน้า 30-90 วัน โดยต้องมี Scenario/Assumption Snapshot และไม่เผยแพร่ยอด Raw Cash Balance
   - คำนวณจาก AR Aging Weighted by Deal Probability, AP Aging, Recurring Payroll, และ Fixed Expenses
3. **Phase 26: AI OCR Document Ingestion:**
   - ทำหน้าที่เป็น Assisted Draft พร้อมคะแนนความเชื่อมั่น (Confidence Score) และแนบไฟล์ต้นฉบับใน DMS
   - บังคับให้ต้องผ่านการตรวจสอบจากมนุษย์ (Human Review) ก่อนแปลงเป็น Expense หรือ Purchase Order จริง ห้าม Auto-post เด็ดขาด
4. **Phase 27 - 28 (Optional Verticals): Manufacturing BOM & Point of Sale (POS):**
   - พัฒนาเมื่อมีความต้องการทางธุรกิจจริง
   - POS Shift Reconciliation จำกัดอยู่เฉพาะในขอบเขต POS ประจำวัน และห้ามรั่วไหลเป็น Organization-wide Cash Balance

---

## 6. แนวทางและมาตรฐานคุณภาพโค้ด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 2 และ 3 ของ `gemini.md` โดยเด็ดขาด** (Multi-Tenant Isolation, Redaction, Permission Enforcement, Session Invalidation, Payslip Privacy Guard, DMS Private Download Guard, 2FA Encrypted Secret Guard, Attendance Privacy Guard, No Cash Balance Guard, AI OCR Human-in-the-Loop, Workflow SoD Guard, Notification Outbox & Secret Protection Guard, Single Active Editor & Discard Guard, Dashboard Scoped Authorization Guard, Employee Master & PDPA Guard, Shift & Attendance Reconciliation Guard, Non-User Payroll Guard)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-23.3 โดยไม่มี Decision/Checklist ใหม่
   - การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production Blocker ใน Phase ที่ปิดแล้ว ต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality ในทุกขั้นตอนการพัฒนา:**
   - PHP Unit & Feature Tests: `php artisan test` (ต้องผ่าน 100%)
   - Frontend Unit Tests: `node --test tests/Frontend/*.test.mjs` (ต้องผ่าน 100%)
   - Frontend Build: `npm run build` (Clean 100%)
   - TypeScript Compilation: `npm run tsc` (Clean 0 errors)
   - Static Code Analysis: `npm run lint` (ESLint 0 warnings / 0 errors), `npm run check-format` (Prettier Clean), `php vendor/bin/pint --test` (Pint Clean 0 issues)

---

## 7. บันทึกการปิดและส่งมอบ Phase ล่าสุด (Phase Closure Summary)

บันทึกสรุปการตรวจรับและปิดงานที่เสร็จสมบูรณ์เรียบร้อยแล้ว:

| Phase | ขอบเขตงานหลัก | วันที่ตรวจรับปิดรอบ | เอกสารอ้างอิงและผลการตรวจรับ |
| :---: | :--- | :---: | :--- |
| **Phase 23.1** | Standard Modal Dialog (60 หน้า 92 ไฟล์), Single Active Editor Coordinator, Discard Guard, Idempotent Submission Key | 2026-09-16 | `docs/PHASE_23_1_MASTER_ROLLOUT.md`<br>ตรวจรับ Exit Criteria ผ่านครบถ้วนบน Verified Scope |
| **Phase 23.2** | System-wide Dashboard Redesign (8 โดเมน + 2 พอร์ทัล), Metric Dictionary v1, Shared Accessible SVG Charts, URL FilterBar, Widget Scoped Authorization, Reorganized Navigation (7 หมวดหมู่) | 2026-09-20 | `docs/PHASE_23_2_1_DASHBOARD_INVENTORY.md`<br>`docs/PHASE_23_2_2_METRIC_DICTIONARY.md`<br>`docs/PHASE_23_2_5_DEFERRED.md`<br>ตรวจรับผ่านเกณฑ์ Exit Criteria, Browser Matrix 60 checks ผ่าน 100% |
| **Phase 23.3** | HR Employee Master (Decoupled from Users, Encrypted at Rest), Shift Scheduling & Roster Matrix Calendar, Attendance Import (Excel/CSV) & Reconciliation Engine (Overnight Shifts, 1.5x/2x/3x OT), Non-User Payroll & Payslip Email Delivery | 2026-09-20 | `docs/PHASE_23_3_HR_EMPLOYEE_ATTENDANCE.md`<br>`gpt.md`<br>`checklist.md`<br>แก้ไขข้อเสนอแนะ P1/P2 ครบถ้วน, Feature Tests 21 passed / 77 assertions ผ่าน 100% |
| **Phase 24** | e-Tax Invoice & RD Prep Gateway Architecture: XML-DSig Signature Pipeline, Provider Gateway Integration (RD Web Service / INET / Sandbox), Webhook HMAC Callback, Async Inquiry (`etax:inquire-status`), Credential AES-256 Vault | 2026-09-20 | `docs/PHASE_24_ETAX_FOUNDATION.md`<br>`checklist.md`<br>ตรวจรับผ่านเกณฑ์ Exit Criteria, Feature Tests 18 passed / 122 assertions ผ่าน 100% |
| **Phase 25** | Cash Flow Forecasting & Financial Health Analytics: Multi-scenario projection (Base/Best/Worst, 30-180 days), AR/AP/Deals/Payroll engine, Health ratios (Current/Quick ratio, DSO, DPO, Runway), SVG Trajectory curve, Artisan snapshot | 2026-09-20 | `checklist.md`<br>`gpt.md`<br>Feature Tests `Phase25CashFlowTest` 8 passed / 38 assertions ผ่าน 100% |
| **HR Enhancement** | Employee Position Master & Searchable Combobox: แยกตาราง `positions` เป็น Master Data อิสระ, Foreign key `employees.position_id` + backward-compatible `position_title` sync, Headless UI Searchable Combobox + Inline Quick-Add, Dark mode base `<select>` fix | 2026-09-20 | `gemini.md`<br>`gpt.md`<br>Feature Tests `EmployeePositionMasterTest` 7 passed / 23 assertions ผ่าน 100% |
| **Phase 26** | AI OCR Document Ingestion: Universal OpenAI-Compatible Vision Architecture (Local AI Ollama/LM Studio 100% Privacy & Cloud Hubs), Assisted Draft Flow, Thai Tax ID Modulo 11 Verification, Math Reconciliation Check, Split-Screen Reviewer, Human-in-the-Loop Guard (No Auto-GL) | 2026-09-21 | `checklist.md`<br>`walkthrough.md`<br>Feature Tests `Phase26AiOcrTest` 10 passed / 60 assertions ผ่าน 100%, Regression Tests 37 passed / 208 assertions ผ่าน 100% |

---

## 8. บันทึกข้อสงสัย การวินิจฉัยปัญหา และแนวทางแก้ไขการเชื่อมต่อ GPT (OpenAI Diagnostic & Resolution Record)

บันทึกสรุปข้อสงสัยเชิงระบบ (Hypotheses), การวินิจฉัยสาเหตุที่แท้จริง (Root Cause Analysis), และแนวทางแก้ไขทางสถาปัตยกรรมที่นำมาปรับปรุงระบบเชื่อมต่อ GPT (OpenAI) ให้มีเสถียรภาพสูงสุดและป้องกันข้อผิดพลาดในระดับ Production:

### 8.1 ตารางสรุปข้อสงสัยและการแก้ไข (Diagnostic & Fix Matrix)

| ลำดับ | ข้อสงสัย / สมมติฐานของปัญหา (Hypothesis) | อาการและผลกระทบ (Symptom / Impact) | สาเหตุที่แท้จริง (Root Cause) | แนวทางแก้ไขทางสถาปัตยกรรม (Architectural Fix) |
| :---: | :--- | :--- | :--- | :--- |
| **1** | **Base URL & Endpoint Resolution**<br>ผู้ใช้ระบุ Base URL เป็น `https://api.openai.com` โดยไม่มี `/v1` | เกิดข้อผิดพลาด `HTTP 404 Not Found` เมื่อกดยิง "Test Connection" หรือส่งรูปสแกน | ระบบเดิมเชื่อมสตริงทื่อๆ เป็น `{$baseUrl}/chat/completions` ทำให้ปลายทางกลายเป็น `https://api.openai.com/chat/completions` ซึ่งไม่มี endpoint นี้ใน OpenAI API | พัฒนาเมธอด `AiConnectionTesterService::resolveChatEndpoint()` ตรวจสอบว่าหาก URL มี `api.openai.com` หรือพอร์ต Ollama แต่ไม่มี `/v1` ระบบจะเติม `/v1/chat/completions` ให้อัตโนมัติอย่างปลอดภัย |
| **2** | **Whitespace ใน API Key**<br>ผู้ใช้คัดลอก Secret Key (`sk-proj-...`) มาจาก OpenAI Dashboard แล้วติดอักขระช่องว่างหรือการขึ้นบรรทัดใหม่ | เกิดข้อผิดพลาด `HTTP 401 Unauthorized: Incorrect API key provided` ทั้งที่คีย์ถูกต้อง | การคัดลอกข้อความบนเบราว์เซอร์มักติด ` ` (Trailing Space) หรือ `\n` ส่งผลให้ Header `Authorization: Bearer sk-proj-... ` มีอักขระไม่พึงประสงค์ | ทำการ `trim()` ตัวแปร `$config->api_key` ทุกจุดก่อนนำไปประกอบเป็น Authorization Header |
| **3** | **Output Token Truncation**<br>ไม่ได้ระบุ `max_tokens` ใน Vision Chat Completions Payload | JSON ผลลัพธ์ถูกตัดขาดตอนกลางคัน ส่งผลให้ `RobustJsonCleaner` เกิด Parse Error และการสแกนล้มเหลว | ใน OpenAI Chat Completions เมื่อมีรูปภาพใน Input หากไม่ระบุ `max_tokens` ตัวโมเดลอาจใช้ Default Token Limit ที่จำกัด ทำให้คำตอบไม่ครบถ้วนเมื่อเอกสารมีหลายรายการ | กำหนด `max_tokens: 2500` เป็นมาตรฐาน และหากเป็นโมเดลกลุ่ม Reasoning (`o1`, `o3-mini`) จะสลับไปใช้ `max_completion_tokens: 2500` พร้อมยกเว้นไม่ส่งพารามิเตอร์ `temperature` (เนื่องจาก `o1/o3` ไม่อนุญาตให้ใช้ temperature) |
| **4** | **Enforcement of Native JSON Mode**<br>GPT ตอบคำนำหรือสรุป Markdown ปะปนมากับ JSON | ตัว Parser ต้องใช้ Regular Expression ในการควานหา JSON ซึ่งอาจเสี่ยงตกหล่นหากโมเดลตอบคำอธิบายยาว | โมเดลภาษาทั่วไปอาจพยายามอธิบายผลการสกัดข้อมูลหากไม่ได้เปิด Native JSON Mode ของ OpenAI | ส่งพารามิเตอร์ `'response_format' => ['type' => 'json_object']` ไปยัง OpenAI Official Endpoint เสมอ เพื่อบังคับให้ตอบเฉพาะ Pure JSON Object |
| **5** | **Vision Tile Resolution (`detail: 'high'`)**<br>การสแกนใบเสร็จไทยบางใบ อ่านเลขประจำตัวผู้เสียภาษี 13 หลัก หรือยอดเงินผิดพลาด | ตัวเลขบาทหรือตัวเลขขนาดเล็กบนสลิป/ใบเสร็จเกิด Hallucination | หากไม่ระบุ detail พารามิเตอร์ OpenAI จะใช้โหมด `auto`/`low` ซึ่งย่อภาพเหลือ 512x512 ทำให้ตัวอักษรไทยขนาดเล็กแตก | ระบุ `'detail' => 'high'` ใน `image_url` payload บังคับให้ GPT-4o ตัดภาพเป็น High-Resolution 512px Grid เพื่อความแม่นยำสูงสุด |
| **6** | **Scanned PDF File Ingestion**<br>ผู้ใช้อัปโหลดไฟล์ใบเสร็จเป็น PDF สแกน (`application/pdf`) | เกิดข้อผิดพลาด `HTTP 400: Invalid image format. OpenAI Vision only supports PNG, JPEG, WEBP` | OpenAI Chat Completions API ไม่รองรับการส่ง Content Type `application/pdf` ใน `image_url` | เพิ่มฟังก์ชัน `extractJpegFromPdf()` สกัดสตรีม JPEG ที่ฝังอยู่ใน Scanned PDF (เช่น PDF จาก CamScanner/Notes) ออกมาเป็นรูปภาพส่งให้ GPT อัตโนมัติ หากเป็น PDF ข้อความล้วนจะแจ้งเตือนผู้ใช้อย่างชัดเจน |
| **7** | **User-Facing Error Transparency**<br>เมื่อเกิดข้อผิดพลาดจากภายนอก ผู้ใช้เห็นเพียงหน้าจอว่างหรือข้อความกำกวม | ผู้ใช้ไม่ทราบว่าเกิดจาก API Key ไม่ถูกต้อง, โควต้าหมด (`insufficient_quota`), หรือโมเดลผิด | ระบบเดิมตัดข้อความ body แบบหยาบ 150-200 ตัวอักษร ทำให้โครงสร้าง error json ของ OpenAI หลุดหาย | สกัด `$response->json()['error']['message']` ออกมาแสดงผลผ่านแบนเนอร์สีแดงใน `OcrReviewer.tsx` และปุ่ม "Test Connection" อย่างโปร่งใส |

### 8.2 ข้อตกลงเชิงระบบสำหรับการต่อยอดโมดูล AI ในอนาคต (AI System Directives)
1. **Never Send Raw PDF Directly to OpenAI Chat Completions**: หากจำเป็นต้องประมวลผล PDF ให้ทำการแปลงหน้าเป็นภาพ (Render to Image) หรือสกัด Embedded Image Stream ก่อนส่งเข้า Vision API เสมอ
2. **Model Parameter Compatibility**: ตระกูลโมเดลให้ตรวจสอบ Prefix เสมอ:
   - ตระกูล `gpt-4o`, `gpt-4o-mini`: ใช้ `max_tokens` + `temperature` + `response_format: { type: "json_object" }`
   - ตระกูล `o1`, `o3`: ใช้ `max_completion_tokens` เท่านั้น และห้ามส่ง `temperature`
   - ตระกูล Local AI (Ollama `qwen2.5-vl`): ยึดการทำงานผ่าน OpenAI-compatible format โดยผ่อนปรน `response_format` ในกรณีที่โมเดลท้องถิ่นยังไม่รองรับ
3. **No Auto-Posting Rule**: ผลลัพธ์ที่ได้จาก AI ทุกประเภท (ไม่ว่าจะฉลาดเพียงใด) ถือเป็น **Assisted Draft** เสมอ ต้องผ่านการยืนยันจากมนุษย์ (Human Approval Gate) ก่อนบันทึกบัญชีแยกประเภททั่วไป (General Ledger)

### 8.3 ข้อสงสัยและประเด็นสำคัญที่ฝากให้ GPT รับรู้และพิจารณา (Open Inquiries & Architectural Handoff to GPT)

หมวดนี้บันทึกข้อสงสัย ประเด็นทางเทคนิค และจุดเสี่ยงที่ Gemini ตรวจพบ เพื่อส่งต่อให้ GPT (หรือเอเจนต์ตัวถัดไป) รับรู้ เข้าใจบริบท และร่วมพิจารณาตัดสินใจในการพัฒนาต่อยอด:

1. **ข้อสงสัยเกี่ยวกับไฟล์ PDF ต้นทาง (PDF Conversion vs Image-Only Strategy)**
   - *ประเด็น*: ปัจจุบันระบบใช้ `extractJpegFromPdf()` ดักจับ JPEG stream ที่ฝังใน Scanned PDF (ซึ่งแก้ปัญหาไฟล์จากแอปสแกนบนมือถือได้ ~80%) แต่หากเป็น **PDF ที่สร้างจากคอมพิวเตอร์ (Vector/Digital PDF)** หรือสลิปธนาคารที่ไม่มีรูป JPEG ฝังอยู่ OpenAI Vision จะไม่สามารถอ่านได้
   - *คำถามให้ GPT รับรู้และพิจารณา*:
     - ใน Phase ถัดไป ควรติดตั้ง Server-side PDF Renderer (เช่น `pdftoppm` / `Imagick` / Spatie Pdf-to-Image) เพื่อแปลงหน้าแรกของ PDF เป็น PNG เสมอก่อนส่ง Vision API หรือไม่?
     - หรือควรใช้แนวทาง Text Extraction (เช่น `smalot/pdfparser`) สกัดข้อความดิบแล้วส่งเป็น Text Prompt ธรรมดาให้ GPT วิเคราะห์สำหรับ Digital PDF แทนการใช้ Vision?

2. **ข้อสงสัยเกี่ยวกับการจัดการต้นทุน Token และ Rate Limits (Cost & Rate Limiting)**
   - *ประเด็น*: การส่งภาพขนาดเต็มพร้อมพารามิเตอร์ `'detail' => 'high'` ทำให้ OpenAI แบ่งภาพเป็นตารางขนาด 512px หลายช่อง คิดคำนวณเป็น ~800-1,500 tokens ต่อภาพ
   - *คำถามให้ GPT รับรู้และพิจารณา*:
     - ในองค์กรขนาดใหญ่ที่มีการอัปโหลดใบเสร็จจำนวนมากต่อวัน ควรมีการทำ Client-side หรือ Server-side Image Downscaling (เช่น ย่อด้านกว้างไม่เกิน 1,600px ก่อน encode base64) เพื่อคุมปริมาณ Token และลด Latency หรือไม่?
     - ควรมีคิว Job Asynchronous (`ProcessOcrExtractionJob`) แทนการประมวลผล Synchronous ใน Web Request หรือไม่ เพื่อป้องกัน Request Timeout เมื่อ OpenAI ตอบสนองช้าในชั่วโมงเร่งด่วน?

3. **ข้อสงสัยเกี่ยวกับ Multi-Model Fallback & Local Model Parity**
   - *ประเด็น*: ความแม่นยำระหว่าง Cloud GPT-4o กับ Local Ollama (`qwen2.5-vl:7b`) มีความแตกต่างกันในกรณีใบเสร็จภาษาไทยที่พิมพ์ด้วย Dot-matrix หรือฟอนต์เฉพาะทาง
   - *คำถามให้ GPT รับรู้และพิจารณา*:
     - ควรออกแบบระบบ **Confidence-Triggered Smart Fallback** หรือไม่? เช่น หากองค์กรตั้งค่าเริ่มต้นเป็น Local AI (Ollama) เพื่อความเป็นส่วนตัว แต่ผลการสแกนได้ Confidence Score ต่ำกว่า 0.60 หรือพบ `math_mismatch = true` ให้มีปุ่มกดสลับเรียก Cloud GPT เพื่อ Re-scan เฉพาะเอกสารนั้นได้?

4. **ข้อสงสัยเกี่ยวกับการแยกประเภทเอกสารจัดซื้อ: Expense vs Vendor Bill (AP Invoice)**
   - *ประเด็น*: ปัจจุบันหน้า Split-Screen Reviewer รองรับการกดแปลงเป็น **Draft Expense** (ค่าใช้จ่ายย่อย/เงินสดย่อย) เป็นหลัก
   - *คำถามให้ GPT รับรู้และพิจารณา*:
     - หากเป็นใบกำกับภาษีซื้อที่เป็นหนี้การค้า (Accounts Payable) ควรเพิ่มปุ่ม **"Convert to Vendor Bill"** ในหน้า Reviewer โดยให้เลือกจับคู่กับ Purchase Order (PO) ที่ค้างรับ (Matching with Open PO) ด้วยหรือไม่?
     - ระบบจับคู่ Supplier ปัจจุบันใช้ Tax ID 13 หลัก หากตรวจพบ Supplier และพบว่ามี PO ค้างรับ ควรแนะนำ PO อัตโนมัติในฟอร์มด้านขวาหรือไม่?

5. **ข้อสงสัยเกี่ยวกับความปลอดภัยและการรั่วไหลของข้อมูล (Data Privacy & Compliance)**
   - *ประเด็น*: การส่ง Base64 Image ไปยัง `https://api.openai.com`
   - *คำถามให้ GPT รับรู้และพิจารณา*:
     - องค์กรควรมีสวิตช์ Global Policy `allow_cloud_ai_processing` ที่สามารถปิดกั้น Cloud AI ได้เบ็ดเสร็จ เพื่อบังคับให้ผู้ใช้ทุกคนต้องใช้เฉพาะ On-Premise Local AI เท่านั้นสำหรับองค์กรที่มีนโยบายข้อมูลชั้นความลับสูง (Confidentiality / PDPA)?

---

## 9. รายงานการตรวจสอบระบบ Setup Wizard (Setup Module Diagnostic & Action Items for GPT)

บันทึกข้อตรวจพบเชิงสถาปัตยกรรม (Code Audit & Gap Analysis) จากการตรวจสอบโค้ดในโฟลเดอร์ `setup/`, `backend/setup/`, `backend/public/setup/` เปรียบเทียบกับคู่มือข้อกำหนด [`docs/SETUP_WIZARD.md`](file:///c:/LocalDevine/www/ERP/docs/SETUP_WIZARD.md):

### 9.1 ปัญหาที่ได้รับการแก้ไขและตรวจรับรองแล้ว (Resolved & Verified Issues)

รายการปัญหาที่ได้รับการแก้ไขในซอร์สโค้ดและผ่านการทดสอบ Automated Test เรียบร้อยแล้ว:

| ลำดับ | รายการที่แก้ไข (Resolved Item) | รายละเอียดการแก้ไข (Implementation Details) | ไฟล์ที่แก้ไข | สถานะการทดสอบ (Verification) |
| :---: | :--- | :--- | :--- | :---: |
| **1** | **`isEnabled()` รองรับ `SETUP_TOKEN` ใน Environment** | ปรับปรุง `Installer::isEnabled()` ให้ตรวจสอบจาก `$this->activationToken() !== null` ทำให้รองรับทั้ง Environment Variable `SETUP_TOKEN` ($\ge$ 32 ตัวอักษร) ตามคำแนะนำหลักในคู่มือ และไฟล์ `enable.php` | [`backend/setup/Installer.php`](file:///c:/LocalDevine/www/ERP/backend/setup/Installer.php#L30-L33) | **Passed**<br>`InstallerTest::test_environment_setup_token_enables_the_installer` |
| **2** | **Preflight ตรวจสอบ `.env.example` ด้วย `is_readable`** | แก้ไขจากการเช็ค `is_writable` เป็น `is_readable($this->path('.env.example'))` เพื่อรองรับเซิร์ฟเวอร์ Production ที่ล็อกสิทธิ์ไฟล์แม่แบบเป็น Read-Only (chmod 644/444) ได้อย่างถูกต้อง | [`backend/setup/Installer.php`](file:///c:/LocalDevine/www/ERP/backend/setup/Installer.php#L54) | **Passed**<br>Preflight check passes with readable template |
| **3** | **Preflight แจ้งเตือนการมีอยู่ของ `.env` เดิมตั้งแต่ Step 1** | เพิ่มการตรวจสอบ `'Clean release directory'` ใน `preflight()` (`! is_file($this->path('.env'))`) เพื่อแจ้งเตือนผู้ใช้ตั้งแต่หน้าแรก ไม่ต้องรอให้กรอกข้อมูลจนถึงขั้นตอนติดตั้งจริง | [`backend/setup/Installer.php`](file:///c:/LocalDevine/www/ERP/backend/setup/Installer.php#L55) | **Passed**<br>`InstallerTest::test_preflight_reports_an_existing_environment_file` |
| **4** | **ปรับปรุง UI Copy และ Hint ให้ครอบคลุม `SETUP_TOKEN`** | อัปเดตข้อความแจ้งเตือน HTTP 403 และข้อความ Hint ใต้ช่องกรอก Setup Token ให้ระบุชัดเจนว่าสามารถใช้ได้ทั้งตัวแปร `SETUP_TOKEN` ของ Web Server หรือไฟล์ `enable.php` | [`backend/public/setup/index.php`](file:///c:/LocalDevine/www/ERP/backend/public/setup/index.php#L49) | **Passed**<br>UI copy matches documentation |
| **5** | **สร้าง Automated Unit Test Suite สำหรับ Installer** | สร้างชุดทดสอบ `Tests\Unit\Setup\InstallerTest` ครอบคลุมการเปิดใช้งานผ่าน token, การตรวจสอบ token ที่ถูกต้อง/ไม่ถูกต้อง, และการรายงาน preflight เมื่อพบ `.env` เดิม | [`backend/tests/Unit/Setup/InstallerTest.php`](file:///c:/LocalDevine/www/ERP/backend/tests/Unit/Setup/InstallerTest.php) | **Passed (100% Green)**<br>2 passed / 6 assertions (0.70s) |

---

### 9.2 รายละเอียดปัญหาและข้อสังเกตที่ยังคงเหลืออยู่ (Remaining Issues & Recommendations)

รายการปัญหาและข้อควรปรับปรุงที่ยังคงค้างอยู่ เพื่อให้ GPT รับทราบและดำเนินการต่อยอด:

| ลำดับ | ระดับความสำคัญ | หัวข้อปัญหา (Issue Title) | อาการและผลกระทบ (Symptom & Impact) | สาเหตุที่แท้จริง (Root Cause) | ตำแหน่งไฟล์ & บรรทัด | แนวทางแก้ไขที่แนะนำ (Recommended Fix) |
| :---: | :---: | :--- | :--- | :--- | :--- | :--- |
| **1** | 🟡 **Medium / Session Edge Case** | **Session Cookie Path อาจกลายเป็นสตริงว่าง (`""`) บน Root Document** | บนบาง Web Server ที่ Document Root อยู่ที่รูท หรือ `$_SERVER['SCRIPT_NAME']` คืนค่า `/index.php` การทำ `rtrim('/', '/')` จะได้สตริงว่าง `""` ส่งผลให้เบราว์เซอร์ตีความขอบเขต Path คลาดเคลื่อนและอาจทำ Session หลุดใน Request ถัดไป | การคำนวณ Path ขาดค่า Fallback กรณีสตริงว่าง | [`backend/public/setup/index.php:L14`](file:///c:/LocalDevine/www/ERP/backend/public/setup/index.php#L14) | เติม Fallback สตริงราก:<br>`'path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/setup/index.php'), '/') ?: '/'` |
| **2** | 🟢 **Low / Routing Edge Case** | **Relative URL Redirection (`../login`) เมื่อเข้าใช้งานผ่าน URL ที่ไม่มี Trailing Slash** | หากผู้ใช้เข้า URL แบบ `http://host/ERP/setup` (ไม่มี `/` ปิดท้าย) บน Apache บาง Config การ Redirect ด้วย `../login` อาจถูก Resolve ย้อนกลับไปเป็น `http://host/login` แทนที่จะเป็น `http://host/ERP/login` | การใช้ Relative Path `../login` อิงกับ Base URI ของเบราว์เซอร์ | [`backend/public/setup/index.php:L43`](file:///c:/LocalDevine/www/ERP/backend/public/setup/index.php#L43)<br>[`backend/public/setup/index.php:L138`](file:///c:/LocalDevine/www/ERP/backend/public/setup/index.php#L138) | บังคับ Redirect ให้มี Trailing Slash เสมอ หรือคำนวณ Base URL สัมพัทธ์จาก Script Name |
| **3** | 🟡 **Medium / Test Coverage** | **ยังขาด Integration Test สำหรับกระบวนการติดตั้งจริง (Install Flow & Migration)** | ปัจจุบัน `InstallerTest` ครอบคลุมเฉพาะ Preflight และ Token Verification แต่ยังไม่มี Test จำลองขั้นตอน `testDatabase()`, `assertDatabaseEmpty()`, และ `install()` | ยังไม่มี Mock Database / SQLite testing fixture สำหรับตัว Installer | [`backend/tests/Unit/Setup/`](file:///c:/LocalDevine/www/ERP/backend/tests/Unit/Setup/) | เพิ่ม Feature/Integration Test จำลองกระบวนการรัน Migration และ Provision Owner ลงในฐานข้อมูลชั่วคราว |

---

### 9.3 สรุปจุดแข็งและความปลอดภัยที่ตรวจรับรองแล้ว (Verified Good Practices)
1. **Directory Traversal & Direct Web Access Protection**: ป้องกันสมบูรณ์แบบทั้ง `setup/.htaccess` (`Options -Indexes`), `backend/setup/.htaccess` (`Require all denied`), และ Rewrite Rule ใน Root `.htaccess` (`RewriteRule ^backend/setup(?:/|$) - [F,L]`)
2. **Atomic Environment Generation**: สร้าง `.env` ผ่าน Temporary File + Atomic Rename, ตั้งสิทธิ์ `chmod 0600`, สุ่ม `APP_KEY` 32 bytes แบบปลอดภัย, และลบ Config cache ทันที
3. **Database Integrity Guard**: ปฏิเสธการเขียนทับฐานข้อมูลที่มีตาราง `migrations` อยู่แล้วอย่างเด็ดขาด และไม่มีการ Seed ข้อมูล Demo
4. **Concurrency & Complete Lifecycle Lock**: มี `install.lock` ป้องกัน Race Condition และเมื่อติดตั้งสำเร็จจะบันทึก `installed.json` พร้อมลบไฟล์ `enable.php` ทิ้งทันที ส่งผลให้ `/setup` ตอบ HTTP 404 เสมอ

---

### 9.4 รายการ Action Items สำหรับ GPT (Remaining Action Items for GPT)
1. **เติม Fallback ใน Cookie Path**: ปรับบรรทัด 14 ใน [`backend/public/setup/index.php`](file:///c:/LocalDevine/www/ERP/backend/public/setup/index.php#L14) เป็น `'path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/setup/index.php'), '/') ?: '/'`
2. **เพิ่ม Integration Test สำหรับ Installer**: พัฒนา Test เพิ่มเติมใน `backend/tests/Unit/Setup/InstallerTest.php` ให้ครอบคลุม `testDatabase()` และ `assertDatabaseEmpty()`

---

## 10. สถาปัตยกรรมระบบการผลิตและสูตรการผลิต Phase 27 (Manufacturing & BOM Architecture Reference)

เสร็จสมบูรณ์และผ่านการตรวจรับ 100% (Complete & Closed — 2026-09-25).

### 10.1 Schema & Data Models
1. **`bill_of_materials`**: สูตรการผลิตแม่แบบ (Header)
   - ฟิลด์: `org_id`, `bom_no`, `name`, `product_id` (Finished Product), `version`, `quantity` (Batch Size), `unit`, `material_cost`, `labor_cost`, `overhead_cost`, `total_cost`, `unit_cost`, `routing_notes`, `is_active`, `created_by`
2. **`bom_items`**: รายการวัตถุดิบและชิ้นส่วนประกอบย่อย
   - ฟิลด์: `bill_of_materials_id`, `item_type` (`raw_material` / `sub_assembly`), `product_id`, `sub_bom_id` (สำหรับ Nested Sub-Assembly), `quantity`, `unit`, `unit_cost`, `scrap_percentage`, `total_cost`, `sort_order`, `notes`
3. **`work_orders`**: ใบสั่งผลิตและการควบคุมการผลิต
   - ฟิลด์: `wo_no`, `bill_of_materials_id`, `product_id`, `warehouse_id` (Finished Goods Destination), `source_warehouse_id` (Raw Material Origin), `status` (`draft` $\rightarrow$ `planned` $\rightarrow$ `in_progress` $\rightarrow$ `quality_check` $\rightarrow$ `completed` / `cancelled`), `planned_quantity`, `actual_quantity`, `scrap_quantity`, `unit`, `material_cost`, `labor_cost`, `overhead_cost`, `total_cost`, `unit_cost`, `start_date`, `due_date`, `completed_date`, `qc_notes`, `qc_passed`, `created_by`
4. **`work_order_items`**: รายการวัตถุดิบที่ถูกจัดสรรและเบิกใช้จริงในใบสั่งผลิต
   - ฟิลด์: `work_order_id`, `product_id`, `bom_item_id`, `required_quantity`, `issued_quantity`, `unit`, `unit_cost`, `total_cost`
5. **`production_costs`**: บันทึกต้นทุนค่าแรงและค่าโสหุ้ยที่เกิดขึ้นจริงระหว่างการผลิต
   - ฟิลด์: `work_order_id`, `cost_type` (`labor` / `overhead`), `description`, `amount`, `cost_date`, `created_by`
6. **`stock_movements` Extension**: เพิ่ม nullable `work_order_id` และประเภทการเคลื่อนไหวใหม่:
   - `work_order_issue`: ตัดจ่ายวัตถุดิบออกจากคลัง
   - `work_order_return`: คืนวัตถุดิบกลับเข้าคลังเมื่อยกเลิกใบสั่งผลิต
   - `work_order_receipt`: รับสินค้าสำเร็จรูปเข้าคลัง

### 10.2 Double-Entry General Ledger Integrity (GL Auto-Posting)
- **Material Issue (เมื่อ Release Work Order):**
  - **Dr. 1160 (Work-in-Progress - งานระหว่างทำ)** = ยอดรวมต้นทุนวัตถุดิบที่เบิก
  - **Cr. 1140 (Raw Materials/Inventory - วัตถุดิบและสินค้าคงเหลือ)** = ยอดรวมต้นทุนวัตถุดิบที่เบิก
- **Production Completion (เมื่อตรวจรับเข้าคลัง FG):**
  - **Dr. 1140 (Finished Goods/Inventory - สินค้าสำเร็จรูป)** = ยอดรวมต้นทุนการผลิตจริง (`total_cost`)
  - **Cr. 1160 (Work-in-Progress - ล้างงานระหว่างทำ)** = ต้นทุนวัตถุดิบ (`material_cost`)
  - **Cr. 5500 (Direct Labor Absorbed - ค่าแรงทางตรงปันส่วนเข้าผลิต)** = ต้นทุนค่าแรง (`labor_cost`)
  - **Cr. 5200 (Factory Overhead Absorbed - โสหุ้ยการผลิตปันส่วนเข้าผลิต)** = ต้นทุนโสหุ้ย (`overhead_cost`)
- **Cancellation Reversal:** หากมีการ Release หรือเบิกจ่ายวัตถุดิบไปแล้วและถูก Cancelled ระบบจะสร้าง Journal Reversal อัตโนมัติ พร้อมส่งคืน Stock วัตถุดิบเข้าคลังเดิม

### 10.3 Business Logic & Protection Mechanisms
1. **Multi-level Recursive BOM Explosion & MRP Flattening:**
   - ฟังก์ชัน `ProductionCostingService::explode()` สามารถคลี่และคำนวณสูตรการผลิตที่ซ้อนกันหลายระดับ (Multi-tier Assemblies)
   - ฟังก์ชัน `flattenExplodedMaterials()` สรุปความต้องการวัตถุดิบสุทธิ (Net Material Demand) รวมทุกระดับพร้อม scrap rate
2. **Circular Dependency Guard:**
   - ตรวจจับ Cycle ในสูตรการผลิต (เช่น BOM A $\rightarrow$ BOM B $\rightarrow$ BOM A) และโยน `DomainException("Circular BOM dependency detected...")` เพื่อป้องกัน Infinite Recursion 100%
3. **Multi-Tenant & Role Permissions Isolation:**
   - สิทธิ์เฉพาะ: `manufacturing.bom.view`, `manufacturing.bom.manage`, `manufacturing.work_orders.view`, `manufacturing.work_orders.manage`, `manufacturing.work_orders.execute`
   - Default roles: `owner`, `admin`, `project_manager`, `finance`
   - Isolation: ทุก query ถูกกรองด้วย `org_id` อย่างเคร่งครัดและป้องกันข้ามองค์กร

### 10.4 ผลการทดสอบและการตรวจรับ (Phase 27 Verification)
- **Feature Test Suite:** `Tests\Feature\Phase27ManufacturingTest` — **8 passed / 69 assertions (100% green)**
  - `can_create_bill_of_materials_and_calculate_costs`
  - `multi_level_bom_explosion_and_flattening`
  - `circular_bom_dependency_throws_domain_exception`
  - `work_order_creation_from_bom_with_scaling`
  - `work_order_lifecycle_flow_stock_movements_and_gl_postings`
  - `work_order_cancellation_reverses_stock_and_gl_if_issued`
  - `production_cost_recording_adjusts_total_and_unit_cost`
  - `manufacturing_permissions_and_multi_tenant_isolation`
- **Frontend Build & Lint:** `npm run build` ผ่าน 100% (Vite production bundle generated), Prettier clean, Pint PSR-12 clean.

---

## 11. External Read-only Summary API (Round 1) — สถาปัตยกรรม, ปัญหาที่พบ และข้อเสนอแนะสำหรับ GPT

**สถานะ:** ผ่านการตรวจรับทางเทคนิคโดย Gemini ครบถ้วน 100% (Complete & Verified — 2026-10-18)
**เอกสารอ้างอิง:** [EXTERNAL_SUMMARY_API.md](docs/EXTERNAL_SUMMARY_API.md), [EXTERNAL_SUMMARY_API_CHECKLIST.md](docs/EXTERNAL_SUMMARY_API_CHECKLIST.md), [OpenAPI Contract v1](docs/openapi/external-summary-v1.json), และ [gpt.md](gpt.md)

### 11.1 สรุปสถาปัตยกรรมและผลการตรวจรับรอบแรก (Architecture & Verification Summary)
1. **API Namespace & Scope Separation**:
   - Base Route: `/api/v1/summaries`
   - Sales: `GET /api/v1/summaries/sales` (ต้องใช้ scope: `summary:sales:read`)
   - Finance: `GET /api/v1/summaries/finance` (ต้องใช้ scope: `summary:finance:read`)
   - Authentication Middleware: `AuthenticateIntegrationKey` รับเฉพาะ Bearer Token รูปแบบ `erp_<public_id>.<secret>`
2. **Security & Guardrails Compliance**:
   - **Zero Plaintext Secret at Rest**: จัดเก็บเฉพาะ SHA-256 hash ในตาราง `integration_api_keys`
   - **One-Time Secret Reveal**: แสดง Secret เต็มใน JSON response เฉพาะตอนสร้าง key และเก็บใน React state ชั่วคราว (ไม่ผ่าน Session, ไม่เก็บใน Inertia shared props หรือ LocalStorage)
   - **Tenant Context Server-Side Derived**: ดึง `org_id` จาก key เท่านั้น ปฏิเสธการ inject `org_id` หรือ `branch_id` จากภายนอกด้วย HTTP 422
   - **Zero Floating-Point Arithmetic**: คำนวณยอดเงินผ่าน `BigDecimal` สเกล 2 ตำแหน่ง (`RoundingMode::HalfUp`) ในรูปแบบ String ทั้งหมด ป้องกัน Floating-point rounding error ใน PHP
   - **Zero Organization-wide Cash Balance Leak**: ไม่รวมยอด Inflow ลบ Outflow เป็นยอดเงินสดคงเหลือรวมทั้งองค์กร
   - **HTTPS & CORS Protection**: Production บังคับ HTTPS (HTTP 426) และ API ปิด CORS wildcard (ออกแบบสำหรับ Server-to-Server ไม่ให้ Frontend Browser ถือ Secret)
3. **ผลการทดสอบเชิงประจักษ์ (100% Passed)**:
   - Scoped & Regression Feature Tests: **64 passed / 515 assertions** (`ExternalSummaryApiTest`, `DashboardMetricsEdgeCasesTest`, `Auth`, `Phase18TwoFactorTest`, `Phase23GatewayTest`)
   - Manufacturing Suite: **8 passed / 69 assertions** (`Phase27ManufacturingTest`)
   - Frontend Unit Tests: **38 passed / 0 failed** (`tests/Frontend/*.test.mjs`)
   - Static Analysis & Build: TypeScript 0 errors, ESLint 0 errors, Laravel Pint 0 issues, Production Vite build ผ่านสมบูรณ์ (25.11s)

---

### 11.2 ปัญหา ข้อจำกัด และข้อสังเกตที่พบ (Known Issues & Limitations)

1. **Browser UAT ยังไม่ได้รับการยืนยันด้วย Browser Automation**:
   - หน้าจอ Settings → API Access (`ApiAccess.tsx` ที่ `/settings/api-access`) ผ่านการตรวจสอบโค้ด, TypeScript, ESLint และ Vite bundle สำเร็จแล้ว แต่ยังขาดการรัน Interactive Browser UAT (Create/Reveal, Copy, In-place Edit, Revoke Dialog, Discard Guard, Keyboard Navigation) เนื่องจากสภาพแวดล้อมเครื่องทดสอบติดปัญหา Timeout เมื่อเปิด Headless Browser กับ Localhost Fixture
2. **การทดสอบ Performance และ EXPLAIN บนฐานข้อมูลขนาดใหญ่ (Production DB)**:
   - ชุดทดสอบความเร็วปัจจุบันรันบน SQLite in-memory (1,000 deals, aggregate queries <= 10, ใช้ index `deals_org_id_created_at_index`) ยังไม่ครอบคลุมการทดสอบ Load บน MySQL/MariaDB ในระดับหลายหมื่นหรือหลายแสน Transactions
3. **Regression เดิมใน `DashboardAccessTest` (นอกขอบเขต Summary API แต่ต้องระวัง)**:
   - การรัน `DashboardAccessTest::test_finance_widget_payload_queries_only_authorized_sources` จะล้มที่ assertion `cash_flow_trend.11.cash_in`
   - **สาเหตุรากเหง้า (Root Cause)**: ใน `Admin/DashboardController` ฟังก์ชัน `financeCashFlowTrend()` คำนวณช่วงเดือนโดยใช้ `endOfMonth()->subMonths(11)` ซึ่งเกิด Date Overflow ในเดือนที่มี 31 วัน ทำให้ array buckets ได้ไม่ครบ 12 เดือน ปัญหานี้เป็นบั๊กเดิมของ Dashboard ไม่ได้เกิดจากการเปลี่ยนแปลงของ External Summary API
4. **ยังไม่ Deploy และยังไม่ Migrate ฐานข้อมูล Production**:
   - Migrations `2026_10_18_000001_create_integration_api_keys.php` และ `2026_10_18_000002_add_summary_cohort_indexes.php` ยังไม่ได้รันในฐานข้อมูล Production จริง

---

### 11.3 ข้อเสนอแนะสำหรับ GPT ในการทำงานต่อ (Proposals & Next Steps for GPT)

1. **การขยาย Summary API สู่โดเมนถัดไป (Projects & Tasks / Procurement & Inventory)**:
   - **ลำดับที่แนะนำ**:
     - **ลำดับที่ 1: Projects & Tasks** (`GET /api/v1/summaries/projects`, Scope: `summary:projects:read`)
       - Metric: สรุปจำนวนโครงการแบ่งตามสถานะ, สรุปชั่วโมงทำงาน, งานที่ค้างส่ง (Overdue Tasks โดยตัดสถานะ `blocked` ออกตามกฎความปลอดภัยข้อ 86), ความคืบหน้าภาพรวม
     - **ลำดับที่ 2: Procurement & Inventory** (`GET /api/v1/summaries/procurement`, Scope: `summary:procurement:read`)
       - Metric: สรุปใบสั่งซื้อ (POs) แบ่งตามสถานะ (Draft, Approved, Partial, Received), สินค้าต่ำกว่าจุดสั่งซื้อ (Low Stock Items), มูลค่าสินค้าคงคลังรวมแยกตามคลัง
     - **ลำดับที่ 3: HR & People** (ต้องระมัดระวังเรื่อง PDPA Privacy ไม่ส่งเงินเดือนรายบุคคล ให้ส่งเฉพาะจำนวนพนักงาน, วันลาค้างอนุมัติ, สถิติขาดลามาสายรวม)
   - **แนวทางปฏิบัติ**:
     - เพิ่มรายการ Scope ใหม่ใน `IntegrationApiKey::SCOPES`
     - แยก service method ใน `ExternalSummaryService` และ map เข้ากับ Route ใน `routes/summaries.php`
     - ยึดข้อกำหนด Metric Dictionary และการส่งยอดเงินเป็น Decimal String ตามรูปแบบ v1 เสมอ

---

## 12. ผลการตรวจรับรอบสมบูรณ์ Phase External API (Round 2 & P0–P6 Comprehensive Verification — 2026-10-19)

**สถานะ:** ผ่านการรับรองทางเทคนิคครบถ้วน 100% (Complete & Verified by Gemini — 2026-10-19)  
**เอกสารประกอบการตรวจรับ:** [gpt.md](gpt.md), [EXTERNAL_SUMMARY_API_CHECKLIST.md](docs/EXTERNAL_SUMMARY_API_CHECKLIST.md), [EXTERNAL_READ_API.md](docs/EXTERNAL_READ_API.md), สัญญา P1–P6 ([P1](docs/EXTERNAL_API_P1_CONTRACT.md), [P2](docs/EXTERNAL_API_P2_CONTRACT.md), [P3_P4](docs/EXTERNAL_API_P3_P4_CONTRACT.md), [P5](docs/EXTERNAL_API_P5_CONTRACT.md), [P6_P7](docs/EXTERNAL_API_P6_P7_CONTRACT.md)), และ [OpenAPI Contract v1 (62 GET paths)](docs/openapi/external-read-v1.json)

---

### 12.1 สรุปภาพรวมและผลการตรวจรับเชิงสถาปัตยกรรม (Architecture & Scope Verification)

1. **ขอบเขตงานที่ได้รับการรับรอง (P0–P6 Read-only External API):**
   - **P0 (Summary API):** ครบทั้ง 3 โดเมนหลัก ได้แก่ Sales (`/api/v1/summaries/sales`), Finance (`/api/v1/summaries/finance`) และ Projects (`/api/v1/summaries/projects`) พร้อมการ Optimize ลด aggregate queries ของ Projects จาก 8 เหลือ 2 queries และแก้ไข Date Overflow ใน Dashboard Cash Flow Trend ครบถ้วน
   - **P1 (Catalog & Inventory):** Reference data registry, Products/Services list & detail พร้อม price/cost scopes แยกชัดเจน, Inventory summary, stock balances, lot number และ expiration tracking (รองรับ `on_hand` ตามบัญชีจริง ไม่สมมติ reservations/ATP)
   - **P2 (HR & People):** HR, Attendance, Leaves, และ Roster summaries รวมถึง Employee directory/profile โดยบังคับใช้ Privacy Guardrail อย่างเคร่งครัด (ไม่เปิดเผยข้อมูลส่วนบุคคล, เงินเดือน, บัญชีธนาคาร, พิกัด GPS หรือเหตุผลการลาส่วนตัว) รองรับพนักงานที่เป็น non-User และคืน HTTP 409 กรณี leave ขาด user bridge
   - **P3 & P4 (Business, Sales & Procurement):** Customer/Supplier directory, ใบเสนอราคา, ใบแจ้งหนี้, ใบส่งของ, สถานะการชำระเงิน, PO, GRN (จับคู่ PO/unit อย่างแม่นยำ), Procurement summary และ Projects/Tasks พร้อมระบบจำกัดสิทธิ์ระดับคู่ค้า (Party-scoped access)
   - **P5 (Approval & Webhooks Outbox):** อ่านสถานะการอนุมัติ ประวัติ และ Delegation (ไม่มี API สำหรับกดอนุมัติ/ปฏิเสธ) พร้อมระบบ Outbound Webhook สถาปัตยกรรม Transactional Outbox ส่งสัญญาณแบบ Minimal pointer เพื่อความปลอดภัยสูงสุด มีระบบ DNS Pinning, HTTPS-only, Replay challenge และ Exponential backoff retry
   - **P6 (Manufacturing & Documents):** สรุปสถานะการผลิต BOM, Work Orders, และ Document Metadata พร้อมระบบ Private Stream Download ที่ตรวจสอบ ACL รายเอกสาร ไม่อ้างอิง storage path หรือ public URL
   - **P7 Exclusion (ตัดออกตามคำสั่งผู้ใช้ 100%):** ไม่มี Write APIs ทุกชนิด และไม่มี Payroll/Salary/Bank APIs ภายนอกเรียกดูผ่าน HTTP GET/HEAD เท่านั้น

2. **การปฏิบัติตาม Security Guardrails และข้อตกลงความปลอดภัย:**
   - **Zero Plaintext Secret at Rest:** แฮชด้วย SHA-256 ในตาราง `integration_api_keys`
   - **One-Time Secret Reveal:** ไม่ส่ง secret ซ้ำ, ไม่เก็บใน session, Inertia shared props หรือ LocalStorage
   - **Tenant & Org Isolation:** สิทธิ์ดึงจาก key เท่านั้น ปฏิเสธการ inject `org_id` / `branch_id` จากภายนอก
   - **Zero Cash Balance Leak:** ไม่คำนวณหรือส่งยอดเงินสดคงเหลือรวมขององค์กรเด็ดขาด
   - **Safe Cursor Sync:** เข้ารหัส cursor 30 นาที, เรียงลำดับ stable (`updated_at`, `id`), fixed watermark UTC delta, ปฏิเสธ future checkpoint

---

### 12.2 หลักฐานเชิงประจักษ์จากการทดสอบจริง (Empirical Verification Results)

การตรวจรับดำเนินการรันชุดคำสั่งทดสอบจริงบนเครื่องในรอบนี้ โดยผลลัพธ์ทั้งหมดผ่าน 100%:

| รายการทดสอบ / เครื่องมือ | คำสั่งที่ใช้ | ผลการทดสอบเชิงประจักษ์ | สถานะ |
| :--- | :--- | :--- | :--- |
| **External API Feature Tests (P0–P6)** | `php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml tests/Feature/ExternalCatalogApiTest.php tests/Feature/ExternalHrApiTest.php tests/Feature/ExternalBusinessApiTest.php tests/Feature/ExternalRestrictedReadApiTest.php tests/Feature/ExternalWebhookTest.php tests/Feature/ExternalReadContractTest.php tests/Feature/ExternalSummaryApiTest.php` | **54 passed / 4,731 assertions** (35.38s) | **PASSED** |
| **Node.js Client & Webhook Verifier** | `node --test examples/external-api/summary-client.test.mjs examples/external-api/webhook-verifier.test.mjs` | **13 passed / 0 failed** (4.03s) | **PASSED** |
| **OpenAPI Specification Generator** | `node examples/external-api/build-openapi.mjs` | สร้างสมบูรณ์ **62 GET paths** (`docs/openapi/external-read-v1.json`) | **PASSED** |
| **Frontend TypeScript Type Check** | `node node_modules/typescript/bin/tsc --noEmit --project tsconfig.json` | **0 errors** (exit code 0) | **PASSED** |
| **Frontend ESLint (API Settings)** | `node node_modules/eslint/bin/eslint.js resources/js/Pages/Settings/ApiAccess.tsx resources/js/Pages/Settings/ApiWebhooks.tsx --max-warnings=0` | **0 errors / 0 warnings** (exit code 0) | **PASSED** |
| **System-wide Regression Tests** | `php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml tests/Feature/DashboardAccessTest.php tests/Feature/DashboardMetricsEdgeCasesTest.php tests/Feature/Phase4DeliveryDashboardTest.php tests/Feature/Auth tests/Feature/Phase18TwoFactorTest.php tests/Feature/Phase23GatewayTest.php tests/Feature/Phase19HrAttendanceTest.php tests/Feature/Phase233EmployeeMasterTest.php tests/Feature/Phase233RosterAndShiftTest.php tests/Feature/Phase20WorkflowTest.php tests/Feature/Phase17DocumentManagementTest.php tests/Feature/Phase181DocumentComplianceTest.php tests/Feature/Phase8InventoryTest.php tests/Feature/Phase15InventoryOperationsTest.php tests/Feature/Phase27ManufacturingTest.php` | **118 passed / 1,205 assertions** (2m 8s) | **PASSED** |

---

### 12.3 สถานะงานค้างและข้อจำกัดที่บันทึกตามข้อเท็จจริง (Pending & Deferred Items)

ตามข้อตกลงและคำสั่งของผู้ใช้ ณ วันที่ 2026-10-19 บันทึกสถานะงานส่วนที่เหลือเพื่อความโปร่งใสและถูกต้องตามข้อกำหนด:

1. **Browser UAT (Pending):**
   - หน้าจอ Settings → API Access และ Webhooks (`ApiAccess.tsx`, `ApiWebhooks.tsx`) ผ่านการตรวจโค้ด, TypeScript, ESLint และ build bundle แล้ว แต่ Interactive Headless Browser UAT ยังติดปัญหา timeout ของสภาพแวดล้อมระบบ จึงยังคงสถานะ Pending สำหรับการตรวจด้วย browser อัตโนมัติ
2. **Database Migrations บน Production (Deferred):**
   - Migrations นโยบาย API และ Webhook Outbox (`2026_10_19_000001_add_integration_access_policy.php`, `2026_10_19_000002_create_external_webhook_outbox.php`) รวมถึง Cohort Indexes ยังไม่ได้รันในฐานข้อมูล Production จริง (รันเฉพาะใน SQLite `:memory:` สำหรับชุดทดสอบ) ตามคำสั่ง "งดไปก่อน" ของผู้ใช้
3. **Target Deployment & Live App Integration (Deferred):**
   - ยังไม่มีการเปิดใช้งาน secret key จริงในสภาพแวดล้อมใช้งานจริง, ไม่เปิดสิทธิ์ ERP จริง, และยังคงตั้งค่า `EXTERNAL_API_WEBHOOKS_ENABLED=false` ไม่ยิง webhook ออกเครือข่ายภายนอก
4. **SLA & Concurrent HTTP Load Testing (Deferred):**
   - ผลการทดสอบ MariaDB isolated fixture (84,000 rows) เป็นหลักฐานประสิทธิภาพ baseline ระดับ Service เท่านั้น ไม่ถือเป็นการรับรอง SLA หรือ Throughput บน Target Production Environment

---

## 13. ข้อเสนอแนะและข้อกำหนดเพิ่มเติมสำหรับ Phase 28 (Standalone POS & ERP Connector Architecture Review — 2026-10-19)

**สถานะ:** ข้อเสนอแนะเชิงสถาปัตยกรรมสำหรับปรับปรุง [checklist.md](checklist.md) (Phase 28) อ้างอิงตาม [gpt.md](gpt.md) และ Guardrails หลักของระบบ  
**เอกสารอ้างอิง:** [checklist.md](checklist.md#L1642-L1722) (Phase 28), [gpt.md](gpt.md), [docs/PHASE_23_PAYMENT_GATEWAY.md](docs/PHASE_23_PAYMENT_GATEWAY.md), และ [docs/SECURITY_REQUIREMENTS.md](docs/SECURITY_REQUIREMENTS.md)

### 13.1 การประเมินและจุดยืนเชิงสถาปัตยกรรม (Architecture Evaluation)
Gemini **เห็นด้วยอย่างยิ่ง** กับโครงสร้างหลักในร่าง Checklist Phase 28 ได้แก่:
1. **การแยก Server และ Database เด็ดขาด (Physical & Logical Separation):** ป้องกันการบุกรุกจากหน้าร้าน (Store physical/network vector) ไม่ให้เข้าถึง ERP Core Database หรือ Credentials หลัก
2. **การบังคับ Connection แบบ Outbound-Only (Pull / Polling Model):** สอดคล้องกับนโยบายใน `gpt.md` ที่ปิดตาย Write API จากภายนอก โดยให้ ERP Connector เป็นฝ่ายติดต่อไปยัง POS เอง (ERP $\rightarrow$ POS Sync API) และคงสถานะ Default Deny Inbound Firewall
3. **การมองข้อมูลจาก POS เป็น Untrusted Input:** บังคับตรวจสอบความถูกต้อง, ราคา, สิทธิ์ส่วนลด, และบันทึกผ่าน ERP Domain Services เดิม
4. **Guardrail การแยกยอดเงินในกะ POS (Cash Balance Boundary):** สอดคล้องกับกฎข้อ 60 & 82 ใน `gpt.md` ที่ห้ามนำยอดเงินสดในกะหน้าร้านไปอนุมานหรือเผยแพร่เป็นยอดเงินสดรวมขององค์กร

---

### 13.2 รายการข้อเสนอแนะ 6 ประเด็นหลักสำหรับปรับปรุง Checklist (Recommended Checklist Additions)

| ประเด็นที่เสนอแนะ | ข้อควรระวัง / สาเหตุรากเหง้า (Root Cause) | ข้อกำหนดและเกณฑ์ที่ต้องเพิ่มเติมใน Checklist |
| :--- | :--- | :--- |
| **1. นโยบายภาษีขาย & e-Tax (Tax Invoicing Policy)** | หน้าร้านออก "ใบกำกับภาษีอย่างย่อ (ABB)" ทันที แต่ลูกค้าอาจขอ "ใบกำกับภาษีเต็มรูป (Full Tax Invoice)" หรือ e-Tax ในภายหลัง อาจเกิด Output VAT ซ้ำซ้อน | • กำหนด Sequence เลขที่ใบกำกับภาษีอย่างย่อและเต็มรูปแยกกัน<br>• มีกลไก Void / Replace Event: ยกเลิกใบกำกับภาษีอย่างย่อเมื่อออกใบเต็มรูป เพื่อป้องกันการนับยอดภาษีขาย (Output VAT 2130) ซ้ำ 2 ครั้ง<br>• ปฏิบัติตาม Guardrail ข้อ 97 ใน `gpt.md` (e-Tax ผ่าน HSM/Certified boundary เท่านั้น ไม่เก็บ Private Key ใน DB หน้าร้าน) |
| **2. การจัดการ Stock Discrepancy หน้าร้าน (Physical vs ERP Stock Conflict)** | ลูกค้าชำระเงินและรับสินค้าหน้าร้านไปแล้ว (Physical Out) แต่เมื่อ Sync เข้า ERP ระบบอาจติด Guardrail "สต็อกในคลัง ERP ไม่พอ" | • **ห้าม Reject บิลขายที่เกิดจริงแล้วทิ้ง:** หากสต็อกคลังสาขาใน ERP ไม่พอ ให้รับบิลขายและยอดเงินเข้าบัญชีตามปกติ<br>• จัดสถานะเป็น `needs_review: inventory_discrepancy` และส่งเข้าคิว **Pending Stock Adjustment** พร้อมแจ้งเตือน Inventory Manager เพื่อตรวจนับและปรับปรุงยอดสต็อกจริง |
| **3. การ Reconcile การชำระเงินดิจิทัล & บัญชีพัก (Multi-tender & Clearing Accounts)** | บิลขายที่เป็น PromptPay QR หรือ EDC Card อาจถูกปลอมแปลงสถานะ `paid` หรือนำยอดไปปนกับเงินสดในลิ้นชัก | • สอดคล้องกับ Phase 23 ใน `gpt.md`: ต้องแนบ `provider_transaction_id` และ `settled_amount` ที่สอบยันกับ Gateway ได้<br>• แยกบัญชีพักเงินตาม Tender Type ชัดเจน:<br>  - เงินสดหน้าร้าน $\rightarrow$ บัญชีพัก `1111 Cash on Hand (POS Drawer)`<br>  - PromptPay QR / EDC $\rightarrow$ บัญชีพัก `1113 Payment Gateway / EDC Clearing`<br>• ห้ามลงบัญชีเงินฝากธนาคารหลัก (`1112 Cash at Bank`) โดยตรงจนกว่าจะผ่านการ Reconcile ยอดสลิป/Statement |
| **4. Double-Entry GL Posting Contract (รายบิล vs สรุปปิดกะ)** | ความสับสนว่าควรลงบัญชี GL รายบิลหรือรวบยอดกะ ทำให้เสี่ยงต่อการเกิด Double Journal Entry ซ้ำซ้อน | • **กำหนดมาตรฐานเด็ดขาด:** ลงบัญชี GL รายบิลแบบ Transactional (Dr. บัญชีพักเงิน/ลูกหนี้, Cr. รายได้ขาย, Cr. ภาษีขาย และ Dr. ต้นทุนขาย, Cr. สินค้าคงคลัง)<br>• **การปิดกะ (Shift Closing):** ทำหน้าที่เฉพาะบันทึกผลต่างเงินสดขาด/เกิน (Cash Over/Short) เท่านั้น:<br>  - เงินสดขาด: `Dr. 5400 Cash Shortage / Cr. 1111 Cash on Hand`<br>  - เงินสดเกิน: `Dr. 1111 Cash on Hand / Cr. 4400 Cash Overage`<br>• ป้องกันปัญหาการลงบัญชีซ้ำซ้อน 100% |
| **5. กลไก Tamper-Proof & Dead-Letter Queue (DLQ)** | ข้อมูลใน POS Database หน้าร้านอาจถูกแก้ไขก่อน Sync หรือมี Payload ที่ Parse ไม่ผ่าน | • ทุก Event ต้องมี SHA-256 Payload Hash สร้าง ณ จุดบันทึก เพื่อตรวจจับการแก้ไขฐานข้อมูลหน้าร้าน<br>• จัดทำ Dead-Letter Queue (DLQ) ฝั่ง ERP Connector พร้อมหน้าจอ UI ใน Admin Settings สำหรับตรวจสอบสาเหตุ และกด Re-process รายการที่แก้ไขข้อมูลหลักแล้ว |
| **6. Pre-implementation Gate: Business Fit Confirmation** | รูปแบบธุรกิจหน้าร้านส่งผลต่อโครงสร้าง Schema และ UI เป็นอย่างมาก | • ยึดตามข้อกำหนดใน `gpt.md` (line 100): ยืนยัน Business Model ของหน้าร้านให้ชัดเจนก่อนเขียนโค้ด (เช่น Retail ค้าปลีกทั่วไป, มินิมาร์ทตัดสต็อก Barcode รวดเร็ว, หรือ F&B ที่มีเปิดโต๊ะ/ส่งครัว) เพื่อไม่ให้เกิด Over-engineering หรือสร้าง Schema ที่ไม่ตรงกับงานจริง |

---

## 14. การประเมินและทบทวนความเหมาะสมของ Checklist Phase 28 (Standalone POS & ERP Connector Review — 2026-10-24)

**สถานะ:** บันทึกผลการตรวจสอบและประเมินความเหมาะสมของ [checklist.md](checklist.md#L1643-L1793) (Phase 28) ฉบับปรับปรุงตามขอบเขตความต้องการ 2026-10-24  
**เอกสารอ้างอิง:** [checklist.md](checklist.md#L1643-L1793) (Phase 28), [gpt.md](gpt.md#L252-L280), [gemini.md](gemini.md#L498-L522) (Section 13)

### 14.1 สรุปผลการประเมิน (Overall Evaluation Verdict)
Gemini ได้ทำการตรวจสอบรายการ Checklist Phase 28 ใน [checklist.md](checklist.md) ครอบคลุมหัวข้อ 28.1 ถึง 28.8 อย่างละเอียด และมีความเห็นว่า:
**"Checklist Phase 28 มีความเหมาะสมอย่างยิ่ง รัดกุม ชัดเจน และพร้อมใช้เป็นกรอบการออกแบบสถาปัตยกรรม (Architecture & Contract Design Gate) ได้อย่างสมบูรณ์"**

---

### 14.2 จุดเด่นและข้อดีของ Checklist ฉบับปรับปรุง (Key Strengths & Architectural Merits)

1. **การควบคุมขอบเขตอย่างเฉียบขาด ป้องกัน Scope Creep (Disciplined Boundary & De-scoping):**
   - **การล็อก 3 กิจการหลัก (Retail, Restaurant, Pharmacy):** กำหนด Core POS ร่วมกันและแยก Capability/Workflow ตามกิจการชัดเจน
   - **การตัดความซับซ้อนเกินจำเป็นในรุ่นแรก:** ตัด Patient History / เวชระเบียน / ระบบวินิจฉัยโรคของร้านขายยาออกไปอย่างเด็ดขาด, ตัด EDC/Card, ระบบสมาชิก/Loyalty, Delivery ออกไปเป็นงานอนาคตใน 28.8
   - **ขอบเขตการชำระเงินที่ชัดเจน:** ยืนยันเฉพาะ เงินสด และ QR Code (Dynamic PromptPay) ซึ่งสอดคล้องกับบริบทหน้าร้านส่วนใหญ่
   - **การจำกัดขอบเขต Online Menu ผ่าน QR:** วางบทบาทเป็นเพียง Catalog / เมนูให้ลูกค้าเปิดดูบนมือถือ หากจะขยายไปสู่การสั่งออเดอร์/ชำระเงินออนไลน์ ได้มีข้อกำหนด Pre-implementation Gate และ Server-side validation กั้นไว้อย่างรัดกุม

2. **การคง Guardrails ด้านสถาปัตยกรรมและความปลอดภัยครบถ้วน (Architectural & Security Guardrails):**
   - **Physical & Logical Separation:** แยก POS Server/Database ออกจาก ERP Server/Database เด็ดขาด ไม่มี Shared DB credentials, `APP_KEY`, หรือ Session
   - **Outbound-Only Polling (Pull Architecture):** ERP Connector ทำหน้าที่ Outbound ออกไปดึงรายการขายและส่งผล Ack กลับ ไม่เปิด Inbound Write API เข้าสู่ ERP Core
   - **Zero Trust on POS Payload:** มองข้อมูลจาก POS เป็น Untrusted Input ต้องผ่านการตรวจสอบสิทธิ์, ราคา, ส่วนลด, สาขา, และหลักฐานการชำระเงินจริงจาก Gateway ฝั่ง ERP ก่อนบันทึกผ่าน Domain Services เดิม
   - **Network Security & SSRF Protection:** บังคับ mTLS / Service Auth, Destination Allowlist, และ Default Deny Firewall

3. **ความถูกต้องทางบัญชีและสต็อกตามมาตรฐาน ERP (Accounting & Stock Integrity):**
   - **Double-Entry GL Contract:** บังคับบันทึก GL รายบิลแบบ Transactional ชัดเจน และใช้การปิดกะ (Shift Closing) บันทึกเฉพาะผลต่างเงินสดขาด/เกิน (Cash Over/Short) เท่านั้น ป้องกันการลงบัญชีซ้ำซ้อน
   - **Clearing Account Segregation:** แยกบัญชีพักเงินสดหน้าร้าน (1111) และบัญชีพักเงินดิจิทัล QR Gateway (1113) ออกจากกันอย่างเด็ดขาด และห้ามลงเงินฝากธนาคารหลัก (1112) จนกว่าจะ Reconcile ยอดสลิป/Statement
   - **Physical vs ERP Stock Conflict Handling:** ไม่ทิ้งบิลขายจริงหน้าร้าน (Verified Physical Sale) หากสต็อก ERP ขาด แต่รับยอดเงินและแยกไปพักที่สถานะ `needs_review: inventory_discrepancy` เข้าคิว Pending Stock Adjustment ให้ผู้จัดการคลังตรวจสอบ โดยไม่ฝืนยอมให้สต็อกติดลบหรือเสกตัวเลขขึ้นมา
   - **Tax Invoicing & Anti-Duplicate Output VAT:** รองรับการออกใบกำกับภาษีอย่างย่อ (ABB) และเต็มรูป (Full Tax) โดยมี Void/Replace event เชื่อมโยง source sale เดียว ป้องกันการลงภาษีขาย (Output VAT 2130) ซ้ำซ้อน

4. **การรักษาวินัยการบันทึกสถานะ (Honest Status Tracking & Gatekeeping):**
   - กำกับไว้อย่างโปร่งใสว่าเครื่องหมาย `[x]` ใน 28.1 หมายถึง **"บันทึกขอบเขตความต้องการแล้ว"** ไม่ใช่การอ้างว่าพัฒนาเสร็จ
   - งานส่วน Architecture, Schema, Code, และ Tests ใน 28.2 - 28.7 ยังคงเป็น `[ ]` รอการพัฒนาจริง
   - มี Pre-implementation Gate (28.1 L1665-1668) เป็นเงื่อนไขตรวจสอบก่อนเริ่มลงมือ Implement

---

### 14.3 ข้อเสนอแนะเชิงลึกเพิ่มเติมสำหรับขั้นตอนออกแบบรายละเอียด (Detailed Design Recommendations)

เพื่อให้การออกแบบสถาปัตยกรรมในหัวข้อ 28.2 - 28.4 มีความราบรื่นสูงสุด Gemini ขอเสนอแนะ 5 ประเด็นสำคัญที่ควรระบุไว้ใน Design Document:

| ลำดับ | ประเด็นที่เสนอแนะ | ข้อควรระวัง / ผลกระทบหากไม่ระบุ | แนวทางออกแบบที่แนะนำ (Recommended Practice) |
| :---: | :--- | :--- | :--- |
| **1** | **รูปแบบเลขที่บิลขายหน้าร้าน (Terminal Document Sequence)** | หากหลาย Terminal ในสาขาแย่งขอ Running Sequence เดียวกันจาก POS Server อาจเกิด Lock Contention หรือติดขัดเมื่อเครือข่ายภายในร้านหน่วง | กำหนด Prefix รูปแบบกระจายศูนย์ เช่น `{BranchCode}-{TerminalID}-{YYYYMMDD}-{Sequence}` เพื่อให้แต่ละ Terminal สามารถ Generate เลขที่บิลได้แบบ Local Sequential โดยไม่ต้องแย่ง Global Lock |
| **2** | **จังหวะการตัดสต็อกวัตถุดิบร้านอาหาร (Restaurant Stock Deduction Timing)** | ร้านอาหารมีทั้งแบบ Fast-Casual (จ่ายก่อนรับอาหาร) และ Dine-In (รับประทานก่อนคิดเงิน) การตัดสต็อกวัตถุดิบอาจไม่ตรงกับจังหวะชำระเงิน | ควรกำหนดให้การจอง/ตัดสต็อกวัตถุดิบ (Recipe Deduction) เกิดขึ้น ณ จังหวะ **"ส่งคำสั่งเข้าครัว (Order Sent to Kitchen)"** ส่วนขั้นตอนชำระเงินเป็นเพียง Settlement และหากลูกค้ายกเลิกอาหารหลังจากเข้าครัวแล้ว ต้องบันทึกเป็น Waste/Spoilage แทนการคืนสต็อกดี |
| **3** | **ความละเอียดของหน่วยนับยา (Pharmacy Base UoM Precision)** | การขายแยกหน่วย (เช่น 1 กล่อง = 10 แผง = 100 เม็ด) หากกำหนด Base Unit ไม่ดี อาจเกิดเศษทศนิยมปัดเศษสะสมใน Stock Ledger | กำหนดให้ **Base Unit of Measure (UoM) ใน ERP เป็นหน่วยย่อยที่สุดที่ตรวจนับได้จริง (เช่น "เม็ด")** และใช้ Conversion Factor ในการขายระดับแผงหรือกล่อง เพื่อให้ยอดตัดสต็อกคงเหลือเป็นจำนวนเต็มเสมอ |
| **4** | **การป้องกัน Race Condition ระหว่าง QR Payment กับ Cash** | ลูกค้าสแกน QR Code แล้วเกิดการหน่วง จึงขอเปลี่ยนใจชำระเงินสดทันที แคชเชียร์กดรับเงินสดขณะที่ลูกค้ากดยืนยันโอนเงินสำเร็จในเวลาไล่เลี่ยกัน | เมื่อลูกค้าขอเปลี่ยนช่องทางชำระเงิน แคชเชียร์ต้องกด "Cancel QR Intent" บนหน้าจอ Terminal ก่อนรับเงินสด เพื่อตัดสิทธิ์การรับ Callback หรือ Reject Webhook ที่เข้ามาภายหลังโดยอัตโนมัติ |
| **5** | **นโยบายการจัดเก็บและล้างข้อมูลบน POS Server (Data Retention & Pruning)** | POS Server ในสาขามักเป็น Hardware ขนาดเล็ก (Mini PC / POS Appliance) ที่มี Disk จำกัด หากเก็บ Log และ Payload ภาพสลิป/QR นานเกินไป Disk อาจเต็ม | ควรกำหนด Data Retention Policy สำหรับ POS Local DB ให้ทำการ Archive หรือ Prune ข้อมูล Event และ Transactions ที่ผ่านการ Sync และ Reconcile เข้า ERP สำเร็จเกิน 90 วันออกจากเครื่องหน้าร้านอย่างปลอดภัย |

---

### 14.4 บทสรุปและคำแนะนำถัดไป (Conclusion & Next Steps)
- **ความเหมาะสมของ Checklist:** **อนุมัติ (Approved for Planning & Design Phase)** สามารถใช้ Checklist Phase 28 ใน [checklist.md](checklist.md) เป็นฐานอ้างอิงในการเริ่มจัดทำ Architecture Document, Sequence Diagram, และ Data Ownership Matrix ตามหัวข้อ 28.2 ได้ทันที
- **คำแนะนำ:** ยึดถือ Pre-implementation Gate ใน 28.1 อย่างเคร่งครัด โดยรอให้ผู้ใช้หรือผู้มีอำนาจตัดสินใจยืนยันรายละเอียดเชิงปฏิบัติการ (เช่น Payment Gateway Provider และรายชื่อสาขา/Terminal นำร่อง) ก่อนเริ่มการล็อก Table Schema หรือสร้างตาราง Migrations จริง

---

## 15. ผลการทบทวนความเข้ากันได้ของระบบ (POS & ERP Compatibility Review — ข้อเสนอแนะเชิงลึกตอบกลับ gpt.md และ checklist.md 2026-10-24)

**สถานะ:** **Approved with Conditions (อนุมัติเชิงหลักการแบบมีเงื่อนไขด้านสถาปัตยกรรม)**  
**เอกสารอ้างอิง:** [gpt.md](gpt.md#L293-L334) (Phase 28 Compatibility Review), [checklist.md](checklist.md#L1643-L1793) (Phase 28), [backend/app/Http/Controllers/Finance/CommercialDocumentController.php](backend/app/Http/Controllers/Finance/CommercialDocumentController.php), [backend/app/Services/FinancialJournalService.php](backend/app/Services/FinancialJournalService.php)

Gemini ได้ทำการตรวจสอบรายการความเสี่ยงเชิงโค้ดและข้อเสนอแนะที่บันทึกใน [gpt.md](gpt.md#L293-L334) ร่วมกับ [checklist.md](checklist.md) Phase 28 อย่างละเอียด มีข้อสรุปและข้อกำหนดเชิงสถาปัตยกรรมเพิ่มเติมดังนี้:

### 15.1 การยืนยันข้อค้นพบระดับโค้ดของ GPT (Validation of GPT's Code-Level Findings)
Gemini **เห็นพ้อง 100% กับข้อสังเกตและข้อค้นพบเชิงลึกที่ GPT ตรวจพบในโค้ด ERP ปัจจุบัน** ซึ่งถือเป็นประเด็นเสี่ยงวิกฤต (Critical Design Risks) หากเริ่มเขียนโค้ดโดยไม่วางแผนล่วงหน้า:

1. **ความเสี่ยงการตัดสต็อกซ้ำซ้อน (Double Stock Deduction Risk):**
   - โค้ดใน [`CommercialDocumentController::deliver()`](backend/app/Http/Controllers/Finance/CommercialDocumentController.php#L194-L201) ทำการคำนวณและสร้าง `StockMovement` ติดลบ (`movement_type = 'deliver_to_customer'`) อยู่ใน Controller โดยตรง
   - หากขั้นตอน POS Import สร้าง Delivery Order (DO) ที่มีสถานะ `delivered` และตัว Connector เองก็สร้าง Stock Movement จากบิล POS อีกชุดหนึ่ง จะทำให้ **สต็อกสินค้าถูกตัดเบิ้ล 2 เท่าทันที**
2. **ความจำเป็นในการสกัด Controller Logic (Service Extraction Requirement):**
   - ปัจจุบัน Logic การตรวจสอบสต็อกติดลบและการสร้าง `StockMovement` ฝังแน่นอยู่ใน Controller
   - ERP Connector Worker ไม่สามารถจำลอง HTTP Request ไปยัง Controller ได้ และในขณะเดียวกันก็ **ห้าม** ยิง Eloquent `StockMovement::create()` ตรงๆ เพื่อข้าม Business Rules, Validation และ Notification Guards
3. **ข้อจำกัดของ FinancialJournalService เดิม (GL Account Mapping & Fallback 1100):**
   - โค้ด [`FinancialJournalService::postPayment()`](backend/app/Services/FinancialJournalService.php#L41) มี Fallback บัญชีเงินสดอยู่ที่ `1100` (Cash & Cash Equivalents)
   - หากนำโมเดล `Payment` เดิมมาใช้กับ POS โดยตรง ยอดเงินสดในลิ้นชัก (Drawer Cash) และยอด PromptPay QR จะวิ่งเข้าบัญชีรวม `1100` ซึ่ง **ละเมิด Guardrail บัญชีพักเงิน (Clearing Accounts 1111 และ 1113)**
4. **ความเสี่ยง Race Condition ระหว่าง QR Settlement ล่าช้ากับเงินสด (Late Webhook Edge Case):**
   - เมื่อแคชเชียร์กดยกเลิก QR เพื่อรับเงินสด แต่หลังจากนั้นไม่กี่วินาที Gateway กลับส่ง Webhook ยืนยันว่าลูกค้าโอนสำเร็จ
   - หากระบบโยน Webhook ทิ้ง ยอดเงินในบัญชีธนาคารกับระบบ ERP จะไม่ตรงกัน (Unreconciled) แต่หากนำไปตัดบิลเดิม จะกลายเป็นการจ่ายเงินซ้ำ 2 เท่า
5. **ความเสี่ยง Overkill ในการใช้ Work Order กับร้านอาหาร (Restaurant BOM vs Manufacturing WO):**
   - แบบจำลอง `WorkOrder` ใน Phase 27 ออกแบบมาสำหรับงานสั่งผลิตแบบ Batch อุตสาหกรรม (มี Planned, Released, Quality Check, Completed)
   - หากนำ Work Order ไปสร้างต่อจานอาหารทุกจานในร้านอาหาร จะทำให้เกิด Overhead มหาศาลและฐานข้อมูลบวมโดยใช่เหตุ

---

### 15.2 เงื่อนไขและข้อกำหนดสถาปัตยกรรม 5 ประการสำหรับขั้นตอนออกแบบ (Architectural Conditions)

Gemini กำหนดให้ขั้นตอนการจัดทำ Architecture & Contract Design (หัวข้อ 28.2) ต้องปฏิบัติตามเงื่อนไข 5 ประการนี้อย่างเคร่งครัด:

| ลำดับ | เงื่อนไขด้านสถาปัตยกรรม (Architectural Condition) | รายละเอียดและแนวทางการนำไปปฏิบัติ (Implementation Directive) |
| :---: | :--- | :--- |
| **1** | **Service Extraction Gate (สกัด Reusable Domain Service)** | • ก่อนสร้าง Connector ให้ทำการสกัด (Refactor) Logic ตัดสต็อกออกจาก `CommercialDocumentController` มาเป็น **`InventoryMovementService`** กลาง<br>• มี method ชัดเจน เช่น `deductStockForSale()` ที่ครอบคลุม `lockForUpdate()`, Negative Stock Guard, Moving Average Costing, และ Low Stock Alert<br>• ให้ทั้ง `CommercialDocumentController` (ERP Web UI) และ `PosImportService` (Connector Worker) เรียกใช้ Service กลางตัวเดียวกัน 100% |
| **2** | **Single Stock Posting Path (กำหนดเส้นทางตัดสต็อกเอกภาพ)** | • กำหนด Transaction Boundary ชัดเจน: เมื่อ POS Event นำเข้า ERP ให้สร้างเอกสารขายพร้อมกำหนด `movement_type = 'pos_sale'` เพียงเส้นทางเดียว<br>• ห้ามผูกการตัดสต็อกไว้กับ Delivery Order ที่ทำงานซ้ำซ้อน<br>• หากต้องสร้าง DO เพื่อการอ้างอิงทางเอกสาร ให้ DO นั้นอยู่ในสถานะที่เชื่อมโยงกับ `pos_sale` movement เดิม โดยไม่ trigger movement ใหม่ |
| **3** | **Direct Cash Sale GL Posting (รองรับการลงบัญชีขายสดหน้าร้าน)** | • ขยาย `FinancialJournalService` ให้มี method **`postPosSale()`** สำหรับบิลขายหน้าร้านโดยเฉพาะ:<br>  - เงินสด: `Dr. 1111 Cash on Hand (POS Drawer) / Cr. 4100 Sales Revenue / Cr. 2130 Output VAT`<br>  - QR Code: `Dr. 1113 Payment Gateway Clearing / Cr. 4100 Sales Revenue / Cr. 2130 Output VAT`<br>• ไม่ต้องวนบันทึกผ่านลูกหนี้การค้า (`1120 Accounts Receivable`) หากเป็นบิลที่ชำระเสร็จสิ้นหน้าร้านทันที เพื่อลดภาระ Journal Entries และป้องกันการสับสนใน Subledger |
| **4** | **นโยบาย Suspended Gateway Receipts (จัดการ Late Webhook)** | • หาก Webhook การชำระเงินของ QR เข้ามาหลังจากบิลนั้นถูกปิดด้วยเงินสดไปแล้ว หรือรายการถูกยกเลิกแล้ว<br>• ระบบ **ห้าม Drop ทิ้งเด็ดขาด** และ **ห้ามนำไปตัดยอดซ้ำ**<br>• ให้บันทึกเข้าตาราง **`suspended_gateway_receipts`** และลงบัญชีพัก `Dr. 1113 Gateway Clearing / Cr. 2190 Other Current Liabilities (Suspended Receipts)` พร้อมส่งการแจ้งเตือนระดับ Priority ไปยังผู้จัดการร้าน/แผนกบัญชีเพื่อกด Refund คืนลูกค้า หรือ Reconcile ยอดเงิน |
| **5** | **Lightweight Recipe Consumption สำหรับร้านอาหาร** | • สำหรับร้านอาหาร ห้ามสร้างเอกสาร `work_orders` รายจาน<br>• ให้ใช้กลไก **On-demand Recipe Consumption (Backflush)** โดย Connector จะอ่านสูตรอาหาร (BOM Snapshot) ณ เวลาขาย แล้วสร้าง `StockMovement` ตัดวัตถุดิบด้วย `movement_type = 'recipe_consumption'` โดยตรง<br>• แยกกรณี "ยกเลิกหลังเข้าครัว" ให้บันทึกเป็น `movement_type = 'food_waste_spoilage'` (Dr. ต้นทุนสูญเสีย / Cr. วัตถุดิบ) ตามสิทธิ์ผู้จัดการ |

---

### 15.3 สรุปผลการรับรอง (Formal Certification Verdict)

- **ผลการรับรองแนวทาง:** **Approved with Conditions**
- แนวคิดการใช้ **Shared Core + Business Modules** (Retail / Restaurant / Pharmacy) ของ POS และการแยก Server ตามที่ระบุใน [checklist.md](checklist.md) มีความเหมาะสมและผ่านการรับรองเชิงหลักการ
- **ขั้นตอนถัดไป:** ทีมพัฒนาสามารถเริ่มขั้นตอนจัดทำเอกสารสถาปัตยกรรมและ Schema (หัวข้อ 28.2–28.4) ได้ โดยต้องนำเงื่อนไขทั้ง 5 ข้อใน Section 15.2 ไปเป็นเกณฑ์ตั้งต้นใน Design Document และ **ต้องไม่เริ่มเขียนโค้ด Application/Connector จนกว่าจะผ่านการ Review แผนสกัด Service (Service Extraction Plan)**

---

### 15.4 รายการ Action Items สำหรับ GPT (Action Items for GPT to Update checklist.md & gpt.md)

เพื่อให้แผนงาน Phase 28 ในเอกสารมีความสมบูรณ์ สอดคล้องกับข้อเท็จจริงในโค้ด และพร้อมสำหรับการออกแบบสถาปัตยกรรมอย่างแท้จริง ขอให้ GPT ดำเนินการอัปเดตเอกสารตามรายการต่อไปนี้:

#### 1. การปรับปรุง [checklist.md](checklist.md#L1643-L1793) (Phase 28 Checklist Updates)
- [x] **Action Item 1.1 (Service Extraction Gate):** เพิ่มข้อกำหนดในหัวข้อ 28.2 และ 28.5 กำหนดให้ต้องทำการสกัด (Refactor) Stock Deduction Logic ออกจาก [`CommercialDocumentController`](backend/app/Http/Controllers/Finance/CommercialDocumentController.php) มาเป็น Service กลาง (`InventoryMovementService`) ก่อนพัฒนา Connector เพื่อให้ทั้งหน้าเว็บ ERP และ Worker ใช้ Business Rules เดียวกัน ป้องกันการยิง SQL ตรงข้าม Validation
- [x] **Action Item 1.2 (Single Stock Posting Path):** ระบุเงื่อนไขในหัวข้อ 28.5 ให้ชัดเจนว่า การนำเข้ารายการขาย POS สู่ ERP ต้องตัดสต็อกผ่าน `movement_type = 'pos_sale'` เพียงเส้นทางเดียว และหากมีการสร้างเอกสาร Delivery Order (DO) ต้องไม่ให้ DO นั้นสร้าง `StockMovement` ซ้ำซ้อน
- [x] **Action Item 1.3 (Direct POS Sale GL Posting Contract):** เพิ่มในหัวข้อ 28.6 และ 28.7 ระบุการขยาย [`FinancialJournalService`](backend/app/Services/FinancialJournalService.php) ให้มี method `postPosSale()` ลงบัญชีขายสดหน้าร้านตรง (`Dr. 1111 Drawer Cash / 1113 Gateway Clearing`, `Cr. 4100 Sales`, `Cr. 2130 Output VAT`) โดยไม่ต้องวนผ่าน `1120 Accounts Receivable`
- [x] **Action Item 1.4 (Suspended Gateway Receipts Policy):** เพิ่มในหัวข้อ 28.6 และ 28.7 นโยบายรองรับ Late QR Webhook โดยไม่ Drop ทิ้งและไม่ตัดบิลซ้ำ แต่บันทึกเข้าตาราง `suspended_gateway_receipts` และลงบัญชีพัก `2190 Other Current Liabilities` พร้อมแจ้งเตือน Manager
- [x] **Action Item 1.5 (Lightweight Recipe Consumption สำหรับร้านอาหาร):** เพิ่มในหัวข้อ 28.6.3 ระบุชัดเจนว่าร้านอาหารจะไม่สร้างเอกสาร `work_orders` ต่อจาน แต่ใช้การตัดสต็อกวัตถุดิบทางตรง (`movement_type = 'recipe_consumption'`) ตาม BOM Snapshot และรองรับ `movement_type = 'food_waste_spoilage'` กรณียกเลิกอาหารหลังเข้าครัว

#### 2. การปรับปรุง [gpt.md](gpt.md#L293-L334) (Handoff Log Updates)
- [x] **Action Item 2.1 (บันทึกสถานะการรับรอง):** บันทึกผลตรวจรับรองสถาปัตยกรรมเดิมใน `gpt.md` และ `checklist.md` เรียบร้อยแล้ว
- [x] **Action Item 2.2 (บันทึกเงื่อนไข 5 ประการ):** สรุปเงื่อนไขสถาปัตยกรรม 5 ข้อเป็นฐานในการจัดทำ Architecture & Contract Document (28.2) และเริ่ม Implementation แล้ว
- [x] **Action Item 2.3 (คงวินัยเอกสาร):** ดำเนินการจัดทำ Architecture Document ([document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md](document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md)) และ Security Plan ([document/PHASE_28_3_POS_SECURITY.md](document/PHASE_28_3_POS_SECURITY.md)) ก่อนเริ่มพัฒนา

---

## 16. การตรวจรับงาน Phase 28 (Standalone POS & ERP Connector — Implementation & Architecture Verification — 2026-10-26)

**สถานะ:** **ผ่านการตรวจรับระดับ Local Development & Architectural Prototype (Verified Local Implementation — In Progress for Production Acceptance)**  
**เอกสารอ้างอิง:** [checklist.md](checklist.md#phase-28-standalone-pos--erp-connector--shared-core--business-modules-planning) (Phase 28), [document/PHASE_28_IMPLEMENTATION_STATUS.md](document/PHASE_28_IMPLEMENTATION_STATUS.md), [document/PHASE_28_LOCAL_TEST_SECURITY.md](document/PHASE_28_LOCAL_TEST_SECURITY.md), [document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md](document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md), [document/PHASE_28_3_POS_SECURITY.md](document/PHASE_28_3_POS_SECURITY.md), [pos/README.md](pos/README.md), [gpt.md](gpt.md)

---

### 16.1 สรุปผลการตรวจรับ (Executive Summary)

Gemini ได้ดำเนินการตรวจสอบและตรวจรับงานพัฒนา **Phase 28 (Standalone POS & ERP Connector — Shared Core + Business Modules)** ตามขอบเขตงานที่มีการส่งมอบ ณ วันที่ 2026-10-26 อย่างละเอียด ทั้งในระดับโค้ด สถาปัตยกรรม ฐานข้อมูล ความปลอดภัย และผลการทดสอบเชิงประจักษ์ (Empirical Evidence)

**ข้อสรุปผลการประเมิน:**
1. **การพัฒนาระดับ Local Development ได้รับการตรวจรับ (Approved for Local Prototype & Core Workflows):**
   - มีการแยก Workspace ของ Standalone POS (`pos/`) ออกจาก ERP Backend (`backend/`) อย่างเด็ดขาด ทั้งในระดับ Dependency, Database, Config, Session, Staff Credentials และ APP_KEY
   - มีการสกัด (Extract) ตรรกะการตัดสต็อกออกจาก Controller สู่ [`InventoryMovementService`](backend/app/Services/InventoryMovementService.php) สำเร็จ ทำให้ทั้งหน้าเว็บ ERP และ POS Worker ใช้ Business Rules และ Entitlement Guard เดียวกัน
   - ระบบ Core และ Business Modules ทั้ง 3 สาย (Retail, Restaurant, Pharmacy) รวมถึงความสามารถส่วนขยาย (QR Menu, Refunds, Cash In-Out, Authorized Discount, Recipe Consumption, Food Waste Spoilage, Pharmacy Return Inspection, e-Tax Signing Owner Selector, และ ERP Ledger Checks) ได้รับการพัฒนาและทดสอบครอบคลุมทุก Test Suite และ Browser Fixture บน Local Environment
2. **ยังไม่สามารถปิด Phase 28 ในระดับ Production Sign-off ได้ (Phase 28 Remains In Progress):**
   - ยังไม่มีการเลือกผู้ให้บริการ e-Tax Provider / Channel / Real Signing / Live Submission
   - ยังไม่มีการต่อเชื่อมระบบเครือข่ายจริงข้าม 2 Server, Certificate mTLS จริง, และ Hardware จริง (เครื่องพิมพ์ใบเสร็จ Thermal Printer / เครื่องสแกนบาร์โค้ด)
   - การทดสอบ Concurrency และ Row-locking ยังกระทำบน SQLite `:memory:` ยังไม่ได้ทดสอบประสิทธิภาพและ Lock Contention บน MariaDB Production
   - โมดูลร้านอาหารยังขาดระบบ Modifiers/Options และ Live Kitchen Push/Polling ส่วนโมดูลร้านขายยายังขาดระบบ Drug Recall/Quarantine แบบอัตโนมัติเต็มรูปแบบ
   - ระบบกระทบยอดปัจจุบันเป็นระดับ ERP-only Ledger Checks ยังขาด Signed POS Checkpoints, Automated Retention Pruning, และ Disaster Recovery Fencing

---

### 16.2 ผลการตรวจสอบความสอดคล้องตามหลักการและ Guardrails (Architecture & Compliance Audit)

| หัวข้อการตรวจสอบ | ผลการตรวจรับเชิงสถาปัตยกรรมและโค้ด | สถานะ |
| :--- | :--- | :---: |
| **1. Service Extraction & Stock Concurrency** | สกัด logic สู่ [`InventoryMovementService`](backend/app/Services/InventoryMovementService.php); บังคับใช้ pessimistic row lock บนตาราง Product ทุก warehouse/lot แม้ stock ledger จะว่าง; ป้องกันการ oversell และป้องกันการบันทึก Delivery Order ซ้ำซ้อน | **ผ่าน (PASSED)** |
| **2. Standalone Isolation & Zero Trust** | โฟลเดอร์ `pos/` แยกแอปพลิเคชันชัดเจน; ทิศทางการเชื่อมต่อเป็น ERP Connector outbound ดึงข้อมูลจาก POS Sync API เท่านั้น ปราศจากเส้นทางที่ POS จะ query หรือ write เข้าฐาน ERP โดยตรง; ไม่มี ERP credentials หลุดไปฝั่ง POS | **ผ่าน (PASSED)** |
| **3. Financial Evidence & Staged Import** | การนำเข้ารายการขายของ ERP Worker แยกสถานะ `financial_imported` ออกจาก `inventory_pending`; หากสต็อกไม่พอ เงินยังคงบันทึกหลักฐานไว้และเปิดงานค้างให้ Inventory Manager ตรวจสอบ ไม่มีการบันทึกซ้ำเมื่อ Replay | **ผ่าน (PASSED)** |
| **4. Cash In-Out & Shift Guardrail** | บันทึกเงินเปิดกะ, รับ–จ่าย และปิดกะจำกัดเฉพาะกะ POS ไม่ปนกับ organization-wide cash balance; การลงบัญชี Cash In-Out ผูกกับ `cash_clearing` asset mapping โดยตรง ไม่สร้าง Invoice, Payment, รายได้ หรือ VAT โดยมิชอบ | **ผ่าน (PASSED)** |
| **5. Refunds & Source Correlation** | การคืนเงินต้องใช้ fresh manager password re-auth; ระบบบันทึก Credit Note และคืนเงินผ่าน `pos_import_refunds` โดยไม่ทำลาย one-reversal constraint เดิมของ ERP และปิดกั้น generic reversal จากฝั่ง ERP ต่อเอกสาร POS | **ผ่าน (PASSED)** |
| **6. Authorized Discount Controls** | เพดานส่วนลดกำหนดโดย ERP (`max_manager_discount_pct`); การอนุมัติของ Manager มีอายุ 5 นาที ผูกมัดกับ Normalized Cart และใช้ได้ครั้งเดียวใน transaction; การคำนวณภาษีและ COGS ถูกต้องตามยอดสุทธิ | **ผ่าน (PASSED)** |
| **7. Lightweight Recipe & Kitchen Lifecycle** | ระบบร้านอาหารตัดสต็อกวัตถุดิบทางตรง (`recipe_consumption`) ตาม BOM Snapshot ณ จังหวะ preparing ครั้งเดียว ไม่สร้าง Work Order ต่อจาน; การยกเลิกหลังเข้าครัวจัดประเภทเป็น `food_waste_spoilage` ไม่ตัดสต็อกซ้ำ | **ผ่าน (PASSED)** |
| **8. Pharmacy Return Inspection & Governance** | การตรวจรับยาคืนกำหนดสิทธิ์เฉพาะผู้จัดการที่เป็นเภสัชกร (`pharmacist_qualified = true`); บันทึกหลักฐาน lot, expiry, สภาพบรรจุภัณฑ์; เลือกคืนสต็อก (Restock พร้อมคืนต้นทุนเดิม) หรือทำลาย (Destroy) ได้อย่างรัดกุม | **ผ่าน (PASSED)** |
| **9. Local Security & Hard-Block Guard** | Feature Flags สำหรับ Transport Loopback, Prescriptions, Public Menu, Refunds, Cash Movements, Discounts, Restaurant และ Inspections ปิดเป็นค่าเริ่มต้น และมี Hard-block ปฏิเสธการทำงานทันทีหาก `APP_ENV=production` | **ผ่าน (PASSED)** |

---

### 16.3 หลักฐานเชิงประจักษ์จากการทดสอบจริง (Empirical Verification Results)

การตรวจรับได้ดำเนินการรันชุดคำสั่งทดสอบจริงบนสภาพแวดล้อม ณ วันที่ 2026-10-26 สรุปผลเชิงประจักษ์ดังตาราง:

| ขอบเขตการทดสอบ | คำสั่งทดสอบ | ผลการทดสอบเชิงประจักษ์ | สถานะ |
| :--- | :--- | :--- | :---: |
| **ERP Phase 28 Feature Tests** | `php artisan test --filter=Phase28` | **33 passed / 290 assertions** (76.88s)<br>- `Phase28InventoryServiceTest` (4 tests)<br>- `Phase28PosImportTest` (22 tests)<br>- `Phase28PosTransportTest` (4 tests)<br>- `Phase28TaxSettingsTest` (3 tests) | **PASSED** |
| **POS Isolated PHPUnit Tests** | `php ../backend/vendor/phpunit/phpunit/phpunit -c phpunit.xml` (จาก `pos/`) | **33 passed / 175 assertions** (1m 32s)<br>ครอบคลุม Core Cash, Cart, Staff, Signer, Shifts, Discounts, Refunds, Restaurant, และ Pharmacy Inspections | **PASSED** |
| **ERP TypeScript & Vite Build** | `npm run build` (ใน `backend/`) | **0 errors / 1,124 modules transformed**<br>Bundle assets สร้างสมบูรณ์รวมถึง `Pos.tsx` และ `PosReconciliation.tsx` (1m 9s) | **PASSED** |
| **POS TypeScript Type Check** | `node backend/node_modules/typescript/bin/tsc -p pos/tsconfig.json --noEmit` | **0 errors** (exit code 0) | **PASSED** |
| **POS Vite Build** | `node backend/node_modules/vite/bin/vite.js build --config pos/vite.config.mjs` | **0 errors / 22 modules transformed**<br>สร้าง `manifest.json`, `pos.js`, `pos.css`, `menu.js` สำเร็จ (7.05s) | **PASSED** |
| **Backend Code Style (Pint)** | `php vendor/bin/pint --test` (ใน `backend/`) | **PASSED** (exit code 0, formatting clean) | **PASSED** |
| **Browser Headless Fixtures** | Playwright test scripts ใน `pos/tests/Browser/` | **ผ่านทุก scenario บนความละเอียด 1440px และ 390px**<br>- QR Menu: `menu-result.json`<br>- Cash Checkout & Shifts: `checkout` result<br>- Manager Refund: `refund-result.json`<br>- Cash Movements: `cash-movements-result.json`<br>- Manager Discount: `discount-result.json`<br>- Restaurant Table/Kitchen/Split: `restaurant-result.json`<br>- Pharmacy Inspection: `inspection-result.json`<br>*(Console errors = 0, Network isolation strictly loopback-only)* | **PASSED** |

---

### 16.4 สรุปสถานะรายการคงค้างสู่ขั้นตอน Production (Pending Items for Production Acceptance)

เพื่อให้ Phase 28 พร้อมสำหรับการลงสู่สภาพแวดล้อมใช้งานจริง ขอให้บันทึกรายการที่ยังต้องดำเนินการต่อ (Pending Action Items) ดังนี้:

1. **e-Tax Live Integration Gate (รอผู้ใช้เลือก Provider):**
   - คัดเลือกและทำสัญญากับผู้ให้บริการ e-Tax Service Provider / Webhook Gateway
   - พัฒนาระบบเชื่อมต่อ Digital Signing (HSM / Cloud Key / PKCS#12) และระบบส่งเอกสาร XML/PDF-A3 พร้อมระบบ Error Handling / Retry ตามมาตรฐาน ขมธอ.
2. **Target Network & Hardware Deployment Gate:**
   - ติดตั้งและกำหนดค่า Reverse Proxy / Firewall แบบ Mutual TLS (mTLS) บน Server จริง แยกโซน ERP และ POS
   - ทดสอบการเชื่อมต่อกับอุปกรณ์จริงหน้าร้าน (Thermal Receipt Printer รุ่นที่เลือกใช้ และเครื่องสแกน 2D Barcode)
3. **MariaDB Concurrency & High Load Stress Testing:**
   - ทดสอบจำลองการขายพร้อมกันหลาย Terminal และแย่งโควตาสต็อกหน่วยสุดท้ายบนฐานข้อมูล MariaDB เพื่อพิสูจน์ Row-level Pessimistic Locking ภายใต้โหลดจริง
4. **Full Reconciliation & Disaster Recovery:**
   - พัฒนาระบบ Signed POS End-of-Day Checkpoints เพื่อตรวจรับรองความครบถ้วนของ event sequence จากฝั่ง POS
   - กำหนดนโยบาย Data Retention, Pruning Policy, และ Fencing เมื่อเกิดกรณี Restore ฐานข้อมูลย้อนหลัง
5. **Business Modules Extended Polish:**
   - Restaurant: เพิ่มการรองรับ Food Modifiers, Portion Sizes, และระบบ WebSocket/SSE Push แจ้งเตือนจอครัวแบบเรียลไทม์
   - Pharmacy: เพิ่มระบบ Batch/Lot Recall อัตโนมัติในกรณีที่พบปัญหายาไม่ผ่านมาตรฐาน

---

## 17. ผลการตรวจรับงาน Phase 28 ฉบับสมบูรณ์สำหรับสภาพแวดล้อม Local (Phase 28 Local Acceptance Sign-off — 2026-10-27)

**สถานะ:** **ผ่านการตรวจรับระดับ Local Development ครบถ้วน 100% (PASSED — Local Development & Architecture Acceptance Verified by Gemini)**  
**เอกสารประกอบการตรวจรับ:** [checklist.md](checklist.md#phase-28-standalone-pos--erp-connector--shared-core--business-modules-planning), [document/PHASE_28_LOCAL_ACCEPTANCE.md](document/PHASE_28_LOCAL_ACCEPTANCE.md), [document/PHASE_28_IMPLEMENTATION_STATUS.md](document/PHASE_28_IMPLEMENTATION_STATUS.md), [document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md](document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md), [document/PHASE_28_3_POS_SECURITY.md](document/PHASE_28_3_POS_SECURITY.md), [document/PHASE_28_REVIEW_ATTEMPT_20261027.md](document/PHASE_28_REVIEW_ATTEMPT_20261027.md), [gpt.md](gpt.md)

---

### 17.1 บทสรุปผลการประเมินรอบสมบูรณ์ (Comprehensive Local Verdict)

Gemini ได้ดำเนินการตรวจสอบโค้ดอย่างละเอียด (Read-only Code Review) ครอบคลุมทั้งส่วนที่ Untracked ทั้งหมดใน [`pos/`](pos/) และ [`backend/`](backend/) รวมถึงรันชุดคำสั่งทดสอบจริงทุกชุดบนเครื่องในวันที่ 2026-10-27

**ผลการวินิจฉัย (Verdict): PASS (ผ่านการรับรองสำหรับ Local Development)**

1. **สถาปัตยกรรมและการป้องกันข้อมูลสองฝั่ง (Zero Trust & No Double-Posting):**
   - **Standalone Isolation**: ฝั่ง POS แยกฐานข้อมูล, Credentials, Session, และ Private Keys ออกจาก ERP ชัดเจน และเชื่อมต่อผ่าน ERP Outbound Connector ผ่าน Loopback HTTPS เท่านั้น ไม่มีเส้นทางที่ POS จะเขียนตรงเข้าฐาน ERP
   - **Double-Entry & Inventory Integrity**: ตรรกะตัดสต็อกรวมศูนย์ที่ [`InventoryMovementService`](backend/app/Services/InventoryMovementService.php) มี Pessimistic Row Lock ระดับ Product เสมอ และระบบ Import บันทึกเงิน (`financial_imported`) กับสต็อก (`inventory_status`) แยกกัน หากสต็อกขาดจะคงหลักฐานเงินไว้และไม่ตัดซ้ำเมื่อ Replay
   - **Zero Tax Inflation**: การเปลี่ยนใบกำกับภาษีอย่างย่อ (ABB) เป็นใบกำกับภาษีเต็มรูป (FULL) อ้างอิงสล็อตเอกสารเดิม ไม่สร้างยอดขาย รายได้ หรือภาษีมูลค่าเพิ่มซ้ำซ้อน และสถานะ e-Tax ถูกตรึงไว้ที่ `awaiting_provider` ไม่แสดงสถานะส่งสำเร็จเท็จ

2. **โมดูลธุรกิจทั้ง 3 สาย (Core & Business Vertical Implementations):**
   - **Retail**: ระบบ QR Menu และ Token Expiry, Cash In-Out ผูกกับ `cash_clearing` asset mapping (ไม่สร้าง Invoice/VAT มิชอบ), Manager Discount เพดานตาม ERP กำหนด ผูก Cart/Terminal และมีอายุ 5 นาที, Refunds ผ่าน Credit Note อ้างอิงเอกสารเดิม
   - **Restaurant**: รองรับ Variant / Size / Modifier ผูกกับ SKU และ BOM เฉพาะของตนเอง, Table Session + Staff Accept, Kitchen Polling ทุก 5 วินาที, การยกเลิกหลังเข้าครัวจัดประเภทเป็น `food_waste_spoilage` ไม่ตัดสต็อกวัตถุดิบซ้ำ
   - **Pharmacy**: กำหนดสิทธิ์ตรวจรับยาคืนเฉพาะผู้จัดการที่เป็นเภสัชกร (`pharmacist_qualified = true`), บันทึกหลักฐาน Lot/Expiry และเลือก Restock คืนต้นทุนเดิม หรือ Destroy ได้อย่างแม่นยำ, ระบบ Lot Recall ดักจับและ Quarantine ทั้งใน POS และ ERP Stock Movement

3. **ความต่อเนื่องและการกู้คืนข้อมูล (Resilience, Checkpoints & Recovery):**
   - **Signed Checkpoints**: ตรวจสอบ Envelope Hash, Sequence, Generation และ High Watermark ครบถ้วน
   - **Independent Recovery Anchor**: เก็บไฟล์ Anchor ภายนอก DB ป้องกันกรณี Restore DB ย้อนหลังแล้วเกิด Event ซ้ำ หากตรวจพบความไม่สอดคล้องจะล็อกระบบด้วย Recovery Fence (HTTP 423) ทันที
   - **Encrypted Archival**: ข้อมูล Outbox ที่ผ่านการกระทบยอดเกิน 90 วัน จะถูกย้ายและเข้ารหัสแบบ Authenticated Encryption ใน Private Disk โดยคง Metadata และ Dedupe Identity ป้องกันการ Replay ซ้ำ

---

### 17.2 หลักฐานเชิงประจักษ์จากการทดสอบจริง (Empirical Verification Results)

| รายการทดสอบ | คำสั่งทดสอบที่ใช้จริง | ผลลัพธ์เชิงประจักษ์ (Empirical Evidence) | สถานะ |
| :--- | :--- | :--- | :---: |
| **ERP Phase 28 Feature Tests** | `php artisan test --filter=Phase28` | **39 passed / 348 assertions** (14.42s)<br>- `Phase28InventoryServiceTest` (4 tests)<br>- `Phase28PosImportTest` (27 tests)<br>- `Phase28PosTransportTest` (4 tests)<br>- `Phase28RecallTest` (1 test)<br>- `Phase28TaxSettingsTest` (3 tests) | **PASSED** |
| **POS Isolated PHPUnit Tests** | `php ../backend/vendor/phpunit/phpunit/phpunit -c phpunit.xml` (ใน `pos/`) | **41 passed / 242 assertions** (31.95s)<br>ครอบคลุม Cart, Shift, Cash, Refunds, Discounts, Tax, Checkpoint, Recovery | **PASSED** |
| **ERP Manufacturing & GL Regression** | `php artisan test --filter=Phase27ManufacturingTest` | **18 passed / 105 assertions** (11.43s)<br>ยืนยันไม่กระทบ Stock Movement และ Journal Posting เดิม | **PASSED** |
| **POS TypeScript Compilation** | `node backend/node_modules/typescript/bin/tsc -p pos/tsconfig.json --noEmit` | **0 errors** (Exit code: 0) | **PASSED** |
| **POS Production Vite Build** | `node backend/node_modules/vite/bin/vite.js build --config pos/vite.config.mjs` | **0 errors / 23 modules transformed**<br>สร้าง manifest.json, pos.js, menu.js, pos.css สำเร็จ (1.22s) | **PASSED** |
| **ERP Production Vite Build** | `npm run build` (ใน `backend/`) | **0 errors / 1,124+ modules transformed**<br>Bundle สร้างสมบูรณ์รวม `Pos.tsx`, `PosSetup.tsx`, `PosReconciliation.tsx` (12.58s) | **PASSED** |
| **Code Style (Laravel Pint)** | `php vendor/bin/pint --test ...` | **0 errors / Passed** (PSR-12 clean) | **PASSED** |
| **Browser Headless Fixtures** | Playwright scripts (1440px และ 390px) | **ผ่านทุก Scenario / Console errors = 0**<br>- `restaurant-online-result.json`: QR, Variant, Kitchen Polling, Checkout<br>- `tax-documents-result.json`: ABB, Full Replacement, Reprint, awaiting_provider | **PASSED** |

---

### 17.3 เงื่อนไขภายนอกสู่ระดับ Production (Pending External Gates)

รายการคงค้างต่อไปนี้เป็น External Dependency สำหรับการ Deploy สู่ Production จริง ไม่ใช่ข้อผิดพลาดในโค้ดระดับ Local:

1. **e-Tax Service Provider & Signing Integration Gate**:
   - การคัดเลือกและทำสัญญากับผู้ให้บริการ e-Tax Service Provider / Gateway ที่ผ่านการรับรองจาก ขมธอ.
   - การติดตั้งใบรับรองอิเล็กทรอนิกส์ (Digital Signing Key / HSM) และระบบเชื่อมต่อ XML/PDF-A3
2. **Physical Hardware & Network Deployment Gate**:
   - การติดตั้งเครือข่ายสอง Server แยกโซนด้วย Mutual TLS (mTLS) Reverse Proxy และ Firewall
   - การทดสอบการเชื่อมต่อกับเครื่องพิมพ์ใบเสร็จความร้อน (Thermal Receipt Printer) และเครื่องสแกนบาร์โค้ดจริง
3. **MariaDB High-Load & Concurrency Stress Gate**:
   - การทดสอบโหลดและการแย่งโควตาสต็อกพร้อมกันหลาย Terminal บน MariaDB 11.2+ เพื่อทดสอบ Row Lock Contention จริง
4. **Operational UAT & Disaster Recovery Drill**:
   - การทดสอบระบบกู้คืนข้อมูล (Recovery Drill) ร่วมกับผู้ดูแลระบบบนสภาพแวดล้อมใช้งานจริง









