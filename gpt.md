# GPT Handoff — งานค้างและข้อกำหนดที่ยังมีผล

อัปเดต 2026-10-19: ตัดประวัติงานที่เสร็จแล้ว บันทึกซ้ำ และสถานะเก่าที่ถูกแก้แล้วออก เก็บเฉพาะบริบทสำหรับทำงานต่อและตรวจรับ อ่านร่วมกับ [gemini.md](gemini.md) และ [checklist.md](checklist.md)

## External Summary API — สถานะล่าสุด

Sales, Finance และ Projects Summary implement แล้ว; Gemini รับรอง Round 1 เท่านั้น ยังไม่ปิด Phase API ทั้งหมด

- Endpoints: `GET /api/v1/summaries/{sales|finance|projects}`; scopes `summary:sales:read`, `summary:finance:read`, `summary:projects:read`
- Admin: Administration & Settings → API Access (`/settings/api-access`); permissions `api_keys.view`, `api_keys.manage`
- Local ERP: ผู้ใช้อนุญาตและรันเฉพาะ `2026_10_18_000001_create_integration_api_keys.php` สำเร็จ; owner/admin ได้ทั้งสอง permission ครบ 2/2 roles ไม่ได้รัน Manufacturing หรือ summary index migrations ที่ยัง Pending และไม่ได้ออก secret key แทนผู้ใช้
- Projects ลด aggregate queries 8 → 2 ด้วย grouped aggregates/conditional SUM และ JOIN parent ที่ตรวจ org/soft-delete; คง metric contract เดิม
- Dashboard month overflow แก้แล้วโดยยึด startOfMonth ของ selected end_date ก่อน subMonths พร้อม regression tests
- Security contract: server-to-server Bearer key, SHA-256 hash, one-time secret reveal, server-derived org, opt-in scopes, expiry/revoke, production HTTPS, ไม่มี summary CORS; ไม่ส่ง plaintext secret ผ่าน session/Inertia/localStorage

### งานรอตรวจรับ / ดำเนินการต่อ

- [ ] Gemini ตรวจรับ Round 2 รวม Projects Summary และ optimization; `gemini.md` section 11 ยังเป็นผล Round 1
- [ ] Browser UAT หน้า API Access: create/reveal/copy/edit/revoke, discard guard, keyboard และ responsive
  - ครั้งล่าสุดถูกบล็อกด้วย `Local browser use is disabled in Work settings`; หยุด fixture server และลบ fixture แล้ว ไม่ถือว่า automated API tests หรือ local migration แทน UAT
- [ ] Deferred ตามผู้ใช้ 2026-10-19: SLA และ HTTP/auth/concurrent load บน target environment; synthetic service benchmark ยังไม่ใช่ production acceptance
- [ ] Deferred ตามผู้ใช้ 2026-10-19: target deployment, migrations/indexes และ integration แอปจริง; local activation ไม่ใช่ production deployment
- [ ] ขยาย API ตาม P0–P7 ทีละ checklist; HR/Employee และโดเมนอื่นยังเป็นแผน ไม่มี endpoint ใหม่

### หลักฐานล่าสุดสำหรับ Gemini

ผลเดิมที่รันไว้ ไม่ได้รัน tests ใหม่ระหว่าง cleanup เอกสาร:

- หลัง Projects optimization: API + DashboardAccess + Delivery Dashboard **39 passed / 801 assertions**; Pint และ diff check ผ่าน
- ก่อน optimization: regression รวม API/Auth/2FA/Gateway/Dashboard **85 passed / 1145 assertions**; TypeScript, ESLint หน้า ApiAccess และ Vite build ผ่าน
- MariaDB 11.2.2 isolated fixture 2 orgs / 84,000 rows: correctness ผ่านทั้งสามโดเมน, `isolated_schema_removed=true`, `original_table_counts_unchanged=true`
- Queries / median / max: Sales 3 / 95.00ms / 175.48ms; Finance 6 / 105.13ms / 236.52ms; Projects 2 / 543.58ms / 1026.02ms
- โหลดเครื่องต่างจากรอบก่อน จึงยืนยันได้เฉพาะ query reduction 8 → 2 ไม่อ้าง causal speedup หรือ SLA ผ่าน; EXPLAIN ยังมี scan ตาม optimizer
- Runner: `backend/tests/Support/external-summary-mariadb-benchmark.php --run-isolated`; ผล local ignored: `temp/external-summary-mariadb-optimized.json`

### เอกสารอ้างอิง

- [API contract / วิธีใช้](docs/EXTERNAL_SUMMARY_API.md), [OpenAPI](docs/openapi/external-summary-v1.json)
- [API checklist](docs/EXTERNAL_SUMMARY_API_CHECKLIST.md): sections 14–16 HR/Employee; section 17 ลำดับ P0–P7 รวม incremental sync/deletion/revocation contracts ก่อน client integration
- [Interactive API diagram](document/22_external_api_architecture.html), [Mermaid / รายละเอียด](document/22_external_api_architecture.md), [JSON spec](document/specs/22_external_api_architecture.json)
- Diagram ผ่าน Archify showcase 9/9 และ containment/readability/viewer chrome 4 viewport; ตรวจภาพ light/dark แล้ว Automated receipt ยังคง `visualReview=pending` ไม่ใช่ผลตรวจรับโดยผู้ใช้
- README และหน้ารวม diagram อัปเดตแล้ว; รายละเอียด implementation ที่ปิดแล้วให้อ่าน source/เอกสารเฉพาะเรื่องแทนบันทึกซ้ำในไฟล์นี้

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

## ผลตรวจรับ Phase 27 — 2026-09-25

**สถานะ: ไม่รับปิด Phase 27** จนกว่าจะแก้ P1 และเพิ่ม regression tests ตามรายการนี้ครบ. ตรวจประกอบกับ `gemini.md` แล้ว พบว่าข้อความ `Complete & Closed` ยังเร็วเกินกว่าหลักฐานใน source.

### P1 — Release Work Order ทำให้สต็อกติดลบหรือสร้าง movement ผิดคลังได้

- `WorkOrderController::store()` และ `update()` อนุญาต `source_warehouse_id`/`warehouse_id` เป็น nullable; `release()` ส่ง request ทั้งก้อนไปยัง service โดยไม่มี validation ของ `issued_items`.
- `ProductionCostingService::transitionStatus()` รับ `issued_items` ที่เป็นค่าติดลบ, ศูนย์, มากกว่า planned quantity หรือ ID ที่ไม่ใช่ item ของ Work Order ได้ และบันทึก `StockMovement` ทันที โดยไม่มี `lockForUpdate()` และไม่มีตรวจ on-hand quantity. ค่าติดลบทำให้ `quantity` กลับเป็นบวกได้.
- ต่างจาก inventory transfer ที่มีการ lock และ `Insufficient source stock` guard อยู่แล้ว.

ต้องแก้ดังนี้:

1. บังคับ source warehouse ก่อน release และ output warehouse ก่อน complete.
2. validate item IDs ว่าเป็นของ Work Order, quantity ต้อง `gt:0` และกำหนด policy ชัดเจนสำหรับ partial/over-issue.
3. lock stock ledger ตาม org/product/source warehouse แล้ว reject เมื่อ on-hand ไม่พอ ก่อนสร้าง movement/GL.
4. เพิ่ม tests สำหรับ crafted negative quantity, quantity เกินแผน, foreign/unknown item ID, missing warehouse, insufficient stock และ concurrent release.

### P1 — Quality Check ถูกข้ามหรือ Complete ได้แม้ QC ไม่ผ่าน

- state machine อนุญาต `in_progress -> completed` และ `quality_check -> completed`.
- `completed` ไม่มี guard ว่า `qc_passed === true`; Work Order ที่ QC failed ยังรับ Finished Goods และ post GL ได้.

ต้องบังคับให้ผ่าน Quality Check ก่อน complete หรือบันทึก product decision ที่อนุญาต bypass QC พร้อม reason/audit/permission เฉพาะ. เพิ่ม tests สำหรับ QC failed, direct complete และ rework flow.

### P1 — Production cost บางประเภทถูกบันทึกแต่ไม่เข้าสู่ต้นทุนหรือ GL

- API รับ `cost_type` เป็น `material`, `labor`, `overhead`, `other` แต่ `addCost()` รวมเฉพาะ labor/overhead ใน Work Order.
- `material` และ `other` สร้าง `production_costs` สำเร็จแต่ไม่กระทบ `total_cost`, `unit_cost` หรือ journal. ทำให้ cost breakdown กับมูลค่า FG/GL ไม่ตรงกัน.

