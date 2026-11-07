# ทะเบียนตรวจรับ UI แบบ local

สถานะ Local Complete วันที่ 7 พฤศจิกายน 2026 ตรวจบน Chrome และข้อมูลสังเคราะห์ การตรวจหน้ารายการครอบคลุมการเปิดหน้า การจัดวาง และข้อผิดพลาด JavaScript ส่วน workflow แสดงในตารางหลักฐานท้ายเอกสาร

[มาตรฐาน UI และ semantic tokens](ui-standards.md) · [ทะเบียนไฟล์และ routes](ui-screen-inventory.md)

## ERP Owner

ตรวจ 60 เส้นทาง รวม 266 กรณีที่ 390 และ 1440 px ทั้ง Light/Dark ตรวจเพิ่มที่ 320 px สำหรับหน้าการเงินและฟอร์มที่เคยจอล้น รวม Profile, POS Integration, Reconciliation และหน้าที่เข้าจากลิงก์รายละเอียด

| URL | หน้าจอ | บทบาทใน fixture |
| --- | --- | --- |
| `/goods-receipts` | Inventory / GRN | Owner |
| `/commercial-documents` | Commercial Docs | Owner |
| `/tax-reports` | Tax Reports | Owner |
| `/finance/cash-flow` | Cash Flow Forecast | Owner |
| `/currencies` | Currencies & FX | Owner |
| `/users` | Users | Owner |
| `/dashboard` | Dashboard | Owner |
| `/executive-dashboard` | Executive Dashboard | Owner |
| `/my-work` | My Work | Owner |
| `/workflows` | Approvals | Owner |
| `/sales-dashboard` | Sales Dashboard | Owner |
| `/customers` | Customers | Owner |
| `/deals` | Deals | Owner |
| `/quotations` | Quotations | Owner |
| `/procurement-dashboard` | Procurement Dashboard | Owner |
| `/purchase-orders` | Purchase Orders | Owner |
| `/suppliers` | Suppliers | Owner |
| `/products` | Products/Services | Owner |
| `/inventory-operations` | Inventory Operations | Owner |
| `/manufacturing/boms` | Bills of Materials (BOM) | Owner |
| `/manufacturing/work-orders` | Work Orders | Owner |
| `/finance-dashboard` | Finance Dashboard | Owner |
| `/invoices` | Invoices | Owner |
| `/expenses` | Expenses | Owner |
| `/finance/ocr` | AI Document OCR | Owner |
| `/bank-accounts` | Bank Accounts | Owner |
| `/bank-statements` | Bank Statements | Owner |
| `/petty-cash` | Petty Cash | Owner |
| `/cheques` | Cheques / PDC | Owner |
| `/treasury-reports` | Treasury Reports | Owner |
| `/e-tax` | e-Tax & RD Prep | Owner |
| `/accounting/chart-of-accounts` | Chart of Accounts | Owner |
| `/accounting/periods` | Accounting Periods | Owner |
| `/accounting/journals` | Journal Entries | Owner |
| `/accounting/reports` | GL Reports | Owner |
| `/fixed-assets` | Fixed Assets | Owner |
| `/hr-dashboard` | HR / Payroll Dashboard | Owner |
| `/hr/employees` | Employee Directory | Owner |
| `/hr/roster` | Shift Roster | Owner |
| `/hr/attendance-import` | Attendance Import | Owner |
| `/hr` | HR & Attendance | Owner |
| `/payroll` | Payroll | Owner |
| `/delivery-dashboard` | Delivery Dashboard | Owner |
| `/projects` | Projects | Owner |
| `/tasks` | Tasks | Owner |
| `/roles` | Roles & Permissions | Owner |
| `/settings/api-access` | API Access | Owner |
| `/settings/ai-configuration` | AI Configuration | Owner |
| `/audit-logs` | Audit Logs | Owner |
| `/settings/organization` | Organization Profile | Owner |
| `/settings/organization-structure` | Structure & Chain | Owner |
| `/settings/payment-gateway` | ฿Payment Gateway | Owner |
| `/settings/notifications` | Notifications | Owner |
| `/documents` | Documents | Owner |
| `/payments` | Payments | Owner |
| `/vendor-payments` | Vendor Payments | Owner |
| `/settings/api-access/webhooks` | API Webhooks | Owner |
| `/profile` | Profile และยืนยันลบบัญชี | Owner สังเคราะห์ |
| `/pos-integration` | POS Integration และ POS Setup ใน ERP | Owner ที่มีสิทธิ์ POS และคลัง |
| `/pos-integration/{instance}/reconciliation` | POS Ledger Reconciliation | Owner ผ่านช่วงวันปกติ/ผิดลำดับ Viewer ตอบ 403 |

