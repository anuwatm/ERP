# Phase 23.2.2 — Metric Dictionary & Data Correctness

สถานะ: **Complete — 2026-09-17**

เอกสารนี้เป็น contract สำหรับ Phase 23.2.3–23.2.7. Dashboard ต้องไม่สร้าง metric ที่ไม่มีแถวในเอกสารนี้ หรือเปลี่ยน status/date/source โดยไม่แก้เอกสารและเพิ่ม test.

## 1. Conventions กลาง

| เรื่อง | Contract |
| --- | --- |
| เวลา | ทุกการคำนวณใช้ `organizations.timezone`; หากว่างใช้ `config('app.timezone')`. ค่า date เก็บเป็น local business date; timestamp แปลงเข้า timezone องค์กรก่อนแบ่งวัน |
| วันเริ่มสัปดาห์ | Monday 00:00:00 ถึง Sunday 23:59:59 ตาม timezone องค์กร |
| Filter flow | `today`, `this_week`, `this_month`, `custom` เป็น URL state. Flow metric filter ด้วย date column ของ metric; snapshot metric แสดง `As of YYYY-MM-DD HH:mm z` และไม่เปลี่ยนตาม date filter |
| Previous period | ใช้ช่วงก่อนหน้าที่ยาวเท่าช่วงที่เลือกและจบก่อนช่วงปัจจุบันหนึ่งวัน. หาก previous = 0 หรือไม่มี data ให้แสดง `N/A`, ไม่คำนวณ percent change |
| Precision | เงิน aggregate ใช้ decimal base amount, sum ก่อน round และแสดง 2 decimals. count เป็น integer. ratio แสดง 1 decimal; percent แสดง 1 decimal |
| เงิน | aggregate ข้ามสกุลใช้ `base_*` snapshot ของเอกสารเท่านั้น โดย label เป็น `organizations.currency`. Metric ที่ไม่มี base snapshot ต้องแยกตาม currency หรือเป็น `Unavailable`; ห้าม sum nominal currencies |
| Tenant/row scope | ทุก query ต้อง `org_id`. Sales ใช้ `SalesAccess`; Project/Task ใช้ `ProjectAccess`/`TaskAccess`; dashboard permission ไม่เพิ่ม source permission. Widget ที่ไม่มี source permission ไม่ serialize prop |
| Empty/error | ข้อมูลว่างของ metric ที่อนุญาตให้แสดง = `0`; ไม่มีสิทธิ์ = ซ่อน; source/query error = error state; source ยังไม่รองรับ = `Unavailable` พร้อมเหตุผล |

## 2. Period flow metrics

