# External Read-only Summary API — Scope & Checklist

สถานะ: P0–P6 ผ่าน Gemini technical verification ตาม [gemini.md section12](../gemini.md) วันที่2026-10-19; P7 sensitive/write ตัดออก. Browser UAT pending; production migrations/deployment/live integration/concurrent HTTP SLA deferred ตามผู้ใช้ ไม่ถือว่า production acceptance ผ่าน
วันที่: 2026-10-19

## 1. เป้าหมายและขอบเขต

ให้แอปภายนอกดึงข้อมูล ERP ผ่าน API ได้ โดยเริ่มจากข้อมูลสรุป แล้วขยายทีละโดเมนจนครอบคลุมระบบ ผู้ใช้อนุญาตเริ่ม implementation เมื่อ 2026-10-18 โดยให้ทำตาม checklist และบันทึกหลักฐานใน gpt.md; ยังไม่ deploy/migrate ฐานข้อมูลใช้งานจริง

- [x] รอบแรก: Summary API สำหรับ Sales และ Finance พร้อมโครงสร้าง authentication และหน้า Admin จัดการ secret key
- [ ] รอบถัดไป: Projects/Tasks → Procurement/Inventory → HR/Payroll → Manufacturing → โดเมนอื่นตามลำดับที่ตกลง
- [x] ทุก endpoint ธุรกิจในระยะแรกเป็น GET/read-only ไม่เปลี่ยนข้อมูลหรือสร้างรายการบัญชี
- [x] ไม่รวมรายการเอกสารรายตัว การดาวน์โหลดไฟล์ export แบบ bulk webhook และ write API ในรอบแรก
- [x] ไม่ส่งข้อมูลส่วนบุคคล เงินเดือนรายบุคคล บัญชีธนาคาร หรือข้อมูลลับในการตอบ summary

## 2. API contract รอบแรก (ข้อเสนอ)

- [x] ใช้ namespace `/api/v1/summaries` แยกจาก payment gateway callbacks เดิม
- [x] `GET /api/v1/summaries/sales` — จำนวนลูกค้าใหม่ จำนวน/มูลค่า deals แยกสถานะ และ quotations แยกสถานะ
- [x] `GET /api/v1/summaries/finance` — invoiced revenue, cash received สุทธิ reversal, expenses approved/paid และยอดลูกหนี้คงค้าง/เกินกำหนด
- [x] กำหนด scope `summary:sales:read` และ `summary:finance:read` แยกกัน ไม่เปิดสิทธิ์ทุกโดเมนโดยอัตโนมัติ
- [x] ระบุ response schema และตัวอย่าง JSON พร้อมตรวจเทียบ implementation; ส่งเฉพาะ fields ที่ allowlist
- [x] ใช้ envelope `data` และ `meta` พร้อม `api_version`, `generated_at`, `timezone`, ช่วงวันที่ และ currency ของยอดเงิน
- [x] เงินเป็น decimal string; จำนวนรายการเป็น integer; ไม่ใช้ float เพื่อส่งยอดเงิน
- [x] Error JSON รูปแบบเดียวกัน: `code`, `message`, `request_id`; ไม่เปิดเผย SQL/stack trace/secret
- [x] กำหนด 401 สำหรับ key ไม่ถูกต้อง/หมดอายุ/ถูกถอน, 403 สำหรับ scope ไม่พอ, 422 สำหรับ filter ไม่ถูกต้อง และ 429 สำหรับเกิน rate limit

## 3. นิยามตัวเลขและ filter

