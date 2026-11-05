# ตรวจความปลอดภัย ERP และ POS — 2026-11-05

ขอบเขตครอบคลุมโค้ด, dependency lockfiles, เส้นทาง Apache, login, session, ข้อมูลบริษัทใน ERP, API และ SQL logs การทดสอบ HTTP ใช้ Apache loopback แยกและไฟล์สังเคราะห์

| สิ่งที่พบ | การแก้ไขและผล |
| --- | --- |
| ERP local เปิด debug | ตั้ง `APP_DEBUG=false` ใน config ที่ใช้อยู่และตัวอย่างติดตั้ง |
| Document root แบบเดิมเข้าถึงไฟล์ที่มีอยู่ได้กว้างเกินไป | จำกัด static paths ที่อนุญาตใน root, ERP public, POS public และ installer หากไม่มี rewrite ระบบปฏิเสธการเข้าถึงแทนการเสิร์ฟไฟล์ |
| ข้อมูลบริษัทแรกถูกใช้เป็น fallback บนหน้า login | หน้า guest ใช้แบรนด์กลางและส่ง `org=null` ผู้ใช้ที่ยืนยันตัวตนเห็นข้อมูลบริษัทจาก relation ของตัวเอง |
| การ cache หน้า ERP/POS ที่มีข้อมูลพนักงานหรือเอกสาร | ใช้ `private, no-store` สำหรับ ERP web, POS staff และ installer |
| Query exception อาจบันทึก SQL และค่าพารามิเตอร์ | ERP/POS บันทึกเฉพาะ SQLSTATE ส่วน external API ใช้ error metadata ตามเดิม |
| POS จำกัด login ตาม IP อย่างเดียว | ใช้ทั้ง 5 ครั้งต่อนาทีต่อ IP และ 10 ครั้งต่อนาทีต่อบัญชี โดยใช้ hash ของอีเมลที่ normalize แล้ว |
| dependency advisories | อัปเดต Laravel เป็น 13.40.0, Symfony และแพ็กเกจที่เกี่ยวข้อง อัปเดต Axios, PostCSS, Vite และ transitive fixes ฝั่ง ERP เพิ่ม lockfiles PHP/JS ฝั่ง POS standalone |
| ความเสี่ยง commit ไฟล์ลับโดยพลาด | ขยาย git ignore สำหรับ local env, SQLite, dump และ backup suffix ที่ตรวจพบ โดยคงไฟล์ตัวอย่าง |

ลดงานซ้ำใน ERP โดยเอา schema lookup และการเลือกบริษัทแรกสำหรับ guest ออก เลื่อน query แจ้งเตือนไปตอน response ต้องใช้จริง ส่วน QR ร้านอาหารอ่านโควตาเฉพาะวัตถุดิบที่เกี่ยวข้องเมื่อชุดวัตถุดิบมีขนาดเหมาะกับ query ใช้ผลเดิมสำหรับชุดขนาดใหญ่

## การตรวจและข้อจำกัด

- Apache fixture ทดสอบ 35 กรณี เส้นทางปกติได้ 200 ไฟล์ที่ห้ามได้ 403 รวม `.env`, encoded `.git`, ไฟล์ backup และไฟล์ที่วางผิดใน public เมื่อปิด rewrite เส้นทางตัวอย่างถูกปฏิเสธ
- `composer audit --locked` และ `pnpm audit` ตรวจทั้ง ERP และ POS registry รายงาน 0 advisories หลังแก้ จาก ERP PHP 14 รายการและ ERP frontend 34 รายการก่อนอัปเดต
- ตรวจรูปแบบ private key และ token ที่พบบ่อยในไฟล์ tracked และประวัติของ branch ปัจจุบัน ไม่พบรายการตรงรูปแบบ การตรวจนี้ครอบคลุมเฉพาะรูปแบบที่ระบุ
- ใช้ `composer install` และ `pnpm install --frozen-lockfile` เพื่อให้ติดตั้งตาม lockfiles ที่ตรวจแล้ว
- เครื่องทดสอบยังเป็น Apache 2.4.41 และ PHP 8.3.1 อัปเกรด runtime และทดสอบ config, HTTPS, canonical Host, cookies และเส้นทางจากเครือข่ายเป้าหมายก่อนเปิดใช้งานจริง [Apache security advisories](https://httpd.apache.org/security/vulnerabilities_24.html) · [PHP changelog](https://www.php.net/ChangeLog-8.php)
- ตั้ง DocumentRoot ของแต่ละแอปที่ `backend/public` หรือ `pos/public` ตามบทบาทของเครื่อง และเปิดให้ใช้กฎ Apache ที่ตรวจแล้ว เก็บฐานข้อมูล, private storage, log และ backup นอก public paths
- **ยังไม่ได้ทดสอบกับระบบจริง** ผลนี้เป็นการตรวจโค้ดและทดสอบ local ด้วยฐานแยก ไม่มีการยิงทดสอบระบบภายนอก

## ผล regression

- ERP auth, external API และ log redaction ผ่าน 26 tests / 219 assertions
- POS regression ผ่าน 114 tests / 2,139 assertions และชุดเจาะจงล่าสุดผ่าน 14 tests / 133 assertions ผลชุดเจาะจงทับซ้อนกับชุดเต็ม
- ERP/POS TypeScript และ Vite build ผ่าน ERP frontend ผ่าน 38 tests

- ERP full regression รัน 566 เคสด้วย CLI memory limit 512 MB พบ 2 เคสที่ข้ามเพราะต้องใช้ MySQL แยก และ 1 เคส Sales ที่ fixture ขึ้นกับวันของเดือน บนต้นเดือน ดีลย้อนหลัง 10 วันอยู่นอกช่วงรายงาน จึงแก้เฉพาะเวลาใน fixture แล้วรัน Sales ทั้งคลาสซ้ำผ่าน 10 tests / 201 assertions ผลชุดเต็มเป็นผลก่อนแก้ fixture
- ลบ node_modules รุ่นเดิมที่พักไว้หลังติดตั้งและตรวจ build ของชุดใหม่แล้ว
