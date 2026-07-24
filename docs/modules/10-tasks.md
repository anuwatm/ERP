# Module: Tasks

| Meta | Value |
| --- | --- |
| Module code | `tasks` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §5.3–5.5 |

---

## 1. ชื่อ Module

**Tasks** — งานย่อยใน project หรืองานภายใน

---

## 2. รายละเอียด / หน้าที่

- สร้าง task ใน project (หรือ internal ถ้า project_id null)
- assign ผู้รับผิดชอบ, priority, due date
- status: todo, in_progress, review, done, blocked
- checklist ย่อยและ comment อยู่ใน MVP; แนบไฟล์ = Post-MVP
- mark overdue อัตโนมัติ
- board view + list view
- recurring task = Post-MVP

---

## 3. Workflow

### 3.1 สร้างและมอบหมาย

```text
Project Detail → Add Task
→ set assignee, due_date, priority
→ status=todo
→ notification task_assigned = Post-MVP
```

### 3.2 ทำงาน

```text
assignee ย้าย status → in_progress → review → done
→ checklist tick
→ comment อัปเดต
→ completed_at เมื่อ done
```

### 3.3 Overdue job

```text
Cron รายวัน:
due_date < today AND status not in (done, blocked)
→ is_overdue=true
→ notification task_overdue = Post-MVP
```

---

## 4. Data Flow

```text
projects / users
      │
      ▼
    tasks 1──* task_checklists
      │
      ├──* task_comments
      ├──► files (entity=task, Post-MVP)
      ───► notifications (Post-MVP)
              │
              ▼
      Dashboard (overdue count) / Reports
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `tasks` | งาน |
| `task_checklists` | รายการย่อย |
| `task_comments` | ความคิดเห็น |

### Business rules

- delete task → cascade checklist/comments
- Member เห็น task ที่ assign ให้ตัวเอง (ตาม policy)
- blocked ต้องมี reason ใน description/comment (แนะนำ)
- MVP overdue ใช้ dynamic query หรือ job update `is_overdue`; ห้ามผูกกับ notification จริงจนกว่า Post-MVP
- `blocked` ไม่นับ overdue ใน MVP เพราะถือว่ารอปัจจัยภายนอก; ถ้าต้องนับ SLA จริงให้เพิ่ม rule หลัง MVP

