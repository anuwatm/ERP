# Gemini Review & Architecture Reference (Clean)

Last audited: 2026-09-08 (Consensus with GPT in `gpt.md` Section 8-9 & Phase 18.1 - 20 Audit & Remediation Gate)

Purpose: บันทึกข้อมูลอ้างอิงสถาปัตยกรรม, Security Guardrails, ข้อกำหนดเชิงระบบที่ยังมีผลต่อการพัฒนา (Decisions That Still Matter), ผลการตรวจสอบ Phase 15 - 20, การตรวจประเมินความเข้ากันได้ของข้อมูล Phase 1 - 20 (Cross-Phase Data Compatibility Audit), ข้อสรุปเห็นชอบร่วมต่อข้อเสนอและข้อแก้ไขของ GPT ใน `gpt.md`, ผลการตรวจสอบ Phase 18.1 (DMS Security & Compliance Remediation), ผลการตรวจสอบ Phase 19 (HR Core, Attendance & Leave Foundation), ผลการตรวจสอบและประเมินเชิงลึก Phase 20 (Dynamic Approval Workflow Engine), ข้อสังเกตและข้อเสนอแนะเพิ่มเติม, และแผนงานขยายระบบ ERP ระยะถัดไปที่สอดคล้องกัน 100% (รายละเอียดงานที่ปิดแล้วดูได้ที่ `checklist.md`, `README.md` และ Git history)

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
| **Attendance Privacy & Consent Guard** | การจัดเก็บ IP และ GPS ในการลงเวลาทำงานต้องเป็นไปตามหลักความยินยอม (Opt-in Consent) ภายใต้ `hr.attendance_privacy` โดยค่าเริ่มต้นคือ `capture_ip=false`, `capture_gps=false`, และ `require_employee_consent=true` หากเปิดการบันทึก IP ระบบจะจัดเก็บเฉพาะ SHA-256 Hash และห้ามจัดเก็บ Raw IP เด็ดขาด |
| **No Cash Balance Guard** | ห้ามสร้าง UI Widget, JSON/Inertia Prop หรือ API เผยแพร่ยอด `Cash Balance` ดิบหรือยอดเงินสดรวมใน Dashboard / Digest จนกว่าจะมี Product Decision และ Bank Reconciliation Boundary ที่ชัดเจน |
| **AI OCR & Automation Guard** | โมดูล AI / OCR สกัดข้อมูลสำหรับร่างเอกสาร (Assisted Draft) พร้อมแนบค่า Confidence และหลักฐานต้นทางเท่านั้น ห้าม Auto-post รายจ่ายหรือบันทึกบัญชี GL โดยปราศจาก Human Review |
| **Workflow SoD & Immutable Snapshot Guard** | ห้ามผู้ขออนุมัติ (Requester) อนุมัติตนเองทุกกรณี (Strict SoD) โดยการตรวจสอบระบบจะบล็อกทั้งในระดับ Assignee Snapshot และ Action Evaluation พร้อมจับภาพ Approver Chain ณ วันยื่นคำขอเพื่อป้องกันผลกระทบจากการปรับผังองค์กรย้อนหลัง |

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
- **DMS Parent-Permission & Link Authorization:** การเข้าถึง ดาวน์โหลด หรือสร้าง `document_links` ผูกกับเอนทิตีภายนอก (Customer, Supplier, PO, Deal, Project, Task, Fixed Asset, Bank Account, Expense) ต้องผ่านการตรวจสอบสิทธิ์ Parent Entity Permission และ Record Scope เสมอ
- **Two-Factor Policy & Privileged Role Enforcement:** นโยบายความปลอดภัย 2FA ควบคุมระดับองค์กรผ่าน `security.two_factor` บังคับให้บทบาทที่มีสิทธิ์เข้าถึงข้อมูลอ่อนไหว (`owner`, `admin`, `finance`) ต้อง Enroll 2FA ก่อนเข้าถึงระบบ โดยมี Middleware `EnsureTwoFactorEnrollment` ควบคุมทุก Web Request พร้อม Rate Limiting ป้องกัน Brute-force
- **HR Attendance, Leave & Payroll Bridge Boundary:** บันทึกเวลาและคำนวณวันลาตัดวันหยุด/เสาร์-อาทิตย์ พร้อมตัด/คืนวันลาแบบ Atomic Transaction (`lockForUpdate`), Attendance Summary รองรับ Cutoff Date และมีสถานะ `draft`, `locked`, `reversed` (ด้วย `reversal_of_id`) โดย**ห้ามบันทึกอัตโนมัติ (No Auto-Posting)** ไปยัง `payroll_runs`, `payroll_items`, หรือ General Ledger ก่อนได้รับการอนุมัติและ Lock รอบบัญชีเงินเดือน
- **Central Dynamic Approval Engine & SoD Boundary:** ระบบอนุมัติเอกสารรวมศูนย์ (`WorkflowEngineService`) จัดเก็บประวัติขั้นตอนเป็น Snapshot แบบคงที่ (Immutable Snapshot) บังคับใช้ Segregation of Duties (ห้ามผู้อนุมัติตนเอง) และตรวจสอบสิทธิ์แบบ Multi-tenant ทุกขั้นตอน โดยการอนุมัติรายจ่ายต้องเชื่อมต่อเข้ากับ Double-entry GL เสมอ

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 20: Complete / Closed**
  - **Core & Financial Foundations (Phase 1 - 7):** Core MVP, CRM, Finance, Delivery, Multi-role Dashboards, Number Sequences, Inclusive VAT, Suppliers & POs.
  - **Documents, Compliance & Treasury (Phase 8 - 10):** Official Print/PDF, Tax Reports/Aging/WHT, Commercial Docs (Quotation, CN/DN, Billing Note, DO, PR, Voucher), Bank Accounts (Encrypted), Bank Reconciliation, Petty Cash, Cheques/PDC.
  - **Accounting, E-Tax & Fixed Assets (Phase 11 - 13):** General Ledger & Double-Entry (COA, Periods, Auto-Posting, Reversal), E-Tax Integration Layer (XML, Hash, RD Prep), Fixed Assets & Monthly Straight-Line Depreciation.
  - **Multi-Currency, AP Subledger & Treasury (Phase 14 - 14.1):** FX Rate Master & Immutable Snapshots, Realized FX, Month-End AR/AP/FCD Revaluation & Auto-Reversal, AP Subledger (`VendorPayment`), FCD Bank Accounts & Two-Sided Transfers, Inventory FX Bridge.
  - **Advanced Inventory & Operations (Phase 15):** Warehouses, Bins, Lots & Expiration, Barcode/QR Scanning, Stock Transfer, Stock Count Differencing, Reorder Point & Low Stock In-App/Email Alerts.
  - **Payroll, Social Security & Tax (Phase 16B):** Employee Payroll Profiles, Effective-Dated Tax & Social Security Policies, Progressive Tax Calculation, Social Security Ceiling Capping, Approval & Auto-GL Posting (`5500`, `5510`, `2140`, `2150`, `2160`, `2170`), Direct Payment Settlement, Payslip PDF Generation & Privacy Guard, ภ.ง.ด. 1 และ Social Security Workpaper CSV Exports.
  - **Enterprise Document Management (Phase 17):** Central Repository, Immutable Versioning (`document_versions`), Many-to-Many Linking (`document_links`), Category-Driven Expiry & Scheduled Alerts (`documents:check-expiry`), 5-Level Sensitivity RBAC, Effective-Dated Retention Policies, Private Download Controller, และ Legacy `StoredFile` Idempotent Backfill (`documents:backfill-legacy`).
  - **Security 2FA & Auth OTP (Phase 18):** RFC 6238 Offline TOTP Engine, Encrypted Secret & Hashed Single-Use Recovery Codes, Org-Level Policy Enforcement (`security.two_factor`), Privileged Role Guard Middleware (`EnsureTwoFactorEnrollment`), SHA-256 Trusted Device Tokens with User-Agent Binding, Owner-Initiated 2FA Reset Flow, Rate Limiting, และ Security Audit Trails.
  - **DMS Security & Compliance Remediation (Phase 18.1):** Parent-Permission Authorization บนการดาวน์โหลดและการผูก `document_links` ข้าม 9 เอนทิตี (Customer, Supplier, PO, Deal, Project, Task, Fixed Asset, Bank Account, Expense), ระบบคำนวณวันสิ้นสุดอายุจัดเก็บ (`retention_until`) อัตโนมัติตามประเภทเอกสารและหมวดหมู่, Legal Hold Protection บล็อกการลบ/ทำลายเอกสารระหว่างคดี/ตรวจสอบภาษี, Scanner Malware Quarantine สำหรับไฟล์เสี่ยง, และคำสั่งปลอดภัย `documents:enforce-retention [--purge]` สำหรับการปรับสถานะเป็น `archived` และลบไฟล์จัดเก็บจริงเฉพาะที่ไม่มีการอ้างอิงเหลืออยู่
  - **HR Core, Attendance & Leave Foundation (Phase 19):** ตารางโครงสร้าง HR 8 ตาราง (`employee_shifts`, `employee_work_profiles`, `holidays`, `attendances`, `leave_types`, `leave_balances`, `leave_requests`, `attendance_summaries`), การบันทึกเวลาเข้า-ออกงานที่เคารพสิทธิความเป็นส่วนตัว (Opt-in Consent Guard, SHA-256 Hashed IP, Optional GPS), ระบบคำนวณวันลาตัดวันหยุดและเสาร์-อาทิตย์, กลไกหักและคืนวันลาแบบ Atomic Transaction (`lockForUpdate`), Attendance Summary Bridge ที่มี Cutoff และรองรับ Correction/Reversal แบบ Immutable โดยไม่ Auto-post ไปยัง Payroll Run หรือบันทึกบัญชี GL เด็ดขาด (คงไว้เพื่อส่งต่อให้ Phase 20 Central Workflow Engine)
  - **Dynamic Approval Workflow Engine (Phase 20):** ระบบสายการอนุมัติกลาง (`workflow_definitions`, `workflow_steps`, `workflow_instances`, `workflow_approvals`, `workflow_delegations`), Immutable Snapshot ประจำเอกสาร, Threshold Evaluation, SoD Deadlock Guard, Execution Mode (Sequential/Parallel First-to-Approve), Delegation Resolution ใน Inbox, Multi-Level Workflow Builder UI สูงสุด 10 ขั้นตอน, การเชื่อมโยงปุ่ม Submit จากโมดูลต้นทาง (Expenses, Purchase Orders, Commercial Documents PR, Leave Requests), การคืนยอดวันลาเมื่อ Reject, และการบันทึกบัญชี Double-entry GL อัตโนมัติเมื่อ Expense ผ่านการอนุมัติขั้นสุดท้าย
