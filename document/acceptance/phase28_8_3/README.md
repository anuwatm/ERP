# Phase 28.8.3 — Local acceptance 2026-11-04

**Local Complete ตามขอบเขตที่ผู้ใช้ยืนยัน**. ไม่ใช่ production/accounting/tax sign-off และยังไม่ได้ทดสอบกับระบบจริง

[คู่มือ/กติกา/diagram](../../PHASE_28_8_3_OFFLINE.md) · [source/artifact hashes](manifest.json) · [foundation snapshot เดิม](foundation-20261101.md)

## ผลตรวจล่าสุด

| ชุดตรวจ | ผลและขอบเขต |
| --- | --- |
| POS full regression | **93 tests /1,958 assertions PASS** — [JUnit](local-pos.junit.xml); รวม Checkout/Restaurant/Delivery/Membership เดิม, ingress, Manager reauthentication, cursor pagination, permit/hold/replay, expiry release guard, frozen-price/current-shift reconciliation และ ABB/FULL |
| ERP POS subset | **57 tests /1,792 assertions PASS** — [JUnit](local-erp.junit.xml); Phase28PosImport/Transport และ member/promotion unit tests. ไม่ใช่ full ERP suite |
| Actual signed Retail roundtrip รอบสุดท้าย | **1 test /14 assertions PASS** — [JUnit](actual-roundtrip.junit.xml). ใช้ event ที่ POS สร้างจริงจาก browser รอบสุดท้ายและ public key สังเคราะห์ ไม่สร้าง signature ใหม่ใน ERP test |
| Chrome PWA สามประเภท | [ผล](pwa-browser.json): Retail/Pharmacy1280/375ตามcase, Restaurant1280; offline reload/unlock/restart-clock block, lost-response intake/retry, Manager review/final ACK, expiry/manifest unused release, cache allowlist และไม่มี page error/แนวนอนล้น |
| Chrome storage / clock / ACK primitives | [6กลุ่ม PASS](storage-final.json): encrypted persistence/lock/wrongpass/scope isolation, cross-tab limits, partial record loss, immutable/canonical permit replay, atomic slot/SKU/VAT/budget, ACK lost-response/re-auth, headroom/transaction abort/orphan metadata และ clock rollback/newcontext |
| Static/build | `tsc -p pos/tsconfig.json --noEmit`, POS Vite production build, scoped Pint/Prettier และ diff whitespace check ผ่าน |
| Configured database | ไม่ migrate ไม่แก้ `.env` จริง; codePOS22/ERP88 migrations. ทั้งสองฝั่งเพิ่ม flag `POS_LOCAL_BROWSER_OFFLINE_ENABLED=false` เป็นค่าเริ่มต้น |

ชุดตรวจทับซ้อนกัน **ห้ามบวกจำนวน tests/assertions เป็นยอดรวมเดียว**. `foundation.junit.xml`, `offline-final.junit.xml` และ `storage-browser.json` เป็นผลย่อย/รอบก่อน ไม่ใช้แทนผลใหม่ในตาราง

## หลักฐานเงินและ Stock

- [Server counters หลัง browser สามประเภท](server-counters.json): sales2/payments2/signed events2; inbox3=Retail/Pharmacy synced2 + Restaurant review1; reservedเหลือ0หลังครบ manifest และ expiry
- [Actual Retail signed event](retail-signed-event.json): synthetic ordinary merchandise10.00 +VAT0.70 =10.70บาท. Provisional slot/client identity ถูกผูกกับ source sale และ original grant
- Actual ERP testตรวจ **Invoice1 / Payment1 / GL3 / receipt claim1**, Stockของสินค้าเดิม2movements=initial adjustment1+Stock out1, entitlement consumed1.0000. receive/process/replayไม่เพิ่มยอดหรือ Stock out ซ้ำ
- ออก ABB และแทนด้วย FULL ใน POS test โดยคง sale/payment1 และตัด quota1ครั้ง. ไม่มี e-Tax provider/signatureจริง และไม่ถือ provisional ว่าเป็นใบกำกับภาษี
- Managerreviewหลังpermitหมดอายุ/ราคาใหม่เปลี่ยน/กะเดิมปิดยังใช้ยอดเดิมและกะปัจจุบัน. Wrong amount/identity conflict/unsafe clinical/recall/lot expiryไม่ force post หรือคืน hold
- ห้ามคืนunusedก่อนpermitหมดอายุ เพราะ offline deviceยังอาจใช้สิทธิ์เดิมได้. หลังหมดอายุต้องตรวจ manifest และยืนยัน unused พร้อมรหัสผ่าน/เหตุผล จึงคืนเฉพาะส่วนไม่consumeครั้งเดียว

## Browser และขอบเขตธุรกิจ

[Desktop Retail](desktop-retail.png) · [Mobile Pharmacy375×900](mobile-pharmacy.png). Retail/Pharmacy ordinary cashตามexplicitallowlist; Restaurantเก็บคำขอรอครัวเท่านั้น. สมาชิก/โปรโมชัน/clinical/Deliveryไม่แสดงว่างานสำเร็จขณะoffline; Pharmacy Deliveryยังปิด

Service worker cacheมีเฉพาะ `/offline/app` และ `/build/assets/*`; shellไม่มีCSRF token. Auth/API/state/contact/เวชระเบียนไม่ถูกcache. Persistent StorageในChromeรอบนี้ได้ **false** ตามจริง; encrypted recordsยังอยู่หลังปิด/เปิดprofileเดิมในstoragetest แต่ไม่รับประกันevictionหรือการล้างข้อมูล

Browserไม่สามารถรับประกันป้องกันผู้ควบคุมเครื่องที่copy/rollbackprofileได้สมบูรณ์. มี encrypted hash chain/CAS/limits เป็น client guard และใช้ server quota hold/dedupe/immutable conflict/Manager review เป็น authority; ERPมี receipt claims เพิ่มอีกชั้น. ไม่ส่ง event/checkpoint/e-Tax private key ลงbrowser

## Fixture cleanup / release notes

[Cleanup](cleanup.json): ปิด server8146 แล้ว, active staff0, sessions0, ถอน synthetic private key. เก็บ inbox/audit/Stock holds ของรอบที่ค้างไว้ ไม่ล้างหลักฐานหรือคืนquotaเพราะปิดfixture. รอบก่อนและรอบสุดท้าย sealed ทั้งหมด ห้ามreuse; หากทดสอบใหม่ต้องสร้าง fresh isolated fixture และไม่ชี้สคริปต์ไปconfiguredDB

**ยังไม่ได้ทดสอบกับระบบจริง**: two-server HTTP/mTLS, MySQL/MariaDB concurrency, อุปกรณ์/เครื่องพิมพ์จริง, production backup/restore/key custody/rotation และบัญชี/ภาษี/privacyจริง. Local flagsยังdefaultfalse; configuredDBไม่ถูกปรับในรอบนี้. ไม่มีการส่งโค้ดใหม่นี้ไปGeminiโดยใช้สิทธิ์POSConfigเดิม

รายการที่ยังไม่ส่งถึงserverและรหัส/metadataสูญหายอาจกู้ไม่ได้. คงserverholdและสอบสวน; ไม่รับประกันrecoveryและไม่ลบpending/reviewอัตโนมัติ. Retention90วันของonline archiveเดิมไม่ใช่คำสั่งลบคิวbrowser
