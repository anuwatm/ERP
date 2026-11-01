# Phase 28.8.1 — Local acceptance 2026-10-30

**Local operational scope complete ตามขอบเขตผู้ใช้: Retail/Restaurant, prepaid cash + COD custody; Pharmacy Delivery ปิด.** COD/fees เป็น policy_pending. ไม่ใช่ accounting/production/Gemini sign-off

[คู่มือและ state diagram](../../PHASE_28_8_1_DELIVERY.md) · [Checklist](../../../checklist.md)

## ผลสุดท้าย

| ตรวจ | ผล/หลักฐาน |
| --- | --- |
| POS regression | 61 tests / 432 assertions — [log](pos-regression.log) |
| ERP Phase28/Manufacturing | 62 tests / 509 assertions ผ่าน — [log](erp-regression.log); ไม่ใช่ full ERP suite |
| Chrome desktop/mobile | สอง lifecycles 1280/375 ผ่าน — [JSON](browser.json), [log](browser.log), [mobile](viewport-375.png), [desktop](viewport-1280.png) |
| POS signatures → ERP services | 35 revisions accepted, checkpoint verified/issues=[] — [JSON](roundtrip.json), [log](roundtrip.log) |
| Public-only independent verification | RSA-SHA256 35/35 copied envelopes ผ่าน — [ผล](signature-verification.json), [public key](synthetic-public.pem), [events](signed-revisions.json), [checkpoint](signed-checkpoint.json) |
| PHP/JS/build | Pint, syntax, POS TypeScript/Vite ผ่าน — [style](php-style.log), [syntax](php-syntax.log), [build](frontend-check.log) |
| Cleanup | owned server ปิด, fixture manager disabled, sessions/private key ลบแล้ว — [ผล](cleanup.json) |

Browser ทดสอบ create/fees/reserve/stale409/dispatch/deliver; simulate response หายหลัง collect commit แล้ว retry request เดิม; remit/receive/refund/restock; ตรวจ drawer evidence 2 รายการ, reservation ของงานไม่ค้าง และไม่มี POS sale/payment เกิดจาก COD. สองขนาดจอเป็นสอง lifecycle ไม่ใช่ 32 independent cases. ตรวจภาพ viewport แล้ว; ไม่มี page errors/horizontal overflow

ERP ทดสอบ worker ปลุก delivery dependency เมื่อ immediate predecessor มาแล้ว; หาก version3 ยังขาด version4 ต้องรอโดยไม่ใช้ attempts เพิ่ม. มี regression ว่า default-off worker ทำงานได้แม้ยังไม่มี delivery table. ก่อนแก้ reproduced accepted→dependency_pending; ผลใน final log เป็นหลังแก้

Roundtrip ใช้ signed POS outbox/checkpoint จริงจาก synthetic fixture เข้า isolated ERP service. before=after: Invoice0/Payment0/Journal0/StockMovement0. ไม่ใช่ target two-server HTTP/mTLS และไม่ได้พิสูจน์ production transport/ACK. ชุดตรวจมีส่วนทับซ้อน ไม่บวกจำนวน tests/revisions/lifecycles เป็นยอดรวม

## ทำซ้ำ

ใช้ PHP/Node ที่ติดตั้งกับโปรเจกต์; รันที่ workspace root:

```powershell
C:/AppServ/php8/php.exe backend/vendor/phpunit/phpunit/phpunit -c pos/phpunit.xml
```

ERP ต้องรันจาก backend/ เพื่อใช้ phpunit.xml ที่บังคับ testing/SQLite :memory::

```powershell
C:/AppServ/php8/php.exe vendor/phpunit/phpunit/phpunit --filter "Phase28|Manufacturing"
```

Browser ใช้ [prepare](../../../pos/tests/Browser/prepare-delivery.php), [environment](../../../pos/tests/Browser/delivery-environment.php), [router](../../../pos/tests/Browser/delivery-router.php) และ [scenario](../../../pos/tests/Browser/delivery.cjs). ต้องเริ่มจาก temp/phase2881-delivery ใหม่; fixture ที่ sealed มี cleanup.json และ prepare จะปฏิเสธการใช้ซ้ำ. เก็บ fixture เก่าแยกก่อนสร้างใหม่ ห้ามเขียนทับหลักฐานเดิม

```powershell
C:/AppServ/php8/php.exe pos/tests/Browser/prepare-delivery.php
```

เริ่มเฉพาะ hidden PHP fixture server ที่ 127.0.0.1:8143 ใช้ pos/public เป็น document root และ pos/tests/Browser/delivery-router.php เป็น router. จากนั้นรัน:

```powershell
node pos/tests/Browser/delivery.cjs
C:/AppServ/php8/php.exe pos/tests/Browser/delivery-checkpoint.php
C:/AppServ/php8/php.exe backend/tests/Browser/verify-delivery-roundtrip.php
node backend/node_modules/typescript/bin/tsc -p pos/tsconfig.json --noEmit
node backend/node_modules/vite/bin/vite.js build --config pos/vite.config.mjs
```

Fixtures มี path/config guard บังคับ temp DB แยก; ไม่ใช้ .env จริง. Scenario ล็อก network ไว้เฉพาะ loopback fixture. Script ใช้ bundled Playwright/Chrome path ของเครื่องนี้; เครื่องอื่นต้องปรับ runtime path. หลังทดสอบหยุดเฉพาะ owned PID ที่ตรวจ command line ตรงกัน, disable synthetic manager, ลบ fixture sessions/private key และ seal cleanup marker. ห้ามทดสอบด้วยบัญชีหรือฐานลูกค้าจริง

## ข้อจำกัด release

- COD เป็น cash-custody evidence ไม่มี ABB/FULL/Payment/GL หรือ ERP inventory posting อัตโนมัติ. ส่งสำเร็จยังถือ reservation รอ settlement policy. ต้องตรวจรับบัญชี/ภาษี/COGS/account mapping ก่อนเปิด financial release
- Fees ใช้ local server-approved candidate rates; promotion engine อยู่28.8.2. Browser offline/PWA อยู่28.8.3. Partial prepaid refund เดิมต้องกระทบยอดแยกก่อน return bridge
- **ยังไม่ได้ทดสอบกับระบบจริง**: branch/device/printer/provider, target two-server/mTLS, MariaDB concurrency
- Migration code totals POS19/ERP84; เพิ่ม POS2/ERP1 แต่ไม่ได้ migrate configured DB หรือเปิด flag จริง
- ไม่ส่งโค้ดรอบนี้ให้ Gemini; สิทธิ์ POSConfig6ไฟล์เดิมไม่ครอบคลุมส่วนนี้

[SHA-256 manifest](manifest.json) ครอบคลุมไฟล์หลักฐานและรายงานในโฟลเดอร์นี้. ไม่เก็บ private key, session, DB, .env หรือข้อมูลลูกค้าจริง
