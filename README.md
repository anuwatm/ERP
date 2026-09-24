# Company OS / Lightweight ERP

สถานะล่าสุด (2026-09-22): **Phase 1 ถึง Phase 26 และ Web Setup Wizard เสร็จสมบูรณ์และผ่านการตรวจรับ 100% (Complete & Closed)** — ระบบ Company OS / Lightweight ERP ครอบคลุมฟังก์ชันองค์กรเต็มรูปแบบ 35 โมดูลหลัก (58 Migrations, 70+ ตาราง), รองรับการติดตั้งผ่านเบราว์เซอร์ (Web Setup Wizard), สถาปัตยกรรม Modal Dialog มาตรฐานกลาง 60 หน้าจอ, ระบบบุคลากรและกะทำงานข้ามวัน, การนำส่งภาษีอิเล็กทรอนิกส์ e-Tax Gateway, การพยากรณ์กระแสเงินสด 3 มิติ และระบบสกัดข้อมูลใบเสร็จด้วย AI OCR (Human-in-the-Loop); ผ่านการทดสอบ Full Suite 380+ tests (2,800+ assertions), Frontend Unit 23 tests, และ Pint/ESLint/Prettier/Vite production build 100% (ดูรายละเอียดใน [`checklist.md`](checklist.md) และ [`docs/CURRENT_IMPLEMENTATION.md`](docs/CURRENT_IMPLEMENTATION.md))

ระบบ ERP ขนาดเบาสำหรับ SME, บริษัทบริการ, software agency, studio, ทีมจัดซื้อ/คลังสินค้า และทีมส่งมอบงานภายในองค์กร

ระบบนี้ออกแบบให้ครอบคลุม flow หลักของธุรกิจบริการและการค้า:

```text
Installer -> Invite User -> Customer -> Deal -> Quotation -> Invoice / DO / Billing Note -> e-Tax Gateway -> Payment / Gateway Settlement -> Bank Recon -> Project -> Task -> PO / Goods Receipt / Inventory -> Tax Reports & 50-Tawi -> Treasury -> HR / Roster / Overnight Attendance -> Multi-rate Payroll -> AI OCR Expense -> Cash Flow Forecast -> Executive Dashboard
```

สถานะระบบ: **Phase 1 ถึง Phase 26 และ Web Setup Wizard เสร็จสมบูรณ์ (Complete & Closed 100%)** ฟีเจอร์ครอบคลุม:
- **Foundation & Security**: Multi-tenant isolation (`org_id`), Breeze Auth, RBAC 7 บทบาท, Master Data & Immutable Audit Logs
- **CRM & Sales Pipeline**: Customers & Contacts Master, Deals Pipeline, Quotations, Commercial Docs (CN/DN, Billing Notes, DO, PR, PV/RV)
- **Billing & e-Tax**: Invoicing, Official PDF/Print (Invoice, Receipt, PO, 50-Tawi), Tax Reports (ภ.พ.30, ภ.ง.ด. 3/53, AR/AP Aging), VAT Engine
- **e-Tax Invoice & RD Prep Gateway (Phase 24)**: XML ตามมาตรฐาน ETDA, Enveloped XML-DSig (RSA-SHA256), Queue Worker อัตโนมัติ, Webhook Callback (HMAC-SHA256), และระบบ Inquire Status จากกรมสรรพากร
- **Payments & Treasury**: รับชำระเงิน, ป้องกัน Overpay, Reversal, รายจ่าย, พร้อมเพย์ Thai QR (Tag 29/30) & Payment Gateway Settlement Bridge, บัญชีธนาคารเข้ารหัส, กระทบยอดสเตทเมนต์, เงินสดย่อย, ทะเบียนเช็ค
- **Cash Flow Forecasting & Analytics (Phase 25)**: เอนจินจำลองกระแสเงินสด 30-180 วันใน 3 สถานการณ์ (Base, Best, Worst), Interactive SVG Trajectory Curve, อัตราส่วนสภาพคล่อง (CR, QR, DSO, DPO, Runway) และสแนปช็อตรายวันอัตโนมัติ
- **AI OCR Document Ingestion (Phase 26)**: Universal Vision Client (Local Ollama / Cloud GPT), สกัดไฟล์ภาพและ Scanned PDF, ตรวจสอบเลขผู้เสียภาษี 13 หลักสูตร Modulo 11, หน้าจอ Split-Screen Reviewer ตรวจรับ และ Human-in-the-Loop Guard (ห้าม Auto-GL)
- **Projects & Delivery**: แปลงจาก Won Deals, สมาชิกโครงการ, งานย่อย (Tasks, Checklists, Comments) และ Delivery Dashboard
- **Advanced Inventory**: คลังสินค้า (Multi-Warehouse), Bin Locations, Lot & Expiry, Barcode/QR Scanning, Stock Differencing
- **General Ledger & Accounting**: ผังบัญชี (CoA), งวดบัญชี, บันทึกรายวันอัตโนมัติ Double-Entry, งบทดลอง และแยกประเภท
- **Multi-Currency & FX**: อัตราแลกเปลี่ยนย้อนหลัง, Realized/Unrealized FX, AR Revaluation, บัญชีเงินฝากเงินตราต่างประเทศ (FCD)
- **Fixed Assets**: ทะเบียนสินทรัพย์, คิดค่าเสื่อมราคาเส้นตรงรายเดือน, การจำหน่าย/ตัดบัญชีสินทรัพย์
- **HR, Roster & Attendance (Phase 23.2, 23.3)**: ทะเบียนตำแหน่งงาน (`positions`), นำเข้าพนักงานแบบชุด (Bulk Import), จัดตารางกะ (Roster Matrix), นำเข้าเวลาจากเครื่องสแกน (.xlsx, .csv), คิดกะข้ามวัน (Overnight Shifts), อัตรา OT 3 ระดับ (1.5x/2.0x/3.0x)
- **Payroll**: นโยบายภาษี/ประกันสังคมแบบ Versioned, คำนวณเงินเดือนสำหรับพนักงานที่มีและไม่มี User บัญชีระบบ, จัดส่งสลิป PDF ทางอีเมลส่วนตัว, รายงาน ภ.ง.ด. 1/1ก
- **Enterprise DMS**: คลังเอกสารส่วนตัว, จัดเวอร์ชัน, ระดับความลับ 5 ขั้น, นโยบายการจัดเก็บ (Retention) และ Legal Hold
- **Security 2FA**: Offline TOTP (RFC 6238), รหัสกู้คืน, Trusted Devices, การรีเซ็ตโดย Owner
- **Central Approval Workflow**: สายอนุมัติพลวัต, ประเมินวงเงิน, SoD Deadlock Guard, มอบหมายงานแทน (Delegation)
- **Operational Notification Outbox**: LINE OA / Slack / Telegram, Backoff ป้องกันบล็อก, Quiet Hours, Daily Digest
- **Customer & Supplier Portals**: Passwordless Magic Link, Scoped Sessions, อนุมัติใบเสนอราคา, ส่งบิลคู่ค้า
- **Master Dialog Standard (Phase 23.1)**: มาตรฐาน Modal Dialog 60 หน้าจอ พร้อม `useEditorCoordinator`, Deep Dirty Detection, และ Discard Guard
- **Web Setup Wizard**: ตัวติดตั้งระบบผ่านเว็บ 4 ขั้นตอน ควบคุมด้วย Setup Token, Preflight Check, สร้าง `.env` แบบ Atomic, รัน 58 Migrations, ติดตั้ง Owner & Master Data อัตโนมัติ พร้อมล็อกระบบถาวรหลังติดตั้ง; มีหน้า `/setup/system-requirements.php` สำหรับตรวจ server โดยไม่ใช้ token

