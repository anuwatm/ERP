# Phase 23.2.1 — Scope & Dashboard Inventory

สถานะ: **Complete — 2026-09-17**

เอกสารนี้เป็น baseline สำหรับ Phase 23.2.2–23.2.7 ไม่ใช่ specification ว่า widget ทุกตัวมีอยู่แล้ว. ชื่อ metric, สูตร, date basis และ payload ที่จะใช้จริงต้องล็อกใน Phase 23.2.2 ก่อนเริ่ม UI/query ของ Phase 23.2.5.

## 1. Source of truth และขอบเขต

| รายการ | Source of truth |
| --- | --- |
| เป้าหมาย UX/widget | `erp-dashboard-design-concept.md` |
| route และ middleware ปัจจุบัน | `backend/routes/web.php` |
| aggregate ภายใน | `backend/app/Http/Controllers/Admin/DashboardController.php`, `backend/app/Http/Controllers/Sales/SalesDashboardController.php` |
| internal UI ปัจจุบัน | `backend/resources/js/Pages/Dashboard.tsx`, `backend/resources/js/Pages/Sales/Dashboard.tsx` |
| external portal | `backend/app/Http/Controllers/PortalController.php`, `backend/resources/js/Pages/Portal/Dashboard.tsx` |
| metric เดิม | `docs/modules/17-dashboard.md` |

Dashboard เป็น read/aggregate layer. หน้ารายการ, รายละเอียด, report และ action screen เป็น drill-down; ไม่ถูกแปลงเป็น dashboard ใน Phase 23.2 เว้นแต่มี requirement ใหม่.

## 2. Inventory ของปัจจุบัน

| Surface | Route / page | Controller / access ปัจจุบัน | สิ่งที่มีจริง | สถานะเทียบ concept |
| --- | --- | --- | --- | --- |
| Admin | `GET /dashboard` → `Dashboard` | `dashboard.view`; org-wide admin summary | โครงสร้างองค์กร, users, roles, audit และ security counters | ต้อง redesign เป็น Admin/Security dashboard |
| Executive | `GET /executive-dashboard` → `Dashboard` | route ต้อง `dashboard.view`; controller ตรวจ `executive.dashboard.view` หรือ role owner/admin | sales/finance/delivery summary | มี aggregate บางส่วน; charts, lists, drill-down ยังไม่ครบ |
| Finance | `GET /finance-dashboard` → `Dashboard` | `expenses.view`; controller ใช้ org scope | revenue, cash in/reversal, AR, expenses, invoice-status aggregate | มี aggregate บางส่วน; aging/trend/lists ยังไม่ครบ |
| Delivery | `GET /delivery-dashboard` → `Dashboard` | route ต้อง `dashboard.view`; controller ตรวจ `projects.view` หรือ `tasks.view` | active projects, overdue tasks, budget/cost, status, task load, risks | มี aggregate บางส่วน; drills/lists และ full scope ยังไม่ครบ |
| Sales | `GET /sales-dashboard` → `Sales/Dashboard` | `sales.dashboard.view`; `SalesAccess` scoped | customers, deals, pipeline, follow-ups, stale deals, stage/owner aggregate | ไม่มี shared period filter; lists/drill-down ยังไม่ครบ |
| Customer portal | `GET /portal` → `Portal/Dashboard` | portal session/grant เท่านั้น, throttle | quotations, invoices, documents; QR เมื่อเข้าเกณฑ์ | เป็น external status dashboard; ห้ามใช้ internal payload |
| Supplier portal | `GET /portal` → `Portal/Dashboard` | portal session/grant เท่านั้น, throttle | POs, payable status, vendor bill submission, documents | เป็น external status dashboard; ห้ามใช้ internal payload |
| Procurement/Inventory | ไม่มี | ไม่มี | ไม่มี dedicated dashboard | ต้องสร้างใน 23.2.5 หลัง metric dictionary |
| HR/Payroll | ไม่มี | ไม่มี | ไม่มี dedicated dashboard | ต้องสร้างใน 23.2.5 หลัง privacy/attendance decisions |
| My Work | ไม่มี | ไม่มี | ไม่มี personal dashboard | ต้องสร้างใน 23.2.5 บน `TaskAccess` scope |