| Metric | Business meaning / formula | Source, status, date | Scope / currency | Previous & empty | Notes |
| --- | --- | --- | --- | --- | --- |
| Invoiced Revenue | ยอดออกบิลก่อน cash collection: `SUM(invoices.base_total)` | invoices `sent`, `partially_paid`, `paid`, `overdue`; `issue_date` | org + `invoices.view`; base currency | equal prior period; no rows = 0 | ไม่ใช่ recognized accounting revenue; total รวม tax/discount ตาม invoice snapshot. VAT/CN/DN reconciliation เป็น Phase 23.2.7 requirementก่อนใช้ชื่อ tax/accounting revenue |
| Cash In | เงินรับลูกหนี้สุทธิ: `SUM(receipt.base_amount) - SUM(reversal.base_amount)` | payments `entry_type` receipt/reversal; `payment_date` | org + `payments.view`; base currency | equal prior; no rows = 0 | แสดง receipt/reversal breakdown เสมอ. ยอดติดลบเป็น reversal net ไม่ใช่ Cash Out |
| AP Cash Out | เงินจ่ายเจ้าหนี้สุทธิ: `SUM(vendor payment.base_amount) - SUM(vendor reversal.base_amount)` | vendor_payments `entry_type` payment/reversal; `payment_date` | org + AP/payment permission; base currency | equal prior; no rows = 0 | ห้ามเรียก "company cash out" จนรวม cash source ทุกชนิดผ่าน reconciliation ได้ |
| Recognized Expense | ต้นทุน/ค่าใช้จ่ายที่ approved หรือ paid: `SUM(expenses.base_amount)` | expenses `approved`, `partially_paid`, `paid`; `expense_date` | org + `expenses.view`; base currency | equal prior; no rows = 0 | ไม่ใช่ cash paid และไม่ใช่ GL P&L หลัง accrual/adjustment |
| New Deals | จำนวน deal ที่สร้างในช่วง | deals ทุก stage; `created_at` | `SalesAccess`; count | equal prior; no rows = 0 | ไม่ใช้เป็น win metric |
| Won Deal Value / Count | closed-won ในช่วง: sum/count deal stage `won` ที่ `won_at` อยู่ในช่วง | deals stage `won`; `won_at` | `SalesAccess`; display separate currencies unless base snapshot added | equal prior; no rows = 0 | `deals` ไม่มี base amount จึงห้ามรวมข้าม currency |
| Lost Deal Count | closed-lost ในช่วง | deals stage `lost`; `lost_at` | `SalesAccess`; count | equal prior; no rows = 0 | ใช้ตัวหาร Win Rate ร่วมกับ Won count |
| Win Rate | `won count / (won count + lost count) × 100` ในช่วง | deals `won_at`/`lost_at` | `SalesAccess`; percent | denominator 0 = N/A | ไม่ใช้ open pipeline เป็น denominator |
| Follow-ups Due Today | activity ที่ต้องทำวันนี้และยังไม่ complete | activities `follow_up_at` อยู่ใน local today, `completed_at IS NULL` | `SalesAccess`-equivalent activity owner/record scope; count | current-day only; no rows = 0 | ไม่รับ period filter |
| Stale Deals | open deal ที่สร้างมาแล้วอย่างน้อย 7 local days และไม่มี `activities.activity_at` หลัง threshold | deals `Deal::OPEN_STAGES`, `created_at`; latest activity | `SalesAccess`; count/list | current snapshot; no rows = 0 | threshold เริ่มต้น 7 วัน. Deal ใหม่ที่ยังไม่ครบ 7 วันไม่ stale แม้ไม่มี activity; list แสดง last activity / no activity clearly |
| Tasks Completed | งานเสร็จในช่วง | tasks `status=done`, `completed_at` | `TaskAccess`; count | equal prior; no rows = 0 | My Work เพิ่ม `assignee_id = auth user` |

## 3. Current snapshot metrics

| Metric | Business meaning / formula | Source/status/as-of | Scope / currency | Empty / restriction |
| --- | --- | --- | --- | --- |
| Outstanding AR | `SUM(invoices.base_balance_due)` เมื่อ balance > 0 และ status `sent`, `partially_paid`, `overdue` | invoices, **as of query time** | org + `invoices.view`; base currency | no rows = 0. ห้ามย้อนหลังด้วย `issue_date` เพราะ balance เป็น current state |
| Overdue AR | Outstanding AR ที่ `due_date < local today` | same as Outstanding, **as of query time** | same | no rows = 0. invoice void/draft/paid ไม่ร่วม |
| Unpaid Invoice Count | count invoice ที่เข้า Outstanding AR | same | same; count | no rows = 0 |
| AR Aging | `base_balance_due` แบ่ง `current` (due today/future), `1–30`, `31–60`, `61+` วัน overdue | same; `days overdue = local today - due_date`, **as of query time** | same | due_date null แสดง bucket `no_due_date` แยก; buckets ต้อง reconcile กับ Outstanding AR |
| Open Pipeline | sum/count deals ใน `Deal::OPEN_STAGES` | deals, **as of query time** | `SalesAccess`; value separate by currency | no rows = 0. ไม่ใช้ date filterเป็น historical snapshot |
| Active Projects | count projects `planning`, `active`, `on_hold` | projects, **as of query time** | `ProjectAccess`; count | no rows = 0 |
| Overdue Tasks | tasks `todo`/`in_progress`, due_date < local today | tasks, **as of query time** | `TaskAccess`; count | blocked/cancelled/done ไม่ร่วม; no rows = 0 |
| Task Due This Week | open task due date inside current local week | tasks `todo`,`in_progress`,`blocked`; `due_date` | `TaskAccess`; count | no rows = 0 |
| Project Progress | average `progress_percent` ของ Active Projects | projects current state | `ProjectAccess`; percent | no active projects = N/A |
| Budget Remaining | `budget_amount - approved/partially_paid/paid expense amount` ต่อ project | projects + expenses, **as of query time** | project permission plus expense permission; only same currency or base budget required | unavailable for cross-currency project. ห้ามเรียกว่า Project Margin/Profit |
| Low Stock | จำนวน stock location (product+warehouse) ที่ on-hand <= reorder point | movement รวมตาม product+warehouse, เฉพาะ active tracked product ที่ reorder_point > 0 และ active warehouse | inventory permission, org/warehouse scope | นับเฉพาะ location ที่มี movement ระบุ warehouse; ไม่รวม service, non-tracked, movement ที่ warehouse เป็น null หรือ location ที่ยังไม่เคยมี movement. Query กลาง `LowStockLocations` และ destination ใช้เงื่อนไขเดียวกัน |
| Active Staff | active HR profile | employee_work_profiles + user active + started/ended dates | HR permission; count | **Unavailable:** no employment-status column nor department relation. Do not substitute total users |
| Attendance Present | distinct scheduled worker with clock-in for local day | attendances + shift/profile | HR permission; count | **Unavailable:** schedule/holiday/absence/late rules are not yet defined. Clock-in raw count may be a separately labeled operational list only |
| Pending Approval | approvals actionable by current user | workflow_approvals `status=pending`, `assigned_user_id=auth user`, joined to workflow instance `pending` | `workflows.approve`, current assignee only | no rows = 0. No dashboard Approve action; drill-down rechecks authorization |
| Pending Invite | currently invited user not expired | users `status=invited`, `invite_expires_at >= now` | users permission; count | no rows = 0 |
| Expired Invite | invited user past expiry | users `status=invited`, `invite_expires_at < now` | users permission; count | no rows = 0; operational state, not security alert |
| Sensitive Audit Events | count selected audit rows; allowlist `user.role_change`, `user.disable`, `user.hierarchy_change` | audit_logs `created_at` in period | audit permission; count | no rows = 0. Label exactly "Sensitive audit events", not security alert |
| Security Alerts | actionable anomalous security signal with severity and dedupe key | no canonical event/threshold source | security permission | **Unavailable.** Do not create KPI until event types, threshold, lifecycle and dedupe contract exist; inactive user and policy-permitted 2FA setting are not alerts |
| Users by Role | role assignment count, not unique-user share | role_user join roles | users/roles permission; count | no rows = 0. Use bar chart labeled "Role assignments"; no donut that implies distinct user percentage |

