# Phase Acceptance Criteria

เอกสารนี้เป็น checklist ปิดงานแต่ละ Phase ก่อนขึ้น Phase ถัดไป.

## Phase 1: Foundation + Admin Dashboard

ผ่านเมื่อ:

- Register สร้าง organization, head office branch, default division, default department และ Owner ได้ใน transaction เดียว.
- Login/logout/reset/verify email ใช้งานได้ผ่าน Laravel Breeze local.
- Invite user และ accept invite ได้; invite token เป็น one-time และหมดอายุได้.
- Disable inactive user แล้ว login ไม่ได้.
- Owner/Admin จัดการ user และ role ได้ตาม permission; branch/division/department CRUD ย้ายไป Phase 1.1.
- ห้าม disable/lower role Owner คนสุดท้าย.
- Server บังคับ `org_id` isolation ทุก query.
- Admin Dashboard แสดง Organization Setup, Users Summary, Role Summary, Security Alerts, Recent Audit.
- Audit log มี event: register, login, invite, accept invite, role change, user hierarchy change.


## Phase 1.1: Admin Master Data & Access Management

ผ่านเมื่อ:

- Owner/Admin เพิ่ม/แก้/ปิดใช้งาน/delete-safe Branch ได้.
- Owner/Admin เพิ่ม/แก้/ปิดใช้งาน/delete-safe Division ได้.
- Owner/Admin เพิ่ม/แก้/ปิดใช้งาน/delete-safe Department ได้.
- Code ของ Branch/Division/Department เป็น text 6 หลัก และ auto-generate จาก `number_sequences` เท่านั้น.
- Head office branch มีได้หนึ่งรายการต่อ organization และการสลับ head office ต้องทำใน transaction เดียวพร้อม audit `branch.set_head_office`.
- Owner/Admin แก้ user profile, hierarchy และ role ได้.
- Owner/Admin disable/re-enable user ได้ตาม policy.
- ระบบห้าม hard delete user ใน MVP.
- Owner/Admin แก้ role-permission assignment ได้.
- ระบบห้าม CRUD permission code จาก UI.
- Server validate hierarchy: Branch -> Division -> Department -> User ทุกครั้ง.
- Server บังคับ `org_id` isolation ทุก query.
- Member ไม่มีสิทธิ create/edit/disable/delete structure, user หรือ role permission.
- Strict delete/disable guard กันลบหรือปิด structure ที่มี active child, user หรือ future business reference พร้อม error message ชัดเจน.
- Audit log ครบ create/update/disable/delete/access changes ของ branch/division/department/user/role permission.
- UI ใช้ `docs/DESIGN_SYSTEM.md`.
- Tests และ verification ผ่านครบก่อนเริ่ม Phase 2.
## Phase 2: CRM / Sales + Sales Dashboard

ผ่านเมื่อ:

- สร้าง/แก้/ค้นหา customer และ contact ได้.
- สร้าง deal, เปลี่ยน stage, ใส่ expected close date และ owner ได้.
- บันทึก activity/follow-up ได้.
- Sales เห็นเฉพาะ customer/deal ที่ `owner_id = user.id`.
- Sales Dashboard แสดง Customers, Deals Pipeline, Won/Lost Deals, Follow-ups Today, Stale Deals.
- Dashboard ยังไม่แสดง Cash In.

## Phase 3: Finance + Finance Dashboard

ผ่านเมื่อ:

- สร้าง product/service catalog ได้.
- สร้าง invoice จาก `deal` หรือ `manual` ได้โดยไม่ต้องมี project.
- `project_id` ใน invoice เป็น nullable และยังไม่บังคับใน Phase 3.
- Server คำนวณ subtotal, tax, total, paid_amount, balance_due เอง.
- Record partial payment ได้ และกัน overpay ภายใต้ transaction lock.
- Posted payment แก้/ลบไม่ได้ ต้อง reverse เท่านั้น.
- Receipt หนึ่งรายการ reverse ได้ครั้งเดียว.
- Expense draft/approve/pay/reject ได้; `project_id` nullable.
- Finance Dashboard แยก Invoiced Revenue, Cash In, Outstanding AR, Overdue AR, Expenses, Cash Out, Net Cash Flow, Invoice Status, Payment Reversal.
- Audit log มี invoice/payment/expense state change.

