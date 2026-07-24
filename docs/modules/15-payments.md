# Module: Payments

| Meta | Value |
| --- | --- |
| Module code | `payments` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §6.5 |

---

## 1. ชื่อ Module

**Payments** — บันทึกเงินเข้าจากลูกค้า

---

## 2. รายละเอียด / หน้าที่

- บันทึก payment ให้ invoice
- รองรับ partial payment
- วิธีชำระ: bank transfer, cash, credit card, promptpay, other
- แนบหลักฐานการโอน
- อัปเดตสถานะ invoice อัตโนมัติ
- รายงาน payment

---

## 3. Workflow

### 3.1 รับชำระเต็มจำนวน

```text
เปิด Invoice → Record Payment
→ amount = balance_due
→ payment_method + payment_date + reference_no
→ optional attach slip (files)
→ บันทึก payments
→ invoices.paid_amount += amount
→ invoices.balance_due = 0, status=paid
→ notification payment_received (optional)
→ audit_logs
```

### 3.2 รับชำระบางส่วน

```text
amount < balance_due
→ status invoice = partially_paid
→ เก็บ history payments หลายรายการต่อ invoice
```

### 3.3 แก้ไข/ยกเลิก payment

```text
Policy: เฉพาะ Finance/Admin
→ ห้ามแก้/ลบ posted payment
→ สร้าง reversal entry อ้าง receipt เดิม
→ lock invoice
→ recalculate paid_amount, balance_due, status
→ audit_logs
```

---

## 4. Data Flow

```text
invoices
    │
    ▼
payments ──► files (attachment_file_id)
    │
    ├──► update invoices (paid_amount, status)
    ├──► Dashboard Cash In / Outstanding AR
    └──► accounting_sync_logs (V2)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `payments` | รายการเงินเข้า |

### Field สำคัญ

`invoice_id`, `entry_type`, `reversal_of_payment_id`, `amount`, `payment_date`, `payment_method`, `reference_no`, `attachment_file_id`, `note`, `idempotency_key`

### Business rules

- amount > 0
- receipt และ reversal เก็บ amount เป็นบวก
- payment ที่ post แล้วแก้หรือลบไม่ได้
- ยกเลิก payment ด้วย reversal entry หนึ่งรายการต่อ receipt
- sum(receipt) - sum(reversal) ต่อ invoice ต้อง ≤ invoice.total
- create/reversal ต้อง lock invoice และ recalc invoice ใน DB transaction เดียว
- invoice ที่มี payment ต้อง reverse payment ครบก่อนจึง void ได้
- ต้องใช้ idempotency key กัน submit/retry ซ้ำ
- ทุกการบันทึกต้อง audit


