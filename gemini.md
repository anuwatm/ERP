# Gemini Review & Audit Notes (Clean)

Last cleaned: 2026-08-27 (Phase 10 Closed)

Purpose: เก็บเฉพาะข้อมูลอ้างอิงสถาปัตยกรรม/ความปลอดภัยหลักที่ยังต้องควบคุม (Security Guardrails), สถานะโครงการล่าสุด, ข้อคิดเห็น/ข้อสังเกตเชิงสถาปัตยกรรม (Architectural Observations & Feedback), ข้อปฏิบัติในการขึ้น Production และแผนงานพัฒนาต่อยอด เพื่อความสะอาดและกระชับของบริบท (รายละเอียดงานที่ปิดแล้วสามารถดูได้ที่ `checklist.md` และ Git history)

---

## 1. ข้อมูลอ้างอิงการควบคุมความปลอดภัย (Security Guardrails Reference)

รายการควบคุมความปลอดภัยที่ต้องคงอยู่ในการพัฒนาต่อยอดทุกส่วน:

| หัวข้อความปลอดภัย | รายละเอียดการควบคุมความปลอดภัย (Security Control) |
| :--- | :--- |
| **Directory Traversal Protection** | ตรวจสอบ Canonical Path ด้วย `realpath()` และ `str_starts_with($fullPath, $basePath)` ป้องกันการเข้าถึงไฟล์นอกพื้นที่จัดเก็บไฟล์สาธารณะอย่างสมบูรณ์ |
| **Invite Token Protection** | ซ่อนและไม่ส่ง Plain Invite Token และ URL ใน Flash Session หากอยู่ในสภาพแวดล้อมที่เป็น `production` |
| **Audit Logs Redaction** | พัฒนา Central Redaction Guard ใน Eloquent `saving` event เพื่อเซนเซอร์คำสำคัญ (เช่น password, token, secret) และทำ Masking เลขประจำตัวประชาชน (`person_id`) |
| **Upload & Attachment Security** | ตรวจสอบนามสกุลและ MIME Type ของไฟล์แนบ (Voucher proof slip, Logos, Documents) อย่างเข้มงวด ป้องกันไฟล์ Executable/Polyglot และจัดเก็บใน storage key ฝั่ง server ใต้ `tenants/{org_id}/...` เท่านั้น |
| **Session Invalidation** | เมื่อมีการ Disable บัญชีผู้ใช้ หรือมีการเปลี่ยนแปลง Role หรือปรับปรุงระดับสิทธิ์ (Permissions) ระบบจะทำลายเซสชันเก่าในตาราง `sessions` และล้าง `remember_token` ของผู้ใช้รายนั้นทันทีเพื่อบังคับให้ออกจากระบบ |
| **Financial Isolation & Privacy** | ข้อมูลการเงินรวมทั้งหมดบน Dashboard แสดงผลเฉพาะผู้ที่มีสิทธิ์ `executive.dashboard.view` หรือผู้ดูแลระบบ (Owner/Admin) เท่านั้น พนักงานปกติหรือพนักงานส่งมอบทั่วไปจะไม่มีการดึงหรือเห็นข้อมูลสรุปการเงินได้เลย |
| **Bank Account Data Protection** | เลขบัญชีธนาคารจัดเก็บแบบเข้ารหัส (`encrypted` cast) และใช้ `account_number_hash` (SHA-256) ตรวจสอบความซ้ำซ้อนภายในองค์กร ส่วนการแสดงผลบน UI และ Audit Logs จะแสดงเฉพาะเลขที่ Mask แล้ว (`maskedAccountNumber`) เท่านั้น |

---

## 2. โครงสร้างและการเข้าถึงข้อมูล (Architecture & Access Scope)

