# Company OS / Lightweight ERP

ระบบ ERP ขนาดเบาสำหรับ SME, บริษัทบริการ, software agency, studio, ทีมจัดซื้อ/คลังสินค้า และทีมส่งมอบงานภายในองค์กร

ระบบนี้ออกแบบให้ครอบคลุม flow หลักของธุรกิจบริการและการค้า:

```text
Invite user -> Customer -> Deal -> Quotation -> Invoice / Billing Note / Delivery Order -> Payment / Reversal / Bank Reconciliation -> Project / Project Members -> Task -> Goods Receipt / Inventory -> Tax Reports & 50-Tawi -> Treasury (Bank/Petty Cash/Cheque) -> Dashboards
```

สถานะล่าสุด: **Phase 11 Done** (Phases 0 ถึง 11 เสร็จสมบูรณ์แล้ว | Production Prep และ Phases 12–16 อยู่ในแผน Roadmap)
ฟีเจอร์หลักในระบบครอบคลุมตั้งแต่ Foundation, Multi-tenant, Auth, RBAC, Master Data, CRM, Sales Pipeline, Invoicing & VAT Compliance, Payments & Reversals, Expenses, Projects & Project Members, Tasks & Collaboration, Executive/Finance/Delivery/Sales/Admin Dashboards, Reporting Filters, Configurable Number Sequences, Suppliers, Purchase Orders, Official PDF/Print (Invoice, Tax Invoice/Receipt, PO, 50-Tawi), Inventory & Goods Receipts (GRN & Stock Ledger), Tax & Aging Reports (Sales Tax ภ.พ.30, Purchase Tax, WHT ภ.ง.ด. 3/53, AR/AP Aging), In-App Notifications & Mail Queues, Commercial & Procurement Documents (Quotations, CN/DN, Billing Notes, Delivery Orders, Purchase Requests, Vouchers) และ Treasury/Banking/Cash Management (Bank Accounts, CSV Statement Reconciliation, Petty Cash, Cheques/PDC, Voucher Attachments, Treasury Reports)

เอกสารสถานะงานหลักอยู่ที่ [`checklist.md`](checklist.md)

---

## ใช้ทำงานอะไร

โปรเจกต์นี้ใช้เป็นระบบบริหารองค์กรแบบรวมศูนย์ (Company OS) ที่เชื่อมโยงทุกแผนกเข้าด้วยกัน:

- **ฝ่ายบริหาร (Executive & Management)**: เห็นภาพรวมสุขภาพบริษัท รายได้ ค่าใช้จ่าย กำไร กระแสเงินสด Pipeline โครงการ ความเสี่ยง และ Task Overdue แบบ Real-time
- **ฝ่ายขาย (Sales & CRM)**: จัดการรายชื่อลูกค้า (Customers), ผู้ติดต่อ (Contacts), ดีลการขาย (Deals Pipeline), ใบเสนอราคา (Quotations), กิจกรรมติดตาม (Activities/Follow-ups)
- **ฝ่ายบัญชีและการเงิน (Finance & Treasury)**: ออกใบแจ้งหนี้ (Invoices), ใบลดหนี้/เพิ่มหนี้ (CN/DN), ใบวางบิล (Billing Notes), รับชำระเงิน (Payments), ใบสำคัญรับ/จ่าย (PV/RV), บันทึกค่าใช้จ่าย (Expenses), จัดการบัญชีธนาคาร (Bank Accounts), กระทบยอดสเตทเมนต์ (Bank Reconciliation), เงินสดย่อย (Petty Cash), ทะเบียนเช็ค (Cheques/PDC) และออกรายงานภาษี/อายุหนี้ (Tax & Aging Reports)
- **ฝ่ายจัดซื้อและคลังสินค้า (Procurement & Inventory)**: จัดการคู่ค้า (Suppliers), ใบขอซื้อ (Purchase Requests), ใบสั่งซื้อ (Purchase Orders), ใบรับสินค้าเข้าคลัง (Goods Receipts/GRN), ทะเบียนการเคลื่อนไหวสต็อก (Stock Movement Ledger) และคำนวณต้นทุนเฉลี่ย (Moving Average Cost)
- **ฝ่ายปฏิบัติการและส่งมอบ (Delivery & Operations)**: จัดการโครงการ (Projects) จาก Won Deals, จัดสรรทีมงาน (Project Members), บริหารงานย่อย (Tasks, Checklists, Comments) และควบคุมต้นทุนโครงการจริง (Actual Cost จาก Expenses)
- **ผู้ดูแลระบบ (Admin & Security)**: จัดการโครงสร้างองค์กร (Branches, Divisions, Departments), ผู้ใช้งาน (Users, Invites, Disable/Enable), สิทธิ์ตามบทบาท (RBAC Permission Matrix) และบันทึกประวัติการใช้งาน (Audit Logs)

---

## Technology Stack