- **Validation Snapshot:** 257 Tests Passed (1,968 Assertions / 0 Failures), TypeScript/Vite Build Clean, ESLint Clean (0 warnings / 0 errors), Prettier Clean, Laravel Pint Clean (0 issues).

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

### 4.8 ผลการตรวจสอบและข้อเสนอแนะ Phase 18.1: DMS Security & Compliance Remediation

#### จุดเด่นที่ผ่านการทดสอบและสอดคล้องตามมาตรฐานความปลอดภัย (Strengths & Verified Architecture):
1. **Parent-Permission Authorization Engine (`DocumentParentAccessService`):**
   - **Download Authorization (`assertParentAccess`):** เมื่อมีการดาวน์โหลดเอกสาร ระบบจะค้นหา `document_links` ทั้งหมดที่ผูกกับเอกสารนั้น และทำการตรวจสอบสิทธิ์ของผู้ใช้เทียบกับเอนทิตีแม่ (Parent Entity) ครอบคลุมทั้ง 9 โมดูล:
     - `Customer` (ตรวจ `customers.view` และ sales rep scope)
     - `Supplier` (ตรวจ `suppliers.view`)
     - `PurchaseOrder` (ตรวจ `purchase_orders.view`)
     - `Deal` (ตรวจ `deals.view` และ deal owner scope)
     - `Project` (ตรวจ `projects.view` และ member scope)
     - `Task` (ตรวจ `tasks.view` และ task assignee scope)
     - `FixedAsset` (ตรวจ `fixed_assets.view`)
     - `BankAccount` (ตรวจ `bank_accounts.view`)
     - `Expense` (ตรวจ `expenses.view`)
   - **Link Creation Authorization (`assertLinkCreationAccess`):** การสร้างความสัมพันธ์ Many-to-Many ใหม่ผ่าน `document_links` บังคับให้ผู้ใช้ต้องมีสิทธิ์แก้ไข/จัดการในเอนทิตีแม่ เช่น `purchase_orders.edit`, `expenses.edit` ป้องกันการลักลอบผูกเอกสารลับเข้ากับเอนทิตีที่ตนไม่มีสิทธิ์
   - **Graceful Standalone Fallback:** กรณีเอกสารส่วนกลางที่ยังไม่ได้ผูกกับเอนทิตีแม่ใดๆ ระบบจะตรวจสอบสิทธิ์ `documents.download` และบังคับใช้ 5-Level Sensitivity RBAC อย่างเคร่งครัด
