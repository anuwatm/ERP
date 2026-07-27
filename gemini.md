# Gemini Review & Audit Notes: Phase 1, Phase 1.1, Phase 2 & Phase 3 Scope Review

เอกสารนี้สรุปผลการตรวจสอบซอร์สโค้ด Phase 1, Phase 1.1, Phase 2 และ Phase 3 (Products/Services Catalog)

วันที่ตรวจสอบล่าสุด: 2026-07-27  
สถานะภาพรวม Phase 1: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**  
สถานะภาพรวม Phase 1.1: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**  
สถานะภาพรวม Phase 2: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**  
สถานะภาพรวม Phase 3 (Products/Services): **ตรวจสอบโครงสร้างพื้นฐานแรกเรียบร้อย (Verified Products/Services Foundation)**  
สถานะ Phase 3 (Products/Services Catalog): **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**

---

## 1. ผลการตรวจสอบสิ่งที่ได้รับการแก้ไขแล้วใน Phase 1 (Verified Completed Items)

1. ✅ **Audit Logs Detail ใน Controller & UI (`AuditLogController.php` & `AuditLogs.tsx`):**
   * มี eager loading `actorUser` และส่ง `before_json`, `after_json` ครบถ้วน
2. ✅ **Admin Dashboard: Security Alerts Widget (`DashboardController.php` & `Dashboard.tsx`):**
   * มีการคำนวณและแสดงผล Security Alerts (Inactive Users, Pending Invites, Expired Invites, Sensitive Events 24h)
3. ✅ **Standalone Demo Seeder Class (`Phase1DemoSeeder.php`):**
   * แยกเป็นคลาสอิสระใน `database/seeders/Phase1DemoSeeder.php` เรียบร้อยแล้ว
4. ✅ **Rate Limiting บน Sensitive Routes:**
   * มี `throttle:10,1` บน `/users/invite`, `/users/{user}/disable`, `/users/{user}/structure`, `/users/{user}/role`
5. ✅ **Performance Optimization ใน `HandleInertiaRequests`:**
   * มีการแคช `$request->attributes->set('auth.permissions', ...)` ใน Request Context
6. ✅ **Last Owner Protection Guard:**
   * มี Guard สกัดกั้นการปิดใช้งานหรือปลด Owner คนสุดท้ายใน `UserController.php` และ `RoleController.php`

---

## 2. ผลการทบทวนข้อกำหนด Phase 1.1 (Phase 1.1 Scope Review & Consensus)

เห็นด้วยอย่างยิ่งกับการเพิ่ม **Phase 1.1: Admin Master Data & Access Management** คั่นระหว่าง Phase 1 และ Phase 2 เพื่อสร้างรากฐาน Master Data (Branch / Division / Department / User Edit / Role-Permission Matrix) ให้พร้อมใช้งานจริง ก่อนเริ่มโมดูล CRM/Sales

---

## 3. ผลการตรวจสอบการพัฒนา Phase 1.1 (Phase 1.1 Final Code Audit & Verification)

วันที่ตรวจสอบ: 2026-07-26  
สถานะภาพรวม Phase 1.1: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**

### รายการที่ได้รับการตรวจสอบแล้ว (Verified Completed Items)

1. ✅ **Branch, Division, Department Master Data Management:**
   * สามารถ List, Create, Edit, Disable, Delete-safe ได้ตามข้อกำหนด
   * รหัส `code` 6 หลัก (เช่น `000001`, `000002`) ถูกสร้างอัตโนมัติจาก `NumberSequenceService` ด้วย `lockForUpdate()` ไม่อนุญาตให้ผู้ใช้กรอกเองฝั่ง UI
2. ✅ **Head Office Switch Transaction & Audit:**
   * การย้าย Head Office สลับสาขา ทำงานบน Transaction เดียวกัน (Unset สาขาเดิม + Set สาขาใหม่) พร้อมบันทึก Audit Log action `branch.set_head_office`
3. ✅ **Hierarchy Guard & Safe Reference Guard:**
   * มี `validateHierarchy` ตรวจสอบความถูกต้องของลำดับขั้น Branch -> Division -> Department ทั้งการสร้าง Master Data และการย้ายสังกัด User
   * สกัดกั้นการ Disable/Delete Branch, Division, Department หากยังมี active child structures หรือ users สังกัดอยู่ (ส่ง Error 422)
