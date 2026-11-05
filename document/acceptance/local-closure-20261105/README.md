# ปิดงานตรวจรับ Local ERP/POS — 2026-11-05

ขอบเขตเดิมที่ผู้ใช้ยืนยัน ครอบคลุม ERP regression, สองเคส MySQL ที่เคยข้าม, สถานะ schema ตาม config และซ้อมกู้คืนบนข้อมูลสังเคราะห์ Phase 28.8.6 ยังเลื่อนไว้ตามเดิม

- ERP ชุดเต็ม 566 เคส / 10,933 assertions ไม่มี failures หรือ errors แยก 2 เคสที่ต้องใช้ MySQL ออกไปรันโดยตรง
- Phase 23 บน MySQL แยกผ่าน 1 test / 8 assertions ตรวจ callback สังเคราะห์สอง process ว่าบันทึก receipt และ journal เพียงชุดเดียว
- Phase 24 บน MySQL แยกผ่าน 1 test / 12 assertions ตรวจ database queue และงานซ้ำด้วย e-Tax simulation ใช้ข้อมูลสังเคราะห์
- ฐานที่ ERP ใช้ตาม config ครบ 88/88 migrations ฐาน POS ตาม config ครบ 25/25 ทั้งคู่ pending 0 และไม่มี migration นอก ledger ที่พบจากการเทียบกับ source จึงไม่ต้องรัน migration เพิ่ม
- ERP MySQL ที่ตั้งค่าอยู่มี 171 ตาราง รายชื่อตารางและคอลัมน์ตรงกับฐานที่สร้างใหม่จาก migration ทั้งหมด
- POS SQLite ที่ตั้งค่าอยู่มี 56 ตาราง โครงสร้าง columns/indexes/foreign keys ตรงกับฐานที่สร้างใหม่ ตรวจ integrity ผ่านและ foreign key errors เป็น 0
- ซ้อมสำรองและกู้คืน ERP MySQL 171 ตาราง / 88 migrations และ POS SQLite 56 ตาราง / 25 migrations ทั้งสองชุดกู้ synthetic canary ได้ ใช้ฐานแยกจากข้อมูลจริง

[หลักฐานแบบ JSON](results.json) · [ประวัติอัปเดต ERP](../../DATABASE_UPDATE_20261104.md) · [ประวัติอัปเดต POS](../../DATABASE_UPDATE_20261105_PHASE28_8_5.md)

**ยังไม่ได้ทดสอบกับระบบจริง** ขอบเขตที่เหลือสำหรับการเปิดใช้งานจริงอยู่ใน checklist เช่น provisioning สาขาจริง นโยบายข้อมูล/บัญชี การเชื่อมอุปกรณ์และ provider และ restore บนสภาพแวดล้อมเป้าหมาย

Antigravity ตรวจหลักฐานแล้วให้ **PASS WITH LIMITATIONS** และไม่พบ blocker ในขอบเขต local เดิม [ผลตรวจฉบับเต็ม](ANTIGRAVITY_REVIEW.md)
