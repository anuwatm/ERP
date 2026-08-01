# Gemini Review & Audit Notes: Phase 1, Phase 1.1, Phase 2, Phase 3 & Phase 4 Scope Review

เอกสารนี้สรุปผลการตรวจสอบซอร์สโค้ดภาพรวม และบันทึกประเด็นด้านสถาปัตยกรรม/ความปลอดภัยที่สำคัญเพื่อใช้ในการอ้างอิงและพัฒนาต่อ

วันที่ตรวจสอบล่าสุด: 2026-08-01
สถานะภาพรวมทุก Phase: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**

---

## 1. ข้อมูลอ้างอิงการแก้ไขช่องโหว่ความปลอดภัย (Security Remediations Reference)

รายการช่องโหว่ความปลอดภัยที่ได้รับการตรวจสอบและแก้ไขเสร็จสิ้นแล้ว เพื่อใช้เป็น Reference เชิงความปลอดภัยในการพัฒนาต่อยอด:

| หัวข้อความปลอดภัย | ไฟล์ที่เกี่ยวข้อง | รายละเอียดการควบคุมความปลอดภัย (Security Control) |
| :--- | :--- | :--- |
| **Directory Traversal Protection** | `routes/web.php` | ตรวจสอบ Canonical Path ด้วย `realpath()` และ `str_starts_with($fullPath, $basePath)` ป้องกันการเข้าถึงไฟล์นอกพื้นที่จัดเก็บไฟล์สาธารณะอย่างสมบูรณ์ |
| **Invite Token Protection** | `UserController.php` | ซ่อนและไม่ส่ง Plain Invite Token และ URL ใน Flash Session หากอยู่ในสภาพแวดล้อมที่เป็น `production` |
| **Audit Logs Redaction** | `AuditLog.php` | พัฒนา Central Redaction Guard ใน Eloquent `saving` event เพื่อเซนเซอร์คำสำคัญ (เช่น password, token, secret) และทำ Masking เลขประจำตัวประชาชน (`person_id`) |
| **Upload File Security** | `OrganizationSettingsController.php` | ตรวจสอบนามสกุลและ MIME Type ของไฟล์โลโก้อย่างเขวด ป้องกันไฟล์ที่เป็น Executable หรือ Polyglot File |
| **Session Invalidation** | `UserController.php` <br> `RoleController.php` | เมื่อมีการ Disable บัญชีผู้ใช้ หรือมีการเปลี่ยนแปลง Role หรือปรับปรุงระดับสิทธิ์ (Permissions) ระบบจะทำลายเซสชันเก่าในตาราง `sessions` และล้าง `remember_token` ของผู้ใช้รายนั้นทันทีเพื่อบังคับให้ออกจากระบบ |

---

## 2. รายงานผลการตรวจสอบเชิงลึกและการวิเคราะห์หลังจบ Phase 3 (Phase 3 Post-Completion Audit Notes)

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

* **ความถูกต้องของโครงสร้างฐานข้อมูล (Database Schema & Integrity):**
  * ตาราง `projects` ใช้ UUID PK, มี FK ไปยัง `organizations`, `customers`, `deals` และ `users` อย่างถูกต้อง มีการลบแบบปลอดภัย (`SoftDeletes`)
  * มี Unique Constraint `UNIQUE(org_id, project_code)` และ `UNIQUE(org_id, deal_id)` ป้องกันโครงการซ้ำซ้อน
  * **ตรงตามข้อกำหนดสถาปัตยกรรม AD-05:** ไม่มีคอลัมน์ `actual_cost` ในตาราง และกำหนดให้ `progress_percent` เป็นค่าที่ระบุโดยตรง (Manual-only)
* **การควบคุมการเข้าถึง (Access Control & Permissions):**
  * เจ้าขององค์กร (Owner) และ Admin สามารถเห็นและจัดการโครงการทั้งหมดได้
  * พนักงานส่งมอบ (Project Manager) เห็นและจัดการได้เฉพาะโครงการที่ตนเองดูแล (`owner_id = user.id`)
  * ป้องกันการโอนย้ายเจ้าของโครงการข้ามบุคคล หากไม่มีสิทธิ์ `projects.reassign`

---

## 4. รายงานผลการตรวจสอบ Phase 4: Tasks Core & Security