- [x] ทำ metric dictionary เทียบกับ query/service ของ Dashboard ปัจจุบัน ก่อนใช้ซ้ำหรือแยก summary service กลาง
- [x] ระบุ field วันที่ของแต่ละ metric เช่น invoice issue_date, payment_date และ expense_date; ไม่ใช้ created_at แทนทุกกรณี
- [x] แยก period metrics ออกจาก snapshot metrics: ลูกหนี้คงค้างใช้ยอด ณ เวลาที่ดึงในรอบแรก พร้อม `as_of`; ยังไม่อ้างเป็นยอดย้อนหลังตาม end_date
- [x] ระบุผลของ draft, void, cancelled, soft-deleted, reversal และ partial payment ต่อทุก metric
- [x] แยกยอดออก Invoice กับเงินรับจริง และแยกรายจ่าย approved กับ paid; ไม่สร้าง Organization-wide Cash Balance จากยอดรับลบจ่าย
- [x] กำหนด base currency/FX ให้ตรงกฎเดิม; ห้ามรวมยอดต่างสกุลเงินโดยไม่แปลง
- [x] รองรับ `start_date`, `end_date` แบบ YYYY-MM-DD; ข้อเสนอ default เดือนปัจจุบัน และจำกัดช่วงสูงสุด 366 วัน
- [x] ตกลง timezone รายงานและขอบเขตวันให้ชัด ตรวจเทียบ runtime และ Dashboard; ส่ง timezone ใน meta
- [x] รอบแรกปฏิเสธ `branch_id` ด้วย 422 เพราะยังไม่มี branch semantics ร่วมกันทุก metric; ไม่ fallback เงียบเป็นยอดทั้งองค์กร
- [x] filter ไม่รู้จัก/ช่วงวันที่กลับด้าน/เกินขอบเขตต้องถูกปฏิเสธ ไม่ fallback เป็นข้อมูลทั้งหมด

## 4. Secret key และ authentication

- [x] key เป็น credential ของแอปภายนอก ผูก organization เดียวและ scopes ที่กำหนด ไม่ใช้ session cookie ของ admin เป็น API credential
- [x] ตรวจแนวทาง Laravel Sanctum ที่ติดตั้งอยู่เทียบกับ dedicated integration key; เลือกวิธีเดียวที่รองรับข้อกำหนดได้โดยไม่สร้าง auth ซ้ำ
- [x] รับ key ผ่าน `Authorization: Bearer <secret>` เท่านั้น; ไม่รับผ่าน URL/query string
- [x] สุ่ม secret แบบ cryptographically secure อย่างน้อย 256 bits; แยก public key identifier/prefix สำหรับค้นหาและแสดงผล
- [x] แสดง secret เต็มเพียงครั้งเดียวตอนสร้าง เก็บเฉพาะ hash และตรวจแบบ timing-safe ตามกลไกที่เลือก; กู้คืน secret เดิมไม่ได้
- [x] metadata: ชื่อแอป, org_id, scopes, created_by, created_at, expires_at, revoked_at และ last_used_at; ไม่ใช้ secret เป็นชื่อหรือ metadata
- [x] ข้อเสนอ default expiry 90 วัน; ให้ admin เลือกอายุได้ตาม policy ที่ตกลงก่อน implementation
- [x] ถอน key มีผลกับ request ถัดไปทันที; cache ต้องไม่ทำให้ key ที่ถอนแล้วยังใช้งานได้
- [x] rotation = สร้าง key ใหม่แล้วถอน key เก่าอย่างชัดเจน รองรับเปลี่ยน credential ฝั่งแอปก่อนถอน
- [x] lookup key เพื่อสร้าง tenant context จาก server เท่านั้น; ไม่รับ org_id เพื่อเลือกองค์กรจาก client
- [x] ตรวจ scopes ทุก request; เพิ่ม endpoint ใหม่แล้ว key เดิมไม่ได้รับสิทธิ์ใหม่โดยอัตโนมัติ
- [x] ใช้ HTTPS ใน production; secret ต้องเก็บฝั่ง server ของแอปผู้เรียก ไม่ฝังใน browser bundle/mobile binary

## 5. หน้า Admin จัดการ key

