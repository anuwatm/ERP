# Module: Settings

| Meta | Value |
| --- | --- |
| Module code | `settings` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §3.1, §9.7, §9.9 |

---

## 1. ชื่อ Module

**Settings** — ตั้งค่าระบบระดับองค์กร

---

## 2. รายละเอียด / หน้าที่

เก็บและจัดการค่า config ที่ทุก module อ่านใช้ร่วมกัน

- Company profile / logo / address / tax id
- Currency, timezone
- Invoice / quotation numbering rules
- Default payment terms
- Roles overview (ลิงก์ไป RBAC)
- Webhook secret, email sender, integration keys
- ใช้ตาราง `settings` แบบ key-value + `number_sequences` สำหรับรันเลขเอกสาร

---

## 3. Workflow

### 3.1 ตั้งค่าบริษัท

```text
Admin เปิด Settings → Company
→ แก้ไข name, address, tax_id, logo
→ บันทึก organizations (+ settings บาง key)
→ modules อื่นอ่านค่าใหม่ทันที
```

### 3.2 ตั้งเลขเอกสาร

```text
กำหนดรูปแบบเลขรัน 6 หลัก (เช่น `000001`; prefix เป็น optional หลัง MVP)
→ บันทึก settings key = invoice.numbering
→ เตรียม/อัปเดต number_sequences
→ ตอนสร้าง invoice ระบบดึง next number แบบ atomic
```

### 3.3 Integration settings

```text
ใส่ email SMTP / webhook secret / accounting credentials
→ เก็บใน settings.value_json (encrypt secrets)
→ API / Automation / Accounting อ่านใช้
```

---

## 4. Data Flow

```text
[Admin UI]
    │ write
    ▼
organizations (profile fields)
settings (key → value_json)
number_sequences (doc counters)
    │
    ├──► Invoices / Quotations (numbering)
    ├──► Finance (currency, payment terms)
    ├──► Automation / API (webhook secret)
    └──► Notifications (email sender)
```

### Keys แนะนำ

| Key | ตัวอย่าง value |
| --- | --- |
| `invoice.numbering` | `{ "digits":6, "reset":"never" }` |
| `quotation.numbering` | `{ "digits":6 }` |
| `payment.default_terms` | `{ "days": 30 }` |
| `email.sender` | `{ "from":"billing@..." }` |
| `webhook.secret` | encrypted string |
| `integrations.flowaccount` | credentials map |

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `settings` | key-value config ต่อ org |
| `number_sequences` | ตัวนับเลขเอกสาร |

### ตารางที่เกี่ยวข้อง

| Table | Relationship |
| --- | --- |
| `organizations` | profile / currency / timezone |
| `branches` | optional per-branch numbering |

### Business rules

- `settings.key` unique ต่อ org
- secret ต้อง encrypt at rest
- เลขเอกสาร/รหัสธุรกิจต้องเป็น text 6 หลัก เช่น `000001`
- การรันเลขเอกสารต้อง atomic (transaction / row lock)
- ห้าม reuse รหัสที่เคยออกแล้ว
- เปลี่ยน currency หลักหลังมีเอกสารแล้ว ต้องระวัง (ไม่ retroactive)