* **การออกแบบความสัมพันธ์และสิทธิ์งานย่อย (Tasks Schema & Access):**
  * สร้างตาราง `tasks`, `task_checklists` (มีคอลัมน์ `org_id` ป้องกันข้อมูลรั่วไหลข้าม Tenant), และ `task_comments` เรียบร้อยแล้ว
  * **ระดับสิทธิ์การดูงาน:** Owner/Admin เห็นทั้งหมด, Project Manager เห็นงานในโครงการที่ตนเองเป็นเจ้าของหรือได้รับมอบหมาย, ส่วน Member (พนักงานทั่วไป) มองเห็นเฉพาะงานที่ Assign ให้ตนเองเท่านั้น
  * **ข้อจำกัดของพนักงานทั่วไป (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
  * **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ ซึ่งสอดคล้องตามข้อตกลง MVP

---

## 5. รายงานผลการตรวจสอบ Phase 4: Finance-Delivery Link & Dashboard

* **การเชื่อมโยงระบบการเงินกับโครงการ (Finance-Delivery Project Link):**
  * **Invoices & Projects:** มีการ Enforce ความปลอดภัยด้วย Guard `assertProjectMatchesCustomer` ป้องกันการเลือกโครงการข้ามลูกค้า โดยระบบจะสกัดกั้นการเชื่อมโยงใบแจ้งหนี้ไปยังโครงการที่ไม่ได้เป็นของลูกค้ารายนั้น
  * **Expenses & Projects:** ตรวจสอบและจำกัดให้เชื่อมโยงโครงการได้ภายใต้องค์กรเดียวกัน
* **การคำนวณราคาทุนจริงแบบ Dynamic (Dynamic Project Cost):**
  * คำนวณแบบ Dynamic บน Memory ของ Server เสมอจากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`)
  * คำนวณอัตรากำไรขั้นต้น (Gross Margin) แบบเรียลไทม์จากสูตร `budget_amount - actual_cost`
* **ระบบแดชบอร์ดส่งมอบงาน (Delivery Dashboard Metrics):**
  * **การปกป้องข้อมูลการเงินของสมาชิก (Member Shield):** สมาชิกทั่วไปที่มีเพียงสิทธิ์ `tasks.view` (ไม่มี `projects.view`) จะสามารถดูสถิติจำนวนงานที่มอบหมายได้ แต่ค่าสถิติด้านการเงินโครงการทั้งหมด (Budget, Actual Cost, Profit) จะแสดงผลเป็น `0` เสมอ เพื่อความปลอดภัย
  * **ข้อมูล Risk Projects:** การคำนวณความเสี่ยงของโครงการ (Delivery Risk) เช่น งบประมาณเกิน (Over Budget), เกินกำหนดส่ง (Past Due), หรืองานสำคัญด่วน (Urgent Open Tasks) จะถูกคัดกรองในระดับ Unique Project IDs ป้องกันการนับจำนวนโครงการที่มีความเสี่ยงซ้ำซ้อน

---

## 6. รายงานผลการตรวจสอบ Phase 5: Executive Dashboard, Security Hardening & UAT (2026-08-01)

* **ความปลอดภัยและการปกป้องข้อมูลเชิงรุก (Security Hardening):**
  * **การซ่อนความลับใน Audit Logs (Central Redaction Guard):** แก้ไขให้ `AuditLog` model ดักกรองคีย์ข้อมูลที่มีความอ่อนไหวสูง เช่น รหัสผ่าน (password), tokens, secrets และทำการเซนเซอร์เป็น `[REDACTED]` แบบ recursive พร้อมทำการ Masking `person_id` และแคสต์ประเภทข้อมูลอย่างปลอดภัย
  * **ระบบควบคุมการตัดสิทธิ์ทันที (Session Invalidation):** ออกแบบระบบ invalidation ใน `UserController` และ `RoleController` เมื่อมีการปิดใช้งานผู้ใช้งาน (Disable), แก้ไขบทบาท (Role) หรือปรับปรุงสิทธิ์บทบาท (Permissions) ระบบจะทำการล้าง `remember_token` และลบ session เก่าในตาราง `sessions` ทันทีเพื่อบังคับให้ออกจากระบบ
  * **การลดการเปิดเผย Token ชั่วคราว (Invite URL Exposure Shield):** ตรวจสอบสภาพแวดล้อมหากเป็น `production` ระบบจะไม่ทำการบันทึก Plain Invite Token และ URL ใน Flash Session เพื่อป้องกันการเปิดเผยข้อมูลโดยไม่จำเป็น
* **การปกป้องความเป็นส่วนตัวของข้อมูลการเงิน (Financial Isolation & Privacy):**
  * ข้อมูลการเงินรวมทั้งหมดบน Dashboard แสดงผลเฉพาะผู้ที่มีสิทธิ์ `executive.dashboard.view` หรือผู้ดูแลระบบ (Owner/Admin) เท่านั้น พนักงานปกติหรือพนักงานส่งมอบจะไม่สามารถดึงหรือเห็นข้อมูลสรุปการเงินได้เลย
  * โค้ดและ API ทั้งหมดไม่มีการส่งคอลัมน์หรือประมวลผลเรื่อง `Cash Balance` เพื่อป้องกันผลกระทบทางบัญชีตามขอบเขตข้อกำหนดของ MVP
* **ผลการรับรองทางเทคนิค:**
  * ชุดทดสอบทั้งหมด (153 Tests, 1151 Assertions) ทำงานผ่านครบถ้วนสมบูรณ์ 100% ไร้ข้อผิดพลาด
  * ฟอร์แมตโค้ด PHP, JS/TS ทั้งหมดผ่านการรัน Pint, Prettier, ESLint และ Vite Build อย่างราบรื่น

---

## 7. ข้อเสนอแนะและแผนผังการออกแบบกราฟิกบน Dashboard โดยละเอียด (Detailed Dashboard Graphical Enhancement Specification)

เพื่อยกระดับ UI/UX ของระบบให้มีความพรีเมียม สวยงาม และช่วยให้ผู้ใช้งานสามารถทำความเข้าใจข้อมูลปริมาณมากได้ทันที เอกสารนี้จึงจัดทำแผนผังข้อกำหนดโดยละเอียดในการปรับปรุง Dashboard ทุกหน้า (ทั้งระบบแอดมิน, แดชบอร์ดผู้บริหาร, ฝ่ายการเงิน, ฝ่ายส่งมอบงาน และฝ่ายขาย) โดยระบุรูปแบบการแสดงผลที่เหมาะสมในแต่ละจุด (ตัวเลข vs กราฟ) และประเภทของกราฟที่ควรเลือกใช้อย่างเจาะจง:

---

### 1. แดชบอร์ดหลักและผู้บริหาร (Executive & System Overview - `Pages/Dashboard.tsx`)

#### A. ส่วน System Administration Overview (KPI สถิติโครงสร้างระบบ)
*   **Branches, Divisions, Departments, Total Users, Active Users, Invited Users, Roles, Total Audits:**
    *   *รูปแบบการแสดงผล:* **ตัวเลข (Plain Number)** ในกล่อง StatCard
    *   *เหตุผล:* เป็นข้อมูลแจกแจงจำนวนโครงสร้างดิบที่ไม่มีความจำเป็นเชิงเปรียบเทียบในมุมวิเคราะห์ แต่มีสีพื้นหลังไอคอนเพื่อแบ่งประเภทอย่างชัดเจน

#### B. ส่วน Security & System Alerts (ความปลอดภัยของระบบ)
*   **Inactive Users, Pending/Expired Invites, Sensitive Events (24h):**
    *   *รูปแบบการแสดงผล:* **กราฟเกจวัดวงแหวนสีเตือน (Radial Gauge / Spark Indicator Ring) คู่กับตัวเลขสถิติ**
    *   *รายละเอียดกราฟ:* ใช้ SVG Circle Gauge แสดงสัดส่วน Inactive Users ต่อ Total Users และ Expired Invites ต่อ Total Invites โดยใช้สีเตือน (แอมเบอร์และโรส) ตามระดับความด่วน

#### C. ส่วน Executive Dashboard (แดชบอร์ดสรุปผู้บริหาร)
*   **Pipeline Value & Won Value (ยอดท่อขายและยอดปิดการขาย):**
    *   *รูปแบบการแสดงผล:* **กราฟเกจวัดความสำเร็จการขาย (Radial Gauge/Speedometer) คู่กับตัวเลข**
    *   *รายละเอียดกราฟ:* SVG Semitransparent Gauge เปรียบเทียบเป้าหมายหรือค่าเฉลี่ย โดยมีตัวเลขยอดเงินแสดงเด่นชัดตรงกลางวงแหวน
*   **Cash In & Outstanding AR (ยอดกระแสเงินสดและยอดค้างจ่าย):**
    *   *รูปแบบการแสดงผล:* **กราฟแท่งแนวตั้งแบ่งสัดส่วน (Stacked Bar Chart)**
    *   *รายละเอียดกราฟ:* เปรียบเทียบ Cash In (ยอดเก็บเงินได้) กับ Outstanding AR (ยอดหนี้ค้างชำระ) โดยมีเฉดสีเงินย่อยที่เกินกำหนด (Overdue AR) ซ้อนอยู่บนกล่องหนี้ค้างชำระ เพื่อวิเคราะห์สุขภาพกระแสเงินสดได้ในภาพเดียว
*   **Gross Profit (กำไรขั้นต้น):**
    *   *รูปแบบการแสดงผล:* **กราฟแถบเปรียบเทียบกำไรสุทธิ (Progress Bar Gauge)**
    *   *รายละเอียดกราฟ:* แสดงสัดส่วนเปอร์เซ็นต์กำไรจากสูตร `(Gross Profit / Invoiced Revenue) * 100`

---

### 2. แดชบอร์ดฝ่ายการเงิน (Finance Dashboard - `Pages/Dashboard.tsx`)

#### A. ส่วนสรุปตัวเลขทางการเงินหลัก
*   **Invoiced Revenue, Cash In, Outstanding AR, Overdue AR, Expenses, Net Cash Flow, Gross Profit:**
    *   *รูปแบบการแสดงผล:* **ตัวเลข (Formatted Currency/Money) เด่นชัด**
    *   *เหตุผล:* ผู้ใช้งานฝ่ายการเงินจำเป็นต้องเห็นตัวเลขทศนิยมที่แม่นยำชัดเจนที่สุด แต่ควรเพิ่มตัวบ่งชี้ทิศทาง (Mini Sparkline / Up-Down Arrow icon) เทียบสถิติย้อนหลัง

#### B. ส่วนสถานะใบแจ้งหนี้ (Invoice Status)
*   **Invoice Status Count & Value (Draft, Sent, Partially Paid, Paid, Overdue, Void):**
    *   *รูปแบบการแสดงผล:* **กราฟโดนัทเปรียบเทียบส่วนแบ่ง (Donut Chart)**
    *   *รายละเอียดกราฟ:* แสดงสัดส่วนจำนวนใบแจ้งหนี้และยอดเงินตามแต่ละสถานะ โดยแบ่งเฉดสีตามระดับความเสี่ยงทางการเงิน (Paid = เขียว, Overdue = แดง, Sent/Partially Paid = ส้ม/ฟ้า, Void = เทา)

#### C. ส่วนการชำระเงินที่ถูกยกเลิก (Payment Reversal)
*   **Reversal Count & Reversal Amount:**
    *   *รูปแบบการแสดงผล:* **กราฟหลอดวัดอัตราสูญเสียกระแสเงินสด (Loss Level Progress Bar)**
    *   *รายละเอียดกราฟ:* แสดงสัดส่วนเงินที่สูญเสียไปจากการยกเลิกบิล (Reversal Amount) เมื่อเทียบกับเงินสดรับรวมทั้งหมด (Total Cash In) ช่วยสะท้อนระดับความเสียหายในภาพกราฟิก

---

### 3. แดชบอร์ดฝ่ายส่งมอบงาน (Delivery Dashboard - `Pages/Dashboard.tsx`)

#### A. ส่วนการควบคุมงบประมาณโครงการ (Budget Control)
*   **Total Budget vs Actual Cost (งบประมาณกับต้นทุนจริง):**
    *   *รูปแบบการแสดงผล:* **กราฟเส้นแถบเปรียบเทียบสะสม (Budget Burn-up Progress Bar)**
    *   *รายละเอียดกราฟ:* แถบความคืบหน้ายาวเต็มความกว้าง (Width 100%) แสดงเปอร์เซ็นต์งบประมาณที่ถูกใช้ไป หากต้นทุนจริงเกิน 90% ของงบประมาณ แถบจะเปลี่ยนจากสีเขียวเป็นสีส้ม/สีแดงกะพริบเพื่อเตือนความเสี่ยงโครงการ

#### B. ส่วนสถานะโครงการและการจัดสรรงาน (Project Status & Task Load)
*   **Project Status Distribution (สถานะแต่ละโครงการ):**
    *   *รูปแบบการแสดงผล:* **กราฟวงแหวนจำแนกสัดส่วน (Donut Ring Chart)**
    *   *รายละเอียดกราฟ:* แสดงสัดส่วนเปอร์เซ็นต์โครงการตามสถานะ (Completed, Active, On Hold, Cancelled)
*   **Task Load by Assignee (ภาระงานแต่ละคน):**
    *   *รูปแบบการแสดงผล:* **กราฟแท่งแนวนอนจัดอันดับ (Ranked Horizontal Bar Chart)**
    *   *รายละเอียดกราฟ:* แสดงจำนวนงานค้างคาของพนักงานแต่ละคน เรียงลำดับจากภาระงานสูงสุดลงมา เพื่อหาคอขวดของการส่งมอบงานได้ง่ายที่สุด

#### C. ส่วนปัจจัยเสี่ยงโครงการ (Delivery Risk)
*   **Over Budget, Past Due Projects, High/Urgent Open Tasks:**
    *   *รูปแบบการแสดงผล:* **กราฟใยแมงมุม (Radar Risk Chart) หรือ ไอคอนตัวเลขพร้อมสัญญาณไฟเตือนกะพริบ (Pulsing Risk Badges)**
    *   *รายละเอียดกราฟ:* เน้นสัญลักษณ์ Risk Status ด้วยเฉดสีแดงและมีแอนิเมชันปุ่มสั่นไหวเบาๆ เมื่อตัวเลขเกินกว่าศูนย์

---

### 4. แดชบอร์ดฝ่ายขาย (Sales Dashboard - `Pages/Sales/Dashboard.tsx`)

#### A. ส่วนดัชนีชี้วัดผลงานและ Conversion Rate
*   **Won Deals vs Lost Deals (ปิดดีลสำเร็จ/ไม่สำเร็จ):**
    *   *รูปแบบการแสดงผล:* **กราฟพายส่วนแบ่งและตัวเลข (Pie Chart with Conversion Rate Center)**
    *   *รายละเอียดกราฟ:* วงแหวนปิดยอดขายแสดงสัดส่วนดีลที่ Won และ Lost เพื่อสะท้อนอัตราส่วนความสำเร็จในการขาย (Sales Win Rate)
*   **Follow-ups & Stale Deals (งานวันนี้และดีลค้างเติ่ง):**
    *   *รูปแบบการแสดงผล:* **ตัวเลขขนาดใหญ่พร้อมสัญลักษณ์สีเตือน** เพื่อเน้นย้ำความเร่งด่วน

#### B. ส่วนการไหลของดีลขาย (Deals Pipeline Funnel)
*   **Pipeline by Stage (ดีลในแต่ละขั้นขาย):**
    *   *รูปแบบการแสดงผล:* **กราฟกรวยยอดขายแนวราบ (Horizontal Funnel Bar Chart)**
    *   *รายละเอียดกราฟ:* ออกแบบด้วย Flexbox ที่มีความกว้างของแถบเป็นสัดส่วนลดหลั่นกันตามดีลในแต่ละ Stage (ตั้งแต่ Qualification -> Proposal -> Negotiation -> Won/Lost) เพื่อให้เห็นทันทีว่ามีคอขวดของดีลขายหลุดไปในขั้นใดมากที่สุด

#### C. ส่วนผู้นำยอดขาย (Top Sales Owners)
*   **Pipeline Value by Owner:**
    *   *รูปแบบการแสดงผล:* **กราฟแท่งเปรียบเทียบแนวตั้ง/แนวนอน (Leaderboard Bar Chart)**
    *   *รายละเอียดกราฟ:* แสดงส่วนแบ่งมูลค่าดีลขายที่อยู่ในความดูแลของ Sales แต่ละท่านเพื่อจัดอันดับผลงานการขับเคลื่อนยอดขาย (Sales Pipeline Leaderboard)

---

### 5. แนวทางการใช้ SVG & Tailwind CSS ในการสร้างกราฟ (Zero-Dependency SVG Guide)

การใช้ SVG ร่วมกับ Tailwind CSS ช่วยให้แสดงผลกราฟิกที่ตอบสนองได้ดี (Responsive), ปรับสีตามระบบ Light/Dark Mode อัตโนมัติ และทำ Micro-animations ได้ง่าย:

#### A. โครงสร้าง Donut / Circle Progress Ring (วงแหวนแสดงอัตราส่วน)
ใช้ในการแสดง Conversion Rate หรือ Security Alert Ratio ตัวอย่างการคำนวณและเขียน SVG:
```tsx
const radius = 40;
const circumference = 2 * Math.PI * radius; // ~251.2
const strokeDashoffset = circumference - (percentage / 100) * circumference;

return (
    <svg className="w-24 h-24 transform -rotate-90">
        {/* วงแหวนพื้นหลัง */}
        <circle cx="50" cy="50" r={radius} className="stroke-slate-100 dark:stroke-slate-800 fill-none" strokeWidth="8" />
        {/* วงแหวนแสดงค่าจริง */}
        <circle
            cx="50"
            cy="50"
            r={radius}
            className="stroke-indigo-600 fill-none transition-all duration-500 ease-out"
            strokeWidth="8"
            strokeDasharray={circumference}
            strokeDashoffset={strokeDashoffset}
            strokeLinecap="round"
        />
    </svg>
);
```

#### B. แถบความคืบหน้าการใช้เงินงบประมาณ (Budget vs Actual Cost Progress Bar)
ใช้ในส่วนของ Delivery Dashboard เพื่อเปรียบเทียบเงินงบประมาณโครงการที่ใช้จริงกับยอดงบประมาณทั้งหมด:
```tsx
const percentage = Math.min((actualCost / totalBudget) * 100, 100);
const barColor = percentage > 90 ? 'bg-rose-500' : percentage > 75 ? 'bg-amber-500' : 'bg-emerald-500';

return (
    <div className="w-full">
        <div className="flex justify-between text-xs font-bold mb-1">
            <span>Budget Utilized</span>
            <span>{percentage.toFixed(1)}%</span>
        </div>
        <div className="w-full h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
            <div
                className={`h-full ${barColor} transition-all duration-700 ease-out rounded-full`}
                style={{ width: `${percentage}%` }}
            />
        </div>
    </div>
);
```

#### C. กราฟ Funnel สำหรับขั้นของดีล (Sales Deals Funnel Chart)
ใช้แสดงการกระจายตัวของดีลใน Sales Pipeline โดยใช้โครงสร้าง Flexbox + ความกว้างไล่ระดับของแถบตามความคืบหน้า:
```tsx
const maxVal = Math.max(...pipelineByStage.map(p => p.value), 1);

return (
    <div className="space-y-3">
        {pipelineByStage.map((row) => {
            const widthPct = Math.max((row.value / maxVal) * 100, 10); // ป้องกันไม่ให้แถบแคบเกินไป
            return (
                <div key={row.stage} className="space-y-1">
                    <div className="flex justify-between text-xs">
                        <span className="font-semibold text-slate-700 dark:text-slate-200 capitalize">{row.stage}</span>
                        <span className="font-mono text-slate-900 dark:text-white">{row.count} deals ({money(row.value)})</span>
                    </div>
                    <div className="w-full h-6 bg-slate-50 dark:bg-slate-900 rounded border border-slate-200/50 overflow-hidden">
                        <div
                            className="h-full bg-gradient-to-r from-blue-500 to-indigo-600 opacity-90 transition-all duration-500 ease-out"
                            style={{ width: `${widthPct}%` }}
                        />
                    </div>
                </div>
            );
        })}
    </div>
);
```

---

### 6. มาตรฐานการควบคุมความสวยงามเชิงศิลป์ (Design System Guidelines)
เมื่อ GPT หรือผู้พัฒนาดำเนินการเขียนกราฟิกเพิ่ม ขอให้ปฏิบัติตามมาตรฐานดังนี้อย่างเข้มงวด:
*   **Color Harmony:** หลีกเลี่ยงการใช้สีสดที่ไม่ได้ตัดโทนเดี่ยว (เช่น สีแดง/น้ำเงิน/เขียวสดพร้อมกัน) ให้ใช้คู่สีไล่เฉด (Gradients) ในตัวพิจารณา เช่น Indigo-to-Blue หรือ Emerald-to-Teal และในสภาวะแจ้งเตือนอันตรายให้ใช้สีแอมเบอร์และโรสที่ลดระดับความสดลงเพื่อไม่ให้ทำลายสายตาผู้ใช้งาน
*   **Responsiveness:** ขนาดของกราฟิก (ความกว้าง/ความสูงของ SVG) จะต้องครอบคลุมภายใต้หน่วย CSS ที่ยืดหยุ่น (เช่น `w-full h-full`) และใช้กล่อง `viewBox` ของ SVG เสมอ เพื่อให้การขยายภาพไม่แตกตัวเมื่อแสดงผลในหน้าจอมือถือหรือแท็บเล็ต
*   **Premium Micro-animations:** ให้ใช้ `transition-all duration-500 ease-out` บนแถบความคืบหน้าและวงรอบเกจวงกลม เพื่อสร้างมิติการเปลี่ยนผ่านข้อมูลที่ลื่นไหลน่าใช้งาน
