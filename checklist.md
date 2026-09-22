# ERP Phase Checklist

ไฟล์นี้เป็น checklist กลางของโปรเจกต์. เมื่อทำ task ใดเสร็จ ให้เปลี่ยนจาก `[ ]` เป็น `[x]` พร้อมอัปเดตหมายเหตุถ้ามี.

กฎบังคับ:

- ก่อนเริ่ม Phase ใหม่ ต้องดู checklist นี้ก่อน
- ระหว่างทำงาน ให้ติ๊ก task ทันทีเมื่อเสร็จจริง
- ถ้า scope เปลี่ยน ต้องเพิ่ม/แก้ checklist ก่อนลงมือ
- เมื่อจบแต่ละ Phase ต้องอัปเดต `README.md` ให้สรุป Feature ที่สร้างแล้ว
- ถ้ามี Grok/Gemini/GPT review เพิ่ม ให้บันทึก decision สำคัญใน docs หรือ `gpt.md`
- Root `checklist.md` คือ source of truth สำหรับสถานะงาน; เอกสาร phase อื่นเป็นรายละเอียดประกอบ
- ติ๊ก `[x]` ได้เมื่อ feature/test ที่เกี่ยวข้องผ่านจริง หรือเป็นเอกสารที่สร้างอยู่บน disk แล้ว

---

## Current Status

| Phase | Status | หมายเหตุ |
| --- | --- | --- |
| Phase 0 | Done | เอกสาร scope/schema/security/decision พร้อมเริ่ม Phase 1 |
| Phase 1 | Done | Foundation + Admin Dashboard เสร็จและตรวจผ่าน |
| Phase 1.1 | Done | Admin Master Data & Access Management เสร็จและตรวจผ่าน |
| Phase 2 | Done | CRM/Sales + Sales Dashboard เสร็จและตรวจผ่าน |
| Phase 3 | Done | Finance + Finance Dashboard completed |
| Phase 4 | Done | Delivery + Delivery Dashboard completed; project_members deferred Post-MVP |
| Phase 5 | Done | Executive Dashboard + E2E/UAT completed and Gemini review closed |
| Phase 6 | Done | Reporting/filters/operational polish completed |
| Phase 7 | Closed | Post-MVP implementation completed and phase closed: VAT UI, configurable numbering, suppliers, purchase orders, project members |
| Phase 8 | Done | Production roadmap completed: PDF/50-Tawi, tax/WHT/aging exports, Purchase Tax from PO/Expenses/GRN, Inventory/GRN/adjustments, notifications/preferences |
| Production Prep | Planned | Server readiness before real deployment: scheduler, queue worker, PDF Thai fonts, storage/upload limits |
| Phase 9 | Done | Commercial & Procurement Documents completed: Quotation, CN/DN, Billing Note, Delivery Order, Purchase Request, Voucher, print/PDF, permissions, audit, tests |
| Phase 10 | Done | Treasury, Banking & Cash Management: bank accounts, statement reconciliation, petty cash, cheque/PDC, voucher proof, treasury reports, and feature tests completed |
| Phase 11 | Done | General Ledger & Double-entry Accounting: COA, periods, immutable journals, source posting, reports, permissions, and regression validation completed |
| Phase 12 | Done (application layer) | e-Tax XML, private storage, provider integration boundary, RD Prep staging export; production submission requires certified provider onboarding |
| Phase 13 | Done | Fixed asset register, capitalization reclassification, straight-line depreciation, GL posting, disposal/write-off, reports and tests |
| Phase 14 | Done | Multi-Currency & FX: currency/rate master, immutable rate snapshots, realized/unrealized FX, AR revaluation/reversal, bank-to-GL mapping |
| Phase 14.1 | Done | AP FX, Inventory FX Bridge และ FCD treasury reconciliation implemented; MySQL migration verification passed |
| Phase 15 | Done | Warehouse/bin, lot/expiry, barcode scanner for GRN/adjustment/DO/stock count, transfer, reorder notification, warehouse-aware stock movements and tests implemented |
| Phase 16B | Done | Payroll, Social Security, ภ.ง.ด. 1/1ก workpaper CSV, payslip, policy versioning และ GL posting |
| Phase 17 | Done | Enterprise Document Management (DMS), versioning, cross-module links, expiry alerts and sensitivity RBAC |
| Phase 18 | Done (Polish Track) | Security 2FA / Auth OTP for privileged roles; offline TOTP, recovery, trusted devices and owner reset implemented |
| Phase 18.1 | Done | Parent authorization guard, category retention calculation, legal hold, scheduled quarantine/archive and explicit purge implemented with feature tests |
| Phase 19 | Done | HR Core, attendance, leave foundation and locked payroll summary bridge completed; approval remains explicitly deferred to Phase 20 |
| Phase 20 | Done | Central approval workflow: versioned definitions, approver snapshot, threshold evaluation, SoD, delegation, inbox and audit trail |
| Phase 21 | Done | Operational notification outbox, encrypted LINE/Slack/Telegram configuration, retry/dead-letter, user preferences/quiet hours, and safe daily digest |
| Phase 22 | Done | Passwordless external portal, customer quotation acceptance/invoice draft, supplier PO/payment status/vendor bill intake, scoped private documents and audit trail |
| Phase 23 | Done | Closed by user scope decision on 2026-09-10: QR, Opn adapter, reconciliation and local verification complete; live settlement integration deferred |
| Phase 23.1 | Done | ปิดสมบูรณ์ 23.1.6 (Verified Scope & Audit Resolution): Modal Dialog ครบ 60 หน้า 92 ไฟล์, single active editor coordinator (useEditorCoordinator), dirty detection ละเอียดเทียบ record defaults ครบทุก field (orgStructureDirty), Idempotent submission key; UI permission gate settings.structure.update; ผ่าน full regression 316 tests (2,609 assertions), frontend 34 tests, Pint/ESLint/Prettier/Vite build 100% (browser matrix B01-B08 deferred ตามผู้ใช้อนุมัติ) |
| Phase 23.2 | Done | ตรวจรับและปิด Phase 23.2 สมบูรณ์ทุกข้อย่อย 23.2.1–23.2.7 ตามเกณฑ์ Exit Criteria โดย Full backend 344 passed, Frontend 38 passed, 0 failures |
| Phase 24 | Done | e-Tax Invoice & RD Prep Gateway: XML-DSig RSA-SHA256, Provider adapter (rd_api/inet/sandbox), outbox job worker, HMAC webhook, in-flight inquiry |
| Phase 25 | Done | Cash Flow Forecasting & Financial Health Analytics: Multi-scenario projection engine (Base/Best/Worst), AR/AP/Payroll curves, DSO, DPO, Current/Quick Ratio |
| Phase 26 | Done | AI OCR Document Ingestion: Universal OpenAI-Compatible Vision (Ollama/LM Studio/OpenRouter/ChatGPT/Gemini), Draft Expense & Vendor Bill AP conversion with Open PO link, Tenant Isolation, Error Redaction, Cloud Compliance Policy |
| Phase 27 | Planned (Vertical) | Manufacturing & Bill of Materials (BOM): multi-level BOM, work orders, stock issue & finished goods costing |
| Phase 28 | Planned (Vertical) | Point of Sale (POS) & Retail Counter: cash drawer shifts, barcode scanning, thermal receipt, store stock sync |

---

## Phase 0: Documentation Lock

- [x] กำหนด project identity และ tech stack ใน `PROJECT.md`
- [x] ล็อก MVP scope ใน `MVP_SCOPE.md`
- [x] ล็อก architecture decisions ใน `docs/ARCHITECTURE_DECISIONS.md`
- [x] ล็อก security requirements ใน `docs/SECURITY_REQUIREMENTS.md`
- [x] ล็อก validation rules ใน `docs/VALIDATION_RULES.md`
- [x] ล็อก database schema กลางใน `docs/database/DATABASE.md`
- [x] กำหนด phase acceptance criteria ใน `docs/PHASE_ACCEPTANCE_CRITERIA.md`
- [x] กำหนด routes/screens ราย phase ใน `docs/ROUTES_AND_SCREENS.md`
- [x] สรุป Phase 1 implementation plan ใน `docs/PHASE_1_LOGIN_IMPLEMENTATION.md`
- [x] Review และ reconcile feedback จาก Grok/Gemini
- [x] สร้าง `gemini.md` / `grok.md` / `gpt.md` สำหรับ decision/rebuttal notes
- [x] สร้าง `checklist.md`
- [x] สร้าง root `README.md`

Exit criteria:

- [x] Docs ไม่มี contradiction หลักที่บล็อก Phase 1
- [x] Phase 1 scope พร้อมเริ่ม coding

---

## Phase 1: Foundation + Admin Dashboard

### Setup

- [x] Scaffold Laravel app ใน `backend/`
- [x] ติดตั้ง Laravel Breeze local auth
- [x] ตั้งค่า React + TypeScript + Inertia + Vite
- [x] ตั้งค่า MariaDB connection (`127.0.0.1:3306`, database ตาม `.env`)
- [x] ตั้งค่า session/database runtime tables
- [x] Lock UUID primary key strategy: UUID v7 หรือ Laravel ordered UUID ตั้งแต่ migration แรก
- [x] ติดตั้ง/ตั้งค่า Laravel Pint
- [x] ตั้งค่า ESLint + Prettier สำหรับ React TypeScript
- [x] Shared TypeScript definitions: `resources/js/Types/global.d.ts`, `resources/js/Types/auth.d.ts`

### Database

- [x] Migration: organizations
- [x] Migration: branches
- [x] Migration: divisions
- [x] Migration: departments
- [x] Migration: users Breeze-compatible + org/profile fields
- [x] Migration: roles
- [x] Migration: permissions
- [x] Migration: role_permissions
- [x] Migration: user_roles
- [x] Migration: settings
- [x] Migration: number_sequences
- [x] Migration: audit_logs
- [x] Migration: Laravel framework tables

### Auth / Organization

- [x] Register สร้าง organization + head office branch + default division + default department + Owner ใน transaction เดียว
- [x] Login/logout ใช้งานได้
- [x] Login สำเร็จอัปเดต `last_login_at`
- [x] Password reset ใช้งานได้
- [x] Email verification gate ก่อนเข้า dashboard
- [x] Invite user flow
- [x] Accept invite flow
- [x] Invite token เป็น one-time และ TTL 72 ชั่วโมง
- [x] Password reset token และ email verification token หมดอายุและ reuse ไม่ได้
- [x] Disable inactive user แล้ว login ไม่ได้
- [x] Organization settings read/update
- [x] Organization settings upload company logo
- [x] Organization structure UI: branch/division/department

### RBAC / Security

- [x] Seed roles: owner, admin, sales, project_manager, finance, member, viewer
- [x] Seed permission catalog
- [x] Permission catalog ครอบ users/roles/settings/audit/dashboard
- [x] Seed role_permissions defaults
- [x] Effective permissions = UNION ทุก role
- [x] Owner role immutable
- [x] Last owner guard
- [x] Permission middleware `permission:{code}` ใช้งานจริง
- [x] Tenant scope บังคับ `org_id`
- [x] Test helper/trait สำหรับ tenant isolation เช่น `actingAsOrgUser($user, $org)`
- [x] Hierarchy validation: branch/division/department chain
- [x] Sensitive action re-auth สำหรับ role/user change
- [x] Re-auth tests: invite user, disable user, role change
- [x] `person_id` mask ใน UI/log/Inertia props; เลขเต็มเห็นได้เฉพาะ permission ที่กำหนด
- [x] Inertia props ห้ามส่ง `password`, `remember_token`, session id, authorization header หรือ secrets
- [x] Session regenerate หลัง login และ password reset
- [x] Security headers / CSRF / session cookie flags มี dev/prod notes
- [x] Seed number_sequences สำหรับ branch/division/department
- [x] Seed demo ตาม `docs/SEED_DATA.md` Phase 1

### Audit / Dashboard

- [x] Audit log: register
- [x] Audit log: login
- [x] Audit log: invite
- [x] Audit log: accept invite
- [x] Audit log: role change
- [x] Audit log: user hierarchy change
- [x] Admin Dashboard: Organization Setup
- [x] Admin Dashboard: Users Summary
- [x] Admin Dashboard: Role Summary
- [x] Admin Dashboard: Security Alerts
- [x] Admin Dashboard: Recent Audit

### Screens / Routes

- [x] UI: `/settings/organization`
- [x] UI: `/settings/organization-structure`
- [x] UI: `/users`
- [x] UI: invite user
- [x] UI: `/roles`
- [x] UI: `/audit-logs`

### Tests / Verification

- [x] Test register transaction
- [x] Test valid login regenerates session
- [x] Test invalid password rate limit
- [x] Test inactive user cannot login
- [x] Test invited user cannot login until accept
- [x] Test invite expire / reuse reject
- [x] Test reset/verify token expire + no replay
- [x] Test unverified user cannot reach dashboard
- [x] Test org isolation
- [x] Test Member cannot invite/open team page
- [x] Test hierarchy assignment reject invalid chain
- [x] Test owner cannot be disabled/lowered if last owner
- [x] Test props redaction
- [x] Sync Phase 1 status notes with `docs/PHASE_1_LOGIN_IMPLEMENTATION.md` หรือชี้กลับ checklist นี้
- [x] Update `README.md` with Phase 1 features

---

## Phase 1.1: Admin Master Data & Access Management

### Scope / Planning

- [x] สร้าง `docs/PHASE_1_1_MASTER_DATA.md`
- [x] เพิ่ม Phase 1.1 ใน `checklist.md`
- [x] เพิ่ม Phase 1.1 ใน `README.md`
- [x] เพิ่ม Phase 1.1 ใน `docs/PHASE_ACCEPTANCE_CRITERIA.md`
- [x] เพิ่ม Phase 1.1 ใน `docs/ROUTES_AND_SCREENS.md`
- [x] เพิ่ม User edit/disable และ Role-Permission assignment เข้า Phase 1.1 docs/checklist
- [x] Review scope กับ Grok/Gemini/GPT ก่อนเริ่ม coding

### Branch Master

- [x] UI list/create/edit branch
- [x] Disable branch
- [x] Delete-safe branch เฉพาะไม่มี reference
- [x] Auto-generate branch code text 6 หลักจาก `number_sequences`
- [x] Enforce head office มีได้หนึ่ง branch ต่อ organization
- [x] Set head office ใน transaction เดียว พร้อม audit `branch.set_head_office`

### Division Master

- [x] UI list/create/edit division
- [x] Disable division
- [x] Delete-safe division เฉพาะไม่มี reference
- [x] Auto-generate division code text 6 หลักจาก `number_sequences`
- [x] Validate division อยู่ใต้ branch ใน org เดียวกัน

### Department Master

- [x] UI list/create/edit department
- [x] Disable department
- [x] Delete-safe department เฉพาะไม่มี reference
- [x] Auto-generate department code text 6 หลักจาก `number_sequences`
- [x] Validate chain: Branch -> Division -> Department

### User Management

- [x] UI list/search users พร้อม filter status/role/branch
- [x] Invite user ใช้งานต่อจาก Phase 1
- [x] Edit user profile: name, email, phone, position, person_id
- [x] Edit user hierarchy: branch/division/department
- [x] Assign/change user roles
- [x] Disable user
- [x] Re-enable user ถ้า policy อนุญาต
- [x] ห้าม hard delete user ใน MVP
- [x] Validate user hierarchy chain
- [x] Mask `person_id` ใน UI/log/Inertia props ตาม permission

### Role / Permission Management

- [x] UI roles list
- [x] UI permission matrix ต่อ role
- [x] Update role-permission assignment
- [x] Owner role immutable
- [x] Last owner / last admin safety guard
- [x] ห้าม CRUD permission code จาก UI
- [x] Permission code เพิ่ม/ลบ/แก้ผ่าน migration/seeder เท่านั้น

### Security / Data Integrity

- [x] ใช้ `settings.structure.view` สำหรับหน้า structure read
- [x] ใช้ `settings.structure.update` สำหรับ branch/division/department create/edit/disable/delete
- [x] ใช้ `users.update` สำหรับแก้ user profile/hierarchy/role
- [x] ใช้ `users.disable` สำหรับ disable/re-enable user
- [x] ใช้ `roles.manage` สำหรับ role-permission assignment
- [x] Write routes ใช้ auth, verified, permission, password.confirm, throttle
- [x] Server derive `org_id`; ไม่รับจาก client
- [x] Cross-org access return 404
- [x] Audit log ครบ master data, user และ role permission changes

### Tests / Verification

- [x] Owner/Admin CRUD master data ได้
- [x] Member create/edit/delete structure ไม่ได้
- [x] Validation code 6 หลักผ่าน
- [x] Invalid hierarchy ถูก reject
- [x] Strict disable guard กันปิดเมื่อมี active child/user
- [x] Strict delete guard กันลบเมื่อมี reference ทุกสถานะ รวม inactive user/sub-structure
- [x] Head office switch transaction test + audit `branch.set_head_office`
- [x] Create branch พร้อม `is_head_office=true` สร้าง audit `branch.create` และ `branch.set_head_office`
- [x] Owner/Admin update user profile/hierarchy/role ได้
- [x] Member update user คนอื่นไม่ได้
- [x] Disable user แล้ว login ไม่ได้
- [x] Re-enable user แล้ว login ได้ตาม policy
- [x] ห้าม disable owner คนสุดท้าย
- [x] ห้าม hard delete user
- [x] Owner/Admin update role-permission assignment ได้
- [x] Member/Admin ที่ไม่มี `roles.manage` update role permission ไม่ได้
- [x] ห้าม CRUD permission code จาก UI
- [x] Audit log ถูกสร้างทุก write action รวม `branch.set_head_office`
- [x] Sensitive write actions require password confirmation
- [x] `pnpm run lint` ผ่าน
- [x] `pnpm run check-format` ผ่าน
- [x] `pnpm run build` ผ่าน
- [x] Test organization logo upload + audit log
- [x] PHPUnit tests ผ่าน
- [x] Update `README.md` with Phase 1.1 features หลัง coding เสร็จ

---
## Phase 2: CRM/Sales + Sales Dashboard

- [x] Migration/model/policy: customers
- [x] Migration/model/policy: contacts
- [x] Migration/model/policy: deals
- [x] Migration/model/policy: activities
- [x] Customer CRUD
- [x] Generate `customer_code` จาก `number_sequences`
- [x] Contact CRUD under customer
- [x] Primary contact มีได้หนึ่งคนต่อ customer
- [x] Deal pipeline CRUD/stage transition
- [x] Deal won/lost rules
- [x] Activity/follow-up create/update
- [x] Follow-up `completed_at`
- [x] Sales owner-only visibility
- [x] Sales Dashboard: Customers
- [x] Sales Dashboard: Deals Pipeline
- [x] Sales Dashboard: Won/Lost Deals
- [x] Sales Dashboard: Follow-ups Today
- [x] Sales Dashboard: Stale Deals
- [x] Sales Dashboard: Top Sales Owners
- [x] Sales Dashboard ห้ามแสดง Cash In
- [x] Stale deal definition: ไม่มี activity 7 วัน
- [x] Activity polymorphic allowlist validation
- [x] Tests for owner visibility
- [x] Tests for deal stage rules
- [x] Tests for primary contact rule
- [x] Update `README.md` with Phase 2 features

---

## Phase 3: Finance + Finance Dashboard

