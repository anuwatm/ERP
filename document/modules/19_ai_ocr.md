# 19 — AI Configuration และ OCR Ingestion

สำรวจ source 2026-10-24 ฐาน commit `b03c393c19762a09862b4a7aab688f1fe47dcdb3`
อ้างอิง working tree; ไม่ได้เรียก AI provider หรือรัน tests

## ขอบเขต

อ่านใบเสร็จ/ใบกำกับจากภาพด้วย Vision API ที่รองรับ OpenAI-compatible protocol
ผู้ใช้ตรวจและแก้ข้อมูลก่อนแปลงเป็น Expense draft หรือ vendor bill draft
ไม่ใช่ AI agent ที่อนุมัติรายจ่ายหรือลง GL อัตโนมัติ

## Source หลัก

- [AiConfigurationController](../../backend/app/Http/Controllers/Settings/AiConfigurationController.php): `index`, `store`, `testConnection`
- [OcrDocumentController](../../backend/app/Http/Controllers/Finance/OcrDocumentController.php): upload/show/convert/reject
- [OcrProcessingService](../../backend/app/Services/Ocr/OcrProcessingService.php): `process`, `resolveAdapter`, `sanitizeErrorMessage`
- [UniversalOpenAiVisionAdapter](../../backend/app/Services/Ocr/UniversalOpenAiVisionAdapter.php): extract/cloud policy/PDF payload
- [ThaiTaxIdValidator](../../backend/app/Services/Ocr/ThaiTaxIdValidator.php)
- [RobustJsonCleaner](../../backend/app/Services/Ocr/RobustJsonCleaner.php)
- [AiConfiguration](../../backend/app/Models/AiConfiguration.php), [OcrExtraction](../../backend/app/Models/OcrExtraction.php)
- [Configuration UI](../../backend/resources/js/Pages/Settings/AiConfiguration.tsx)
- [OCR list](../../backend/resources/js/Pages/Finance/OcrIndex.tsx), [Reviewer](../../backend/resources/js/Pages/Finance/OcrReviewer.tsx)
- [Routes](../../backend/routes/web.php)

## Routes และสิทธิ์

| Method / path | Action | Permission / throttle |
|---|---|---|
| GET /settings/ai-configuration | index | ai_settings.manage |
| POST /settings/ai-configuration | store | ai_settings.manage; 10/min |
| POST /settings/ai-configuration/test | testConnection | ai_settings.manage; 10/min |
| GET /finance/ocr | index | ocr.view |
| POST /finance/ocr/upload | upload | ocr.upload; 20/min |
| GET /finance/ocr/{extraction} | show | ocr.view |
| POST /finance/ocr/{extraction}/convert-expense | convertExpense | ocr.convert; 15/min |
| POST /finance/ocr/{extraction}/convert-vendor-bill | convertVendorBill | ocr.convert; 15/min |
| POST /finance/ocr/{extraction}/reject | reject | ocr.convert; 15/min |

controller ตรวจ org เพิ่มใน actions ของ extraction
route ชุดนี้ไม่มี password.confirm เพิ่มเฉพาะ action เหมือน routes การเงินบางชุด
ไฟล์ preview/download ผ่าน FileController ตรวจ ocr.view และ extraction/file relation

## Entities

AiConfiguration: org_id, provider_type, base_url, encrypted api_key, model_name
เก็บ temperature, timeout_seconds, is_active และ last_test_status
OcrExtraction: stored_file_id, status, parsed_data_json, raw_response_text, confidence_score
เก็บ tax_id_valid/math_mismatch, target_type/id และ reviewer timestamps
OcrFieldResult: extraction_id, field_key, extracted_value, confidence และ correction flag
OcrTemplate: vendor matching และ default_category
StoredFile: private source document อ้าง extraction
Expense: ปลายทาง draft ทั้ง expense และ vendor bill
Supplier, PurchaseOrder, Project เป็น master/parent ที่เลือกตอน convert
[Migration](../../backend/database/migrations/2026_09_22_000002_create_phase26_ai_and_ocr_tables.php)

## Configuration behavior

store validate provider จาก AiConfiguration::PROVIDERS และ base_url string ไม่เกิน 255
temperature 0–1; timeout 5–300 วินาที
ไม่ส่ง api_key ใหม่จะเก็บ key เดิม ไม่ล้างด้วย blank input
AuditLog เก็บ provider/base_url/model/is_active แต่ไม่เก็บ api_key
index แสดงสถานะ key แทนส่ง secret ทั้งค่า
ไม่มี active config จะ fallback Ollama `http://localhost:11434/v1`
model fallback `qwen2.5-vl:7b`, temperature 0.1, timeout 60s
ไม่มีหลักฐานว่า runtime มี Ollama หรือ model ติดตั้งแล้ว