| Layer | Technology | รายละเอียด |
| --- | --- | --- |
| Backend | PHP 8.3, Laravel 13 | Core Backend Framework & Domain Logic |
| Frontend | React 18, TypeScript | Single Page Application UI Component |
| SPA Bridge | Inertia.js | Props & State Routing ระหว่าง Laravel และ React |
| Styling | Tailwind CSS | Utility-first Modern CSS Framework |
| Build Tool | Vite | Fast Frontend Bundler |
| Database | MariaDB / MySQL | Relational Database พร้อม UUID v7 / Ordered UUID |
| PDF Engine | DomPDF | สร้างเอกสาร PDF ทางการ (รองรับ BahtText, Original/Copy, VOID) |
| Auth | Laravel Breeze | Local Auth, Password Confirmation, Verification |
| Queue & Mail | Laravel Queue & Mailable | Async background jobs, In-App notifications, Mail queue |
| Test Suite | PHPUnit | Automated Feature/Unit Tests (195+ passed tests, 1,600+ assertions) |
| Code Quality | Laravel Pint, ESLint, Prettier | Code Formatting & Static Analysis |

---

## Module Overview

| Module | หน้าที่หลัก | Phase ที่พัฒนา |
| --- | --- | --- |
| **Organization** | โครงสร้างบริษัท Multi-tenant, สาขา (Branch), ฝ่าย (Division), แผนก (Department) | Phase 1, 1.1 |
| **User & Access** | จัดการผู้ใช้, คำเชิญ (Invite), RBAC Roles & Permissions, Disable/Enable ผู้ใช้ | Phase 1, 1.1 |
| **Audit Log** | บันทึกประวัติการทำงานสำคัญทุกจุด พร้อม Before/After Snapshot และ User Tracker | Phase 1, 1.1 |
| **CRM** | ฐานข้อมูลลูกค้า (Customers), ผู้ติดต่อ (Contacts), Primary Contact, ข้อมูลภาษี | Phase 2 |
| **Sales Pipeline** | ดีลการขาย (Deals), Stage Flow, Won/Lost Rules, กิจกรรม (Activities & Timeline) | Phase 2 |
| **Quotations** | ใบเสนอราคา, สถานะ Draft/Sent/Approved/Rejected/Expired, แปลงเป็น Invoice | Phase 9 |
| **Product Catalog** | แคตตาล็อกสินค้า/บริการ, ราคา, รูปแบบภาษี (Tax Modes: Exclusive, Inclusive, No Tax) | Phase 3, 6, 7 |
| **Invoices** | สร้างใบแจ้งหนี้จากดีลหรือ Manual, คำนวณภาษีฝั่ง Server, รองรับ Discount & VAT Included | Phase 3, 6, 7, 8 |
| **Payments** | รับชำระเงิน (Partial/Full), ป้องกัน Overpay ด้วย DB Lock, Payment Reversal | Phase 3, 10 |
| **Expenses** | บันทึกค่าใช้จ่าย, แนบสลิป, ลำดับอนุมัติ (Draft -> Approved -> Paid -> Rejected), ผูก PO/Project | Phase 3, 7, 8 |
| **Suppliers** | ทะเบียนคู่ค้า/ผู้ขาย (Suppliers Master), ข้อมูลภาษี, ช่องทางติดต่อ | Phase 7 |
| **Purchase Orders** | ใบสั่งซื้อ (PO), Itemized Lines, อนุมัติ/ยกเลิก, พิมพ์/PDF, เชื่อมโยง Expense/GRN | Phase 7, 8 |
| **Inventory & GRN** | ใบรับสินค้า (Goods Receipt), Stock Movement Ledger, ปรับยอด/ส่งคืน, ต้นทุนเฉลี่ย | Phase 8 |
| **Tax & Aging Reports** | ภาษีขาย (ภ.พ.30), ภาษีซื้อ, หัก ณ ที่จ่าย (ภ.ง.ด.3/53), อายุหนี้ลูกหนี้/เจ้าหนี้ (AR/AP Aging) | Phase 8 |
| **Commercial Docs** | ใบลดหนี้/เพิ่มหนี้ (CN/DN), ใบวางบิล (Billing Note), ใบส่งของ (DO), ใบขอซื้อ (PR), ใบสำคัญ (PV/RV) | Phase 9 |
| **Treasury & Banking** | บัญชีธนาคารเข้ารหัส, นำเข้า CSV Bank Statement & Reconciliation, เงินสดย่อย, ทะเบียนเช็ค | Phase 10 |
| **General Ledger** | Chart of Accounts, accounting periods, immutable double-entry journals, source posting, trial balance และ account ledger | Phase 11 |
| **Projects & Tasks** | แปลงจาก Won Deal, สมาชิกโครงการ (Project Members), งานย่อย (Tasks, Checklists, Comments) | Phase 4, 7 |
| **Notifications** | กระดิ่งแจ้งเตือน In-App, อีเมลคิวแจ้งเตือน (PO, Invoice Due, Assign, Invite), Preferences | Phase 8 |
| **Dashboards** | Admin, Executive, Finance, Delivery, Sales Dashboards พร้อมตัวกรองช่วงเวลาและกราฟสรุป | Phase 1, 2, 3, 4, 5, 6 |

---

## รายละเอียด Feature ตาม Phase ทั้งหมด

