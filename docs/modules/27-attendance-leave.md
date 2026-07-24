# Module: Attendance / Leave

| Meta | Value |
| --- | --- |
| Module code | `attendance_leave` |
| Version | V2 |
| Priority | P2 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §8.2–8.4 |

---

## 1. ชื่อ Module

**Attendance / Leave** — ระบบเวลาและวันลา

---

## 2. รายละเอียด / หน้าที่

- Clock in / clock out
- Leave request + approval
- Leave balance
- Attendance report + calendar view

---

## 3. Workflow

### 3.1 ลงเวลา

```text
Employee clock in
→ attendances (work_date, clock_in_at)
→ เลิกงาน clock_out_at
→ status present/late ตาม rule
```

### 3.2 ขอลา

```text
สร้าง leave_requests (type, date range, reason)
→ status=pending
→ แจ้ง approver
→ approved/rejected
→ ถ้า approved: บวก used_days ใน leave_balances
```

---

## 4. Data Flow

```text
employees
    │
    ├──► attendances
    ├──► leave_requests ──► leave_balances
    └──► notifications (approval)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `attendances` | ลงเวลาต่อวัน |
| `leave_requests` | คำขอลา |
| `leave_balances` | วันลาคงเหลือต่อปี |

### Business rules

- unique (employee_id, work_date)
- leave ต้องไม่ชนช่วงที่ approved แล้ว (validate)
- อนุมัติต้องมี permission เหมาะสม
