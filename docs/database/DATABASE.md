# Central Database Schema

เอกสารนี้เป็น **แหล่งความจริงเดียว (Single Source of Truth)** ของโครงสร้างฐานข้อมูลทั้งระบบ

- ทุก module ต้องอ้างอิงตาราง/คอลัมน์จากไฟล์นี้เท่านั้น
- ห้ามนิยามตารางซ้ำใน module doc โดยขัดกับไฟล์นี้
- เมื่อมีการเปลี่ยน schema ให้แก้ไฟล์นี้ก่อน แล้วอัปเดต module doc ที่เกี่ยวข้อง

อ้างอิงแผน: [`ERP_FEATURE_PLAN.md`](../../ERP_FEATURE_PLAN.md)

> ไฟล์นี้เป็น schema กลางระยะยาวของระบบทั้งหมด. **MVP migration ใช้เฉพาะ subset ใน `MVP_SCOPE.md`**: organizations, branches, divisions, departments, users/RBAC, settings/number_sequences, audit_logs, customers, contacts, deals, activities, projects, tasks, products, invoices, invoice_items, payments, expenses, และ files แบบจำกัดสำหรับ payment/expense attachment. ตารางอื่นเป็น Post-MVP/V2/V3 backlog.

---

## 1. หลักการร่วม (Conventions)

### 1.1 Multi-tenant

- ตารางธุรกิจส่วนใหญ่มี `org_id` (FK → `organizations.id`)
- การ query ต้อง filter ตาม `org_id` เสมอ (row-level isolation)

### 1.2 คอลัมน์มาตรฐาน (ทุกตารางหลัก)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | UUID PK | ใช้ time-ordered UUID: UUID v7 หรือ Laravel ordered UUID (`Str::orderedUuid()`) |
| `org_id` | UUID FK | ยกเว้นตารางระบบระดับ platform |
| `created_at` | TIMESTAMP | default now() |
| `updated_at` | TIMESTAMP | auto-update |
| `created_by` | UUID FK → users | nullable สำหรับ system |
| `updated_by` | UUID FK → users | nullable |

### 1.3 Soft delete (แนะนำ)

ตารางธุรกิจสำคัญควรมี:

| Column | Type | Notes |
| --- | --- | --- |
| `deleted_at` | TIMESTAMP NULL | null = active |

### 1.4 สกุลเงิน / จำนวนเงิน

| Column pattern | Type | Notes |
| --- | --- | --- |
| `*_amount` | DECIMAL(18,2) | money fields |
| `currency` | CHAR(3) | เช่น THB, USD (default จาก settings) |

### 1.5 Business/display code

รหัสที่ user เห็นและใช้ค้นหา เช่น `branch.code`, `customer_code`, `project_code`, `invoice_no` ให้เก็บเป็น text 6 หลัก:

| Pattern | Type | Notes |
| --- | --- | --- |
| `*_code`, `*_no`, `code` ของ org hierarchy | CHAR(6) | numeric string, leading zero ได้ เช่น `000001`; unique ภายใน scope |

> ไม่ใช่ primary key. Primary key `id` ใช้ time-ordered UUID: UUID v7 หรือ Laravel ordered UUID (`Str::orderedUuid()`).

### 1.6 สถานะ (Enums)

ใช้ string enum / check constraint ให้ชื่อตรงกันทั้งระบบ (ดูในแต่ละตาราง)

---

## 2. Entity Relationship Overview

```text
organizations
  ├── branches ── divisions ── departments
  ├── users ── user_roles ── roles ── role_permissions ── permissions
  ├── customers ── contacts
  │     └── deals ── invoices ── invoice_items ── payments
  │           └── projects (Phase 4) ── tasks / milestones
  │                 ▲
  │                 └── invoices.project_id links later
  ├── suppliers ── purchase_orders ── purchase_order_items
  ├── products ── inventory / stock_movements
  ├── expenses
  ├── employees ── attendances / leave_requests / payslips
  ├── files / notifications / audit_logs
  ├── automation_rules / webhook_events
  └── settings / api_tokens
```

---

## 3. Foundation Tables

### 3.1 `organizations`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| name | VARCHAR(255) | NOT NULL | ชื่อบริษัท |
| legal_name | VARCHAR(255) | | ชื่อจดทะเบียน |
| tax_id | VARCHAR(50) | | เลขผู้เสียภาษี |
| email | VARCHAR(255) | | |
| phone | VARCHAR(50) | | |
| address | TEXT | | |
| logo_url | VARCHAR(500) | | |
| currency | CHAR(3) | NOT NULL DEFAULT 'THB' | |
| timezone | VARCHAR(64) | NOT NULL DEFAULT 'Asia/Bangkok' | |
| status | VARCHAR(20) | NOT NULL DEFAULT 'active' | active, suspended |
| created_at | TIMESTAMP | NOT NULL | |
| updated_at | TIMESTAMP | NOT NULL | |

> ตารางนี้ไม่มี `org_id` (เป็น root tenant)

### 3.2 `branches`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| code | CHAR(6) | UNIQUE(org_id, code) | text 6 หลัก เช่น `000001` |
| name | VARCHAR(255) | NOT NULL | |
| address | TEXT | | |
| phone | VARCHAR(50) | | |
| is_head_office | BOOLEAN | DEFAULT false | |
| status | VARCHAR(20) | DEFAULT 'active' | |
| created_at / updated_at / created_by / updated_by | | | |

### 3.3 `divisions`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| branch_id | UUID | FK branches NOT NULL | |
| code | CHAR(6) | UNIQUE(org_id, branch_id, code) | text 6 หลัก เช่น `000001` |
| name | VARCHAR(255) | NOT NULL | ฝ่าย |
| status | VARCHAR(20) | DEFAULT 'active' | |
| created_at / updated_at / created_by / updated_by | | | |

