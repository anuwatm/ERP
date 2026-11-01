# GPT Handoff — สถานะปัจจุบันและงานต่อ

อัปเดต 2026-11-01. ใช้ [checklist.md](checklist.md) เป็นทะเบียนงาน, [gemini.md](gemini.md) เป็นบันทึก reviewer และ source/tests เป็นหลักฐาน implementation. รายละเอียดงานที่ปิดแล้วเก็บในเอกสารเฉพาะเรื่อง ไม่คัดลอกผลหลายรอบมาซ้ำใน handoff.

## สถานะที่ยืนยันแล้ว

- งานค้าง local ในขอบเขตเดิมปิดแล้ว: Phase18 polish, local production tooling/runbooks, Phase23.1 browser acceptance, External API local acceptance และ diagram cleanup. [ผลรวม/ข้อจำกัด](docs/LOCAL_ACCEPTANCE_20261030.md) · [หลักฐาน](document/acceptance/20261030/README.md).
- ผลตรวจรอบปิดงาน: ERP538 tests/9424assertions รวม2skippedที่ต้องใช้isolated MySQL Phase23/24; POS45tests/268assertions; frontend38PASS. Dialog matrix284PASS/0FAIL และ60NO_CREATE_SURFACEที่ไม่นับผ่าน; contextual36PASS. เป็นผลbaselineรอบ2026-10-30; ผลPhase28.8.xรอบใหม่แยกด้านล่าง.
- Phase28 core Retail/Restaurant/Pharmacy และ POS Config ผ่าน local scope; Geminiหมวด17เป็นPhase28 baseline และ [POS Config review](document/POS_CONFIG_GEMINI_REVIEW_20261029.md) ครอบคลุมเฉพาะ6ไฟล์ที่อนุญาต ไม่ใช่การรับรองERP fixesทั้งหมดหรือproduction.
- External Read API P0–P6 มีimplementationและผ่านtechnical reviewตามGeminiหมวด12; localHTTP9/browser key lifecycle2ผ่านแล้ว. ไม่คงRound2/หน้าAPIAccessเป็นpendingจากบันทึกเก่า. P7 sensitive/write APIs ถูกตัดออกตามขอบเขตเดิม. [API contract](docs/EXTERNAL_SUMMARY_API.md) · [API checklist](docs/EXTERNAL_SUMMARY_API_CHECKLIST.md).
- Diagram18/18ผ่านautomated showcase/containment; visualReview=pendingยังไม่ใช่human sign-off. [Diagram status](document/DOCUMENTATION_UPDATE_20261029.md).
- Understand publishล่าสุดก่อนปรับchecklist28.8/cleanupนี้:1125files/3242nodes/10175edges/10layers/22tour, source drift0ขณะpublish. กราฟยังไม่รวมโค้ดPhase28.8.1/28.8.2และเอกสารหลังpublish ไม่อ้างว่าfingerprintsยังcurrent. [Pipeline/ข้อจำกัด](document/UNDERSTAND_PIPELINE.md).
- Configured DB snapshot2026-10-27: ERP83/POS17migrations,pending0;ไม่ใช่การตรวจฐานจริงใหม่วันนี้. [Upgrade evidence](document/DATABASE_UPGRADE_20261027.md). รอบDeliveryมีcode totals POS19/ERP84; ปัจจุบันรวม28.8.2เป็นPOS21/ERP87 ยังไม่migrateconfigured DBและไม่แก้.envจริง.
- Fixture serverปิดแล้ว, synthetic credentials/portal tokens/sessionถูกถอนหรือลบ;เก็บหลักฐานผลทดสอบไว้ ไม่ใช้สถานะruntimeจากประวัติเป็นปัจจุบัน.

## Phase28.8 — สถานะและงานถัดไป

### กำลังทำ28.8.3 — 2026-11-01