- [x] เพิ่มหน้า Settings → API Access พร้อม permission `api_keys.view` และ `api_keys.manage`; default ให้ owner/admin ตาม PermissionCatalog
- [x] จำกัดการจัดการ key เฉพาะองค์กรของ admin; ตรวจสิทธิ์ฝั่ง server ทุก action
- [x] ตารางแสดงชื่อแอป, prefix, scopes, สถานะ active/expired/revoked, วันหมดอายุ, ผู้สร้าง และใช้งานล่าสุด
- [x] สร้าง key: ชื่อแอป + เลือก scopes แบบ explicit + วันหมดอายุ; ไม่มี default เลือกทั้งหมด
- [x] แสดง/คัดลอก secret ครั้งเดียว พร้อมข้อความให้เก็บในที่ปลอดภัย; response ทั่วไปและ Inertia shared props ไม่มี secret
- [x] จัดการชื่อและวันหมดอายุตาม policy; เปลี่ยน scopes ผ่านการออก key ใหม่เพื่อตรวจสอบย้อนหลังได้
- [x] ปุ่มถอน key พร้อมยืนยันผลกระทบ; ไม่มี hard delete ประวัติ credential
- [x] create/revoke และ action ที่เพิ่มอายุ key ใช้ password confirmation และ 2FA policy ที่ระบบมี
- [x] ใช้มาตรฐาน dialog, dirty guard และ notification เดิมของโปรเจกต์
- [x] Audit log สำหรับ create/revoke/metadata update บันทึก key ID และ scopes โดยไม่มี secret/hash

## 6. Isolation, abuse control และ performance

- [x] ทุก aggregate query บังคับ org_id จาก key รวมถึง relation, subquery และ cache key
- [x] key ไม่ให้สิทธิ์เรียกหน้า admin, web routes หรือ gateway endpoints และ session login ไม่ใช้แทน key บน API นี้
- [x] ข้อเสนอเริ่มต้น rate limit 60 requests/minute/key พร้อม limiter ฝั่ง IP สำหรับคำขอ key ไม่ถูกต้อง และปรับได้ตามโหลดจริง
- [x] ตอบ rate-limit headers และ Retry-After สำหรับ 429; ป้องกันปริมาณ key/usage ไม่จำกัดตาม policy ที่ตกลง
- [x] เก็บ request_id, key ID, endpoint, status, duration และ last_used_at โดยไม่เก็บ Authorization header หรือ payload ลับ
- [x] response ไม่ถูก shared/public cache; หาก cache aggregate ต้องแยก org/scopes/filter/currency และแจ้ง freshness
- [ ] จำกัด query cost ด้วยช่วงวันที่และ aggregate query (มี implementation/index test แล้ว); ยังต้องตรวจ query plan/index ด้วยข้อมูลขนาดใกล้ใช้งานจริง
- [x] ไม่เปิด CORS wildcard เพื่อรองรับ secret ใน frontend; server-to-server เป็นรูปแบบเริ่มต้น

## 7. Verification และเกณฑ์รับงาน

- [x] Feature tests: ไม่มี key/ผิด/หมดอายุ/ถอนแล้ว → 401; scope ไม่พอ → 403; key ถูกต้อง → 200
- [x] Cross-org tests ทั้ง summary, branch filter และหน้า key management รวมกรณีเดา key/resource ID
- [x] ตรวจ one-time reveal, hash storage และ secret ไม่รั่วใน list props, logs, audit และ errors
- [x] ทดสอบ permission/password confirmation/2FA ตาม policy สำหรับการจัดการ key
- [x] ทดสอบ rate limit, revoke ทันที และ rotation โดยไม่ขยายสิทธิ์ key เก่า
- [x] ทดสอบตัวเลขจาก dataset ที่คำนวณคำตอบไว้: partial payment, reversal, void, overdue, FX และ org ที่ไม่มีข้อมูล
- [x] ทดสอบช่วงวัน ขอบเขต timezone และความต่าง period/snapshot ให้ตรง metric dictionary
- [x] ตรวจ read-only: GET ไม่เปลี่ยนข้อมูลธุรกิจ; อนุญาตเฉพาะ telemetry ของ credential/request
- [x] Regression tests เฉพาะ Dashboard/auth/gateway ที่เกี่ยวข้อง พร้อม Pint, frontend lint/typecheck/build เมื่อมี UI changes
- [x] เขียน OpenAPI specification, ตัวอย่าง curl และคู่มือ create/use/rotate/revoke พร้อม secret placeholder เท่านั้น
- [x] สรุปผลทดสอบจริงใน checklist เมื่อรันแล้วเท่านั้น

## 8. ลำดับงานและขอบเขตรอบต่อไป

