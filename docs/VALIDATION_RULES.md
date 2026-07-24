# Validation Rules

เอกสารนี้ล็อก validation ฝั่ง server สำหรับ MVP. Client validation ใช้ช่วย UX เท่านั้น แต่ผลตัดสินอยู่ที่ server.

## Global Rules

- ทุก request ใช้ `org_id` จาก authenticated session เท่านั้น ห้ามรับ `org_id` จาก client.
- ทุก UUID ที่รับเข้ามาต้องตรวจว่า record อยู่ใน org เดียวกัน.
- ทุก money ใช้ `DECIMAL(18,2)` และต้อง `>= 0` ยกเว้นระบุเป็นอย่างอื่น.
- ทุก business/display code ใช้ text 6 หลัก regex `^[0-9]{6}$`.
- `id` ที่เป็น primary key ใช้ UUID ไม่ใช่รหัส 6 หลัก.
- date ใช้ timezone `Asia/Bangkok` ตอนแสดงผลและ business rule.
- ห้ามเชื่อ `total`, `paid_amount`, `balance_due`, `status`, `role`, `permission` จาก client.

## Users

| Field | Rule |
| --- | --- |
| email | required, email, unique ภายใน org, lowercase ก่อนบันทึก |
| password | required เมื่อ `auth_provider=local` และ active; hashed only |
| display_name | required, max 255 |
| person_id | nullable, digits only, length 13, validate checksum ถ้าเปิดใช้, store plaintext `CHAR(13)` |
| position | nullable, max 100, ไม่ใช้ตัดสินสิทธิ์ |
| branch_id/division_id/department_id | nullable ได้ แต่ถ้ามีต้องอยู่ใน chain เดียวกันภายใน org |
| status | active, inactive, invited |

## Invite token

- Invite link ต้อง signed, one-time use, TTL 72 ชั่วโมง.
- หมดอายุแล้วต้องออก invite ใหม่และ audit event.
- Accept invite ตั้ง password แล้วเปลี่ยน `status=active`.
- Phase 1 บังคับ `email_verified_at` ก่อนเข้า `/dashboard`; user ที่ยังไม่ verify เข้าได้เฉพาะหน้า verify/resend.

## Organization Hierarchy

| Field | Rule |
| --- | --- |
| branch.code | required, regex `^[0-9]{6}$`, unique `(org_id, code)` |
| division.code | required, regex `^[0-9]{6}$`, unique `(org_id, branch_id, code)` |
| department.code | required, regex `^[0-9]{6}$`, unique `(org_id, division_id, code)` |
| name | required, max 255 |
| status | active, inactive |

### Organization hierarchy guard

- ห้าม inactive/delete branch/division/department ที่ยังมี active users อยู่.
- ห้าม inactive/delete branch/division/department ที่ยังมี active/open projects หรือ unpaid invoices สังกัดอยู่.
- ต้อง re-assign ข้อมูลไปยังโครงสร้างอื่นใน org เดียวกันก่อนปิดใช้งาน.
- Head office branch ห้ามลบ; เปลี่ยนได้เฉพาะข้อมูลทั่วไปตาม permission.

## CRM / Sales

| Field | Rule |
| --- | --- |
| customer_code | required, regex `^[0-9]{6}$`, unique `(org_id, customer_code)` |
| company_name | required, max 255 |
| contact.email | nullable, email |
| deal.value_amount | `DECIMAL(18,2)`, `>= 0` |
| deal.stage | new, contacted, qualified, proposal, negotiation, won, lost |
| deal.customer_id/contact_id | ต้องอยู่ใน org เดียวกัน |

### Deal stage transition

- เมื่อ `stage=won`: server set `won_at=now()`, clear `lost_at/lost_reason`.
- เมื่อ `stage=lost`: `lost_reason` required, server set `lost_at=now()`, clear `won_at`.
- stage ที่ปิดแล้วแก้กลับได้เฉพาะ Owner/Admin หรือ permission เฉพาะ พร้อม audit.

## Finance

| Field | Rule |
| --- | --- |
| product.price/cost | `DECIMAL(18,2)`, `>= 0` |
| invoice_no | required, regex `^[0-9]{6}$`, unique `(org_id, invoice_no)` |
| invoice.customer_id | required, อยู่ใน org เดียวกัน |
| invoice.deal_id | nullable, Phase 3 ใช้ได้, ต้องอยู่ใน org เดียวกัน |
| invoice.project_id | nullable; Phase 4 ค่อยผูก project ได้ |
| invoice.tax_mode | exclusive, inclusive, no_tax; default exclusive |
| invoice_items.quantity | required, `> 0`, decimal 18,4 |
| invoice_items.unit_price | required, `>= 0` |
| invoice_items.discount_amount | `>= 0` และไม่เกิน line subtotal |
| invoice_items.tax_rate | `0-100` |
| invoice.status | draft, sent, partially_paid, paid, overdue, void |
| invoice edit guard | ห้ามแก้ invoice_items/total เมื่อ `paid_amount > 0` จนกว่า reverse payment ครบ |
| needs_sales_review | derived flag: invoice ที่มี `deal_id` ถูก void และไม่มี active invoice อื่นใต้ deal เดียวกัน; ไม่ใช่คอลัมน์ใน MVP |
| payment.amount | required, `> 0`, ต้องไม่ทำให้ net receipt เกิน invoice total |
| payment.entry_type | receipt หรือ reversal |
| payment.reversal_of_payment_id | required เมื่อ `entry_type=reversal`; reverse ได้ครั้งเดียว |
| payment.reference_no | nullable, max 100; ไม่ใช่รหัส 6 หลัก |
| expense_no | required, regex `^[0-9]{6}$`, unique `(org_id, expense_no)` |
| expense.amount | required, `> 0` |
| expense.status | draft, approved, paid, rejected |
| expense.project_id | nullable; Phase 4 ใช้คำนวณ project cost |

## Delivery

| Field | Rule |
| --- | --- |
| project_code | required, regex `^[0-9]{6}$`, unique `(org_id, project_code)` |
| project.customer_id/deal_id | nullable แต่ถ้ามีต้องอยู่ใน org เดียวกัน |
| project.status | planned, active, on_hold, completed, cancelled |
| progress_percent | `0-100` |
| task.project_id | nullable สำหรับ internal task; ถ้ามีต้องอยู่ใน org เดียวกัน |
| task.assignee_id | nullable, ต้องเป็น active user ใน org เดียวกัน |
| task.status | todo, in_progress, review, done, blocked |
| task.priority | low, medium, high, urgent |

## Financial Calculation

- Backend ใช้ rounding แบบ `PHP_ROUND_HALF_UP`.
- คำนวณระดับ line ก่อนรวม header.
- Line subtotal = `quantity * unit_price`.
- `exclusive`: taxable amount = `line subtotal - discount_amount`; line tax = `taxable amount * tax_rate / 100`.
- `inclusive`: line gross = `line subtotal - discount_amount`; line tax = `line gross - (line gross / (1 + tax_rate / 100))`.
- `no_tax`: line tax = 0.
- Header total = sum line totals หลัง rounding.

## File Upload MVP

- ใช้เฉพาะ payment/expense attachment แบบจำกัด.
- ห้าม executable/script.
- จำกัดชนิดไฟล์: pdf, jpg, jpeg, png.
- จำกัดขนาดตาม config.
- ทุก download ต้องตรวจ permission ของ parent entity.
- `storage_key` ต้องสร้างฝั่ง server เท่านั้นและห้ามมี `..`, slash จาก input, หรือ filename เดิมเป็น path.



