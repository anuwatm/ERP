# Gemini Review & Architecture Reference

Last updated: 2026-11-01 (Phase 1–27 & External Read API Complete; Phase 28 Standalone POS, Phase 28.8.1 & 28.8.2 Local Scope Passed; Phase 28.8.3 Offline Foundation Verified)

Purpose: บันทึกข้อมูลอ้างอิงสถาปัตยกรรม (Architecture Reference), Security Guardrails, ข้อกำหนดเชิงระบบที่ยังมีผลต่อการพัฒนา (Decisions That Still Matter), สรุปผลการตรวจรับงาน (Acceptance Verdicts), และแนวทางการพัฒนาสำหรับ Phase ปัจจุบัน (`Phase 28.8`). รายละเอียดงานย้อนหลัง Phase 1–27 ดูได้ที่ `checklist.md`, `README.md`, `gpt.md`, `docs/`, `document/` และ Git history.

---

## 1. สถานะโครงการปัจจุบัน (Current Status)

**สถานะล่าสุด (2026-11-01): Phase 1–27 และ External Read API (P0–P6) เสร็จสมบูรณ์ 100%; Phase 28 (Standalone POS & ERP Connector), Phase 28.8.1 (Ordering / Delivery) และ Phase 28.8.2 (Membership / Promotions) ผ่านการตรวจรับระดับ Local Scope (PASS) แล้ว; Phase 28.8.3 (Browser / PWA Offline — Foundation Stage) ผ่านการตรวจรับระดับโครงสร้างพื้นฐาน (PASS Foundation) พร้อมพัฒนาส่วน PWA UI, Sync State Machine และ Server Reconciliation ต่อ**

- **Phase 1–27 (รวม Phase 18.1, 23.1, 23.2, 23.3, 24, 25, 26, 27): เสร็จสมบูรณ์และปิดรอบ 100% (Complete & Closed)**
  - Core MVP, CRM/Sales, Finance, Delivery, Multi-role Dashboards, VAT/Number Sequences, Suppliers & POs (Phase 1–7)
  - Commercial Documents, Tax Reports/Aging/WHT, Treasury, Encrypted Bank Accounts, Bank Reconciliation, Petty Cash, Cheques/PDC (Phase 8–10)
  - General Ledger & Double-Entry Accounting, E-Tax XML/RD Prep, Fixed Assets & Straight-Line Depreciation (Phase 11–13)
  - Multi-Currency & Rate Snapshot, AP Subledger (`VendorPayment`), FCD Accounts, Inventory FX Bridge (Phase 14–14.1)
  - Advanced Inventory, Warehouses, Bins, Lots & Expiry, Barcode/QR Scanning, Stock Differencing (Phase 15)
  - Payroll, Social Security, Progressive Tax, Direct Settlement, Payslip PDF & Privacy Guard, ภ.ง.ด. 1 Workpapers (Phase 16B)
  - Enterprise Document Management (DMS), Central Repository, Immutable Versioning, 5-Level Sensitivity RBAC, Retention Policies & Legal Hold (Phase 17 & 18.1)
  - Security 2FA / Offline RFC 6238 TOTP, Encrypted Secrets, Single-Use Recovery Codes, Trusted Devices, Owner Reset (Phase 18)
  - HR Core, Employee Master (`employees` แยกจาก `users`, Position Master `positions`, Bulk Import), Shift Scheduling & Roster Matrix, Attendance Import & Reconciliation (Overnight Shift, OT 1.5x/2x/3x), Non-User Payroll & Payslip Email (Phase 19 & 23.3)
  - Central Dynamic Approval Workflow Engine, Immutable Snapshots, SoD Deadlock Guard, Delegations, Expense Double-Entry GL Posting (Phase 20)
  - Operational Notifications & Outbox Engine, Encrypted Channel Vault (LINE OA / Slack / Telegram), Exponential Backoff & Dead-Letter Log, Safe Daily Digest (Phase 21)
  - Customer & Supplier Self-Service Portals, External Identity Isolation, Magic Link, Vendor Bill Quarantine Gate (Phase 22)
  - PromptPay Thai QR & Payment Gateway Settlement, Exact Satang (Tag 29/30), Opn API Checkout, HMAC-SHA256 Signed Settlement Bridge (Phase 23 — Live Bridge Deferred)
  - System-wide Master Dialog & Single Active Editor Standard 60 หน้า 92 ไฟล์ (Phase 23.1) และ System-wide Dashboard Redesign 8 โดเมน + 2 พอร์ทัล ตาม `Metric Dictionary v1` (Phase 23.2)
  - e-Tax Invoice & RD Prep Gateway Architecture: ETDA XML-DSig (`RSA-SHA256`, `xml-c14n`), Multi-Provider Adapter (`rd_api`, `inet`, `sandbox`), Webhook HMAC Callback, `etax:inquire-status` (Phase 24)
  - Cash Flow Forecasting & Financial Health Analytics: 30–180 Days Projection (Base/Best/Worst), Current/Quick Ratio, DSO, DPO, Working Capital Runway (Phase 25)
  - AI OCR Document Ingestion: Universal OpenAI-Compatible Vision (Local Ollama/LM Studio & Cloud OpenAI/OpenRouter/Gemini), Thai Tax ID Modulo 11, Math Check, Split-Screen Reviewer, Assisted Draft Guard (Phase 26)
  - Manufacturing & Bill of Materials (BOM): Multi-level BOM Explosion, Circular Dependency Guard, Work Order Lifecycle, Stock Issue/Receipt, Auto-GL WIP `1160` & FG `1140` (Phase 27)
- **External Read-only API (P0–P6 — Complete & Verified):**
  - ครอบคลุม 62 GET Endpoints ภายใต้ `/api/v1` (Summaries, Catalog/Inventory, HR/People, Business/Sales/Procurement, Approvals/Webhooks Outbox, Manufacturing/DMS Stream) พร้อม OpenAPI Contract v1 และ Local Browser/HTTP Acceptance
- **Phase 28: Standalone POS & ERP Connector (Local Complete / Production Pending):**
  - แยกแอปพลิเคชันและฐานข้อมูล (`pos/`) ออกจาก ERP (`backend/`) เด็ดขาด, ERP Outbound Connector, Shared `InventoryMovementService`, Signed Events/Checkpoints, Independent Recovery Anchor & Fence, Encrypted 90-Day Archive, โมดูล Retail / Restaurant / Pharmacy, และอัปเกรดฐานข้อมูลจริงตาม Config แล้ว (`document/DATABASE_UPGRADE_20261027.md`)
  - **Phase 28.8.1: Ordering / Delivery (Local Operational Scope — PASSED):** รองรับการจัดส่งและรับที่ร้านสำหรับ Retail และ Restaurant POS (`prepaid_cash` / `cod_cash`), ระบบจองโควตาสต็อก/ครัว, แยกเงินสด COD (`collect` / `remit` เข้ากะผู้จัดการที่เปิดอยู่ / `refund`), ตรวจรับของคืน (`restock` / `destroy`), บล็อก Pharmacy Delivery 100%, และซิงค์ `delivery.updated` พร้อมลายเซ็น RSA-SHA256 เข้า ERP Inbox & Checkpoint (`document/PHASE_28_8_1_DELIVERY.md`)
  - **Phase 28.8.2: Membership / Promotions (Local Scope — PASSED):** ERP เป็น Member Master, สมัครสมาชิกจาก POS แบบเข้ารหัส Hybrid (`AES-256-GCM` + `RSA-OAEP` ใน `MemberEnvelope`) รอผู้มีสิทธิ์ตรวจรับ/จับคู่สมาชิกเดิม, เผยแพร่เฉพาะข้อมูล Mask (`name_masked`, `phone_masked`) อายุไม่เกิน 15 นาที, คำนวณส่วนลด Exclusive Fixed/Percent ก่อน VAT ด้วย `PromotionCalculator` ตัวเดียวกันทั้ง POS/ERP (ปัดเศษสตางค์ Largest Remainder), จัดสรรสิทธิ์คูปองจำกัดจำนวนล่วงหน้า (`pos_promotion_allocations` / `pos_promotion_rights`), และเชื่อมการคืนสิทธิ์คูปองเมื่อ Full Refund หรือ Cancelled/Returned Delivery แยกจากการคืนสต็อกจริง (`document/PHASE_28_8_2_MEMBERSHIP.md`)
  - **Phase 28.8.3: Browser / PWA Offline (In Progress — Foundation Stage PASSED):** พัฒนาและตรวจรับส่วนรากฐาน (`OfflineIngressService`, `OfflinePermitService`, `EncryptedQueue.ts`, `TrustedClock.ts`, Migration `2026_11_01_000001_create_offline_ingress.php`) เรียบร้อยแล้ว; งานถัดไปคือการเชื่อม PWA Shell / Service Worker, UI จัดการคิว/ปลดล็อก, Sync/ACK State Machine และ Server Financial Reconciliation (`document/PHASE_28_8_3_OFFLINE.md`)