### 3.4 `departments`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| branch_id | UUID | FK branches NOT NULL | |
| division_id | UUID | FK divisions NOT NULL | |
| code | CHAR(6) | UNIQUE(org_id, division_id, code) | text 6 หลัก เช่น `000001` |
| name | VARCHAR(255) | NOT NULL | แผนก |
| status | VARCHAR(20) | DEFAULT 'active' | |
| created_at / updated_at / created_by / updated_by | | | |

### 3.5 `users`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| branch_id | UUID | FK branches NULL | |
| division_id | UUID | FK divisions NULL | |
| department_id | UUID | FK departments NULL | |
| email | VARCHAR(255) | UNIQUE(org_id, email) | |
| password | VARCHAR(255) | NULL | **hashed only** (Laravel Breeze); required when `auth_provider=local` และ active |
| remember_token | VARCHAR(100) | NULL | Breeze remember me; ห้าม expose ใน props/logs |
| auth_provider | VARCHAR(30) | NOT NULL DEFAULT 'local' | `local`, ภายหลัง `oidc` ฯลฯ |
| auth_provider_user_id | VARCHAR(255) | NULL UNIQUE | ใช้ map external IdP; local ว่างได้ |
| display_name | VARCHAR(255) | NOT NULL | |
| person_id | CHAR(13) | NULL | เลขบัตรประชาชน 13 หลัก; เก็บตรง; แสดงผลแบบ masked |
| position | VARCHAR(100) | | ตำแหน่งงาน / job title (ไม่ใช่ role สิทธิ์) |
| phone | VARCHAR(50) | | |
| avatar_url | VARCHAR(500) | | |
| status | VARCHAR(20) | DEFAULT 'active' | active, inactive, invited |
| last_login_at | TIMESTAMP | | |
| invited_at | TIMESTAMP | | |
| email_verified_at | TIMESTAMP | | |
| created_at / updated_at / created_by / updated_by | | | |
| deleted_at | TIMESTAMP | | |

> **AD-03:** MVP = local Breeze. ห้ามเก็บ plaintext password. ดู override ใน `docs/ARCHITECTURE_DECISIONS.md`

**Users sensitive fields:** person_id ต้องรับ input เป็นตัวเลข 13 หลัก, validate checksum ได้, เก็บตรงใน CHAR(13), แสดงผลแบบ masked เช่น 1-2345-xxxxx-xx-x, จำกัด permission การดูเลขเต็ม, และห้าม log/export เลขเต็มโดยไม่จำเป็น.

### 3.6 `roles`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| code | VARCHAR(50) | UNIQUE(org_id, code) | owner, admin, sales, project_manager, finance, member, viewer |
| name | VARCHAR(100) | NOT NULL | |
| description | TEXT | | |
| is_system | BOOLEAN | DEFAULT false | role เริ่มต้นห้ามลบ |
| created_at / updated_at | | | |

### 3.7 `permissions`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| code | VARCHAR(100) | UNIQUE | เช่น `customers.view` |
| module | VARCHAR(50) | NOT NULL | customers, deals, invoices, ... |
| action | VARCHAR(50) | NOT NULL | view, create, update, delete, approve, export |
| description | TEXT | | |

> ตารางนี้เป็น global catalog (ไม่มี org_id)

### 3.8 `role_permissions`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| role_id | UUID | FK roles PK | |
| permission_id | UUID | FK permissions PK | |

### 3.9 `user_roles`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| user_id | UUID | FK users PK | |
| role_id | UUID | FK roles PK | |
| assigned_at | TIMESTAMP | | |
| assigned_by | UUID | FK users | |

---

## 4. CRM & Sales Tables

### 4.1 `customers`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| customer_code | CHAR(6) | UNIQUE(org_id, customer_code) | text 6 หลัก เช่น `000001` |
| company_name | VARCHAR(255) | NOT NULL | |
| tax_id | VARCHAR(50) | | |
| customer_type | VARCHAR(30) | | lead, prospect, active, inactive |
| status | VARCHAR(20) | DEFAULT 'active' | |
| owner_id | UUID | FK users | ผู้ดูแล |
| phone | VARCHAR(50) | | |
| email | VARCHAR(255) | | |
| line_id | VARCHAR(100) | | |
| website | VARCHAR(255) | | |
| address | TEXT | | |
| source | VARCHAR(100) | | แหล่งที่มา |
| tags | JSON | | หรือแยกตาราง tags |
| note | TEXT | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

**Indexes:** `(org_id, owner_id)`, `(org_id, status)`, `(org_id, company_name)`

### 4.2 `contacts`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| customer_id | UUID | FK customers NULL (MVP: NOT NULL) | MVP contact ผูก customer ก่อน |
| supplier_id | UUID | NULL; MVP ไม่ใส่ FK | Post-MVP ค่อยเพิ่ม FK suppliers |
| name | VARCHAR(255) | NOT NULL | |
| position | VARCHAR(100) | | |
| phone | VARCHAR(50) | | |
| email | VARCHAR(255) | | |
| line_id | VARCHAR(100) | | |
| is_primary | BOOLEAN | DEFAULT false | |
| note | TEXT | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

**MVP Check:** `customer_id IS NOT NULL`. Post-MVP เมื่อเปิด suppliers ค่อยใช้ `customer_id IS NOT NULL OR supplier_id IS NOT NULL`.

