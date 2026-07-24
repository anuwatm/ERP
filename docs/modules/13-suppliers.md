# Module: Suppliers

| Meta | Value |
| --- | --- |
| Module code | `suppliers` |
| Version | V1 หลัง MVP |
| Priority | P1 (Post-MVP) |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §6.2 |

---

## 1. ชื่อ Module

**Suppliers** — จัดการผู้ขาย / คู่ค้า

---

## 2. รายละเอียด / หน้าที่

- CRUD supplier
- เก็บ contact, ที่อยู่, payment terms
- ผูกกับ expense
- ผูก purchase order ใน V2
- รายงานการใช้จ่ายตาม supplier

---

## 3. Workflow

### 3.1 ขึ้นทะเบียนคู่ค้า

```text
Finance/Admin เพิ่ม supplier
→ gen supplier_code
→ เพิ่ม contacts (optional)
→ status=active
```

### 3.2 ใช้กับค่าใช้จ่าย

```text
สร้าง Expense
→ เลือก supplier_id
→ แนบ receipt
→ approve / paid
```

### 3.3 ใช้กับ PO (V2)

```text
Create Purchase Order → supplier_id
→ receive / ผูก inventory
```

---

## 4. Data Flow

```text
suppliers 1──* contacts
    │
    ├──► expenses.supplier_id
    ├──► purchase_orders.supplier_id (V2)
    └──► Reports (spend by supplier)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `suppliers` | master คู่ค้า |

### Field สำคัญ

`supplier_code`, `name`, `tax_id`, `email`, `phone`, `address`, `payment_terms`, `status`

### Business rules

- `supplier_code` เป็น text 6 หลัก และ unique ต่อ org
- soft delete ถ้ามี expense/PO
- แยกชัดจาก customers (คนละตาราง) แม้บริษัทเดียวกันอาจมี 2 บทบาท

