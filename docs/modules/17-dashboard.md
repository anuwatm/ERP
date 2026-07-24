# Module: Dashboard

| Meta | Value |
| --- | --- |
| Module code | `dashboard` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §11 (ไม่มีตารางเฉพาะ) |

---

## 1. ชื่อ Module

**Dashboard** — หน้ารวมสถานะบริษัท (ดูทุกเช้า)

---

## 2. รายละเอียด / หน้าที่

เป็น **read/aggregate layer** ไม่เป็นเจ้าของตารางธุรกิจ

แสดง:

- Invoiced Revenue, Cash In, Outstanding AR, Overdue AR
- Recognized Expense, Cash Out, Gross Profit
- invoice ค้างชำระ, pipeline value, deal by stage
- project กำลังทำ, task overdue, follow-up วันนี้
- top customers, recent activity
- กรองช่วงเวลา: today, week, month, custom
- export summary PDF/CSV (Post-MVP)


### Dashboard by Phase

Dashboard ต้องเริ่มตั้งแต่ Phase 1 และขยายตามข้อมูลที่มีจริงในแต่ละ Phase ห้ามแสดงตัวเลขที่ยังไม่มี source table รองรับ

| Phase | Dashboard | Widgets |
| --- | --- | --- |
| Phase 1 | Admin Dashboard | Organization Setup, Users Summary, Role Summary, Security Alerts, Recent Audit |
| Phase 2 | Sales Dashboard | Customers, Deals Pipeline, Won/Lost Deals, Follow-ups Today, Stale Deals, Top Sales Owners |
| Phase 3 | Finance Dashboard | Invoiced Revenue, Cash In, Outstanding AR, Overdue AR, Expenses, Net Cash Flow, Invoice Status, Payment Reversal |
| Phase 4 | Delivery Dashboard | Active Projects, Project Status, Overdue Tasks, Task Load, Project Budget vs Expense, Project Profit Snapshot, Delivery Risk |
| Phase 5 | Executive Dashboard | Revenue, Cash In, Outstanding AR, Overdue AR, Expenses, Gross Profit, Pipeline, Active Projects, Overdue Tasks, Collection Rate, Recent Activity |

**Rules:**

- Phase 1 ห้ามโชว์ sales/finance/project metrics
- Phase 2 ห้ามโชว์ Cash In เพราะยังไม่มี payment
- Phase 3 ต้องแยก `Invoiced Revenue` กับ `Cash In`
- Phase 4 เพิ่ม delivery health และ project profitability จากข้อมูลจริง
- Phase 5 รวมทุก widget เป็น executive summary และใช้ทดสอบ end-to-end


### Empty states

| Phase | Empty state |
| --- | --- |
| Phase 1 | ไม่มี invited user ให้แสดง action invite; ไม่มี security alert ให้แสดงสถานะปลอดภัย |
| Phase 2 | ไม่มี customer/deal ให้แสดง 0 และปุ่มสร้าง customer/deal |
| Phase 3 | ไม่มี invoice/payment ให้แสดง finance metric เป็น 0; ห้ามซ่อน Invoiced Revenue/Cash In |
| Phase 4 | ไม่มี project/task ให้แสดง 0 และปุ่มสร้าง project/task |
| Phase 5 | widget ที่ไม่มี permission ต้องถูกซ่อน; widget ที่มี permission แต่ไม่มีข้อมูลให้แสดง 0/empty state |

### Metric หลัก

Invoiced Revenue, Cash In, Outstanding AR, Overdue AR, Recognized Expense, Cash Out, Gross Profit, Pipeline Value, Win Rate, Overdue Tasks, Unpaid Invoices, Project Margin

---

## 3. Workflow

### 3.1 เปิด Dashboard

```text
User login → โหลด widgets ตาม permission
→ apply date range + optional branch filter
→ query aggregate จากหลายตาราง
→ cache สั้น ๆ (optional)
```

### 3.2 Drill-down

```text
คลิก widget (เช่น Unpaid Invoices)
→ ไป list หน้า Invoices filter status
```

### 3.3 Export summary (Post-MVP)

```text
Post-MVP: Export PDF/CSV ของตัวเลขช่วงเวลาที่เลือก
→ ตรวจ permission reports.export / dashboard.export
```

---

## 4. Data Flow

```text
invoices + payments ──► Invoiced Revenue / Cash In / AR
expenses ─────────────► Recognized Expense / Cash Out
deals ────────────────► Pipeline / Win rate
projects + tasks ─────► Delivery health
activities ───────────► Follow-ups today
customers ────────────► Top customers
        │
        ▼
   Dashboard API (aggregate)
        │
        ▼
   Dashboard UI
```


### Metric glossary

| Metric | Meaning |
| --- | --- |
| Invoiced Revenue | ยอดเปิด invoice ไม่รวม void |
| Cash In | เงินรับจริงสุทธิหลังหัก reversal; Dashboard ต้องแสดง receipt/reversal แยกใน drilldown เพื่ออธิบายวันที่ที่ยอดสุทธิติดลบ |
| Recognized Expense | ค่าใช้จ่ายที่ approved/paid ตาม expense_date |
| Cash Out | ค่าใช้จ่ายที่ paid แล้วตาม paid_at |
| Gross Profit | Invoiced Revenue - Recognized Expense; ไม่ใช่เงินสดคงเหลือ |
| Net Cash Flow | Cash In - Cash Out; ไม่ใช่ Cash Balance |

### ตัวอย่างการคำนวณ (MVP)

| Metric | แหล่ง |
| --- | --- |
| Invoiced Revenue | sum(invoices.total) by issue_date, status sent/partially_paid/paid/overdue |
| Cash In | sum(payments receipt) - sum(payments reversal) by payment_date |
| Follow-ups Today | activities ที่ `follow_up_at` เป็นวันนี้ และ `completed_at IS NULL` |
| Outstanding AR | sum(invoices.balance_due) status sent/partially_paid/overdue |
| Overdue AR | sum(invoices.balance_due) ที่ due_date < today และยังไม่ paid/void |
| Recognized Expense | sum(expenses.amount) by expense_date, status approved/paid |
| Cash Out | sum(expenses.amount) by paid_at, status paid |
| Gross Profit | Invoiced Revenue - Recognized Expense |
| Net Cash Flow | Cash In - Cash Out |
| Pipeline Value | sum(deals.value_amount) ที่ยังไม่ won/lost |
| Unpaid Invoices | count/sum invoices ที่ balance_due > 0 |
| Overdue Tasks | count tasks.is_overdue = true |

> MVP ไม่มี opening balance / bank reconciliation จึงห้ามแสดง Cash Balance เป็นยอดเงินจริง

> Reversal ใช้ `payment_date` ของวันที่ทำรายการจริง. ถ้าวันใด Cash In สุทธิติดลบจาก reversal ให้แสดง breakdown receipt/reversal ชัดเจน ไม่ตีความเป็น Cash Out.

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

*ไม่มี* — ใช้ view / query / materialized view ได้ภายหลัง

### ตารางที่อ่าน (shared)

`invoices`, `payments`, `expenses`, `deals`, `projects`, `tasks`, `activities`, `customers`, `organizations`, `branches`

### Optional later

- `dashboard_snapshots` (cache รายวัน) — Post-MVP

### Business rules

- ทุก query ต้อง filter `org_id`
- ซ่อน widget ตาม permission (เช่น finance widgets เฉพาะ Finance/Owner)
- Member อาจเห็นเฉพาะงานตัวเอง




