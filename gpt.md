# GPT Decision Log

Last updated: 2026-09-10

Purpose: เก็บเฉพาะสถานะปัจจุบัน, ข้อตกลงที่ยังมีผล และ guardrail ของงานที่ยังไม่เริ่ม. รายละเอียด Phase ที่ปิดแล้วดู `checklist.md`, `README.md`, `gemini.md` และ Git history.

---

## Current Status

- Phase 1-22: closed.
- Latest verification (2026-09-10): SQLite regression 57 tests / 325 assertions; isolated MySQL Phase 23 plus concurrency 15 tests / 101 assertions, including migration/rollback. The configured local database migrations are complete. Live provider certification is still not complete.
- Phase 23: closed on 2026-09-10 by explicit user scope decision. QR/Settings, Opn checkout/event verification, signed settlement intake, Payment/GL reconciliation and local MySQL verification are complete. The user has not requested the actual settlement bridge connection, so merchant provisioning, live bridge integration and sandbox/banking scan are deferred outside Phase 23 closure. They are not claimed as tested or completed. No live-payment configuration or accounting guard was changed by this closure. Details: `docs/PHASE_23_PAYMENT_GATEWAY.md`.
- งานที่เป็น security, data integrity, regression หรือ production blocker ใน phase ที่ปิดแล้ว แก้ได้เมื่อมี test และบันทึกเหตุผล.

## Active Guardrails

### Security and access

- ใช้ `User::hasPermissionCode()` เป็นจุดกลางของ permission. Sensitive write ใช้ `password.confirm` ตาม scope เดิม.
- ห้ามส่ง password, token, secret, `person_id`, bank account number หรือ PII ดิบไป UI, log หรือ Inertia props. `AuditLog` ต้อง redaction อย่างต่อเนื่อง.
- การ disable user, เปลี่ยน role หรือ permission ต้อง invalidate session และ clear/rotate `remember_token` ตาม implementation เดิม.
- ห้ามเผยแพร่ raw cash balance ใน widget, API, Inertia prop หรือ notification digest จนกว่าจะมี product decision และ bank-reconciliation boundary.
- 2FA เป็น org policy ที่ค่าเริ่มต้น `enabled=false`; เมื่อเปิดและ `required_for_privileged_roles=true` จึงบังคับ owner/admin/finance. TOTP ต้องทำงาน offline ได้.
- Attendance IP/GPS เป็น opt-in เท่านั้น: IP เก็บ hash, GPS ต้องมี consent/purpose/retention/access control และมี fallback สำหรับงานนอกสถานที่.

### Accounting and document integrity

- Journal, payment, reversal และ workflow transition ต้อง idempotent. งวดบัญชีที่ปิดห้าม post/void/แก้ย้อนหลัง.
- เอกสารเงินตราต่างประเทศต้อง snapshot currency, exchange rate และ base amounts; ห้ามคำนวณย้อนหลังจาก exchange rate master.
- `VendorPayment` และ AR `Payment` เป็นคนละ flow. Expense ตั้ง AP; GRN ใช้ Inventory/GRNI; payroll ไม่ผ่าน AP subledger.
- DMS ใช้ `documents`, immutable `document_versions` และ many-to-many `document_links`. Download/link ต้องตรวจ org, parent permission, sensitivity และ scan status ทุกครั้ง.
- Retention ใช้ policy ตาม category และ effective date; legal hold บล็อกการลบ/ทำลาย. เอกสาร tax/finance ที่ posted หรือ accepted เป็น append-only.

### Workflow and HR

- Approval ใช้ `WorkflowEngineService` กลางสำหรับ Leave, PR, PO และ Expense. ต้อง snapshot definition/assignee ณ เวลาส่ง, บังคับ SoD, delegation scope/expiry และ immutable audit.
- Reject/revision ต้องปรับ source status ตาม domain contract; leave ที่ถูก reject ต้องคืน entitlement แบบ transaction. Expense final approval ต้องผ่าน GL posting เดิมเสมอ.
- Attendance summary รองรับ leave `submitted` และ `approved` แต่ห้าม auto-post payroll/GL ก่อน lock period และ import contract ที่ชัดเจน.

