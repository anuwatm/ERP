# Current Implementation Reference

สถานะล่าสุด (2026-09-22): **Phase 1 - 26 (รวม Phase 18.1, 23.1, 23.2, 23.3, 24, 25, 26) และ Setup Wizard เสร็จสมบูรณ์และผ่านการตรวจรับ 100% (Complete & Closed)** — ครอบคลุม Core MVP, Commercial Docs, Tax Reports, Treasury, GL, Multi-Currency, Inventory, Payroll, DMS, 2FA, Workflows, Notifications, Self-Service Portals, Payment Gateway, System-wide Dialog Standard, Dashboard Redesign, HR Employee Master & Shifts & Reconciliation, e-Tax Gateway XML-DSig, Cash Flow Forecasting, AI OCR Document Ingestion (Local AI & Cloud Hubs), และ Web Setup Wizard. ผ่านการทดสอบ Full Backend Regression 380+ tests (3,680+ assertions ผ่าน 100%), Frontend Unit 23 tests, Pint/ESLint/Prettier/Vite production build 100% สะอาดสมบูรณ์.

เอกสารนี้เป็นภาพรวม implementation ปัจจุบัน ใช้สำหรับอ่านระบบร่วมกับ source code; โดย migration, route และ feature tests เป็น source of truth ของ runtime.

## Runtime

| Layer | Current implementation |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Inertia 2 |
| Frontend | React 18, TypeScript, Vite, Tailwind CSS, Headless UI |
| Database | MySQL 8.0+ / MariaDB 10.5+ compatible, 58 migrations, UUID v7 / Ordered UUID |
| Runtime drivers | database session/cache/queue, log mailer in local |
| Locale / timezone | `th` locale; Laravel application timezone `UTC` (Organization timezone `Asia/Bangkok`) |
| Auth | Laravel Breeze local auth, email verification, RBAC 7 บทบาท, optional organization 2FA TOTP (RFC 6238) |
| Setup Engine | Web-based Setup Wizard (`/setup`) พร้อม Security Activation Token, Preflight Server Check, Clean Release Guard, Atomic `.env` Generator, และ Install Lock |

## Implemented Domains

