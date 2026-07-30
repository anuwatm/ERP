# GPT Decision Log (Clean)

Last cleaned: 2026-07-30

ไฟล์นี้เก็บเฉพาะ decision / rebuttal / active handoff ที่ยังมีผลต่อการพัฒนาต่อไป ส่วน audit notes ที่ผ่านแล้วถูกตัดออกเพื่อลด context หนัก

---

## 1. Current Phase Status

- Phase 1: Done
- Phase 1.1: Done
- Phase 2: Done
- Phase 3: Done
- Phase 4: Done
- Next: Phase 5 Executive Dashboard + E2E/UAT

Phase 4 final state:

- Projects core: done
- Tasks core: done
- Invoice/Expense project link: done
- Dynamic project cost: done
- Delivery Dashboard metrics: done
- `project_members`: deferred Post-MVP/V2

---

## 2. Decisions That Still Matter

### MVP Scope

- ไม่มี Cash Balance ใน MVP ทั้ง UI และ JSON/Inertia props
- ไม่มี export / notification / public API ใน MVP
- Full tax invoice compliance, credit note, suppliers, PO, inventory เป็น Post-MVP/V2
- `project_members` ไม่เปิดใน MVP; ใช้ owner/assignee-only ก่อน

### Security / Permission

- ใช้ `User::hasPermissionCode()` เป็น helper กลางสำหรับ permission checks
- Finance Dashboard แสดงเฉพาะ user ที่มี `expenses.view`
- Delivery Dashboard แสดงเฉพาะ user ที่มี `projects.view` หรือ `tasks.view`
- Member ที่มีแค่ `tasks.view` เห็นเฉพาะ task scope ของตัวเอง และไม่เห็น project financial metrics
- `person_id`, password, token, secrets ห้ามหลุดใน UI/log/Inertia props
- Sensitive writes ใช้ `password.confirm` ตาม route ที่กำหนด

### Sales / Invoice

- Invoice จาก deal ที่ถูก void ไม่ auto-reopen deal
- ใช้ derived flag `needs_sales_review` แทน auto state change ของ Deal
- เหตุผล: void invoice เป็น finance correction ได้ ไม่ได้แปลว่า deal ยกเลิกเสมอ

### Payments

- Payment receipt/reversal ใช้ `idempotency_key`
- ห้าม overpay ด้วย transaction + `lockForUpdate()`
- Reversal amount เก็บเป็นบวก แต่ Cash In / Net Cash Flow ต้องหักด้วย `entry_type = reversal`
- ห้ามใช้ `SUM(payments.amount)` ตรง ๆ สำหรับ cash-in report

### Expenses / Files

- Expense status: `draft`, `approved`, `paid`, `rejected`
- Project cost นับเฉพาะ expenses status `approved` หรือ `paid`
- Files MVP จำกัดเฉพาะ payment slip และ expense receipt
- Generic files สำหรับ customer/deal/project/task เป็น Post-MVP/V2

### Projects / Tasks

- ไม่มี `projects.actual_cost`
- Project actual cost เป็น derived value จาก approved/paid expenses เท่านั้น
- Project progress เป็น manual `progress_percent`
- Project Manager เห็น project ที่ตัวเองเป็น owner
- Member เห็นเฉพาะ task ที่ assign ให้ตัวเอง
- Member update task ได้เฉพาะ `status`
- Internal task รองรับ `project_id = null`
- `blocked` task ไม่นับเป็น overdue ใน MVP

---

## 3. Deferred / Not Implemented Now

### `project_members`

Decision: defer Post-MVP/V2

เหตุผล:

- Phase 4 ผ่านด้วย owner/assignee-only visibility แล้ว
- `ProjectAccess` และ `TaskAccess` enforce scope ชัดเจนแล้ว
- การเพิ่ม `project_members` จะกระทบ data model, permission matrix, UI assignment, dashboard scope, tests และ migration เพิ่ม
- Gemini ตรวจ Phase 4 แล้วให้ `Fully Verified & Completed` ไม่มี blocker

### Tax / Invoice Compliance

Deferred:

- Inclusive VAT subtotal display แบบ net subtotal
- Header discount VAT allocation
- Credit note / full tax invoice compliance

เหตุผล:

- เป็น accounting/tax compliance รอบใหญ่
- กระทบสูตร backend, UI preview, historical invoice expectations, tests และเอกสาร

### Number Format Expansion

Deferred:

- `invoice_no` / `expense_no` จาก `char(6)` เป็น `varchar(30)`

เหตุผล:

- ยังไม่เป็น blocker ของ MVP
- ควรทำตอนเตรียม UAT/demo หรือก่อนปรับ numbering format จริง

### Dashboard Date Filters

Deferred:

- Finance/Delivery dashboard period filters รายเดือน/รายปี/custom range

เหตุผล:

- ตอนนี้เป็น all-time aggregate ตาม MVP
- ควรทำใน Phase 5 หรือ reporting enhancement

### Demo Seeder Expansion

Deferred:

- เพิ่ม demo seed สำหรับ products, invoices, payments, expenses, projects, tasks

เหตุผล:

- เหมาะกับ Phase 5 UAT seed data

---

## 4. Phase 3 Final Summary

Completed:

- Products/Services catalog
- Manual invoice + invoice items
- Create invoice from deal with prefill
- Server-side invoice totals
- Payment receipt, partial payment, no overpay, reversal
- Expenses draft/approve/pay/reject
- Payment/expense attachments
- Finance Dashboard
- Overdue invoice command
- `needs_sales_review` after void invoice from deal
- Concurrent payment no-overpay test

Important verification references:

- Phase 3 final regression recorded: 55 Phase 3 tests / 401 assertions
- Later Phase 3/4 regression subsets passed during Phase 4 work

---

## 5. Phase 4 Final Summary

Completed:

- Projects table/model/controller/UI
- Create manual project
- Create project from won deal, one project per deal
- Project owner visibility and owner reassignment
- Tasks, checklists, comments
- Internal task with `project_id = null`
- Member task visibility and status-only update
- Invoice project link with customer match guard
- Expense project link with org guard
- Dynamic project cost from approved/paid expenses
- Project list Actual Cost / Margin
- Delivery Dashboard metrics
- Phase 4 UAT gate closed: `project_members` deferred Post-MVP/V2

Delivery Dashboard metrics:

- Active Projects = `planning`, `active`, `on_hold`
- Project Status breakdown
- Overdue Tasks = `todo` / `in_progress` where `due_date < today`
- Task Load = open tasks by assignee (`todo`, `in_progress`, `blocked`)
- Budget vs Expense = budget vs approved/paid expenses
- Project Profit = total budget - actual cost
- Delivery Risk = distinct risk project count plus breakdown

Important verification references:

- `Phase4DeliveryDashboardTest`: 3 tests / 66 assertions
- Phase 4 + Finance Dashboard regression: 23 tests / 250 assertions
- Phase 4 finance/project cost regression: 28 tests / 178 assertions
- `pnpm run check-format`: passed
- `pnpm run lint`: passed
- `pnpm run build`: passed
- Laravel Pint: passed

Gemini final Phase 4 audit:

- Section 20 status: `Fully Verified & Completed`
- No blocker / no code change required

---

## 6. Next Phase 5 Focus

Start Phase 5 with:

- Executive Dashboard aggregates Sales + Finance + Delivery
- Widget visibility by permission
- No Cash Balance shown
- E2E flow: Invite user -> Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard
- E2E role isolation tests
- E2E payment reversal tests
- E2E invoice totals tests
- E2E dashboard metrics tests
- E2E multi-role UNION permission test
- E2E `needs_sales_review` after invoice void
- UAT seed data and expected dashboard values
- Security review: no password/token/secret/full `person_id`
- Negative scope test: no export/notifications/public API in MVP

---

## 7. Recent Operational Note

Login route check on 2026-07-30:

- Correct URL in local Apache setup: `http://localhost/ERP/login`
- `http://localhost/login` returns 404 because app lives under `/ERP`
- Laravel cache/views/routes cleared after check
- Authentication + Dashboard regression passed: 7 tests / 74 assertions