### 4.3 `deals`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| title | VARCHAR(255) | NOT NULL | |
| customer_id | UUID | FK customers NOT NULL | |
| contact_id | UUID | FK contacts NULL | |
| stage | VARCHAR(30) | NOT NULL | new, contacted, qualified, proposal, negotiation, won, lost |
| value_amount | DECIMAL(18,2) | DEFAULT 0 | |
| currency | CHAR(3) | DEFAULT 'THB' | |
| probability | INT | 0–100 | |
| expected_close_date | DATE | | |
| owner_id | UUID | FK users | |
| source | VARCHAR(100) | | |
| lost_reason | TEXT | | เมื่อ stage=lost |
| won_at | TIMESTAMP | | |
| lost_at | TIMESTAMP | | |
| note | TEXT | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 4.4 `activities`

กิจกรรม CRM/Deal/Project แบบ polymorphic

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| entity_type | VARCHAR(30) | NOT NULL | customer, deal, project, contact |
| entity_id | UUID | NOT NULL | |
| activity_type | VARCHAR(30) | NOT NULL | call, meeting, email, line, note, system |
| subject | VARCHAR(255) | | |
| body | TEXT | | |
| activity_at | TIMESTAMP | NOT NULL | |
| follow_up_at | TIMESTAMP | | |
| completed_at | TIMESTAMP | NULL | follow-up done; dashboard ดึงเฉพาะ completed_at IS NULL |
| owner_id | UUID | FK users | |
| created_at / updated_at / created_by / updated_by | | | |

**Indexes:** `(org_id, entity_type, entity_id)`, `(org_id, follow_up_at, completed_at, owner_id)`

### 4.5 `quotations`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| quotation_no | CHAR(6) | UNIQUE(org_id, quotation_no) | text 6 หลัก เช่น `000001` |
| customer_id | UUID | FK customers NOT NULL | |
| contact_id | UUID | FK contacts NULL | |
| deal_id | UUID | FK deals NULL | |
| status | VARCHAR(20) | NOT NULL | draft, sent, accepted, rejected, expired |
| issue_date | DATE | NOT NULL | |
| valid_until | DATE | | |
| subtotal | DECIMAL(18,2) | DEFAULT 0 | |
| discount_amount | DECIMAL(18,2) | DEFAULT 0 | |
| tax_amount | DECIMAL(18,2) | DEFAULT 0 | |
| total | DECIMAL(18,2) | DEFAULT 0 | |
| currency | CHAR(3) | DEFAULT 'THB' | |
| version | INT | DEFAULT 1 | |
| notes | TEXT | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 4.6 `quotation_items`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| quotation_id | UUID | FK quotations NOT NULL ON DELETE CASCADE | |
| product_id | UUID | FK products NULL | |
| description | VARCHAR(500) | NOT NULL | |
| quantity | DECIMAL(18,4) | NOT NULL DEFAULT 1 | |
| unit | VARCHAR(30) | | |
| unit_price | DECIMAL(18,2) | NOT NULL | |
| discount_amount | DECIMAL(18,2) | DEFAULT 0 | |
| tax_rate | DECIMAL(5,2) | DEFAULT 0 | |
| line_total | DECIMAL(18,2) | NOT NULL | |
| sort_order | INT | DEFAULT 0 | |
| created_at / updated_at | | | |

---

## 5. Delivery Tables

### 5.1 `projects`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| project_code | CHAR(6) | UNIQUE(org_id, project_code) | text 6 หลัก เช่น `000001` |
| name | VARCHAR(255) | NOT NULL | |
| customer_id | UUID | FK customers NULL | |
| deal_id | UUID | FK deals NULL | |
| owner_id | UUID | FK users | |
| status | VARCHAR(20) | NOT NULL | planned, active, on_hold, completed, cancelled |
| start_date | DATE | | |
| due_date | DATE | | |
| budget_amount | DECIMAL(18,2) | | |
| progress_percent | DECIMAL(5,2) | DEFAULT 0 | 0–100 |
| description | TEXT | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

> **AD-05:** **ไม่มี**คอลัมน์ `actual_cost`. Project cost = `sum(expenses.amount)` ที่ `project_id` เดียวกันและ status `approved`/`paid` (exclude soft-deleted)

### 5.2 `milestones`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| project_id | UUID | FK projects NOT NULL | |
| name | VARCHAR(255) | NOT NULL | |
| due_date | DATE | | |
| amount | DECIMAL(18,2) | | payment milestone |
| invoice_id | UUID | FK invoices NULL | |
| status | VARCHAR(20) | DEFAULT 'incomplete' | incomplete, complete |
| completed_at | TIMESTAMP | | |
| sort_order | INT | DEFAULT 0 | |
| created_at / updated_at / created_by / updated_by | | | |

### 5.3 `tasks`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| project_id | UUID | FK projects NULL | null = internal work |
| title | VARCHAR(255) | NOT NULL | |
| description | TEXT | | |
| assignee_id | UUID | FK users | |
| status | VARCHAR(20) | NOT NULL | todo, in_progress, review, done, blocked |
| priority | VARCHAR(20) | DEFAULT 'medium' | low, medium, high, urgent |
| due_date | DATE | | |
| completed_at | TIMESTAMP | | |
| is_overdue | BOOLEAN | DEFAULT false | cache / job update |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 5.4 `task_checklists`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | tenant isolation |
| task_id | UUID | FK tasks NOT NULL ON DELETE CASCADE | |
| title | VARCHAR(255) | NOT NULL | |
| is_done | BOOLEAN | DEFAULT false | |
| sort_order | INT | DEFAULT 0 | |
| created_at / updated_at | | | |

### 5.5 `task_comments`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| task_id | UUID | FK tasks NOT NULL ON DELETE CASCADE | |
| body | TEXT | NOT NULL | |
| author_id | UUID | FK users NOT NULL | |
| created_at / updated_at | | | |

---

## 6. Finance Tables