## หน้าแยกจาก ERP Owner

| URL | หน้าจอ | บทบาทใน fixture | หลักฐาน |
| --- | --- | --- | --- |
| `/login`, `/forgot-password` | เข้าสู่ระบบและขอลิงก์กู้รหัสผ่าน | Guest | ผ่าน Chrome 6 กรณี รวมเปลี่ยนธีมโดยรักษาข้อมูลที่กรอก |
| `/register`, `/reset-password/{token}`, `/invites/{user}/{token}` | สมัคร กู้รหัส และรับคำเชิญ | Guest/คำเชิญสังเคราะห์ | เปิด route จริงและตรวจฟอร์ม Light/Dark ที่ 320/1440 px |
| `/confirm-password`, `/verify-email`, `/two-factor/setup`, `/two-factor-challenge` | ยืนยันตัวตนและตั้งค่า 2FA | บัญชีสังเคราะห์ตามสถานะ | Chrome ตรวจยืนยันรหัสผ่าน เปิดใช้ TOTP แสดงรหัสกู้คืน 8 รหัส และเปิดรหัสได้ครั้งเดียว |
| `/portal/sign-in`, `/portal` | Customer Portal | ลูกค้าสังเคราะห์ | ผ่าน Chrome ทั้ง Light/Dark ที่ 390/1440 px เห็นเอกสารของตนและเปิด Dashboard พนักงานไม่ได้ |
| `/portal/sign-in`, `/portal` | Supplier Portal | คู่ค้าสังเคราะห์ | ผ่าน Chrome ทั้ง Light/Dark ที่ 390/1440 px เปิดและปิด Vendor Bill ด้วยคีย์บอร์ด |
| `/finance/ocr/{extraction}` | ตรวจผล OCR และดูต้นฉบับ | ERP Owner สังเคราะห์ที่มีสิทธิ์ OCR | ผ่าน Chrome ทั้ง Light/Dark ที่ 320/1440 px ตรวจทางลัดบนมือถือ ต้นฉบับจาก route ที่ตรวจสิทธิ์ และ confidence badge |
| HTTP 403/404/419/429/500/503 | หน้า error กลาง | ตามสถานะการเข้าถึง | ตรวจ 404 ที่ 320 px ใน Chrome ทั้งสองธีม ส่วน HTTP status, Retry-After และ JSON ผ่าน Feature test |
| `/setup/`, `/setup/system-requirements.php` | ERP Setup และข้อกำหนดระบบ | ผู้ติดตั้ง local | Chrome 320 px ทั้งสองธีมบน release จำลอง ตรวจ Setup เปิดใช้งาน ปิดใช้งาน HTTP 403 และล็อกหลังติดตั้ง HTTP 404 |
| `/two-factor/recovery-codes`, `/invoice-payment/{invoice}` | Recovery Codes และหน้า PromptPay | บัญชีและรายการชำระเงินสังเคราะห์ | Chrome ตรวจ Blade ด้วยข้อมูลสังเคราะห์ที่ 320 px ทั้งสองธีม และ PHPUnit ตรวจ flow 2FA และ payment gateway จำลอง |
| `/pos-membership` | ทะเบียนสมาชิก ERP | ERP Owner บนฐานสังเคราะห์ | Chrome ผ่านที่ 320 px ทั้งสองธีมเมื่อเปิด feature flag และตอบ HTTP 503 เมื่อปิด |