2. **Automated Thai Legal Retention & Legal Hold Engine (`DocumentRetentionService`):**
   - **Dynamic Retention Calculation:** เมื่อสร้างหรือจัดหมวดหมู่เอกสาร ระบบจะคำนวณ `retention_until` อัตโนมัติจากนโยบายในตาราง `retention_policies` (อิงตามประมวลรัษฎากรและ พ.ร.บ. บัญชี 5-7 ปี)
   - **Legal Hold Protection:** ฟิลด์ `legal_hold` (boolean) มีผลยับยั้งการทำลายหรือลบไฟล์โดยสิ้นเชิง ตราบใดที่เอกสารอยู่ในระหว่างกระบวนการไต่สวนทางกฎหมาย หรือรอการตรวจสอบภาษี
   - **Scanner Quarantine Guard:** ตรวจจับสถานะความปลอดภัยของไฟล์ หากผลสแกนมัลแวร์ล้มเหลว (`scan_status === 'infected'`) ระบบจะระงับการเข้าถึงและแยกไฟล์เข้าสู่ Quarantine ทันที
3. **Automated Enforcement Artisan Command (`documents:enforce-retention`):**
   - คำสั่ง `php artisan documents:enforce-retention [--purge]` ทำการกวาดเอกสารที่เกินกำหนด `retention_until` และไม่มี `legal_hold` เพื่อปรับสถานะเป็น `archived`
   - เมื่อระบุแฟล็ก `--purge` ระบบจะลบไฟล์ต้นฉบับออกจาก Physical Storage เฉพาะเมื่อไม่มีเวอร์ชันอื่นหรือเอกสารอื่นอ้างอิงถึง `storage_key` นั้นอยู่อีกต่อไป
4. **Test Verification:** ผ่านการทดสอบ `Phase181DocumentComplianceTest.php` ทั้ง 4 tests (12 assertions) 100%

---

### 4.9 ผลการตรวจสอบและข้อเสนอแนะ Phase 19: HR Core, Attendance & Leave Foundation

#### จุดเด่นที่ผ่านการทดสอบและปฏิบัติตามมาตรฐานสถาปัตยกรรม (Strengths & Verified Architecture):
1. **Domain Model & Schema Isolation:**
   - Migration `2026_09_07_000001_create_phase19_hr_tables.php` สร้างตาราง HR 8 ตารางอย่างถูกต้อง พร้อม Scoping `org_id` ทุกตาราง:
     - `employee_shifts`: กะการทำงาน (เวลาเริ่ม, สิ้นสุด, พัก, และ grace period นาที)
     - `employee_work_profiles`: โปรไฟล์พนักงาน รหัสพนักงาน สายการบังคับบัญชา (`manager_user_id`)
     - `holidays`: ปฏิทินวันหยุดนักขัตฤกษ์/วันหยุดบริษัท
     - `attendances`: ประวัติการลงเวลาเข้า-ออกงาน
     - `leave_types`: ประเภทการลา (ลาป่วย, ลากิจ, ลาพักร้อน, ลาไม่รับค่าจ้าง)
     - `leave_balances`: ยอดวันลาคงเหลือแยกตามปี/พนักงาน/ประเภทการลา
     - `leave_requests`: คำขอลาหยุดงาน
     - `attendance_summaries`: สรุปเวลาทำงานเพื่อส่งต่อฝ่ายบัญชี/เงินเดือน
2. **Strict Compliance with `gpt.md`: ห้ามสร้าง Bespoke Approval Engine ใน Phase 19:**
   - สถานะของ `leave_requests` จำกัดไว้เพียง 3 สถานะที่จำเป็นต่อรากฐาน: `draft`, `submitted`, `cancelled`
   - **ไม่มีการสร้าง bespoke approval logic, approval buttons, delegation หรือ SoD เฉพาะกิจใน Phase 19** โดยแยกโครงสร้างไว้อย่างถูกต้อง เพื่อรอเชื่อมต่อเข้ากับ **Phase 20 (Central Dynamic Approval Workflow Engine)**
3. **Privacy & PII Protection (PDPA Compliance):**
   - ตั้งค่านโยบายระดับองค์กรใน `settings` ภายใต้ `hr.attendance_privacy` โดยค่าเริ่มต้นคือ `capture_ip=false`, `capture_gps=false`, และ `require_employee_consent=true`
   - หากเปิดใช้งานการบันทึก IP ระบบจะจัดเก็บเฉพาะ **SHA-256 Hash** (`hash('sha256', $ip)`) **ห้ามจัดเก็บ Raw IP ในฐานข้อมูลและ Audit Log เด็ดขาด**
   - พิกัด GPS เป็นการกรอกแบบทางเลือก (Optional) และระบบบล็อกการลงเวลาทันทีหากองค์กรกำหนดให้ต้องยินยอม แต่ผู้ใช้ไม่ได้ให้ Consent
4. **Leave Balance Transactional Integrity:**
   - การคำนวณวันลา (`businessDays`) ข้ามวันหยุดเสาร์-อาทิตย์และวันหยุดนักขัตฤกษ์ในตาราง `holidays` อย่างแม่นยำ
   - การตัดยอดวันลาตอนยื่นขอลา (`submitLeave`) และการคืนยอดวันลาตอนยกเลิกคำขอ (`cancelLeave`) ทำงานภายใต้ Database Transaction พร้อมคำสั่ง `lockForUpdate()` ป้องกันปัญหา Race Condition
   - ป้องกันการยื่นคำขอลาซ้ำซ้อนในช่วงวันเดียวกัน (Overlap Detection)
