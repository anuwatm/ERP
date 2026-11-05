# GPT Handoff — งานที่ยังเปิด

อัปเดต 2026-11-05 ใช้ [checklist](checklist.md) ติดตามงาน และ [Gemini](gemini.md) ดูผลตรวจรับล่าสุด

## งานถัดไป

- Phase 28.8.6 ยังเลื่อนไว้ ขอบเขตการจ่าย QR, Card/EDC, mixed tender และ shared-stock connection model อยู่ใน checklist
- Restaurant POS ยังรอทดสอบมือถือ, HTTPS LAN และอุปกรณ์จริง ใช้คู่มือ Phase 28.8.5 สำหรับตั้งค่า

## งานที่ยังรอระบบจริงและข้อจำกัด

**ยังไม่ได้ทดสอบกับระบบจริง** — local acceptance ไม่ใช่production sign-off.

- e-Tax provider/channel/certificate/signing adapterและcontractจริง;signing ownerเลือกเป็นบุคคลได้แล้ว แต่ไม่ได้แปลว่ามีcertificateหรือส่งproviderแล้ว.
- ProvisionฐานPOSของสาขาที่จะใช้จริง:branch/terminal/staff/keys/catalog/quota/issuer profile;อย่าสร้างแทนผู้ใช้จากบัญชีsyntheticหรือใช้fixtureเป็นฐานขาย.
- Two-server deployment/firewall/mTLS/credential rotation,merchant/settlement onboardingและอุปกรณ์printer/scannerจริง.
- MariaDB concurrent quota/numbering/load/crash,Manufacturing target concurrencyและMySQL testsที่ข้าม;ห้ามใช้SQLiteแทนrow-lock/load proof.
- Target HTTPS/session/cookie/domain,storage/font permissions,log handlers/queue/scheduler/monitoring-owner,backup retention approvalและproduction ERP/POS restore drill/UAT/SLO/tax acceptance. นโยบายarchivePOS90วันที่อนุมัติแล้วไม่ใช่การอนุมัติbackup retentionทุกระบบ.
- Browserผลล่าสุดเป็นChrome/responsive emulation;physical mobile keyboard/touch,Safari/WebKitและoperator UATยังไม่รับรอง.
- Notification DLQ replay/resetและquiet-hours defer policyยังต้องกำหนดpermission/org/audit/retry cap/expiry/dedupe/recipient authorizationก่อนเปลี่ยนbehavior;quiet hoursคงsuppressตามเดิม.

## ข้อกำหนดทำงานต่อ

- รักษาPOS/ERPแยกdatabase,credentials,sessionและAPP_KEY;ไม่มีPOS writeเข้าERP DBตรง ใช้connector/contractsและserver-derived scopeเดิม.
- Production flags/local hard-blocksคงเดิมจนมีขอบเขตและtarget acceptanceที่เหมาะสม;คำสั่งปรับเอกสารไม่ใช่คำสั่งเปิดระบบจริง.
- การทดสอบเปลี่ยนข้อมูลใช้isolated fixtureเท่านั้น;ตรวจenvironment/pathใหม่ก่อนรัน ไม่ใช้operational databaseหรือmigrate:freshบนฐานใช้งาน.
- Backend-only write routesที่เป็นdocumented exceptionsไม่ต้องเพิ่มUIหากไม่มีrequirement;ฟีเจอร์28.8ให้ยึดrequirementใหม่ของผู้ใช้.
- ห้ามส่งexception message/raw provider responseหรือsecretsเข้าUI/log;ใช้generic messageและallowlisted metadata.
- การส่งไฟล์ให้Geminiที่อนุญาตไว้ครอบคลุมเฉพาะ6ไฟล์POSConfigในreview record;ไม่ถือเป็นสิทธิ์ส่งโค้ด/ข้อมูลใหม่ทุกส่วนออกไป.

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

## ประวัติและแหล่งอ้างอิง

- [Handoffเดิมก่อนcleanup](document/history/GPT_HANDOFF_20261030_PRE_CLEANUP.md):เก็บครบทั้งผลตรวจPhase27เก่า,APIrolloutและPhase28รายวัน. สถานะpending/ข้อกล่าวหาbugเก่าในนั้นต้องตรวจsourceและหลักฐานใหม่ ไม่ยกกลับเป็นงานค้างโดยอัตโนมัติ.
- [Current implementation](docs/CURRENT_IMPLEMENTATION.md) · [Production runbook](docs/PRODUCTION_RUNBOOK.md) · [Phase28 runtime](docs/PHASE_28_POS.md) · [POS config](document/POS_CONFIGURATION.md).
