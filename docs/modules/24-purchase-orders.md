# Module: Purchase Orders

| Meta | Value |
| --- | --- |
| Module code | `purchase_orders` |
| Version | V2 |
| Priority | P2 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §6.7–6.8 |

---

## 1. ชื่อ Module

**Purchase Orders** — สั่งซื้อ/จ้าง supplier

---

## 2. รายละเอียด / หน้าที่

- สร้าง PO เลือก supplier + รายการ
- สถานะ: draft, sent, approved, received, cancelled
- ผูก expense / inventory (V2)
- export PDF
- ใช้เมื่อซื้อของหรือจ้างคู่ค้าบ่อย

---

## 3. Workflow

### 3.1 สร้างและอนุมัติ

```text
Create PO → เพิ่ม purchase_order_items
→ status draft → sent → approved
→ gen po_no
```

### 3.2 รับของ

```text
status=received
→ ถ้า track inventory: stock_movements type=in
→ optional สร้าง expense จากยอด PO
```

### 3.3 ยกเลิก

```text
status=cancelled (ถ้ายังไม่ received)
```

---

## 4. Data Flow

```text
suppliers / products
        │
        ▼
purchase_orders 1──* purchase_order_items
        │
        ├──► stock_movements (received)
        ├──► expenses (optional)
        └──► files / PDF
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `purchase_orders` | header |
| `purchase_order_items` | lines |

### Business rules

- `po_no` เป็น text 6 หลัก และ unique ต่อ org
- received แล้วไม่ควรแก้ qty โดยไม่มี adjustment
- ต้องมี supplier_id เสมอ
