# GPT Decision Log (Clean)

Last updated: 2026-08-23 (Phase 8 In Progress)

Purpose: เก็บเฉพาะ reference ที่ยังมีผลต่อการพัฒนาต่อ, decision สำคัญ, security guardrails, สถานะล่าสุด และแผนงานพัฒนา เพื่อให้ LLM ทุกตัว (GPT, Gemini, Claude) มี context ที่ตรงกัน 100%. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `gemini.md`, `README.md`, และ git diff.

---

## 1. Current Status

- Phase 1-7: completed / closed. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `README.md`, และ `gemini.md`.
- Phase 8: In Progress; production roadmap core slices implemented, purchase-tax source expansion remains once expense/GRN tax source schema exists.
- Phase 8 completed slices:
  - Invoice / Tax Invoice-Receipt / Purchase Order official print views, BahtText, Original/Copy marker, VOID watermark, logo/branch header, org-scope print guards, UI Print buttons, and feature tests.
  - Sales/Purchase Tax Reports first pass with date/branch filters, CSV export, org isolation, and feature tests.
  - AR/AP Aging first pass with 0-30, 31-60, 61-90, >90 buckets and feature tests.
  - WHT report from approved/paid expenses with ภ.ง.ด. 3/53 form support and CSV export.
  - PDF binary export for Invoice/Tax Invoice/Receipt/PO and 50-Tawi WHT certificate PDF.
  - Excel-compatible `.xls` report export for tax/WHT/aging reports.
  - Inventory/GRN from approved PO with partial receive, over-receive guard, stock ledger, adjustment in/out, supplier return, on-hand summary, and average cost.
  - Notification service with queued mail, in-app notification, dedupe guard, PO approval, invoice due/overdue, invite, task/project assignment, preferences, and navbar unread count.
- Latest targeted Phase 8 validation: **21 tests / 223 assertions passed**.
- Latest full validation: **185 tests / 1526 assertions passed**.

---

## 2. Decisions That Still Matter

### Security / Permission Guardrails

- Use `User::hasPermissionCode()` as central permission helper.
- Sensitive writes require `password.confirm` where already scoped.
- `person_id`, password, token, secrets must not leak in UI/log/Inertia props.
- AuditLog must keep central recursive redaction for password/token/secret keys and `person_id`.
- Production invite flow must not flash plain invite token or `invite_url`.
- User disable, role change, and role permission changes must invalidate affected sessions and rotate/clear `remember_token` where implemented.
- Executive Dashboard data requires `executive.dashboard.view` or owner/admin fallback.
- Finance Dashboard requires `expenses.view`.
- Delivery Dashboard is for `projects.view` or `tasks.view`; task-only member sees own task scope and no project financial metrics.
- No Cash Balance in MVP / Post-MVP: no UI widget, no JSON/Inertia prop, no API field.

### Sales / Invoice / Tax Compliance

- Void invoice from deal does not auto-reopen or auto-change deal state; uses derived `needs_sales_review` flag instead.
- Number Sequences support tokens (`{YYYY}`, `{YY}`, `{MM}`, `{DD}`, `{BRANCH}`, `{SEQ:n}`) with scope (`organization` / `branch`) and reset periods (`none`, `yearly`, `monthly`, `daily`).
- `invoice_no` and `expense_no` are `varchar(30)` with database concurrency unique guard.
- Inclusive VAT calculates Gross Subtotal, Net Subtotal, VAT included, and allocates header discount before tax computation. Matches between backend `calculateTotals` and frontend preview.

### Payments / Procurement / Finance

- Payment receipt/reversal uses `idempotency_key`.
- No overpay is enforced with transaction + `lockForUpdate()`.
- Reversal amount is stored positive, but reports subtract by `entry_type = reversal`.
- Do not use raw `SUM(payments.amount)` for cash-in or net cash flow.
- Project actual cost is derived from expenses with status `approved` or `paid` only (no `projects.actual_cost` column).
- Suppliers have CRUD with org scope and unique supplier code.
- Purchase Orders support Create / Update / Approve / Cancel with server-side totals calculation and Expense chain validation.

### Projects / Tasks / Members

- `project_members` supports multi-user assignment with roles and access scope in `ProjectAccess`.
- Project Manager sees projects they own / admin sees all.
- Assigned member sees assigned tasks and updates task `status` only.
- Internal tasks support `project_id = null`.
- `blocked` tasks do not count as overdue in metrics.

---

## 3. Active Phase 8 Design Scope

Implementation order recommendation:

1. **Official Document Print & PDF Export**
   - Done: Invoice, Tax Invoice / Receipt, Purchase Order print/PDF, BahtText, logo/branch header, org guard, 50-Tawi WHT certificate.
   - Remaining: deeper Thai font hardening for production print shops if required.
2. **Tax & Accounting Reports**
   - Done: Sales Tax, Purchase Tax, WHT, CSV/XLS export, AR/AP aging.
   - Remaining: Expenses/GRN purchase tax source expansion once tax source schema exists.
3. **Email Notifications & Background Queues**
   - Done: PO approval, invoice due soon/overdue, user invite mail, task/project member notifications, preferences, dedupe.
4. **Inventory & Goods Receipt (GRN)**
   - Done: Approved PO -> Goods Receipt -> stock movement ledger -> on-hand calculation, adjustment/return movements, average costing.
