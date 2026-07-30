# Company OS / Lightweight ERP

ระบบ ERP (Enterprise Resource Planning) และระบบบริหารจัดการภายในองค์กรขนาดเล็ก (Company OS) ที่ออกแบบมาเป็นพิเศษสำหรับ SME, ทีมบริการ, ซอฟต์แวร์เอเจนซี, สตูดิโอ และหน่วยงานบริการทั่วไป ด้วยการเน้นย้ำความเรียบง่าย ประสิทธิภาพสูง ความปลอดภัยระดับสูง และความสมบูรณ์ของข้อมูลทางการเงินและการส่งมอบงาน

---

## 1. หลักการออกแบบและสถาปัตยกรรม (Design Principles & Architecture)

โปรเจกต์นี้ได้รับการพัฒนาขึ้นโดยยึดมั่นในหลักการทางวิศวกรรมซอฟต์แวร์ที่เข้มงวด:

*   **Multi-tenant Isolation:** ป้องกันความปลอดภัยของข้อมูลแยกตามองค์กร (`org_id`) ในทุกๆ ตารางหลัก คิวรีทั้งหมดจะถูกจำกัดขอบเขตในระดับ Row-level เพื่อไม่ให้เกิดการรั่วไหลของข้อมูลระหว่างบริษัท/องค์กรผู้ใช้งาน
*   **High Security Baseline:**
    *   ป้องกันภัยคุกคามตามมาตรฐาน OWASP Top 10 (CSRF Protection, SQL Injection Prevention ผ่าน PDO Bound Parameters เสมอ)
    *   ระบบการเข้ารหัสและบันทึกรหัสผ่านด้วย Secure Hashing (Laravel Breeze) ไม่มี plaintext password ในตารางระบบ
    *   Rate Limiting (`throttle:10,1`) ในเส้นทางที่เปราะบาง เช่น การเชิญผู้ใช้, การระงับสิทธิ์, และการเปลี่ยนสิทธิ์
    *   **Strict Re-authentication:** การบันทึกธุรกรรมทางการเงินและคำสั่งสำคัญ เช่น การรับชำระเงิน, การคืนเงิน (Reverse), การปฏิเสธใบเบิกค่าใช้จ่าย จะต้องได้รับการยืนยันรหัสผ่านใหม่ก่อนทำรายการเสมอ
*   **Auditability & State Snapshot:** ทุกๆ การสร้าง แก้ไข หรือทำลาย Master Data และเอกสารทางการเงิน จะถูกบันทึกไว้ในตาราง `audit_logs` พร้อมสลักข้อมูลภาพถ่ายสถานะก่อนและหลังแก้ไข (`before_json` และ `after_json`) เพื่อความโปร่งใสสูงสุด
*   **QA Integrity & Robust Tests:** การันตีคุณภาพโค้ดด้วยชุดทดสอบFeature Test ครอบคลุมเคสการเข้าถึง (Access Matrix), กติกาความปลอดภัย, และความถูกต้องของข้อมูลทางการเงิน พร้อมระบบจัดรูปแบบโค้ด (Prettier & Laravel Pint) และตรวจไวยากรณ์ (ESLint) เป็นระเบียบ 100%

---

## 2. เทคโนโลยีและภาษาที่ใช้ (Technology Stack)

การพัฒนาแบบ Hybrid ที่รวมเอาประสิทธิภาพของ Backend และความยืดหยุ่นของ Modern SPA Frontend เข้าไว้ด้วยกัน:

*   **Backend Layer:** PHP 8.3 + Laravel Framework 11/13
*   **Frontend Layer:** React 18 + TypeScript + Tailwind CSS (จัดทำสไตล์ Dark Mode คลอบคลุม)
*   **SPA Bridge:** Inertia.js (เชื่อมต่อ Laravel Controllers ไปหา React Pages ได้โดยตรงโดยไม่ต้องมี API overhead)
*   **Asset Bundler:** Vite
*   **Database:** MariaDB / MySQL (รองรับการทำ Row Locking ด้วย `lockForUpdate()`)
*   **Testing & Quality Tools:** PHPUnit 12, Prettier, ESLint (TypeScript-ESLint), Laravel Pint

---

## 3. โครงสร้างของโปรเจกต์ (Project Structure)

โครงสร้างโฟลเดอร์แบบ Monorepo/Laravel App:

```text
ERP/
├── backend/                             # โฟลเดอร์หลักของ Laravel + React Application
│   ├── app/                             # โค้ด Backend หลัก
│   │   ├── Http/Controllers/            # ตัวควบคุมคำสั่งแยกตามแผนก (Admin, Sales, Finance, Delivery)
│   │   ├── Models/                      # Eloquent Models (User, Invoice, Project, Task, Expense ฯลฯ)
│   │   ├── Support/                     # Helper Classes สำหรับตรวจสอบสิทธิ์ (ProjectAccess, SalesAccess ฯลฯ)
│   │   └── Policies/                    # นโยบายความปลอดภัยการเข้าถึง
│   ├── database/
│   │   ├── migrations/                  # โครงสร้างตารางฐานข้อมูลแยกตามวันเวลาการบันทึก
│   │   └── seeders/                     # ตัวจำลองข้อมูลทดสอบ (Phase1DemoSeeder)
│   ├── resources/
│   │   └── js/                          # โค้ด Frontend ฝั่ง React + TypeScript
│   │       ├── Components/              # UI Components ส่วนกลาง (Card, StatCard, Badge, DataTable ฯลฯ)
│   │       ├── Layouts/                 # เทมเพลตหน้าจอ (AuthenticatedLayout, GuestLayout)
│   │       ├── Pages/                   # หน้าจอหลักแยกตามแผนก
│   │       │   ├── Admin/               # หน้าตั้งค่าระบบ, ประวัติการบันทึก Audit
│   │       │   ├── Sales/               # ลูกค้า, ดีลการขาย
│   │       │   ├── Finance/             # แคตตาล็อกสินค้า, ใบแจ้งหนี้, ค่าใช้จ่าย
│   │       │   ├── Delivery/            # โครงการส่งมอบงาน, ตารางงานย่อย
│   │       │   └── Dashboard.tsx        # แดชบอร์ดสรุปผลรวมและส่วนย่อย
│   │       └── Utils/                   # ตัวช่วยจัดรูปแบบและการเงิน (Format, Money)
│   ├── tests/
│   │   └── Feature/                     # Feature Tests อัตโนมัติแบ่งตาม Phase
│   ├── vite.config.js                   # ตั้งค่า Vite Asset compiler
│   └── package.json                     # รายการ TypeScript / React dependencies
├── checklist.md                         # รายการความคืบหน้าของฟีเจอร์ MVP ทั้งหมด
├── gemini.md                            # บันทึกผลการตรวจสอบซอร์สโค้ดและประเด็นความปลอดภัย
├── gpt.md                               # บันทึกการตัดสินใจทางสถาปัตยกรรมของบอท
└── MVP_SCOPE.md                         # ขอบเขตระบบรุ่นทดสอบแรกสุด (MVP)
```

---

## 4. ความสามารถหลักของแต่ละโมดูล (Key Features)

ระบบแบ่งออกเป็น 5 มิติหลักที่ผูกผสานงานกัน:

### 1. ระบบองค์กรและสิทธิ์ผู้ใช้งาน (Hierarchy & User Management)
*   **โครงสร้างองค์กร 4 ระดับ:** รองรับโครงสร้างบริษัทย่อย `Branch` -> `Division` -> `Department` -> `User` พร้อมระบบย้ายสังกัดและตรวจสอบความถูกต้องแบบลำดับขั้น (Hierarchy Validation Guard)
*   **การคุ้มครองระบบ (Last Owner Protection):** ป้องกันไม่ให้มีการปลดผู้ใช้บทบาท `Owner` คนสุดท้ายออกจากระบบเพื่อหลีกเลี่ยงสิทธิ์หลงลืม
*   **ความปลอดภัยข้อมูลผู้ใช้ (Person ID Masking):** ข้อมูลเลขบัตรประชาชน `person_id` ถูก Mask ไว้ที่ฝั่ง Client และมี Security Policy ป้องกันการแก้ไขค่าโดยผู้ไม่มีสิทธิ์

### 2. โมดูล CRM & ดีลการขาย (CRM & Sales Pipeline)
*   **ข้อมูลลูกค้าและผู้ติดต่อ:** บันทึกข้อมูลบริษัทลูกค้า คู่ไปกับผู้ติดต่อใต้สังกัด โดยบังคับกฎ "Primary Contact มีได้เพียง 1 คนต่อลูกค้า 1 ราย"
*   **ไพป์ไลน์การขาย (Deals Stage):** ติดตามดีลตามกระบวนการ บังคับกรอกเหตุผลที่แพ้การขาย (`lost_reason`) เมื่อปิดดีลไม่สำเร็จ และบันทึกวันที่สำเร็จอัตโนมัติเมื่อปิดการขายได้ (`won`)
*   **ปฏิทินกิจกรรมกิจกรรมย่อย (Polymorphic Activity Timeline):** บันทึกการโทร, นัดประชุม, อีเมล และโน้ตติดตามงาน ภายใต้ดีลหรือลูกค้าอย่างเป็นระเบียบ

