# Phase 23.1.5 — Remaining Browser Acceptance

สถานะ: Deferred โดยผู้ใช้อนุมัติ 2026-09-15 ให้ปิด Phase 23.1.5 เฉพาะขอบเขตที่ผ่าน. B01–B08 ยังต้องทดสอบ ไม่ใช่ Pass. Browser connection ของ GPT ยัง timeout; ทดสอบต่อเมื่อเครื่องมือพร้อม.
Base URL: http://localhost/ERP. งานสร้าง/แก้ข้อมูลทดสอบต้องใช้ isolated environment เท่านั้น.

| ID | กรณีและขั้นตอน | เกณฑ์ผ่าน | สถานะ |
|---|---|---|---|
| B01 | ทุกกลุ่มฟอร์มใน PHASE_23_1_FORM_INVENTORY: Create/Edit → เปลี่ยนค่า → Minimize/Restore → Keep/Discard | Keep คงทุกค่า; Discard reset; editor ถัดไปเปิดถูกต้อง | Deferred — user approved |
| B02 | Inventory/Payroll: เปิดฟอร์มสะอาดแล้วเปิด editor อื่น; ระหว่าง processing ลองปิด/สลับ | clean switch ไม่เตือน; processing ไม่ทำ draft หายหรือ submit ซ้ำ | Deferred — user approved |
| B03 | Desktop 1280px และ mobile 375px, light/dark: Tab/Shift+Tab, resize, navigation/back | focus ใช้งานได้, ปุ่มไม่หลุดจอ, dirty navigation เตือนและ Keep คง draft | Deferred — user approved |
| B04 | กรอก draft → ตัด network → submit → เชื่อมต่อ → retry | ไม่มี false success; draft อยู่; processing คืนสภาพ; retry บันทึกครั้งเดียว | Deferred — user approved |
| B05 | บน test session: session หมดอายุหรือ record ถูกล็อก/ลบก่อน submit | ไม่มี false success; error/re-auth recovery ชัดเจน; ตรวจว่าค่า draft อยู่หรือสูญหาย | Deferred — user approved |
| B06 | isolated dataset 1,000+ records: เปิด/ค้นหา/เลื่อนฟอร์ม | ระบุจำนวนข้อมูลและเวลาวัดจริง; ไม่มี UI freeze/error | Deferred — user approved |
| B07 | isolated uploads: ระบุชนิด/ขนาดไฟล์ → upload สำเร็จ/ล้มเหลว/retry | แสดง progress/error, draft อยู่เมื่อ fail, retry ไม่ซ้ำ; บันทึก latency | Deferred — user approved |
| B08 | เปิด dialog → wheel/touch เลื่อน → ปิด | background scroll position ไม่เปลี่ยนขณะเปิด; เลื่อนได้หลังปิด | Deferred — user approved |

ทุกแถวต้องแยกผลตาม module/role/browser version/viewport/theme พร้อม expected/actual และ screenshot/video/log. ไม่ใช้ชื่อกลุ่มหรือ unit tests แทนผลรายกรณี.