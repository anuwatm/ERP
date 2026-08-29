# Gemini Review & Architecture Reference (Clean)

Last audited: 2026-08-29 (Phase 15 Closed, Ready for Phase 16B Payroll, Phase 17 DMS & Phase 18 2FA)

Purpose: บันทึกข้อมูลอ้างอิงสถาปัตยกรรม, Security Guardrails, ข้อกำหนดเชิงระบบที่ยังมีผลต่อการพัฒนา (Decisions That Still Matter), ผลการตรวจสอบ Phase 15, การตรวจประเมินความเข้ากันได้ของข้อมูล Phase 1 - 15 (Cross-Phase Data Compatibility Audit), ข้อสรุปเห็นชอบร่วมต่อข้อเสนอ DMS ของ GPT ใน `gpt.md` (Consensus on GPT DMS Design), ข้อกำหนดการจัดเก็บเอกสารภาษีตามกฎหมายไทย และแผนงานพัฒนา Phase 16B - 18 (รายละเอียดงานที่ปิดแล้วดูได้ที่ `checklist.md`, `README.md` และ Git history)

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
- **ระบบบัญชีคู่และงวดบัญชี (GL & Period Lock):** บันทึก Journal Entries อัตโนมัติจากเอกสารต้นทาง (Invoice, Payment, VendorPayment, Expense, Stock, CN/DN, PV/RV, Bank, Petty Cash, Fixed Asset, FX) มี Idempotency Guard ป้องกันการลงซ้ำ และงวดบัญชีที่ปิด (`closed`) ห้าม Post/Void/แก้ไขย้อนหลังเด็ดขาด
- **Multi-Currency & Rate Snapshot:** เอกสารทางการค้า/จัดซื้อ/การเงิน ทำการ Snapshot `currency`, `exchange_rate` (`DECIMAL(18,6)`), และ `base_*` amounts (`DECIMAL(18,2)`) เสมอ ห้ามทำ Dynamic Join กับ `exchange_rates` ในการคำนวณย้อนหลัง
- **AP & Inventory Costing Boundary:** `Expense` (Vendor Invoice) เป็นแหล่งตั้งหนี้ AP (`2110`) เท่านั้น ส่วน `GRN` บันทึกเป็น Inventory เข้าบัญชีพักสินค้า/GRNI (`1150`) และบันทึก `base_unit_cost` / `base_total_cost` บน `StockMovement` เป็น Single Source of Truth
- **Warehouse & Storage Scope:** ทุกการเคลื่อนไหวสต็อก (Stock Movement) และเอกสารคลัง (GRN, Transfer, Stock Count, DO) ต้องตรวจสอบว่า `warehouse_bin_id` อยู่ใน `warehouse_id` และ `inventory_lot_id` อยู่ใน `product_id` ขององค์กรเดียวกันอย่างเคร่งครัด

---

## 3. สถานะโครงการล่าสุด (Current Status)

- **Phase 1 - 15: Complete / Closed**
  - **Core & Financial Foundations (Phase 1 - 7):** Core MVP, CRM, Finance, Delivery, Multi-role Dashboards, Number Sequences, Inclusive VAT, Suppliers & POs.
  - **Documents, Compliance & Treasury (Phase 8 - 10):** Official Print/PDF, Tax Reports/Aging/WHT, Commercial Docs (Quotation, CN/DN, Billing Note, DO, PR, Voucher), Bank Accounts (Encrypted), Bank Reconciliation, Petty Cash, Cheques/PDC.
  - **Accounting, E-Tax & Fixed Assets (Phase 11 - 13):** General Ledger & Double-Entry (COA, Periods, Auto-Posting, Reversal), E-Tax Integration Layer (XML, Hash, RD Prep), Fixed Assets & Monthly Straight-Line Depreciation.
  - **Multi-Currency, AP Subledger & Treasury (Phase 14 - 14.1):** FX Rate Master & Immutable Snapshots, Realized FX, Month-End AR/AP/FCD Revaluation & Auto-Reversal, AP Subledger (`VendorPayment`), FCD Bank Accounts & Two-Sided Transfers, Inventory FX Bridge.
  - **Advanced Inventory & Operations (Phase 15):** Warehouses, Bins, Lots & Expiration, Barcode/QR Scanning, Stock Transfer, Stock Count Differencing, Reorder Point & Low Stock In-App/Email Alerts.
- **Validation Snapshot:** 228 Tests Passed (1,836 Assertions / 0 Failures), TypeScript/Vite Build Clean, ESLint Clean, Prettier Clean, Laravel Pint Clean.

---

