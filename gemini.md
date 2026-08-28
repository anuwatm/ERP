# Gemini Review & Audit Notes (Clean)

Last cleaned: 2026-08-28 (Phase 13 Closed)

Purpose: รวมข้อมูลอ้างอิงสถาปัตยกรรมและความปลอดภัยหลัก (Security Guardrails), ผลการตรวจสอบและข้อเสนอแนะเชิงสถาปัตยกรรม Phase 13 (Fixed Assets & Depreciation), สถานะโครงการล่าสุด, ข้อปฏิบัติในการขึ้น Production และแผนงานพัฒนาต่อยอด Phase 14+ โดยตัดรายละเอียดงานเก่าที่ปิดเรียบร้อยแล้วออกเพื่อความกระชับและแม่นยำ (รายละเอียดงานที่ปิดแล้วสามารถดูได้ที่ `checklist.md` และ Git history)

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

---

## 2. โครงสร้างและการเข้าถึงข้อมูล (Architecture & Access Scope)

- **การคำนวณราคาทุนจริงโครงการ (Dynamic Project Cost):** คำนวณแบบ Dynamic บน Memory จากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`) และห้ามมีคอลัมน์ `actual_cost` ในตาราง `projects`
- **การจำกัดสิทธิ์พนักงาน (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
- **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ
- **ความถูกต้องทางภาษีและการค้า (Tax & Commercial Integrity):** ห้ามใช้วิธี Void กับใบกำกับภาษีหรือเอกสารการค้าทางการที่ส่งมอบหรือยื่นรอบบัญชีแล้ว ให้ใช้วิธีออก Credit Note / Debit Note เพื่อปรับยอดหนี้และกระทบยอดภาษีขายตามกฎหมายสรรพากร
- **ระบบบัญชีคู่และงวดบัญชี (GL & Period Lock):** บันทึก Journal Entries อัตโนมัติจากเอกสารต้นทาง (Invoice, Payment, Expense, Stock, CN/DN, PV/RV, Bank, Petty Cash, Fixed Asset) มี Idempotency Guard ป้องกันการลงซ้ำ และงวดบัญชีที่ปิด (`closed`) ห้าม Post/Void/แก้ไขย้อนหลังเด็ดขาด

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 13: Complete / Closed**
  - **Core & Operations (Phase 1 - 7):** Core MVP, Multitenancy, Dashboards แยกตามบทบาท, Number Sequences, Inclusive VAT, Procurement & Suppliers, Project Members.
  - **Official Documents & Compliance (Phase 8):** Official Print/PDF (Invoice, Tax Invoice/Receipt, PO, 50-Tawi WHT), Inventory/GRN/Costing, Tax Reports/Aging/WHT, In-App & Queued Notifications.
  - **Commercial & Procurement Documents (Phase 9):** Quotations & Convert to Invoice, Credit Note / Debit Note, Billing Note, Delivery Order, Purchase Request (PR -> PO), Payment/Receipt Voucher (PV/RV) พร้อม Print/PDF.
  - **Treasury, Banking & Cash Management (Phase 10):** Bank Accounts Master (Encrypted/Masked), Bank Statement Import (CSV) & Fingerprint Deduplication, Bank Reconciliation (Match/Unmatch Audit Trail), Petty Cash Management (Imprest Fund, Independent Approval, Reimbursement), Cheque/PDC Lifecycle Management (Registered, Deposited, Cleared with Statement Line, Bounced, Cancelled), Voucher Attachment (Upload/Download Proof Slip with Org Isolation), Treasury Summary Reports.
  - **General Ledger & Double-Entry Accounting (Phase 11):** Chart of Accounts (SME Template), Accounting Periods & Period Lock Guard, Journal Entries (Auto-Posting, Source Idempotency, Immutable Policy, Reversal Journal Pattern), Trial Balance, General Ledger & Account Ledger Reports.
  - **E-Tax & RD Online Tax Filing (Phase 12):** E-Tax Document Generation (Tax Invoice, Receipt, CN, DN) บน Private Storage พร้อม SHA-256 Hash, Fail-Closed Signature/Submission Adapter Architecture, E-Tax Configuration (Vault/KMS Reference Scope), Submission Queue & Attempt History Tracking, RD Prep Staging Text Export สำหรับ ภ.ง.ด. 3 และ ภ.ง.ด. 53.
  - **Fixed Assets & Depreciation (Phase 13):** Asset Categories & GL Account Mapping (Asset, Accumulated Depreciation, Depreciation Expense), Capitalization Source จาก Expense (`approved`/`paid`) และ Goods Receipt พร้อม Unique Guard ป้องกันบันทึกซ้ำ, Straight-Line Monthly Depreciation (Catch-up Calculation, Scheduled Cron `assets:depreciate`, Idempotent GL Auto-Posting), Asset Disposal & Write-off (Stop Depreciation, Remove Accumulated/Cost, Realize Proceeds & Gain/Loss on Disposal), Proof Attachment Upload & Custody/Location Tracking.
- **Validation Snapshot:** 217 Tests Passed (1,795 Assertions / 0 Failures), TypeScript/Vite Build Clean, ESLint Clean, Prettier Clean, Pint Clean.
- *(รายละเอียดประวัติงานที่ปิดแล้วใน Phase 1 - 13 ดูได้ที่ `checklist.md` และ Git history)*

---

## 4. ผลการตรวจสอบและข้อเสนอแนะเชิงสถาปัตยกรรม Phase 13: Fixed Assets & Depreciation (Phase 13 Review & Recommendations)

จากการตรวจสอบ Source Code, Data Flow, Security และ Test Suite ของ **Phase 13 (Fixed Assets & Depreciation)** มีข้อสรุปและข้อเสนอแนะเชิงสถาปัตยกรรมดังนี้:

### จุดเด่นที่ผ่านการทดสอบและปฏิบัติตามมาตรฐานสถาปัตยกรรม (Strengths & Verified Architecture):
1. **Capitalization Recognition & Double-Posting Guard:**
   - การแปลงเป็นทรัพย์สิน (Capitalization) รองรับ 2 แหล่งที่มา:
     - **Expense:** สถานะ `approved`/`paid` บันทึก Reclassify: `Dr. Fixed Asset (1210) / Cr. Operating Expense (5200)`
     - **Goods Receipt (GRN):** ยอดสุทธิก่อนภาษี บันทึก: `Dr. Fixed Asset (1210) / Cr. Inventory (1140)`
   - **Double-Capitalization Guard:** ตาราง `fixed_assets` มี Database Unique Constraint บน `[org_id, capitalization_source_type, capitalization_source_id]` ป้องกันการแปลงซ้ำจาก Expense หรือ GRN เดิม 100%
   - ใบสั่งซื้อ (PO) ใช้เป็นเอกสารอ้างอิงผ่าน GRN เพื่อป้องกันไม่ให้เกิด Journal Entry ซ้ำซ้อน
2. **Catch-up Monthly Straight-Line Depreciation:**
   - คำนวณค่าเสื่อมราคาแบบเส้นตรงรายเดือน `(cost - salvage_value) / useful_life_months` โดย Net Book Value จะไม่ต่ำกว่าราคาซาก (`salvage_value`) เสมอ
   - มีกลไก **Catch-up อัตโนมัติ** ใน `FixedAssetService::runThroughMonth()` ซึ่งจะคำนวณและบันทึกย้อนหลังตามลำดับเดือน (Chronological order) หากคำสั่ง Scheduler ไม่ได้รันในเดือนก่อนหน้า
   - ป้องกันการ Post ค่าเสื่อมซ้ำในเดือนเดียวกันด้วย `posting_event = 'depreciation:YYYY-MM'` ร่วมกับตาราง `asset_depreciations`
   - ตั้งค่า Scheduler `assets:depreciate` รันทุกวันที่ 1 เวลา 01:00 น. เพื่อตัดรอบค่าเสื่อมราคาเดือนก่อนหน้าเข้า General Ledger
3. **Disposal & Write-off Integrity:**
   - ก่อนจำหน่ายหรือตัดจำหน่าย ระบบจะคำนวณและ Post ค่าเสื่อมราคาสะสมจนถึงสิ้นเดือนก่อนวันจำหน่ายก่อนเสมอ
   - บันทึกตัดราคาทุนทรัพย์สิน, ล้างค่าเสื่อมสะสม, รับรู้เงินที่ได้จากการขาย (Proceeds) และบันทึกผลกำไร/ขาดทุนจากการจำหน่ายทรัพย์สิน (`5300`) เข้า GL ทันที
   - สินทรัพย์ที่ `disposed` หรือ `written_off` แล้ว จะถูกล็อกสถานะห้ามแก้ไขหรือคิดค่าเสื่อมราคาเพิ่ม
4. **Org-Scoped File Attachments:**
   - การอัปโหลดหลักฐานทรัพย์สิน (รูปภาพ, ใบรับประกัน, เอกสารสิทธิ์) ทำงานผ่าน `FileAttachmentManager` บน Private Disk พร้อมตรวจสิทธิ์องค์กรและจำกัดประเภทไฟล์ (`pdf, jpg, jpeg, png, webp`) ปลอดภัย

### ข้อเสนอแนะเชิงสถาปัตยกรรมสำหรับการต่อยอด (Architectural Recommendations for Future Enhancements):
1. **Disposal Proceeds Destination Account Mapping:**
   - ปัจจุบันยอดรับจากการขายทรัพย์สิน (`proceeds`) ลงเดบิตบัญชีเงินสด/ธนาคารกลาง (`1110`)
   - *ข้อเสนอแนะ:* ในอนาคต (เชื่อมโยงกับ Phase 10 Treasury) สามารถเพิ่มตัวเลือกระบุบัญชีธนาคารปลายทาง (`bank_account_id`) เพื่อให้ยอดเงินเข้าผูกกับ Bank Statement และกระทบยอด Bank Reconciliation ได้โดยตรง
2. **Asset Category Account Flexibility:**
   - หมวดหมู่ทรัพย์สิน (`AssetCategory`) ผูกกับผังบัญชี 3 บัญชี (Asset, Accumulated Depreciation, Depreciation Expense) โดยระบบมี Template เริ่มต้น (1210/1219/5310)
   - *ข้อเสนอแนะ:* สำหรับองค์กรที่มีทรัพย์สินหลายประเภท สามารถสร้างหมวดหมู่เพิ่มเติมได้ เช่น อาคารและสิ่งปลูกสร้าง (1220/1229/5320), ยานพาหนะ (1230/1239/5330) พร้อมกำหนดอายุการใช้งานตามเกณฑ์สรรพากร (เช่น ยานพาหนะ 5 ปี, อาคารถาวร 20 ปี)

---

## 5. ข้อแนะนำการเตรียมความพร้อมขึ้น Production (Production Deployment & Server Prep)

1. **Background Workers & Scheduler:**
   - **Laravel Scheduler (Cron):** ตั้งค่า `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1` บนเซิร์ฟเวอร์เพื่อให้คำสั่งตรวจเช็กหนี้ แจ้งเตือนอัตโนมัติ คำสั่งตัดค่าเสื่อมราคารายเดือน (`assets:depreciate` ทุกวันที่ 1 เวลา 01:00) ทำงานสม่ำเสมอ
   - **Queue Worker (Supervisor/Systemd):** รัน `php artisan queue:work --tries=3` เพื่อให้ระบบส่งอีเมลแจ้งเตือนและคิวส่งเอกสาร e-Tax ทำงานเบื้องหลังโดยไม่บล็อก Web Request
2. **Thai Fonts for DomPDF:**
   - เซิร์ฟเวอร์ Linux Production ต้องติดตั้ง TrueType Fonts ภาษาไทย (เช่น Sarabun หรือ THSarabunNew) ใน `storage/fonts` หรือฝังผ่าน `@font-face` เพื่อป้องกันปัญหาสระลอยหรือภาษาไทยแสดงเป็น `???` ในไฟล์ PDF
3. **Uploads & File Storage:**
   - รัน `php artisan storage:link` และตรวจสอบการตั้งค่า `upload_max_filesize` / `post_max_size` (PHP) และ `client_max_body_size` (Nginx) ให้รองรับการอัปโหลดไฟล์แนบ สลิป และเอกสารหลักฐานทรัพย์สิน
4. **Environment Security & Disaster Recovery:**
   - Production `.env` ต้องตั้งค่า `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
   - ตั้งค่า Automated Daily Database Backup และทดสอบ Restore Drill อย่างน้อย 1 รอบก่อนเปิดใช้งานจริง

