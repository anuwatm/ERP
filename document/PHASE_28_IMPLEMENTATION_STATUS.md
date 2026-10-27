# Phase 28 — Implementation work register

**Current acceptance:** [Geminiหมวด17](../gemini.md) local PASS; configured ERP/POS pending0 และ migration-fix regression46/422. [Current summary](../docs/PHASE_28_POS.md) · [API](PHASE_28_API.md) · [Schema](PHASE_28_DATABASE.md) · [Operations](../docs/PHASE_28_OPERATIONS.md). เนื้อหา dated work log ด้านล่างเป็นประวัติ ต้องไม่ใช้ pending statements เก่าแทนสถานะปัจจุบัน.

สถานะล่าสุด 2026-10-27: Gemini ตรวจรับ local PASS ตาม gemini.md หมวด 17. อัปเดตฐาน ERP/POS ตาม config แล้วตามคำสั่งผู้ใช้; ดู [database upgrade](DATABASE_UPGRADE_20261027.md). ยังไม่ใช่ production sign-off. ให้ยึด [handoff ล่าสุด](PHASE_28_LOCAL_ACCEPTANCE.md) เมื่อขัดกับประวัติด้านล่าง

## Authorization / scope

- อนุมัติเพิ่มเติม 2026-10-25: ผู้ใช้ตอบ “อนุญาตพัฒนาและทดสอบ local เท่านั้น” สำหรับ Connector outbound และ upload/download ใบสั่งยา. ใช้ข้อมูลจำลอง, remote/production ยังปิด; คำอนุมัตินี้ไม่ได้อนุมัติส่งข้อมูลไป Server จริงหรือเก็บใบสั่งยาจริง
- ผู้ใช้อนุมัติใช้ Architecture/Security drafts 28.2–28.3 เป็นฐานพัฒนา รวม partitioned quota และหยุดขายเมื่อ quota หมดระหว่าง ERP outage
- ทำรุ่นแรก 28.2–28.7; 28.8 คง deferred
- ยังไม่มี e-Tax provider/channel และ Server จริง; มีเครื่องพิมพ์ตาม D01 แต่ยังไม่ทราบรุ่น/driver และยังไม่ทดสอบ hardware. สั่งทดสอบเท่าที่ทำได้ใน local
- คำยืนยันล่าสุด 2026-10-26: ยังไม่มี e-Tax provider; signing owner ต้องเลือกบุคคลได้ พัฒนาเป็น org-scoped active ERP user selector แล้ว โดยยังไม่ผูก certificate/เปิด signing/submission. ผู้ใช้ยืนยันของคืน Pharmacy ให้ผู้จัดการที่เป็นเภสัชกรตรวจและเลือกคืน Stock/ทำลายได้
- ข้อปฏิเสธเดิมเรื่อง transport/prescription ได้รับคำยืนยัน local-only แล้ว: เพิ่ม adapter เฉพาะ loopback HTTPS และไฟล์จำลองที่ allowlist hash; ทั้งสองส่วนปิดเป็นค่าเริ่มต้น และ hard-block production. ดู [ขอบเขตและผลตรวจ](PHASE_28_LOCAL_TEST_SECURITY.md); ยังไม่อนุมัติ remote egress/ข้อมูลสุขภาพจริง

## ประวัติ runtime ก่อน sign-off (2026-10-25–26)