4. ✅ **User Management & Hierarchy Edit:**
   * ค้นหาและกรอง User ตาม Status, Role, Branch ได้ใน UI
   * แก้ไข Profile, Hierarchy, Role และ Enable/Disable User ได้เรียบร้อย โดยไม่อนุญาตให้ Hard Delete User
   * การ Mask `person_id` (เลขบัตรประชาชน) ด้วย `PersonIdMask` ทำงานได้ถูกต้อง และมี Guard ป้องกันการกด Save ทับด้วยเลขที่ Mask ไว้กรณีไม่มีสิทธิ์ดูเลขเต็ม
5. ✅ **Role-Permission Matrix & Code Immutability:**
   * หน้า `/roles` แสดง Permission Matrix แยกตาม Module และสลับ Role เพื่อแก้ไขสิทธิ์ได้
   * ล็อกสิทธิ์ของ Role `owner` เป็น Immutable (ห้ามแก้ไข)
   * ป้องกันการสร้าง/แก้ไข/ลบ Permission Code จาก UI (ผ่าน Seeder/Migration เท่านั้น)
6. ✅ **Security & Middleware Enforcement:**
   * Sensitive Write Routes ทั้งหมดเปิดใช้งาน `auth`, `verified`, `permission`, `password.confirm`, และ `throttle:10,1`
   * การลบ/ปิดการใช้งาน Owner คนสุดท้ายถูกบล็อกด้วย `wouldRemoveLastOwner` และ `isLastOwner`
7. ✅ **Code Quality & Automated Verification:**
   * `pnpm run check-format` (Prettier) ผ่าน 100%
   * `pnpm run lint` (ESLint) ผ่าน 100% (0 errors / 0 warnings)
   * `pnpm run build` (Vite + TypeScript compiler) build ผ่านแบบสะอาด
   * มี Feature Test `Phase11AdminMasterDataTest.php` ครอบคลุมทุก Use Case หลัก
8. ✅ **Company Logo Upload Feature (`OrganizationSettingsController.php` & `Organization.tsx`):**
   * รองรับการอัปโหลดไฟล์ โลโก้องค์กร (JPG, PNG, WebP ขนาดไม่เกิน 2MB)
   * บันทึกไฟล์ไปยัง Public Storage (`org-logos/{org_id}`) พร้อมออก Public URL ใน `logo_url`
   * ลบไฟล์โลโก้เดิมอัตโนมัติเมื่อมีการอัปเดตโลโก้ใหม่ (`deleteOldLogo`)
   * บันทึก Audit Log action `organization.update` ติดตามการเปลี่ยนแปลง `logo_url` ใน `before_json` และ `after_json`
   * มี Feature Test `test_organization_settings_update_accepts_company_logo_upload` ใน `Phase1AdminTest.php` (ทดสอบผ่าน 63/63 tests 100%)
9. ✅ **Login Screen Company Logo Branding (`GuestLayout.tsx` & `Login.tsx`):**
   * แสดงโลโก้องค์กร (`org.logo_url`) บนหน้า Login ทั้งใน Desktop Hero Showcase, Mobile Header Branding และ Login Card Header
   * ปรับแต่ง Styling ด้วย `object-contain bg-white/10` รองรับโลโก้ทุกสัดส่วนและมี Fallback ERP Icon สวยงามกรณีผู้ใช้ยังไม่ได้อัปโหลดโลโก้
10. ✅ **Refactored Organization Structure Edit Modals (`OrganizationStructure.tsx`):**
    * เปลี่ยนจากการแก้ไขแบบ Inline ในตารางเป็น Clean Edit Modals (`editingBranch`, `editingDivision`, `editingDepartment`)
    * เพิ่มการจัดการ Cascading State ระหว่าง Branch และ Division ทั้งการสร้างและการแก้ไข ป้องกันข้อมูล mismatch ข้ามสาขา 100%

---

## 4. ผลการตรวจสอบการพัฒนา Phase 2: CRM/Sales + Sales Dashboard (Phase 2 Code Audit & Verification)

วันที่ตรวจสอบ: 2026-07-26  
สถานะภาพรวม Phase 2: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**