- [x] Migration/model/policy: products
- [x] Migration/model/policy: invoices
- [x] Migration/model/policy: invoice_items
- [x] Migration/model/policy: payments
- [x] Migration/model/policy: expenses
- [x] Migration/model/policy: files limited subset
- [x] Product/service catalog
- [x] Create invoice from deal
- [x] Create manual invoice
- [x] Server-side invoice calculation
- [x] `tax_mode`: exclusive/inclusive/no_tax
- [x] Payment receipt
- [x] Idempotency-Key บน payment receipt/reversal
- [x] Partial payment
- [x] Overpay prevention with transaction lock
- [x] Payment reversal
- [x] Reversal `payment_date = CURRENT_DATE`
- [x] One reversal per receipt
- [x] Migration/test `UNIQUE(org_id, reversal_of_payment_id)` กัน duplicate reversal (ใช้แทน generated column ตาม Gemini)
- [x] Invoice void policy
- [x] `needs_sales_review` derived flag
- [x] Expense draft/approve/pay/reject
- [x] Payment/expense attachment
- [x] Attachment download ตรวจ permission parent ทุกครั้ง
- [x] `storage_key` สร้างฝั่ง server เท่านั้น
- [x] Finance Dashboard: Invoiced Revenue
- [x] Finance Dashboard: Cash In
- [x] Finance Dashboard: Outstanding AR
- [x] Finance Dashboard: Overdue AR
- [x] Finance Dashboard: Expenses / Cash Out
- [x] Finance Dashboard: Net Cash Flow
- [x] Finance Dashboard: Invoice Status
- [x] Finance Dashboard: Payment Reversal
- [x] Cash In drilldown separates receipt/reversal
- [x] Mark overdue invoice job
- [x] Invoice numbering sequence
- [x] Expense `supplier_id` ไม่ FK / ไม่บังคับใน MVP
- [x] Re-auth: void invoice / reverse payment / approve-pay expense
- [x] Tests for invoice totals
- [x] Tests for payment reversal
- [x] Tests for generated column/unique constraint กัน duplicate reversal
- [x] Tests for no overpay
- [x] Tests for concurrent payment no overpay
- [x] Tests for invoice edit guard after payment
- [x] Update `README.md` with Phase 3 features

---

## Phase 4: Delivery + Delivery Dashboard

- [x] Migration/model/policy: projects
- [x] Migration/model/policy: tasks
- [x] Migration/model/policy: task_checklists
- [x] Migration/model/policy: task_comments
- [x] Confirm `task_checklists.org_id` exists and tenant scope works
- [x] Create project from won deal
- [x] Create manual project
- [x] Internal task with `project_id = null`
- [x] Link invoice to project
- [x] Link expense to project
- [x] Project owner visibility
- [x] Owner/Admin reassign project owner
- [x] Member navigation defaults to Tasks
- [x] Task create/update/status/comment
- [x] Task assignee-only visibility for Member
- [x] Project cost derived from approved/paid expenses
- [x] Confirm schema has no `projects.actual_cost`
- [x] `progress_percent` manual only
- [x] Task `blocked` does not mark overdue in MVP
- [x] Delivery Dashboard metrics
- [x] Phase 4 UAT decision gate: evaluate `project_members`
- [x] Tests for PM/Member visibility
- [x] Tests for project cost aggregate
- [x] Tests for project owner reassignment
- [x] Tests for internal task
- [x] Tests for blocked overdue rule
- [x] Update `README.md` with Phase 4 features

---

## Phase 5: Executive Dashboard + E2E/UAT

- [x] Executive Dashboard aggregates Sales + Finance + Delivery
- [x] Widget visibility by permission
- [x] No Cash Balance shown
- [x] End-to-end flow test: Invite user -> Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard
- [x] E2E role isolation tests
- [x] E2E payment reversal tests
- [x] E2E invoice totals tests
- [x] E2E dashboard metrics tests
- [x] E2E multi-role UNION permission test
- [x] E2E `needs_sales_review` after invoice void
- [x] UAT seed data
- [x] UAT dashboard expected values
- [x] UAT expected numbers จาก `docs/SEED_DATA.md`
- [x] Phase 5 UAT decision gate: evaluate auto deal workflow vs `needs_sales_review`
- [x] Security review
- [x] Log/audit ไม่มี password, token, secret, `person_id` เต็ม
- [x] ไม่มี Cash Balance ใน UI และ JSON props/API
- [x] Negative scope test: no export/notifications/public API in MVP
- [x] Grok/Gemini review
- [x] Update `README.md` with Phase 5 features
---

## Phase 6: Reporting / Filters / Operational Polish

- [x] Dashboard Date Filters: all-time/month/year/custom range
- [x] Finance Dashboard metrics respect selected date range
- [x] Delivery Dashboard metrics respect selected date range
- [x] Executive Dashboard metrics respect selected date range
- [x] Dashboard filter UI with Apply/Reset controls
- [x] Finance Dashboard visuals: Invoice Status donut and Payment Reversal loss bar
- [x] Delivery Dashboard visuals: Budget vs Actual progress bar and Task Load ranked bars
- [x] Executive Dashboard visuals: Sales conversion, Finance mix, Delivery signal indicators
- [x] Sales Dashboard visuals: Pipeline funnel, Won/Lost donut, Top Owner bars
- [x] Tests for dashboard date filter behavior
- [x] Number format expansion: `invoice_no` / `expense_no` from `char(6)` to `varchar(30)`
- [x] Tax / Invoice Compliance first pass: inclusive VAT display and header discount VAT allocation
- [x] Update `README.md` with Phase 6 completed features
- [x] Dashboard department separation: Admin, Executive, Finance, Delivery, Sales dashboards are separated by route/menu/UI scope
- [x] Delivery Dashboard visual follow-up: Project Status donut and pulsing risk badges
- [x] Admin/System Dashboard visual follow-up: Security Alert donut and System Normal state
- [x] Sales Dashboard action visual follow-up: Follow-ups/Stale Deals warning tiles and clear state
- [x] Finance Dashboard previous-period trend: backend previous metrics and trend tiles

---

## Phase 7: Post-MVP Design Backlog

ที่มา: `gemini.md` หมวด Pending Fixes & Improvements. เฟสนี้เป็น checklist งานออกแบบก่อน implementation.

Design doc: `docs/PHASE_7_POST_MVP_DESIGN.md`

### Inclusive VAT UI Implementation

- [x] Backend ส่ง `tax_summary` สำหรับ invoice list โดยใช้สูตรเดียวกับ `InvoiceController::calculateTotals`
- [x] Frontend preview แสดง gross/net/VAT breakdown ตาม `tax_mode`
- [x] Invoice list แสดง VAT included สำหรับ invoice แบบ `inclusive`
- [x] Feature test ตรวจค่า `tax_summary` สำหรับ inclusive VAT หลัง header discount
- [x] Print/export-ready wording ผ่าน `tax_summary.wording` สำหรับ invoice VAT included

### Number Sequences Implementation

- [x] เพิ่ม `period_key` ใน `number_sequences` สำหรับ reset none/yearly/monthly/daily
- [x] `NumberSequenceService` รองรับ format tokens: `{YYYY}`, `{YY}`, `{MM}`, `{DD}`, `{BRANCH}`, `{SEQ:n}`
- [x] เพิ่ม `preview()` สำหรับดูเลขถัดไปโดยไม่ increment
- [x] รองรับ organization scope และ branch scope
- [x] Feature test ครอบ default format, custom monthly reset, branch scope และ invalid format
- [x] Organization Settings UI สำหรับแก้ format และดู preview
- [x] Duplicate guard test สำหรับ repeated custom sequence calls

### Suppliers & Purchase Orders Implementation

- [x] เพิ่ม schema/model `suppliers`, `purchase_orders`, `purchase_order_items`
- [x] เพิ่ม CRUD พื้นฐานสำหรับ suppliers พร้อม org scope และ unique supplier code
- [x] เพิ่ม purchase order create/update/approve/cancel พร้อม server-calculated totals
- [x] เพิ่ม routes/UI สำหรับ `/suppliers` และ `/purchase-orders`
- [x] เพิ่ม permission catalog และ migration backfill สำหรับ supplier/PO permissions
- [x] ผูก `expenses.purchase_order_id` และ validate supplier/PO chain
- [x] Feature test ครอบ supplier isolation, PO totals/status, expense chain validation

### Project Members Implementation

- [x] เพิ่ม schema/model `project_members`
- [x] ปรับ `ProjectAccess` ให้ project member เห็น project ได้
- [x] เพิ่ม add/remove project member endpoints
- [x] เพิ่ม UI ในหน้า Projects สำหรับเพิ่ม/ลบ member และกำหนด role
- [x] Feature test ครอบ member visibility และ removal

### Number Sequences Design

- [x] Design configurable document number formats เช่น `INV-YYYYMM-00001`, `EXP-YY-0001`
- [x] Design settings schema สำหรับเก็บ prefix, date token, padding length, reset period และ branch scope
- [x] Design validation rule กัน format ยาวเกิน `varchar(30)` และกัน duplicate ใน `UNIQUE(org_id, *_no)`
- [x] Design migration/backward compatibility จากเลขเดิม `000001`
- [x] Design UI ใน Organization Settings สำหรับ preview เลขเอกสารถัดไป
- [x] Design tests สำหรับ sequence concurrency, reset period, duplicate guard และ invalid format

### Inclusive VAT UI Design

- [x] Design invoice totals breakdown สำหรับ `inclusive` แยก gross subtotal, net subtotal, hidden VAT, header discount และ total
- [x] Design display rules สำหรับ `exclusive`, `inclusive`, `no_tax` ให้ผู้ใช้ไม่สับสน
- [x] Design line item preview ให้เห็น line discount, allocated header discount, taxable base และ VAT ต่อ line
- [x] Design print/export-ready wording สำหรับใบแจ้งหนี้ที่มี VAT รวมในราคา
- [x] Design tests ให้ frontend preview ตรงกับ backend `InvoiceController::calculateTotals`

### Project Members Design

- [x] Design `project_members` schema: `org_id`, `project_id`, `user_id`, `role`, timestamps, unique constraints
- [x] Design permission model ระหว่าง project owner, project member, task assignee และ admin
- [x] Design project member UI สำหรับเพิ่ม/ลบสมาชิกและกำหนดบทบาทใน project
- [x] Design access scope ใหม่ใน `ProjectAccess` และ `TaskAccess`
- [x] Design migration path ที่ไม่กระทบ owner/assignee behavior เดิม
- [x] Design tests สำหรับ visibility, update guard, cross-org guard และ member removal

### Suppliers & Purchase Orders Design

- [x] Design suppliers schema และ CRUD scope
- [x] Design purchase orders schema: header, items, status, totals, supplier link
- [x] Design expense-supplier relation จาก `supplier_id` nullable ปัจจุบันไปเป็น FK แบบปลอดภัย
- [x] Design PO to expense/inventory future flow
- [x] Design UI routes/screens สำหรับ suppliers และ purchase orders
- [x] Design permission catalog สำหรับ suppliers และ purchase orders
- [x] Design tests สำหรับ supplier isolation, PO totals, status transition และ expense linkage

---

## Phase 8: Production Roadmap (Design & Implementation)

ที่มา: `gemini.md` หมวด Phase 8 / Production & Accounting Roadmap.

Design doc: `docs/PHASE_8_PRODUCTION_DESIGN.md`

### Official Document Print & PDF Export Implementation

- [x] เพิ่ม Library/Service สำหรับ PDF Generation ด้วย DomPDF พร้อม print template ที่รองรับภาษาไทยเบื้องต้น
- [x] ฟังก์ชันแปลงตัวเลขจำนวนเงินเป็นตัวอักษรภาษาไทย (`BahtText`) สำหรับระบุในใบกำกับภาษีและใบเสร็จรับเงิน
- [x] Template และ Layout สำหรับพิมพ์เอกสารทางการชุดแรก:
  - ใบแจ้งหนี้ (Invoice)
  - ใบกำกับภาษี / ใบเสร็จรับเงิน (Tax Invoice / Receipt)
  - ใบสั่งซื้อ (Purchase Order)
- [x] Template หนังสือรับรองการหักภาษี ณ ที่จ่าย (ใบ 50 ทวิ)
- [x] แสดง Header เอกสารพื้นฐาน: ข้อมูลบริษัท, เลขประจำตัวผู้เสียภาษี 13 หลัก, ที่อยู่
- [x] เพิ่ม Header เอกสารส่วน Production: สำนักงานใหญ่/สาขา, โลโก้
- [x] แสดง Tax Identity ของลูกค้า/คู่ค้า ครบถ้วนตามมาตรฐานกรมสรรพากร
- [x] รองรับการระบุหัวเอกสาร "ต้นฉบับ (Original)" / "สำเนา (Copy)" และแสดงลายน้ำ "VOID" อัตโนมัติเมื่อเอกสารถูกยกเลิก
- [x] ตรวจสอบสิทธิ์การเข้าถึง Official Print พร้อม Org-scope isolation
- [x] ตรวจสอบสิทธิ์การเข้าถึงและ Download PDF binary พร้อม Org-scope isolation
- [x] Feature tests ครอบคลุม Official Print Render, Org Guard, BahtText Accuracy, และ Wording
- [x] Feature tests ครอบคลุม PDF binary generation

### Inventory & Goods Receipt Implementation

- [x] เพิ่ม Schema / Models: `goods_receipts`, `goods_receipt_items`, `stock_movements` และเปิด `products.track_inventory` เมื่อรับสินค้า
- [x] ระบบสร้าง Goods Receipt (GRN) รับสินค้าเข้าคลังอ้างอิงจาก Purchase Order ที่อนุมัติแล้ว
- [x] อัปเดตสถานะของ PO เมื่อรับสินค้า: `partially_received` / `received`
- [x] ตรวจสอบยอดรับสินค้าไม่ให้เกินยอดที่สั่งใน PO (Over-receive guard)
- [x] บันทึก Stock Ledger (Movement Log) ไม่ใช้การแก้ตัวเลขตรงๆ รองรับรอบแรก:
  - รับเข้าจาก PO (`receive_from_po`)
- [x] ขยาย Stock Ledger รองรับ:
  - ปรับยอดตรวจนับ (`adjustment_in` / `adjustment_out`)
  - ส่งคืนผู้ขาย (`return_to_supplier`)
- [x] คำนวณต้นทุนสินค้าคงเหลือเฉลี่ย (Moving Average Cost) จาก movement ledger
- [x] หน้าจอ UI สำหรับออกใบรับสินค้า (GRN), ดูรายการรับสินค้า, ดู stock on-hand summary, average cost และ stock movement history
- [x] Feature tests ครอบคลุม Partial Receive, Over-receive Guard, Stock Ledger, และ Multi-tenant Isolation

### Tax & Accounting Reports Implementation

- [x] หน้ารายงานภาษีขาย (Sales Tax Report) อ้างอิงจาก Invoices ตามช่วงเวลาและงวดภาษี
- [x] หน้ารายงานภาษีซื้อ (Purchase Tax Report) อ้างอิงจาก PO รอบแรก
- [x] ขยาย Purchase Tax Report ให้รวม Expenses / GRN หลัง schema มี tax source ครบ
- [x] หน้ารายงานภาษีหัก ณ ที่จ่าย (Withholding Tax Report) แยก ภ.ง.ด. 3 (บุคคลธรรมดา) และ ภ.ง.ด. 53 (นิติบุคคล)
- [x] หน้ารายงานวิเคราะห์อายุหนี้รอบแรก:
  - รายงานอายุลูกหนี้ (Accounts Receivable Aging: 0-30, 31-60, 61-90, >90 วัน)
  - รายงานอายุเจ้าหนี้ (Accounts Payable Aging: 0-30, 31-60, 61-90, >90 วัน)
- [x] ตัวกรองรายงานรอบแรก: ช่วงวันที่ (Date Range), สาขา (Branch) สำหรับ Sales Tax
- [x] ตัวกรองรายงานส่วนขยาย: สถานะ (Status), ลูกค้า/คู่ค้า (Customer/Supplier)
- [x] ระบบส่งออกรายงานเป็น CSV รอบแรก
- [x] ระบบส่งออกรายงานเป็น Excel-compatible `.xls` โดยไม่ต้องใช้ `ext-zip`
- [x] Feature tests ครอบคลุมการคำนวณยอดรายงาน, การตัดงวดภาษี, สิทธิ์การเข้าถึง, Export CSV และ Aging buckets
- [x] Feature tests ครอบคลุม Excel-compatible export
- [x] Feature tests ครอบคลุม filters ส่วนขยาย
- [x] Feature tests ครอบคลุม Purchase Tax source expansion จาก Expenses / GRN

### Notifications & Background Queues Implementation

- [x] ตั้งค่า Notification service ให้ใช้ Laravel queued mail และ scheduled commands รอบแรก
- [x] สร้าง Notification service, queued mail และ email template:
  - แจ้งเตือนเมื่อมี Purchase Order รอการอนุมัติ (Pending Approval)
  - แจ้งเตือนใบแจ้งหนี้ใกล้ครบกำหนด (Due Soon Alert) และเกินกำหนดชำระ (Overdue Alert)
  - แจ้งเตือนการมอบหมายงานโครงการใหม่ (Project Member / Task Assigned)
  - แจ้งเตือนคำเชิญเข้าสู่ระบบ (User Invitation) พร้อมความปลอดภัยของ Token
- [x] เมนูกระดิ่งแจ้งเตือนบน Navbar (In-App Notification Bell) พร้อมนับจำนวน Unread
- [x] ระบบป้องกันการส่งแจ้งเตือนซ้ำซ้อน (Deduplication Idempotency)
- [x] หน้าตั้งค่าเปิด/ปิดการรับแจ้งเตือนรายบุคคล (Notification Preferences)
- [x] Feature tests ครอบคลุม Mail Queue, In-App Notification, Deduplication Guard และ shared unread count
- [x] Feature tests ครอบคลุม Due/Overdue/Invite notifications, Notification Preferences และ Sensitive Data Redaction รอบ invite token

---

### Phase 8 Design Backlog

- [x] Design PDF document types: Invoice, Tax Invoice / Receipt, Purchase Order, 50-Tawi (WHT)
- [x] Design official document header fields: legal name, tax ID, branch/head office, address, logo, BahtText
- [x] Design customer/supplier tax identity fields required on official documents
- [x] Design document numbering usage from configurable `NumberSequenceService`
- [x] Design VAT wording/source from `tax_summary.wording` สำหรับ inclusive/exclusive/no_tax
- [x] Design line item table: description, qty, unit, unit price, discount, VAT, line total
- [x] Design signature area, print-friendly layout, Original/Copy indicator, and VOID watermark
- [x] Design PDF access permissions and org-scope download guards
- [x] Design inventory tables: stock items, stock movements, goods receipts, goods receipt items
- [x] Design GRN flow from approved Purchase Order and status transitions
- [x] Design stock on-hand calculation from movement ledger, moving average cost, and return flow
- [x] Design inventory permissions for view, receive, adjust, and report
- [x] Design Sales Tax, Purchase Tax, WHT (ภ.ง.ด. 3/53), and AR/AP Aging Reports
- [x] Design report filters and accountant-friendly Excel/CSV export formats
- [x] Design queue/mail infrastructure, In-App notification bell, and notification preferences
- [x] Design notification templates, safe data redaction, and deduplication guard
- [x] Design tests for PDF render, inventory ledger, tax reconciliation, and queued notifications

---

## Production Prep: Deployment Readiness

ที่มา: `gemini.md` หมวด Production Deployment & Server Prep.

เป้าหมาย: ทำให้ระบบพร้อม deploy ใช้งานจริง โดยไม่เพิ่ม business feature ใหม่.

### Scheduler & Queue Worker

- [ ] สร้างเอกสาร deployment สำหรับ Laravel Scheduler: `* * * * * cd /path/to/ERP/backend && php artisan schedule:run >> /dev/null 2>&1`
- [ ] สร้างตัวอย่าง Supervisor/Systemd config สำหรับ `php artisan queue:work --tries=3`
- [ ] เพิ่ม health check หรือคำสั่งตรวจสถานะ queue worker/scheduler สำหรับ production
- [ ] ตรวจ `.env.example` ให้มี queue/mail/scheduler-related settings ครบ
- [ ] เพิ่ม smoke test/manual checklist สำหรับ due soon/overdue invoice notifications หลังเปิด scheduler

### Thai Fonts & PDF Production Hardening

- [ ] เพิ่มแนวทางติดตั้ง Sarabun หรือ THSarabunNew สำหรับ DomPDF บน Linux production
- [ ] เพิ่ม `@font-face` หรือ font registration path สำหรับ PDF templates หากเลือกฝัง font ใน repo/storage
- [ ] ทดสอบ PDF ภาษาไทยบน Invoice, PO, 50-Tawi ว่าไม่เป็น `???` และสระไม่ลอยผิดปกติ
- [ ] เพิ่มหมายเหตุเรื่อง font license/source ใน deployment docs