Navigation ปัจจุบันใน `AuthenticatedLayout.tsx` มี Admin, Executive, Sales, Finance และ Delivery dashboard links. Role landing ยัง redirect สู่ `dashboard`; Phase 23.2 ต้องใช้ effective permissions ไม่ใช่ชื่อ role เพื่อเลือก fallback landing.

## 3. Target widget inventory และ data readiness

สัญลักษณ์: **Ready** = มี source และ aggregate ปัจจุบัน, **Partial** = มี source แต่ต้องเพิ่ม query/contract, **Decision** = ต้องล็อก definition หรือ policy ก่อนทำ, **Missing** = ยังไม่มี dashboard/query.

| Dashboard | คำถามที่ตอบ | KPI / chart / list เป้าหมาย | Source ที่ตรวจพบ | Permission และ row scope | Date basis / currency | Drill-down | Readiness |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Executive | บริษัทเป็นอย่างไรและเสี่ยงตรงไหน | Revenue, Cash In, AR, Overdue AR, Gross Profit, Active Projects; revenue/expense, cash in/out, pipeline, project status; top customers, overdue invoices, projects at risk | invoices, payments, expenses, deals, projects, tasks, customers | `executive.dashboard.view` และ source permissions; org scope ต้องไม่ขยายสิทธิ์ | metric period แยก issue/payment/expense date; เงินใช้ document/base-currency rule ที่ต้องล็อก | invoice/project/customer/deal lists | Partial |
| Sales | ต้องตามใครและ deal ใดเสี่ยง | pipeline, new/won deals, win rate, follow-ups; stage/owner charts; follow-ups, stale deals, expiring quotations | deals, activities, quotations, customers | `sales.dashboard.view`, `SalesAccess` สำหรับ customer/deal/activity | created_at, won_at, follow_up_at, valid_until ต้องล็อกแยก | sales lists/kanban พร้อม filter owner/stage | Partial |
| Finance | เงินเข้าและลูกหนี้ค้างเท่าไร | revenue, cash in, outstanding/overdue AR, unpaid invoices, net cash flow; AR aging, invoice status, cash trend; overdue invoices, payments, expenses awaiting approval | invoices, payments, expenses | dashboard permission และ `invoices.view`/`payments.view`/`expenses.view`; org scope | issue_date, payment_date, paid_at และ as-of AR แยกนิยาม; ห้าม raw cash balance | invoice/payment/expense filtered lists | Partial |
| Delivery | โครงการและงานใดล่าช้า/เกินงบ | active projects, overdue tasks, due this week, progress, margin; project/task-load/budget charts; risk projects, overdue tasks | projects, tasks, expenses, invoices (margin needs definition) | `projects.view`/`tasks.view`, `ProjectAccess`/`TaskAccess`; expense data ต้องไม่รั่ว | snapshot status และ due-date metric ต้องไม่ถูก filter period โดยไม่ระบุ | project/task lists by risk/assignee | Partial / Decision |
| Procurement & Inventory | ต้องสั่งอะไรและ spend เท่าไร | open/pending POs, low stock, spend; PO status, supplier spend; low stock, pending PO, GRN list | purchase orders, stock levels/movements, products, suppliers, expenses | module read permissions, org and warehouse scope | PO/receipt/expense date and GRNI boundary ต้องล็อก | PO, inventory, supplier, GRN lists | Missing / Decision |
| HR & Payroll | คนมาทำงาน/คำขอลา/รอบจ่ายเป็นอย่างไร | active employees, attendance today, pending leave, last payroll; headcount/attendance charts; leave/today leave list | employees, attendances, leave requests, payroll runs | HR/payroll permissions; no individual salary in payload | work_date, schedule/holiday, payroll period; timezone/policy required | employees, attendance, leave, payroll detail | Missing / Decision |
| Admin & Security | ผู้ใช้/สิทธิ์/เหตุการณ์เสี่ยงเป็นอย่างไร | active users, pending invites, defined alerts, audit events; role assignment chart; invites, audit, security alert list | users, roles, role assignments, audit logs, 2FA policy/state | users/roles/audit/security permissions; no role-name bypass | current snapshot; audit `created_at`; alert definitions/dedup required | users/audit filtered lists | Partial / Decision |
| My Work | วันนี้ฉันต้องทำอะไร | due today, overdue, completed this week; my tasks grouped by project | tasks, projects | `tasks.view`, `TaskAccess`; no finance/deal payload | due_date/completed_at; current overdue snapshot | task list/detail only | Missing |
| Customer Portal | quotation/invoice/document สถานะใดต้องทำต่อ | permitted quotations, invoices, documents; payment QR only permitted THB balance | quotations, invoices, documents, portal grants | magic-link portal identity; customer + org + document scan status | document and invoice status; no org aggregate | permitted item only | Partial visual refresh |
| Supplier Portal | PO, bill, payment statusใดต้องทำต่อ | permitted POs/payables, submitted bills, documents | purchase orders, expenses/payables, submissions, documents | magic-link portal identity; supplier + org + document scan status | document/payment status; no org aggregate | permitted item only | Partial visual refresh |

