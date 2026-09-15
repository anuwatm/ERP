# Concept การออกแบบ Dashboard — Company OS / Lightweight ERP

> อ้างอิงจากเอกสารโปรเจกต์ (`docs/modules/17-dashboard.md` และโมดูลที่เกี่ยวข้องทั้งหมด) ออกแบบให้แต่ละ Dashboard ตรงกับข้อมูลที่ "มีจริง" ในระบบ และตรงกับสิ่งที่แต่ละส่วนงานต้องใช้ตัดสินใจจริงในการทำงานประจำวัน

---

## 1. หลักการออกแบบ (Design Principles)

| หลักการ | รายละเอียด |
|---|---|
| **ชัดเจน (Clear)** | 1 widget = 1 คำถามที่ตอบได้ทันที ไม่ยัดหลายเมตริกไว้ในการ์ดเดียว |
| **ดูง่าย (Scannable)** | ตัวเลขสำคัญ (KPI) อยู่แถวบนสุด ขนาดใหญ่ อ่านได้ในสายตาแรกที่มอง |
| **เข้าใจเร็ว (At-a-glance)** | ทุก KPI มีบริบทเปรียบเทียบเสมอ (vs ช่วงก่อนหน้า, vs เป้า, หรือ breakdown) ไม่โชว์เลขลอยๆ |
| **มีกราฟประกอบ** | ข้อมูลที่มีมิติเวลา → กราฟเส้น/แท่งเทียบช่วงเวลา; ข้อมูลที่มีมิติสัดส่วน → โดนัท/แท่งเทียบหมวด |
| **Actionable** | ทุก widget ที่เป็นรายการ (list/table) ต้องคลิกแล้วไปหน้า list จริงพร้อม filter ที่ตรงกัน (drill-down) |
| **สิทธิ์การมองเห็น** | widget ที่ user ไม่มี permission ต้อง**ซ่อนทั้งหมด** ไม่ใช่แสดงแบบจางๆ |
| **Empty state ที่มีความหมาย** | ไม่มีข้อมูล → แสดง 0 พร้อมปุ่ม action ("+ สร้าง Deal", "+ เชิญ User") ห้ามซ่อน widget เฉยๆ ยกเว้นเพราะสิทธิ์ |

### Widget Vocabulary ที่ใช้ทั้งระบบ (ให้ทุก Dashboard ใช้ชุดเดียวกัน)

| ประเภท | ใช้เมื่อ | ตัวอย่าง |
|---|---|---|
| **KPI Card** | ตัวเลขสรุป 1 ค่า + % เปลี่ยนแปลงจากช่วงก่อนหน้า | Invoiced Revenue, Overdue AR |
| **Line Chart** | แนวโน้มตามเวลา (รายวัน/สัปดาห์/เดือน) | Revenue vs Expense 6 เดือนล่าสุด |
| **Bar Chart** | เปรียบเทียบระหว่างหมวด/บุคคล/ช่วง | Spend by Supplier, Task Load by Assignee |
| **Donut / Stacked Bar** | สัดส่วนของสถานะ | Invoice Status Distribution, Deal by Stage |
| **Table / List** | รายการที่ต้องลงมือทำต่อ | Overdue Invoices, Follow-ups Today |
| **Progress Bar** | ความคืบหน้าเทียบเป้า | Project Progress %, Budget Utilization |

### Color Convention (ใช้สม่ำเสมอทุก Dashboard)

| สี | ความหมาย |
|---|---|
| เขียว | ดี / ตรงเป้า / Paid / Won |
| แดง | เสี่ยง / เกินกำหนด / Overdue / Lost |
| เหลือง/ส้ม | ต้องติดตาม / Pending / รออนุมัติ |
| น้ำเงิน/เทา | ข้อมูลทั่วไป / เป็นกลาง |

### Filter มาตรฐานทุก Dashboard

ช่วงเวลา: **วันนี้ / สัปดาห์นี้ / เดือนนี้ / กำหนดเอง** — filter นี้ครอบคลุมทุก widget ในหน้าเดียวกัน (ยกเว้น widget ที่นิยามเป็น snapshot ปัจจุบันเสมอ เช่น Overdue Tasks ที่ต้องนับ ณ วันนี้ตลอด)

---

## 2. Mapping: บทบาทผู้ใช้ → Dashboard