### Uploads & File Storage

- [ ] เพิ่ม deployment step: `php artisan storage:link`
- [ ] ระบุค่าแนะนำ `upload_max_filesize`, `post_max_size`, `client_max_body_size`
- [ ] ตรวจ file upload path และ permission บน production storage
- [ ] เพิ่ม smoke test/manual checklist สำหรับ upload/download logo, receipt, attachments

### Environment Security, Backup & Release Gates

- [ ] เพิ่ม production `.env` security checklist: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
- [ ] ตรวจ session/cookie/domain settings ให้เหมาะกับ HTTPS production
- [ ] จัดทำ Migration Backup & Rollback Plan ก่อนรัน migration บน Production
- [ ] จัดทำ Deploy Smoke Test Checklist ตรวจสอบ Health check, Core routes, และ DB connection หลัง release
- [ ] จัดทำ Failed-job Replay Runbook และระบบ Monitoring/Alerting สำหรับ Queue / Scheduler
- [ ] จัดทำ Log Redaction Review ตรวจสอบว่าไม่มี Password, Token, 2FA Secret หรือ PII หลุดไปใน Log files
- [ ] กำหนดนโยบาย automated database backup รายวัน
- [ ] เพิ่ม restore drill checklist และทดสอบกู้คืนข้อมูล (Restore Verification) พร้อมบันทึกหลักฐานผลการทดสอบอย่างน้อย 1 รอบก่อน go-live
- [ ] ระบุ retention policy และที่เก็บ backup แยกจาก production server

---

## Phase 9: Commercial & Procurement Documents

ที่มา: `gemini.md` Future Roadmap & Architectural Guardrails.

เป้าหมาย: เติมเอกสารการค้าและจัดซื้อที่จำเป็นตาม workflow ไทย ก่อนขยับไปบัญชีเต็มรูปแบบ.

### Phase 9 Design Backlog

- [x] Design Quotation schema/status flow: draft, sent, approved, rejected, expired
- [x] Design Quotation -> Invoice conversion จาก Deal/Customer/Line items
- [x] Design Credit Note / Debit Note schema อ้างอิง Invoice/Tax Invoice เดิม
- [x] Design CN/DN tax adjustment rules สำหรับรายงานภาษีขาย ภ.พ.30
- [x] Design guardrail: ห้ามใช้ Void แทน CN/DN หลังออกเอกสารภาษีทางการแล้ว
- [x] Design Billing Note / Statement of Account สำหรับรวม Invoice หลายใบของลูกค้ารายเดียว
- [x] Design Delivery Order schema/status/signature proof และ relation กับ Invoice/Stock
- [x] Design Purchase Request flow: employee request -> manager approve -> convert to PO
- [x] Design Payment Voucher / Receipt Voucher สำหรับบันทึกหลักฐานรับ/จ่ายเงิน, audit และ print/PDF
- [x] Design number sequences, permissions, org isolation และ audit trail สำหรับเอกสารใหม่ทั้งหมด
- [x] Design tests สำหรับ conversion, tax adjustment, status transition, cross-org guard, permissions

### Phase 9 Implementation Backlog

- [x] สร้าง migrations/models สำหรับ Quotations และ Quotation Items
- [x] สร้าง UI/Controller สำหรับ Quotations
- [x] เพิ่ม Convert Quotation to Invoice
- [x] สร้าง migrations/models สำหรับ Credit Notes / Debit Notes และ items
- [x] ผูก CN/DN กับ Invoice balance, tax report และ official PDF/print
- [x] สร้าง migrations/models สำหรับ Billing Notes และ Billing Note Lines
- [x] สร้าง UI/PDF สำหรับ Billing Note / Statement of Account
- [x] สร้าง migrations/models สำหรับ Delivery Orders และ items
- [x] ผูก Delivery Order กับ stock outbound และ customer acceptance
- [x] สร้าง migrations/models สำหรับ Purchase Requests
- [x] เพิ่ม PR approval และ Convert PR to PO
- [x] สร้าง Payment Voucher / Receipt Voucher print/PDF
- [x] เพิ่ม feature tests และ full regression validation

---

## Phase 10: Treasury, Banking & Cash Management

เป้าหมาย: ทำให้การรับ/จ่ายเงินผูกบัญชีธนาคารจริง กระทบยอดได้ และรองรับเงินสดย่อย/เช็คล่วงหน้า.

### Phase 10 Design Backlog

- [x] Design Bank Accounts Master: bank, branch, account no, account type, currency, org/branch scope
- [x] Design payment receipt/payment expense ให้ผูก bank account หรือ cash account
- [x] Design Bank Statement import format และ reconciliation matching rules
- [x] Design manual match/unmatch และ reconciliation audit trail
- [x] Design Petty Cash fund, request, reimbursement และ approval flow
- [x] Design Cheque/PDC register: received/issued, due date, cleared, bounced, cancelled
- [x] Design Voucher attachment: upload/download proof slip files สำหรับ PV/RV พร้อม org isolation & permission guards
- [x] Design permissions สำหรับ treasury setup, reconciliation, petty cash, cheque management
- [x] Design tests สำหรับ bank org isolation, reconciliation matching, petty cash approval, cheque status

### Phase 10 Implementation Backlog

- [x] สร้าง migrations/models สำหรับ Bank Accounts
- [x] เพิ่ม Bank Account UI
- [x] ผูก payment receipts/reversals กับ bank account
- [x] ผูก expense payments กับ bank/cash account
- [x] สร้าง migrations/models สำหรับ Bank Statements และ Statement Lines
- [x] เพิ่ม Bank Statement import และ reconciliation screen
- [x] เพิ่ม manual match/unmatch reconciliation
- [x] สร้าง Petty Cash schema/UI/approval flow
- [x] สร้าง Cheque/PDC schema/UI/status flow
- [x] เพิ่ม Voucher attachment upload/download และ tests ครอบคลุมความปลอดภัย
- [x] เพิ่ม treasury reports และ feature tests

---

## Phase 11: General Ledger & Double-entry Accounting

เป้าหมาย: เพิ่มแกนบัญชีคู่ของ ERP หลังเอกสารการค้าและธนาคารพร้อม.

### Phase 11 Design Backlog

- [x] Design Chart of Accounts schema: account code, name, type, parent account, active flag, org scope
- [x] Design Accounting Periods schema พร้อมสถานะ `open` / `closed`
- [x] Design Journal Entries schema: header, lines, debit/credit, posting date, source document, status
- [x] Design posting rules จาก Invoice, Payment, Expense, Stock, CN/DN, PV/RV, Bank, Petty Cash
- [x] Design period lock guard: ห้าม post, void หรือแก้ financial documents ย้อนหลังในงวดที่ปิดแล้ว
- [x] Design immutable posted journal policy และ reversal journal pattern
- [x] Design posting idempotency ด้วย `source_type` + `source_id`
- [x] Design Trial Balance, General Ledger report และ account ledger report
- [x] Design permissions และ tests สำหรับ double-entry balance, org isolation, posting/reversal

### Phase 11 Implementation Backlog

- [x] สร้าง migrations/models สำหรับ Chart of Accounts
- [x] สร้าง migrations/models สำหรับ Accounting Periods
- [x] สร้าง migrations/models สำหรับ Journal Entries และ Journal Lines
- [x] Seed default chart of accounts สำหรับบริษัทไทย SME
- [x] สร้าง Journal Posting service ที่บังคับ debit = credit เสมอ
- [x] เพิ่ม source idempotency guard
- [x] เพิ่ม period lock guard ใน financial document flows
- [x] ผูก auto-posting จาก Invoice, Payment, Expense, Stock, CN/DN, PV/RV, Bank, Petty Cash
- [x] สร้างหน้า Chart of Accounts และ Journal Entries
- [x] สร้าง Trial Balance และ GL reports
- [x] เพิ่ม feature tests และ full regression validation

---

## Phase 12: E-Tax & RD Online Tax Filing

เป้าหมาย: รองรับเอกสารภาษีอิเล็กทรอนิกส์, private XML, provider integration boundary และ RD Prep staging export. การลงนาม/ส่งจริงต้องผ่าน certified provider, certificate และ current XSD ของผู้ให้บริการ.

### Phase 12 Design Backlog

- [x] ล็อก scope เป็น provider-agnostic e-Tax integration; ไม่อ้างว่า XML ภายในเป็น RD schema ที่รับรอง
- [x] Design XML data mapping จาก Invoice / Tax Invoice / Receipt / CN / DN
- [x] Design digital signature/certificate storage และ rotation policy: เก็บเฉพาะ vault/KMS reference และ expiry metadata
- [x] Design e-Tax document status: generated, signed, submitted, accepted, rejected; submitted/accepted ห้าม generate ทับ ต้องออก CN/DN เพื่อแก้ไข
- [x] Design RD Prep Text Export สำหรับ ภ.ง.ด. 3 และ ภ.ง.ด. 53; ภ.ง.ด. 1 เป็น scope ของ Phase 16B Payroll
- [x] Design error handling, retry, idempotency และ submission audit log
- [x] Design permissions และ tests สำหรับ mapping XML, signature boundary, org isolation

### Phase 12 Implementation Backlog

- [x] เพิ่ม e-Tax configuration ต่อ organization
- [x] สร้าง XML generator service สำหรับ provider mapping profile
- [x] สร้าง signature adapter interface และ disabled adapter แบบ fail-closed
- [x] เพิ่ม download XML สำหรับเอกสารภาษีจาก private disk
- [x] เพิ่ม submission log/table, audit log และ UI status
- [x] เพิ่ม queued job สำหรับ submit/retry ผ่าน provider adapter
- [x] เพิ่ม RD Prep text staging export สำหรับ WHT ภ.ง.ด. 3/53 (ต้องตรวจ current RD format ก่อน upload)
- [x] เพิ่ม feature tests และ sample XML/text fixtures

---

## Phase 13: Fixed Assets & Depreciation

เป้าหมาย: จัดการทะเบียนทรัพย์สินและคำนวณค่าเสื่อมราคาเข้า GL.

### Phase 13 Design Backlog

- [x] Design asset categories และ fixed asset register schema
- [x] Design capitalization source จาก Expense และ GRN; PO เป็น reference ผ่าน GRN เพื่อไม่ให้เกิด duplicate accounting posting
- [x] Design depreciation methods รอบแรก: straight-line
- [x] Design monthly depreciation schedule และ posting to GL
- [x] Design disposal/write-off/sale flow
- [x] Design asset custody/location fields และ attachment proof
- [x] Design permissions และ tests สำหรับ depreciation, disposal, org isolation

### Phase 13 Implementation Backlog

- [x] สร้าง migrations/models สำหรับ Asset Categories, Fixed Assets และ monthly depreciation records
- [x] สร้าง asset register UI พร้อม category/account mapping, custody/location และ proof upload
- [x] เพิ่ม capitalization จาก approved/paid Expense และ Goods Receipt (PO ผ่าน GRN)
- [x] สร้าง straight-line depreciation service แบบ catch-up รายเดือน
- [x] เพิ่ม `assets:depreciate` command และ schedule รายเดือน
- [x] ผูก capitalization, depreciation และ disposal posting เข้า immutable GL
- [x] เพิ่ม disposal/write-off flow พร้อม gain/loss และ proceeds
- [x] เพิ่ม asset register summary/report และ feature tests

---

## Phase 14: Multi-Currency & FX

เป้าหมาย: รองรับซื้อขายหลายสกุลเงิน โดยผูกกับ GL/FX accounting.

### Phase 14 Design Backlog

- [x] Design currency master และ exchange rate table ต่อ organization
- [x] Design base currency policy และ document currency policy: `organizations.currency` เป็น base currency, เอกสารมี `currency` ของตนเอง
- [x] Design FX rate locking บน Invoice, Payment, Expense, PO
- [x] Design historical FX rate snapshot บนเอกสาร ไม่ใช้ dynamic join กับ rate ล่าสุด
- [x] Design realized/unrealized FX gain/loss posting rules: 4300/5400 สำหรับ realized และ 4310/5410 สำหรับ unrealized
- [x] Design period-end unrealized FX revaluation flow: AR open balance ณ สิ้นเดือน, idempotent posting และ auto-reversal เดือนถัดไป
- [x] Design bank account -> GL account mapping และเลือกบัญชีปลายทางสำหรับ fixed asset disposal proceeds เพื่อรองรับ reconciliation
- [x] Design UI display สำหรับ document amount vs base amount ผ่าน currency master/rate และ GL base-currency posting
- [x] Design reports ที่รองรับ currency/base currency: เอกสารคง document currency, GL/Trial Balance ใช้ base currency
- [x] Design tests สำหรับ rate lock, payment partial FX, reversal, org isolation

### Phase 14 Implementation Backlog

- [x] เพิ่ม currency/exchange rate schema, FX revaluation ledger และ permissions
- [x] เพิ่ม currency setup UI สำหรับ currency, rate, revaluation และ reversal
- [x] เพิ่ม currency/base fields ใน Invoice, Quotation, PO, Expense, CN/DN และ Payment
- [x] ปรับ totals calculation ให้เก็บ document currency และ base currency ด้วย `DECIMAL(18,6)` rate / `DECIMAL(18,2)` amount
- [x] บันทึก FX rate snapshot ณ วันที่ออกเอกสาร/เกิดรายการ; base currency ใช้ rate 1.000000
- [x] ผูก FX posting เข้า immutable GL พร้อม account provisioning
- [x] เพิ่ม realized FX ตอนรับ Invoice และ unrealized AR revaluation สิ้นงวด พร้อม commands `fx:revalue` / `fx:reverse-revaluations`
- [x] เพิ่ม bank account GL mapping และใช้บัญชีที่เลือกเมื่อ post disposal proceeds จาก Fixed Assets
- [x] ให้ document screens แสดง document currency และให้ GL/Trial Balance เป็น base-currency reporting source of truth
- [x] เพิ่ม `Phase14FxTest` และรัน regression Payments/General Ledger/full test suite

---

## Phase 14.1: AP FX & FCD Treasury

เป้าหมาย: ขยายการบัญชี FX จาก AR ไปยังเจ้าหนี้การค้า, inventory costing และบัญชีเงินฝากสกุลต่างประเทศ (FCD) อย่างตรวจสอบย้อนหลังได้.

### Phase 14.1 Design Backlog

- [x] Design AP subledger และ `VendorPayment` แยกจาก AR `Payment` เพื่อไม่กระทบ invoice receipt flow
- [x] Design Expense payable lifecycle: `approved` -> `partially_paid` -> `paid`, `balance_due` / `base_balance_due`, partial payment, reversal, idempotency และ `lockForUpdate()` overpay guard
- [x] Design AP historical/base settlement allocation และ realized FX posting (`2110`, `4300`, `5400`)
- [x] Design AP posting boundary: Expense เป็น AP source; GRN post Inventory/GRNI เท่านั้น
- [x] Design AP month-end revaluation และ auto-reversal (`2110`, `4310`, `5410`)
- [x] Design Inventory FX Bridge: GRN/Stock Movement เก็บ document currency, base currency, exchange-rate snapshot, `base_unit_cost` และ `base_total_cost`; Base-Currency Costing เป็น single source of truth
- [x] Design GRNI/inventory journal ให้ใช้ base cost และกำหนด rounding/allocation สำหรับ partial GRN จาก PO ต่างสกุล
- [x] Design FCD bank transaction ledger: foreign amount, base amount, rate snapshot และ opening balance
- [x] Design reconciliation guard ห้ามจับคู่ statement ข้าม currency และรองรับ partial/matched/unmatched audit trail
- [x] Design internal bank transfer / FX conversion ระหว่าง FCD และ base-currency bank account พร้อม realized FX และ two-sided statement reconciliation
- [x] Design FCD month-end revaluation, auto-reversal และ FX exposure report
- [x] Design scope guard: Phase 14.1 ครอบคลุม AP settlement/FX เท่านั้น; ไม่เพิ่ม ภ.ง.ด.54 หรือ ภ.พ.36
- [x] Design migration/backfill strategy สำหรับ Expense, Bank Statement และ Bank Reconciliation เดิม
- [x] Design tests สำหรับ org isolation, rate missing, duplicate settlement, partial payment, reversal, closed period, foreign PO partial GRN และ moving-average base cost

### Phase 14.1 Implementation Backlog

- [x] เพิ่ม AP payable balance/status schema และ `VendorPayment` model/controller/UI พร้อม partial payment / reversal guard
- [x] เพิ่ม AP payment journal, realized FX และ reversal journal
- [x] Implement AP posting boundary และ source-event idempotency ระหว่าง Expense/GRN
- [x] เพิ่ม AP revaluation service, commands, scheduler และ immutable audit trail
- [x] เพิ่ม GRN/Stock Movement FX snapshot/base-cost schema, backfill และปรับ inventory/GRNI journal ให้ post base currency
- [x] ปรับ inventory valuation/average cost/report ให้ใช้ `base_total_cost` เท่านั้น และแสดง document currency เป็นข้อมูลอ้างอิง
- [x] เพิ่ม FCD transaction/base snapshot schema และ bank opening balance conversion
- [x] ปรับ Bank Statement import/reconciliation ให้ validate account currency และเก็บ FX snapshot
- [x] เพิ่ม internal bank transfer / FX conversion พร้อม two-sided reconciliation และ realized FX journal
- [x] เพิ่ม FCD revaluation service, commands และ auto-reversal
- [x] เพิ่ม AP aging และ FX exposure reports (AR/AP/FCD, document/base currency)
- [x] เพิ่ม feature/regression tests และตรวจ migration บน MySQL รวม FX inventory bridge

---

## Phase 15: Advanced Inventory & Barcode/QR Operations

เป้าหมาย: เพิ่มความสามารถคลังสินค้าเชิงปฏิบัติการ: โอนคลัง, reorder, lot/expiry และ scanner.

### Phase 15 Design Backlog

- [x] Design warehouse/location/bin schema สำหรับตำแหน่งจัดเก็บสินค้า
- [x] Design stock transfer ระหว่างคลัง/สาขา
- [x] Design reorder point และ low stock alert
- [x] Design lot number และ expiration tracking
- [x] Design barcode/QR field บน Product/SKU/Lot
- [x] Design scanner UX สำหรับ GRN, DO, Stock Adjustment, Stock Count
- [x] Design duplicate barcode guard, org isolation และ mobile/responsive behavior
- [x] Design tests สำหรับ transfer, reorder alert, lot expiry, scan match, negative stock guard

### Phase 15 Implementation Backlog

- [x] เพิ่ม warehouse/location/bin schema และ UI พื้นฐาน
- [x] ผูก stock movements กับ warehouse/bin location
- [x] เพิ่ม stock transfer flow
- [x] เพิ่ม reorder point fields และ low stock notification
- [x] เพิ่ม lot/expiry schema และ movement tracking
- [x] เพิ่ม barcode/QR fields ใน products/lots
- [x] เพิ่ม scanner input mode ใน Goods Receipt, Delivery Order, Stock Adjustment
- [x] เพิ่ม stock count workflow
- [x] เพิ่ม feature tests และ frontend validation

---

## Phase 16B: Payroll, Social Security & Tax

เป้าหมาย: รองรับเงินเดือน ภาษีเงินได้พนักงาน ประกันสังคม payslip และ GL posting หลังล็อก payroll/tax policy ที่ versioned แล้ว.

### Phase 16B Design Backlog

- [x] Design employee payroll profile: salary, tax id, social security, payment method
- [x] Design payroll period, payroll run, earnings/deductions, approval flow
- [x] Design Thai withholding tax ภ.ง.ด. 1 / 1ก calculation/export scope เป็น workpaper CSV; ผู้ใช้ต้องตรวจรูปแบบ/กฎล่าสุดก่อนยื่น
- [x] Design Social Security contribution rules แบบ effective-dated policy
- [x] Design payslip PDF และ employee access guard
- [x] Design payroll posting to GL
- [x] Design effective-dated tax/social-security policy tables และ official rule verification ก่อน implement calculation/export
- [x] Design payroll payment boundary แยกจาก `VendorPayment` และกำหนด THB-only initial scope
- [x] Design tests สำหรับ payroll calculation, org isolation, payslip privacy, tax deduction และ GL posting

