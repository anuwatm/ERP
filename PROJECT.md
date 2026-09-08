# Project Definition: Company OS / Lightweight ERP

เอกสารนี้สรุปตัวตนโปรเจกต์, stack และลำดับ authority ของเอกสาร

> **สถานะ ณ ปัจจุบัน:** Phase 0-18.1 ถูก implement แล้ว. ข้อความที่ระบุ MVP/V1/V2 ด้านล่างเป็น baseline ประวัติการตัดสินใจ ไม่ใช่ข้อจำกัดของ runtime ปัจจุบัน. สถานะงานให้ยึด [`checklist.md`](./checklist.md), schema ให้ยึด migrations และ routes ให้ยึด `backend/routes/web.php`.

---

## 1. Product identity

| Item | Value |
| --- | --- |
| ชื่อภายใน | Company OS / Lightweight ERP |
| โค้ดเนมโฟลเดอร์ | `ERP` |
| กลุ่มเป้าหมาย | SME, ทีมบริการ, ทีมซอฟต์แวร์, เอเจนซี, สตูดิโอ |
| ภาษา UI หลัก | ไทย (`APP_LOCALE=th`) |
| ตลาดเงิน | ประเทศไทย; รองรับ currency/rate master และ FX ตั้งแต่ Phase 14 |
| Tenant model | 1 user สังกัด 1 organization และอยู่ในโครงสร้าง `branch -> division -> department` ได้ |
| Application timezone | `UTC` ใน Laravel runtime; วันทำการ/เอกสารกำหนดตามข้อมูลธุรกิจและ policy |

### แกนธุรกิจ

```text
CRM → Quotation/Invoice → Payment → Treasury/GL → Project/Task → Inventory/Payroll → Dashboard
```

### ปัญหาที่แก้

เจ้าของบริษัท/ทีมเล็กต้องการระบบเดียวที่เห็น **ลูกค้า → โอกาสขาย → งานส่งมอบ → เงินเข้า-ออก → ภาพรวม** โดยไม่ต้องเริ่มจากบัญชีเต็มรูปแบบ

### สิ่งที่ไม่ใช่เป้าหมายตอนนี้

- Manufacturing/BOM, POS, HR attendance/leave และ portal ภายนอก (Phase 19+)
- การยื่น e-Tax จริงกับกรมสรรพากรโดยตรง; ต้องผ่าน certified provider/onboarding (Phase 24)

---

## 2. Document authority (สำคัญ)

เมื่อเอกสารขัดกัน ใช้ลำดับนี้ (สูง → ต่ำ):

1. `backend/database/migrations/*.php` — schema runtime
2. `backend/routes/web.php` และ controller/policy/service — behavior runtime
3. [`checklist.md`](./checklist.md) — phase status และ work ที่เหลือ
4. [`document/DATABASE_ERD.md`](./document/DATABASE_ERD.md), [`document/GROUPS_WORKFLOW.md`](./document/GROUPS_WORKFLOW.md) — diagrams ที่ sync แล้ว
5. [`docs/SECURITY_REQUIREMENTS.md`](./docs/SECURITY_REQUIREMENTS.md), [`docs/VALIDATION_RULES.md`](./docs/VALIDATION_RULES.md) — engineering constraints
6. เอกสาร MVP/phase/module/feature plan ที่เหลือ — planning history หรือ detailed rationale

**กฎ:** `P0` ใน feature plan **ไม่เท่ากับ** “ต้องมีใน MVP” — ดู AD-01 และ `MVP_SCOPE.md`

---

## 3. Tech stack (ล็อก)

