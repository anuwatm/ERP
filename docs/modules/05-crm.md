# Module: CRM (Customers)

| Meta | Value |
| --- | --- |
| Module code | `customers` / `crm` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §4.1, §4.4 |

---

## 1. ชื่อ Module

**CRM** — จัดการลูกค้าและความสัมพันธ์

---

## 2. รายละเอียด / หน้าที่

- CRUD ลูกค้า (บริษัท)
- เก็บข้อมูลติดต่อ, ประเภท, owner, tags, source
- บันทึก activity (call, meeting, email, LINE, note)
- ตั้ง follow-up date
- ดู timeline ลูกค้า
- ค้นหา/กรองลูกค้า; แนบไฟล์และ import/export CSV = Post-MVP
- เป็นจุดเริ่มของ flow: Customer → Deal → Invoice/Payment; Phase 4 ค่อยต่อ Project/Task

> Contact แยก module (`contacts`) แต่ทำงานคู่กัน

---

## 3. Workflow

### 3.1 สร้างลูกค้า

```text
Sales/Admin กรอก company_name + contact info
→ gen customer_code (number_sequences)
→ บันทึก customers (type=lead/prospect)
→ optional สร้าง primary contact
→ audit_logs create customer
```

### 3.2 ดูแลความสัมพันธ์

```text
เปิด Customer Detail
→ บันทึก activity + follow_up_at
→ mark follow-up done ด้วย `activities.completed_at`
→ อัปเดต status/type (lead → active)
→ แนบไฟล์สำคัญ (Post-MVP)
→ ดู timeline รวม activity/deal/invoice
```

### 3.3 เปลี่ยนมือดูแล

```text
เปลี่ยน owner_id
→ audit update
→ optional notification ถึง owner ใหม่
```

---

## 4. Data Flow

```text
[UI]
        │
        ▼
   customers ◄──► contacts
        │
        ├──► activities (entity_type=customer)
        ├──► deals.customer_id
        ├──► invoices / projects
        ├──► quotations (Post-MVP)
        └──► files (entity customer)
```

**Outbound**

| Module | ใช้ |
| --- | --- |
| Deals | customer_id |
| Invoices | billing party |
| Quotations | billing party (Post-MVP) |
| Projects | client |
| Dashboard / Reports | top customers, activity |
| Customer Portal (V3) | portal_users.customer_id |

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `customers` | master ลูกค้า |

### ตารางร่วม

| Table | Role |
| --- | --- |
| `contacts` | ผู้ติดต่อใต้ลูกค้า |
| `activities` | timeline / follow-up |
| `files` | เอกสารแนบ (Post-MVP; MVP ใช้จำกัดกับ payment/expense attachment) |
| `deals`, `projects`, `invoices` | child records |

### Field สำคัญ (`customers`)

`customer_code`, `company_name`, `tax_id`, `customer_type`, `status`, `owner_id`, `phone`, `email`, `line_id`, `address`, `source`, `tags`

### Business rules

- `customer_code` เป็น text 6 หลัก และ unique ใน org
- soft delete ถ้ามี deal/invoice ผูกอยู่
- filter ตาม owner สำหรับ role Member/Sales ตาม policy