## POS Blade

| URL | View / จุดเข้า | ผู้ใช้ | หลักฐาน |
| --- | --- | --- | --- |
| `/` | `pos.blade.php` → `resources/pos.tsx` | พนักงานสาขา | ตรวจหน้าขาย Retail/Restaurant/Pharmacy และสลิป 78 มม. Chrome ตรวจยอดก่อนส่วนลด Retail ที่ 390/1440 px ทั้งสองธีม รวมส่วนลด การคืนเงิน และเภสัชกรตรวจรับของคืนให้รอ ERP หรือบันทึกทำลาย |
| `/setup` | `setup.blade.php` | ผู้ติดตั้ง local ที่มี token | ตรวจติดตั้งฐานใหม่ ล็อกอิน และป้องกันติดตั้งซ้ำ |
| `/config` | `configuration.blade.php` | Manager ของสาขา | ตรวจ Light/Dark และสิทธิ์ |
| `/deliveries` | `deliveries.blade.php` → `public/delivery.js` | พนักงานที่มีสิทธิ์จัดส่ง | ตรวจ Light/Dark กรณีงานว่าง การคงข้อมูลเมื่อกรอกไม่ครบ และสร้างร่าง COD |
| `/membership` | `membership.blade.php` | พนักงานสาขาที่เปิดใช้สมาชิก | ตรวจ Light/Dark และเหตุผลที่ยังส่งคำขอไม่ได้เมื่อ ERP ยังตั้งค่าไม่ครบ |
| `/patients` | `patients.blade.php` → `resources/patients.ts` | บทบาทตามสิทธิ์ Pharmacy | Chrome 23 กรณี รวม 16 กรณี Light/Dark ที่ 390/1440 px สำหรับเภสัชกร, Manager ที่มี/ไม่มีสิทธิ์ และ cashier ตรวจ mask, การปฏิเสธข้ามสาขา และ 404 จาก Retail/Restaurant |
| `/offline/app` | `offline.blade.php` → `resources/offline/app.ts` | พนักงานที่ได้รับ permit | ตรวจทั้งสามประเภท เปิดซ้ำ offline และ reconnect/replay |
| `/menu` | `menu.blade.php` → `resources/menu.tsx` | ลูกค้าที่เปิดลิงก์เมนู | ตรวจ Light/Dark ทั้งกรณีไม่มี QR และเปิด QR ใช้งานได้ Chrome ตรวจจองโต๊ะ Walk-in, QR ประจำโต๊ะ, ส่งออเดอร์, ติดตามคิวครัว, กลับมาเชื่อมต่อ และชำระเงิน รวมถึงแยกบิลใน fixture ร้านอาหาร |
| `/tax-documents/{id}/print` | `tax-document.blade.php` | พนักงานสาขาตามสิทธิ์เอกสารภาษี | ตรวจภาพพิมพ์ ABB/Full สังเคราะห์ |
| `/deliveries/{id}/slip` | `delivery-slip.blade.php` | พนักงานสาขาตามสิทธิ์จัดส่ง | ตรวจภาพพิมพ์สังเคราะห์ |

## ผลตรวจรับใน workspace

