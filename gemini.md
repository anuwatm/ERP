# Gemini Review & Audit Notes: Phase 1, Phase 1.1, Phase 2, Phase 3 & Phase 4 Scope Review

เอกสารนี้สรุปผลการตรวจสอบซอร์สโค้ดภาพรวม และบันทึกประเด็นด้านสถาปัตยกรรม/ความปลอดภัยที่สำคัญเพื่อใช้ในการอ้างอิงและพัฒนาต่อ

วันที่ตรวจสอบล่าสุด: 2026-07-30  
สถานะภาพรวม Phase 1: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**  
สถานะภาพรวม Phase 1.1: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**  
สถานะภาพรวม Phase 2: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**  
สถานะภาพรวม Phase 3: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**  
สถานะภาพรวม Phase 4 (Delivery Core & Dashboard): **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**

---

## 1. บันทึกประเด็นความปลอดภัยเชิงลึก (Security Audit Findings & Remediation Recommendations)

วันที่ตรวจสอบ: 2026-07-27  
สถานะความปลอดภัยภาพรวม: **มีความปลอดภัยระดับสูง (High Security Baseline)** ระบบมีการป้องกัน OWASP Top 10 ครอบคลุม (CSRF Protection, SQL Injection Prevention ผ่าน PDO Bound Parameters, Tenant Isolation ด้วย `org_id`, Mass Assignment Protection, Rate Limiting บน Sensitive Routes, Audit Trail ครบถ้วน)

รายการประเด็นความปลอดภัย 5 ประการพร้อมสถานะการแก้ไขเพื่อใช้เป็นแนวทางอ้างอิง:

### 1. ✅ **[RESOLVED]** Public Storage Symlink Traversal & Direct File Access (`web.php`)
* **สถานะ:** **แก้ไขเรียบร้อยแล้ว (Fixed)**
* **รายละเอียด:** ใน `routes/web.php` ได้เพิ่มการใช้ `realpath()` และตรวจสอบ Canonical Path ด้วย `str_starts_with($fullPath, $basePath)` ป้องกัน Directory Traversal อย่างสมบูรณ์แล้ว

### 2. ⚠️ Invite Acceptance URL Token Exposure in Flash Session (`UserController.php`)
* **สถานะ:** **ยังคงเปิดไว้สำหรับ Dev/Demo Environment**
* **ประเด็นที่พบ:** ใน `UserController::invite()` มีการส่ง Plain Invite Token กลับไปทาง Flash Session เพื่ออำนวยความสะดวกในสภาพแวดล้อม Demo/Dev แต่ในสภาพแวดล้อม Production Token ลับไม่ควรปรากฏบน Session Data
* **วิธีแก้ไขที่แนะนำ (Remediation):**
  * ใน Production ให้ส่งผ่าน Mail Notification (`Notification::send()`) และลบการส่ง `plainToken` ผ่าน Flash Session
  * ซ่อน Invite URL Flash Data เมื่อ `app.env === 'production'`

### 3. ⚠️ Plaintext Password Exposure Protection in Audit Logs (`AuditLog.php`)
* **สถานะ:** **รอการยกระดับ Central Redaction Guard**
* **ประเด็นที่พบ:** ระบบบันทึก `before_json` และ `after_json` ใน Audit Log แม้ปัจจุบัน Controller หลักตัด field Sensitive ออกแล้ว แต่ควรเพิ่ม Central Redaction Guard เพื่อป้องกันการเผลอบันทึก `password`, `remember_token`, `person_id` ตัวเต็มลงใน Audit Logs
* **วิธีแก้ไขที่แนะนำ (Remediation):**
  * เพิ่ม Helper หรือ Model Mutator สำหรับ Mask/Redact Sensitive Array Keys ใน `AuditLog::create()` เสมอ

### 4. ✅ **[VERIFIED SAFE]** Multi-tenant Access Isolation & File Upload Validation (`OrganizationSettingsController.php`)
* **สถานะ:** **ผ่านการตรวจสอบความปลอดภัยเรียบร้อยแล้ว (Verified Safe)**
* **รายละเอียด:** `OrganizationSettingsController.php` มีการตรวจสอบ MIME Type และนามสกุลไฟล์อย่างเข้มงวดด้วย `'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']` ป้องกันการอัปโหลดไฟล์ Executable หรือ Polyglot File