| พื้นที่ | ไฟล์/ขอบเขต |
| --- | --- |
| Stock extraction | `backend/app/Services/InventoryMovementService.php`; existing Product row locks คุม writer ทุก warehouse/lot แม้ ledger ว่าง, entitlement guards และ source identity |
| ERP writers | Delivery Order, receipt/manual adjustment, transfer/count และ manufacturing เรียก shared service; lock ก่อนคำนวณต้นทุน; POS-linked DO ไม่ตัดซ้ำ |
| ERP schema | migrations 2026_10_25_000001–000003: entitlement/source, instances/catalog/inbox/conflicts/import links และ permissions |
| ERP import | signature/catalog/scope/totals/grant validation, financial+inventory stages, financial evidence คงอยู่เมื่อ inventory_pending, one Invoice/Payment path, explicit validated per-org account mappings |
| ERP monitoring | `/pos-integration` พร้อม tenant scope, pause และ authorized replay; ไม่เปิด inbound POS Write API |
| POS workspace | `pos/` เป็น Laravel application แยก config/bootstrap/routes/schema/session/staff/DB จาก ERP; Composer dependencies แยก, local tests ใช้ vendor ร่วมแบบ explicit |
| Retail Core | branch/terminal/shift, exact-decimal cash checkout, FEFO quota consumption, immutable snapshots, signed transactional outbox, replay conflict/rollback |
| POS security | staff auth/idle+absolute timeout, mTLS ingress verification + branch/scope allowlists, no ERP credentials; production checkout ปิดจน release acceptance อนุมัติ |
| POS UI | React/Vite login/catalog/barcode filtering/cart/cash/shift/receipt sample; เอกสารตัวอย่างไม่ใช่ ABB/Full/e-Tax ที่ผ่านรับรอง |
| Local-only extension | Connector HTTP/TLS options + bounded loopback destination, synthetic prescription quarantine/private ACL/audit/checkout rollback, Pharmacy conversion/pharmacist authority และ quota freeze/reclaim evidence |
| Public menu — local Retail | QR token เก็บเฉพาะ hash/expire/revoke, explicit online visibility, anonymous request + dedupe, staff accept/reject/re-review, checkout เดิม claim request ครั้งเดียว; ยังไม่รับเงินหรือกัน Stock ตอนส่งคำขอ |
| Refunds — local | Manager re-auth ผูก command/หมดอายุ, partial/full source quantities + cumulative rounding, immutable conversion/precision, cash payout จากกะปัจจุบัน, quarantine-only returns, signed outbox/branch scope และ rollback ทั้งรายการเมื่อ signer เสีย |
| ERP refund import — local | ตรวจ signature/registered manager/original source, dependency_pending ก่อน sale, dedicated partial-refund subledger เชื่อม receipt/Credit Note/Payment/GL; ไม่เปลี่ยน one-reversal constraint เดิม, ไม่ restock/คืน COGS อัตโนมัติ |
| Refund reporting | Invoice net amounts + original source snapshot; tax report แสดง original gross แล้วหัก Credit Note ครั้งเดียว, ledger copy คงยอดต้นฉบับ; generic ERP reverse/void/CN-DN ของ POS ถูกกันเพื่อรักษา source correlation |
| Cash In-Out — local | Fresh manager approval, dedupe/overdraw guard, signed cash.moved และ shift cash ledger ใช้ decimal ทั้งทาง; ERP ใช้ explicit trusted cash_clearing asset mapping โพสต์ internal transfer ไม่สร้าง Invoice/Payment/revenue/VAT/Stock |
| ERP ledger checks | `/pos-integration/{instance}/reconciliation` แยก permission pos.reconcile/org scope, source amounts/net refunds/Payment/GL totals/stock correlation; date inclusive สูงสุด 31 วัน, จำกัด 100 records/type + 1000 allocations. source_checkpoint=not_available จึงไม่รับรอง remote completeness |
| Authorized discount — local | ERP publish max_manager_discount_pct ต่อ SKU (default 0), fresh manager password ผ่าน typed command approval, ผูก cashier/cart/unit/terminal/catalog, expire 5 นาที + single-use ใน checkout transaction; ERP ตรวจ signed evidence/manager authority/ceiling และ unique approval ข้าม sale |

## งานค้างเดิมก่อนพัฒนารอบ 2026-10-27 (ประวัติ)

- Provisioning/admin configuration ครบ, production quota protocol/restore fencing และ targeted concurrency บน MariaDB (freeze/reclaim ทำ local-only แล้ว)
- Live transport หลัง provision destination/egress, bounded network recovery/backoff และ two-server integration
- Restaurant modifier/size/extra recipe variants, public menu/table scope และ production readiness; reservation/kitchen/recipe/split bill/waste ทำ local แล้วตามผลด้านล่าง. Pharmacy recall/production readiness ยังเหลือ; inspection-to-stock แบบ local เพิ่มแล้ว
- Void workflows/production discount/cash/refund/inspection policy, full tax issuance/replacement และ e-Tax provider/signing/submission จริง
- Signed POS checkpoint/full reconciliation, archive/retention/restore และ production operations/security/UAT ตาม checklist 28.7; ERP-only ledger checks มี UI แล้ว ไม่ใช่ full reconciliation