5. **Strict Payroll & Accounting Boundary (No Auto-Posting Guard):**
   - `attendance_summaries` ทำหน้าที่สรุปชั่วโมงทำงานจริง, OT, วันลาจ่ายค่าจ้าง และวันลาไม่จ่ายค่าจ้าง (LWOP) พร้อม Cutoff Date
   - วงจรสถานะของ Summary คือ `draft`, `locked`, และ `reversed` (สร้างเรคคอร์ดทดแทนพร้อมอ้างอิง `reversal_of_id`)
   - **ไม่มีการ Auto-post หรือสร้างข้อมูลใน `payroll_runs`, `payroll_items`, หรือ General Ledger entries เด็ดขาด** รักษาขอบเขตความปลอดภัยทางบัญชีก่อนการออกแบบ Import Contract
6. **RBAC & Ownership Security:**
   - สิทธิ์ใหม่ 4 ระดับ: `hr.self.view`, `hr.team.view`, `hr.manage`, `hr.summary.manage`
   - ผู้จัดการ (`manager_user_id`) ดูข้อมูลได้เฉพาะพนักงานใต้บังคับบัญชาตรงผ่าน `hr.team.view`
   - พนักงานทั่วไปดูได้เฉพาะข้อมูลของตนเองผ่าน `hr.self.view`
   - Controller ตรวจสอบ Multi-tenant (`assertOrg`) และความเป็นเจ้าของเรคคอร์ด (`assertOwn`) ซ้ำซ้อน 100%
7. **Test Verification:** ผ่านการทดสอบ `Phase19HrAttendanceTest.php` ทั้ง 3 tests (22 assertions) ครอบคลุม Privacy consent, Leave balance atomic deduction/restoration, และ Summary lock/reversal non-posting to payroll.

---

### 4.10 ข้อสังเกตและข้อเสนอแนะเพิ่มเติมสำหรับ Phase 18.1 และ Phase 19 (Findings & Polish Recommendations)

จากการตรวจสอบซอร์สโค้ดและพฤติกรรมการทำงานอย่างละเอียด พบจุดที่สามารถขัดเกลาและนำไปต่อยอดได้ดังนี้:

1. **Action Confirmation Guards ในหน้าจอ `Hr/Index.tsx`:**
   - *ข้อสังเกต:* ปุ่มเปลี่ยนสถานะที่สำคัญในฝั่งผู้ใช้ เช่น "Cancel Request" ในแถบ Leave Requests หรือปุ่ม "Lock Summary" และ "Reverse Summary" ในแถบ Attendance Summaries ปัจจุบันส่ง Inertia Request ทันทีที่คลิก
   - *ข้อเสนอแนะ:* ควรเพิ่ม Modal หรือ Browser Confirmation Prompt (`window.confirm`) เพื่อให้ผู้ใช้ยืนยันความตั้งใจก่อนยกเลิกวันลา หรือก่อน Lock/Reverse สรุปเวลาทำงาน เพื่อป้องกันความผิดพลาดจาก Human Error
2. **การรองรับวันลาแบบครึ่งวันและรายชั่วโมง (Half-Day / Hourly Leave Support):**
   - *ข้อสังเกต:* ในโครงสร้างฐานข้อมูล คอลัมน์ `days` ใน `leave_balances` และ `leave_requests` กำหนดเป็น `DECIMAL(5,1)` ซึ่งรองรับทศนิยม แต่ฟังก์ชันคำนวณ `businessDays()` ใน `HrAttendanceService` ปัจจุบันคำนวณเฉพาะจำนวนวันเต็ม (1.0, 2.0 วัน)
   - *ข้อเสนอแนะ:* ใน Phase 20 (Workflow Engine) สามารถเพิ่มตัวเลือกช่วงเวลาลาในแบบฟอร์ม: `full_day` (1.0), `half_day_morning` (0.5 วัน), `half_day_afternoon` (0.5 วัน) หรือระบุชั่วโมง เพื่อเพิ่มความยืดหยุ่นให้สอดคล้องกับระเบียบบริษัททั่วไป
3. **Office Coordinate Geofencing & Radius Validation:**
   - *ข้อสังเกต:* ปัจจุบันระบบเก็บค่า Latitude / Longitude เมื่อผู้ใช้ยินยอม แต่ยังไม่มีการตรวจรัศมีระยะห่างจากที่ตั้งสำนักงาน (Geofence Radius) ตามที่ระบุไว้เป็นข้อสังเกตใน `gpt.md` (ข้อ 283)
   - *ข้อเสนอแนะ:* ในอนาคตสามารถเพิ่มฟิลด์ `office_lat`, `office_lng`, `radius_meters` ในการตั้งค่ากะงานหรือองค์กร และใช้สูตร Haversine คำนวณระยะห่างเพื่อ Flag เตือนการลงเวลานอกสถานที่
4. **ระบบนำเข้าปฏิทินวันหยุดประจำปี (Public Holiday Import Tool):**
   - *ข้อสังเกต:* ปัจจุบันการเพิ่มวันหยุดในตาราง `holidays` ต้องกรอกทีละรายการผ่านหน้า Master Setup
   - *ข้อเสนอแนะ:* เพิ่มปุ่ม "Import Public Holidays" ที่สามารถโหลดไฟล์ CSV หรือดึงวันหยุดตามประกาศธนาคารแห่งประเทศไทย (BOT Holiday Calendar) เข้าสู่ระบบได้ในคลิกเดียว
5. **การตั้งเวลา Background Scheduler สำหรับ Document Retention:**
   - *ข้อสังเกต:* คำสั่ง `php artisan documents:enforce-retention [--purge]` ใช้งานได้สมบูรณ์แล้ว
   - *ข้อเสนอแนะ:* ควรกำหนด Schedule ใน `routes/console.php` หรือ `app/Console/Kernel.php` ให้รันสัปดาห์ละ 1 ครั้ง (เช่น `schedule->command('documents:enforce-retention')->weekly()`) เพื่อให้การทำความสะอาดเอกสารหมดอายุเกิดขึ้นอัตโนมัติอย่างต่อเนื่อง *(ดำเนินการเพิ่มใน `routes/console.php` เรียบร้อยแล้ว)*

---

### 4.11 ผลการตรวจสอบและข้อเสนอแนะ Phase 20: Central Dynamic Approval Workflow Engine

