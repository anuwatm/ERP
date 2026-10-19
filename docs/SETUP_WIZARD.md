# Setup Wizard สำหรับติดตั้งบน Web Server

Wizard อยู่ที่ `backend/setup/` และ public entry อยู่ที่ `backend/public/setup/index.php`. หลัง deploy ให้เปิด `/setup`.

## Deployment bundle

ต้อง copy ทั้ง `backend/vendor/`, `backend/public/build/`, `backend/setup/`, `backend/public/setup/` และ `setup/` พร้อม source/migrations. Server ไม่ต้องมี Composer หรือ Node แต่ต้องมี PHP 8.3+, PDO MySQL, OpenSSL, mbstring, fileinfo, DOM, SimpleXML, cURL, zip และสิทธิ์เขียน `backend/.env`, `backend/storage/`, `backend/bootstrap/cache/`.

## เปิด installer

เลือกอย่างใดอย่างหนึ่ง:

1. แนะนำ: ตั้ง `SETUP_TOKEN` ที่ web-server environment เป็น random token อย่างน้อย 32 ตัวอักษร
2. สำหรับ host ที่ตั้ง environment ไม่ได้: copy `backend/setup/enable.php.example` เป็น `backend/setup/enable.php` แล้วเปลี่ยน token

`enable.php` ถูกลบหลังติดตั้งสำเร็จ. ทั้ง `enable.php` และ `backend/setup/` ถูกปิดจาก direct web access ใน Apache; ห้าม commit ไฟล์นี้.

## การทำงานของ wizard

ก่อนติดตั้ง เปิด `/setup/system-requirements.php` เพื่อตรวจ PHP version, required extensions (`pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `dom`, `simplexml`, `curl`, `zip`), memory/upload limits, directory permissions และ build assets ได้โดยไม่ต้องใช้ `SETUP_TOKEN` หน้านี้ไม่แสดงค่าลับหรือ `phpinfo()` และตรวจ database credential ใน Step 2 ของ wizard. ทุก Failed/Warning มีคำแนะนำเฉพาะสาเหตุ; ปุ่ม Fix สร้างได้เฉพาะ `storage/` หรือ `bootstrap/cache/` ที่ยังไม่มีและ parent directory เขียนได้ โดยต้องยืนยัน `SETUP_TOKEN` พร้อม CSRF token และไม่เปลี่ยน permission หรือ PHP/server configuration.

1. **Step 1 (Security):** ตรวจ PHP, extensions, writable directories, `vendor/` และ `public/build/` พร้อมยืนยัน `SETUP_TOKEN`
2. **Step 2 (Server & DB):** รับข้อมูล Application และทดสอบ database connection
3. **Step 3 (Organization):** รับข้อมูล Organization และ Owner account
4. **Step 4 (Install):** ยืนยันพิมพ์ INSTALL เพื่อสร้าง `.env`/`APP_KEY`, clear config cache, migrate เฉพาะ database ที่ยังไม่มี migration, provision roles/permissions/Owner และเขียน install lock

หลังสำเร็จ lock อยู่ที่ `storage/app/installer/installed.json`; `/setup` จะตอบ 404 และไม่สามารถ run migration ซ้ำจาก web ได้. Wizard ไม่ seed demo data และไม่อนุญาตใช้ database ที่มีตาราง `migrations` อยู่แล้ว. Wizard ปฏิเสธ release directory ที่มี `.env` อยู่แล้ว เพื่อไม่เขียนทับระบบเดิม; การ upgrade ใช้ deployment runbook/CLI แยกต่างหากหลัง backup.