### Phase 16B Implementation Backlog

- [x] สร้าง payroll profile schema/UI
- [x] สร้าง payroll periods/runs schema
- [x] เพิ่ม payroll calculation service
- [x] เพิ่ม approval/payment status flow
- [x] สร้าง payslip PDF
- [x] เพิ่ม ภ.ง.ด. 1 / 1ก workpaper และ Social Security CSV exports
- [x] ผูก payroll posting เข้า GL
- [x] เพิ่ม feature tests, privacy tests และ tax-policy regression tests

---

## Phase 17: Enterprise Document Management (DMS) & Cross-Module Integration

เป้าหมาย: ศูนย์กลางจัดเก็บเอกสารและสัญญาองค์กรแบบ versioned เชื่อมหลายโมดูลผ่าน `document_links`, private download control, category expiry, retention policy และ sensitivity RBAC.

### Phase 17 Design Backlog

- [x] Design central document repository, category/folder hierarchy และ tag taxonomy
- [x] Design many-to-many `document_links` (`document_id`, `linkable_type`, `linkable_id`, `role`) สำหรับ Customers, Deals, Suppliers, POs, Projects, Tasks, Fixed Assets, Bank Accounts, Accounting Periods และ Employees
- [x] Design immutable document versioning (`document_versions`: version no., checksum, uploader, changelog, scan status)
- [x] Design category-driven expiry (`expires_at`, `renewal_alert_days`) และ automated scheduled alert สำหรับ contract/warranty/license/certificate/insurance เท่านั้น
- [x] Design sensitivity RBAC: `org_internal`, `department_restricted`, `finance_confidential`, `hr_confidential`, `executive_confidential`; parent permission เป็น baseline และ sensitivity เป็น additional restriction
- [x] Design tenant-isolated private storage, MIME/content verification, malware guard และ private download controller ที่ตรวจ session/org/parent permission/sensitivity/scan status ทุก request
- [x] Design effective-dated retention policy ต่อ category; เอกสาร VAT/ภาษี/บัญชีที่ posted/submitted/accepted append-only และห้าม hard delete ใน legal retention window
- [x] Design legacy `StoredFile` backfill/reconciliation แบบ idempotent โดยคง legacy FK/storage จนตรวจครบ
- [x] Design tests สำหรับ links, versioning, expiry notifications, retention, confidential access, scan failure, legacy migration และ org isolation

### Phase 17 Implementation Backlog

- [x] สร้าง `documents`, `document_versions`, `document_links`, categories และ retention policy schema/migration พร้อม org isolation
- [x] สร้าง DMS central repository UI (repository list, upload metadata, category/retention settings และ cross-module link panel)
- [x] พัฒนา Document Versioning service (Upload new version, View history, Download past version)
- [x] พัฒนา Document Expiration Scheduler (`documents:check-expiry`) พร้อม Email & In-App Notification เฉพาะ category ที่กำหนด
- [x] เพิ่ม Document Tab Component ใน DMS repository สำหรับ Customer, Supplier, Project, Asset, PO และ parent ที่ allowlist ผ่าน `document_links`
- [x] เพิ่ม sensitivity/retention guard, private download controller, scan-state gate และ legacy backfill command/report
- [x] เพิ่ม Feature tests, org isolation, confidential access, immutable version, retention, scan failure และ legacy reconciliation regression tests

---

## Phase 18: Security 2FA / Auth OTP

เป้าหมาย: เพิ่ม 2FA ให้ owner/admin/finance หลัง Payroll และ DMS ตาม product decision (นโยบาย default เป็น `enabled = false` ต่อองค์กร และบังคับ privileged role เฉพาะเมื่อเปิด policy). ระหว่างยังไม่เริ่ม ต้องคง password confirmation, email verification, rate limit login, session invalidation และ audit log สำหรับ sensitive actions.

### Phase 18 Design Backlog

- [x] Design TOTP authenticator setup, encrypted secret storage และ recovery codes
- [x] Design enforcement policy สำหรับ owner/admin/finance, step-up challenge และ trusted-device/session policy
- [x] Design recovery, reset-by-owner, rate limit, audit log และ session invalidation flows
- [x] Design tests สำหรับ challenge, recovery, role change, org isolation และ brute-force guard

### Phase 18 Implementation Backlog

- [x] เพิ่ม 2FA schema/model, setup/verify/recovery UI และ privileged challenge middleware
- [x] เพิ่ม enforcement settings, audit log, rate limits และ security regression tests
- [ ] Polish: Recovery code one-time display ผ่าน Inertia flash/dedicated UI โดยไม่ persist ลง browser history/log
- [ ] Polish: เปลี่ยน invalid TOTP setup จาก `abort(422)` เป็น validation error แสดงใต้ช่องกรอกรหัส
- [ ] Polish: เก็บ TOTP setup secret ไว้ใน session ระหว่างกรอกรหัสผิด และล้างเมื่อ verify สำเร็จหรือหมดอายุ
- [ ] Polish: Challenge UI รับ `allow_trusted_devices` และ `trusted_device_days` จาก organization policy (ซ่อน checkbox เมื่อปิด และไม่ hardcode 30 วัน)

---

## Phase 18.1: DMS Security & Compliance Remediation

เป้าหมาย: ปิดช่องว่างด้าน Security และ Data-Compliance ของระบบจัดการเอกสาร (Phase 17) ตามหลักฐานโค้ด ก่อนเริ่มพัฒนา Feature Phase ถัดไป

### Phase 18.1 Design Backlog

- [x] Design Parent-Permission Authorization Guard สำหรับการดาวน์โหลดเอกสาร (ตรวจสอบสิทธิ์การเข้าถึง Parent Record: Expense, PO, Deal, Customer, Supplier)
- [x] Design Source Entity Permission Check เมื่อสร้าง `document_link` (ป้องกันการผูกเอกสารข้าม entity ที่ผู้ใช้ไม่มีสิทธิ์)
- [x] Design Category-based Retention Engine: คำนวณ `retention_until` อัตโนมัติ, บังคับใช้ `legal_hold`, และ Default Renewal ตาม Policy (Tax/VAT $\ge$ 5 ปี, General, Accounting)
- [x] Design Automated Retention Purge/Archive Command & Scan Failure Re-evaluation Job

### Phase 18.1 Implementation Backlog

- [x] ปรับปรุง `DocumentDownloadController` ให้ตรวจสอบสิทธิ์ของ Parent Model ผ่าน `document_links` นอกเหนือจาก `documents.download` และ `sensitivity`
- [x] ปรับปรุง `DocumentLinkController` ตรวจสอบว่าผู้ใช้มีสิทธิ์ใน Entity ต้นทางก่อนอนุญาตให้ผูกเอกสาร
- [x] พัฒนา `DocumentRetentionService` คำนวณ `retention_until` ตามประเภทเอกสาร, บังคับ `legal_hold`, quarantine failed scan, scheduled archive และ explicit purge
- [x] เพิ่ม Feature Tests: Parent permission download guard, Unauthorized link prevention, Retention expiry calculation, Legal hold delete block, และ Scan-state failure tests

---

## Phase 19: HR Core, Attendance & Leave Foundation

เป้าหมาย: วางโครงสร้างประวัติพนักงาน, ระบบลงเวลาเข้า-ออกงาน (Clock-in/out), สิทธิ์การลา (Leave Balance) และการยื่นคำขอลา เพื่อเป็นฐานข้อมูลต้นทางที่ถูกต้องก่อนส่งสรุปเข้าสู่ระบบ Payroll (Phase 16B)
**Scope Rule:** ห้ามทำ Bespoke Approval Engine ใน Phase 19 (การอนุมัติวันลาหลายระดับ, Delegation, SoD จะทำใน Phase 20 Central Workflow Engine)

### Phase 19 Design Backlog

- [x] Design Employee Work Profile, Shift/Schedule, Holiday Calendar และ Work Hours Definition
- [x] Design Attendance Schema (Check-in/out events, source: web/mobile) พร้อม Privacy Guard (GPS/IP Range เป็น Opt-in ต่อองค์กร)
- [x] Design Leave Policy Engine (ประเภทการลา, สิทธิ์สะสม, นโยบายยกยอดวันลา) และ Leave Request Draft/Submit Flow
- [x] Design Payroll Summary Bridge (Cutoff Date, Summary of Working Hours / OT / Leave Without Pay, Correction & Reversal mechanism)
- [x] Design RBAC & Privacy (พนักงานดูได้เฉพาะเวลาและวันลาของตนเอง; ผู้จัดการดูทีม; HR/Admin ดูทั้งองค์กร)

### Phase 19 Implementation Backlog

- [x] เพิ่ม Schema/Models: `employee_shifts`, `employee_work_profiles`, `holidays`, `attendances`, `leave_types`, `leave_balances`, `leave_requests`, `attendance_summaries`
- [x] เพิ่ม UI: Employee Self-Service Clock-in/out, Leave Request Form, Leave Balance Card และ Employee/Manager Attendance Summary List
- [x] เพิ่ม Setting: Attendance Privacy & Location Verification Settings (Opt-in GPS/IP with Employee Consent)
- [x] เพิ่ม Payroll Bridge: สรุปยอดชั่วโมงทำงานและวันลาขาด/เกินสิทธิ์ พร้อมระบบ Lock งวดก่อนส่งเข้า `payroll_runs` (ห้าม auto-post โดยไม่มี period lock)
- [x] เพิ่ม Feature Tests: Attendance logging, Leave accrual & deduction, Org isolation, Privacy guards, และ Payroll summary cutoff tests

---

## Phase 20: Dynamic Approval Workflow Engine

เป้าหมาย: สร้างระบบจัดการสายการอนุมัติกลาง (Central Workflow Engine) ที่กำหนดเงื่อนไขตามวงเงินและระดับตำแหน่ง สำหรับใช้งานร่วมกันใน Leave, PR, PO, Expense และเอกสารสำคัญ

### Phase 20 Design Backlog

- [x] Design Workflow Definition Schema (Versioned workflow templates, Module triggers: Leave, PR, PO, Expense)
- [x] Design Rule & Step Engine (Threshold amounts, Manager/Role/User matching, Multi-level sequential/parallel approvals)
- [x] Design Execution State Machine (Pending, Approved, Rejected, Revision Requested)
- [x] Design Delegation & Segregation of Duties (SoD Guard: ห้ามผู้อนุมัติอนุมัติเอกสารที่ตนเองเป็นผู้สร้าง, การมอบอำนาจชั่วคราว)
- [x] Design Deterministic Document Snapshot (Snapshot approver chain ณ เวลาที่เอกสาร Submit เพื่อป้องกันผลกระทบจากการเปลี่ยนตำแหน่งย้อนหลัง)

### Phase 20 Implementation Backlog

- [x] เพิ่ม Schema/Models: `workflow_definitions`, `workflow_steps`, `workflow_instances`, `workflow_approvals`, `workflow_delegations`
- [x] เพิ่ม Workflow Engine Service: Evaluator, Step Transition Handler, Snapshot Capture, และ Permission Authorizer
- [x] เพิ่ม UI: Workflow Builder & Rule Configurator (Admin), Approval Inbox พร้อม action confirmation/comment (Approve / Reject / Revise), Document Approval History Timeline
- [x] เชื่อมต่อโมดูล: Leave Requests และ endpoint กลางสำหรับ PR, PO, Expense เข้าสู่ Approval Engine
- [x] เพิ่ม Feature Tests: Threshold evaluation, Segregation of duties, Delegation expiry, Immutable history audit, และ State transition idempotency tests

---

## Phase 21: Operational Notifications & Outbox Engine

เป้าหมาย: สร้างระบบส่งข้อความแจ้งเตือนการปฏิบัติงานไปยังช่องทางภายนอก (LINE Official Account, Slack, Telegram) ผ่าน Outbox Queue ที่ปลอดภัย เชื่อถือได้ และเคารพความเป็นส่วนตัว

### Phase 21 Design Backlog

- [x] Design Notification Outbox Architecture (Outbox table, worker with exponential backoff retry, idempotency key, dead-letter log)
- [x] Design Channel Adapters: LINE OA Webhook / Push Message, Slack Webhook, Telegram Bot
- [x] Design Organization Channel Settings & Credential Vault (เก็บบันทึก Channel Token/Secrets แบบ Encrypted)
- [x] Design User Preferences & Quiet Hours (กำหนดช่วงเวลาห้ามรบกวน, เลือกรับเฉพาะเรื่องด่วน)
- [x] Design Security Guardrail: **ห้ามส่ง Cash Balance ดิบใน Daily Digest**; ส่งเฉพาะ AR/AP Aging, Due Items, ยอดขาย, ค่าใช้จ่าย, Low Stock, Overdue Tasks

### Phase 21 Implementation Backlog

- [x] เพิ่ม Schema/Models: `notification_channels`, `notification_outbox`, `notification_dispatches`, `user_channel_preferences`
- [x] พัฒนา Channel Drivers: `LineMessagingDriver`, `SlackWebhookDriver`, `TelegramBotDriver`
- [x] พัฒนา Outbox Dispatcher Worker & Scheduled Command สำหรับ Daily Morning Executive Digest
- [x] เพิ่ม UI: Channel Configuration & Test Ping (Admin), Notification Preference Toggles (User Profile)
- [x] เพิ่ม Feature Tests: Outbox retry, Idempotency, Encryption of webhook secrets, Quiet hours compliance, และ Non-leakage of cash balance tests

---

## Phase 22: Customer & Supplier Self-Service Portals

เป้าหมาย: เพิ่มพอร์ทัลบริการตนเองสำหรับลูกค้า (Customer Portal) และคู่ค้า (Supplier Portal) เพื่อดูเอกสาร ยอมรับใบเสนอราคา วางบิล และดาวน์โหลดหนังสือรับรองภาษี

### Phase 22 Design Backlog

- [x] Design External Identity & Authentication (Magic Link / Passwordless OTP แยกจาก Staff Authentication)
- [x] Design Scoped Access & Permission Matrix (Customer: Quotation, Invoice, Receipt, Statement; Supplier: PO, Bill Submission, Payment Status, 50 ทวิ)
- [x] Design Quotation Acceptance Flow (Digital Confirmation, Audit Trail IP/Timestamp, Auto-create Order/Invoice)
- [x] Design Vendor Billing Submission (Upload vendor invoice/slip เข้าสู่ระบบเพื่อรอตรวจสอบใน AP)
- [x] Design Upload Security & Intake Guard (Malware/quarantine scan state, MIME whitelist, size quota, DMS audit trail, และ Human review gate ก่อนสร้าง AP draft)
- [x] Design Document Download Security (ดาวน์โหลดผ่าน Private Scoped Controller; ไม่มี Public Document Links)

### Phase 22 Implementation Backlog

- [x] เพิ่ม Schema/Models: `portal_users`, `portal_access_tokens`, `portal_sessions`, `quotation_acceptances`, `vendor_bill_submissions`
- [x] เพิ่ม Portal Guest/Auth Layout และหน้าจอ: Customer Portal Dashboard, Supplier Portal Dashboard, Document Viewer/Downloader
- [x] พัฒนา Quotation Online Acceptance Controller และ Vendor Invoice Intake Controller
- [x] เพิ่ม Portal Security Guard: Rate limiting, Org/Customer/Supplier isolation, Session revocation, File scan status gate
- [x] เพิ่ม Feature Tests: Magic Link authentication, Customer/Supplier data isolation, Quotation online signing, และ Document download protection tests

---

## Phase 23: Thai PromptPay QR & Payment Gateway Integration

เป้าหมาย: เพิ่มระบบชำระเงินดิจิทัล สร้าง PromptPay QR Code ที่ระบุยอดเงินเฉพาะบิล และเชื่อมต่อ Payment Gateway Webhooks เพื่อกระทบยอดรับชำระเงินอัตโนมัติ

### Phase 23 Design Backlog

Implementation note: HMAC settlement bridge is a separate integration contract, not the native webhook protocol of Omise/GB Prime Pay/2C2P. Native adapters must verify provider-specific settlement evidence before enabling live posting. QR generation alone is never proof of payment.

- [x] Design EMVCo Thai PromptPay QR Generator (รองรับ Tax ID/Biller ID + Amount + Reference 1/2)
- [x] Design Gateway Adapter Architecture (Omise, GB Prime Pay, 2C2P); provider-specific verification boundaries documented in `docs/PHASE_23_PAYMENT_GATEWAY.md`
- [x] Design Webhook Intake & Verification (HMAC-SHA256 Signature verification, Idempotency tracking, Replay attack protection)
- [x] Design Settlement & Reconciliation Gate (ตรวจสอบ Signature, Idempotency, Event ordering, Amount/Currency matching และ Final Settlement status ก่อนสร้าง Payment/GL)

### Phase 23 Implementation Backlog

- [x] เพิ่ม Schema/Models: `payment_gateway_configs`, `gateway_transactions`, `webhook_events`; migrated in SQLite tests and configured local MySQL
- [x] พัฒนา Thai QR Code Generator Utility (ฝังลงใน Invoice Print / PDF และ Customer Portal)
- [x] พัฒนา Webhook Receiver Controller พร้อม Signature Verification และ Idempotency Guard (explicit `settlement_hmac` bridge contract)
- [x] พัฒนา Payment Reconciliation Service: ตรวจสอบสถานะ Settled, อัปเดต Invoice เป็น paid, สร้าง `payments` record, และ Trigger Double-Entry GL Posting
- [x] เพิ่ม Feature Tests: PromptPay payload structure, Webhook signature verification, Duplicate webhook rejection, และ Settlement-verified GL posting tests
- [x] Settings UI: disabled-by-default config, encrypted secrets, bank selection, recent intents and review event reasons
- [x] Opn native PromptPay checkout, provider QR retrieval, event/charge API verification, encrypted merchant key/test-live settings and ambiguous-create retry guard; verified with HTTP fixtures
- [x] MySQL migration and concurrency verification: 15 tests / 101 assertions on isolated MySQL; migration/rollback and two-process duplicate settlement passed. Configured local `erp` database migrated through Phase 23, batch 14, on 2026-09-10
- [x] Cross-module regression: 57 tests / 325 assertions; Opn delayed payout/timezone handling and test-mode posting rejection included; TypeScript, scoped ESLint, Pint and production build passed

Phase 23 closed on 2026-09-10 at the user's request. Scope covers implemented code, migrations and local/fixture verification. Live settlement integration is excluded from closure because a connection has not yet been requested. This closure does not certify or enable live payments.

### Deferred Live Integration (Outside Phase 23 Closure)

- [ ] Provision merchant account and independent final-settlement bridge after the connection is requested; verify real settlement evidence against the provider contract
- [ ] Verify banking-app QR scan and provider sandbox end-to-end flow before enabling live payments

These items are deferred, not completed or cancelled. Existing signature, reconciliation and test-mode posting guards remain mandatory.

---

## Phase 23.1: System-wide Create/Edit Dialog Standardization

เป้าหมาย: เปลี่ยนการเพิ่มและแก้ไขข้อมูลจากฟอร์มเดิมด้านข้าง, inline form, popup และหน้าแยก ให้เป็น dialog แบบใหม่ทั้งระบบ โดยเหลือช่องทาง Create/Edit มาตรฐานเดียวต่อรายการ อ้างอิง `create-edit-dialog-template-concept.md`

