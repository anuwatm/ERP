# Architecture Decisions: MVP Overrides

เอกสารนี้เป็นข้อกำหนดบังคับสำหรับ implementation MVP และ override ข้อความที่ขัดกันใน `ERP_FEATURE_PLAN.md`, `docs/database/DATABASE.md` และ module documents. เมื่อเริ่ม migration ให้แก้ schema กลางตามเอกสารนี้ก่อน.

## AD-01: MVP ไม่เท่ากับ P0 ทั้งหมด

MVP ใช้ scope จาก [`../MVP_SCOPE.md`](../MVP_SCOPE.md) เท่านั้น. Feature P0 อื่นอยู่ใน V1 backlog หลัง MVP.

## AD-02: Money and dashboard metrics

- ทุก money field ใช้ `DECIMAL(18,2)`; ห้าม `FLOAT`/`DOUBLE`.
- service ฝั่ง server คำนวณ line total, invoice header total, `paid_amount`, `balance_due` และ rounding.
- Dashboard ต้องแยก metric:
  - `Invoiced Revenue`: `sum(invoices.total)` by `issue_date`, status `sent`, `partially_paid`, `paid`, `overdue`; ไม่รวม `void`.
  - `Cash In`: `sum(receipt) - sum(reversal)` by `payment_date`.
  - `Recognized Expense`: `sum(expenses.amount)` by `expense_date`, status `approved`/`paid`.
  - `Cash Out`: `sum(expenses.amount)` by `paid_at`, status `paid`.
  - `Gross Profit`: `Invoiced Revenue - Recognized Expense`.
  - `Net Cash Flow`: `Cash In - Cash Out`.
- MVP ไม่มี opening balance หรือ bank reconciliation จึงห้ามแสดง `Cash Balance` เป็นตัวเลขจริง.

## AD-03: Authentication

### MVP (ล็อก)

- Auth provider โหมด `local` ผ่าน **Laravel Breeze** (password hashing, reset, email verification ในแอป).
- ตาราง `users` เก็บทั้ง profile/org membership และ credential ของ local:
  - `auth_provider` VARCHAR(30) NOT NULL DEFAULT `local`
  - `auth_provider_user_id` VARCHAR(255) NULL — ใช้เมื่อ provider ภายนอก; local ใช้ `users.id` / email เป็นตัวอ้างอิงได้
  - `password` VARCHAR(255) NULL — **hashed เท่านั้น**; บังคับมีค่าเมื่อ `auth_provider=local` และ status active
- Login local: ตรวจ email + password + `status=active` + `org` active แล้วสร้าง session
- Invite: สร้าง user `status=invited`, ส่ง signed link; ตอน accept ตั้งรหัสผ่านแล้วเปลี่ยนเป็น `active`
- ห้ามเก็บ password แบบ plaintext; ห้ามส่ง `password` / `remember_token` ออก Inertia props

### เป้าหลัง MVP (production privileged)

- รองรับ external OIDC/SSO + MFA
- เมื่อใช้ external อย่างเดียว: `password` เป็น null ได้, ผูกด้วย `auth_provider_user_id`
- Login ตรวจ provider identity ก่อน แล้ว map เข้า `users`

## AD-04: Payment immutability

เพิ่ม columns ใน `payments`:

| Column | Type | Rule |
| --- | --- | --- |
| `entry_type` | VARCHAR(20) | `receipt` หรือ `reversal`, default `receipt` |
| `reversal_of_payment_id` | UUID FK payments NULL | reversal อ้าง receipt เดิม |

