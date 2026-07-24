# Seed Data

เอกสารนี้กำหนดข้อมูลเริ่มต้นสำหรับ dev/UAT ใน MVP.

## Phase 1: Foundation

- Organization demo: currency `THB`, timezone `Asia/Bangkok`, status `active`.
- Branch: head office code `000001`.
- Division: default code `000001`.
- Department: default code `000001`.
- Owner user: active, verified email, assigned Owner role.
- System roles: `owner`, `admin`, `sales`, `project_manager`, `finance`, `member`, `viewer`.
- Permission catalog ตาม `docs/modules/02-user-role-permission.md`.
- Role permission mappings.
- Number sequences: branch, division, department ตั้ง `last_number=1` หลังสร้าง default records code `000001`; next generate ต้องได้ `000002`.
- Admin Dashboard demo counts ต้องตรง seed.

## Phase 2: CRM / Sales

- Customers อย่างน้อย 5 ราย.
- Contacts อย่างน้อย 1-2 คนต่อ customer.
- Deals ครบ stage หลัก: new, contacted, qualified, proposal, negotiation, won, lost.
- Activities/follow-ups มีรายการวันนี้, overdue, และ stale deal.
- Sales Dashboard expected values ต้องระบุใน seed comment หรือ test fixture.

## Phase 3: Finance

- Products/services อย่างน้อย 5 รายการ.
- Invoice manual อย่างน้อย 1 ใบ.
- Invoice from deal อย่างน้อย 1 ใบ.
- Partial payment case.
- Paid invoice case.
- Overdue invoice case.
- Reversal payment case.
- Expenses: draft, approved, paid, rejected.
- Finance Dashboard expected values: Invoiced Revenue, Cash In, Outstanding AR, Overdue AR, Expenses, Cash Out, Net Cash Flow, Gross Profit.

## Phase 4: Delivery

- Projects จาก deal won อย่างน้อย 2 project.
- Tasks ครบ status: todo, in_progress, review, done, blocked.
- Overdue task อย่างน้อย 1 รายการ.
- Invoice linked to project อย่างน้อย 1 ใบ.
- Expense linked to project อย่างน้อย 1 รายการ.
- Delivery Dashboard expected values: Active Projects, Overdue Tasks, Budget vs Expense, Project Profit.

## Phase 5: UAT

- Dataset ต้องรัน flow ครบ:

```text
Invite user -> Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard
```

- มี user ทุก role.
- มีข้อมูลที่ทดสอบ permission visibility ได้.
- มี expected dashboard numbers สำหรับ automated test.

## Number sequence algorithm

- ถ้า seed default record ด้วย code `000001` ให้ตั้ง `last_number=1`.
- ถ้ายังไม่มี record ของ doc_type นั้น ให้ตั้ง `last_number=0`.
- เวลา generate: lock row, 
- `next = last_number + 1`, code = `LPAD(next, 6, '0')`, update `last_number = next`.