#### จุดเด่นที่ผ่านการทดสอบและปฏิบัติตามมาตรฐานสถาปัตยกรรม (Strengths & Verified Architecture):
1. **Central Dynamic Workflow Architecture (ตามข้อตกลงใน `gpt.md` ข้อ 8 และ 9):**
   - Migration `2026_09_07_000002_create_phase20_workflow_tables.php` วางโครงสร้าง 5 ตารางหลักอย่างรัดกุมพร้อม Scoping `org_id` ทุกตาราง:
     - `workflow_definitions`: แม่แบบลำดับการอนุมัติแบบ Versioned ควบคุมช่วงวงเงิน (`amount_min`, `amount_max`), แผนก, และประเภทเอกสาร
     - `workflow_steps`: ขั้นตอนการอนุมัติ (Step No, Assignment Type: `user` / `manager` / `role`, Execution Mode)
     - `workflow_instances`: Instance การทำงานจริงที่ผูกกับเอกสารธุรกิจ
     - `workflow_approvals`: รายการคิวการอนุมัติรายบุคคล พร้อมสถานะ (`queued`, `pending`, `approved`, `rejected`, `revision_requested`)
     - `workflow_delegations`: การมอบอำนาจอนุมัติชั่วคราว
   - **No Bespoke Approval Logic:** ไม่มีการกระจายโค้ดอนุมัติเฉพาะกิจในโมดูลย่อย แต่รวมศูนย์ผ่าน `WorkflowEngineService` ตามเป้าหมายของระบบ
2. **Deterministic Snapshot Integrity:**
   - เมื่อยื่นเอกสารเข้า Workflow (`submit`), ระบบจะจับภาพ `definition_snapshot` (ลำดับขั้นตอน, โหมด, และรายชื่อผู้อนุมัติที่คำนวณได้ ณ เวลานั้น) และ `subject_snapshot` (ยอดเงิน, รหัสเอกสาร, ผู้ขอ) จัดเก็บเป็น JSON
   - การปรับเปลี่ยนโครงสร้างองค์กร, สายบังคับบัญชา หรือสิทธิ์ของผู้ใช้ในภายหลัง จึงไม่มีผลกระทบย้อนหลังต่อ Workflow ที่กำลังดำเนินอยู่
3. **Strict Segregation of Duties (SoD) & Role Security:**
   - ระบบบล็อกไม่ให้ผู้ขออนุมัติ (Requester) ทำการอนุมัติตนเอง (`$instance->requester_user_id === $actor->id`) ผ่านข้อยกเว้น `ValidationException`
   - การมอบอำนาจ (Delegation) ตรวจสอบเงื่อนไขอย่างเคร่งครัด: ต้องอยู่ในช่วงเวลา (`starts_at` ถึง `ends_at`), ต้อง active, และห้ามมอบอำนาจให้ตนเอง
4. **RBAC & Multi-Tenant Isolation:**
   - สิทธิ์ใหม่ 3 รายการ: `workflows.view`, `workflows.manage`, `workflows.approve`
   - ทุก Endpoint และ Service มีการตรวจสอบ Tenant (`assertOrg` / `$instance->org_id === $actor->org_id`) และ Ownership อย่างชัดเจน
   - มีการบันทึก `AuditLog` ทุกจังหวะสำคัญ: `workflow.definition.create`, `workflow.submit`, `workflow.approved`, `workflow.rejected`, `workflow.revision_requested`, และ `workflow.delegation.create`
5. **Test Pass Rate:**
   - ผ่านการทดสอบ `Phase20WorkflowTest.php` ครอบคลุม Threshold Snapshot, SoD Block, Delegation Expiry และ Idempotent Approval Action
   - ชุดทดสอบทั้งหมดของระบบผ่าน **255 tests (1,963 assertions / 0 failures)**

---

#### ปัญหาและข้อบกพร่องที่ต้องแก้ไขเร่งด่วน (Critical Findings & Remediation Required):

1. **[CRITICAL - Accounting Integrity] การอนุมัติ Expense ข้ามขั้นตอนการลงบัญชี Double-entry GL:**
   - *ปัญหา:* ใน `ExpenseController::approve()`, การอนุมัติ Expense มีการคำนวณยอดหนี้ (`payable_total`, `balance_due`) และเรียกคำสั่ง `$journals->postExpenseApproval($expense, $user->id)` เพื่อสร้าง Journal Voucher และบันทึกบัญชีแยกประเภท
   - แต่ใน `WorkflowEngineService::markSubjectApproved()` เมื่อ Expense ผ่านการอนุมัติครบทุกขั้นตอน ระบบทำการอัปเดตเพียง:
     `$subject->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])`
   - *ผลกระทบ:* Expense ที่อนุมัติผ่าน Central Workflow Engine จะ **ไม่มีการบันทึกบัญชี GL และไม่มีการเซ็ตยอด Payable** ทำให้งบการเงินและยอดเจ้าหนี้คลาดเคลื่อนอย่างรุนแรง
   - *แนวทางแก้ไข:* ใน `markSubjectApproved()` กรณี `subject_type === 'expense'` ต้องเรียกใช้ `FinancialJournalService::postExpenseApproval()` พร้อมคำนวณ `payable_total`, `base_payable_total`, `balance_due`, `base_balance_due` ให้เหมือนกับ `ExpenseController::approve()`

2. **[CRITICAL - Data & Entitlement Integrity] การ Reject/Revise ไม่ปรับสถานะเอกสารต้นทาง และไม่คืนยอดวันลา:**
   - *ปัญหา:* ใน `WorkflowEngineService::act()` เมื่อ Action เป็น `rejected` หรือ `revision_requested`:
     ```php
     if ($action !== 'approved') {
         $instance->update(['status' => $action, 'completed_at' => now()]);
         return $instance->fresh();
     }
     ```
     ระบบอัปเดตสถานะเฉพาะในตาราง `workflow_instances` แต่ **ไม่ได้อัปเดตสถานะของ `$subject`** (เช่น `leave_requests`, `expenses`, `purchase_requests`, `purchase_orders`) ให้เปลี่ยนเป็น `rejected` หรือ `draft/revision`
   - *ผลกระทบต่อวันลา (Leave Balance Leak):* ตอนที่พนักงานกด Submit คำขอลา (`submitLeave`), ยอดวันลาถูกหักออกจาก `leave_balances` ไปแล้ว หากคำขอถูก Reject ใน Workflow Engine ยอดวันลาที่หักไปแล้ว **จะไม่ได้รับคืน** ทำให้พนักงานเสียสิทธิ์วันลาไปโดยปริยาย
   - *แนวทางแก้ไข:*
     - เพิ่มฟังก์ชัน `markSubjectRejected(WorkflowInstance $instance, string $action)`
     - สำหรับ `leave_request`: ให้คืนยอดวันลาเข้า `leave_balances` (ตาม logic ใน `cancelLeave`) และปรับสถานะ `LeaveRequest` เป็น `rejected` หรือ `draft` (กรณีขอแก้ไข)
     - สำหรับ `expense`, `purchase_request`, `purchase_order`: ปรับสถานะเอกสารให้สอดคล้องกัน