| Role (ตาม RBAC) | Dashboard หลักที่เห็น |
|---|---|
| Owner | Executive Dashboard (เห็นทุก widget ทุกส่วนงาน) |
| Admin | Admin Dashboard + Procurement/Inventory + HR (ตามสิทธิ์) |
| Sales | Sales Dashboard |
| Finance | Finance Dashboard + Procurement/Inventory (AP) |
| Project Manager | Delivery Dashboard |
| Member | My Work (personal) |
| Viewer | Executive Dashboard แบบอ่านอย่างเดียว ไม่มีปุ่ม action |

---

## 3. Executive Dashboard — ภาพรวมบริษัท (Owner)

**คำถามที่ต้องตอบได้ทันที:** "บริษัทตอนนี้เป็นยังไง กำไรอยู่ไหม เงินสดพอไหม งานเสี่ยงตรงไหน"

**Layout:**
```
[ KPI Row: 6 การ์ด ]
[ Line Chart: Revenue vs Expense ]  [ Bar: Cash In vs Cash Out ]
[ Bar/Funnel: Pipeline by Stage ]   [ Donut: Project Status ]
[ Table: Top Customers ]  [ Table: Overdue Invoices ]  [ Table: Projects at Risk ]
```

| Widget | ประเภท | ข้อมูล/สูตร | Source | Drilldown |
|---|---|---|---|---|
| Invoiced Revenue | KPI Card | sum(invoices.total) status ใน sent/partially_paid/paid/overdue, by issue_date | invoices | → Invoices list |
| Cash In | KPI Card | sum(receipt) − sum(reversal) by payment_date | payments | → Payments list |
| Outstanding AR | KPI Card | sum(balance_due) status sent/partially_paid/overdue | invoices | → Invoices (filter unpaid) |
| Overdue AR | KPI Card | sum(balance_due) ที่ due_date < today | invoices | → Invoices (filter overdue) |
| Gross Profit | KPI Card | Invoiced Revenue − Recognized Expense | invoices, expenses | — |
| Active Projects | KPI Card | count(projects.status='active') | projects | → Projects list |
| Revenue vs Expense | Line Chart | รายเดือน 6 เดือนล่าสุด: invoices.total vs expenses.amount | invoices, expenses | — |
| Cash In vs Cash Out | Bar Chart | payments (receipt-reversal) vs expenses (paid) รายเดือน | payments, expenses | — |
| Pipeline by Stage | Bar/Funnel | sum(deals.value_amount) group by stage (ไม่รวม won/lost) | deals | → Deals kanban |
| Project Status | Donut | count(projects) group by status | projects | → Projects list |
| Top Customers | Table | sum(invoices.total) by customer, top 5-10 | invoices, customers | → Customer detail |
| Overdue Invoices | Table | invoice_no, customer, amount, วันเกิน | invoices | → Invoice detail |
| Projects at Risk | Table | project ที่มี overdue task สูง หรือ cost > budget | projects, tasks, expenses | → Project detail |

**Empty state:** ยังไม่มี invoice/deal/project ให้แสดง 0 ทุกการ์ด พร้อมปุ่ม "+ สร้าง Deal" / "+ สร้าง Invoice"

---

## 4. Sales Dashboard — Pipeline การขาย (Sales)

**คำถามที่ต้องตอบได้ทันที:** "วันนี้ต้องตามลูกค้าใคร ดีลไหนใกล้ปิด ดีลไหนค้างนานเกินไป"

**Layout:**
```
[ KPI Row: 5 การ์ด ]
[ Bar/Funnel: Deals by Stage ]   [ Bar: Top Sales Owners ]
[ Table: Follow-ups วันนี้ ]  [ Table: Stale Deals ]  [ Table: Quotations ใกล้หมดอายุ ]
```