เอกสารสถานะงานหลักอยู่ที่ [`checklist.md`](checklist.md) และ [`docs/CURRENT_IMPLEMENTATION.md`](docs/CURRENT_IMPLEMENTATION.md)

**การติดตั้งระบบ:** รองรับทั้ง **[การติดตั้งผ่าน Web Setup Wizard](#1-การติดตั้งผ่าน-web-setup-wizard-web-based-installer)** บน Web Server และ **[การติดตั้งผ่าน Command Line](#2-การติดตั้งผ่าน-command-line-local-development)** สำหรับ Local Development (ดูรายละเอียดข้อกำหนดเพิ่มเติมใน [`docs/SETUP_WIZARD.md`](docs/SETUP_WIZARD.md))

---

## 🗺️ System Diagrams & Architecture Documentation

ระบบมีชุด Diagram สถาปัตยกรรม เวิร์กโฟลว์ และผังฐานข้อมูลฉบับสมบูรณ์ (Archify Interactive HTML + Mermaid Diagrams):

* 🌐 **[Interactive Diagram Portal Hub](document/index.html)** — ศูนย์รวมไดอะแกรมแบบ Interactive HTML ทั้ง 21 ชุด ครอบคลุม 35 โมดูล (เปิดดูบน Browser ได้ทันที พร้อมสลับ Dark/Light mode, ซูม, ฟิลเตอร์ และ Export)
* 📑 **[System Diagrams & Architecture Specs](document/README.md)** — สารบัญและ Mermaid Diagrams ภาพรวมระบบ 21 ชุดหลัก
* 🧩 **[7 Domain Group Workflows (35 Modules)](document/GROUPS_WORKFLOW.md)** — ผังการทำงานและวงจรสถานะเจาะลึก 7 กลุ่มงานหลัก (28 Diagrams)
* 🗄️ **[Full Database ER Diagram (70+ Tables)](document/DATABASE_ERD.md)** — ผังแบบจำลองฐานข้อมูลทั้งระบบ 21 โดเมนหลัก อ้างอิง 58 Migrations
* 👥 **[HR Employee, Attendance & Payroll Integration](document/17_hr_employee_attendance_payroll.md)** — สถาปัตยกรรม HR บุคลากร, Position Master, Bulk Import, กะข้ามวัน, OT 3 ระดับ และ Non-User Payroll
* 🧾 **[e-Tax Invoice & RD Prep Gateway Pipeline](document/18_etax_gateway_pipeline.md)** — ไปป์ไลน์ e-Tax ตามมาตรฐาน ETDA พร้อม Enveloped XML-DSig, Queue Worker และ HMAC Callback (Phase 24)
* 📈 **[Cash Flow Forecasting & Financial Health Analytics](document/19_cash_flow_forecasting_engine.md)** — เอนจินวิเคราะห์กระแสเงินสด 3 สถานการณ์, SVG Trajectory Curve และอัตราส่วนสภาพคล่อง (Phase 25)
* 🤖 **[AI OCR Document Ingestion & Human Verification](document/20_ai_ocr_document_ingestion.md)** — สกัดข้อมูลใบเสร็จด้วย Universal Vision Hub, Modulo 11 Thai Tax ID และ Split-Screen Reviewer (Phase 26)
* 🚀 **[Web Setup Wizard Architecture & Installer Lifecycle](document/21_setup_wizard_installer_lifecycle.md)** — วงจรชีวิตตัวติดตั้งระบบผ่านเว็บ, Setup Token, Preflight, Atomic `.env` และ Lockout
* 🔄 **[Payroll Policy, Approval & GL Flow](document/11_payroll_policy_gl_flow.html)** — Interactive workflow ของการคำนวณ อนุมัติ จ่ายเงิน และลงบัญชี (Phase 16B)
* 📂 **[DMS Document Lifecycle](document/12_document_management_lifecycle.html)** — Interactive intake, versioning และ expiry ของระบบเอกสารองค์กร (Phase 17)
* 🔐 **[2FA TOTP & Privileged Gate Sequence](document/13_two_factor_auth_sequence.html)** — Interactive sequence ของการยืนยันตัวตนสองขั้นตอนและอุปกรณ์ที่เชื่อถือได้ (Phase 18)
* ⚡ **[Approval Workflow Instance Lifecycle](document/14_approval_workflow_engine.html)** — Interactive lifecycle ของการประเมินสายอนุมัติพลวัต (Phase 20)
* 💳 **[PromptPay Thai QR & Payment Gateway Settlement Flow](document/15_payment_gateway_settlement.html)** — Interactive sequence ของการสร้าง QR พร้อมเพย์, ยืนยัน Opn charge และ signed settlement bridge (Phase 23)
* 📋 **[Master Dialog Standard & Editor Coordinator Lifecycle](document/16_master_dialog_editor_lifecycle.html)** — Interactive lifecycle ของระบบ modal dialog สร้าง/แก้ไข, การตรวจจับ dirty state, การยืนยันก่อนปิด และการป้องกันส่งซ้ำระดับ client/backend (Phase 23.1)

---

## ใช้ทำงานอะไร

โปรเจกต์นี้ใช้เป็นระบบบริหารองค์กรแบบรวมศูนย์ (Company OS) ที่เชื่อมโยงทุกแผนกเข้าด้วยกัน:

- **ฝ่ายบริหาร (Executive & Management)**: เห็นภาพรวมสุขภาพบริษัท รายได้ ค่าใช้จ่าย กำไร กระแสเงินสด Pipeline โครงการ ความเสี่ยง และ Task Overdue แบบ Real-time
- **ฝ่ายขาย (Sales & CRM)**: จัดการรายชื่อลูกค้า (Customers), ผู้ติดต่อ (Contacts), ดีลการขาย (Deals Pipeline), ใบเสนอราคา (Quotations), กิจกรรมติดตาม (Activities/Follow-ups)
- **ฝ่ายบัญชีและการเงิน (Finance & Treasury)**: ออกใบแจ้งหนี้ (Invoices), ใบลดหนี้/เพิ่มหนี้ (CN/DN), ใบวางบิล (Billing Notes), รับชำระเงิน (Payments), ใบสำคัญรับ/จ่าย (PV/RV), บันทึกค่าใช้จ่าย (Expenses), จัดการบัญชีธนาคาร (Bank Accounts), กระทบยอดสเตทเมนต์ (Bank Reconciliation), เงินสดย่อย (Petty Cash), ทะเบียนเช็ค (Cheques/PDC), payroll, payslip และ workpaper ภ.ง.ด.1/ประกันสังคม
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
| Test Suite | PHPUnit | Automated Feature/Unit Tests (380+ passed tests, 2,800+ assertions) |
| AI Vision Hub | OpenAI-Compatible API | รองรับ Local Ollama (qwen2.5-vl) และ Cloud Hubs (GPT-4o, Gemini) |
| Digital Signature | OpenSSL / XML-DSig | มาตรฐาน ETDA XML Canonicalization (C14N) และ RSA-SHA256 |
| Code Quality | Laravel Pint, ESLint, Prettier | Code Formatting & Static Analysis |

---

## Module Overview

| Module | หน้าที่หลัก | Phase ที่พัฒนา |
| --- | --- | --- |
| **Organization** | โครงสร้างบริษัท Multi-tenant, สาขา (Branch), ฝ่าย (Division), แผนก (Department) | Phase 1, 1.1 |
| **User & Access** | จัดการผู้ใช้, คำเชิญ (Invite), RBAC Roles & Permissions, Disable/Enable ผู้ใช้ | Phase 1, 1.1 |
| **Two-Factor Auth (2FA)** | TOTP RFC 6238, Recovery Codes, trusted device 1-90 วัน และ Role-based policy (ปิดเป็นค่าเริ่มต้น) | Phase 18 |
| **HR & Positions** | ทะเบียนพนักงาน (แยกอิสระจาก User), ทะเบียนตำแหน่งงาน (`positions`), นำเข้าพนักงานแบบชุด (Bulk Excel/CSV Import) พร้อมเข้ารหัสข้อมูลส่วนบุคคล (AES-256) | Phase 19, 23.2, 23.3 (Done) |
| **Attendance & Roster** | จัดกะรายวัน/รายเดือน (Roster Matrix), นำเข้าเวลาสแกนนิ้ว (.xlsx, .csv), คิดกะข้ามวัน (Overnight Shifts), คำนวณ OT 3 อัตรา (1.5x/2x/3x) และหักมาสาย | Phase 19, 23.3 (Done) |
| **e-Tax Gateway** | จัดเตรียมและนำส่งใบกำกับภาษีอิเล็กทรอนิกส์ XML-DSig มาตรฐาน ETDA, คิวส่งเบื้องหลัง, Webhook HMAC-SHA256, ดึงสถานะกรมสรรพากรอัตโนมัติ | Phase 12, 24 (Done) |
| **Cash Flow Forecasting** | พยากรณ์กระแสเงินสด 30-180 วัน 3 สถานการณ์ (Base, Best, Worst), กราฟ Trajectory Curve, อัตราส่วนสภาพคล่อง (CR, QR, DSO, DPO, Runway) | Phase 25 (Done) |
| **AI OCR Ingestion** | สกัดข้อมูลใบเสร็จ/ใบแจ้งหนี้ด้วย AI Vision Hub, ตรวจสอบ Tax ID Modulo 11, หน้าจอ Split-Screen Reviewer ตรวจรับ (No Auto-GL Guard) | Phase 26 (Done) |
| **Web Setup Wizard** | ตัวติดตั้งระบบผ่านเว็บ 4 ขั้นตอน ควบคุมด้วย Setup Token, Preflight Check, สร้าง `.env` แบบ Atomic, รัน 58 Migrations, สร้าง Owner อัตโนมัติ | Setup Wizard (Done) |
| **Approval Workflow** | Versioned workflow definition, threshold, user/manager/role approver, snapshot, delegation, SoD และ approval inbox | Phase 20 |
| **Operational Notifications** | Durable outbox, retry/dead-letter, encrypted LINE/Slack/Telegram, opt-in preferences, quiet hours และ safe daily digest | Phase 21 |
| **Audit Log** | บันทึกประวัติการทำงานสำคัญทุกจุด พร้อม Before/After Snapshot และ User Tracker | Phase 1, 1.1 |
| **CRM** | ฐานข้อมูลลูกค้า (Customers), ผู้ติดต่อ (Contacts), Primary Contact, ข้อมูลภาษี | Phase 2 |
| **Sales Pipeline** | ดีลการขาย (Deals), Stage Flow, Won/Lost Rules, กิจกรรม (Activities & Timeline) | Phase 2 |
| **Quotations** | ใบเสนอราคา, สถานะ Draft/Sent/Approved/Rejected/Expired, แปลงเป็น Invoice | Phase 9 |
| **Product Catalog** | แคตตาล็อกสินค้า/บริการ, ราคา, รูปแบบภาษี (Tax Modes: Exclusive, Inclusive, No Tax) | Phase 3, 6, 7 |
| **Invoices** | สร้างใบแจ้งหนี้จากดีลหรือ Manual, คำนวณภาษีฝั่ง Server, รองรับ Discount & VAT Included | Phase 3, 6, 7, 8 |
| **E-Tax & Invoicing** | ใบกำกับภาษีอิเล็กทรอนิกส์ XML มาตรฐาน ETDA, SHA256 Hash, Private Storage, RD Prep Text Export | Phase 12 |
| **Payments** | รับชำระเงิน (Partial/Full), ป้องกัน Overpay ด้วย DB Lock, Payment Reversal | Phase 3, 10 |
| **Expenses** | บันทึกค่าใช้จ่าย, แนบสลิป, ลำดับอนุมัติ (Draft -> Approved -> Paid -> Rejected), ผูก PO/Project | Phase 3, 7, 8 |
| **Suppliers** | ทะเบียนคู่ค้า/ผู้ขาย (Suppliers Master), ข้อมูลภาษี, ช่องทางติดต่อ | Phase 7 |
| **Purchase Orders** | ใบสั่งซื้อ (PO), Itemized Lines, อนุมัติ/ยกเลิก, พิมพ์/PDF, เชื่อมโยง Expense/GRN | Phase 7, 8 |
| **Inventory & GRN** | ใบรับสินค้า (Goods Receipt), Stock Movement Ledger, ปรับยอด/ส่งคืน, ต้นทุนเฉลี่ย | Phase 8 |
| **Inventory Operations** | หลายคลังสินค้า (Multi-Warehouse), Bin Locations, โอนย้ายสต็อก, Lot & Expiry, Barcode/QR | Phase 15 |
| **Tax & Aging Reports** | ภาษีขาย (ภ.พ.30), ภาษีซื้อ, หัก ณ ที่จ่าย (ภ.ง.ด.3/53), อายุหนี้ลูกหนี้/เจ้าหนี้ (AR/AP Aging) | Phase 8 |
| **Commercial Docs** | ใบลดหนี้/เพิ่มหนี้ (CN/DN), ใบวางบิล (Billing Note), ใบส่งของ (DO), ใบขอซื้อ (PR), ใบสำคัญ (PV/RV) | Phase 9 |
| **Treasury & Banking** | บัญชีธนาคารเข้ารหัส, นำเข้า CSV Bank Statement & Reconciliation, เงินสดย่อย, ทะเบียนเช็ค | Phase 10 |
| **General Ledger** | Chart of Accounts, accounting periods, immutable double-entry journals, source posting, trial balance และ account ledger | Phase 11 |
| **Fixed Assets** | Asset register, straight-line monthly depreciation/GL, disposal/write-off, attachment proof และ GL posting | Phase 13 |
| **Multi-Currency & FX** | Currency/rate master, document snapshots, realized/unrealized FX และ AR revaluation | Phase 14 |
| **Payroll** | Payroll profile, tax/social-security policy แบบ effective-dated, calculation snapshot, payslip PDF, PND1/SSO workpaper CSV และ GL posting | Phase 16B |
| **Documents (DMS)** | คลังเอกสาร private, versioning, scan gate, sensitivity, expiry alerts, parent authorization, retention/legal hold, quarantine/archive และ explicit purge | Phase 17, 18.1 |
| **Projects & Tasks** | แปลงจาก Won Deal, สมาชิกโครงการ (Project Members), งานย่อย (Tasks, Checklists, Comments) | Phase 4, 7 |
| **Notifications** | กระดิ่งแจ้งเตือน In-App, อีเมลคิวแจ้งเตือน (PO, Invoice Due, Assign, Invite), Preferences | Phase 8 |
| **Dashboards** | Admin, Executive, Finance, Delivery, Sales, My Work, Procurement/Inventory, HR/Payroll และ Portal แบบแยกสิทธิ์ พร้อมตัวกรอง กราฟ และ filtered drill-down; metric ที่ source ยังไม่พร้อมระบุ unavailable | Phase 1–6, 23.2 |

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
- **Admin Dashboard**: แสดงผู้ใช้ที่ active, pending invites, role distribution และ audit events ตามสิทธิ์ข้อมูล; Security Alerts ยังไม่พร้อมจนกว่าจะกำหนด event source และเกณฑ์ alert

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

## แผนการพัฒนาและสถานะ (Roadmap: Production Prep & Phases 11–18)

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

### Phase 12: E-Tax & RD Preparation
- **e-Tax Integration Layer**: สร้าง XML ต่อองค์กรสำหรับ Tax Invoice, Receipt, Credit Note และ Debit Note; เก็บ private พร้อม hash และ audit trail
- **Provider Boundary**: มี signing/submission adapter, certificate reference และ queued retry; ค่าเริ่มต้น fail-closed จนกว่าจะเชื่อม certified provider
- **RD Prep Text Export**: ส่งออก staging text สำหรับ ภ.ง.ด. 3/53 โดยต้องตรวจสอบ format ปัจจุบันของกรมสรรพากรก่อน upload; ภ.ง.ด. 1 อยู่ใน Phase 16 Payroll

### Phase 13: Fixed Assets & Depreciation
- **Asset Register**: Asset category พร้อม account mapping, custody/location, proof attachment และแหล่งต้นทุนจาก approved Expense หรือ GRN
- **Depreciation Engine**: คำนวณค่าเสื่อม straight-line รายเดือนแบบ idempotent พร้อม immutable GL posting และ accounting period lock
- **Disposal & Write-off**: จำหน่าย/ตัดจำหน่าย, รับ proceeds และบันทึกกำไรหรือขาดทุนเข้า GL

### Phase 14: Multi-Currency & FX Management
- **Currency & Exchange Rates**: Currency master และ exchange rate ต่อ organization โดย `Organization.currency` เป็น base currency
- **Immutable FX Snapshot**: Invoice, Quotation, PO, Expense, CN/DN และ Payment เก็บ rate/base amount ณ วันที่เกิดรายการ; history ไม่ recalculation ตาม rate ล่าสุด
- **FX Accounting**: Realized FX ตอนรับชำระ Invoice, unrealized AR revaluation สิ้นงวด, reversible journal และ scheduled commands `fx:revalue` / `fx:reverse-revaluations`
- **Bank GL Mapping**: Bank account เลือก Chart of Account ได้ และ Fixed Asset disposal proceeds ต้องเลือก bank account ที่มี GL mapping

### Phase 15: Advanced Inventory & Barcode/QR Operations (Done)
- **Multi-Warehouse & Bin Locations**: ระบบหลายคลังสินค้าและระบุตำแหน่งจัดเก็บ (Bin/Rack)
- **Stock Transfer**: โอนย้ายสินค้าระหว่างคลังและสาขา
- **Reorder Points & Alerts**: กำหนดจุดสั่งซื้อซ้ำและแจ้งเตือนเมื่อสินค้าใกล้หมด
- **Barcode & QR Scanner**: รองรับการสแกน Barcode/QR ผ่านกล้องมือถือหรือเครื่องสแกนในการรับ/ส่งสินค้า

### Phase 16B: Payroll, Social Security & Tax (Done)
- **Payroll Profile & Policy Versioning**: profile พนักงานผูก User, policy ภาษี/ประกันสังคมแบบ effective-dated และ snapshot ต่อ run
- **Payroll Lifecycle & GL**: `draft -> calculated -> approved -> paid`, post salary/social-security expense และ payroll liabilities เข้า GL; payment ไม่ใช้ `VendorPayment`
- **Payslip / Workpapers**: payslip PDF แบบ owner-or-finance guard และ CSV workpaper ภ.ง.ด.1/ประกันสังคม; ต้องตรวจรูปแบบและข้อกำหนดล่าสุดก่อนยื่นจริง

### Phase 17-18.1: Enterprise Document Management (Done)
- **Central Document Repository**: จัดเก็บเอกสารองค์กรแยกตาม Organization และ Category
- **Document Versioning & Integrity**: บันทึกประวัติเวอร์ชันไฟล์พร้อม SHA256 checksum และบันทึก change notes
- **Sensitivity Tiers & Granular RBAC**: ควบคุมสิทธิ์ 5 ระดับ: `org_internal`, `department_restricted`, `finance_confidential`, `hr_confidential`, `executive_confidential`
- **Polymorphic Document Links**: ผูกเอกสารเข้ากับ Invoice, Expense, Fixed Asset, หรือ User
- **Compliance Enforcement**: category policy คำนวณ `retention_until`, default renewal และ legal hold; download/link ต้องผ่าน parent authorization, failed scan ถูก quarantine, scheduler archive เอกสารครบกำหนด และ purge ต้องใช้ explicit command

### Phase 18: Security 2FA (Done - polish planned)
- Offline TOTP, encrypted secret, recovery codes, owner reset, rate limit และ audit log. Policy 2FA ปิดเป็นค่าเริ่มต้น และตั้ง trusted device ได้ 1-90 วัน

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
| **Phase 12** | **Done (application layer)** | E-Tax XML/private storage, signing-submission adapter boundary, audit/retry และ RD Prep staging export; live submission requires certified provider onboarding |
| **Phase 13** | **Done** | Asset register, straight-line monthly depreciation/GL, disposal/write-off, attachment proof และ reports |
| **Phase 14** | **Done** | Currency/rate master, immutable document/payment snapshot, realized FX, AR revaluation/reversal และ bank-to-GL mapping |
| **Phase 15** | **Done** | Multi-warehouse & bins, stock transfer, reorder alerts, lot/expiry, Barcode/QR scanning |
| **Phase 16B** | **Done** | Payroll profile/policy versioning, calculation, payslip PDF, workpaper CSV and GL posting |
| **Phase 17** | **Done** | DMS core, private versioned documents, links, sensitivity and expiry alerts |
| **Phase 18.1** | **Done** | Parent authorization, retention calculation/legal hold, scheduled quarantine/archive and explicit purge |
| **Phase 19** | **Done** | HR core, attendance clock-in/out, leave balance/requests, shift/holiday and locked payroll summary |
| **Phase 20** | **Done** | Dynamic approval workflow engine, multi-step routing, SoD guard, temporary delegations, and approval inbox |
| **Phase 21** | **Done** | Operational notification outbox, encrypted external channels, retry/dead-letter, quiet hours and safe digest |
| **Phase 22** | **Done** | Passwordless customer/supplier portals, scoped commercial data, online quotation acceptance, vendor-bill quarantine and private DMS downloads |
| **Phase 23** | **Done** | QR, Settings, Opn adapter, reconciliation and local tests complete; live settlement bridge and sandbox/scan certification deferred |
| **Phase 23.1** | **Done** | มาตรฐาน Create/Edit Dialog ทั่วระบบ 60 หน้าจอ, single active editor coordinator (`useEditorCoordinator`), deep dirty detection, discard guard, idempotent submission |
| **Phase 23.2** | **Done** | Universal Dashboard Date Filters, Visual Charts, Filtered Drill-down |
| **Phase 23.3** | **Done** | Employee Master (decoupled from user), Position Master (`positions` table), Bulk Import (Excel/CSV), Roster Matrix, Excel attendance import, Overnight shifts, 1.5x/2x/3x OT, Non-user payslip email delivery |
| **Phase 24** | **Done** | e-Tax Gateway Pipeline: ETDA XML-DSig Signature (RSA-SHA256), Queue Worker, Webhook Callback (HMAC-SHA256), RD Status Inquire Cron |
| **Phase 25** | **Done** | Cash Flow Forecasting & Financial Health Analytics: 3 Simulation Scenarios (Base/Best/Worst), Interactive SVG Trajectory Curve, Liquidity Ratios (CR, QR, DSO, DPO, Runway), Automated Daily Snapshots |
| **Phase 26** | **Done** | AI OCR Document Ingestion: Universal Vision Client (Ollama / OpenAI), PDF JPEG Extraction, Thai Tax ID Modulo 11 Validator, Split-Screen Reviewer, Human-in-the-Loop Guard (No Auto-GL) |
| **Setup Wizard** | **Done** | Web-based GUI Installer 4 ขั้นตอน, Setup Token Guard (>= 32 chars), Preflight Checks, Atomic `.env` Generation (chmod 0600), 58 Migrations, Owner & Master Data Provisioning, Permanent Lockout (`installed.json`) |

---

## Routes Summary

| Route | Module / หน้าจอ | สิทธิ์ที่ต้องใช้ (Permission) |
| --- | --- | --- |
| `/dashboard` | Admin Dashboard | `dashboard.view` |
| `/executive-dashboard` | Executive Dashboard | `dashboard.view` |
| `/finance-dashboard` | Finance Dashboard | `expenses.view` |
| `/delivery-dashboard` | Delivery Dashboard | `dashboard.view` |
| `/sales-dashboard` | Sales Dashboard | `sales.dashboard.view` |
| `/my-work` | งานที่มอบหมายให้ผู้ใช้ | `tasks.view` |
| `/procurement-dashboard` | Procurement/Inventory Dashboard | สิทธิ์ต้นทางของแต่ละ widget |
| `/hr-dashboard` | HR/Payroll Dashboard | สิทธิ์ HR หรือ Payroll ตาม widget |
| `/vendor-payments` | รายการ Vendor Payments แบบอ่านอย่างเดียว | `expenses.view` |
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
| `/general-ledger` | Chart of Accounts, Journals, Trial Balance | `gl.view` |
| `/bank-accounts` | Bank Accounts Master (บัญชีธนาคาร) | `treasury.accounts.view` |
| `/bank-statements` | CSV Import & Reconciliation Screen | `treasury.reconciliation.view` |
| `/petty-cash` | Petty Cash Funds & Requisitions | `petty_cash.view` |
| `/cheques` | Cheque & PDC Register (ทะเบียนเช็ค) | `cheques.view` |
| `/treasury-reports` | Treasury & Position Reports | `treasury.reports.view` |
| `/fixed-assets` | Fixed Assets Register & Depreciation | `fixed_assets.view` |
| `/currencies` | Multi-Currency & Exchange Rates | `currencies.view` |
| `/inventory-operations` | Multi-Warehouse, Bins, Transfers & Lots | `inventory.view` |
| `/expenses` | Expenses & Approvals | `expenses.view` |
| `/payroll` | Payroll, policy versions, runs, payslips and workpaper exports | `payroll.view` |
| `/documents` | Enterprise Document Management (DMS) | `documents.view` |
| `/hr/attendance` | Attendance Clock-In/Out, Leave Requests & Payroll Summary | `attendance.view` |
| `/workflows` | Dynamic Approval Definitions, Step Config & Approval Inbox | `workflows.view` |
| `/projects` | Projects & Project Members | `projects.view` |
| `/tasks` | Tasks, Checklists & Comments | `tasks.view` |
| `/users` | User Management & Invitations | `users.view` |
| `/roles` | Role & Permission Matrix | `roles.view` |
| `/audit-logs` | Audit Trail & Activity Logs | `audit.view` |
| `/two-factor/setup` | 2FA TOTP Enrollment & Recovery Codes | `auth` |
| `/two-factor-challenge` | 2FA Login Challenge & Trusted Device | `auth` |
| `/settings/organization` | Organization, Sequences & 2FA Policy Settings | `settings.organization.view` |
| `/settings/notifications` | Notification Preferences and External Channel Opt-in | authenticated user |
| `/settings/payment-gateway` | PromptPay settings, receiving bank and settlement event review | `settings.organization.view` / `.update` |
| `/invoice-payment/{invoice}` | Scoped PromptPay invoice QR | matching customer portal or verified staff with `invoices.view` |
| `/portal` | Customer & Supplier Passwordless Portal | Magic Link & revocable portal session |
| `/api/gateway/{config}/settlement` | Verified HMAC Settlement Bridge | HMAC + timestamp window + throttle |
| `/api/gateway/{config}/opn` | Opn Event Verification Intake | Authenticated merchant API lookup + throttle |
| `/settings/organization-structure` | Branches, Divisions, Departments | `settings.structure.view` |
| `/hr/positions` | Position Master Management | `hr.employees.view` / `hr.employees.manage` |
| `/hr/employees/import` | Bulk Employee Import (.xlsx, .csv) | `hr.employees.manage` |
| `/hr/attendance-import` | Attendance Import & Reconciliation Engine | `hr.attendance.import` |
| `/hr/roster` | Shift Scheduling & Roster Matrix | `hr.shifts.manage` |
| `/e-tax` | e-Tax Dashboard, Document Lifecycle & RD Prep | `e_tax.view` / `e_tax.manage` |
| `/finance/cash-flow` | Cash Flow Forecasting & Trajectory Analytics | `cash_flow.view` / `cash_flow.manage` |
| `/finance/ocr` | AI OCR Intake & Split-Screen Reviewer | `ocr.view` / `ocr.upload` / `ocr.convert` |
| `/settings/ai-configuration` | AI Vision Hub Settings (Ollama / OpenAI) | `settings.ai.view` / `settings.ai.manage` |
| `/setup` | Web Setup Wizard Installer | Guarded: `SETUP_TOKEN` or `enable.php` (404 if locked) |

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
- **External Portal Isolation**: Portal session แยกจาก Staff authentication, Magic Link ใช้ครั้งเดียวและหมดอายุ, ข้อมูล/เอกสารถูกกรองด้วย `org_id` และ customer/supplier identity; รับไฟล์ผู้ขายเป็น `pending_scan` และไม่สร้าง AP draft อัตโนมัติ

---

## การติดตั้งและเริ่มต้นใช้งานระบบ (Installation & Getting Started)

ระบบรองรับการติดตั้ง 2 รูปแบบ:
1. **[การติดตั้งผ่าน Web Setup Wizard (สำหรับ Web Server / Production)](#1-การติดตั้งผ่าน-web-setup-wizard-web-based-installer)** — ติดตั้งสะดวกผ่านเบราว์เซอร์ ไม่จำเป็นต้องมี Composer หรือ Node.js บน Server
2. **[การติดตั้งผ่าน Command Line (สำหรับ Local Development)](#2-การติดตั้งผ่าน-command-line-local-development)** — สำหรับนักพัฒนาที่ต้องการรันผ่าน CLI, migrate seed ข้อมูลทดสอบ และรัน Dev Server

---

### 1. การติดตั้งผ่าน Web Setup Wizard (Web-based Installer)

เหมาะสำหรับการ Deploy ขึ้น Web Server จริงจาก Release Bundle (ที่มี `backend/vendor/` และ `backend/public/build/` มาแล้ว)

#### 📋 สิ่งที่ต้องเตรียม (Server Requirements)
- **PHP 8.3 ขึ้นไป** พร้อมส่วนขยาย: `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `dom`, `simplexml`, `curl`
- **Database**: MySQL 8.0+ หรือ MariaDB 10.5+ (สร้าง Database เปล่าไว้ล่วงหน้า)
- **สิทธิ์การเขียนไฟล์ (Writable Permissions)**: โฟลเดอร์ `backend/storage/`, `backend/bootstrap/cache/`, และไดเรกทอรีรากของ Backend เพื่อสร้างไฟล์ `.env`
- **Web Document Root**:
  - แนะนำ: ชี้ Document Root ไปที่ `backend/public`
  - หรือ: ชี้ที่ไดเรกทอรีรากของโปรเจกต์ (มี `setup/index.php` รองรับ)

#### 🚀 ขั้นตอนการติดตั้งทีละสเต็ป (Step-by-Step)

##### ตรวจสอบ Server Requirement ก่อนติดตั้ง
- เปิด `https://your-domain/setup/system-requirements.php` ได้โดยไม่ต้องใช้ `SETUP_TOKEN`
- หน้านี้ตรวจ PHP, required extensions (`pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `dom`, `simplexml`, `curl`), runtime limits, release bundle และสิทธิ์เขียนไฟล์ โดยไม่แสดง password, token หรือ `phpinfo()`
- รายการ Failed/Warning แสดงคำแนะนำเฉพาะสาเหตุ; ปุ่ม Fix มีเฉพาะกรณีสร้าง `storage/` หรือ `bootstrap/cache/` ที่ยังไม่มีและ web-server user สร้างได้

##### ขั้นตอนที่ 1: เปิดใช้งานตัวติดตั้ง (Activate Installer)
เพื่อความปลอดภัย ตัวติดตั้งจะถูกปิดใช้งานเป็นค่าเริ่มต้น ให้เลือกทำอย่างใดอย่างหนึ่ง:
- **วิธีที่ 1 (แนะนำและสะดวกที่สุด)**:
  1. ไปที่โฟลเดอร์ `backend/setup/`
  2. คัดลอกไฟล์ `enable.php.example` แล้วเปลี่ยนชื่อเป็น `enable.php` (พาธเต็ม: `backend/setup/enable.php`)
  3. เปิดไฟล์ `backend/setup/enable.php` แล้วแก้ค่า Token สุ่มความยาว**อย่างน้อย 32 ตัวอักษร**:
     ```php
     <?php
     return 'your-random-secret-token-32-chars-long-here';
     ```
- **วิธีที่ 2 (ตั้งค่าผ่าน Server Environment)**:
  - ตั้งค่า Environment Variable ของ Web Server ชื่อ `SETUP_TOKEN` ให้มีค่าเป็น Random Token ความยาวอย่างน้อย 32 ตัวอักษร

##### ขั้นตอนที่ 2: เข้าสู่หน้า Setup Wizard บนเบราว์เซอร์
- เปิดเบราว์เซอร์ไปที่: `http://your-domain/setup` (หรือ `http://localhost/ERP/setup`)
- **Step 1 (Security & Preflight)**:
  - ระบบจะตรวจสอบความพร้อมของเซิร์ฟเวอร์ (PHP version, extensions, writable folders)
  - นำ Token ความยาว $\ge 32$ ตัวอักษรที่ตั้งไว้ในขั้นตอนที่ 1 มากรอกในช่อง **"Setup token"**
  - กดปุ่ม **"Continue"**

##### ขั้นตอนที่ 3: กำหนดค่าแอปพลิเคชันและฐานข้อมูล
- **Step 2 (Server & DB)**:
  - **Application Name**: ชื่อระบบ (เช่น `ERP Company`)
  - **Application URL**: URL เต็มของระบบ เช่น `https://erp.yourdomain.com` (ต้องขึ้นต้นด้วย `http://` หรือ `https://`)
  - **Database Host & Port**: โฮสต์และพอร์ตฐานข้อมูล (เช่น `127.0.0.1`, พอร์ต `3306`)
  - **Database Name**: ชื่อฐานข้อมูลที่เตรียมไว้ (ต้องเป็น Database เปล่าที่ยังไม่มีตาราง `migrations`)
  - **Username & Password**: บัญชีผู้ใช้ฐานข้อมูล
  - กดปุ่ม **"Test connection and continue"** เพื่อทดสอบการเชื่อมต่อจริง

##### ขั้นตอนที่ 4: ข้อมูลองค์กรและผู้ดูแลระบบสูงสุด (Owner)
- **Step 3 (Organization & Owner)**:
  - **Organization Name**: ชื่อบริษัท/องค์กร
  - **Legal Name / Tax ID**: ชื่อนิติบุคคลและเลขประจำตัวผู้เสียภาษี 13 หลัก (ใส่หรือไม่ก็ได้)
  - **Owner Name & Email**: ชื่อและอีเมลของผู้ดูแลระบบสูงสุด (Owner)
  - **Owner Password**: รหัสผ่านสำหรับเข้าสู่ระบบของ Owner (บังคับความยาว**อย่างน้อย 12 ตัวอักษร**)
  - กดปุ่ม **"Review installation"**

##### ขั้นตอนที่ 5: ยืนยันการติดตั้งจริง
- **Step 4 (Install)**:
  - ตรวจสอบสรุปข้อมูลปลายทาง
  - พิมพ์คำว่า **`INSTALL`** ในช่องยืนยัน
  - กดปุ่ม **"Install ERP"**
  - ระบบจะดำเนินการเบื้องหลังโดยอัตโนมัติ:
    1. สร้างไฟล์ `.env` ที่ปลอดภัย พร้อมสุ่ม `APP_KEY` และล็อกสิทธิ์ `chmod 0600`
    2. รัน Database Migrations โครงสร้างตารางทั้งหมด
    3. สร้างบทบาท (Roles), สิทธิ์ (Permissions Catalog), โครงสร้างสาขา/แผนก และบัญชี Owner
    4. บันทึกไฟล์ล็อกการติดตั้งที่ `backend/storage/app/installer/installed.json`
    5. ลบไฟล์ `backend/setup/enable.php` ทิ้งอัตโนมัติเพื่อความปลอดภัย
  - เมื่อเสร็จสิ้น ระบบจะ Redirect ไปที่หน้า Login (`/login?installed=1`) สามารถเข้าสู่ระบบด้วยอีเมลและรหัสผ่านของ Owner ได้ทันที

> 🔒 **ความปลอดภัยหลังติดตั้ง**: หลังติดตั้งสำเร็จ หน้า `/setup` จะตอบกลับด้วย **HTTP 404** เสมอเพื่อปิดกั้นการรันซ้ำผ่านเว็บอย่างสมบูรณ์

---

### 2. การติดตั้งผ่าน Command Line (Local Development)

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
Pass: 380+ passed, 2,800+ assertions (Full Backend Regression Suite 100% Pass)
```

---

## Development Notes

- Phase 23 integration contract, verification results and workflow diagram: [Payment Gateway](docs/PHASE_23_PAYMENT_GATEWAY.md). Default disabled. Opn checkout and event verification have fixture coverage; the separate HMAC bridge requires independent final-settlement evidence before Payment/GL. GB Prime Pay and 2C2P are design boundaries, not implemented adapters.

- `checklist.md` คือ source of truth ของสถานะงาน
- `docs/database/DATABASE.md` คือ source of truth ของ schema
- ก่อนเพิ่ม module ใหม่ควรแก้ docs และ checklist ก่อน
- ห้ามรับ `org_id` จาก client สำหรับ business write
- ห้าม expose secret/token/password ใน Inertia props
- Write action สำคัญควรมี audit log
