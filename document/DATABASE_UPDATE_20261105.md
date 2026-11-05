# อัปเดตฐานข้อมูล — 2026-11-05 / Phase28.8.4

Configured POS SQLite `pos/database/database.sqlite`: **23/23 migrations, pending0**, จาก22; เพิ่ม `pos_patients`, `pos_patient_revisions`, `pos_patient_visits`, `pos_patient_dispensing` ว่างทั้ง4ตาราง. ตารางรวม51→55. ERPไม่ได้แก้รอบนี้ คง88/88ตามรายงาน2026-11-04.

สำรอง SQLiteก่อน migrate พร้อมตรวจ integrity และจำนวนทุกตาราง. Backupอยู่ `pos/storage/app/private/database-backups/20261105-phase2884/pos-20261104T182937Z.sqlite` (ชื่อเวลาUTC; วันทำงานไทย2026-11-05), 663552bytes. SHA256 `4df6aa626d0ae4ad5d6c634b79c48b915b73167fdfdda7b9dab541d0945f316c`. ไม่แนบฐาน/keysในหลักฐานสาธารณะ.

ตรวจอิสระ: integrity_check=ok, foreign_key_checkว่าง, unique indexesครบ, bill referenceเป็น encrypted payload/HMAC ไม่มี plain order_id. จำนวนข้อมูลเดิมทุกตารางคงเดิม ยกเว้น migration ledgerเพิ่มหนึ่ง. ไม่มีseed/reset. `.env`, connection, APP_ENV=production และ flagsทั้งหมดคงเดิมfalse; patient featureยังปิดและproduction hard-block.

[ผล schema/index/integrity JSON](DATABASE_UPDATE_20261105.json) · [รายงานรอบก่อน](DATABASE_UPDATE_20261104.md)

**ยังไม่ได้ทดสอบกับระบบจริง**. Schemaพร้อมไม่ใช่การอนุมัติข้อมูลผู้ป่วยจริง/clinical/key custody/retention หรือproduction release.
