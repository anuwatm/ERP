# Gemini Review & Audit Notes (Clean)

Last cleaned: 2026-08-28 (Phase 12 Closed)

Purpose: รวมข้อมูลอ้างอิงสถาปัตยกรรมและความปลอดภัยหลัก (Security Guardrails), สรุปผลการตรวจและข้อเสนอแนะ Phase 12 (E-Tax & RD Prep), สถานะโครงการล่าสุด, ข้อปฏิบัติในการขึ้น Production และแผนงานพัฒนาต่อยอด Phase 13+ โดยตัดรายละเอียดงานเก่าที่ปิดเรียบร้อยแล้วออกเพื่อความกระชับและแม่นยำ (รายละเอียดงานที่ปิดแล้วสามารถดูได้ที่ `checklist.md` และ Git history)

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
| **Bank Account & Key Data Protection** | เลขบัญชีธนาคารจัดเก็บแบบเข้ารหัส (`encrypted` cast) พร้อม `account_number_hash` (SHA-256) และ Certificate ของ e-Tax เก็บเฉพาะ Vault Reference/Expiry Date เท่านั้น (ห้ามเก็บ Private Key ใน Database) |

---

## 2. โครงสร้างและการเข้าถึงข้อมูล (Architecture & Access Scope)

- **การคำนวณราคาทุนจริงโครงการ (Dynamic Project Cost):** คำนวณแบบ Dynamic บน Memory จากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`) และห้ามมีคอลัมน์ `actual_cost` ในตาราง `projects`
- **การจำกัดสิทธิ์พนักงาน (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
- **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ
- **ความถูกต้องทางภาษีและการค้า (Tax & Commercial Integrity):** ห้ามใช้วิธี Void กับใบกำกับภาษีหรือเอกสารการค้าทางการที่ส่งมอบหรือยื่นรอบบัญชีแล้ว ให้ใช้วิธีออก Credit Note / Debit Note เพื่อปรับยอดหนี้และกระทบยอดภาษีขายตามกฎหมายสรรพากร
- **ระบบบัญชีคู่และงวดบัญชี (GL & Period Lock):** บันทึก Journal Entries อัตโนมัติจากเอกสารต้นทาง (Invoice, Payment, Expense, Stock, CN/DN, PV/RV, Bank, Petty Cash) มี Idempotency Guard ป้องกันการลงซ้ำ และงวดบัญชีที่ปิด (`closed`) ห้าม Post/Void/แก้ไขย้อนหลังเด็ดขาด

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 12: Complete / Closed**
  - **Core & Operations (Phase 1 - 7):** Core MVP, Multitenancy, Dashboards แยกตามบทบาท, Number Sequences, Inclusive VAT, Procurement & Suppliers, Project Members.
  - **Official Documents & Compliance (Phase 8):** Official Print/PDF (Invoice, Tax Invoice/Receipt, PO, 50-Tawi WHT), Inventory/GRN/Costing, Tax Reports/Aging/WHT, In-App & Queued Notifications.
  - **Commercial & Procurement Documents (Phase 9):** Quotations & Convert to Invoice, Credit Note / Debit Note, Billing Note, Delivery Order, Purchase Request (PR -> PO), Payment/Receipt Voucher (PV/RV) พร้อม Print/PDF.
  - **Treasury, Banking & Cash Management (Phase 10):** Bank Accounts Master (Encrypted/Masked), Bank Statement Import (CSV) & Fingerprint Deduplication, Bank Reconciliation (Match/Unmatch Audit Trail), Petty Cash Management (Imprest Fund, Independent Approval, Reimbursement), Cheque/PDC Lifecycle Management (Registered, Deposited, Cleared with Statement Line, Bounced, Cancelled), Voucher Attachment (Upload/Download Proof Slip with Org Isolation), Treasury Summary Reports.
  - **General Ledger & Double-Entry Accounting (Phase 11):** Chart of Accounts (SME Template), Accounting Periods & Period Lock Guard, Journal Entries (Auto-Posting, Source Idempotency, Immutable Policy, Reversal Journal Pattern), Trial Balance, General Ledger & Account Ledger Reports.
  - **E-Tax & RD Online Tax Filing (Phase 12):** E-Tax Document Generation (Tax Invoice, Receipt, CN, DN) บน Private Storage พร้อม SHA-256 Hash, Fail-Closed Signature/Submission Adapter Architecture, E-Tax Configuration (Vault/KMS Reference Scope), Submission Queue & Attempt History Tracking, RD Prep Staging Text Export สำหรับ ภ.ง.ด. 3 และ ภ.ง.ด. 53.
- **Validation Snapshot:** 214 Tests Passed (1,770 Assertions / 0 Failures), TypeScript/Vite Build Clean, ESLint Clean, Prettier Clean, Pint Clean.
- *(รายละเอียดประวัติงานที่ปิดแล้วใน Phase 1 - 12 ดูได้ที่ `checklist.md` และ Git history)*

---

## 4. ผลการตรวจสอบและข้อเสนอแนะสถาปัตยกรรม Phase 12 (Phase 12 Review & Recommendations)

จากการตรวจสอบ Source Code, Data Flow, Security และ Test Suite ของ **Phase 12 (E-Tax & RD Online Tax Filing)** มีข้อสรุปและข้อเสนอแนะเชิงสถาปัตยกรรมดังนี้:

### จุดเด่นที่ทำได้ถูกต้องตามแบบแผนสถาปัตยกรรม (Strengths & Best Practices):
1. **Provider-Agnostic Integration Boundary:** แยก `ETaxDocumentService` และ `ETaxConfig` ออกจาก Third-party Provider เจ้าใดเจ้าหนึ่งอย่างชัดเจน ทำให้โครงสร้างรองรับผู้ให้บริการ e-Tax ที่ผ่านการรับรอง (Certified Service Providers) ได้หลากหลาย
2. **Fail-Closed Security Architecture:** ทั้ง `DisabledETaxSignatureAdapter` และ `DisabledETaxSubmissionAdapter` ปฏิบัติตามหลักความปลอดภัยแบบ Fail-Closed โดยไม่ยอมให้สถานะเอกสารถูกเปลี่ยนเป็น `signed` หรือ `submitted` โดยไม่มี Adapter จริงที่ Onboard ผ่านการรับรอง
3. **Zero Secret Leakage in Database:** ฐานข้อมูลจัดเก็บเฉพาะ `certificate_reference` (Vault Key ID/KMS Reference) และ `certificate_expires_at` ไม่มีการบันทึก Private Key, Password หรือ Binary Certificate ลงในฐานข้อมูลแอปพลิเคชัน
4. **Private Storage & Content Integrity:** จัดเก็บไฟล์ XML ใน Private Disk (`local`) ใต้ `e-tax/{org_id}/...` พร้อมคำนวณ `xml_sha256` และบังคับตรวจสิทธิ์ Organization Scope ทุกครั้งที่ดาวน์โหลด
5. **Sanitized RD Prep Text Export:** `RDPrepExportService` มีการกรองและแทนที่ตัวอักษรควบคุม (`|`, `\r`, `\n`) ในชื่อผู้จำหน่าย ป้องกันปัญหา Field Delimiter Injection และระบุ Header Disclaimer ชัดเจน

### ข้อเสนอแนะเชิงสถาปัตยกรรมและการขยายผล (Architectural Recommendations for Production Integration):
1. **Official ETDA Schema Transformation:**
   - XML ในระบบปัจจุบันใช้โครงสร้าง `provider-mapping-v1` สำหรับ Mapping ภายใน
   - *คำแนะนำ:* เมื่อเชื่อมต่อจริงกับ Certified Provider หรือระบบ Direct e-Tax by Email/API ของกรมสรรพากร ให้สร้าง Provider Adapter เฉพาะเพื่อ Transform เป็นรูปแบบ ETDA XML ตามมาตรฐานทางการ (เช่น `TaxInvoice_CrossIndustryInvoice_2p0.xsd`, `CreditNote_CrossIndustryInvoice_2p0.xsd`) และลงลายมือชื่อดิจิทัล (XMLDSig / XAdES)
2. **Submission Immutability Guard:**
   - เอกสาร e-Tax ที่มีสถานะ `submitted` หรือ `accepted` แล้ว ไม่ควรเปิดให้ Generate ซ้ำทับเอกสารเดิม
   - *คำแนะนำ:* หากมีความจำเป็นต้องปรับปรุงยอดหรือข้อมูล ให้ใช้วิธีออก Credit Note / Debit Note และ Generate e-Tax Document สำหรับ CN/DN นั้นแทนตามมาตรฐานสรรพากร
3. **RD Prep Tax Condition Customization:**
   - *คำแนะนำ:* ในอนาคตสามารถเพิ่มฟิลด์รหัสเงื่อนไขการหักภาษี (เช่น 1=หัก ณ ที่จ่าย, 2=ออกให้ตลอดไป, 3=ออกให้ครั้งเดียว) ใน Organization/Expense Settings เพื่อรองรับการยื่นภาษีหัก ณ ที่จ่ายในกรณีพิเศษ

---

## 5. ข้อแนะนำการเตรียมความพร้อมขึ้น Production (Production Deployment & Server Prep)

1. **Background Workers & Scheduler:**
   - **Laravel Scheduler (Cron):** ตั้งค่า `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1` บนเซิร์ฟเวอร์เพื่อให้คำสั่งตรวจเช็กหนี้และแจ้งเตือนอัตโนมัติทำงานสม่ำเสมอ
   - **Queue Worker (Supervisor/Systemd):** รัน `php artisan queue:work --tries=3` เพื่อให้ระบบส่งอีเมลแจ้งเตือนและคิวส่งเอกสาร e-Tax ทำงานเบื้องหลังโดยไม่บล็อก Web Request
2. **Thai Fonts for DomPDF:**
   - เซิร์ฟเวอร์ Linux Production ต้องติดตั้ง TrueType Fonts ภาษาไทย (เช่น Sarabun หรือ THSarabunNew) ใน `storage/fonts` หรือฝังผ่าน `@font-face` เพื่อป้องกันปัญหาสระลอยหรือภาษาไทยแสดงเป็น `???` ในไฟล์ PDF
3. **Uploads & File Storage:**
   - รัน `php artisan storage:link` และตรวจสอบการตั้งค่า `upload_max_filesize` / `post_max_size` (PHP) และ `client_max_body_size` (Nginx) ให้รองรับการอัปโหลดไฟล์แนบและสลิป
4. **Environment Security & Disaster Recovery:**
   - Production `.env` ต้องตั้งค่า `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
   - ตั้งค่า Automated Daily Database Backup และทดสอบ Restore Drill อย่างน้อย 1 รอบก่อนเปิดใช้งานจริง

