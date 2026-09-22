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



