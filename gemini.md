# Gemini Review & Architecture Reference (Clean)

Last audited: 2026-08-30 (Phase 17 Enterprise DMS Closed, Ready for Phase 18 Security 2FA)

Purpose: บันทึกข้อมูลอ้างอิงสถาปัตยกรรม, Security Guardrails, ข้อกำหนดเชิงระบบที่ยังมีผลต่อการพัฒนา (Decisions That Still Matter), ผลการตรวจสอบ Phase 15 - 17, การตรวจประเมินความเข้ากันได้ของข้อมูล Phase 1 - 17 (Cross-Phase Data Compatibility Audit), ข้อสรุปเห็นชอบร่วมต่อข้อเสนอ DMS ของ GPT ใน `gpt.md` (Consensus on GPT DMS Design), ข้อกำหนดการจัดเก็บเอกสารภาษีตามกฎหมายไทย และแผนงานพัฒนา Phase 18 (รายละเอียดงานที่ปิดแล้วดูได้ที่ `checklist.md`, `README.md` และ Git history)

---

## 1. ข้อมูลอ้างอิงการควบคุมความปลอดภัย (Security Guardrails Reference)

รายการควบคุมความปลอดภัยที่ต้องคงอยู่ในการพัฒนาต่อยอดทุกส่วน:

| หัวข้อความปลอดภัย | รายละเอียดการควบคุมความปลอดภัย (Security Control) |
| :--- | :--- |
| **Directory Traversal Protection** | ตรวจสอบ Canonical Path ด้วย `realpath()` และ `str_starts_with($fullPath, $basePath)` ป้องกันการเข้าถึงไฟล์นอกพื้นที่จัดเก็บไฟล์สาธารณะอย่างสมบูรณ์ |
| **Invite Token Protection** | ซ่อนและไม่ส่ง Plain Invite Token และ URL ใน Flash Session หากอยู่ในสภาพแวดล้อมที่เป็น `production` |
| **Audit Logs Redaction** | พัฒนา Central Redaction Guard ใน Eloquent `saving` event เพื่อเซนเซอร์คำสำคัญ (เช่น password, token, secret) และทำ Masking เลขประจำตัวประชาชน (`person_id`) |
| **Upload & Attachment Security** | ตรวจสอบนามสกุลและ MIME Type ของไฟล์แนบ (Voucher proof slip, Fixed Asset proof, Logos, Documents) อย่างเข้มงวด ป้องกันไฟล์ Executable/Polyglot และจัดเก็บใน storage key ฝั่ง server ใต้ `tenants/{org_id}/...` เท่านั้น |
| **Session Invalidation** | เมื่อมีการ Disable บัญชีผู้ใช้ หรือมีการเปลี่ยนแปลง Role หรือปรับปรุงระดับสิทธิ์ (Permissions) ระบบจะทำลายเซสชันเก่าในตาราง `sessions` และล้าง `remember_token` ของผู้ใช้รายนั้นทันทีเพื่อบังคับให้ออกจากระบบ |
| **Financial Isolation & Privacy** | ข้อมูลการเงินรวมทั้งหมดบน Dashboard แสดงผลเฉพาะผู้ที่มีสิทธิ์ `executive.dashboard.view` หรือผู้ดูแลระบบ (Owner/Admin) เท่านั้น พนักงานปกติหรือพนักงานส่งมอบทั่วไปจะไม่มีการดึงหรือเห็นข้อมูลสรุปการเงินได้เลย |
| **Bank Account & Key Data Protection** | เลขบัญชีธนาคารจัดเก็บแบบเข้ารหัส (`encrypted` cast) พร้อม `account_number_hash` (SHA-256) และ Certificate ของ e-Tax เก็บเฉพาะ Vault Reference/Expiry Date เท่านั้น (ห้ามเก็บ Private Key ใน Database) |
| **Payslip & Payroll Privacy Guard** | พนักงานแต่ละคนสามารถเข้าถึงและดาวน์โหลด Payslip PDF ได้เฉพาะรายการที่เป็นของตนเองเท่านั้น (`user_id === auth()->id()`) เว้นแต่เป็นผู้ถือสิทธิ์ `payroll.view` ในองค์กรเดียวกัน และห้ามข้ามองค์กร 100% |
| **DMS Private Download & Scan Guard** | ไฟล์เอกสารส่วนกลางดาวน์โหลดผ่าน Private Download Controller ที่ตรวจสอบ Session, `org_id`, สิทธิ์การเข้าถึง, ความลับ (Sensitivity), และสถานะความปลอดภัยของไฟล์ (`scan_status === 'clean'`) ทุก Request 100% |