### 3. ระบบการเงินและเอกสารใบแจ้งหนี้ (Finance & Billing)
*   **แคตตาล็อกสินค้า (Product Catalog):** แยกสิทธิ์พนักงานขายดูรายการได้อย่างเดียว (`products.view`) กับฝ่ายบัญชีที่จัดการข้อมูลได้ (`products.manage`) พร้อมการันตีคีย์ SKU ไม่ซ้ำในหน่วยงาน
*   **การชำระเงินและการกู้ข้อมูล (Payments & Reversals):** รองรับการจ่ายเงินแบบบางส่วน (Partial) และเต็มจำนวน (Full) ป้องกันการจ่ายเงินเกินยอดค้างจ่าย (Overpay Guard) ด้วย Row-Level DB Locking และรองรับการทำรายการกู้เงินคืน (Reversal) พร้อมป้องกัน Double Reversal
*   **การจัดการค่าใช้จ่าย (Expenses & Attachment Files):** บันทึกค่าใช้จ่ายในบริษัทแนบใบเสร็จหลักฐาน โดยใบเสร็จจะถูกบันทึกแบบสุ่มชื่อ UUID เพื่อป้องกัน Directory Traversal และจำกัดสิทธิ์ดาวน์โหลดไฟล์เฉพาะบิลที่ผู้ใช้มีสิทธิ์เข้าถึง (ID harvesting protection)

### 4. ระบบส่งมอบโครงการและตารางงาน (Delivery & Projects Management)
*   **การสร้างโครงการ:** สามารถสร้างโครงการจากยอดมูลค่าของดีลขายที่ชนะ (`won_stage`) เพื่อรับข้อมูลงบประมาณและลูกค้ามาตั้งต้นทันที
*   **การจัดการงานย่อย (Tasks & Checklists):** รายการงานย่อยผูกกับโครงการ โดยมี checklists และการแสดงความคิดเห็น (Comments) แยกการดูแล
*   **สิทธิ์พนักงานทั่วไป (Member Status):** บัญชีพนักงานทั่วไปที่มีเพียงสิทธิ์ `tasks.view` จะได้รับอนุญาตให้ปรับเปลี่ยนได้เฉพาะสถานะของงานย่อยที่ได้รับมอบหมาย (`todo` -> `in_progress` -> `done`) แต่ไม่สามารถเปลี่ยนชื่อ ชื่องบประมาณ หรือผู้รับผิดชอบได้ เพื่อรักษาวินัยการทำงาน

### 5. ระบบรายงานผลแบบเรียลไทม์ (Live Dashboards)
*   **Admin Dashboard:** แสดงภาพรวมระบบ, บัญชีผู้ใช้ออนแอร์, และแถบสีเตือนความปลอดภัยของเซสชัน
*   **Sales Dashboard:** รายงานยอดมูลค่าดีลค้างอยู่ในกระบวนการ (Pipeline Value), การปิดการขายดีเด่น และระบุ Stale Deals (ดีลที่ไม่มีกิจกรรมติดตามเกิน 7 วัน)
*   **Finance Dashboard:** คำนวณรายรับที่ตกลง (Recognized Revenue), ยอดกระแสเงินสดรับ/จ่ายจริง (Cash In / Cash Out) และยอดผลต่างกำไรของบริษัท
*   **Delivery Dashboard:** สรุปยอดโครงการที่อยู่ระหว่างส่งมอบ, สรุปงานค้างส่งของพนักงาน (Task Load) และรายงาน **Delivery Risk** (สรุปจำนวนโครงการที่สุ่มเสี่ยง เช่น งบประมาณบานปลาย, กำหนดเวลาส่งเกินกำหนด โดยไม่มีสิทธิ์งบการเงินรั่วไหลไปยังบทบาท Member)

---

## 5. สถาปัตยกรรมและโครงสร้างฐานข้อมูล (Database Schema)