### 6.1 `products`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| sku | VARCHAR(50) | UNIQUE(org_id, sku) NULL | optional |
| name | VARCHAR(255) | NOT NULL | |
| type | VARCHAR(20) | NOT NULL | product, service, package |
| category | VARCHAR(100) | | |
| unit | VARCHAR(30) | | |
| price | DECIMAL(18,2) | NOT NULL DEFAULT 0 | |
| cost | DECIMAL(18,2) | DEFAULT 0 | |
| is_active | BOOLEAN | DEFAULT true | |
| description | TEXT | | |
| track_inventory | BOOLEAN | DEFAULT false | V2 |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 6.2 `suppliers`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| supplier_code | CHAR(6) | UNIQUE(org_id, supplier_code) | text 6 หลัก เช่น `000001` |
| name | VARCHAR(255) | NOT NULL | |
| tax_id | VARCHAR(50) | | |
| email | VARCHAR(255) | | |
| phone | VARCHAR(50) | | |
| address | TEXT | | |
| payment_terms | VARCHAR(100) | | |
| status | VARCHAR(20) | DEFAULT 'active' | |
| note | TEXT | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 6.3 `invoices`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| branch_id | UUID | FK branches NULL | optional numbering per branch |
| invoice_no | CHAR(6) | UNIQUE(org_id, invoice_no) | text 6 หลัก เช่น `000001` |
| customer_id | UUID | FK customers NOT NULL | |
| project_id | UUID | FK projects NULL | |
| quotation_id | UUID | FK quotations NULL | |
| deal_id | UUID | FK deals NULL | |
| status | VARCHAR(30) | NOT NULL | draft, sent, partially_paid, paid, overdue, void |
| tax_mode | VARCHAR(20) | NOT NULL DEFAULT 'exclusive' | exclusive, inclusive, no_tax |
| issue_date | DATE | NOT NULL | |
| due_date | DATE | | |
| subtotal | DECIMAL(18,2) | DEFAULT 0 | |
| discount_amount | DECIMAL(18,2) | DEFAULT 0 | |
| tax_amount | DECIMAL(18,2) | DEFAULT 0 | |
| total | DECIMAL(18,2) | DEFAULT 0 | |
| paid_amount | DECIMAL(18,2) | DEFAULT 0 | |
| balance_due | DECIMAL(18,2) | DEFAULT 0 | total - paid |
| currency | CHAR(3) | DEFAULT 'THB' | |
| notes | TEXT | | |
| sent_at | TIMESTAMP | | |
| paid_at | TIMESTAMP | | |
| voided_at | TIMESTAMP | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 6.4 `invoice_items`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| invoice_id | UUID | FK invoices NOT NULL ON DELETE CASCADE | |
| product_id | UUID | FK products NULL | |
| description | VARCHAR(500) | NOT NULL | |
| quantity | DECIMAL(18,4) | NOT NULL DEFAULT 1 | |
| unit | VARCHAR(30) | | |
| unit_price | DECIMAL(18,2) | NOT NULL | |
| discount_amount | DECIMAL(18,2) | DEFAULT 0 | |
| tax_rate | DECIMAL(5,2) | DEFAULT 0 | |
| line_total | DECIMAL(18,2) | NOT NULL | |
| sort_order | INT | DEFAULT 0 | |
| created_at / updated_at | | | |

### 6.5 `payments`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| invoice_id | UUID | FK invoices NOT NULL | |
| entry_type | VARCHAR(20) | NOT NULL DEFAULT 'receipt' | receipt, reversal |
| reversal_of_payment_id | UUID | FK payments NULL | reversal อ้าง receipt เดิม |
| amount | DECIMAL(18,2) | NOT NULL | |
| payment_date | DATE | NOT NULL | |
| payment_method | VARCHAR(30) | NOT NULL | bank_transfer, cash, credit_card, promptpay, other |
| reference_no | VARCHAR(100) | | |
| attachment_file_id | UUID | FK files NULL | |
| note | TEXT | | |
| idempotency_key | VARCHAR(100) | NULL UNIQUE(org_id, idempotency_key) | กัน submit payment ซ้ำ |
| created_at / updated_at / created_by / updated_by | | | |

**Rules:** `amount > 0`; payment ที่ post แล้วห้ามแก้/ลบ; ยกเลิกด้วย reversal entry เท่านั้น; receipt หนึ่งรายการ reverse ได้ครั้งเดียว; reversal ใช้ `payment_date = CURRENT_DATE` ของวันที่ทำรายการจริง; `paid_amount = sum(receipt.amount) - sum(reversal.amount)` และ `balance_due = total - paid_amount`.

### 6.6 `expenses`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| expense_no | CHAR(6) | UNIQUE(org_id, expense_no) | text 6 หลัก เช่น `000001` |
| category | VARCHAR(50) | NOT NULL | salary, software, marketing, travel, office, contractor, hosting, misc |
| title | VARCHAR(255) | NOT NULL | |
| amount | DECIMAL(18,2) | NOT NULL | |
| expense_date | DATE | NOT NULL | |
| project_id | UUID | FK projects NULL | |
| supplier_id | UUID | NULL; MVP ไม่ใส่ FK | Post-MVP ค่อยเพิ่ม FK suppliers |
| status | VARCHAR(20) | NOT NULL | draft, approved, paid, rejected |
| receipt_file_id | UUID | FK files NULL | |
| approved_by | UUID | FK users NULL | |
| approved_at | TIMESTAMP | | |
| paid_at | TIMESTAMP | | |
| note | TEXT | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 6.7 `purchase_orders` (V2)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| po_no | CHAR(6) | UNIQUE(org_id, po_no) | text 6 หลัก เช่น `000001` |
| supplier_id | UUID | FK suppliers NOT NULL | |
| status | VARCHAR(20) | NOT NULL | draft, sent, approved, received, cancelled |
| issue_date | DATE | | |
| expected_date | DATE | | |
| subtotal | DECIMAL(18,2) | DEFAULT 0 | |
| tax_amount | DECIMAL(18,2) | DEFAULT 0 | |
| total | DECIMAL(18,2) | DEFAULT 0 | |
| notes | TEXT | | |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 6.8 `purchase_order_items` (V2)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| purchase_order_id | UUID | FK purchase_orders NOT NULL ON DELETE CASCADE | |
| product_id | UUID | FK products NULL | |
| description | VARCHAR(500) | NOT NULL | |
| quantity | DECIMAL(18,4) | NOT NULL | |
| unit_price | DECIMAL(18,2) | NOT NULL | |
| line_total | DECIMAL(18,2) | NOT NULL | |
| sort_order | INT | DEFAULT 0 | |
| created_at / updated_at | | | |

