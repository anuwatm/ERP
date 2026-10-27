# 15 — Workflows และ Approvals

สำรวจ source 2026-10-24 ฐาน commit `b03c393c19762a09862b4a7aab688f1fe47dcdb3`
อ้างอิง working tree ขณะอ่าน; ไม่ได้รัน tests

## ขอบเขต

approval engine กลางสำหรับ `leave_request`, `purchase_request`, `purchase_order`, `expense`
ไม่ใช่ engine ที่ทุกโมดูลใช้โดยอัตโนมัติ
จัดเก็บ definition แบบมี version และ snapshot ผู้อนุมัติตอน submit
รองรับมอบหมายรายบุคคล ผู้จัดการ หรือ role และ delegation ตามช่วงเวลา

## Source หลัก

- [WorkflowController](../../backend/app/Http/Controllers/WorkflowController.php): `index`, `storeDefinition`, `submit`, `act`, `storeDelegation`
- [WorkflowEngineService](../../backend/app/Services/WorkflowEngineService.php): ธุรกรรมและ state transition
- [หน้าจอ](../../backend/resources/js/Pages/Workflows/Index.tsx)
- [Dialogs](../../backend/resources/js/Pages/Workflows/WorkflowDialogs.tsx)
- [Migration](../../backend/database/migrations/2026_09_07_000002_create_phase20_workflow_tables.php)
- [Routes](../../backend/routes/web.php)

## Routes

| Method / path | Permission | Action |
|---|---|---|
| GET /workflows | workflows.view | inbox/definitions/history |
| POST /workflows/definitions | workflows.manage | definition version ใหม่ |
| POST /workflows/submit/{subjectType}/{subjectId} | workflows.view | submit |
| POST /workflow-approvals/{workflowApproval}/act | workflows.approve | ลงความเห็น |
| POST /workflow-delegations | workflows.approve | มอบหมายแทน |

POST ทั้งหมดใช้ password.confirm
definitions และ delegations ใช้ EnsureFormSubmission เพิ่ม
reject/revision_requested ต้องมี comment ไม่เกิน 2,000 ตัวอักษร

## Data model

- WorkflowDefinition: org_id, code, version, subject_type, amount_min/max, is_active
- unique `(org_id, code, version)`
- WorkflowStep: definition_id, step_no, assignment_type, execution_mode
- unique `(workflow_definition_id, step_no)`
- WorkflowInstance: subject_type/id, requester_user_id, definition_snapshot, subject_snapshot
- instance เก็บ current_step_no, submitted_at, completed_at, status
- WorkflowApproval: instance_id, step_no, assigned_user_id, acted_by_user_id
- approval เก็บ delegated_from_user_id, comment, acted_at, status
- WorkflowDelegation: delegator/delegate, starts_at/ends_at, subject_type nullable, is_active
- subject อ้าง polymorphic type/id ผ่าน service map ไม่ใช่ Eloquent automatic relation ใน engine

## Definition selection

`storeDefinition` สร้าง version ถัดไปตาม code ภายใน org
รองรับ 1–10 steps; assignment_type=`user|manager|role`
`definitionFor` กรอง org, subject_type, is_active และช่วง amount
amount ใช้ `total ?? base_payable_total ?? amount ?? 0` แปลง float
เลือก version สูงสุดของชุดที่ match
แม้ migration มี department_id แต่ query selection นี้ไม่ใช้ department_id
หากหลาย code match version เท่ากัน ไม่มี business priority เพิ่มใน method นี้

## Submit flow

1. หา subject เฉพาะ org ผู้ร้อง
2. creator ใช้ created_by หรือ user_id และต้องตรง requester
3. transaction หา pending instance เดิมและคืนเดิมหากพบ
4. ต้องมี active definition และมี steps
5. resolve user / manager จาก EmployeeWorkProfile / ผู้ใช้ใน role
6. เอา requester ออกจากรายชื่อผู้อนุมัติทุก step
7. หาก step ใดไม่มีผู้อนุมัติที่เหลือ จะ validation error
8. snapshot definition, subject และ assignee_ids
9. สร้าง instance=`pending`, current_step_no=1
10. step แรก approvals=`pending`; ขั้นถัดไป=`queued`

`submit` ไม่ได้เปลี่ยนสถานะ subject เป็น submitted โดยอัตโนมัติ
snapshot ทำให้การแก้ definition ภายหลังไม่เปลี่ยน assignees ของ instance นี้

## Act และ state machine

`act` lock approval และ instance ภายใน transaction
org ต่างตอบ 404; approval ที่ไม่ pending คืน instance เดิม
ผู้ร้องอนุมัติตัวเองไม่ได้ แม้ได้รับ delegation
actor ต้อง assigned user หรือ delegate ที่ active และช่วงเวลาครอบคลุมปัจจุบัน
รองรับ approved/rejected/revision_requested

- rejected: instance rejected และ subject rejected
- revision_requested: instance revision_requested แต่ subject กลับ draft
- approved + sequential: ต้องไม่มี approval pending ใน step เดิมก่อน
- approved + parallel: approval pending อื่นใน step กลายเป็น superseded ทันที
- มี step ถัดไป: เปลี่ยน approvals ของ step นั้น queued → pending
- ไม่มี step ถัดไป: markSubjectApproved และ instance approved

คำว่า parallel ใน implementation นี้มีผลแบบคนหนึ่งอนุมัติแล้วข้ามผู้ร่วม step
จึงไม่ใช่เงื่อนไขทุกคนใน parallel ต้องอนุมัติ

## ผลข้ามโมดูล

expense final approval ตั้ง payable_total/base_payable_total และ balance_due
แล้วเรียก FinancialJournalService::postExpenseApproval ใน transaction
purchase_request/purchase_order ตั้ง approved_by/approved_at
leave_request ที่ submitted และ paid leave เมื่อ reject/revision จะคืน used_days
การคืนวันลาอิง leave year จาก starts_on
หากไม่ใช่ leave จะอัปเดต updated_by ประกอบสถานะ
inbox รวม delegation ที่ยังไม่หมดอายุและกรอง org
history จำกัด 100 รายการที่ assigned หรือ acted โดยผู้ใช้

## ข้อควรตรวจต่อ

`act` ตรวจ approval pending แต่ไม่ได้ตรวจ instance status pending ซ้ำก่อนเปลี่ยนผล
หลัง reject/revision sibling approvals ไม่ถูกปิดใน branch นี้ จึงควรทดสอบการ act ค้าง
definition selection ใช้ float สำหรับ threshold เงิน
ไม่พบ scheduler escalation/expiry ของ approval ใน service นี้
AuditLog ทำผ่าน controller; การเรียก service ตรงต้องประเมิน audit แยก

## Tests อ้างอิง — ไม่ได้รัน

[Phase20WorkflowTest](../../backend/tests/Feature/Phase20WorkflowTest.php)
อ่าน assertions threshold 1,500 เลือก definition ขั้นสูง และ self approval ถูกปฏิเสธ
อ่านกรณี delegation หมดอายุถูก deny และ active delegation final approve ได้
อ่าน reject paid leave คืน used_days จาก 1 เป็น 0
อ่าน expense final approval ตั้ง balance_due=1,000 และมี journal_entries
ผลเหล่านี้เป็นความตั้งใจของ tests ไม่ใช่ผล execution รอบนี้