## Phase 4: Delivery + Delivery Dashboard

ผ่านเมื่อ:

- สร้าง project จาก deal won หรือ manual ได้.
- สร้าง task ใต้ project หรือ internal task ได้.
- Assign task ให้ user ใน org ได้.
- PM/Member visibility ถูกต้องตาม Role Visibility Matrix.
- Owner/Admin reassign `projects.owner_id` ได้เมื่อ PM เดิม inactive หรือย้ายงาน เพื่อไม่ให้ project กำพร้า.
- ผูก invoice/expense กับ project ได้หลังมี project.
- Project cost คำนวณจาก expenses approved/paid แบบ aggregate ไม่ cache `actual_cost`.
- Delivery Dashboard แสดง Active Projects, Project Status, Overdue Tasks, Task Load, Budget vs Expense, Project Profit Snapshot, Delivery Risk.

## Phase 5: Executive Dashboard + UAT

ผ่านเมื่อ:

- Flow `Invite user -> Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard` ใช้งานได้ครบ.
- Executive Dashboard รวม Sales + Finance + Delivery โดยซ่อน widget ตาม permission.
- Dashboard ไม่มี Cash Balance จริง.
- E2E tests ครอบ role isolation, payment reversal, invoice totals, dashboard metrics.
- UAT seed data พร้อม demo.
- ไม่มี critical/security bug เปิดค้าง.

## Phase 6: Reporting / Filters / Operational Polish

ผ่านเมื่อ:

- Dashboard รองรับ date/month filters.
- Finance/Delivery/Executive metrics ใช้ช่วงวันที่เดียวกัน.
- Dashboard visual polish และ empty states ตรวจผ่าน.
- Document number columns รองรับความยาวสำหรับ format หลัง MVP.
- Invoice VAT compliance รอบแรกตรวจผ่าน.

## Phase 7: Post-MVP Features

ผ่านเมื่อ:

- Configurable Number Sequences รองรับ tokens, reset period, scope และ preview.
- Inclusive VAT UI แสดง gross/net/VAT breakdown และ `tax_summary.wording`.
- Suppliers CRUD ใช้งานได้ภายใต้ org scope.
- Purchase Orders create/update/approve/cancel ได้ และ server คำนวณ totals.
- Expense validate supplier/PO chain.
- Project Members เพิ่ม/ลบสมาชิกได้ และ member เห็น project ที่ถูก assign.
- Tests, lint, format, build ผ่านครบ.

## Phase 8: Production Roadmap

ผ่านเมื่อ:

- Official PDF export สำหรับ Invoice, Tax Invoice/Receipt, PO และ 50-Tawi WHT certificate.
- Goods Receipt เชื่อมกับ approved PO, inventory on-hand, adjustment/return movement และ average cost.
- Sales/Purchase/WHT Tax reports export CSV และ Excel-compatible XLS ได้สำหรับงานบัญชี.
- Email notifications ทำงานผ่าน queue สำหรับ approval, due/overdue, invite, task/project assignment และมี in-app notification dedupe/preferences.

สถานะปัจจุบัน:

- Official Print/PDF เสร็จสำหรับ Invoice, Tax Invoice/Receipt และ PO พร้อม BahtText, logo/branch header, Original/Copy, VOID watermark และ PDF binary.
- 50-Tawi WHT certificate PDF เสร็จจาก expenses ที่มี withholding tax.
- Sales/Purchase Tax Reports, WHT Report, CSV/XLS export และ org isolation เสร็จรอบแรก.
- AR/AP Aging first pass เสร็จด้วยช่วง 0-30, 31-60, 61-90, >90 วัน.
- Inventory/GRN เสร็จ: partial receive, over-receive guard, stock ledger, adjustment in/out, return to supplier, on-hand summary, average cost.
- Notifications/Queues เสร็จ: PO pending approval, invoice due/overdue, invite, task/project assignment, queued mail, in-app notification, unread count, dedupe, preferences.
- ยัง pending: Purchase Tax source expansion จาก Expense/GRN เมื่อ schema ภาษีซื้อจาก expense/receipt พร้อมใช้งาน.

