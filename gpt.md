# GPT Decision Log

## สถานะและงานค้าง

- **Phase 23.1: Complete & Closed (2026-09-16)** — ตรวจรับ Exit Criteria 23.1.6 ผ่านครบถ้วนบน Verified Scope:
  - Inventory 91 surfaces บน 60 หน้าจอ 92 ไฟล์ได้รับการปรับปรุงสู่ `CreateEditDialog` หรือมี Documented Exceptions ครบถ้วน
  - ไร้ duplicate forms หรือ dead imports/components, ESLint/Prettier/Vite build 100% clean
  - Backend regression 311 tests (2,575 assertions), Frontend unit 23 tests ผ่าน 100%
  - สรุปขอบเขตและอัปเดต `README.md`, `docs/CURRENT_IMPLEMENTATION.md`, `docs/ROUTES_AND_SCREENS.md`, `checklist.md` ครบถ้วน
- **Deferred B01–B08:** งาน browser/network/performance matrix ที่เหลือคงเป็น deferred ตามข้อตกลงผู้ใช้อนุมัติ 2026-09-15 (ดูขั้นตอนและเกณฑ์ใน `docs/PHASE_23_1_5_REMAINING_ACCEPTANCE.md`)
- **Phase ถัดไป:** Phase 23.2 System-wide Dashboard Redesign (ตาม `erp-dashboard-design-concept.md`) หรือ Phase 24 Direct e-Tax Foundation


## ข้อตกลงและงาน deferred ที่ยังมีผล

- Browser acceptance ทั้ง Phase ยังไม่ครบ: external Portal, processing/network failures, permission combinations, native reload, physical mobile keyboard, Safari/WebKit และ MySQL concurrency ยังต้องตรวจแยก.
- ห้ามส่ง exception message ดิบเข้า UI/log เพราะอาจมี token/URL/response secret; ใช้ข้อความทั่วไปกับ metadata ที่อนุญาต.
- DLQ replay/reset ยัง deferred: ต้องกำหนด permission/org, audit, retry cap, message expiry และ recipient authorization ก่อน replay. Quiet hours คง suppress; defer ต้องมี policy expiry/dedupe/urgent precedence ก่อนเปลี่ยน behavior.
- คง 12 backend-only write routes เป็น backend-only; ไม่สร้าง UI เพิ่มโดยไม่มี requirement. แยก Portal identity/scope จาก staff auth และคง backend authorization.
- การแก้ security/data-integrity/regression/production blocker ใน Phase ที่ปิดแล้วทำได้เมื่อมี test และบันทึกเหตุผล. การปิด Phase ไม่ใช่ production certification หรือคำสั่งเปิด live integrations/ส่ง notification จริง.
- Destructive database tests ใช้ฐานข้อมูลแยกเท่านั้น: ผล MySQL เดิมใช้ `127.0.0.1:33073`, `erp_phase23_test`; ห้ามใช้ operational `erp`. สถานะ service/runtime เก่าไม่ใช่สถานะปัจจุบัน ต้องตรวจใหม่ก่อนใช้งาน.

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
- Opn reserves `creating` before an external API call; ambiguous results become `creation_unknown` and block retries. Pending/unresolved charges block provider, mode and merchant-key changes; uncertain creation also blocks receiver changes after local expiry. Provider callback confirmation alone never posts accounting.
- Opn `provider_paid_at` comes from authenticated charge retrieval and is distinct from final `settled_at`. QR expiry checks customer payment time; receipt/GL uses settlement date. A two-day payout delay is covered by the native-provider fixture test. Do not collapse these timestamps when implementing the real settlement bridge.
- Opn test mode never posts Payment/GL, even after a signed settlement with provider confirmation. Accounting tests use mocked live-mode provider responses, not real API calls. Payment/settlement timestamps normalize to the existing application timezone (UTC).

- Portal ใช้ external identity แยกจาก staff session, scoped access, expiry/revocation, rate limit, audit และ private DMS authorization; ไม่มี public document URL.
- Portal intake ห้ามสร้าง AP/GL อัตโนมัติ; ต้องผ่าน scan และ finance review ก่อน.
- Payment gateway ต้อง verify signature, deduplicate, handle event ordering และ reconcile invoice/payment/final settlement ก่อนสร้าง receipt หรือ GL.
- e-Tax ต้องใช้ certified provider, key vault/HSM boundary, outbox/reconciliation และ legal review; ห้ามเก็บ private key ใน database.
- OCR สร้าง assisted draft พร้อม confidence, source retention และ human review เท่านั้น; ห้าม auto-post.
- Forecast ต้องมี scenario/assumption snapshot และไม่เปิด raw cash balance.
- BOM/POS เป็น optional vertical; เริ่มเมื่อยืนยัน business fit ด้าน manufacturing/retail.