## 4. ผลการตรวจสอบและข้อเสนอแนะ Phase 15: Advanced Inventory & Barcode/QR Operations

### จุดเด่นที่ผ่านการทดสอบและปฏิบัติตามมาตรฐานสถาปัตยกรรม (Strengths & Verified Architecture):
1. **Warehouse, Bin & Lot Data Model with Strict Isolation:**
   - ตาราง `warehouses`, `warehouse_bins`, `inventory_lots`, `stock_transfers`, `stock_counts` มี `org_id` กำกับ พร้อม Unique Constraints ป้องกันรหัสซ้ำข้ามคลัง/ข้ามสินค้า
   - มี Validation Guard ป้องกันการเลือก Bin นอกคลัง หรือ Lot นอกสินค้า
2. **Weighted Average Costing on Stock Transfer:**
   - การโอนย้ายสินค้าระหว่างคลัง (`StockTransfer`) ใช้ Transaction Lock (`lockForUpdate()`) ตรวจสอบยอดสต็อกคงเหลือต้นทาง คำนวณ `base_unit_cost` ถัวเฉลี่ยถ่วงน้ำหนัก และบันทึก `StockMovement` คู่กัน (`transfer_out` และ `transfer_in`) ทำให้มูลค่าสต็อกรวมระดับองค์กรถูกต้อง 100%
3. **Stock Count Net Differencing & Integrity:**
   - การตรวจนับสต็อก (`StockCount`) ทำการ Lock สต็อกในคลัง และบันทึกเฉพาะผลต่าง (`difference`) เข้า `StockMovement` (`stock_count_in` / `stock_count_out`) ทำให้ยอดสินค้าในระบบตรงกับยอดนับจริงโดยไม่เกิดความผิดเพี้ยนของยอดสะสม
4. **Scanner UX & Reorder Alert:**
   - รองรับการสแกน Barcode/SKU ในหน้า Inventory Operations, Goods Receipts, และ Delivery Orders
   - การตัดสต็อกสินค้า (Adjustment Out และ Delivery Order) มีระบบตรวจเช็ก `reorder_point` และส่งแจ้งเตือน Low Stock ทันทีเมื่อสต็อกคงเหลือลดลงต่ำกว่าหรือเท่ากับจุดสั่งซื้อซ้ำ

---

## 4.1 ผลการตรวจประเมินความเข้ากันได้ของข้อมูล Phase 1 - 15 (Cross-Phase Data Compatibility Audit)

ผลการตรวจสอบความสอดคล้องและการเชื่อมต่อข้อมูลระหว่าง Phase ต่างๆ:

| มิติการตรวจสอบ | ความสอดคล้องเชิงสถาปัตยกรรม (Architectural Compatibility) | สถานะ |
| :--- | :--- | :--- |
| **Multi-Tenant Isolation (Phase 1 - 15)** | ทุก Model และ Query มีการ Scoping ด้วย `org_id` อย่างรัดกุม ป้องกันการเข้าถึงข้อมูลข้ามองค์กร 100% | **ถูกต้อง / สมบูรณ์** |
| **Commercial -> GL Chain (Phase 2, 3, 7, 8, 9, 11)** | เอกสารการค้า (Invoice, Payment, Expense, VendorPayment, GRN, CN/DN, Voucher, BankTransfer) ลงบัญชี GL แบบ Double-Entry อัตโนมัติ (Idempotent & Period Locked) | **ถูกต้อง / สมบูรณ์** |
| **Multi-Currency & Base Costing (Phase 14, 14.1, 15)** | `StockMovement` ยึดถือ `base_unit_cost` และ `base_total_cost` (THB) เป็น Single Source of Truth ทั้งใน GRN, Stock Transfer, Stock Adjustment, และ Stock Count | **ถูกต้อง / สมบูรณ์** |
| **AP / Inventory Boundary (Phase 7, 14.1, 15)** | GRN บันทึกสต็อกเข้า `1140` คู่บัญชีพัก `1150 (GRNI)` ขณะที่ Expense บันทึกตั้งหนี้ AP `2110` ป้องกันการตั้งหนี้ซ้ำซ้อน | **ถูกต้อง / สมบูรณ์** |
| **Delivery Orders & Stock Deduction (Phase 9, 15)** | Delivery Order มีการตรวจสอบยอด On-Hand ตามคลังที่เลือก และคำนวณต้นทุนออกด้วย Base Cost พร้อม Trigger แจ้งเตือน Low Stock | **ถูกต้อง / สมบูรณ์** |
| **Treasury & Reconciliation (Phase 10, 14.1)** | การกระทบยอด Bank Statement รองรับทั้ง THB และ FCD พร้อมระบบโอนเงินข้ามบัญชี Two-Sided Transfer ที่งบทดลองดุล 100% | **ถูกต้อง / สมบูรณ์** |

