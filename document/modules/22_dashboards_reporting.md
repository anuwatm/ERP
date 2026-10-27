# 22 — Dashboards, Metrics และการเปิดเผยข้อมูล

ตรวจจาก working tree วันที่ 2026-10-24; HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` ไม่ได้รัน dashboard/browser/performance tests

## ขอบเขตและ Source map

Dashboard รวมข้อมูลจากแต่ละ domain แต่ไม่ใช่ต้นทาง transaction หรือ GL

- [DashboardController.php](../../backend/app/Http/Controllers/Admin/DashboardController.php) — admin/executive/finance/delivery scopes
- [DashboardAccess.php](../../backend/app/Support/DashboardAccess.php) — capabilities, scope และ widget gates
- [DashboardDate.php](../../backend/app/Support/DashboardDate.php) — วันตาม timezone องค์กร
- [DashboardLanding.php](../../backend/app/Support/DashboardLanding.php) — เลือก landing จาก permission
- [Dashboard.tsx](../../backend/resources/js/Pages/Dashboard.tsx) และ [Components/Dashboard](../../backend/resources/js/Components/Dashboard)
- [SalesDashboardController.php](../../backend/app/Http/Controllers/Sales/SalesDashboardController.php)
- [MyWorkDashboardController.php](../../backend/app/Http/Controllers/Delivery/MyWorkDashboardController.php)
- [ProcurementDashboardController.php](../../backend/app/Http/Controllers/Finance/ProcurementDashboardController.php)
- [HrDashboardController.php](../../backend/app/Http/Controllers/HrDashboardController.php)

## Routes และ scope gates

ทุก route ในตารางใช้ `auth`, `verified` อ่าน [web.php](../../backend/routes/web.php) และ gate ใน controller ร่วมกัน

| Route | เงื่อนไขสำคัญ |
| --- | --- |
| `/dashboard` | route ต้อง `dashboard.view` |
| `/executive-dashboard` | route `dashboard.view`; scope ต้อง `executive.dashboard.view` เพิ่ม |
| `/finance-dashboard` | route `expenses.view`; scope ต้อง `dashboard.view` + `expenses.view` |
| `/delivery-dashboard` | route `dashboard.view`; scope ต้อง `projects.view` หรือ `tasks.view` เพิ่ม |
| `/my-work` | `tasks.view` และ row access ใน MyWork controller |
| `/procurement-dashboard` | controller ตรวจ permission ต้นทาง อ่าน [Procurement](07_procurement_inventory.md) |
| `/hr-dashboard` | controller ต้อง (`hr.manage` และ `hr.self.view`) หรือ `payroll.view` |

Sales dashboard route/visibility ดู [CRM](04_crm_sales.md) สิทธิ์ใน navigation อย่างเดียวไม่ใช่สิทธิ์เปิด route

## วิธีประกอบข้อมูล

`DashboardController::__invoke()` ส่ง `dashboardScope`, `dashboardCapabilities`, `dashboardWidgets`, filters และ legacy composites บางส่วน

- Widget query ทำหลังผ่าน permission ต้นทาง เช่น `invoices.view`, `payments.view`, `expenses.view`
- Legacy `financeSummary` ส่งเมื่อ finance scope และมีสิทธิ์ invoice/payment/expense ครบ
- Legacy `deliverySummary` มี approved expense cost จึงไม่ส่งเมื่อไม่มีสิทธิ์ source finance
- `summary`, `securityAlerts`, `executiveSummary` ตั้ง null ใน entry ปัจจุบัน; `recentAudits` ตั้ง array ว่าง ข้อมูล admin ใช้ widgets
- ไม่ควรเพิ่ม query ข้าม domain ก่อนตรวจ gate แล้วค่อยซ่อนด้วย frontend

## Metric lineage ของ widget ปัจจุบัน

| Widget | ต้นทาง/นิยาม |
| --- | --- |
| Revenue | Invoice `base_total`, status sent/partially_paid/paid/overdue ตาม `issue_date` |
| AR/Aging | Invoice `base_balance_due > 0`, status sent/partially_paid/overdue; แยก due date ไม่มีค่า/current/1–30/31–60/61+ |
| Cash In | Payment base_amount แยก receipt/reversal ตาม payment_date; หน้าเว็บแสดงสุทธิ |
| Recognized Expense | Expense base_amount status approved/partially_paid/paid ตาม expense_date |
| AP Cash Out | VendorPayment base_amount payment ลบ reversal ตาม payment_date |
| Pipeline | Open Deal ผ่าน SalesAccess และแยกยอดตาม currency |
| Admin users | User ใน org แยก active/invited/expired/inactive |
| Admin audit | AuditLog ใน org; sensitive subset กำหนดใน query |

Legacy composite methods ยังมี logic ของตนเอง ต้องตรวจ method/prop ที่หน้าใช้อยู่ก่อนเทียบตัวเลข อย่าใช้คำอธิบาย metric จากแผน MVP แทนโค้ดล่าสุด

## Date และ visibility

- `DashboardDate::now()` ใช้ organization timezone ก่อน fallback app timezone
- DATE fields ใช้ business dates โดยตรง
- Timestamp ลงท้าย `_at` แปลงขอบ local midnight กลับ app timezone; upper bound ใช้ก่อนเที่ยงคืนวันถัดไป
- Sales/Projects/Tasks มี scope helpers สำหรับ row visibility เพิ่มจาก org
- HR dashboard ไม่ส่ง pending leave/on-leave data ให้ payroll-only viewer; latest payroll run ส่งตาม payroll permission
- Low-stock ใช้ [LowStockLocations.php](../../backend/app/Support/LowStockLocations.php) ร่วมกับ Procurement dashboard; ไม่ถือว่าจำนวนสินค้าเท่ากับจำนวน location

## รายงานที่แยกออกไป

- Tax/aging: [Tax/e-Tax](11_tax_etax.md)
- GL/trial balance: [Accounting](09_accounting_fx_assets.md)
- Treasury: [Treasury](10_treasury_banking.md)
- Forecast/financial-health assumptions: [Cash Flow](23_cash_flow_forecasting.md)
- External summary contract: [API](18_external_api_webhooks.md) ไม่ใช่ dump props ของ internal dashboard

## Tests อ้างอิง — อ่าน ไม่ได้รัน

- [DashboardAccessTest.php](../../backend/tests/Feature/DashboardAccessTest.php) — source permissions, query gating, org/actor snapshot, HR isolation, low-stock query count
- [DashboardMetricsEdgeCasesTest.php](../../backend/tests/Feature/DashboardMetricsEdgeCasesTest.php) — empty org, date boundaries, reversals, FX และ reconciliation
- [DashboardPerformanceTest.php](../../backend/tests/Feature/DashboardPerformanceTest.php) — fixture budget ไม่ใช่ production benchmark
- [Phase6DashboardFiltersTest.php](../../backend/tests/Feature/Phase6DashboardFiltersTest.php)
- [phase232-dashboard.cjs](../../backend/tests/Browser/phase232-dashboard.cjs)

กลับ [สารบัญ](README.md)