---

## 6. แผนงานพัฒนาต่อยอด (Future Roadmap & Architectural Guardrails)

### แผนผังลำดับการพัฒนา Phase 13 - 16:

```
[Phase 13: Complete]
       ↓
[Phase 14: Multi-Currency & FX] (Rate Snapshot, Realized/Unrealized Gain/Loss Revaluation)
       ↓
[Phase 15: Advanced Inventory & Barcode/QR] (Stock Transfer, Reorder Alert, Lot/Expiry, Scanner UX)
       ↓
[Phase 16: Payroll, Social Security & Security 2FA] (Salary, ภ.ง.ด.1/1ก, สปส., Payslip, 2FA Auth)
```

### รายละเอียดและ Architectural Guardrails รายโมดูล:

1. **Phase 14: Multi-Currency & FX (ระบบหลายสกุลเงินและอัตราแลกเปลี่ยน):**
   - **Currency Master & Base Currency:** กำหนด Base Currency ต่อองค์กร (ค่าเริ่มต้น THB) และตารางอัตราแลกเปลี่ยนรายวัน
   - **Historical FX Rate Snapshot:** Snapshot อัตราแลกเปลี่ยนลงเอกสาร (Invoice, Payment, Expense, PO, CN/DN) ณ วันที่เกิดรายการ ไม่ใช้ Dynamic Join กับเรตปัจจุบัน
   - **Realized FX Gain/Loss:** คำนวณและบันทึกกำไร/ขาดทุนจากอัตราแลกเปลี่ยนที่เกิดขึ้นจริง ณ วันที่รับชำระหรือจ่ายเงิน (Payment Settlement) เข้า GL
   - **Unrealized FX Revaluation:** คำนวณปรับปรุงมูลค่าลูกหนี้/เจ้าหนี้ต่างประเทศ ณ วันสิ้นงวดบัญชี (Period-End Revaluation)