| Phase | Domain | Current capability |
| --- | --- | --- |
| 1-6 | Foundation, CRM, Finance, Delivery, Dashboards | โครงสร้างองค์กร (Branch/Division/Department), 7 roles, audit log, ลูกค้า/ผู้ติดต่อ, ดีลการขาย, ใบแจ้งหนี้/การชำระเงิน/ค่าใช้จ่าย, โครงการ/งานย่อย และแดชบอร์ดกรองตามมิติ |
| 7-9 | Procurement & commercial documents | ระบบออกเลขเอกสารยืดหยุ่น, ผู้ขาย (Suppliers), ใบขอซื้อ/ใบสั่งซื้อ (PR/PO), ใบเสนอราคา (Quotations), ใบลดหนี้/เพิ่มหนี้ (CN/DN), ใบวางบิล, ใบส่งของ, ใบสำคัญรับ/จ่าย (PV/RV), เอกสารพิมพ์ทางการ/PDF |
| 8 | Reporting, tax, GRN, notifications | รายงานภาษี (ภ.พ.30, ภ.ง.ด. 3/53, 50 ทวิ), รายงานอายุหนี้ (AR/AP Aging), ใบรับสินค้า (GRN) และบัญชีคุมสต็อกสินค้า (Moving Average Cost), การแจ้งเตือน In-app และคิวส่งอีเมล |
| 10-11 | Treasury & General Ledger | บัญชีธนาคารเข้ารหัส (`encrypted`), กระทบยอดสเตทเมนต์ (Bank Reconciliation), เงินสดย่อย (Petty Cash), ทะเบียนเช็ค/PDC, ระบบบัญชีคู่ (Double-Entry GL Journals) บันทึกอัตโนมัติจากเอกสารต้นทาง, งวดบัญชี (Period Lock), และรายงาน GL |
| 12, 24 | E-Tax application & Gateway Architecture | สร้าง XML ตามมาตรฐาน ETDA, ท่อประมวลผลลายมือชื่อดิจิทัล Enveloped XML-DSig (C14N, RSA-SHA256, DigestValue, KeyInfo, X.509 cert reference), Provider Gateway (RD Web Service, Certified Provider INET, Sandbox Gateway), SubmitETaxDocument queue worker, และ Webhook Callback HMAC-SHA256 พร้อม Artisan Inquire Status |
| 13-14.1 | Assets & FX | ทะเบียนสินทรัพย์และค่าเสื่อมราคาวิธีเส้นตรง, จัดการสกุลเงินและอัตราแลกเปลี่ยนประวัติศาสตร์, คำนวณ Realized/Unrealized FX อัตโนมัติ, บัญชีเงินฝากเงินตราต่างประเทศ (FCD), สะพานเชื่อม AP/Inventory FX |
| 15 | Inventory operations | คลังสินค้า/ชั้นวาง (Warehouse/Bin), การจัดการล็อตสินค้าและวันหมดอายุ (Lots & Expiry), การโอนย้ายสต็อก, ตรวจนับสินค้า (Stock Count), สแกนบาร์โค้ด/QR Code, และแจ้งเตือนจุดสั่งซื้อใหม่ (Reorder Alert) |
| 16B | Payroll | นโยบายเงินเดือนแบบ Versioned, คำนวณประกันสังคมและภาษีหัก ณ ที่จ่ายอัตโนมัติ (ภ.ง.ด. 1/1ก), จ่ายตรงตัดบัญชีเงินสด/ธนาคาร, สลิปเงินเดือน PDF พร้อม Privacy Guard ห้ามข้ามสิทธิ์, และบันทึกบัญชี GL อัตโนมัติ |
| 17-18.1 | DMS core & compliance | คลังเอกสารรวมศูนย์ (Central DMS Repository), จัดการเวอร์ชันแบบไม่ลบทับ (Immutable Versioning), ตรวจสอบความปลอดภัยไฟล์ (Scan Gate), กำหนดชั้นความลับ 5 ระดับ, สิทธิ์เข้าถึงแบบ Parent Permission, นโยบายอายุเอกสาร 5-7 ปี (Retention Enforcement) และคำสั่ง purge |
| 18 | 2FA | ยืนยันตัวตนสองขั้นตอนแบบออฟไลน์ด้วย TOTP (RFC 6238), รหัสกู้คืนแบบ Single-Use, อุปกรณ์ที่เชื่อถือได้ (Trusted Devices 1-90 วัน), เจ้าของระบบรีเซ็ตสิทธิ์, และบังคับใช้กับบทบาทสิทธิพิเศษ (Owner/Admin/Finance) |
| 19 | HR core, attendance & leave | โปรไฟล์พนักงาน/ผู้บังคับบัญชา/วันหยุด, บันทึกเวลาเข้า-ออกงาน, นโยบายความเป็นส่วนตัวบันทึก IP แบบ SHA-256 Hash และ GPS ตามความยินยอม (Opt-in), บริหารวันลาและโควตาพร้อมระบบคืนวันลาเมื่อคำขอถูกยกเลิก, ล็อกและกลับรายการสรุปเวลาทำงาน (Attendance Summary) |
| 20 | Dynamic approval workflow | เครื่องมือออกแบบสายอนุมัติแบบพลวัต (Versioned Definition), จับคู่ตามเกณฑ์ยอดเงิน (Thresholds), กำหนดผู้อนุมัติตามบุคคล/หัวหน้างาน/บทบาท (Sequential & Parallel First-to-Approve), กฎเข้มงวด SoD ห้ามขออนุมัติตนเอง, มอบหมายงานชั่วคราว (Delegation), กล่องข้อความอนุมัติ (Approval Inbox), และลงบัญชี GL อัตโนมัติเมื่อ Expense ผ่านการอนุมัติ |
| 21 | Operational notifications | ช่องทางแจ้งเตือนทรานแซกชัน (Transactional Outbox Pattern), บัญชีจัดเก็บ Credentials ภายนอกแบบเข้ารหัส (`encrypted:array` สำหรับ LINE OA / Slack / Telegram), ระบบส่งซ้ำแบบ Exponential Backoff สูงสุด 5 ครั้งก่อนเข้า Dead-letter, การตั้งค่าความเงียบ (Quiet Hours), และสรุปรายงานประจำวันสำหรับผู้บริหาร (Safe Daily Digest ไม่รั่วไหลยอดเงินสด) |
| 22 | External portals | พอร์ทัลลูกค้าและคู่ค้าภายนอก (Customer & Supplier Portal), ล็อกอินแบบไร้รหัสผ่านด้วย Magic Link (หมดอายุใน 30 นาที), แยกเซสชันจากสตาฟฟ์ 100%, ลูกค้ากดยืนยันรับใบเสนอราคาและดูใบแจ้งหนี้, คู่ค้าส่งใบวางบิลพร้อมระบบกักกันไฟล์รอสแกน (Scanner Quarantine) |
| 23 | Payment gateway foundation | สร้าง PromptPay Thai QR ตามมาตรฐาน BOT (Tag 29/30 Satang แม่นยำ), ตรวจสอบรายการ Opn API, รับและตรวจสอบลายมือชื่อ HMAC-SHA256 Settlement Bridge, ป้องกันการส่งซ้ำด้วย Idempotency Key, บันทึกการรับชำระและลงบัญชี GL อัตโนมัติ (Live Bridge Deferred) |
| 23.1 | Master dialog standard | มาตรฐานหน้าต่างสร้าง/แก้ไขโมดอลทั้งระบบ (`CreateEditDialog`), ผู้ประสานงานควบคุม Editor ตัวเดียว (`useEditorCoordinator`), ตรวจจับการเปลี่ยนแปลงละเอียดทุกฟิลด์ (Dirty Detection), ป้องกันข้อมูลสูญหาย (`useDiscardGuard`), และกักกันการกดยืนยันซ้ำด้วย Idempotent Submission Key ครอบคลุม 60 หน้า 92 ไฟล์ |
| 23.2 | System-wide dashboard redesign | ปรับปรุงแดชบอร์ด 8 โดเมนภายใน (Executive, Sales, Finance, Delivery, Procurement, HR, Admin, My Work) และ 2 พอร์ทัลภายนอก, ปฏิบัติตาม `Metric Dictionary v1`, แสดงผลกราฟด้วย Native Accessible SVG ที่เข้าถึงได้, ระบบ FilterBar เก็บ State ใน URL, คัดกรอง Widget ตามสิทธิ์จริง (`DashboardAccess`), และปรับผังเมนูเป็น 7 หมวดหมู่มาตรฐาน |
| 23.3 | HR Employee Master, Shifts & Reconciliation | แยกแบบจำลอง `Employee` เป็นอิสระจาก `User`, ตารางกะและการจัดเวร (Roster Matrix Calendar), นำเข้าเวลาสแกนนิ้ว/บัตร รองรับทั้ง Excel (`.xlsx`, `.xls`, `.xml`) และ CSV/TSV, Reconciliation Engine คำนวณชั่วโมงทำงานจริง กะข้ามวัน (Overnight Shift) และคิดอัตรา OT 1.5x / 2.0x / 3.0x ตาม Shift Master, บริหารเงินเดือนพนักงานทั่วไป/พนักงานที่ไม่ใช่ User (Non-User Payroll) และส่งสลิปเงินเดือนทางอีเมล (`PayslipMail`) |
| HR+ | Employee Position Master & Bulk Import | ตารางตำแหน่งงานเป็น Master Data อิสระ (`positions`), เชื่อมโยงกับ `employees.position_id` พร้อม backward-compatible sync, Searchable & Filterable Combobox (`PositionCombobox`) พร้อมปุ่มสร้างตำแหน่งใหม่แบบ Inline Quick-Add, ปรับ Dark Mode Base CSS, และระบบนำเข้าประวัติพนักงานแบบชุด (`EmployeeImportService`) รองรับ Excel/CSV/TSV พร้อม Auto-create Position และเข้ารหัสข้อมูลตาม PDPA |
| 25 | Cash flow forecasting & health analytics | ระบบพยากรณ์กระแสเงินสดล่วงหน้า 30-180 วัน ด้วย 3 สถานการณ์ (Base, Best, Worst), Engine คำนวณ Inflow (AR Aging + Deal Pipeline Probability) และ Outflow (AP Approved Expenses + Open POs + Scheduled Payroll), คำนวณอัตราส่วนสภาพคล่อง Current Ratio, Quick Ratio, DSO, DPO, และ Working Capital Runway, กราฟ Interactive SVG Trajectory Curve, ปรับแต่งพารามิเตอร์จำลอง, และคำสั่งบันทึก Snapshot รายวัน `cashflow:snapshot` |
| 26 | AI OCR document ingestion | สถาปัตยกรรมสกัดข้อมูลเอกสารใบเสร็จ/ใบกำกับภาษีด้วย Universal OpenAI-Compatible Vision (รองรับทั้ง Local AI Ollama/LM Studio และ Cloud Hubs GPT-4o/OpenRouter/Gemini), สกัด JPEG stream จาก Scanned PDF อัตโนมัติ, บังคับ Native JSON Mode, ตรวจสอบเลขผู้เสียภาษี 13 หลักด้วย Modulo 11 และตรวจสอบความถูกต้องทางคณิตศาสตร์ (Math Reconciliation), หน้าจอ Split-Screen Reviewer ตรวจเทียบเอกสารต้นฉบับกับข้อมูลสกัด, แปลงเป็น Assisted Draft Expense/Bill โดยมี Human-in-the-Loop ควบคุม (ห้าม Auto-post GL เด็ดขาด) |
| Setup | Web-based setup wizard | ติดตั้งระบบบน Web Server ผ่านเบราว์เซอร์ (`/setup`), ควบคุมความปลอดภัยด้วย Setup Token (ผ่าน Server Environment หรือไฟล์ `backend/setup/enable.php`), Preflight ตรวจสอบ PHP 8.3+, Extensions, Writable Folders และ Clean Release (ปฏิเสธ `.env` เดิม), ทดสอบการเชื่อมต่อ PDO MySQL, ปฏิเสธฐานข้อมูลที่มีตาราง `migrations` เดิม, สร้าง `.env` แบบ Atomic + `chmod 0600`, รัน Database Migrations, Provision เจ้าของระบบ (Owner), ปิดหน้า `/setup` ด้วย Install Lock (`installed.json`), และลบไฟล์ `enable.php` อัตโนมัติ |