---

## 2. โครงสร้างและการเข้าถึงข้อมูล (Architecture & Access Scope)

- **การคำนวณราคาทุนจริงโครงการ (Dynamic Project Cost):** คำนวณแบบ Dynamic บน Memory จากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`) และห้ามมีคอลัมน์ `actual_cost` ในตาราง `projects`
- **การจำกัดสิทธิ์พนักงาน (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
- **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ
- **ความถูกต้องทางภาษีและการค้า (Tax & Commercial Integrity):** ห้ามใช้วิธี Void กับใบกำกับภาษีหรือเอกสารการค้าทางการที่ส่งมอบหรือยื่นรอบบัญชีแล้ว ให้ใช้วิธีออก Credit Note / Debit Note เพื่อปรับยอดหนี้และกระทบยอดภาษีขายตามกฎหมายสรรพากร
- **ระบบบัญชีคู่และงวดบัญชี (GL & Period Lock):** บันทึก Journal Entries อัตโนมัติจากเอกสารต้นทาง (Invoice, Payment, VendorPayment, Expense, Stock, CN/DN, PV/RV, Bank, Petty Cash, Fixed Asset, FX, Payroll) มี Idempotency Guard ป้องกันการลงซ้ำ และงวดบัญชีที่ปิด (`closed`) ห้าม Post/Void/แก้ไขย้อนหลังเด็ดขาด
- **Multi-Currency & Rate Snapshot:** เอกสารทางการค้า/จัดซื้อ/การเงิน ทำการ Snapshot `currency`, `exchange_rate` (`DECIMAL(18,6)`), และ `base_*` amounts (`DECIMAL(18,2)`) เสมอ ห้ามทำ Dynamic Join กับ `exchange_rates` ในการคำนวณย้อนหลัง
- **AP & Inventory Costing Boundary:** `Expense` (Vendor Invoice) เป็นแหล่งตั้งหนี้ AP (`2110`) เท่านั้น ส่วน `GRN` บันทึกเป็น Inventory เข้าบัญชีพักสินค้า/GRNI (`1150`) และบันทึก `base_unit_cost` / `base_total_cost` บน `StockMovement` เป็น Single Source of Truth
- **Warehouse & Storage Scope:** ทุกการเคลื่อนไหวสต็อก (Stock Movement) และเอกสารคลัง (GRN, Transfer, Stock Count, DO) ต้องตรวจสอบว่า `warehouse_bin_id` อยู่ใน `warehouse_id` และ `inventory_lot_id` อยู่ใน `product_id` ขององค์กรเดียวกันอย่างเคร่งครัด
- **Payroll & AP Boundary:** การจ่ายเงินเดือนพนักงาน (`PayrollRun`) บันทึกผ่านบัญชีค้างจ่าย `2140` (Payroll Payable) และตัดจ่ายเข้าบัญชีเงินสด/ธนาคารโดยตรง โดยไม่ผ่านตาราง `VendorPayment` (AP Subledger) และจำกัด Initial Scope เป็นสกุลเงิน THB เท่านั้น
- **DMS Central Repository & Cross-Module Linking:** จัดเก็บเอกสารองค์กรแบบรวมศูนย์ (`documents`, `document_versions`) พร้อมความสามารถเชื่อมโยงแบบ Many-to-Many ผ่าน `document_links` ควบคุมการแจ้งเตือนวันหมดอายุเฉพาะหมวดที่เปิดใช้ และบังคับใช้นโยบายการเก็บรักษาเอกสารภาษี/การเงินตามกฎหมายไทย (Retention Window 5-7 ปี) แบบ Append-Only

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 17: Complete / Closed**
  - **Core & Financial Foundations (Phase 1 - 7):** Core MVP, CRM, Finance, Delivery, Multi-role Dashboards, Number Sequences, Inclusive VAT, Suppliers & POs.
  - **Documents, Compliance & Treasury (Phase 8 - 10):** Official Print/PDF, Tax Reports/Aging/WHT, Commercial Docs (Quotation, CN/DN, Billing Note, DO, PR, Voucher), Bank Accounts (Encrypted), Bank Reconciliation, Petty Cash, Cheques/PDC.
  - **Accounting, E-Tax & Fixed Assets (Phase 11 - 13):** General Ledger & Double-Entry (COA, Periods, Auto-Posting, Reversal), E-Tax Integration Layer (XML, Hash, RD Prep), Fixed Assets & Monthly Straight-Line Depreciation.
  - **Multi-Currency, AP Subledger & Treasury (Phase 14 - 14.1):** FX Rate Master & Immutable Snapshots, Realized FX, Month-End AR/AP/FCD Revaluation & Auto-Reversal, AP Subledger (`VendorPayment`), FCD Bank Accounts & Two-Sided Transfers, Inventory FX Bridge.
  - **Advanced Inventory & Operations (Phase 15):** Warehouses, Bins, Lots & Expiration, Barcode/QR Scanning, Stock Transfer, Stock Count Differencing, Reorder Point & Low Stock In-App/Email Alerts.
  - **Payroll, Social Security & Tax (Phase 16B):** Employee Payroll Profiles, Effective-Dated Tax & Social Security Policies, Progressive Tax Calculation, Social Security Ceiling Capping, Approval & Auto-GL Posting (`5500`, `5510`, `2140`, `2150`, `2160`, `2170`), Direct Payment Settlement, Payslip PDF Generation & Privacy Guard, ภ.ง.ด. 1 และ Social Security Workpaper CSV Exports.
  - **Enterprise Document Management (Phase 17):** Central Repository, Immutable Versioning (`document_versions`), Many-to-Many Linking (`document_links`), Category-Driven Expiry & Scheduled Alerts (`documents:check-expiry`), 5-Level Sensitivity RBAC, Effective-Dated Retention Policies, Private Download Controller, และ Legacy `StoredFile` Idempotent Backfill (`documents:backfill-legacy`).
- **Validation Snapshot:** 239 Tests Passed (1,890 Assertions / 0 Failures), TypeScript/Vite Build Clean, ESLint Clean (0 warnings), Prettier Clean, Laravel Pint Clean (0 issues).

---

## 4. ผลการตรวจสอบและข้อเสนอแนะราย Phase

### 4.1 ผลการตรวจประเมินความเข้ากันได้ของข้อมูล Phase 1 - 17 (Cross-Phase Data Compatibility Audit)

| มิติการตรวจสอบ | ความสอดคล้องเชิงสถาปัตยกรรม (Architectural Compatibility) | สถานะ |
| :--- | :--- | :--- |
| **Multi-Tenant Isolation (Phase 1 - 17)** | ทุก Model และ Query มีการ Scoping ด้วย `org_id` อย่างรัดกุม ป้องกันการเข้าถึงข้อมูลข้ามองค์กร 100% | **ถูกต้อง / สมบูรณ์** |
| **Commercial & Payroll -> GL Chain (Phase 2, 3, 7, 8, 9, 11, 16B)** | เอกสารการค้าและ Payroll ลงบัญชี GL แบบ Double-Entry อัตโนมัติ (Idempotent & Period Locked) | **ถูกต้อง / สมบูรณ์** |
| **Multi-Currency & Base Costing (Phase 14, 14.1, 15)** | `StockMovement` ยึดถือ `base_unit_cost` และ `base_total_cost` (THB) เป็น Single Source of Truth ทั้งใน GRN, Stock Transfer, Stock Adjustment, และ Stock Count | **ถูกต้อง / สมบูรณ์** |
| **AP / Inventory / Payroll Boundaries (Phase 7, 14.1, 15, 16B)** | GRN บันทึกสต็อกเข้า `1140` คู่บัญชีพัก `1150 (GRNI)`, Expense บันทึกตั้งหนี้ AP `2110`, Payroll บันทึกหนี้เงินเดือน `2140` แยกกันชัดเจน | **ถูกต้อง / สมบูรณ์** |
| **DMS Cross-Module Integration (Phase 17)** | ตาราง `document_links` รองรับการผูกเอกสารร่วมกับ Customer, Supplier, PO, Deal, Project, Fixed Asset, Bank Account และ Expense อย่างยืดหยุ่น | **ถูกต้อง / สมบูรณ์** |
| **Download Security & Privacy (Phase 3, 10, 16B, 17)** | Private Controller สำหรับ Voucher proof, Payslip PDF และ DMS Documents ตรวจสอบ Session/Role/Owner/MIME/Scan-status ทุก Request | **ถูกต้อง / สมบูรณ์** |

---

### 4.2 ผลการตรวจสอบและข้อเสนอแนะ Phase 16B: Payroll, Social Security & Tax

#### จุดเด่นที่ผ่านการทดสอบ (Strengths & Verified Architecture):
1. **Effective-Dated & Versioned Policies:** ตาราง `payroll_tax_policies` และ `social_security_policies` รองรับ `effective_from` และ `effective_to`
2. **Progressive Thai PIT & SSO Capping Calculation:** คำนวณภาษีหัก ณ ที่จ่าย ภ.ง.ด. 1 และประกันสังคม ม.33 (5% จำกัดเพดานสูงสุด 17,500 บาท)
3. **Double-Entry GL Auto-Posting:** Approval Step (Dr. 5500, 5510 / Cr. 2140, 2150, 2160, 2170) และ Payment Step (Dr. 2140 / Cr. 1100/1110)
4. **Privacy & Security Guardrails:** พนักงานดาวน์โหลดได้เฉพาะ Payslip ของตนเอง สิทธิ์ `payroll.view` เข้าถึงได้เฉพาะคนในองค์กร

---

### 4.3 การพิจารณาและข้อตกลงร่วมต่อข้อเสนอ DMS ของ GPT ใน gpt.md (Gemini Consensus on GPT DMS Proposal)

Gemini มีข้อสรุป**เห็นชอบร่วมกัน 100%** ใน 6 มิติหลัก:
1. **Roadmap Sequencing:** `Phase 16B (Payroll) -> Phase 17 (Enterprise DMS) -> Phase 18 (Security 2FA)`
2. **Many-to-Many Join Table (`document_links`):** แยกตารางเชื่อมโยงรองรับบทบาท `primary`, `supporting`, `generated`
3. **Private Download Controller:** สตรีมไฟล์ผ่าน Controller พร้อมตรวจสอบ Session, Org, Parent Permission, Sensitivity, และ Scan Status
4. **Sensitivity Taxonomy 5 ระดับ:** `org_internal`, `department_restricted`, `finance_confidential`, `hr_confidential`, `executive_confidential`
5. **Category-Driven Expiry:** เปิดระบบแจ้งเตือนเฉพาะสัญญา, ใบอนุญาต, กรมธรรม์ และเอกสารที่มีวันหมดอายุ
6. **Thai Tax & Legal Retention Policy:** กำหนดเกณฑ์เก็บรักษาเอกสารภาษี/การเงินไม่น้อยกว่า 5-7 ปีตามประมวลรัษฎากร มาตรา 87/3 เป็นแบบ Append-only

---

### 4.4 ผลการตรวจสอบและข้อเสนอแนะ Phase 17: Enterprise Document Management (DMS) & Cross-Module Integration

#### จุดเด่นที่ผ่านการทดสอบและปฏิบัติตามมาตรฐานสถาปัตยกรรม (Strengths & Verified Architecture):
1. **Central Repository & Many-to-Many Linking Data Model:**
   - ตาราง `documents`, `document_versions`, `document_links`, `document_categories`, และ `retention_policies` มี `org_id` กำกับอย่างเข้มงวด
   - `document_links` รองรับการเชื่อมโยง Polymorphic หลายเอนทิตีพร้อมกัน (Customer, Supplier, PO, Deal, Project, Task, Fixed Asset, Bank Account, Expense) พร้อมระบุ Role (`primary`, `supporting`, `generated`)
2. **Immutable Document Versioning & Content Integrity:**
   - เมื่อมีการอัปโหลดเอกสารเวอร์ชันใหม่ (`addVersion`) ระบบจะบันทึกเป็น Record ใหม่ใน `document_versions` พร้อมคำนวณ `checksum_sha256`, บันทึก `version_no` ต่อเนื่อง, `change_note`, และอัปเดต `current_version_id` โดยไม่ลบทับเวอร์ชันเดิม
3. **5-Level Sensitivity RBAC & Private Download Streaming Controller:**
   - การดาวน์โหลดไฟล์ (`DocumentController@download`) ผ่าน Private Controller ที่ตรวจสอบเงื่อนไขความปลอดภัย 4 ชั้น:
     1. Active authenticated session และ `org_id` ตรงกัน
     2. มีสิทธิ์ `documents.download`
     3. ตรวจสอบ Sensitivity Level (เช่น `finance_confidential` เข้าถึงได้เฉพาะ Owner, Admin, Finance; `hr_confidential` / `executive_confidential` เฉพาะ Owner, Admin)
     4. ตรวจสอบสถานะมัลแวร์ (`scan_status === 'clean'`)
4. **Category-Driven Expiry & Automated Notification Scheduler:**
   - คำสั่ง `php artisan documents:check-expiry` ตรวจสอบวันหมดอายุ (`expires_at`) และส่ง In-App Notification แจ้งเตือนผู้รับผิดชอบตามระยะเวลา `renewal_alert_days` เฉพาะ Category ที่เปิดใช้งาน `expiry_tracking_enabled`
5. **Effective-Dated Retention Policy Engine:**
   - ตาราง `retention_policies` กำหนด `minimum_retention_days` และ `legal_hold_required` แบบ Effective-Dated รองรับการจัดเก็บเอกสารภาษี/บัญชีตามกฎหมายไทย (5-7 ปี)
6. **Idempotent Legacy Backfill Tooling:**
   - คำสั่ง `php artisan documents:backfill-legacy` ทำการแปลงไฟล์เดิมจาก `stored_files` เข้าสู่ระบบ DMS กลางพร้อมสร้าง Version และ Link โดยคง `storage_key` เดิมและรองรับการรันซ้ำแบบ Idempotent 100%

#### ข้อเสนอแนะและสิ่งที่ได้รับการตรวจสอบ/ปรับปรุง (Findings & Verified Adjustments):
- **Code Style & Formatting Harmony:**
  - รัน Prettier Formatting จัดระเบียบโค้ดใน `resources/js/Pages/Documents/Index.tsx`
  - รัน Laravel Pint จัดระเบียบโค้ด PHP ใน Models, Controllers, Commands, และ Feature Tests ครบถ้วน
- **Production Readiness Suggestions for DMS:**
  - **Scheduler Cron:** ตรวจสอบให้แน่ใจว่าได้เพิ่ม `schedule:run` บนเซิร์ฟเวอร์เพื่อให้คำสั่ง `documents:check-expiry` ตรวจสอบแจ้งเตือนวันหมดอายุทุกวัน
  - **Malware Scanner Hook:** สถาปัตยกรรมรองรับ `scan_status` (`pending_scan`, `clean`, `infected`) เตรียมพร้อมสำหรับการผูกกับ Antivirus Daemon (เช่น ClamAV) ก่อนปล่อยไฟล์ให้ดาวน์โหลดใน Production

---

## 5. ข้อแนะนำการเตรียมความพร้อมขึ้น Production (Production Deployment & Server Prep)

1. **Background Workers & Scheduler:**
   - **Laravel Scheduler (Cron):** ตั้งค่า `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1` บนเซิร์ฟเวอร์เพื่อให้คำสั่งตรวจเช็กหนี้ แจ้งเตือนอัตโนมัติ คำสั่งตัดค่าเสื่อมราคารายเดือน (`assets:depreciate` ทุกวันที่ 1 เวลา 01:00), คำสั่ง Revaluation/Reversal สิ้นงวด และคำสั่งตรวจวันหมดอายุเอกสาร (`documents:check-expiry`) ทำงานสม่ำเสมอ
   - **Queue Worker (Supervisor/Systemd):** รัน `php artisan queue:work --tries=3` เพื่อให้ระบบส่งอีเมลแจ้งเตือนและคิวส่งเอกสาร e-Tax ทำงานเบื้องหลังโดยไม่บล็อก Web Request
2. **Thai Fonts for DomPDF:**
   - เซิร์ฟเวอร์ Linux Production ต้องติดตั้ง TrueType Fonts ภาษาไทย (เช่น Sarabun หรือ THSarabunNew) ใน `storage/fonts` หรือฝังผ่าน `@font-face` เพื่อป้องกันปัญหาสระลอยหรือภาษาไทยแสดงเป็น `???` ในไฟล์ PDF ทั้ง Invoice, PO, WHT และ Payslip
3. **Uploads & File Storage:**
   - รัน `php artisan storage:link` และตรวจสอบการตั้งค่า `upload_max_filesize` / `post_max_size` (PHP) และ `client_max_body_size` (Nginx) ให้รองรับการอัปโหลดไฟล์แนบ สลิป และเอกสาร DMS
4. **Environment Security & Disaster Recovery:**
   - Production `.env` ต้องตั้งค่า `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
   - ตั้งค่า Automated Daily Database Backup และทดสอบ Restore Drill อย่างน้อย 1 รอบก่อนเปิดใช้งานจริง

