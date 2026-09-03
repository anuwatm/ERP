# Gemini Review & Architecture Reference (Clean)

Last audited: 2026-09-02 (Consensus with GPT in `gpt.md` & Aligned Expansion Roadmap Phase 19 - 28)

Purpose: บันทึกข้อมูลอ้างอิงสถาปัตยกรรม, Security Guardrails, ข้อกำหนดเชิงระบบที่ยังมีผลต่อการพัฒนา (Decisions That Still Matter), ผลการตรวจสอบ Phase 15 - 18, การตรวจประเมินความเข้ากันได้ของข้อมูล Phase 1 - 18 (Cross-Phase Data Compatibility Audit), ข้อสรุปเห็นชอบร่วมต่อข้อเสนอและข้อแก้ไขของ GPT ใน `gpt.md` (Consensus on GPT DMS Design & Phase 19+ Review), ข้อกำหนดการจัดเก็บเอกสารภาษีตามกฎหมายไทย, ผลการตรวจประเมินระบบความปลอดภัย 2FA ใน Phase 18, และแผนงานขยายระบบ ERP ระยะถัดไปที่สอดคล้องกัน 100% (รายละเอียดงานที่ปิดแล้วดูได้ที่ `checklist.md`, `README.md` และ Git history)

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
| **Two-Factor Authentication & Secret Guard** | `two_factor_secret` และ `two_factor_recovery_codes` ถูกเข้ารหัสด้วย Eloquent `encrypted` cast, ซ่อนจาก JSON serialization (`#[Hidden]`), Recovery Codes ถูกแฮชด้วย `Hash::make` และเป็น Single-Use (ทำลายทันทีที่ใช้สำเร็จ), Trusted Device Tokens เก็บเฉพาะ SHA-256 Hash ผูกกับ User-Agent, และการรีเซ็ต 2FA สงวนสิทธิ์เฉพาะ Owner ในองค์กรเดียวกัน |
| **No Cash Balance Guard** | ห้ามสร้าง UI Widget, JSON/Inertia Prop หรือ API เผยแพร่ยอด `Cash Balance` ดิบหรือยอดเงินสดรวมใน Dashboard / Digest จนกว่าจะมี Product Decision และ Bank Reconciliation Boundary ที่ชัดเจน |
| **AI OCR & Automation Guard** | โมดูล AI / OCR สกัดข้อมูลสำหรับร่างเอกสาร (Assisted Draft) พร้อมแนบค่า Confidence และหลักฐานต้นทางเท่านั้น ห้าม Auto-post รายจ่ายหรือบันทึกบัญชี GL โดยปราศจาก Human Review |

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
- **Two-Factor Policy & Privileged Role Enforcement:** นโยบายความปลอดภัย 2FA ควบคุมระดับองค์กรผ่าน `security.two_factor` บังคับให้บทบาทที่มีสิทธิ์เข้าถึงข้อมูลอ่อนไหว (`owner`, `admin`, `finance`) ต้อง Enroll 2FA ก่อนเข้าถึงระบบ โดยมี Middleware `EnsureTwoFactorEnrollment` ควบคุมทุก Web Request พร้อม Rate Limiting ป้องกัน Brute-force

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 18: Complete / Closed**
  - **Core & Financial Foundations (Phase 1 - 7):** Core MVP, CRM, Finance, Delivery, Multi-role Dashboards, Number Sequences, Inclusive VAT, Suppliers & POs.
  - **Documents, Compliance & Treasury (Phase 8 - 10):** Official Print/PDF, Tax Reports/Aging/WHT, Commercial Docs (Quotation, CN/DN, Billing Note, DO, PR, Voucher), Bank Accounts (Encrypted), Bank Reconciliation, Petty Cash, Cheques/PDC.
  - **Accounting, E-Tax & Fixed Assets (Phase 11 - 13):** General Ledger & Double-Entry (COA, Periods, Auto-Posting, Reversal), E-Tax Integration Layer (XML, Hash, RD Prep), Fixed Assets & Monthly Straight-Line Depreciation.
  - **Multi-Currency, AP Subledger & Treasury (Phase 14 - 14.1):** FX Rate Master & Immutable Snapshots, Realized FX, Month-End AR/AP/FCD Revaluation & Auto-Reversal, AP Subledger (`VendorPayment`), FCD Bank Accounts & Two-Sided Transfers, Inventory FX Bridge.
  - **Advanced Inventory & Operations (Phase 15):** Warehouses, Bins, Lots & Expiration, Barcode/QR Scanning, Stock Transfer, Stock Count Differencing, Reorder Point & Low Stock In-App/Email Alerts.
  - **Payroll, Social Security & Tax (Phase 16B):** Employee Payroll Profiles, Effective-Dated Tax & Social Security Policies, Progressive Tax Calculation, Social Security Ceiling Capping, Approval & Auto-GL Posting (`5500`, `5510`, `2140`, `2150`, `2160`, `2170`), Direct Payment Settlement, Payslip PDF Generation & Privacy Guard, ภ.ง.ด. 1 และ Social Security Workpaper CSV Exports.
  - **Enterprise Document Management (Phase 17):** Central Repository, Immutable Versioning (`document_versions`), Many-to-Many Linking (`document_links`), Category-Driven Expiry & Scheduled Alerts (`documents:check-expiry`), 5-Level Sensitivity RBAC, Effective-Dated Retention Policies, Private Download Controller, และ Legacy `StoredFile` Idempotent Backfill (`documents:backfill-legacy`).
  - **Security 2FA & Auth OTP (Phase 18):** RFC 6238 Offline TOTP Engine, Encrypted Secret & Hashed Single-Use Recovery Codes, Org-Level Policy Enforcement (`security.two_factor`), Privileged Role Guard Middleware (`EnsureTwoFactorEnrollment`), SHA-256 Trusted Device Tokens with User-Agent Binding, Owner-Initiated 2FA Reset Flow, Rate Limiting, และ Security Audit Trails.
