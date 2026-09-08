# Current Implementation Reference

เอกสารนี้เป็นภาพรวม implementation ณ Phase 19. ใช้สำหรับอ่านระบบปัจจุบันร่วมกับ source code; ไม่แทน migration, route หรือ test ซึ่งเป็น source of truth ของ runtime.

## Runtime

| Layer | Current implementation |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Inertia 2 |
| Frontend | React 18, TypeScript, Vite |
| Database | MySQL/MariaDB compatible, 45 migrations |
| Runtime drivers | database session/cache/queue, log mailer in local |
| Locale / timezone | `th` locale; Laravel application timezone `UTC` |
| Auth | Laravel Breeze local auth, email verification, RBAC, optional organization 2FA |

## Implemented Domains

| Phase | Domain | Current capability |
| --- | --- | --- |
| 1-6 | Foundation, CRM, Finance, Delivery, dashboards | Organization hierarchy, 7 roles, audit, CRM/deals, invoices/payments/expenses, projects/tasks and filtered dashboards |
| 7-9 | Procurement & commercial documents | Configurable numbering, suppliers, PO, quotations, CN/DN, billing note, delivery order, purchase request, voucher, official print/PDF |
| 8 | Reporting, tax, GRN, notifications | Tax/WHT/aging exports, goods receipts and stock movements, in-app preferences and queued mail |
| 10-11 | Treasury & GL | Bank accounts/statements/reconciliation, petty cash, cheque/PDC, immutable double-entry journals, periods and reports |
| 12 | E-Tax application layer | XML generation, private storage, submission adapter boundary and RD preparation export; not direct certified filing |
| 13-14.1 | Assets & FX | Asset/depreciation/disposal, currency/rates, realized/unrealized FX, AP/inventory/FCD bridge |
| 15 | Inventory operations | Warehouse/bin/lot, transfer, stock count, barcode/QR scanning and reorder alerts |
| 16B | Payroll | Effective-dated payroll policies, calculation snapshot, payslip, PND1/SSO workpaper and GL posting |
| 17-18.1 | DMS core & compliance | Private versioned documents, checksum, scan gate, category, sensitivity, expiry alert, parent authorization, retention enforcement and purge workflow |
| 18 | 2FA | Offline TOTP, recovery codes, trusted devices, owner reset, audit and policy-controlled privileged-role enforcement |
| 19 | HR core, attendance & leave | Work profile/manager/shift/holiday, self-service clock-in/out, opt-in IP hash/GPS with consent, leave balances and draft/submit/cancel flow, locked time summary with reversal |
| 20 | Dynamic approval workflow | Versioned definition, threshold matching, user/manager/role approver snapshot, sequential/parallel steps, SoD, temporary delegation, inbox and immutable action history |

## Current Boundaries

| Area | Boundary |
| --- | --- |
| DMS | Category policy calculates `retention_until`, default renewal and legal hold. Download/link requires every linked parent to be visible; scheduled enforcement quarantines failed scans and archives due documents. Purge is an explicit command only |
| File scanning | Production document versions stay `pending_scan` until an external scanner marks them clean; no scanner service is bundled |
| E-Tax | The system produces/stores XML and supports a provider boundary. Certified provider onboarding, certificates and direct Revenue Department filing are not implemented |
| 2FA | Policy defaults to off. When enabled, enrollment/challenge depends on organization policy and privileged role; trusted-device lifetime is 1-90 days |
| HR to payroll | Attendance summaries are manually created with a cutoff, then locked or reversed into a replacement draft. They do not create `payroll_runs`, payroll items or GL entries automatically |
| Workflow | Approval starts only when a creator submits a supported document to a matching active definition. Workflow final approval updates source status but intentionally does not auto-post GL; financial posting integration needs its own idempotent contract |
| Future domains | External notification channels, portals, gateway payments, direct e-Tax gateway, forecasting, OCR, manufacturing and POS are planned from Phase 21 onward |

## Source Navigation

| Need | Source |
| --- | --- |
| Phase state / remaining work | [`../checklist.md`](../checklist.md) |
| Database schema | `backend/database/migrations/*.php` and [`../document/DATABASE_ERD.md`](../document/DATABASE_ERD.md) |
| HTTP routes | `backend/routes/web.php` and [`ROUTES_AND_SCREENS.md`](./ROUTES_AND_SCREENS.md) |
| Policies / business behavior | `backend/app/Services`, `backend/app/Policies`, controllers and `backend/tests` |
| Diagrams | [`../document/README.md`](../document/README.md), [`../document/GROUPS_WORKFLOW.md`](../document/GROUPS_WORKFLOW.md) |

## Verification Snapshot

- `php artisan about`: Laravel 13.22.0, PHP 8.3.1, MySQL driver
- `php artisan route:list --except-vendor`: 212 routes
- 50 PHPUnit test files and 45 migrations were present during the documentation audit