---

## 7. Inventory Tables (V2)

### 7.1 `warehouses`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| code | CHAR(6) | UNIQUE(org_id, code) | text 6 หลัก เช่น `000001` |
| name | VARCHAR(255) | NOT NULL | |
| branch_id | UUID | FK branches NULL | |
| address | TEXT | | |
| status | VARCHAR(20) | DEFAULT 'active' | |
| created_at / updated_at / created_by / updated_by | | | |

### 7.2 `stock_levels`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| warehouse_id | UUID | FK warehouses NOT NULL | |
| product_id | UUID | FK products NOT NULL | |
| quantity_on_hand | DECIMAL(18,4) | DEFAULT 0 | |
| reorder_level | DECIMAL(18,4) | DEFAULT 0 | low stock |
| UNIQUE | | (warehouse_id, product_id) | |
| updated_at | TIMESTAMP | | |

### 7.3 `stock_movements`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| warehouse_id | UUID | FK warehouses NOT NULL | |
| product_id | UUID | FK products NOT NULL | |
| movement_type | VARCHAR(30) | NOT NULL | in, out, adjust, damage, transfer |
| quantity | DECIMAL(18,4) | NOT NULL | signed or absolute+type |
| reference_type | VARCHAR(30) | | purchase_order, invoice, adjustment |
| reference_id | UUID | | |
| note | TEXT | | |
| moved_at | TIMESTAMP | NOT NULL | |
| created_at / created_by | | | |

---

## 8. HR Tables

### 8.1 `employees` (V1 light / V2+)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| user_id | UUID | FK users NULL UNIQUE | map บัญชี login |
| employee_code | CHAR(6) | UNIQUE(org_id, employee_code) | text 6 หลัก เช่น `000001` |
| first_name | VARCHAR(100) | NOT NULL | |
| last_name | VARCHAR(100) | | |
| email | VARCHAR(255) | | |
| phone | VARCHAR(50) | | |
| position | VARCHAR(100) | | |
| branch_id | UUID | FK branches NULL | |
| division_id | UUID | FK divisions NULL | |
| department_id | UUID | FK departments NULL | |
| employment_status | VARCHAR(30) | DEFAULT 'active' | active, resigned, suspended |
| start_date | DATE | | |
| end_date | DATE | | |
| salary_base | DECIMAL(18,2) | | optional |
| created_at / updated_at / created_by / updated_by / deleted_at | | | |

### 8.2 `attendances` (V2)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| employee_id | UUID | FK employees NOT NULL | |
| work_date | DATE | NOT NULL | |
| clock_in_at | TIMESTAMP | | |
| clock_out_at | TIMESTAMP | | |
| status | VARCHAR(20) | | present, late, absent, leave |
| note | TEXT | | |
| created_at / updated_at | | | |
| UNIQUE | | (employee_id, work_date) | |

### 8.3 `leave_requests` (V2)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| employee_id | UUID | FK employees NOT NULL | |
| leave_type | VARCHAR(30) | NOT NULL | annual, sick, personal, unpaid |
| start_date | DATE | NOT NULL | |
| end_date | DATE | NOT NULL | |
| days | DECIMAL(5,1) | NOT NULL | |
| status | VARCHAR(20) | NOT NULL | pending, approved, rejected, cancelled |
| reason | TEXT | | |
| approver_id | UUID | FK users NULL | |
| decided_at | TIMESTAMP | | |
| created_at / updated_at / created_by / updated_by | | | |

### 8.4 `leave_balances` (V2)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| employee_id | UUID | FK employees NOT NULL | |
| leave_type | VARCHAR(30) | NOT NULL | |
| year | INT | NOT NULL | |
| entitled_days | DECIMAL(5,1) | DEFAULT 0 | |
| used_days | DECIMAL(5,1) | DEFAULT 0 | |
| UNIQUE | | (employee_id, leave_type, year) | |

### 8.5 `payroll_runs` (V3)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| period_year | INT | NOT NULL | |
| period_month | INT | NOT NULL | 1–12 |
| status | VARCHAR(20) | NOT NULL | draft, approved, paid |
| total_gross | DECIMAL(18,2) | DEFAULT 0 | |
| total_deduction | DECIMAL(18,2) | DEFAULT 0 | |
| total_net | DECIMAL(18,2) | DEFAULT 0 | |
| approved_at | TIMESTAMP | | |
| created_at / updated_at / created_by / updated_by | | | |

### 8.6 `payslips` (V3)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| payroll_run_id | UUID | FK payroll_runs NOT NULL | |
| employee_id | UUID | FK employees NOT NULL | |
| base_salary | DECIMAL(18,2) | DEFAULT 0 | |
| allowance | DECIMAL(18,2) | DEFAULT 0 | |
| deduction | DECIMAL(18,2) | DEFAULT 0 | |
| net_pay | DECIMAL(18,2) | DEFAULT 0 | |
| details_json | JSON | | breakdown |
| created_at / updated_at | | | |

---

## 9. Platform / System Tables

