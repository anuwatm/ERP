# Module: Contacts

| Meta | Value |
| --- | --- |
| Module code | `contacts` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §4.2 |

---

## 1. ชื่อ Module

**Contacts** — แยก “คน” ออกจาก “บริษัท”

---

## 2. รายละเอียด / หน้าที่

- รองรับลูกค้า/คู่ค้า 1 ราย มีหลายผู้ติดต่อ
- กำหนดตำแหน่ง, contact หลัก (`is_primary`)
- เก็บช่องทางติดต่อและ note เฉพาะคน
- ผูก contact กับ deal / project / invoice ได้
- MVP ใช้ฝั่ง `customer_id` เท่านั้น; `supplier_id` เป็น Post-MVP

---

## 3. Workflow

### 3.1 เพิ่ม contact ใต้ลูกค้า

```text
เปิด Customer Detail → Add Contact
→ กรอก name, position, phone, email, line_id
→ ถ้า is_primary=true ให้ unset primary เดิม
→ บันทึก contacts
```

### 3.2 ใช้ contact ในดีล

```text
สร้าง/แก้ไข Deal
→ เลือก customer
→ เลือก contact_id จากรายการของ customer นั้น
→ activity อาจอ้างถึง contact ในเนื้อหา
```

### 3.3 Contact ฝั่ง supplier

```text
Supplier Detail → Add Contact
→ contacts.supplier_id = supplier
→ ใช้ตอนติดตาม PO / billing
```

---

## 4. Data Flow

```text
customers ──1:*── contacts ──*── deals.contact_id
suppliers ──1:*── contacts (Post-MVP)
                      │
                      └── activities / notes (optional)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `contacts` | ผู้ติดต่อ |

### Constraints

- MVP: ต้องมี `customer_id` เสมอ
- Post-MVP: เมื่อเปิด suppliers ค่อยอนุญาต `supplier_id` และใช้ rule `customer_id` หรือ `supplier_id` อย่างน้อยหนึ่งค่า
- แนะนำ unique partial: หนึ่ง primary ต่อ customer

### Field สำคัญ

MVP: `name`, `position`, `phone`, `email`, `line_id`, `is_primary`, `note`, `customer_id`  
Post-MVP: เพิ่ม `supplier_id`

### Business rules

- ลบ contact ที่มี deal อ้างอิง → soft delete หรือ block
- primary contact ใช่ default ตอนสร้าง quotation/invoice


