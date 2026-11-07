# Phase 28 & 28.8.1–28.8.5 — API and route inventory — อัปเดต 2026-11-06

> **อัปเดตล่าสุด (2026-11-06):** ครอบคลุม Routes ของ Phase 28 Core, **POS Setup Wizard (`/setup`)**, **Manager Config (`/config`)**, **Phase 28.8.1 Delivery (`/deliveries`)**, **Phase 28.8.2 Membership (`/membership`, `/pos-membership`)**, **Phase 28.8.3 Browser Offline (`/offline/*`)**, **Phase 28.8.4 Pharmacy Patient History (`/patients/*`)**, **Phase 28.8.5 Restaurant Queue & Seating (`/restaurant/seating`, `/restaurant/orders/{id}/hold`)**, และ **`POST /purchase-orders/preview`** สำหรับคำนวณยอดตัวอย่างใบสั่งซื้อใน ERP.

Local implementation ผ่านการตรวจรับครบถ้วน; ฐานตาม config อัปเดตแล้ว (`ERP 88/88` และ `POS 25/25`). ตารางด้านล่างอ่านจาก Laravel route metadata ปัจจุบัน. นี่ไม่ใช่ public ERP Write API และไม่นับรวมใน External Read API 62 GET paths

## Authentication and boundaries

- ERP POS admin ใช้ ERP auth/verified/org scope + `pos.view`, `pos.manage`, `pos.reconcile` ตาม route. Mutation สำคัญใช้ recent password confirmation/throttle; provisioning/recovery เพิ่ม `inventory.adjust`
- POS staff ใช้ session/CSRF แยก ERP; server ตรวจ branch/terminal/role/feature flags. Manager actions ตรวจ password และ command binding ไม่ส่ง password เข้า event/audit
- Public menu ใช้ expiring/revocable token ผูก branch/table session และ throttle; anonymous request ยังไม่ชำระเงินหรือ reserve Stock จน staff accept
- Connector base `/connector/v1`: service authentication/mTLS ingress verification + instance/branch/scope allowlists. Transport ทดสอบเฉพาะ loopback HTTPS; production/remote ยังปิด
- `401/403`: auth/scope/feature denied; `409`: state/evidence conflict; `422`: validation; `423`: recovery fence; `429`: throttled. เก็บ response error จริง ไม่ตีความ network timeout เป็น success

## ERP routes (POS Integration, Membership & PO Preview)

| Method | URI | Route name / Scope |
| --- | --- | --- |
| `POST` | `/pos-events/{inbox}/replay` | `pos.replay` |
| `GET / HEAD` | `/pos-integration` | `pos.index` |
| `POST` | `/pos-integration/instances` | `pos.register` |
| `POST` | `/pos-integration/recalls` | `pos.recalls` |
| `POST` | `/pos-integration/restaurant-options` | `pos.restaurant-options` |
| `POST` | `/pos-integration/tax-profile` | `pos.tax-profile` |
| `POST` | `/pos-integration/tax-settings` | `pos.tax-settings` |
| `POST` | `/pos-integration/{instance}/activate` | `pos.activate` |
| `POST` | `/pos-integration/{instance}/authorities` | `pos.authority` |
| `POST` | `/pos-integration/{instance}/catalog` | `pos.catalog` |
| `POST` | `/pos-integration/{instance}/checkpoint` | `pos.checkpoint` |
| `POST` | `/pos-integration/{instance}/grants` | `pos.grant` |
| `POST` | `/pos-integration/{instance}/pause` | `pos.pause` |
| `GET / HEAD` | `/pos-integration/{instance}/reconciliation` | `pos.reconciliation` |
| `POST` | `/pos-integration/{instance}/recover` | `pos.recover` |
| `POST` | `/pos-integration/{instance}/signing-keys` | `pos.signing-key` |
| `GET / HEAD` | `/pos-membership` | `pos.membership.index` (Phase 28.8.2) |
| `POST` | `/pos-membership/members` | `pos.membership.members` (Phase 28.8.2) |
| `POST` | `/pos-membership/promotions` | `pos.membership.promotions` (Phase 28.8.2) |
| `POST` | `/pos-membership/allocations` | `pos.membership.allocations` (Phase 28.8.2) |
| `POST` | `/pos-membership/publications` | `pos.membership.publications` (Phase 28.8.2) |
| `POST` | `/pos-membership/requests/{id}/review` | `pos.membership.review` (Phase 28.8.2) |
| `POST` | `/purchase-orders/preview` | `purchase-orders.preview` (UI Redesign 2-Column Server Preview) |

