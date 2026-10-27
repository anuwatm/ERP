# Configured database upgrade — 2026-10-27 (Asia/Bangkok)

ผู้ใช้สั่งอัปเดตฐานข้อมูลที่ตั้งค่าใช้อยู่. รัน forward migrations เท่านั้น ไม่ใช้ migrate:fresh/reset หรือ seed ข้อมูลจำลอง

## ผลดำเนินการ

- ERP: MySQL `127.0.0.1:3306`, database `erp`; applied 24 migrations ที่ค้าง รวม Phase 27 / integration / Phase 28. หลังอัปเดต applied รวม 83, pending 0
- POS: config ปัจจุบันใช้ `pos/database/database.sqlite` ซึ่งเดิมไม่มีไฟล์. สร้างฐานใหม่และ applied ครบ 17 migrations, pending 0. ไม่เปลี่ยน connection config ไม่ seed staff/orders
- POS `PRAGMA quick_check`: ok; foreign_key_check: 0 violations. Orders 0 / Staff 0. ยังต้อง provision branch/terminal/staff/keys/catalog/quota ก่อนใช้งาน POS

## Backup และตรวจข้อมูลเดิม

สำรอง ERP ก่อน migration ด้วย mysqldump single-transaction พร้อม routines/triggers/events ใน private storage:

`backend/storage/app/private/db-backups/20261026-175206/erp-before-migrate.sql`

- ขนาด 343,637 bytes; ตรวจ completion marker และ SHA-256 `1b38840f63bf49f3d401e5419bd6f407cdd5a0f77def8cff699f6b3bd8dd192d`
- `before.json` / `after.json` ในโฟลเดอร์เดียวกันเก็บ metadata เทียบก่อน/หลัง ไม่พิมพ์ dump หรือ secrets ลงรายงาน
- ตารางเดิมครบ 133 ตาราง ไม่มีตารางหาย/จำนวนแถวลดลง. จำนวนแถวเปลี่ยนเฉพาะ `migrations` 59→83 และ `permissions` 145→153
- POS ไม่มีฐานเดิมให้สำรอง จึงสร้างใหม่โดยไม่เขียนทับไฟล์เดิม

## Compatibility fix ระหว่าง migrate

พบ MySQL error 1059: generated foreign-key/index names ยาวเกิน 64 ตัวอักษร ซึ่ง SQLite tests ไม่จำกัดแบบเดียวกัน. กำหนดชื่อ explicit ที่สั้นลง 8 รายการใน webhook / POS cash / kitchen / return inspection / checkpoint / tax-document migrations โดยคงคอลัมน์และ uniqueness/foreign-key semantics เดิม

Migration webhook สร้างตารางค้างก่อนพบ error. ตรวจ backup ว่าทั้งสามตารางไม่เคยมีมาก่อน และตรวจว่าปัจจุบันว่างทั้งหมด จึงนำออกเฉพาะตารางใหม่ที่ว่างเพื่อรัน migration ที่แก้แล้ว. ไม่ drop ตารางเดิม/ไม่ปิด foreign-key checks. หลังรัน foreign keys ของ webhook deliveries ครบ 2 รายการ

ตรวจ SQL ด้วย migrate --pretend ก่อน retry: identifiers เกิน 64 ตัวอักษรเหลือ 0. จากนั้น migration ทุกตัวผ่านบนฐาน ERP ที่ตั้งค่าใช้อยู่จริง

## Regression

- `php vendor/phpunit/phpunit/phpunit --filter 'Phase28|ExternalWebhook'`: **46 tests / 422 assertions ผ่าน** บน SQLite :memory: แยกจากฐาน ERP
- Pint `--test` เฉพาะ 6 migration files ที่แก้: ผ่าน
- การอัปเดต schema นี้ไม่ใช่ production/UAT/real-network/hardware/e-Tax acceptance