### 9.1 `files`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| storage_key | VARCHAR(500) | NOT NULL | path/S3 key |
| file_name | VARCHAR(255) | NOT NULL | |
| mime_type | VARCHAR(100) | | |
| size_bytes | BIGINT | | |
| category | VARCHAR(50) | | receipt, contract, general |
| entity_type | VARCHAR(30) | | MVP allowlist: payment, expense; Post-MVP: customer, deal, project, invoice, task, ... |
| entity_id | UUID | | |
| uploaded_by | UUID | FK users | |
| created_at / updated_at / deleted_at | | | |


**MVP attachment pattern:** `payments.attachment_file_id` และ `expenses.receipt_file_id` เป็น canonical FK สำหรับไฟล์แนบการเงิน. `files.entity_type/entity_id` ใช้เป็น reverse lookup เท่านั้นและต้องชี้ parent เดียวกันหลัง create สำเร็จ. MVP allowlist ของ `files.entity_type` คือ `payment`, `expense`. Download ตรวจ permission จาก parent entity เสมอ.

**Storage key rule:** `storage_key` ต้องสร้างฝั่ง server ด้วย random UUID/cryptographic random เช่น `tenants/{org_id}/{year}/{month}/{uuid}.{ext}`; ห้ามใช้ชื่อไฟล์หรือ path จากผู้ใช้โดยตรง.
### 9.2 `notifications`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| user_id | UUID | FK users NOT NULL | |
| type | VARCHAR(50) | NOT NULL | task_assigned, invoice_overdue, ... |
| title | VARCHAR(255) | NOT NULL | |
| body | TEXT | | |
| entity_type | VARCHAR(30) | | allowlist: deal, project, task, invoice, payment, expense, user |
| entity_id | UUID | | |
| channel | VARCHAR(20) | DEFAULT 'in_app' | in_app, email, line |
| is_read | BOOLEAN | DEFAULT false | |
| read_at | TIMESTAMP | | |
| created_at | TIMESTAMP | | |

### 9.3 `audit_logs`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| actor_user_id | UUID | FK users NULL | system = null |
| action | VARCHAR(50) | NOT NULL | create, update, delete, login, approve, ... |
| entity_type | VARCHAR(50) | NOT NULL | allowlist: user, role, organization, branch, division, department, customer, contact, deal, project, task, product, invoice, payment, expense |
| entity_id | UUID | | |
| before_json | JSON | | field สำคัญ |
| after_json | JSON | | |
| ip_address | VARCHAR(45) | | |
| user_agent | TEXT | | |
| request_id | VARCHAR(100) | | correlation/request id |
| created_at | TIMESTAMP | NOT NULL | |

**Indexes:** `(org_id, created_at)`, `(org_id, entity_type, entity_id)`, `(org_id, actor_user_id)`

### 9.4 `automation_rules`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| name | VARCHAR(255) | NOT NULL | |
| trigger_type | VARCHAR(50) | NOT NULL | invoice_overdue, task_due_today, deal_stale |
| condition_json | JSON | | |
| action_type | VARCHAR(50) | NOT NULL | create_notification, webhook, email |
| action_json | JSON | | |
| is_active | BOOLEAN | DEFAULT true | |
| created_at / updated_at / created_by / updated_by | | | |

### 9.5 `automation_logs`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| rule_id | UUID | FK automation_rules NULL | |
| status | VARCHAR(20) | | success, failed, skipped |
| message | TEXT | | |
| payload_json | JSON | | |
| created_at | TIMESTAMP | | |

### 9.6 `webhook_events`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| event_type | VARCHAR(50) | NOT NULL | |
| target_url | VARCHAR(500) | NOT NULL | |
| payload_json | JSON | | |
| status | VARCHAR(20) | | pending, sent, failed |
| attempts | INT | DEFAULT 0 | |
| last_error | TEXT | | |
| next_retry_at | TIMESTAMP | | |
| created_at / updated_at | | | |

### 9.7 `settings`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| key | VARCHAR(100) | NOT NULL | UNIQUE(org_id, key) |
| value_json | JSON | NOT NULL | |
| created_at / updated_at / updated_by | | | |

**ตัวอย่าง keys:** `invoice.numbering`, `quotation.numbering`, `payment.default_terms`, `email.sender`, `webhook.secret`

### 9.8 `api_tokens`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| name | VARCHAR(100) | NOT NULL | |
| token_hash | VARCHAR(255) | NOT NULL UNIQUE | |
| scopes | JSON | | |
| last_used_at | TIMESTAMP | | |
| expires_at | TIMESTAMP | | |
| revoked_at | TIMESTAMP | | |
| created_by | UUID | FK users | |
| created_at / updated_at | | | |

### 9.9 `number_sequences`

ใช้รันเลขเอกสาร/รหัสธุรกิจอัตโนมัติ. `deals` ไม่ใช้ `number_sequences` ใน MVP เพราะไม่มี `deal_code`.

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| branch_id | UUID | FK branches NULL | |
| doc_type | VARCHAR(30) | NOT NULL | branch, division, department, customer, project, invoice, expense, quotation, supplier, po, employee, warehouse |
| prefix | VARCHAR(20) | | optional display prefix; MVP code/no หลักยังเป็น CHAR(6) |
| year | INT | | optional reset รายปี |
| last_number | INT | DEFAULT 0 | 0-999999; format เป็น 6 หลักด้วย leading zero |
| UNIQUE | | (org_id, branch_id, doc_type, year) | |

**Rules:** `last_number` ห้ามเกิน `999999`; การออกเลขใหม่ต้อง lock row แล้วคำนวณ `next = last_number + 1`, บันทึก code/no เป็น `LPAD(next, 6, '0')`, จากนั้น set `last_number = next`; ห้าม reuse รหัสที่เคยออกแล้ว.

