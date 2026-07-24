# Module: Quotations

| Meta | Value |
| --- | --- |
| Module code | `quotations` |
| Version | V1 หลัง MVP |
| Priority | P1 (Post-MVP) |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §4.5–4.6 |

---

## 1. ชื่อ Module

**Quotations** — ใบเสนอราคาก่อนออก invoice

---

## 2. รายละเอียด / หน้าที่

- สร้าง quotation จาก deal หรือจากลูกค้าตรง
- รายการสินค้า/บริการหลายบรรทัด (qty, price, discount, tax)
- คำนวณ subtotal / tax / total
- สถานะ: draft, sent, accepted, rejected, expired
- วันหมดอายุ, export PDF, ส่ง link ลูกค้า
- Convert เป็น invoice หรือ project
- Versioning เบื้องต้น (`version`)

---

## 3. Workflow

### 3.1 สร้างและส่ง

```text
Deal (proposal stage) → Create Quotation
→ เพิ่ม quotation_items จาก products หรือ custom line
→ คำนวณยอด
→ status draft → sent
→ gen quotation_no จาก number_sequences
→ export PDF / share link
```

### 3.2 ลูกค้าตอบรับ

```text
status = accepted
→ Convert to Invoice และ/หรือ Project
→ audit log
```

### 3.3 หมดอายุ / ปฏิเสธ

```text
Job: valid_until < today และ status=sent → expired
หรือ user set rejected
```

### 3.4 Versioning

```text
แก้ไข quotation ที่ sent แล้ว
→ clone เป็น version ใหม่ (version + 1)
→ เก็บประวัติเดิม
```

---

## 4. Data Flow

```text
deals / customers / products
            │
            ▼
     quotations 1──* quotation_items
            │
            ├──► invoices (convert)
            └──► projects (optional convert)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `quotations` | header |
| `quotation_items` | line items |

### Field เงิน

`subtotal`, `discount_amount`, `tax_amount`, `total`

### Business rules

- line_total คำนวณจาก qty × unit_price − discount (+ tax ตามนโยบาย)
- `quotation_no` เป็น text 6 หลัก และ unique ต่อ org
- accepted quotation เป็นแหล่งสร้าง invoice ได้
- ห้ามแก้ยอดแบบเงียบ ๆ หลัง accepted โดยไม่มี version/audit
