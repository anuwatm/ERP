# Module: Inventory / Stock

| Meta | Value |
| --- | --- |
| Module code | `inventory` |
| Version | V2 |
| Priority | P2 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §7.1–7.3 |

---

## 1. ชื่อ Module

**Inventory / Stock** — สินค้าคงคลัง

---

## 2. รายละเอียด / หน้าที่

- คลังสินค้า (warehouses)
- stock in / out / adjust / damage / transfer
- low stock alert
- movement history
- inventory valuation (เบื้องต้น)
- barcode/QR (optional)
- **ไม่ทำใน V1** ถ้าธุรกิจหลักเป็นบริการ

ทำงานคู่กับ `products.track_inventory = true`

---

## 3. Workflow

### 3.1 ตั้งคลังและสต็อกเริ่มต้น

```text
สร้าง warehouse
→ เลือก product ที่ track_inventory
→ stock_levels เริ่มต้น / opening balance ผ่าน stock_movements adjust
```

### 3.2 รับเข้า / จ่ายออก

```text
IN: จาก PO received หรือ manual
OUT: ขาย/ใช้งาน (optional ผูก invoice)
→ เขียน stock_movements
→ อัปเดต stock_levels.quantity_on_hand
```

### 3.3 Low stock

```text
quantity_on_hand <= reorder_level
→ notification / dashboard alert
```

---

## 4. Data Flow

```text
products (track_inventory)
        │
        ▼
warehouses ── stock_levels ── stock_movements
        ▲            ▲
        │            │
 purchase_orders   manual adjust / damage / transfer
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `warehouses` | คลัง |
| `stock_levels` | ยอดคงเหลือ |
| `stock_movements` | ประวัติเคลื่อนไหว |

### Business rules

- ทุกการเปลี่ยนยอดต้องมี movement (audit trail)
- unique (warehouse_id, product_id) ใน stock_levels
- ห้าม quantity ติดลบถ้า policy ไม่ให้ oversell
- valuation method ระบุตอน implement (เช่น average cost)