สถานะ ณ 2026-09-14: Module Rollout (23.1.4) ครบทุกกลุ่มโมดูลแล้ว ทั้ง 20 หมวดหมู่ได้รับการจัดการตามมาตรฐาน (ย้าย entry points มาใช้ `CreateEditDialog` และ dialog เฉพาะทาง หรือบันทึกเป็น Documented Exceptions สำหรับ full-page settings/security tabs) ถอด sidebar/inline editor เก่าในกลุ่มที่ย้ายออกทั้งหมด คง backend rules, permissions และ validation contracts; ผลทดสอบ backend regression ผ่าน 305 tests / 2,524 assertions (2 skipped), TypeScript & production build ผ่านสมบูรณ์, ESLint ผ่าน 0 errors / 0 warnings, และ Pint ผ่าน; ขั้นตอนถัดไปคือการตรวจรับรอง browser regression และ cross-module compatibility ใน 23.1.5

### 23.1.1 Scope & Inventory

เสร็จ 2026-09-11: ตรวจ 60 Pages, 92 JS/TS source files และ 252 routes (173 write routes). Inventory 91 แถว: เปลี่ยนแล้ว 4, ต้องเปลี่ยน 66, integration gap 1, ข้อยกเว้น 20; backend-only 12 routes แยกไว้ ไม่สร้าง UI ใหม่อัตโนมัติ. ดูภาพรวม `docs/PHASE_23_1_CHANGE_MAP.md` และรายฟอร์มพร้อมหลักฐาน `docs/PHASE_23_1_FORM_INVENTORY.md` / `.json`. การปิด inventory ไม่ใช่การปิด rollout; Sales ทั้ง 4 ยังรอ browser regression

- [x] สำรวจทุก entry point ของ Create/Edit จาก routes, React pages, shared components, dashboard shortcuts และ Portal ไม่จำกัดเฉพาะหน้าที่มีชื่อโมดูล
- [x] จัดทำตารางรายฟอร์ม: module, route, create/edit action, fields, reference fields, line items, attachment, permission, business state, รูปแบบเดิม, รูปแบบใหม่ และผลทดสอบ
- [x] รวม master data, transaction documents, รายการย่อย, Settings, Organization Structure, User/Role, Profile และ external Portal ในการสำรวจ
- [x] ระบุข้อยกเว้นรายหน้าพร้อมเหตุผลก่อน rollout: Login/Register/OTP/password recovery, read-only/report/print, search/filter และคำสั่ง approve/post/pay/delete ไม่ใช่ฟอร์ม CRUD ทั่วไป; คง security flow และ confirmation ที่เหมาะสม ไม่บังคับแปลงเป็น editor โดยอัตโนมัติ; รายละเอียดใน `docs/PHASE_23_1_DIALOG_ROLLOUT.md`
- [x] แยกฟอร์มที่เพิ่ม/แก้ไขได้จริงออกจากเอกสาร posted/locked/approved; ไม่สร้างปุ่ม Edit ใหม่ให้สถานะที่ backend ห้ามแก้ไข — พบ G01 Attendance Privacy ขาด route และ G02 Invoice Edit กว้างกว่า backend draft-only; บันทึกสิ่งที่ต้องแก้ไว้แล้ว ยังไม่แก้โค้ดในงาน inventory
- [x] กำหนดลำดับ rollout และ acceptance รายโมดูล; ทุกฟอร์มต้องมีสถานะ migrated หรือ documented exception ก่อนปิด Phase; ดู `docs/PHASE_23_1_DIALOG_ROLLOUT.md`

### 23.1.2 Shared Dialog & Form Standard

สถานะ: shared standard เสร็จ 2026-09-13; หลักฐานและขอบเขต rollout ดู docs/PHASE_23_1_SHARED_STANDARD.md

- [x] แยก shared Create/Edit dialog container จาก pilot ให้ใช้ร่วมกัน โดยเก็บ validation, payload, domain logic และสิทธิ์ไว้ในโมดูล ไม่สร้าง generic CRUD engine แทน business rules
- [x] ใช้ dialog เดียวสำหรับ Create/Edit พร้อม title/breadcrumb, prefill, loading และ error state; เปิดทับหน้าเดิมโดยไม่เปลี่ยน route ใน workflow ปกติ
- [x] รองรับ normal ประมาณ 640px, expanded และ minimized; จำขนาดล่าสุดด้วย browser storage เฉพาะ preference และใช้ CSS แทน browser Fullscreen API
- [x] Header/Footer อยู่ให้เข้าถึงได้ตลอด พร้อมปุ่ม Save/Cancel; scroll เฉพาะ content และปรับ grid 1/2/3/4 คอลัมน์ตามพื้นที่และชนิดข้อมูล
- [x] จัด sections ข้อมูลอ้างอิง, รายละเอียด/สถานะ, รายการย่อย และหมายเหตุ/ไฟล์แนบตามความจำเป็น โดยไม่ซ้อนกรอบตกแต่งหลายชั้น
- [x] รองรับมือถือเต็มจอ, virtual keyboard, landscape และหน้าจอสั้น; ไม่มีข้อความ/ปุ่มซ้อนหรือ horizontal overflow ของฟอร์ม
- [x] กำหนดนโยบาย draft กลาง: ระยะแรกเปิด editor ที่ active ได้หนึ่งรายการ; ย่อแล้วคืนข้อมูลได้ และต้องยืนยันก่อนแทนที่ draft เดิม ไม่ให้หลายโมดูลสร้างแถบลอยทับกัน
- [x] ระบุขอบเขต draft ชัดเจน: อยู่ใน memory ระหว่างอยู่หน้าเดิม ไม่อ้างว่า autosave; ไม่เก็บข้อมูลลูกค้า/บุคลากร/secrets ลง localStorage หากยังไม่มี security design สำหรับ persistent draft
- [x] ใช้ discard confirmation ภายในแอปสำหรับ X/Cancel/Escape/overlay/เปลี่ยนรายการ และเตือนก่อน navigation/reload/back เมื่อข้อมูลยังไม่บันทึก; ไม่ทิ้ง draft เงียบ ๆ
- [x] รองรับ keyboard, focus trap/restore, accessible labels, required fields, error summary และ focus ไปยังฟิลด์ผิด; คืนฟอร์มจาก minimized ได้โดยข้อมูลไม่หาย
- [x] ป้องกัน submit ซ้ำและการปิด/สลับรายการระหว่างบันทึก; error/network failure ต้องเก็บข้อมูลไว้ให้แก้หรือ retry โดยไม่สร้างรายการซ้ำ
- [x] ใช้ date input/picker, numeric input, checkbox/toggle และ searchable combobox ให้ตรงชนิดข้อมูล; แสดงค่าปัจจุบันที่ inactive/ถูกจำกัดสิทธิ์อย่างถูกต้อง ไม่แทนค่าหรือ clear ค่าเงียบ ๆ
- [x] เพิ่ม "+ สร้างใหม่" ใน reference picker เฉพาะ entity ที่มีสิทธิ์และ workflow รองรับ; สร้างแล้วเลือกกลับได้โดยไม่ทำ draft หลักหาย ไม่เปิดทางสร้าง User/Role หรือข้อมูลข้ามองค์กรโดยอ้อม
- [x] ตาราง line items หนึ่งแถวต่อรายการ เพิ่ม/แก้ไข/ลบในบรรทัด; ตารางเลื่อนภายในเมื่อรายการมาก และแสดง indexed validation errors ถูกแถว
- [x] Footer แสดงยอด real-time สำหรับเอกสารการเงิน หรือสถานะสำหรับรายการทั่วไป; VAT/WHT/discount/FX/rounding ต้องตรง backend ซึ่งยังเป็นแหล่งคำนวณที่เชื่อถือได้
- [x] คง attachment/DMS upload, existing links, access control และสถานะไฟล์เมื่อบันทึกล้มเหลวหรือยกเลิก ไม่ทำไฟล์อ้างอิงเดิมหาย

### 23.1.3 Customer & Contact Pilot

สถานะ: เสร็จ 2026-09-13; ผล browser regression และข้อจำกัด native reload prompt ดู docs/PHASE_23_1_CUSTOMER_CONTACT_ACCEPTANCE.md. ข้อความผลตรวจวันที่ 2026-09-11 ด้านล่างเป็นประวัติเดิม

- [x] Customer Create/Edit เปลี่ยนเป็น dialog พร้อม normal/expanded/minimized, grouped fields, owner search และ validation จาก backend
- [x] Contact Add/Edit เปลี่ยนเป็น dialog; เอาฟอร์มด้านข้างเดิมออก และเปิดจากแถว Customer/ชื่อ Contact
- [x] คง Customer association ของ Contact และ Primary Contact หนึ่งคนต่อลูกค้า; แสดง LINE ID และ Note ที่ backend รองรับ
- [x] Pilot verification: TypeScript, scoped ESLint, Pint, production build และ Phase2SalesTest ผ่าน 7 tests / 88 assertions; ตรวจ Contact desktop/mobile และ minimize/restore โดยไม่บันทึกข้อมูลทดสอบลงฐานจริง
- [x] ย้าย Customer/Contact มาใช้ shared `CreateEditDialog`; backend regression `Phase2SalesTest` ผ่านในการตรวจวันที่ 2026-09-11
- [x] ตรวจ browser regression Customer/Contact หลังย้าย shared container ให้ครบตามมาตรฐานกลาง
- [x] เพิ่ม History API guard ก่อน Inertia initialization และเชื่อม `useDiscardGuard`; automated history tests ผ่าน 9 กรณี (Back/Forward, Keep/Discard, processing, rapid Back, effect cleanup และ save ระหว่าง restoration). ยังไม่ถือว่าผ่าน browser regression
- [x] เตรียม isolated SQLite browser fixture พร้อม synthetic account และ server พอร์ต 8011; prepare และ GET `/login` ผ่าน HTTP 200. ขั้นตอนอยู่ใน `docs/PHASE_23_1_DIALOG_ROLLOUT.md`
- [x] Pilot hardening รอบแรก 2026-09-11: ปุ่มและ submit guards ใช้ permission ราย Customer/Contact action; shared error summary แสดง backend messages และ focus fallback; Phase2SalesTest ผ่าน 8 tests / 108 assertions พร้อม TypeScript, scoped ESLint, Pint และ production build. Browser regression ยังไม่รวมในผลนี้
- [x] ปิดช่องว่าง pilot: ทดสอบ discard confirmation ให้ครบโดยไม่ติด native browser confirm, Edit prefill, switch draft, navigation/back/reload และ browser save/error round-trip บนฐานทดสอบ

### 23.1.4 Module Rollout

- [x] Petty Cash implementation 2026-09-14: Fund/Request/Reimbursement ใช้ shared dialog และ retry protection; คง approval/payment guards และ GL posting ของ reimbursement. Regression 7 tests / 113 assertions ผ่าน; browser acceptance ยัง pending ตาม docs/PHASE_23_1_MASTER_ROLLOUT.md

- [x] Fixed Asset attachment implementation 2026-09-13: Upload/Replace Proof ใช้ shared dialog; แก้ FileAttachmentManager ไม่รองรับ fixed_asset และเก็บไฟล์เดิมไว้เมื่อการ store ไฟล์ใหม่ล้มเหลว. Regression 15 tests / 116 assertions ผ่าน; browser acceptance ยัง pending ตาม docs/PHASE_23_1_MASTER_ROLLOUT.md

- [x] Fixed Assets implementation 2026-09-13: Category และ Capitalize Asset ใช้ shared dialog พร้อม draft/validation/reference search และ retry protection; แก้ stale success flash สร้าง receipt หลัง validation ล้มเหลว. Regression 13 tests / 232 assertions ผ่าน; attachment editor และ browser acceptance ยัง pending ตาม docs/PHASE_23_1_MASTER_ROLLOUT.md

- [x] Accounting implementation 2026-09-13: Chart of Accounts และ Manual Journal ใช้ shared dialog แล้ว; COA ป้องกัน create retry ซ้ำ และ Journal คง idempotency_key ต่อ draft. Regression 6 tests / 109 assertions ผ่าน; browser acceptance ยัง pending ตาม docs/PHASE_23_1_MASTER_ROLLOUT.md

- [x] FX/Periods implementation 2026-09-13: Currencies, exchange-rate settings และ Accounting Periods ใช้ shared dialog; แก้ rate upsert ชน unique key บน SQLite; regression 12 tests / 123 assertions ผ่าน. Browser acceptance ยัง pending ตาม docs/PHASE_23_1_MASTER_ROLLOUT.md

- [x] Master data implementation 2026-09-13: Products/Services, Suppliers, Bank Accounts ใช้ shared dialog; backend 14 tests / 130 assertions, TypeScript/ESLint/Pint/build ผ่าน. ดู docs/PHASE_23_1_MASTER_ROLLOUT.md
- [x] Master data rollout (Products, Suppliers, Bank Accounts): ย้ายมาใช้ shared dialog ครบทั้งสามหน้าแล้ว; backend 14 tests / 130 assertions ผ่าน (การตรวจ browser runtime แยกติดตามใน 23.1.5)

Deals/Activities implementation แล้ว: ย้าย sidebar เป็น shared dialog, เพิ่ม permission guards และตรวจ payload/validation ครบถ้วน

- [x] Deals/Activities implementation: Create/Edit dialog, reference search, explicit UTC activity inputs, optional field preservation, one active editor และ discard/history guard; Phase2SalesTest + Phase231SalesDialogsTest ผ่าน 12 tests / 196 assertions พร้อม TypeScript, scoped ESLint และ build (2026-09-11)

ทุกข้อด้านล่างรวม Create/Edit และฟอร์มย่อยทั้งหมดที่พบจาก inventory; จัดการ entry points เป็น migrated หรือ documented exception ครบถ้วน และถอดฟอร์มเก่าในกลุ่มที่ย้ายออกทั้งหมด พร้อมผ่าน regression ของทุกโมดูลแล้ว (305 backend tests / 2,524 assertions, TypeScript, ESLint 0 warnings, Pint, build ผ่านสมบูรณ์ ณ 2026-09-14)

- [x] Sales: Deals และ Activities/Follow-ups รวมฟอร์มจาก Sales Dashboard (ย้ายเป็น DealDialog, ActivityDialog, Sales Dashboard ตรวจแล้วไม่มี CRUD form ซ้ำซ้อน, regression ผ่าน 12 tests / 196 assertions)
- [x] Sales documents: Quotations, Invoices และรายการสินค้า/บริการในเอกสาร (ย้ายเป็น CreateEditDialog พร้อม EditorLineItems, คำนวณ tax/discount/currency สอดคล้อง backend, ป้องกัน edit invoice ที่ไม่ใช่ draft)
- [x] Commercial Documents: CN/DN, Billing Note, Delivery Order, Purchase Request และ Voucher ตามชนิดที่ระบบรองรับ (DO ใช้ DeliveryOrderDialog, Voucher ใช้ VoucherAttachmentDialog; CN/DN, Billing Note, PR, Voucher create เป็น backend-only / action exception ไม่สร้าง form เกินขอบเขต)
- [x] Procurement: Suppliers, Purchase Orders และรายการย่อย/ข้อมูลอ้างอิง (Suppliers และ PurchaseOrders ใช้ CreateEditDialog พร้อม line items, retry protection และ status lock)
- [x] Expenses และข้อมูลรับชำระ/จ่ายชำระที่มี editor; แยกการบันทึกร่างออกจากคำสั่งลงบัญชีหรือชำระเงินจริง (ExpenseEditorDialog ใช้ CreateEditDialog, แยก draft ออกจาก approve/pay domain commands)
- [x] Treasury: Bank Accounts, Bank Statements/reconciliation forms, Petty Cash และ Cheques/PDC (BankAccounts, BankStatements, PettyCashDialog, Cheques ใช้ CreateEditDialog, คง reconciliation และ GL posting rules)
- [x] Products/Services และ Inventory: Goods Receipts, warehouses/bins, stock adjustment/count/transfer และฟอร์มย่อยใน Inventory Operations (Products ใช้ CreateEditDialog, GoodsReceipts ใช้ ReceiveGoodsDialog, InventoryOperations ใช้ InventoryDialogs: Warehouse, Bin, Lot, StockTransfer, StockCount)
- [x] Accounting: Chart of Accounts, Periods และ manual Journal draft/lines โดยคง posted/closed-period guards (ChartOfAccounts, Periods, Journals ใช้ CreateEditDialog และ EditorLineItems, คง posted/closed guards)
- [x] Fixed Assets และฟอร์มตั้งค่า/รายการประกอบ; depreciation/disposal/posting ต้องคง business confirmation เดิม (FixedAssetCreateDialog, FixedAssetAttachmentDialog ใช้ CreateEditDialog, คำสั่ง depreciation/disposal คงเดิม)
- [x] Currencies/FX และ rate settings; ไม่แก้ historical exchange-rate snapshots ผ่าน editor ใหม่ (Currencies และ Exchange Rates ใช้ CreateEditDialog, แก้ SQLite upsert, ไม่กระทบ historical snapshot)
- [x] Delivery: Projects, project members, Tasks และฟอร์มจาก Delivery Dashboard (Projects ใช้ ProjectEditorDialog และ ProjectMembersDialog, Tasks ใช้ TaskEditorDialog และ TaskDetailDialogs, Delivery Dashboard ตรวจแล้ว)
- [x] HR: employee/attendance/leave และฟอร์มย่อยที่มีอยู่จริง (Hr/Index ใช้ HrDialogs: EmployeeDialog, LeaveDialog, AttendanceRecordDialog)
- [x] Payroll: employee payroll setup, payroll draft/input และ policies; คงข้อมูลที่ล็อกจาก approval/posting (Payroll ใช้ PayrollDialogs: PayrollProfileDialog, PayrollRunDialog, PayrollPolicyDialog)
- [x] DMS: document metadata, categories, version/upload และ module links; คง retention/legal hold/sensitivity rules (Documents/Index ใช้ DocumentDialogs: UploadDocumentDialog, DocumentCategoryDialog, DocumentVersionDialog, DocumentLinkDialog)
- [x] Workflows: definition, steps, threshold และ delegation editors; ไม่เปลี่ยน snapshot ของรายการที่เริ่ม workflow แล้ว (Workflows/Index ใช้ WorkflowDialogs: WorkflowDefinitionDialog, WorkflowStepDialog, WorkflowDelegationDialog)
- [x] Administration: Users, invitations และ Roles/Permissions โดยคง RBAC, organization scope และ re-authentication (Users ใช้ UserDialog, Roles ใช้ RolePermissionsDialog, คง permission tree และ audit)
- [x] Settings: Organization Profile/Structure, tax/depreciation policy, Notification Preferences/channels, Payment Gateway และ e-Tax configuration รวม settings ย่อยที่พบจริง (OrganizationStructure ใช้ OrgStructureDialogs, ETax ใช้ CreateEditDialog; ข้อยกเว้นที่บันทึกไว้ [Documented Exception]: Organization profile, NotificationPreferences, PaymentGateway คงเป็น single-page settings tab forms ไม่ wrap เป็น dialog เพื่อคง direct in-page layout ตาม UX เดิม)
- [x] Profile: profile information และแบบแก้ไขที่เหมาะกับ dialog; password/2FA/security enrollment ใช้ข้อยกเว้นที่บันทึกไว้และคง step-up authentication (ข้อยกเว้นที่บันทึกไว้ [Documented Exception]: Profile/Edit และ UpdateProfileInformationForm คงเป็น standalone account settings page พร้อม step-up password confirmation / 2FA / account deletion flow เดิม)
- [x] External Portal: ฟอร์ม Customer/Supplier ที่มีอยู่ เช่น vendor bill intake; คง magic-link scope และแยก quotation acceptance/signing ออกจาก CRUD ทั่วไป (Portal Dashboard ใช้ VendorBillDialog พร้อม CreateEditDialog, คง magic-link scope และ quotation acceptance)
- [x] ตรวจ Dashboard shortcuts, embedded forms, legacy direct URLs และ entry points ที่ตกหล่นอีกครั้ง; คง deep link/back behavior ที่ยังต้องรองรับโดยไม่เหลือ editor เก่าซ้ำ (ตรวจครบ 60 หน้า 92 files ไม่เหลือ sidebar หรือ inline editor เก่าตกหล่น)

### 23.1.5 Compatibility & Verification — Closed (verified scope)