---

## 6. แผนงานพัฒนาต่อยอด (Future Roadmap & Architectural Guardrails)

### แผนผังลำดับการพัฒนา Phase 17 - 18:

```
[Phase 17: Enterprise Document Management (DMS)] (Complete / Closed)
       ↓
[Phase 18: Security 2FA / Auth OTP] (2FA Enforcement, Recovery Codes, Security Audit) [พร้อมเริ่มทันที]
```

### รายละเอียดและ Architectural Guardrails รายโมดูล:

1. **Phase 17: Enterprise Document Management (DMS) & Cross-Module Integration (CLOSED):**
   - ศูนย์กลางจัดเก็บเอกสารและสัญญาองค์กรแบบรวมศูนย์ รองรับ `document_links` Many-to-Many, Versioning, Expiration Alert, Category Retention (5-7 ปีตามกฎหมายสรรพากร) และ Sensitivity 5 ระดับ
2. **Phase 18: Security 2FA (READY TO START):**
   - Two-Factor Authentication (2FA / Authenticator OTP) สำหรับบัญชีผู้ดูแล (Owner/Admin) และฝ่ายการเงิน (Finance) พร้อม Recovery Codes

---

## 7. คำแนะนำสำหรับ AI ในการพัฒนาต่อยอด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 1 และ 2 ของ `gemini.md` โดยเด็ดขาด** (Org Isolation, Redaction, Permission Check, Session Invalidation, Payslip Privacy Guard, DMS Private Download Guard)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-17 โดยไม่มี Decision/Checklist ใหม่
   - **อนุญาตและให้ดำเนินการได้:** การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production-Blocking Issue ใน Phase ที่ปิดแล้ว โดยต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality:** รัน `php artisan test`, `npm run build`, `npm run lint`, `npm run check-format`, และ `php vendor/bin/pint --test` ให้ผ่าน 100% ทุกครั้งที่จบ Slice/Phase

