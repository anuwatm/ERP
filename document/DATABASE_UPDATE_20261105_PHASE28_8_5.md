# อัปเดตฐาน POS สำหรับ Phase 28.8.5 — 2026-11-05

ฐาน POS SQLite ตาม config ที่ `pos/database/database.sqlite` อัปเดต **23 → 25 migrations** เหลือ 0 รายการ ตารางรวม 55 → 56 เพิ่มตารางคิวที่นั่งว่าง และเพิ่มคอลัมน์เลขคิว/พักคิวครัวกับความจุโต๊ะ

ก่อน migration สำรอง SQLite ไว้ใน `pos/storage/app/private/database-backups/20261105-phase2885/pos-20261105T071808Z.sqlite` ขนาด 749,568 bytes ตรวจ SHA-256 `651681d73ac938bad281c0b9197503fba4ccf32da207b0ae53952421a1b39214`

ตรวจหลังอัปเดตแล้ว `integrity_check=ok` และ `foreign_key_check` ไม่มีข้อผิดพลาด ดัชนีกันซ้ำครบ จำนวนข้อมูลเดิมทุกตารางคงเดิมยกเว้น migration ledger ไม่มีการ seed ข้อมูล ค่า environment และ flags เดิมคงอยู่ ฟีเจอร์ร้านอาหารยังปิดในฐานตาม config

**ยังไม่ได้ทดสอบกับระบบจริง** [ผลตรวจฐาน JSON](DATABASE_UPDATE_20261105_PHASE28_8_5.json)