Foundationแล้ว: OfflineIngressService/OfflinePermitService/API/migration2026_11_01_000001, managerpolicy+Stockholds+20slots, noexpiryreclaim, encrypted IndexedDB(PBKDF2/AES-GCM)+revisionCAS+partial-loss guard, TrustedClock(context-only/freshonline/rollback/suspend). ยังไม่มีPWA UI/serviceworker/quota queue binding/sync ACK/financial reconciliation; ทุกinboxreviewและoffline_sale_authorized=false. ผู้ใช้ยืนยันdefaults15min/20txn/THB10000(Managerปรับ), unlockpassphrase12ไม่เก็บplaintext/no recovery, cashเฉพาะordinary approvedSKU; clinical/member/promo/kitchen/Deliverydraft-only. Late/price/closedshift: Managerรับcustodyเข้ากะปัจจุบันและreviewยอดเดิม ใช้วันที่ตรวจรับในlocal รอtax/accountingจริง. POSreg86/1891ก่อนHTTPtestเพิ่ม, targetedfinal6/53+TS+Chrome storage/clockผ่าน, persistent=falseจริง. [สถานะ/งานต่อ](document/PHASE_28_8_3_OFFLINE.md) · [หลักฐานfoundation](document/acceptance/phase28_8_3/README.md). CodePOS22/ERP87; configuredDB/.envไม่แตะ. ใช้nativePHP/TS/Playwrightทดสอบจริงก่อนปิดphase. Phase28.8.2manifestเป็นsnapshotเก่า ไม่อ้างsourcehashcurrentหลังเริ่ม28.8.3.

### ปิด local 28.8.2 — 2026-11-01

Local membership/promotions complete ตามผู้ใช้ยืนยัน: ERP master/encrypted pending enrollment/manual review/masked publication15min, exclusive fixed/percentก่อนVAT, allocated rights/CAS, partial/full refund, COD fees/retry/inspection/release แยกStock. ERP subset82/1937, POS81/1851, TS/buildทั้งสองapp, browser1280/375ทุกPOS+stale409/lost-response/reviewผ่าน. ActualRetail signed roundtrip10events=7sales+3requests, replayไม่เพิ่มInvoice/Payment/GL/claim; independent26signaturesผ่าน. [หลักฐาน](document/acceptance/phase28_8_2/README.md) · [คู่มือ/diagram](document/PHASE_28_8_2_MEMBERSHIP.md). Fixturesealed: serverหยุด, activeusers0, sessions/privatekeysถอน; ไม่ลบmember/audit. ConfiguredDB/.envไม่เปลี่ยน; codeERP87/POS21ยังไม่migrateฐานจริง. Production gates: บัญชี/ภาษี/privacy/retention/keyrotation, two-server/mTLS/MySQL concurrency/อุปกรณ์จริง. Geminiตรวจlocal scopeแล้วตามgemini.mdหมวด9ที่ผู้ใช้ให้ดู; ไม่ส่งไฟล์เพิ่มออกภายนอก. ห้ามreusefixtureที่sealed.

### ปิด local 28.8.1 — 2026-10-30

Retail/Restaurant: workflow/edit/retry/fees/return ครบ, shared quota reservation, Restaurant kitchen/waste เดิม, prepaid refund/inspection bridge, COD collect/remit/refund และข้ามกะเป็น cash-custody evidence ตามผู้ใช้ยืนยัน. Pharmacy Delivery ปิด. Signed delivery revisions เข้า ERP/checkpoint โดยไม่สร้าง COD ABB/Payment/GL/Stock Movement; ส่งสำเร็จยัง hold stock รอ settlement review. [คู่มือ/diagram](document/PHASE_28_8_1_DELIVERY.md) · [หลักฐาน](document/acceptance/phase28_8_1/README.md).

ผลใหม่: POS61tests/432assertions; ERP Phase28/Manufacturing62tests/509assertions (subset ไม่ใช่full ERP); Chrome1280/375 สอง lifecycle ผ่านพร้อม stale409/lost-response/รับคืน/คืนเงิน/restock; actual POS signed roundtrip35revisions/checkpoint verified/issues=[]. ชุดตรวจทับซ้อน ไม่บวกยอด. Gemini review เดิมไม่ครอบคลุมโค้ดนี้ ไม่ส่งออกด้วยสิทธิ์POSConfig6ไฟล์.