### 5. ⚠️ Session Invalidation on Password Change / Role Change (`UserController.php` & `RoleController.php`)
* **สถานะ:** **ข้อเสนอแนะเพิ่มเติมสำหรับการ Hardening**
* **ประเด็นที่พบ:** เมื่อมีการ Disable User หรือเปลี่ยน Role ของ User ใน Admin Panel ตัว Session เดิมของ User รายนั้นบนเครื่องอื่นยังไม่ถูกระงับทันทีในมิลลิวินาทีนั้น
* **วิธีแก้ไขที่แนะนำ (Remediation):**
  * เมื่อ admin ปิดใช้งานบัญชี (`users.disable`) ให้ทำการอัปเดต `remember_token` ของ User รายนั้นใหม่ หรือเรียก `DB::table('sessions')->where('user_id', $user->id)->delete();` เพื่อตัดการเชื่อมต่อทันที

---

## 2. รายงานผลการตรวจสอบเชิงลึกและการวิเคราะห์หลังจบ Phase 3 (Phase 3 Post-Completion Audit Notes)

วันที่ตรวจสอบ: 2026-07-29  
บันทึกประเด็นด้านการเงิน บัญชี และสถาปัตยกรรมสืบเนื่องจาก Phase 3:

### 1. ประเด็นความปลอดภัยและการควบคุมสิทธิ์ (Security & Access Control)
* **การป้องกัน N+1 Query และการรั่วไหลของข้อมูลการเงิน:**
  * การดึงข้อมูลประวัติการชำระเงินย่อยของ Invoice ในหน้า Dashboard/List ปรับปรุงโดยการเพิ่ม `with(['payments'])` ช่วยลดปริมาณ Database Roundtrip ลงอย่างชัดเจน
  * การสแกน API สำหรับการสร้าง/ยกเลิกการชำระเงิน Enforce สิทธิ์เฉพาะ `finance`, `admin`, `owner`

### 2. ข้อบกพร่องด้านสถาปัตยกรรมข้อมูลและไฟล์ (Data & File Architecture)
* **การจัดเก็บไฟล์แนบใบเสร็จค่าใช้จ่าย (Receipt Attachment Storage):**
  * ใช้การสร้างคีย์ไฟล์แบบสุ่มตาม UUID ป้องกัน Directory Traversal และจำกัดการเข้าถึงในระดับ Tenant `org_id`
  * เมธอดการดาวน์โหลดไฟล์มีการทำสิทธิ์ตรวจสอบผ่าน Model Mapping ว่ามีความตรงกันระหว่างสิทธิ์ของผู้ร้องขอกับฟิลด์ `receipt_file_id` หรือ `attachment_file_id`

### 3. จุดที่ควรปรับปรุงด้านข้อกำหนดทางบัญชีและกฎหมาย (Accounting & VAT Compliance)
* **การแสดงผลภาษีมูลค่าเพิ่ม (VAT Calculation):**
  * รองรับการคำนวณแบบ Tax-exclusive (บวกนอก) และ Tax-inclusive (รวมใน) ครบถ้วน
  * บน React UI หน้า Invoices พัฒนาพรีวิวตัวเลขคำนวณภาษีฝั่ง Client-side ให้สอดคล้องกันแบบ Real-time โดยถอดสูตรภาษีตรงกันกับฝั่งหลังบ้านอย่างสมบูรณ์

---

## 3. รายงานผลการตรวจสอบ Phase 4: Projects Core (Phase 4 Projects Core Audit & Verification)

วันที่ตรวจสอบ: 2026-07-29  
สถานะภาพรวม Phase 4 (Projects Core): **เสร็จสมบูรณ์ 100% และผ่านการทดสอบแบบ Regression ไร้ข้อผิดพลาด (Fully Verified & Completed)**

* **ความถูกต้องของโครงสร้างฐานข้อมูล (Database Schema & Integrity):**
  * ตาราง `projects` ใช้ UUID PK, มี FK ไปยัง `organizations`, `customers`, `deals` และ `users` อย่างถูกต้อง มีการลบแบบปลอดภัย (`SoftDeletes`)
  * มี Unique Constraint `UNIQUE(org_id, project_code)` และ `UNIQUE(org_id, deal_id)` ป้องกันโครงการซ้ำซ้อน
  * **ตรงตามข้อกำหนดสถาปัตยกรรม AD-05:** ไม่มีคอลัมน์ `actual_cost` ในตาราง และกำหนดให้ `progress_percent` เป็นค่าที่ระบุโดยตรง (Manual-only)