| Widget | ประเภท | ข้อมูล/สูตร | Source | Drilldown |
|---|---|---|---|---|
| Pipeline Value | KPI Card | sum(deals.value_amount) stage ที่ยังไม่ closed | deals | → Deals kanban |
| New Deals (ช่วงเวลา) | KPI Card | count(deals created ในช่วง) | deals | → Deals list |
| Won Deals (มูลค่า) | KPI Card | sum(value_amount) stage=won, by won_at | deals | → Deals (filter won) |
| Win Rate | KPI Card | won / (won+lost) × 100 ในช่วงเวลา | deals | — |
| Follow-ups วันนี้ | KPI Card | count(activities.follow_up_at=today, completed_at is null) | activities | → Follow-up list |
| Deals by Stage | Funnel/Bar | count + value ต่อ stage: new/contacted/qualified/proposal/negotiation | deals | → Deals kanban filter stage |
| Top Sales Owners | Bar Chart | sum(won value) group by owner_id | deals | → Deals filter owner |
| Follow-ups วันนี้ | Table | customer, กิจกรรมล่าสุด, เจ้าของ | activities | → Customer/Deal detail |
| Stale Deals | Table | deal ไม่มี activity เกิน 7 วัน | deals, activities | → Deal detail |
| Quotations ใกล้หมดอายุ | Table | status=sent, valid_until ใกล้ถึง | quotations | → Quotation detail |

**Empty state:** ไม่มี customer/deal → แสดง 0 และปุ่ม "+ สร้าง Customer" / "+ สร้าง Deal"

---

## 5. Finance Dashboard — การเงินและกระแสเงินสด (Finance)

**คำถามที่ต้องตอบได้ทันที:** "ใครค้างจ่ายเรา เงินเข้าวันนี้เท่าไหร่ ต้องตามใบไหนก่อน"

**Layout:**
```
[ KPI Row: 6 การ์ด ]
[ Bar: AR Aging Buckets ]        [ Donut: Invoice Status Distribution ]
[ Line: Cash In vs Cash Out ]
[ Table: Overdue Invoices ]  [ Table: Payments ล่าสุด ]  [ Table: Expenses รออนุมัติ ]
```

| Widget | ประเภท | ข้อมูล/สูตร | Source | Drilldown |
|---|---|---|---|---|
| Invoiced Revenue | KPI Card | เหมือน Executive (scope ทั้ง org) | invoices | → Invoices list |
| Cash In | KPI Card | receipt − reversal by payment_date | payments | → Payments list |
| Outstanding AR | KPI Card | sum(balance_due) sent/partially_paid/overdue | invoices | → Invoices filter unpaid |
| Overdue AR | KPI Card | sum(balance_due) due_date < today | invoices | → Invoices filter overdue |
| Unpaid Invoices | KPI Card | count(balance_due > 0) | invoices | → Invoices list |
| Net Cash Flow | KPI Card | Cash In − Cash Out | payments, expenses | — |
| AR Aging | Bar Chart | balance_due แบ่งช่วง current/1-30/31-60/60+ วัน | invoices | → Invoices filter aging bucket |
| Invoice Status Distribution | Donut | count invoices group by status | invoices | → Invoices filter status |
| Cash In vs Cash Out | Line Chart | รายวัน/สัปดาห์ตามช่วง filter | payments, expenses | — |
| Overdue Invoices | Table | invoice_no, customer, balance_due, วันเกิน, เรียงเกินมากสุดก่อน | invoices | → Invoice detail (Record Payment) |
| Payments ล่าสุด | Table | invoice, จำนวนเงิน, วิธีชำระ, วันที่ | payments | → Payment detail |
| Expenses รออนุมัติ | Table | title, จำนวนเงิน, category, ผู้ขอ | expenses | → Expense detail (Approve) |

**Empty state:** ไม่มี invoice/payment → แสดง Finance metric เป็น 0 ทุกตัว ห้ามซ่อน Invoiced Revenue/Cash In

---

## 6. Delivery Dashboard — ความคืบหน้าโครงการ (Project Manager)

**คำถามที่ต้องตอบได้ทันที:** "โครงการไหนช้า งานใครค้าง ต้นทุนเกินงบไหม"

**Layout:**
```
[ KPI Row: 5 การ์ด ]
[ Donut: Project Status ]        [ Bar: Task Load by Assignee ]
[ Bar: Budget vs Actual Cost (top projects) ]
[ Table: Projects at Risk ]  [ Table: Overdue Tasks ]
```

