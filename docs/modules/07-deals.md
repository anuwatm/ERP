# Module: Leads / Deals / Sales Pipeline

| Meta | Value |
| --- | --- |
| Module code | `deals` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §4.3, §4.4 |

---

## 1. ชื่อ Module

**Leads / Deals / Sales Pipeline** — จัดการโอกาสขายตั้งแต่ lead ถึงปิดการขาย

---

## 2. รายละเอียด / หน้าที่

- สร้าง lead/deal ผูก customer + contact
- Pipeline board แบบ Kanban
- Stage: New → Contacted → Qualified → Proposal → Negotiation → Won / Lost
- มูลค่าดีล, probability, expected close date, owner
- บันทึก activity / follow-up
- สร้าง invoice จาก deal ได้ใน Phase 3; quotation = Post-MVP
- Deal won → Phase 4 สร้าง project ได้
- Deal lost → ใส่ lost_reason
- Sales Dashboard แสดง pipeline value; conversion report เต็ม = Post-MVP

---

## 3. Workflow

### 3.1 Lead to pipeline

```text
Create Deal (title, customer, value, stage=new)
→ assign owner
→ บันทึก activities ระหว่างติดตาม
→ เลื่อน stage บน kanban
```

### 3.2 ปิด Won

```text
stage = won
→ set won_at
→ optional: Create Invoice จาก deal (Phase 3); Create Quotation = Post-MVP
→ optional: Create Project จาก deal (Phase 4)
→ Dashboard pipeline ลด / revenue forecast อัปเดต
```

### 3.3 ปิด Lost

```text
stage = lost
→ บังคับ lost_reason
→ set lost_at
→ ใช้ใน Sales Dashboard; win/loss report เต็ม = Post-MVP
```

### 3.4 Stale deal automation (Post-MVP)

```text
Cron/Automation: deal ไม่มี activity 7 วัน
→ แจ้ง owner (Notifications)
```

### 3.5 Invoice void feedback

```text
Invoice จาก deal ถูก void และไม่มี invoice อื่นที่ active ใต้ deal นั้น
→ แสดง derived flag `needs_sales_review` บน deal/invoice detail ให้ Sales Owner ตรวจ stage/value
→ MVP ไม่ auto-reopen deal และไม่ใช้ notifications module เพื่อไม่แก้ sales history โดยไม่ตั้งใจ
```

---

## 4. Data Flow

```text
customers / contacts
        │
        ▼
      deals ◄──► activities
        │
        ├──► quotations.deal_id (Post-MVP)
        ├──► projects.deal_id
        └──► invoices.deal_id (optional)
                │
                ▼
        Dashboard / Reports (pipeline, win rate)
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `deals` | โอกาสขาย |

### ตารางร่วม

| Table | Role |
| --- | --- |
| `activities` | entity_type=deal |
| `customers`, `contacts`, `users` | FK |
| `projects` | downstream |

### Stage enum

`new | contacted | qualified | proposal | negotiation | won | lost`

### Business rules

- เปลี่ยนเป็น won/lost ควร immutable บางส่วน (value snapshot)
- probability 0–100
- Sales เห็น deal ตาม owner policy
- Member ไม่มี deal list; เห็น deal name/status แบบ linked read-only เฉพาะจาก task detail เท่านั้น
- pipeline value = sum(value_amount) ของ stage ที่ยังไม่ closed
- ถ้า invoice ที่สร้างจาก deal ถูก void ให้ใช้ manual review/audit ก่อนปรับ stage ของ deal
- `needs_sales_review` เป็น derived flag ไม่ใช่คอลัมน์ MVP: true เมื่อ deal มี invoice ถูก void ล่าสุดและไม่มี invoice active (`sent`, `partially_paid`, `paid`, `overdue`) เหลืออยู่

