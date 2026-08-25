# Gemini Review & Audit Notes (Clean)

Last cleaned: 2026-08-25 (Phase 9 Closed)

Purpose: เก็บเฉพาะข้อมูลอ้างอิงสถาปัตยกรรม/ความปลอดภัยหลักที่ยังต้องควบคุม (Security Guardrails), สถานะโครงการล่าสุด, ข้อคิดเห็น/ข้อสังเกตเชิงสถาปัตยกรรม (Architectural Observations & Feedback), ข้อปฏิบัติในการขึ้น Production และแผนงานพัฒนาต่อยอด เพื่อความสะอาดและกระชับของบริบท (รายละเอียดงานที่ปิดแล้วสามารถดูได้ที่ `checklist.md` และ Git history)

---

## 1. ข้อมูลอ้างอิงการควบคุมความปลอดภัย (Security Guardrails Reference)

รายการควบคุมความปลอดภัยที่ต้องคงอยู่ในการพัฒนาต่อยอดทุกส่วน:

| หัวข้อความปลอดภัย | รายละเอียดการควบคุมความปลอดภัย (Security Control) |
| :--- | :--- |
| **Directory Traversal Protection** | ตรวจสอบ Canonical Path ด้วย `realpath()` และ `str_starts_with($fullPath, $basePath)` ป้องกันการเข้าถึงไฟล์นอกพื้นที่จัดเก็บไฟล์สาธารณะอย่างสมบูรณ์ |
| **Invite Token Protection** | ซ่อนและไม่ส่ง Plain Invite Token และ URL ใน Flash Session หากอยู่ในสภาพแวดล้อมที่เป็น `production` |
| **Audit Logs Redaction** | พัฒนา Central Redaction Guard ใน Eloquent `saving` event เพื่อเซนเซอร์คำสำคัญ (เช่น password, token, secret) และทำ Masking เลขประจำตัวประชาชน (`person_id`) |
| **Upload File Security** | ตรวจสอบนามสกุลและ MIME Type ของไฟล์โลโก้อย่างเข้มงวด ป้องกันไฟล์ที่เป็น Executable หรือ Polyglot File |
| **Session Invalidation** | เมื่อมีการ Disable บัญชีผู้ใช้ หรือมีการเปลี่ยนแปลง Role หรือปรับปรุงระดับสิทธิ์ (Permissions) ระบบจะทำลายเซสชันเก่าในตาราง `sessions` และล้าง `remember_token` ของผู้ใช้รายนั้นทันทีเพื่อบังคับให้ออกจากระบบ |
| **Financial Isolation & Privacy** | ข้อมูลการเงินรวมทั้งหมดบน Dashboard แสดงผลเฉพาะผู้ที่มีสิทธิ์ `executive.dashboard.view` หรือผู้ดูแลระบบ (Owner/Admin) เท่านั้น พนักงานปกติหรือพนักงานส่งมอบทั่วไปจะไม่มีการดึงหรือเห็นข้อมูลสรุปการเงินได้เลย และไม่มีการส่งคอลัมน์หรือประมวลผลเรื่อง `Cash Balance` |

---

## 2. โครงสร้างและการเข้าถึงข้อมูล (Architecture & Access Scope)