ต้องเลือก contract เดียว: รองรับทุก type แล้วรวมต้นทุนและ post/reverse GL ตามบัญชีที่กำหนด หรือจำกัด validation/UI ให้รับเฉพาะประเภทที่รองรับจริง. เพิ่ม tests ครบทุก type และตรวจ journal/FG valuation.

### P1 — Write actions ที่เปลี่ยนต้นทุนไม่มี idempotency guard

- routes `plan`, `release`, `quality-check`, `complete`, `cancel` และ `costs.store` ไม่มี `EnsureFormSubmission` ทั้งที่ `costs.store` สามารถถูกส่งซ้ำและเพิ่ม `ProductionCost`/ต้นทุนซ้ำได้.
- UI `WorkOrders.tsx` ใช้ `router.post`/`useForm.post` โดยไม่ส่ง submission key.

ต้องใช้ `EnsureFormSubmission` กับทุก write action ที่เหมาะสมและสร้าง submission key ใน UI; เพิ่ม test double-submit โดยเฉพาะ `costs.store`.

### P2 — Multi-level BOM ยังไม่ถูกใช้ในการเบิกวัตถุดิบของ Work Order

- มี `explode()` และ `flattenExplodedMaterials()` แต่ `createWorkOrder()` สร้าง `work_order_items` จาก `bom->items` ชั้นแรกเท่านั้น.
- ถ้า Phase 27 ต้องเบิก raw material ของ multi-level BOM ใน Work Order เดียว ต้องใช้ flattened leaves. หากตั้งใจให้เบิก sub-assembly inventory แยก Work Order ต้องระบุ policy, traceability และ test ให้ชัดเจน.

### P2 — เอกสารและสถานะไม่สอดคล้อง

- `gemini.md` ระบุ Phase 27 `Complete & Closed` และรายละเอียด Phase 27 ใน `checklist.md` ติ๊กครบ แต่ตารางสรุป `checklist.md` ยังระบุ `Phase 27 | Planned (Vertical)`.
- `README.md`, `docs/ROUTES_AND_SCREENS.md`, `docs/modules/README.md` และ diagrams ยังไม่มี Phase 27/menu Manufacturing/route ใหม่ จึงไม่ครบตามการอ้างว่า Phase ปิดแล้ว.

หลัง P1 ผ่าน ให้ปรับเอกสารและ diagrams ให้สอดคล้องกัน แล้วบันทึกคำสั่งทดสอบพร้อมผลจริง.

### หลักฐานที่ตรวจผ่านรอบนี้

- `php artisan test --compact tests/Feature/Phase27ManufacturingTest.php` — **8 passed / 69 assertions**
- Laravel Pint (Phase 27 files) — passed
- PHP syntax check (service/controllers/migration) — passed
- `node node_modules/typescript/bin/tsc --noEmit` และ `node node_modules/vite/bin/vite.js build` — passed

หมายเหตุ: `npm run build` เรียกไม่ได้จาก environment นี้ เพราะ global npm ชี้ไปที่ `C:\\Users\\anuwat\\AppData\\Roaming\\npm\\node_modules\\npm\\bin\\npm-cli.js` ที่ไม่มีอยู่; ไม่ใช่ TypeScript/Vite failure ของ Phase 27.

### P2 — ESLint ของหน้า Manufacturing ยังไม่ผ่าน

คำสั่ง `node node_modules/eslint/bin/eslint.js resources/js/Pages/Manufacturing/WorkOrders.tsx resources/js/Pages/Manufacturing/BillOfMaterials.tsx resources/js/Layouts/AuthenticatedLayout.tsx --max-warnings=0` พบ 2 errors:

1. `BillOfMaterials.tsx:350` ใช้ `any` (`@typescript-eslint/no-explicit-any`).
2. `WorkOrders.tsx:189` เรียก `Date.now()` ระหว่าง render (`react-hooks/purity`).

ต้องแก้ type ให้ชัดเจนและย้ายการคำนวณ initial due date ไปอยู่ใน event/initializer ที่ไม่ละเมิด purity แล้วรัน ESLint ซ้ำ. ข้อความใน `gemini.md` ที่ระบุ ESLint clean จึงยังยืนยันไม่ได้.

## P0 Summary API closeout — 2026-10-19 (กำลังตรวจรับ)

ผู้ใช้ยืนยันให้ปิดเฉพาะ Summary API ปัจจุบัน (P0) ไม่ขยาย P1–P7

- เพิ่ม `docs/EXTERNAL_API_INTEGRATION.md` และ server-side Node.js client `examples/external-api/summary-client.mjs`: HTTPS/redirect guard, timeout, bounded retries, Retry-After, terminal 401/403/422, error redaction และ rotation/downstream stale-data policy
- Client tests: `node --test examples/external-api/summary-client.test.mjs` — **8 passed / 0 failed** ไม่มี secret จริงหรือ network ภายนอก
- ระบุมาตรฐาน list/detail เป็น design เท่านั้น ไม่อ้างว่ามี endpoints หรือ HR privacy approval แล้ว; อัปเดต checklist แยก service benchmark ที่ทำแล้วจาก concurrent HTTP/SLA ที่ยังค้าง
- Runtime พบ Composer bootstrap เปิดไม่ได้ สร้าง autoload กลับด้วย `composer dump-autoload --no-scripts --no-interaction` สำเร็จ 7,585 classes; PHP require โดยตรงผ่าน แต่ PHPUnit ยังรายงาน Cannot open bootstrap script ใน sandbox รอบนอก sandbox รันได้ พบ 84 passed / 1 failed (1,128 assertions) จาก mutable Carbon fixture ของ Delivery Dashboard; แก้ copy()->subDay() และตรึงวันจันทร์เพื่อทดสอบ boundary รันซ้ำผ่าน 85 tests / 1146 assertions ไม่เปลี่ยน production logic
- Browser รอบนี้: in-app webview attach timeout; Chrome execution timeout ไม่ผ่าน UAT และไม่มีการสร้าง/revoke key จริง
- คงงานรับรอง Gemini Round 2, browser UAT, SLA/target environment และ integration แอปจริงเป็น unchecked; ไม่สามารถรับรองแทนผู้ตรวจหรือสมมติ SLA/ปลายทางขึ้นเอง

ผู้ใช้สั่ง “งดไปก่อน” สำหรับ target deployment/integration และ SLA เมื่อ 2026-10-19: คงเป็น deferred ไม่ใช่ passed และไม่เปิด live integration รอบนี้ เพิ่ม `examples/external-api/smoke.mjs` สำหรับ read-only smoke test จาก backend แอปจริงเมื่อพร้อม โดยพิมพ์เฉพาะ domain/ok หรือ code/status
### P0 verification final — 2026-10-19

- `php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml tests/Feature/ExternalSummaryApiTest.php tests/Feature/DashboardAccessTest.php tests/Feature/DashboardMetricsEdgeCasesTest.php tests/Feature/Phase4DeliveryDashboardTest.php tests/Feature/Auth tests/Feature/Phase18TwoFactorTest.php tests/Feature/Phase23GatewayTest.php` — **85 passed / 1146 assertions**, exit 0, 76.420s (SQLite in-memory, retry นอก sandbox)
- `php vendor/bin/pint --test tests/Feature/Phase4DeliveryDashboardTest.php` — passed
- `node --test examples/external-api/summary-client.test.mjs` — **8 passed / 0 failed**, exit 0; Node syntax check client/smoke ผ่าน
- Delivery fixture root cause: `$businessNow->subDay()` เปลี่ยน mutable Carbon เดิม ทำให้ done fixture กลายเป็นเมื่อวานและหลุดสัปดาห์เมื่อวันจันทร์; ใช้ `copy()->subDay()` และตรึงวันจันทร์ 2026-10-19 เพื่อให้ regression ตรวจ boundary นี้เสมอ ไม่แก้ Dashboard production semantics
- อัปเดต README/docs index/master checklist/API checklist ตามขอบเขต P0 และ deferred ล่าสุด; ไม่รัน build เพราะไม่ได้แก้ frontend และไม่รัน migrations/สร้าง key/เปิด live integration รอบนี้
- ส่งมอบ implementation/supporting checks ของ P0; การตรวจรับยังไม่ปิด: Browser UAT และ Gemini Round 2 คง pending, target deployment/integration และ SLA deferred ตามคำสั่งผู้ใช้ ไม่ตีความเป็น acceptance ผ่าน
## P1–P6 Read API — ส่งตรวจ Gemini รวม (2026-10-19)