### Phase 0: Documentation & Architecture Lock
- **Single Source of Truth**: ล็อกเอกสารความต้องการและสถาปัตยกรรมระบบทั้งหมดใน [`docs/`](docs/)
- **MVP Scope & ADR**: กำหนดขอบเขต MVP ใน [`MVP_SCOPE.md`](MVP_SCOPE.md) และ Architecture Decision Records ใน [`docs/ARCHITECTURE_DECISIONS.md`](docs/ARCHITECTURE_DECISIONS.md)
- **Security & Data Integrity**: กำหนดกฎความปลอดภัย [`docs/SECURITY_REQUIREMENTS.md`](docs/SECURITY_REQUIREMENTS.md), กฎการตรวจสอบข้อมูล [`docs/VALIDATION_RULES.md`](docs/VALIDATION_RULES.md) และ Database Schema [`docs/database/DATABASE.md`](docs/database/DATABASE.md)

### Phase 1: Foundation, Multi-tenant Isolation, Auth & Admin Dashboard
- **Multi-tenant Isolation**: แยกข้อมูลเด็ดขาดด้วย `org_id` ทุก Query ระดับโมเดล ป้องกัน Cross-org Data Leakage
- **Authentication & Registration**: สมัครสมาชิกพร้อมสร้าง Organization, สาขาสำนักงานใหญ่ (Head Office), ฝ่าย, แผนก และ Owner ภายใน Database Transaction เดียว
- **User Invitation**: เชิญผู้ใช้ร่วมงานด้วย Token แบบใช้ครั้งเดียว (One-time Token, TTL 72 ชั่วโมง)
- **Security Baseline**: ป้องกัน Brute-force ด้วย Rate Limiting, ยืนยันรหัสผ่าน (`password.confirm`) สำหรับ Action สำคัญ, และ Mask ข้อมูลสำคัญ เช่น `person_id`
- **Admin Dashboard**: แสดงภาพรวมการตั้งค่าองค์กร, สรุปจำนวนผู้ใช้งาน, สรุปสิทธิ์ Roles, แจ้งเตือนความปลอดภัย (Security Alerts) และประวัติ Audit Logs ล่าสุด

### Phase 1.1: Admin Master Data & Access Management
- **Branch Master**: สร้าง/แก้ไข/ปิดใช้งานสาขา, สลับสำนักงานใหญ่ (Head Office Enforcement) พร้อม Audit Log
- **Division & Department Master**: บริหารฝ่ายและแผนก ตรวจสอบความถูกต้องของสายบังคับบัญชา (Hierarchy Chain Validation) พร้อม Guard ป้องกันการลบ/ปิดเมื่อมีข้อมูลอ้างอิง
- **User Management**: ค้นหา/กรองผู้ใช้ตามบทบาท/สาขา, แก้ไขโปรไฟล์และตำแหน่ง, ปิดใช้งาน/เปิดใช้งานใหม่ (Disable/Enable), ป้องกันการปิด Owner คนสุดท้าย
- **Role-Permission Matrix**: ระบบสิทธิ์ RBAC แบบ Union Permissions, เมทริกซ์สิทธิ์ต่อ Role, ป้องกันการแก้ไข Role Owner

### Phase 2: CRM & Sales Pipeline Management
- **Customer & Contact Master**: จัดการรายชื่อลูกค้าและบริษัทคู่ค้า, เพิ่มผู้ติดต่อหลายคนต่อหนึ่งลูกค้า, กำหนดผู้ติดต่อหลัก (Primary Contact)
- **Deals Pipeline**: กระดานดีลตาม Stage (`new`, `contacted`, `qualified`, `proposal`, `negotiation`, `won`, `lost`), บันทึกมูลค่าและโอกาสปิดการขาย
- **Won/Lost Workflow**: บันทึก `won_at` อัตโนมัติเมื่อปิดการขายเพื่อส่งต่อเปิด Invoice/Project, บังคับระบุ `lost_reason` เมื่อปิดดีลไม่สำเร็จ
- **Polymorphic Timeline & Activities**: บันทึกการโทร นัดหมาย ประชุม อีเมล พร้อมระบบแจ้งเตือน Follow-up และตรวจจับดีลที่ไม่มีความเคลื่อนไหว (Stale Deals $\ge$ 7 วัน)
- **Sales Dashboard**: แสดง Pipeline Funnel, กราฟ Won/Lost, ยอดขายตาม Sales Owner และรายการ Follow-up ประจำวัน

### Phase 3: Core Finance, Invoicing & Payments
- **Product & Service Catalog**: แคตตาล็อกสินค้าและบริการสำหรับดึงราคาและรูปแบบภาษี
- **Invoicing Engine**: สร้าง Invoice จาก Won Deal หรือ Manual Invoice, คำนวณภาษีและยอดสุทธิฝั่ง Server
- **Tax Modes**: รองรับภาษีแบบ Exclusive (ราคาไม่รวม VAT), Inclusive (ราคารวม VAT), และ No Tax (ยกเว้น VAT)
- **Payment Processing**: รับชำระเงินเต็มจำนวนและแบ่งชำระ (Partial Payment) พร้อม Database Lock ป้องกันการรับเงินเกินยอด (Overpay Guard)
- **Payment Reversal**: ระบบยกเลิกรายการรับเงินแบบ Idempotent ผ่าน Unique Constraint โดยไม่ลบประวัติการเงินเดิม
- **Expense Workflow**: บันทึกค่าใช้จ่ายพร้อมกระบวนการอนุมัติและจ่ายเงิน (Draft $\rightarrow$ Approved $\rightarrow$ Paid $\rightarrow$ Rejected) พร้อมแนบสลิป/ใบเสร็จ
- **Finance Dashboard**: สรุปยอด Invoiced Revenue, Cash In, Outstanding AR, Overdue AR, Expenses/Cash Out และ Net Cash Flow