- **Validation Snapshot:** 246 Tests Passed (1,921 Assertions / 0 Failures), TypeScript/Vite Build Clean, ESLint Clean (0 warnings), Prettier Clean, Laravel Pint Clean (0 issues).

---

## 4. ผลการตรวจสอบและข้อเสนอแนะราย Phase

### 4.1 ผลการตรวจประเมินความเข้ากันได้ของข้อมูล Phase 1 - 18 (Cross-Phase Data Compatibility Audit)

| มิติการตรวจสอบ | ความสอดคล้องเชิงสถาปัตยกรรม (Architectural Compatibility) | สถานะ |
| :--- | :--- | :--- |
| **Multi-Tenant Isolation (Phase 1 - 18)** | ทุก Model และ Query มีการ Scoping ด้วย `org_id` อย่างรัดกุม ป้องกันการเข้าถึงข้อมูลข้ามองค์กร 100% | **ถูกต้อง / สมบูรณ์** |
| **Commercial & Payroll -> GL Chain (Phase 2, 3, 7, 8, 9, 11, 16B)** | เอกสารการค้าและ Payroll ลงบัญชี GL แบบ Double-Entry อัตโนมัติ (Idempotent & Period Locked) | **ถูกต้อง / สมบูรณ์** |
| **Multi-Currency & Base Costing (Phase 14, 14.1, 15)** | `StockMovement` ยึดถือ `base_unit_cost` และ `base_total_cost` (THB) เป็น Single Source of Truth ทั้งใน GRN, Stock Transfer, Stock Adjustment, และ Stock Count | **ถูกต้อง / สมบูรณ์** |
| **AP / Inventory / Payroll Boundaries (Phase 7, 14.1, 15, 16B)** | GRN บันทึกสต็อกเข้า `1140` คู่บัญชีพัก `1150 (GRNI)`, Expense บันทึกตั้งหนี้ AP `2110`, Payroll บันทึกหนี้เงินเดือน `2140` แยกกันชัดเจน | **ถูกต้อง / สมบูรณ์** |
| **DMS Cross-Module Integration (Phase 17)** | ตาราง `document_links` รองรับการผูกเอกสารร่วมกับ Customer, Supplier, PO, Deal, Project, Fixed Asset, Bank Account และ Expense อย่างยืดหยุ่น | **ถูกต้อง / สมบูรณ์** |
| **Download Security & Privacy (Phase 3, 10, 16B, 17)** | Private Controller สำหรับ Voucher proof, Payslip PDF และ DMS Documents ตรวจสอบ Session/Role/Owner/MIME/Scan-status ทุก Request | **ถูกต้อง / สมบูรณ์** |
| **Privileged 2FA & Auth Security (Phase 18)** | 2FA Policy Engine ตรวจสอบบทบาท `owner`/`admin`/`finance` และบังคับ Setup ก่อนเข้าถึง Route อื่นๆ พร้อมระบบ Trusted Device ปลอดภัย | **ถูกต้อง / สมบูรณ์** |

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

