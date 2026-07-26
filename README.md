# Company OS / Lightweight ERP

ระบบ ERP เบาสำหรับ SME, ทีมบริการ, ทีมซอฟต์แวร์, เอเจนซี และสตูดิโอ

เป้าหมาย MVP:

```text
Invite user -> Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard
```

สถานะตอนนี้: **Phase 1.1 Done / พร้อมเตรียม Phase 2 CRM/Sales**

---

## Tech Stack

| Layer | ใช้ |
| --- | --- |
| Backend | PHP 8.3 + Laravel 13 |
| Frontend | React 18 + TypeScript + Inertia + Vite |
| Database | MariaDB / MySQL-compatible |
| Auth | Laravel Breeze local |
| Currency MVP | THB |

---

## Project Tracking

- งานทั้งหมดติดตามใน [`checklist.md`](./checklist.md)
- เมื่องานเสร็จ ให้ติ๊ก checklist ทันที
- เมื่อจบแต่ละ Phase ต้องอัปเดต README นี้ด้วย Feature ที่สร้างแล้ว
- `checklist.md` เป็น source of truth สำหรับสถานะงาน

---

## Quick Start Phase 1

Application source อยู่ที่ [`backend/`](./backend/)

Local URL:

- Web: `http://localhost/ERP/`
- Login: `http://localhost/ERP/login`
- Database: MariaDB `127.0.0.1:3306`

### Demo Test Accounts

คำสั่งสร้างข้อมูลทดสอบ (Seeder):
```powershell
cd backend
php artisan db:seed
```

| Role | Email | Password | Status | Notes |
| --- | --- | --- | --- | --- |
| **Owner** | `owner@example.com` | `password` | Active | สิทธิ์สูงสุด เข้าถึงได้ทุกเมนู (แนะนำสำหรับทดสอบ) |
| **Admin** | `admin@example.com` | `password` | Active | สิทธิ์แอดมิน จัดการผู้ใช้และระบบ |
| **Invited Member** | `member@example.com` | - | Invited | บัญชีทดสอบรับคำเชิญ (Accept Invite flow) |
| **Inactive Viewer** | `viewer@example.com` | `password` | Inactive | บัญชีทดสอบสถานะถูกปิดใช้งาน *(Login ไม่ได้)* |


ตรวจระบบหลัก:

```powershell
cd backend
php -c .php\php.ini vendor\phpunit\phpunit\phpunit
pnpm run lint
pnpm run check-format
pnpm run build
php -c .php\php.ini artisan migrate:fresh --force
```
หมายเหตุ CLI PHP:

- ถ้าต้องการใช้ `php artisan test` โดยตรง ให้เปิด extension `mbstring` ใน `php.ini`
- ถ้าต้องการให้ company logo แสดงผ่าน web server ให้รัน `php -c .php\php.ini artisan storage:link` หลัง setup storage
- โปรเจกต์นี้มี `.php\php.ini` สำหรับ local verification ด้วยคำสั่ง `php -c .php\php.ini vendor\phpunit\phpunit\phpunit`

Phase 1 ที่ทำแล้ว:

- Laravel 13 + Breeze local auth
- React + TypeScript + Inertia + Vite
- MariaDB connection
- Organization hierarchy: company -> branch -> division -> department -> user
- RBAC 7 roles + permission middleware
- Invite/accept user พร้อม token one-time TTL 72 ชั่วโมง
- Admin screens: organization profile with company logo upload, organization structure, users, roles, audit logs
- Admin Dashboard สรุป org/users/roles/recent audit
- Audit log: register, login, invite, accept invite, organization update including company logo

---

## Phase Summary

| Phase | Status | Feature Summary |
| --- | --- | --- |
| Phase 0 | Done | เอกสาร scope, schema, security, validation, phase criteria, routes/screens, review decisions |
| Phase 1 | Done | Foundation, auth, org hierarchy, RBAC, invite, audit, Admin Dashboard |
| Phase 1.1 | Done | Admin Master Data & Access Management: Company Identity/Logo, Branch, Division, Department, User, Role-Permission |
| Phase 2 | Not started | CRM/Sales: customers, contacts, deals, activities, Sales Dashboard |
| Phase 3 | Not started | Finance: products, invoices, payments/reversal, expenses, Finance Dashboard |
| Phase 4 | Not started | Delivery: projects, tasks, project cost, Delivery Dashboard |
| Phase 5 | Not started | Executive Dashboard, E2E tests, UAT |

---

## Implemented Features