- **การคำนวณราคาทุนจริงโครงการ (Dynamic Project Cost):** คำนวณแบบ Dynamic บน Memory จากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`) และห้ามมีคอลัมน์ `actual_cost` ในตาราง `projects`
- **การจำกัดสิทธิ์พนักงาน (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
- **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ
- **ความถูกต้องทางภาษีและการค้า (Tax & Commercial Integrity):** ห้ามใช้วิธี Void กับใบกำกับภาษีหรือเอกสารการค้าทางการที่ส่งมอบหรือยื่นรอบบัญชีแล้ว ให้ใช้วิธีออก Credit Note / Debit Note เพื่อปรับยอดหนี้และกระทบยอดภาษีขายตามกฎหมายสรรพากร
- **การกระทบยอดและการเงิน (Treasury Integrity):** การกระทบยอด Bank Statement, การจัดการ Petty Cash และ Cheque/PDC ใน Phase 10 จัดการในระดับ Treasury State โดยข้อมูลยอดคงเหลือคำนวณแบบ Dynamic จาก Transactions จริง และเตรียมพร้อมเชื่อม Double-Entry Journal Postings ใน Phase 11

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 10: Complete / Closed**
  - **Core & Operations (Phase 1 - 7):** Core MVP, Multitenancy, Dashboards แยกตามบทบาท, Number Sequences, Inclusive VAT, Procurement & Suppliers, Project Members.
  - **Official Documents & Compliance (Phase 8):** Official Print/PDF (Invoice, Tax Invoice/Receipt, PO, 50-Tawi WHT), Inventory/GRN/Costing, Tax Reports/Aging/WHT, In-App & Queued Notifications.
  - **Commercial & Procurement Documents (Phase 9):** Quotations & Convert to Invoice, Credit Note / Debit Note, Billing Note, Delivery Order, Purchase Request (PR -> PO), Payment/Receipt Voucher (PV/RV) พร้อม Print/PDF.
  - **Treasury, Banking & Cash Management (Phase 10):** Bank Accounts Master (Encrypted/Masked), Bank Statement Import (CSV) & Fingerprint Deduplication, Bank Reconciliation (Match/Unmatch Audit Trail), Petty Cash Management (Imprest Fund, Independent Approval, Reimbursement), Cheque/PDC Lifecycle Management (Registered, Deposited, Cleared with Statement Line, Bounced, Cancelled), Voucher Attachment (Upload/Download Proof Slip with Org Isolation), Treasury Summary Reports.
- **Validation Snapshot:** 209 Tests Passed (1,707 Assertions / 0 Failures), TypeScript/Vite Build Clean, ESLint Clean, Prettier Clean, Pint Clean.
- *(รายละเอียดประวัติงานที่ปิดแล้วใน Phase 1 - 10 ดูได้ที่ `checklist.md` และ Git history)*

---

## 4. ข้อคิดเห็นและการประสานสถาปัตยกรรม Phase 10 สู่ Phase 11 (Reconciled Architecture Notes for Phase 11)

จากการตรวจสอบร่วมกันระหว่าง Gemini และ GPT มีข้อสรุปและแนวทางสถาปัตยกรรมสำหรับ **Phase 11 (General Ledger & Double-Entry Accounting)** ดังนี้:

1. **การเชื่อมต่อ Treasury สู่ผังบัญชีแยกประเภท (Treasury to GL & Recognition Points):**
   - *หลักการบัญชีคงค้าง (Accrual Basis):* ไม่ Hardcode ผัง Dr./Cr. แบบ 1-step ตายตัว แต่ให้รองรับตาม Accounting Policy & Recognition Point:
     - **Billings / Invoices & Expenses Approval:** บันทึกตั้งหนี้/ลูกหนี้ก่อน (เช่น Dr. AR / Cr. Sales, Dr. Expense / Cr. AP)
     - **Settlement / Payments:** บันทึกตัดหนี้/ลูกหนี้เมื่อรับหรือจ่ายเงินจริง (เช่น Dr. Bank / Cr. AR, Dr. AP / Cr. Bank หรือ Petty Cash)
     - **Petty Cash Reimbursement:** Dr. Petty Cash (1112) / Cr. Bank Account (1111)
2. **วงจรเช็คและบัญชีพัก (Cheque Clearing & Idempotency Guard):**
   - การลงบันทึกเช็คต้องมี Clearing Accounts (เช่น เช็ครอเรียกเก็บ / เช็คจ่ายรอนำฝาก) รองรับวงจรตั้งแต่ Issue/Receive $\rightarrow$ Deposit $\rightarrow$ Cleared
   - ป้องกันการลงบัญชีซ้ำซ้อนด้วย Unique Constraint / Guard: `source_type + source_id + posting_event`
3. **การปิดรอบ Statement (Bank Statement Period Closing Guard):**
   - รองรับปุ่ม "Close Statement Period" บนหน้า UI เมื่อกระทบยอด Reconciled ครบทุกรายการ
   - Statement ที่มีสถานะ `closed` ห้าม Match/Unmatch และเชื่อมโยงกับการล็อกงวดบัญชี (Accounting Period Lock) ใน Phase 11
4. **การควบคุมวงเงินเงินสดย่อย (Petty Cash Imprest Fund Safeguard):**
   - ใช้ระบบ **Soft Warning** แจ้งเตือนเมื่อยอดเบิกสะสมที่รอการชดเชย (Unreimbursed Paid Requests) เกินวงเงิน Imprest Fund (ไม่ใช้ Hard Block เพื่อความยืดหยุ่นตามนโยบายองค์กร)
5. **ความยืดหยุ่นของไฟล์ Bank Statement Import (Bank CSV Formats):**
   - คงความเข้มงวดในการตรวจสอบ Header มาตรฐาน 5 คอลัมน์ พร้อม SHA-256 Fingerprint ป้องกันแถวซ้ำ
   - จัดเตรียมโครงสร้างให้พร้อมสำหรับต่อยอด Preset/Parser ของธนาคารไทย (KBANK, SCB, BBL, KTB) ในอนาคต

---

## 5. ข้อแนะนำการเตรียมความพร้อมขึ้น Production (Production Deployment & Server Prep)

1. **Background Workers & Scheduler:**
   - **Laravel Scheduler (Cron):** ตั้งค่า `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1` บนเซิร์ฟเวอร์เพื่อให้คำสั่งตรวจเช็กหนี้และแจ้งเตือนอัตโนมัติทำงานสม่ำเสมอ
   - **Queue Worker (Supervisor/Systemd):** รัน `php artisan queue:work --tries=3` เพื่อให้ระบบส่งอีเมลแจ้งเตือนทำงานเบื้องหลังโดยไม่บล็อก Web Request
2. **Thai Fonts for DomPDF:**
   - เซิร์ฟเวอร์ Linux Production ต้องติดตั้ง TrueType Fonts ภาษาไทย (เช่น Sarabun หรือ THSarabunNew) ใน `storage/fonts` หรือฝังผ่าน `@font-face` เพื่อป้องกันปัญหาสระลอยหรือภาษาไทยแสดงเป็น `???` ในไฟล์ PDF
3. **Uploads & File Storage:**
   - รัน `php artisan storage:link` และตรวจสอบการตั้งค่า `upload_max_filesize` / `post_max_size` (PHP) และ `client_max_body_size` (Nginx) ให้รองรับการอัปโหลดไฟล์แนบและสลิป
4. **Environment Security & Disaster Recovery:**
   - Production `.env` ต้องตั้งค่า `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
   - ตั้งค่า Automated Daily Database Backup และทดสอบ Restore Drill อย่างน้อย 1 รอบก่อนเปิดใช้งานจริง

