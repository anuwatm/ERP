# Phase 23.2.5 — Accepted scope and deferred work

Decision 2026-09-18: user accepted closing only the verified dashboard rollout scope. The items below are **deferred**, not implemented or verified, and must not be described as complete.

## Deferred metrics and source contracts

- Active Staff, Attendance Present, Headcount by Department, Attendance Trend: define employment status, employee-to-department relation, schedule/holiday/absence rules before computing.
- Security Alerts: define actionable event source, severity, lifecycle, threshold and deduplication. Inactive users and expired invites remain operational follow-ups, not alerts.
- Gross Profit and Project Margin: reconcile revenue/cost recognition, project scope and currency conversion. Budget Remaining is not profit.
- Cross-currency project Budget/Actual comparison: require a validated base-currency budget and cost contract.
- Remaining chart/list semantic filters not covered by the tested Finance cash-month, AR Aging and Invoice Status links.

## Closed acceptance evidence

- Browser matrix is complete: 60 checks cover all internal dashboards and both Portal roles at desktop/mobile, light/dark, keyboard, filters, drill-down, back/refresh, read-only and permitted actions. See `PHASE_23_2_7_VERIFICATION.md`.
- Performance is complete: the 1,000-record isolated fixture per principal source keeps all 10 routes at <= 50 queries and < 1 second. No dashboard cache exists, so no cache isolation/freshness contract is applicable.
- Full Phase 23.2.7 cross-module exit criteria and regression are complete. The metric/source items above remain deferred implementation work; they are not claims of delivered metrics.

## Verified rollout scope

- Finance, Executive, Sales, Delivery, Admin: source-scoped widgets and filtered lists detailed in `checklist.md` and `gpt.md`.
- My Work: assigned task counts and exact task links. Phase4DeliveryDashboardTest 5 passed / 217 assertions.
- Procurement/Inventory: PO status/open orders, current-user workflow approvals, committed PO value by supplier/currency, recent goods receipts, and Low Stock product+warehouse locations with exact drill-down. DashboardAccessTest checks permission/tenant isolation, PO filters and Low Stock source/destination agreement.
- HR/Payroll: submitted leave, approved leave today and latest payroll run with source permissions. No headcount/attendance number.
- Portal: summary and empty states from portal party-scoped payload; Phase22PortalTest 3 passed / 24 assertions.
- Permission-based landing and no fake Security Alerts. Auth/landing tests 43 passed / 223 assertions. Phase 23.1 dialog integration tests 11 passed / 208 assertions.
- Finance AR Aging, Invoice Status and cash-month chart links now have filtered destinations; AP cash out uses read-only Vendor Payments. Phase3FinanceDashboardTest checks destination rows.
- Current scoped regression: DashboardAccessTest + Phase3FinanceDashboardTest, 17 passed / 416 assertions. Frontend checks are recorded in `checklist.md`.