ไม่ติ๊ก phase ว่าเสร็จเพียงเพราะมี schema หรือ tests บางส่วน; รายการที่ยังไม่ implement ไม่ใช่ external blocker ทั้งหมด ต้องทำต่อ

## ประวัติ validation ระหว่างพัฒนา (ไม่ใช่จำนวนล่าสุด)

- ERP selected regression 2026-10-26: 82 tests / 624 assertions ครอบคลุม Phase28, Payments, Tax Reports, Commercial Documents, GL, Inventory/Operations/FX และ Manufacturing; ชุดนี้ต่างจาก baseline เดิม 97/581 จึงไม่บวก/เปรียบเทียบจำนวนตรง ๆ
- ERP focused import/transport หลังตรึง account snapshot และเพิ่ม worker dependency recovery: 18 tests / 140 assertions; ทับซ้อน selected regression ไม่บวกจำนวนรวม
- POS isolated testsล่าสุด: 29 tests / 136 assertions รวม discount/cart binding/manager revocation/signer rollback/refund net amount
- ERP focused import/transport ล่าสุดหลัง discount: 21 tests / 189 assertions; ชุดทับซ้อน ไม่บวกจำนวนกับ regression ก่อนหน้า
- ERP หลัง Cash In-Out: selected Finance/GL/Phase28 regression 47/453; หลังเพิ่ม ledger checks focused import 16/161. จำนวนทับซ้อนชุดข้างต้น ไม่บวกกัน
- POS browser fixture: login/open shift/cash checkout/change/close shift, viewport 1440/390 และ console errors=0 ผ่าน; ภาพ/result อยู่ `temp/phase28-pos-browser/`
- ERP และ POS TypeScript/build ผ่าน; จัด format ด้วย Pint และตรวจ diff/links แล้ว
- PHP tests ใช้ SQLite `:memory:` ไม่ migrate ฐานข้อมูลใช้งานจริง; ไม่ใช้ SQLite เป็นหลักฐานพิสูจน์ row-lock concurrency บน MariaDB

Browser ใช้ SQLite file เฉพาะ fixture ใน temp แทน `:memory:` เพื่อให้ HTTP หลาย request ใช้ข้อมูลเดียวกัน. Local test server ปิดหลังตรวจแล้ว

ได้รับคำตอบ async approval local-only แล้ว และทำส่วนที่อนุมัติตาม [ขอบเขต local-only](PHASE_28_LOCAL_TEST_SECURITY.md). ไม่ได้เปิด production หรือ remote transport. Restaurant/ภาษี/e-Tax/operations และรายการค้างอื่นยังต้องทำต่อ ไม่ถือว่าคำขอทำทั้งหมดสำเร็จแล้ว

## Local implementation — 2026-10-26