### Phase 4: Delivery & Project Management
- **Project Provisioning**: สร้าง Project อัตโนมัติจาก Won Deal (1-to-1 Mapping) หรือสร้าง Manual Project
- **Task Management**: มอบหมายงานย่อย, กำหนดความสำคัญ (Priority), วันครบกำหนด (Due Date), รายการ Checklist และกล่องความคิดเห็น (Task Comments)
- **Internal Tasks**: รองรับการสร้างงานภายในองค์กรโดยไม่ต้องผูกกับ Project
- **Cost Calculation**: คำนวณต้นทุนโครงการจริง (Actual Cost) แบบ Real-time จากยอดรวม Expense ที่อนุมัติ/จ่ายแล้ว
- **Delivery Dashboard**: รายงานสถานะโครงการ, ภาระงานของทีม (Workload), งานที่เกินกำหนด (Overdue Tasks) และความคืบหน้างบประมาณ (Budget vs Actual)

### Phase 5: Executive Dashboard & End-to-End Integration
- **Executive Dashboard**: รวบรวมข้อมูลสรุปมุมมองผู้บริหารจาก Sales, Finance, และ Delivery พร้อมระบบความปลอดภัยซ่อน Widget ตามสิทธิ์
- **End-to-End Testing**: ทดสอบ Flow การทำงานจริงครบวงจร ตั้งแต่ Invite User $\rightarrow$ Customer $\rightarrow$ Deal $\rightarrow$ Invoice $\rightarrow$ Payment $\rightarrow$ Project $\rightarrow$ Task $\rightarrow$ Dashboard
- **Financial Integrity**: ป้องกันการแสดงยอด Cash Balance ปลอมก่อนมีระบบกระทบยอดธนาคารจริง และรองรับการแจ้งเตือน `needs_sales_review` เมื่อ Invoice ถูก Void

### Phase 6: Reporting Filters, Visual Enhancements & Operational Compliance
- **Dynamic Date Filters**: ตัวกรองช่วงเวลาในทุก Dashboard (All-time, เดือนนี้, ปีนี้, กำหนดช่วงวันเอง Custom Range)
- **Visual Analytics**: แสดง Donut Charts, Pipeline Funnels, Risk Badges และ Trend Tiles แสดงแนวโน้มเทียบช่วงก่อนหน้า
- **Document Number Expansion**: ขยายขนาดรหัสเอกสารเป็น `varchar(30)` เพื่อรองรับรูปแบบเลขเอกสารสากล
- **Departmental Dashboard Separation**: แยกเส้นทาง Route และ UI Navigation สำหรับแต่ละ Dashboard ให้ชัดเจน

### Phase 7: Post-MVP Operational Enhancements
- **Configurable Number Sequences**: ตั้งค่ารูปแบบเลขเอกสารได้เองตามต้องการ รองรับ Tokens: `{YYYY}`, `{YY}`, `{MM}`, `{DD}`, `{BRANCH}`, `{SEQ:n}` พร้อมรอบ Reset รายปี/รายเดือน/รายวัน
- **Inclusive VAT Breakdown**: แสดง Gross Subtotal, Net Subtotal, ภาษีที่รวมอยู่ในราคา และการจัดสรรส่วนลดระดับ Header อย่างโปร่งใส
- **Suppliers Master**: ระบบทะเบียนคู่ค้า/ผู้ขาย สำหรับกระบวนการจัดซื้อ
- **Purchase Orders (PO)**: ออกใบสั่งซื้อสินค้า/บริการ, คำนวณภาษีฝั่ง Server, กระบวนการอนุมัติ/ยกเลิก และเชื่อมโยงกับการตั้งเบิก Expense
- **Project Members & Collaboration**: ระบบเพิ่มสมาชิกในโครงการ กำหนดบทบาททีมงาน และควบคุมสิทธิ์การมองเห็น Task ภายใน Project

### Phase 8: Production Roadmap, Official Documents, Tax Reports, Inventory & Notifications
- **Official Print & PDF Generation**: Engine สร้าง PDF คุณภาพสูงด้วย DomPDF, แสดงลายน้ำ VOID อัตโนมัติ, ระบุหัวเอกสาร ต้นฉบับ/สำเนา, และแปลงจำนวนเงินเป็นตัวอักษรภาษาไทย (`BahtText`)
- **Official Documents Set**: รองรับการพิมพ์และดาวน์โหลด PDF สำหรับ:
  - ใบแจ้งหนี้ (Invoice)
  - ใบกำกับภาษี / ใบเสร็จรับเงิน (Tax Invoice / Receipt)
  - ใบสั่งซื้อ (Purchase Order)
  - หนังสือรับรองการหักภาษี ณ ที่จ่าย (ใบ 50 ทวิ)