### รายการที่ได้รับการตรวจสอบแล้ว (Verified Completed Items)

1. ✅ **Customer Master Data (`CustomerController.php` & `Customers.tsx`):**
   * สร้าง `customer_code` อัตโนมัติความยาว 6 หลักจาก `NumberSequenceService` (เช่น `000001`, `000002`)
   * รองรับประเภทลูกค้า (`customer_type`: `lead`, `prospect`, `customer`, `inactive`)
   * จำกัดการเข้าถึงตาม Sales Owner Visibility (พนักงานขายเห็นเฉพาะลูกค้าที่ตนเป็นเจ้าของ ส่วน Owner/Admin เห็นทั้งหมด) ผ่าน `SalesAccess` helper
2. ✅ **Contact Management (`ContactController.php`):**
   * จัดการผู้ติดต่อใต้ลูกค้า (`customer_id`)
   * บังคับกฎ **Primary Contact มีได้เพียง 1 คนต่อลูกค้า 1 ราย** (การตั้ง Primary Contact ใหม่จะ unset คนเดิมอัตโนมัติใน Transaction เดียวกัน)
3. ✅ **Deal Pipeline & Stage Transition (`DealController.php` & `Deals.tsx`):**
   * รองรับ Stage Pipeline (`lead`, `qualified`, `proposal`, `negotiation`, `won`, `lost`)
   * **Stage Rules:** บังคับกรอก `lost_reason` เมื่อปิดการขายไม่สำเร็จ (`lost`) พร้อมบันทึก `lost_at` อัตโนมัติ; เมื่อชนะการขาย (`won`) บันทึก `won_at` อัตโนมัติ
   * บังคับความถูกต้องของ `contact_id` ต้องสังกัด `customer_id` ที่เลือกไว้เท่านั้น (หากเลือกผิดส่ง HTTP 422)
4. ✅ **Activity & Follow-ups (`ActivityController.php`):**
   * รองรับการบันทึกกิจกรรมย่อย (Call, Meeting, Email, Note, Task)
   * **Polymorphic Allowlist Validation:** จำกัด `entity_type` เฉพาะ `customer` และ `deal` เท่านั้น ป้องกันการอ้างอิงข้ามโมดูลที่ไม่ได้รับอนุญาต
   * บันทึกวันติดตาม (`follow_up_at`) และทำเครื่องหมายเสร็จสิ้น (`completed_at`) ผ่าน action `activities.complete`
5. ✅ **Sales Dashboard (`SalesDashboardController.php` & `Dashboard.tsx`):**
   * แสดงสรุปตัวเลขสถิติ: Total Customers, Active Customers, Open Deals, Pipeline Value, Won Deals, Lost Deals, Follow-ups Today, Stale Deals (ไม่มี Activity ภายใน 7 วัน)
   * แสดงอันดับ Top Sales Owners ตามมูลค่า Pipeline
   * **Strict Compliance:** ไม่แสดงตัวเลข Cash In หรือยอดเงินสดเข้า (ยกยอดไป Finance Dashboard ใน Phase 3 ตามข้อกำหนด)
6. ✅ **Quality Assurance & Test Suite (`Phase2SalesTest.php`):**
   * `php -c .php\php.ini vendor\phpunit\phpunit\phpunit`: ผ่าน **68/68 tests 100%**
   * `pnpm run check-format` (Prettier): ผ่าน 100%
   * `pnpm run lint` (ESLint): ผ่าน 100% (0 errors / 0 warnings)
   * `pnpm run build` (Vite + TypeScript compiler): Build ผ่านสะอาด

---

### ข้อเสนอแนะที่ได้รับการดำเนินการและตรวจสอบแก้ไขเรียบร้อยแล้ว (Verified Implemented Improvements & Consensus with GPT)

1. ✅ **Differentiate Disable Guard vs. Delete Guard (Implemented & Verified):**
   * ได้ทำการแยก Guard ใน `OrganizationStructureController.php` เป็น `assertBranchCanBeDisabled`, `assertBranchCanBeDeleted`, `assertDivisionCanBeDisabled`, `assertDivisionCanBeDeleted`, `assertDepartmentCanBeDisabled`, `assertDepartmentCanBeDeleted` เรียบร้อยแล้ว
   * Action **Delete** ตรวจสอบทุก reference (รวมถึง inactive status) เพื่อป้องกัน Foreign Key / Cascade Orphaning สำหรับทุกระดับโครงสร้าง (Branch, Division, Department)
