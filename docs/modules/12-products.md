# Module: Product / Service Catalog

| Meta | Value |
| --- | --- |
| Module code | `products` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | C`../database/DATABASE.md`](../database/DATABASE.md) §6.1 |

---

## 1. ชื่อ Module

**Product / Service Catalog** — รายการสินค้า/บริการมาตรฐาน

---

## 2. รายละเอียด / หน้าที่

- เก็บ master ของสินค้า บริการ แพ็กเกจ
- กำหนด price, cost, unit, category, SKU
- เปิด/ปิดใช้งาน (`is_active`)
- ใช้ซ้ำใน quotation และ invoice line items
- V2: เปิด `track_inventory` เชื่อมสต็อก

**Type:** product | service | package

---

## 3. Workflow

### 3.1 จัดการ catalog

```text
Admin/Finance เพิ่ม product
→ ตั้ง price/cost/unit
→ is_active=true
→ ใช้เลือกตอนสร้าง quote/invoice
```

### 3.2 ใช้งานในเอกสาร

```text
Quotation/Invoice Add line
→ เลือก product_id
→ copy name, unit_price, unit ไป line item
→ user แก้ราคา line ได้ (snapshot)
```

### 3.3 ปิดใช้งาน

```text
is_active=false
→ ไม่โชว์ใน picker
→ เอกสารเก่ายังอ้าง product_id ได้
```

---

## 4. Data Flow

```text
CCatalog UI / Import CSV]
          │
          ▼
       products
          │
          ├──► quotation_items.product_id
          ├──► invoice_items.product_id
          ├──► purchase_order_items.product_id (V2)
          └──► stock_levels / stock_movements (V2, ถ้า track_inventory)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `products` | master catalog |

### Field สำคัญ

`sku`, `name`, `type`, `category`, `unit`, `price`, `cost`, `is_active`, `track_inventory`

### Business rules

- `sku` unique ต่อ org ถ้ามีค่า
- soft delete ถ้าเคยถูกใช้ในเอกสาร
- ราคาใน line item เป็น snapshot ไม่ sync ย้อนจาก master อัตโนมัติ