- **Closed Phase Governance:** งานที่เป็น Security Vulnerability, Data Integrity Bug, Regression Fix หรือ Production Blocker ใน Phase ที่ปิดแล้ว (Phase 1–27) สามารถแก้ไขได้โดยต้องมี Feature Test ครอบคลุมและบันทึกเหตุผลชัดเจน ห้ามเพิ่มฟีเจอร์ใหม่ย้อนกลับเข้า Phase ที่ปิดแล้วโดยไม่มี Decision/Checklist ใหม่

---

## 2. ข้อมูลอ้างอิงการควบคุมความปลอดภัย (Active Security Guardrails Reference)

รายการควบคุมความปลอดภัยที่ต้องคงอยู่ในการพัฒนาต่อยอดทุกส่วน:

| หัวข้อความปลอดภัย | รายละเอียดการควบคุมความปลอดภัย (Security Control) |
| :--- | :--- |
| **Directory Traversal Protection** | ตรวจสอบ Canonical Path ด้วย `realpath()` และ `str_starts_with($fullPath, $basePath)` ป้องกันการเข้าถึงไฟล์นอกพื้นที่จัดเก็บไฟล์สาธารณะอย่างสมบูรณ์ |
| **Invite Token Protection** | ซ่อนและไม่ส่ง Plain Invite Token และ URL ใน Flash Session หากอยู่ในสภาพแวดล้อมที่เป็น `production` |
| **Audit Logs Redaction** | ทำงานผ่าน Central Redaction Guard ใน Eloquent `saving` event เพื่อเซนเซอร์คำสำคัญ (password, token, secret) และทำ Masking เลขประจำตัวประชาชน (`person_id`, `citizen_id`) อย่างต่อเนื่อง |
| **Upload & Attachment Security** | ตรวจสอบนามสกุลและ MIME Type ของไฟล์แนบอย่างเข้มงวด ป้องกันไฟล์ Executable/Polyglot และจัดเก็บใน storage key ฝั่ง server ใต้ `tenants/{org_id}/...` เท่านั้น |
| **Session Invalidation** | เมื่อมีการ Disable บัญชีผู้ใช้ หรือมีการเปลี่ยนแปลง Role หรือปรับปรุงระดับสิทธิ์ (Permissions) หรือพนักงานลาออก (`resigned`/`terminated`) ระบบจะทำลายเซสชันเก่าในตาราง `sessions` และล้าง `remember_token` ของผู้ใช้รายนั้นทันทีเพื่อบังคับให้ออกจากระบบ |
| **Financial Isolation & Privacy** | ข้อมูลการเงินรวมทั้งหมดบน Dashboard แสดงผลเฉพาะผู้ที่มีสิทธิ์ `executive.dashboard.view` หรือผู้ดูแลระบบ (Owner/Admin) เท่านั้น พนักงานปกติจะไม่มีการดึงหรือเห็นข้อมูลสรุปการเงิน |
| **Bank Account & Key Data Protection** | เลขบัญชีธนาคารจัดเก็บแบบเข้ารหัส (`encrypted` cast) พร้อม `account_number_hash` (SHA-256) และ Certificate ของ e-Tax เก็บเฉพาะ Vault Reference/Expiry Date เท่านั้น (ห้ามเก็บ Private Key ใน Database) |
| **Payslip & Payroll Privacy Guard** | พนักงานแต่ละคนสามารถเข้าถึงและดาวน์โหลด Payslip PDF ได้เฉพาะรายการที่เป็นของตนเองเท่านั้น (`user_id === auth()->id()`) เว้นแต่เป็นผู้ถือสิทธิ์ `payroll.view` ในองค์กรเดียวกัน และห้ามข้ามองค์กร 100% |
| **DMS Private Download & Scan Guard** | ไฟล์เอกสารส่วนกลางดาวน์โหลดผ่าน Private Download Controller ที่ตรวจสอบ Session, `org_id`, สิทธิ์การเข้าถึง, Parent Permission, ความลับ (Sensitivity 5 ระดับ), และสถานะความปลอดภัยของไฟล์ (`scan_status === 'clean'`) ทุก Request 100% |
| **Two-Factor Authentication & Secret Guard** | `two_factor_secret` และ `two_factor_recovery_codes` ถูกเข้ารหัสด้วย Eloquent `encrypted` cast, ซ่อนจาก JSON serialization (`#[Hidden]`), Recovery Codes ถูกแฮชด้วย `Hash::make` และเป็น Single-Use, Trusted Device Tokens เก็บเฉพาะ SHA-256 Hash ผูกกับ User-Agent, และการรีเซ็ต 2FA สงวนสิทธิ์เฉพาะ Owner ในองค์กรเดียวกัน |
| **Attendance Privacy & Consent Guard** | การจัดเก็บ IP และ GPS ในการลงเวลาทำงานต้องเป็นไปตามหลักความยินยอม (Opt-in Consent) ภายใต้ `hr.attendance_privacy` โดยค่าเริ่มต้นคือ `capture_ip=false`, `capture_gps=false`, และ `require_employee_consent=true` หากเปิดการบันทึก IP ระบบจะจัดเก็บเฉพาะ SHA-256 Hash และห้ามจัดเก็บ Raw IP เด็ดขาด |
| **No Cash Balance Guard** | ห้ามสร้าง UI Widget, JSON/Inertia Prop หรือ API เผยแพร่ยอด `Cash Balance` ดิบหรือยอดเงินสดรวมใน Dashboard หรือ Daily Digest Notification จนกว่าจะมี Product Decision และ Bank Reconciliation Boundary ที่ชัดเจน |
| **AI OCR & Automation Guard** | โมดูล AI / OCR สกัดข้อมูลสำหรับร่างเอกสาร (Assisted Draft) พร้อมแนบค่า Confidence และหลักฐานต้นทางเท่านั้น ห้าม Auto-post รายจ่ายหรือบันทึกบัญชี GL โดยปราศจาก Human Review |
| **Workflow SoD & Immutable Snapshot Guard** | ห้ามผู้ขออนุมัติ (Requester) อนุมัติตนเองทุกกรณี (Strict SoD) โดยระบบกรอง Requester ออกตั้งแต่ระดับ Assignee Snapshot และตรวจบล็อกซ้ำใน Action Evaluation พร้อมจับภาพ Snapshot ลำดับขั้นตอน ณ วันยื่นคำขอเพื่อป้องกันผลกระทบจากการปรับผังองค์กรย้อนหลัง |
| **Notification Outbox & Secret Protection Guard** | Credentials ของช่องทางภายนอก (`notification_channels.config` เช่น Webhook URL, Access Token, Bot Token) ต้องจัดเก็บแบบ `encrypted:array` เสมอ ห้ามรั่วไหลไปยัง Audit Logs หรือส่งไปยัง Inertia Client Props เด็ดขาด และสงวนสิทธิ์การจัดการเฉพาะผู้ถือสิทธิ์ `settings.organization.update` |
| **Portal Identity & Quarantine Guard** | External Portal แยก Identity และ Session (`portal_users`, `portal_sessions`) ออกจากระบบสตาฟฟ์ 100%, Magic Link แบบ Single-use (30 นาที) เก็บเฉพาะ SHA-256 Hash, ไฟล์ใบวางบิลจากคู่ค้าถูกกักกันด้วยสถานะ `pending_scan` ห้ามแปลงเป็น AP Invoice หรือลง GL อัตโนมัติเด็ดขาดจนกว่าจะผ่านการ Scan และ Finance Staff ตรวจสอบยืนยัน |
| **Payment Gateway & Settlement Bridge Guard** | `recipient_id`, `webhook_secret`, และ `provider_secret` ต้องเข้ารหัสแบบ `encrypted` และซ่อนใน `$hidden` เสมอ, การบันทึก Payment และ Double-Entry GL เกิดขึ้นได้เฉพาะเมื่อมี Settlement Webhook ที่ลงลายมือชื่อ HMAC-SHA256 ถูกต้องพร้อม Timestamp Window ไม่เกิน 300s, การรับ Webhook ของ Opn บันทึกเพียงสถานะ `provider_confirmed` (ห้ามลงบัญชี GL), Opn Test Mode และกรณี Timeout ก้ำกึ่ง (`creation_unknown`) ห้ามลงบัญชีเด็ดขาด, และการเชื่อมต่อ Settlement Bridge จริงถือเป็น Deferred Live Integration จนกว่าจะมีการขอเชื่อมต่อจริง |
| **Single Active Editor & Discard Guard** | ป้องกันข้อมูลสูญหาย (Data Loss) จากการปิด dialog หรือสลับ editor โดยไม่ตั้งใจ: หน้าจอ Master ที่มีหลาย modal editor (Inventory, Payroll) ต้องควบคุมผ่าน Guarded Switch Coordinator (`switchEditor` + `onRequestCloseRef`) เสมอ หาก draft dirty ต้องถามยืนยัน Discard Changes ก่อนสลับ และบล็อกการสลับขณะ processing 100% |
| **Dashboard Scoped Authorization & Metrics Guard** | การแสดงผล Dashboard และ Metrics ทุกตัวต้องผ่าน Effective Permissions (`DashboardAccess`) ห้ามใช้ Role Name ในการ bypass, แยก Period Flow ออกจาก Current Snapshot, คำนวณขอบเขตวัน/เวลาอิงตาม Timezone ขององค์กร และห้ามเผยแพร่ Unreconciled figures ในฐานะ Accounting facts |
| **Employee Master & PDPA Guard** | ข้อมูลส่วนบุคคลของพนักงาน (`citizen_id`, `tax_id`, `bank_account_number`) ต้องเข้ารหัสแบบ Eloquent `encrypted` cast และทำ Masking เมื่อส่งออก UI/Logs, เมื่อพนักงานลาออกหรือถูกเลิกจ้าง (`resigned`/`terminated`) ระบบจะตัดสิทธิ์และ Disable บัญชี User ที่ผูกไว้พร้อมทำลาย Session ทันที |
| **Shift & Attendance Reconciliation Guard** | การคำนวณเวลาทำงาน OT (1.5x, 2x, 3x) และหักมาสายต้อง Snapshot อัตราและเกณฑ์จาก `EmployeeShift` ของกะนั้นๆ เสมอ และรองรับช่วงเวลาสืบค้นกะข้ามวัน (Overnight Shift), การแก้ไขเวลา Punch ด้วยมือต้องบันทึกเหตุผลและ Audit Log เสมอ |
| **Non-User Payroll & Payslip Delivery Guard** | สลิปเงินเดือนของพนักงานที่ไม่มี User Account ในระบบ ให้จัดส่งทางอีเมลส่วนตัว (`personal_email`) ผ่าน `PayslipMail` ภายใต้การควบคุมสิทธิ์ `payroll.manage` โดยต้องไม่เปิดเผยข้อมูลเงินเดือนแก่พนักงานอื่น |
| **External Read API Guard** | `/api/v1` รองรับเฉพาะ `GET`/`HEAD` ด้วย Bearer Key `erp_<public_id>.<secret>` (เก็บเฉพาะ SHA-256 hash), ดึง `org_id` จาก key เท่านั้น, ห้ามเปิดเผยข้อมูลเงินเดือน/บัญชีธนาคาร/เลขบัตร ปชช./ยอดเงินสดรวมองค์กร และแสดง Secret เต็มเพียงครั้งเดียวตอนสร้าง |
| **Standalone POS & ERP Connector Guard** | แยกแอปและฐานข้อมูล `pos/` ออกจาก `backend/` 100%, ERP เป็นฝ่ายเชื่อมต่อไปยัง POS ฝ่ายเดียว (ไม่มี POS write เข้า ERP DB), เก็บ Private Key สำหรับเซ็น Event/Checkpoint บน POS Server เท่านั้น (ห้ามส่งลง Browser/IndexedDB), ตัดสต็อกผ่าน `InventoryMovementService` เส้นทางเดียว (`pos_sale` / `recipe_consumption`), แยก `financial_imported` จาก `inventory_pending`, และจำกัดยอดเงินสดกะ POS เฉพาะในกะ ห้ามปนกับยอดเงินสดรวมขององค์กร |

