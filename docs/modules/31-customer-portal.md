# Module: Customer Portal

| Meta | Value |
| --- | --- |
| Module code | `customer_portal` |
| Version | V3 |
| Priority | P3 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §9.13 |

---

## 1. ชื่อ Module

**Customer Portal** — ให้ลูกค้าดูและดำเนินการเอกสารเอง

---

## 2. รายละเอียด / หน้าที่

- Login สำหรับลูกค้า (คนละชุดกับ internal users)
- ดู quotations / approve quotation
- ดู invoices
- อัปโหลดไฟล์
- comment ใน project (optional)
- payment link

---

## 3. Workflow

### 3.1 เปิดสิทธิ์ portal

```text
Admin เชิญ portal user จาก customer
→ สร้าง portal_users (email, password)
→ ส่ง invite
```

### 3.2 ลูกค้าใช้งาน

```text
Login portal
→ เห็นเฉพาะข้อมูล customer_id ของตน
→ ดู quote → approve
→ ดู invoice → เปิด payment link
→ upload file ผูก entity ที่อนุญาต
```

---

## 4. Data Flow

```text
portal_users ──► customers
                    │
                    ├──► quotations (read/approve)
                    ├──► invoices (read)
                    ├──► projects (limited)
                    └──► files
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `portal_users` | บัญชีลูกค้าภายนอก |

### ตารางร่วม (read/limited write)

`customers`, `quotations`, `invoices`, `projects`, `files`

### Business rules

- แยก auth ออกจาก `users` ภายใน
- บังคับ scope เฉพาะ `customer_id` ของ session
- approve quotation ต้อง audit
- ห้ามเห็นข้อมูลลูกค้ารายอื่น / ข้อมูลภายในบริษัท