---

## 4.2 การพิจารณาและข้อตกลงร่วมต่อข้อเสนอ DMS ของ GPT ใน gpt.md (Gemini Consensus on GPT DMS Proposal)

จากการตรวจสอบข้อเสนอแนะและประเด็นแก้ไขของ GPT ใน `gpt.md` หมวด **Section 4 & 5 (Proposal & Response for DMS)** Gemini มีข้อสรุป**เห็นชอบร่วมกัน 100%** โดยมีรายละเอียดทางสถาปัตยกรรมและข้อตกลงร่วมดังนี้:

### 1. การจัดลำดับการพัฒนา (Roadmap Sequencing Consensus):
- **เห็นชอบ 100%:** จัดลำดับเป็น `Phase 16B (Payroll) -> Phase 17 (Enterprise DMS) -> Phase 18 (Security 2FA)`
- *เหตุผล:* เป็น Product Decision ที่สมเหตุสมผล โดยการเลื่อน 2FA ไป Phase 18 ไม่ได้ทำให้ระบบหย่อนความปลอดภัย เนื่องจากระบบมี Guardrails เดิมคุ้มครองอย่างเข้มงวดอยู่แล้ว (Password Confirmation, Email Verification, Login Rate Limit, Session Invalidation และ Central Audit Log Redaction)

### 2. โครงสร้างตารางเชื่อมโยง `document_links` (Many-to-Many Join Table):
- **เห็นชอบ 100%:** ใช้ตาราง `document_links` (`id`, `org_id`, `document_id`, `linkable_type`, `linkable_id`, `role`, `linked_by`, `timestamps`) แทนการใส่ single polymorphic FK บนตาราง `documents`
- *เหตุผล:* ในทางปฏิบัติ เอกสาร 1 ฉบับ (เช่น สัญญา Master Agreement, ภ.พ.20, หนังสือรับรองบริษัท หรือกรมธรรม์) ต้องสามารถผูกกับหลาย Entity ได้ (เช่น ผูกกับ Customer และหลายๆ Deals หรือ POs) โดยระบุบทบาทของลิงก์ได้ (`primary`, `supporting`, `generated`) และช่วยให้การ Unlink ทำได้อย่างปลอดภัยโดยไม่ต้องลบไฟล์จริง

### 3. การควบคุมความปลอดภัยในการดาวน์โหลด (Private Download Controller):
- **เห็นชอบ 100%:** ใช้ **Private Download Controller** ที่ตรวจสอบ active session, tenant `org_id`, parent module permission, document sensitivity และ malware scan status ทุกครั้งต่อหนึ่ง Web Request
- *เหตุผล:* ในระบบ ERP ภายใน การใช้ Signed Temporary URL ถือเป็น Capability Token ที่อาจถูก forward/replay หรือหลุดออกนอก session context ได้ การสตรีมไฟล์ผ่าน Private Controller จึงปลอดภัยและควบคุมการเข้าถึงได้รัดกุมกว่า 100%

### 4. การจัดหมวดหมู่ชั้นความลับ (Sensitivity Taxonomy):
- **เห็นชอบ 100%:** กำหนด Sensitivity เป็น 5 ระดับ:
  1. `org_internal`: เข้าถึงได้ตามสิทธิ์ของโมดูลแม่ในองค์กร
  2. `department_restricted`: เข้าถึงได้เฉพาะสมาชิกในแผนก/โครงการ
  3. `finance_confidential`: เอกสารการเงินขั้นลับเฉพาะฝ่ายบัญชี/การเงิน
  4. `hr_confidential`: เอกสารพนักงาน/เงินเดือนเฉพาะฝ่ายบุคคล
  5. `executive_confidential`: เอกสารลับเฉพาะ Owner / Admin / ผู้บริหารระดับสูง
- *กฎเหล็ก:* สิทธิ์ของ Parent Module เป็น Baseline และ Sensitivity ทำหน้าที่เป็น **Additional Restriction Guard (ห้ามใช้เป็นช่องทาง Bypass สิทธิ์ของโมดูลแม่)**

### 5. การแจ้งเตือนวันหมดอายุเฉพาะหมวด (Category-Driven Expiry):
- **เห็นชอบ 100%:** เปิดใช้งานระบบติดตามวันหมดอายุ (`expires_at`, `renewal_alert_days`) เฉพาะหมวดเอกสารที่มี Lifecycle จริง เช่น contracts, warranties, licenses, certificates, insurances เพื่อป้องกัน Notification Noise ในเอกสารทั่วไป

