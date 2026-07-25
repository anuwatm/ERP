# GPT Decision Notes: ERP Phase 0

**วันที่:** 2026-07-25  
**สถานะ:** Phase 0 docs lock / ยังไม่เริ่ม coding  
**หน้าที่:** บันทึกเหตุผลของ GPT เมื่อรับหรือไม่รับข้อเสนอจาก Grok/Gemini

---

## 1. Consensus ล่าสุด

เห็นด้วยกับ Grok/Gemini และอัปเดตแล้ว:

- `checklist.md` ต้องเป็น source of truth สำหรับ tracking งาน
- หลังจบแต่ละ Phase ต้องอัปเดต root `README.md`
- Phase 1 checklist ต้องเพิ่ม security, PII, routes/screens, seed และ token tests
- Phase 3 checklist ต้องเพิ่ม idempotency, reversal date, attachment permission และ finance re-auth
- Phase 4 checklist ต้องเพิ่ม `progress_percent` manual-only, no `actual_cost`, blocked overdue rule, internal task และ `task_checklists.org_id`
- Phase 5 checklist ต้องเพิ่ม log redaction, no Cash Balance leak, negative scope tests และ UAT expected values
- README ต้องมี decision notes, quick start Phase 1, links เอกสารหลัก, out-of-scope และ implemented features template

---

## 2. ข้อเสนอที่ยังไม่รับเป็น implementation ตอนนี้

### 2.1 `project_members`

ยังไม่เพิ่มใน MVP ตอนนี้

เหตุผล:

- Docs ปัจจุบันล็อก owner/assignee-only แล้ว
- Member ใช้ `/tasks` เป็นหลัก และเห็น project/customer linked read-only จาก task detail
- การเพิ่ม `project_members` ไม่ใช่แค่ migration 4 columns แต่รวม UI จัดทีม, policy, dashboard scope, audit, seed และ tests

Decision gate:

เพิ่มก่อนปิด Phase 4 ถ้า UAT พบว่า:

- 1 project มีหลายคนต้องเห็น budget/progress/status ร่วมกันทุกวัน
- Member ต้องเปิด Project Detail เพื่อทำงาน
- PM ต้องแชร์ overview ให้ทีมโดยไม่ให้เห็น finance detail
- task-only ทำให้ navigation/403 สับสนซ้ำ

### 2.2 Auto-reopen deal หลัง invoice void

ยังไม่ auto เปลี่ยน stage ตอนนี้

เหตุผล:

- `won` เป็น sales history
- `void invoice` เป็น finance correction
- เคสปกติอาจเป็นออก invoice ผิดแล้ว void เพื่อออกใหม่ โดย deal ยัง won ถูกต้อง
- Auto `won -> negotiation` อาจทำให้ sales history เพี้ยน

ทางเลือก MVP:

- ใช้ derived flag `needs_sales_review`
- แสดงบน deal/invoice detail
- ใช้ audit/manual review
- ไม่ใช้ notifications module

Decision gate:

ถ้า UAT พบว่า void invoice ใต้ deal ส่วนใหญ่คือ “ลูกค้ายกเลิกดีลจริง” ค่อยออกแบบ workflow/metric ใหม่หลังมีข้อมูลจริง

---

## 3. ข้อที่ตรวจแล้วว่าทำไว้ใน docs แล้ว

| ประเด็น | สถานะ |
| --- | --- |
| `contacts.supplier_id` ไม่มี FK ใน MVP | ทำแล้ว |
| `task_checklists.org_id` | ทำแล้ว |
| `number_sequences` row lock / atomic increment | ทำแล้ว |
| invoice edit guard เมื่อ `paid_amount > 0` | ทำแล้ว |
| `tax_mode` | ทำแล้ว |
| `activities.completed_at` | ทำแล้ว |
| `storage_key` server-generated | ทำแล้ว |
| Cash In drilldown แยก receipt/reversal | ทำแล้ว |

---

## 4. คำสั่งผู้ใช้ที่ต้องรักษา

