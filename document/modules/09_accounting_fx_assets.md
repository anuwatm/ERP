# 09 — บัญชีแยกประเภท, FX และสินทรัพย์ถาวร

อ้างอิงโค้ด ณ 2026-10-24, HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` รวม working tree ปัจจุบัน
เอกสารจากการอ่าน implementation และ tests; ไม่ได้รัน tests ในการจัดทำบทนี้

## หน้าที่และขอบเขต

- ผังบัญชีแยกองค์กร, งวดบัญชี, สมุดรายวัน, trial balance และ general ledger
- Snapshot อัตราแลกเปลี่ยนสำหรับเอกสาร; realized FX เมื่อชำระ และ unrealized FX สิ้นเดือน
- Capitalization, ค่าเสื่อมรายเดือน และ disposal/write-off สินทรัพย์
- Treasury และธุรกรรม AR/AP เรียกใช้บริการลงบัญชีชุดเดียวกัน

## จุดเข้าและสิทธิ์

อ้างอิง [routes/web.php](../../backend/routes/web.php)

| Route/action | Permission |
|---|---|
| GET `/accounting/chart-of-accounts` → `chartOfAccounts` | `accounting.chart_accounts.view` |
| POST/PATCH `/accounting/chart-of-accounts[/id]` | `accounting.chart_accounts.manage` |
| GET `/accounting/periods` | `accounting.periods.view` |
| POST `/accounting/periods`, POST `/{id}/close` | `accounting.periods.manage` |
| GET `/accounting/journals` | `accounting.journals.view` |
| POST `/accounting/journals` | `accounting.journals.post` |
| POST `/accounting/journals/{id}/reverse` | `accounting.journals.reverse` |
| GET `/accounting/reports` | `accounting.reports.view` |
| GET `/currencies` | `fx.view` |
| POST `/currencies`, `/exchange-rates` | `fx.manage` |
| POST `/fx-revaluations`, `/fx-revaluations/reverse` | `fx.revalue` |
| GET `/fixed-assets` | `fixed_assets.view` |
| POST `/fixed-assets/categories`, `/fixed-assets`, `/{id}/attachment` | `fixed_assets.manage` |
| POST `/fixed-assets/depreciate` | `fixed_assets.depreciate` |
| POST `/fixed-assets/{id}/dispose` | `fixed_assets.dispose` |

Mutation routes ข้างต้นใช้ `password.confirm` และ throttle; บาง create routes ใช้ `EnsureFormSubmission`
Controller ตรวจ `org_id` เพิ่มจาก route permission ก่อนเข้าถึง record

## โค้ดหลัก

- [GeneralLedgerController](../../backend/app/Http/Controllers/Finance/GeneralLedgerController.php): ผังบัญชี, งวด, manual journal และรายงาน
- [JournalPostingService](../../backend/app/Services/JournalPostingService.php): `post`, `reverse`, `resolveLines`, `assertBalanced`
- [FinancialJournalService](../../backend/app/Services/FinancialJournalService.php): map ธุรกรรม AR/AP/payroll/stock/manufacturing เป็น debit/credit
- [AccountingPeriodService](../../backend/app/Services/AccountingPeriodService.php): `openPeriodFor`, `assertOpen`
- [ChartOfAccountProvisioner](../../backend/app/Services/ChartOfAccountProvisioner.php): สร้างบัญชีมาตรฐานต่อองค์กร
- [CurrencyController](../../backend/app/Http/Controllers/Finance/CurrencyController.php), [FxRateService](../../backend/app/Services/FxRateService.php), [FxRevaluationService](../../backend/app/Services/FxRevaluationService.php)
- [FixedAssetController](../../backend/app/Http/Controllers/Finance/FixedAssetController.php), [FixedAssetService](../../backend/app/Services/FixedAssetService.php)

## Entity และความสัมพันธ์

- `ChartOfAccount`: parent account, account type, normal balance, `is_postable`, `status`
- `AccountingPeriod` 1:N `JournalEntry` 1:N `JournalLine`; line เชื่อม `ChartOfAccount`
- Entry เก็บ `source_type`, `source_id`, `posting_event`; reversal เชื่อม `reversal_of_id`
- `Currency`, `ExchangeRate`: คู่ base/quote และวันที่มีผล
- `FxRevaluation`: source, เดือน, foreign amount, rate, before/after, journal และ reversal journal
- `AssetCategory`: asset account, accumulated depreciation account, depreciation expense account และอายุเริ่มต้น
- `FixedAsset` N:1 category; 1:N `AssetDepreciation`; อ้าง source expense/goods receipt และ attachment file
- Models: [Models](../../backend/app/Models) ได้แก่ `ChartOfAccount.php`, `AccountingPeriod.php`, `JournalEntry.php`, `JournalLine.php`, `Currency.php`, `ExchangeRate.php`, `FxRevaluation.php`, `AssetCategory.php`, `FixedAsset.php`, `AssetDepreciation.php`

## Workflow ลงบัญชี

1. `post()` เปิด transaction และหา entry เดิมด้วย org/source/event พร้อม `lockForUpdate`
2. ถ้ามี entry เดิม ส่งกลับทันที ทำให้เหตุการณ์เดิมลงบัญชีซ้ำไม่ได้ในเส้นทางนี้
3. Provision ผังบัญชีและหา open period ครอบคลุม posting date
4. แต่ละ line ต้องใช้บัญชี `active` และ `is_postable=true`
5. Line ต้องมี debit หรือ credit บวกเพียงด้านเดียว; ยอดรวมสองด้านต้องเท่ากันหลังปัดสองตำแหน่ง และมากกว่าศูนย์
6. สร้าง entry สถานะ `posted`, lines, เลขจาก `NumberSequenceService`, audit `journal.post`
7. `reverse()` รับเฉพาะ original `posted`; สลับ debit/credit, สร้าง reversal ใน open period แล้วเปลี่ยน original เป็น `reversed`

`reports()` รวมทั้ง `posted` และ `reversed` เพื่อให้ original และ reversal หักล้างกัน
หากไม่มี period เลย `openPeriodFor()` สร้าง FY ของวันที่ลงบัญชีเป็น `open`
หากมี period อื่นอยู่แล้วแต่ไม่ครอบคลุมวันที่ จะ reject; period `closed` ลงบัญชีไม่ได้
`storePeriod()` กันช่วงซ้อน; `closePeriod()` กันปิดซ้ำและกัน draft journal
ไม่พบ route reopen period ในชุด routes นี้

## Workflow FX

- `FxRateService` เลือก rate วันที่ล่าสุดที่ไม่เกิน transaction date; เอกสารเก็บ base currency/amount ไว้
- `revalueReceivables()` เลือก invoice `sent`, `partially_paid`, `overdue` และ `balance_due>0`
- `revaluePayables()` เลือก expense `approved`, `partially_paid` และมียอดค้าง
- ข้าม source ที่ currency เท่ากับ base หรือเคย revalue เดือนนั้นแล้ว
- AR gain: Dr `1120`, Cr `4310`; AR loss: Dr `5410`, Cr `1120`
- AP เพิ่มมูลค่าหนี้: Dr `5410`, Cr `2110`; ลดมูลค่า: Dr `2110`, Cr `4310`
- FCD ใช้บัญชีธนาคาร active ที่ map GL; foreign amount จาก opening balance บวก/ลบ bank transfers ถึงสิ้นเดือน
- Revaluation เก็บ snapshot และปรับ base balance ของ source ภายใน transaction
- `reverseMonth()` กลับ journal ณ วันที่ปัจจุบันและปรับยอด source พร้อม `reversed_at`
- การ reverse ยังต้องผ่าน open period ของวันที่ reversal

## Workflow สินทรัพย์

- `capitalize()` รองรับ source `expense` ที่ `approved`/`paid` หรือ `goods_receipt` ที่มี cost ไม่รวมภาษีมากกว่าศูนย์
- Source เดิม capitalize ซ้ำไม่ได้; category ต้อง active
- Salvage ต้องไม่ติดลบและต่ำกว่า cost; method เป็น `straight_line`
- เริ่มค่าเสื่อมต้นเดือน `available_for_use_date`; ไม่ prorate รายวัน
- Capitalization Dr บัญชีสินทรัพย์ category / Cr expense `5200` หรือ inventory `1140`
- `runThroughMonth()` ไล่เฉพาะ active assets, catch up ตั้งแต่เดือนแรกถึง target
- ค่าเสื่อมต่อเดือน `(cost-salvage)/useful_life_months` จำกัดไม่ให้ NBV ต่ำกว่า salvage
- แต่ละเดือนใช้ posting event `depreciation:YYYY-MM` และเก็บ `AssetDepreciation`
- `dispose()` คิดค่าเสื่อมถึงเดือนก่อน disposal ก่อน; รับเฉพาะ asset `active`
- ปลายทาง `disposed` หรือ `written_off`; write-off proceeds เป็นศูนย์
- กลับ asset cost/accumulated depreciation และบันทึก gain/loss ที่ `5300`
- Attachment ใช้ File record; ตรวจรายละเอียด validation และ replacement ใน controller

## หน้าจอและ schema

- [Accounting pages](../../backend/resources/js/Pages/Accounting): `ChartOfAccounts`, `Periods`, `Journals`, `Reports`
- [Finance pages](../../backend/resources/js/Pages/Finance): `Currencies`, `FixedAssets`, `FixedAssetCreateDialog`, `FixedAssetAttachmentDialog`
- [GL migration](../../backend/database/migrations/2026_08_27_000001_create_phase11_general_ledger_tables.php)
- [Fixed assets migration](../../backend/database/migrations/2026_08_28_000002_create_phase13_fixed_assets_tables.php)
- [FX migration](../../backend/database/migrations/2026_08_28_000003_create_phase14_fx_tables.php)
- [AP/FCD/inventory FX migration](../../backend/database/migrations/2026_08_28_000004_create_phase141_ap_fcd_and_inventory_fx_tables.php)
- [Base amount backfill](../../backend/database/migrations/2026_09_17_000001_backfill_same_currency_finance_base_amounts.php)

## Tests ที่อ่านและขอบเขตที่ยังไม่ยืนยัน

- [Phase11GeneralLedgerTest](../../backend/tests/Feature/Phase11GeneralLedgerTest.php): balanced/idempotent/reversal, unbalanced/closed period rejection, tenant/permission
- [Phase14FxTest](../../backend/tests/Feature/Phase14FxTest.php): dated rate, receipt realized gain, month-end revaluation/reversal
- [Phase141ApInventoryFxTest](../../backend/tests/Feature/Phase141ApInventoryFxTest.php): vendor settlement/reversal, AP revalue, FCD transfer balanced journal
- [Phase13FixedAssetsTest](../../backend/tests/Feature/Phase13FixedAssetsTest.php): capitalize/depreciate/dispose, optional fields retry, proof upload/scope
- Tests เหล่านี้เป็นหลักฐาน intention และ assertions; ยังไม่มีผลรันจากการจัดทำเอกสารนี้
- จำนวนเงินใช้ `float`, `round`, decimal casts ผสมกัน ต้องประเมิน precision ก่อนธุรกรรมปริมาณสูง
- FCD revaluation คำนวณจาก opening+transfers; อย่าสมมติว่ารวม cash movement ทุกประเภท
- ไม่พบ external FX feed หรือ asset methods นอก straight line ในบริการที่ตรวจ
- ดู [Treasury](10_treasury_banking.md), [Tax/e-Tax](11_tax_etax.md), [HR/Payroll](12_hr_attendance_payroll.md), [Manufacturing](13_manufacturing.md) สำหรับผู้เรียก GL
