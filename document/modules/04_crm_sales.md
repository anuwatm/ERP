# 04 — CRM และ Sales

อ่านโค้ด ณ 2026-10-24 · HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` พร้อม working tree ขณะสำรวจ
เอกสารนี้เป็น static reference; ไม่ได้รันระบบหรือ tests เพื่อยืนยันพฤติกรรมจริง

## ขอบเขต

เก็บ Customer, Contact, Deal และ Activity สำหรับติดตามโอกาสขาย
Customer ใช้ `customer_type` แทน lead แยกตาราง; Deal เป็น pipeline ที่เชื่อมใบเสนอราคา ใบแจ้งหนี้ และ Project
จุดเข้าหน้าจอคือ [Customers.tsx](../../backend/resources/js/Pages/Sales/Customers.tsx) และ [Deals.tsx](../../backend/resources/js/Pages/Sales/Deals.tsx)
ฟอร์มเขียนข้อมูลผ่าน Inertia ไป Laravel; dialog ลูกอยู่ใน `Sales/CustomerDialog`, `ContactDialog`, `DealDialog`

## Routes และสิทธิ์

อ้างอิง [web.php](../../backend/routes/web.php) และ controller methods ด้านล่าง
เส้นทางภายในอยู่ในกลุ่ม authentication; mutation หลักใช้ `password.confirm` และ `throttle:10,1`
POST สร้าง customer/contact/deal ใช้ `EnsureFormSubmission` เพิ่มด้วย

| HTTP / URL | Method | Permission |
|---|---|---|
| GET `/customers` | CustomerController::index | `customers.view` |
| POST `/customers` | CustomerController::store | `customers.create` |
| PATCH `/customers/{customer}` | CustomerController::update | `customers.update` |
| DELETE `/customers/{customer}` | CustomerController::destroy | `customers.delete` |
| POST `/customers/{customer}/contacts` | ContactController::store | `contacts.create` |
| PATCH `/contacts/{contact}` | ContactController::update | `contacts.update` |
| DELETE `/contacts/{contact}` | ContactController::destroy | `contacts.delete` |
| GET `/deals` | DealController::index | `deals.view` |
| POST `/deals` | DealController::store | `deals.create` |
| PATCH `/deals/{deal}` | DealController::update | `deals.update` |

Permission เพียงอย่างเดียวไม่ให้เห็นทุก record: [SalesAccess::scopeCustomers / scopeDeals / assertCustomerVisible / assertDealVisible](../../backend/app/Support/SalesAccess.php) จำกัด `org_id` เสมอ
เฉพาะ role code `owner` หรือ `admin` เห็นทุกรายการในองค์กร; คนอื่นเห็นเฉพาะ `owner_id` ของตน
กรณีต่างองค์กรตอบ 404; record ในองค์กรแต่คนละ owner ตอบ 403

## ข้อมูลและความสัมพันธ์

- [Customer](../../backend/app/Models/Customer.php): รหัสลูกค้า ชื่อบริษัท ภาษี ที่อยู่ ช่องทางติดต่อ owner; มี contacts, deals, invoices
- [Contact](../../backend/app/Models/Contact.php): อยู่ใต้ Customer; มี name, position, phone, email, line_id, is_primary
- [Deal](../../backend/app/Models/Deal.php): belongsTo customer/contact/owner; hasMany quotations/invoices/activities; hasOne project
- [Activity](../../backend/app/Models/Activity.php): ใช้ `entity_type` + `entity_id` และเวลา activity/follow-up/completion
- [migration CRM](../../backend/database/migrations/2026_07_26_000002_create_crm_sales_tables.php): UUID, `org_id`, unique `(org_id, customer_code)`; Customer/Contact/Deal มี soft delete
- เงิน Deal เก็บ decimal สองตำแหน่ง; การแสดงผลหลายสกุลเงินต้องคง currency ไม่รวมยอดข้าม currency ตรง ๆ

## Workflow Customer และ Contact

1. [CustomerController::store](../../backend/app/Http/Controllers/Sales/CustomerController.php) ตรวจฟอร์ม สร้างรหัสผ่าน NumberSequenceService และบันทึก audit
2. `customer_type` รับ `lead`, `prospect`, `active`, `inactive`; `status` รับ `active`, `inactive` เป็นคนละ field
3. ผู้ใช้ทั่วไปถูกกำหนด owner เป็นตัวเอง; owner/admin เลือก owner ในองค์กรได้
4. `update` ใช้ ownership guard เดียวกับหน้า list
5. `destroy` ปฏิเสธเมื่อมี Deal หรือ Invoice; ไม่ใช่การลบลูกค้าแล้วลบธุรกรรมทั้งหมด
6. [ContactController::store / update](../../backend/app/Http/Controllers/Sales/ContactController.php) ใช้ transaction เมื่อกำหนด primary และล้าง primary ของ contact อื่นใน customer เดียวกัน
7. `destroy` contact ปฏิเสธเมื่อมี Deal อ้างอิง

## Workflow Deal

อ้างอิง [DealController::validateDeal / applyStageRules / assertContactBelongsToCustomer](../../backend/app/Http/Controllers/Sales/DealController.php)

`new → contacted → qualified → proposal → negotiation → won / lost` เป็นลำดับทางธุรกิจที่ UI ใช้งานได้
Controller ตรวจเพียง stage อยู่ใน allowlist; ไม่บังคับว่าต้องไล่ทุกขั้นหรือห้ามย้อน stage

- `lost` ต้องมี lost_reason; ตั้ง lost_at และล้าง won_at
- `won` ตั้ง won_at; ล้าง lost_at/lost_reason
- กลับ stage เปิด: ล้าง won_at/lost_at/lost_reason
- หากคงสถานะ won/lost จะคง timestamp เดิมผ่าน null-coalescing
- customer/contact ต้องอยู่ในองค์กร และ Contact ต้องเป็นของ Customer ที่เลือก
- amount ≥ 0; probability อยู่ 0–100; currency ยาว 3 ตัว
- owner/admin เลือก owner ได้; ผู้ใช้ทั่วไปใช้ตนเอง
- `index` กรอง stage, owner, currency, customer, deal, created/won/lost date และ open_only ได้

`attachNeedsSalesReview` ตั้ง `needs_sales_review=true` เมื่อไม่มี Invoice สถานะ sent/partially_paid/paid/overdue และ Invoice ล่าสุดของ Deal เป็น void
จึงเป็น flag ขอให้ฝ่ายขายทบทวน ไม่ได้เปลี่ยน stage Deal อัตโนมัติ

## Activity และการเชื่อมโมดูล

[ActivityController::store / update / complete](../../backend/app/Http/Controllers/Sales/ActivityController.php) ตรวจว่า entity มองเห็นได้ก่อนเขียน
`update` ตรวจทั้ง entity เดิมและใหม่; `complete` ตั้ง completed_at
Activity owner ไม่ใช่สิทธิ์ข้าม ownership ของ Customer/Deal

- Deal → Quotation/Invoice: ใช้ customer chain เดียวกัน; ดู [05](05_quotations_commercial_documents.md), [06](06_billing_payments_expenses.md)
- Deal won → Project: ProjectController::storeFromDeal สร้างได้ครั้งเดียวตามเงื่อนไขปกติ; ดู [08](08_projects_tasks.md)
- ปุ่มสร้าง Invoice/Project ใน Deal ใช้ flag จาก permission ทั้ง create และ view
- AuditLog บันทึก create/update/delete ใน controller; ไม่ใช่ event store สำหรับ replay ทุก mutation

## Tests ที่อ่าน — ไม่ได้รัน

[Phase2SalesTest](../../backend/tests/Feature/Phase2SalesTest.php) มีกรณีสร้างรหัสลูกค้า, owner scope, primary contact, contact/customer chain, lost reason และห้ามลบลูกค้าที่มี invoice
มี `test_customer_view_permission_does_not_grant_dialog_writes` และ `test_sales_followups_require_current_record_ownership`
[Phase3DealInvoiceReviewTest](../../backend/tests/Feature/Phase3DealInvoiceReviewTest.php) เป็นจุดอ่านต่อของ flag ทบทวนฝ่ายขาย
[Phase231SalesDialogsTest](../../backend/tests/Feature/Phase231SalesDialogsTest.php) เป็นชุดตรวจ dialog ภายหลัง

## ข้อจำกัด / จุดที่ต้องตรวจต่อ

- Route ของ Deal ที่สำรวจไม่มี DELETE; อย่าอนุมานจาก SoftDeletes ว่ามีปุ่มลบจริง
- การ scope CRM ต่างจาก Finance บางหน้า: QuotationController อ่าน Customer/Deal ทั้งองค์กร ไม่ใช้ SalesAccess
- primary contact ใช้ transaction แต่ migration CRM ที่อ่านไม่แสดง unique constraint แบบ partial สำหรับ is_primary; ต้องทดสอบ concurrency แยกหากเป็นข้อกำหนด
- ไม่ได้ตรวจ browser interaction, ประสิทธิภาพข้อมูลจำนวนมาก หรือฐานข้อมูลที่ deploy จริง