---

## 6. แผนงานพัฒนาต่อยอดและคำแนะนำสถาปัตยกรรม (Future Roadmap & Architectural Guardrails)

### แผนผังลำดับการพัฒนา Phase 10 - 16:

```
[Phase 10: Complete]
       ↓
[Phase 11: General Ledger & Double-entry Accounting] (Chart of Accounts, Journal Posting, Period Lock)
       ↓
[Phase 12: E-Tax & RD Online Tax Filing] (XML ETDA, Digital Signature, RD Prep Text Export ภ.ง.ด. 1/3/53)
       ↓
[Phase 13: Fixed Assets & Depreciation] (Asset Register, Monthly Straight-line Depreciation to GL)
       ↓
[Phase 14: Multi-Currency & FX] (Rate Snapshot, Realized/Unrealized Gain/Loss Revaluation)
       ↓
[Phase 15: Advanced Inventory & Barcode/QR] (Stock Transfer, Reorder Alert, Lot/Expiry, Scanner UX)
       ↓
[Phase 16: Payroll, Social Security & Security 2FA] (Salary, ภ.ง.ด.1/1ก, สปส., Payslip, 2FA Auth)
```

### รายละเอียดและ Architectural Guardrails รายโมดูล:

1. **Phase 11: General Ledger (GL) & Double-Entry Accounting:**
   - **Chart of Accounts & Journal Posting:** ผังบัญชี (สินทรัพย์, หนี้สิน, ส่วนของเจ้าของ, รายได้, ค่าใช้จ่าย) และบันทึก Journal Entries อัตโนมัติจาก Invoice, Payment, Expense, Stock, CN/DN, PV/RV, Bank, และ Petty Cash ตาม Recognition Point
   - **Accounting Period & Period Lock:** ตาราง `accounting_periods` พร้อมสถานะ `open` / `closed` และ Guardrail ห้ามแก้ไข/Void/Post เอกสารการเงินย้อนหลังเข้าสู่งวดที่ปิดแล้ว
   - **Immutable Posting & Reversal Pattern:** Journal Entry ที่ `posted` แล้วห้ามแก้ตัวเลขเด็ดขาด หากผิดพลาดต้องออก Reversal Journal เท่านั้น
   - **Posting Idempotency:** ป้องกันการ Post ซ้ำซ้อนจากเอกสารต้นทางเดิม (ผูก `source_type + source_id + posting_event`)