- Public menu ใช้ `POS_LOCAL_PUBLIC_MENU_ENABLED=true` เฉพาะ local/testing + loopback HTTP. ไม่เผย cost/stock/staff และไม่เปิดเมนู Pharmacy. QR ใส่ token ใน URL fragment, API รับ token ใน body;ไม่ใส่ token ใน path/query/access log
- Refund ใช้ `POS_LOCAL_REFUNDS_ENABLED=true` ใน POS/ERP แยกกัน; default false และ production hard-block. ERP ต้อง provision `pos_staff_authorities.role=manager` ของ instance นั้นก่อนรับ signed refund
- `sale.refunded` ใช้ aggregate_id=refund UUID, payload.sale_id/source_line_id ผูกต้นฉบับ. Outbox มี branch_id สำหรับ scope poll/results ที่ไม่จำกัดเฉพาะ sale aggregate. Refund ไม่ปล่อย quota และไม่สร้าง StockMovement; สินค้าคืนรอตรวจ/lot verification ก่อนงาน inspection ที่ยังไม่ implement
- Credit Note/partial reversal เชื่อม original payment ใน `pos_import_refunds` ชัดเจน. ไม่แก้ unique reversal_of_payment_id เดิม; generic ERP reversal ของ POS receipt ถูกปิด. Journal ใช้ typed persisted account mapping เดิม และ period locks; transaction rollback ครบเมื่อโพสต์ไม่ผ่าน
- Sale เก็บ trusted `account_snapshot` จาก ERP provisioning เดิม; refund และ inventory retry ใช้ snapshot นี้และตรวจ account ยัง active/postable/org ถูกต้อง. ไม่รับ GL mapping จาก payload หรือเปลี่ยนตาม provisioning รุ่นใหม่
- Worker retry dependency_pending เมื่อ original sale พร้อมแล้ว โดยกรอง existence ใน query ก่อน bounded limit; รายการขาด parent ไม่กินรอบ retry หรือขวาง pending งานอื่น. Manual needs_review ยังต้อง authorized replay
- Legacy sale ที่ไม่มี immutable snapshot/precision evidence จะพักตรวจ ไม่ใช้ราคา/อัตราแปลงปัจจุบันแทน. ต้นทุนของคืนยังไม่รับกลับจน inspection; ไม่ตีความ refund accepted ว่า stock return ตรวจเสร็จ
- Browser เพิ่ม QR→request→staff acceptance→shared checkout→customer completed และ manager sale→refund→quarantine→close shift; viewport 1440/390, external requests ถูก block, ไม่มี JS error. ผลอยู่ `temp/phase28-pos-browser/menu-result.json` และ `refund-result.json`
- Cash In-Out ใช้ POS_LOCAL_CASH_MOVEMENTS_ENABLED=true ใน POS/ERP แยกกัน, default false + production hard-block; manager open shift, command-specific re-auth, local synthetic reference. ERP ต้องตั้ง account_codes.cash_clearing เป็นบัญชี asset/postable/active ของ org ที่ต่างจาก mapped accounts เดิม; ไม่มี fallback หรือ client-supplied GL
- Cash ledger แยก opening cash/sales/refunds/cash movements, ไม่ใช้ยอดปิดกะสร้างยอดเงินสดองค์กร. Browser cash_in=20/cash_out=30/opening=100/close=90 ผ่าน 1440/390 ไม่มี JS errors; `temp/phase28-pos-browser/cash-movements-result.json`
- Ledger checks เป็น readonly transaction และ current evidence checks แม้เลือกช่วงวันอดีต; invoice net เทียบ cumulative refunds ทุกวัน. ไม่รวม opening cash เป็น cash flow และไม่รวมหลาย currency ปนกัน. Tests ตรวจ drift, permission และ tenant scope ผ่าน; UI ตรวจ TypeScript/build, ยังไม่ได้ browser UAT ของหน้า ERP นี้
- Discount ใช้ POS_LOCAL_DISCOUNTS_ENABLED=true แยก POS/ERP, default false + production hard-block. เพดานต้อง publish โดย ERP ชัดเจนก่อน; 20% ใน tests/browser เป็น synthetic policy ไม่ใช่ค่าธุรกิจ default. manager ของสาขายืนยัน password ให้ cashier/เภสัชกรได้โดยไม่เปลี่ยนผู้ขาย; ไม่เก็บ password ใน approval/event/audit
- Approval ผูก normalized cart/หน่วย/quantity/terminal/catalog/requester/command hash, expire 5 นาที และใช้ได้หนึ่ง sale. Checkout ล็อก staff ตาม UUID order ก่อน terminal/branch เพื่อคุม revocation และลด lock-order inversion; เปลี่ยน cart/catalog หรือ manager ถูก revoke ต้อง review ใหม่. Signer failure rollback quota/sale/approval consumption พร้อมกัน
- ส่วนลดลด taxable subtotal ก่อนคำนวณ VAT; ERP สร้าง Invoice/Payment/GL ตามยอดสุทธิและคง quantity/COGS เดิม. Refund คำนวณจาก original net snapshot ไม่จ่ายคืนราคาเต็มก่อนส่วนลด. Browser discount 2.00 → taxed net 8.56/change 11.44 ผ่าน 1440/390 ไม่มี JS errors; `temp/phase28-pos-browser/discount-result.json`

## Restaurant / signing owner / Pharmacy inspections — 2026-10-26