## POS routes (Core, Connector, Setup, Config & Phase 28.8.1–28.8.5)

| Method | URI | Boundary |
| --- | --- | --- |
| `GET / HEAD` | `/` | Session/staff or page/health |
| `GET / HEAD` | `/setup` | Local/loopback POS Setup Wizard (`setup.show`) |
| `POST` | `/setup` | Local/loopback + `POS_SETUP_TOKEN` + empty DB (`setup.install`) |
| `GET / HEAD` | `/config` | Manager / own branch / local-loopback |
| `POST` | `/config/{section}` | `branch` / `terminal` / `staff` + manager password |
| `POST` | `/checkout` | Session/staff |
| `PUT` | `/connector/v1/catalog/{version}` | Connector |
| `GET / HEAD` | `/connector/v1/checkpoint` | Connector |
| `PUT` | `/connector/v1/checkpoints/{id}/ack` | Connector |
| `GET / HEAD` | `/connector/v1/events` | Connector |
| `PUT` | `/connector/v1/grants/{id}` | Connector |
| `PUT` | `/connector/v1/grants/{id}/freeze` | Connector |
| `PUT` | `/connector/v1/membership/{version}` | Connector (`membership:publish`, Phase 28.8.2) |
| `PUT` | `/connector/v1/recalls/{id}` | Connector |
| `PUT` | `/connector/v1/results/{id}` | Connector |
| `PUT` | `/connector/v1/tax-profile` | Connector |
| `POST` | `/customer-requests/{id}/accept` | Session/staff |
| `POST` | `/customer-requests/{id}/reject` | Session/staff |
| `GET / HEAD` | `/deliveries` | Session/staff + local/loopback (Phase 28.8.1, non-Pharmacy) |
| `POST` | `/deliveries` | Session/staff + local/loopback (Phase 28.8.1) |
| `POST` | `/deliveries/{id}/transition` | Session/staff + local/loopback (Phase 28.8.1) |
| `GET / HEAD` | `/deliveries/{id}/slip` | Session/staff + local/loopback (Phase 28.8.1) |
| `GET / HEAD` | `/membership` | Session/staff + local/loopback (Phase 28.8.2) |
| `POST` | `/membership/drafts` | Session/staff + local/loopback (Phase 28.8.2) |
| `POST` | `/membership/quote` | Session/staff + local/loopback (Phase 28.8.2) |
| `GET / HEAD` | `/offline/app` | Local/testing PWA Shell (Phase 28.8.3) |
| `GET / HEAD` | `/offline/context` | Session/staff + local/loopback (Phase 28.8.3) |
| `GET / HEAD` | `/offline/session` | Session/staff + local/loopback (Phase 28.8.3) |
| `POST` | `/offline/devices` | Session/staff + local/loopback (Phase 28.8.3) |
| `POST` | `/offline/policies` | Manager + password + local/loopback (Phase 28.8.3) |
| `GET / POST` | `/offline/permits` | Session/staff or Manager + local/loopback (Phase 28.8.3) |
| `POST` | `/offline/inbox` | Session/staff + local/loopback (Phase 28.8.3) |
| `GET / HEAD` | `/offline/review` | Manager + local/loopback (Phase 28.8.3) |
| `POST` | `/offline/accept` | Manager + password + local/loopback (Phase 28.8.3) |
| `POST` | `/offline/close-permit` | Manager + password + local/loopback (Phase 28.8.3) |
| `GET / HEAD` | `/patients` | Pharmacy staff + local/loopback (Phase 28.8.4) |
| `GET / HEAD` | `/patients/options` | Pharmacy staff + JSON + local/loopback (Phase 28.8.4) |
| `POST` | `/patients` | Clinical authority + JSON + local/loopback (Phase 28.8.4) |
| `POST` | `/patients/search` | Clinical authority + JSON + local/loopback (Phase 28.8.4) |
| `GET / POST` | `/patients/{id}` | Clinical authority + JSON + local/loopback (Phase 28.8.4) |
| `GET / HEAD` | `/patients/{id}/{kind}` | Clinical authority + JSON + local/loopback (Phase 28.8.4) |
| `POST` | `/patients/{id}/visits` | Clinical authority + JSON + local/loopback (Phase 28.8.4) |
| `POST` | `/local-cash-movements` | Session/staff |
| `POST` | `/local-discount-approvals` | Session/staff |
| `POST` | `/local-fixtures/prescriptions` | Session/staff |
| `GET / HEAD` | `/local-fixtures/prescriptions/{id}` | Session/staff |
| `POST` | `/local-refunds` | Session/staff |
| `GET / HEAD` | `/local-return-inspections` | Session/staff |
| `POST` | `/local-return-inspections` | Session/staff |
| `POST` | `/local-voids` | Session/staff |
| `POST` | `/login` | Session/staff |
| `POST` | `/logout` | Session/staff |
| `GET / HEAD` | `/menu` | Public token/menu |
| `POST` | `/menu-tokens` | Session/staff |
| `POST` | `/menu-tokens/{id}/revoke` | Session/staff |
| `POST` | `/menu/catalog` | Public token/menu |
| `POST` | `/menu/requests` | Public token/menu |
| `POST` | `/menu/status` | Public token/menu |
| `POST` | `/restaurant/bill-preview` | Session/staff |
| `POST` | `/restaurant/cancel` | Session/staff |
| `POST` | `/restaurant/checkout` | Session/staff |
| `POST` | `/restaurant/customer-requests/{id}` | Session/staff |
| `POST` | `/restaurant/lines/{id}/{action}` | Session/staff |
| `POST` | `/restaurant/orders` | Session/staff |
| `POST` | `/restaurant/orders/{id}/hold` | Manager hold/release queue + reason (Phase 28.8.5) |
| `GET / POST` | `/restaurant/seating` | Walk-in & 90-min table reservation queue (Phase 28.8.5) |
| `POST` | `/restaurant/seating/{id}/{action}` | `call` / `seat` / `cancel` / `no_show` (Phase 28.8.5) |
| `POST` | `/restaurant/sessions/{id}/close` | Session/staff |
| `POST` | `/restaurant/tables` | Session/staff (`+capacity`) |
| `POST` | `/restaurant/tables/{id}/open` | Session/staff |
| `POST` | `/shifts` | Session/staff |
| `POST` | `/shifts/{id}/close` | Session/staff |
| `GET / HEAD` | `/state` | Session/staff |
| `GET / HEAD` | `/tax-documents` | Session/staff |
| `POST` | `/tax-documents` | Session/staff |
| `GET / HEAD` | `/tax-documents/{id}/print` | Session/staff |
| `GET / HEAD` | `/up` | Health |