### 9.10 `ai_usage_logs` (V2)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| user_id | UUID | FK users | |
| feature | VARCHAR(50) | | summarize_deal, draft_message |
| input_tokens | INT | | |
| output_tokens | INT | | |
| status | VARCHAR(20) | | success, failed, skipped_no_key |
| created_at | TIMESTAMP | | |

### 9.11 `accounting_sync_logs` (V2)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| provider | VARCHAR(30) | NOT NULL | flowaccount, peak, xero, quickbooks |
| entity_type | VARCHAR(30) | NOT NULL | customer, invoice, payment, expense |
| entity_id | UUID | NOT NULL | |
| external_id | VARCHAR(100) | | |
| direction | VARCHAR(10) | | push, pull |
| status | VARCHAR(20) | | pending, success, failed |
| error_message | TEXT | | |
| payload_json | JSON | | |
| created_at / updated_at | | | |

### 9.12 `import_jobs`

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| entity_type | VARCHAR(30) | NOT NULL | customers, products, invoices |
| file_id | UUID | FK files | |
| status | VARCHAR(20) | | pending, validating, preview, importing, done, failed |
| total_rows | INT | | |
| success_rows | INT | | |
| error_rows | INT | | |
| error_json | JSON | | |
| created_by | UUID | FK users | |
| created_at / updated_at | | | |

### 9.13 `portal_users` (V3)

| Column | Type | Constraints | Description |
| --- | --- | --- | --- |
| id | UUID | PK | |
| org_id | UUID | FK organizations NOT NULL | |
| customer_id | UUID | FK customers NOT NULL | |
| email | VARCHAR(255) | NOT NULL | |
| password | VARCHAR(255) | NOT NULL | hashed only (V3 portal) |
| status | VARCHAR(20) | DEFAULT 'active' | |
| last_login_at | TIMESTAMP | | |
| created_at / updated_at | | | |

---


## 10. Laravel Framework Tables (MVP)

ตารางกลุ่มนี้เป็น framework/runtime tables ของ Laravel Breeze และ session driver. ไม่ถือเป็น business module แต่ต้องอยู่ใน migration Phase 1.

### 10.1 `users` Breeze compatibility

`users` ต้องรองรับคอลัมน์ของ Laravel auth เพิ่มจาก schema profile:

| Column | Notes |
| --- | --- |
| password | hashed only |
| remember_token | nullable; ห้าม expose ใน props/logs |
| email_verified_at | nullable timestamp |

### 10.2 Required framework tables

| Table | Purpose |
| --- | --- |
| sessions | encrypted server-side DB session |
| password_reset_tokens | password reset flow |
| cache / cache_locks | Laravel cache ถ้าใช้ database cache driver |
| jobs / failed_jobs | queue jobs สำหรับ mail/reset/invite ถ้าใช้ database queue |

> หากเลือก cache/queue driver แบบอื่นใน production ให้ยังคงระบุ migration strategy ใน deploy notes.

---

## 11. Cross-module Foreign Key Map

| From | Column | To |
| --- | --- | --- |
| contacts | customer_id | customers.id |
| branches | org_id | organizations.id |
| divisions | branch_id | branches.id |
| departments | division_id | divisions.id |
| users | branch_id | branches.id |
| users | division_id | divisions.id |
| users | department_id | departments.id |
| contacts | supplier_id | suppliers.id (Post-MVP FK) |
| deals | customer_id | customers.id |
| deals | contact_id | contacts.id |
| quotations | deal_id | deals.id |
| quotations | customer_id | customers.id |
| projects | deal_id | deals.id |
| projects | customer_id | customers.id |
| tasks | project_id | projects.id |
| milestones | project_id | projects.id |
| milestones | invoice_id | invoices.id |
| invoices | customer_id | customers.id |
| invoices | project_id | projects.id |
| invoices | quotation_id | quotations.id |
| invoice_items | product_id | products.id |
| payments | invoice_id | invoices.id |
| expenses | project_id | projects.id |
| expenses | supplier_id | suppliers.id (Post-MVP FK) |
| purchase_orders | supplier_id | suppliers.id |
| stock_levels | product_id | products.id |
| employees | user_id | users.id |
| files | entity_* | polymorphic |
| activities | entity_* | polymorphic |

---

## 12. Module Ownership Map

| Module | Primary tables | Shared / read |
| --- | --- | --- |
| Organization | organizations, branches, divisions, departments | users |
| User / Role | users, roles, permissions, role_permissions, user_roles | organizations |
| CRM | customers | contacts, activities, users |
| Contacts | contacts | customers, suppliers |
| Deals | deals | customers, contacts, activities |
| Quotations | quotations, quotation_items | deals, products, customers |
| Projects | projects | deals, customers, tasks, milestones |
| Tasks | tasks, task_checklists, task_comments | projects, users |
| Milestones | milestones | projects, invoices |
| Products | products | quotation_items, invoice_items |
| Suppliers | suppliers | contacts, expenses, purchase_orders |
| Invoices | invoices, invoice_items | customers, projects, products |
| Payments | payments | invoices, files |
| Expenses | expenses | projects, suppliers, files |
| Purchase Orders | purchase_orders, purchase_order_items | suppliers, products |
| Inventory | warehouses, stock_levels, stock_movements | products |
| Employees | employees | users, branches, divisions, departments |
| Attendance / Leave | attendances, leave_requests, leave_balances | employees |
| Payroll | payroll_runs, payslips | employees |
| Files | files | ทุก module ที่ attach |
| Notifications | notifications | users |
| Audit Log | audit_logs | ทุก module เขียน event |
| Automation | automation_rules, automation_logs, webhook_events | multi-entity |
| Settings | settings, number_sequences | organizations |
| API | api_tokens | audit / rate limit |
| AI | ai_usage_logs | deals, projects (read) |
| Accounting Integration | accounting_sync_logs | invoices, payments, customers, expenses |
| Import / Export | import_jobs | files + target entities |
| Customer Portal | portal_users | customers, quotations, invoices |
| Dashboard / Reports | *(ไม่มีตารางเฉพาะ — aggregate view)* | invoices, payments, expenses, deals, projects, tasks |