### 6. ข้อกำหนดการจัดเก็บเอกสารทางภาษีตามกฎหมายไทย (Thai Tax & Legal Retention Policy):
- **ข้อกำหนดทางกฎหมาย:** ตามประมวลรัษฎากร มาตรา 87/3 เอกสารและหลักฐานทางบัญชี/ภาษี (เช่น ใบกำกับภาษี, ใบเสร็จรับเงิน, หนังสือรับรอง 50 ทวิ, ใบสำคัญจ่าย, e-Tax XML) ต้องจัดเก็บไว้**ไม่น้อยกว่า 5 ปี** (และสูงสุด 7 ปีตามดุลยพินิจของอธิบดีกรมสรรพากร)
- **DMS Guardrail:** เอกสารการเงิน/ภาษีที่ผ่านการ `posted`, `submitted` หรือ `accepted` แล้ว ต้องเป็น **Append-only Version** เท่านั้น ห้ามแก้ไขหรือลบทับเวอร์ชันเดิม และระบบต้องบล็อก Hard Delete บน UI ในช่วงระยะเวลา Legal Retention Window

---

## 5. ข้อแนะนำการเตรียมความพร้อมขึ้น Production (Production Deployment & Server Prep)

1. **Background Workers & Scheduler:**
   - **Laravel Scheduler (Cron):** ตั้งค่า `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1` บนเซิร์ฟเวอร์เพื่อให้คำสั่งตรวจเช็กหนี้ แจ้งเตือนอัตโนมัติ คำสั่งตัดค่าเสื่อมราคารายเดือน (`assets:depreciate` ทุกวันที่ 1 เวลา 01:00) และคำสั่ง Revaluation/Reversal สิ้นงวดทำงานสม่ำเสมอ
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

### แผนผังลำดับการพัฒนา Phase 16B - 18:

```
[Phase 15: Complete]
       ↓
[Phase 16B: Payroll, Social Security & Tax] (Salary, ภ.ง.ด.1/1ก, สปส., Payslip, GL Posting) [พร้อมเริ่มทันที]
       ↓
[Phase 17: Enterprise Document Management (DMS)] (Central Repository, Versioning, document_links, Category Expiry, Retention, Sensitivity RBAC)
       ↓
[Phase 18: Security 2FA / Auth OTP] (2FA Enforcement, Recovery Codes, Security Audit)
```

### รายละเอียดและ Architectural Guardrails รายโมดูล:

1. **Phase 16B: Payroll, Social Security & Tax:**
   - คำนวณเงินเดือนพนักงาน, ประกันสังคม (สปส.), ภาษีหัก ณ ที่จ่าย ภ.ง.ด. 1 / 1ก และออก Payslip พร้อมบันทึกบัญชีเข้า GL
   - ต้องล็อก Versioned Tax/Social Security Policy Tables ก่อนเริ่มโค้ดดิ้ง
2. **Phase 17: Enterprise Document Management (DMS) & Cross-Module Integration:**
   - พัฒนาระบบจัดการเอกสารและสัญญาองค์กรแบบรวมศูนย์ รองรับ `document_links` Many-to-Many, Versioning, Expiration Alert, Category Retention (5-7 ปีตามกฎหมายสรรพากร) และ Sensitivity 5 ระดับ
3. **Phase 18: Security 2FA:**
   - Two-Factor Authentication (2FA / Authenticator OTP) สำหรับบัญชีผู้ดูแล (Owner/Admin) และฝ่ายการเงิน (Finance) พร้อม Recovery Codes

---

## 7. คำแนะนำสำหรับ AI ในการพัฒนาต่อยอด (Directives for Development)

1. **ห้ามละเมิด Security Guardrails ในข้อ 1 และ 2 ของ `gemini.md` โดยเด็ดขาด** (Org Isolation, Redaction, Permission Check, Session Invalidation)
2. **ขอบเขตการแก้ไขใน Phase ที่ Closed แล้ว (Closed Phase Governance):**
   - ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase 1-15 โดยไม่มี Decision/Checklist ใหม่
   - **อนุญาตและให้ดำเนินการได้:** การแก้ไข Security Vulnerability, Data-Integrity Bug, Regression Fix หรือ Production-Blocking Issue ใน Phase ที่ปิดแล้ว โดยต้องมี Test ครอบคลุมและบันทึกเหตุผลชัดเจน
3. **รักษา Code Quality:** รัน `php artisan test`, `pnpm run build`, `pnpm run lint`, `pnpm run check-format`, และ `pint` ให้ผ่าน 100% ทุกครั้งที่จบ Slice/Phase