- **Inventory & Goods Receipts (GRN)**:
  - รับสินค้าเข้าคลังอ้างอิงจาก PO ที่อนุมัติแล้ว พร้อมระบบ Over-receive Guard ป้องกันรับเกิน
  - ทะเบียนความเคลื่อนไหวสินค้า (Stock Movement Ledger) รองรับการรับเข้า, ปรับยอดตรวจนับ (Adjustment In/Out) และส่งคืนผู้ขาย (Return to Supplier)
  - คำนวณต้นทุนสินค้าคงเหลือเฉลี่ยถ่วงน้ำหนัก (Moving Average Cost) อัตโนมัติ
- **Tax & Accounting Reports**:
  - รายงานภาษีขาย (Sales Tax Report) สำหรับยื่น ภ.พ.30
  - รายงานภาษีซื้อ (Purchase Tax Report) รวบรวมจาก PO, Expenses, และ GRN
  - รายงานภาษีหัก ณ ที่จ่าย (Withholding Tax Report) แยก ภ.ง.ด. 3 และ ภ.ง.ด. 53
  - รายงานวิเคราะห์อายุหนี้ (AR Aging & AP Aging: 0-30, 31-60, 61-90, >90 วัน)
  - ส่งออกข้อมูลเป็น CSV และไฟล์ Excel-compatible `.xls`
- **In-App Notifications & Mail Queue**:
  - กระดิ่งแจ้งเตือนบน Navbar พร้อมตัวเลขนับ Unread
  - ส่งอีเมลคิวแจ้งเตือนอัตโนมัติ: PO รออนุมัติ, ใบแจ้งหนี้ใกล้ครบกำหนดและเกินกำหนด, การมอบหมายงาน/โครงการ, คำเชิญผู้ใช้
  - หน้าตั้งค่าเปิด/ปิดการรับแจ้งเตือนรายบุคคล (Notification Preferences) พร้อมระบบ Deduplication Idempotency ป้องกันส่งซ้ำ

### Phase 9: Commercial & Procurement Documents
- **Quotations**: จัดการใบเสนอราคา, สถานะ Draft/Sent/Approved/Rejected/Expired, และปุ่ม 1-Click Convert Quotation to Invoice
- **Credit Notes & Debit Notes (CN/DN)**: ออกใบลดหนี้/เพิ่มหนี้อ้างอิง Invoice เดิม ปรับปรุงยอดคงค้างและรายงานภาษีขาย ภ.พ.30 อย่างถูกต้องตามกฎหมายแทนการ Void
- **Billing Notes / Statements of Account**: รวมใบแจ้งหนี้หลายใบของลูกค้ารายเดียวเพื่อทำใบวางบิล พร้อมรายงานสรุปและพิมพ์ PDF
- **Delivery Orders (DO)**: ออกใบส่งของจากรายการสินค้าใน Invoice, บันทึกชื่อผู้รับสินค้าและหลักฐานการเซ็นรับ พร้อมตัดยอดสต็อกขาออก
- **Purchase Requests (PR)**: พนักงานสร้างใบขอซื้อ, ผู้จัดการอนุมัติ, และแปลงเป็นใบสั่งซื้อ (Convert PR to PO)
- **Payment & Receipt Vouchers (PV/RV)**: ออกใบสำคัญรับเงินและใบสำคัญจ่ายเงิน สำหรับยืนยันการเงิน บันทึกบัญชี และพิมพ์เอกสาร PDF พร้อมระบบแนบหลักฐานการทำรายการ

### Phase 10: Treasury, Banking & Cash Management
- **Encrypted Bank Accounts Master**: บันทึกสมุดบัญชีธนาคารของบริษัท พร้อมเข้ารหัสเลขบัญชีในฐานข้อมูล และป้องกันการบันทึกเลขที่บัญชีซ้ำ
- **Account-linked Transactions**: การรับชำระเงิน (Receipt), การคืนเงิน (Reversal) และการจ่ายค่าใช้จ่าย (Expense) ผูกตรงกับบัญชีธนาคารหรือบัญชีเงินสด
- **CSV Bank Statement Import & Reconciliation**: นำเข้าสเตทเมนต์ธนาคารจากไฟล์ CSV, ป้องกันรายการซ้ำ, และระบบจับคู่กระทบยอด (Match / Unmatch) พร้อม Audit Trail
- **Petty Cash Management**: บริหารกองทุนเงินสดย่อย, ตั้งเบิก (Request), อนุมัติอิสระ, บันทึกจ่ายเงิน และขอเบิกเงินชดเชยเติมกองทุน (Reimbursement)
- **Cheque & PDC Register**: ทะเบียนรับ/จ่ายเช็คและเช็คล่วงหน้า (Post-Dated Cheques) พร้อมบันทึกสถานะฝากเช็ค, เคลียร์เช็คกระทบยอด, เช็คคืน (Bounced) และยกเลิกเช็ค
- **Voucher Proof Attachments**: แนบสลิป/หลักฐานการโอนในใบสำคัญ PV/RV พร้อมการควบคุมสิทธิ์ดาวน์โหลดตาม Parent Entity
- **Treasury Reports**: สรุปยอดเงินในบัญชีที่คาดการณ์ (Expected Bank Position), รายการ Statement ที่ยังไม่ได้กระทบยอด, ยอดเงินสดย่อย และเช็คที่รอการเคลียร์