### ขอบเขตล่าสุดจากผู้ใช้

- ทำ P1–P6 ต่อโดยไม่รอ P0 acceptance และให้ Gemini ตรวจรวมท้ายรอบ แทนคำสั่ง P0-only/ตรวจทีละโดเมนเดิม
- **ตัด P7 ทั้งหมด:** ไม่เปิด payroll/salary/bank/identity/private-data APIs และไม่ทำ Write API ทุกชนิด แอปภายนอกอ่านด้วย GET/HEAD เท่านั้น
- Outbound webhook เป็น minimal signal ให้กลับมาดึง GET ไม่ใช่คำสั่งเขียนธุรกิจ; default EXTERNAL_API_WEBHOOKS_ENABLED=false ไม่มี live dispatch ในงานนี้
- Browser UAT/Gemini acceptance ยัง pending; deployment/แอปจริง/concurrent HTTP SLA ยัง deferred ตามคำสั่งเดิม

### Implementation ที่ส่งตรวจ

- P1: fixed Reference registry, Product/service list/detail พร้อม price/cost scopes แยก, Inventory summary/balances/lot/expiry; on_hand เท่านั้น ไม่มี units master/reservation/ATP สมมติ
- P2: HR/Attendance/Leaves/Roster summaries และ Employee directory/profile/operational lists; explicit HR branch/all-branches policy. Non-User supported; leave without linked User →409 ไม่คืน0. ไม่มี private contact/identity/payroll/bank/GPS/leave reasons
- P3/P4: Customers/Suppliers, Quotations/Invoices/DeliveryOrders/Payments, PO/GRN/items, Procurement summary และ Projects/Tasks. Party-bound credentials + current issuer row ceilings + parent org guards; finance decimal2/quantities decimal4. Posted receipt matches PO/product/case-sensitive unit; no project work hours without source ledger
- P5: Approval read assignment/history/delegation ไม่มี approve/reject. Model save transactional outbox สำหรับ Invoice/StockMovement/Task/WorkflowInstance, HMAC/ownership challenge, public DNS pinning/HTTPS/no redirects/no proxy/response limits, retries5/dead-letter/delivery leases และ Admin Webhooks. Payload minimal pointer ไม่มี names/amounts/PII; recheck key/party/row ก่อนส่ง
- P6: Manufacturing stored-state summary/BOM/WO/items; formula/cost opt-in. Document explicit ID allowlist + classification/category/link ACL + current-version parent guards และ clean private-stream download ไม่เผย storage path/public URL
- Common: 37 opt-in read/download scopes, encrypted cursor30m/stable(updated_at,id)/fixed sync_watermark inclusive UTC delta; soft-delete tombstones เมื่อรองรับ. Live consistency ไม่ใช่ snapshot; derived/ACL/hard-delete ต้อง full reconciliation. Approvals/POitems/employee operational/inventory balances ปฏิเสธ delta
- Admin API Access: principal party picker, HR branch policy, document allowlist picker; domain permissions ก่อนออก scope และ immutable scopes/policy ต้อง rotate ไม่มี secret ผ่าน session/Inertia/localStorage
- ไม่มี business write routes: OpenAPI/route contract tests เทียบครบ62GETpaths และตรวจ middleware/scopes/forbidden fields

### หลักฐานคำสั่งจริง

ทุก PHPUnit ใช้ backend/phpunit.xml SQLite :memory: + RefreshDatabase ไม่ใช้ ERP จริง. Sandbox เคยเปิด autoload/Pint write ไม่ได้ จึงรันคำสั่งเดิมนอก sandbox สำเร็จ ไม่ใช่ application failure

- `php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml tests/Feature/ExternalCatalogApiTest.php tests/Feature/ExternalHrApiTest.php tests/Feature/ExternalBusinessApiTest.php tests/Feature/ExternalRestrictedReadApiTest.php tests/Feature/ExternalWebhookTest.php tests/Feature/ExternalReadContractTest.php tests/Feature/ExternalSummaryApiTest.php` — **54 passed / 4,731 assertions**, exit0,16.508s
- รวม sync change-during-pagination fixed watermark/recovery, future checkpoint rejection และ HR directory/4summaries constant queries <=20queries รวม auth/framework เมื่อเพิ่ม1เป็น21employees
- Regression command ใช้ PHPUnit config เดียวกัน ตามลำดับ `DashboardAccessTest DashboardMetricsEdgeCasesTest Phase4DeliveryDashboardTest Auth Phase18TwoFactorTest Phase23GatewayTest Phase19HrAttendanceTest Phase233EmployeeMasterTest Phase233RosterAndShiftTest Phase20WorkflowTest Phase17DocumentManagementTest Phase181DocumentComplianceTest Phase8InventoryTest Phase15InventoryOperationsTest Phase27ManufacturingTest` ใต้ tests/Feature — **118 passed / 1,205 assertions**, exit0,192.621s
- `node --test examples/external-api/summary-client.test.mjs examples/external-api/webhook-verifier.test.mjs` — **13 passed / 0 failed**, exit0. Http fake/preventStrayRequests ไม่ส่ง network จริง
- `node examples/external-api/build-openapi.mjs` — generated62GETpaths; contract test ตรวจ routes/refs/fields/auth ทั้งหมด
- `node node_modules/typescript/bin/tsc --noEmit --project tsconfig.json`, targeted ESLint `resources/js/Pages/Settings/ApiAccess.tsx resources/js/Pages/Settings/ApiWebhooks.tsx --max-warnings=0` — exit0
- `node node_modules/vite/bin/vite.js build` — exit0,1122modules,124s; npm global runtime เดิมยังเสีย จึงใช้ node entrypoints
- Laravel Pint เฉพาะ API controllers/support/services/models/observer/routes/tests — exit0; Prettier API pages/generator และ git diff --check exit0 (line-ending warnings เท่านั้น)

### Migrations / rollout / ข้อจำกัดที่ห้ามนับเป็น passed

- **ยังไม่รันฐานใช้งานจริง:** 2026_10_19_000001_add_integration_access_policy, 2026_10_19_000002_create_external_webhook_outbox. Migration key เดิมเคยได้รับอนุญาตให้รัน local ไม่ใช่สิทธิ์ deploy รอบใหม่; tests migrate เฉพาะ SQLite in-memory
- ไม่สร้าง/revoke key จริง ไม่เพิ่มสิทธิ์ ERP จริง ไม่ส่ง webhook network หรือเปิด flag จริง. ต้องตรวจ target migrations/shared cache/cURL/scheduler/receiver + UAT ก่อนเปิด production
- HR missing_schedule_workdays=null เพราะไม่มี required-workday calendar. Leave days ใช้ weekdays/org holidays ไม่ใช่ payroll entitlement. Work contacts withheld. Manufacturing costs currency=null/sourceไม่มีsnapshot; quantitiesใช้ current product unit
- Full reconciliation จำเป็นสำหรับ hard delete/parent/ACL/derivedchanges; webhookครอบคลุม model save paths ไม่ครอบคลุม bulkSQL/delete. At-least-onceไม่ใช่ exactly-once
- P0 MariaDB84k benchmark เป็นหลักฐานเดิมเฉพาะ summaries3ตัว ไม่ใช่ proof ของ P1–P6 native SQL/load/SLA. Native target correctness/performance และ concurrent SLA ยังไม่ผ่านตรวจรับ
- Browser UAT รอบก่อน in-app/Chrome timeout ยังไม่มีหลักฐานใหม่ ไม่ติ๊ก passed. Gemini ต้องตรวจรวมเอง ไม่แก้ gemini.md เพื่อรับรองแทน
- ประเด็น Phase27 source logic/ESLint ก่อนหน้าคงสถานะเดิม แม้ regression tests ผ่าน; Read API ไม่ปิดข้อโต้แย้งการตรวจรับ manufacturing

เอกสารส่งตรวจ: docs/EXTERNAL_READ_API.md, EXTERNAL_API_P1/P2/P3_P4/P5/P6_P7_CONTRACT.md, docs/openapi/external-read-v1.json, docs/EXTERNAL_SUMMARY_API_CHECKLIST.md sections14–19, README.md/checklist.md. Diagramใหม่ในคู่มือ; HTML22เดิมคง P0 baseline

## GitHub README / API diagram refresh — 2026-10-19

