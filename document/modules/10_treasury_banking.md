# 10 — Treasury, ธนาคาร, เงินสดย่อย และ Cash Flow

Snapshot 2026-10-24, HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` รวม working tree
วิเคราะห์จากโค้ดและ tests ที่อ่าน ไม่ได้รัน tests ในบทนี้

## หน้าที่

เก็บบัญชีธนาคารและ GL mapping, นำเข้า statement, reconcile กับรายการ ERP,
โอนระหว่างบัญชี, เงินสดย่อย, เช็ค และคาดการณ์ cash flow หลาย scenario
การบันทึกข้อมูลใน ERP ไม่ได้หมายถึงส่งคำสั่งโอนไปธนาคารจริง

## Routes และ permissions

อ้างอิง [web.php](../../backend/routes/web.php)

| Route | Permission |
|---|---|
| GET `/bank-accounts` | `treasury.accounts.view` |
| POST/PATCH `/bank-accounts[/id]` | `treasury.accounts.manage` |
| POST `/bank-transfers` | `treasury.accounts.manage` |
| GET `/bank-statements` | `treasury.reconciliation.view` |
| POST `/bank-statements/import` | `treasury.reconciliation.manage` |
| POST `/bank-statement-lines/{id}/match`, `/unmatch` | `treasury.reconciliation.manage` |
| GET `/petty-cash` | `petty_cash.view` |
| POST `/petty-cash/funds`, `/requests`, `/reimbursements` | `petty_cash.manage` |
| POST `/petty-cash/requests/{id}/approve`, `/reject` | `petty_cash.approve` |
| POST `/petty-cash/requests/{id}/pay` | `petty_cash.manage` |
| GET `/cheques` | `cheques.view` |
| POST `/cheques`, `/cheques/{id}/transition` | `cheques.manage` |
| GET `/treasury-reports` | `treasury.reports.view` |
| GET `/finance/cash-flow` | `cash_flow.view` |
| POST `/finance/cash-flow/snapshot`, PATCH `/finance/cash-flow/scenarios/{id}` | `cash_flow.manage` |

Mutation ส่วนใหญ่มี `password.confirm`, throttle; fund/request/reimbursement/cheque create ใช้ `EnsureFormSubmission`
Scope record และ `Rule::exists` จำกัดองค์กรของผู้ใช้

## โค้ดหลัก

- [BankAccountController](../../backend/app/Http/Controllers/Finance/BankAccountController.php): `index`, `store`, `update`, validation
- [BankStatementController](../../backend/app/Http/Controllers/Finance/BankStatementController.php): `import`, `match`, `unmatch`, `candidates`, `assertCandidateMatches`
- [BankTransferController](../../backend/app/Http/Controllers/Finance/BankTransferController.php): `store`
- [TreasuryOperationsController](../../backend/app/Http/Controllers/Finance/TreasuryOperationsController.php): เงินสดย่อย/เช็ค
- [TreasuryReportController](../../backend/app/Http/Controllers/Finance/TreasuryReportController.php): ภาพรวม unreconciled, petty cash และเช็คค้าง
- [CashFlowController](../../backend/app/Http/Controllers/Finance/CashFlowController.php): `index`, `snapshot`, `updateScenario`
- [CashFlowForecastService](../../backend/app/Services/Finance/CashFlowForecastService.php): forecast/scenarios/financial health
- [FinancialJournalService](../../backend/app/Services/FinancialJournalService.php), [FxRateService](../../backend/app/Services/FxRateService.php): GL และ base currency snapshots

## Entities และความสัมพันธ์

- `BankAccount`: org, optional branch, currency, opening/base balance, optional chart account
- `BankStatement` N:1 bank account; 1:N `BankStatementLine`
- `Reconciliation` เชื่อม statement line กับ candidate ผ่านชนิดและ ID; เก็บประวัติ unmatch
- `BankTransfer` มี source/destination accounts, foreign/base amounts, date/reference
- `PettyCashFund` เชื่อม custodian user และ optional bank account
- `PettyCashRequest` เชื่อม fund, requester, approver, payer
- `PettyCashReimbursement` เชื่อม fund/bank account และลง GL
- `Cheque` เชื่อม bank account และ statement line ตอน cleared
- `ForecastScenario`, `CashFlowForecast`, `FinancialMetricLog`: สมมติฐาน ผล forecast และ metric snapshots
- ดู model definitions ใน [Models](../../backend/app/Models)

## บัญชีและการโอน

- บัญชีต้องมีเลขบัญชีไม่ซ้ำในองค์กร; validate branch/GL references อยู่ในองค์กรเดียวกัน
- หน้าจอเสนอ GL asset accounts ที่ active/postable
- โอนต้อง source/destination คนละบัญชี ทั้งคู่ active และ map chart of accounts แล้ว
- `store()` ใช้ transaction; FX snapshot และ journal ผ่านบริการกลาง
- ไม่มี bank API transfer ใน controller นี้; เป็น internal accounting record

## Statement → reconciliation

1. Upload CSV และเลือก active bank account ขององค์กร
2. Headers ต้องตรง `transaction_date,amount,description,reference_no,balance_after`; จำกัด 5,000 rows
3. ตรวจ date/amount/balance และสร้าง normalized SHA-256 row fingerprint
4. ตัดรายการที่นำเข้าแล้ว; ถ้าไม่เหลือ row ใหม่ reject
5. สร้าง statement `open`, line `unreconciled`, currency/base snapshot
6. Candidates มาจาก `Payment`, paid `Expense`, `VendorPayment`, bank transfer สองด้าน
7. `match()` lock line; กัน statement `closed`, line ที่ reconciled แล้ว
8. Candidate ต้อง account เดียวกันและ signed amount เท่ากันหลังปัดสองตำแหน่ง
9. สร้าง reconciliation และเปลี่ยน line เป็น `reconciled`
10. `unmatch()` ต้องมี active reconciliation และ statement ไม่ closed; เก็บ `unmatched_at` แล้วคืน `unreconciled`

Candidate matching ตรวจ account/amount/direction ไม่ได้บังคับวันที่เท่ากัน
History เก็บ audit แทนการลบหลักฐานการ match เดิม

## เงินสดย่อย

- `storeFund()` สร้าง fund `active` และกำหนด imprest/custodian
- `storeRequest()` สร้าง request `submitted`
- `approveRequest()` รับเฉพาะ submitted และห้าม requester อนุมัติของตัวเอง → `approved`
- `rejectRequest()` รับเฉพาะ submitted, ห้าม requester reject เอง, ต้อง reason → `rejected`
- `payRequest()` รับเฉพาะ approved → `paid`
- `reimburse()` สร้าง reimbursement และเรียก FinancialJournalService; ตรวจ fund/bank active
- อย่าสมมติว่า request pay ติดต่อธนาคารหรือสร้าง Expense อัตโนมัติ: วิธีนั้นอัปเดตสถานะและ audit

## เช็ค

- สร้างเป็น `registered`; transition เป้าหมาย `deposited`, `cleared`, `bounced`, `cancelled`
- สถานะ final คือ `cleared`, `bounced`, `cancelled`; เปลี่ยนต่อไม่ได้
- กันเลือกสถานะเดิมซ้ำ
- Cleared ต้องมี statement line โดย bank account และ absolute amount ตรง cheque
- Bounced/cancelled ต้องมี reason และเก็บ timestamps
- Controller ไม่ได้บังคับให้ทุกเช็คผ่าน deposited ก่อน cleared
- Treasury report นับ pending จาก `registered` และ `deposited`

## Forecast และข้อจำกัด

สูตรและผลข้างเคียงล่าสุดดู [23 Cash Flow Forecasting](23_cash_flow_forecasting.md) รวมข้อสังเกตว่าเปิดหน้าจอมีการสร้าง snapshot/log

- `getOrCreateDefaultScenarios()` เตรียม scenarios; `generateForecast()` สร้างคาดการณ์
- Inflow อาศัย AR ค้างและ sales pipeline; outflow อาศัย AP, purchase orders และ payroll
- `getMultiScenarioComparison()` เปรียบเทียบสมมติฐาน; `calculateFinancialHealth()` บันทึก ratios/runway
- `updateScenario()` ตรวจเจ้าขององค์กรก่อนแก้ assumptions
- Forecast เป็น model จากข้อมูล ERP และ assumptions จึงไม่ใช่ bank confirmed balance
- ต้องตรวจว่า import/payment records ครบก่อนอ้างเป็น cash position จริง

## UI, schema และ tests

- Pages ใน [Finance](../../backend/resources/js/Pages/Finance): `BankAccounts`, `BankStatements`, `PettyCash`, `Cheques`, `TreasuryReports`, `CashFlow`
- [Bank accounts migration](../../backend/database/migrations/2026_08_26_000002_create_phase10_bank_accounts_table.php)
- [Payment/expense linking](../../backend/database/migrations/2026_08_26_000003_link_treasury_accounts_to_payments_and_expenses.php)
- [Statements migration](../../backend/database/migrations/2026_08_26_000004_create_phase10_bank_statements_tables.php)
- [Petty cash/cheques migration](../../backend/database/migrations/2026_08_26_000006_create_phase10_petty_cash_and_cheques_tables.php)
- [Cash flow migration](../../backend/database/migrations/2026_09_21_000001_create_phase25_cash_flow_tables.php)
- [Phase10BankAccountsTest](../../backend/tests/Feature/Phase10BankAccountsTest.php): bank account access/validation
- [Phase10BankStatementsTest](../../backend/tests/Feature/Phase10BankStatementsTest.php): duplicate import, invalid headers/tenant, match/unmatch history, mismatched candidate
- [Phase10TreasuryOperationsTest](../../backend/tests/Feature/Phase10TreasuryOperationsTest.php): petty cash และ cheque flows
- [Phase25CashFlowTest](../../backend/tests/Feature/Phase25CashFlowTest.php): scenario seeding, AR/pipeline/AP/PO/payroll projections, health, scope, snapshot command
- ยังไม่ได้รัน tests เหล่านี้; อ่าน assertions เพื่อเข้าใจ contract เท่านั้น
- ต่อเนื่อง: [Accounting/FX/assets](09_accounting_fx_assets.md)
