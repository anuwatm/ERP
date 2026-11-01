# Phase 28.8.3 — Foundation snapshot 2026-11-01

**In Progress ไม่ใช่การตรวจรับทั้งPhase และยังไม่ใช่ POS รับเงิน offline ที่พร้อมใช้**

[สถานะ/กติกาที่ผู้ใช้ยืนยัน/งานต่อ](../../PHASE_28_8_3_OFFLINE.md)

| ตรวจ | ผล |
| --- | --- |
| POS regression ก่อนเพิ่มHTTP testท้ายรอบ | 86 tests /1,891 assertions ผ่าน รวมCheckout/Restaurant/Deliveryเดิมและoffline foundation |
| Foundation/HTTP/permit testsหลังแก้UTC | 6 tests /53 assertions ผ่าน — [JUnit](foundation.junit.xml); ทับซ้อนกับregression ไม่บวกเป็นยอดรวม |
| Chrome IndexedDB/WebCrypto จริง | [ผล](storage-browser.json): encrypted write/lock/browser restart, wrong passphrase, scope isolation, atomic capสองtab, partial record loss guard, clock rollback/new context freeze ผ่าน |
| Browser storage | `persistent=false` ตามผลจริง แต่ข้อมูลยังอ่านกลับได้หลังปิด/เปิดprofileเดิม ไม่อ้างว่าจะไม่ถูกevict/lost |
| TypeScript | `tsc -p pos/tsconfig.json --noEmit` ผ่าน รวมสองmoduleใหม่ |
| ConfiguredDB/flags | ไม่migrateฐานใช้งาน ไม่แก้.envจริง; flagใหม่defaultfalse |

Serverออกราคา/Stockreservation/document slotsได้ในlocal tests แต่payloadระบุ `provisional_only=true`, `reconciliation_available=false`. Inboxเก็บหลักฐานแบบencryptedและเข้าreviewเท่านั้น ไม่สร้างsale/payment/ABB/FULL/GL/event

Browser harnessใช้loopback serverชั่วคราวและsynthetic Chrome profile ปิดserver/browserหลังจบ ไม่มีPOSevent/checkpoint privatekeyหรือข้อมูลจริงในprofile ไม่ใช่PWA-shell/reconnect/ERP acceptance. กรณีทดสอบข้อมูลหายจงใจลบหนึ่งrecordจากfixtureเท่านั้น Productioncodeไม่มีAPIลบคิว

งานต่อคือPWA UI/serviceworker, bindquota/slotsกับprovisional queue, sync/ACK, reconcile/custody/managerreview และbrowser acceptanceทุกPOS. กติกาreviewของผู้ใช้ยืนยันแล้ว: คงยอดรับจริง, Managerรับเงินเข้ากะปัจจุบันก่อนลงsaleครั้งเดียว, ใช้วันที่ตรวจรับในlocalและรอaccounting/tax reviewก่อนใช้จริง