### หลักการตารางข้อมูล (Conventions)
1.  **Primary Keys:** ตารางหลักทั้งหมดใช้ UUID v7 หรือ Ordered UUID เพื่อความยืดหยุ่นและการจอง ID บนฝั่ง Client ได้ปลอดภัย
2.  **Tenant Isolation:** ทุกตารางข้อมูลเกี่ยวกับธุรกิจจะมีฟิลด์ `org_id` (FK → `organizations.id`) และมีการประมวลผล filter บน Controllers เสมอ
3.  **Audit Columns:** ทุกตารางจะมีฟิลด์ `created_at`, `updated_at`, `created_by`, `updated_by`, และ `deleted_at` (กรณีเปิดใช้ Soft Delete)
4.  **Financial Fields:** ยอดเงินทั้งหมดจะถูกเก็บเป็นประเภท `DECIMAL(18,2)` ร่วมกับฟิลด์ระบุสกุลเงิน `currency CHAR(3)` (เช่น 'THB')

### แผนผังความสัมพันธ์ (Entity Relationship Overview)
```text
organizations (Tenant Root)
  ├── branches ── divisions ── departments (โครงสร้างองค์กร)
  ├── users ── user_roles ── roles ── role_permissions ── permissions (ระบบสิทธิ์)
  ├── customers ── contacts
  │     └── deals ── invoices ── invoice_items ── payments (การขายและการเงิน)
  │           └── projects ── tasks ── task_checklists & task_comments (การส่งมอบ)
  ├── expenses (ค่าใช้จ่ายองค์กร ผูกกับ projects.id ได้แบบ Nullable)
  ├── files (ไฟล์แนบระบบความปลอดภัยสูง)
  └── audit_logs (ประวัติการทำธุรกรรมและคำสั่ง)
```

---

## 6. การตั้งค่าและการใช้งานระบบเบื้องต้น (Quick Start & Setup)

### 1. ความต้องการของระบบ (Prerequisites)
*   PHP 8.3 ขึ้นไป (พร้อมเปิดใช้งาน extension: `PDO`, `sqlite3`, `mbstring`)
*   Node.js (LTS v20 ขึ้นไป)
*   Composer (สำหรับ PHP package management)
*   pnpm (สำหรับ JavaScript package management)

### 2. การติดตั้ง (Installation)
เปิด Terminal และทำตามขั้นตอนดังนี้:

```powershell
# 1. เข้าไปยังโฟลเดอร์ backend
cd backend

# 2. คัดลอกการตั้งค่าสภาพแวดล้อม
copy .env.example .env

# 3. ติดตั้ง Dependencies ของระบบ Backend
composer install

# 4. ติดตั้ง Dependencies ของระบบ Frontend
pnpm install

# 5. สร้าง Application Key
php artisan key:generate

# 6. ลิงก์ Storage เพื่อแสดงโลโก้
php -c .php\php.ini artisan storage:link
```

### 3. การเตรียมฐานข้อมูลและจำลองข้อมูลทดสอบ (Migration & Seeding)
ระบบมาพร้อมกับ `Phase1DemoSeeder` ที่เตรียมชุดข้อมูลจำลองตั้งแต่โครงสร้างองค์กร, บทบาทสิทธิ์, ลูกค้า, ดีลขาย, ใบแจ้งหนี้, และตารางโปรเจกต์ไว้ให้ทดสอบเรียบร้อยแล้ว:

```powershell
# สั่งสร้างตารางและใส่ข้อมูล Seeder
php artisan migrate:fresh --seed
```

### 4. บัญชีทดสอบที่จัดเตรียมไว้ (Demo Test Accounts)
คุณสามารถลงชื่อเข้าใช้งานระบบได้จาก URL `/login` ด้วยรหัสผ่านเริ่มต้นคือ `password` ดังนี้:

| บทบาท (Role) | อีเมลสำหรับเข้าสู่ระบบ (Email) | สิทธิ์และการเข้าถึงข้อมูล |
| :--- | :--- | :--- |
| **Owner** | `owner@example.com` | **สิทธิ์สูงสุดขององค์กร** เข้าถึงได้ทุกเมนูและข้อมูลทางการเงินทั้งหมด (แนะนำสำหรับใช้สาธิต) |
| **Admin** | `admin@example.com` | จัดการผู้ใช้, สร้าง Master Data, ปิดการใช้งานสมาชิก และดูระบบโครงสร้างสาขา |
| **Sales** | `sales@example.com` | ฝ่ายขาย เห็นและเข้าจัดการเฉพาะลูกค้า/ดีลการขายที่ตนเองเป็นผู้ดูแล |
| **Project Manager** | `pm@example.com` | ผู้จัดส่งงาน เห็นและจัดการเฉพาะโปรเจกต์และบิลการเงินที่ระบุตนเองเป็นเจ้าของโครงการ |
| **Member** | `member@example.com` | พนักงานส่งมอบงานทั่วไป เห็นเฉพาะรายการงานย่อย (Tasks) ที่มอบหมายให้ตนเองเท่านั้น |
| **Viewer** | `viewer@example.com` | บัญชีผู้เข้ามาตรวจสอบข้อมูลเฉยๆ สิทธิ์การเข้าถึงแบบ Read-only |