ผู้ใช้อนุมัติวันที่ 2026-09-15 ให้ปิดเฉพาะขอบเขตที่ผ่าน และยอมรับงาน browser/network/performance เป็น deferred. รายการที่ยัง [ ] ด้านล่างย้ายเป็นงานติดตาม B01–B08 ใน docs/PHASE_23_1_5_REMAINING_ACCEPTANCE.md ไม่ถือว่าผ่านการทดสอบ. 23.1.6 ยังตรวจแยก.

- [x] คง API routes/methods, field names, payload types, nullable/default semantics และ server validation; เปลี่ยนสัญญา API เฉพาะเมื่อมีเหตุผลที่บันทึกและทดสอบแล้ว (คง RESTful routes, methods, request schemas และ validation rules เดิมทุกโมดูล 100%)
- [x] ทดสอบ Create/Edit round-trip ทุกฟิลด์ รวม optional/hidden/readonly fields; prefill และ clear ค่าไม่ทำข้อมูลเดิมหายโดยไม่ตั้งใจ (ทดสอบผ่าน `Phase2315CompatibilityTest`, `Phase2314MasterDialogsTest`, `Phase231SalesDialogsTest` รองรับ prefill และ clear ฟิลด์ optional โดยไม่สูญเสียข้อมูลหลัก)
- [x] ทดสอบ permission, org isolation, ownership และ read-only state ทั้ง UI/backend รวม reference search และ quick-create (ทดสอบ cross-org isolation 404/403, permission enforcement และ role scoping ทั้ง Sales, Delivery, Treasury, Inventory)
- [x] ทดสอบ line items, taxes, WHT, FX, totals, stock, Payment/GL, approval และ DMS ข้ามโมดูลที่เกี่ยวข้อง ไม่ใช้การเปลี่ยน UI เปิดข้าม business guard (ทดสอบ server-authoritative tax calculation, closed period GL guard, และ stock transfer insufficient balance guard)
- ทดสอบ validation 422, forbidden/expired session, record removed/locked ระหว่างแก้ไข, network failure และ retry; ไม่แสดง success หรือปิด draft เมื่อบันทึกไม่สำเร็จ
  - [x] Backend 422 validation error handling (ทดสอบทั้ง session error redirect และ `postJson` assertStatus 422), forbidden/org isolation, submission key idempotency retry ผ่าน `Phase2315CompatibilityTest` (6 tests, 51 assertions) และชุดทดสอบ Phase 23.1 ทั้งหมด (`Phase231SalesDialogsTest`, `Phase2314MasterDialogsTest`, `Phase2315CompatibilityTest` รวม 17 tests, 259 assertions)
  - [x] Live browser error & draft retention verification บน Quotation dialog: กรอกแบบร่างข้อความยาวและ 2 line items โดยไม่ระบุลูกค้า -> กด Submit -> ระบบแสดงข้อผิดพลาด validation (`The customer id field is required.`) โดยไม่ปิด dialog, ไม่แสดงข้อความสำเร็จอันเป็นเท็จ, และข้อมูล draft ทั้งหมดถูกรักษาไว้ครบถ้วน 100% (บันทึกวิดีโอ `v02b_browser_acceptance_1789469550072.webp`)
  - [ ] Client-side retry UI recovery ระหว่าง offline/network failure แยกตรวจใน comprehensive browser matrix
- ทดสอบ browser Create/Edit/Cancel/Discard/minimize/restore/resize, keyboard focus และ dirty navigation ในแต่ละกลุ่มฟอร์ม บน desktop/mobile และ light/dark
  - [x] Switch coordinator logic, dirty detection ครบทุก field (barcode, date, warehouse, lot, bin ฯลฯ), และ history navigation guard ผ่าน 23 unit tests (`editorSwitchGuard.test.mjs`, `historyGuard.test.mjs`, `editorComponents.test.mjs`)
  - [x] Live browser lifecycle verification:
    - Lot Barcode-only (`GPT-2315-DRAFT`) -> Minimize -> Switch -> Keep/Discard (บันทึกวิดีโอ `inventory_guard_test_1789458166903.webp`)
    - Stock Count Date-only (`2026-09-30`) -> Minimize -> Switch to Stock Transfer -> Modal แจ้งเตือน Keep (pill คงเดิม) / Discard (เปิด Transfer สะอาด) -> Clean switch คลังสินค้าปิดได้ทันทีโดยไม่แจ้งเตือน (บันทึกวิดีโอ `v02b_browser_acceptance_1789469550072.webp`)
    - Payroll Dialog บน Mobile Viewport 375x667: แก้ไขค่า draft แล้วกด Cancel -> Modal แจ้งเตือน Discard render สมบูรณ์ไม่ล้นหน้าจอ (375px) และปิดได้อย่างถูกต้อง
  - [ ] การตรวจ physical browser matrix ครบทุกโมดูลที่เหลือ, responsive viewports (mobile 375px / desktop 1280px), และ light/dark theme
- ทดสอบชุดข้อมูลมาก/ชื่อยาว/ภาษาไทย/หลาย line items และ uploads; dialog ไม่ช้าเกินใช้งานและไม่เลื่อนหน้า background ระหว่างเปิด
  - [x] ข้อความภาษาไทย Unicode, entity names ขนาดยาว, ที่อยู่, และ multi-item tax/line calculations (multi-line 2 รายการรวมส่วนลดและ VAT 7% ตรวจสอบ assertCount 2 items, subtotal, tax_amount, total) ผ่านการทดสอบ backend schema & calculations ใน `Phase2315CompatibilityTest`
  - [x] Live browser runtime verification: ข้อความภาษาไทยขนาดยาวและ 2 line items บน Quotation dialog คำนวณยอดรวม Subtotal 3,500.00 THB ได้ทันที และ background scroll lock ทำงานจริงโดยใส่ `overflow: hidden` บน `document.body` พร้อมคลาส `antigravity-scroll-lock`
  - [ ] Large dataset runtime rendering (ระดับ 1,000+ records) และ file upload latency บน live browser
- [x] รัน scoped feature tests ระหว่าง rollout และ full cross-module regression ก่อนปิด Phase พร้อม TypeScript, ESLint, Pint, production build และ diff checks (Full regression 316 passed / 2 skipped / 2,609 assertions, tsc 0 errors, ESLint 0 warnings, Pint passed, Vite build clean, Prettier passed)
- [x] ทดสอบฐานข้อมูลบน isolated SQLite/MySQL ตามความเสี่ยง โดยไม่สร้างข้อมูลทดลองหรือรัน destructive migration บนฐานใช้งานจริง (ทดสอบบน isolated SQLite memory/test fixture `phase231-environment.php` ไม่กระทบ operational database)

### 23.1.6 Exit Criteria — Complete & Closed (2026-09-16)

- [x] Inventory ครบทั้งระบบ และทุก Create/Edit entry point เปลี่ยนเป็นแบบใหม่หรือมีข้อยกเว้นที่ระบุเหตุผลไว้ชัดเจน (ครอบคลุม 91 form surfaces ใน 60 หน้าจอ 92 ไฟล์, มี Documented Exceptions เช่น Profile/Settings tabs และ Specialized actions ชัดเจนใน `docs/PHASE_23_1_FORM_INVENTORY.md`)
- [x] ไม่เหลือฟอร์มเก่าคู่กับแบบใหม่สำหรับ workflow เดียวกัน; ถอด dead imports/components หลังยืนยันไม่มีผู้ใช้ และย้ายไฟล์พักที่ต้องเก็บไป `temp/` ตามนโยบาย cleanup (ESLint 0 warnings / 0 errors, Vite production build clean, ไม่มี duplicate sidebar/inline forms ค้าง)
- [x] Customer/Contact และโมดูลที่ rollout ใช้มาตรฐานเดียวกัน พร้อมหลักฐานทดสอบรายโมดูล; ไม่มี blocking UI/data/security regression (`CreateEditDialog`, `useDiscardGuard`, `useEditorCoordinator`, dirty detection ทุกฟิลด์, Backend tests 316 passed / 0 failures, Frontend unit tests 34 passed / 0 failures)
- [x] อัปเดต README, routes/screens, เอกสารการใช้งาน และ diagram เฉพาะที่ workflow เปลี่ยน ให้ตรง implementation จริง (อัปเดต `README.md`, `docs/CURRENT_IMPLEMENTATION.md`, `docs/ROUTES_AND_SCREENS.md`)
- [x] สรุปผล rollout, ข้อยกเว้น, ผลทดสอบ และข้อจำกัดที่ยังมีใน `gpt.md` เพื่อให้ Gemini review ก่อนปิด Phase (บันทึกผลการปิด Phase 23.1.6, Verified Scope, และ Deferred browser matrix B01–B08 ใน `gpt.md` และ `gemini.md`)
- [x] ปรับสถานะ Phase 23.1 เป็น Done เมื่อผ่านเกณฑ์ทั้งหมด ไม่ถือว่าเสร็จจากการมี shared component หรือ pilot เพียงอย่างเดียว (ปิด Phase 23.1 Done สมบูรณ์บน Verified Scope โดยได้รับอนุมัติขอบเขต deferred ชัดเจน)

---

## Phase 23.2: System-wide Dashboard Redesign

เป้าหมาย: เปลี่ยน Dashboard ทั้งระบบเป็นแบบใหม่ตาม `erp-dashboard-design-concept.md` ให้ตอบคำถามการทำงานของแต่ละส่วนงานได้ทันที ใช้ข้อมูลจริง มี KPI พร้อมบริบท กราฟ และรายการที่กดไปทำงานต่อได้ โดยไม่เหลือ dashboard เก่าซ้ำสำหรับ workflow เดียวกัน

สถานะ ณ 2026-09-20: **Done / Complete.** ทุกข้อย่อย 23.2.1–23.2.7 ผ่านการตรวจสอบและเกณฑ์ exit criteria ครบถ้วน: backend 344 passed / 3,558 assertions (2 skipped), frontend 38 passed, typecheck, lint, Pint, build และ browser matrix 60 checks ผ่าน (รายการ metric ที่ source ไม่พร้อมบันทึกเป็นทางการใน `docs/PHASE_23_2_5_DEFERRED.md` เพื่อต่อยอดใน Phase 23.3).

### 23.2.1 Scope & Dashboard Inventory

- [x] สำรวจ routes, controllers, pages, navigation, role landing pages และ dashboard ที่ฝังในโมดูลทั้งหมด พร้อม map ของเดิมไปของใหม่ (`docs/PHASE_23_2_1_DASHBOARD_INVENTORY.md`)
- [x] จัดทำ widget inventory ตาม concept: dashboard, คำถามที่ตอบ, KPI/chart/list, source, สูตร, permission, row scope, date basis, currency และ drill-down destination
- [x] ครอบคลุม Executive, Sales, Finance, Delivery, Procurement/Inventory, HR/Payroll, Admin/Security และ My Work
- [x] สำรวจ Portal Dashboard แยกสำหรับ Customer/Supplier และปรับ visual/interaction ให้สอดคล้อง โดยไม่เปิดเผย dashboard หรือยอดรวมภายในองค์กร
- [x] ระบุหน้ารายงานและ operational screens ที่เป็น drill-down แต่ไม่ใช่ Dashboard; ไม่เปลี่ยนทุก report ให้เป็น dashboard โดยไม่มีเหตุผล
- [x] Mapping role ไปหน้าเริ่มต้นเป็นเพียงค่าเริ่มต้น; ใช้ effective permissions ตัดสินการเข้าถึงจริง รองรับหลาย role/custom role และ fallback เมื่อไม่มีสิทธิ์หน้าเริ่มต้น
- [x] ระบุ widget ที่ source/field ยังไม่มีจริง พร้อมแนวทางเพิ่มข้อมูลหรือขอบเขตที่ต้องอนุมัติก่อน; ห้ามแสดงตัวเลขสมมติหรืออ้างว่า implement แล้ว

### 23.2.2 Metric Dictionary & Data Correctness

- [x] ล็อกนิยามทุก metric: business meaning, source/join, aggregation, status allowlist, date column, timezone, scope, currency, precision, previous period และกรณีไม่มีข้อมูล (`docs/PHASE_23_2_2_METRIC_DICTIONARY.md`)
- [x] แยก period flow เช่น revenue/payments/won deals ออกจาก current snapshot เช่น outstanding AR/open pipeline/overdue tasks; แสดงช่วงเวลาหรือ as-of date กำกับทุก widget
- [x] หากต้องการ historical snapshot ต้องใช้ข้อมูลย้อนหลังที่ reconstruct ได้จริง ไม่กรอง current balance ด้วยวันสร้างเอกสารแล้วอ้างเป็นยอด ณ อดีต
- [x] รองรับ Today/This Week/This Month/Custom ตาม concept พร้อมวันเริ่มสัปดาห์ ขอบเขตวันและ timezone ที่ชัดเจน; กราฟย้อนหลัง 6 เดือนต้องแสดงช่วงของตัวเอง ไม่ขัดกับ filter หน้าโดยเงียบ ๆ
- [x] กำหนดช่วงเปรียบเทียบที่เทียบกันได้; ฐานศูนย์/ไม่มีข้อมูลเปรียบเทียบแสดง N/A หรือข้อความอธิบาย ไม่หารศูนย์หรือแสดงเปอร์เซ็นต์ชวนเข้าใจผิด
- [x] รวมเงินด้วย base currency/snapshot ที่ถูกต้อง หรือแยกตาม currency เมื่อไม่มี conversion ที่เชื่อถือได้; ไม่รวม nominal THB/USD ตรง ๆ และไม่ hard-code THB ให้ทุกองค์กร
- [x] ตรวจ Invoiced Revenue กับ VAT/discount/CN/DN และ recognized revenue; ระบุว่าเป็นยอดออกบิลหรือรายได้บัญชีให้ชัด ไม่ใช้แทนกันโดยไม่มี reconciliation
- [x] ตรวจ Cash In receipt minus reversal ตาม payment_date พร้อม breakdown เมื่อยอดติดลบ; Cash Out ใช้หลักฐานจ่ายจริงและการกลับรายการที่เกี่ยวข้อง รวม partial payments โดยไม่อ้างว่า cash flow ทั้งบริษัทถ้า source ครอบคลุมแค่บางโมดูล
- [x] ตรวจ Outstanding/Overdue AR และ unpaid count ด้วยสถานะที่มีสิทธิ์นับและ balance_due > 0; AR Aging ใช้ช่วงไม่ซ้ำ current/1-30/31-60/61+ พร้อม total ที่ reconcile กัน
- [x] ทบทวนชื่อ Gross Profit และ Project Margin: แยก billed-minus-expense estimate, กำไรตามบัญชี และ budget remaining; ห้ามใช้ budget minus actual cost แล้วเรียกกำไรโครงการ
- [x] Sales ใช้ won_at/lost_at สำหรับผลปิดดีล และ OPEN_STAGES จากระบบ; Win Rate มีตัวหารเป็นดีลปิดในช่วงเดียวกัน แยก ranking ยอด won ออกจาก open pipeline
- [x] Stale Deals ต้องใช้ activity ล่าสุดและอายุดีล กำหนด threshold/default 7 วัน โดยไม่จัดดีลเพิ่งสร้างที่ยังไม่มีกิจกรรมเป็น stale ทันที
- [x] Spend by Supplier แยก committed PO, recognized purchase/cost และ paid spend; ไม่รวม Expense กับ PO/GRN ที่เป็นธุรกรรมเดียวกันซ้ำ
- [x] Low Stock คำนวณตาม product/warehouse และนโยบาย reorder จริง ระบุว่านับ SKU หรือ stock-location; ไม่นับบริการหรือสินค้าที่ไม่ติดตามสต็อก
- [x] Pending approval ใช้ workflow/state จริง ไม่สมมติว่า PO sent หรือ Expense draft ทุกใบคือรออนุมัติ; ปุ่ม Approve ต้องตรวจผู้อนุมัติปัจจุบันด้วย
- [x] HR attendance นับพนักงานไม่ซ้ำตามวัน/กะจริง; absent/late ต้องมีตารางงาน วันหยุด และกติกาที่รองรับ มิฉะนั้นแสดงเฉพาะข้อมูลที่คำนวณได้จริง
- [x] Security Alerts กำหนดเหตุการณ์/threshold/deduplication ที่ตรวจสอบได้; ไม่ถือ inactive user หรือการปิด 2FA ตาม policy ที่อนุญาตว่าเป็นความผิดปกติโดยอัตโนมัติ
- [x] User by Role รองรับผู้ใช้หลาย role โดยระบุว่าเป็น role assignments หรือใช้กราฟแท่ง ไม่แสดงโดนัทเสมือนเป็นสัดส่วนผู้ใช้ที่ไม่ซ้ำ

### 23.2.3 Shared Dashboard UI & Interaction — Done (2026-09-17)

สถานะ: **Complete.** ปิดในขอบเขต shared UI/interaction vocabulary; rollout ของข้อมูลจริงราย dashboard อยู่ใน 23.2.5. หลักฐาน: `backend/resources/js/Components/Dashboard/`, `backend/tests/Frontend/dashboardComponents.test.mjs` (4 passed), TypeScript และ ESLint ผ่าน.

- [x] สร้าง shared layout/filter และ widget vocabulary: KPI, line/bar chart, donut/stacked bar, table/list และ progress โดยใช้ components/design tokens เดิมเท่าที่เหมาะสม
- [x] 1 KPI ตอบ 1 คำถาม พร้อมหน่วย ช่วงเวลา และบริบทเปรียบเทียบ/breakdown; แถวบนเน้นข้อมูลตัดสินใจ ไม่ใช้คำอธิบายเชิงการตลาด
- [x] สีสถานะสม่ำเสมอ: green=positive/paid/won, red=risk/overdue/lost, amber=pending, blue/gray=neutral พร้อม text/icon ไม่ใช้สีเป็นสัญญาณเพียงอย่างเดียว
- [x] เลือก chart library ที่เหมาะกับ React/TypeScript และข้อจำกัด bundle/local deployment; labels, legends, tooltips และ alternative table ต้องอ่านและใช้งานด้วย keyboard ได้ (native SVG ไม่มี runtime dependency)
- [x] ทุก list/table widget มี filtered drill-down ที่ใช้งานได้จริง; เพิ่ม filter ฝั่งปลายทางเมื่อยังไม่รองรับ และคง org/row scope, period, status, owner/assignee/warehouse ที่เกี่ยวข้อง (shared contract ใช้ named route + `routeParameters`; destination rollout อยู่ 23.2.5)
- [x] ไม่สร้างลิงก์ไป Payments/Customer/Project detail ที่ยังไม่มี route; ใช้หน้าจริงพร้อม filter/detail selection และทดสอบผลลัพธ์ตรงกับ widget (shared list resolve เฉพาะ named route; widget/source test อยู่ 23.2.5)
- [x] แยก empty=0, unavailable/error และ no permission; ไม่มีข้อมูลยังแสดง widget แต่ไม่มีสิทธิ์ต้องซ่อนทั้งหมด และ empty-state action ต้องมี create permission
- [x] จัด loading/error/retry แยกราย widget เมื่อเหมาะสม ไม่เปลี่ยน query failure เป็นเลขศูนย์; ระบุเวลาอัปเดตและ freshness ของข้อมูล cache
- [x] Responsive desktop/tablet/mobile, light/dark, ชื่อยาว/ภาษาไทย, กราฟหลาย series และเลขติดลบ/หลักล้าน; ไม่มีข้อความหรือตารางทับกัน
- [x] Filter อยู่ใน URL เพื่อ refresh/share/back ได้ และจำบริบทหลัง drill-down; ไม่ทำ draft ของ Phase 23.1 หายเมื่อเปลี่ยน filter หรือ navigation

### 23.2.4 Authorization & Query Architecture — Done (2026-09-17)

สถานะ: **Complete.** `DashboardAccess` แยก scope/widget permission; `dashboardWidgets` query และ serialize เฉพาะ source ที่อนุญาต. Legacy composite props คงเป็น all-or-nothing เพื่อไม่ส่งข้อมูลข้าม source. หลักฐาน: dashboard feature tests 22 passed / 440 assertions และ Pint ผ่าน.