1. ตรวจทาน scope/metric dictionary/default policy และ API contract
2. ทำ key authentication, tenant context และหน้า admin พร้อม tests
3. เปิด Sales Summary แล้วตรวจตัวเลขและประสิทธิภาพ
4. เปิด Finance Summary แล้วตรวจตัวเลขและ regression
5. ขยาย summary ทีละโดเมน; HR/Payroll ต้องกำหนด privacy scope เพิ่มก่อนเปิด
6. ออก scope/contract/checklist ใหม่ก่อนเพิ่ม detail/list/export/write API

## 9. จุดที่ต้องตกลงก่อนเริ่มโค้ด

- [x] ยืนยันรอบแรก Sales + Finance และ fields ตาม metric dictionary
- [x] ยืนยัน expiry 90 วัน, rate limit 60/minute/key, date window 366 วัน และ timezone รายงาน
- [x] ยืนยันผู้จัดการ key: owner/admin และข้อจำกัดการให้สิทธิ์เพิ่มเติมผ่าน custom roles
- [x] กำหนดรอบแรกเป็น server-to-server และบันทึกข้อจำกัดว่าแอปผู้เรียกต้องมี backend เก็บ secret; browser/mobile โดยตรงต้องออกแบบ credential flow เพิ่มก่อน

Default policy และรอบแรกยึดตามเอกสารนี้ภายหลังผู้ใช้สั่งเริ่มงาน; implementation/คู่มือ: [EXTERNAL_SUMMARY_API.md](EXTERNAL_SUMMARY_API.md), contract: [OpenAPI v1](openapi/external-summary-v1.json), หลักฐานตรวจ: [gpt.md](../gpt.md)

## 10. ตรวจรับและงานคงเหลือ

- [x] Automated verification: API รอบสุดท้าย 18 tests / 170 assertions และ Pint ผ่าน; regression กลุ่มที่เกี่ยวข้องเคยผ่านรวม API 64 tests / 514 assertions ก่อนเพิ่ม expiry-offset assertion
- [x] Frontend: TypeScript, ESLint ของหน้า API/AuthenticatedLayout และ Vite production build ผ่าน; existing frontend tests 38 passed
- [x] Gemini ตรวจรับ source/contract/tests เทียบ gpt.md ก่อนปิดรอบแรก (ผ่านการตรวจสอบทุกมิติ: API Contract, Metric Dictionary, Tenant Isolation, Key Lifecycle, Telemetry Sanitization, Code Style และ Builds เมื่อ 2026-10-18)
- [ ] Browser UAT: create/reveal/edit/revoke, password confirmation, dirty/minimize/keyboard และ responsive; browser tool เปิด isolated localhost server แล้ว timeout จึงยังไม่อ้างว่าผ่าน
- [x] ตรวจ MariaDB isolated fixture 84,000 rows และ EXPLAIN แล้ว; ยังไม่ใช่ concurrent HTTP SLA/production acceptance (ดู sections 12–13)
- [ ] Deferred ตามคำสั่งผู้ใช้ 2026-10-19: ทดสอบแอปผู้เรียกจริงและติดตั้ง migration/config/build บน environment เป้าหมาย
- [x] แก้ regression `DashboardAccessTest::test_finance_widget_payload_queries_only_authorized_sources` ด้วย startOfMonth ก่อน subMonths; regression เดือน 30/31 วัน/leap year/custom range ผ่านในชุด 85 tests / 1145 assertions

## 11. Round 2 — Projects & Tasks (เริ่ม 2026-10-18 ตามข้อเสนอ Gemini)