---


## 13. MVP Constraints / Indexes / Seed Data

### 13.1 Required constraints before first migration

| Area | Constraint |
| --- | --- |
| business/display code | `CHAR(6)` + regex/check numeric string `^[0-9]{6}$` where DB supports; otherwise validate in app; no reuse after soft delete |
| money | `amount`, `total`, `paid_amount`, `balance_due`, `price`, `cost` ต้อง `>= 0` |
| payments | `amount > 0` |
| payments reversal | one receipt can have only one reversal; MariaDB strategy = generated column `reversal_target_id = IF(entry_type = 'reversal', reversal_of_payment_id, NULL)` + `UNIQUE(org_id, reversal_target_id)` หรือ transaction lock + app validation |
| number_sequences | `last_number BETWEEN 0 AND 999999`; ใช้ row lock/atomic increment; Phase 1 ใช้ app-maintained `branch_key/year_key` เพื่อ enforce unique เมื่อ `branch_id/year` nullable บน MariaDB |
| invoice totals | `balance_due >= 0`, `paid_amount >= 0`, `total >= 0`; `tax_mode IN (exclusive, inclusive, no_tax)` |
| hierarchy | division must belong to branch/org; department must belong to division/branch/org; user assignment must validate chain in app service |
| contacts primary | app-level transaction: เมื่อ set `is_primary=true` ต้อง unset primary เดิมใน customer เดียวกัน |
| invoice edit guard | invoice ที่ `paid_amount > 0` ห้ามแก้ item/total จนกว่า reverse payment ครบ |

### 13.2 Required indexes for MVP dashboards

| Table | Index |
| --- | --- |
| customers | `(org_id, owner_id, created_at)` |
| deals | `(org_id, stage, owner_id, expected_close_date)` |
| activities | `(org_id, entity_type, entity_id)`, `(org_id, follow_up_at, completed_at, owner_id)` |
| invoices | `(org_id, status, issue_date)`, `(org_id, due_date, status)`, `(org_id, customer_id)`, `(org_id, deal_id)`, `(org_id, project_id)` |
| payments | `(org_id, invoice_id, entry_type)`, `(org_id, payment_date)`, `(org_id, reversal_of_payment_id)` |
| expenses | `(org_id, expense_no)`, `(org_id, status, expense_date)`, `(org_id, paid_at)`, `(org_id, project_id, status)` |
| projects | `(org_id, status, owner_id, due_date)` |
| tasks | `(org_id, status, assignee_id, due_date)`, `(org_id, project_id)` |
| task_checklists | `(org_id, task_id)` |
| audit_logs | `(org_id, created_at)`, `(org_id, entity_type, entity_id)` |

### 13.3 Idempotency

- Financial write endpoints ต้องรับ `Idempotency-Key` หรือสร้าง key ฝั่ง server สำหรับ retry-safe operation.
- MVP บังคับกับ payment receipt/reversal ก่อน.
- key unique ภายใน org.
- request ซ้ำด้วย key เดิมต้องคืนผลเดิม ไม่สร้าง payment ซ้ำ.

### 13.4 Default seed data

| Phase | Seed |
| --- | --- |
| Phase 1 | organization demo, head office branch `000001`, default division `000001`, default department `000001`, owner user, roles, permissions, role_permissions, number_sequences |
| Phase 2 | sample customers, contacts, deals, activities |
| Phase 3 | sample products/services, invoices, payments, reversal case, expenses |
| Phase 4 | sample projects, tasks, linked invoice/expense |
| Phase 5 | UAT dataset ครบ flow และ dashboard snapshot expected values |

### 13.5 Default roles

`owner`, `admin`, `sales`, `project_manager`, `finance`, `member`, `viewer`

### 13.6 Default number sequences

สร้างแถวเริ่มต้นตาม record ที่ seed แล้ว: ถ้าสร้าง default code `000001` ให้ตั้ง `last_number=1`; ถ้ายังไม่มี record ให้ตั้ง `last_number=0`.

- Phase 1: branch, division, department
- Phase 2: customer
- Phase 3: invoice
- Phase 4: project
- Post-MVP/V2: quotation, supplier, po, employee, warehouse


### 13.7 Soft delete financial rules

| Entity | Rule |
| --- | --- |
| payments | ห้าม soft delete; ใช้ reversal เท่านั้น |
| invoices | ห้าม soft delete ถ้ามี net payment; ใช้ void flow แทน |
| expenses | soft delete ได้ตาม permission แต่ต้อง exclude จาก aggregate |
| projects | ห้าม soft delete ถ้ามี open invoice/expense; ใช้ status `cancelled` ก่อน |
| business/display code | ห้าม reuse แม้ record ถูก soft delete |
---

## 14. Change Control

1. แก้ schema ในไฟล์นี้ก่อนเสมอ
2. อัปเดต module doc ที่ `docs/modules/*.md` ให้ตรง FK / field
3. ถ้าเป็น breaking change ให้ระบุ version / migration note
4. ห้ามให้ module ใดสร้างคอลัมน์ซ้ำความหมายต่างกัน (เช่น `total` vs `total_amount` ปนกันโดยไม่จำเป็น)

---

## 15. Document Index

รายละเอียด workflow / data flow ของแต่ละ module อยู่ที่:

[`docs/modules/README.md`](../modules/README.md)
