---

## แผนการพัฒนาในอนาคต (Roadmap: Production Prep & Phases 11–16)

### Production Prep: Deployment Readiness (Planned)
- **Scheduler & Queues**: ตั้งค่า Laravel Scheduler สำหรับ Cron Jobs และ Supervisor สำหรับ Worker Process
- **PDF Thai Fonts**: ติดตั้งฟอนต์ภาษาไทยมาตรฐาน (Sarabun / THSarabunNew) บนเซิร์ฟเวอร์ Linux สำหรับ DomPDF
- **Disaster Recovery**: นโยบายและขั้นตอน Backup ฐานข้อมูลอัตโนมัติรายวันพร้อมคู่มือกู้คืนระบบ

### Phase 11: General Ledger & Double-entry Accounting
- **Chart of Accounts (COA)**: ผังบัญชี SME ต่อองค์กร พร้อม default account seed และการควบคุม active/postable
- **Accounting Periods**: การเปิด/ปิดงวดบัญชีพร้อม Period Lock ป้องกันการ post หรือ reversal ย้อนหลัง
- **Immutable Journals**: บังคับ Debits = Credits, source-event idempotency และ reversal journal สำหรับการแก้ไข
- **Automatic Posting**: Invoice, Payment, Expense, CN/DN, Goods Receipt และ Petty Cash Reimbursement สร้าง journal ตาม recognition point; PV/RV, reconciliation และ request เป็น evidence/control จึงไม่ post ซ้ำ
- **Financial Statements**: งบทดลอง (Trial Balance) และสมุดบัญชีแยกประเภท (General Ledger/Account Ledger)

### Phase 12: E-Tax & RD Online Tax Filing (Planned)
- **e-Tax Invoice by Email / API**: สร้างไฟล์ XML ตามมาตรฐาน ETDA/กรมสรรพากร พร้อม Digital Signature
- **RD Prep Text Export**: ส่งออกไฟล์ข้อความสำหรับยื่นแบบภาษีออนไลน์ ภ.ง.ด. 1, ภ.ง.ด. 3, ภ.ง.ด. 53 ผ่านโปรแกรมของกรมสรรพากร

### Phase 13: Fixed Assets & Depreciation (Planned)
- **Asset Register**: ทะเบียนสินทรัพย์ถาวร และการบันทึกต้นทุนจาก Expense/PO/GRN
- **Depreciation Engine**: คำนวณค่าเสื่อมราคารายเดือน (Straight-line Method) และบันทึกบัญชีเข้า GL อัตโนมัติ
- **Disposal & Write-off**: จำหน่ายสินทรัพย์และตัดจำหน่ายทางบัญชี

### Phase 14: Multi-Currency & FX Management (Planned)
- **Currency & Exchange Rates**: ตารางอัตราแลกเปลี่ยนสกุลเงินต่างประเทศ
- **FX Rate Snapshot**: บันทึกอัตราแลกเปลี่ยน ณ วันที่เกิดรายการ
- **Realized & Unrealized FX**: คำนวณและบันทึกกำไร/ขาดทุนจากอัตราแลกเปลี่ยนที่เกิดขึ้นจริงและปรับปรุงสิ้นงวด

### Phase 15: Advanced Inventory & Barcode/QR Operations (Planned)
- **Multi-Warehouse & Bin Locations**: ระบบหลายคลังสินค้าและระบุตำแหน่งจัดเก็บ (Bin/Rack)
- **Stock Transfer**: โอนย้ายสินค้าระหว่างคลังและสาขา
- **Reorder Points & Alerts**: กำหนดจุดสั่งซื้อซ้ำและแจ้งเตือนเมื่อสินค้าใกล้หมด
- **Barcode & QR Scanner**: รองรับการสแกน Barcode/QR ผ่านกล้องมือถือหรือเครื่องสแกนในการรับ/ส่งสินค้า

### Phase 16: Payroll, Social Security & Security 2FA (Planned)
- **Payroll Profile & Salary Calculation**: คำนวณเงินเดือน ค่าล่วงเวลา รายการหัก ภาษีหัก ณ ที่จ่าย ภ.ง.ด. 1 และเงินสมทบประกันสังคม
- **Payslip PDF**: สร้างสลิปเงินเดือนแบบ PDF สำหรับพนักงาน
- **Two-Factor Authentication (2FA)**: ยืนยันตัวตน 2 ขั้นตอนด้วย OTP/Authenticator App สำหรับ Admin และ Finance

---

## Current Phase Status

