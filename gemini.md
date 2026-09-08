# Gemini Review & Architecture Reference (Clean)

Last updated: 2026-09-08 (Post Phase 20 Closure & Cleanup)

Purpose: บันทึกข้อมูลอ้างอิงสถาปัตยกรรม (Architecture Reference), Security Guardrails, ข้อกำหนดเชิงระบบที่ยังมีผลต่อการพัฒนา (Decisions That Still Matter), และแนวทางการพัฒนาต่อยอดสำหรับ Phase ที่ยังไม่เริ่ม. รายละเอียดการตรวจรับรองและผลงาน Phase 1 - 20 ที่ปิดแล้วดูได้ที่ `checklist.md`, `README.md` และ Git history.

---

## 1. สถานะโครงการปัจจุบัน (Current Status)

- **Phase 1 - 20 (รวม Phase 18.1): เสร็จสมบูรณ์และปิดรอบแล้ว 100% (Complete & Closed)**
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
- **สถานะการทดสอบระบบ (Validation Snapshot):** 257 Tests Passed (1,968 Assertions / 0 Failures), TypeScript `tsc` Clean, Vite Build Clean, ESLint Clean (0 warnings / 0 errors), Prettier Clean, Laravel Pint Clean.
- **ประตูสู่ Phase ถัดไป (Next Gate):** **Phase 21: Operational Notifications & Outbox Engine**
- **Closed Phase Governance:** งานที่เป็น Security Vulnerability, Data Integrity Bug, Regression Fix หรือ Production Blocker ใน Phase ที่ปิดแล้ว สามารถแก้ไขได้โดยต้องมี Feature Test ครอบคลุมและบันทึกเหตุผลชัดเจน ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-20 โดยไม่มี Decision/Checklist ใหม่

---

## 2. ข้อมูลอ้างอิงการควบคุมความปลอดภัย (Active Security Guardrails Reference)

รายการควบคุมความปลอดภัยที่ต้องคงอยู่ในการพัฒนาต่อยอดทุกส่วน:

| หัวข้อความปลอดภัย | รายละเอียดการควบคุมความปลอดภัย (Security Control) |
| :--- | :--- |
| **Directory Traversal Protection** | ตรวจสอบ Canonical Path ด้วย `realpath()` และ `str_starts_with($fullPath, $basePath)` ป้องกันการเข้าถึงไฟล์นอกพื้นที่จัดเก็บไฟล์สาธารณะอย่างสมบูรณ์ |
| **Invite Token Protection** | ซ่อนและไม่ส่ง Plain Invite Token และ URL ใน Flash Session หากอยู่ในสภาพแวดล้อมที่เป็น `production` |
| **Audit Logs Redaction** | ทำงานผ่าน Central Redaction Guard ใน Eloquent `saving` event เพื่อเซนเซอร์คำสำคัญ (password, token, secret) และทำ Masking เลขประจำตัวประชาชน (`person_id`) อย่างต่อเนื่อง |
| **Upload & Attachment Security** | ตรวจสอบนามสกุลและ MIME Type ของไฟล์แนบอย่างเข้มงวด ป้องกันไฟล์ Executable/Polyglot และจัดเก็บใน storage key ฝั่ง server ใต้ `tenants/{org_id}/...` เท่านั้น |
| **Session Invalidation** | เมื่อมีการ Disable บัญชีผู้ใช้ หรือมีการเปลี่ยนแปลง Role หรือปรับปรุงระดับสิทธิ์ (Permissions) ระบบจะทำลายเซสชันเก่าในตาราง `sessions` และล้าง `remember_token` ของผู้ใช้รายนั้นทันทีเพื่อบังคับให้ออกจากระบบ |
| **Financial Isolation & Privacy** | ข้อมูลการเงินรวมทั้งหมดบน Dashboard แสดงผลเฉพาะผู้ที่มีสิทธิ์ `executive.dashboard.view` หรือผู้ดูแลระบบ (Owner/Admin) เท่านั้น พนักงานปกติจะไม่มีการดึงหรือเห็นข้อมูลสรุปการเงิน |
| **Bank Account & Key Data Protection** | เลขบัญชีธนาคารจัดเก็บแบบเข้ารหัส (`encrypted` cast) พร้อม `account_number_hash` (SHA-256) และ Certificate ของ e-Tax เก็บเฉพาะ Vault Reference/Expiry Date เท่านั้น (ห้ามเก็บ Private Key ใน Database) |
| **Payslip & Payroll Privacy Guard** | พนักงานแต่ละคนสามารถเข้าถึงและดาวน์โหลด Payslip PDF ได้เฉพาะรายการที่เป็นของตนเองเท่านั้น (`user_id === auth()->id()`) เว้นแต่เป็นผู้ถือสิทธิ์ `payroll.view` ในองค์กรเดียวกัน และห้ามข้ามองค์กร 100% |
| **DMS Private Download & Scan Guard** | ไฟล์เอกสารส่วนกลางดาวน์โหลดผ่าน Private Download Controller ที่ตรวจสอบ Session, `org_id`, สิทธิ์การเข้าถึง, Parent Permission, ความลับ (Sensitivity 5 ระดับ), และสถานะความปลอดภัยของไฟล์ (`scan_status === 'clean'`) ทุก Request 100% |
| **Two-Factor Authentication & Secret Guard** | `two_factor_secret` และ `two_factor_recovery_codes` ถูกเข้ารหัสด้วย Eloquent `encrypted` cast, ซ่อนจาก JSON serialization (`#[Hidden]`), Recovery Codes ถูกแฮชด้วย `Hash::make` และเป็น Single-Use, Trusted Device Tokens เก็บเฉพาะ SHA-256 Hash ผูกกับ User-Agent, และการรีเซ็ต 2FA สงวนสิทธิ์เฉพาะ Owner ในองค์กรเดียวกัน |
| **Attendance Privacy & Consent Guard** | การจัดเก็บ IP และ GPS ในการลงเวลาทำงานต้องเป็นไปตามหลักความยินยอม (Opt-in Consent) ภายใต้ `hr.attendance_privacy` โดยค่าเริ่มต้นคือ `capture_ip=false`, `capture_gps=false`, และ `require_employee_consent=true` หากเปิดการบันทึก IP ระบบจะจัดเก็บเฉพาะ SHA-256 Hash และห้ามจัดเก็บ Raw IP เด็ดขาด |
| **No Cash Balance Guard** | ห้ามสร้าง UI Widget, JSON/Inertia Prop หรือ API เผยแพร่ยอด `Cash Balance` ดิบหรือยอดเงินสดรวมใน Dashboard / Digest จนกว่าจะมี Product Decision และ Bank Reconciliation Boundary ที่ชัดเจน |
| **AI OCR & Automation Guard** | โมดูล AI / OCR สกัดข้อมูลสำหรับร่างเอกสาร (Assisted Draft) พร้อมแนบค่า Confidence และหลักฐานต้นทางเท่านั้น ห้าม Auto-post รายจ่ายหรือบันทึกบัญชี GL โดยปราศจาก Human Review |
| **Workflow SoD & Immutable Snapshot Guard** | ห้ามผู้ขออนุมัติ (Requester) อนุมัติตนเองทุกกรณี (Strict SoD) โดยระบบกรอง Requester ออกตั้งแต่ระดับ Assignee Snapshot และตรวจบล็อกซ้ำใน Action Evaluation พร้อมจับภาพ Snapshot ลำดับขั้นตอน ณ วันยื่นคำขอเพื่อป้องกันผลกระทบจากการปรับผังองค์กรย้อนหลัง |

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

---

## 4. ข้อแนะนำการเตรียมความพร้อมขึ้น Production (Production Deployment & Server Readiness)

