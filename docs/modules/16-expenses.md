# Module: Expenses / Costs

| Meta | Value |
| --- | --- |
| Module code | `expenses` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §6.6 |

---

## 1. ชื่อ Module

**Expenses / Costs** — ค่าใช้จ่ายบริษัทและต้นทุนโครงการ

---

## 2. รายละเอียด / หน้าที่

- บันทึก expense พร้อม category
- Phase 3 สร้าง expense ได้โดยไม่ต้องผูก project; Phase 4 ค่อยผูก project เพื่อคำนวณต้นทุนงาน
- แนบ receipt
- สถานะ: draft → approved → paid (หรือ rejected)
- Dashboard expense/cash out ใน MVP; รายงานรายเดือน / by category / project cost = Post-MVP
- ใช้คำนวณ project margin

### Category เริ่มต้น

Salary, Software, Marketing, Travel, Office, Contractor, Hosting, Misc

---

## 3. Workflow

### 3.1 บันทึกค่าใช้จ่าย

```text
User สร้าง expense (title, amount, date, category)
→ optional project_id (ใช้จริงใน Phase 4) / supplier_id (Post-MVP)
→ attach receipt
→ status=draft
```

### 3.2 อนุมัติ

```text
Owner/Admin/Finance approve (permission `expenses.approve` + reauth)
→ status=approved, approved_by/at
→ ถ้าผูก project → project cost เปลี่ยนตอน query (derived)
→ notification = Post-MVP
```

### 3.3 จ่ายเงิน

```text
status=paid, paid_at
→ รายงาน expense / cash out
```

---

## 4. Data Flow

```text
[UI]
  │
  ▼
expenses ──► project cost query: sum(amount) where status in (approved, paid)
  │
  ───► suppliers (Post-MVP)
  ───► files (receipt)
  └──► Reports / Dashboard (expense, gross profit)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `expenses` | รายการค่าใช้จ่าย |

### Field สำคัญ

`expense_no`, `category`, `title`, `amount`, `expense_date`, `project_id`, `supplier_id`, `status`, `receipt_file_id`, `approved_by`, `approved_at`, `paid_at`

### Business rules

- `expense_no` เป็น text 6 หลัก และ unique ต่อ org

- ต้องมี permission `expenses.approve` ถึงจะอนุมัติ
- ห้าม cache project cost; reject/void/status change สะท้อนผ่าน aggregate query เดียวกัน (AD-05)
- soft delete + audit


