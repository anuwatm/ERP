# อัปเดตฐานข้อมูลที่ใช้งานอยู่ — 2026-11-04

สถานะ: **เสร็จแล้ว** ตามคำสั่งผู้ใช้ อัปเดต configured connections จริงของ ERP และ POS ไม่ใช่ฐาน fixture

| ระบบ | Connection | Migrations ก่อน → หลัง | ค้าง | ตาราง ก่อน → หลัง |
| --- | --- | --- | --- | --- |
| ERP | MySQL `erp` | 83 → 88/88 | 0 | 165 → 173 |
| POS | SQLite `pos/database/database.sqlite` | 17 → 22/22 | 0 | 35 → 51 |

## ตรวจหลังปรับปรุง

- `migrate:status` และการเทียบ migration files กับ ledger: ไม่มีรายการค้างทั้งสองฐาน
- ตรวจจำนวนข้อมูลทุกตารางเดิมแล้วเท่าเดิม ยกเว้น migration ledger ที่เพิ่มรายการตามปกติ ไม่มี seed/reset/ลบข้อมูลธุรกิจ
- ตารางใหม่ทั้งหมดว่าง พร้อมใช้งานตาม code ล่าสุด; unique indexes สำหรับ offline receipt dedupe, terminal lease, document slots และ grant reservations ครบ
- SQLite `integrity_check=ok` และ `foreign_key_check` ไม่พบปัญหา
- คง environment เดิม: ERP `local`, POS `production`; ไม่แก้ `.env`, ไม่เปิด feature flags และไม่เปลี่ยน connection

## ข้อมูลสำรอง

เก็บนอก public directory ใน `backend/storage/app/private/database-backups/20261104-phase2883/` พร้อม Apache deny rule และ gitignore. ตรวจ checksum หลังย้ายเข้า private storage และตรวจซ้ำหลัง migration แล้ว

- ERP: `backend-20261104GMT0755220.sql` — 397,210 bytes
  SHA-256: `3aae8a4b4488e62c053cfe92a9a71e0ea4df6141fe783d00b4a421dd05cc7324`
  ตรวจ: InnoDB consistent single-transaction dump + successful exit + completion marker
- POS: `pos-20261104GMT0755220.sqlite` — 430,080 bytes
  SHA-256: `93a68730f155a08df7b3a3a98a54807c87987a2faa51536b20cc8e7bb70d1798`
  ตรวจ: SQLite integrity_check + every table row count

MySQL backup ตรวจ consistent dump/exit/completion/checksum; รอบนี้ไม่ได้ restore dump ลงระบบใช้งาน. SQLite backup ตรวจ integrity และจำนวนข้อมูลทุกตารางก่อนรัน migration

## Migrations ที่รัน

### ERP

- `2026_10_30_000001_create_pos_delivery_revisions`
- `2026_10_30_000002_create_pos_membership_master`
- `2026_10_31_000001_create_membership_publications`
- `2026_10_31_000002_create_member_request_inbox`
- `2026_11_04_000001_create_pos_offline_receipt_claims`

### POS

- `2026_10_30_000001_create_delivery_workflow`
- `2026_10_30_000002_add_delivery_operations`
- `2026_10_31_000001_create_membership_runtime`
- `2026_10_31_000002_create_member_drafts`
- `2026_11_01_000001_create_offline_ingress`

## ขอบเขตและเอกสารก่อนหน้า

เพิ่ม schema ของ Delivery, Membership/Promotions และ Browser Offline ถึง Phase28.8.3 ครบ ไม่เปลี่ยนนโยบายบัญชี/ภาษีหรือเปิดระบบ/อุปกรณ์จริง. **ยังไม่ได้ทดสอบกับระบบจริง**

รายงาน acceptance ของ Phase28.8.1/2/3 และ source hashes เดิมเป็น snapshot ตอนพัฒนา ซึ่งเกิดก่อนคำสั่งอัปเดตฐานข้อมูลนี้. ข้อความใน snapshot ว่าไม่ได้ migrate หมายถึงขณะตรวจรับเดิม ใช้รายงานฉบับนี้เป็นสถานะ configuredDB ล่าสุด ไม่เขียนทับหลักฐานเดิม

[ผลตรวจแบบ JSON](DATABASE_UPDATE_20261104.json)