1. **Background Workers & Scheduler:**
   - **Laravel Scheduler (Cron):** ตั้งค่า `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1` บนเซิร์ฟเวอร์เพื่อให้คำสั่งตรวจเช็กหนี้ แจ้งเตือนอัตโนมัติ คำสั่งตัดค่าเสื่อมราคารายเดือน (`assets:depreciate` ทุกวันที่ 1 เวลา 01:00), คำสั่ง Revaluation/Reversal สิ้นงวด, คำสั่งตรวจวันหมดอายุเอกสาร (`documents:check-expiry`) และคำสั่งจัดการ Retention เอกสารหมดอายุ (`documents:enforce-retention`) ทำงานสม่ำเสมอ
   - **Queue Worker (Supervisor/Systemd):** รัน `php artisan queue:work --tries=3` เพื่อให้ระบบส่งอีเมลแจ้งเตือนและคิวส่งเอกสาร e-Tax ทำงานเบื้องหลังโดยไม่บล็อก Web Request
2. **Thai Fonts for DomPDF:**
   - เซิร์ฟเวอร์ Linux Production ต้องติดตั้ง TrueType Fonts ภาษาไทย (เช่น Sarabun หรือ THSarabunNew) ใน `storage/fonts` หรือฝังผ่าน `@font-face` เพื่อป้องกันปัญหาสระลอยหรือภาษาไทยแสดงเป็น `???` ในไฟล์ PDF ทั้ง Invoice, PO, WHT และ Payslip
3. **Uploads & File Storage:**
   - รัน `php artisan storage:link` และตรวจสอบการตั้งค่า `upload_max_filesize` / `post_max_size` (PHP) และ `client_max_body_size` (Nginx) ให้รองรับการอัปโหลดไฟล์แนบ สลิป และเอกสาร DMS
4. **Environment Security & Disaster Recovery:**
   - Production `.env` ต้องตั้งค่า `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
   - ตรวจสอบ `APP_KEY` ให้คงที่เสมอ เนื่องจากการเข้ารหัส `two_factor_secret`, `two_factor_recovery_codes`, และ Bank Accounts พึ่งพาคีย์นี้ หาก `APP_KEY` เปลี่ยนจะทำให้ถอดรหัสข้อมูลเดิมไม่ได้
   - ตั้งค่า Automated Daily Database Backup และทดสอบ Restore Drill อย่างน้อย 1 รอบก่อนเปิดใช้งานจริง

---

## 5. แผนงานพัฒนาต่อยอดและ Guardrails (Future Roadmap & Architectural Guardrails)

### แผนผังลำดับการพัฒนาที่เห็นชอบร่วมกัน (Consensus Roadmap Phase 1 - 28):

```
[Phase 1 - 18.1 & Phase 19: Core, Financials, Treasury, Inventory, Payroll, DMS, 2FA & HR Foundation] (Closed)
       ↓
[Phase 20: Dynamic Approval Workflow Engine] (Thresholds, Multi-level, Delegation, Leave/PR/PO/Expense Chains) (Closed)
       ↓
[Phase 21: Operational Notifications & Outbox Engine] (LINE OA / Slack / Telegram, Quiet Hours, No Cash Balance) (Next Gate)
       ↓
[Phase 22: Customer & Supplier Self-Service Portals] (External Identity, Scan Gate & Scoped Access)
       ↓
[Phase 23: Thai PromptPay QR & Payment Gateway] (Verified Webhooks & Settlement-Verified GL)
       ↓
[Phase 24: Direct E-Tax Invoice & RD API Gateway] (Certified Provider & Vault Signing)
       ↓
[Phase 25: Cash Flow Forecasting & Financial Health] (30-90 Day Projection without raw Cash Balance)
       ↓
[Phase 26: AI OCR Document Ingestion] (Assisted Drafts + Human-in-the-Loop)
       ↓