| Layer | Choice | หมายเหตุ |
| --- | --- | --- |
| Backend | **PHP 8.3 + Laravel 13** | application root: `backend/` |
| Frontend | **React 18 + TypeScript + Inertia + Vite** | ไม่แยก SPA+REST เต็มรูปแบบใน MVP |
| Database | **MariaDB 11.x / MySQL-compatible** (`utf8mb4`) | local dev: MariaDB `127.0.0.1:3306`; Laravel ใช้ `DB_CONNECTION=mysql`; money = `DECIMAL(18,2)` เท่านั้น |
| Auth (MVP) | **Laravel Breeze local** | `auth_provider = local` |
| Auth privileged | Offline TOTP 2FA + recovery codes + policy per organization | Phase 18; OIDC/SSO ยังเป็นอนาคต |
| Session | Encrypted server-side DB session + secure cookie | CSRF เปิดใช้ |
| App code language | PHP + TypeScript | ห้ามผสม backend หลายภาษาใน MVP |
| Primary package manager | Composer + npm | |

### Naming conventions

| หัวข้อ | มาตรฐาน |
| --- | --- |
| DB column องค์กร | `org_id` (ไม่ใช้ `organization_id` ใน schema) |
| Org hierarchy | `organizations -> branches -> divisions -> departments -> users` |
| Laravel model relation | map `org_id` → `organization()` ได้ แต่ column ชื่อ `org_id` |
| Money | `DECIMAL(18,2)`; ห้าม `FLOAT`/`DOUBLE` |
| Primary key | UUID v7 หรือ Laravel ordered UUID (`Str::orderedUuid()`) เป็นมาตรฐาน MVP; ใช้ตั้งแต่ migration แรก |
| Business/display code | รหัสที่ user เห็น เช่น `branch.code`, `customer_code`, `project_code`, `invoice_no` ใช้ `CHAR(6)` เป็น text 6 หลัก เช่น `000001`; ไม่ใช่ primary key |
| Soft delete | ตารางธุรกิจสำคัญมี `deleted_at` |
| Permission code | `module.action` เช่น `invoices.create` |
| Role codes (MVP) | `owner`, `admin`, `sales`, `project_manager`, `finance`, `member`, `viewer` |
| Time storage | timestamptz-equivalent / app timezone `Asia/Bangkok` |
| Currency (MVP) | `THB` fixed; column `currency` เก็บได้เพื่อขยายทีหลัง |

---

## 4. MVP scope (สรุปล็อก)

รายละเอียดเต็ม: [`MVP_SCOPE.md`](./MVP_SCOPE.md)

### In

- Foundation: org hierarchy (`organization -> branch -> division -> department -> user`), auth local, RBAC 7 roles, settings, audit
- CRM/delivery: customers, contacts, deals (+ activity/follow-up), projects, tasks
- Finance: products/services, invoices, payments (receipt/reversal, no overpay), expenses
- Dashboard ตาม Phase: Admin, Sales, Finance, Delivery, Executive summary (แยก Invoiced Revenue vs Cash In; ห้ามโชว์ Cash Balance ปลอม)

### Out

- quotations, milestones, suppliers, PO, inventory
- HR / attendance / leave / payroll
- PDF/CSV export, LINE/email notify, automation, public API
- accounting sync, AI, customer portal, multi-branch operation/report เต็ม
- bank recon, opening balance, tax invoice, credit note

### End-to-end DoD

```text
Invite user → Customer → Deal → Invoice → Payment → Project → Task → Dashboard
```

Owner เห็นแค่ org ตัวเอง · Sales ทำ CRM · Finance ทำ invoice/payment ก่อนมี project ได้ · PM ทำ project/task · ยอดถูกต้อง · มี audit

---

## 5. Version roadmap

| Version | ชื่อ | เป้าหมาย |
| --- | --- | --- |
| **MVP** | First shippable | Flow เงิน+งานใน org เดียว พร้อมโครงสร้างบริษัทพื้นฐาน |
| **V1** | Core Company OS | เติม P0/P1 ที่เหลือหลัง MVP (reports เต็ม, quotations, files, notifications ฯลฯ) |
| **V2** | Operations | Inventory, PO, HR เบา, notify, accounting integration, AI |
| **V3** | Advanced | Multi-branch operation/report เต็ม, payroll เต็ม, portal, plugin, BI |

