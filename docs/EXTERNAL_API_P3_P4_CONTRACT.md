# P3–P4 — Parties / sales / procurement / delivery read contract

เริ่ม 2026-10-19 ผู้ใช้ให้พัฒนาทุกโดเมนต่อและส่ง Gemini ตรวจรวมท้ายรอบ ทุก endpoint ส่วนนี้เป็น read-only

## Principal และ visibility

access_policy.principal = organization (ค่าเริ่มต้น), customer หรือ supplier; party principal ต้องมี party_id ที่อยู่ org และผู้จัดการ key เข้าถึงได้ Customer keys อนุญาตเฉพาะ customers/quotations/invoices/delivery-orders/payments; Supplier keys เฉพาะ suppliers/purchase-orders/goods-receipts ไม่ให้ summary org-wide, HR, inventory, project หรือข้อมูลอีก party แม้ใส่ scope เกินมาด้วย

ทุก request ต้องอยู่ใต้ org จาก key, party จาก server policy และสิทธิ์/row visibility ของผู้สร้าง key ที่ยัง active ไม่รับ party/org เพื่อสวมสิทธิ์จาก client Customer documents ไม่ส่ง draft; supplier purchase orders ไม่ส่ง draft เอกสารยกเลิกที่เคยเปิดเผยยังมี status เพื่อ reconciliation ไม่ตีความ paid/accepted/delivered เป็นสถานะเดียวกัน

## Routes / scopes

GET /api/v1/{resource} และ /{resource}/{id}; resource เป็น fixed allowlist เท่านั้น:

| Resource | Scope | Fields พื้นฐาน |
| --- | --- | --- |
| customers | customers:read | id, customer_code, company_name, customer_type, status, updated_at |
| suppliers | suppliers:read | id, supplier_code, name, status, updated_at |
| quotations | quotations:read | quotation_no/customer_id/status/issue_date/valid_until/currency/totals |
| invoices | invoices:read | invoice_no/customer_id/status/issue_date/due_date/currency/totals/paid_amount/balance_due |
| delivery-orders | delivery-orders:read | do_no/invoice_id/status/delivery_date/delivered_at |
| payments | payments:read | invoice_id/entry_type/reversal_of_payment_id/payment_date/amount/currency |
| purchase-orders | purchase-orders:read | po_no/supplier_id/status/order_date/expected_date/currency/totals |
| goods-receipts | goods-receipts:read | grn_no/purchase_order_id/received_date/status/currency |
| projects | projects:read | project_code/name/status/start_date/due_date/progress_percent |
| tasks | tasks:read | project_id/title/status/priority/due_date/completed_at |

ไม่มี tax ID, private contacts, bank accounts, payment references/credentials, attachments, free-text notes, task comments/assignee, project members/budget/cost; เงินเป็น decimal strings ตาม stored document currency ไม่คำนวณ FX ใหม่

Document detail ส่ง header และ items สูงสุด100 พร้อม items_truncated; GET /{resource}/{id}/items ใช้ pagination เดิมเพื่ออ่านรายการที่เหลือทุกแถวตรวจ parent/org/product relations ไม่ serialize model ทั้งก้อน PO item มี received_quantity/outstanding_quantity decimal4 จาก posted GRN lines เท่านั้น ไม่ใช้สถานะ PO แทน quantity จริง

GET /projects/{id}/tasks ตรวจ parent และ row visibility ของ Task ด้วย ไม่ใช้ summary:projects:read แทน projects:read/tasks:read ไม่มี work hours เพราะไม่มี source schema

GET /summaries/procurement scope summary:procurement:read: period ตาม order_date จัด status/currency/total; snapshot outstanding ordered/received quantities ของ open PO รายสินค้า/หน่วย ไม่บวก quantities ต่างหน่วยเป็นยอดเดียว

List ใช้ limit/cursor/updated_since เดิมและ allowlisted status filter; search เฉพาะ business number/name ไม่เปิด arbitrary relation/include/fields Monetary fields เป็น explicit document scope ไม่พ่วงมากับ basic party read

Tests: mixed-party foreign IDs/parents/lines, draft withholding, role ceiling, revoked/expired key, no secret/PII, decimal precision, partial receipt/reversal, pagination และ GET business read-only