| Phase | สถานะ | ขอบเขตฟีเจอร์ |
| --- | --- | --- |
| **Phase 0** | **Done** | Documentation, MVP scope lock, database schema, architecture decisions |
| **Phase 1** | **Done** | Foundation, multi-tenant isolation, Breeze auth, RBAC, Admin Dashboard |
| **Phase 1.1** | **Done** | Admin Master Data (Branch/Division/Department), User management, Role-Permission matrix |
| **Phase 2** | **Done** | CRM customers, contacts, primary contact, deals pipeline, activities, Sales Dashboard |
| **Phase 3** | **Done** | Product catalog, invoices, partial/full payments, overpay prevention, reversals, expenses, Finance Dashboard |
| **Phase 4** | **Done** | Projects from won deals, tasks, checklists, comments, cost aggregation, Delivery Dashboard |
| **Phase 5** | **Done** | Executive Dashboard, E2E flow tests, role union permissions, UAT baseline |
| **Phase 6** | **Done** | Dashboard Date Filters, visual charts, number sequence expansion, inclusive VAT engine, dashboard separation |
| **Phase 7** | **Closed** | Configurable numbering sequences, inclusive VAT UI, suppliers master, purchase orders, project members |
| **Phase 8** | **Done** | Official PDF/Print (Invoice/Tax Invoice/PO/50-Tawi), GRN & Inventory ledger, Tax & Aging reports, In-App notifications & Mail queue |
| **Production Prep** | *Planned* | Deployment readiness, Laravel Scheduler/Supervisor config, Linux Thai fonts, backup & recovery |
| **Phase 9** | **Done** | Commercial & Procurement Docs: Quotations, CN/DN, Billing Notes, Delivery Orders, Purchase Requests, Vouchers (PV/RV) |
| **Phase 10** | **Done** | Treasury, Banking & Cash Management: Bank accounts, CSV statement reconciliation, Petty cash, Cheques/PDC, Treasury reports |
| **Phase 11** | **Done** | Chart of Accounts, periods, immutable journals, source posting, Trial Balance และ General Ledger |
| **Phase 12** | *Planned* | E-Tax XML generation, Digital Signature, RD Online Tax Filing exports |
| **Phase 13** | *Planned* | Fixed Assets register, monthly depreciation schedule, asset disposal |
| **Phase 14** | *Planned* | Multi-Currency master, exchange rates snapshot, Realized/Unrealized FX |
| **Phase 15** | *Planned* | Multi-warehouse & bins, stock transfer, reorder alerts, lot/expiry, Barcode/QR scanning |
| **Phase 16** | *Planned* | Payroll calculation, ภ.ง.ด. 1/Social Security, Payslip PDF, Two-Factor Authentication (2FA) |

---

## Routes Summary

| Route | Module / หน้าจอ | สิทธิ์ที่ต้องใช้ (Permission) |
| --- | --- | --- |
| `/dashboard` | Admin Dashboard | `dashboard.view` |
| `/executive-dashboard` | Executive Dashboard | `dashboard.view` |
| `/finance-dashboard` | Finance Dashboard | `expenses.view` |
| `/delivery-dashboard` | Delivery Dashboard | `dashboard.view` |
| `/sales-dashboard` | Sales Dashboard | `sales.dashboard.view` |
| `/customers` | CRM Customers Management | `customers.view` |
| `/deals` | Sales Deals Pipeline & Activities | `deals.view` |
| `/quotations` | Quotations (ใบเสนอราคา) | `quotations.view` |
| `/products` | Product & Service Catalog | `products.manage` |
| `/invoices` | Invoices & Tax Invoices | `invoices.view` |
| `/invoices/{id}/print`, `/pdf` | Official Print / PDF Export | `invoices.view` |
| `/suppliers` | Suppliers Master (ทะเบียนผู้ขาย) | `suppliers.view` |
| `/purchase-orders` | Purchase Orders (ใบสั่งซื้อ) | `purchase_orders.view` |
| `/goods-receipts` | Goods Receipts & Stock Movements | `inventory.view` |
| `/tax-reports` | Sales Tax, Purchase Tax, WHT, AR/AP Aging | `tax_reports.view` |
| `/commercial-documents` | CN/DN, Billing Notes, DO, PR, Vouchers | `billing_notes.view` |
| `/bank-accounts` | Bank Accounts Master (บัญชีธนาคาร) | `treasury.accounts.view` |
| `/bank-statements` | CSV Import & Reconciliation Screen | `treasury.reconciliation.view` |
| `/petty-cash` | Petty Cash Funds & Requisitions | `petty_cash.view` |
| `/cheques` | Cheque & PDC Register (ทะเบียนเช็ค) | `cheques.view` |
| `/treasury-reports` | Treasury & Position Reports | `treasury.reports.view` |
| `/expenses` | Expenses & Approvals | `expenses.view` |
| `/projects` | Projects & Project Members | `projects.view` |
| `/tasks` | Tasks, Checklists & Comments | `tasks.view` |
| `/users` | User Management & Invitations | `users.view` |
| `/roles` | Role & Permission Matrix | `roles.view` |
| `/audit-logs` | Audit Trail & Activity Logs | `audit.view` |
| `/settings/organization` | Organization & Number Sequences Settings | `settings.organization.view` |
| `/settings/notifications` | Notification Preferences | `settings.organization.view` |
| `/settings/organization-structure` | Branches, Divisions, Departments | `settings.structure.view` |

---

## Security Model & Data Integrity

