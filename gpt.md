# GPT Decision Log

## สถานะ Phase 23.2 — 2026-09-20

**Phase 23.2 Complete & Done (2026-09-20).** ตรวจรับและปิด Phase 23.2 สมบูรณ์ทุกข้อย่อย 23.2.1–23.2.7 ตามเกณฑ์ Exit Criteria โดยรายการที่ไม่พร้อมบันทึกส่งมอบต่อใน Phase 23.3 (HR/Attendance) และ Phase 25 (Advanced Analytics) ตาม `docs/PHASE_23_2_5_DEFERRED.md`.

- **Backend Full Regression:** **344 passed / 3,558 assertions, 2 skipped** (ต้องใช้ isolated MySQL Phase 23/24). 0 failed.
- **Frontend Node Tests:** **38 passed / 0 failed** ครอบคลุม dashboard filters, widgets, keyboard charts, line-item errors, discard guards, org dirty checks และ Today/This Week filters.
- **Code Quality & Builds:** TypeScript `node_modules/.bin/tsc.cmd --noEmit` 0 errors, ESLint `node_modules/.bin/eslint.cmd resources/js --max-warnings=0` 0 errors 0 warnings, Laravel Pint passed, Production Vite build passed, `git diff --check` passed.
- **Edge-Case Metrics (`DashboardMetricsEdgeCasesTest`):** 6 passed / 158 assertions ครอบคลุม date/timezone boundaries (ช่วงต้น/สิ้นเดือน Aug 1 vs Jul 31 และ Aug 31 vs Sep 1), zero values & empty organization handling, partial payments, negative reversals net cash, void/cancelled/draft/rejected document exclusions, และ FX multi-currency base conversions.
- **Performance Budget (`DashboardPerformanceTest`):** ทดสอบ isolated fixture 1,000 records ต่อแหล่งข้อมูลหลัก ทุก dashboard อยู่ในงบ query <= 50 และ response < 1 วินาที: My Work 13/67ms, HR 13/50ms, Portal 14-15/47-60ms, Procurement 22/67ms, Finance 25/52ms, Delivery 35/336ms, Sales 41/85ms, Executive 41/178ms, Admin 48/278ms.
- **Browser Acceptance Matrix (`temp/phase232-browser/results.json`):** ครบ 60 checks: 8 dashboards ที่ Desktop 1440px และ Mobile 390px ใน Dark และ Light themes, ไม่มีการล้นจอ (`scrollWidth <= innerWidth`), ตรวจ Role variants (Owner, Readonly Finance, Payroll User, Denied 403, Portal Customer/Supplier magic-link isolation), keyboard chart data-table disclosure (`Enter`), filter validation alert (`From > To`) และ URL reload persistence โดยมี 0 runtime errors.
- **Menu Reorganization:** ปรับโครงสร้างเมนูหลักใน `AuthenticatedLayout.tsx` เป็น 7 กลุ่มมาตรฐาน (Overview, Sales & CRM, Procurement & Inventory, Finance & Accounting, HR & People, Delivery & Projects, Administration & Settings).
- **Next Phase:** Phase 23.3 (HR Employee Master, Shift Scheduling & Attendance Import) ต่อไป.

## สถานะ Phase 23.2.2 — 2026-09-17

**Complete.** Metric contract อยู่ที่ `docs/PHASE_23_2_2_METRIC_DICTIONARY.md`: ใช้ base-currency snapshots, แยก flow จาก as-of snapshot, กำหนด period/timezone/previous-period และห้ามใช้ metric ที่ historical reconstruction หรือ policy ยังไม่รองรับ.

- `Outstanding AR`, aging, open pipeline และ overdue task เป็น current snapshot เท่านั้น.
- `Cash In` และ AP Cash Out แยก receipt/payment ออกจาก reversal; ยังไม่อ้างเป็น company-wide cash flow.
- Gross Profit, Project Margin, Low Stock, HR active/attendance และ Security Alerts ที่ยังไม่มี source/policy ตรงนิยาม ถูกล็อกเป็น Unavailable จนกว่าจะเพิ่ม source/test.

## สถานะ Phase 23.2.1 — 2026-09-17

**Complete.** จัดทำ inventory ที่ตรวจ source จริงแล้วใน `docs/PHASE_23_2_1_DASHBOARD_INVENTORY.md` ครอบคลุม internal 8 domain และ Customer/Supplier Portal พร้อม route/controller/page/navigation, source, permission/row scope, date/currency basis, drill-down และ readiness gap.

- Dashboard ปัจจุบันมี Admin, Executive, Finance, Delivery, Sales และ Portal; Procurement/Inventory, HR/Payroll และ My Work ยังไม่มี dashboard เฉพาะ.
- Metric ที่เป็น historical/as-of, currency, security alert, delivery margin และ HR attendance ถูกระบุเป็น decision ของ Phase 23.2.2 ก่อน implementation.
- Portal คงใช้ portal identity/grant แยกจาก staff dashboard และห้ามส่ง internal aggregate ไป client portal.

## สถานะตรวจรับ Phase 23.1.6 — 2026-09-16

**Approved / Complete & Closed บน Verified Scope.**

- E01 dirty detection, E02 เอกสาร rollout/routes, E03 read-only UI gate และ E04 Prettier แก้แล้ว.
- Full backend: **316 passed, 2 skipped, 2,609 assertions**.
- Frontend unit: **34 passed, 0 failed**.
- `tsc --noEmit`, ESLint และ Prettier ผ่าน.

ประวัติข้อที่ปิดแล้วเก็บไว้ที่ `temp/phase2316-gemini-closure-before-review.md`.


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
