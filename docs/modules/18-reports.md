# Module: Reports

| Meta | Value |
| --- | --- |
| Module code | `reports` |
| Version | V1 |
| Priority | P0 backlog (Post-MVP) |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §11 |

---

## 1. ชื่อ Module

**Reports** — รายงานเพื่อช่วยตัดสินใจ

---

## 2. รายละเอียด / หน้าที่

Post-MVP: สร้างรายงานกรองได้ตามวันที่ / ลูกค้า / owner และ export CSV/PDF. MVP ใช้ Dashboard mini เท่านั้น.

### รายงาน V1 หลัง MVP

- Revenue report
- Expense report
- Profit report
- Invoice aging
- Unpaid invoices
- Pipeline report
- Deal win/loss report
- Project profitability
- Task overdue report
- Customer activity report

Saved views → V2

---

## 3. Workflow

### 3.1 รันรายงาน

```text
เลือกประเภทรายงาน
→ ตั้ง filter (date, customer, owner, branch)
→ query + aggregate
→ แสดงตาราง/กราฟ
→ export CSV/PDF (Post-MVP)
```

### 3.2 Aging

```text
กลุ่ม unpaid invoices ตามอายุ:
0–30 / 31–60 / 61–90 / 90+ วัน จาก due_date
```

---

## 4. Data Flow

```text
[Report params]
      │
      ▼
Report service ──read──► invoices, payments, expenses,
                         deals, projects, tasks, activities, customers
      │
      ├──► UI table/chart
      └──► Export file (CSV/PDF) + audit export action
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

*ไม่มีใน V1* (query ตรง; MVP ไม่มี reports module แยก)

### Optional V2

| Table idea | Role |
| --- | --- |
| `saved_report_views` | เก็บ filter ของ user — ยังไม่ใส่ schema กลางจนกว่าจะ implement |

### Business rules

- export ต้องมี permission `reports.export`
- ตัวเลขต้องสอดคล้องนิยามเดียวกับ Dashboard
- ไม่ expose ข้อมูลข้าม org