- ผู้ใช้ขอ README สำหรับเผยแพร่ GitHub ไม่รวมความคืบหน้า/Phase: เขียนใหม่เป็น product overview, features, installation/deployment, API/key/sync/webhooks/security และ development commands
- ตัดตาราง Phase/Roadmap, progress/acceptance, local environment, benchmark/test result history และลิงก์ review logs ออกจาก README; ประวัติงานยังคงใน gpt.md/checklists เดิม
- เพิ่ม GitHub-renderable Mermaid system architecture และสารบัญ diagrams ไม่อ้างจำนวนโมดูล/migrations ที่เปลี่ยนตามโค้ด
- ปรับ document/22_external_api_architecture.md เป็น diagram API ปัจจุบัน: architecture/sequence/key lifecycle/sync/outbox/receiver ครอบคลุม read resources/visibility และข้อจำกัด sensitive/write
- ไม่แก้ sealed HTML22/spec เดิม; README ชี้ Mermaid API ฉบับปัจจุบันแทน ไม่อ้าง HTMLเดิมเป็นแผนผัง API ทั้งหมด
- เป็น documentation-only ไม่เปลี่ยน runtime ไม่รัน migrations หรือ publish/push GitHub

## README feature catalogue — 2026-10-19

- เพิ่ม feature catalogue ปัจจุบันใน README แยก22หมวด ครอบคลุม organization/auth/CRM/commercial/finance/procurement/inventory/banking/GL/FX/assets/tax/gateway/projects/HR/payroll/manufacturing/DMS/approvals/notifications/portals/dashboards/forecast/OCR/API/setup/UI
- เทียบ routes/controllers และ docs/CURRENT_IMPLEMENTATION.md ร่วมกับข้อจำกัด API ล่าสุด ไม่เพิ่ม POS/planned adapters หรืออ้าง live certification
- คง README แบบ GitHub: ไม่มี Phase/Roadmap/acceptance/progress logs; แยก payrollภายในจาก externalAPIที่ห้ามเผยข้อมูลลับ
- Documentation-only ไม่เปลี่ยน application logic, migrations หรือ publish repository
- ตรวจเอกสาร: 22หมวด, ไม่มี Phase/Roadmap/บันทึกสถานะพัฒนาใน README; คำว่า ความคืบหน้า ใช้อธิบาย Tasks เท่านั้น; local links/fences ผ่าน; ไม่รัน application tests เพราะแก้เฉพาะ Markdown

## Phase 28 — Standalone POS / ERP Connector checklist (2026-10-19)

คำขอผู้ใช้: ปรับ Phase28 ตามแนวคิด POS คนละ Server เพื่อลดการเข้าถึง ERP จากภายนอก และบันทึกประกอบการตรวจรับ ยังไม่ขอเริ่ม code หรือ deploy

- เปลี่ยน Phase28 จาก POS ที่ผูก ERP เป็น Standalone POS + POS DB + separate cashier auth/secrets; ERP Connector เริ่ม outbound เพื่อ publish catalog / poll sales / send results ไม่มี POS-initiated inbound หรือ shared ERP DB credentials/session/APP_KEY
- คง external `/api/v1` GET/HEAD-only ตามข้อสรุป API เดิม Internal import เฉพาะ POS เป็นงานใหม่ที่ ERP Worker ดึงเข้ามาตรวจ ไม่ใช่ generic public Write API; outbound POST ไป POS ใช้ส่ง catalog/ผลรับเข้าเท่านั้น
- บันทึก baseline ที่เสนอ: online POS, stock pool/warehouse/quota แยกสาขา, ERP polling; ERP outage ไม่หยุดขายบน POS Server แต่ browser offline/POS outage เป็น design แยกภายหลัง ไม่อ้างรองรับแล้ว
- เพิ่ม checklist 28.1–28.8 ครอบคลุม ownership/versioned contracts/network/mTLS or service auth/rotation/SSRF/branch ACL, schemas/inbox/outbox, immutable sales/returns, unique event+source order, commit/checkpoint/ack recovery และ at-least-once sync
- Import ต้อง validate branch/product/unit/price policy/tax/discount/payment evidence และใช้ ERP business services เดิมพร้อม transaction; provider proof ไม่เชื่อ flag/รูปสลิป, GL/stock/payment ไม่โพสต์ซ้ำ และไม่ส่งข้อมูล HR/payroll/bank/national_id ทั้งองค์กรไป POS
- เพิ่ม checkout/multi-tender/thermal receipt/shift over-short, stock allocation/oversell, refunds/reversals, tax document ownership และ account mapping โดยไม่สมมติบัญชีคงที่
- เพิ่ม administration/monitoring/reconciliation/dead-letter-review และ tests cross-org/instance/branch, forged values, duplicates/concurrency/crash/lost-ack/out-of-order/outages/network isolation พร้อม UAT/load/recovery บนสองServerจริงก่อน production
- Guardrail เดิมยังอยู่: reconciliation กะ POS ไม่ใช่ organization-wide cash balance. การแยก Server ไม่รับประกันว่ารายการจาก POS ถูกต้อง จึงยังตรวจทุก import
- ติ๊กเฉพาะการบันทึกหลักการ/ขอบเขตที่ผู้ใช้ให้ปรับ checklist งานออกแบบรายละเอียด/implementation/acceptance ยัง unchecked ไม่อ้างทำระบบ POS เสร็จ
- เปลี่ยนเฉพาะ checklist.md และ gpt.md; ไม่แก้ README ให้มี Phase/ความคืบหน้า ไม่เปลี่ยน runtime ไม่รัน migrations/network หรือเปิด API ใหม่

## Gemini review alignment / Phase28 checklist refinement — 2026-10-19

- อ่าน gemini.md sections12–13 โดยเฉพาะ review13.2 หกข้อ; ปรับ checklist.md Phase28 เพิ่มรายละเอียด/เกณฑ์ทดสอบ ไม่เริ่ม implementation และไม่แก้ gemini.md เพื่อรับรองแทน
- ข้อ1: ABB/FullTax/e-Tax sequence/source linkage/replacement/VATไม่ซ้ำ + certified signing boundary ไม่มี private key ใน POS DB; ขั้นตอน void/replace ต้องตรวจข้อกำหนดภาษีก่อนพัฒนา ไม่ยืนยันข้อบังคับ voidทุกกรณีจาก review เพียงอย่างเดียว
- ข้อ2: verified physical sale ไม่ทิ้งเมื่อ ERP stock ขาด; แยก financial_imported/inventory_pending, needs_review: inventory_discrepancy, manager adjustment และ stock/COGS completionครั้งเดียว. ไม่ข้าม domain guards ไม่ auto-negative/auto-adjust และไม่รับยอดเงินจาก forged POS payload
- ข้อ3: provider reference/amount/currency/merchant/status + unique payment evidence, gross/fee/net/settlement/refund; แยก drawer/clearing/bank และ reconcileก่อนย้ายเงิน. เลขบัญชี1111/1113/1112/5400/4400ในreviewเป็นตัวอย่าง mapping ต้องตรวจผังจริงไม่ hard-code
- ข้อ4: transactional GL รายบิล; ปิดกะเฉพาะ over/short ไม่โพสต์ sales/VAT/COGS/paymentซ้ำ; bank deposit/settlementเป็น transferคนละรายการ และ inventory pending ไม่แกล้งรายงานต้นทุนศูนย์
- ข้อ5: canonical payload+SHA256 at recording, signature/HMAC+key lifecycle/immutableERP evidence และ AdminDLQ/reprocessผ่านpipelineเดิมพร้อม retention/redaction. ปรับคำ tamper-proof: hashอย่างเดียวไม่ป้องกันแก้ทั้งpayload/hash; compromisedPOShost/keyยังส่งeventปลอมได้ ต้องตรวจหลักฐานอิสระ
- ข้อ6: BusinessFitเป็น pre-implementation gate ต้องยืนยัน Retail/minimart/F&B, branches/terminals/devices/payment/tax/stockownership ก่อนออกschema/UI ไม่ถามซ้ำในงานเอกสารเพราะยังไม่เริ่มcode
- เพิ่ม tests taxreplacement/stockpending/clearingGL/integrityDLQ ให้แต่ละข้อมีเกณฑ์ตรวจรับชัด ไม่ติ๊กว่าพัฒนาเสร็จ
- gemini.md section12 มี combined technical verification จริงแล้ว: ปรับสถานะเก่าใน docs/EXTERNAL_SUMMARY_API_CHECKLIST.md และแยก master cross-cutting technical passed ออกจาก operational pending/deferred. ข้อความ Gemini pending ในบันทึกก่อนหน้านี้เป็นประวัติก่อนผลตรวจล่าสุด
- API BrowserUATยังpending; migrations/deployment/liveintegration/concurrentHTTPSLAยังdeferredตามgemini12.3 ไม่ถือว่าreviewนี้รับรองproductionแล้ว. ไม่เปลี่ยนREADMEให้มีPhase/progress ไม่รันcode/tests/migrations/networkในงานMarkdownนี้
- ตรวจเอกสาร: six review topics/acceptance tasks มีครบ, checked boundary6ข้อเดิมไม่เพิ่ม, ไม่มี Gemini technical gate ค้างผิดสถานะ และลิงก์ local ผ่าน; diff check ใช้เฉพาะไฟล์เอกสารที่แก้

