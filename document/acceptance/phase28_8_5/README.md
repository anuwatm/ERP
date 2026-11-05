# Phase 28.8.5 — Local acceptance

ปิดขอบเขต local วันที่ 2026-11-05 [คู่มือและ diagram](../../PHASE_28_8_5_RESTAURANT.md)

- POS regression **113 tests / 2,118 assertions** ผ่านทั้งหมด [JUnit](pos.junit.xml)
- ชุดเจาะจงหลังเพิ่มกรณีผู้จองมาถึง **14 tests / 130 assertions** รวมคิวตามเวลารับ, Manager override, booking 90 นาที, no-show 15 นาที, session replay, QR ราคาเปลี่ยน, quota และ Retail contract ชุดนี้ทับซ้อนกับชุดเต็ม [JUnit](restaurant.junit.xml)
- Chrome หน้าลูกค้า 390 px และ POS 1440/390 px ทดสอบจอง/ยกเลิก จัด Walk-in ส่ง QR แบบตัดการเชื่อมต่อและทำคำตอบหาย พนักงานรับเข้าครัว ลูกค้าเห็นสถานะ และชำระเงิน [Browser report](browser.json) · [QR มือถือ](qr-mobile.png) · [POS desktop](pos-desktop.png) · [POS mobile](pos-mobile.png)
- ตรวจฐาน fixture สองรอบ พบหนึ่งคำขอ หนึ่งออเดอร์ หนึ่งชำระเงิน และหนึ่งเหตุการณ์ตัดวัตถุดิบต่อรอบ ปิด server ทดสอบและถอนบัญชี, session และกุญแจสังเคราะห์แล้ว [Server report](server.json)
- TypeScript, Vite build, Pint และ Prettier ผ่าน
- POS SQLite ตาม config ครบ **25/25 migrations** พร้อม backup และตรวจ integrity [รายงานฐาน](../../DATABASE_UPDATE_20261105_PHASE28_8_5.md)

**ยังไม่ได้ทดสอบกับระบบจริง** รวมมือถือ/เครื่องสแกนจริง, HTTPS LAN จริง และฐานเป้าหมายภายใต้โหลดหลายเครื่อง
