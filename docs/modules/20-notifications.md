# Module: Notifications

| Meta | Value |
| --- | --- |
| Module code | `notifications` |
| Version | V1 หลัง MVP (in-app/email), LINE ใน V2 |
| Priority | P1 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §9.2 |

---

## 1. ชื่อ Module

**Notifications** — แจ้งเตือนสิ่งที่ต้องทำ

---

## 2. รายละเอียด / หน้าที่

- In-app notification + unread count
- Email notification (Post-MVP)
- LINE notification (V2)
- ตั้งค่า preferences (อนาคต / settings)
- Mark read

### Event หลัก

- assigned task
- task overdue
- deal follow-up due
- invoice overdue
- expense approval needed
- payment received

---

## 3. Workflow

### 3.1 สร้างแจ้งเตือน

```text
Domain event (เช่น task assigned)
→ Notification service
→ insert notifications (user_id, type, title, body, entity_*)
→ optional enqueue email/LINE
```

### 3.2 ผู้ใช้รับสาร

```text
เปิด bell icon → list unread
→ คลิก → mark read + ไป entity
→ mark all read
```

---

## 4. Data Flow

```text
Tasks / Deals / Invoices / Payments / Expenses / Automation
                        │
                        ▼
              Notification service
                        │
          ┌─────────────┼─────────────┐
          ▼             ▼             ▼
     notifications   Email provider  LINE (V2)
      (in_app DB)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `notifications` | กล่องแจ้งเตือนต่อ user |

### Field สำคัญ

`user_id`, `type`, `title`, `body`, `entity_type`, `entity_id`, `channel`, `is_read`, `read_at`

### Business rules

- แจ้งเฉพาะ user ที่เกี่ยวข้อง + อยู่ใน org เดียวกัน
- อย่า spam: debounce event ซ้ำ
- email ใช้ settings email.sender
