# 08 — Projects และ Tasks

อ่านโค้ด ณ 2026-10-24 · HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` พร้อม working tree ขณะสำรวจ
เป็น static reference; tests ที่อ้างอ่าน source ไม่ได้รัน

## ขอบเขต

Project รวมงานส่งมอบ ลูกค้า Deal, owner, สมาชิก งบและความคืบหน้า
Task อยู่ใต้ Project หรือเป็นงานภายในไม่มี Project; มี assignee, priority, checklist และ comments
Project progress เป็น field ที่กรอก ไม่ได้คำนวณจากสัดส่วน tasks ใน controller ที่อ่าน

ไฟล์หลัก: [ProjectController](../../backend/app/Http/Controllers/Delivery/ProjectController.php), [TaskController](../../backend/app/Http/Controllers/Delivery/TaskController.php)
UI: [Projects.tsx](../../backend/resources/js/Pages/Delivery/Projects.tsx), [ProjectEditorDialog.tsx](../../backend/resources/js/Pages/Delivery/ProjectEditorDialog.tsx), [ProjectMembersDialog.tsx](../../backend/resources/js/Pages/Delivery/ProjectMembersDialog.tsx)
UI งาน: [Tasks.tsx](../../backend/resources/js/Pages/Delivery/Tasks.tsx), [TaskEditorDialog.tsx](../../backend/resources/js/Pages/Delivery/TaskEditorDialog.tsx), [TaskDetailDialogs.tsx](../../backend/resources/js/Pages/Delivery/TaskDetailDialogs.tsx)

## Routes / Permissions

อ้างอิง [web.php](../../backend/routes/web.php); mutation ใช้ password.confirm และ throttle:10,1
POST สร้าง Project/Task/member/checklist/comment รวม storeFromDeal ใช้ EnsureFormSubmission

| HTTP / URL | Method | Permission |
|---|---|---|
| GET `/projects` | Project::index | `projects.view` |
| POST `/projects` | Project::store | `projects.create` |
| PATCH `/projects/{project}` | Project::update | `projects.update` |
| POST `/deals/{deal}/project` | storeFromDeal | `projects.create` |
| POST `/projects/{project}/members` | storeMember | `projects.update` |
| DELETE `/projects/{project}/members/{member}` | destroyMember | `projects.update` |
| GET `/tasks` | Task::index | `tasks.view` |
| POST `/tasks` | Task::store | `tasks.create` |
| PATCH `/tasks/{task}` | Task::update | `tasks.update` |
| POST `/tasks/{task}/checklists` | storeChecklist | `tasks.update` |
| PATCH `/task-checklists/{checklist}` | toggleChecklist | `tasks.update` |
| POST `/tasks/{task}/comments` | storeComment | `tasks.comment` |

Project/Task ในตารางหมายถึง controller; `projects.reassign` ตรวจเพิ่มใน ProjectController::update

## Entities / schema

- [Project](../../backend/app/Models/Project.php): customer/deal/owner, tasks, members; budget_amount, currency, progress_percent, dates, note
- [ProjectMember](../../backend/app/Models/ProjectMember.php): project/user/role
- [Task](../../backend/app/Models/Task.php): optional project, assignee, checklists, comments; status, priority, due_date, completed_at
- [TaskChecklist](../../backend/app/Models/TaskChecklist.php): title, is_done, sort_order; [TaskComment](../../backend/app/Models/TaskComment.php): body/user
- [Project migration](../../backend/database/migrations/2026_07_29_000001_create_delivery_projects_table.php)
- [Task migration](../../backend/database/migrations/2026_07_29_000003_create_delivery_tasks_tables.php): UUID/org_id; project/assignee nullable; checklist/comment cascade กับ task
- [member/procurement migration](../../backend/database/migrations/2026_08_21_000002_create_phase7_procurement_and_members.php)

## Access scope ที่ต้องแยกให้ชัด

อ้างอิง [ProjectAccess](../../backend/app/Support/ProjectAccess.php), [TaskAccess](../../backend/app/Support/TaskAccess.php)

- ทั้งสอง helper ให้ role `owner`/`admin` เห็นทุก record ใน org
- Project: คนอื่นเห็นเมื่อเป็น owner หรือมี membership
- Task: คนอื่นเห็นเมื่อเป็น assignee หรือเป็น owner ของ project; membership อย่างเดียวไม่ทำให้เห็น tasks ทั้งหมด
- Task manager: owner/admin หรือ project owner; งานไม่มี project ใช้ permission `tasks.create` เป็นเงื่อนไข canManageProjectTasks
- การมี route permission ยังต้องผ่าน record visibility; ต่างองค์กรตอบ 404
- Project member จัดการสมาชิกไม่ได้เพียงเพราะมองเห็น: store/destroyMember ต้อง owner/admin หรือ project owner เพิ่ม

## Project workflow

อ้างอิง ProjectController::store/update/storeFromDeal/validateProject/assertDealMatchesCustomer

1. สร้าง manual Project ได้; customer/deal optional
2. สร้างรหัสด้วย NumberSequenceService; คนทั่วไป owner เป็นตนเอง, owner/admin เลือก owner ได้
3. status รับ planning, active, on_hold, completed, cancelled
4. progress_percent รับ 0–100; due_date ต้องไม่ก่อน start_date
5. ถ้า link Deal: ต้องเป็น won และผ่าน SalesAccess; ถ้าให้ Customer มาต้องตรงกับ Deal
6. validateProject จำกัด Deal เดียวต่อ Project ใน org ผ่าน Rule::unique
7. storeFromDeal รับเฉพาะ won และห้ามมี Project ของ Deal แล้ว
8. ใช้ title/customer/value/currency ของ Deal; status planning, budget=deal value; currency fallback THB
9. update คง owner เดิม เว้นมี `projects.reassign`; permission นี้แยกจากการเห็น Project ทั้งหมด
10. controller ตรวจ status allowlist แต่ไม่มี transition matrix บังคับทีละขั้น

## Member และ notification

อ้างอิง ProjectController::storeMember/destroyMember

- user ที่เพิ่มต้องอยู่ org; role ต้องอยู่ ProjectMember::ROLES: manager, member, viewer
- ห้ามเพิ่ม owner เป็น member เพราะ owner ถูกรวมอยู่แล้ว
- updateOrCreate ด้วย `(project_id,user_id)` จึงใช้แก้ member role ได้
- ส่ง notification `project.member_assigned` พร้อม deduplication key ที่อิง project/user
- ลบสมาชิกตรวจทั้ง org_id และ project_id ของ member

## Task workflow

อ้างอิง TaskController::store/update/validateTask/storeChecklist/toggleChecklist/storeComment

- status: todo, in_progress, blocked, done, cancelled; priority: low, normal, high, urgent
- store ต้องผ่าน canManageProjectTasks; project optional; assignee ไม่ระบุใช้ผู้สร้าง
- done ตั้ง completed_at; เปลี่ยนออกจาก done ล้าง completed_at
- update ตรวจ task เดิม visible ก่อน; ถ้าไม่ได้สิทธิ์ manage ตาม helper จะรักษา project/title/description/priority/assignee/due_date เดิม และรับเฉพาะ status
- ไม่ได้บังคับให้ checklist เสร็จทุกข้อก่อนเปลี่ยน done
- checklist เพิ่ม sort_order จาก max+1; toggle รับ boolean is_done และตรวจ parent task visible
- comment body ต้องไม่เกิน 2000 ตัวอักษร; ต้องมี tasks.comment และ task visible
- notifyAssignee ใช้ key task/user; ถูกเรียกตอน create/update ไม่ใช่เฉพาะเมื่อเปลี่ยน assignee
- Tasks.tsx ส่ง PATCH พร้อมข้อมูล task เพื่อเปลี่ยน status; server ยังคงเป็นผู้จำกัด fields

## ต้นทุนและการเชื่อมโมดูล

ProjectController::attachProjectCosts ทำงานเมื่อผู้ใช้มี `expenses.view`
รวม Expense สถานะ approved/partially_paid/paid แยก project/currency ด้วย SUM(amount)
ถ้าพบ currency ต่างจาก Project จะไม่แนบ actual_cost/budget_remaining ของ Project นั้น
หากตรงกันทุกแถว: actual_cost=SUM(amount), budget_remaining=budget−actual_cost
จึงเป็นต้นทุนจาก Expense amount ตามนิยามโค้ด ไม่ใช่ paid cash หรือ GL cost ทั้งหมด
Invoice/Expense/PO อ้าง Project ได้; ดู [06](06_billing_payments_expenses.md), [07](07_procurement_inventory.md)

## Tests ที่อ่าน — ไม่ได้รัน

[Phase4ProjectsTest](../../backend/tests/Feature/Phase4ProjectsTest.php): manual project, won deal สร้างได้ครั้งเดียว, non-won ถูกปฏิเสธ, owner/admin scope, reassign permission
[Phase4TasksTest](../../backend/tests/Feature/Phase4TasksTest.php): project/internal tasks, member เห็นเฉพาะ assigned, status-only update, checklist/comment visibility และ blocked ไม่ auto overdue
[Phase4FinanceProjectLinkTest](../../backend/tests/Feature/Phase4FinanceProjectLinkTest.php): จุดอ้างอิง finance/project chain

## ข้อควรตรวจต่อ

- ไม่มี delete Project/Task route ในชุดที่สำรวจ; อย่าเพิ่ม flow ลบในคู่มือจากการคาดเดา
- progress ไม่ sync กับ completed tasks; จึงอาจเป็น completed แต่ progress ไม่ใช่ 100 ได้ตาม validation ปัจจุบัน
- storeFromDeal ตรวจซ้ำด้วย query ก่อน create; ต้องดู DB constraint/concurrency เพิ่มก่อนรับรองสร้างครั้งเดียวภายใต้ request พร้อมกัน
- Task update ใช้ project จาก request เพื่อคำนวณ canManage; ควรตรวจกรณีย้าย task/เปลี่ยน project แบบ hostile payload เพิ่มก่อนเปลี่ยน access logic
