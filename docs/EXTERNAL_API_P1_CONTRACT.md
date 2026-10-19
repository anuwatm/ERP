# P1 — Reference / Products / Inventory contract

เริ่ม implementation 2026-10-19 ตามคำสั่งผู้ใช้ให้ทำ P1–P7 ต่อและส่ง Gemini ตรวจรวมท้ายรอบ ไม่รอ P0 acceptance เดิม; deployment/integration และ SLA ยังคง deferred

เงินเดือน บัญชีธนาคาร และ secret ทั้งหมดไม่ออก API ตามคำสั่งผู้ใช้ รวมถึงไม่เพิ่มเข้า Employee basic profile

## Endpoints และ scopes

| GET path ใต้ /api/v1 | Scope | Fields / policy |
| --- | --- | --- |
| reference/branches | reference:organization:read | id, code, name, status |
| reference/divisions | reference:organization:read | id, code, name, status, branch_id |
| reference/departments | reference:organization:read | id, code, name, status, branch_id, division_id |
| reference/positions | reference:organization:read | id, code, name, status, department_id |
| reference/warehouses | inventory:read | id, code, name, status, branch_id |
| reference/currencies | reference:currencies:read | id, code, name, decimal_places, status |
| products / products/{id} | products:read | id, sku, name, type, category, unit, is_active, track_inventory, updated_at |
| inventory/balances | inventory:read | product_id, warehouse_id, warehouse_bin_id, inventory_lot_id, on_hand (decimal string 4 places) |
| summaries/inventory | summary:inventory:read | current snapshot of tracked products and low-stock counts using actual reorder_point |

ไม่มี Units master ใน schema จึงไม่สร้างข้อมูลอ้างอิงสมมติ; unit อ่านจาก Product เท่านั้น ราคาและต้นทุนต้องมี products:prices:read / products:costs:read เพิ่มจาก products:read ไม่ให้ scope พื้นฐานอ่านราคา/ต้นทุนโดยปริยาย

## List / sync

JSON envelope data/meta, max 100/default 50 ต่อหน้า; opaque authenticated cursor มีอายุ 30 นาที ผูก key/org/scopes/path/filters; sort updated_at,id แบบ ascending และ high-water timestamp ต่อรอบ; ไม่รับ arbitrary sort/include/fields/org_id

Products รองรับ search (max 100), type, is_active; reference ใช้ cursor/limit/updated_since เท่านั้น `updated_since` RFC3339 UTC inclusive; client upsert/dedupe และ full reconciliation เสมอก่อนสลับสิทธิ์หรือหลัง cursor หมดอายุ ไม่อ้าง transaction snapshot ขณะเขียนพร้อมกัน

Soft-deleted resource ส่ง tombstone id/deleted_at เมื่อ delta sync; reference master ที่ hard-delete ได้ต้อง full reconciliation ไม่มีการอ้าง complete change feed; fields ความสัมพันธ์ต้องเป็น org เดียวกันและ parent ยังมองเห็นได้ ไม่ serialize relations ทั้งก้อน

Inventory balances เป็น live aggregation ตาม product/warehouse/bin/lot ไม่มี reservation ledger จึงไม่มี reserved/available/ATP; legacy warehouse=null คงเป็น unassigned bucket ไม่เดาคลัง ไม่ส่ง cost/valuation หรือ supplier details; orphan/cross-org product/warehouse/bin/lot ถูกตัดออก

Inventory low stock: active tracked non-deleted products ที่ reorder_point > 0 และผลรวม signed stock quantity < reorder_point; รวม zero-stock products ด้วย ใช้ snapshot ปัจจุบัน ไม่อ้างย้อนหลังตาม end_date ไม่มีการรวม quantities ต่างหน่วยเป็นยอดเดียว

## Security / acceptance

ใช้ key guard เดิม no-store/HTTPS/rate limit/expiry/revoke ทุก request; scopes ใหม่ opt-in เท่านั้น ผู้จัดการ key ต้องมีสิทธิ์อ่านโดเมนจริงก่อนออก scope ใหม่; Summary ไม่ให้สิทธิ์ list/detail

Tests ก่อนส่งมอบ: cross-org/foreign parents, missing/deleted IDs, invalid filters/cursors, cursor context/expiry, basic vs cost/price leakage, signed fractional quantities/zero/negative stock, bounded queries/no N+1 และ regression key management/scopes เดิม
