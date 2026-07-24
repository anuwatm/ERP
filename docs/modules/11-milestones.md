# Module: Milestones

| Meta | Value |
| --- | --- |
| Module code | `milestones` |
| Version | V1 หลัง MVP |
| Priority | P1 (Post-MVP) |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §5.2 |

---

## 1. ชื่อ Module

**Milestones** — จุดส่งมอบหลักใน project (Post-MVP)

---

## 2. รายละเอียด / หน้าที่

- กำหนดจุดส่งมอบสำคัญและ due date
- รองรับ payment milestone (amount)
- ผูก invoice ได้
- สถานะ complete / incomplete
- ใช้คำนวณ project progress

---

## 3. Workflow

### 3.1 วางแผน milestone

```text
Project → Add Milestone (name, due_date, amount)
→ sort_order
→ status=incomplete
```

### 3.2 เรียกเก็บตาม milestone

```text
Milestone ถึงกำหนดส่งมอบ
→ Create Invoice จาก milestone.amount
→ ผูก milestones.invoice_id (Post-MVP)
→ ส่ง invoice ให้ลูกค้า
```

### 3.3 ปิด milestone

```text
งานเสร็จ → status=complete, completed_at=now
→ recalculate project.progress_percent
   (เช่น completed_milestones / total * 100)
```

---

## 4. Data Flow

```text
projects 1──* milestones ──► invoices
                │
                └──► project.progress_percent (derived)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `milestones` | จุดส่งมอบ |

### Field สำคัญ

`project_id`, `name`, `due_date`, `amount`, `invoice_id`, `status`, `completed_at`, `sort_order`

### Business rules

- ลบ milestone ที่ผูก invoice แล้ว → block หรือ detach
- amount ใช้เป็น default ตอนสร้าง invoice
- progress จาก milestone เป็นหนึ่งในวิธีคำนวณ (อาจรวมกับ tasks)