- Restaurant: local-only `POS_LOCAL_RESTAURANT_ENABLED`, โต๊ะ/กลับบ้าน, reserve วัตถุดิบต่อ portion, sent → acknowledged → preparing → ready → served; consume ที่ preparing ครั้งเดียว. จ่ายหลังเสิร์ฟ/เลือกแยกบิลใช้ checkout และ Invoice/Payment เดิม ไม่สร้าง Work Order หรือ Stock/COGS ซ้ำ. Manager ยกเลิกก่อนใช้คืน reservation; ของเสียก่อนใช้ consume ครั้งเดียว หลังใช้ reclass ต้นทุน ไม่ตัดวัตถุดิบซ้ำ
- เพิ่ม `RestaurantPanel.tsx`: โต๊ะ/ออเดอร์/ครัว/เสิร์ฟ/ยกเลิก/เลือกจ่ายแยกบิล/preview ยอดจาก server. Browser เปิดโต๊ะ→ครัว→เสิร์ฟ→แยก 2 บิล บิลละ 10.70→ปิดโต๊ะและกะ ผ่าน 1440/390 ไม่มี JS error; `temp/phase28-pos-browser/restaurant-result.json`. จอครัวใช้ปุ่มตรวจสถานะ refresh; ยังไม่เพิ่ม push/live polling
- ERP reconciliation ตรวจ kitchen refs/claim/hash, ingredient StockMovement, original kitchen cost และ journal ของ prepared sale เพิ่มแล้ว. ไม่ถือว่าเป็น signed checkpoint/full remote reconciliation และยังไม่ตรวจ kitchen/waste ที่ไม่ผูกยอดขายครบทุกชนิด
- Signing owner: `pos_tax_settings` แยก org, เลือก/เปลี่ยน/ล้าง active user ในองค์กรเดียวกันผ่าน POS Integration ด้วย `pos.manage` + recent password confirmation + audit. ไม่มี provider/certificate/signing/submission หรือผลรับรองภาษีจากการเลือกบุคคล
- Pharmacy inspection: `POS_LOCAL_RETURN_INSPECTIONS_ENABLED` default false/production hard-block ทั้ง POS/ERP. ต้อง role manager + `pharmacist_qualified=true` ซึ่ง provision แยกทั้ง POS staff และ ERP authority; default false และไม่ยอมให้ cashier ตั้งคุณสมบัติตัวเอง. การเป็น manager อย่างเดียวตรวจไม่ได้
- รับเฉพาะของคืนที่มี lot ต้นฉบับและ conversion/precision snapshot ครบ; ตรวจปริมาณรวมต่อ refund line และ allocation ข้าม refund. คืนขายต้องยืนยัน packaging/storage/lot และไม่หมดอายุทั้งวันตรวจ/วัน ERP รับคืน. Legacy ที่ evidence ไม่ครบพักตรวจ
- signed `return.inspected` ผ่าน outbox/inbox เดิม: ขาด refund เป็น dependency_pending; worker retry เมื่อ refund มาแล้ว. ERP คืน Stock และ credit COGS จากต้นทุน source allocation แบบ cumulative rounding ครั้งเดียว ใช้ account snapshot เดิม, shared inventory service และ journal period guards. ไม่เปลี่ยน consumed/granted quota. Destroy บันทึกหลักฐานโดยไม่ลด Stock/COGS ซ้ำ. Rollback เมื่อ signer/GL ล้มเหลว
- UI `ReturnInspectionPanel.tsx` เลือกของรอตรวจ/lot/ปริมาณหน่วยฐาน/หลักฐาน/ผลตรวจพร้อม manager re-auth; หลังบันทึกแสดงรอ ERP ตรวจรับ. Browser restock/destroy ผ่าน 1440/390 ไม่มี JS error; `temp/phase28-pos-browser/inspection-result.json`
- Validation: ERP Phase28 ทั้งชุด 33 tests / 290 assertions; POS ทั้งชุด 33 / 173 ก่อนเพิ่ม expiry/storage assertions, focused inspection ล่าสุด 1 / 10. จำนวนทับซ้อน ไม่บวกกัน. TypeScript ERP/POS และ POS Vite ผ่าน; Pint ผ่าน. ใช้ SQLite :memory: และ temp browser fixture เท่านั้น ไม่ migrate ฐานใช้งานจริง
- ยัง In Progress: provisioning/admin ครบ, modifiers/online restaurant table flow, recall, void/full tax/e-Tax, signed checkpoints/retention/restore fencing และ operations acceptance เป็นงานที่ต้องทำต่อ; บางส่วนยังเป็นงานพัฒนา ไม่ใช่ external blocker ทั้งหมด