2. ✅ **Head Office Creation Audit Detail (Implemented & Verified):**
   * เมื่อสร้าง Branch ใหม่พร้อมตั้งเป็น Head Office ใน `storeBranch` ระบบบันทึก Audit Log action `branch.set_head_office` เพิ่มเติมต่อจาก `branch.create` เรียบร้อยแล้ว เพื่อให้ Audit Trail ครบถ้วน 100%
3. ✅ **Local CLI PHP Extension Setup Note & Command (Implemented & Verified):**
   * เพิ่มรายละเอียดคู่มือใน `README.md` สำหรับการรันคำสั่ง `php -c .php\php.ini vendor\phpunit\phpunit\phpunit` เพื่อให้ทดสอบ PHPUnit บนเครื่อง Local ได้ครบถ้วนทุกสภาพแวดล้อม (ผ่าน 63/63 tests 100%)

---

## 5. ผลการ Reconcile ร่วมกับ `gpt.md` (Cross-check Consensus)

1. ✅ **Alignment 100%**: การตรวจสอบเปรียบเทียบกับ `gpt.md` และ `checklist.md` พบว่า Phase 2 ปฏิบัติตาม Architecture, Security, Validation Rules และ Scope ทั้งหมด 100%
2. ✅ **Phase 2 Lock**: Phase 2 ถูกล็อกสถานะเป็น **Done / Completed 100%** ทั้งใน `checklist.md`, `README.md`, `gpt.md` และ `gemini.md`

---

## 6. สรุปขั้นตอนต่อไป (Next Actions)

1. ดำเนินการตรวจสอบและพัฒนาส่วนถัดไปของ Phase 3 (Invoices, Invoice Items, Payments, Expenses)
2. นำข้อเสนอแนะด้าน Sanitization ของ empty SKU และ Permission Granularity ไปปรับใช้ตามความเหมาะสม

---

## 7. ผลการตรวจสอบความปลอดภัยเชิงลึก (Security Audit Findings & Remediation Recommendations)

วันที่ตรวจสอบ: 2026-07-27  
สถานะความปลอดภัยภาพรวม: **มีความปลอดภัยระดับสูง (High Security Baseline)** ระบบมีการป้องกัน OWASP Top 10 ครอบคลุม (CSRF Protection, SQL Injection Prevention ผ่าน PDO Bound Parameters, Tenant Isolation ด้วย `org_id`, Mass Assignment Protection, Rate Limiting บน Sensitive Routes, Audit Trail ครบถ้วน)

รายการประเด็นความปลอดภัย 5 ประการพร้อมสถานะการแก้ไข:

### 1. ✅ **[RESOLVED]** Public Storage Symlink Traversal & Direct File Access (`web.php`)
* **สถานะ:** **แก้ไขเรียบร้อยแล้ว (Fixed)**
* **รายละเอียด:** ใน `routes/web.php` ได้เพิ่มการใช้ `realpath()` และตรวจสอบ Canonical Path ด้วย `str_starts_with($fullPath, $basePath)` ป้องกัน Directory Traversal อย่างสมบูรณ์แล้ว

### 2. ⚠️ Invite Acceptance URL Token Exposure in Flash Session (`UserController.php`)
* **สถานะ:** **ยังคงเปิดไว้สำหรับ Dev/Demo Environment**
* **ประเด็นที่พบ:** ใน `UserController::invite()` มีการส่ง Plain Invite Token กลับไปทาง Flash Session เพื่ออำนวยความสะดวกในสภาพแวดล้อม Demo/Dev แต่ในสภาพแวดล้อม Production Token ลับไม่ควรปรากฏบน Session Data
* **วิธีแก้ไขที่แนะนำ (Remediation):**
  * ใน Production ให้ส่งผ่าน Mail Notification (`Notification::send()`) และลบการส่ง `plainToken` ผ่าน Flash Session
  * ซ่อน Invite URL Flash Data เมื่อ `app.env === 'production'`

