# 13 — Manufacturing: BOM, Work Orders และต้นทุนผลิต

Snapshot 2026-10-24, HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` รวม working tree
อ่าน source และ tests; ไม่ได้รัน manufacturing tests ในบทนี้

## หน้าที่

- สร้าง Bill of Materials แบบหลายระดับ พร้อม waste, labor และ overhead
- แตก BOM และรวมวัตถุดิบปลายทางสำหรับ planned production quantity
- Work Order lifecycle เชื่อม stock movement และ GL/WIP
- บันทึก quality check, good quantity, scrap และ production costs

## Routes และ permissions

อ้างอิง [web.php](../../backend/routes/web.php)

| Route/action | Permission |
|---|---|
| GET `/manufacturing/boms` และ `/{id}/explosion` | `manufacturing.bom.view` |
| POST `/manufacturing/boms` | `manufacturing.bom.manage` |
| PATCH/DELETE `/manufacturing/boms/{id}` | `manufacturing.bom.manage` |
| POST `/manufacturing/boms/{id}/duplicate` | `manufacturing.bom.manage` |
| GET `/manufacturing/work-orders` | `manufacturing.work_orders.view` |
| POST `/manufacturing/work-orders`, PATCH `/{id}` | `manufacturing.work_orders.manage` |
| POST `/manufacturing/work-orders/{id}/plan`, `/costs` | `manufacturing.work_orders.manage` |
| POST `/manufacturing/work-orders/{id}/release`, `/quality-check`, `/complete`, `/cancel` | `manufacturing.work_orders.execute` |

Mutation ต้อง password confirm และ throttle 10/minute
Work order create/plan/release/QC/complete/cancel/costs และ BOM create ใช้ `EnsureFormSubmission`
Controller ตรวจ `org_id` ของ record ก่อน action; ต่างองค์กร 403

## โค้ดหลัก

- [BillOfMaterialsController](../../backend/app/Http/Controllers/Manufacturing/BillOfMaterialsController.php): index/store/update/duplicate/destroy/explosion
- [WorkOrderController](../../backend/app/Http/Controllers/Manufacturing/WorkOrderController.php): create/update/lifecycle/addCost
- [ProductionCostingService](../../backend/app/Services/ProductionCostingService.php): calculateBomCost/explode/flattenExplodedMaterials/createWorkOrder/transitionStatus
- [FinancialJournalService](../../backend/app/Services/FinancialJournalService.php): postWorkOrderMaterialIssue/postWorkOrderCompletion/reverseWorkOrder
- [NumberSequenceService](../../backend/app/Services/NumberSequenceService.php): BOM/WO document numbering

## Entities และ relations

- [BillOfMaterials](../../backend/app/Models/BillOfMaterials.php): table `bill_of_materials`, product, version, batch quantity, unit, is_active, cost totals; SoftDeletes
- [BomItem](../../backend/app/Models/BomItem.php): BOM parent, product, optional sub_bom_id, quantity, waste, cost และ sequence
- [WorkOrder](../../backend/app/Models/WorkOrder.php): BOM/product, source warehouse, destination warehouse, quantities/status/QC/dates/costs
- [WorkOrderItem](../../backend/app/Models/WorkOrderItem.php): snapshot product/planned/issued quantities/unit/cost ต่อ WO
- [ProductionCost](../../backend/app/Models/ProductionCost.php): WO + labor/overhead cost record
- [StockMovement](../../backend/app/Models/StockMovement.php): WO issue/receipt/return movements
- WO เชื่อม journals ผ่าน source/event ใน GL; ไม่ใช่แก้ยอด stock column โดยตรง

## BOM costing และ explosion

1. สร้าง BOM และรายการ; `calculateBomCost()` sync item totals และ BOM total
2. Effective item qty = quantity × (1+waste_percent/100)
3. Material total = effective qty × unit cost; BOM total = material+labor+overhead
4. Unit cost = total/batch quantity
5. Sub-BOM ที่ unit cost ไม่บวกใช้ sub-BOM total_cost/quantity เป็น fallback
6. `explode()` scale ตามจำนวนที่ต้องผลิต และ recurse sub-BOM
7. Visited BOM IDs กัน circular dependency ด้วย DomainException
8. `flattenExplodedMaterials()` รวมเฉพาะ leaf raw materials โดย product_id

`duplicate()` สร้าง BOM copy; `destroy()` soft delete และ audit
Explosion เป็น JSON endpoint ที่ UI ใช้ดูโครงสร้าง ไม่ได้เบิก stock
Cost เป็น BOM snapshot/ค่าที่กรอก ไม่ใช่การดึง weighted-average valuation สดทุกครั้ง

## สร้างและวางแผน WO

- `createWorkOrder()` โหลด BOM ของ org; planned quantity ต้องมากกว่าศูนย์
- Scale material/labor/overhead จาก BOM; เริ่ม `draft`
- Default เก็บ WO items ระดับแรกของ BOM
- `flatten_materials=true` แตกหลายระดับแล้วสร้าง items เป็น leaf material รวม
- เก็บ unit cost/material cost snapshot ไว้ใน WO items
- `plan()` เปลี่ยนเป็น planned และกำหนด start/due date
- `update()` แก้ WO ที่ยังไม่ completed/cancelled

## State machine จาก transitionStatus()

| Current | Next ที่อนุญาต |
|---|---|
| `draft` | `planned`, `in_progress`, `cancelled` |
| `planned` | `in_progress`, `cancelled` |
| `in_progress` | `quality_check`, `cancelled` |
| `quality_check` | `completed`, `in_progress`, `cancelled` |
| `completed` | ไม่มี |
| `cancelled` | ไม่มี |

Target เท่ากับ current คืน WO เดิม; transition อื่นนอกตาราง throw DomainException
Service ใช้ DB transaction สำหรับ transition และผลข้างเคียง

## Release → เบิกวัตถุดิบ

- Target `in_progress` ต้องมี source warehouse
- Optional `issued_items` keys ต้องเป็น item ของ WO นี้; quantity ต้อง numeric และมากกว่าศูนย์
- ถ้าไม่ระบุ item quantity ใช้ planned quantity
- ตรวจ on-hand ด้วย sum StockMovement ของ org/warehouse/product พร้อม lock ก่อนเบิก
- Stock ไม่พอ reject ทั้ง transaction
- สร้าง `work_order_issue`, quantity/cost ติดลบ; บันทึก issued_quantity และ actual material cost
- GL Dr WIP `1160` / Cr inventory `1140` ผ่าน FinancialJournalService
- อัปเดต total/unit cost โดยใช้ labor/overhead ที่ WO เก็บ

## Quality check → Complete

- `quality_check` เก็บ actual_quantity, scrap_quantity, qc_notes, qc_passed
- Default actual=planned; default scrap=0; default qc_passed=false
- Complete ต้อง destination warehouse และ qc_passed=true
- ถ้า actual quantity ไม่บวก ใช้ payload actual หรือ planned ตาม service
- Total production cost = material+labor+overhead; unit cost หาร actual good qty
- สร้าง `work_order_receipt` ใน destination warehouse เมื่อ actual good quantity บวก
- GL ย้าย WIP เข้า inventory และ absorb labor/overhead ตาม FinancialJournalService
- Completed WO แก้ไข/เพิ่ม cost ไม่ได้ใน controller

## Cancel และต้นทุนเพิ่มเติม

- Cancel draft/planned เปลี่ยนสถานะโดยไม่มี stock return
- Cancel in_progress/quality_check คืน issued materials ด้วย `work_order_return` และ reverse WO journals
- Completed/cancelled เป็น final ไม่มี cancel ซ้ำผ่าน state machine
- `addCost()` ยอมเฉพาะ `labor`, `overhead`, amount ขั้นต่ำ 0.01 และ description
- เพิ่ม cost record และบวก cost กับ WO estimate เดิม ไม่ใช่แทนค่า estimate
- Recalculate total/unit cost หลังเพิ่ม; unit cost ตอนนี้ยังหาร planned quantity

## UI และ schema

- [BillOfMaterials.tsx](../../backend/resources/js/Pages/Manufacturing/BillOfMaterials.tsx): BOM editing/explosion/costing
- [WorkOrders.tsx](../../backend/resources/js/Pages/Manufacturing/WorkOrders.tsx): planning/release/QC/complete/costs
- [Icons.tsx](../../backend/resources/js/Pages/Manufacturing/Icons.tsx): shared visual assets ของ module
- [Manufacturing migration](../../backend/database/migrations/2026_09_25_000001_create_phase27_manufacturing_tables.php): BOM/items/WO/items/production costs และเชื่อม stock movements
- ต้องมี Product, Warehouse, inventory opening movements, GL accounts และ open accounting period ก่อน execute

## Tests ที่อ่านและข้อจำกัด

[Phase27ManufacturingTest](../../backend/tests/Feature/Phase27ManufacturingTest.php) มี assertions ครอบคลุม:

- BOM create/cost, multi-level explosion/flatten, circular dependency rejection
- WO scaling และ lifecycle พร้อม stock/GL
- Cancellation คืน stock/reverse GL, add production cost
- Permissions/tenant isolation
- Missing source warehouse, invalid issued qty/foreign item, insufficient stock
- Complete ก่อน QC/failed QC ถูกปฏิเสธ; rework flow
- Invalid cost type, submission idempotency, flattened materials WO

ไม่ได้รัน tests จึงไม่ยืนยัน concurrency หรือเงินจริงจากการอ่านเพียงอย่างเดียว
Movement currency/base currency hardcode `THB`, rate=1 ใน service นี้
QC เป็น boolean/notes ยังไม่เห็น inspection sampling, machine routing execution หรือ serial genealogy ใน module นี้
Rework `quality_check → in_progress` เข้า branch เบิกวัตถุดิบเดิมอีกครั้ง; ต้องตรวจ use case partial/rework ก่อนใช้จริง
Stock availability lock เป็น aggregate query; อย่าสรุปว่าผ่าน concurrent release ทุก database โดยไม่มีผลทดสอบ
เชื่อม [Accounting](09_accounting_fx_assets.md) และ Inventory module สำหรับความหมาย valuation/stock