---

## 3. โครงสร้างและการเข้าถึงข้อมูล (Active Architecture & Access Scope)

- **การคำนวณราคาทุนจริงโครงการ (Dynamic Project Cost):** คำนวณแบบ Dynamic บน Memory จากยอดรวมค่าใช้จ่าย (Expenses) ที่ผูกกับโครงการที่มีสถานะเป็น `approved` หรือ `paid` เท่านั้น (ไม่รวมรายการที่ยังเป็น `draft` หรือถูก `rejected`) และห้ามมีคอลัมน์ `actual_cost` ในตาราง `projects`
- **การจำกัดสิทธิ์พนักงาน (Member Guard):** สมาชิกทั่วไปที่เป็นผู้รับผิดชอบงาน (Assignee) แต่ไม่ได้เป็นเจ้าของโครงการ จะมีสิทธิ์แก้ไขได้เฉพาะสถานะของงาน (`status`) เท่านั้น ระบบหลังบ้านจะป้องกันการแก้ไขชื่อ, รายละเอียด หรือผู้รับผิดชอบงานอย่างปลอดภัย
- **สถานะขัดข้อง (Blocked Overdue Rule):** งานที่มีสถานะเป็น `blocked` แม้เกินกำหนดเวลาส่งจะไม่ถูกนับเป็นงานล่าช้า (Overdue Task) ในสถิติระบบ
- **ความถูกต้องทางภาษีและการค้า (Tax & Commercial Integrity):** ห้ามใช้วิธี Void กับใบกำกับภาษีหรือเอกสารการค้าทางการที่ส่งมอบหรือยื่นรอบบัญชีแล้ว ให้ใช้วิธีออก Credit Note / Debit Note เพื่อปรับยอดหนี้และกระทบยอดภาษีขายตามกฎหมายสรรพากร
- **ระบบบัญชีคู่และงวดบัญชี (GL & Period Lock):** บันทึก Journal Entries อัตโนมัติจากเอกสารต้นทาง มี Idempotency Guard ป้องกันการลงซ้ำ และงวดบัญชีที่ปิด (`closed`) ห้าม Post/Void/แก้ไขย้อนหลังเด็ดขาด
- **Multi-Currency & Rate Snapshot:** เอกสารทางการค้า/จัดซื้อ/การเงิน ทำการ Snapshot `currency`, `exchange_rate` (`DECIMAL(18,6)`), และ `base_*` amounts (`DECIMAL(18,2)`) เสมอ ห้ามทำ Dynamic Join กับ `exchange_rates` ในการคำนวณย้อนหลัง
- **AP & Inventory Costing Boundary:** `Expense` (Vendor Invoice) เป็นแหล่งตั้งหนี้ AP (`2110`) เท่านั้น ส่วน `GRN` บันทึกเป็น Inventory เข้าบัญชีพักสินค้า/GRNI (`1150`) และบันทึก `base_unit_cost` / `base_total_cost` บน `StockMovement` เป็น Single Source of Truth
- **Warehouse & Storage Scope:** ทุกการเคลื่อนไหวสต็อก (Stock Movement) และเอกสารคลัง (GRN, Transfer, Stock Count, DO) ต้องตรวจสอบว่า `warehouse_bin_id` อยู่ใน `warehouse_id` และ `inventory_lot_id` อยู่ใน `product_id` ขององค์กรเดียวกันอย่างเคร่งครัด
- **Payroll & AP Boundary:** การจ่ายเงินเดือนพนักงาน (`PayrollRun`) บันทึกผ่านบัญชีค้างจ่าย `2140` (Payroll Payable) และตัดจ่ายเข้าบัญชีเงินสด/ธนาคารโดยตรง โดยไม่ผ่านตาราง `VendorPayment` (AP Subledger) และจำกัด Initial Scope เป็นสกุลเงิน THB เท่านั้น
- **DMS Central Repository & Parent-Permission Authorization:** จัดเก็บเอกสารองค์กรแบบรวมศูนย์ (`documents`, `document_versions`) เชื่อมโยงผ่าน `document_links` บังคับใช้นโยบายการเก็บรักษาเอกสารภาษี/บัญชี 5–7 ปี (Retention Window) และตรวจสอบสิทธิ์ Parent Entity Permission ทุกครั้ง
- **Two-Factor Policy & Privileged Role Enforcement:** นโยบายความปลอดภัย 2FA ควบคุมระดับองค์กรผ่าน `security.two_factor` บังคับให้บทบาทที่มีสิทธิ์เข้าถึงข้อมูลอ่อนไหว (`owner`, `admin`, `finance`) ต้อง Enroll 2FA ก่อนเข้าถึงระบบ
- **HR, Employee Master, Position Master & Attendance Engine:**
  - แบบจำลอง `Employee` แยกอิสระจาก `User` (`user_id` nullable), ตารางตำแหน่งงานแยกเป็น `positions` (`employees.position_id` +ซิงค์ `position_title` แบบ bidirectional), รองรับ Bulk Import (`EmployeeImportService`)
  - ตารางกะ (`employee_shift_schedules`), นำเข้าเวลาสแกน (`attendance_raw_logs`), คำนวณชั่วโมงทำงาน/กะข้ามวัน/OT (1.5x, 2x, 3x) และส่งต่อเข้า `PayrollService` โดยห้าม Auto-post ลง GL ก่อนอนุมัติและ Lock งวด