2. **Phase 15: Advanced Inventory & Barcode/QR Operations:**
   - **Stock Transfer:** โอนย้ายสินค้าระหว่างคลังสินค้าหรือสาขา (Inter-warehouse transfer)
   - **Reorder Point & Low Stock Alert:** กำหนดระดับสต็อกขั้นต่ำและแจ้งเตือนเมื่อสินค้าใกล้หมด
   - **Lot & Expiration Tracking:** คุม Lot Number และวันหมดอายุสินค้า
   - **Barcode / QR Scanner UX:** สแกนรับสินค้า (GRN), จ่ายสินค้า (DO), ปรับยอด (Adjust) พร้อมระบุ Shelf/Bin Location
3. **Phase 16: Payroll, Social Security & Security 2FA:**
   - คำนวณเงินเดือนพนักงาน, ประกันสังคม (สปส.), ภาษีหัก ณ ที่จ่าย ภ.ง.ด. 1 / 1ก และออก Payslip
   - Two-Factor Authentication (2FA / Authenticator OTP) สำหรับบัญชีผู้ดูแลและฝ่ายการเงิน

---

## 7. คำแนะนำสำหรับ AI ในการพัฒนาต่อยอด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 1 และ 2 ของ `gemini.md` โดยเด็ดขาด** (Org Isolation, Redaction, Permission Check, Session Invalidation)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-13 โดยไม่มี Decision/Checklist ใหม่
   - **อนุญาตและให้ดำเนินการได้:** การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production-Blocking Issue ใน Phase ที่ปิดแล้ว โดยต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality:** รัน `php artisan test`, `pnpm run build`, `pnpm run lint`, `pnpm run check-format`, และ `pint` ให้ผ่าน 100% ทุกครั้งที่จบ Slice/Phase