### Operational notifications

- Notification ต้องผ่าน `notification_events` dedupe และ `notification_outbox` per channel. Dispatch retry แบบ exponential backoff ได้สูงสุดห้าครั้ง และเก็บ failed/dispatch history เพื่อแก้ไขงานได้.
- LINE, Slack และ Telegram config เป็น encrypted org setting. External delivery ต้องเปิด channel และ user opt-in พร้อม quiet hours/urgent-only preference.
- Daily digest ห้ามมี raw cash balance; ใช้เฉพาะ operational count ที่ guardrail อนุญาต.

## Future Roadmap Decisions

### Phase 22 and later

- Phase 23 implementation details and diagram: `docs/PHASE_23_PAYMENT_GATEWAY.md`. Intent amount uses integer satang; webhook hashes exact bytes with a five-minute signature window, deduplicates events and locks invoice before receipt/GL posting. Closed periods roll back the entire event transaction. Late/mismatched payments are review-only.
- HMAC is a trusted settlement-bridge protocol, not an assumed provider API. Opn requires API verification, 2C2P uses provider JWT and inquiry contracts, GB Prime Pay requires the contracted integration version. No live settlement claim without provider evidence.
- `mysql8` Windows service start was initially denied. The configured MySQL server at `127.0.0.1:3306`, database `erp`, subsequently became reachable; all four pending Phase 21-23 migrations completed as batch 14 on 2026-09-10. Destructive tests use only a separate MySQL 8.0.17 instance at `127.0.0.1:33073`, database `erp_phase23_test`, data under `temp/phase23-mysql-20260909`; never the operational database. See Phase 23 verification record for results.
- Opn reserves `creating` before an external API call; ambiguous results become `creation_unknown` and block retries. Pending/unresolved charges block provider, mode and merchant-key changes; uncertain creation also blocks receiver changes after local expiry. Provider callback confirmation alone never posts accounting.
- Opn `provider_paid_at` comes from authenticated charge retrieval and is distinct from final `settled_at`. QR expiry checks customer payment time; receipt/GL uses settlement date. A two-day payout delay is covered by the native-provider fixture test. Do not collapse these timestamps when implementing the real settlement bridge.
- Opn test mode never posts Payment/GL, even after a signed settlement with provider confirmation. Accounting tests use mocked live-mode provider responses, not real API calls. Payment/settlement timestamps normalize to the existing application timezone (UTC).
- MySQL exposed a Phase 23 fixture with a 7-character customer code against `CHAR(6)`; corrected to `GATE01`. Phase 15 rollback also needed foreign keys removed before columns and an organization index restored before dropping its composite replacement. These are compatibility fixes, not inventory business-rule changes.

- Portal ใช้ external identity แยกจาก staff session, scoped access, expiry/revocation, rate limit, audit และ private DMS authorization; ไม่มี public document URL.
- Phase 22 completed: one-time 30-minute Magic Link, hashed/revocable 8-hour portal session, org/party-scoped customer and supplier dashboards, invoice draft from accepted quotation, and vendor bill quarantine. Portal intake ห้ามสร้าง AP/GL อัตโนมัติ; ต้องผ่าน scan และ finance review ก่อน.
- Payment gateway ต้อง verify signature, deduplicate, handle event ordering และ reconcile invoice/payment/final settlement ก่อนสร้าง receipt หรือ GL.
- e-Tax ต้องใช้ certified provider, key vault/HSM boundary, outbox/reconciliation และ legal review; ห้ามเก็บ private key ใน database.
- OCR สร้าง assisted draft พร้อม confidence, source retention และ human review เท่านั้น; ห้าม auto-post.
- Forecast ต้องมี scenario/assumption snapshot และไม่เปิด raw cash balance.
- BOM/POS เป็น optional vertical; เริ่มเมื่อยืนยัน business fit ด้าน manufacturing/retail.