- **การคำนวณราคาทุนจริงโครงการ (Dynamic Project Cost):** คำนวณแบบ Dynamic บน Memory จากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`) และห้ามมีคอลัมน์ `actual_cost` ในตาราง `projects`
- **การจำกัดสิทธิ์พนักงาน (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
- **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ
- **ความถูกต้องทางภาษีและการค้า (Tax & Commercial Integrity):** ห้ามใช้วิธี Void กับใบกำกับภาษีหรือเอกสารการค้าทางการที่ส่งมอบหรือยื่นรอบบัญชีแล้ว ให้ใช้วิธีออก Credit Note / Debit Note เพื่อปรับยอดหนี้และกระทบยอดภาษีขายตามกฎหมายสรรพากร

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 9: Complete / Closed**
  - **Core & Operations (Phase 1 - 7):** Core MVP, Multitenancy, Dashboards แยกตามบทบาท, Number Sequences, Inclusive VAT, Procurement & Suppliers, Project Members.
  - **Official Documents & Compliance (Phase 8):** Official Print/PDF (Invoice, Tax Invoice/Receipt, PO, 50-Tawi WHT), Inventory/GRN/Costing, Tax Reports/Aging/WHT, In-App & Queued Notifications.
  - **Commercial & Procurement Documents (Phase 9):** Quotations & Convert to Invoice, Credit Note / Debit Note (กระทบ Invoice balance & ภ.พ.30), Billing Note / Statement of Account, Delivery Order (ตัดสต็อก Outbound & บันทึกหลักฐานผู้รับของ), Purchase Request (PR -> Approve -> Convert to PO), Payment/Receipt Voucher (PV/RV) พร้อม Print/PDF และ Security/Audit Isolation.
- **Validation Snapshot:** 195 Tests Passed (1,625 Assertions / 0 Failures), TypeScript/Vite Build Clean, ESLint Clean, Prettier Clean, Pint Clean.
- *(รายละเอียดประวัติงานที่ปิดแล้วใน Phase 1 - 9 ดูได้ที่ `checklist.md` และ Git history)*

---

## 4. ข้อคิดเห็นและข้อสังเกตจาก Phase 9 (Phase 9 Architectural Feedback & Review Notes)

จากการตรวจสอบการทำงานจริงและโครงสร้างของ Phase 9 มีข้อสังเกตและคำแนะนำเชิงสถาปัตยกรรมสำหรับเตรียมเชื่อมต่อไปยัง Phase 10 และ Phase 11 ดังนี้:

1. **Credit Note (CN) กับการรับคืนสินค้า (Sales Return vs Stock Inbound):**
   - *สถานะปัจจุบัน:* CN มีการคำนวณปรับลดหนี้ของ Invoice และปรับลดยอดภาษีขายในรายงาน ภ.พ.30 ได้อย่างสมบูรณ์แบบ
   - *ข้อสังเกตสำหรับ Phase 11 (GL):* หากการออก CN มีกรณีที่เกิดจากการ "รับคืนสินค้ากลับเข้าคลัง" ควรให้ผู้ใช้งานทำ Stock Adjustment (Type: In) ควบคู่ไปด้วย เพื่อให้ทั้งยอดสต็อก On-hand และการลงบัญชีคู่ตัดต้นทุนขาย (COGS Reversal) ใน Phase 11 สอดคล้องกันอย่างถูกต้อง
2. **Billing Note (ใบวางบิล) กับสถานะการชำระเงิน (Settlement Visibility):**
   - *สถานะปัจจุบัน:* รวม Invoice ค้างชำระของลูกค้ารายเดียวกันได้ถูกต้อง พร้อมหน้าพิมพ์ใบวางบิล
   - *คำแนะนำต่อยอด:* ใน Phase 10 เมื่อมีการรับชำระเงินผ่าน Bank Account หรือ Cheque แล้ว สามารถเพิ่มตัวกรองหรือ Badge สถานะ "Paid / Partially Paid" บนรายการบิลที่วางบิลไปแล้วเพื่อให้ฝ่ายการเงินติดตามยอดง่ายขึ้น
3. **Delivery Order (DO) และการป้องกันสต็อกติดลบ:**
   - *สถานะปัจจุบัน:* DO มีการตัด Stock Outbound เมื่อเอกสารเปลี่ยนเป็นสถานะ `delivered` พร้อมบันทึกหลักฐานผู้รับของ และมี Validation ป้องกันสต็อกติดลบ
   - *ข้อสังเกตสำหรับ Phase 15 (Barcode/QR):* โครงสร้างของ DO Item เตรียมพร้อมรองรับการต่อยอดสแกน Serial/Lot/Bin Location ได้ทันทีใน Phase 15
4. **Purchase Request (PR) ➔ Purchase Order (PO) 1:1 Flow:**
   - *สถานะปัจจุบัน:* การอนุมัติ PR และแปลงเป็น PO ทำงานแบบ 1:1 ป้องกันการสร้าง PO ซ้ำจาก PR เดิมได้อย่างปลอดภัย ครอบคลุมการใช้งานของธุรกิจทั่วไป
5. **Payment Voucher (PV) / Receipt Voucher (RV) สำหรับการเชื่อม Double-Entry (Phase 11):**
   - *สถานะปัจจุบัน:* จัดเก็บบันทึกข้อมูล (Records), Audit Log, และพิมพ์ใบสำคัญรับ-จ่ายเงิน (Print/PDF) พร้อมโครงสร้างที่เชื่อมโยงกับเอกสารต้นทาง (Payment/Expense) โดยระบบแนบไฟล์สลิป/หลักฐานโดยตรงบนตัว Voucher จะบรรจุเข้าสู่ Backlog ของ Phase 10
   - *ข้อสังเกตสำหรับ Phase 11:* โครงสร้างข้อมูลของ PV/RV ในปัจจุบันเหมาะสมมากสำหรับใช้เป็น Trigger Document ในการ Auto-Post บัญชีแยกประเภท (Dr. Expense/Vendor / Cr. Bank/Cash/WHT Payable) เมื่อก้าวเข้าสู่ระบบ General Ledger

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
[Phase 9: Complete]
       ↓
[Phase 10: Treasury, Banking & Cash Management] (Bank Accounts, Bank Reconciliation, Petty Cash, Cheque PDC)
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

1. **Phase 10: Treasury, Banking & Cash Management (การเงิน, ธนาคาร, เงินสดย่อย และเช็ค):**
   - **Bank Accounts Master:** ตารางสมุดบัญชีธนาคารบริษัท แยกสาขา/เลขบัญชี สำหรับตัดเงินรับชำระและจ่ายเงิน
   - **Bank Reconciliation:** นำเข้า Bank Statement กระทบยอดเงินฝากธนาคารกับบันทึกในระบบ
   - **Petty Cash (เงินสดย่อย):** วงเงินสดย่อยออฟฟิศ, การบันทึกใบเบิกเงินสดย่อย และการเบิกชดเชยเงินสดย่อย
   - **Cheque Management (ทะเบียนเช็ค):** คุมเช็ครับ/เช็คจ่ายล่วงหน้า (Post-Dated Cheque - PDC), สถานะรอเรียกเก็บ, เช็คผ่าน (Cleared), และเช็คเด้ง (Bounced)
2. **Phase 11: General Ledger (GL) & Double-Entry Accounting:**
   - **Chart of Accounts & Journal Posting:** ผังบัญชี และบันทึก Journal Entries อัตโนมัติจาก Invoice, Payment, Expense, Stock, CN/DN, PV/RV, Bank, และ Petty Cash
   - **Accounting Period & Period Lock:** ตาราง `accounting_periods` พร้อมสถานะ `open` / `closed` และ Guardrail ห้ามแก้ไข/Void/Post เอกสารการเงินย้อนหลังเข้าสู่งวดที่ปิดแล้ว
   - **Immutable Posting & Reversal Pattern:** Journal Entry ที่ `posted` แล้วห้ามแก้ตัวเลขเด็ดขาด หากผิดพลาดต้องออก Reversal Journal เท่านั้น
   - **Posting Idempotency:** ป้องกันการ Post ซ้ำซ้อนจากเอกสารต้นทางเดิม (ผูก `source_type` + `source_id`)
3. **Phase 12: E-Tax & RD Online Tax Filing (ภาษีอิเล็กทรอนิกส์และการยื่นภาษีออนไลน์):**
   - **E-Tax Invoice / E-Receipt:** สร้างไฟล์ XML ตามมาตรฐาน ETDA และลงลายมือชื่อดิจิทัล (Digital Signature) รองรับ e-Tax by Email / RD API
   - **RD Prep Text Export:** ส่งออกไฟล์ Text รูปแบบมาตรฐานของโปรแกรม RD Prep สรรพากร เพื่อใช้อัปโหลดยื่นภาษี ภ.ง.ด. 1, ภ.ง.ด. 3, และ ภ.ง.ด. 53 ทางอินเทอร์เน็ต
4. **Phase 13: Fixed Assets & Depreciation:**
   - ทะเบียนทรัพย์สินบริษัท และคำนวณค่าเสื่อมราคารายเดือน (Straight-Line) Post เข้า GL อัตโนมัติ
5. **Phase 14: Multi-Currency & FX:**
   - **Historical FX Rate Snapshot:** Snapshot อัตราแลกเปลี่ยนลงเอกสาร ณ วันที่เกิดรายการ ไม่ใช้ Dynamic Join
   - **Realized vs Unrealized FX:** แยกการรับรู้กำไร/ขาดทุนจากอัตราแลกเปลี่ยนจริง และเมื่อสิ้นงวดบัญชี
6. **Phase 15: Advanced Inventory & Barcode/QR Operations:**
   - **Stock Transfer:** โอนย้ายสินค้าระหว่างคลังสินค้าหรือสาขา (Inter-warehouse transfer)
   - **Reorder Point & Low Stock Alert:** กำหนดระดับสต็อกขั้นต่ำและแจ้งเตือนเมื่อสินค้าใกล้หมด
   - **Lot & Expiration Tracking:** คุม Lot Number และวันหมดอายุสินค้า
   - **Barcode / QR Scanner UX:** สแกนรับสินค้า (GRN), จ่ายสินค้า (DO), ปรับยอด (Adjust) พร้อมระบุ Shelf/Bin Location
7. **Phase 16: Payroll, Social Security & Security 2FA:**
   - คำนวณเงินเดือนพนักงาน, ประกันสังคม (สปส.), ภาษีหัก ณ ที่จ่าย ภ.ง.ด. 1 / 1ก และออก Payslip
   - Two-Factor Authentication (2FA / Authenticator OTP) สำหรับบัญชีผู้ดูแลและฝ่ายการเงิน

---

## 7. คำแนะนำสำหรับ AI ในการพัฒนาต่อยอด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 1 และ 2 ของ `gemini.md` โดยเด็ดขาด** (Org Isolation, Redaction, Permission Check, Session Invalidation)
2. **ห้ามแก้ไขประวัติที่ Closed แล้วใน Phase 1 - 9** ให้ต่อยอดใน Phase ใหม่ตามลำดับ
3. **รักษา Code Quality:** รัน `php artisan test`, `pnpm run build`, `pnpm run lint`, `pnpm run check-format`, และ `pint` ให้ผ่าน 100% ทุกครั้งที่จบ Slice/Phase
