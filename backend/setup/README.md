# ERP Setup Wizard

เปิด wizard ได้ที่ `/setup` หลัง copy release folder ขึ้น server แล้ว. รองรับทั้ง Document Root ที่ `backend/public` และ repository root (ผ่าน `setup/index.php`).

หลังเปิด installer แล้ว เปิด `/setup/system-requirements.php` เพื่อตรวจ requirement แบบละเอียดด้วย token เดียวกัน หน้านี้ไม่แสดงค่าลับหรือ `phpinfo()`; การตรวจ database credential อยู่ใน wizard.

## เปิด installer อย่างปลอดภัย

1. Copy `enable.php.example` เป็น `enable.php` ในโฟลเดอร์นี้
2. เปลี่ยนค่าที่ return เป็น random token อย่างน้อย 32 ตัวอักษร
3. เปิด `/setup`, ผ่าน preflight และกรอก token
4. กรอก config/database, Organization และ Owner
5. ยืนยัน `INSTALL`

Wizard สร้าง `.env`, `APP_KEY`, migrate เฉพาะ database ที่ยังไม่มี `migrations`, สร้าง Owner/roles/permissions และบันทึก lock ที่ `storage/app/installer/installed.json`. หลังสำเร็จ wizard ลบ `enable.php` และ `/setup` ใช้งานไม่ได้.

ไม่ต้องมี Composer หรือ Node บน server หาก release มี `vendor/` และ `public/build/` แล้ว. Web server ยังต้องใช้ PHP 8.3+, PDO MySQL, OpenSSL, mbstring, fileinfo และสิทธิ์เขียน `.env`, `storage/`, `bootstrap/cache/`.

ห้ามนำ `enable.php` หรือ `.env` เข้าสู่ source control.
