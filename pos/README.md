# Standalone POS workspace — development preview

Laravel 13 / PHP 8.3 / React 18. Config, DB, session, staff credentials, migrations และ release แยกจาก ERP ที่ `backend/`. ห้ามใช้ ERP `.env`, APP_KEY หรือ DB principal กับ POS

## สถานะ — 2026-10-27

**Local Complete / Gemini PASS; Production Pending.** Retail/Restaurant/Pharmacy, variants/table QR/kitchen polling, manager refund/void/discount/Cash In-Out, pharmacy inspection/recall, ABB/FULL replacement/print, signed checkpoints และ encrypted archive/restore/fencing พัฒนาและตรวจ localแล้ว. ไม่มี patient history

Configured POS SQLite รันครบ17 migrations / pending0; เป็นฐานใหม่ยังไม่มี staff/orders. ERP MySQL `erp` รันครบ83 migrations / pending0. [หลักฐานและ backup](../document/DATABASE_UPGRADE_20261027.md)

`POS_RELEASE_APPROVED=false` และ local feature flags defaultปิด; ยังไม่เปิด production. ABB/FULL เป็นเอกสาร local ที่ยังไม่ผ่าน hardware/tax production acceptance. e-Tax queue `awaiting_provider` จนเลือก provider/certificate/adapter. Transport local-only loopback HTTPS; prescription รับเฉพาะ synthetic hash allowlist. [Operations](../docs/PHASE_28_OPERATIONS.md) · [API](../document/PHASE_28_API.md) · [Schema](../document/PHASE_28_DATABASE.md)

## เรียกใช้และคู่มือแยกประเภทร้าน

เปิดหน้า `/` ของ POS เช่น **http://127.0.0.1:8128/** หลังเปิด local server. ประเภทหน้าร้านขึ้นกับสาขาของบัญชี staff + feature flags ไม่ใช่ URL แยก

- [เริ่มต้น/วิธีเปิด server/ทดลองด้วยข้อมูลจำลอง](../document/README_POS.md)
- [ร้านขายของทั่วไป — retail](../document/README_POS_RETAIL.md)
- [ร้านอาหาร — restaurant](../document/README_POS_RESTAURANT.md)
- [ร้านขายยา — pharmacy](../document/README_POS_PHARMACY.md)

คู่มือมีคำสั่งสร้างสาขา/Terminal/staff ครั้งแรกและขั้นตอนทำงานรายวัน. โหมด fixture แยกฐานจาก configured POS/ERP; โหมดใช้สาขาตนเองต้อง provision catalog/quota/keys ก่อนขาย

## Dependencies / local development

`POS_LOCAL_RESTAURANT_ENABLED=true` เปิด Restaurant เฉพาะ local/testing. `POS_LOCAL_RETURN_INSPECTIONS_ENABLED=true` เปิดตรวจของคืน Pharmacy บน loopback; ผู้ตรวจต้อง role manager + `pos_staff.pharmacist_qualified=true` และ ERP ต้อง provision authority ที่มีคุณสมบัติเดียวกัน. Default false; ไม่ใช่การยืนยันคุณวุฒิจริงหรือ production acceptance. Restock รอ ERP ตรวจ source/lot/cost และไม่เพิ่ม quota กลับเอง

Browser fixtures เพิ่ม: หลัง `prepare.php` ใช้ `prepare-restaurant.php` + `restaurant.cjs` หรือ `prepare-pharmacy.php` + `inspection.cjs`. ทั้งหมดใช้ temp SQLite เดิมและเก็บหลักฐานไว้ ไม่ล้างข้อมูล. ต้องเปิด local server ตามคำสั่งด้านล่าง; scripts บล็อก external origins

Production ต้อง install dependencies ภายใน workspace นี้ด้วย Composer/npm และออก lockfiles ที่ตรวจแล้ว. ในเครื่องพัฒนาปัจจุบันทดสอบโดยใช้ dependencies ที่มีอยู่ใน `backend/` แบบ explicit; ไม่แชร์ DB/session หรือข้อมูล ERP

```powershell
# จาก repository root
node backend/node_modules/typescript/bin/tsc -p pos/tsconfig.json --noEmit
node backend/node_modules/vite/bin/vite.js build --config pos/vite.config.mjs

# POS isolated tests (SQLite :memory:; test bootstrap เปิด vendor fallback เฉพาะ local)
Set-Location pos
php ../backend/vendor/phpunit/phpunit/phpunit -c phpunit.xml
```

## Browser test ด้วยข้อมูลจำลอง

Fixture scripts บังคับ SQLite ที่ `temp/phase28-pos-browser/database.sqlite` เท่านั้น และใช้ private key/บัญชีจำลองเฉพาะ fixture. ไม่รัน ERP migrations หรือเชื่อม e-Tax/network ภายนอก

```powershell
# จาก pos/
php tests/Browser/prepare.php
php -S 127.0.0.1:8128 -t public tests/Browser/router.php

# อีก terminal จาก repository root; ส่ง absolute node_modules ที่มี playwright เป็น argument
node pos/tests/Browser/checkout.cjs <playwright-node_modules>
```