[Phase 27 - 28 (Optional Verticals): Manufacturing BOM & Point of Sale (POS)]
```

### ข้อกำหนดและ Guardrails สำหรับ Phase ถัดไป:

1. **Phase 21: Operational Notifications & Outbox Engine:**
   - ต้องใช้ Transactional Outbox, Idempotency Key, Retry/Exponential Backoff, Dead-Letter Queue Visibility และ Per-Org Opt-in
   - Credentials ของช่องทางภายนอก (LINE OA, Slack, Telegram) ต้องเข้ารหัสหรืออ้างอิง Secret Store; Webhook ขาออกต้องมีการเซ็นลายมือชื่อดิจิทัล (Signed Webhooks)
   - รองรับ User Notification Preferences และ Quiet Hours
   - Notification Digest รายวันรายงาน AR/AP Aging, Due Items, ยอดขาย, ค่าใช้จ่าย, Low Stock, Overdue Tasks, เอกสาร DMS ใกล้หมดอายุ แต่**ห้ามคำนวณหรือส่งค่า Raw Cash Balance เด็ดขาด**
2. **Phase 22: Customer & Supplier Portals:**
   - ใช้ External Identity แยกจาก Staff Session เด็ดขาด, Scoped Access, Expiry/Revocation, Rate Limit และ Audit Trail
   - ดาวน์โหลดเอกสารผ่าน Private Controller เท่านั้น ห้ามสร้าง Public Document URLs
   - การอัปโหลดไฟล์จากคู่ค้าต้องผ่าน Scanner Quarantine Gate, MIME/Size Validation, และ Human Review Gate ก่อนสร้างร่าง AP Invoice
3. **Phase 23: PromptPay QR & Payment Gateway:**
   - Webhook ต้องมี Signature Verification, Replay Attack Protection, Deduplication, และ Event Ordering
   - ต้องกระทบยอดและยืนยัน Settlement Status สำเร็จก่อนออกใบเสร็จรับเงินหรือบันทึกบัญชี Double-entry GL
4. **Phase 24: Direct E-Tax Invoice & RD API Gateway:**
   - เชื่อมต่อผ่าน Certified Provider หรือ RD API พร้อมระบบจัดการ Private Key ผ่าน HSM / Cloud Key Vault (ห้ามเก็บ Private Key ใน Database)
   - มี Outbox & Reconciliation State Machine และจัดเก็บ CAdES-BES Signature
5. **Phase 25: Cash Flow Forecasting & Financial Health:**
   - แสดงการประมาณการกระแสเงินสดล่วงหน้า 30-90 วัน โดยต้องมี Scenario/Assumption Snapshot และไม่เผยแพร่ยอด Raw Cash Balance
6. **Phase 26: AI OCR Document Ingestion:**
   - ทำหน้าที่เป็น Assisted Draft พร้อมคะแนนความเชื่อมั่น (Confidence Score) และแนบไฟล์ต้นฉบับใน DMS
   - บังคับให้ต้องผ่านการตรวจสอบจากมนุษย์ (Human Review) ก่อนแปลงเป็น Expense หรือ Purchase Order จริง ห้าม Auto-post เด็ดขาด
7. **Phase 27 - 28 (Optional Verticals): Manufacturing BOM & Point of Sale (POS):**
   - พัฒนาเมื่อมีความต้องการทางธุรกิจจริง
   - POS Shift Reconciliation จำกัดอยู่เฉพาะในขอบเขต POS ประจำวัน และห้ามรั่วไหลเป็น Organization-wide Cash Balance

---

## 6. แนวทางและมาตรฐานคุณภาพโค้ด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 2 และ 3 ของ `gemini.md` โดยเด็ดขาด** (Multi-Tenant Isolation, Redaction, Permission Enforcement, Session Invalidation, Payslip Privacy Guard, DMS Private Download Guard, 2FA Encrypted Secret Guard, Attendance Privacy Guard, No Cash Balance Guard, AI OCR Human-in-the-Loop, Workflow SoD Guard)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-20 โดยไม่มี Decision/Checklist ใหม่
   - การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production Blocker ใน Phase ที่ปิดแล้ว ต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality ในทุกขั้นตอนการพัฒนา:**
   - PHP Unit & Feature Tests: `php artisan test` (ต้องผ่าน 100%)
   - Frontend Build: `npm run build` (Clean 100%)
   - TypeScript Compilation: `npm run tsc` (Clean 0 errors)
   - Static Code Analysis: `npm run lint` (ESLint 0 warnings / 0 errors), `npm run check-format` (Prettier Clean), `php vendor/bin/pint --test` (Pint Clean 0 issues)
