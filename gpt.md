# GPT Decision Log (Clean)

Last cleaned: 2026-08-01

Purpose: เก็บเฉพาะ reference ที่ยังมีผลต่อการพัฒนาต่อ, decision สำคัญ, deferred scope, security guardrails และสถานะล่าสุด. รายละเอียด audit ที่แก้จบแล้วให้ดู `gemini.md`, `checklist.md`, `README.md` แทน.

---

## 1. Current Phase Status

- Phase 1: Done
- Phase 1.1: Done
- Phase 2: Done
- Phase 3: Done
- Phase 4: Done
- Phase 5: Done / closed after Gemini review
- Phase 6: In progress

Latest verified baseline:

- Full PHPUnit suite: 154 tests / 1200 assertions passed
- Laravel Pint: passed
- `pnpm run check-format`: passed
- `pnpm run lint`: passed
- `pnpm run build`: passed

Test command note for local Windows:

- Use PHP with required extensions enabled, e.g. `fileinfo`, `mbstring`, `openssl`, `intl`, `pdo_sqlite`, `sqlite3`, `pdo_mysql`.
- Previous working full-suite command used `C:\AppServ\php8\php.exe` with `-d extension_dir=C:\AppServ\php8\ext` and explicit `-d extension=...` flags.

---

## 2. Decisions That Still Matter

### MVP Scope

- No Cash Balance in MVP: no UI widget, no JSON/Inertia prop, no API field.
- No export / notifications / public API in MVP.
- Full tax invoice compliance, credit note, suppliers, PO, inventory: Post-MVP/V2.
- Generic file module for customer/deal/project/task: Post-MVP/V2.
- `project_members`: deferred Post-MVP/V2; MVP uses owner/assignee scope.

### Security / Permission

- Use `User::hasPermissionCode()` as central permission helper.
- Sensitive writes require `password.confirm` where already scoped.
- `person_id`, password, token, secrets must not leak in UI/log/Inertia props.
- AuditLog must keep central recursive redaction:
  - any key containing `password`
  - any key containing `token`
  - any key containing `secret`
  - `person_id` is masked after type-safe string/null casting
- Production invite flow must not flash plain invite token or `invite_url`.
- User disable, role change, and role permission changes must invalidate affected sessions and rotate/clear `remember_token` where implemented.
- Executive Dashboard data is visible only to `executive.dashboard.view` or owner/admin fallback.
- Department dashboards stay permission scoped:
  - Finance Dashboard: `expenses.view`
  - Delivery Dashboard: `projects.view` or `tasks.view`
- Member with only `tasks.view` sees own task scope and no project financial metrics.

### Sales / Invoice

- Void invoice from deal does not auto-reopen or auto-change deal state.
- Use derived `needs_sales_review` flag instead.
- Reason: void invoice can be a finance correction, not necessarily deal cancellation.

### Payments / Finance

- Payment receipt/reversal uses `idempotency_key`.
- No overpay is enforced with transaction + `lockForUpdate()`.
- Reversal amount is stored positive, but reports must subtract by `entry_type = reversal`.
- Do not use raw `SUM(payments.amount)` for cash-in or net cash flow.
- Project actual cost is derived from expenses with status `approved` or `paid` only.

### Projects / Tasks

- No `projects.actual_cost` column.
- Project progress is manual `progress_percent`.
- Project Manager sees projects they own.
- Member sees tasks assigned to them.
- Member can update task `status` only.
- Internal tasks support `project_id = null`.
- `blocked` tasks do not count as overdue in MVP.
- Delivery Risk count uses distinct project IDs, not duplicated risk rows.

---

## 3. Deferred / Not Implemented Now

### `project_members`

Decision: defer Post-MVP/V2.

Reason:

- Phase 4 passed with owner/assignee-only visibility.
- Adding it affects data model, permission matrix, UI assignment, dashboard scope, tests, and migrations.
- Gemini Phase 4 audit marked current model as completed.

### Tax / Invoice Compliance

Deferred:

- Inclusive VAT subtotal display as net subtotal.
- Header discount VAT allocation.
- Credit note / full tax invoice compliance.

Reason:

- Larger accounting compliance scope.
- Impacts backend formula, UI preview, historical expectations, tests, and docs.

### Number Format Expansion