- `amount > 0`; receipt และ reversal เก็บ amount เป็นบวก.
- ยอดสุทธิ invoice = `sum(receipt) - sum(reversal)`.
- MariaDB/MySQL ไม่รองรับ PostgreSQL-style partial unique index. ให้ enforce receipt หนึ่ง reverse ได้ครั้งเดียวด้วย generated column เช่น `reversal_target_id = IF(entry_type = 'reversal', reversal_of_payment_id, NULL)` แล้วทำ `UNIQUE(org_id, reversal_target_id)`, หรือใช้ transaction lock + app validation ถ้า migration ทำ generated column ไม่ได้.
- V1 ปฏิเสธ overpay. receipt ต้องไม่เกิน `balance_due` ภายใต้ transaction lock ของ invoice.
- payment ที่ post แล้วแก้หรือลบไม่ได้. การยกเลิกสร้าง reversal entry พร้อม audit log.
- reversal ใช้ `payment_date = CURRENT_DATE` ของวันที่ทำรายการ reversal จริง เพื่อไม่แก้งวดเดิมย้อนหลัง; Dashboard คำนวณ Cash In สุทธิตาม `payment_date` ของแต่ละ entry.
- สร้าง receipt/reversal ต้อง recalculate `invoices.paid_amount`, `balance_due`, status ใน DB transaction เดียว.
- invoice ที่มี payment ต้อง reverse payment ครบก่อนจึง void ได้.
- ถ้า invoice ที่ผูก `deal_id` ถูก void และไม่มี invoice active อื่นใต้ deal เดียวกัน ให้แสดง derived flag `needs_sales_review` บน deal/invoice detail; ห้าม auto-reopen deal ใน MVP.

## AD-05: Project cost

- ลบ `projects.actual_cost`; เป็น derived value เท่านั้น.
- Project cost = `sum(expenses.amount)` ของ project เดียวกันที่ status `approved` หรือ `paid`, excluding soft-deleted expense.
- ทุก project page, dashboard และ report ใช้ aggregate query/shared query เดียวกัน.

## AD-06: Polymorphic relations

ใช้ `entity_type` + `entity_id` ได้เฉพาะ `activities`, `files`, `notifications`, `audit_logs`.

- `entity_type` ต้องอยู่ allowlist ของ module.
- target entity ต้องอยู่ org เดียวกันและไม่ถูก soft delete.
- ทุก query มี index `(org_id, entity_type, entity_id)`.
- scheduled cleanup/reconciliation ตรวจ orphan reference หลัง delete/retention job.

## AD-07: Organization hierarchy

MVP ใช้โครงสร้างบริษัทภายใน org เดียว:

```text
organizations -> branches -> divisions -> departments -> users
```

- Register ต้องสร้าง default head office branch, default division และ default department ให้ owner.
- `users` มี `org_id`, `branch_id`, `division_id`, `department_id`.
- `divisions.branch_id` ต้องอยู่ใน org เดียวกัน.
- `departments.division_id` และ `departments.branch_id` ต้องอยู่ใน org/branch เดียวกัน.
- user อยู่ได้ 1 branch / 1 division / 1 department ใน MVP.
- ทุกการ assign/move user ต้อง validate hierarchy และเขียน audit log.

## Implementation Order

1. Apply AD-03, AD-06, AD-07, AD-09 schema + auth/RBAC/tenant scope.
2. Apply AD-04 invoice/payment transaction and audit tests.
3. Apply AD-08 payment/expense attachment flow before finance upload.
4. Apply AD-05 expense aggregate.
5. Apply AD-02 dashboard queries and metric labels.
6. Run end-to-end flow defined in [`../MVP_SCOPE.md`](../MVP_SCOPE.md).



## AD-08: MVP file attachments

MVP รองรับไฟล์แนบเฉพาะ payment/expense attachment แบบจำกัด.

- `payments.attachment_file_id` และ `expenses.receipt_file_id` เป็น canonical FK.
- `files.entity_type/entity_id` ใช้เป็น reverse lookup และต้อง sync ให้ชี้ parent เดียวกันหลัง parent create สำเร็จ.
- Upload flow: upload file -> create payment/expense with FK -> update file reverse lookup -> audit.
- Download ต้องตรวจ permission จาก parent entity ทุกครั้ง.
- Task/customer/project generic files = Post-MVP.

## AD-09: Tenant scope implementation

MVP ต้องใช้ Laravel policy + shared query scope/trait สำหรับทุก business model ที่มี `org_id`.

- Default query ต้อง filter `org_id` จาก authenticated session/context.
- การ bypass scope ใช้ได้เฉพาะ system job ที่ระบุชัดและต้อง audit/log.
- Child tables ที่ query ได้โดยตรงควรมี `org_id` เพื่อป้องกัน tenant leakage.
