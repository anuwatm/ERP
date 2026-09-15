# Phase 23.1.3 Customer & Contact Pilot

สถานะ: เสร็จ 2026-09-13 — ปิด browser regression ของ pilot บน isolated SQLite พอร์ต 8011

รอบนี้ตรวจ implementation จาก Phase 23.1.2 ไม่พบข้อผิดพลาดที่ต้องแก้ production code เพิ่ม เปลี่ยนเอกสารและ checklist ให้ตรงหลักฐาน

| กรณี | ผลตรวจ |
| --- | --- |
| Customer Create | สร้างชื่อไทยยาวพร้อม LINE ID; กลับรายการและได้ customer code 000001 |
| Customer Edit | แก้ Note แล้วเปิด Edit ใหม่พบค่าที่บันทึก; owner/type/status และ association เดิมยังอยู่ |
| Customer validation | ชื่อ 256 ตัวอักษรแสดง backend error; Note ยังคงอยู่; ไม่ปิดฟอร์ม |
| Contact Create | เปิดจากแถว Customer ใหม่; ชื่อเกิน 255 แสดง error และ focus ช่อง Name; LINE ID/Note/Primary คงอยู่; แก้ชื่อแล้ว save สำเร็จ |
| Contact Edit | เปิดจากชื่อผู้ติดต่อ; prefill LINE ID/Note/Primary/Customer ถูกต้อง; แก้ Position แล้วเปิดใหม่พบ Pilot reviewer |
| Primary / scope | รายการแสดง Primary ที่ Customer ถูกคน; backend regression ตรวจ primary uniqueness, organization/owner scope และ view-only write denial |
| X / Cancel / Escape | dirty Contact เปิด Discard unsaved changes? ทุกทาง; Keep editing คง draft |
| Overlay | คลิกนอก Customer dialog เปิดคำยืนยันในแอป |
| Minimize / switch | Contact ย่อเป็นแถบเดียว; Create Customer ขอ discard ก่อนแทนที่; Keep/restore คืน Note เดิม |
| Navigation | จาก minimized Customer กด Deals เปิดคำยืนยัน; Keep/restore คงชื่อเดิม |
| Back / Forward | Customers → Deals → Customers; dirty Back ขอคำยืนยัน; Keep คงค่า; Discard ไป Deals; Forward กลับ Customers |
| Reload | เรียก reload ขณะ dirty แล้ว Customer draft ยังคงอยู่; เครื่องมือไม่คืน native prompt จึงไม่อ้างว่าได้ตรวจข้อความ/ปุ่มของ native prompt |
| Session confirmation | session ใหม่ขอรหัสผ่านใน Customer dialog; ยืนยันแล้ว submit ต่อได้โดยไม่เสีย draft |
| Responsive | Contact 375×812 และ 812×375: form scrollWidth เท่ากับ clientWidth, footer อยู่ใน viewport; Customer 375×812 พร้อม validation ผ่านเงื่อนไขเดียวกัน |
| Theme / keyboard | ใช้งานทั้ง dark และ light; error focus และ Escape ผ่าน; shared focus trap/restore ตรวจใน 23.1.2 |

## Validation

รัน `php artisan test --compact --filter='Phase2SalesTest|Phase231SalesDialogsTest|PasswordConfirmationTest'` ผ่าน 19 tests / 251 assertions ในรอบ pilot

TypeScript, scoped ESLint, Pint, production build และ frontend 15 tests ผ่านใน Phase 23.1.2 บน implementation เดียวกัน รอบนี้ไม่มีการแก้ application code จึงไม่ build ซ้ำ

ข้อมูลที่สร้าง/แก้เพื่อ browser test อยู่เฉพาะ `temp/phase231-browser/database.sqlite` ไม่ใช่ฐาน operational

ข้อจำกัดการรับรอง: Chromium/IAB และ viewport จำลอง; ไม่อ้าง physical mobile keyboard, Safari หรือข้อความ native reload prompt ทุก browser. หน้า Customer/Contact ไม่มี attachment/line items จึงอ้าง shared standard แยกจาก module pilot นี้

Phase 23.1 ทั้งหมดยังไม่ปิด; rollout โมดูลอื่นตาม 23.1.4
