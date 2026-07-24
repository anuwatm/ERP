# Module: Payroll

| Meta | Value |
| --- | --- |
| Module code | `payroll` |
| Version | V3 |
| Priority | P3 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §8.5–8.6 |

---

## 1. ชื่อ Module

**Payroll** — เงินเดือนและสลิป

---

## 2. รายละเอียด / หน้าที่

- คำนวณเงินเดือน (base, allowance, deduction)
- สร้าง payslip
- อนุมัติรอบจ่าย (payroll run)
- รายงาน payroll

**ข้อควรระวัง:** เกี่ยวข้องกฎหมาย/ภาษี — ไม่ทำเต็มในระยะแรก  
ระบบนี้เป็น operational payroll เบื้องต้น ไม่ทดแทนระบบบัญชีภาษี

---

## 3. Workflow

### 3.1 เปิดรอบจ่าย

```text
สร้าง payroll_runs (year, month) status=draft
→ gen payslips ต่อ employee จาก salary_base + adjustments
→ ตรวจทาน
→ approve → status=approved
→ mark paid → status=paid
```

### 3.2 ออก payslip

```text
Employee/HR ดู payslip
→ export PDF (optional)
```

---

## 4. Data Flow

```text
employees.salary_base (+ attendance/leave adjustments optional)
                │
                ▼
         payroll_runs 1──* payslips
                │
                └──► Reports / export
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `payroll_runs` | รอบจ่ายรายเดือน |
| `payslips` | สลิปต่อคน |

### Business rules

- 1 employee ต่อ run ไม่ซ้ำ
- หลัง approved แก้ได้จำกัด + audit
- ข้อมูลเงินเดือน = sensitive — RBAC เข้ม
- ไม่เก็บเป็น book of record ภาษี
