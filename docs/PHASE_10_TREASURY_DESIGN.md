# Phase 10: Treasury, Banking & Cash Management Design

สถานะ: Done

เป้าหมาย: ให้รายการรับและจ่ายเงินอ้างอิงแหล่งเงินจริง กระทบยอดกับ statement ได้ และจัดการเงินสดย่อย/เช็คล่วงหน้าได้โดยไม่ทำลายข้อมูลการเงินเดิม.

## Boundaries

- ทุกตารางธุรกิจต้องมี `org_id`; server derive จาก authenticated user เท่านั้น.
- ยอดเงินใช้ `DECIMAL(18,2)` และ currency รอบแรกคือ `THB`.
- รายการที่ posted, reconciled, cleared หรือ cancelled ห้าม hard delete. การแก้ไขใช้ status transition หรือ reversal ใน Phase 11.
- การกระทบยอดใน Phase 10 เป็น treasury state เท่านั้น ยังไม่ post journal entry. Phase 11 จะเป็นเจ้าของ double-entry posting.

## 1. Bank Accounts

ตาราง `bank_accounts`: `id`, `org_id`, `branch_id nullable`, `bank_name`, `bank_code nullable`, `branch_name nullable`, `account_name`, `account_number_masked`, `account_number_encrypted`, `account_type`, `currency`, `is_cash_account`, `is_active`, `opening_balance`, `opening_balance_date`, `created_by`, `updated_by`, timestamps, soft delete.

- Unique: `org_id + account_number_encrypted` (ผ่าน deterministic hash สำหรับตรวจซ้ำ; ไม่ expose ค่าเต็มใน Inertia props/audit logs).
- `account_type`: `savings`, `current`, `cash`, `petty_cash`.
- Payment receipt/reversal และ expense payment จะเก็บ `bank_account_id nullable`; หากไม่มี คือ legacy/manual record.
- ปิดบัญชีได้เมื่อไม่มี unreconciled statement line หรือ active cheque/PDC ค้างอยู่.

## 2. Statements and Reconciliation

`bank_statements` เก็บ header: `bank_account_id`, `statement_date_from/to`, `opening_balance`, `closing_balance`, `source_file_id nullable`, `imported_at/by`, `status`.

`bank_statement_lines` เก็บ immutable imported line: `statement_id`, `transaction_date`, `amount_signed`, `balance_after nullable`, `description`, `reference_no`, `external_transaction_id nullable`, `row_fingerprint`, `status`.

- Unique `bank_account_id + row_fingerprint` กัน import ซ้ำ.
- Matching หา candidate จาก bank account, amount, direction, reference และ window วันทำการ `+/- 3 วัน`.
- Auto-match ได้เฉพาะ candidate เดียวและ score สูงพอ. ถ้ามีหลาย candidate ต้อง manual match.
- `reconciliations` เก็บ `statement_line_id`, `reconcilable_type/id`, `match_method` (`auto`, `manual`), `matched_by/at`, `unmatched_by/at`, `note`.
- Manual unmatch ต้องเก็บ audit trail และห้าม unmatch จาก statement ที่ closed.

## 3. Petty Cash

`petty_cash_funds`: เงินตั้งต้นต่อ custodian/branch, `fund_no`, `bank_account_id nullable`, `custodian_user_id`, `imprest_amount`, `status`.

`petty_cash_requests`: คำขอเบิก, `fund_id`, `request_no`, `requester_id`, `amount`, `expense_date`, `purpose`, `status` (`draft`, `submitted`, `approved`, `paid`, `rejected`, `cancelled`), approval and payment timestamps/users.

`petty_cash_reimbursements`: เติมเงินกองทุนหลังรวม request ที่ paid, อ้าง payment/bank account ได้.

- ผู้ขออนุมัติ request ของตัวเองไม่ได้.
- ยอด paid และ reimbursement ต้องคำนวณจาก transaction records; ไม่เก็บ cash balance ที่แก้มือได้.

## 4. Cheque / PDC

`cheques`: `org_id`, `bank_account_id nullable`, `direction` (`received`, `issued`), `cheque_no`, `bank_name`, `drawer_or_payee`, `amount`, `issue_date`, `due_date`, `status`, source polymorphic fields, clear/bounce/cancel metadata.

- Status: `registered`, `deposited`, `cleared`, `bounced`, `cancelled`.
- `cleared` ต้องอ้าง bank statement line; `bounced` และ `cancelled` ต้องมี reason.
- Unique cheque number ต่อ `org_id + bank_name + direction`; duplicate ที่ต่างธนาคารต้องเป็นไปได้.

## 5. Voucher Proof Attachments

PV/RV ใช้ table `files` เดิมและ `vouchers.attachment_file_id` เป็น canonical parent link.

- รับเฉพาะ `pdf`, `jpg`, `jpeg`, `png`, `webp`, ขนาดไม่เกิน 5 MB.
- `storage_key` สร้างใน server ใต้ `tenants/{org_id}/...`; client ส่ง path เองไม่ได้.
- Upload ต้องมี `vouchers.update`, password confirmation และ throttle.
- Download ตรวจทั้ง `org_id`, `vouchers.view`, และยืนยันว่า `attachment_file_id` ของ voucher ตรงกับ file ที่ขอ.
- บันทึก `file.upload` และ `file.download` audit log โดยไม่บันทึก binary หรือชื่อ path ที่ client ควบคุม.

## Permissions and Delivery Order

เพิ่ม permission ใหม่เป็นชุด: `treasury.accounts.*`, `treasury.reconciliation.*`, `petty_cash.*`, `cheques.*`. Owner/Admin ได้ทั้งหมด; Finance ได้ operational permissions; บทบาทอื่น read-only เฉพาะที่จำเป็น.

ลำดับ implementation:

1. Voucher proof attachment เพื่อปิดช่องว่างจาก Phase 9
2. Bank Accounts master และผูก receipt/expense payment
3. Statement import/reconciliation
4. Petty cash
5. Cheque/PDC
6. Treasury reports และ full regression

## Test Matrix

- Bank account org isolation, duplicate guard และ deactivation guard
- Statement import duplicate fingerprint และ matching ambiguity
- Manual match/unmatch audit trail และ closed statement guard
- Petty cash self-approval guard และ fund amount integrity
- Cheque transitions, due date, bounced/cancelled reason
- Voucher upload MIME/size validation, canonical parent download guard, permission guard และ org isolation
