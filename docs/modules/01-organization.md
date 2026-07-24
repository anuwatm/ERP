# Module: Organization / Branch / Division / Department

| Meta | Value |
| --- | --- |
| Module code | `organization` |
| Version | V1 (MVP foundation), multi-branch operation/report เต็มใน V3 |
| Priority | P0 (MVP foundation) |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §3.1–3.5 |

---

## 1. ชื่อ Module

**Organization / Branch / Division / Department** — โครงสร้างบริษัทหลายชั้น

---

## 2. รายละเอียด / หน้าที่

Module นี้เป็น **tenant root** ของระบบ Company OS

โครงสร้างที่ล็อก:

```text
บริษัท (organizations)
  └── สาขา (branches)
      └── ฝ่าย (divisions)
          └── แผนก (departments)
              └── ผู้ใช้งาน (users)
```

- เก็บข้อมูลบริษัท (`organizations`)
- แบ่งสาขา (`branches`)
- แบ่งฝ่าย (`divisions`)
- แบ่งแผนก (`departments`)
- ผูกผู้ใช้งานเข้ากับสาขา/ฝ่าย/แผนก
- เป็นฐานของ tenant isolation ผ่าน `org_id`

**ไม่ทำใน module นี้:** login, permission รายละเอียด (ดู User/Role), settings เอกสาร (ดู Settings)

---

## 3. Workflow

### 3.1 Onboarding บริษัทใหม่

```text
1. สร้าง organization (ชื่อ, currency, timezone)
2. สร้าง head office branch (is_head_office = true)
3. สร้าง division เริ่มต้น เช่น Operations หรือ General
4. สร้าง department เริ่มต้นใต้ division เช่น Sales, Delivery, Finance
5. ผูก user คนแรก (Owner) กับ org + branch + division + department
```

### 3.2 เพิ่มโครงสร้างบริษัท

```text
Admin เปิด Organization settings
→ Add branch
→ Add division ใต้ branch
→ Add department ใต้ division
→ Assign users เข้า branch/division/department
```

### 3.3 ย้ายผู้ใช้งาน

```text
เลือก user
→ เปลี่ยน branch/division/department
→ validate hierarchy อยู่ใน org เดียวกัน
→ audit_logs
```

---

## 4. Data Flow

```text
organizations
    │
    ▼
branches
    │
    ▼
divisions
    │
    ▼
departments
    │
    ▼
users
    │
    ▼
ทุกตารางธุรกิจอื่นใช้ org_id เดียวกัน
```

**Outbound ไป module อื่น**

| Target | ใช้ข้อมูล |
| --- | --- |
| User / Role | org membership + branch/division/department assignment |
| Settings | company profile, currency, timezone |
| Invoices | branch_id สำหรับ numbering (optional) |
| Dashboard / Reports | filter by branch/division/department (Post-MVP สำหรับ reports เต็ม) |
| Employees | branch/division/department mapping (Post-MVP) |

---

## 5. โครงสร้าง Database (อ้างอิงกลาง)

> นิยามเต็มอยู่ใน DATABASE.md — ส่วนนี้เป็น **ownership view** เท่านั้น

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `organizations` | tenant root / บริษัท |
| `branches` | สาขา |
| `divisions` | ฝ่าย |
| `departments` | แผนก |

### ตารางที่เกี่ยวข้อง (shared)

| Table | Relationship |
| --- | --- |
| `users` | `org_id`, `branch_id`, `division_id`, `department_id` |
| `employees` | `branch_id`, `division_id`, `department_id` (Post-MVP) |
| `invoices` | `branch_id` optional |
| `settings` | key-value ต่อ org |

### ความสัมพันธ์สำคัญ

```text
organizations 1──* branches
branches      1──* divisions
divisions     1──* departments
departments   1──* users
organizations 1──* users
```

### Business rules

- ทุก business query ต้อง scope ด้วย `org_id`
- `branch.code` เป็น text 6 หลัก และ unique ภายใน org
- `division.code` เป็น text 6 หลัก และ unique ภายใน branch
- `department.code` เป็น text 6 หลัก และ unique ภายใน division
- user อยู่ได้ 1 branch / 1 division / 1 department ใน MVP
- branch/division/department ของ user ต้องอยู่ใน org เดียวกัน
- อย่างน้อย 1 branch, 1 division, 1 department ต่อ org ตอน register
- ห้าม hard-delete org ที่มีข้อมูลธุรกิจ — ใช้ status = suspended