| Widget | ประเภท | ข้อมูล/สูตร | Source | Drilldown |
|---|---|---|---|---|
| Active Projects | KPI Card | count(status=active) | projects | → Projects list |
| Overdue Tasks | KPI Card | count(is_overdue=true) | tasks | → Tasks filter overdue |
| Tasks ครบกำหนดสัปดาห์นี้ | KPI Card | count(due_date within 7 days, not done) | tasks | → Tasks list |
| Avg. Project Progress | KPI Card | avg(progress_percent) ของ project active | projects | — |
| Project Margin (รวม) | KPI Card | sum(revenue − cost) ของ project active/completed ในช่วง | invoices, expenses, projects | → Project detail |
| Project Status | Donut | count group by planned/active/on_hold/completed/cancelled | projects | → Projects filter status |
| Task Load by Assignee | Bar Chart | count(tasks) group by assignee, แยกสี by status | tasks | → Tasks filter assignee |
| Budget vs Actual Cost | Bar Chart (คู่) | budget_amount vs sum(expenses.amount approved/paid) ต่อ project (top N) | projects, expenses | → Project detail |
| Projects at Risk | Table | project ที่ cost > budget หรือ overdue task > threshold | projects, expenses, tasks | → Project detail |
| Overdue Tasks | Table | task, project, assignee, due_date, วันเกิน | tasks | → Task detail |

**Empty state:** ไม่มี project/task → แสดง 0 พร้อมปุ่ม "+ สร้าง Project" / "+ เพิ่ม Task"

---

## 7. Procurement & Inventory Dashboard (Admin / Finance)

**คำถามที่ต้องตอบได้ทันที:** "ต้องสั่งของอะไรเพิ่ม ของหมดคลังไหม จ่ายซัพพลายเออร์รายไหนเยอะสุด"

**Layout:**
```
[ KPI Row: 4 การ์ด ]
[ Donut: PO Status ]             [ Bar: Spend by Supplier (Top 10) ]
[ Table: Low Stock Alert ]  [ Table: PO รออนุมัติ ]  [ Table: Goods Receipt ล่าสุด ]
```

| Widget | ประเภท | ข้อมูล/สูตร | Source | Drilldown |
|---|---|---|---|---|
| Open Purchase Orders | KPI Card | count(status ใน draft/sent/approved) | purchase_orders | → PO list |
| PO รออนุมัติ | KPI Card | count(status=sent) | purchase_orders | → PO filter รออนุมัติ |
| Low Stock Items | KPI Card | count(quantity_on_hand ≤ reorder_level) | stock_levels | → Inventory filter low stock |
| Spend by Supplier (ช่วงเวลา) | KPI Card | sum(expenses.amount + PO ที่ received) by supplier | expenses, purchase_orders | → Supplier detail |
| PO Status Distribution | Donut | count group by draft/sent/approved/received/cancelled | purchase_orders | → PO filter status |
| Spend by Supplier | Bar Chart | top 10 supplier by total spend | expenses, suppliers | → Supplier detail |
| Low Stock Alert | Table | product, คลัง, quantity_on_hand, reorder_level | stock_levels, products | → Inventory detail |
| PO รออนุมัติ | Table | po_no, supplier, มูลค่า, วันที่สร้าง | purchase_orders | → PO detail (Approve) |
| Goods Receipt ล่าสุด | Table | po_no, product, จำนวนรับเข้า, วันที่ | stock_movements | → Movement detail |

---

## 8. HR & Payroll Dashboard (Admin / Owner / Finance ที่มีสิทธิ์ payroll.view)

**คำถามที่ต้องตอบได้ทันที:** "วันนี้มีใครมาทำงานบ้าง ใครขอลารออนุมัติ รอบจ่ายเงินเดือนล่าสุดเป็นยังไง"

**Layout:**
```
[ KPI Row: 4 การ์ด ]
[ Bar: Headcount by Department ]   [ Bar: Attendance สัปดาห์นี้ ]
[ Table: คำขอลารออนุมัติ ]  [ Table: พนักงานลาวันนี้ ]
```

| Widget | ประเภท | ข้อมูล/สูตร | Source | Drilldown |
|---|---|---|---|---|
| Active Employees | KPI Card | count(employment_status=active) | employees | → Employees list |
| เข้างานวันนี้ | KPI Card | count(attendances.work_date=today, clock_in_at not null) | attendances | → Attendance today |
| คำขอลารออนุมัติ | KPI Card | count(leave_requests.status=pending) | leave_requests | → Leave requests |
| รอบจ่ายล่าสุด | KPI Card | payroll_runs ล่าสุด: สถานะ + ยอดรวม | payroll_runs | → Payroll run detail |
| Headcount by Department | Bar Chart | count(employees) group by department | employees | → Employees filter dept |
| Attendance สัปดาห์นี้ | Bar Chart | present/late/absent รายวัน 7 วันล่าสุด | attendances | — |
| คำขอลารออนุมัติ | Table | พนักงาน, ประเภทลา, ช่วงวันที่ | leave_requests | → Leave request detail (Approve) |
| พนักงานลาวันนี้ | Table | พนักงาน, ประเภทลา | leave_requests, leave_balances | — |