| Phase | Implemented Features | Updated |
| --- | --- | --- |
| Phase 0 | Documentation lock, MVP scope, schema/security/validation decisions, checklist, README | 2026-07-25 |
| Phase 1 | Laravel/Breeze auth, MariaDB schema, org hierarchy, RBAC, permission middleware, invite/accept user, admin screens, audit log, Admin Dashboard, 51 PHPUnit tests / 235 assertions | 2026-07-25 |
| Phase 1.1 | Master Data CRUD, auto-generated structure codes, head office switch, user edit/disable/enable, role-permission matrix, strict guards, audit logs, 63 PHPUnit tests / 273 assertions | 2026-07-26 |
| Phase 2 | Not implemented yet | - |
| Phase 3 | Not implemented yet | - |
| Phase 4 | Not implemented yet | - |
| Phase 5 | Not implemented yet | - |

---

## Phase 1.1 Features

Phase 1.1 เพิ่ม Admin Master Data & Access Management ต่อจาก Phase 1:

- Company Identity & Information edit + company logo upload
- Branch / Division / Department create/edit/disable/delete-safe
- Auto-generate code 6 หลักจาก `number_sequences`
- Set Head Office แบบ transaction พร้อม audit `branch.set_head_office`
- Strict delete/disable guard เมื่อมี active child/user/reference
- User edit profile, hierarchy, role, disable และ re-enable
- Role-Permission matrix สำหรับแก้ permission assignment
- Permission code ยัง immutable ผ่าน UI; เพิ่ม/แก้ผ่าน migration/seeder เท่านั้น
- Audit log ครบ master data, user และ role permission changes

Verification:

- PHPUnit: 63 tests / 273 assertions
- `pnpm run lint`: passed
- `pnpm run check-format`: passed
- `pnpm run build`: passed

---
## Phase 0 Features / Decisions

Phase 0 เป็นงานเอกสารและ decision lock เท่านั้น ยังไม่มี source code application

สิ่งที่ล็อกแล้ว:

- Project identity และ tech stack
- MVP scope
- MariaDB/MySQL-compatible schema direction
- Organization hierarchy: company -> branch -> division -> department -> user
- RBAC 7 roles: owner, admin, sales, project_manager, finance, member, viewer
- `person_id` เก็บ plaintext `CHAR(13)` แต่ต้อง mask ใน UI/log/export
- Business/display code ใช้ text 6 หลัก เช่น `000001`
- Payment ที่ post แล้วแก้/ลบไม่ได้ ใช้ reversal เท่านั้น
- Dashboard แยก Invoiced Revenue กับ Cash In
- ห้ามแสดง Cash Balance ใน MVP
- MVP ยังไม่มี `project_members`; ใช้ owner/assignee-only ก่อน
- Invoice void ใต้ deal ใช้ derived flag `needs_sales_review`; ไม่ auto-reopen deal
- Files MVP จำกัด payment/expense attachment
- Full tax invoice compliance, suppliers, PO, inventory, export, notifications เป็น Post-MVP/V2

เอกสารหลัก:

- [`docs/README.md`](./docs/README.md)
- [`PROJECT.md`](./PROJECT.md)
- [`MVP_SCOPE.md`](./MVP_SCOPE.md)
- [`docs/ARCHITECTURE_DECISIONS.md`](./docs/ARCHITECTURE_DECISIONS.md)
- [`docs/SECURITY_REQUIREMENTS.md`](./docs/SECURITY_REQUIREMENTS.md)
- [`docs/VALIDATION_RULES.md`](./docs/VALIDATION_RULES.md)
- [`docs/ROUTES_AND_SCREENS.md`](./docs/ROUTES_AND_SCREENS.md)
- [`docs/SEED_DATA.md`](./docs/SEED_DATA.md)
- [`docs/database/DATABASE.md`](./docs/database/DATABASE.md)
- [`docs/modules/README.md`](./docs/modules/README.md)
- [`docs/PHASE_ACCEPTANCE_CRITERIA.md`](./docs/PHASE_ACCEPTANCE_CRITERIA.md)
- [`docs/PHASE_1_LOGIN_IMPLEMENTATION.md`](./docs/PHASE_1_LOGIN_IMPLEMENTATION.md)
- [`docs/PHASE_1_1_MASTER_DATA.md`](./docs/PHASE_1_1_MASTER_DATA.md)

Decision notes:

- [`gemini.md`](./gemini.md)
- [`grok.md`](./grok.md)
- [`gpt.md`](./gpt.md)

---

## Out of Scope MVP

- `project_members` implementation จนกว่า Phase 4 UAT จะยืนยัน
- Auto-reopen deal หลัง invoice void
- Full files module
- Tax invoice compliance / credit note
- Cash Balance / bank reconciliation
- Suppliers, PO, inventory
- Export, notifications, public API