## Payload and idempotency contracts

- Signed event domain `company-os.pos-event.v1`: immutable event/source identity, sequence, instance/key/schema/payload hash + signature. ERP validates registered key, source catalog/policy, money/unit/lot/quota and original dependency evidence
- Signed checkpoint domain `company-os.pos-checkpoint.v1`: generation, after/through/high watermark, ordered envelope-hash manifest + digest. Max 1,000 entries/page. ACK เฉพาะ ERP reconciliation verified
- Retry `request_key`/source identity เดิมต้องได้ผลเดิม; เปลี่ยน payload ภายใต้ identity เดิมเป็น conflict. Lost ACK ไม่สร้าง financial/payment/Stock ซ้ำ
- Tax full replacement ใช้ original sale + issuer snapshot + buyer name/tax_id/branch_no/address. ใช้ server totals; ไม่มี GL/payment fields ที่ client เลือกเอง
- Restaurant option เป็น configured SKU/BOM; passive `/state` polling ไม่ต่อ idle timeout. Pharmacy inspection ต้อง manager + `pharmacist_qualified=true` ทั้ง authority ฝั่ง POS/ERP

Field schemas/validation ให้ยึด [POS staff routes](../pos/routes/web.php), [connector routes](../pos/routes/connector.php), [ERP controller](../backend/app/Http/Controllers/Admin/PosController.php), [ERP event services](../backend/app/Services/Pos) และ [POS services](../pos/app/Services). Credentials/tokens/private keys ไม่ใส่ในเอกสารตัวอย่าง

## POS Config routes — 2026-10-29

| Method | URI | Scope |
| --- | --- | --- |
| GET / HEAD | `/config` | manager / own branch / local-loopback |
| POST | `/config/branch` | name/business_type + manager password |
| POST | `/config/terminal` | code + manager password |
| POST | `/config/staff` | name/email/password confirmation/role + manager password |

ดู [contract/errors/security](POS_CONFIGURATION.md). Route POST จริงใช้ `/config/{section}` และ controller allowlist branch/terminal/staff เท่านั้น; ไม่ใช่ External Read API.