3. **[HIGH - Payroll Integration Gap] Attendance Summary ข้ามวันลาที่ได้รับการอนุมัติ (Approved Leave Omission):**
   - *ปัญหา:* ใน `HrAttendanceService::createSummary()` บรรทัดที่ 131:
     `$leaves = LeaveRequest::where('org_id', $orgId)->where('user_id', $userId)->where('status', 'submitted')->whereDate(...)...`
     ระบุเงื่อนไขค้นหาเฉพาะ `where('status', 'submitted')`
   - *ผลกระทบ:* เมื่อ LeaveRequest ได้รับการอนุมัติผ่าน Phase 20 สถานะจะเปลี่ยนเป็น `approved` ทำให้ฟังก์ชัน `createSummary()` มองข้ามวันลาที่อนุมัติแล้ว และคำนวณวันลา (`paid_leave_days`, `unpaid_leave_days`) เป็น 0 ส่งผลให้ข้อมูลที่ส่งต่อ Payroll ผิดพลาด
   - *แนวทางแก้ไข:* ปรับเงื่อนไขเป็น `whereIn('status', ['submitted', 'approved'])` หรือ `where('status', 'approved')`

4. **[HIGH - Workflow Deadlock] ความเสี่ยงเกิดภาวะ Deadlock ใน Role-based Approval เมื่อ Requester มี Role นั้น:**
   - *ปัญหา:* ใน `assignees()`, เมื่อกำหนด Step เป็น `'role'` ระบบดึง User ทุกคนใน Role นั้นมาสร้างคิว `WorkflowApproval`. หาก Requester เป็นหนึ่งในคนที่มี Role นั้น (เช่น ผู้จัดการ หรือ เจ้าหน้าที่การเงิน) ระบบจะสร้างเรคคอร์ด `pending` ให้ตัว Requester ด้วย
   - ใน `act()`, ระบบบล็อก SoD ไม่ให้ Requester อนุมัติเอกสารตนเอง
   - แต่ในบรรทัด 87:
     `if ($stepApprovals->contains('status', 'pending')) { return $instance->fresh(); }`
     ระบบตรวจสอบว่าถ้ายังมีรายการใดค้าง `pending` จะไม่ยอมขยับ Step. เนื่องจาก Requester อนุมัติไม่ได้ รายการของ Requester จึงค้าง `pending` ตลอดไป ทำให้ขั้นตอนการอนุมัติไม่มีวันเสร็จสิ้น (Permanent Deadlock)
   - *แนวทางแก้ไข:* กรอง Requester ออกจากรายชื่อ Assignee ของ Step นั้นตั้งแต่ขั้นตอน Snapshot:
     `$assignees = $this->assignees($requester, $step, $subject)->reject(fn ($id) => $id === $requester->id);`
     และกำหนดเงื่อนไขว่าหากเป็น Role Assignment ให้ถือว่าการอนุมัติของบุคคลใดบุคคลหนึ่งใน Role มีผลครบถ้วน (First-to-approve) หรือ Unanimous ยกเว้น Requester

5. **[MEDIUM - CI/CD Quality Gates Failure] ข้อผิดพลาดจากการตรวจสอบ Code Standards:**
   - **PHP Pint Format Failure:**
     - `backend/routes/web.php`: พบการเรียงลำดับ Use statement สลับกัน (`ordered_imports`)
     - `backend/tests/Feature/Phase20WorkflowTest.php`: บรรทัดที่ 63 มีการเยื้องบรรทัด (`statement_indentation`) ของ `return $users;` ผิดปกติ
   - **ESLint Typescript Failure (39 Errors):**
     - `backend/resources/js/Pages/Workflows/Index.tsx`: พบข้อผิดพลาด `@typescript-eslint/no-explicit-any` จำนวน 21 จุด
     - `backend/resources/js/Pages/Hr/Index.tsx`: พบข้อผิดพลาด `@typescript-eslint/no-explicit-any` จำนวน 18 จุด
     - จำเป็นต้องประกาศ TypeScript Interface ให้ชัดเจน (เช่น `WorkflowDefinition`, `WorkflowApproval`, `WorkflowInstance`, `WorkflowDelegation`, `User`, `PageProps`)
   - **Prettier Formatting Failure:**
     - `backend/resources/js/Layouts/AuthenticatedLayout.tsx`
     - `backend/resources/js/Pages/Hr/Index.tsx`
     - `backend/resources/js/Pages/Workflows/Index.tsx`
     - ต้องรัน `npm run format` หรือ Prettier write เพื่อให้ผ่าน CI Gate 100%

6. **[CRITICAL - Workflow Engine Flaw] `execution_mode` (Sequential vs Parallel) เป็น Dead Code และไม่มีกลไก First-to-Approve สำหรับ Role Assignment:**
   - *ปัญหา:* ฟิลด์ `execution_mode` ('sequential' / 'parallel') ใน `workflow_steps` และ `definition_snapshot` ไม่เคยถูกนำมาคำนวณใน `WorkflowEngineService::act()`
   - ปัจจุบันโค้ด `$stepApprovals->contains('status', 'pending')` ถือว่าทุกรายการที่สร้างใน Step นั้นต้องอนุมัติครบทุกคนเสมอ (Unanimous All-Approvers)
   - *ผลกระทบ:* เมื่อกำหนด Step เป็น `role` (เช่น ฝ่ายการเงินหรือผู้จัดการ 3 คน) คนใดคนหนึ่งอนุมัติจะไม่สามารถผ่าน Step ได้จนกว่าทั้ง 3 คนจะกดอนุมัติครบทุกคน ซึ่งขัดกับหลัก First-to-Approve หรือ Any-one approver ในกระบวนการทางธุรกิจจริง
   - *แนวทางแก้ไข:* กำหนดกลไกประเมินผลของ Step ให้รองรับ First-to-Approve สำหรับ Role Step หรือใช้ `execution_mode` ในการควบคุมว่าจะต้อง Unanimous หรือ Any-one จากนั้นให้ Auto-resolve รายการที่เหลือใน Step นั้นให้เป็น `superseded` หรือ `bypassed`