---

### 4.5 ผลการตรวจสอบและข้อเสนอแนะ Phase 18: Security 2FA / Auth OTP

#### จุดเด่นที่ผ่านการทดสอบและปฏิบัติตามมาตรฐานสถาปัตยกรรม (Strengths & Verified Architecture):
1. **Offline Zero-Dependency RFC 6238 TOTP Engine (`TotpService`):**
   - พัฒนา TOTP Service ภายในตัวโดยไม่ต้องพึ่งพา 3rd-party library ภายนอก รองรับการคำนวณ HMAC-SHA1 ตาม RFC 6238, Drift Window +/- 1 (รองรับ Clock Skew 30 วินาที), รหัส 6 หลัก, Base32 Secret Generator (32 ตัวอักษร), และสร้าง standard `otpauth://totp/...` URI สำหรับสแกนผ่าน Google Authenticator / Microsoft Authenticator / Apple Passwords ได้ทันที
2. **Encrypted Storage & Hashed Single-Use Recovery Codes:**
   - ฟิลด์ `two_factor_secret` จัดเก็บด้วย Eloquent `encrypted` cast
   - ฟิลด์ `two_factor_recovery_codes` จัดเก็บด้วย `encrypted:array` โดยแต่ละชุดรหัส Recovery Code ถูกผ่านการ Hash ด้วย `Hash::make` เมื่อใช้งานรหัสใดไปแล้ว ระบบจะลบรหัสนั้นออกจาก Array ทันที (`consumeRecoveryCode`) ป้องกันการใช้ซ้ำ 100%
   - ทั้ง 2 ฟิลด์ถูกบรรจุใน `#[Hidden]` attribute ของ Model `User` ป้องกันการ Leak ผ่าน API / JSON Response
3. **Organization-Level Policy Engine (`TwoFactorPolicyService`):**
   - จัดเก็บนโยบายในตาราง `settings` ภายใต้คีย์ `security.two_factor` รองรับการปรับแต่ง:
     - `enabled`: สวิตช์เปิด/ปิดระดับองค์กร
     - `required_for_privileged_roles`: บังคับเปิด 2FA สำหรับบทบาท `owner`, `admin`, `finance`
     - `allow_trusted_devices`: อนุญาตให้จดจำอุปกรณ์
     - `trusted_device_days`: อายุการจดจำอุปกรณ์ (1 - 90 วัน ค่าเริ่มต้น 30 วัน)
   - หน้าจอการตั้งค่าองค์กร (`Settings/Organization.tsx`) มี UI จัดการ Security Policy พร้อมระบบยืนยันรหัสผ่าน (`password.confirm`) และบันทึก Audit Log การเปลี่ยนแปลงนโยบาย
4. **Privileged Route Guard Middleware (`EnsureTwoFactorEnrollment`):**
   - Middleware ตรวจจับทุก Request ใน Web Group หากผู้ใช้ถือ Privileged Role แต่ยังไม่ได้ Enroll/ยืนยัน 2FA ระบบจะดักจับและบังคับ Redirect ไปยังหน้า `two-factor.setup` ทันที โดยเปิดข้อยกเว้นเฉพาะ Route ที่จำเป็น (`two-factor.*`, `password.*`, `logout`, `verification.*`)
5. **Secure Challenge Flow & SHA-256 Trusted Device Tokens:**
   - ระหว่าง Login เมื่อผ่านด่าน Password แล้ว หากผู้ใช้เปิด 2FA ระบบจะ Log Out ออกทันทีและจำลอง Pending State ผ่าน Session `two_factor_pending_user_id` เพื่อเข้าสู่หน้า Challenge
   - ระบบ Trusted Device ใช้ Token สุ่ม 80 ตัวอักษร จัดเก็บเฉพาะ `token_hash` (SHA-256) และ `user_agent_hash` ลงในตาราง `two_factor_trusted_devices` พร้อมผูก Secure HttpOnly Lax Cookie
