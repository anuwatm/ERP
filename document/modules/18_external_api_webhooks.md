# 18 — External Read API และ Webhook Outbox

Source snapshot 2026-10-24 ฐาน commit `b03c393c19762a09862b4a7aab688f1fe47dcdb3`
อธิบาย routes/services จริง; ไม่ได้เรียก endpoint หรือส่ง webhook จริง

## ขอบเขตและ source

API อ่านข้อมูลสำหรับ integration ใช้ bearer key ขององค์กรหรือ party
Webhook แจ้ง resource เปลี่ยนแล้วให้ผู้รับเรียก Read API อ่านรายละเอียดตามสิทธิ์
ไม่เปิด write API สำหรับแก้ธุรกิจใน route ชุดนี้

- [External routes](../../backend/routes/external.php), [Summary routes](../../backend/routes/summaries.php)
- [Bootstrap](../../backend/bootstrap/app.php): register API ผ่าน routes/gateway.php
- [AuthenticateIntegrationKey](../../backend/app/Http/Middleware/AuthenticateIntegrationKey.php): `handle`
- [IntegrationApiKey](../../backend/app/Models/IntegrationApiKey.php): SCOPES/SCOPE_PERMISSIONS/PARTY_SCOPES
- [ExternalBusinessController](../../backend/app/Http/Controllers/Api/ExternalBusinessController.php): RESOURCES/ITEMS/index/show/items
- [ExternalReadPagination](../../backend/app/Support/ExternalReadPagination.php): `filters`, `page`
- [ExternalDocumentAccess](../../backend/app/Support/ExternalDocumentAccess.php): `query`, `forUser`
- [ExternalWebhookService](../../backend/app/Services/ExternalWebhookService.php): `record`, `verify`, `dispatch`, `deliver`, `mayDeliver`, `send`
- [WebhookDestinationResolver](../../backend/app/Services/WebhookDestinationResolver.php): `resolve`, `isPublicIp`
- [Config](../../backend/config/external_api.php)

## Management routes

| Method / path | Action | Permission |
|---|---|---|
| GET /settings/api-access | index | api_keys.view |
| POST /settings/api-access/keys | store | api_keys.manage |
| PATCH /settings/api-access/keys/{apiKey} | update | api_keys.manage |
| POST /settings/api-access/keys/{apiKey}/revoke | revoke | api_keys.manage |
| GET /settings/api-access/parties | parties | api_keys.manage |
| GET /settings/api-access/documents | documents | api_keys.manage |
| GET /settings/api-access/webhooks | index | api_keys.view |
| POST /settings/api-access/webhooks | store | api_keys.manage |
| POST /settings/api-access/webhooks/{webhook}/{verify,pause,rotate} | action ชื่อเดียวกัน | api_keys.manage |

mutations ต้อง password.confirm, throttle:10,1; party/document selectors throttle:30,1
[Key controller](../../backend/app/Http/Controllers/Settings/IntegrationApiKeyController.php)
และ [Webhook controller](../../backend/app/Http/Controllers/Settings/IntegrationWebhookController.php) ตรวจ org/active user เพิ่ม

## Read API routes

ทุก path ต่อไปนี้ prefix `/api/v1` และใช้ GET

| Path / กลุ่ม | Scope |
|---|---|
| /summaries/sales, /summaries/finance, /summaries/projects | summary:{domain}:read |
| /customers, /suppliers, /quotations, /invoices, /delivery-orders, /payments | {resource}:read |
| /purchase-orders, /goods-receipts, /projects, /tasks | {resource}:read |
| กลุ่ม resource ข้างบน /{id} | scope ของ resource |
| /{quotations,invoices,delivery-orders,purchase-orders,goods-receipts}/{id}/items | scope ของ resource |
| /projects/{id}/tasks | tasks:read |
| /products, /products/{id} | products:read |
| /reference/{resource} | inventory:read หรือ reference:organization:read/reference:currencies:read ตาม resource |
| /inventory/balances | inventory:read |
| /summaries/inventory, /summaries/procurement, /summaries/manufacturing | summary:{domain}:read |
| /summaries/hr, /summaries/attendance, /summaries/leaves, /summaries/roster | summary:{domain}:read |
| /employees, /employees/{id} | employees:read |
| /employees/{id}/{attendance,roster,leave-balances,leave-requests} | employees:{attendance,roster,leaves}:read |
| /manufacturing/{boms,work-orders}[/{id}[/items]] | manufacturing:{resource}:read |
| /documents, /documents/{id} | documents:read |
| /documents/{id}/download | documents:download |
| /approvals, /approvals/{id} | approvals:read |

price/cost/formula scopes ควบคุม fields เพิ่ม ไม่ใช่ endpoints แยกในตารางนี้
รายการ reference ที่แน่นอนอยู่ ExternalCatalogController::REFERENCES

## Authentication และ row access