> `salary_base` และรายละเอียดเงินเดือนเป็นข้อมูลอ่อนไหว จำกัด permission ตาม `26-employees.md`; Dashboard แสดงเฉพาะยอดรวมของรอบจ่าย ไม่แสดงเงินเดือนรายบุคคล

---

## 9. Admin / Security Dashboard (Admin)

**คำถามที่ต้องตอบได้ทันที:** "มี user ใหม่รอ invite ไหม มีความผิดปกติด้าน security ไหม ใครทำอะไรล่าสุด"

**Layout:**
```
[ KPI Row: 4 การ์ด ]
[ Donut: User by Role ]
[ Table: Pending Invites ]  [ Table: Recent Audit Log ]  [ Table: Security Alerts ]
```

| Widget | ประเภท | ข้อมูล/สูตร | Source | Drilldown |
|---|---|---|---|---|
| Total Users | KPI Card | count(users.status=active) | users | → Users list |
| Pending Invites | KPI Card | count(users.status=invited) | users | → Users filter invited |
| Security Alerts | KPI Card | เช่น failed login ผิดปกติ, 2FA ไม่เปิดในกลุ่มที่ policy บังคับ | audit_logs | → Audit log filter security |
| Audit Events วันนี้ | KPI Card | count(audit_logs created_at=today) | audit_logs | → Audit log |
| User by Role | Donut | count(user_roles) group by role | user_roles | → Users filter role |
| Pending Invites | Table | email, role, วันที่เชิญ, หมดอายุ | users | → resend/revoke invite |
| Recent Audit Log | Table | user, action, entity, เวลา | audit_logs | → Audit log detail |

---

## 10. My Work — มุมมองส่วนตัว (Member)

**คำถามที่ต้องตอบได้ทันที:** "วันนี้ฉันต้องทำอะไรบ้าง อะไรค้างอยู่"

**Layout:**
```
[ KPI Row: 3 การ์ด ]
[ Table: งานของฉัน กลุ่มตาม Project ]
```

| Widget | ประเภท | ข้อมูล/สูตร | Source | Drilldown |
|---|---|---|---|---|
| งานครบกำหนดวันนี้ | KPI Card | count(tasks.assignee=me, due_date=today) | tasks | → Task list |
| งานเกินกำหนด | KPI Card | count(is_overdue=true, assignee=me) | tasks | → Task list |
| เสร็จแล้วสัปดาห์นี้ | KPI Card | count(status=done, completed_at within 7 days) | tasks | — |
| งานของฉัน | Table | task, project, priority, due_date, สถานะ | tasks | → Task detail |

> Member **ไม่เห็น**ข้อมูลการเงิน/ยอดขาย/deal list ตาม row-level policy; เห็นเฉพาะชื่อ deal/project แบบ read-only จาก task ที่ตนถูก assign เท่านั้น

**Viewer:** ใช้ layout เดียวกับ Executive Dashboard แต่ทุก widget เป็น read-only (ไม่มีปุ่ม action เช่น Approve/Record Payment)

---

## 11. กติกากลางที่ใช้ทุก Dashboard

- ทุก query ต้อง filter ด้วย `org_id` เสมอ (multi-tenant isolation)
- Widget ที่ user ไม่มี permission module นั้น (เช่น `invoices.view`, `payroll.view`) → **ซ่อนทั้ง widget**
- Phase/สถานะโมดูลที่ยังไม่ implement จริง → ห้ามแสดงตัวเลขนั้นบน Dashboard (ห้ามแสดงเลขที่ไม่มี source table รองรับ)
- Drill-down ทุกจุดต้องพาไปหน้า list เดิมพร้อม filter ที่ตรงกับ widget ที่กด ไม่ใช่หน้า list เปล่า
- ตัวเลขที่เป็นเงิน ต้องระบุหน่วย (THB) และช่วงเวลาที่คำนวณกำกับไว้ใต้ตัวเลขเสมอ
- Cash In ที่ติดลบจาก reversal → ต้องแสดง breakdown receipt/reversal แยก ไม่ตีความเป็น Cash Out