## API_Document reference — 2026-10-19

- ผู้ใช้ขอเอกสาร API_Document ใน folder document: สร้าง document/API_Document.md สำหรับ external read `/api/v1` ครบ62GETpaths แยก endpointไม่รวมadminsession/paymentcallbacks/POSfuturewrite
- แต่ละ endpointมี purpose/scopes/visibility, allowlistedpath+queryparameters/limits, curlplaceholder, JSONoutputสมมติ, fieldpath/type/optional additional scope/ความหมายภาษาไทย และ error codes ที่ต้องจัดการ
- เพิ่ม common metadata/pagination/watermark/delta/tombstone/fullreconciliation, currentstatuses และerrorreference401/403/404/405/409coverage+genericconflict/422/426/429/500พร้อมretryguidance แยกproxy/TLSerrorsจากapplicationJSON
- อธิบายruntimeอย่างตรงไปตรงมา: ข้อความกลางยังใช้คำsummaryแม้เรียกresourceอื่น,422ไม่ส่งfielderrorarray, metadataไม่ครบทุกendpoint และvisibilityorganizationไม่ลบHR/partyACL; ราคา/costoptional ไม่ใช่0, summarysnapshotไม่ใช่historical, nonUserleave409, costsไม่มีcurrencysnapshot
- ตัวอย่างdownloadเป็นprivatebinary ไม่มีJSONdata/meta/publicURL; currentversion/scan/ACLอาจทำให้404แม้metadataอ่านได้ และcurl --outputต้องตรวจstatusก่อนเก็บไฟล์
- ใช้routes/controllers/services/models/configและOpenAPIปัจจุบันอ้างอิง; ตัวอย่างทั้งหมดสมมติ ไม่ดึงฐานERPจริง/ไม่ใช้secretจริง/ไม่ส่งnetwork
- ตรวจเทียบเอกสารครบ62pathตรงOpenAPIและมีscope/request/output/fields/errors;61JSONexamplesผ่านตรวจfield/type/required/patternตามschema, JSONcodeblocksparseได้ และfencesสมดุล (เป็นofflineโครงสร้างvalidation ไม่ใช่liveintegrationหรือfullJSONSchema certification)
- เพิ่มลิงก์ในREADME.md/document/README.md/docs/README.md ไม่เพิ่มPhase/progressในREADME ไม่แก้runtimeหรือmigrations
- Finaldoccheck:62endpointsectionsครบpurpose/scopes/parameters/curl/output/fields/errors, JSONparse/fences/local linksผ่าน และไม่พบliteralERPsecret; diffcheckเฉพาะเอกสาร ไม่รันapplicationtestsเพราะไม่มีruntimechanges

## Phase 28 — POS / ERP Compatibility Review สำหรับ Gemini — 2026-10-24