| ขอบเขต | ผล | รายงาน |
| --- | --- | --- |
| ERP หน้ารายการ/รายละเอียด | 60 เส้นทาง 266 กรณี ไม่พบจอล้นหรือ page error | [ERP inventory report](../../temp/ui-redesign-acceptance/erp-current-ui-inventory-report.json) |
| Profile, ตาราง และฟอร์มกลาง | 6 กรณี 320/768/1440 px ตรวจคีย์บอร์ด ร่างเดิม และ semantic contrast | [Foundation report](../../temp/ui-redesign-acceptance/ui-foundation-report.json) |
| สีของสถานะและปุ่ม | 22 คู่สีใน Chrome จาก CSS ที่ build | [Status contrast report](../../temp/ui-redesign-acceptance/ui-status-contrast-report.json) |
| CreateEditDialog | 152 กรณี ตรวจ focus, ย่อ/คืนร่าง และ Keep/Discard | [Editor matrix](../../temp/ui-redesign-acceptance/ui-shared-matrix.json) |
| BOM และ Work Order | 36 กรณี รวมต้นไม้หลายชั้น ยอดวัสดุจาก API เดิม Retry และคีย์บอร์ด | [Manufacturing report](../../temp/ui-redesign-acceptance/manufacturing-ui-report.json) |
| Auth และรหัสกู้คืน | 40 กรณี รวมยืนยันตัวตนและเปิดใช้ 2FA จริงใน fixture | [Auth report](../../temp/ui-redesign-acceptance/auth-complete-ui-report.json) |
| ผู้ป่วย Pharmacy | 23 กรณี รวม masking และขอบเขตสาขา | [Patient report](../../temp/phase2884-patients-ui-acceptance-v1/browser-report.json) |
| ภาพเปรียบเทียบ ERP | 20 ภาพจาก frontend Git `d66e530` บนข้อมูลสังเคราะห์ชุดเดียวกัน | [Before capture index](../../temp/ui-redesign-acceptance/erp-baseline-capture-report.json) |

รายงาน Editor matrix ใช้กับ `CreateEditDialog` ส่วนหน้าต่างเฉพาะ BOM/Work Order ใช้รายงาน Manufacturing ชุดทดสอบใน repo แยกเส้นทางของแต่ละชุดแล้ว

ตัวอย่างภาพเทียบ [Dashboard ก่อน](../../temp/ui-redesign-acceptance/erp-before-dashboard-1440-light.png) · [Dashboard ปัจจุบัน](../../temp/ui-redesign-acceptance/erp-screen-dashboard-1440-light.png) · [Profile ก่อน](../../temp/ui-redesign-acceptance/erp-before-profile-1440-dark.png) · [Profile ปัจจุบัน](../../temp/ui-redesign-acceptance/profile-1440-dark.png)

### ภาพคู่ Light/Dark ของหน้าอ้างอิง

เก็บภาพจากแอปที่ 1440 px ด้วยข้อมูลสังเคราะห์ เทียบลำดับและการจัดวางกับ [mockup ต้นแบบ](redesign-reference/)

| หน้า | Light | Dark |
| --- | --- | --- |
| ERP Dashboard | [ภาพ Light](redesign-reference/verified/dashboard-light.png) | [ภาพ Dark](redesign-reference/verified/dashboard-dark.png) |
| Create Purchase Order | [ภาพ Light](redesign-reference/verified/purchase-order-light.png) | [ภาพ Dark](redesign-reference/verified/purchase-order-dark.png) |
| Retail POS | [ภาพ Light](redesign-reference/verified/retail-pos-light.png) | [ภาพ Dark](redesign-reference/verified/retail-pos-dark.png) |
| Restaurant POS | [ภาพ Light](redesign-reference/verified/restaurant-pos-light.png) | [ภาพ Dark](redesign-reference/verified/restaurant-pos-dark.png) |
| Pharmacy POS | [ภาพ Light](redesign-reference/verified/pharmacy-pos-light.png) | [ภาพ Dark](redesign-reference/verified/pharmacy-pos-dark.png) |

### ทำซ้ำการตรวจ Auth และ Manufacturing

เตรียม SQLite fixture ด้วย `backend/tests/Browser/prepare-remaining.php` ตามคู่มือชุด Browser แล้วรัน `php backend/tests/Browser/prepare-ui-specialized.php` จาก root ก่อนทดสอบ Auth แต่ละรอบ เปิด server ด้วย `backend/tests/Browser/remaining-router.php` ที่ `127.0.0.1:8139` และรันสคริปต์ `auth-complete-ui.cjs` หรือ `manufacturing-ui.cjs` จาก root กำหนด `PLAYWRIGHT_PATH` และ `CHROME_PATH` เมื่อใช้ Playwright/Chrome ที่ติดตั้งแยก

[Checklist local](../../checklist.md#checklist-ปรับ-layout-ทั้งระบบ--local-complete) · [หมายเหตุการทดสอบระบบจริง](../../README.md)