- **Central Dynamic Approval & Notification Outbox:** `WorkflowEngineService` บังคับใช้ Strict SoD และ Snapshot ขั้นตอนอนุมัติ; ระบบแจ้งเตือนทำงานผ่าน Transactional Outbox (`notification_outbox`) พร้อม Exponential Backoff
- **AI OCR Vision Architecture (Phase 26):**
  - ใช้ `UniversalOpenAiVisionAdapter` รองรับทั้ง Local AI (Ollama/LM Studio) และ Cloud AI; สกัด JPEG จาก Scanned PDF (`extractJpegFromPdf`), ใช้ `detail: 'high'`, บังคับ JSON output, และตรวจ Modulo 11 ของเลขผู้เสียภาษี 13 หลักและสมการ $Subtotal + VAT = Total$
- **Manufacturing & BOM Architecture (Phase 27):**
  - ตาราง `bill_of_materials`, `bom_items`, `work_orders`, `work_order_items`, `production_costs`; บริการ `ProductionCostingService` คำนวณ Multi-level explosion พร้อมป้องกัน Circular dependency; บันทึกบัญชี WIP (`1160`) และ Finished Goods (`1140`) อัตโนมัติ
- **Standalone POS & ERP Connector Architecture (Phase 28):**
  - ใช้ Shared Core + Business Modules (`Retail`, `Restaurant`, `Pharmacy`) บนฐานข้อมูล POS แยกอิสระ; ซิงค์ผ่าน Signed Events เข้า ERP Inbox
  - การตัดสต็อกรวมศูนย์ที่ `InventoryMovementService` พร้อม Pessimistic Row Lock บน Product; ร้านอาหารตัดวัตถุดิบทางตรงด้วย `recipe_consumption` (ไม่สร้าง Work Order รายจาน) และแยกอาหารเสียหลังเข้าครัวเป็น `food_waste_spoilage`; ร้านขายยาบังคับให้ผู้จัดการที่เป็นเภสัชกร (`pharmacist_qualified = true`) ตรวจรับยาคืนก่อน `restock`/`destroy` และมีระบบกักกัน Lot Recall ทั้งฝั่ง POS และ ERP

---

## 4. ข้อแนะนำการเตรียมความพร้อมขึ้น Production (Production Deployment & Server Readiness)

1. **Background Workers & Scheduler:**
   - **Laravel Scheduler (Cron):** ตั้งค่า `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1` สำหรับงานประจำวัน (`notifications:dispatch`, `notifications:daily-digest`, `invoices:mark-overdue`, `assets:depreciate`, `fx:revalue`, `documents:enforce-retention`, `cashflow:snapshot`)
   - **Queue Worker (Supervisor/Systemd):** รัน `php artisan queue:work --tries=3` สำหรับส่งอีเมล, `PayslipMail`, Webhook Outbox, และคิว `etax`
2. **PHP Extensions, Thai Fonts & Storage:**
   - เปิดใช้งาน `ext-zip` และ `ext-simplexml` สำหรับ Excel/XML import
   - ติดตั้งฟอนต์ภาษาไทย (Sarabun / THSarabunNew) สำหรับ DomPDF
   - รัน `php artisan storage:link` และตั้งค่าขนาดอัปโหลดให้เหมาะสม