- ตอบและเอกสารหลักเป็นภาษาไทย
- `person_id` เก็บ plaintext `CHAR(13)` ไม่ encrypt
- `person_id` ต้อง mask ใน UI/log/export และจำกัดสิทธิ์เห็นเลขเต็ม
- Business/display code ใช้ text 6 หลัก เช่น `000001`
- Primary key ใช้ UUID ไม่ใช่รหัส 6 หลัก
- Database target คือ MariaDB/MySQL-compatible
- Phase 0 เป็นเอกสารเท่านั้น
---

## 5. รับข้อเสนอ Gemini รอบ Phase 1 Readiness

เห็นด้วยทั้งหมด และอัปเดต docs/checklist แล้ว:

- Lock primary key strategy เป็น time-ordered UUID ตั้งแต่ migration แรก: UUID v7 หรือ Laravel ordered UUID (`Str::orderedUuid()`)
- Phase 3 ต้องทดสอบ generated column `reversal_target_id` + `UNIQUE(org_id, reversal_target_id)` บน MariaDB เพื่อ enforce one reversal per receipt ระดับ database
- Phase 1 ต้องเตรียม shared TypeScript definitions สำหรับ Inertia props: `auth.user`, `auth.permissions`, `org`, `flash`
- Phase 1 ต้องมี tenant isolation test helper เช่น `actingAsOrgUser($user, $org)`
- Phase 1 ต้องตั้งค่า Laravel Pint, ESLint และ Prettier ตั้งแต่ต้น

เหตุผลที่ไม่โต้แย้ง:

ข้อเสนอทั้งหมดลดความเสี่ยงตอนเริ่ม coding และไม่เพิ่ม scope business ของ MVP จึงเหมาะกับ Phase 1 readiness.

---

## 6. Phase 1 Implementation Note: number_sequences

ทดสอบ MariaDB 11.2 แล้ว generated column ที่อ้าง `branch_id` แบบ UUID nullable ไม่ผ่าน migration (`1901 Function or expression ... cannot be used in GENERATED ALWAYS AS`).

Decision สำหรับ Phase 1:

- ใช้ `branch_key` และ `year_key` เป็นคอลัมน์ปกติ
- ให้ app/seed set ค่า sentinel เอง: branch null = `00000000-0000-0000-0000-000000000000`, year null = `0`
- ยัง enforce unique ด้วย `UNIQUE(org_id, branch_key, doc_type, year_key)` ได้
- ไม่กระทบ Phase 3 payment reversal generated column เพราะเป็นคนละ constraint และยังต้องทดสอบจริงตาม checklist

---

## 7. รับข้อเสนอ Gemini รอบ Phase 1 Code Audit

เห็นด้วยและแก้แล้ว:

- เพิ่ม `Security Alerts` ใน Admin Dashboard แล้ว
- เพิ่ม guard/test สำหรับ owner/last owner และ role immutable behavior แล้ว
- เพิ่ม Audit Log payload/UI ให้แสดง actor name/email และ `before_json` / `after_json` diff details แล้ว
- เพิ่ม Phase 1 demo seeder แล้ว
- เพิ่ม tests: session regenerate, login rate limit, reset/verify token expire/replay แล้ว
- เพิ่ม `throttle:10,1` บน management routes: invite, disable, structure update, role update
- ปรับ `HandleInertiaRequests` ให้ cache effective permissions ใน request attributes เพื่อลด query ซ้ำใน request เดียว

ไม่มีข้อโต้แย้งในรอบนี้ เพราะข้อเสนอทั้งหมดเป็น hardening/observability ของ Phase 1 และไม่เพิ่ม business scope ใหม่.

---

## 8. รับข้อเสนอ Gemini รอบ Phase 1 Updated Audit

เห็นด้วยและแก้เพิ่มแล้ว:

- ยืนยัน `AuditLogController` โหลด `actorUser:id,name,email` และส่ง `before_json` / `after_json` ให้ Inertia props แล้ว
- เพิ่ม `securityAlerts` prop ใน `DashboardController` และเพิ่ม Security Alerts widget ใน `Dashboard.tsx`
- แยก demo seed เป็น `database/seeders/Phase1DemoSeeder.php` และให้ `DatabaseSeeder` call เฉพาะ seeder นี้
- เพิ่ม test dashboard security alerts เพื่อกัน regression

ไม่มีข้อโต้แย้งในรอบนี้ เพราะเป็นการทำให้ implementation ตรงกับเอกสารและ reviewer expectation มากขึ้น.

---

## 9. Consensus เรื่อง UI Redesign & Enterprise Design System

เห็นด้วยและล็อกมาตรฐาน UI ตาม `docs/DESIGN_SYSTEM.md`:

- ล็อกโทนสีหลักเป็น Slate 900 / Slate 50 ร่วมกับ Indigo Accent (`#4F46E5`)
- กำหนด App Shell เป็น **Left Sidebar Navigation + Sticky Header Topbar**
- บังคับใช้ Reusable UI Components (`StatCard`, `Badge`, `PageHeader`, `Card`, `DataTable`) ในทุกๆ Phase เพื่อความสวยงามและเป็นมาตรฐานเดียวกัน


## 10. Phase 1.1 Planning Decision

เพิ่ม Phase 1.1 เป็นรอบเอกสารและเตรียม scope ก่อน coding เพื่อทำ Admin Master Data & Access Management: Branch, Division, Department, User edit/disable และ Role-Permission assignment ก่อนเริ่ม Phase 2 CRM/Sales.

เหตุผล:

- Phase 1 มี schema และ default organization structure แล้ว แต่ยังไม่ควรถือว่า master data CRUD ครบ
- Phase 2 จะอ้างอิง Branch/Division/Department ใน user assignment, customer/deal ownership และ dashboard/report filtering
- User edit/disable และ role-permission assignment ควรพร้อมก่อนเริ่มใช้งาน CRM/Sales เพราะเป็น access control พื้นฐาน
- การทำ Phase 1.1 ก่อนช่วยลดการแก้ข้อมูลตรง database และเพิ่ม audit trail ให้ master data ตั้งแต่ต้น
- ยังไม่เริ่ม coding ตามคำสั่งผู้ใช้; รอบนี้แก้เอกสารและ checklist เท่านั้น

Decision:

- ใช้ permission เดิม `settings.structure.view` และ `settings.structure.update` สำหรับ structure CRUD ใน Phase 1.1 MVP
- ใช้ `users.update`, `users.disable`, `roles.manage` สำหรับ user/access management
- Delete ใช้แนวทาง delete-safe: ลบได้เฉพาะไม่มี reference; ถ้ามี reference ให้ disable
- Business code ของ Branch/Division/Department ต้องเป็น text 6 หลัก
- ไม่เปิด CRUD permission code จาก UI เพราะ permission code เป็น system config ที่ middleware/policy อ้างตรง ต้องแก้ผ่าน migration/seeder เท่านั้น
- Phase 1.1 ต้องผ่านก่อนเริ่ม Phase 2

---


## 11. รับข้อเสนอ Gemini รอบ Phase 1.1 Scope Review

เห็นด้วยและอัปเดต docs/checklist แล้ว:

- Branch/Division/Department code ต้อง auto-generate 6 หลักจาก `number_sequences`; ไม่เปิดให้ผู้ใช้พิมพ์เอง
- การสลับ Head Office ต้องทำใน transaction เดียว พร้อม audit `branch.set_head_office`
- Disable/Delete structure ต้องมี strict reference guard ถ้ามี active child/user/reference ต้อง block พร้อม error message ชัดเจน
- Permission code ยัง immutable ผ่าน UI; แก้ได้เฉพาะ migration/seeder
- หน้า organization structure ต้องใช้ Tab Navigation ตาม `docs/DESIGN_SYSTEM.md`

ไม่มีข้อโต้แย้ง เพราะข้อเสนอทั้งหมดช่วยลดความผิดพลาดของ master data และไม่เพิ่ม business scope ของ Phase 2.

---

