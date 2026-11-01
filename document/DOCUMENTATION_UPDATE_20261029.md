# Documentation / diagrams refresh — 2026-10-29

## ขอบเขต

ปรับเอกสารปัจจุบันให้ตรง POS Config ที่พัฒนาเพิ่มหลัง Phase 28: `/config`, manager-only/own-branch/local-loopback, password re-auth, allowlist และ audit. อัปเดต README, architecture/contract/security, API/routes, database/ERD references, operations/validation, คู่มือ POS ทั้งสามประเภท และ portal. สร้าง [คู่มือ Config](POS_CONFIGURATION.md) พร้อม sequence และ logical ERD.

เอกสารโมดูลที่ไม่เปลี่ยนพฤติกรรมคงรายละเอียดเดิม. ไม่แก้ Gemini verdict, acceptance เก่า, checklist baseline, database-upgrade report หรือ snapshot ให้กลายเป็นการรับรองใหม่. ตัวเลข migrations เป็นผลตรวจวันที่ 2026-10-27 ไม่ได้ตรวจฐานซ้ำในงานนี้.

## Diagram

- สร้าง HTML จาก JSON specs ครบ 18 ชุด รวม [23 — POS Server / Config](23_pos_server_configuration.html) ชนิด architecture
- ต้นฉบับ 18 ชุดผ่าน showcase 9/9 artifact checks, composition errors=0/warnings=0
- [Delivery receipts](DIAGRAM_DELIVERY_20261029.json) เก็บ SHA-256 และขนาดของ spec/HTML ทุกไฟล์
- ตรวจ visual containment ที่ 1440×900, 1600×1000, 1920×1080 และ 2048×1320 ทั้ง light/dark ดู [ผลล่าสุด](DIAGRAM_VISUAL_CHECK_20261029.json)
- ย้าย cards จาก diagram เดิมที่ล้นจอมา [DIAGRAM_NOTES](DIAGRAM_NOTES.md) โดยเก็บรายละเอียดครบ ไม่ซ่อน overflow หรือลดขนาดตัวอักษรเพื่ออ้างว่าผ่าน
- Diagram ใหม่ตรวจภาพ 1440 dark และ 2048 light ด้วยตาแล้ว; visual-check ของเครื่องยังรายงาน visualReview=pending ตามสัญญาเครื่องมือ ไม่ถือเป็น human sign-off ทุกไฟล์
- เนื้อหาใหม่ของ diagram 23 เป็นไทย ส่วน Viewer UI และ html lang fallback เป็น English

## หลักฐาน / ข้อจำกัด

[Link audit](DOCUMENT_LINK_CHECK_20261029.json) แสดงจำนวนไฟล์และลิงก์ที่ตรวจล่าสุด. แก้ลิงก์ file:/// ของ CashFlowForecastService เป็น relative path. งานนี้เป็นเอกสารและ diagram ไม่เปลี่ยน application code/config/ฐานข้อมูล และไม่รัน business tests ซ้ำ. ผลจากงาน Config ก่อนหน้า: POS45/268, TypeScript/Vite/Pint ผ่าน.

Gemini PASS วันที่ 2026-10-27 ไม่ครอบคลุม Config ใหม่. ยังไม่ได้ทดสอบระบบ/อุปกรณ์จริง; e-Tax provider adapter รอ provider/contract. Understand graph เป็น baseline 2026-10-27 (1,030 files/10 layers/22 steps) ยังไม่ได้ rescan โค้ด Config ใหม่ ไม่แก้ fingerprints ให้แสดงเหมือนสแกนแล้ว.

## สรุปผล visual รอบสุดท้าย

อัปเดตต่อเนื่อง 2026-10-30: desktop containment ผ่าน **18/18** ชุดที่ 1440×900, 1600×1000, 1920×1080 และ 2048×1320 ทั้ง light/dark ตาม receipt ล่าสุด. Showcase 9/9 checks ผ่านครบทุกไฟล์ และ SHA-256 ของ spec/HTML ตรง receipt.

แก้ 14 ชุดเดิมด้วย authored layout: lifecycle ลดช่องว่างและยก terminal states, sequence จัดระยะข้อความโดยคงทุก message/activation, workflow รวม lane ที่สัมพันธ์กันโดยคงทุก node/edge, dataflow/ER ปรับ canvas/ช่องว่าง. ไม่ใช้ overflow:hidden, crop หรือ font reduction. เปลี่ยน motion เป็น none ให้เปิดภาพนิ่งตามค่าเริ่มต้น.

ตรวจ screenshot ของ 14 ไฟล์ที่แก้ด้วย AI อย่างน้อยไฟล์ละหนึ่งขนาด/ธีมแล้ว; ไม่ใช่ human sign-off. เครื่องมือยังคง visualReview=pending ตามสัญญา. Viewer UI และ html lang ยังคง fallback English; authored content เดิมคง English.

ผล 4/18 และรายชื่อ 14 overflow ที่บันทึกก่อนแก้เป็นประวัติเดิม ถูกแทนด้วยผลปัจจุบัน 18/18 ด้านบน. รายละเอียดเดิมที่ย้ายออกจาก cards ยังอยู่ใน DIAGRAM_NOTES.md ครบ.