## OCR pipeline จริง

1. upload validate document เป็น file ขนาดไม่เกิน 10 MB
2. [FileAttachmentManager](../../backend/app/Support/FileAttachmentManager.php) เก็บ private tenant file และตรวจ MIME/extension
3. สร้าง extraction pending แล้วเรียก process ใน request เดียวกัน
4. process เปลี่ยน processing และอ่านไฟล์ local
5. เลือก active AI config หรือ fallback
6. ตรวจ cloud compliance ก่อนเรียก adapter
7. adapter extract และ normalize JSON fields
8. ตรวจ subtotal + vat_amount ต่าง total_amount มากกว่า 0.05 หรือไม่
9. ตรวจเลขภาษีไทยด้วย checksum
10. match supplier ใน org จาก tax_id แล้ว fallback vendor_name LIKE
11. match OcrTemplate เพื่อแนะนำ category
12. transaction บันทึก extracted, parsed data และ field results
13. หากผิดพลาดบันทึก failed/error ที่ sanitize แล้ว rethrow
14. upload controller redirect ไป reviewer พร้อมข้อความ error ทั่วไป

ผล field สำคัญ: vendor_name, tax_id, invoice_no, document_date,
subtotal, vat_rate, vat_amount, total_amount พร้อม confidence
confidence บางค่ามี fallback 0.85; ไม่ใช่ค่าความน่าเชื่อถือที่พิสูจน์จาก provider เสมอ
math_mismatch และ tax_id_valid เป็น flag ให้ตรวจ ไม่ใช่การอนุมัติ

## State และการ convert

pending → processing → extracted หรือ failed
extracted/pending/failed สามารถ convert แบบให้คนกรอกยืนยันได้
convert เปลี่ยน extraction converted และ target_type/id ชี้ Expense
สร้าง Expense status draft พร้อม FX snapshot และยอดที่ตรวจ input แล้ว
convertVendorBill ผูก supplier/PO ตาม validation และข้อมูลใน reviewer
PO ที่แสดงให้เลือกจำกัด approved/partially_received
`reject` บันทึก rejected และข้อมูลผู้ review; method ไม่จำกัด source status ก่อน reject
เมื่อ convert จะเปลี่ยน StoredFile.entity_type/id ไปชี้ expense จึงใช้ expenses.view สำหรับ preview/download ต่อ
OCR validation ใช้ tax_mode=`none|include|exclude` และบันทึกตรงลง Expense
Workflow expense approval ตรวจค่า `exclusive` จึงต้องตรวจความสอดคล้อง tax mode ข้ามโมดูลก่อนใช้งาน
approval/GL เกิดใน Finance หรือ Workflow ภายหลัง จึงต้องอ่าน module การเงินต่อ

## Cloud policy และ provider limits

provider_type openai/openrouter/gemini ถือเป็น cloud
host ที่ไม่ใช่ localhost/127.0.0.1/::1 หรือ .local ก็ถือ cloud
Setting key `ai.compliance` field `allow_cloud_ai_processing` ใช้ block ได้
หากไม่มี setting นี้ default ของ isCloudAllowed เป็น true
จึงไม่ควรอธิบายว่าแอปบังคับ local-only โดยค่าเริ่มต้น
policy host classification ไม่ใช่ SSRF/public-IP resolver แบบ external webhook

ภาพส่งเป็น vision payload; PDF รองรับเฉพาะกรณี extract embedded JPEG ได้
ใช้ค้น JPEG markers ไม่ใช่ PDF renderer หลายหน้า
PDF ไม่มี JPEG จะ error ขอให้อัปโหลด JPG/PNG/WEBP
timeout อาจทำให้ upload HTTP request รอนาน เพราะ process เป็น synchronous
provider mock/testing ใช้ MockOcrAdapter ตาม resolveAdapter
sanitize error ปิด API key/Bearer/URL/เลข 13 หลัก และจำกัด 255 ตัวอักษร

## Tests อ้างอิง — ไม่ได้รัน

[Phase26AiOcrTest](../../backend/tests/Feature/Phase26AiOcrTest.php)
มี assertions tax-id checksum, encrypted config, connection tester, OCR pipeline,
math mismatch, human review ไม่ auto GL, draft conversions, tenant isolation,
OpenAI endpoint resolution, error redaction และ cloud compliance
provider mocks ทดสอบ contract ได้ แต่ไม่ยืนยัน accuracy ของใบเสร็จจริงหรือ model availability

