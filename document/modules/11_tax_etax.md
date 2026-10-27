# 11 — Tax reports และ e-Tax

Snapshot 2026-10-24, HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` รวม working tree
หลักฐานเป็นการอ่าน source/tests; ไม่ได้รัน tests และไม่ได้ทดสอบ provider ภายนอก

## ขอบเขต

- รายงานภาษีขาย ซื้อ หัก ณ ที่จ่าย และ AR/AP aging พร้อม CSV/Excel export
- สร้าง e-Tax XML snapshot จากเอกสาร ERP, download, sign, queue submit และติดตามผล
- RD Prep export สำหรับ ภ.ง.ด. 3/53
- ความพร้อม production ต้องแยกจากการมีหน้าจอหรือสถานะ `accepted` ในฐานข้อมูล

## Routes และสิทธิ์

อ้างอิง [web.php](../../backend/routes/web.php)

| Route/action | Permission |
|---|---|
| GET `/tax-reports`, `/{type}/export`, `/{type}/excel` | `tax_reports.view` |
| GET `/e-tax` | `e_tax.view` |
| PATCH `/e-tax/config` | `e_tax.manage` |
| POST `/e-tax/documents` → `generate` | `e_tax.manage` |
| GET `/e-tax/documents/{id}/download` | `e_tax.view` |
| POST `/e-tax/documents/{id}/sign` | `e_tax.manage` |
| POST `/e-tax/documents/{id}/submit`, `/{id}/inquire` | `e_tax.submit` |
| GET `/e-tax/rd-prep` | `e_tax.view` |

Config/generate/sign/submit ใช้ `password.confirm`; submission throttle 5 ครั้ง/นาที
Record e-Tax ต่างองค์กรถูกปฏิเสธด้วย 403 ใน controller

## โค้ดหลักและข้อมูล

- [TaxReportController](../../backend/app/Http/Controllers/Finance/TaxReportController.php): `index`, `export`, `excel`, source queries และ aging
- [ETaxController](../../backend/app/Http/Controllers/Finance/ETaxController.php): config, generate/download/sign/submit/inquire และ RD Prep
- [ETaxDocumentService](../../backend/app/Services/ETax/ETaxDocumentService.php): source eligibility, immutable snapshot, XML/hash
- [ETaxProviderGatewayService](../../backend/app/Services/ETax/ETaxProviderGatewayService.php): sign, submit, inquiry, webhook transition
- [SubmitETaxDocument](../../backend/app/Jobs/SubmitETaxDocument.php): queue `etax`, `$tries=3`, ทำเฉพาะ record `queued`
- [ETaxConfig](../../backend/app/Models/ETaxConfig.php): `mode`, provider, certificate metadata, signature mode และ encrypted `provider_settings`
- [ETaxDocument](../../backend/app/Models/ETaxDocument.php): source/document type, payload snapshot, XML path/SHA-256, state/timestamps
- [ETaxSubmissionAttempt](../../backend/app/Models/ETaxSubmissionAttempt.php): attempt number, provider, response/ref, time/status
- [RDPrepExportService](../../backend/app/Services/ETax/RDPrepExportService.php): ข้อมูลหัก ณ ที่จ่ายสำหรับนำเข้าเครื่องมือภายนอก

## รายงานภาษี

- CSV `export()` รับ `sales`, `purchase`, `withholding`; type อื่น 404
- `excel()` เพิ่ม `ar-aging`, `ap-aging`
- Filters: date range, branch, status, customer, supplier; scope `org_id`
- ใช้ Invoice และ issued credit/debit notes สำหรับขาย; แหล่งซื้อประกอบด้วย Expense, GoodsReceipt และเอกสารปรับปรุงตาม query
- ภาษีหัก ณ ที่จ่ายอิง approved/paid expenses ที่มี withholding amount
- AR aging ใช้ invoice `sent`, `partially_paid`, `overdue`
- AP aging ใน controller นี้ query purchase orders `approved`, `partially_received`, `received`; ต้องแยกจาก AP balance ของ Expense/VendorPayment
- รายงานเป็นข้อมูลจากสถานะ ERP ไม่ใช่หลักฐานยื่นแบบสำเร็จ

## Generate และ snapshot

1. `assertSource()` ตรวจชนิดเอกสารและ source
2. `tax_invoice`: Invoice `sent`, `partially_paid`, `paid`, `overdue`
3. `receipt`: Payment `entry_type=receipt` และ amount มากกว่าศูนย์
4. `credit_note`/`debit_note`: CreditDebitNote มี type ตรงกัน
5. บันทึก payload และ XML ลง local private storage พร้อม SHA-256 → `generated`
6. Document ที่ `queued`, `simulated`, `submitted`, `accepted` หรือเคยมี attempt จะ regenerate ไม่ได้
7. `xml()` ตรวจไฟล์มีอยู่และ checksum ตรงก่อนส่งคืน

## Modes: disabled / manual / mock / provider

- `ETaxConfig::MODES` ปัจจุบันคือ `disabled`, `manual_export`, `mock`, `provider`
- `updateConfig()` ยอมรับทั้ง 4 ค่า; mock บังคับ `provider_code=mock`, `signature_mode=none`
- Submit route ยอมเฉพาะ config mode `mock` หรือ `provider`; disabled/manual ไม่ queue submission
- `manual_export` ใช้การสร้าง/download XML เป็นทางออกด้วยคน ไม่มี auto submission
- Mock adapter ตรวจองค์กร, mode, XML profile และปฏิเสธ DOCTYPE/ENTITY
- Mock คืน `SIMULATED_ONLY`; gateway บันทึก document/attempt `simulated` ไม่มี network และไม่เปลี่ยนเป็น submitted/accepted
- [MockETaxSubmissionAdapter](../../backend/app/Services/ETax/MockETaxSubmissionAdapter.php) คือ simulation ที่ gateway เรียกโดยตรง
- [DisabledETaxSignatureAdapter](../../backend/app/Services/ETax/DisabledETaxSignatureAdapter.php) และ [DisabledETaxSubmissionAdapter](../../backend/app/Services/ETax/DisabledETaxSubmissionAdapter.php) โยน validation exception
- อย่างไรก็ตาม [AppServiceProvider::register](../../backend/app/Providers/AppServiceProvider.php) bind interfaces ไป `ETaxXmlSignatureService` และ `RdApiSubmissionAdapter`; ไม่ได้ bind disabled adapters
- Gateway constructor inject concrete signer/provider/mock services จึงอย่าสมมติว่า disabled class เป็น production gate ที่ใช้งานจริง

## Submit/inquiry/webhook

- Controller lock document; queue ซ้ำในสถานะ queued เป็น no-op
- รับ source state `generated`, `signed`, `error`, `rejected` และ attempts ต้องน้อยกว่า 3
- เปลี่ยนเป็น `queued`, dispatch job `afterCommit()`
- Gateway lock document, สร้าง attempt `processing`; เมื่อครบ 3 เปลี่ยน document เป็น `error`
- Provider mode auto-sign ถ้า signature mode `xml_dsig` และยังไม่มี `signed_at`
- [RdApiSubmissionAdapter::submit](../../backend/app/Services/ETax/RdApiSubmissionAdapter.php) POST `/documents/submit`, timeout 15 วินาที, base64 XML และ `X-Idempotency-Key=ETAX-SUBMIT-{id}`
- API URL/client credentials มาจาก encrypted provider settings หรือ config fallback
- Response status accepted → `accepted`; อื่นที่ successful → `submitted`; exception → `error`, attempt `failed`
- `inquire()` เรียก provider แล้ว map accepted/rejected; `handleWebhook()` ค้น record ขององค์กรและไม่เปลี่ยน final accepted/rejected ซ้ำ
- Queue worker และ provider credentials/network ต้องตั้งภายนอก; การอ่าน code ไม่ยืนยัน endpoint contract จริง

## Signature ที่สร้างจริง และขอบเขต production

[ETaxXmlSignatureService::sign](../../backend/app/Services/ETax/ETaxXmlSignatureService.php) สร้าง XML `ds:Signature`, `SignedInfo`, digest และ `KeyInfo`
แต่บรรทัดสร้างค่าลายเซ็นจริงคือ:

```php
$rawSignature = hash_hmac('sha256', $canonicalSignedInfo, $document->org_id.':'.$document->id, true);
$certMock = base64_encode('ETAX-VAULT-CERTIFICATE-REF:'.($document->organization?->tax_id ?: 'TAXID'));
```

- แม้ `SignatureMethod` ระบุ RSA-SHA256 ค่าจริงเป็น HMAC-SHA256 และ certificate เป็น placeholder string
- ไม่พบ private key/certificate vault signing จริงใน method นี้; `certificate_reference` เป็น config metadata
- จึงยังสรุปไม่ได้ว่าลายเซ็นนี้ตรวจผ่าน XML-DSig/RD/provider production
- ไม่พบ environment gate เช่น `app()->environment('production')` ใน controller/gateway/adapter ที่ตรวจ
- Provider mode มีเส้นทาง HTTP จริง; ชื่อ mode และ class ไม่ได้เป็นหลักฐาน regulatory compliance

## RD Prep, UI และ migrations

- `RDPrepExportService::export()` รับเฉพาะ form 3/53 และบังคับ organization tax ID
- Source เป็น Expense `approved`/`paid`, form ตรง, withholding amount บวก; date filter ใช้ expense date
- [TaxReports.tsx](../../backend/resources/js/Pages/Finance/TaxReports.tsx), [ETax.tsx](../../backend/resources/js/Pages/Finance/ETax.tsx)
- [e-Tax schema](../../backend/database/migrations/2026_08_28_000001_create_phase12_etax_tables.php)
- [Purchase tax sources](../../backend/database/migrations/2026_08_25_000001_add_purchase_tax_sources_to_expenses_and_goods_receipts.php)
- [Expense withholding fields](../../backend/database/migrations/2026_08_23_000002_add_withholding_tax_to_expenses.php)

## Tests ที่อ่านและความไม่สอดคล้อง

- [Phase8TaxReportsTest](../../backend/tests/Feature/Phase8TaxReportsTest.php): tax report sources/export
- [Phase12ETaxTest](../../backend/tests/Feature/Phase12ETaxTest.php): e-Tax/XML/RD Prep contracts
- [Phase24ETaxFoundationTest](../../backend/tests/Feature/Phase24ETaxFoundationTest.php): opt-in mock, duplicate queue, tampered XML, retry, tenant/private payload และ recovery
- ชื่อ test `test_mock_is_opt_in_and_live_config_is_rejected` อาจทำให้เข้าใจว่าห้าม provider
- เมื่ออ่าน body จริง test ส่ง `invalid_mode` ไม่ใช่ `provider`; จึงไม่ใช่หลักฐานว่า provider ถูกปฏิเสธ
- เป็นความคลุมเครือของชื่อ test กับ coverage; ไม่ใช่ confirmed failing test หรือข้อยืนยัน production gate
- เอกสารนี้ไม่ได้รายงานผล tests หรือยืนยัน production submission
- เชื่อม [Accounting](09_accounting_fx_assets.md) และ [Treasury](10_treasury_banking.md) สำหรับ source transactions
