# 06 — Invoice, รับชำระ, Expense และจ่ายเจ้าหนี้

อ่านโค้ด ณ 2026-10-24 · HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` พร้อม working tree ขณะสำรวจ
เป็น static reference; ไม่ได้รัน tests หรือเชื่อมธนาคาร/gateway จริง

## ขอบเขตและจุดเข้า

Invoice เป็นลูกหนี้; Payment เป็น ledger รับชำระและ reversal
Expense เป็นค่าใช้จ่าย/เจ้าหนี้; VendorPayment เป็น ledger จ่ายและ reversal
แยกจาก Voucher ซึ่งเป็นเอกสาร commercial และจาก Bank Statement ซึ่งเป็นการกระทบยอด

| ส่วน | Controller | Frontend |
|---|---|---|
| Invoice | [InvoiceController](../../backend/app/Http/Controllers/Finance/InvoiceController.php) | [Invoices.tsx](../../backend/resources/js/Pages/Finance/Invoices.tsx) |
| รับชำระ | [PaymentController](../../backend/app/Http/Controllers/Finance/PaymentController.php) | [Payments.tsx](../../backend/resources/js/Pages/Finance/Payments.tsx) และ dialog ใน Invoices |
| Expense | [ExpenseController](../../backend/app/Http/Controllers/Finance/ExpenseController.php) | [Expenses.tsx](../../backend/resources/js/Pages/Finance/Expenses.tsx) |
| จ่ายเจ้าหนี้ | [VendorPaymentController](../../backend/app/Http/Controllers/Finance/VendorPaymentController.php) | [VendorPayments.tsx](../../backend/resources/js/Pages/Finance/VendorPayments.tsx) |

## Routes / Permissions

อ้างอิง [web.php](../../backend/routes/web.php); mutation หลักใช้ password.confirm และ throttle:10,1
POST สร้าง Invoice/Expense ใช้ EnsureFormSubmission เพิ่ม; อย่าสับสนกับ idempotency ของ Payment

| HTTP / URL | Method | Permission |
|---|---|---|
| GET `/invoices` | Invoice::index | `invoices.view` |
| POST `/invoices` | Invoice::store | `invoices.create` |
| PATCH `/invoices/{invoice}` | Invoice::update | `invoices.update` |
| PATCH `/invoices/{invoice}/void` | Invoice::void | `invoices.void` |
| GET `/invoices/{invoice}/print` หรือ `/pdf` | print / pdf | `invoices.view` |
| GET `/payments` | Payment::index | `payments.view` |
| POST `/invoices/{invoice}/payments` | Payment::store | `payments.create` |
| POST `/payments/{payment}/reverse` | Payment::reverse | `payments.reverse` |
| GET `/expenses` | Expense::index | `expenses.view` |
| POST `/expenses` | Expense::store | `expenses.create` |
| PATCH `/expenses/{expense}` | Expense::update | `expenses.update` |
| POST `/expenses/{expense}/approve` | Expense::approve | `expenses.approve` |
| POST `/expenses/{expense}/pay` | Expense::pay | `expenses.pay` |
| POST `/expenses/{expense}/reject` | Expense::reject | `expenses.reject` |
| GET `/expenses/{expense}/withholding-certificate` | withholdingCertificate | `expenses.view` |
| POST `/expenses/{expense}/vendor-payments` | VendorPayment::store | `expenses.pay` |
| POST `/vendor-payments/{vendorPayment}/reverse` | VendorPayment::reverse | `expenses.pay` |
| GET `/vendor-payments` | VendorPayment::index | `expenses.view` |

ชื่อ Invoice/Payment/Expense ในตารางหมายถึง controller ไม่ใช่ Model method
Controller ตรวจ org_id ของ record; หน้าการเงินใช้ข้อมูลในองค์กร ไม่ได้ใช้ SalesAccess owner scope ทุกจุด

## Entities และ schema

- [Invoice](../../backend/app/Models/Invoice.php): customer, branch, optional deal/project/quotation, items, payments
- Invoice totals: subtotal, discount_amount, tax_amount, total, paid_amount, balance_due และ base_* สำหรับสกุลฐาน
- [Payment](../../backend/app/Models/Payment.php): invoice_id, receipt/reversal, reversal target, bank account, amount/date/method, idempotency key, attachment และ FX
- [Expense](../../backend/app/Models/Expense.php): supplier/project/PO/bank/file optional; category, amount, tax, withholding, payable_total, paid/balance และ base values
- [VendorPayment](../../backend/app/Models/VendorPayment.php): expense_id, payment/reversal, amount/base_amount/expense_base_amount และ idempotency key
- [Invoice migration](../../backend/database/migrations/2026_07_27_000003_create_finance_invoices_tables.php), [Payment migration](../../backend/database/migrations/2026_07_28_000001_create_finance_payments_table.php), [Expense migration](../../backend/database/migrations/2026_07_28_000003_create_finance_expenses_table.php)
- [AP/FX migration](../../backend/database/migrations/2026_08_28_000004_create_phase141_ap_fcd_and_inventory_fx_tables.php): VendorPayment unique `(org_id,idempotency_key)` และ unique reversal target; เพิ่ม payable/paid/balance fields

## Invoice workflow

อ้างอิง InvoiceController::store/update/void/validateInvoice/invoicePayload

1. สร้างสถานะ draft หรือ sent; ต้องมี Customer และ items; ตรวจ Deal/Project ว่าเป็นของ Customer ที่เลือก
2. คำนวณยอดจากรายการและ tax_mode ฝั่ง server; หมายเลขจาก NumberSequenceService และ FX snapshot ตามวันเอกสาร
3. store/update เรียก FinancialJournalService::postInvoice เมื่อผลลัพธ์เป็น sent; draft ยังไม่ post Invoice journal; แก้ได้เฉพาะ draft
4. UI `canEditInvoice` ใน Invoices.tsx ตรวจ draft เช่นเดียวกัน
5. void ปฏิเสธเมื่อ paid_amount > 0 หรือ void แล้ว; reverse journal ตาม service และเปลี่ยน status
6. สถานะ Model: draft, sent, partially_paid, paid, overdue, void
7. Quotation conversion เป็นอีกทางสร้าง Invoice draft; CN/DN เปลี่ยน totals/balance ผ่าน CommercialDocumentController

## รับชำระและ reversal

อ้างอิง PaymentController::store/reverse/lockInvoice/recalculateInvoicePaymentState

- amount ต้อง ≥ 0.01; ตรวจ payment_method allowlist และ bank account active ในองค์กร
- รับ idempotency key จาก request/header ตาม helper; existing key ต้องตรง payload ที่ helper ตรวจ
- store ตรวจ key ก่อนและหลัง lock Invoice ใน DB transaction
- ห้าม Invoice void และห้ามรับเกิน balance_due; bank currency ต้องตรง invoice currency
- รับชำระไม่ได้ตรวจเฉพาะ sent: guard ที่อ่านปฏิเสธ void และ overpayment เป็นหลัก
- สร้าง receipt แล้ว recalculate จาก net payments; balance=0 → paid
- balance > 0 และเลย due date → overdue; มิฉะนั้น netPaid>0 → partially_paid; ไม่มียอด → sent
- FX receipt ใช้อัตราวันจ่าย; invoice_base_amount แบ่งจาก base balance ของ Invoice เพื่อแยกผลต่าง FX
- แนบ pdf/jpg/jpeg/png/webp ผ่าน FileAttachmentManager
- reverse รับเฉพาะ receipt; receipt ที่มี reversal แล้วทำซ้ำไม่ได้
- reversal เป็น record ใหม่อ้าง receipt เดิม แล้ว recalculation และ reverse posting; ไม่ลบประวัติรับเงิน

## Expense และ VendorPayment workflow

อ้างอิง ExpenseController::store/update/approve/pay/reject และ VendorPaymentController::store/reverse

- Expense ใหม่เป็น draft; แก้ได้ draft เท่านั้น; supplier ไม่บังคับ
- draft → approved พร้อม journal approval; draft/approved → rejected โดยต้องมี note
- reject approved เรียก reverseExpenseApproval; partially_paid/paid ไม่ผ่าน guard reject นี้
- สถานะ Model: draft, approved, partially_paid, paid, rejected
- `ExpenseController::pay` จ่ายยอดคงเหลือทั้งหมดจาก approved/partially_paid; สร้าง VendorPayment แล้วตั้ง paid/balance=0
- `VendorPaymentController::store` รองรับจ่ายบางส่วน: lock Expense, ตรวจ key, ต้อง approved/partially_paid และห้ามเกิน balance
- store เส้นทางนี้ตรวจ bank currency ให้ตรง Expense; สร้าง payment/FX แล้ว recalculate state
- reverse ใช้ payment ต้นทางที่ยังไม่กลับรายการ; lock payment/expense แล้วสร้าง reversal และคำนวณยอดใหม่
- payable/base payable แยกจาก amount; จึงไม่ควรสรุปยอดจ่ายเจ้าหนี้ด้วย sum(amount) ของ Expense อย่างเดียว
- withholdingCertificate ต้องมี withholding_tax_amount > 0 มิฉะนั้น 404

## Dependencies

[FinancialJournalService](../../backend/app/Services/FinancialJournalService.php) เชื่อม AR/AP, cash/bank และ realized FX ตาม source operation
postInvoice debit AR `1120`, credit revenue `4100` และ VAT `2130`; postExpenseApproval debit expense `5200`/VAT `1130`, credit AP `2110`
Payment/VendorPayment ใช้ bank mapping หรือ fallback cash `1100`; realized FX ใช้ gain `4300` หรือ loss `5400`
[FxRateService](../../backend/app/Services/FxRateService.php) เก็บ snapshot; ไม่ควรคำนวณรายการเก่าด้วย rate ปัจจุบัน
Expense project_id ใช้รวม actual cost ใน ProjectController; Invoice project_id ใช้รายได้ตามโครงการ
GatewaySettlementService เป็นทางชำระอีกเส้นทาง; ดูเอกสาร integrations/gateway และ tests ก่อนแก้ logic ร่วม

## Tests ที่อ่าน — ไม่ได้รัน

[Phase3PaymentsTest](../../backend/tests/Feature/Phase3PaymentsTest.php): partial/full/overdue, overpay, idempotency payload mismatch, reverse once, unique reversal, concurrency case และ reverse permission
[Phase3ExpensesTest](../../backend/tests/Feature/Phase3ExpensesTest.php): draft creation/update, approve/pay/reject, reason และ org/permission scope
[Phase3InvoicesTest](../../backend/tests/Feature/Phase3InvoicesTest.php), [Phase3OverdueInvoicesTest](../../backend/tests/Feature/Phase3OverdueInvoicesTest.php), [Phase141ApInventoryFxTest](../../backend/tests/Feature/Phase141ApInventoryFxTest.php) เป็นชุดอ้างอิงต่อ

## ข้อควรตรวจต่อ

- ExpenseController::pay กับ VendorPaymentController::store เป็นสอง implementation; pay ที่อ่านไม่มี lockForUpdate/key จาก client และไม่มี guard currency แบบ store
- การมี transaction ไม่เท่ากับป้องกัน concurrent mutation ทุกทาง; ต้องทดสอบ engine ฐานข้อมูลจริง
- Invoice void/update guard อ่านก่อน transaction; ไม่สรุปว่าแข่งกับ Payment ปลอดภัยครบทุกกรณี
- มี float/round ในการคำนวณแม้ schema ใช้ decimal; เปลี่ยนสูตรต้องตรวจ GL, FX, CN/DN และ reversal พร้อมกัน
