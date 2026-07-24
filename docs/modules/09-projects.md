# Module: Projects

| Meta | Value |
| --- | --- |
| Module code | `projects` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §5.1 |

---

## 1. ชื่อ Module

**Projects** — จัดการงานส่งมอบหลังปิดการขาย (Phase 4)

---

## 2. รายละเอียด / หน้าที่

- สร้าง project จาก deal won หรือสร้างเอง
- กำหนด owner, customer, วันที่, budget, status
- ดู board / progress
- Phase 4 ผูก invoice/expense ที่สร้างไว้ก่อนหน้าเข้ากับ project ได้
- คำนวณ project margin
- milestone และเอกสารที่เกี่ยวข้อง = Post-MVP

**Status:** planned, active, on_hold, completed, cancelled

---

## 3. Workflow

### 3.1 Kickoff จาก deal

```text
Deal stage=won → Create Project
→ copy customer_id, deal_id, budget จาก value
→ gen project_code
→ status=planned/active
→ สร้าง tasks เริ่มต้น (milestones = Post-MVP)
```

### 3.2 ดำเนินโครงการ

```text
Team ทำงานผ่าน Tasks
→ อัปเดต progress_percent แบบ manual (MVP)
→ derive progress จาก milestone/task = Post-MVP
→ บันทึก expenses → project cost (derived sum)
→ ออก invoice ตาม project/manual (milestone billing = Post-MVP)
```

### 3.3 ปิดโครงการ

```text
status=completed
→ ตรวจ unpaid invoice / open tasks
→ Review profitability = revenue(invoices) - project cost (expenses approved/paid)
```

---

## 4. Data Flow

```text
deals / customers
        │
        ▼
     projects
        │
        ├──► tasks
        ├──► milestones ──► invoices
        ├──► expenses (cost)
        ├──► invoices (revenue)
        └──► files
                │
                ▼
        Dashboard (active projects) / Reports (margin)
```

**Margin formula (แนวทาง)**

```text
Project Revenue = sum(invoices.total where project_id and status in sent/partially_paid/paid/overdue)
Project Cost    = sum(expenses.amount) approved/paid ของ project (ไม่มี cache column)
Margin          = Revenue - Cost
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `projects` | master โครงการ |

### ตารางร่วม

`tasks`, `invoices`, `expenses`, `deals`, `customers`; `milestones`, `files` = Post-MVP

> MVP ยังไม่มี `project_members`; การเห็น project ใช้ `owner_id` เท่านั้น. ถ้าต้องการทีมหลายคนใน project ให้เพิ่ม `project_members` หลัง MVP ก่อนขยาย visibility rule.

### Field สำคัญ

`project_code`, `name`, `customer_id`, `deal_id`, `owner_id`, `status`, `start_date`, `due_date`, `budget_amount`, `progress_percent`  
(ไม่มี `actual_cost` — ดู AD-05)

### Business rules

- `project_code` เป็น text 6 หลัก และ unique ต่อ org
- ห้าม soft delete project ที่มี open invoice/expense; ใช้ status `cancelled` แทนจนกว่าเอกสารการเงินปิด
- progress 0–100; MVP = manual only, derive จาก tasks/milestones = Post-MVP