- [x] แก้ Dashboard month overflow โดยยึดวันแรกของเดือนก่อนลบเดือน; ทดสอบเดือน 30/31 วัน, leap year และ custom period โดยไม่เปลี่ยน permission semantics
- [x] กำหนด contract ก่อน implementation: `GET /api/v1/summaries/projects`, scope `summary:projects:read`; key เดิมต้องไม่ได้ scope ใหม่อัตโนมัติ
- [x] `data.period`: `projects_created_by_current_status` และ `tasks_created_by_current_status` เป็น arrays `{status,count}` ตาม created_at ในช่วงวันที่; `tasks_completed` นับสถานะ done ตาม completed_at ในช่วงวันที่
- [x] `data.snapshot`: `as_of`, `projects_by_status`, `tasks_by_status`, `active_projects`, `overdue_projects`, `overdue_tasks`, `average_active_project_progress_percent` (decimal string 1 ตำแหน่ง หรือ null เมื่อไม่มีโครงการที่เข้าเกณฑ์)
- [x] Active projects = planning/active/on_hold; overdue projects = active projects ที่ due_date < วันนี้; overdue tasks = todo/in_progress เท่านั้น ไม่รวม blocked/done/cancelled เช่นเดียวกับ Dashboard
- [x] ตรวจ project parent ของ task ว่าอยู่ org เดียวกับ key และไม่ถูก soft-delete; ไม่ส่งชื่อ/รายละเอียด/ผู้รับผิดชอบ งบประมาณ รายจ่าย หรือข้อมูลรายบุคคล
- [x] ไม่เพิ่ม work hours ในรอบนี้: ยังไม่มี timesheet/time-entry schema ของ Project/Task ที่ใช้สรุปชั่วโมงได้ จึงไม่สมมติตัวเลขจาก HR attendance
- [x] เพิ่ม route/controller/service/scopes และ UI label; meta.currency_mode เป็น not_applicable สำหรับ Projects
- [x] เพิ่ม OpenAPI/คู่มือ ตัวอย่าง และ tests ครอบคลุม scope เดิม/ใหม่, tenant/parent isolation, dates/timezone, status/soft delete/empty org, bounded aggregate queries
- [x] รัน API/Delivery/Dashboard/Auth/2FA/Gateway regression 85 tests / 1145 assertions ผ่าน; Pint, TypeScript, scoped ESLint และ build ผ่าน บันทึกผลจริงใน gpt.md
- [x] Gemini ตรวจรับ Round2/P0–P6 รวม ตาม gemini.md section12 วันที่2026-10-19; ไม่ใช่ Browser UAT/production acceptance

## 12. หลักฐาน performance / UAT ล่าสุด

- [x] MariaDB 11.2.2 isolated synthetic benchmark: 2 orgs รวม 84,000 rows ใน deals/invoices/payments/projects/tasks; correctness Sales/Finance/Projects ผ่าน และเก็บ EXPLAIN ทุก aggregate
- [x] runner ตรวจ schema จริงก่อน migrate, ปฏิเสธ writes ผ่าน connection เดิม, ลบ schema สุ่มหลังจบ และตรวจจำนวนแถวตารางหลักในฐานเดิมไม่เปลี่ยน
- [ ] Performance acceptance ยังไม่ปิด: ผลหลัง optimization อยู่ section 13; ต้องวัด concurrent HTTP บน target environment และตกลง SLA ไม่ใช้ผล service benchmark แทน
- [ ] Browser UAT ยังไม่ปิด: local key migration/permissions เปิดใช้แล้วตามคำอนุญาตผู้ใช้; browser tool รอบล่าสุด timeout ทั้ง in-app browser และ Chrome ไม่ถือว่าผ่าน create/reveal/copy/edit/revoke

## 13. Projects performance follow-up (2026-10-18)

- [x] รวม Projects/Tasks จาก 8 เหลือ 2 grouped conditional aggregate queries โดยคง response contract เดิม; task JOIN ตรวจทั้ง parent org และ deleted_at
- [x] เพิ่ม query-budget assertion: endpoint ไม่เกิน 7 queries รวม authentication/telemetry และมี summary aggregate 2 queries เท่านั้น
- [x] API/Dashboard/Delivery regression หลังปรับ: 39 tests / 801 assertions ผ่าน; Pint และ git diff --check ผ่าน
- [x] MariaDB fixture เดิม 84,000 rows: correctness ผ่าน, Sales median 95ms / Finance 105.13ms / Projects 543.58ms (max 1026.02ms), Projects 2 queries; schema cleanup และ original table counts unchanged ผ่าน
- [ ] Deferred ตามคำสั่งผู้ใช้ 2026-10-19: concurrent HTTP load/SLA; ผลก่อน–หลังเป็นคนละรอบ โหลดเครื่องต่างกัน จึงไม่อ้าง speedup ทั้งหมดมาจาก query optimization
- [ ] Browser UAT รอบล่าสุดถูกบล็อกโดย `Local browser use is disabled in Work settings`; fixture SQLite/server ที่เตรียมถูกหยุดและลบแล้ว ไม่เปลี่ยน settings หรือ ERP permissions เพื่อข้ามข้อจำกัด