- **Multi-tenant Isolation**: กำหนดขอบเขตข้อมูลด้วย `org_id` เสมอในทุก Domain Model ป้องกันการเข้าถึงข้ามองค์กร (Cross-org access ได้รับ `404`)
- **Granular RBAC**: ควบคุมการเข้าถึงด้วย Middleware `permission:{code}` และรองรับ Union Permissions ของผู้ใช้หลาย Role
- **Sensitive Action Re-authentication**: บังคับยืนยันรหัสผ่าน (`password.confirm`) ก่อนบันทึกข้อมูลสำคัญ เช่น จัดการผู้ใช้, แก้สิทธิ์ Role, ออกเอกสารการเงิน, Reverse การชำระเงิน, จัดการบัญชีธนาคาร
- **Write Route Throttling**: ป้องกันการส่งคำขอถี่เกินไป (`throttle:10,1` หรือ `throttle:5,1`)
- **Strict Data Encryption & Privacy**:
  - เลขบัญชีธนาคาร (`bank_accounts.account_number`) ถูกเข้ารหัสในฐานข้อมูล
  - เลขประจำตัวประชาชน/ผู้เสียภาษี (`person_id`) ถูก Mask ในการแสดงผลและ Props ยกเว้นผู้มีสิทธิ์
  - ห้ามส่ง Secrets, Tokens, Passwords ใน Inertia Props
- **Financial Invariant Guards**:
  - Payment ป้องกัน Overpay ด้วย Database Transaction Lock
  - Payment ห้ามลบทิ้ง ต้องใช้ Idempotent Reversal
  - Official Tax Documents ห้ามใช้การ Void เมื่อออกเอกสารทางการแล้ว ให้ใช้ Credit Note / Debit Note
- **Parent-Scoped File Access**: ไฟล์แนบสลิป/ใบเสร็จ/Voucher Proof ตรวจสอบสิทธิ์การดาวน์โหลดผ่าน Parent Entity เสมอ

---

## Setup & Running Locally

### 1. เข้าสู่ไดเรกทอรี Backend
```powershell
cd backend
```

### 2. ติดตั้ง Dependencies
```powershell
composer install
pnpm install
```

### 3. ตั้งค่า Environment File
```powershell
copy .env.example .env
php artisan key:generate
```

### 4. Migrate ฐานข้อมูลและ Seed ข้อมูลตัวอย่าง
```powershell
php artisan migrate:fresh --seed
```

### 5. เชื่อมต่อ Storage สำหรับไฟล์แนบ
```powershell
php artisan storage:link
```

### 6. เริ่มต้นเซิร์ฟเวอร์ Local Development
เปิด 2 Terminal:

**Terminal 1 (Backend):**
```powershell
php artisan serve
```

**Terminal 2 (Frontend Vite):**
```powershell
pnpm run dev
```

เปิด Browser ไปที่: [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## Demo Accounts

หลังการ Seed ข้อมูล สามารถเข้าสู่ระบบด้วยรหัสผ่าน: `password`

| บทบาท (Role) | อีเมล (Email) | สิทธิ์และหน้าที่หลัก |
| --- | --- | --- |
| **Owner** | `owner@example.com` | สิทธิ์สูงสุดทุกโมดูล, จัดการโครงสร้างองค์กรและตั้งค่าระบบ |
| **Admin** | `admin@example.com` | ผู้ดูแลระบบ, จัดการผู้ใช้, Role/Permission Matrix, Audit Logs |
| **Sales** | `sales@example.com` | จัดการลูกค้า, ดีลการขาย, ใบเสนอราคา, Sales Dashboard |
| **Project Manager** | `pm@example.com` | จัดการโครงการ, สมาชิกโครงการ, มอบหมายงานย่อย, ติดตามความคืบหน้า |
| **Finance** | `finance@example.com` | จัดการใบแจ้งหนี้, การรับเงิน, ค่าใช้จ่าย, ภาษี, ธนาคาร, เช็ค, Treasury |
| **Member** | `member@example.com` | สมาชิกทีมปฏิบัติการ, ดูงานและ Checklist ที่ตนเองได้รับมอบหมาย |
| **Viewer** | `viewer@example.com` | สิทธิ์อ่านอย่างเดียว (Read-only) สำหรับตรวจสอบข้อมูล |

---

## Quality & Test Verification

รันชุดทดสอบ Backend ทั้งหมด:
```powershell
php artisan test
```

รันการตรวจสอบ Code Style และ Static Analysis ของ Frontend:
```powershell
pnpm run lint
pnpm run check-format
pnpm run build
```

รันการจัด Format โค้ด PHP:
```powershell
vendor\bin\pint
```

ผลการทดสอบล่าสุด:
```text
Pass: 195 passed, 1625 assertions
```

---

## Development Notes

- `checklist.md` คือ source of truth ของสถานะงาน
- `docs/database/DATABASE.md` คือ source of truth ของ schema
- ก่อนเพิ่ม module ใหม่ควรแก้ docs และ checklist ก่อน
- ห้ามรับ `org_id` จาก client สำหรับ business write
- ห้าม expose secret/token/password ใน Inertia props
- Write action สำคัญควรมี audit log
