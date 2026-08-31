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
| Phase 17 | Done | Enterprise Document Management (DMS), versioning, cross-module links, retention and confidentiality controls |
| Phase 18 | Done | Security 2FA / Auth OTP for privileged roles; offline TOTP, recovery, trusted devices and owner reset implemented |

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

### Environment Security & Backup

- [ ] เพิ่ม production `.env` security checklist: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`
- [ ] ตรวจ session/cookie/domain settings ให้เหมาะกับ HTTPS production
- [ ] กำหนดนโยบาย automated database backup รายวัน
- [ ] เพิ่ม restore drill checklist และทดสอบกู้คืนข้อมูลอย่างน้อย 1 รอบก่อน go-live
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

เป้าหมาย: เพิ่ม 2FA ให้ owner/admin/finance หลัง Payroll และ DMS ตาม product decision. ระหว่างยังไม่เริ่ม ต้องคง password confirmation, email verification, rate limit login, session invalidation และ audit log สำหรับ sensitive actions.

### Phase 18 Design Backlog

- [x] Design TOTP authenticator setup, encrypted secret storage และ recovery codes
- [x] Design enforcement policy สำหรับ owner/admin/finance, step-up challenge และ trusted-device/session policy
- [x] Design recovery, reset-by-owner, rate limit, audit log และ session invalidation flows
- [x] Design tests สำหรับ challenge, recovery, role change, org isolation และ brute-force guard

### Phase 18 Implementation Backlog

- [x] เพิ่ม 2FA schema/model, setup/verify/recovery UI และ privileged challenge middleware
- [x] เพิ่ม enforcement settings, audit log, rate limits และ security regression tests