Browser test บล็อกทุก origin ยกเว้น `127.0.0.1:8128`; ตรวจ login/open shift/checkout/change/close shift และ responsive 1440/390 px. Fixture คงหลักฐานเดิมเมื่อรันซ้ำ ไม่ล้างฐานที่มีอยู่. ใช้บัญชีจำลอง `cashier@pos.test` / `local-fixture-password` เฉพาะ test server นี้

## Integration boundaries

> **หมายเหตุ — ยังไม่ได้ทดสอบกับระบบและอุปกรณ์จริง:** ขั้นตอนเชื่อมต่อด้านล่างเป็นแนวทางเตรียมติดตั้ง ยังไม่ได้ทดสอบบน Server/เครือข่ายจริง (firewall/mTLS), เครื่องพิมพ์ใบเสร็จ, เครื่องสแกนบาร์โค้ด หรือบริการ e-Tax จริง รวมถึง load/concurrency และ backup/recovery บนสภาพแวดล้อมใช้งานจริง. ผลตรวจรับเป็น local development เท่านั้น ต้องทดสอบการเชื่อมต่อจริงก่อนใช้งาน. e-Tax provider/certificate และ adapter ยังรอการเลือกผู้ให้บริการ.

- Connector ingress ต้องมี mTLS ที่ web server ยืนยันจริงและกำหนด server variables `SSL_CLIENT_VERIFY`/`SSL_CLIENT_FINGERPRINT` จาก verified certificate; ห้าม map จาก HTTP client headers
- `POS_CONNECTOR_BRANCH_IDS`/`POS_CONNECTOR_SCOPES` ต้อง provision ใน POS environment; default ว่าง deny ทุก operation
- Local tests ป้อน certificate server variables จำลองเพื่อทดสอบ authorization; ไม่ใช่การพิสูจน์ TLS/firewall จริง
- ERP endpoints `/pos-integration`, pause และ replay ใช้ ERP user permissions/re-authentication; ไม่มี public ERP write endpoint สำหรับ POS
- ฐาน ERP/POS ตาม config รัน migrations ครบแล้ว ดู [ผลอัปเดตฐานข้อมูล](../document/DATABASE_UPGRADE_20261027.md). ฐาน POS ใหม่ยังต้อง provision ก่อนใช้ขาย
- Quota partition, freeze/reclaim, counters/watermark และ restore fencing ทำและตรวจ local แล้ว. ห้ามคืน quota จาก timeout/revoke เอง; การทดสอบบนระบบจริงเป็นไปตามหมายเหตุข้างต้น
- Retention ออนไลน์ขั้นต่ำ90วันและ encrypted archive/restore พร้อม sticky hold ทำแล้ว; ไม่ลบ pending/review/hold/source evidence/dedupe อัตโนมัติ. ดู [operations](../docs/PHASE_28_OPERATIONS.md) สำหรับ backup/recovery

## ข้อจำกัดก่อนขยาย

ERP `payments` เดิมยังคง unique one-reversal-per-receipt. Partial POS refunds ใช้ `pos_import_refunds` เชื่อม source refund/original payment/Credit Note/refund Payment ชัดเจน; generic ERP reverse/void/CN-DN ของ POS ถูกกัน. ไม่ปลด unique guard เดิม และ original POS sale/receipt/signed inbox ไม่ถูกเขียนทับ

Local Retail menu ใช้ `POS_LOCAL_PUBLIC_MENU_ENABLED=true`, APP_ENV=local และ loopback APP_URL. Manager สร้าง/revoke QR, anonymous ส่งคำขอ, cashier/manager ตรวจรับ แล้วจ่ายเงินสดผ่าน checkout เดิม. ส่งคำขอยังไม่กัน Stock/รับเงิน; สินค้าต้อง explicit online_visible และไม่เปิด Pharmacy menu

Local refunds ใช้ `POS_LOCAL_REFUNDS_ENABLED=true` แยกใน POS และ ERP. Manager ต้องยืนยันรหัสผ่านทุก command และมี open shift/เงินใน drawer พอ; ERP ต้องมี source manager ใน `pos_staff_authorities`. ใช้ราคา/conversion/base_step/account mapping ต้นฉบับ ไม่ใช้ config ใหม่ย้อนหลัง. Legacy evidence ไม่ครบพักตรวจ; physical return รอ inspection ไม่คืน quota/COGS เอง. ทั้งสอง feature ปิด default และ hard-block production

Warehouse-null legacy stock ไม่ใช้เป็นสิทธิ์ POS โดยปริยาย; เมื่อมี entitlement แล้ว outbound ที่ไม่ระบุ warehouse ถูกบล็อก และ lot-tracked stock ต้องมี allocation ที่ชัดเจน

## Local acceptance / recovery

ขอบเขตล่าสุด ผลตรวจ และ provisioning/backup/restore/archive/hold runbook: [Phase 28 local acceptance](../document/PHASE_28_LOCAL_ACCEPTANCE.md). Local ABB/Full พร้อม print/reprint; e-Tax queue awaiting_provider จนกว่าจะมี provider contract จริง. ห้ามเปิด production จากผล local.