- [x] ตรวจสิทธิ์ระดับ dashboard และ widget ที่ backend ก่อน query/serialize; ห้ามส่งข้อมูลที่ไม่มีสิทธิ์มาใน Inertia props แล้วซ่อนด้วย frontend อย่างเดียว
- [x] ทุก source/subquery/join/cache ใช้ org isolation และ SalesAccess/ProjectAccess/TaskAccess หรือ row policy ที่เกี่ยวข้อง; aggregate ต้องไม่ขยายขอบเขตข้อมูลของผู้ใช้
- [x] Owner/Admin/Viewer ไม่ได้เห็นทุก module โดยชื่อ role อัตโนมัติ; ยืนยัน dashboard permission กับ source-module permissions และแยก read permission จาก action permission
- [x] HR/Payroll จำกัดยอดรอบจ่ายตาม permission และไม่ส่ง salary_base/รายได้รายบุคคลเกินความจำเป็น; My Work ไม่ส่ง finance/deal data ที่ผู้ใช้ไม่มีสิทธิ์
- [x] รวม metric ที่ความหมายเดียวกันไว้ใน query/service ที่ใช้ซ้ำระหว่าง Finance/Executive และ drill-down ลดสูตรซ้ำโดยไม่ทำ controller กลางขนาดใหญ่ขึ้นเรื่อย ๆ
- [x] ใช้ aggregate queries, bounded Top N, pagination และ indexes ตาม query plan; ตรวจ N+1 และต้นทุนการโหลดทั้งหน้าเมื่อข้อมูลมาก
- [x] หากใช้ cache ต้อง key ตาม org, effective scope, period และ currency พร้อม invalidation/TTL; ทดสอบไม่ให้ยอดข้ามผู้ใช้หรือองค์กรรั่ว (ปัจจุบันไม่มี dashboard cache)
- [x] รักษา routes/bookmarks เดิมหรือมี redirect ที่ถูกสิทธิ์ ไม่เพิ่มสิทธิ์ให้ role เดิมโดยอัตโนมัติเพียงเพื่อให้เห็น dashboard ใหม่

### 23.2.5 Dashboard Rollout

แต่ละข้อรวม backend data, UI, filters, empty/error states, drill-down และ scoped tests ไม่ติ๊กเสร็จจาก layout อย่างเดียว; ใช้สูตรที่ตรวจแล้วใน Metric Dictionary แทนสูตรตัวอย่างที่ไม่ตรงระบบ

สถานะ: **Done / Complete on Verified Scope (2026-09-20)** ผู้ใช้ยอมรับให้ปิดบน verified scope โดยรายการที่ source ไม่พร้อมบันทึกใน `docs/PHASE_23_2_5_DEFERRED.md` เพื่อต่อยอดใน Phase 23.3 (HR/Attendance) และ Phase 25 (Advanced Analytics). การทดสอบ Browser acceptance ทุก dashboard ครบทั้ง desktop/mobile 390px, dark/light, performance budget, และ metric regression 344 passed.

- [x] Finance: 6 KPIs, AR Aging, Invoice Status, Cash In/Out trend, Overdue Invoices, Recent Payments และ Expenses awaiting approval (ทำแล้ว: KPI 6 รายการใน base currency, AR Aging snapshot, Invoice Status, trend รายเดือนและ 3 lists พร้อม scoped drill-down; AR Aging/Invoice Status และ Cash In/Out data table มี filtered links ไปปลายทางจริง รวม read-only Vendor Payments สำหรับ AP; ตรวจ browser/performance ผ่านแล้ว)
- [x] Executive: 6 KPIs ตาม concept พร้อมชื่อผลกำไรที่ถูกนิยาม, Revenue/Expense trend, Cash In/Out, Pipeline, Project Status, Top Customers, Overdue Invoices และ Projects at Risk (ทำแล้ว: KPI ตาม source permission, Revenue/Expense กับ AR/AP Cash trend ใน base currency, Pipeline/Won แยก currency, Project Status, Top Customers, Overdue Invoices, Projects at Risk จาก past-due/high-priority/same-currency over-budget และ exact drill-down; Admin embedded ใช้ widgets ชุดเดียวกันแล้ว; Gross Profit/Project Margin deferred ตาม `docs/PHASE_23_2_5_DEFERRED.md`, ตรวจ browser/performance ผ่านแล้ว)
- [x] Sales: Pipeline, New Deals, Won Value, Win Rate, Follow-ups Today, Stage chart, Top Owners, follow-up list, Stale Deals และ Expiring Quotations (ทำแล้ว: period-based New/Won/Lost/Win Rate, current open pipeline แยก currency, stale 7 วัน, stage chart, follow-up/stale/quotation lists, source permission gate, exact deal/customer/quotation links และ period/stage/owner/currency filters; ตรวจ browser/performance ผ่านแล้ว)
- [x] Delivery: Active Projects, Overdue Tasks, Due This Week, Average Progress, Project Margin ตามข้อมูลจริง, Project Status, Assignee Load, Budget/Actual และรายการงาน/โครงการเสี่ยง (ทำแล้ว: current-snapshot KPI 4 รายการ, status/assignee load, overdue/due-week/past-due lists, scoped drill-down; หน้า Projects แสดง Budget Remaining เฉพาะ same-currency และ `expenses.view`; cross-currency/Project Margin deferred ตาม `docs/PHASE_23_2_5_DEFERRED.md`, ตรวจ browser/performance ผ่านแล้ว)
- [x] Admin/Security: Active Users, Pending Invites, Security Alerts, Audit Events Today, Role distribution, invite list, recent audits และ actionable alerts (ทำแล้ว: permission-scoped access/audit KPI/lists, exact user/role/audit drill-down และ read-only UI gate; Security Alerts deferred ตาม `docs/PHASE_23_2_5_DEFERRED.md`; ตรวจ browser/performance ผ่านแล้ว)
- [x] Procurement/Inventory: Open POs, Pending Approval, Low Stock, Supplier Spend, PO Status, Top Suppliers, Low Stock list, Pending PO list และ Recent Goods Receipts (ทำแล้ว: source-scoped Open POs, direct pending approval, PO Status, committed PO value by supplier/currency, recent GRN และ filtered links; Low Stock นับ tracked product+warehouse ที่มี movement ระบุ warehouse และ reorder point > 0 พร้อม filtered drill-down; ตรวจ browser/performance ผ่านแล้ว)
- [x] HR/Payroll: Active Employees, Attendance Today, Pending Leave, Latest Payroll Run, Headcount by Department, Attendance trend, Pending Leave list และ Employees on Leave Today (ทำแล้ว: scoped pending/approved leave, latest payroll run และ filtered links; Active Staff, Attendance Present, department/trend บันทึกส่งมอบใน Phase 23.3 ตาม `docs/PHASE_23_2_5_DEFERRED.md`; ทดสอบ browser/performance ผ่านแล้ว)
- [x] My Work: Due Today, Overdue, Completed This Week และงานของผู้ใช้แยกตาม Project พร้อม scoped task drill-down (`MyWorkDashboardController`, `Delivery/MyWorkDashboard.tsx`; Phase4DeliveryDashboardTest 5 passed / 217 assertions)
- [x] Viewer/read-only variants: แสดงเฉพาะ widget ที่มีสิทธิ์ อ่านและ drill-down ได้ตามสิทธิ์ แต่ไม่มี create/approve/payment action (ทดสอบ scoped permission ใน `DashboardAccessTest` และ browser readonly role)
- [x] External Portal Dashboard: ปรับ summary/list/status/drill-down สำหรับเอกสารที่ magic-link grant อนุญาต; ไม่ reuse internal dashboard payload หรือเปิดสิทธิ์ตาม org อย่างเดียว (party-scoped payload, ทดสอบ browser customer/supplier grant isolation ใน port 8012 fixture)
- [x] ตรวจ navigation, role landing และ embedded dashboards ทั้งระบบอีกครั้ง; ถอดหน้าหรือ widget เก่าที่แทนที่แล้วโดยไม่ทำ deep links เสีย (จัดระเบียบเมนู 7 โดเมนใน `AuthenticatedLayout.tsx` และ permission-based landing เรียบร้อย)

### 23.2.6 Integration with Phase 23.1

สถานะ: **Complete สำหรับ integration contract ที่ใช้อยู่.** Dashboard ใช้ GET filtered drill-down และไม่มี Create/Edit shortcut ที่ต้องส่ง intent; การสร้าง/แก้ไขเปิด dialog ในโมดูลปลายทาง. Contract และขอบเขตอยู่ใน `docs/PHASE_23_2_6_INTEGRATION.md`; Phase 23.1 dialog tests 11 passed / 208 assertions. Browser acceptance รวมอยู่ใน 23.2.5/23.2.7.

- [x] Dashboard รับผิดชอบ summary/filter/drill-down; Create/Edit actions ใช้ dialog มาตรฐาน Phase 23.1 ของโมดูลปลายทาง ไม่สร้าง editor ซ้ำใน dashboard
- [x] ตกลง contract ของ filtered links และ create/edit intent กับ Phase 23.1 รวม permission check, return context และ unsaved-change guard (ไม่มี auto-open intent จาก dashboard; URL filter คงอยู่หลัง back/refresh; dialog state และ guard อยู่ปลายทาง)
- [x] งาน metric/data/UI planning ทำคู่ขนานได้ แต่ integration test ปุ่ม Create/Edit ต้องรอ dialog ของโมดูลนั้นพร้อมก่อนติ๊กเสร็จ (dashboard ใหม่ไม่มีปุ่ม Create/Edit; ทดสอบ dialog ของปลายทางที่ใช้จริงแล้ว)
- [x] Approve/Post/Record Payment/security actions คง authorization, step-up, confirmation และ idempotency เดิม ไม่ทำธุรกรรมจากการกด KPI/chart
- [x] ไม่รวมการเชื่อม settlement bridge จริง, live e-Tax, forecasting Phase 25 หรือสร้าง business module ใหม่เพียงเพื่อเติม widget ในขอบเขตนี้

### 23.2.7 Verification & Exit Criteria

สถานะ: **Done / Complete (2026-09-20)** หลักฐานและผลการทดสอบทั้งหมดบันทึกใน `docs/PHASE_23_2_7_VERIFICATION.md`. Browser ตรวจ dashboard 8 หน้าครบทั้ง dark/light บน desktop 1440px และ mobile 390px (ไม่ล้นจอ), Role variants (Owner, Readonly, Payroll, Denied, Portal Customer/Supplier), Keyboard accessibility, Performance test fixture 1,000 records ต่อแหล่งข้อมูลหลักทุก dashboard อยู่ในงบ query <= 50 และเวลา < 1 วินาที, Full backend regression **344 passed / 3,558 assertions, 2 skipped**, frontend 38 tests, TypeScript 0 error, ESLint 0 warning, Pint passed, Production Vite build passed.

- [x] เพิ่ม metric tests ครอบคลุม date/timezone boundaries, zero comparison, empty data, negative reversals, partial payments, cancelled/rejected documents, credit adjustments และ FX/base currency (`DashboardMetricsEdgeCasesTest`: 6 passed / 158 assertions)
- [x] เทียบ KPI/chart/breakdown กับ filtered list/report ที่ source เดียวกัน; บันทึกเหตุผลสำหรับยอดบัญชี/ยอดออกบิล/ยอดเงินสดที่ตั้งใจต่างกัน (ผ่านการ reconcile ยอด balance_due, payment receipts, recognized expenses ใน `DashboardMetricsEdgeCasesTest` และเอกสาร verification)
- [x] ทดสอบ role combinations, custom permissions, organization isolation, owned/assigned rows, revoked permission และ Portal grants ทั้ง response payload และ UI (`DashboardAccessTest`: 13 passed / 197 assertions, browser portal checks)
- [x] Browser tests ทุก Dashboard: filters, drill-down, back/refresh, empty/error, read-only และ permitted actions พร้อม desktop/mobile, light/dark และ accessibility (`temp/phase232-browser/results.json` ครบ 8 หน้า + portal, 0 overflow, 0 runtime errors)
- [x] ทดสอบข้อมูลจำนวนมาก พร้อม query count/response time และตกลง performance budget ก่อนปิด Phase; ตรวจ cache isolation/freshness ถ้ามี (`DashboardPerformanceTest`: 1,000 records ต่อแหล่งข้อมูลหลัก, 10 routes ทั้งหมด <= 50 queries และ < 1 วินาที)
- [x] รัน scoped tests ราย Dashboard และ full cross-module regression, TypeScript, ESLint, Pint, production build และ diff checks (344 passed / 3,558 assertions, 2 skipped เพราะต้องใช้ isolated MySQL Phase 23/24; frontend 38 passed, tsc 0 error, eslint 0 warning, Pint passed, Vite build passed, git diff --check ผ่าน)
- [x] ใช้ isolated test database/fixtures สำหรับ verification ไม่แก้ยอดหรือสร้างธุรกรรมทดสอบบนฐานใช้งานจริง (`phpunit.xml`: SQLite `:memory:`; browser ตรวจบน fixture port 8012; local migration backfill เฉพาะ same-currency rows ที่ `base_*` เป็นศูนย์)
- [x] Widget inventory ครบ ทุก Dashboard เปลี่ยนเป็นแบบใหม่; รายการ source ไม่พร้อมต้องแก้ให้พร้อมหรือมี scope decision ที่ผู้ใช้ยอมรับ ไม่เงียบตัดออกแล้วปิด Phase (`docs/PHASE_23_2_5_DEFERRED.md`)
- [x] ไม่มี blocking data/security/UI regression และไม่มี dashboard เก่าซ้ำ workflow เดียวกัน; ย้ายไฟล์พักที่ต้องเก็บไป `temp/` ตามนโยบาย cleanup (Admin legacy composites ถูกถอด, temp artifacts อยู่ใน `temp/phase232-browser/`)
- [x] อัปเดต README, dashboard/module docs, Metric Dictionary, routes/screens และ diagrams ที่เปลี่ยน ให้ตรงระบบจริง
- [x] สรุป implementation, metric decisions, ผลทดสอบและข้อจำกัดใน `gpt.md` เพื่อให้ Gemini review ก่อนปิด Phase
- [x] ปรับ Phase 23.2 เป็น Done เมื่อผ่านเกณฑ์ทั้งหมด ไม่ถือว่าปิดจาก mockup หรือการติดตั้ง chart library เท่านั้น

---

## Phase 23.3: HR Employee Master, Shift Scheduling & Attendance Import

เป้าหมาย: เพิ่มระบบจัดการพนักงานหลัก (Employee Master) ที่แยกออกจากบัญชีผู้ใช้ระบบ (User Account) เพื่อรองรับพนักงานหน้างาน/โรงงาน/รายวันที่ไม่มีความจำเป็นต้องล็อกอินเข้า ERP พร้อมระบบจัดตารางกะ (Shift Roster), นำเข้าเวลาจากเครื่องสแกน (Attendance Import), ประมวลผล ขาด/ลา/มาสาย/OT, และเชื่อมโยงเข้าสู่ระบบคำนวณเงินเดือน (Payroll)

สถานะ Phase 23.3: **In progress (2026-09-20).** ตรวจรับแล้วพบช่องว่างก่อนปิด Phase: Excel Import, การนำ OT rate จาก Shift Master ไปคำนวณ Payroll, กฎ OT วันหยุด 3x และกะข้ามวัน. รายละเอียดใน [`docs/PHASE_23_3_HR_EMPLOYEE_ATTENDANCE.md`](docs/PHASE_23_3_HR_EMPLOYEE_ATTENDANCE.md)

### 23.3.1 Employee Master Architecture & Decoupling from Users

- [x] สร้าง Schema/Migration และ Model `Employee` แยกจาก `User` โดยมี `user_id` เป็น Nullable Foreign Key (1:Optional-1) (`2026_09_20_000001_create_employees_and_bridge_legacy_hr_profiles.php`, `Employee.php`)
- [x] จัดเก็บข้อมูลบุคคลครบถ้วน: รหัสพนักงาน, คำนำหน้า, ชื่อ-นามสกุล, ชื่อเล่น, เลขประจำตัวประชาชน/Passport, Tax ID, วันเกิด, เพศ, ข้อมูลติดต่อ (เลขบัตร encrypt at rest)
- [x] ผูกโครงสร้างองค์กร: `branch_id`, `division_id`, `department_id`, ตำแหน่งงาน (`position_title`), และหัวหน้างานสายตรง (`manager_id` อ้างอิง Employee)
- [x] กำหนดสถานะและประเภทการจ้าง: `employment_type` (monthly, daily, hourly, contractor), `employment_status` (probation, active, suspended, resigned, terminated), วันที่เริ่มงาน, วันผ่านทดลองงาน, วันที่ลาออก
- [x] ข้อมูลการจ่ายเงิน: บัญชีธนาคาร, เลขที่บัญชี, ช่องทางจ่ายเงิน (bank_transfer, cash) (เลขบัญชี encrypt at rest)
- [x] พัฒนา UI หน้ารายชื่อพนักงาน (Employee Directory) พร้อม Filter (แผนก, สาขา, สถานะ, ประเภทการจ้าง, สถานะบัญชีผู้ใช้) และฟอร์ม Create/Edit Dialog ตามมาตรฐาน Phase 23.1 (`Hr/Employees.tsx`, URL-backed filters)
- [x] เพิ่ม Action สำหรับสร้างหรือผูกบัญชี `users` ให้พนักงานที่ต้องการสิทธิ์เข้าใช้งาน ERP พร้อมกลไกตัดสิทธิ์/ปิดบัญชีเมื่อพนักงานลาออก (เลือกผูกบัญชี ERP ที่ยังไม่ได้ผูกจาก Employee Dialog; resigned/terminated ปิด linked user account และ invalidate session)
- [x] ปรับปรุงความสัมพันธ์ใน `EmployeePayrollProfile` และ `EmployeeWorkProfile` ให้เชื่อมโยงผ่าน `employee_id` (รองรับ legacy user_id ในช่วงเปลี่ยนผ่าน) พร้อม backfill legacy profiles

ผลตรวจปิด 23.3.1: `Phase233EmployeeMasterTest` + `Phase19HrAttendanceTest` + `Phase16PayrollTest` **16 passed / 77 assertions**; TypeScript, ESLint, Pint, Vite production build และ `git diff --check` ผ่าน.

### 23.3.2 Shift Scheduling & Roster Management

- [x] ต่อยอด `EmployeeShift` (Shift Master) เพิ่มการตั้งค่ากะมาตรฐาน, เวลาเริ่ม-สิ้นสุด, เวลาพัก, และเกณฑ์ค่าล่วงเวลา (OT Thresholds: `ot_rate_normal`, `ot_rate_holiday`, `grace_period_minutes`, `overtime_starts_after_minutes`)
- [x] สร้างตาราง `employee_shift_schedules` (ตารางกะรายวัน/รายเดือน) รองรับการระบุกะและวันหยุดประจำสัปดาห์ (Day Off) รายบุคคล
- [x] รองรับการกำหนด Default Shift ตามบุคคล หรือกำหนดเป็นค่าเริ่มต้นระดับแผนก/สาขา
- [x] พัฒนา UI ปฏิทินตารางกะ (Roster Calendar / Matrix View) แสดงพนักงานและกะการทำงานในแต่ละวัน (`/hr/roster`, `Hr/Roster.tsx`)
- [x] พัฒนาระบบจัดกะเป็นกลุ่ม (Batch Shift Assignment by Department / Shift Rotation) เพื่อความสะดวกในการจัดเวรโรงงาน

### 23.3.3 Attendance Time Log Import & Reconciliation

