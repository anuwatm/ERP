# MVP Scope: ERP รอบแรก

เอกสารนี้ล็อกขอบเขต build รอบแรกของ `Company OS / Lightweight ERP` เพื่อให้ส่งใช้งานได้ก่อน แล้วค่อยขยายตาม [`ERP_FEATURE_PLAN.md`](./ERP_FEATURE_PLAN.md)

## เป้าหมาย

ให้ทีมทำ flow นี้ได้ครบใน org เดียว:

```text
Invite user -> Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard
```

## In Scope

### Foundation

- Organization: 1 org, timezone, currency `THB`, โครงสร้าง `บริษัท -> สาขา -> ฝ่าย -> แผนก -> ผู้ใช้งาน`
- Auth ผ่าน provider + user profile/org membership
- RBAC: Owner, Admin, Sales, Project Manager, Finance, Member, Viewer
- Settings: company profile, invoice numbering, payment terms
- Audit log: login, create/update/delete, role change, invoice/payment/expense state change

### CRM and delivery

- Customers และ Contacts
- Deals: stage, amount, owner, expected close date, activity/follow-up
- Projects: customer/deal link, budget, due date, status; ทำใน Phase 4 หลัง Finance
- Tasks: assignee, due date, status, comment

### Finance

- Product / Service Catalog เฉพาะ service/product ใช้สร้าง invoice
- Invoices: draft, sent, partially_paid, paid, overdue, void; Phase 3 ต้องสร้างจาก deal/manual ได้โดยไม่ต้องมี project
- Payments: partial payment, attachment, receipt/reversal; ไม่รองรับ overpay
- Expenses: draft, approved, paid, rejected; Phase 3 สร้างแบบไม่ผูก project ได้, Phase 4 ค่อยผูก project
- Dashboard ตาม Phase: Admin, Sales, Finance, Delivery, Executive summary; metrics หลักรวม Invoiced Revenue, Cash In, Outstanding AR, Overdue AR, Recognized Expense, Cash Out, Gross Profit, unpaid invoices, pipeline, overdue tasks

## Out of Scope

- Quotations, milestones, suppliers, purchase orders, inventory
- HR, attendance, leave, payroll
- PDF/CSV export, email/LINE notifications, automation, import/export, public API
- Accounting sync, AI assistant, customer portal, multi-branch operation/report เต็ม
- Bank reconciliation, opening balance, tax invoice compliance, credit note

## Locked Rules

### Money

- ทุกเงินใช้ `DECIMAL(18,2)` และ `currency=THB` ใน MVP
- server คำนวณ line total, invoice total และยอดคงเหลือ; client ส่งมาเพื่อแสดงผลเท่านั้น
- แสดง `Invoiced Revenue` และ `Cash In` แยกกันเสมอ

### Auth and isolation

- MVP ใช้ Laravel Breeze local: เก็บ **hashed** password ใน `users.password` (`auth_provider=local`)
- ห้าม plaintext password; เตรียม `auth_provider` / `auth_provider_user_id` สำหรับ OIDC ภายหลัง
- ทุก business query filter `org_id`; server บังคับ RBAC, row ownership และตรวจว่า branch/division/department อยู่ใน org เดียวกัน

### Payment and invoice

- payment ที่ post แล้วแก้หรือลบไม่ได้
- ยกเลิก payment ด้วย reversal entry เดียวต่อ receipt
- create/reversal ต้อง lock invoice, กัน overpay, แล้ว recalculate invoice ใน DB transaction เดียว
- invoice ที่มี payment ต้อง reverse payment ก่อนจึง void ได้

### Project cost and polymorphic relations

- project cost = sum(expenses.amount) ที่ project เดียวกันและ status `approved`/`paid`; ไม่เก็บ cache `actual_cost`
- `entity_type` + `entity_id` ต้อง validate allowlist, target entity และ `org_id`; มี index และ cleanup job

## Build Order

1. Schema, auth provider integration, organization hierarchy/isolation, RBAC, audit log, Admin dashboard
2. Customers, contacts, deals, activities/follow-up, Sales dashboard
3. Product/service catalog, invoices, payments/reversal, expenses, Finance dashboard
4. Projects, tasks, Delivery dashboard
5. Executive dashboard summary, end-to-end tests, UAT data migration

## Definition of Done

- Owner ดู dashboard ของ org ตัวเองได้โดยไม่เห็นข้อมูล org อื่น
- Sales สร้าง customer/deal และ follow-up ได้
- Project Manager สร้าง project/task และเห็นงานที่รับผิดชอบ
- Finance ออก invoice, รับ partial payment, reverse payment และอนุมัติ expense ได้
- ยอด invoice, dashboard และ project cost เปลี่ยนถูกต้องหลัง receipt/reversal/expense state change
- ทุก action เงินและสิทธิ์สำคัญมี audit record




