# 07 — Procurement และ Inventory

อ่านโค้ด ณ 2026-10-24 · HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` พร้อม working tree ขณะสำรวจ
เป็น static reference; ไม่ได้รัน tests หรือรับสินค้าจริง

## ขอบเขตและไฟล์หลัก

จัดการ Supplier/Product → PR → PO → Goods Receipt → Stock Movement พร้อม Warehouse/Bin/Lot, Transfer, Count และ scan
PO/GR ไม่ใช่ Expense โดยอัตโนมัติในทุกเส้นทาง; เจ้าหนี้อ้าง PO ผ่าน Expense ได้

- [SupplierController](../../backend/app/Http/Controllers/Finance/SupplierController.php), [ProductController](../../backend/app/Http/Controllers/Finance/ProductController.php): master data
- [PurchaseOrderController](../../backend/app/Http/Controllers/Finance/PurchaseOrderController.php): store/update/approve/cancel/print/pdf
- [GoodsReceiptController](../../backend/app/Http/Controllers/Finance/GoodsReceiptController.php): รับสินค้าจาก PO, stock summary และปรับ stock
- [InventoryOperationsController](../../backend/app/Http/Controllers/Finance/InventoryOperationsController.php): warehouse/bin/lot, transfer/count/scan
- Frontend: [PurchaseOrders.tsx](../../backend/resources/js/Pages/Finance/PurchaseOrders.tsx), [GoodsReceipts.tsx](../../backend/resources/js/Pages/Finance/GoodsReceipts.tsx), [InventoryOperations.tsx](../../backend/resources/js/Pages/Finance/InventoryOperations.tsx)

## Routes และ Permissions

อ้างอิง [web.php](../../backend/routes/web.php); mutations ที่อ่านใช้ password.confirm
PO, GR และ stock movement มี throttle:10,1; operations บางเส้นทางไม่มี throttle นี้

| HTTP / URL | Method | Permission |
|---|---|---|
| GET/POST `/suppliers` | index/store | `suppliers.view/create` |
| PATCH/DELETE `/suppliers/{supplier}` | update/destroy | `suppliers.update/delete` |
| GET/POST `/purchase-orders` | index/store | `purchase_orders.view/create` |
| PATCH `/purchase-orders/{purchaseOrder}` | update | `purchase_orders.update` |
| POST `/purchase-orders/{purchaseOrder}/approve` | approve | `purchase_orders.approve` |
| POST `/purchase-orders/{purchaseOrder}/cancel` | cancel | `purchase_orders.cancel` |
| GET `/purchase-orders/{purchaseOrder}/print` หรือ `/pdf` | print/pdf | `purchase_orders.view` |
| GET `/goods-receipts` | GoodsReceipt::index | `inventory.view` |
| POST `/goods-receipts` | GoodsReceipt::store | `inventory.receive` |
| POST `/stock-movements` | GoodsReceipt::storeMovement | `inventory.adjust` |
| GET `/inventory-operations` | index | `inventory.view` |
| POST `/warehouses` | storeWarehouse | `inventory.adjust` |
| POST `/warehouses/{warehouse}/bins` | storeBin | `inventory.adjust` |
| POST `/inventory-lots` | storeLot | `inventory.receive` |
| POST `/stock-transfers` | transfer | `inventory.adjust` |
| POST `/stock-counts` | stockCount | `inventory.adjust` |
| GET `/inventory-scan?code=...` | scan | `inventory.view` |

POST สร้าง PO/GR, stock movement และ inventory operations ใช้ EnsureFormSubmission ตาม route
PR routes/permissions และการแปลง PO อยู่ใน [05](05_quotations_commercial_documents.md)

## Entities และ schema

- [PurchaseOrder](../../backend/app/Models/PurchaseOrder.php) belongsTo supplier/project; hasMany items/goodsReceipts
- [PurchaseOrderItem](../../backend/app/Models/PurchaseOrderItem.php): optional Product, quantity, price, tax และ line total
- [GoodsReceipt](../../backend/app/Models/GoodsReceipt.php) / [GoodsReceiptItem](../../backend/app/Models/GoodsReceiptItem.php): อ้าง PO/PO item และยอดรับจริง
- [StockMovement](../../backend/app/Models/StockMovement.php): quantity/cost แบบ signed; optional warehouse/bin/lot และ reference เอกสาร
- [Warehouse](../../backend/app/Models/Warehouse.php), [WarehouseBin](../../backend/app/Models/WarehouseBin.php), [InventoryLot](../../backend/app/Models/InventoryLot.php), [StockTransfer](../../backend/app/Models/StockTransfer.php)
- [procurement migration](../../backend/database/migrations/2026_08_21_000002_create_phase7_procurement_and_members.php)
- [inventory migration](../../backend/database/migrations/2026_08_23_000001_create_phase8_inventory_tables.php)
- [operations migration](../../backend/database/migrations/2026_08_29_000001_create_phase15_inventory_operations_tables.php), [GR lot migration](../../backend/database/migrations/2026_08_29_000002_add_inventory_lot_to_goods_receipt_items.php)
- [AP/inventory FX migration](../../backend/database/migrations/2026_08_28_000004_create_phase141_ap_fcd_and_inventory_fx_tables.php) เพิ่ม base cost/FX ใน GR และ movements

## PO workflow

อ้างอิง PurchaseOrderController::store/update/approve/cancel/validatePurchaseOrder

1. สร้าง draft/sent; server คำนวณยอดและ FX snapshot; ออกเลขผ่าน NumberSequenceService
2. แก้ไขได้เฉพาะ draft; UI ไม่ใช่สิ่งเดียวที่จำกัดการแก้
3. approve รับ draft/sent → approved พร้อม approved_at/by
4. cancel ห้าม received, closed, cancelled; guard นี้ยังยอม partially_received
5. Model statuses: draft, sent, approved, partially_received, received, cancelled, closed
6. ไม่มีเหตุผลให้อนุมานว่า closed มี endpoint transition เพียงเพราะอยู่ใน Model constant
7. หน้า PurchaseOrders.tsx มี create form และปุ่ม approve/cancel/print/pdf ตามสถานะและ permission

## Goods Receipt และ stock ledger

อ้างอิง GoodsReceiptController::store/poReceiptStatus/storeMovement/averageCost/validateStorageLocation

- รับได้เฉพาะ PO approved หรือ partially_received
- item ต้องเป็นของ PO; lot ต้องเป็นของ product ที่ PO item อ้าง
- quantity รับต้องไม่เกิน ordered − received ที่สะสมแล้ว
- bin ต้องมี warehouse และเป็นของ warehouse นั้น
- บันทึก GR/items และ StockMovement รับเข้าใน transaction; คำนวณภาษีตาม PO tax_mode
- PO ทุก item รับครบ → received; ไม่ครบ → partially_received
- เรียก FinancialJournalService::postGoodsReceipt เพื่อ posting
- stock summary ใช้ SUM(quantity), inventory value ใช้ SUM(base_total_cost), average cost = value/on_hand เมื่อ on_hand > 0
- manual outbound movement ห้ามทำ on_hand ติดลบ; unit_cost ไม่ระบุใช้ averageCost
- low stock notification ผูก reorder_point ผ่าน NotificationService; หน้ารายงาน location ใช้ LowStockLocations
- DeliveryOrder delivered และ Manufacturing เป็นแหล่ง movement อื่น; จึงอย่าคิดว่า ledger มีแต่ GR/manual adjustments

## Warehouse / Lot / Transfer / Count

อ้างอิง InventoryOperationsController ทุก public method

- storeWarehouse/storeBin สร้างสถานที่; storeLot เก็บ lot_no, manufactured_at, expires_at, barcode โดย expiry ต้องไม่ก่อน manufactured date
- index ส่ง lots ที่ไม่หมดอายุ ณ today; การซ่อนใน dropdown ไม่เท่ากับทุก write path ปฏิเสธ lot หมดอายุ
- transfer ต้อง source ≠ destination, quantity > 0, lot ตรง product ถ้ามี
- transaction รวม source quantity และ base_total_cost ภายใต้ query ที่เรียก lockForUpdate
- available ไม่พอ → 422; สร้าง transfer_out จำนวนลบ และ transfer_in จำนวนบวก
- ใช้สกุลฐานองค์กรและ rate=1; total stock รวมไม่เปลี่ยน
- stockCount รับจำนวนจริง ≥ 0 และ product ห้ามซ้ำใน request; สร้าง count สถานะ posted ทันที
- เทียบ counted กับ system; สร้าง movement เฉพาะส่วนต่าง; ตรงกันไม่สร้าง movement
- scan ตัด whitespace แล้วค้น barcode หรือ SKU ภายในองค์กร; code ว่าง → 422; ไม่พบ → 404

## Dependencies / Tests ที่อ่าน — ไม่ได้รัน

Supplier/Product ใช้ร่วม Expense/Invoice/Quotation; PR conversion และ DO อยู่ CommercialDocumentController
FX/GL และ Notification เป็นผลข้างเคียงที่ต้องตามเมื่อแก้ต้นทุน/รับสินค้า
[Phase8InventoryTest](../../backend/tests/Feature/Phase8InventoryTest.php): partial/full receipt, over-receive, lot/product mismatch, org scope, return/adjust/average cost และ negative stock
[Phase15InventoryOperationsTest](../../backend/tests/Feature/Phase15InventoryOperationsTest.php): transfer 10 → 6/4, currency ฐานองค์กร, stock ไม่พอ, count 10 → 7 และ barcode unique ต่อองค์กร
[Phase141ApInventoryFxTest](../../backend/tests/Feature/Phase141ApInventoryFxTest.php): ชุดอ้างอิงต้นทุนสกุลฐาน

## ข้อควรตรวจต่อ

- GoodsReceiptController::store ตรวจ PO/remaining ก่อนหรือระหว่าง transaction โดยไม่ได้ lock PO/item ในโค้ดที่อ่าน; concurrent over-receive ยังต้องพิสูจน์
- manual storeMovement ตรวจ on_hand แล้ว insert แยก; อย่าอ้างว่า negative stock ปลอดภัยภายใต้การแข่งขันทั้งหมด
- transfer ไม่ระบุ lot จะรวมทุก lot ใน source แต่ movement ใหม่ lot=null; ต้องยืนยันนโยบาย tracking
- stockCount นับระดับ product/warehouse ไม่แยก lot; การจัดสรร lot หลังปรับต้องพิจารณาเพิ่ม
- average cost ใช้ยอดสะสม ledger และ float/round; ไม่ใช่หลักฐานว่ามี FIFO หรือ FEFO allocation