3. **Environment Security & Disaster Recovery:**
   - Production `.env` ต้องตั้งค่า `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
   - รักษา `APP_KEY` ให้คงที่เสมอ (ใช้ถอดรหัส 2FA, Bank Accounts, Employee PII, AI Keys, Notification Channels, และ POS Config)
   - ตั้งค่า Automated Daily Database Backup และทดสอบ Restore Drill

---

## 5. มาตรฐานคุณภาพโค้ดและการตรวจสอบ (Directives for Development & Verification)

1. **ห้ามละเมิด Security Guardrails ในข้อ 2 และ 3 ของ `gemini.md` โดยเด็ดขาด**
2. **รักษา Code Quality ในทุกขั้นตอนการพัฒนา:**
   - ERP PHPUnit Tests: `php artisan test` (ใน `backend/`)
   - POS Isolated PHPUnit Tests: `php ../backend/vendor/phpunit/phpunit/phpunit -c phpunit.xml` (ใน `pos/`)
   - Frontend Unit Tests: `node --test tests/Frontend/*.test.mjs`
   - TypeScript & Vite Builds: `npm run tsc` และ `npm run build` (ทั้ง `backend/` และ `pos/`)
   - Static Analysis & Style: `npm run lint`, `npm run check-format`, และ `php vendor/bin/pint --test`

---

## 6. สรุปบันทึกการตรวจรับงานที่เสร็จสมบูรณ์แล้ว (Completed Phase & Module Acceptance Summary)

รายละเอียดเชิงลึกของแต่ละรอบถูกบันทึกไว้ใน `checklist.md`, `gpt.md`, `docs/` และ `document/` สรุปสถานะที่ผ่านการตรวจรับโดย Gemini แล้วดังนี้:

| โมดูล / Phase | วันที่ตรวจรับ | ผลการตรวจรับและหลักฐานเชิงประจักษ์ (Verification Snapshot) |
| :--- | :---: | :--- |
| **Phase 23.1 – 26** (Dialogs, Dashboards, HR Employee Master/Position/Bulk Import, e-Tax Gateway, Cash Flow, AI OCR, Setup Wizard) | 2026-09-21 | **COMPLETE & CLOSED (100% Green)**<br>Backend Regression 380+ tests passed, Scoped suites (`Phase233*`, `Phase24*`, `Phase25*`, `Phase26AiOcrTest`, `InstallerTest`) ผ่านครบ 100%, TypeScript/ESLint/Pint/Vite Clean |
| **Phase 27: Manufacturing & BOM** | 2026-09-25 | **COMPLETE & CLOSED (100% Green)**<br>`Phase27ManufacturingTest` 18 passed / 105 assertions, Multi-level BOM, WIP/FG GL Posting, Frontend & Pint Clean |
| **External Read-only API (Round 1 & P0–P6)** | 2026-10-19 | **COMPLETE & VERIFIED (100% Green)**<br>62 GET paths (`docs/openapi/external-read-v1.json`), Feature Tests 54 passed / 4,731 assertions, Client/Webhook verifier 13 passed, Local HTTP/Browser key lifecycle verified *(Production deployment & SLA deferred)* |
| **Phase 28: Standalone POS & ERP Connector (28.1–28.7 Local Scope)** | 2026-10-27 | **PASSED — Local Development & Architecture Acceptance**<br>- ERP Phase 28 + External Webhook: **46 passed / 422 assertions** (หลังอัปเกรดฐาน MySQL `erp` 83 migrations)<br>- POS Isolated PHPUnit: **41 passed / 242 assertions** (ฐาน POS SQLite 17 migrations)<br>- Browser Headless Fixtures (1440px / 390px): ผ่านครบทุกโมดูล (Retail, Restaurant, Pharmacy, ABB→Full Tax)<br>*(ดูรายละเอียดใน `document/PHASE_28_LOCAL_ACCEPTANCE.md` และ `document/DATABASE_UPGRADE_20261027.md`)* |
| **Phase 28.8.1: Ordering / Delivery (Local Operational Scope)** | 2026-10-30 | **PASSED — Local Operational Scope Acceptance**<br>- POS Isolated PHPUnit: **71 passed / 1,781 assertions** (`DeliveryTest` 20 cases + regression)<br>- ERP Phase 28 + Manufacturing: **62 passed / 509 assertions** (`Phase28PosImportTest` 16 cases + regression)<br>- POS TypeScript (`tsc --noEmit`), Vite Build & Pint (Phase 28.8.1 files): **Clean (0 errors)**<br>- Browser Headless (`1280x900` / `375x812`) & Signed Outbox→ERP Roundtrip (35 revisions): **Verified 100%**<br>*(ดูรายละเอียดใน `document/PHASE_28_8_1_DELIVERY.md` และ `document/acceptance/phase28_8_1/`)* |
| **Phase 28.8.2: Membership / Promotions (Local Scope)** | 2026-11-01 | **PASSED — Local Scope Acceptance**<br>- POS Isolated PHPUnit: **81 passed / 1,851 assertions** (`PromotionCalculatorTest`, `DeliveryTest`, `CheckoutTest` + regression)<br>- ERP Phase 28 + Manufacturing + Membership: **82 passed / 1,937 assertions** (`PosPromotionCalculatorTest`, `PosMemberEnvelopeTest`, `Phase28PosImportTest`)<br>- POS & ERP TypeScript (`tsc --noEmit`), Vite Builds & Pint (Phase 28.8.2 files): **Clean (0 errors)**<br>- Browser Headless (`1280x900` / `375x812` ทั้ง Retail/Restaurant/Pharmacy), Stale `409`/Replay & Signed Retail→ERP Roundtrip (10 events / 26 signatures): **Verified 100%**<br>*(ดูรายละเอียดใน `document/PHASE_28_8_2_MEMBERSHIP.md` และ `document/acceptance/phase28_8_2/`)* |
| **Phase 28.8.3: Browser / PWA Offline (Foundation Stage)** | 2026-11-01 | **PASSED — Foundation Stage Acceptance (In Progress)**<br>- POS Isolated PHPUnit: **87 passed / 1,904 assertions** (รวม `OfflineIngressTest` 6 cases / 53 assertions + full POS regression)<br>- POS TypeScript (`tsc -p pos/tsconfig.json --noEmit`) & Pint (Phase 28.8.3 foundation files): **Clean (0 errors)**<br>- Chrome IndexedDB / WebCrypto & `TrustedClock` (`storage-browser.json`): **PASSED 100%** (PBKDF2 600k + AES-256-GCM, Cross-tab CAS cap, Partial loss guard, Clock rollback freeze)<br>*(ดูรายละเอียดใน `document/PHASE_28_8_3_OFFLINE.md` และ `document/acceptance/phase28_8_3/`)* |

### รายการ External Gates ที่ยังค้างสำหรับ Production ของ Phase 28 (ไม่ใช่ Blocker ของงานพัฒนา Local):
1. **e-Tax Live Provider & Signing Gate:** รอเลือกผู้ให้บริการ e-Tax Provider จริงและผูกใบรับรองดิจิทัล (HSM/Cloud Key) สำหรับส่งข้อมูลจริงแทนสถานะ `awaiting_provider`
2. **Target Two-Server Network & Hardware Gate:** ติดตั้ง mTLS Reverse Proxy/Firewall ข้าม 2 Server จริง และทดสอบร่วมกับเครื่องพิมพ์ใบเสร็จความร้อน (Thermal Printer) / เครื่องสแกนบาร์โค้ดจริง
3. **MariaDB High-Concurrency Stress & Operational UAT Gate:** ทดสอบการแย่งโควตาสต็อกพร้อมกันหลาย Terminal บน MariaDB จริง และซ้อมแผนกู้คืนข้อมูล (Recovery Drill) ร่วมกับผู้ดูแลระบบ

---

## 7. การตรวจรับ Checklist และข้อกำหนดสถาปัตยกรรมสำหรับ Phase 28.8 (POS Extensions — 2026-10-30)

**สถานะ:** **ผ่านการตรวจรับสมบูรณ์ 100% (APPROVED — Checklist Ready for Phase 28.8.1–28.8.3 Design & Implementation)**  
**เอกสารอ้างอิง:** [checklist.md](checklist.md#L1706-L1788) (หัวข้อ `28.8 POS extensions`)

รายการใน [checklist.md](checklist.md#L1706-L1788) ได้รวมทั้ง Guardrails หลักและจุดเชื่อมระหว่างโมดูล (Cross-Module Edge Cases ทั้ง 5 ข้อ) ครบถ้วนทุกประเด็นแล้ว สรุปกรอบสถาปัตยกรรมที่ล็อกไว้สำหรับใช้พัฒนา `28.8.1` – `28.8.5` ดังนี้:

1. **Ordering / Delivery (`28.8.1`):**
   - ใช้ Account Mapping ขององค์กรแทนการ Hardcode รหัสบัญชี GL และแยกเงินสดระหว่างจัดส่ง (COD) ไม่ให้ปนกับเงินสดในลิ้นชักของกะที่ปิดแล้ว
   - แยกใบส่งของ หลักฐานรับเงิน และ ABB/FULL ออกจากกัน; หากออกเอกสารขายแล้วแต่ส่งไม่สำเร็จต้องใช้ Reversal / Return / Credit Note พร้อม Source Correlation (ไม่ตีความ Failed Delivery ว่าเกิด Cash Refund โดยอัตโนมัติ)
   - แยก `delivery_fee` / `packaging_fee` ออกจากยอดสินค้า พร้อม Snapshot กติกาส่วนลดและภาษี
   - แยกการจัดการของคืนจากงานจัดส่งตามประเภทธุรกิจ (Retail ตรวจสภาพก่อนคืน Stock, Restaurant บันทึก `waste` อ้างอิง `recipe_consumption` ต้นทุนเดิมโดยไม่ตัดซ้ำ, Pharmacy ให้ผู้จัดการที่เป็นเภสัชกรตรวจรับก่อน `restock`/`destroy` และให้เภสัชกรอนุมัติก่อนปล่อยจัดส่งยา)
2. **Membership / Promotions (`28.8.2`):**
   - ERP เป็น Master หรือรับ Draft Member จาก POS ผ่าน Signed Event เดิม (ไม่มี Direct ERP DB Write) พร้อมทำ PDPA Masking/Encryption
   - คำนวณส่วนลดโปรโมชันและสมาชิกเป็น Trade Discount ก่อน VAT ให้สอดคล้องกันทุก POS และปันส่วนยอดสุทธิจริงสำหรับ ABB/FULL/GL และ Refund
   - โปรโมชันจำกัดสิทธิ์ (Limited Redemption / คูปองใช้ครั้งเดียว) ต้องผ่าน Online Check หรือโควตาที่จัดสรรเฉพาะเครื่อง และเชื่อมการคืนสิทธิ์คูปอง (`Redemption Reversal`) เข้ากับกรณี Void/Cancelled/Failed Delivery (แยกจากการคืน Stock จริงที่ต้องผ่านการตรวจรับสภาพก่อน)
3. **Browser / PWA Offline (`28.8.3`):**
   - เก็บ `POS Instance Event/Checkpoint Private Key` ที่ POS Server เท่านั้น (ไม่ส่งลง Browser/IndexedDB) และแยก Event Signature ออกจาก e-Tax Document Signature
   - แยก `Offline Provisional Receipt` / `Offline Number Quota` ออกจาก `Offline Stock Quota` เพื่อกัน Oversell และกำหนด Hard-Blocks ขณะ Offline (ห้าม Refund/Void บิลที่ยังไม่ซิงค์, ห้ามเปลี่ยน FULL ข้ามเครื่อง, ห้ามจ่ายยาติด Recall/ต้องตรวจสถานะสด)
   - งานจัดส่ง (`Dispatch`, `COD Settlement`, `Failed Delivery Return`) ใช้ **POS Server เป็น Online Authority** สำหรับรุ่นแรก (Browser Offline เก็บได้เฉพาะคำขอ Pending)
   - เมื่อ Logout/Timeout/เปลี่ยนกะ ห้ามลบคิว `pending`/`failed`/`review` แต่ต้องแยกสิทธิ์อ่าน/ถอดรหัสตามบทบาทและสาขา พร้อมกำหนดเพดาน `max_offline_transactions` / `max_offline_amount` และตรวจผล `StorageManager.persist()` จริง
   - บันทึก `client_created_at` แยกจาก `server_received_at` (ไม่เขียนทับเมื่อ Retry) พร้อมตรวจจับ Clock Skew / Rollback โดยห้ามทิ้งรายการที่รับเงินแล้วแต่ให้ส่งเข้าคิว Review
4. **Vertical Extensions (`28.8.4` Pharmacy & `28.8.5` Restaurant) & Deferred (`28.8.6`):**
   - `28.8.4`: ระบุ Decision Supersede ของเวชระเบียนเฉพาะเมื่อพัฒนาและเปิดใช้ใน Pharmacy POS, เข้ารหัสข้อมูลสุขภาพ at rest, ไม่ Cache เวชระเบียนใน Browser อัตโนมัติ, และแยก `Drug Allergy Guard` รอกำหนดขอบเขตจากเภสัชกร
   - `28.8.5`: POS Server เป็น Authority เดียวสำหรับ Public QR Order Acceptance, Table Session และการจัดสรรโต๊ะ Walk-in / Reservation (ไม่มี Online Deposit ในรอบนี้)

**สรุปผลการตรวจรับ:** Checklist ของ **Phase 28.8** พร้อมสมบูรณ์ 100% **สามารถเริ่มงาน `28.8.1` $\rightarrow$ `28.8.2` $\rightarrow$ `28.8.3` ได้ทันที**

---

## 8. ผลการตรวจรับงาน Phase 28.8.1: Ordering / Delivery (Local Operational Scope — 2026-10-30)

**สถานะ:** **ผ่านการตรวจรับระดับ Local Operational Scope (PASSED — Ready for Phase 28.8.2 Membership / Promotions)**  
**เอกสารอ้างอิง:** [document/PHASE_28_8_1_DELIVERY.md](document/PHASE_28_8_1_DELIVERY.md), [document/acceptance/phase28_8_1/README.md](document/acceptance/phase28_8_1/README.md), [checklist.md](checklist.md#L1712-L1728)

### 8.1 สรุปการตรวจสอบสถาปัตยกรรมและ Security Guardrails (Verified Controls)
1. **POS Server Authority, Optimistic Concurrency & Idempotency:**
   - ทุกการสร้างและเปลี่ยนสถานะงานจัดส่งใน `pos/app/Services/DeliveryService.php` และ `pos/app/Services/DeliveryOperations.php` ทำงานภายใต้ Transaction + `lockForUpdate()`, บังคับตรวจ `version` (`409 Conflict` เมื่อ Stale), และตรวจ `request_key` + `request_hash` (SHA-256) ใน `pos_delivery_actions` ป้องกันการยิงซ้ำหรือใช้ Key ซ้ำแต่เปลี่ยน Payload
2. **PDPA & Privacy Isolation:**
   - ข้อมูลผู้รับ/ที่อยู่จัดส่ง (`Delivery::$casts['contact'] = 'encrypted:array'`) เข้ารหัส at rest, ซ่อนจาก JSON (`$hidden`), ไม่ส่งออกใน `pos_audit` หรือ `pos_outbox` (`delivery.updated`), และหน้าใบนำส่ง (`delivery-slip.blade.php`) ส่ง Header `Cache-Control: private, no-store` พร้อม Escape ข้อมูลทุกจุด
3. **Pharmacy Hard-Block (100%):**
   - บล็อกโมดูล `pharmacy` ทั้งใน `DeliveryService::actor()` (`403 Forbidden` ทุกบทบาท) และฝั่ง ERP `PosDeliveryImportService::import()` (`PosSignatureException` ปฏิเสธ Event หากมี `business_type = pharmacy` หลุดเข้ามา)
4. **การแยก Prepaid Cash vs COD Cash-Custody และเพดานบัญชี (`policy_pending`):**
   - **Prepaid Cash (`prepaid_cash`):** ผูกกับบิลเงินสดที่ชำระแล้ว (`pos_orders` แบบ 1:1 unique `sale_id`) โดยไม่สร้างยอดขาย/ภาษี/ตัดสต็อกซ้ำ และไม่อนุญาตให้กด `collect` ซ้ำ
   - **COD Cash (`cod_cash`):** คำนวณราคาและ VAT 7% ฝั่ง Server จาก Catalog ปัจจุบัน แยก `delivery_fee` และ `packaging_fee` ออกจากยอดสินค้า; แยกขั้นตอนรับเงินของคนส่ง (`collect` $\rightarrow$ `collected_unremitted`) ออกจากขั้นตอนนำส่งเงินเข้าลิ้นชักผู้จัดการที่เปิดกะอยู่ (`remit` ลง `pos_delivery_cash` คำนวณใน `ShiftCashService::totals()` โดยไม่แตะกะที่ปิดแล้ว) และทำเครื่องหมาย `financial_status = policy_pending` โดยไม่แอบสร้าง `pos_orders`, `pos_payments`, `invoices`, หรือ `journal_entries` ก่อนมีนโยบายบัญชี/ภาษีอนุมัติ
5. **การจองโควตาสต็อก งานครัว และการจัดการของคืนแยกตามธุรกิจ:**
   - **Retail COD:** จองโควตาใน `pos_grants` (`pos_delivery_reservations`); หากส่งไม่สำเร็จ (`fail`) จะยังไม่คืนสต็อกและไม่ตีความว่าคืนเงินอัตโนมัติ จนกว่าจะรับของกลับเข้าสาขา (`receive`) และผู้จัดการตรวจสภาพ (`inspect` เลือก `restock` ที่ตรวจ Lot Recall/วันหมดอายุ หรือ `destroy`)
   - **Restaurant COD:** สร้างใบงานครัว Takeaway (`pos_delivery_kitchen_lines`) บล็อกการคิดเงินซ้ำผ่าน `RestaurantService::bill()`, บังคับสถานะครัว `ready`/`served` ก่อนปล่อยรถ (`dispatch`), และเมื่อรับของคืนจากงานที่ส่งไม่สำเร็จจะบังคับ `destroy` (อาหารเสีย `food_waste_spoilage` อ้างอิง `recipe_consumption` เดิมโดยไม่ตัดวัตถุดิบซ้ำ)
6. **Signed Outbox Revisions & ERP Checkpoint Verification:**
   - ทุกการเปลี่ยนสถานะสร้าง `delivery.updated` พร้อมลายเซ็น RSA-SHA256; ฝั่ง ERP (`PosDeliveryImportService`) ตรวจลายเซ็น, บังคับลำดับ `version` ต่อเนื่อง (`version - 1` ต้องมีอยู่ มิฉะนั้นโยน `PosDependencyPending` เพื่อรอรอบซิงค์), และ `PosCheckpointService` ตรวจนับความครบถ้วนของ `delivery.updated` เทียบกับ `pos_import_delivery_revisions` (ปิดค่าเริ่มต้นด้วย `config('pos.local_delivery_enabled') === false` เพื่อไม่กระทบเครื่องที่ยังไม่รัน Migration)

### 8.2 หลักฐานการทดสอบเชิงประจักษ์ (Empirical Verification Snapshot)
- **POS Isolated PHPUnit (`pos/phpunit.xml`):** **PASSED — 71 tests / 1,781 assertions** (รวม `DeliveryTest` 20 cases ครบทุก Lifecycle, Concurrency, Idempotency, Prepaid/COD, Kitchen/Return Inspection, Pharmacy Block)
- **ERP Phase 28 & Manufacturing Suite:** **PASSED — 62 tests / 509 assertions** (รวม `Phase28PosImportTest` 16 cases ทดสอบการนำเข้า `delivery.updated`, Out-of-order Version Dependency, Pharmacy Block, และ Checkpoint Audit)
- **Static Analysis, Build & Code Style:**
  - `tsc -p pos/tsconfig.json --noEmit` & `vite build --config pos/vite.config.mjs`: **PASSED (0 errors)**
  - `pint --test` (เฉพาะไฟล์ในขอบเขต Phase 28.8.1 ทั้ง `pos/` และ `backend/`): **PASSED (Clean)**
- **Browser & Signed Sync Roundtrip Fixtures (`document/acceptance/phase28_8_1/`):**
  - ผ่านการทดสอบ Headless Chrome ทั้งขนาด `1280x900` และ `375x812` (`consoleErrors: []`, `pageErrors: []`, `overflow: false`) และซิงค์ `delivery.updated` ครบ 35 Revisions เข้า ERP Inbox พร้อมยืนยันลายเซ็นสำเร็จ 100%

### 8.3 รายการ Release Gates ที่ค้างไว้ตามขอบเขต (Non-Blocking for Phase 28.8.2)
1. **COD & Delivery Fee Financial/Tax Policy Gate:** รอกำหนดผังบัญชีและจังหวะการออกใบกำกับภาษี (ABB/FULL), การรับรู้รายได้ค่าขนส่ง/ค่าบรรจุภัณฑ์, และการตัดสต็อกจริง/COGS ใน ERP สำหรับรายการ `cod_cash` (ปัจจุบันแยกสถานะเป็น `policy_pending` อย่างปลอดภัย)
2. **Operational Database Migration Gate:** ไฟล์ Migration ใหม่ 3 ไฟล์ (`pos/database/migrations/2026_10_30_000001_create_delivery_workflow.php`, `2026_10_30_000002_add_delivery_operations.php`, และ `backend/database/migrations/2026_10_30_000001_create_pos_delivery_revisions.php`) ทดสอบใน Isolated SQLite เรียบร้อยแล้ว รอคำสั่งอนุมัติก่อนรันอัปเกรดลงฐานข้อมูล Operational จริงตาม Config

---

## 9. ผลการตรวจรับงาน Phase 28.8.2: Membership / Promotions (Local Scope — 2026-11-01)

**สถานะ:** **ผ่านการตรวจรับระดับ Local Scope (PASSED — Ready for Phase 28.8.3 Browser / PWA Offline)**  
**เอกสารอ้างอิง:** [document/PHASE_28_8_2_MEMBERSHIP.md](document/PHASE_28_8_2_MEMBERSHIP.md), [document/acceptance/phase28_8_2/README.md](document/acceptance/phase28_8_2/README.md), [checklist.md](checklist.md#L1729-L1749)

### 9.1 สรุปการตรวจสอบสถาปัตยกรรมและ Security Guardrails (Verified Controls)
1. **ERP Member Master, Hybrid Encrypted Enrollment & PDPA Masking:**
   - ERP เป็น Member Master (`pos_members` ใน `PosMembershipMasterService`) โดยจัดเก็บ `contact` แบบ `encrypted:array`, ซ่อนใน `$hidden`, และค้นหาหมายเลขซ้ำด้วย Keyed HMAC-SHA256 Fingerprint แยกตาม `org_id` (`phone_fingerprint`) โดยไม่รวมบัญชีอัตโนมัติ (ต้องให้ผู้ถือสิทธิ์ `pos.manage` ยืนยันจับคู่หรืออนุมัติ)
   - คำขอสมัครสมาชิกจาก POS (`MemberDraftService` $\rightarrow$ `member.requested`) เข้ารหัสข้อมูลติดต่อด้วย Hybrid Envelope (`AES-256-GCM` ผูก AAD `instance_id|branch_id|request_id` + `RSA-OAEP` 2048-bit แยกคนละคู่กุญแจกับ Event Signing Key ใน `shared/pos/MemberEnvelope.php`) ไม่ส่ง Plaintext PII ลง Outbox
   - Publication ที่ส่งให้ POS (`PosMembershipPublicationService` $\rightarrow$ `MembershipService::provision()`) ส่งเฉพาะข้อมูลที่ Mask แล้ว (`name_masked`, `phone_masked` รูปแบบ `***1234`), จำกัดขนาดไม่เกิน 2 MiB และมีอายุสูงสุด 15 นาที (ต่ออายุเมื่อเหลือ $\le 5$ นาที)
2. **Shared Deterministic Promotion Calculator & Pre-VAT Trade Discount:**
   - ใช้ `Company\Pos\PromotionCalculator` (`shared/pos/PromotionCalculator.php`) ตัวเดียวกันทั้งฝั่ง POS และ ERP คำนวณส่วนลดแบบ Exclusive (`fixed` หรือ `percent` ก่อน VAT, 1 โปรโมชันต่อบิล, ห้ามซ้อนกับ `discount_approval_id` ของผู้จัดการ)
   - แยกการตั้งค่าองค์ประกอบยอดซื้อขั้นต่ำ (`minimum_components`: `merchandise`, `delivery`, `packaging`) ออกจากฐานที่ได้รับส่วนลด (`discount_components`), ปันส่วนส่วนลดระดับสตางค์ด้วยวิธี Largest Remainder ผูกกับ Line ID คงที่ และคำนวณ VAT หลังหักส่วนลดอย่างแม่นยำทั้งใน `CheckoutService`, `PosSaleValidator`, และ `PosImportService`
3. **Pre-Allocated Limited Redemption Rights & Dependency-Safe Reversal:**
   - โปรโมชันจำกัดจำนวน (`max_uses` / `per_member_limit`) ใช้สิทธิ์ที่ ERP จัดสรรล่วงหน้าให้แต่ละ Instance (`pos_promotion_allocations` $\rightarrow$ `pos_promotion_rights`) และล็อกตัดสิทธิ์ใน Transaction เดียวกับการขาย/การจัดส่ง (`MembershipService::claim()`)
   - การคืนสิทธิ์ (`release` / `releaseRefundedSale`) เกิดขึ้นครั้งเดียวเมื่อบิลถูก Full Refund ครบทุกบรรทัด (Partial Refund ไม่คืนสิทธิ์) หรือเมื่อ COD Delivery ถูกยกเลิกก่อนรับเงิน / รับของคืนผ่านการตรวจสภาพ (`returned_inspected` / `disposed`) หรือคืนเงินครบแล้วเท่านั้น โดยแยกขาดจากการคืนสต็อกพร้อมขาย
   - หากบิลใหม่ที่ใช้ Slot เดิมซิงค์มาถึง ERP ก่อนที่ Event `sale.refunded` ของบิลเก่าจะมาถึง `PosMembershipImportService::accept()` จะโยน `PosDependencyPending('promotion_release')` เพื่อรอ Event ต้นทางโดยไม่สร้างเอกสารบัญชีซ้ำหรือปฏิเสธผิดพลาด

### 9.2 หลักฐานการทดสอบเชิงประจักษ์ (Empirical Verification Snapshot)
- **POS Isolated PHPUnit (`pos/phpunit.xml`):** **PASSED — 81 tests / 1,851 assertions** (ครอบคลุม `PromotionCalculatorTest`, `DeliveryTest`, `CheckoutTest`, `RestaurantTest`, `ConfigurationTest` ครบทุกธุรกิจ)
- **ERP Phase 28, Manufacturing & Membership Suite:** **PASSED — 82 tests / 1,937 assertions** (ครอบคลุม `PosPromotionCalculatorTest`, `PosMemberEnvelopeTest`, `Phase28PosImportTest` ทดสอบครบทั้งการคำนวณส่วนลด, Hybrid Envelope, การซิงค์ `member.requested`, Stale/Replay, Partial vs Full Refund Release, และ `promotion_release` Dependency)
- **Static Analysis, Build & Code Style:**
  - `tsc` & `vite build` ทั้งฝั่ง `pos/` และ `backend/`: **PASSED (0 errors)**
  - `pint --test` (ไฟล์ในขอบเขต Phase 28.8.2 ทั้ง `shared/`, `pos/`, และ `backend/`): **PASSED (Clean)**
- **Browser & Signed Sync Roundtrip Fixtures (`document/acceptance/phase28_8_2/`):**
  - ผ่านการทดสอบ Headless Chrome ทั้งขนาด `1280x900` และ `375x812` ครบทั้ง Retail, Restaurant, และ Pharmacy (`pageErrors: []`, `noHorizontalOverflow: true`), ทดสอบ Stale Publication `409` + Refresh/Reprice, และซิงค์ Signed Events จริง 10 รายการ (7 Sales + 3 Enrollment Requests) พร้อมตรวจลายเซ็น 26 Envelopes ผ่าน 100%

### 9.3 รายการ Release Gates ที่ค้างไว้ตามขอบเขต (Non-Blocking for Phase 28.8.3)
1. **Promotion & COD Accounting/Tax, PDPA Retention & Key Rotation Gate:** รอตรวจรับผังบัญชี/ภาษีสำหรับส่วนลดโปรโมชันและค่าจัดส่ง COD, นโยบายการเก็บรักษา/ลบข้อมูลส่วนบุคคลของสมาชิกจริง, และกลไกหมุนเวียนกุญแจ `POS_MEMBER_ENROLLMENT_PRIVATE_KEY_PATH` / `POS_MEMBER_LOOKUP_KEY`
2. **Operational Database Migration Gate:** ปัจจุบันโค้ดมี Migration รวม `ERP 87 / POS 21` (เพิ่มจากที่รันในฐานข้อมูลจริงรอบ `2026-10-27` ซึ่งอยู่ที่ `ERP 83 / POS 17` คือ Migration ของ `28.8.1` และ `28.8.2` รวมฝั่งละ 4 ไฟล์) ทดสอบใน Isolated SQLite ผ่านครบถ้วนแล้ว รอคำสั่งอนุมัติก่อนรันอัปเกรดลงฐานข้อมูล Operational จริงตาม Config

---

## 10. ผลการตรวจรับงาน Phase 28.8.3: Browser / PWA Offline — Foundation Stage (2026-11-01)

**สถานะ:** **ผ่านการตรวจรับส่วนโครงสร้างพื้นฐาน (PASSED Foundation Stage — In Progress: Ready for PWA Shell/UI, Sync State Machine & Server Reconciliation)**  
**เอกสารอ้างอิง:** [document/PHASE_28_8_3_OFFLINE.md](document/PHASE_28_8_3_OFFLINE.md), [document/acceptance/phase28_8_3/README.md](document/acceptance/phase28_8_3/README.md), [checklist.md](checklist.md#L1750-L1769)

### 10.1 สรุปการตรวจสอบสถาปัตยกรรมและ Security Guardrails (Verified Foundation Controls)
1. **Server Offline Evidence Ingress & Conflict Preservation (`OfflineIngressService` / `/offline/devices`, `/offline/inbox`):**
   - ผูก `device_id` กับ `instance_id`, `branch_id`, `terminal_id`, และ `staff_id` ภายใต้ Pessimistic Lock (`pos_offline_devices`) พร้อมคืนค่า `offline_sale_authorized: false` ชัดเจนในระยะ Foundation
   - รับหลักฐาน (`sale.draft`, `restaurant.order.draft`, `delivery.request` ขนาดไม่เกิน 64 KB) โดยบันทึก `client_created_at` แยกจาก `server_received_at` ครั้งแรกอย่างเป็น Immutable (การยิงซ้ำไม่เขียนทับ `server_received_at`) และเข้ารหัส Payload at rest ด้วย `Crypt::encryptString`
   - หากส่ง `client_transaction_id` เดิมซ้ำแต่ Payload เปลี่ยน (`payload_hash` ไม่ตรง) ระบบจะรักษา Payload ต้นฉบับไว้ใน `pos_offline_inbox` และบันทึก Payload ที่ขัดแย้งลง `pos_offline_conflicts` แบบเข้ารหัส พร้อมตั้งสถานะ `review` (`reason_code = identity_conflict`) โดยไม่ทับหลักฐานเดิมและไม่สร้างรายการขายซ้ำ
2. **Manager Policy & Pre-Allocated Stock/Slot Permits (`OfflinePermitService` / `/offline/policies`, `/offline/permits`):**
   - ค่าเริ่มต้นต่อสาขา: `permit_minutes = 15`, `max_offline_transactions = 20`, `max_offline_amount = 10000.00`, `product_ids = []`; การแก้ไขผ่าน `POST /offline/policies` บังคับใช้ `ManagerApproval::verify` (ยืนยันรหัสผ่านผู้จัดการสด) และตรวจ `expected_version` (`409 Conflict` เมื่อ Stale)
   - การออก Permit (`POST /offline/permits`) จำกัดเฉพาะธุรกิจ `retail`/`pharmacy` สกุลเงิน `THB` และเฉพาะสินค้าทั่วไปที่อยู่ใน `policy.product_ids` เท่านั้น (บล็อกสินค้าที่มี Metadata `pharmacy` เวชภัณฑ์/ยาควบคุม และ `restaurant` งานครัวด้วย `422`), บังคับให้มีเพียง 1 Permit ที่ยังไม่ Reconcile ต่อ Terminal (`active_terminal_id` unique — แม้หมดอายุก็ไม่คืนโควตาอัตโนมัติป้องกันกรณีรับเงินสดไปแล้ว), จองสต็อกเข้า `pos_grants.reserved_quantity` + `pos_offline_reservations` ผ่าน `EntitlementAllocator::take(..., offlinePermitId: $id)`, และสร้างช่องเลขเอกสาร `pos_offline_slots` ล่วงหน้า
3. **Browser Encrypted IndexedDB Queue (`pos/resources/offline/EncryptedQueue.ts`):**
   - ใช้ WebCrypto `PBKDF2-SHA256` (`600,000` iterations, salt 16 bytes, รหัสปลดล็อก 12–128 ตัวอักษร) สร้างกุญแจ `AES-256-GCM` แบบ Non-extractable ใน Memory เท่านั้น (ล็อกอัตโนมัติเมื่อ `pagehide` และไม่เก็บรหัสผ่านหรือ Plaintext Key ลง Storage)
   - ผูก AAD เข้ากับ `scopeHash|recordId` (`instanceId`, `branchId`, `staffId`, `terminalId`, `deviceId`) ป้องกันการคัดลอกข้อมูลข้ามสาขา/ผู้ใช้/อุปกรณ์
   - ตรวจ `StorageManager.persist()` และ `navigator.storage.estimate()` ก่อนเขียน, ใช้ IndexedDB Transaction (`durability: 'strict'`) พร้อม Revision CAS กันหลายแท็บเขียนชนกัน, บังคับเพดาน `maxTransactions` / `maxAmountCents`, และตรวจทานจำนวน/ผลรวมยอดเงินของ Records เทียบกับ Vault Metadata ทุกครั้ง (`entries()`) หากพบว่า Record บางส่วนหายไปจะหยุดรับรายการใหม่ทันทีโดยคงหลักฐานที่เหลือไว้
4. **Honest-Client Monotonic & Wall-Clock Guard (`pos/resources/offline/TrustedClock.ts`):**
   - บังคับเริ่มต้นจากเวลา Server สด (`rtt <= 5000ms`), ตรวจความต่างระหว่าง `Date.now()` กับ `performance.now()` (หากคลาดเคลื่อนเกิน `2000ms` จะเปลี่ยนสถานะเป็น `clock_changed`), ตรวจจับการพับหน้าจอ/หลับเครื่อง (`visibilitychange` หรือ Timer ขาดช่วงเกิน `5000ms` $\rightarrow$ `context_suspended`) และการหมดอายุ Permit (`permit_expired`), และล็อกสิทธิ์จนกว่าจะออนไลน์รับเวลาใหม่

### 10.2 หลักฐานการทดสอบเชิงประจักษ์ (Empirical Verification Snapshot)
- **POS Isolated PHPUnit (`pos/phpunit.xml`):** **PASSED — 87 tests / 1,904 assertions** (รวม `OfflineIngressTest` 6 cases / 53 assertions ทดสอบ Replay, Conflict Preservation, Permit Stock Reservation, No Auto-Reclaim on Expiry, Clinical/Kitchen Block, และ Manager Policy Re-authentication)
- **Static Analysis & Code Style:**
  - `tsc -p pos/tsconfig.json --noEmit`: **PASSED (0 errors)**
  - `pint --test` (ไฟล์ในขอบเขต Phase 28.8.3 Foundation): **PASSED (Clean)**
- **Browser IndexedDB / WebCrypto & Clock Fixture (`document/acceptance/phase28_8_3/storage-browser.json`):**
  - ผ่านการทดสอบบน Headless Chrome จริงครบทั้ง 3 หมวด (`durable encrypted write and lock`, `browser restart, incorrect passphrase, scope isolation and atomic cross-tab cap`, และ `clock rollback freezes authority; fresh online sample required after new context`)

### 10.3 งานถัดไปเพื่อให้จบ Phase 28.8.3 สมบูรณ์ (Next Implementation Steps)
1. **PWA Shell, Service Worker & Offline UI:** เชื่อมหน้าจอตั้งค่า Device, ปลดล็อกคิว, แจ้งเตือนสถานะพื้นที่จัดเก็บ (`persistent` / headroom), แสดงรายการในคิว และออกเลขใบรับเงินชั่วคราว (Provisional Receipt ที่แยกจาก ABB/FULL) พร้อมบล็อกคำสั่งที่ต้องออนไลน์
2. **Quota/Slot Binding & Sync/ACK State Machine:** ผูกการตัดโควตาสต็อก/เพดานเงินสดและ Slot เข้ากับ `EncryptedQueue` ใน Browser และทำ State Machine การซิงค์ (`pending` $\rightarrow$ `syncing` $\rightarrow$ `synced` / `failed` / `review`) ที่ทนทานต่อการปิดโปรแกรม/เน็ตหลุด/เปลี่ยนกะ
3. **Server Financial Reconciliation & Manager Conflict Review:** พัฒนาตัวตรวจรับฝั่ง POS Server เพื่อตรวจสอบราคา/วันหมดอายุ/Lot Recall/กะ/เวลา แล้วสร้างบิลขาย (`pos_orders`), เอกสารภาษี และ Signed Outbox Event เพียงครั้งเดียว พร้อมหน้าจอให้ผู้จัดการตรวจรับเงินสดเข้ากะปัจจุบันสำหรับรายการที่ติด `review` และคืนโควตาที่ไม่ได้ใช้หลัง Reconcile เสร็จสิ้น
4. **End-to-End Browser Offline Acceptance & ERP Roundtrip:** ทดสอบครบทั้ง Retail, Restaurant และ Pharmacy POS ตั้งแต่ Offline $\rightarrow$ Reconnect $\rightarrow$ Reconcile $\rightarrow$ ERP Sync Roundtrip ก่อนติ๊กปิดหัวข้อ `28.8.3` ใน `checklist.md`
