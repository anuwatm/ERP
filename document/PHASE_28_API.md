# Phase 28 — API and route inventory — 2026-10-27

Local implementation ผ่าน Gemini; ฐานตาม config อัปเดตแล้ว. ตารางด้านล่างอ่านจาก Laravel route metadata ปัจจุบัน. นี่ไม่ใช่ public ERP Write API และไม่นับรวมใน External Read API 62 GET paths

## Authentication and boundaries

- ERP POS admin ใช้ ERP auth/verified/org scope + `pos.view`, `pos.manage`, `pos.reconcile` ตาม route. Mutation สำคัญใช้ recent password confirmation/throttle; provisioning/recovery เพิ่ม `inventory.adjust`
- POS staff ใช้ session/CSRF แยก ERP; server ตรวจ branch/terminal/role/feature flags. Manager actions ตรวจ password และ command binding ไม่ส่ง password เข้า event/audit
- Public menu ใช้ expiring/revocable token ผูก branch/table session และ throttle; anonymous request ยังไม่ชำระเงินหรือ reserve Stock จน staff accept
- Connector base `/connector/v1`: service authentication/mTLS ingress verification + instance/branch/scope allowlists. Transport ทดสอบเฉพาะ loopback HTTPS; production/remote ยังปิด
- `401/403`: auth/scope/feature denied; `409`: state/evidence conflict; `422`: validation; `423`: recovery fence; `429`: throttled. เก็บ response error จริง ไม่ตีความ network timeout เป็น success

## ERP routes

| Method | URI | Route name |
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

## POS routes

| Method | URI | Boundary |
| --- | --- | --- |
| `GET / HEAD` | `/` | Session/staff or page/health |
| `POST` | `/checkout` | Session/staff or page/health |
| `PUT` | `/connector/v1/catalog/{version}` | Connector |
| `GET / HEAD` | `/connector/v1/checkpoint` | Connector |
| `PUT` | `/connector/v1/checkpoints/{id}/ack` | Connector |
| `GET / HEAD` | `/connector/v1/events` | Connector |
| `PUT` | `/connector/v1/grants/{id}` | Connector |
| `PUT` | `/connector/v1/grants/{id}/freeze` | Connector |
| `PUT` | `/connector/v1/recalls/{id}` | Connector |
| `PUT` | `/connector/v1/results/{id}` | Connector |
| `PUT` | `/connector/v1/tax-profile` | Connector |
| `POST` | `/customer-requests/{id}/accept` | Session/staff or page/health |
| `POST` | `/customer-requests/{id}/reject` | Session/staff or page/health |
| `POST` | `/local-cash-movements` | Session/staff or page/health |
| `POST` | `/local-discount-approvals` | Session/staff or page/health |
| `POST` | `/local-fixtures/prescriptions` | Session/staff or page/health |
| `GET / HEAD` | `/local-fixtures/prescriptions/{id}` | Session/staff or page/health |
| `POST` | `/local-refunds` | Session/staff or page/health |
| `GET / HEAD` | `/local-return-inspections` | Session/staff or page/health |
| `POST` | `/local-return-inspections` | Session/staff or page/health |
| `POST` | `/local-voids` | Session/staff or page/health |
| `POST` | `/login` | Session/staff or page/health |
| `POST` | `/logout` | Session/staff or page/health |
| `GET / HEAD` | `/menu` | Public token/menu |
| `POST` | `/menu-tokens` | Session/staff or page/health |
| `POST` | `/menu-tokens/{id}/revoke` | Session/staff or page/health |
| `POST` | `/menu/catalog` | Public token/menu |
| `POST` | `/menu/requests` | Public token/menu |
| `POST` | `/menu/status` | Public token/menu |
| `POST` | `/restaurant/bill-preview` | Session/staff or page/health |
| `POST` | `/restaurant/cancel` | Session/staff or page/health |
| `POST` | `/restaurant/checkout` | Session/staff or page/health |
| `POST` | `/restaurant/customer-requests/{id}` | Session/staff or page/health |
| `POST` | `/restaurant/lines/{id}/{action}` | Session/staff or page/health |
| `POST` | `/restaurant/orders` | Session/staff or page/health |
| `POST` | `/restaurant/sessions/{id}/close` | Session/staff or page/health |
| `POST` | `/restaurant/tables` | Session/staff or page/health |
| `POST` | `/restaurant/tables/{id}/open` | Session/staff or page/health |
| `POST` | `/shifts` | Session/staff or page/health |
| `POST` | `/shifts/{id}/close` | Session/staff or page/health |
| `GET / HEAD` | `/state` | Session/staff or page/health |
| `GET / HEAD` | `/tax-documents` | Session/staff or page/health |
| `POST` | `/tax-documents` | Session/staff or page/health |
| `GET / HEAD` | `/tax-documents/{id}/print` | Session/staff or page/health |
| `GET / HEAD` | `/up` | Session/staff or page/health |

## Payload and idempotency contracts

- Signed event domain `company-os.pos-event.v1`: immutable event/source identity, sequence, instance/key/schema/payload hash + signature. ERP validates registered key, source catalog/policy, money/unit/lot/quota and original dependency evidence
- Signed checkpoint domain `company-os.pos-checkpoint.v1`: generation, after/through/high watermark, ordered envelope-hash manifest + digest. Max 1,000 entries/page. ACK เฉพาะ ERP reconciliation verified
- Retry `request_key`/source identity เดิมต้องได้ผลเดิม; เปลี่ยน payload ภายใต้ identity เดิมเป็น conflict. Lost ACK ไม่สร้าง financial/payment/Stock ซ้ำ
- Tax full replacement ใช้ original sale + issuer snapshot + buyer name/tax_id/branch_no/address. ใช้ server totals; ไม่มี GL/payment fields ที่ client เลือกเอง
- Restaurant option เป็น configured SKU/BOM; passive `/state` polling ไม่ต่อ idle timeout. Pharmacy inspection ต้อง manager + `pharmacist_qualified=true` ทั้ง authority ฝั่ง POS/ERP

Field schemas/validation ให้ยึด [POS staff routes](../pos/routes/web.php), [connector routes](../pos/routes/connector.php), [ERP controller](../backend/app/Http/Controllers/Admin/PosController.php), [ERP event services](../backend/app/Services/Pos) และ [POS services](../pos/app/Services). Credentials/tokens/private keys ไม่ใส่ในเอกสารตัวอย่าง