## 4. Existing aggregate mapping และ gaps

| Current aggregate | Current status/source rule | Target disposition |
| --- | --- | --- |
| Invoiced Revenue | invoices status `sent`, `partially_paid`, `paid`, `overdue`; `issue_date` | Retain after metric dictionary validates status and multi-currency presentation |
| Cash In | receipts minus reversals by `payment_date` | Retain breakdown; never label as Cash Out or Cash Balance |
| Outstanding / Overdue AR | open invoice statuses, current `balance_due`, current due-date check | Rework as explicit as-of snapshot; do not pretend historical AR by filtering `issue_date` |
| Recognized Expense / Cash Out | approved/paid by `expense_date`; paid by `paid_at` | Retain only after status/date dictionary is signed off |
| Gross Profit / Project Profit | revenue minus expense; delivery currently budget minus actual expense | These are different business meanings. Rename/define separately before display |
| Delivery risk | over-budget, past-due project, urgent/high open task | Retain only with threshold, status and row-scope definition |
| Security alert total | inactive user, expired invite, selected audit actions | Do not call inactive users or policy-permitted 2FA settings an alert. Define events, threshold and dedupe first |
| Sales pipeline/stale/follow-up | `SalesAccess`, open stages, 7-day activity cutoff | Add period support and list payloads after definitions lock |

## 5. Drill-down inventory (operational, not dashboards)

| Domain | Existing destination class | Required Phase 23.2 contract |
| --- | --- | --- |
| Sales | Customers, Deals, Activities, Quotations | Preserve owner/stage/date filter in URL and row scope |
| Finance | Invoices, Payments, Expenses, tax/accounting reports | Preserve status, aging bucket/date range; action permission remains at destination |
| Delivery | Projects, Tasks | Preserve risk, assignee, due-date filters via `ProjectAccess`/`TaskAccess` |
| Procurement/Inventory | Purchase Orders, stock/products, GRN/movements, Suppliers | Keep warehouse/org filters and approval permission at destination |
| HR/Payroll | Employees, Attendance, Leave, Payroll | Never carry salary details in dashboard URL/payload |
| Admin/Security | Users, Roles, Audit Logs, organization settings | Write actions require their existing permissions and step-up controls |
| Portal | permitted quotation/invoice/PO/document pages | Grant/party scope must be verified again for every destination |

## 6. Explicit scope decisions for later phases

1. No widget may use mock data. Items marked **Decision** require Phase 23.2.2 approval before implementation.
2. Current filter contract is `all_time`, `month`, `year`, `custom`; the concept's Today/This Week/This Month/Custom contract is a 23.2.2/23.2.3 migration decision and must stay URL-backed.
3. Portal is a separate dashboard family. It must never receive internal dashboard aggregate, organization totals or staff authorization props.
4. Reports remain reports. Phase 23.2 only adds drill-down links to existing operational/list screens.
5. Role landing is a default only. The later selector must use effective permissions and have a safe fallback when a stored/default dashboard is unauthorized.
6. No raw cash balance, individual payroll amount, or unauthorized aggregate enters an Inertia payload.

## 7. Completion evidence

- Routes, controllers, Inertia pages, navigation and portal surfaces were inventoried from current source on 2026-09-17.
- Every concept dashboard has a target row with question, widget family, source, permission/row scope, date/currency rule, drill-down and readiness.
- Readiness gaps are explicit and assigned to Phase 23.2.2 or later; none are represented as implemented.
