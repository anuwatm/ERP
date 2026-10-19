# P5 — Approval read & outbound webhook contract

ผู้ใช้ยกเว้น Write API ทั้งหมด 2026-10-19: ไม่มีการอนุมัติหรือเปลี่ยนข้อมูลธุรกิจผ่าน integration key; webhook เป็นสัญญาณให้ผู้รับกลับมาดึงข้อมูล ไม่ใช่คำสั่งเขียนเข้า ERP

## Approval read

GET /api/v1/approvals และ /approvals/{id}; scope approvals:read ออกได้เมื่อมี workflows.approve ปัจจุบัน actor ceiling = ผู้สร้าง key ที่ยัง active เท่านั้น ไม่รับ user_id/approver จาก client เห็นเฉพาะที่ถูก assign/เคยลงมือ หรือ delegation ที่ยังมีผลและตรง subject type; ไม่สวมสิทธิ์ approver หรือทำ approve/reject

Fields: approval UUID, workflow_instance_id, step_no, status, acted_at, updated_at และ subject_type/id/status ของ instance; ไม่ส่งชื่อผู้ลา/เหตุผล/ความคิดเห็น/subject_snapshot/definition_snapshot/เอกสารแนบ ตรวจ parent/org จริงเสมอ ไม่มีข้อมูลเงินเดือนหรือบัญชีธนาคาร

## Webhook

- Admin session + api_keys.manage + password confirmation/2FA เดิม จัดการ endpoint/secret/verification/pause/rotation และ delivery status; integration key ไม่มี write route สำหรับตั้งค่า webhook
- ผูกแต่ละ endpoint กับ integration key เดียวใน org เดียวกัน; ส่งเฉพาะ events ที่ key มี scope/party/row access ปัจจุบันก่อน dispatch ทุกครั้ง key expiry/revoke หยุดส่งทันที
- Events: invoice.payment.status.changed, stock.balance.changed, task.status.changed, approval.status.changed; immutable event ID/schema_version/occurred_at/resource_type/resource_id ไม่มี amounts/names/PII/secret; client dedupe event ID และ fetch latest state เพื่อจัดการ out-of-order
- Transactional outbox ผูกกับ model save transaction ของแหล่งข้อมูลที่ติดตั้ง observer; bulk SQL ที่ข้าม model events ไม่ใช่ event API ต้องใช้ domain service/model path ข้อมูลลบและการเปลี่ยนสิทธิ์ใช้ full reconciliation ไม่อ้าง tombstone webhook
- At-least-once; timeout อาจส่งสำเร็จแล้ว ให้ retry event ID เดิม ไม่อ้าง exactly-once; retry สูงสุด5 attempts พร้อม exponential backoff/Retry-After แล้ว dead-letter
- HMAC SHA256 ของ timestamp + '.' + exact request body; headers X-ERP-Event-Id และ X-ERP-Signature=t=...,v1=...; receiver ตรวจ timestamp window5นาทีและ timing-safe compare พร้อม dedupe เก็บ secret encrypted at rest และ reveal ครั้งเดียว JSON ไม่ผ่าน Inertia/session
- ยืนยันปลายทางด้วย signed challenge: POST verification challenge ผู้รับต้องคืน challenge เดิมและ HMAC(secret, 'verify:' + challenge); rotate แล้ว pause จน verify ใหม่สำเร็จ
- URL เฉพาะ HTTPS/443 ไม่มี userinfo/fragment/redirect; ตรวจ A/AAAA ทุก IP ว่า public, ปฏิเสธ private/reserved/loopback/link-local/mapped addresses และ pin DNS IP ใน cURL ต่อ attempt ป้องกัน DNS rebinding; ไม่ใช้ proxy จาก environment
- Logs/delivery history เก็บแค่ event ID, status/code, attempt count, timestamps ไม่เก็บ response body/secret/signature header; จำกัด endpoint/dispatch batch และ request timeout
- ค่าเริ่มต้น external_api.webhooks_enabled=false; ไม่ส่ง network จริงหรือเปิด live integration ระหว่างพัฒนา ต้อง deploy migration/config แล้วตรวจปลายทางจริงตามงาน deferred ก่อนเปิดใช้

Tests: assignment/delegation/foreign parent, no approval writes, transactional rollback/outbox, retry/dedupe/dead-letter, revoke/party visibility, secret redaction, signature/challenge และ SSRF/redirect/DNS pinning ทั้งหมดใช้ fake transport ใน tests