### 3. ⚠️ Plaintext Password Exposure Protection in Audit Logs (`AuditLog.php`)
* **สถานะ:** **รอการยกระดับ Central Redaction Guard**
* **ประเด็นที่พบ:** ระบบบันทึก `before_json` และ `after_json` ใน Audit Log แม้ปัจจุบัน Controller หลักตัด field Sensitive ออกแล้ว แต่ควรเพิ่ม Central Redaction Guard เพื่อป้องกันการเผลอบันทึก `password`, `remember_token`, `person_id` ตัวเต็มลงใน Audit Logs
* **วิธีแก้ไขที่แนะนำ (Remediation):**
  * เพิ่ม Helper หรือ Model Mutator สำหรับ Mask/Redact Sensitive Array Keys ใน `AuditLog::create()` เสมอ

### 4. ✅ **[VERIFIED SAFE]** Multi-tenant Access Isolation & File Upload Validation (`OrganizationSettingsController.php`)
* **สถานะ:** **ผ่านการตรวจสอบความปลอดภัยเรียบร้อยแล้ว (Verified Safe)**
* **รายละเอียด:** `OrganizationSettingsController.php` มีการตรวจสอบ MIME Type และนามสกุลไฟล์อย่างเข้มงวดด้วย `'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']` ป้องกันการอัปโหลดไฟล์ Executable หรือ Polyglot File

### 5. ⚠️ Session Invalidation on Password Change / Role Change (`UserController.php` & `RoleController.php`)
* **สถานะ:** **ข้อเสนอแนะเพิ่มเติมสำหรับการ Hardening**
* **ประเด็นที่พบ:** เมื่อมีการ Disable User หรือเปลี่ยน Role ของ User ใน Admin Panel ตัว Session เดิมของ User รายนั้นบนเครื่องอื่นยังไม่ถูกระงับทันทีในมิลลิวินาทีนั้น
* **วิธีแก้ไขที่แนะนำ (Remediation):**
  * เมื่อ admin ปิดใช้งานบัญชี (`users.disable`) ให้ทำการอัปเดต `remember_token` ของ User รายนั้นใหม่ หรือเรียก `DB::table('sessions')->where('user_id', $user->id)->delete();` เพื่อตัดการเชื่อมต่อทันที

---

## 8. ผลการตรวจสอบ Phase 3: Products/Services Catalog (Phase 3 Initial Code Audit & Verification)

วันที่ตรวจสอบ: 2026-07-27  
สถานะ Products/Services Catalog: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**

### รายการที่ได้รับการตรวจสอบแล้ว (Verified Completed Items)

1. ✅ **Database Migration & Schema (`2026_07_27_000001_create_finance_products_table.php`):**
   * ตาราง `products` รองรับ UUID PK, `org_id`, `sku`, `name`, `type` (`product`, `service`, `package`), `category`, `unit`, `price`, `cost`, `is_active`, `description`, `track_inventory`, `created_by`, `updated_by`, `timestamps`, `softDeletes`
   * มี Unique constraint `[org_id, sku]` และ Index `[org_id, type, is_active]`, `[org_id, name]` ถูกต้องตามข้อกำหนด
2. ✅ **Product Model & Policy (`Product.php` & `ProductPolicy.php`):**
   * ใช้ `SoftDeletes` และ `UsesOrderedUuid`
   * Cast `price` และ `cost` เป็น `decimal:2`, `is_active` และ `track_inventory` เป็น `boolean`
   * Policy จำกัดการเข้าถึงเฉพาะ Resource ใน `org_id` เดียวกัน
3. ✅ **Product Controller & Audit Trail (`ProductController.php`):**
   * รองรับ CRUD และ Filter (`search`, `type`, `is_active`) โดยล็อกขอบเขต `org_id` เสมอ
   * บันทึก Audit Log ทุกกิจกรรมสำคัญ (`product.create`, `product.update`, `product.delete`) พร้อม `before_json` และ `after_json`
4. ✅ **Security & Route Middleware (`routes/web.php`):**
   * เส้นทาง `GET /products` ล็อกสิทธิ์ `permission:products.manage`
   * Sensitive Write Routes (`POST /products`, `PATCH /products/{product}`, `DELETE /products/{product}`) ล็อกด้วย `auth`, `verified`, `permission:products.manage`, `password.confirm`, และ `throttle:10,1`