7. **[HIGH - Delegation Visibility Gap] หน้าจอ Inbox ไม่แสดงรายการที่ได้รับมอบหมายอำนาจ (Delegated Approvals Invisible in Inbox UI):**
   - *ปัญหา:* ใน `WorkflowController::index`, รายการ Inbox ดึงเฉพาะ `where('assigned_user_id', $user->id)`
   - *ผลกระทบ:* ผู้รับมอบอำนาจ (Delegate) จะไม่เห็นรายการของ Delegator ในหน้า Inbox ของตนเอง ทำให้ไม่สามารถปฏิบัติหน้าที่แทนได้ผ่าน UI แม้ว่าใน Service `isDelegate()` จะอนุญาตให้กดอนุมัติได้ก็ตาม
   - *แนวทางแก้ไข:* ขยาย Inbox Query ให้รวม `workflow_approvals` ที่ `assigned_user_id` อยู่ในรายชื่อผู้มอบอำนาจที่ยังมีผลบังคับใช้ (`delegator_user_id` ที่มอบหมายให้ `$user->id` และยังอยู่ในช่วง `starts_at` - `ends_at`)

8. **[MEDIUM - Subject Lifecycle & Status Consistency across Business Modules]:**
   - *ปัญหา:* ในการอนุมัติ/ปฏิเสธเอกสารแต่ละโมดูล (LeaveRequest, PurchaseRequest, PurchaseOrder, Expense) มีสถานะและพฤติกรรมเฉพาะ:
     - `LeaveRequest`: รองรับ `draft`, `submitted`, `approved`, `rejected`, `cancelled` (คืนยอดวันลาเมื่อ rejected)
     - `PurchaseRequest`: รองรับ `draft`, `approved`, `rejected`
     - `PurchaseOrder`: รองรับ `draft`, `approved`, `cancelled` (หรือ `rejected`)
     - `Expense`: รองรับ `draft`, `approved`, `rejected`, `paid`
   - *แนวทางแก้ไข:* ออกแบบ Interface หรือ Handler Mapping กลางสำหรับ Subject Status Transition ใน `WorkflowEngineService` ให้ปรับสถานะเอกสารต้นทางและจัดการ Side-effects (เช่น คืนยอดวันลา, ปรับยอดหนี้ AP, บันทึก GL) อย่างถูกต้องตาม Domain Contract ของแต่ละโมดูล

---

#### ข้อสังเกตและข้อเสนอแนะด้าน UI / UX (Usability Polish Recommendations):

1. **Workflow Builder รองรับเพียง Step เดียวในหน้า UI:**
   - แบบฟอร์มใน `Workflows/Index.tsx` ฮาร์ดโค้ดชื่อฟิลด์เป็น `steps[0][...]` ทำให้ผู้ใช้สร้างได้เพียงขั้นตอนเดียวจากหน้าจอ UI แม้ว่า Backend และ Validation จะรองรับถึง 10 ขั้นตอน
   - *ข้อเสนอแนะ:* เพิ่มปุ่ม "+ Add Step" ในหน้า UI เพื่อให้ผู้ดูแลระบบสามารถกำหนด Multi-Level Workflow (เช่น Step 1: Manager -> Step 2: Finance -> Step 3: Executive) ได้จริง
2. **Action Confirmation Modal พร้อมช่องระบุ Comment:**
   - ปุ่ม Approved, Rejected, Revision Requested ในแถบ My Approval Inbox ปัจจุบันส่ง Request ทันทีที่คลิก
   - *ข้อเสนอแนะ:* ควรมี Modal แสดงรายละเอียดสรุปของเอกสาร พร้อมช่องกรอก `comment` (โดยเฉพาะกรณี Rejected หรือ Revision Requested ควรบังคับให้ใส่เหตุผล)
3. **การแสดงผล Document Identifier ที่สื่อความหมาย:**
   - ในหน้า Inbox คอลัมน์ Document ปัจจุบันแสดงผลเป็น UUID (เช่น `expense · 9e32a1...`)
   - *ข้อเสนอแนะ:* ส่ง Display Name / Document Number (เช่น `EXP-2026-0001`, `PR-001`, หรือ `Leave: สมชาย (3 วัน)`) และทำลิงก์เปิดดูเอกสารต้นทางได้โดยตรง
4. **ปุ่ม "Submit to Approval Workflow" ในโมดูลต้นทาง:**
   - ปัจจุบันมี Route กลาง `/workflows/submit/{subjectType}/{subjectId}` แต่ในหน้าจอ Leave Requests, Expenses, PR, PO ยังไม่มีปุ่มให้ผู้ใช้กดส่งเข้า Workflow จากหน้าจอนั้นๆ โดยตรง
5. **การแสดงสถานะ Delegation ในประวัติและไทม์ไลน์ (Delegation Audit & Attribution Display):**
   - เมื่อมีการอนุมัติแทนกัน ควรแสดงใน Timeline ให้ชัดเจนว่า "อนุมัติโดย [Delegate Name] ปฏิบัติหน้าที่แทน [Original Approver]" เพื่อความโปร่งใสใน Audit Trail

---

### 4.12 ผลการตรวจรับรองการแก้ไข Phase 20 (Phase 20 Remediation Verification & Approval Gate)

จากการตรวจสอบซอร์สโค้ดและรันชุดทดสอบระบบอย่างครบวงจรหลัง GPT ทำการแก้ไขข้อบกพร่องตามรายการในข้อ 4.11 พบว่าปัญหาทั้งหมดได้รับการแก้ไขอย่างถูกต้องสมบูรณ์ 100%:

1. **Expense Double-entry GL Posting:**
   - ใน `WorkflowEngineService::markSubjectApproved()` มีการคำนวณ `payable_total`, `base_payable_total`, `balance_due`, `base_balance_due` และเรียกใช้ `$this->journals->postExpenseApproval($subject->fresh(), $actor->id)` สมบูรณ์
   - ยืนยันด้วย Feature Test `test_expense_final_approval_sets_payable_and_posts_gl` ตรวจสอบทั้งยอดหนี้และ `journal_entries` ในฐานข้อมูลจริง
2. **Leave Balance Refund & Status Sync on Reject/Revise:**
   - พัฒนาฟังก์ชัน `markSubjectNotApproved()` รองรับการคืนยอดวันลาเข้า `leave_balances` สำหรับวันลาที่จ่ายค่าจ้าง (`is_paid`) และปรับสถานะ `LeaveRequest` เป็น `rejected` (หรือ `draft` กรณีขอแก้ไข)
   - โมดูล Expense, Purchase Request, และ Purchase Order ได้รับการปรับสถานะเป็น `rejected` / `draft` สอดคล้องกัน
   - ยืนยันด้วย Feature Test `test_rejection_refunds_paid_leave_and_revision_returns_to_draft`