### 5. การรันระบบในโหมดพัฒนา (Running Locally)
เปิด Terminal สองหน้าต่างคู่กันภายใต้โฟลเดอร์ `backend`:

*   **หน้าต่างที่ 1 (Backend Dev Server):**
    ```powershell
    php artisan serve
    ```
*   **หน้าต่างที่ 2 (Vite Frontend compiler):**
    ```powershell
    pnpm run dev
    ```
เข้าดูผลลัพธ์ผ่านเว็บเบราว์เซอร์ได้ที่: `http://127.0.0.1:8000`

---

## 7. การตรวจสอบคุณภาพและรันทดสอบอัตโนมัติ (Verification Commands)

ก่อนทำการส่งมอบโค้ดขึ้น GitHub ทุกครั้ง สามารถรันคำสั่งเหล่านี้เพื่อตรวจสอบความสะอาดเรียบร้อย:

```powershell
# 1. รันการทดสอบ Feature Tests ทั้งหมด (143 Tests, 901 Assertions)
php -c .php\php.ini vendor\phpunit\phpunit\phpunit

# 2. ตรวจสอบความสะอาดไวยากรณ์ React/JS
pnpm run lint

# 3. ตรวจสอบการจัดฟอร์แมต Prettier
pnpm run check-format

# 4. ทดสอบความถูกต้องของสไตล์และการเขียน PHP
composer run pint

# 5. ทดสอบบิลด์ Frontend Bundle สำหรับ Production
pnpm run build
```

---

## 8. ขอบเขตนอกเหนือจาก MVP (Deferred / Out of Scope for MVP)

เพื่อรักษาโครงสร้างระบบให้เบาและรวดเร็วสำหรับรุ่นแรกสุด (MVP) รายการฟีเจอร์ต่อไปนี้ได้รับการพิจารณาเลื่อน (Defer) ไปพัฒนาในรุ่นถัดไป (Post-MVP/V2):

*   **ระบบสมาชิกโครงการ (`project_members`):** เลื่อนไปเป็นสิทธิ์ทีมในรุ่นถัดไป โดยปัจจุบันใช้การ Enforce สิทธิ์ความปลอดภัยผ่านเจ้าของโครงการ (Project Owner) และผู้ได้รับมอบหมายงาน (Assignee) ซึ่งครอบคลุมและมีประสิทธิภาพเพียงพอแล้ว
*   **ระบบไฟล์แนบส่วนกลาง (Generic Files):** ใน MVP นี้การอัปโหลดไฟล์จะจำกัดเฉพาะสลิปโอนเงิน (Payment slip) และใบเสร็จค่าใช้จ่าย (Expense receipt) เท่านั้น ส่วนระบบแชร์ไฟล์บน Customer, Deal, Project, Task ทั่วไปจะถูกจัดเตรียมในรุ่น V2
*   **การจัดการภาษีขั้นสูง (Advanced Tax Compliance):** ระบบปันส่วนส่วนลดระดับหัวเอกสาร (Header Discount allocation), การทำเอกสารใบลดหนี้ (Credit Note) และเอกสารกำกับภาษีแบบเต็มรูปแบบ
*   **ตัวกรองช่วงเวลาบน Dashboard (Dashboard Date Filters):** การกรองข้อมูลแดชบอร์ดสรุปผลรายเดือน/รายปี หรือแบบกำหนดช่วงเวลาเอง (ปัจจุบันแสดงยอดสะสมแบบ All-time)
*   **ระบบส่งออกและแจ้งเตือน (Export / Notifications):** การส่งออกรายงานเป็น Excel/CSV และการส่งอีเมล/ไลน์แจ้งเตือนอัติโนมัติเมื่อเกิดกิจกรรมใหม่ (จะถูกจัดทำใน V2)

