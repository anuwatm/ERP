# GPT Decision Log (Clean)

Last cleaned: 2026-08-01

Purpose: เก็บเฉพาะ reference ที่ยังมีผลต่อการพัฒนาต่อ, decision สำคัญ, deferred scope, security guardrails และสถานะล่าสุด. รายละเอียดงานที่ปิดแล้วให้ดู `checklist.md`, `gemini.md`, `README.md`, และ git diff.

---

## 1. Current Status

- Phase 1: Done
- Phase 1.1: Done
- Phase 2: Done
- Phase 3: Done
- Phase 4: Done
- Phase 5: Done / Gemini review closed
- Phase 6: In progress / dashboard reporting & visual polish mostly done

Latest known validation snapshots:

- Full PHPUnit suite baseline before latest dashboard visual work: 154 tests / 1200 assertions passed
- Dashboard-focused after latest work: `phpunit --filter Dashboard` => 16 tests / 395 assertions passed
- Frontend: `pnpm run check-format`, `pnpm run lint`, `pnpm run build` passed after latest changes
- Pint: passed after latest changes

Local test note:

- Use `C:\AppServ\php8\php.exe` with required extensions when running PHPUnit locally: `fileinfo`, `mbstring`, `openssl`, `intl`, `pdo_sqlite`, `sqlite3`, `pdo_mysql`.

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
- AuditLog must keep central recursive redaction for password/token/secret keys and `person_id`.
- Production invite flow must not flash plain invite token or `invite_url`.
- User disable, role change, and role permission changes must invalidate affected sessions and rotate/clear `remember_token` where implemented.
- Executive Dashboard data requires `executive.dashboard.view` or owner/admin fallback.
- Finance Dashboard requires `expenses.view`.
- Delivery Dashboard is for `projects.view` or `tasks.view`; task-only member sees own task scope and no project financial metrics.

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

Reason: Phase 4 passed with owner/assignee visibility. Adding it affects model, permissions, UI assignment, dashboard scope, tests, and migrations.

### Tax / Invoice Compliance

Deferred:

- Inclusive VAT subtotal display as net subtotal.
- Header discount VAT allocation.
- Credit note / full tax invoice compliance.

Reason: larger accounting compliance scope; impacts formulas, UI preview, historical expectations, tests, and docs.

### Number Format Expansion

Deferred:

- `invoice_no` / `expense_no` from `char(6)` to `varchar(30)`.

Reason: not an MVP blocker. Do when preparing real UAT/demo numbering format.

### Broad Code Optimization / Refactor

Decision: do not do broad dedup/refactor inside closed Phase 5/active dashboard visual slice.

Reason: security-sensitive green baseline. Refactor should be scoped with a risk/test plan.

---

## 4. Dashboard / Reporting Reference

### Dashboard Separation

Implemented:

- `/dashboard` => Admin/System overview only in UI.
- `/executive-dashboard` => Executive metrics only.
- `/finance-dashboard` => Finance metrics only.
- `/delivery-dashboard` => Delivery metrics only.
- `/sales-dashboard` => Sales-only page.

Important note:

- `/dashboard` still keeps backend props backward-compatible for existing tests/callers, but UI hides non-admin sections.
- Date filters submit to the active dashboard route.

### Date Filters

Implemented:

- `all_time`
- `month=YYYY-MM`
- `year=YYYY`
- `custom` via `from=YYYY-MM-DD&to=YYYY-MM-DD`

Applied to:

- Executive metrics
- Finance metrics
- Delivery metrics

Finance trend:

- `financeSummary.previous` exists only when a selected date range has a meaningful previous same-length period.
- `all_time` keeps `previous = null`.

### Visual Dashboard Work

Done:

- Admin/System: Security Alert donut + System Normal state.
- Executive: Sales Conversion, Finance Mix, Delivery Signal cards.
- Finance: Invoice Status donut, Payment Reversal bar, previous-period trend tiles.
- Delivery: Budget Burn bar, Task Load bars, Project Status donut, Risk badges.
- Sales: Pipeline funnel, Won/Lost donut, Top Owner bars, Follow-ups/Stale warning tiles.

Guardrails:

- Charts support exact figures, not replace them.
- Keep exact money/metric numbers visible.
- Reuse current props first; add backend data only when chart needs real data.
- No Cash Balance remains unchanged.

---

## 5. Phase Behavior Reference

### Phase 3 Finance

Important completed behavior:

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

### Phase 4 Delivery

Important completed behavior:

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

### Phase 5 Executive / UAT

Important completed behavior:

- Executive Dashboard aggregates Sales + Finance + Delivery.
- `executive.dashboard.view` permission added.
- Owner/admin fallback can see executive summary.
- No Cash Balance in UI/props/API.
- UAT seed data in `Phase1DemoSeeder`.
- Expected UAT dashboard values documented in `docs/SEED_DATA.md`.
- E2E coverage includes invite/customer/deal/invoice/payment/project/task/dashboard flow, role isolation, multi-role permission union, payment reversal metrics, invoice totals, audit redaction, session invalidation, and production invite token safety.

### Organization Chart

Implemented before Phase 5:

- Organization Structure page includes Organization Chart.
- Uses existing Branch -> Division -> Department -> Users data.
- No new database table/migration.

---

## 6. Operational Notes

Local URL:

- Correct local Apache URL: `http://localhost/ERP/login`.
- `http://localhost/login` returns 404 because app lives under `/ERP`.

When Gemini reviews:

- Gemini should read this file for current decisions only.
- Historical completion logs were intentionally removed to reduce context weight.
- If Gemini suggests broad optimization/refactor, ask for a scoped target and risk/test plan before implementation.
- Current actionable backlog after dashboard visual work: Number format expansion and Tax/Invoice Compliance first pass, unless Gemini adds a new scoped blocker.