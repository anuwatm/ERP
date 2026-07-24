# Module: Audit Log

| Meta | Value |
| --- | --- |
| Module code | `audit` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §9.3 |

---

## 1. ชื่อ Module

**Audit Log** — บันทึกความเคลื่อนไหวสำคัญของระบบ

---

## 2. รายละเอียด / หน้าที่

- บันทึก create / update / delete และ action สำคัญ
- เก็บ actor, timestamp, entity, before/after (field สำคัญ)
- filter ตาม user / module / date
- export ได้เฉพาะ admin/owner (Post-MVP)
- ใช้ตรวจสอบการเงิน สิทธิ์ และการเปลี่ยนแปลงข้อมูลอ่อนไหว

### Event ที่ควรเก็บ (ขั้นต่ำ)

- login
- create customer
- update deal
- change invoice status
- record payment
- delete task
- approve expense
- change permission

---

## 3. Workflow

### 3.1 เขียน log (async/sync)

```text
Business action สำเร็จ
→ Application layer สร้าง audit event
→ บันทึก audit_logs (org_id, actor, action, entity, before/after)
→ (optional) queue ถ้า high volume
```

### 3.2 ค้นหา / ตรวจสอบ

```text
Admin เปิด Audit Log
→ filter user / entity_type / date range / action
→ ดู diff before_json vs after_json
→ export CSV (permission: audit.export) (Post-MVP)
```

---

## 4. Data Flow

```text
[ทุก Module] ──event──► Audit writer
                            │
                            ▼
                       audit_logs
                            │
                            ▼
                    [Audit UI / Export]
```

**Inbound sources:** Auth, CRM, Deals, Invoices, Payments, Expenses, RBAC, Tasks, Settings

**Outbound:** Reports (optional compliance), Export

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `audit_logs` | immutable event stream |

### ฟิลด์สำคัญ

- `actor_user_id`, `action`, `entity_type`, `entity_id`
- `before_json`, `after_json`
- `ip_address`, `user_agent`, `created_at`

### Business rules

- ห้าม update/delete audit row จาก UI ปกติ (append-only)
- ไม่เก็บ secret/password ใน before/after
- index ตาม org + time เพื่อ query เร็ว
- retention policy กำหนดภายหลัง (เช่น 1–2 ปี)