6. **Owner-Initiated 2FA Reset Flow:**
   - รองรับกรณีผู้ใช้ทำอุปกรณ์ Authenticator และ Recovery Code หาย โดย Owner ขององค์กรสามารถสั่ง Reset 2FA ให้ผู้ใช้ในองค์กรเดียวกันได้ที่ `POST users/{user}/two-factor/reset` (บังคับยืนยันรหัสผ่าน Owner และจำกัด Rate Limit) พร้อมทำลาย Trusted Devices เก่าทิ้งทั้งหมดและบันทึก Audit Log
7. **Rate Limiting & Anti-Brute-Force Guard:**
   - ป้องกันการ Brute-force รหัส TOTP ด้วย Laravel `RateLimiter` (จำกัด 5 ครั้งต่อนาทีต่อ IP/User) ทั้งในระดับ Route Middleware (`throttle:5,1`) และใน Controller Logic

#### ข้อเสนอแนะเพื่อความสมบูรณ์แบบและการปรับปรุงในอนาคต (Findings & Polish Recommendations):
1. **Recovery Codes Display Visibility in UI:**
   - ปัจจุบันใน `TwoFactorController@confirmSetup` มีการส่ง `$codes` ผ่าน `session()->flash('two_factor_recovery_codes', $codes)` แต่ใน `HandleInertiaRequests.php` ยังไม่ได้นำคีย์นี้มารวมใน flash props ส่งผลให้หน้าจอ Profile ยังไม่แสดงกล่อง Recovery Codes ให้ผู้ใช้คัดลอกจัดเก็บหลัง Setup เสร็จ แนะนำให้เพิ่มการส่ง Flash Prop หรือทำ Dedicated Modal/Page สำหรับแสดง Recovery Codes ให้ผู้ใช้กด "บันทึก/พิมพ์" รหัสกู้คืน
2. **Form Validation Error Experience in Setup:**
   - ใน `TwoFactorController@confirmSetup` มีการใช้ `abort_unless(..., 422)` ซึ่งจะแสดงเป็นหน้าจอ Error 422 ของเว็บ แนะนำให้ปรับเป็น `throw ValidationException::withMessages(['code' => 'Invalid authenticator code.'])` หรือ `back()->withErrors(...)` เพื่อให้ข้อผิดพลาดแสดงผลสวยงามใต้ช่อง Input ใน React Form
3. **Session Secret Lifecycle on Failed Verification:**
   - ใน `confirmSetup` มีการใช้ `$request->session()->pull('two_factor_setup_secret')` ซึ่งจะลบ Secret ออกจาก Session ทันที หากผู้ใช้กรอกตัวเลขผิดครั้งแรก Secret จะหายไป ทำให้ต้องเริ่มสร้าง Secret ใหม่ แนะนำให้ใช้ `session()->get(...)` ระหว่างตรวจสอบ และสั่ง `session()->forget(...)` เมื่อยืนยันสำเร็จเท่านั้น
4. **Dynamic Trusted Device Duration on Challenge Screen:**
   - ในหน้าจอ `TwoFactorChallenge.tsx` ข้อความเช็กบ็อกซ์ระบุตัวเลขตายตัวเป็น "Trust this device for 30 days" ในอนาคตสามารถส่ง Policy Prop จาก Backend เพื่อแสดงจำนวนวันจริงตามการตั้งค่าขององค์กร (`trusted_device_days`) และซ่อน Checkbox อัตโนมัติหากองค์กรปิด `allow_trusted_devices`

---

### 4.6 ข้อสรุปเห็นชอบร่วมต่อข้อเสนอแนะและข้อแก้ไขของ GPT ใน `gpt.md` (Gemini Consensus on GPT Review & Guardrails)

Gemini มีข้อสรุป**เห็นชอบร่วมกัน 100%** กับข้อสังเกต ข้อแก้ไข และการปรับลำดับของ GPT ใน `gpt.md` ดังนี้:

1. **การปรับลำดับ Tier 1 (Approval Engine ก่อน Notifications):**
   - เห็นชอบ 100% ให้ลำดับเป็น `Phase 19: HR Core & Leave -> Phase 20: Dynamic Approval Workflow Engine -> Phase 21: Operational Notifications & Outbox` เพื่อให้ระบบมี State Machine, Approver Snapshot, และ Audit Trail กลางที่สมบูรณ์ก่อน แล้วจึงเชื่อมต่อ Outbound Webhook/Notification ส่งแจ้งเตือน ซึ่งป้องกันการผูก Logic เฉพาะกิจที่ซ้ำซ้อนในแต่ละโมดูล
