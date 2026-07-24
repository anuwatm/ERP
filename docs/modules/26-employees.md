# Module: HR — Employees

| Meta | Value |
| --- | --- |
| Module code | `employees` |
| Version | V1 light, ใช้จริงขึ้นใน V2 |
| Priority | P1 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §8.1 |

---

## 1. ชื่อ Module

**HR: Employees** — ข้อมูลพนักงาน

---

## 2. รายละเอียด / หน้าที่

- ขึ้นทะเบียนพนักงาน
- ข้อมูลติดต่อ, ตำแหน่ง, department, branch
- employment status, start/end date
- salary base (optional)
- map กับ user account (`user_id`)
- เก็บเอกสารพนักงาน (ผ่าน Files)

ไม่รวม payroll เต็ม (แยก module V3)

---

## 3. Workflow

### 3.1 รับพนักงานใหม่

```text
HR/Admin สร้าง employee
→ gen employee_code
→ optional สร้าง/ผูก user login + role
→ อัปโหลดเอกสาร
→ status=active
```

### 3.2 ปรับตำแหน่ง/แผนก

```text
อัปเดต position, department_id, branch_id
→ audit
```

### 3.3 ลาออก

```text
employment_status=resigned, end_date
→ optional deactivate linked user
```

---

## 4. Data Flow

```text
users / departments / branches
            │
            ▼
        employees
            │
            ├──► attendances / leave_* (V2)
            ├──► payslips (V3)
            └──► files (documents)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `employees` | master พนักงาน |

### Field สำคัญ

`user_id`, `employee_code`, `first_name`, `last_name`, `position`, `department_id`, `branch_id`, `employment_status`, `start_date`, `salary_base`

### Business rules

- `user_id` unique ถ้ามี (1 user ต่อ 1 employee)
- แยก employee ออกจาก users เพราะมีพนักงานที่ไม่มี login
- salary_base เป็นข้อมูลอ่อนไหว — จำกัด permission