token รูปแบบ `erp_{24 hex}.{64 hex}`; ค้น prefix แล้วเทียบ SHA-256 แบบ hash_equals
reject key revoked/expired หรือ organization ไม่ active
production ต้อง HTTPS มิฉะนั้น 426
ต้องมี scope และ allowsPrincipalScope ผ่านทั้งคู่
principal customer/supplier จำกัด PARTY_SCOPES และ party_id
controllers/support ตรวจ issuer permission และ row scope เพิ่ม ไม่ใช่มี key แล้วได้ทุก row
Document API ต้อง allowlist document_ids และ active/internal sensitivity
category sensitivity ต้อง internal เช่นกัน และ parent links ทุกอันต้องผ่าน visibility
การ download ต้องอ่าน ExternalDocumentController เพิ่มเรื่อง scan และไฟล์

## Pagination และ contract

limit 1–100, default 50; updated_since ใช้ UTC format `Y-m-dTH:i:sZ`
reject query/body ที่ไม่รองรับ
cursor encrypted ผูก key/org/scopes/access_policy/path/filters และอายุ 30 นาที
sort `(updated_at,id)` และ snapshot upper bound จึงไม่ใช้ offset pagination
delta ของ soft-deleted resource ให้ tombstone fields ตาม implementation
upper bound เป็น fixed sync watermark ไม่ใช่ database snapshot; response ระบุ `consistency=live` และ `reconciliation_required=true` จึงต้องทำ full reconciliation สำหรับข้อมูลลบ/visibility และ derived values ที่ delta ไม่ครอบคลุม
response ใช้ ExternalApiResponse และ request id
rate key 60/min; invalid token 30/min/IP พร้อม Retry-After
summary max date window config 366 วัน
key อายุเริ่มต้น 90 วัน สูงสุด 365 วัน active keys สูงสุด 20

## Webhook entities และ flow

IntegrationWebhook ผูก key, org, events, url, encrypted signing_secret, verified_at/status
IntegrationWebhookEvent เป็น business event กลาง
IntegrationWebhookDelivery แยก delivery ต่อ endpoint พร้อม attempts/lease/status
[Migration](../../backend/database/migrations/2026_10_19_000002_create_external_webhook_outbox.php)

รองรับ invoice.payment.status.changed, stock.balance.changed, task.status.changed, approval.status.changed
record เฉพาะ endpoint active/verified และ event subscription ตรง
create endpoint เริ่ม paused; verify signed challenge จึง active
rotate เปลี่ยน secret แล้ว paused และล้าง verified_at
config EXTERNAL_API_WEBHOOKS_ENABLED default false
scheduler external-api:dispatch-webhooks --limit=20 ทุกนาที

pending/retry → processing → delivered / retry / dead_letter / skipped_no_access
processing มี lease 30 วินาทีและ reclaim หลังหมด lease
ตรวจ key/permission/row visibility ซ้ำก่อนส่ง จึง revoke แล้วหยุด delivery ได้
2xx สำเร็จ; 429/5xx/transport failure retry ตามข้อจำกัด สูงสุด 5 attempts
4xx อื่น dead_letter; รองรับ Retry-After
payload ไม่ใช่ full resource dump; ส่ง event/resource identification

## Transport และข้อจำกัด

HTTPS port 443 เท่านั้น; ปฏิเสธ unsafe URL และ private/reserved IP
resolve public IP แล้ว pin ผ่าน CURLOPT_RESOLVE; ปิด proxy และ redirects
TLS verify เปิด, connect timeout 2s, total timeout 5s, response จำกัด 8 KB
ต้องมี curl extension และ DNS/network จริง
signature `X-ERP-Signature: t={timestamp},v1=HMAC_SHA256(timestamp.body)`
header X-ERP-Event-Id ใช้ dedupe ฝั่งผู้รับ
webhooks นี้แยกจาก payment settlement และ e-Tax callbacks

## Tests — ไม่ได้รัน

[ExternalWebhookTest](../../backend/tests/Feature/ExternalWebhookTest.php): อ่าน rollback/outbox, exact byte signature, 429 retry, revoke visibility
อ่าน assertions DNS pin/TLS/no redirect และไม่ส่ง private task title ใน payload
[ExternalReadContractTest](../../backend/tests/Feature/ExternalReadContractTest.php)
[ExternalRestrictedReadApiTest](../../backend/tests/Feature/ExternalRestrictedReadApiTest.php)
[ExternalBusinessApiTest](../../backend/tests/Feature/ExternalBusinessApiTest.php)
[ExternalHrApiTest](../../backend/tests/Feature/ExternalHrApiTest.php)
[ExternalCatalogApiTest](../../backend/tests/Feature/ExternalCatalogApiTest.php)
[ExternalSummaryApiTest](../../backend/tests/Feature/ExternalSummaryApiTest.php)