2. **การตัด Cash Balance ออกจาก Daily Digest:**
   - เห็นชอบ 100% ให้ตัดการส่งค่า `Cash Balance` ออกจาก Daily Digest / BI รายวัน เพื่อรักษา Guardrail เดิมที่กำหนดไว้ (เนื่องจากยังไม่มี Bank Reconciliation Boundary เต็มรูปแบบ) โดย Digest จะเน้นรายงานยอด AR/AP Aging, Due Items, ยอดขาย, ค่าใช้จ่าย, Low Stock และ Overdue Tasks
3. **Attendance Privacy & Consent Guard:**
   - เห็นชอบ 100% ให้การตรวจสอบ GPS/IP Range เป็นการตั้งค่าแบบ Optional (Opt-in per Org) พร้อมระบุ Purpose, Consent และ Data Retention อย่างเคร่งครัด ไม่บังคับเป็น Mandatory Default เพื่อคุ้มครองข้อมูลส่วนบุคคล (PDPA/PII)
4. **Payroll Cutoff & Locked Summary Bridge:**
   - เห็นชอบ 100% ว่า Payroll Bridge ต้องส่งเฉพาะ Approved Summary ที่ผ่าน Cutoff Date และมีกลไก Correction/Reversal รองรับ โดยห้าม Auto-post เงินเดือน/GL โดยปราศจาก Payroll Period Lock
5. **Customer & Supplier Portal Isolation:**
   - เห็นชอบ 100% ว่า Portals ต้องใช้ External Identity แยกจาก Staff Session, Scoped Permissions, Expiry/Revocation, Rate Limit และห้ามใช้ Public Document URLs
6. **Payment Gateway & Direct e-Tax Preconditions:**
   - เห็นชอบ 100% ว่า Webhook ต้องมี Signature Verification, Idempotency, และ Reconcile กับ Invoice ให้สมบูรณ์ก่อนบันทึกบัญชี GL และ e-Tax Direct API ต้องผ่านการทดสอบ Certification และจัดการ Private Key ผ่าน HSM/Key Vault เท่านั้น
7. **AI OCR Human-in-the-loop:**
   - เห็นชอบ 100% ว่า OCR มีบทบาทเป็น Assisted Draft พร้อมแสดง Confidence และรูปต้นฉบับ โดยต้องผ่านการตรวจสอบและยืนยันจากผู้ใช้ (Human Review) ก่อนแปลงเป็น Expense/PO จริงเสมอ
8. **BOM & POS เป็น Optional Vertical Tracks:**
   - เห็นชอบ 100% ให้แยกโมดูล Manufacturing (BOM) และ POS ออกเป็น Product Tracks เฉพาะทางเพื่อพัฒนาเมื่อมีความต้องการทางธุรกิจจริง
9. **DMS & Phase 18 Polish Verification:**
   - เห็นชอบกับการตรวจสอบและปรับปรุงสิทธิ์ Parent Permission บน `document_links`, การคำนวณ `retention_until` ตามกฎหมายภาษี, และการขัดเกลา UX ของ 2FA Recovery Codes ให้สมบูรณ์แบบก่อนเปิดใช้งาน Production

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
   - ตรวจสอบ `APP_KEY` ให้คงที่เสมอ เนื่องจากการเข้ารหัส `two_factor_secret`, `two_factor_recovery_codes`, และ Bank Accounts พึ่งพาคีย์นี้ หาก `APP_KEY` เปลี่ยนจะทำให้ถอดรหัสข้อมูลเดิมไม่ได้
   - ตั้งค่า Automated Daily Database Backup และทดสอบ Restore Drill อย่างน้อย 1 รอบก่อนเปิดใช้งานจริง

---

### 4.7 ความเห็นพ้องร่วมต่อข้อเสนอปรับปรุง Checklist ของ GPT (Consensus on GPT Section 8 Checklist Review)

Gemini เห็นพ้อง 100% กับข้อเสนอของ GPT ในหมวดที่ 8 ของ `gpt.md` ดังนี้:

1. **DMS Security & Compliance Remediation (Phase 18.1):** เพิ่ม Phase 18.1 เพื่อปิดช่องว่าง Parent-permission Authorization ตอน Download / Link creation และระบบคำนวณ/บังคับใช้ Retention Policy (`retention_until`, `legal_hold`, category renewal) พร้อม Regression Tests ให้สมบูรณ์ก่อนขึ้น Feature Phase ใหม่
2. **Phase 19 ห้ามทำ Bespoke Approval:** ใน Phase 19 ให้ทำเฉพาะ Profile, Schedule, Attendance, Leave Balance และ Draft/Submit Form เท่านั้น ส่วน Manager Multi-level Approval Dashboard, Delegation, และ SoD ให้ย้ายไปทำใน **Phase 20 (Central Dynamic Approval Engine)** เพื่อป้องกันการสร้าง State Machine ซ้ำซ้อน
3. **Phase 18 Policy & UX Polish:** ยืนยัน 2FA นโยบายเริ่มต้นเป็น `enabled = false` ต่อองค์กร และบังคับเฉพาะ privileged roles เมื่อเปิดใช้งาน พร้อมเก็บตก UX Recovery Code Flash, Validation under input, Secret session lifecycle และ Dynamic trusted device UI
4. **Phase 22 Vendor Portal Upload Security:** เพิ่ม File scan state (malware/quarantine), MIME/size limits, DMS audit และ Human review gate ก่อนสร้าง AP draft
5. **Phase 23 Gateway Posting Guard:** บังคับให้มี Settlement status verification ก่อนสร้าง Payment และบันทึกบัญชี Double-entry GL
6. **Phase 28 POS Boundary Guard:** การกระทบยอดเงินในกะ (Shift Reconciliation) จำกัดอยู่เฉพาะในขอบเขต POS เท่านั้น และห้ามส่งต่อเป็น Organization-wide Cash Balance
7. **Production Prep Deployment Gates:** เพิ่ม Migration rollback plan, Deploy smoke test checklist, Failed-job replay runbook, Log redaction review, และ Restore verification drill

---

## 6. แผนงานพัฒนาต่อยอด (Future Roadmap & Architectural Guardrails)

### แผนผังลำดับการพัฒนาที่เห็นชอบร่วมกัน (Consensus Roadmap Phase 1 - 28):

```
[Phase 1 - 18: Core, Financials, Treasury, Inventory, Payroll, DMS & 2FA] (Closed)
       ↓
[Phase 18 Polish & Phase 18.1: DMS Security & Compliance Remediation]
       ↓
[Phase 19: HR Core, Attendance & Leave Foundation] (No bespoke approval; Draft/Submit & Balances only)
       ↓
[Phase 20: Dynamic Approval Workflow Engine] (Thresholds, Delegation, Leave/PR/PO/Expense Chains)
       ↓
[Phase 21: Operational Notifications & Outbox] (LINE OA / Slack / Telegram, Quiet Hours, No Cash Balance)
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

### สรุปสถานะโครงการ:
- **Phase 1 ถึง Phase 18:** เสร็จสมบูรณ์แล้วทุก Phase (100% Complete)
- **Phase 18 Polish & Phase 18.1 Remediation:** วางแผนเพื่อปิดช่องว่างความปลอดภัยและความสอดคล้องทางกฎหมายก่อนเริ่ม Phase 19
- ระบบผ่านการทดสอบ Regression ครอบคลุมทั้ง Backend (246 Feature Tests Passed, 1,921 Assertions) และ Frontend (TypeScript Compile, ESLint, Prettier, Pint Clean)

---

## 7. คำแนะนำสำหรับ AI ในการพัฒนาต่อยอด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 1 และ 2 ของ `gemini.md` โดยเด็ดขาด** (Org Isolation, Redaction, Permission Check, Session Invalidation, Payslip Privacy Guard, DMS Private Download Guard, 2FA Encrypted Secret Guard, No Cash Balance Guard, AI OCR Human-in-the-Loop)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-18 โดยไม่มี Decision/Checklist ใหม่
   - **อนุญาตและให้ดำเนินการได้:** การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production-Blocking Issue ใน Phase ที่ปิดแล้ว โดยต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality:** รัน `php artisan test`, `npm run build`, `npm run lint`, `npm run check-format`, และ `php vendor/bin/pint --test` ให้ผ่าน 100% ทุกครั้งที่จบ Slice/Phase