## 14. HR Summary API — implementation (2026-10-19)

- [x] HR Summary ใช้ Employee รองรับ non-User; current snapshot แยก period started_on/resigned_on; active/probation employment dates และ probation30วัน
- [x] แยก status/type/org units; small groups<5 suppress และ cap100groups; ไม่อ้าง historical headcount จาก current status
- [x] Attendance: distinct employees และ employee-workdays แยกกัน; dedupe/reconciled priority; integer minutes/OT; pending/incomplete/confirmed absent ไม่อนุมานจาก missing punch
- [x] Leave: overlap period/status, approved employee-date dedupe ตาม weekdays/org holidays; ระบุ linked/non-User coverage ไม่รวม total_days ทั้งคำขอข้ามช่วง
- [x] Roster: allocation/day-off/eligible/scheduled/without-any-schedule และ department suppression; ตรวจ employment dates และ parent shift
- [x] ระบุ missing_schedule_workdays=null เพราะไม่มี authoritative required-workday calendar; ไม่อ้าง missing schedule = absence

## 15. Employee Read API — implementation

- [x] Directory/profile UUID/code/names/nickname/org structure/manager/employment dates/type/status แบบ field allowlist; filters/search validation และ cursor limit50/max100
- [x] รองรับ non-User Employee และพนักงานพ้นสภาพใน directory; manager/parents นอก visibility ถูกซ่อน
- [x] Attendance/roster operational endpoints จำกัดช่วงวัน366; ไม่ส่ง GPS/IP/notes/pay rates
- [x] Leave requests ไม่ส่งประเภท/เหตุผล/attachments; balances decimal days ของ current year ไม่อ้างย้อนหลัง
- [x] Leave non-User หรือ invalid bridge ไม่คืน0เสมือนครอบคลุม: 409 unsupported_employee_coverage
- [x] ไม่เปิด work contact เพราะยังแยก email/phone ส่วนตัวไม่ได้; ไม่เปิด salary/bank/identity
- [x] HR visibility ต้อง explicit all branches หรือ own branch IDs; scopes summary/detail/operational แยกกัน

## 16. HR & Employee — Security / Acceptance

- [x] Scopes opt-in และ issuance domain permissions; key เดิมไม่ได้สิทธิ์เพิ่ม
- [x] Tenant/parent/branch/manager guards และ explicit fields; foreign/missing IDs ไม่เปิดข้อมูลข้ามองค์กร
- [x] Framework expiry/revoke/HTTPS/no-store/rate-limit/sanitized logs; ไม่มี credentials/PII/search text ใน telemetry
- [x] Contract/OpenAPI + automated tests เรื่อง privacy/coverage/reconciliation/status/period boundaries และ permissions
- [ ] Browser UAT ของ key policy/branch picker/one-time reveal/copy/revoke และ download
- [x] Gemini ตรวจรวมท้ายรอบแล้ว ตาม gemini.md section12 วันที่2026-10-19 แทน gate ทีละโดเมนเดิม; Browser UAT แยก pending
- [x] Employee directory/HR/Attendance/Leave/Roster summaries: query count คงที่เมื่อเพิ่ม21พนักงาน และ <=20queries รวม framework/auth ใน SQLite
- [ ] Target MariaDB correctness/load/SLA ของโดเมนใหม่ — deferred; benchmark P0 ไม่ครอบคลุม HR

## 17. External App API Roadmap — implementation scope

คำสั่งล่าสุดแทน P0-only: ทำ P1–P6 ต่อให้ครบ แล้วให้ Gemini ตรวจรวม; P7 sensitive/write ตัดออก. กล่องติ๊กด้านล่างหมายถึงโค้ด/สัญญาที่ทำแล้ว ไม่ใช่ production acceptance. [คู่มือรวม](EXTERNAL_READ_API.md), [OpenAPI62paths](openapi/external-read-v1.json).