## Current Boundaries & Policies

| Area | Boundary |
| --- | --- |
| Setup Wizard | ทำงานเฉพาะการติดตั้ง instance ใหม่บน release bundle ที่ยังไม่มี `.env` และฐานข้อมูลว่างเท่านั้น; การอัปเกรดเวอร์ชันใช้ CLI Deployment Runbook; หลังติดตั้งสำเร็จ หน้า `/setup` คืนค่า HTTP 404 ถาวร |
| AI / OCR | ผลลัพธ์จากการสกัดข้อมูลทุกประเภทถือเป็น **Assisted Draft** เสมอ ต้องผ่านการรีวิวและยืนยันโดยผู้มีอำนาจ (Human Reviewer) ก่อนแปลงเป็นรายการในระบบ และห้าม Auto-post ลงบัญชีแยกประเภททั่วไป (GL) โดยเด็ดขาด |
| e-Tax Gateway | รองรับโหมด disabled, manual_export, mock และ live provider (RD API / INET / Sandbox); Private Key ต้องจัดเก็บภายนอกฐานข้อมูล (Cloud KMS / HSM / Vault) ห้ามบันทึกใน Database เด็ดขาด |
| Cash Flow Forecast | แสดงการประมาณการกระแสเงินสดเชิงพยากรณ์พร้อม Scenario Snapshot โดยไม่เผยแพร่ยอดเงินสดดิบที่ยังไม่ได้รับการกระทบยอด (Unreconciled Cash) ในฐานะข้อเท็จจริงทางบัญชี |
| HR Attendance to Payroll | สรุปเวลาทำงาน (Attendance Summary) เมื่อกระทบยอดและตรวจสอบแล้ว สามารถนำเข้าสู่ `payroll_items` ได้ แต่จะไม่สร้าง `payroll_runs` หรือลงบัญชี GL อัตโนมัติจนกว่าจะมีการปิดรอบและอนุมัติ Payroll Run ตามสายงาน |
| DMS Compliance | การเข้าถึงเอกสารต้องผ่าน Parent Entity Permission เสมอ; ไฟล์ที่อัปโหลดต้องผ่านสถานะ `scan_status === 'clean'` จึงจะดาวน์โหลดได้; นโยบายการเก็บรักษาเอกสารบัญชี/ภาษี 5-7 ปีเป็นแบบ Append-only โดยคำสั่ง purge ต้องสั่งผ่าน CLI โดยตรงเท่านั้น |
| Payment Gateway | การบันทึกรับเงินและการลงบัญชี GL อัตโนมัติจะเกิดขึ้นเฉพาะเมื่อมี Settlement Webhook ที่ลงลายมือชื่อ HMAC-SHA256 ถูกต้องพร้อม Timestamp Window $\le$ 300s เท่านั้น; การเชื่อมต่อ Provider ฝั่ง Production เป็น Deferred Live Integration |