2. **Phase 12: E-Tax & RD Online Tax Filing (ภาษีอิเล็กทรอนิกส์และการยื่นภาษีออนไลน์):**
   - **E-Tax Invoice / E-Receipt:** สร้างไฟล์ XML ตามมาตรฐาน ETDA และลงลายมือชื่อดิจิทัล (Digital Signature) รองรับ e-Tax by Email / RD API
   - **RD Prep Text Export:** ส่งออกไฟล์ Text รูปแบบมาตรฐานของโปรแกรม RD Prep สรรพากร เพื่อใช้อัปโหลดยื่นภาษี ภ.ง.ด. 1, ภ.ง.ด. 3, และ ภ.ง.ด. 53 ทางอินเทอร์เน็ต
3. **Phase 13: Fixed Assets & Depreciation:**
   - ทะเบียนทรัพย์สินบริษัท และคำนวณค่าเสื่อมราคารายเดือน (Straight-Line) Post เข้า GL อัตโนมัติ
4. **Phase 14: Multi-Currency & FX:**
   - **Historical FX Rate Snapshot:** Snapshot อัตราแลกเปลี่ยนลงเอกสาร ณ วันที่เกิดรายการ ไม่ใช้ Dynamic Join
   - **Realized vs Unrealized FX:** แยกการรับรู้กำไร/ขาดทุนจากอัตราแลกเปลี่ยนจริง และเมื่อสิ้นงวดบัญชี
5. **Phase 15: Advanced Inventory & Barcode/QR Operations:**
   - **Stock Transfer:** โอนย้ายสินค้าระหว่างคลังสินค้าหรือสาขา (Inter-warehouse transfer)
   - **Reorder Point & Low Stock Alert:** กำหนดระดับสต็อกขั้นต่ำและแจ้งเตือนเมื่อสินค้าใกล้หมด
   - **Lot & Expiration Tracking:** คุม Lot Number และวันหมดอายุสินค้า
   - **Barcode / QR Scanner UX:** สแกนรับสินค้า (GRN), จ่ายสินค้า (DO), ปรับยอด (Adjust) พร้อมระบุ Shelf/Bin Location
6. **Phase 16: Payroll, Social Security & Security 2FA:**
   - คำนวณเงินเดือนพนักงาน, ประกันสังคม (สปส.), ภาษีหัก ณ ที่จ่าย ภ.ง.ด. 1 / 1ก และออก Payslip
   - Two-Factor Authentication (2FA / Authenticator OTP) สำหรับบัญชีผู้ดูแลและฝ่ายการเงิน

---

## 7. คำแนะนำสำหรับ AI ในการพัฒนาต่อยอด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 1 และ 2 ของ `gemini.md` โดยเด็ดขาด** (Org Isolation, Redaction, Permission Check, Session Invalidation)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-10 โดยไม่มี Decision/Checklist ใหม่
   - **อนุญาตและให้ดำเนินการได้:** การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production-Blocking Issue ใน Phase ที่ปิดแล้ว โดยต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality:** รัน `php artisan test`, `pnpm run build`, `pnpm run lint`, `pnpm run check-format`, และ `pint` ให้ผ่าน 100% ทุกครั้งที่จบ Slice/Phase