### P0 — มาตรฐานและ acceptance เดิม

- [x] Summary API Sales/Finance/Projects, scoped keys/admin, guide/server client, test fixtures และ benchmark P0 ตามหลักฐานก่อนหน้า
- [x] Common allowlists/errors/GET-only/rate-limit/no-store; organization/party/HR/document visibility แยกกัน
- [x] Gemini combined technical acceptance ผ่าน ตาม gemini.md section12 วันที่2026-10-19
- [ ] Browser UAT ยัง pending
- [ ] Target deployment/integration/concurrent HTTP SLA deferred ตามผู้ใช้

### P1 — Reference / Products / Inventory

- [x] Reference allowlist branches/divisions/departments/positions/warehouses/currencies; ไม่มี dynamic tables หรือ unit master ที่ไม่มีจริง
- [x] Product/service list/detail + explicit price/cost scopes
- [x] Inventory summary low/negative stock + balances product/warehouse/bin/lot/expiry; signed decimal4, parent org guards
- [x] ส่ง on_hand เท่านั้น ไม่มี reservation ledger จึงไม่อ้าง reserved/available/ATP
- [x] Contract/tenant/fields/filter/cursor/tombstone tests

### P2 — HR / Employee

- [x] Summary/directory/profile และ attendance/leave/roster ตาม sections14–16; scope/policy/unsupported coverage explicit
- [x] ไม่เปิด payroll/health/identity/bank/private contacts

### P3 — Parties / Sales / Payments

- [x] Customer/Supplier directory basic company allowlist ไม่มี personal contacts/tax/bank
- [x] Quotations/Invoices/Delivery Orders list/detail/items, monetary decimal/currency/status; ไม่เปิด generic source file download
- [x] Payments/reversal pointer ตาม row ceiling; ไม่มี bank/gateway credentials และไม่รวมเป็น organization cash balance
- [x] Party key เห็นเฉพาะเอกสารเผยแพร่ของ party; foreign/mixed-parent/issuer-current-permission tests

### P4 — Procurement / Projects / Tasks

- [x] Procurement summary currency/status และ outstanding by exact case-sensitive unit จาก posted GRN ที่ตรง PO/product/unit
- [x] PO/GRN list/detail/items พร้อม parent isolation/received/outstanding; ไม่ใช้ ordered quantity เป็น received
- [x] Projects/Tasks list/detail/project tasks ใช้ current issuer row permissions; แยก summary scopes
- [x] ไม่ส่ง members/comments/private contacts/project financials หรือ work hours ที่ไม่มี source ledger

### P5 — Approval Read / Outbound Signals

- [x] Approval list/detail ตาม assignment/history/active delegation ของ issuer; ไม่มี approve/reject route หรือ client actor override
- [x] Transactional outbox + stable event ID + HMAC/replay/ownership verification/rotation + retry/dead-letter + Admin delivery status
- [x] HTTPS/public DNS/IP pinning/no redirect/no proxy/response limit/timeouts ป้องกัน SSRF; fake transport tests เท่านั้น
- [x] Minimal signals invoice payment/stock/task/approval; recheck key/party/row ceiling ทุก dispatch
- [x] At-least-once/dedupe/out-of-order/full reconciliation ระบุชัด; default flag false ไม่ส่ง live network
- [ ] Verified receiver/scheduler/target integration จริง deferred ตาม deployment

### P6 — Manufacturing / Documents

- [x] Manufacturing summary/BOM/WO/items แบบ stored-state read; formula/cost scopes opt-in พร้อม domain ceiling/foreign parents
- [x] ไม่มี currency snapshot ส่ง null พร้อมเหตุผล; unit summary เป็น Product ปัจจุบัน; ไม่อ้างว่าปิดบั๊ก Phase27 เดิม
- [x] Document metadata explicit IDs/classification/category/link ACL/current-version isolation
- [x] Private stream download ต้อง read+download scope และ scan clean; no-store/nosniff/audit ไม่มี storage paths/public URLs
- [x] Documents ห้ามใช้หลบข้อจำกัด payroll/bank/private data; metadata ไม่เผย internal audit/people

