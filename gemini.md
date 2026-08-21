# Gemini Review & Audit Notes (Clean)

Last cleaned: 2026-08-01

Purpose: เก็บเฉพาะข้อมูลอ้างอิงสถาปัตยกรรม/ความปลอดภัยหลักที่ยังต้องควบคุม (Security Guardrails) และแผนงานที่ยังไม่ได้ทำ เพื่อความสะอาดของบริบท

---

## 1. ข้อมูลอ้างอิงการควบคุมความปลอดภัย (Security Guardrails Reference)

รายการควบคุมความปลอดภัยที่ต้องคงอยู่ในการพัฒนาต่อยอด:

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

## 3. แผนงานที่ยังไม่ได้ทำ (Pending Phase 6 Tasks)

ตามแผนงานของ Phase 6 ส่วนที่ยังไม่ได้เริ่มพัฒนา:

- [x] **Number format expansion:** ขยายขนาดฟิลด์ `invoice_no` / `expense_no` จาก `char(6)` เป็น `varchar(30)`
- [x] **Tax / Invoice Compliance first pass:** แสดงผล Inclusive VAT และการจัดสรรภาษีมูลค่าเพิ่มสำหรับส่วนลดท้ายบิล (Header discount VAT allocation)

---

## 4. รายการที่ต้องแก้ไขปรับปรุง (Pending Fixes & Improvements)

*ไม่มีข้อผิดพลาดพบจากการทดสอบ PHPUnit, ESLint, Pint หรือการคอมไพล์ในเบราว์เซอร์*

- [ ] **Configurable Number Sequences (ปรับแต่งรูปแบบเลขที่เอกสาร):** ปรับปรุง `NumberSequenceService` และหน้าตั้งค่าองค์กรให้รองรับรูปแบบกำหนดเอง (เช่น `INV-YYYYMM-00001` หรือ `EXP-YY-0001`) เพื่อใช้ประโยชน์จากฟิลด์ `varchar(30)` ที่ขยายขนาดในฐานข้อมูลเรียบร้อยแล้ว
- [ ] **Inclusive VAT UI Subtotal Display (การแสดงผลราคาก่อนและหลังภาษี):** ปรับปรุงหน้าจอสร้าง/ดูใบแจ้งหนี้เพื่อแสดงราคาสุทธิไม่รวมภาษี (Net Subtotal) และภาษีมูลค่าเพิ่มที่ซ่อนอยู่แยกให้ชัดเจนตามมาตรฐานสรรพากร กรณีเลือก `Inclusive VAT`
- [ ] **Project Members Assignment (ดีเฟอร์จาก Phase 4):** เพิ่มตารางเชื่อมโยง `project_members` เพื่อรองรับการระบุสมาชิกหลายคนในหนึ่งโครงการ แทนการตรวจสอบอิงเฉพาะ Owner/Assignee
- [ ] **Suppliers and Purchase Orders (ดีเฟอร์จาก Phase 3):** พัฒนาระบบคู่ค้า (Suppliers) และใบสั่งซื้อ (Purchase Orders) เพื่อให้โมดูลรายจ่าย (Expenses) สามารถอ้างอิงข้อมูลได้อย่างถูกต้องและรองรับการเก็บ Inventory ในอนาคต