## Source Navigation

| Need | Source |
| --- | --- |
| Phase state / remaining work | [`../checklist.md`](../checklist.md) |
| Database schema | `backend/database/migrations/*.php` and [`../document/DATABASE_ERD.md`](../document/DATABASE_ERD.md) |
| HTTP routes | `backend/routes/web.php`, `backend/routes/gateway.php` and [`ROUTES_AND_SCREENS.md`](./ROUTES_AND_SCREENS.md) |
| Setup Wizard docs | [`SETUP_WIZARD.md`](./SETUP_WIZARD.md) and [`../backend/setup/README.md`](../backend/setup/README.md) |
| Policies / business behavior | `backend/app/Services`, `backend/app/Policies`, controllers and `backend/tests` |
| System Diagrams | [`../document/README.md`](../document/README.md), [`../document/GROUPS_WORKFLOW.md`](../document/GROUPS_WORKFLOW.md) |

## Verification Snapshot

- `php artisan about`: Laravel 13.x, PHP 8.3.x, MySQL/MariaDB driver
- `php artisan route:list --except-vendor`: 250+ routes
- 58 database migrations
- Full Backend Regression Suite: **380+ tests passed (3,680+ assertions / 0 failures)**
- Setup Wizard Suite: `InstallerTest` **2 passed (6 assertions / 0 failures)**
- Code Quality: Laravel Pint Clean, ESLint Clean (0 errors / 0 warnings), Prettier Clean, TypeScript `tsc` Clean (0 errors), Vite Production Build Clean (0 errors)
