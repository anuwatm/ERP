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
| Phase 3 | Not started | Finance + Finance Dashboard |
| Phase 4 | Not started | Delivery + Delivery Dashboard |
| Phase 5 | Not started | Executive Dashboard + E2E/UAT |

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
- [ ] Migration/model/policy: payments
- [ ] Migration/model/policy: expenses
- [ ] Migration/model/policy: files limited subset
- [x] Product/service catalog
- [ ] Create invoice from deal
- [x] Create manual invoice
- [x] Server-side invoice calculation
- [x] `tax_mode`: exclusive/inclusive/no_tax
- [ ] Payment receipt
- [ ] Idempotency-Key บน payment receipt/reversal
- [ ] Partial payment
- [ ] Overpay prevention with transaction lock
- [ ] Payment reversal
- [ ] Reversal `payment_date = CURRENT_DATE`
- [ ] One reversal per receipt
- [ ] Migration/test generated column `reversal_target_id` + `UNIQUE(org_id, reversal_target_id)` บน MariaDB
- [x] Invoice void policy
- [ ] `needs_sales_review` derived flag
- [ ] Expense draft/approve/pay/reject
- [ ] Payment/expense attachment
- [ ] Attachment download ตรวจ permission parent ทุกครั้ง
- [ ] `storage_key` สร้างฝั่ง server เท่านั้น
- [ ] Finance Dashboard: Invoiced Revenue
- [ ] Finance Dashboard: Cash In
- [ ] Finance Dashboard: Outstanding AR
- [ ] Finance Dashboard: Overdue AR
- [ ] Finance Dashboard: Expenses / Cash Out
- [ ] Finance Dashboard: Net Cash Flow
- [ ] Finance Dashboard: Invoice Status
- [ ] Finance Dashboard: Payment Reversal
- [ ] Cash In drilldown separates receipt/reversal
- [ ] Mark overdue invoice job
- [x] Invoice numbering sequence
- [ ] Expense `supplier_id` ไม่ FK / ไม่บังคับใน MVP
- [ ] Re-auth: void invoice / reverse payment / approve-pay expense
- [x] Tests for invoice totals
- [ ] Tests for payment reversal
- [ ] Tests for generated column/unique constraint กัน duplicate reversal
- [ ] Tests for no overpay
- [ ] Tests for concurrent payment no overpay
- [x] Tests for invoice edit guard after payment
- [ ] Update `README.md` with Phase 3 features

---

## Phase 4: Delivery + Delivery Dashboard

- [ ] Migration/model/policy: projects
- [ ] Migration/model/policy: tasks
- [ ] Migration/model/policy: task_checklists
- [ ] Migration/model/policy: task_comments
- [ ] Confirm `task_checklists.org_id` exists and tenant scope works
- [ ] Create project from won deal
- [ ] Create manual project
- [ ] Internal task with `project_id = null`
- [ ] Link invoice to project
- [ ] Link expense to project
- [ ] Project owner visibility
- [ ] Owner/Admin reassign project owner
- [ ] Member navigation defaults to Tasks
- [ ] Task create/update/status/comment
- [ ] Task assignee-only visibility for Member
- [ ] Project cost derived from approved/paid expenses
- [ ] Confirm schema has no `projects.actual_cost`
- [ ] `progress_percent` manual only
- [ ] Task `blocked` does not mark overdue in MVP
- [ ] Delivery Dashboard metrics
- [ ] Phase 4 UAT decision gate: evaluate `project_members`
- [ ] Tests for PM/Member visibility
- [ ] Tests for project cost aggregate
- [ ] Tests for project owner reassignment
- [ ] Tests for internal task
- [ ] Tests for blocked overdue rule
- [ ] Update `README.md` with Phase 4 features

---

## Phase 5: Executive Dashboard + E2E/UAT

- [ ] Executive Dashboard aggregates Sales + Finance + Delivery
- [ ] Widget visibility by permission
- [ ] No Cash Balance shown
- [ ] End-to-end flow test: Invite user -> Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard
- [ ] E2E role isolation tests
- [ ] E2E payment reversal tests
- [ ] E2E invoice totals tests
- [ ] E2E dashboard metrics tests
- [ ] E2E multi-role UNION permission test
- [ ] E2E `needs_sales_review` after invoice void
- [ ] UAT seed data
- [ ] UAT dashboard expected values
- [ ] UAT expected numbers จาก `docs/SEED_DATA.md`
- [ ] Phase 5 UAT decision gate: evaluate auto deal workflow vs `needs_sales_review`
- [ ] Security review
- [ ] Log/audit ไม่มี password, token, secret, `person_id` เต็ม
- [ ] ไม่มี Cash Balance ใน UI และ JSON props/API
- [ ] Negative scope test: no export/notifications/public API in MVP
- [ ] Grok/Gemini review
- [ ] Update `README.md` with Phase 5 features

