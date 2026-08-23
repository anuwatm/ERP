# Gemini Review & Audit Notes (Clean)

Last cleaned: 2026-08-23 (Phase 8 In Progress)

Purpose: เก็บเฉพาะข้อมูลอ้างอิงสถาปัตยกรรม/ความปลอดภัยหลักที่ยังต้องควบคุม (Security Guardrails) และแผนงานพัฒนาต่อยอด เพื่อความสะอาดและกระชับของบริบท (รายละเอียดงานที่ปิดแล้วสามารถดูได้ที่ `checklist.md` และ Git history)

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

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 7: Complete / Closed** (ครอบคลุม Core MVP, Dashboard แยกตาม Role, Number Sequences, Inclusive VAT, Suppliers & POs, Project Members)
- **Phase 8: In Progress** (เริ่ม implementation ด้วย Official Print/Export foundation และ Tax Reports first pass)
- **Validation Snapshot:** 170 Tests Passed (1,372 Assertions / 0 Failures), TypeScript/Vite Build, ESLint, Prettier, Pint Clean

---

## 4. แผนงานพัฒนาต่อยอด (Phase 8: Production & Accounting Roadmap)

สถานะ: ออกแบบครบแล้วใน `checklist.md`; เริ่ม implementation แล้วในส่วน Official Print/Export foundation และ Tax Reports first pass. ลำดับ implementation แนะนำต่อคือ PDF binary package -> Thai font -> 50-Tawi -> Tax report expansion/WHT/aging -> Notifications/Queues -> Inventory/GRN เว้นแต่ธุรกิจต้องใช้ stock จริงก่อน

### 1. Official Document Print & PDF Export (ระบบพิมพ์เอกสารทางการและภาษี)
- **PDF Layouts:** ออกแบบฟอร์มเอกสารใบกำกับภาษี/ใบเสร็จรับเงิน (Tax Invoice / Receipt), ใบสั่งซื้อ (PO), และใบแจ้งหนี้ (Invoice)
- **Thai Compliance:**
  - แปลงตัวเลขยอดสุทธิเป็นตัวอักษรภาษาไทย (BahtText) เช่น `(หนึ่งหมื่นสองพันสามร้อยสี่สิบห้าบาทถ้วน)`
  - ระบุเลขผู้เสียภาษี 13 หลัก, สำนักงานใหญ่/สาขา, โลโก้, ข้อมูลคู่ค้า และลายเซ็นผู้อนุมัติ
  - รองรับการระบุหัวเอกสาร "ต้นฉบับ (Original)" / "สำเนา (Copy)" และลายน้ำ "VOID" สำหรับเอกสารที่ถูกยกเลิก
  - รองรับแบบฟอร์มหนังสือรับรองการหักภาษี ณ ที่จ่าย (ใบ 50 ทวิ)

### 2. Inventory & Goods Receipt (GRN - การจัดการคลังสินค้า)
- **Goods Receipt Flow:** ระบบรับสินค้าจาก Purchase Order ที่อนุมัติแล้ว พร้อมอัปเดตยอดคงเหลือใน PO (`partially_received` / `received` / `closed`)
- **Stock Movement Ledger:** บันทึก Stock Ledger (Movement Log) ไม่ใช้การแก้ตัวเลขตรงๆ รองรับประเภท movement: รับเข้าจาก PO, ปรับปรุงยอดตรวจนับ (Adjustment In/Out), และส่งคืนผู้ขาย (Return to Supplier)
- **Multi-Warehouse & Costing:** รองรับการจัดเก็บแยกคลัง/สาขา และบันทึกต้นทุนเฉลี่ย (Moving Average Cost) เพื่อคำนวณกำไรขั้นต้นและต้นทุนขาย (COGS)

### 3. Tax & Accounting Reports (รายงานภาษีและวิเคราะห์อายุหนี้)
- **Tax Reports:** รายงานภาษีขาย (Sales Tax Report) และภาษีซื้อ (Purchase Tax Report) สำหรับสรุปยอดและส่งออก Excel/CSV เพื่อยื่น ภ.พ.30
- **Withholding Tax Reports:** รายงานสรุปรายการหัก ณ ที่จ่าย ภ.ง.ด. 3 (บุคคลธรรมดา) และ ภ.ง.ด. 53 (นิติบุคคล)
- **AR / AP Aging Reports:** รายงานวิเคราะห์อายุลูกหนี้ (Accounts Receivable Aging) และอายุเจ้าหนี้ (Accounts Payable Aging) แยกช่วงเวลา (0-30, 31-60, 61-90, >90 วัน)

### 4. Notifications & Background Queues (ระบบแจ้งเตือนและคิวงาน)
- **Alert Triggers:** แจ้งเตือนเมื่อมี Purchase Order รออนุมัติ, ใบแจ้งหนี้ใกล้ครบกำหนด/เกินกำหนดชำระ (Due Soon / Overdue Alerts), มอบหมายงานโครงการ และการเชิญผู้ใช้
- **Multi-Channel:** รองรับทั้ง Email Notifications (Background Queues) และ In-App Notification Bell บนเมนู Navbar
- **Preferences & Safety:** ตั้งค่าเปิด/ปิดการแจ้งเตือนรายบุคคล และมีระบบ Throttling/Deduplication ป้องกันการส่งแจ้งเตือนซ้ำซ้อน