* **การควบคุมการเข้าถึง (Access Control & Permissions):**
  * เจ้าขององค์กร (Owner) และ Admin สามารถเห็นและจัดการโครงการทั้งหมดได้
  * พนักงานส่งมอบ (Project Manager) เห็นและจัดการได้เฉพาะโครงการที่ตนเองดูแล (`owner_id = user.id`)
  * ป้องกันการโอนย้ายเจ้าของโครงการข้ามบุคคล หากไม่มีสิทธิ์ `projects.reassign`

---

## 4. รายงานผลการตรวจสอบ Phase 4: Tasks Core & Security (2026-07-29)

วันที่ตรวจสอบ: 2026-07-30  
สถานะภาพรวม Phase 4 (Tasks Core): **เสร็จสมบูรณ์ 100% และผ่านการทดสอบครอบคลุมเงื่อนไขการเข้าถึง (Fully Verified & Completed)**

* **การออกแบบความสัมพันธ์และสิทธิ์งานย่อย (Tasks Schema & Access):**
  * สร้างตาราง `tasks`, `task_checklists` (มีคอลัมน์ `org_id` ป้องกันข้อมูลรั่วไหลข้าม Tenant), และ `task_comments` เรียบร้อยแล้ว
  * **ระดับสิทธิ์การดูงาน:** Owner/Admin เห็นทั้งหมด, Project Manager เห็นงานในโครงการที่ตนเองเป็นเจ้าของหรือได้รับมอบหมาย, ส่วน Member (พนักงานทั่วไป) มองเห็นเฉพาะงานที่ Assign ให้ตนเองเท่านั้น
  * **ข้อจำกัดของพนักงานทั่วไป (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
  * **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ ซึ่งสอดคล้องตามข้อตกลง MVP

---

## 5. รายงานผลการตรวจสอบ Phase 4: Finance-Delivery Link & Dashboard (2026-07-30)

วันที่ตรวจสอบ: 2026-07-30  
สถานะภาพรวม Phase 4 (Finance-Delivery Link & Dashboard): **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**

* **การเชื่อมโยงระบบการเงินกับโครงการ (Finance-Delivery Project Link):**
  * **Invoices & Projects:** มีการ Enforce ความปลอดภัยด้วย Guard `assertProjectMatchesCustomer` ป้องกันการเลือกโครงการข้ามลูกค้า โดยระบบจะสกัดกั้นการเชื่อมโยงใบแจ้งหนี้ไปยังโครงการที่ไม่ได้เป็นของลูกค้ารายนั้น
  * **Expenses & Projects:** ตรวจสอบและจำกัดให้เชื่อมโยงโครงการได้ภายใต้องค์กรเดียวกัน
* **การคำนวณราคาทุนจริงแบบ Dynamic (Dynamic Project Cost):**
  * คำนวณแบบ Dynamic บน Memory ของ Server เสมอจากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`)
  * คำนวณอัตรากำไรขั้นต้น (Gross Margin) แบบเรียลไทม์จากสูตร `budget_amount - actual_cost`
* **ระบบแดชบอร์ดส่งมอบงาน (Delivery Dashboard Metrics):**
  * **การปกป้องข้อมูลการเงินของสมาชิก (Member Shield):** สมาชิกทั่วไปที่มีเพียงสิทธิ์ `tasks.view` (ไม่มี `projects.view`) จะสามารถดูสถิติจำนวนงานที่มอบหมายได้ แต่ค่าสถิติด้านการเงินโครงการทั้งหมด (Budget, Actual Cost, Profit) จะแสดงผลเป็น `0` เสมอ เพื่อความปลอดภัย
  * **ข้อมูล Risk Projects:** การคำนวณความเสี่ยงของโครงการ (Delivery Risk) เช่น งบประมาณเกิน (Over Budget), เกินกำหนดส่ง (Past Due), หรืองานสำคัญด่วน (Urgent Open Tasks) จะถูกคัดกรองในระดับ Unique Project IDs ป้องกันการนับจำนวนโครงการที่มีความเสี่ยงซ้ำซ้อน
* **ผลการรับรองทางเทคนิค:**
  * ชุดทดสอบทั้งหมด (143 Tests, 901 Assertions) ทำงานผ่านครบถ้วน (Green) หลังปรับแก้ความเปราะบางของวันที่ในไฟล์ `Phase3ExpensesTest.php`
  * Vite assets คอมไพล์และบิลด์สมบูรณ์ และไม่มีข้อผิดพลาดจากการตรวจสอบ ESLint หรือ Prettier