Releaseแยก: บัญชี/ภาษี COD/fees, ABB/FULL/COGS/account mapping/settle stock และระบบ/อุปกรณ์จริง/two-server/mTLS/MariaDB concurrency. POS2/ERP1 migrations ใหม่ยังไม่รันกับconfigured DB; flagdefaultfalse และไม่แก้.envจริง. ไม่ขยาย28.8.2/28.8.3โดยอัตโนมัติ.

Geminiหมวด18.3รับรองระดับแผนพร้อมdesign notes ไม่ใช่ผลทดสอบฟีเจอร์ใหม่. Checklistรวมข้อเสนอ5เรื่องล่าสุดแล้ว:COD documents,delivery fee/promotion eligibility,online delivery authority,redemption reversalและclock skew.

1. **28.8.1 Ordering/Delivery:** local operational scopeปิดตามด้านบน;เหลือrelease/accounting gates.
2. **28.8.2 Membership/Promotions — ทุกPOS:** member master/custody/cross-branch,กติกาและการปัดเศษ,limited redemption,refund/failed deliveryคืนสิทธิ์เพียงครั้งเดียว. การคืนStockต้องรับคืน/ตรวจสภาพก่อน ไม่เพิ่มจากสถานะfailedโดยอัตโนมัติ.
3. **28.8.3 Browser/PWA offline — ทุกPOS:** durable local queue,เลขเอกสารและstock quotaแยกกัน,server-held signing keys,provisional receipts,sync/dedupe/recovery/conflict,logout isolation,storage limitsและclock-skew review. ห้ามทิ้งรายการที่รับเงินแล้วเพราะเวลาเครื่องผิด.
4. **28.8.4 ประวัติผู้ป่วย/เวชระเบียน — Pharmacyเท่านั้น:** supersedeข้อจำกัดเดิมเฉพาะโมดูลที่พัฒนา/ตรวจรับแล้ว;custody/encryption/ACL/access auditก่อนข้อมูลจริง. Drug Allergy/Contraindicationอัตโนมัติเป็นข้อเสนอรอกำหนดขอบเขต ไม่รวมโดยปริยาย.
5. **28.8.5 Restaurantเท่านั้น:** ต่อยอดQRเมนู/สั่งอาหาร/table sessionเดิม,คิวครัวตามลำดับ,คิวที่นั่งและจองโต๊ะ. คงstaff acceptanceจนกว่าจะตกลงauto-accept;POS Serverยืนยันโต๊ะ/รับออเดอร์,ไม่มีonline depositรอบนี้.
6. **28.8.6 ยังคงเลื่อน:** QR payment/provider/late callbacks,Card/EDC/mixed tender;shared-stock real-time reservationหรือconnection modelใหม่ต้องออกแบบ/อนุมัติแยก.

ก่อนพัฒนาให้กำหนดนโยบายที่ยังเปิดอยู่ในchecklist เช่นCOD lifecycle,member master,โปรโมชัน,offline limits,คิว/ถือโต๊ะ/มาสาย และclinical/data custody. รุ่นแรกdispatch/ยืนยันส่งสำเร็จ/CODรับคืน/รับของคืนต้องผ่านPOS Server;offlineเป็นdraft/pending. ไม่เดารายละเอียดบัญชี/ภาษี/เภสัชกรรมแทนผู้รับผิดชอบ.

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
- Manufacturing/POS มี local implementation แล้ว; ฟีเจอร์ใหม่ให้ยึดขอบเขตและลำดับใน checklist ปัจจุบัน.

## ประวัติและแหล่งอ้างอิง

- [Handoffเดิมก่อนcleanup](document/history/GPT_HANDOFF_20261030_PRE_CLEANUP.md):เก็บครบทั้งผลตรวจPhase27เก่า,APIrolloutและPhase28รายวัน. สถานะpending/ข้อกล่าวหาbugเก่าในนั้นต้องตรวจsourceและหลักฐานใหม่ ไม่ยกกลับเป็นงานค้างโดยอัตโนมัติ.
- [Current implementation](docs/CURRENT_IMPLEMENTATION.md) · [Production runbook](docs/PRODUCTION_RUNBOOK.md) · [Phase28 runtime](docs/PHASE_28_POS.md) · [POS config](document/POS_CONFIGURATION.md).
