# GPT Decision Log (Clean)

Last updated: 2026-08-25 (Gemini Phase 9 Reconciled)

Purpose: เก็บเฉพาะ reference ที่ยังมีผลต่อการพัฒนาต่อ, decision สำคัญ, security guardrails, สถานะล่าสุด และแผนงานพัฒนา เพื่อให้ LLM ทุกตัว (GPT, Gemini, Claude) มี context ที่ตรงกัน 100%. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `gemini.md`, `README.md`, และ git diff.

---

## 1. Current Status

- Phase 1-9: completed / closed. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `README.md`, `gemini.md`, และ Git history.
- Latest full validation: **195 tests / 1625 assertions passed**.

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

## 3. Gemini Reconciliation Status

- Phase 9 PV/RV wording reconciled with Gemini: current scope is record, audit, print/PDF, and source-document readiness.
- Voucher attachment upload/download is accepted as Phase 10 backlog, not a Phase 9 reopen.
- Keep Phase 10 acceptance strict: file upload validation, server-generated `storage_key`, parent permission download guard, org isolation, audit log, and feature tests.