Deferred:

- `invoice_no` / `expense_no` from `char(6)` to `varchar(30)`.

Reason:

- Not an MVP blocker.
- Do when preparing real UAT/demo numbering format.

### Dashboard Date Filters

Deferred:

- Dashboard Date Filters are now implemented for Finance/Delivery/Executive metrics: all-time/month/year/custom range.

### Code Optimization / Refactor

Decision: do not do broad dedup/refactor inside closed Phase 5.

Reason:

- Phase 5 hardening is security-sensitive and green.
- Broad refactor should be Phase 6 cleanup or a dedicated branch with full regression.

---

## 4. Phase Reference Summary

### Phase 3: Finance

Completed and important behavior:

- Products/Services catalog.
- Manual invoice + invoice items.
- Invoice from deal with prefill.
- Server-side invoice totals.
- Payment receipt, partial payment, no overpay, reversal.
- Expenses draft/approve/pay/reject.
- Payment/expense attachments.
- Finance Dashboard.
- Overdue invoice command.
- `needs_sales_review` after void invoice from deal.
- Concurrent payment no-overpay test.

### Phase 4: Delivery

Completed and important behavior:

- Projects + tasks + task_checklists + task_comments.
- Manual project and project from won deal.
- One project per deal.
- Project owner visibility and reassignment guard.
- Internal task with `project_id = null`.
- Member task visibility and status-only update.
- Invoice project link guarded by customer match.
- Expense project link guarded by org.
- Dynamic project cost from approved/paid expenses.
- Delivery Dashboard metrics.
- `project_members` deferred Post-MVP/V2.

### Pre-Phase 5 UX Addition

Organization Structure page includes Organization Chart.

- Uses existing Branch -> Division -> Department -> Users data.
- No new database table/migration.

### Phase 5: Executive Dashboard + E2E/UAT

Completed and important behavior:

- Executive Dashboard aggregates Sales + Finance + Delivery.
- `executive.dashboard.view` permission added.
- Owner/admin fallback can see executive summary.
- No Cash Balance in UI/props/API.
- UAT seed data added in `Phase1DemoSeeder`.
- Expected UAT dashboard values documented in `docs/SEED_DATA.md`.
- E2E coverage added:
  - Invite user -> Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard
  - Role isolation
  - Multi-role UNION permission
  - Payment reversal metrics
  - Invoice totals
  - Dashboard metrics
  - `needs_sales_review` after invoice void
  - No export/notifications/public API scope
  - Audit log redaction and session invalidation
  - Production invite does not flash plain token

Gemini final review:

- Phase 5 marked fully audited/secured in `gemini.md`.
- No new actionable code changes after latest review.

---

## 5. Operational Notes

Local URL:

- Correct local Apache URL: `http://localhost/ERP/login`.
- `http://localhost/login` returns 404 because app lives under `/ERP`.

When Gemini reviews:

- Gemini should read this file for current decisions only.
- Historical full details are intentionally removed to reduce context weight.
- If Gemini suggests broad optimization/refactor, ask for a scoped target and risk/test plan before implementation.
---

## 6. Phase 6 Started: Dashboard Date Filters

Implemented on 2026-08-01:

- Added `dashboardFilters` query handling to `DashboardController`.
- Supported periods:
  - `all_time` default
  - `month` via `month=YYYY-MM`
  - `year` via `year=YYYY`
  - `custom` via `from=YYYY-MM-DD&to=YYYY-MM-DD`
- Applied selected range to Executive, Finance, and Delivery metrics while keeping default all-time behavior unchanged.
- Added Dashboard UI filter bar with Apply/Reset controls.
- Added `Phase6DashboardFiltersTest` covering month filter behavior across sales, finance, delivery, and returned filter props.

Verification:

- Phase 5 + Phase 6 dashboard tests: 11 tests / 299 assertions passed
- Full PHPUnit suite: 154 tests / 1200 assertions passed
- Laravel Pint: passed
- `pnpm run check-format`: passed
- `pnpm run lint`: passed
- `pnpm run build`: passed

Remaining Phase 6:

- Number format expansion: `invoice_no` / `expense_no` from `char(6)` to `varchar(30)`.
- Tax / Invoice Compliance first pass: inclusive VAT display and header discount VAT allocation.