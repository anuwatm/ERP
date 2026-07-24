# Module: Accounting Integration

| Meta | Value |
| --- | --- |
| Module code | `accounting_integration` |
| Version | V2 |
| Priority | P2 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §9.11 |

---

## 1. ชื่อ Module

**Accounting Integration** — เชื่อมระบบบัญชีภายนอก

---

## 2. รายละเอียด / หน้าที่

ระบบ ERP นี้เป็น **operational layer**  
บัญชีภาษี / book of record ให้อยู่ที่ระบบภายนอก

### ระบบเป้าหมาย

- FlowAccount
- PEAK
- Xero
- QuickBooks (optional)

### ความสามารถ

- sync customer, invoice, payment, expense
- mapping account/category
- sync status + log
- retry failed sync

---

## 3. Workflow

### 3.1 ตั้งค่าเชื่อมต่อ

```text
Settings → Integration
→ ใส่ credentials provider
→ เก็บใน settings (encrypt)
→ map categories
```

### 3.2 Push เอกสาร

```text
Invoice status=sent/paid หรือ Payment created
→ enqueue sync job
→ เรียก provider API
→ บันทึก accounting_sync_logs (external_id, status)
→ ถ้า failed: retry ตาม policy
```

### 3.3 Pull (optional)

```text
ดึงสถานะ/เอกสารบางส่วนจาก provider
→ อัปเดต local status เท่าที่ map ได้
```

---

## 4. Data Flow

```text
customers / invoices / payments / expenses
                │
                ▼
     Accounting connector service
                │
                ├──► FlowAccount / PEAK / Xero / QB
                └──► accounting_sync_logs
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `accounting_sync_logs` | ประวัติ sync |

### Config

เก็บ credentials/mapping ใน `settings` (keys ต่อ provider)

### Business rules

- external system = book of record ด้านภาษี
- อย่า dual-write โดยไม่มี idempotency key
- ทุก failure ต้องเห็นใน log + retry
- อย่า sync ข้อมูลเกิน scope ที่ user อนุญาต