---

## 6. Architecture rules (สรุป)

รายละเอียดเต็ม: [`docs/ARCHITECTURE_DECISIONS.md`](./docs/ARCHITECTURE_DECISIONS.md)

| ID | Rule |
| --- | --- |
| AD-01 | MVP ≠ ทุก P0 |
| AD-02 | Money metrics แยก Invoiced vs Cash; ไม่มี Cash Balance จริงใน MVP |
| AD-03 | Auth MVP = local Breeze; เตรียมคอลัมน์ provider สำหรับ OIDC ภายหลัง |
| AD-04 | Payment immutable + reversal; lock invoice; no overpay |
| AD-05 | Project cost = sum(expenses approved/paid); **ไม่มี** `projects.actual_cost` |
| AD-06 | Polymorphic เฉพาะ activities/files/notifications/audit_logs + allowlist |
| AD-07 | Org hierarchy = company -> branch -> division -> department -> user |
| AD-08 | MVP file attachment จำกัดเฉพาะ payment/expense; generic files เป็น Post-MVP |
| AD-09 | Tenant scope ใช้ policy + shared org scope กับทุก business model |

### Server trust boundary

- ห้ามเชื่อ `org_id`, role, price, total, status จาก client
- ทุก business query filter `org_id` จาก session
- คำนวณเงินฝั่ง server เท่านั้น
- Financial write ใช้ DB transaction + (แนะนำ) idempotency key

---

## 7. Build phases

| Phase | เนื้อหา | Status |
| --- | --- | --- |
| 0-8 | Foundation, CRM, Finance, Delivery, reporting, procurement, inventory/notification baseline | Done |
| 9-16B | Commercial documents, Treasury, GL, e-Tax application layer, Assets, FX, Inventory operations, Payroll | Done |
| 17-18.1 | DMS core and compliance | Done |
| 18 | 2FA security | Done; polish track |
| 19+ | HR, approvals, notifications, portal, payment and e-Tax gateway | Planned |

รายละเอียด Phase 1: [`docs/PHASE_1_LOGIN_IMPLEMENTATION.md`](./docs/PHASE_1_LOGIN_IMPLEMENTATION.md)

---

## 8. Repo layout (เป้า)

```text
ERP/
  PROJECT.md                 ← ไฟล์นี้
  MVP_SCOPE.md
  ERP_FEATURE_PLAN.md
  docs/
    README.md
    ARCHITECTURE_DECISIONS.md
    SECURITY_REQUIREMENTS.md
    PHASE_1_LOGIN_IMPLEMENTATION.md
    database/DATABASE.md
    modules/...
  backend/                   ← Laravel 13 application
```

---

## 9. Non-goals for coding style

- อย่าสร้าง microservice หลายภาษาใน MVP
- อย่าทำ mobile native
- อย่าทำ plugin system / workflow builder
- อย่า cache ยอดเงินสำคัญโดยไม่มี reconciliation plan
- อย่าใส่ feature นอก `MVP_SCOPE.md` ก่อน DoD ของ flow หลักผ่าน

---

## 10. Open decisions (ยังไม่ล็อก — ไม่บล็อก MVP)

| หัวข้อ | ทางเลือก | หมายเหตุ |
| --- | --- | --- |
| Hosting | VPS / Forge / cloud | ทีมเลือกตอน deploy |
| Object storage | local disk (dev) → S3-compatible (prod) | ไฟล์แนบ payment |
| Email provider | SMTP / SES / Resend | invite + reset |
| UUID implementation detail | Laravel ordered UUID หรือ UUID v7 package | ต้องคงรูปแบบ time-ordered UUID ตามมาตรฐาน Primary key |
| Report module timing | หลัง MVP ทันที vs คู่ Phase 5 | ตาม AD-01 อยู่นอก MVP; Dashboard ตาม Phase อยู่ใน MVP |
| External IdP | Auth0 / Keycloak / Google Workspace | หลัง MVP |