**สถานะ:** Gemini ให้ผล **Approved with Conditions (2026-10-24)** ตาม [gemini.md sections 14–15](gemini.md) โดย section 15 เป็นผลทบทวน compatibility: รับรองแนวทางเชิงหลักการแบบมีเงื่อนไข ไม่ใช่ผลทดสอบ integration หรือ production readiness. อ้างอิง [Phase 28](checklist.md#phase-28-standalone-pos--erp-connector--shared-core--business-modules-planning). งานรอบนี้แก้เอกสารเท่านั้น ไม่แก้ runtime/schema หรือเริ่ม coding; งานออกแบบและเงื่อนไขที่ยังไม่เสร็จยัง pending

### แนวทางที่ผู้ใช้ยืนยัน

- POS codebase เดียว ใช้ Shared Core + Business Modules: Retail / Restaurant / Pharmacy; Core ร่วมนี้เป็นส่วนกลางของ POS ไม่ใช่การสร้าง ERP บัญชี/คลังอีกชุด
- POS Server/DB แยกจาก ERP; ERP Connector เริ่ม connection ออกไป POS เท่านั้น และ External API `/api/v1` เดิมยังเป็น read-only. ไม่มีการให้ POS เขียนฐาน ERP โดยตรง
- Core รับผิดชอบสาขา/Terminal/สิทธิ์/กะ/ตะกร้า/เงินสดและ QR/เอกสารขาย/คืนสินค้า/stock reservation/sync ร่วมกัน; Modules เพิ่มกฎเฉพาะกิจการ ไม่ทำ payment/tax/stock posting ซ้ำ
- รุ่นแรกไม่เก็บประวัติผู้ป่วย; workflow ร้านอาหาร/เภสัชกร, Provider QR, รูปแบบเอกสารภาษี และรายละเอียด stock ownership ยังมีข้อรอยืนยันตาม checklist

### ข้อสรุปและหลักฐานจากโค้ดเดิม

**ประเมินว่าออกแบบให้ทำงานร่วมกับ ERP เดิมได้ แต่ยังรับประกันว่าไม่มีข้อขัดแย้งทั้งหมดไม่ได้.** การแยก Server ไม่ป้องกันยอดขาย/รับเงิน/Stock/GL ซ้ำโดยตัวมันเอง ต้องมี integration contract และทดสอบร่วม

| จุดเชื่อม | หลักฐาน / ความเสี่ยง | ข้อเสนอให้ตรวจรับก่อน implementation |
| --- | --- | --- |
| Stock / Delivery Order | [CommercialDocumentController](backend/app/Http/Controllers/Finance/CommercialDocumentController.php) สร้าง StockMovement ติดลบเมื่อ Delivery Order เป็น delivered; หาก POS import ตัดอีกหรือสร้าง DO ที่ตัดซ้ำ ยอดจะผิด | ระบุ event เจ้าของ stock movement, source sale/line/lot และ correlation กับ DO; ใช้ stock posting เพียงเส้นทางเดียวต่อเหตุการณ์ พร้อม reservation/consumption แยกชัด |
| Invoice / Payment / GL | [InvoiceController](backend/app/Http/Controllers/Finance/InvoiceController.php) เรียก postInvoice และ [PaymentController](backend/app/Http/Controllers/Finance/PaymentController.php) เรียก postPayment ผ่าน [FinancialJournalService](backend/app/Services/FinancialJournalService.php); POS ลง GL แยกแล้วเรียกเส้นทางเดิมซ้ำอาจลงบัญชีซ้ำ | เลือก ERP document/posting path ให้แน่นอน พร้อม source identity/idempotency; ปิดกะลงเฉพาะผลต่าง ไม่ลงยอดขาย/รับเงิน/ภาษีซ้ำ |
| Payment clearing / บัญชี | FinancialJournalService::postPayment ปัจจุบันเลือกบัญชีผ่าน cashAccount และ fallback `1100`; ยังไม่ใช่หลักฐานว่ารองรับ POS drawer/QR clearing mapping ตามแผนแล้ว | ตรวจ account mapping จริงขององค์กรและ extension point ก่อน reuse; ไม่ hard-code เลขบัญชีตามตัวอย่างหรือถือว่าบริการเดิมรองรับครบ |
| สินค้า / ราคา / หน่วย | ERP เป็น master ตามแผน แต่ POS cache อาจเก่าระหว่าง polling | กำหนด catalog/version/stale policy และราคา/ภาษี/หน่วย/สูตร snapshot ณ เวลาขาย; import ตรวจตาม contract ไม่แทนหลักฐานขายด้วยค่าปัจจุบันอย่างเงียบ ๆ |
| คลังสาขา / quota | ERP และ POS อาจแย่ง stock pool เดียวระหว่าง sync; ยอดใน cache ไม่รับประกันว่ายังขายได้ | กำหนด ownership ของ warehouse/quota และทุกช่องทางที่ใช้ pool; concurrency control, reconciliation และ inventory_pending โดยไม่ข้าม stock guard |
| QR / เอกสารภาษี | ช่องทางเดิมและ POS อาจสร้าง payment/document จากต้นทางเดียวกัน; cancel QR ไม่รับประกันว่าเงินยังไม่เข้า | กำหนด provider/merchant/document ownership, unique references และ original/replacement links; valid late webhook ต้องตรวจและกระทบยอด/คืนเงิน ไม่ทิ้งอัตโนมัติและไม่ apply ซ้ำ |
| Restaurant / Pharmacy | สูตร/จังหวะใช้วัตถุดิบ หน่วยขาย lot/expiry และ precision ต้องตรวจความสามารถเดิมก่อนต่อเพิ่ม | ทำ gap analysis ต่อ module; reuse กลไก Stock ที่เหมาะสม เพิ่ม adapter/service เท่าที่จำเป็น ไม่เปลี่ยนความหมายข้อมูลเดิมหรือเพิ่ม patient history |

### ข้อเสนอด้านการปรับโครงสร้าง

- Logic Stock บางส่วนอยู่ใน Controller จริง จึงอาจต้อง extract เป็น application/domain service ที่ ERP UI และ Connector เรียกใช้ร่วมกัน; ไม่เรียก Controller จำลอง request จาก Connector และไม่เขียน StockMovement ตรงเพื่อข้าม validation/สิทธิ์/period locks
- ยังไม่ได้ตัดสินว่าต้อง refactor ทุก Controller หรือสร้าง generic framework; ให้สำรวจเส้นทางที่จำเป็นก่อน แล้วแยกเฉพาะ logic ที่ต้อง reuse พร้อมรักษาพฤติกรรมเดิมและทดสอบ regression
- บริการที่มีอยู่ เช่น FinancialJournalService/JournalPostingService เป็นจุด reuse ที่ต้องตรวจ contract/guards จริง ไม่ถือว่าต่อ Connector ได้ทันทีโดยไม่ปรับใด ๆ

### ผลรับรองและงานตรวจต่อหลัง Gemini review

- [x] บันทึกผล Gemini section 15: Approved with Conditions; เห็นพ้องความเสี่ยงเชิงโค้ดและ Shared Core + Business Modules. เครื่องหมายนี้หมายถึงบันทึกผล review แล้ว ไม่ใช่งาน implementation หรือ acceptance tests ผ่าน

- [ ] ตรวจหลักฐานจากโค้ดและความเหมาะสมของ Shared Core + Business Modules โดยคง ERP เป็นระบบหลักด้าน master data/stock ledger/บัญชี
- [ ] ตรวจ Event-to-ERP Mapping ที่ต้องจัดทำ: POS event → ERP document/service → stock movement → payment → GL รวม refund/void/replacement/waste; ระบุ owner, transaction boundary, dedupe key, ordering และ retry/recovery ของแต่ละผลกระทบ
- [ ] ตรวจว่าการใช้ Invoice/Payment/DO และการตัดวัตถุดิบครัวไม่ทำรายได้/ภาษี/เงินรับ/stock/COGS ซ้ำ; ช่องว่างต้องระบุเป็นงานออกแบบ ไม่รับรองด้วยการแยก Server เพียงอย่างเดียว
- [ ] ตรวจแผน extract/reuse services และ account mapping ว่าไม่เปลี่ยนพฤติกรรม ERP เดิม ไม่ข้าม org/branch permissions, period locks, validation หรือ audit
- [ ] ตรวจเกณฑ์ regression/integration: ERP UI เดิมยังทำงาน, duplicate/concurrent/out-of-order/lost-ack, ERP offline/reconnect, payment race/late webhook, stock shortage/quota และ refund ผ่านเพียงครั้งเดียว
- [x] บันทึกผลการรับรองเชิงหลักการพร้อมเงื่อนไขทั้ง 5 ข้อตาม Gemini section 15.2 ด้านล่าง; งาน mapping/design/code/tests ในรายการอื่นยังไม่เสร็จ และ requirement ที่ผู้ใช้ยังไม่ยืนยันยัง pending

### การตอบรับเงื่อนไข Gemini section 15.2 และ Action Items 15.4

1. **Service Extraction Gate:** เพิ่มใน checklist 28.2/28.5 ให้ review แผน extract stock logic เป็นบริการกลาง เช่น InventoryMovementService ก่อนพัฒนา Connector; หลังอนุมัติ design จึง refactor และ regression เส้นทาง ERP เดิม. ต้องออกแบบ concurrency lock ต่อ stock pool จริง รวมกรณี ledger ว่าง ไม่ถือว่า lock บน SUM แก้ทุก race
2. **Single Stock Posting Path:** เพิ่ม mapping/dedupe/DO linkage; เสนอ pos_sale สำหรับสินค้าสำเร็จรูป และ recipe_consumption สำหรับวัตถุดิบตามสูตร ไม่ตัดสิ่งเดียวกันซ้ำ. DO อ้างอิงต้องไม่ trigger movement อีกชุด ชื่อ movement และผลต่อรายงานยังต้องตรวจ schema/design
3. **Direct POS Sale GL:** เพิ่มตัวเลือก postPosSale สำหรับบิลชำระครบและ guard ไม่ให้ลงซ้ำผ่าน Invoice/Payment; direct paid sale ไม่ใช่คำตอบอัตโนมัติสำหรับออเดอร์ร้านอาหารที่ยังไม่จ่าย ต้องกำหนด recognition/AR/settlement และ document linkage. เลขบัญชี 1111/1113/4100/2130/1120 ใน review เป็นตัวอย่าง ต้องตรวจ mapping องค์กร ไม่ hard-code หรือเปลี่ยน ERP ทุกช่องทางให้ใช้ direct sale
4. **Suspended Gateway Receipts:** เพิ่ม durable evidence/สถานะพักเงิน เช่น suspended_gateway_receipts, dedupe, liability mapping, notification และ refund/reconcile ที่มีสิทธิ์. เลข 2190 เป็นตัวอย่าง; หาก clearing บันทึกแล้วต้องไม่ debit อีกครั้ง และไม่ทิ้ง valid late webhook/ไม่ apply บิลที่จ่ายแล้วซ้ำ
5. **Lightweight Recipe Consumption:** ไม่สร้าง work_orders ต่อจาน; ใช้ snapshot ณ consumption event ผ่านบริการกลาง. ปรับถ้อยคำ Gemini ที่เสนอสร้าง movement โดยตรงให้ไม่ขัดกับ Service Extraction Gate และแยก waste หลัง consume เป็น cost reclassification/หลักฐานที่ไม่ลดวัตถุดิบเดิมซ้ำ; ส่งครัวไม่ได้แปลว่าใช้วัตถุดิบแล้วทุกครั้ง

เพิ่มเกณฑ์ regression/integration ใน checklist 28.7 ครอบคลุมเงื่อนไขทั้งหมด. **สถานะ Action Items คือปรับเอกสารแล้ว ไม่ใช่ทำ services/tables/GL/refund เสร็จ**; ชื่อ service/method/table/movement เป็นข้อเสนอสำหรับ design review ไม่ใช่ schema ที่ล็อกแล้ว. การรับรองของ Gemini ไม่ใช่คำยืนยัน requirement ใหม่จากผู้ใช้

**Gate ที่คงไว้:** จัดทำและอนุมัติ Architecture & Contract Document (28.2) รวม Service Extraction Plan และปิด requirement gates ที่เกี่ยวข้องก่อน Application/Connector code หรือ migrations. ไม่แก้ gemini.md เพื่อรับรองแทนผู้เขียน; รอบนี้แก้เฉพาะ checklist.md/gpt.md และตรวจ diff ไม่รัน application tests

**การตรวจของ GPT รอบนี้:** อ่านโค้ดเฉพาะจุดตามลิงก์ข้างต้นเพื่อยืนยันความเสี่ยงเชิงออกแบบ ไม่ใช่ full code audit; ไม่รัน application tests เพราะไม่มี runtime changes. ตรวจ `git diff --check -- gpt.md` สำหรับงานเอกสาร

## Phase 28.1 — คำตอบผู้ใช้ 2026-10-25

บันทึกคำตอบใน document/PHASE_28_1_POS_SCOPE.md และปรับ checklist แล้ว; คำตอบล่าสุดมีผลแทน scope เดิมที่ขัดกัน: pilot 1 สาขา 1 PC, หนึ่งสาขาหนึ่งกิจการ, Stock สาขา/HQ ดูภาพรวม, cash-only รุ่นแรก (QR payment deferred), ABB/Full ที่ POS และ e-Tax รุ่นแรก, online ส่งออเดอร์ให้พนักงาน, restaurant โต๊ะ/กลับบ้าน/จ่ายหลังกิน/จอครัว; pharmacy กล่อง/แผง/เม็ด/ยาน้ำ เภสัชกรบันทึก สั่งยาหน้าร้าน แนบใบสั่งยา optional ไม่มี patient history.

D10 ผู้ใช้ขอข้อเสนอ: online POS Server, Terminal/ครัวเชื่อมตลอด, browser offline ภายหลัง; ERP outage ใช้ stock/catalog สาขาที่ตกลงและ outbox, e-Tax ส่งไม่ได้เก็บ pending ไม่อ้างส่งสำเร็จ. ยังรอยืนยัน baseline/deployment และข้อค้างใน Scope Register ไม่ปิด 28.1 โดยสมมติคำตอบ. e-Tax Provider/channel/signing ยังไม่เลือก ไม่ถือว่าของเดิมพร้อม production. รอบนี้เอกสารเท่านั้น ไม่แก้ code/schema/migrations.

## Phase 28.1 — Requirements Baseline Complete — 2026-10-25

ผู้ใช้ยืนยัน baseline online, POS Server ส่วนกลาง, มี receipt printer, ERP ขาย/เบิก Stock สาขาได้, Restaurant แยกบิลและ takeaway จ่ายตอนรับอาหาร. e-Tax ยังไม่มี provider; จัดเป็น gate 28.2 ก่อนล็อก contract/schema ตามคำถามก่อนหน้า ไม่ตัด e-Tax ออกจากรุ่นแรก.

ปิด 28.1 เฉพาะ requirement scope ใน checklist และ document/PHASE_28_1_POS_SCOPE.md. รุ่นเครื่องพิมพ์/driver และประเภทกิจการนำร่องยังไม่ถูกเลือก ต้องระบุก่อน setup/UAT ไม่เดาแทนผู้ใช้. Stock pool มี ERP/POS สอง writer จึงต้อง design reservation/quota/concurrency ที่ทั้งสองเคารพก่อน implementation; ขายต่อช่วง ERP outage ได้เฉพาะ allocation policy ที่ review แล้ว ไม่รับประกันจาก polling/cache. สาขาขาดการเชื่อม POS ส่วนกลางไม่รองรับ browser offline รุ่นแรก.

28.2–28.7 ยังไม่เสร็จ ไม่แก้ code/schema/migrations. Gemini review เดิมไม่ใช่การรับรอง requirement ใหม่หรือ design ที่ยังไม่ทำ. รอบนี้ตรวจเอกสาร/diff เท่านั้น.

## Phase 28.2 — Architecture & Contract Draft — 2026-10-25

**Authorization/implementation ต่อเนื่อง:** ผู้ใช้อนุมัติ drafts 28.2–28.3 เป็นฐานพัฒนา รวม quota partition และหยุดขายเมื่อ quota หมดระหว่าง ERP outage; ทำรุ่นแรก 28.2–28.7 คง 28.8 deferred. ผู้ใช้ยังไม่มีของจริง ให้ทดสอบเท่าที่ทำได้. ดู [Work Register](document/PHASE_28_IMPLEMENTATION_STATUS.md) และ [POS README](pos/README.md). เพิ่ม shared InventoryMovementService/ERP writers regression, standalone POS Retail cash workspace, inbox/import stages/validated account mappings/DO correlation และ monitor/pause/replay. Broad ERP regression ล่าสุด 89 tests/540 assertions, focused import 10/57 (ทับซ้อน), POS 9/26, browser cash shift desktop/mobile และ builds/typecheck ผ่าน. ไม่ migrate ฐานใช้งานจริงหรืออ้าง production readiness

**Approval review / คำอนุมัติล่าสุด:** ผู้ใช้ตอบ “อนุญาตพัฒนาและทดสอบ local เท่านั้น” แล้วสำหรับ outbound Connector และ prescription upload/download. เพิ่ม loopback HTTPS transport และ synthetic-file hash allowlist/private quarantine/ACL/audit/checkout rollback พร้อม production hard-block; default flags ปิด. เพิ่ม Pharmacy conversion/pharmacist authority และ quota freeze/reclaim แบบ local-only. ดู [ขอบเขตและผลตรวจ](document/PHASE_28_LOCAL_TEST_SECURITY.md). ไม่ส่ง remote/เก็บ real prescription; scanner จริง/provider/production gates ยังค้าง. ข้อปฏิเสธเดิมเรื่อง mass assignment/arbitrary GL mapping แก้เป็น explicit allowlist/typed validated persisted mapping แล้ว ไม่ใช่ blocker ค้าง

งานต่อเนื่อง: เริ่ม 28.3 ตามคำสั่งผู้ใช้ ดู [Security Design Draft](document/PHASE_28_3_POS_SECURITY.md). ครอบคลุม firewall matrix, service identity/rotation/revoke, key custody, staff role/session, public menu token, SSRF, catalog allowlist, event signature/replay และ audit/incident/verification matrix. สถานะ In Progress / Draft for Review; ไม่ถือว่าคำสั่งเริ่ม 28.3 อนุมัติ draft 28.2 แล้ว. ยังไม่แก้ application/config/network จริงหรือสร้างบัญชี; deployment inputs/workflow/retention/thresholds ยังต้อง review และ e-Tax provider gate คงเดิม. ตรวจ links/whitespace ของเอกสาร ไม่รัน application tests สำหรับงานเอกสาร

เริ่มตามคำสั่งผู้ใช้; จัดทำ [Architecture & Contract Draft](document/PHASE_28_2_POS_ARCHITECTURE_CONTRACT.md) พร้อม topology/sequence/threat model, data ownership, Service Extraction Plan อ้าง source จริง, stock quota grant/reclaim/fencing, versioned sync/import transaction/recovery, Core/Module contracts และ event-to-ERP mapping

ผู้ใช้ยืนยันยังไม่มี e-Tax provider/signing owner; คง decision gate ก่อน freeze integration/schema. Quota partition, kitchen consumption trigger, pharmacist policy และค่าตั้งต้น operational เป็นข้อเสนอรอ review ไม่ถือว่าผู้ใช้อนุมัติแล้ว. ตรวจพบ stock writers เพิ่มใน InventoryOperationsController/GoodsReceiptController/ProductionCostingService ซึ่งต้องเคารพ shared locking/entitlement ก่อนเปิด quota ไม่สกัดเฉพาะ Delivery Order แล้วอ้างว่าปลอดภัยทั้งหมด

สถานะ In Progress / Draft for Review; checklist 28.2 ยังไม่ติ๊กผ่าน. ต้อง review Architecture + Service Extraction Plan และปิด gates ที่เกี่ยวข้องก่อน Application/Connector code/migrations ตาม checklist และ Gemini section 15.2. ไม่แก้ gemini.md เพื่อรับรองแทน reviewer. รอบนี้แก้เฉพาะเอกสาร ตรวจ links/diff ไม่รัน application tests

## Phase 28 — Local Menu and Refunds — 2026-10-26

เพิ่ม Retail QR menu/token expiry/revoke, explicit online visibility, anonymous request/dedupe, staff accept/reject/re-review, shared checkout claim และ customer status. เพิ่ม partial/full cash refunds ด้วย fresh manager approval ที่ผูก exact command, original conversion/base precision, cumulative rounding, current shift cash availability และ quarantine-only returns. ERP รับ signed sale.refunded, manager registry, dependency recovery, dedicated original-payment/Credit Note/refund Payment subledger, original account snapshot และ GL/period-lock rollback. กัน generic ERP reverse/void/CN-DN ที่ข้าม source workflow; tax report และ ledger copy คง original source amounts แล้วแสดง adjustment แยก ไม่หักซ้ำ

ผลตรวจ: POS 26 tests/112 assertions; ERP selected Stock/Finance/GL/Manufacturing/Phase28 regression 82/624; focused import/transport หลัง account snapshot/worker recovery 18/140 (ทับซ้อน). Browser QR menu, cash checkout และ refund desktop/mobile 1440/390 ไม่มี JS errors; TypeScript/Vite/Pint ผ่าน. ใช้ SQLite :memory: หรือ dedicated temp browser fixture เท่านั้น; local server ปิดหลังตรวจ. ไม่มี migration ฐานใช้งานจริงหรือ external provider traffic

Phase 28 ยัง In Progress: Restaurant/recipe/kitchen/split/waste, Cash In-Out/discount, stock return inspection/recall, full tax/e-Tax, reconciliation/retention/restore และ production operations acceptance ยังค้าง; 28.8 deferred. รายละเอียดปัจจุบันอยู่ document/PHASE_28_IMPLEMENTATION_STATUS.md. ไม่ถือว่าคำขอทำที่เหลือทั้งหมดเสร็จแล้ว

### Phase 28 — Cash In-Out / ERP ledger checks — 2026-10-26

ต่อจาก menu/refunds: เพิ่ม signed cash.moved, manager re-auth/dedupe/overdraw, decimal shift cash ledger และ explicit cash_clearing asset mapping ใน ERP; ไม่สร้าง Invoice/Payment/revenue/VAT/Stock. POS 27/120, browser Cash In 20/Out 30/opening 100/close 90 ผ่าน desktop/mobile. ERP selected Finance/GL/Phase28 หลัง cash 47/453; focused import หลัง ledger checks 16/161 (ทับซ้อนชุดก่อนหน้า). เพิ่ม readonly ledger reconciliation UI แยก pos.reconcile/org scope ตรวจ source/net refunds/Payment/GL totals/stock และ currency; ยังไม่มี signed POS checkpoint จึงไม่รับรอง events ครบ. Restaurant/discount/inspection/full tax/e-Tax/full reconciliation/operations ยังเหลือ ดู work register ปัจจุบัน

### Phase 28 — Authorized Discount — 2026-10-26

เพิ่ม local-only manager discount approval ผูก normalized cashier/cart/unit/terminal/catalog, expire 5 นาที และใช้ได้ครั้งเดียวใน checkout transaction. ERP ต้อง publish max_manager_discount_pct ต่อ SKU (default 0), ตรวจ signed approval/registered manager/เพดาน/เวลา และ unique approval ข้าม sale. Tax/Invoice/Payment/GL ใช้ net หลังส่วนลด, quantity/COGS เดิม; refund คืนตาม original net. Signer failure rollback approval consumption+stock+sale ทั้งกลุ่ม. POS 29/136, ERP focused import/transport 21/189; browser cashier→manager approval→net 8.56/change 11.44→close shift ผ่าน 1440/390 ไม่มี JS error. 20% ใน browser เป็น synthetic fixture policy เท่านั้น. Restaurant/inspection/full tax/e-Tax/full reconciliation/operations ยังเหลือ และ 28.8 deferred

### Phase 28 — Restaurant / selectable signing owner / Pharmacy inspections — 2026-10-26

Restaurant local UI ครบโต๊ะ/ครัว/เสิร์ฟ/แยกบิล พร้อม reservation/recipe consumption/waste และ ERP pipeline เดิม; ไม่สร้าง Stock/COGS ซ้ำตอนชำระ. Reconciliation เพิ่ม kitchen source/stock/cost/journal checks. Browser 1440/390 ผ่าน ไม่มี JS error.

ผู้ใช้ยืนยันยังไม่มี e-Tax provider และให้เลือก signing owner เป็นบุคคลได้: เพิ่ม org-scoped active ERP user selector พร้อม pos.manage/password confirmation/audit; ยังไม่มี certificate/signing/submission. ผู้ใช้ยืนยันของคืน Pharmacy ให้ผู้จัดการที่เป็นเภสัชกรเลือกคืนขาย/ทำลาย: เพิ่ม pharmacist_qualified แยก role, local inspection UI/quantity bounds/lot/expiry/condition evidence/signed event และ ERP original-cost restock. Retry รอ refund ได้ ไม่คืน quota และ destroy ไม่ลด stock ซ้ำ.

ผลตรวจ ERP Phase28 33 tests/290 assertions; focused ล่าสุด 5/54 ทับซ้อน. POS ทั้งชุด 33/173 ก่อนเพิ่ม expiry/storage assertions; focused ล่าสุด 1/10. ERP/POS TypeScript, POS Vite, Pint ผ่าน. Browser Restaurant และ inspection ทั้งสองผลผ่าน 1440/390 ไม่มี JS error. ใช้ SQLite :memory: และ temp fixture เท่านั้น ไม่ migrate ฐานใช้งานจริงหรือส่ง provider traffic. รายละเอียดและงานที่ยังต้องพัฒนาอยู่ document/PHASE_28_IMPLEMENTATION_STATUS.md; ยังไม่ปิด Phase 28.

### Phase 28 — Local completion handoff — 2026-10-27

เพิ่ม provisioning/admin, restaurant variants/table QR/auto-poll, lot recall, local ABB→Full, signed checkpoints, encrypted private archive/restore/sticky hold, independent recovery anchor + paged fenced recovery และ persisted backoff/health. ERP Phase28 39/348; POS 41/240 ก่อน sticky-hold extension, focused checkpoint 1/18 หลังเพิ่ม. Selected Finance/Inventory regression 80/674; TypeScript/build สองแอปและ browser 1440/390 ผ่าน. ดู document/PHASE_28_LOCAL_ACCEPTANCE.md สำหรับหลักฐานและ runbook. ยังไม่ใช่ production sign-off; provider adapter รอ e-Tax contract จริง. ส่ง Gemini ตรวจรับหลังพัฒนา ไม่อ้างผลล่วงหน้า.

ผลรอบสุดท้าย POS ทั้งชุด 41/242 หลัง sticky hold. เรียก Gemini CLI แบบ plan/read-only แล้ว: configured model และ Gemini 3 Flash ตอบ503 ซ้ำ; 2.5 Pro ตอบ404. ไม่มีผลตรวจรับใหม่ ไม่อ้างว่า Gemini อนุมัติ. บันทึก document/PHASE_28_REVIEW_ATTEMPT_20261027.md; fixture servers ปิดแล้ว.

### Configured database upgrade — 2026-10-27

ผู้ใช้ยืนยัน Gemini ตรวจรับแล้ว: gemini.md section17 ระบุ PASS local. จากนั้นผู้ใช้สั่งอัปเดตฐานตาม config: สำรอง ERP ก่อน migrate, รัน 24 migrations ค้าง (รวม83/pending0); สร้าง POS SQLite ที่ config ระบุ รัน17/pending0. ตาราง ERP เดิม133ครบ ไม่มีจำนวนแถวลดลง; เพิ่มเฉพาะ migrations/permissions. แก้8 generated constraint/index names เกิน MySQL64 chars และซ่อมเฉพาะตาราง webhook ใหม่ที่ว่างหลัง failed migration. Regression Phase28+ExternalWebhook46/422 และ Pint ผ่าน. รายละเอียด/backup: document/DATABASE_UPGRADE_20261027.md. ไม่ seed POS; ยังต้อง provision ก่อนขาย.

### Documentation reconciliation — 2026-10-27

ผู้ใช้สั่ง update all document. ปรับเอกสารปัจจุบันให้ตรง Geminiหมวด17 local PASS และ configured DB upgrade; active Phase28 checklist แยก local done/production external/deferred. เก็บ checklistเดิมครบใน document/PHASE_28_CHECKLIST_BASELINE.md พร้อมแก้relative links. เพิ่ม docs/PHASE_28_POS.md, PHASE_28_OPERATIONS.md, document/PHASE_28_API.md (runtime route metadata), PHASE_28_DATABASE.md (migration inventory). อัปเดต root/pos/central READMEs, architecture/security/requirements/schema/ERD/routes/validation/acceptance และ externalAPI migration status. ไม่แก้รายงาน Geminiต้นฉบับ/ไม่เพิ่มผลทดสอบที่ไม่ได้รัน. ไม่มี app code/DB change ในรอบเอกสารนี้; ตรวจเอกสาร/links/route+schema inventories แทน business testsซ้ำ

### POS usage READMEs — 2026-10-27

ผู้ใช้ขอวิธีเปิด POSสามประเภทและคู่มือรายประเภท. เพิ่ม document/README_POS.md และ README_POS_RETAIL/RESTAURANT/PHARMACY.md พร้อมลิงก์จากroot/pos/docs/document READMEs. ใช้URLPOSrootเดียว เลือกตามstaffbranch.business_type+flags; มีโหมดtemp fixturesแยกจากconfiguredDBและโหมดprovisionสาขาตนเอง. ตรวจrolesจริง: restrictedpharmacyต้องpharmacist ส่วนinspectionต้องqualifiedmanager; restaurantfrontdeskใช้cashier/manager ครัวใช้kitchen/manager. อธิบายlocalHTTP UIไม่แทนHTTPSconnector และหมายเหตุยังไม่ทดสอบระบบ/อุปกรณ์จริง. ไม่สร้างบัญชี/เปลี่ยน.env/ฐานข้อมูลในงานเอกสารนี้
