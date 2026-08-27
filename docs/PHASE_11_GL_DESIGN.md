# Phase 11: General Ledger & Double-Entry Accounting Design

สถานะ: Done

เป้าหมาย: เพิ่ม General Ledger แบบ double-entry ที่ immutable หลัง post, รองรับ accrual accounting, period lock และเชื่อม source document อย่าง idempotent โดยไม่แก้ข้อมูล operational ของ Phase 1-10.

## Principles

- ทุก ledger record ต้องมี `org_id`; account, period, source document และ journal line ต้องอยู่ในองค์กรเดียวกัน.
- จำนวนเงินเก็บ `DECIMAL(18,2)` ใน `THB` สำหรับ Phase 11. Multi-currency เป็น Phase 14.
- Journal ที่ `posted` ห้ามแก้หรือ hard delete. แก้ไขด้วย reversal journal เท่านั้น.
- Journal จะ post ได้เมื่อผลรวม debit เท่ากับ credit, ทุก account active และ posting date อยู่ใน accounting period ที่ open.
- Source event หนึ่ง post ได้ครั้งเดียวด้วย unique `org_id + source_type + source_id + posting_event`. Reversal เป็น event ใหม่และอ้าง original journal.
- Posting point ต้องเป็น accounting policy: ห้ามให้ controller source document ประกอบ Dr/Cr เอง.

## 1. Chart of Accounts

ตาราง `chart_of_accounts`:

- `id`, `org_id`, `code`, `name`, `account_type`, `normal_balance`, `parent_id nullable`, `is_postable`, `status`, `description`, timestamps.
- `account_type`: `asset`, `liability`, `equity`, `revenue`, `expense`.
- `normal_balance`: `debit` หรือ `credit`; service derive/validate ตาม type.
- unique `org_id + code`; parent ต้องอยู่ org เดียวกัน และ parent account ต้องไม่เป็น postable.
- account ที่มี journal line แล้วปิดได้ แต่ห้าม hard delete และห้าม post ใหม่เมื่อ `inactive`.

Default Thai SME chart จะ seed ต่อ organization: Cash/Bank, Petty Cash, AR, Inventory, Input VAT, AP, Output VAT, Cheque Clearing, Capital, Sales Revenue, COGS และ expense categories หลัก. Seed ต้อง idempotent และไม่ทับ account ที่ผู้ใช้แก้เอง.

## 2. Accounting Periods

ตาราง `accounting_periods`:

- `id`, `org_id`, `name`, `start_date`, `end_date`, `status` (`open`, `closed`), `closed_by`, `closed_at`, timestamps.
- unique `org_id + start_date + end_date`; date range ห้าม overlap ภายใน org.
- period close ต้องตรวจว่าไม่มี draft journal ของ period และ transaction source ที่ต้อง post ค้างอยู่ตาม policy ที่เปิดใช้.
- document guard เรียก `AccountingPeriodService::assertOpenForDate(orgId, date)` ก่อน post, void, reverse หรือแก้ financial state ที่มีผลต่อ GL.

## 3. Journal Storage

ตาราง `journal_entries`:

- `id`, `org_id`, `accounting_period_id`, `entry_no`, `posting_date`, `description`, `status` (`draft`, `posted`, `reversed`), `source_type nullable`, `source_id nullable`, `posting_event nullable`, `reversal_of_id nullable`, `posted_by`, `posted_at`, timestamps.
- unique `org_id + entry_no` และ partial/idempotency guard สำหรับ source event ที่ไม่ null.

ตาราง `journal_lines`:

- `id`, `org_id`, `journal_entry_id`, `chart_of_account_id`, `description nullable`, `debit`, `credit`, `sort_order`, timestamps.
- แต่ละ line มี debit หรือ credit ได้เพียงด้านเดียวและต้องมากกว่า 0; header total debit = total credit enforce ใน service ภายใน DB transaction.
- ห้าม reference account, period หรือ entry ข้าม org.

## 4. Posting Service and Accounting Policy

`JournalPostingService` เป็น owner เดียวของ create/post/reverse. ทุก source controller ส่ง command ที่มี source id และ event เท่านั้น; service resolve accounts ผ่าน policy map ขององค์กร.

Initial recognition policy:

| Source event | Debit | Credit |
| --- | --- | --- |
| Invoice issued | Accounts Receivable | Sales Revenue, Output VAT |
| Payment receipt | Bank/Cash | Accounts Receivable |
| Payment reversal | Accounts Receivable | Bank/Cash |
| Expense approved | Expense/Input VAT | Accounts Payable |
| Expense paid | Accounts Payable | Bank/Cash |
| Goods receipt | Inventory | GRNI / Accounts Payable ตาม policy |
| Credit Note issued | Sales Return/Output VAT | Accounts Receivable |
| Debit Note issued | Accounts Receivable | Revenue/Output VAT |
| Petty cash reimbursement | Petty Cash | Bank/Cash |
| Cheque received/deposited/cleared | Clearing accounts ตาม event | AR/Bank ตาม policy |

Voucher, Bank reconciliation และ Petty Cash request ไม่ post โดยตัวเองหากไม่มี economic event ใหม่; ใช้เป็น evidence/control ของ source event ที่ถูก post แล้ว.

## 5. Reversal and Idempotency

- `post(sourceType, sourceId, postingEvent)` lock source/idempotency key ก่อนสร้าง entry.
- ถ้ามี posted entry สำหรับ key เดิม ให้คืน existing entry; ถ้ามี draft/failed state ให้ resume หรือ fail safely โดยไม่สร้าง duplicate.
- `reverse(entryId, date, reason)` สร้าง journal ใหม่โดยสลับ debit/credit ทุก line, อ้าง `reversal_of_id`, และห้าม reverse ซ้ำ journal เดิม.
- Source cancellation/void หลัง post ต้อง request reversal; ห้าม update posted lines.

## 6. Permissions and UI

Permissions:

- `accounting.chart_accounts.view/manage`
- `accounting.periods.view/manage`
- `accounting.journals.view/create/post/reverse`
- `accounting.reports.view`

UI รอบแรก:

- Chart of Accounts management
- Accounting Period list/open/close
- Journal entry list/detail พร้อม source link และ reversal relation
- Trial Balance, General Ledger และ Account Ledger filters by period/account/date

## 7. Test Contract

- debit total != credit total ต้องไม่สามารถ post ได้.
- cross-org account/period/source ต้องได้ 404 หรือ validation error.
- source event เดิม post ซ้ำต้องคืน entry เดิม ไม่เพิ่ม journal.
- posted entry แก้ line/header ไม่ได้; reverse ได้ครั้งเดียวและยอดกลับด้านถูกต้อง.
- closed period block post/reverse/financial source mutation.
- Trial Balance debit/credit เท่ากัน และ ledger report รวมจาก posted journals เท่านั้น.

## Delivery Sequence

1. Chart of Accounts, periods, journal schema และ seed chart.
2. Posting/reversal/period services พร้อม feature tests.
3. Invoice, payment, expense, CN/DN, inventory, voucher, treasury adapters ตาม policy.
4. UI และ Trial Balance/GL reports.
5. Full regression, accounting scenario fixtures และ documentation update.