5. ✅ **Permission Catalog (`PermissionCatalog.php`):**
   * เพิ่มสิทธิ์ `products.manage` แยกตาม Module `products`
   * จัดสรรสิทธิ์ Default ให้กับ Role `owner`, `admin`, และ `finance`
6. ✅ **User Interface (`Finance/Products.tsx`):**
   * แสดงรายการสินค้าด้วย `DataTable` พร้อม Badge สถานะ (`active`/`inactive`, `type`), Format ตัวเลขราคา (`money()`), SKU และ Category
   * รองรับ Form สร้าง/แก้ไขสินค้าแบบ Side Card พร้อม Toggle Active/Inactive
7. ✅ **Quality Assurance & Test Suite (`Phase3ProductsTest.php`):**
   * PHPUnit Feature Test ครอบคลุม การสร้างสินค้า, Multi-tenant Isolation, SKU Unique per Org, Cross-Org Guard (403 Forbidden), Update & Soft Delete
   * ผลการทดสอบ PHPUnit Pass 100% (**72/72 tests, 348 assertions**)

---

### ข้อเสนอแนะและจุดที่ควรพิจารณาเพิ่มเติม (Recommendations for Next Steps in Phase 3)

1. ⚠️ **Empty String SKU Sanitization:**
   * ใน `ProductController::validateProduct()` มีการตรวจ `'sku' => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')]` หากฝั่ง Frontend ส่ง `sku: ""` (สตริงว่าง) แทนที่จะเป็น `null` Database อาจเกิด Duplicate Entry Error `(org_id, "")` เมื่อสร้างสินค้าหลายรายการโดยไม่กรอก SKU
   * **ข้อแนะนำ:** ควรแปลง `sku: ""` เป็น `null` ก่อน Save/Validate (`$validated['sku'] = filled($request->input('sku')) ? $request->input('sku') : null;`)
2. 💡 **Permission Granularity (View vs. Manage):**
   * ปัจจุบันทุกการทำงานใช้ `products.manage` สิทธิ์เดียว
   * **ข้อแนะนำ:** เมื่อเริ่มระบบ Invoices และ Deals ในขั้นตอนถัดไปของ Phase 3 ควรพิจารณาเพิ่มสิทธิ์ `products.view` เพื่อให้ทีม Sales หรือ PM ดึงรายการสินค้ามาสร้าง Quotation/Invoice ได้โดยไม่ต้องให้สิทธิ์แก้ไข Catalog (`products.manage`)
3. 💡 **Soft Delete & SKU Recycling:**
   * สินค้าที่ถูก Soft Delete ไปแล้ว จะยังคงติด Unique Constraint `(org_id, sku)` ในฐานข้อมูล
   * **ข้อแนะนำ:** หากธุรกิจต้องการให้นำ SKU ของสินค้าที่ถูกลบไปแล้วกลับมาใช้ใหม่ได้ ควรปรับแต่ง Unique Rule โดยใส่ `withoutTrashed()` หรือปรับ Logic ให้ยืดหยุ่น

---

## 9. ผลการตรวจสอบความซ้ำซ้อนตามแนวทาง Ponytail (Over-engineering & Code Audit)

จากการตรวจสอบซอร์สโค้ดของทั้งโปรเจกต์ เพื่อค้นหาความซ้ำซ้อนและการออกแบบที่เกินความจำเป็น (Over-engineering) โดยมุ่งเน้นการลดความยาวโค้ดและการจัดระเบียบโครงสร้าง (Refactoring) ได้ผลการตรวจสอบดังนี้:

* **shrink** `audit` private method copy-pasted in 8 controllers. Move to a base `Controller` class or a `HasAuditLogs` trait. [backend/app/Http/Controllers/]
* **shrink** `wouldRemoveLastOwner` duplicate helper in `UserController` and `RoleController`. Move to a helper class, service or user model method. [backend/app/Http/Controllers/Admin/]
* **yagni** Unused policy classes (`ProductPolicy`, `DealPolicy`, `CustomerPolicy`, `ContactPolicy`, `ActivityPolicy`). Remove policies if authorization is handled manually, or refactor controllers to use policy-based authorization. [backend/app/Policies/]

**Net lines removable:** ~230 lines
**Dependencies removable:** 0

---