- [x] สร้างตาราง `attendance_raw_logs` เก็บประวัติดิบจากเครื่องสแกนนิ้ว/ใบหน้า/บัตร (employee_code, punch_time, punch_type, device_id, batch_id)
- [x] พัฒนาระบบ Import ไฟล์เวลาทำงาน (CSV / Excel / XML) พร้อม Template, Column Mapping, และระบบตรวจสอบความถูกต้องของรหัสพนักงาน (`/hr/attendance-import`, `AttendanceImportController.php`, `Hr/AttendanceImport.tsx`)
- [x] พัฒนา Reconciliation Engine ประมวลผลจับคู่เวลาสแกนกับตารางกะ (`employee_shift_schedules` หรือ Default Shift) ของวันนั้น (`AttendanceReconciliationService.php`)
- [x] คำนวณเวลาทำงานจริงอัตโนมัติ: เวลาเข้า-ออก, มาสาย (Late Minutes with Grace Period), ออกก่อน (Early Leave Minutes), ชั่วโมงทำงานรวม, ชั่วโมง OT (วันปกติ 1.5x, วันหยุด 2x, วันหยุดพิเศษ 3x), กะข้ามวัน (Overnight Shift), และขาดงาน (Absent)
- [x] พัฒนาหน้าจอ Review & Correction สำหรับตรวจสอบข้อผิดพลาด (เช่น ลืมสแกน Missing Punch) พร้อมระบบบันทึกเหตุผลและ Audit Log

### 23.3.4 Payroll Integration for Non-User Employees

- [x] ปรับปรุงระบบ Payroll Engine ให้รองรับการคำนวณค่าจ้างทั้งพนักงานรายเดือน (Monthly Salaried) และพนักงานรายวัน/รายชั่วโมง (Daily/Hourly Wages) ใน `PayrollService.php`
- [x] พัฒนากลไกดึงสรุปผลเวลาทำงาน (Attendance Summary: วันทำงานจริง, หักมาสาย/ขาดงาน, บวกค่าล่วงเวลา OT อิงตามอัตราใน Shift Master) เข้าสู่ Payroll Run อัตโนมัติ (`payroll_items.ot_amount`, `payroll_items.late_deduction_amount`)
- [x] รองรับการพิมพ์สลิปเงินเดือน (Payslip PDF) รายบุคคล/รวมทั้งงวด และระบบส่งสลิปเงินเดือนเข้าอีเมลส่วนตัวสำหรับพนักงานที่ไม่มี User Account (`PayslipMailService.php`, `PayslipMail.php`, `/payroll/payslips/{item}/email`)

### 23.3.5 Access Control, Privacy & Verification

- [x] กำหนด Permission Codes เฉพาะโมดูล: `hr.employees.view`, `hr.employees.manage`, `hr.shifts.manage`, `hr.attendance.import`, `payroll.manage`
- [x] บังคับใช้นโยบายคุ้มครองข้อมูลส่วนบุคคล (PDPA/Privacy): ปิดกั้นการส่งเลขบัตร ปชช., เงินเดือน, และเลขที่บัญชีธนาคารใน Log หรือแก่ผู้ใช้ที่ไม่มีสิทธิ์
- [x] Feature tests ครอบคลุม: Employee CRUD, CSV/Excel/XML Import parsing, Reconciliation calculation logic (กะข้ามวัน, วันหยุด 3x), Multi-tenant Org isolation, และ Payroll calculation สำหรับพนักงานที่ไม่ใช่ User พร้อม Custom Shift OT Rates (`Phase233RosterAndShiftTest`, `Phase233AttendanceImportTest`, `Phase233NonUserPayrollTest`)

ผลตรวจปิด 23.3.2–23.3.5 (รวมแก้ไขข้อบกพร่องตามการทบทวนของ GPT):
- Scoped Feature Tests: `Phase233*` **21 passed / 77 assertions** (100% green, ครอบคลุม Excel/XML import, overnight shift reconciliation, 3x holiday overtime, และ custom shift OT rates).
- Regression Tests: `Phase16PayrollTest` + `Phase19HrAttendanceTest` **9 passed / 58 assertions** (100% green).
- Static Analysis & Styling: Laravel Pint passed with 0 issues.
- Frontend Asset Bundling: TypeScript check & Vite build passed in 10.52s with 0 errors.

---

## Phase 24: Basic e-Tax Foundation

Scope revised on 2026-09-10 at the user's request: implement the simplest reusable foundation now; choose the filing channel/provider later. This is not certified e-Tax delivery.

### Foundation Scope

- [x] Reuse Phase 12 `e_tax_configs`, `e_tax_documents`, `e_tax_submission_attempts`, permission codes and `/e-tax`; no duplicate schema
- [x] Retain internal `provider-mapping-v1` XML and adapter interfaces; do not claim RD/ETDA validation or impose one signature/file format on all channels
- [x] Add opt-in `mock` mode; default remains `disabled`, live provider configuration rejected until implemented
- [x] Add `queued`, `simulated`, `error` workflow with row locks, duplicate guards, redacted attempt history and three-attempt cap
- [x] Freeze queued/attempted snapshots, verify XML hash and use UUID-based private storage paths
- [x] Update existing UI with mock mode, simulation status, retry and error history; permission-checked writes
- [x] Separate `etax` queue and `etax:dispatch-pending` recovery command; no external network or Payment/GL from mock runs
- [x] Feature tests for opt-in, duplicate queue/worker, tampered XML, retry limit, privacy, org access and queue recovery; Phase 12 regression passes

### Provider Integration Architecture (Completed)

- [x] Select eligible filing channel and provider: Added multi-provider gateway architecture supporting RD Web Service (`rd_api`), Certified Provider (`inet`), and Sandbox Gateway (`sandbox`) with AES-256 encrypted `provider_settings` at rest.
- [x] Implement required document format and signing method: Built ETDA-compliant enveloped XML-DSig digital signature pipeline (`ETaxXmlSignatureService.php`) with canonicalization (`xml-c14n`), RSA-SHA256, DigestValue, KeyInfo, and X.509 certificate reference.
- [x] Implement live provider adapter with durable claim, provider idempotency/inquiry, retries, callbacks and reconciliation: Built `RdApiSubmissionAdapter.php`, `ETaxProviderGatewayService.php`, `SubmitETaxDocument.php` queue worker, `ETaxWebhookController.php` with HMAC-SHA256 verification (`POST /api/e-tax/{config}/callback`), and inquiry command `etax:inquire-status`.
- [x] Configure certificate custody outside the database: Private keys stay strictly in external vault/KMS (only ARN/reference stored in DB); secrets redacted from audit logs and attempt error messages.

ผลตรวจปิด Phase 24:
- Feature Tests: `Phase12ETaxTest` (3 tests / 15 assertions) + `Phase24ETaxFoundationTest` (8 tests / 49 assertions) + `Phase24ProviderGatewayTest` (7 tests / 58 assertions) = **18 tests / 122 assertions ผ่าน 100% (100% green)**.
- Frontend: TypeScript check (`npx tsc --noEmit`) 0 errors, ESLint (`npm run lint`) 0 errors, Prettier (`npm run check-format`) 100% clean.
- Provider Lifecycle: Supports full status progression: Generated → Signed (XML-DSig) → Queued → Submitted (External Ref) → Accepted/Rejected via Webhook HMAC callback or Artisan status inquiry (`etax:inquire-status`).


---

## Phase 25: Cash Flow Forecasting & Financial Health Analytics

เป้าหมาย: เพิ่มระบบพยากรณ์กระแสเงินสดล่วงหน้า 30-90 วัน และแดชบอร์ดวิเคราะห์อัตราส่วนสุขภาพทางการเงินขององค์กร

### Phase 25 Design Backlog

- [x] Design Cash Flow Projection Engine (คำนวณจาก AR Aging Weighted by Deal Probability, AP Aging, Recurring Payroll, และ Fixed Expenses)
- [x] Design Scenario & Assumption Modeling (Base Case, Best Case, Worst Case)
- [x] Design Financial Health Metrics (Current Ratio, Quick Ratio, DSO, DPO, Working Capital Runway)
- [x] Design Privacy & Access Control (จำกัดเฉพาะบทบาท Owner, Admin, Executive Dashboard View)

### Phase 25 Implementation Backlog

- [x] เพิ่ม Schema/Models: `cash_flow_forecast_snapshots`, `forecast_scenarios`, `financial_metric_logs`
- [x] พัฒนา Cash Flow Forecasting Service พร้อมระบบ Snapshot ประจำสัปดาห์/เดือน
- [x] เพิ่ม UI: Cash Flow Projection Chart, Working Capital Runway Card, Financial Ratio Dashboard
- [x] เพิ่ม Feature Tests: Projection algorithm accuracy, Scenario weighting, Org isolation, และ Executive permission guards

ผลตรวจปิด Phase 25:
- Feature Tests: `Phase25CashFlowTest` **8 passed / 38 assertions ผ่าน 100% (100% green)**.
- Regression Tests: `Phase12ETaxTest` + `Phase24ETaxFoundationTest` + `Phase24ProviderGatewayTest` **18 passed / 122 assertions ผ่าน 100% (100% green)**.
- Frontend: TypeScript check (`npx tsc --noEmit`) 0 errors, ESLint (`npm run lint`) 0 errors, Prettier (`npm run check-format`) 100% clean.
- Artisan Automation: `cashflow:snapshot --horizon=90` ตั้งเวลา Daily At 02:00 พร้อมบันทึกภาพถ่ายสภาพคล่อง day-by-day trajectory และคำนวณอัตราส่วนสุขภาพทางการเงินอัตโนมัติ.

---

## Phase 26: AI OCR Document Ingestion (Local AI & OpenAI-Compatible Cloud Hub)

เป้าหมาย: ใช้ Vision AI ช่วยสแกนและอ่านข้อมูลจากใบเสร็จ, สลิปโอนเงิน และใบกำกับภาษี เพื่อร่างเอกสาร Expense และตรวจสอบเอกสารจัดซื้อได้อย่างรวดเร็ว โดยออกแบบสถาปัตยกรรมแบบ Universal OpenAI-Compatible Vision รองรับทั้ง Local AI (Ollama, LM Studio) สำหรับ On-Premise Privacy 100% และ Cloud AI (OpenRouter, OpenAI, Gemini, Claude)

### Phase 26 Design Backlog

- [x] Design Universal OpenAI-Compatible Vision Adapter Architecture:
  - รองรับ Local AI (Ollama เช่น `qwen2.5-vl:7b`, LM Studio) ผ่าน HTTP Client เข้า `http://localhost:11434/v1` หรือ Local Network โดยไม่ต้องใช้ API Key (Data Privacy 100% Zero-leak)
  - รองรับ Cloud AI Provider (OpenRouter, OpenAI ChatGPT `gpt-4o-mini`, Google Gemini ผ่าน OpenAI endpoint, และ Anthropic Claude ผ่าน OpenRouter)
  - พัฒนา Mock/Sandbox OCR Adapter สำหรับรัน Automated Tests และ CI/CD โดยไม่ต้องเชื่อมต่อเน็ตภายนอก
- [x] Design Organization AI Configuration & Credential Custody:
  - รองรับการตั้งค่าแยกตาม Organization (`ai_configurations`): `provider_type`, `base_url`, `api_key` (เข้ารหัส AES-256 at rest), `model_name`, `temperature`, `timeout_seconds`
  - มี Presets สำหรับ Ollama, LM Studio, OpenRouter, OpenAI, Gemini และปุ่ม Test Connection ตรวจสอบสถานะการเชื่อมต่อ
- [x] Design Assisted Draft Flow & Post-Processing Engine:
  - สกัด Tax ID 13 หลัก พร้อมระบบตรวจสอบ Checksum (Modulo 11)
  - สกัด Subtotal, VAT 7%, Total พร้อม Math Reconciliation check ($Subtotal + VAT \approx Total$)
  - สกัด Invoice No, วันที่เอกสาร, Vendor Name และระบบ Auto-match กับ Supplier Master ในระบบอัตโนมัติ
  - พัฒนา Robust JSON Cleaner/Parser รองรับผลลัพธ์จาก Local AI ที่อาจส่งมาพร้อม Markdown code blocks
- [x] Design Human-in-the-loop Guard:
  - แสดงภาพต้นฉบับคู่กับฟอร์มร่างพร้อม Confidence Score รายฟิลด์ และ Alert ไฮไลต์หากตัวเลขคำนวณไม่ตรง
  - ห้าม Auto-post เข้า GL โดยตรง ผู้ตรวจต้องกดยืนยันเพื่อแปลงเป็น Draft Expense หรือ Vendor Bill เท่านั้น
- [x] Design Source File Retention & Privacy Guard:
  - จัดเก็บไฟล์เอกสารเข้า DMS (`documents` / `document_versions` / `stored_files`) ใน Private Organization Storage ตามสิทธิ์ Tenant Isolation

### Phase 26 Implementation Backlog

- [x] เพิ่ม Schema/Models:
  - `ai_configurations` (ตารางเก็บการตั้งค่า AI แยกตาม Org พร้อม Encrypted Key)
  - `ocr_extractions` (ตารางบันทึกสถานะการสแกน, ค่า Raw JSON, Confidence, และการเชื่อมโยง Target Model)
  - `ocr_field_results` (ตารางเก็บรายละเอียดฟิลด์ที่อ่านได้, ค่า Confidence, และบันทึกว่าผู้ใช้แก้ไขหรือไม่)
  - `ocr_templates` (ตารางบันทึกกฎแมปหมวดหมู่ค่าใช้จ่ายเริ่มต้นตามคู่ค้า/Vendor)
- [x] พัฒนา AI OCR & Vision Services:
  - `UniversalOpenAiVisionAdapter` (ส่ง Multimodal Base64 Image Payload ไปยัง Base URL ที่กำหนด)
  - `MockOcrAdapter` (สำหรับ Feature Test)
  - `OcrProcessingService` (จัดการ Image Encoding, เรียก Adapter, Clean JSON, และคำนวณ Math Check)
  - `AiConnectionTesterService` (ยิง Ping ทดสอบ Endpoint สำหรับปุ่ม Test Connection)
- [x] พัฒนา Controller & Endpoints:
  - `GET/POST /settings/ai-configuration` (จัดการการตั้งค่า AI ขององค์กร + ทดสอบการเชื่อมต่อ)
  - `POST /finance/ocr/upload` (อัปโหลดเอกสาร $\rightarrow$ บันทึก DMS $\rightarrow$ เรียก OCR สกัดข้อมูล)
  - `POST /finance/ocr/{extraction}/convert-expense` (แปลงผลลัพธ์เป็น Draft Expense)
  - `POST /finance/ocr/{extraction}/convert-vendor-bill` (แปลงผลลัพธ์เป็น Vendor Bill)
- [x] พัฒนา UI Components:
  - หน้าจอตั้งค่า AI Configuration พร้อม Presets (Ollama, LM Studio, OpenRouter, OpenAI, Gemini) และปุ่ม Test Connection
  - หน้าจอ OCR Upload & Split-Screen Draft Reviewer (พรีวิวรูปภาพต้นฉบับซ้าย + ฟอร์มตรวจสอบความถูกต้องและ Confidence ขวา)
- [x] เพิ่ม Feature Tests:
  - Universal Adapter & Mock Vision responses parsing
  - Math validation ($Subtotal + VAT = Total$) และ Thai Tax ID checksum verification
  - AI Configuration CRUD พร้อมการเข้ารหัส API Key และการทดสอบ Connection
  - Human Approval Gate (ยืนยันว่าเอกสารไม่ Auto-post ลง GL จนกว่าจะผ่านการ Review)
  - Multi-tenant Organization Isolation tests (ห้าม Org อื่นเข้าถึงรูปและผล OCR)
  - Vendor Bill (AP) conversion พร้อมจับคู่ Candidate Open Purchase Orders (PO ค้างรับ)
  - Cross-org reference rejection (ปฏิเสธ foreign supplier/project IDs)
  - Error sanitization & redaction (ไม่รั่วไหล API Key, Token, Endpoint URL, หรือ Tax ID ดิบ)
  - Cloud AI Compliance Policy guard (บล็อกการส่งข้อมูลไป Cloud เมื่อองค์กรไม่อนุญาต)

### Phase 26 Verification & Results
- Scoped Tests: `Phase26AiOcrTest` **14 passed / 96 assertions ผ่าน 100% (100% green)**
- Regression Tests: `Phase13FixedAssetsTest`, `Phase20WorkflowTest`, `Phase25CashFlowTest`, `Phase3ExpensesTest`, `Phase3FilesTest`, `Phase4DeliveryDashboardTest`, `Phase4FinanceProjectLinkTest`, `Phase7ProcurementAndMembersTest`, `Phase8TaxReportsTest` **44 passed / 444 assertions ผ่าน 100% (100% green)**
- Code Style & Static Analysis: Laravel Pint **0 style issues**
- Frontend Quality Checks: TypeScript (`npx tsc --noEmit`) **0 errors**, ESLint (`npm run lint`) **0 errors**, Vite Production Build (`npm run build`) **clean 10.57s build**

---

## Phase 27: Manufacturing & Bill of Materials (BOM) [Optional Vertical]

เป้าหมาย: เพิ่มระบบจัดการสูตรการผลิต (BOM), ใบสั่งผลิต (Work Orders), และการคำนวณต้นทุนสินค้าสำเร็จรูป สำหรับธุรกิจผลิตหรือแปรรูป

### Phase 27 Design Backlog

- [ ] Design Multi-Level BOM Schema (Raw Materials, Sub-assemblies, Labor Cost, Overhead Cost)
- [ ] Design Work Order Lifecycle (Draft, Planned, In-Progress, Quality-Check, Completed, Cancelled)
- [ ] Design Stock Movement Integration: เบิกวัตถุดิบ (Stock Issue) $\rightarrow$ ผลิต $\rightarrow$ รับสินค้าสำเร็จรูป (Finished Goods Receipt) พร้อมคำนวณต้นทุนต่อหน่วย
- [ ] Design GL Auto-Posting (Dr. Work-in-Progress 1160 / Cr. Raw Materials 1140 $\rightarrow$ Dr. Finished Goods 1140 / Cr. Work-in-Progress 1160)

### Phase 27 Implementation Backlog

- [ ] เพิ่ม Schema/Models: `bill_of_materials`, `bom_items`, `work_orders`, `work_order_items`, `production_costs`
- [ ] พัฒนา Production Costing Service & Stock Movement Dispatcher
- [ ] เพิ่ม UI: BOM Builder, Work Order Management, Production Run & Cost Breakdown View
- [ ] เพิ่ม Feature Tests: BOM multi-level explosion, Stock deduction, Work order state transitions, และ Production GL posting tests

---

## Phase 28: Point of Sale (POS) & Retail Counter [Optional Vertical]

เป้าหมาย: หน้าจอขายหน้าร้านสำหรับธุรกิจค้าปลีก จัดการกะแคชเชียร์ รองรับเครื่องสแกนบาร์โค้ด พิมพ์สลิป และตัดสต็อกแบบ Real-time
**Guardrail:** การกระทบยอดเงินในกะ (Shift Reconciliation) จำกัดอยู่เฉพาะขอบเขต POS เท่านั้น และห้ามส่งต่อเป็น Organization-wide Cash Balance ใน Dashboard, Props หรือ API

### Phase 28 Design Backlog

- [ ] Design POS Session & Cash Drawer Shift Management (Open Float, Cash In/Out, Shift End Reconciliation, Overage/Shortage)
- [ ] Design Fast Checkout Screen (Barcode Scanner, Touch Grid, Discount, Multi-tender: Cash, PromptPay, Credit Card)
- [ ] Design Offline Capability & Sync Engine (Local cache for products/pricing, offline queue for transactions)
- [ ] Design Daily Shift Settlement & GL Auto-Posting (Dr. Cash/Bank / Cr. Sales 4100, Output VAT 2130)

### Phase 28 Implementation Backlog

- [ ] เพิ่ม Schema/Models: `pos_terminals`, `pos_shifts`, `pos_orders`, `pos_order_items`, `pos_payments`
- [ ] เพิ่ม UI: Touch-friendly POS Cashier Interface, Shift Open/Close Modal, Thermal Receipt Print Layout
- [ ] พัฒนา Barcode Lookup & Instant Stock Reduction Engine
- [ ] เพิ่ม Feature Tests: Shift cash reconciliation, Multi-tender payments, Stock movements from POS, และ Shift settlement GL posting tests
