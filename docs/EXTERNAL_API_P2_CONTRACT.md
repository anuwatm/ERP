# P2 — HR / Employee read contract

เงินเดือน/บัญชีธนาคารไม่ออก API ตามคำสั่งผู้ใช้ ไม่ส่ง national_id/tax_id/birth date/private contact/GPS/IP/reason/attachments หรือข้อมูลสุขภาพ ไม่มี payroll scope

## Credential policy

Scopes ใหม่ต้องออกโดยผู้มี api_keys.manage และ hr.employees.manage เท่านั้น ต้องเลือก access_policy.hr_all_branches=true อย่าง explicit หรือ hr_branch_ids ที่อยู่ใน org; ไม่เลือกทั้งคู่ถูกปฏิเสธ เปลี่ยน policy/scopes ต้อง rotate key ไม่แก้ key เดิม HR keys เก่าที่ไม่มี policy ไม่เห็นพนักงาน

สรุป/รายคนใช้ Employee เป็นฐาน รวม non-User; parent structure ต้องเป็น org เดียวกัน และ manager reference ถูกซ่อนเมื่ออยู่นอก visibility scope Current snapshot ไม่ใช่ historical headcount

## GET /api/v1

| Path | Scope | Contract |
| --- | --- | --- |
| summaries/hr | summary:hr:read | employment_status/type snapshot, started_on/resigned_on period, probation window 30 วันจากวันนี้เฉพาะ employed staff |
| summaries/attendance | summary:attendance:read | employee-workdays กับ distinct employee แยกกัน; minutes integer; แยก reconciled/pending/incomplete ไม่อนุมาน absent จาก missing punch |
| summaries/leaves | summary:leaves:read | คำขอทับช่วงตาม starts_on/ends_on แยก status; coverage linked/non-User ชัดเจน ไม่มี breakdown ประเภทลาหรือข้อมูลสุขภาพ |
| summaries/roster | summary:roster:read | allocated workdays/day-off/distinct employees; eligible workdays/missing schedules ใช้ employment dates แต่ไม่อ้าง missing schedule = absent |
| employees / employees/{id} | employees:read | UUID, employee_code, names, nickname, org structure refs, manager ref, employment_type/status, started_on/probation_ends_on/resigned_on |
| employees/{id}/attendance | employees:attendance:read | work_date, clock times, reconciled/absent flags, worked/late/early/OT minutes; ไม่ส่ง correction notes/geolocation |
| employees/{id}/roster | employees:roster:read | schedule_date/day-off, shift id/start/end/break; ไม่ส่ง OT rates/pay/notes |
| employees/{id}/leave-balances | employees:leaves:read | opening/accrued/carry_over/used/remaining decimal days ของ current year ณ ปัจจุบัน; ไม่อ้างย้อนหลัง |
| employees/{id}/leave-requests | employees:leaves:read | วันที่/จำนวนวัน/status ไม่ส่งประเภทลา เหตุผล หรือไฟล์แนบ |

List default50/max100/cursor30m; operational list ใช้ start_date/end_date ไม่เกิน366วัน ใช้ work_date/schedule_date/leave overlap ตาม resource ไม่ใช่ updated_at ของ import

Non-User Employee เรียก leave endpoints ได้ unsupported_employee_coverage (409) แทน []/0; summary มี coverage ชัดเจน วันที่ลา overlap ต้องใช้ policy ของระบบจริง หาก reconstruct ส่วนวันลาไม่ได้ให้ส่ง unavailable แทนบวก total_days ทั้งคำขอข้ามช่วง

Directory รวมพนักงานพ้นสภาพตาม scope และ employment_status filter เพื่อ reconcile ข้อมูลเก่าได้ ไม่เผย personal contacts จาก email/phone เดิมโดยถือว่าเป็น work contact

Roster missing_schedule_workdays=null เพราะไม่มี authoritative required-workday calendar รายคน; department groups<5 suppress และ cap100. Active employees ใช้ current active/probation + recorded employment dates; ไม่อ้าง historical snapshot. Operational lists ไม่รองรับ updated_since; ใช้ period reads/full reconciliation.

Tests: scope separation, org/branch/foreign parent/manager, non-User coverage, leakage, pagination, dates/overnight, pending attendance/confirmed absent, approved/cancelled overlaps และ query budget