### P7 — ตัดออกตามคำสั่งผู้ใช้

- [x] ไม่สร้าง Payroll/salary/bank/identity/private-data API/scopes
- [x] ไม่สร้าง Write API ทุกชนิดสำหรับแอปภายนอก: create/update/delete/task/approval/stock/payment
- [x] Route contract tests ยืนยันทุก /api/v1 route มีเฉพาะ GET/HEAD และ fields/schema ไม่มีข้อมูลที่ตัดออก
- [x] Admin key/webhook session และ internal telemetry/outbox ไม่ใช่สิทธิ์เขียนธุรกิจของ integration key

### Cross-cutting — Sync / Contracts / Acceptance

- [x] Encrypted cursor30นาทีผูก credential/filter/path + stable(updated_at,id) + fixed sync_watermark; default50/max100
- [x] updated_since inclusive UTC Z เฉพาะ resource รองรับ; tombstones สำหรับ soft-delete; computed resources ปฏิเสธ delta422 และระบุ delta_supported=false
- [x] Guide bootstrap/delta/cursor expiry/full reconciliation; derived/ACL/hard-delete ต้อง reconcile ไม่อ้าง snapshot/exactly-once
- [x] OpenAPI รวม62GETpaths + contract checks scopes/auth/field allowlists; Node client/receiver examples และ fake network tests
- [ ] Query-budget/MariaDB performance evidence ทุก resource และ concurrent load/SLA ก่อนเปิดจริง ยังไม่อ้าง P0 benchmark แทน
- [x] Gemini combined technical review ผ่าน ตาม gemini.md section12
- [ ] Browser UAT ยังต้องตรวจจริง; deployment/integration/SLA deferred

## 18. P0 closeout — 2026-10-19

บันทึกประวัติ P0 ก่อนขยายขอบเขต: ผู้ใช้เคยยืนยัน P0-only ต่อมาเปลี่ยนเป็น P1–P6 และตัด P7 sensitive/write. Target deployment/integration/SLA ยังคง deferred ไม่ใช่ passed.

- [x] Integration guide + server-side client + read-only smoke command; client tests 8 ผ่าน
- [x] มาตรฐาน list/detail/sync/credential visibility เดิมเป็น design; ปัจจุบันมี P1–P6 implementation ตาม section17
- [x] Regression ล่าสุดหลังแก้ mutable Carbon fixture/Monday boundary: 85 tests / 1146 assertions ผ่าน; Pint ผ่าน; client tests 8 ผ่าน; ไม่มี production logic/UI changes รอบนี้
- [ ] Browser UAT: in-app attach timeout และ Chrome execution timeout รอบนี้
- [x] Gemini Round2 technical acceptance ผ่าน ตาม gemini.md section12 วันที่2026-10-19; operational gates ยังแยกอยู่
- [ ] Deferred โดยผู้ใช้: target deployment/integration และ concurrent HTTP SLA
## 19. Combined implementation handoff — 2026-10-19

- [x] API suite 54 tests / 4,731 assertions ผ่าน รวม cursor watermark/recovery และ HR constant query budget
- [x] Regression ระบบเดิม 118 tests / 1,205 assertions ผ่าน: Dashboard/Delivery/Auth/2FA/Gateway/HR/Employee/Roster/Workflow/Documents/Inventory/Manufacturing
- [x] Node client/receiver tests13ผ่าน; OpenAPI62GETpaths สอดคล้อง routes/scopes/field restrictions
- [x] TypeScript, ESLint เฉพาะหน้า API Access/Webhooks, Vite production build และ Pint ผ่าน
- [x] README/guide/contracts/checklists/gpt.md อัปเดตขอบเขต read-only; ไม่มี live network/real key changes หรือ new migrations บน ERP จริง
- [x] Gemini ตรวจรวม implementation/ข้อจำกัดแล้ว: gemini.md section12 วันที่2026-10-19 มีผล API54tests/4731assertions, regression118tests/1205assertions, Node13tests และ62GETpaths; อ้างผลผู้ตรวจ ไม่ใช่รันทดสอบใหม่รอบนี้
- [ ] Browser UAT pending; deployment/integration/SLA deferred ตามผู้ใช้
