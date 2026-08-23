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
| Phase 8 | In Progress | Core production roadmap implemented: PDF/50-Tawi, tax/WHT/aging exports, Inventory/GRN/adjustments, notifications/preferences; tax source/filter expansion remains |

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
- [ ] ขยาย Purchase Tax Report ให้รวม Expenses / GRN หลัง schema มี tax source ครบ
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
