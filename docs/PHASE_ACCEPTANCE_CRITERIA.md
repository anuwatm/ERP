# Phase Acceptance Criteria

เอกสารนี้เป็น checklist ปิดงานแต่ละ Phase ก่อนขึ้น Phase ถัดไป.

## Phase 1: Foundation + Admin Dashboard

ผ่านเมื่อ:

- Register สร้าง organization, head office branch, default division, default department และ Owner ได้ใน transaction เดียว.
- Login/logout/reset/verify email ใช้งานได้ผ่าน Laravel Breeze local.
- Invite user และ accept invite ได้; invite token เป็น one-time และหมดอายุได้.
- Disable inactive user แล้ว login ไม่ได้.
- Owner/Admin จัดการ user, role, branch, division, department ได้ตาม permission.
- ห้าม disable/lower role Owner คนสุดท้าย.
- Server บังคับ `org_id` isolation ทุก query.
- Admin Dashboard แสดง Organization Setup, Users Summary, Role Summary, Security Alerts, Recent Audit.
- Audit log มี event: register, login, invite, accept invite, role change, user hierarchy change.

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