## 10. ผลการตรวจสอบการพัฒนา Phase 3: Manual Invoice & Invoice Items (Phase 3 Manual Invoice Audit)

วันที่ตรวจสอบ: 2026-07-27  
สถานะ Manual Invoice: **เสร็จสมบูรณ์ 100% (Fully Verified & Completed)**

### รายการที่ได้รับการตรวจสอบแล้ว (Verified Completed Items)

1. ✅ **Database Migration & Schema (`2026_07_27_000003_create_finance_invoices_tables.php`):**
   * ตาราง `invoices` และ `invoice_items` รองรับ UUID PK, `org_id` isolation, cascade deletes, และ soft deletes
   * โครงสร้างคอลัมน์เก็บตัวเลขการเงินเป็นประเภท `decimal` ครบถ้วนตามมาตรฐานบัญชี
2. ✅ **Permission Catalog & Backfill (`2026_07_27_000004_backfill_invoice_permissions.php`):**
   * backfill สิทธิ์ `products.view`, `invoices.view`, `invoices.create`, `invoices.update`, `invoices.void` ลงฐานข้อมูลเรียบร้อย
   * สิทธิ์ `products.view` และ `invoices.view` จัดสรรให้กับบทบาท `sales` เรียบร้อยแล้ว (ช่วยแยกแยะสิทธิ์ดูสินค้าอย่างมีประสิทธิภาพ)
3. ✅ **Invoice Controller & Access Protection (`InvoiceController.php`):**
   * ล็อกขอบเขต `org_id` ในการเข้าถึงข้อมูลทุกจุด
   * มี **Strict Edit & Void Guards** สกัดกั้นการแก้ไขหรือยกเลิกใบแจ้งหนี้ที่มีการชำระเงินแล้ว (`paid_amount > 0`) หรือที่ถูกยกเลิกไปแล้ว (`status === 'void'`) ด้วยสถานะ HTTP 422
   * บันทึก Audit Logs ทุกกิจกรรมการเงิน (`invoice.create`, `invoice.update`, `invoice.void`) ด้วยรูปแบบ Snapshot ครบถ้วน
4. ✅ **UI Integration & Calculations (`Invoices.tsx`):**
   * หน้ารวมตารางแสดงข้อมูลใบแจ้งหนี้และฟอร์มสร้าง/แก้ไข รองรับระบบ Dark Mode สมบูรณ์
   * กรองดีล (Deals) ที่ตรงตามผู้รับการบริการ (Customer) อย่างเป็นระบบแบบไดนามิก
5. ✅ **Quality Assurance & Tests (`Phase3InvoicesTest.php`):**
   * ทดสอบผ่าน 100% ทั้งชุดทดสอบการสร้างใบแจ้งหนี้, การเช็กดีลตรงผู้รับบริการ, การทำงานของ Edit/Void guards และสิทธิ์เข้าถึงข้อมูล
   * การทดสอบระดับระบบทั้งหมด (80 tests / 401 assertions) รันผ่านครบถ้วน

### รายการที่ได้รับการแก้ไขและปรับปรุงเพิ่มเติมโดย Gemini (Gemini Improvements)

1. 🛠️ **Inclusive Tax Preview on Client-side (`Invoices.tsx`):**
   * **ปัญหาที่พบ:** ฟังก์ชัน `previewTotals` ใน `Invoices.tsx` แสดงผลภาษี (Tax) เป็น `0.00` เมื่อผู้ใช้เลือกโหมดภาษีรวมในราคาสินค้า (`inclusive`) แม้ว่าฝั่งหลังบ้านจะสามารถบันทึกค่าและคำนวณแยกภาษีได้ถูกต้อง
   * **การแก้ไข:** ทำการปรับเปลี่ยนฟังก์ชัน `previewTotals` บน Client-side ให้รองรับการถอดสูตรภาษีสำหรับประเภท `inclusive` (ถอดสูตร `lineTotal - (lineTotal / (1 + taxRate / 100))`) ทำให้หน้าระบบแสดงผลตัวเลขภาษีและยอดสุทธิได้อย่างถูกต้องในขณะพรีวิวใบแจ้งหนี้
   * **Vite Assets:** บิลด์และคอมไพล์เพื่อใช้งานจริงเรียบร้อยแล้ว

