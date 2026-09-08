# Module: Settings

| Meta | Value |
| --- | --- |
| Module code | `settings` |
| Version | Current |
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
- Notification preferences และ 2FA policy ระดับองค์กร
- ใช้ตาราง `organizations`, `settings` แบบ key-value และ `number_sequences` สำหรับรันเลขเอกสาร

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

### 3.3 Security settings

```text
Owner/Admin เปิด Security ใน Organization Settings
→ ตั้ง `security.two_factor.enabled`, privileged roles, trusted device และอายุ 1-90 วัน
→ บันทึก settings.value_json
→ `TwoFactorPolicyService` อ่านก่อน login challenge และ middleware enrollment gate
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
    ├──► Notifications (preferences)
    └──► Auth (2FA policy)
```

### Keys แนะนำ

| Key | ตัวอย่าง value |
| --- | --- |
| `invoice.numbering` | `{ "digits":6, "reset":"never" }` |
| `quotation.numbering` | `{ "digits":6 }` |
| `payment.default_terms` | `{ "days": 30 }` |
| `security.two_factor` | `{ "enabled":false, "required_for_privileged_roles":true, "allow_trusted_devices":true, "trusted_device_days":30 }` |
| `notifications.preferences` | ช่องทาง/ประเภทการแจ้งเตือนต่อผู้ใช้ |

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
- 2FA policy ปิดเป็นค่าเริ่มต้น; `trusted_device_days` รับได้ 1-90 วันเมื่อเปิด trusted devices
- เลขเอกสาร/รหัสธุรกิจต้องเป็น text 6 หลัก เช่น `000001`
- การรันเลขเอกสารต้อง atomic (transaction / row lock)
- ห้าม reuse รหัสที่เคยออกแล้ว
- เปลี่ยน currency หลักหลังมีเอกสารแล้ว ต้องระวัง (ไม่ retroactive)