---

## 6. แผนงานพัฒนาต่อยอด (Future Roadmap & Architectural Guardrails)

### แผนผังลำดับการพัฒนา Phase 12 - 16:

```
[Phase 12: Complete]
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

1. **Phase 13: Fixed Assets & Depreciation (ทรัพย์สินถาวรและค่าเสื่อมราคา):**
   - **Fixed Asset Register:** ทะเบียนทรัพย์สินบริษัท (Asset Code, Category, Purchase Date, Cost, Salvage Value, Useful Life, Location, Custodian)
   - **Capitalization Linkage:** บันทึกรับเข้าเป็นทรัพย์สินจาก Expense / PO / GRN
   - **Depreciation Calculation & GL Posting:** คำนวณค่าเสื่อมราคารายเดือน (Straight-Line Method) และ Post บันทึกบัญชีเข้า General Ledger อัตโนมัติ (Dr. ค่าเสื่อมราคา / Cr. ค่าเสื่อมราคาสะสม)
   - **Asset Disposal / Write-off:** ตัดจำหน่าย/ขายทรัพย์สิน บันทึกกำไรหรือขาดทุนจากการจำหน่ายทรัพย์สินเข้า GL
2. **Phase 14: Multi-Currency & FX:**
   - **Historical FX Rate Snapshot:** Snapshot อัตราแลกเปลี่ยนลงเอกสาร ณ วันที่เกิดรายการ ไม่ใช้ Dynamic Join
   - **Realized vs Unrealized FX:** แยกการรับรู้กำไร/ขาดทุนจากอัตราแลกเปลี่ยนจริง และเมื่อสิ้นงวดบัญชี
3. **Phase 15: Advanced Inventory & Barcode/QR Operations:**
   - **Stock Transfer:** โอนย้ายสินค้าระหว่างคลังสินค้าหรือสาขา (Inter-warehouse transfer)
   - **Reorder Point & Low Stock Alert:** กำหนดระดับสต็อกขั้นต่ำและแจ้งเตือนเมื่อสินค้าใกล้หมด
   - **Lot & Expiration Tracking:** คุม Lot Number และวันหมดอายุสินค้า
   - **Barcode / QR Scanner UX:** สแกนรับสินค้า (GRN), จ่ายสินค้า (DO), ปรับยอด (Adjust) พร้อมระบุ Shelf/Bin Location
4. **Phase 16: Payroll, Social Security & Security 2FA:**
   - คำนวณเงินเดือนพนักงาน, ประกันสังคม (สปส.), ภาษีหัก ณ ที่จ่าย ภ.ง.ด. 1 / 1ก และออก Payslip
   - Two-Factor Authentication (2FA / Authenticator OTP) สำหรับบัญชีผู้ดูแลและฝ่ายการเงิน

---

## 7. คำแนะนำสำหรับ AI ในการพัฒนาต่อยอด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 1 และ 2 ของ `gemini.md` โดยเด็ดขาด** (Org Isolation, Redaction, Permission Check, Session Invalidation)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-12 โดยไม่มี Decision/Checklist ใหม่
   - **อนุญาตและให้ดำเนินการได้:** การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production-Blocking Issue ใน Phase ที่ปิดแล้ว โดยต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality:** รัน `php artisan test`, `pnpm run build`, `pnpm run lint`, `pnpm run check-format`, และ `pint` ให้ผ่าน 100% ทุกครั้งที่จบ Slice/Phase
