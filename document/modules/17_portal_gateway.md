# 17 — Customer/Supplier Portal และ Payment Gateway

สำรวจ source 2026-10-24 ฐาน commit `b03c393c19762a09862b4a7aab688f1fe47dcdb3`
อ้างอิง working tree; ไม่ได้เปิด portal จริงหรือส่ง payment/provider request

## ขอบเขตและแหล่งหลัก

Portal เป็น session แยกจาก staff สำหรับ customer/supplier
Gateway สร้าง QR ชำระ invoice และรับ settlement ก่อนลงบัญชี

- [PortalController](../../backend/app/Http/Controllers/PortalController.php)
- [PortalAccessService](../../backend/app/Services/PortalAccessService.php)
- [GatewayController](../../backend/app/Http/Controllers/GatewayController.php)
- [GatewaySettlementService](../../backend/app/Services/GatewaySettlementService.php)
- [Portal UI](../../backend/resources/js/Pages/Portal/Dashboard.tsx)
- [Gateway UI](../../backend/resources/js/Pages/Settings/PaymentGateway.tsx)
- [web routes](../../backend/routes/web.php), [API gateway routes](../../backend/routes/gateway.php)

## Routes

| Method / path | Action / gate |
|---|---|
| POST /portal-users | provision; users.create, password.confirm, throttle:10,1 |
| GET /portal/access/{token} | consume; throttle:10,1 |
| GET /portal/sign-in | signIn |
| GET /portal | dashboard; throttle:60,1 |
| POST /portal/logout | logout; throttle:10,1 |
| POST /portal/quotations/{quotation}/accept | acceptQuotation; throttle:10,1 |
| POST /portal/vendor-bills | submitVendorBill; throttle:5,1 |
| GET /portal/document-versions/{version}/download | download; throttle:30,1 |
| GET /invoice-payment/{invoice} | qr; throttle:30,1 และตรวจ staff/party access |
| GET /settings/payment-gateway | settings.organization.view |
| PUT /settings/payment-gateway | settings.organization.update, password.confirm, throttle:10,1 |
| POST /api/gateway/{config}/settlement | signed settlement; throttle:60,1 |
| POST /api/gateway/{config}/opn | provider event; throttle:30,1 |

แม้ public portal routes ไม่ใช้ staff auth แต่ controller ต้องได้ active PortalUser จาก cookie

## Entities และ relations

PortalUser ผูก org_id และ party_type/id (`customer|supplier`)
PortalAccessToken เก็บ SHA-256 token_hash, expires_at, used_at
PortalSession เก็บ token_hash, expiry, revoked_at, last_seen_at, user_agent_hash/ip_hash
VendorBillSubmission ผูก supplier/portal_user/document และ pending_scan
PaymentGatewayConfig ผูก org และบัญชี THB พร้อม encrypted secrets
GatewayTransaction ผูก invoice/config/bank/payment และจำนวน amount_minor
WebhookEvent บันทึก event_id/payload_hash/status/reason เพื่อกัน replay

## Provision และ login

`provision` ตรวจ party อยู่ org ก่อน updateOrCreate PortalUser
สร้าง magic token 80 ตัวอักษร อายุ 30 นาที แล้วส่งลิงก์ด้วย Mail::raw
`consumeMagicLink` transaction + lock ตรวจยังไม่ใช้และไม่หมดอายุ
token ใช้ได้ครั้งเดียวและต้องเป็น active PortalUser
สร้าง session 80 ตัวอักษร อายุ 8 ชั่วโมง เก็บเฉพาะ hash
cookie ชื่อ erp_portal_session; HttpOnly และ SameSite=lax
`consume` เรียก cookie โดย secure=false ชัดเจน ควรตรวจ reverse proxy/HTTPS deployment
session เก็บ IP/UA hashes แต่ `user` ไม่ใช้ hashes นั้นตรวจความตรง
logout ตั้ง revoked_at และล้าง cookie

## Portal business behavior

customer เห็น quotations sent/approved/converted
invoice เห็น sent/partially_paid/paid/overdue ของ customer ตน
accept ต้อง quotation sent และยังไม่หมดอายุ
accept สร้าง Invoice draft และ copy รายการ/FX snapshot จาก quotation
quotation เปลี่ยน converted พร้อม converted_invoice_id และ audit
การ accept จึงยังไม่ได้ส่ง invoice หรือรับเงินอัตโนมัติ
supplier เห็น PO sent/approved/partially_received/received/closed
supplier เห็น payables approved/partially_paid/paid และ submissions ของตน
upload bill สร้าง Document finance_confidential และ DocumentVersion pending_scan
VendorBillSubmission เป็น pending_scan เช่นกัน; ไม่ใช่ Expense ที่อนุมัติแล้ว
portal download ต้อง org ตรง, scan clean และ document มี link ตรง party

## QR intent และ settlement

`intent` lock invoice และตรวจ THB, status sent/partially_paid/overdue, ยอดมากกว่า 0
reuse pending intent ยอดเดิมที่ยังไม่หมดอายุ
creating/creation_unknown กันการสร้าง provider charge ซ้ำเมื่อผลยังคลุมเครือ
QR intent อายุเริ่มต้น 30 นาที และเก็บจำนวนเงินหน่วยย่อย
OPN ใช้ provider QR; มีบริการ PromptPay QR สำหรับเส้นทางที่กำหนด

`receive` ตรวจ signature/timestamp และ payload ตาม config
same event id + hash ใช้ผลเดิม; conflicting replay ต้องถูกปฏิเสธ
settled จะลงบัญชีเมื่อ transaction/invoice/amount/currency/bank/time window ถูกต้อง
ต้องมี posted invoice journal, FX snapshot และ accounting period ไม่ closed
OPN test mode ห้ามลงบัญชีแม้ได้ signed settlement
provider confirmation/charge identity ต้องพร้อมเมื่อเส้นทางกำหนด
ผิดเงื่อนไขสำคัญเข้า event status review แทนสร้าง payment
ผ่านแล้วสร้าง payment/GL, อัปเดต balance_due และ invoice paid/partially_paid/overdue
transaction settled และ event processed ใน transaction เดียวกัน

## Dependencies และข้อจำกัด

ใช้ NumberSequenceService, FinancialJournalService, bank accounts, accounting periods, FX
การเปลี่ยน receiver/provider config ถูก block เมื่อมี intent ที่เสี่ยงรับ settlement ค้าง
e-Tax callback เป็น route อีกชุด ไม่ใช่ settlement gateway นี้
ต้องมี mail transport, local private storage, provider keys และ network ตาม provider
pending_scan ต้องมีขั้นตอนสแกนแยกก่อนดาวน์โหลด supplier bill
ไม่มีหลักฐานจากการอ่าน source ว่า provider live credentials พร้อมใช้งาน

## Tests ที่ตรวจรายการ/อ่าน — ไม่ได้รัน

- [Phase22PortalTest](../../backend/tests/Feature/Phase22PortalTest.php): single-use link, party scope, supplier quarantine
- [Phase23GatewayTest](../../backend/tests/Feature/Phase23GatewayTest.php): QR CRC/reference, duplicate settlement, signature/replay, closed period, overpay, test mode, ambiguous create
- tests ระบุ expected behavior; ไม่ใช่รายงานผลรันรอบนี้