3. **Approved Leaves in Attendance Summary:**
   - ใน `HrAttendanceService::createSummary()` ปรับเงื่อนไขการดึงวันลาเป็น `whereIn('status', ['submitted', 'approved'])` ครอบคลุมวันลาที่อนุมัติแล้วอย่างแม่นยำ
4. **SoD Role Deadlock Prevention & Execution Mode:**
   - กรอง Requester ออกจากรายชื่อ Assignee ของ Step นั้นตั้งแต่ขั้นตอน Snapshot (`reject(fn (string $id) => $id === $requester->id)`) ป้องกันปัญหา Deadlock
   - กำหนดพฤติกรรม `execution_mode`:
     - `parallel`: รองรับแบบ First-to-Approve โดยเมื่อมีผู้อนุมัติคนแรก รายการที่เหลือใน Step จะถูกปรับเป็น `superseded`
     - `sequential`: บังคับให้ทุกคนใน Step ต้องดำเนินการครบถ้วน
5. **Delegated Approvals Visibility in Inbox UI:**
   - ใน `WorkflowController::index` เพิ่มการค้นหา Active Delegations ที่มอบหมายให้ `$user->id` และดึง Approval ค้างอยู่มาแสดงผลใน Inbox พร้อมตั้งค่าความสัมพันธ์ `delegatedFromUser` แสดงผลว่า "Delegated from [Name]"
6. **Multi-Step Workflow Builder UI:**
   - หน้าจอ `Workflows/Index.tsx` พัฒนาเป็น Dynamic Step Builder รองรับการเพิ่ม/ลบขั้นตอนได้สูงสุด 10 Steps (`builderSteps`) และส่งข้อมูลเป็น JSON Array ไปยัง Backend โดยตรง
7. **Submit Approval Triggers in Source Modules:**
   - เพิ่มปุ่ม "Submit Approval" ในสถานะ Draft ของหน้า Expenses (`Expenses.tsx`), Purchase Orders (`PurchaseOrders.tsx`), และ Commercial Documents Purchase Requests (`CommercialDocuments.tsx`)
8. **Action Confirmation & Comment Mandate:**
   - มี Confirmation Prompt ในการกด Action ทุกครั้ง และบังคับกรอกเหตุผล (Comment Required) ในกรณี `rejected` และ `revision_requested` ทั้งฝั่ง UI และ Backend Form Validation
9. **Static Analysis & CI/CD Gates Pass 100%:**
   - **PHP Pint:** Passed (0 errors / 0 issues)
   - **TypeScript (`tsc`):** Clean (0 errors)
   - **ESLint:** 0 errors / 0 warnings (กำจัด `any` ทั้ง 39 จุดและประกาศ Interface ครบถ้วน)
   - **Prettier:** Passed (All matched files use Prettier code style)
   - **Vite Build:** 1,051 modules transformed / build clean
   - **PHPUnit / Feature Tests:** **257 Tests Passed (1,968 assertions / 0 failures)**

**สรุป:** Phase 20 ผ่านการตรวจรับรอง (Approved & Closed) อย่างเป็นทางการ พร้อมส่งมอบเข้าสู่ Phase 21 ต่อไป

---

## 6. แผนงานพัฒนาต่อยอด (Future Roadmap & Architectural Guardrails)

### แผนผังลำดับการพัฒนาที่เห็นชอบร่วมกัน (Consensus Roadmap Phase 1 - 28):

```
[Phase 1 - 18.1 & Phase 19: Core, Financials, Treasury, Inventory, Payroll, DMS, 2FA & HR Foundation] (Closed)
       ↓
[Phase 20: Dynamic Approval Workflow Engine] (Thresholds, Multi-level, Delegation, Leave/PR/PO/Expense Chains) (Closed)
       ↓
[Phase 21: Operational Notifications & Outbox] (LINE OA / Slack / Telegram, Quiet Hours, No Cash Balance) (Next Gate)
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
- **Phase 1 ถึง Phase 20 (รวม Phase 18.1 Remediation):** เสร็จสมบูรณ์แล้วทุก Phase (100% Complete & Closed)
- **Phase 20 Dynamic Approval Workflow Engine:** พัฒนาโครงสร้างตาราง, Service กลาง, Inbox UI, Multi-Step Builder, Delegation Resolution, และเชื่อมต่อ Leave/PR/PO/Expense พร้อมแก้ไขจุดบกพร่อง Double-Entry GL posting, Leave balance refund, SoD role deadlock, และผ่าน Code Quality Standards (Pint, Prettier, ESLint 0 warnings, TypeScript) ครบถ้วน 100%
- **สถานะการทดสอบระบบ (Test Verification):**
  - Backend Feature Tests: **257 Tests Passed (1,968 Assertions / 0 Failures)**
  - Frontend Build: **Vite Build Clean (1,051 modules transformed / 0 errors)**
  - TypeScript Compilation: **`tsc` Clean (0 errors)**
  - Static Code Analysis: **ESLint Clean (0 warnings / 0 errors)**, **Prettier Clean**, **Laravel Pint Passed**
- **ประตูสู่ Phase ถัดไป (Next Gate):** พร้อมเปิดประตูสู่ **Phase 21: Operational Notifications & Outbox**

---

## 7. คำแนะนำสำหรับ AI ในการพัฒนาต่อยอด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 1 และ 2 ของ `gemini.md` โดยเด็ดขาด** (Org Isolation, Redaction, Permission Check, Session Invalidation, Payslip Privacy Guard, DMS Private Download Guard, 2FA Encrypted Secret Guard, Attendance Privacy Guard, No Cash Balance Guard, AI OCR Human-in-the-Loop, Workflow SoD Guard)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-20 โดยไม่มี Decision/Checklist ใหม่
   - **อนุญาตและให้ดำเนินการได้:** การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production-Blocking Issue ใน Phase ที่ปิดแล้ว โดยต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality:** รัน `php artisan test`, `npm run build`, `npm run lint`, `npm run check-format`, และ `php vendor/bin/pint --test` ให้ผ่าน 100% ทุกครั้งที่จบ Slice/Phase
