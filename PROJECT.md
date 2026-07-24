# Project Definition: Company OS / Lightweight ERP

เอกสารนี้ล็อก **ตัวตนโปรเจกต์ + ขอบเขต + stack + ลำดับความสำคัญของเอกสาร**  
ถ้าข้อความในไฟล์อื่นขัดกับเอกสารนี้ ให้ใช้ลำดับ authority ด้านล่าง

---

## 1. Product identity

| Item | Value |
| --- | --- |
| ชื่อภายใน | Company OS / Lightweight ERP |
| โค้ดเนมโฟลเดอร์ | `ERP` |
| กลุ่มเป้าหมาย | SME, ทีมบริการ, ทีมซอฟต์แวร์, เอเจนซี, สตูดิโอ |
| ภาษา UI หลัก (MVP) | ไทย (รองรับ English label ภายหลังได้) |
| ตลาดเงิน (MVP) | ประเทศไทย, สกุลเงิน `THB` เท่านั้น |
| Tenant model (MVP) | 1 user สังกัด 1 organization และอยู่ในโครงสร้าง `branch -> division -> department` ได้ |
| Timezone default | `Asia/Bangkok` |

### แกนธุรกิจ

```text
CRM → Deal → Invoice → Payment → Project → Task → Dashboard
```

### ปัญหาที่แก้

เจ้าของบริษัท/ทีมเล็กต้องการระบบเดียวที่เห็น **ลูกค้า → โอกาสขาย → งานส่งมอบ → เงินเข้า-ออก → ภาพรวม** โดยไม่ต้องเริ่มจากบัญชีเต็มรูปแบบ

### สิ่งที่ไม่ใช่เป้าหมายตอนนี้

- ERP ระดับ enterprise ครบ manufacturing / POS / payroll เต็ม
- ใบกำกับภาษีตามกฎหมายไทย (tax invoice compliance)
- บัญชีแยกประเภท (full ledger) / bank reconciliation
- Multi-branch เชิง operation/report เต็มรูปแบบ, multi-currency accounting

---

## 2. Document authority (สำคัญ)

เมื่อเอกสารขัดกัน ใช้ลำดับนี้ (สูง → ต่ำ):

1. [`MVP_SCOPE.md`](./MVP_SCOPE.md) — ขอบเขต build รอบแรก
2. [`docs/ARCHITECTURE_DECISIONS.md`](./docs/ARCHITECTURE_DECISIONS.md) — กฎ override ทางเทคนิค
3. [`docs/SECURITY_REQUIREMENTS.md`](./docs/SECURITY_REQUIREMENTS.md) — ข้อกำหนด security บังคับ
4. [`docs/PHASE_1_LOGIN_IMPLEMENTATION.md`](./docs/PHASE_1_LOGIN_IMPLEMENTATION.md) — รายละเอียด implement foundation
5. [`docs/VALIDATION_RULES.md`](./docs/VALIDATION_RULES.md) — validation/server-side guard
6. [`docs/PHASE_ACCEPTANCE_CRITERIA.md`](./docs/PHASE_ACCEPTANCE_CRITERIA.md) — DoD ราย phase
7. [`docs/ROUTES_AND_SCREENS.md`](./docs/ROUTES_AND_SCREENS.md) — route/screen scope ราย phase
8. [`docs/SEED_DATA.md`](./docs/SEED_DATA.md) — default/UAT seed data
9. [`docs/database/DATABASE.md`](./docs/database/DATABASE.md) — schema กลาง (ต้อง sync กับ AD ก่อน migrate)
10. [`docs/modules/*.md`](./docs/modules/) — รายละเอียด per module
11. [`ERP_FEATURE_PLAN.md`](./ERP_FEATURE_PLAN.md) — แผนยาว / backlog / แรงบันดาลใจ feature

**กฎ:** `P0` ใน feature plan **ไม่เท่ากับ** “ต้องมีใน MVP” — ดู AD-01 และ `MVP_SCOPE.md`

---

## 3. Tech stack (ล็อก)

| Layer | Choice | หมายเหตุ |
| --- | --- | --- |
| Backend | **PHP 8.3 + Laravel 13** | application root: `backend/` |
| Frontend | **React 18 + TypeScript + Inertia + Vite** | ไม่แยก SPA+REST เต็มรูปแบบใน MVP |
| Database | **MariaDB 11.x / MySQL-compatible** (`utf8mb4`) | local dev: MariaDB `127.0.0.1:3306`; Laravel ใช้ `DB_CONNECTION=mysql`; money = `DECIMAL(18,2)` เท่านั้น |
| Auth (MVP) | **Laravel Breeze local** | `auth_provider = local` |
| Auth (เป้าผลิต privileged) | OIDC/SSO + MFA | เพิ่มหลัง MVP ตาม AD/Security |
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
| Primary key | UUID แนะนำ (Laravel: UUID string / ordered UUID) |
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
| 0 | เอกสาร + schema lock (repo นี้) | In progress — no coding |
| 1 | Foundation: Laravel/Breeze, org hierarchy, RBAC, invite, audit, Admin dashboard | Not started (docs only) |
| 2 | CRM: customers, contacts, deals, activities, Sales dashboard | Not started |
| 3 | Finance: products/services, invoices, payments, expenses, Finance dashboard | Not started |
| 4 | Delivery: projects, tasks, Delivery dashboard | Not started |
| 5 | Executive dashboard summary + E2E tests + UAT | Not started |

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
  backend/                   ← Laravel app (ยังไม่สร้างใน repo)
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
| UUID strategy | UUID v7 / ordered UUID | เลือกตอน migration แรก |
| Report module timing | หลัง MVP ทันที vs คู่ Phase 5 | ตาม AD-01 อยู่นอก MVP; Dashboard ตาม Phase อยู่ใน MVP |
| External IdP | Auth0 / Keycloak / Google Workspace | หลัง MVP |










