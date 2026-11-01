# Phase 28.8.2 — Local acceptance 2026-11-01

**Local scope complete:** ERP member master, encrypted POS enrollment/review, exclusive fixed/percent promotions, allocated rights, checkout/refund/Delivery integration และ UI ทุกประเภท POS ตามกติกา local ที่ผู้ใช้ยืนยัน ไม่ใช่ production หรือ Gemini sign-off

[คู่มือและ diagram](../../PHASE_28_8_2_MEMBERSHIP.md) · [Checklist](../../../checklist.md)

| ตรวจ | ผล / หลักฐาน |
| --- | --- |
| POS full regression | 81 tests / 1,851 assertions — [JUnit](pos.junit.xml) |
| ERP Phase28/Manufacturing + calculator/envelope | 82 tests / 1,937 assertions — [JUnit](erp.junit.xml); subset ไม่ใช่ full ERP suite |
| Browser Chrome | Retail/Restaurant/Pharmacy ที่1280/375 รวม6 checkout cases +3 enrollment cases, pageErrors=[] — [ผล](browser-report.json) |
| Cart / replay | เติมเงินสดเพียงพอแล้วยังรับเงินไม่ได้ก่อน preview; เปลี่ยนจำนวนทำให้ quote เดิมใช้ไม่ได้; Retail lost response หลัง commit ส่งซ้ำ request เดิมได้บิลเดิม |
| Source ledger | แต่ละ6บิลที่ตรวจมี sale event/payment/claim อย่างละ1; Restaurant4 preparation events ใช้วัตถุดิบรวม2.0000 ไม่ตัดซ้ำตอน checkout — [ผล](source-verification.json) |
| Signed Retail → ERP services | 10 events: 7 sales +3 enrollment requests; Invoice7/Payment7/Journal21/StockMovement8 (รวม adjustment-in เดิม1) /claim7 คงเดิมหลัง replay; net9.00 VAT0.63 ต่อ sale — [ผล](roundtrip.json) |
| Review / stale publication | ERP review publication เปิดสมาชิกที่ตรวจรับแล้ว; stale checkout409 ไม่สร้าง sale/claim, refresh+repriceแล้วเพิ่มอย่างละ1 — [ผล](review-browser-report.json) |
| Independent signatures | 26/26 source envelopes ผ่าน RSA-SHA256 ด้วย ERP canonicalizer/public key; ไม่ใช่ทั้งหมดถูก import เข้า ERP — [ผล](signature-verification.json), [public key](synthetic-public.pem), [events](signed-events.json) |
| Layout หลังปรับปุ่ม | 6 viewport cases ไม่มี horizontal overflow และข้อความปุ่มไม่ตัดคำ — [ผล](layout-report.json) |
| Build / style | POS/ERP TypeScript และ Vite build ผ่าน; Pint ผ่านสำหรับไฟล์ Phase นี้; ย้าย shared test contract ออกจาก PSR-4 production source |
| Cleanup | owned server stopped, active POS staff0/ERP users0, sessions12ลบ, private keys0, fixture sealed; ไม่ลบ member/audit — [ผล](cleanup.json) |

ภาพขณะเลือกสมาชิก/โปรโมชัน: [Retail1280](retail-1280.png) · [Retail375](retail-375.png) · [Restaurant1280](restaurant-1280.png) · [Restaurant375](restaurant-375.png) · [Pharmacy1280](pharmacy-1280.png) · [Pharmacy375](pharmacy-375.png). ภาพก่อนปรับ whitespace ของปุ่ม; layout-report ตรวจ build หลังปรับแล้ว

Browser เป็นหนึ่ง isolated SQLite app fixture มีสาม business branches ไม่ใช่สาม deployment. Retail actual signed roundtrip ใช้ isolated ERP services และ seeded test authority publications; ไม่ใช่ two-server HTTP/mTLS/ACK acceptance. Pharmacy browser ใช้สินค้าทั่วไป; pharmacist/controlled-unit guards ตรวจใน PHPUnit ไม่ใช่การทดลองจ่ายยาจริง

Cases/assertions/signatures/roundtrip มีส่วนทับซ้อน ไม่บวกเป็นยอดทดสอบรวม. รอบเตรียม fixture มี test reruns จึงมี signed events มากกว่า6บิลใน browser-report สุดท้าย รายงาน roundtrip ระบุจำนวนที่ตรวจจริงแยกไว้

ERP regression รวมทดสอบว่า signed cancellation หลัง dispatch ไม่คืนสิทธิ์ข้ามการรับคืน/ตรวจสภาพ, partial refund ไม่คืนสิทธิ์, cumulative full refund คืนครั้งเดียว, dependency รอ signed release และไม่มี COD financial posting อัตโนมัติ

## ทำซ้ำ

รันจากแต่ละ app เพื่อใช้ phpunit.xml ของ app นั้น ซึ่งบังคับ SQLite `:memory:`:

```powershell
# cwd: pos/
C:/AppServ/php8/php.exe ../backend/vendor/phpunit/phpunit/phpunit -c phpunit.xml
# cwd: backend/
C:/AppServ/php8/php.exe vendor/phpunit/phpunit/phpunit --filter 'Phase28|Manufacturing|PosPromotionCalculatorTest|PosMemberEnvelopeTest'
```

Browser harness อยู่ `pos/tests/Browser/membership*`, `prepare-membership.php`, `verify-membership.php`, `export-membership-evidence.php`, `cleanup-membership.php`; ERP bridgeอยู่ `backend/tests/Browser/verify-membership-roundtrip.php`. ต้องใช้ fresh isolated fixture ใน workspace แยก; fixtureรอบนี้มีcleanup.jsonป้องกัน reuse และถอน private keys แล้ว ห้ามเปลี่ยนไปใช้ configured DB หรือเปิดบัญชี fixture กลับมา

## ยังไม่ทดสอบ / ก่อนเปิดจริง

- **ยังไม่ได้ทดสอบกับระบบหรืออุปกรณ์จริง** รวม two-server/mTLS และ MySQL/MariaDB concurrency
- บัญชี/ภาษีของ promotions และ COD/fees, privacy/consent/retention, key custody/rotation ต้องตรวจรับก่อน production
- Migration code ปัจจุบัน ERP87/POS21; ยังไม่ migrate configured DB จาก snapshot เดิม ERP83/POS17. Default flags ยังfalse ไม่แก้ `.env` จริง
- ยังไม่ส่ง Phase นี้ให้ Gemini สิทธิ์ส่งเดิมจำกัดเฉพาะ6ไฟล์ POS Config
- ไม่รวม Phase28.8.3 PWA offline, restaurant queue/QR ordering ส่วนขยาย และเวชระเบียน; ทำตาม Phase ของแต่ละเรื่อง