## 4. Reconciliation and non-equivalence rules

1. **Revenue vs accounting/VAT:** `Invoiced Revenue` is document total by issue date. It is neither recognized revenue nor VAT payable. Credit/debit note and tax reconciliation must be a separate validated accounting metric.
2. **Cash flow:** Cash In covers AR payment records. AP Cash Out covers `vendor_payments`. Neither is the organisation's full cash flow until all bank/treasury sources and reversals reconcile.
3. **Historical snapshots:** Outstanding AR, aging, open pipeline, active projects and overdue tasks only mean now. Historical versions require event ledger/snapshot data that can reconstruct the relevant state.
4. **Gross Profit and Project Margin:** both are unavailable in dashboard Phase 23.2.5. "Invoiced Revenue minus Recognized Expense" may be shown only as **Billed-minus-expense estimate** after matching scope. "Budget minus actual cost" is **Budget Remaining**, never profit/margin.
5. **Spend by Supplier:** three separate metrics only: committed PO (`purchase_orders`), recognized supplier expense (`expenses`), and AP cash paid (`vendor_payments`). Never add them together.
6. **Pending approval:** source is current `workflow_approvals`, not PO `sent`, Expense `draft`, or any guessed business-document state.

## 5. Filter and chart contract

| Widget category | Filter behavior |
| --- | --- |
| Flow KPI/list | selected URL period and its metric date column |
| Snapshot KPI/list | ignores date range; displays as-of timestamp and its own current-state definition |
| Six-month trend | always six completed/including calendar months ending current month, with explicit label; it does not silently inherit a custom filter |
| Comparison | only same-length prior period; N/A on zero or unavailable denominator |
| Drill-down | sends the same filter semantics in URL; destination recalculates scope/authorization and does not trust client totals |

## 6. Phase 23.2.5 implementation gate

- Use `base_total`, `base_amount`, `base_balance_due` and `base_*` expense/payment snapshots for cross-currency finance widgets.
- Do not ship widgets marked Unavailable. Add the missing canonical source/policy, migration/backfill where required, metric tests, then amend this dictionary.
- No Dashboard payload includes raw cash balance, individual payroll pay, or a source-module aggregate without that module's permission.
