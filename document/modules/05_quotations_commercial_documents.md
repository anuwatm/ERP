# 05 — Quotation และ Commercial Documents

อ่านโค้ด ณ 2026-10-24 · HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` พร้อม working tree ขณะสำรวจ
เป็น static reference; tests ที่กล่าวถึงอ่าน source เท่านั้น ไม่ได้รัน

## ขอบเขตและจุดเข้า

Quotation เป็นเอกสารก่อน Invoice; Commercial Documents รวม CN/DN, Billing Note, Delivery Order, PR และ Voucher
จุดหลัก: [QuotationController](../../backend/app/Http/Controllers/Finance/QuotationController.php), [CommercialDocumentController](../../backend/app/Http/Controllers/Finance/CommercialDocumentController.php)
Frontend: [Quotations.tsx](../../backend/resources/js/Pages/Finance/Quotations.tsx), [CommercialDocuments.tsx](../../backend/resources/js/Pages/Finance/CommercialDocuments.tsx)
Quotations.tsx สร้าง draft และส่ง approve/reject/convert ผ่าน Inertia; server เป็นผู้คำนวณยอด

## Routes / Permissions

อ้างอิง [web.php](../../backend/routes/web.php); mutation ส่วนใหญ่มี password.confirm และ throttle

| HTTP / URL | Method | Permission |
|---|---|---|
| GET `/quotations` | index | `quotations.view` |
| POST `/quotations` | store | `quotations.create` |
| PATCH `/quotations/{quotation}` | update | `quotations.update` |
| POST `/quotations/{quotation}/approve` | approve | `quotations.approve` |
| POST `/quotations/{quotation}/reject` | reject | `quotations.approve` |
| POST `/quotations/{quotation}/convert-to-invoice` | convertToInvoice | `quotations.convert` |
| GET `/commercial-documents` | CommercialDocumentController::index | `billing_notes.view` |
| POST `/credit-debit-notes` | storeCreditDebitNote | `credit_debit_notes.create` |
| POST `/billing-notes` | storeBillingNote | `billing_notes.create` |
| POST `/delivery-orders` | storeDeliveryOrder | `delivery_orders.create` |
| POST `/purchase-requests` | storePurchaseRequest | `purchase_requests.create` |
| POST `/purchase-requests/{purchaseRequest}/approve` | approvePurchaseRequest | `purchase_requests.approve` |
| POST `/purchase-requests/{purchaseRequest}/convert-to-po` | convertPurchaseRequest | `purchase_requests.approve` |
| POST `/vouchers` | storeVoucher | `vouchers.create` |
| POST `/vouchers/{voucher}/attachment` | storeVoucherAttachment | `vouchers.update` |
| GET `/commercial-documents/{type}/{id}/print` หรือ `/pdf` | print / pdf | `billing_notes.view` |

POST Quotation และ Delivery Order มี EnsureFormSubmission; อย่าเหมารวมว่าทุก commercial mutation ใช้ middleware นี้
ตัว controller ตรวจ org_id ของเอกสารก่อนเปลี่ยนสถานะ; Quotation index ใช้ org scope ไม่ใช้ SalesAccess owner scope

## Entities และ schema

- [Quotation](../../backend/app/Models/Quotation.php): customer, optional deal, items, convertedInvoice; มี tax_mode, totals, currency/FX snapshot และ timestamps สถานะ
- [QuotationItem](../../backend/app/Models/QuotationItem.php): product optional, description, quantity, unit_price, discount, tax_rate, line_total
- [quotation migration](../../backend/database/migrations/2026_08_25_000002_create_phase9_quotations_tables.php): ตาราง quotation header/items
- [commercial migration](../../backend/database/migrations/2026_08_25_000003_create_phase9_commercial_documents_tables.php): credit_debit_notes/items, billing_notes/lines, delivery_orders/items, purchase_requests/items, vouchers
- BillingNoteLine เก็บ amount_due ณ วันสร้าง; unique `(billing_note_id, invoice_id)`
- หมายเลข CN/DN, Billing, DO, PR, Voucher ใช้ NumberSequenceService และ unique ต่อองค์กรใน migration
- Foreign key ไป Invoice ของ CN/DN, BillingLine, DO ใช้ restrictOnDelete

## Quotation workflow

อ้างอิง QuotationController::store/update/transition/convertToInvoice

1. สร้างได้เฉพาะ `draft` หรือ `sent`; ต้องมีอย่างน้อย 1 item และ Customer ในองค์กร
2. Deal optional แต่ถ้าให้มา ต้องเป็นของ Customer ที่เลือก
3. คำนวณ line จาก quantity × unit_price − line discount โดยไม่ต่ำกว่า 0
4. header discount ไม่เกิน subtotal; กระจายส่วนลดตามสัดส่วนก่อนคำนวณ tax
5. tax_mode: `exclusive` บวกภาษี, `inclusive` แยกภาษีออกจากยอด, `no_tax` ตั้งภาษีเป็น 0
6. FxRateService snapshot ตาม currency และ issue_date; currency บันทึก uppercase
7. แก้ไขได้เฉพาะ draft/sent; ลบ items เดิมแล้วสร้างใหม่ใน transaction
8. approve/reject รับเฉพาะ draft/sent; ตั้ง approved_at/rejected_at และ audit
9. convert รับเฉพาะ approved; สร้าง Invoice draft, คัดลอก items/totals/FX, ตั้ง paid_amount=0 และ balance_due=total
10. issue_date ของ Invoice ใหม่เป็นวันปัจจุบัน แต่ FX snapshot คัดลอกจาก Quotation
11. เปลี่ยน Quotation เป็น converted พร้อม converted_invoice_id/converted_at

Model ยังประกาศ `expired` ใน STATUSES; controller นี้ไม่มี transition สำหรับหมดอายุอัตโนมัติ
จุดรับยอมรับจากภายนอกคือ POST `/portal/quotations/{quotation}/accept`; ต้องอ่าน PortalController เพิ่มเพื่ออธิบายสิทธิ์ token และ acceptance

## Commercial workflow

อ้างอิง CommercialDocumentController methods ตามชื่อแต่ละข้อ

- `storeCreditDebitNote`: lock Invoice; ปฏิเสธ void; credit ยอดรวมเกิน balance_due ไม่ได้
- บันทึก note สถานะ issued; credit ลด / debit เพิ่ม subtotal, tax, total, balance และ base amounts บน Invoice โดยตรง
- เรียก FinancialJournalService::postCreditDebitNote; ไม่ใช่เพียง PDF อ้างอิง
- `storeBillingNote`: Invoice ทุกใบต้องอยู่ใน Customer เดียวและ balance_due > 0; lock records แล้ว snapshot ยอดค้างลงแต่ละ line
- Billing Note ไม่รับชำระเงินเองและไม่หัก balance Invoice; Payment ยังผูก Invoice
- `storeDeliveryOrder`: รับ draft/delivered; ห้าม Invoice void และห้ามซ้ำเมื่อมี DO delivered อยู่แล้ว
- เมื่อ delivered ตรวจยอดสินค้าและสร้าง movement ขาออก; รายการที่ไม่ใช่สินค้าไม่ได้ใช้ stock แบบเดียวกัน
- `storePurchaseRequest`: สร้าง draft พร้อม items และ total
- `approvePurchaseRequest`: draft → approved; `convertPurchaseRequest`: ต้อง approved และมี supplier
- convert PR สร้าง PO draft พร้อม items โดยกำหนด `currency=THB`, `tax_mode=no_tax`; แล้ว PR → converted
- `storeVoucher`: ออกหลักฐาน receipt/payment ตาม validation; ไม่ควรถือว่าเป็น Payment ledger โดยอัตโนมัติ
- `storeVoucherAttachment`: ตรวจ org แล้ว lock Voucher ก่อนผูก file; ใช้ FileAttachmentManager
- print/pdf เลือก entity จาก type; รูปแบบเอกสารไม่ใช่หลักฐานว่าได้ส่งให้ลูกค้าจริง

## การเชื่อมโมดูล

Quotation ใช้ Customer/Deal/Product/NumberSequence/FX; conversion สร้าง Invoice แต่ไม่ได้สร้าง Payment
CN/DN กระทบ AR และ GL; Billing Note รวม AR; DO กระทบ stock; PR กระทบ Procurement
ดู [06 Billing](06_billing_payments_expenses.md), [07 Procurement/Inventory](07_procurement_inventory.md)
FinancialJournalService เป็นจุดตรวจ posting policy หากเปลี่ยนสูตรยอดหรือสถานะเอกสาร

## Tests ที่อ่าน — ไม่ได้รัน

[Phase9QuotationsTest](../../backend/tests/Feature/Phase9QuotationsTest.php): server-calculated totals, org scope, deal/customer chain, approved conversion แล้วเรียกซ้ำได้ 422
[Phase9CommercialDocumentsTest](../../backend/tests/Feature/Phase9CommercialDocumentsTest.php): credit ลด balance/ห้ามเกิน, billing customer scope, DO outbound stock, PR → PO, voucher และ commercial org scope

## ข้อควรตรวจต่อ

- conversion guard ป้องกันเรียกซ้ำตามลำดับปกติ แต่ QuotationController ไม่ lock Quotation ก่อนตรวจสถานะ; tests ที่อ่านไม่พิสูจน์ concurrent conversion
- Invoice วันใหม่ใช้ FX เก่าจาก Quotation ตามโค้ด; ควรยืนยันนโยบายธุรกิจเมื่อมีการเปลี่ยนอัตรา
- Billing Note total เป็น snapshot; ไม่ refresh ตาม Payment ที่เกิดทีหลัง
- PR conversion hardcode THB/no_tax; อย่าอธิบายว่ารองรับ multi-currency ครบทุกเส้นทาง
- คำนวณเงินด้วย float และ round ใน controller; ต้องอ่าน GL/FX tests ก่อนแก้ rounding
