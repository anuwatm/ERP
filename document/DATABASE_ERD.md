# ผังแบบจำลองฐานข้อมูลทั้งระบบ (Complete Database ER Diagram — 38+ Tables)

เอกสารนี้รวบรวม **Entity Relationship (ER) Diagram ฉบับสมบูรณ์ของระบบ Company OS / Lightweight ERP ทั้งหมด 38+ ตาราง** อ้างอิงตามโครงสร้างฐานข้อมูลกลาง [`docs/database/DATABASE.md`](file:///c:/LocalDevine/www/ERP/docs/database/DATABASE.md) (Single Source of Truth) พร้อมคำอธิบายความสัมพันธ์, Primary Keys, Foreign Keys, Constraints, และกฎทางธุรกิจภาษาไทยอย่างละเอียด

---

## 📑 สารบัญโดเมนฐานข้อมูล (Database Domains)

1. [ภาพรวมความสัมพันธ์ระหว่างโดเมนทั้งระบบ (Master System-Wide ERD)](#1-ภาพรวมความสัมพันธ์ระหว่างโดเมนทั้งระบบ-master-system-wide-erd)
2. [Domain 1: โครงสร้างองค์กรและระบบผู้ใช้ (Foundation & IAM)](#2-domain-1-โครงสร้างองค์กรและระบบผู้ใช้-foundation--iam)
3. [Domain 2: ลูกค้าสัมพันธ์และโอกาสการขาย (CRM & Sales)](#3-domain-2-ลูกค้าสัมพันธ์และโอกาสการขาย-crm--sales)
4. [Domain 3: การบริหารโครงการและงานส่งมอบ (Projects & Delivery)](#4-domain-3-การบริหารโครงการและงานส่งมอบ-projects--delivery)
5. [Domain 4: การเงิน ใบแจ้งหนี้ รับชำระ และการจัดซื้อ (Finance, Billing & Procurement)](#5-domain-4-การเงิน-ใบแจ้งหนี้-รับชำระ-และการจัดซื้อ-finance-billing--procurement)
6. [Domain 5: คลังสินค้าและสินค้าคงคลัง (Warehouse & Inventory)](#6-domain-5-คลังสินค้าและสินค้าคงคลัง-warehouse--inventory)
7. [Domain 6: Payroll (Phase 16B)](#7-domain-6-payroll-phase-16b)
8. [Domain 7: แพลตฟอร์ม บันทึกประวัติ และการเชื่อมต่อระบบ (Platform & Integrations)](#8-domain-7-แพลตฟอร์ม-บันทึกประวัติ-และการเชื่อมต่อระบบ-platform--integrations)
9. [มาตรฐานและกฎข้อบังคับของฐานข้อมูล (Database Conventions & Strict Rules)](#9-มาตรฐานและกฎข้อบังคับของฐานข้อมูล-database-conventions--strict-rules)

---

## 1. ภาพรวมความสัมพันธ์ระหว่างโดเมนทั้งระบบ (Master System-Wide ERD)

ผังแสดงความสัมพันธ์ระดับแกนกลาง (Core Entities) ระหว่าง 7 โดเมนหลักในระบบ

```mermaid
erDiagram
    ORGANIZATIONS ||--o{ BRANCHES : "1:N แบ่งสาขา"
    BRANCHES ||--o{ DIVISIONS : "1:N แบ่งสายงาน"
    DIVISIONS ||--o{ DEPARTMENTS : "1:N แบ่งแผนก"
    DEPARTMENTS ||--o{ USERS : "1:N สังกัดผู้ใช้"
    
    ORGANIZATIONS ||--o{ USERS : "1:N เจ้าของบัญชี"
    ORGANIZATIONS ||--o{ CUSTOMERS : "1:N ครอบครองลูกค้า"
    ORGANIZATIONS ||--o{ PRODUCTS : "1:N แคตตาล็อกสินค้า"
    ORGANIZATIONS ||--o{ SUPPLIERS : "1:N คู่ค้า/ผู้จำหน่าย"
    ORGANIZATIONS ||--o{ WAREHOUSES : "1:N คลังสินค้า"
    ORGANIZATIONS ||--o{ EMPLOYEE_PAYROLL_PROFILES : "1:N payroll profiles"

    CUSTOMERS ||--o{ CONTACTS : "1:N รายชื่อผู้ติดต่อ"
    CUSTOMERS ||--o{ DEALS : "1:N เปิดโอกาสการขาย"
    CUSTOMERS ||--o{ QUOTATIONS : "1:N ออกใบเสนอราคา"
    CUSTOMERS ||--o{ INVOICES : "1:N ออกใบแจ้งหนี้"
    CUSTOMERS ||--o{ PORTAL_USERS : "1:N ผู้ใช้พอร์ทัลลูกค้า"

    DEALS ||--o{ QUOTATIONS : "1:N จัดทำใบเสนอราคา"
    DEALS ||--o{ PROJECTS : "1:N (Won) เปิดโปรเจกต์ส่งมอบ"
    
    PROJECTS ||--o{ MILESTONES : "1:N แบ่งงวดงาน"
    MILESTONES ||--o{ TASKS : "1:N งานย่อยในงวด"
    PROJECTS ||--o{ TASKS : "1:N งานย่อยทั้งหมด"
    PROJECTS o|--o{ INVOICES : "0..1:N อ้างอิงเรียกเก็บเงิน"
    PROJECTS o|--o{ EXPENSES : "0..1:N ผูกต้นทุนโครงการ"

    INVOICES ||--o{ INVOICE_ITEMS : "1:N รายการในบิล"
    INVOICES ||--o{ PAYMENTS : "1:N บันทึกรับชำระ"
    PRODUCTS ||--o{ INVOICE_ITEMS : "1:N อ้างอิงราคาขาย"
    
    SUPPLIERS ||--o{ PURCHASE_ORDERS : "1:N สั่งซื้อสินค้า"
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : "1:N รายการสั่งซื้อ"
    PRODUCTS ||--o{ PURCHASE_ORDER_ITEMS : "1:N สินค้าที่สั่งซื้อ"
    WAREHOUSES ||--o{ STOCK_LEVELS : "1:N จัดเก็บในคลัง"
    PRODUCTS ||--o{ STOCK_LEVELS : "1:N ยอดคงเหลือ"

    USERS ||--o| EMPLOYEE_PAYROLL_PROFILES : "0..1:1 profile"
    PAYROLL_TAX_POLICIES ||--o{ PAYROLL_RUNS : "locked policy"
    SOCIAL_SECURITY_POLICIES ||--o{ PAYROLL_RUNS : "locked policy"
    PAYROLL_RUNS ||--o{ PAYROLL_ITEMS : "1:N calculation"
    USERS ||--o{ PAYROLL_ITEMS : "receives payslip"
```

---

## 2. Domain 1: โครงสร้างองค์กรและระบบผู้ใช้ (Foundation & IAM)

ครอบคลุมการแบ่ง Tenant, ลำดับชั้นองค์กร, และระบบสิทธิ์ RBAC 7 บทบาท (`owner`, `admin`, `sales`, `project_manager`, `finance`, `member`, `viewer`)

```mermaid
erDiagram
    ORGANIZATIONS {
        uuid id PK
        varchar name "ชื่อองค์กร/บริษัท"
        varchar legal_name "ชื่อนิติบุคคลจดทะเบียน"
        varchar tax_id "เลขประจำตัวผู้เสียภาษี"
        char currency "สกุลเงินหลัก (e.g. THB)"
        timestamp created_at
    }

    BRANCHES {
        uuid id PK
        uuid org_id FK "อ้างอิงองค์กร"
        char code "รหัสสาขา 6 หลัก (e.g. 000001)"
        varchar name "ชื่อสาขา"
        boolean is_head_office "เป็นสำนักงานใหญ่หรือไม่"
    }

    DIVISIONS {
        uuid id PK
        uuid org_id FK
        uuid branch_id FK "อ้างอิงสาขา"
        char code "รหัสสายงาน 6 หลัก"
        varchar name "ชื่อสายงาน"
    }

    DEPARTMENTS {
        uuid id PK
        uuid org_id FK
        uuid division_id FK "อ้างอิงสายงาน"
        char code "รหัสแผนก 6 หลัก"
        varchar name "ชื่อแผนก"
    }

    USERS {
        uuid id PK
        uuid org_id FK "1 User = 1 Org"
        uuid department_id FK "สังกัดแผนก (Nullable)"
        varchar name "ชื่อ-นามสกุล"
        varchar email "อีเมล (Unique ในระบบ)"
        varchar password "รหัสผ่านที่แฮชแล้ว"
        varchar status "active, inactive, suspended"
    }

    ROLES {
        uuid id PK
        uuid org_id FK "Nullable (System Roles มี org_id เป็น null)"
        varchar code "owner, admin, sales, finance, pm, member, viewer"
        varchar name "ชื่อบทบาทที่แสดง"
        boolean is_system "บทบาทมาตรฐานระบบ"
    }

    PERMISSIONS {
        uuid id PK
        varchar code "module.action (e.g. invoices.create)"
        varchar module "ชื่อโมดูล"
        varchar name "คำอธิบายสิทธิ์"
    }

    USER_ROLES {
        uuid id PK
        uuid user_id FK
        uuid role_id FK
    }

    ROLE_PERMISSIONS {
        uuid id PK
        uuid role_id FK
        uuid permission_id FK
    }

    ORGANIZATIONS ||--o{ BRANCHES : "has"
    BRANCHES ||--o{ DIVISIONS : "has"
    DIVISIONS ||--o{ DEPARTMENTS : "has"
    DEPARTMENTS ||--o{ USERS : "contains"
    ORGANIZATIONS ||--o{ USERS : "owns"
    USERS ||--o{ USER_ROLES : "assigned"
    ROLES ||--o{ USER_ROLES : "has"
    ROLES ||--o{ ROLE_PERMISSIONS : "grants"
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : "defined_in"
```

---

## 3. Domain 2: ลูกค้าสัมพันธ์และโอกาสการขาย (CRM & Sales)

ครอบคลุมฐานข้อมูลลูกค้า, รายชื่อผู้ติดต่อ, ท่อโอกาสการขาย (Deals), ประวัติกิจกรรมติดตาม, และใบเสนอราคา (Quotations)

```mermaid
erDiagram
    CUSTOMERS {
        uuid id PK
        uuid org_id FK
        char customer_code "รหัสลูกค้า 6 หลัก (Unique ใน Org)"
        varchar company_name "ชื่อบริษัท/ลูกค้า"
        varchar tax_id "เลขผู้เสียภาษี"
        varchar customer_type "lead, prospect, customer"
        varchar status "active, inactive"
        uuid owner_id FK "พนักงานผู้ดูแล (users.id)"
        varchar phone
        varchar email
    }

    CONTACTS {
        uuid id PK
        uuid org_id FK
        uuid customer_id FK "สังกัดลูกค้า"
        varchar name "ชื่อผู้ติดต่อ"
        varchar position "ตำแหน่ง"
        varchar email
        varchar phone
        boolean is_primary "เป็นผู้ติดต่อหลักหรือไม่"
    }

    DEALS {
        uuid id PK
        uuid org_id FK
        uuid customer_id FK "ลูกค้าเป้าหมาย"
        uuid owner_id FK "พนักงานผู้รับผิดชอบ"
        varchar name "ชื่อดีล/โครงการที่เสนอ"
        decimal value "มูลค่าดีล DECIMAL(18,2)"
        char currency "สกุลเงิน"
        varchar stage "new, contacted, qualified, proposal, negotiation, won, lost"
        decimal win_probability "โอกาสชนะ (0.00 - 100.00)"
        date expected_close_date "วันที่คาดว่าจะปิดการขาย"
        varchar lost_reason "เหตุผลที่แพ้ดีล"
    }

    ACTIVITIES {
        uuid id PK
        uuid org_id FK
        varchar entity_type "customer, deal, contact"
        uuid entity_id "ID ของ Entity ที่เกี่ยวข้อง"
        uuid user_id FK "ผู้บันทึกกิจกรรม"
        varchar type "call, meeting, email, line, note"
        text notes "รายละเอียดกิจกรรม"
        timestamp follow_up_at "กำหนดเวลาติดตามผล"
        timestamp completed_at "เวลาที่ติดตามสำเร็จ"
    }

    QUOTATIONS {
        uuid id PK
        uuid org_id FK
        char quotation_no "เลขที่ใบเสนอราคา 6 หลัก"
        uuid customer_id FK
        uuid deal_id FK "อ้างอิงดีล (Nullable)"
        date issue_date "วันที่ออกเอกสาร"
        date valid_until "ใช้ได้ถึงวันที่"
        varchar status "draft, sent, accepted, rejected, expired"
        decimal subtotal "ยอดรวมก่อนภาษี DECIMAL(18,2)"
        decimal discount_amount "ส่วนลด"
        decimal tax_amount "ภาษีมูลค่าเพิ่ม"
        decimal total_amount "ยอดรวมสุทธิ"
    }

    QUOTATION_ITEMS {
        uuid id PK
        uuid quotation_id FK
        uuid product_id FK "อ้างอิงสินค้า/บริการ (Nullable)"
        varchar description "รายละเอียดรายการ"
        decimal quantity "จำนวน DECIMAL(12,4)"
        decimal unit_price "ราคาต่อหน่วย DECIMAL(18,2)"
        decimal amount "ยอดรวมรายการ DECIMAL(18,2)"
    }

    CUSTOMERS ||--o{ CONTACTS : "has"
    CUSTOMERS ||--o{ DEALS : "generates"
    CUSTOMERS ||--o{ QUOTATIONS : "billed_to"
    DEALS ||--o{ QUOTATIONS : "originates"
    QUOTATIONS ||--o{ QUOTATION_ITEMS : "contains"
```

---

## 4. Domain 3: การบริหารโครงการและงานส่งมอบ (Projects & Delivery)

ครอบคลุมโครงการที่เกิดจาก Deal Won หรือสร้างโดยตรง, งวดงาน (Milestones), งานย่อย (Tasks), Checklists, และข้อคิดเห็น (Comments)

```mermaid
erDiagram
    PROJECTS {
        uuid id PK
        uuid org_id FK
        char project_code "รหัสโปรเจกต์ 6 หลัก"
        varchar name "ชื่อโครงการ"
        uuid customer_id FK "ลูกค้าเจ้าของงาน"
        uuid deal_id FK "อ้างอิงดีลต้นทาง (Nullable)"
        uuid manager_id FK "ผู้จัดการโครงการ (users.id)"
        varchar status "planning, in_progress, on_hold, completed, cancelled"
        date start_date "วันที่เริ่มโครงการ"
        date target_end_date "วันที่กำหนดเสร็จ"
        date actual_end_date "วันที่ส่งมอบจริง"
        decimal budget_amount "งบประมาณโครงการ DECIMAL(18,2)"
    }

    MILESTONES {
        uuid id PK
        uuid org_id FK
        uuid project_id FK "สังกัดโครงการ"
        char milestone_code "รหัสงวดงาน 6 หลัก"
        varchar name "ชื่องวดงาน"
        date due_date "กำหนดส่งมอบงวด"
        varchar status "pending, in_progress, ready_for_review, approved, cancelled"
        decimal billing_amount "ยอดเงินประจำงวด DECIMAL(18,2)"
        boolean invoice_generated "ออกบิลแล้วหรือยัง"
    }

    TASKS {
        uuid id PK
        uuid org_id FK
        uuid project_id FK "สังกัดโครงการ"
        uuid milestone_id FK "สังกัดงวดงาน (Nullable)"
        uuid assignee_id FK "ผู้รับผิดชอบ (users.id)"
        varchar title "ชื่องาน"
        text description "รายละเอียดงาน"
        varchar status "todo, in_progress, blocked, done, cancelled"
        varchar priority "low, medium, high, urgent"
        text blocker_reason "สาเหตุที่งานติดขัด (เมื่อ status = blocked)"
        date due_date "วันกำหนดส่งงาน"
        timestamp completed_at "เวลาที่งานเสร็จจริง"
    }

    TASK_CHECKLISTS {
        uuid id PK
        uuid task_id FK "สังกัดงานย่อย"
        varchar item_text "ข้อความเช็คลิสต์"
        boolean is_completed "ทำเสร็จแล้วหรือยัง"
    }

    TASK_COMMENTS {
        uuid id PK
        uuid task_id FK "สังกัดงานย่อย"
        uuid user_id FK "ผู้แสดงความคิดเห็น"
        text comment "ข้อความคอมเมนต์"
        timestamp created_at
    }

    PROJECTS ||--o{ MILESTONES : "has"
    PROJECTS ||--o{ TASKS : "contains"
    MILESTONES ||--o{ TASKS : "groups"
    TASKS ||--o{ TASK_CHECKLISTS : "has"
    TASKS ||--o{ TASK_COMMENTS : "has"
```

---

## 5. Domain 4: การเงิน ใบแจ้งหนี้ รับชำระ และการจัดซื้อ (Finance, Billing & Procurement)

ครอบคลุมแคตตาล็อกสินค้า, ใบแจ้งหนี้ (Invoices), การรับเงิน (Payments), ค่าใช้จ่าย (Expenses), คู่ค้า (Suppliers), และใบสั่งซื้อ (Purchase Orders)

```mermaid
erDiagram
    PRODUCTS {
        uuid id PK
        uuid org_id FK
        char product_code "รหัสสินค้า 6 หลัก"
        varchar name "ชื่อสินค้า/บริการ"
        varchar type "product, service"
        decimal unit_price "ราคาขายมาตรฐาน DECIMAL(18,2)"
        decimal cost_price "ราคาทุน DECIMAL(18,2)"
        char currency "สกุลเงิน"
        varchar unit "หน่วยนับ (e.g. ชิ้น, ชม., งวด)"
        boolean is_active
    }

    SUPPLIERS {
        uuid id PK
        uuid org_id FK
        char supplier_code "รหัสคู่ค้า 6 หลัก"
        varchar name "ชื่อบริษัทคู่ค้า/ผู้จำหน่าย"
        varchar tax_id "เลขผู้เสียภาษี"
        varchar phone
        varchar email
    }

    INVOICES {
        uuid id PK
        uuid org_id FK
        char invoice_no "เลขที่ใบแจ้งหนี้ 6 หลัก (Unique ใน Org)"
        uuid customer_id FK "ลูกค้าที่เรียกเก็บเงิน"
        uuid project_id FK "อ้างอิงโปรเจกต์ (Nullable)"
        uuid milestone_id FK "อ้างอิงงวดงาน (Nullable)"
        date issue_date "วันที่ออกบิล"
        date due_date "วันครบกำหนดชำระ"
        varchar status "draft, sent, partially_paid, paid, overdue, void"
        decimal subtotal "ยอดก่อนภาษี DECIMAL(18,2)"
        decimal discount_amount "ส่วนลด"
        decimal tax_amount "ภาษี"
        decimal total_amount "ยอดรวมสุทธิ DECIMAL(18,2)"
        decimal paid_amount "ยอดที่รับชำระแล้ว DECIMAL(18,2)"
    }

    INVOICE_ITEMS {
        uuid id PK
        uuid invoice_id FK
        uuid product_id FK "อ้างอิงสินค้า (Nullable)"
        varchar description "รายละเอียดรายการ"
        decimal quantity "จำนวน DECIMAL(12,4)"
        decimal unit_price "ราคาต่อหน่วย DECIMAL(18,2)"
        decimal amount "ยอดรวมรายการ DECIMAL(18,2)"
    }

    PAYMENTS {
        uuid id PK
        uuid org_id FK
        uuid invoice_id FK "ใบแจ้งหนี้ที่ชำระ"
        decimal amount "จำนวนเงินที่รับชำระ DECIMAL(18,2)"
        char currency "สกุลเงิน"
        date payment_date "วันที่ชำระเงิน"
        varchar payment_method "bank_transfer, credit_card, cash, cheque"
        varchar reference_no "เลขอ้างอิงการโอนเงิน/เช็ค"
        uuid attachment_file_id FK "หลักฐานสลิปโอนเงิน (files.id)"
        text notes "บันทึกช่วยจำ"
    }

    EXPENSES {
        uuid id PK
        uuid org_id FK
        char expense_no "รหัสค่าใช้จ่าย 6 หลัก"
        uuid supplier_id FK "คู่ค้าที่จ่ายเงินให้ (Nullable)"
        uuid project_id FK "คิดเป็นต้นทุนโปรเจกต์ (Nullable)"
        varchar category "หมวดหมู่ค่าใช้จ่าย (travel, software, sub-contract)"
        decimal amount "จำนวนเงินสุทธิ DECIMAL(18,2)"
        date expense_date "วันที่เกิดค่าใช้จ่าย"
        varchar status "draft, pending_approval, approved, paid, rejected"
        uuid receipt_file_id FK "ใบเสร็จรับเงิน (files.id)"
    }

    PURCHASE_ORDERS {
        uuid id PK
        uuid org_id FK
        char po_no "เลขที่ใบสั่งซื้อ 6 หลัก"
        uuid supplier_id FK "คู่ค้าที่สั่งซื้อ"
        uuid project_id FK "ผูกกับโปรเจกต์ (Nullable)"
        date order_date "วันที่สั่งซื้อ"
        varchar status "draft, sent, partially_received, received, cancelled"
        decimal total_amount "ยอดสั่งซื้อรวม DECIMAL(18,2)"
    }

    PURCHASE_ORDER_ITEMS {
        uuid id PK
        uuid purchase_order_id FK
        uuid product_id FK
        varchar description
        decimal quantity "จำนวนที่สั่ง"
        decimal received_quantity "จำนวนที่รับแล้ว"
        decimal unit_price "ราคาซื้อ"
        decimal amount
    }

    INVOICES ||--o{ INVOICE_ITEMS : "contains"
    INVOICES ||--o{ PAYMENTS : "settles"
    SUPPLIERS ||--o{ PURCHASE_ORDERS : "receives"
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : "contains"
    SUPPLIERS ||--o{ EXPENSES : "billed_from"
```

---

## 6. Domain 5: คลังสินค้าและสินค้าคงคลัง (Warehouse & Inventory)

ครอบคลุมคลังสินค้า, สต็อกคงเหลือแยกตามคลัง และประวัติการรับเข้า/ตัดออก (Stock Movement Trail)

```mermaid
erDiagram
    WAREHOUSES {
        uuid id PK
        uuid org_id FK
        char warehouse_code "รหัสคลัง 6 หลัก"
        varchar name "ชื่อคลังสินค้า"
        text address "สถานที่ตั้ง"
        boolean is_active
    }

    STOCK_LEVELS {
        uuid id PK
        uuid org_id FK
        uuid warehouse_id FK "คลังสินค้าที่เก็บ"
        uuid product_id FK "สินค้า"
        decimal current_stock "จำนวนคงเหลือ DECIMAL(12,4)"
        decimal reserved_stock "จำนวนที่จองไว้"
        decimal minimum_alert_level "จุดเตือนสั่งซื้อเพิ่ม"
    }

    STOCK_MOVEMENTS {
        uuid id PK
        uuid org_id FK
        uuid warehouse_id FK
        uuid product_id FK
        varchar movement_type "in_purchase, out_sales, transfer_in, transfer_out, adjustment"
        decimal quantity "จำนวนที่เคลื่อนไหว"
        decimal stock_before "ยอดก่อนทำรายการ"
        decimal stock_after "ยอดหลังทำรายการ"
        varchar reference_type "purchase_order, invoice, manual"
        uuid reference_id "ID เอกสารอ้างอิง"
        uuid created_by FK "ผู้บันทึกรายการ"
        timestamp created_at
    }

    WAREHOUSES ||--o{ STOCK_LEVELS : "holds"
    PRODUCTS ||--o{ STOCK_LEVELS : "tracked_in"
    WAREHOUSES ||--o{ STOCK_MOVEMENTS : "records"
    PRODUCTS ||--o{ STOCK_MOVEMENTS : "moves"
```

---

## 7. Domain 6: Payroll (Phase 16B)

Phase 16B implement payroll บน `users` โดยตรง. Employee master, attendance, leave และ OT เป็น scope อนาคต จึงไม่แสดงเป็นตารางจริงใน ERD นี้.

```mermaid
erDiagram
    EMPLOYEE_PAYROLL_PROFILES {
        uuid id PK
        uuid org_id FK
        uuid user_id FK "UNIQUE per organization"
        decimal monthly_salary
        decimal fixed_allowance
        decimal fixed_deduction
        decimal annual_tax_allowance
        boolean social_security_enabled
        varchar payment_method
        varchar status "active, inactive"
    }
    PAYROLL_TAX_POLICIES {
        uuid id PK
        uuid org_id FK
        date effective_from
        date effective_to
        decimal employment_expense_rate
        decimal employment_expense_cap
        json brackets_json
        varchar source_url
    }
    SOCIAL_SECURITY_POLICIES {
        uuid id PK
        uuid org_id FK
        date effective_from
        date effective_to
        decimal employee_rate
        decimal employer_rate
        decimal wage_ceiling
        varchar source_url
    }
    PAYROLL_RUNS {
        uuid id PK
        uuid org_id FK
        varchar run_no
        date period_start
        date period_end
        date payment_date
        uuid payroll_tax_policy_id FK
        uuid social_security_policy_id FK
        uuid bank_account_id FK
        varchar status "draft, calculated, approved, paid"
        decimal gross_amount
        decimal net_pay_amount
    }
    PAYROLL_ITEMS {
        uuid id PK
        uuid org_id FK
        uuid payroll_run_id FK
        uuid employee_payroll_profile_id FK
        uuid user_id FK
        decimal salary_amount
        decimal allowance_amount
        decimal employee_social_security_amount
        decimal employer_social_security_amount
        decimal withholding_tax_amount
        decimal net_pay_amount
        json calculation_snapshot
    }
    USERS ||--o| EMPLOYEE_PAYROLL_PROFILES : "has"
    PAYROLL_TAX_POLICIES ||--o{ PAYROLL_RUNS : "locked_for"
    SOCIAL_SECURITY_POLICIES ||--o{ PAYROLL_RUNS : "locked_for"
    PAYROLL_RUNS ||--o{ PAYROLL_ITEMS : "contains"
    EMPLOYEE_PAYROLL_PROFILES ||--o{ PAYROLL_ITEMS : "source"
    USERS ||--o{ PAYROLL_ITEMS : "payslip owner"
```

---

## 8. Domain 7: แพลตฟอร์ม บันทึกประวัติ และการเชื่อมต่อระบบ (Platform & Integrations)

ครอบคลุมไฟล์แนบ (Files), การแจ้งเตือน (Notifications), ประวัติการใช้งาน (Audit Logs), กฎอัตโนมัติ (Automation Rules), การ Sync บัญชีภายนอก และ Customer Portal

```mermaid
erDiagram
    FILES {
        uuid id PK
        uuid org_id FK
        varchar entity_type "invoices, payments, expenses, customers"
        uuid entity_id "ID ของข้อมูลที่ไฟล์แนบอยู่"
        varchar original_name "ชื่อไฟล์เดิมที่อัปโหลด"
        varchar storage_path "ที่อยู่ไฟล์ใน Storage (Local / S3)"
        varchar mime_type "image/png, application/pdf"
        bigint file_size "ขนาดไฟล์ (Bytes)"
        uuid uploaded_by FK "ผู้อัปโหลด"
    }

    AUDIT_LOGS {
        uuid id PK
        uuid org_id FK
        uuid user_id FK "ผู้ทำรายการ"
        varchar entity_type "ชื่อตารางข้อมูล"
        uuid entity_id "ID ข้อมูลที่ถูกแก้"
        varchar action "created, updated, deleted, login, export"
        json old_values "ค่าข้อมูลเดิมก่อนแก้ไข"
        json new_values "ค่าข้อมูลใหม่หลังแก้ไข"
        varchar ip_address "IP Address"
        timestamp created_at "บันทึกเวลา (ห้ามแก้ไข)"
    }

    NOTIFICATIONS {
        uuid id PK
        uuid org_id FK
        uuid user_id FK "ผู้รับการแจ้งเตือน"
        varchar type "system_alert, task_assigned, invoice_overdue"
        varchar title "หัวข้อแจ้งเตือน"
        text message "เนื้อหาข้อความ"
        boolean is_read "เปิดอ่านแล้วหรือยัง"
        timestamp read_at
    }

    AUTOMATION_RULES {
        uuid id PK
        uuid org_id FK
        varchar name "ชื่อกฎการทำงานอัตโนมัติ"
        varchar trigger_event "deal.won, invoice.paid, task.blocked"
        json conditions "เงื่อนไข (JSON)"
        json actions "การกระทำ (ส่ง Notify, ออก Task, เรียก Webhook)"
        boolean is_active
    }

    AUTOMATION_LOGS {
        uuid id PK
        uuid org_id FK
        uuid rule_id FK
        varchar status "success, failed"
        text execution_details "ผลลัพธ์การทำงาน"
        timestamp executed_at
    }

    WEBHOOK_EVENTS {
        uuid id PK
        uuid org_id FK
        varchar event_type "invoice.settled, customer.created"
        varchar target_url "URL ปลายทาง"
        json payload_json "ข้อมูลที่ส่งออก"
        varchar status "pending, delivered, failed"
        int attempts "จำนวนครั้งที่พยายามส่ง"
        text last_error
    }

    NUMBER_SEQUENCES {
        uuid id PK
        uuid org_id FK
        uuid branch_id FK "Nullable"
        varchar doc_type "branch, customer, project, invoice, expense, po"
        varchar prefix "คำนำหน้า (e.g. INV, PRJ)"
        int year "ปี พ.ศ./ค.ศ."
        int last_number "ตัวเลขล่าสุด (0 - 999999)"
    }

    SETTINGS {
        uuid id PK
        uuid org_id FK
        varchar key "ชื่อการตั้งค่า (Unique ใน Org)"
        json value_json "ค่าคอนฟิก"
    }

    ACCOUNTING_SYNC_LOGS {
        uuid id PK
        uuid org_id FK
        varchar provider "flowaccount, peak, xero, quickbooks"
        varchar entity_type "customer, invoice, payment, expense"
        uuid entity_id "ID ข้อมูลใน ERP"
        varchar external_id "ID ข้อมูลในโปรแกรมบัญชีภายนอก"
        varchar direction "push, pull"
        varchar status "pending, success, failed"
        text error_message
    }

    PORTAL_USERS {
        uuid id PK
        uuid org_id FK
        uuid customer_id FK "ผูกกับลูกค้า"
        varchar email "อีเมลล็อกอินพอร์ทัล"
        varchar password "รหัสผ่านที่แฮชแล้ว"
        varchar status "active, inactive"
        timestamp last_login_at
    }

    AUTOMATION_RULES ||--o{ AUTOMATION_LOGS : "logs"
```

---

## 9. มาตรฐานและกฎข้อบังคับของฐานข้อมูล (Database Conventions & Strict Rules)

1. **ระบบคีย์หลัก (Primary Keys):**  
   - ทุกตารางใช้ Primary Key ชื่อ `id` เป็นชนิด **Time-Ordered UUID (UUIDv7)** เพื่อประสิทธิภาพในการทำ Indexing และป้องกันการคาดเดา ID ข้อมูล
2. **การแยกข้อมูลตามผู้เช่า (Multi-Tenant Isolation):**  
   - ตารางธุรกิจเกือบทุกตารางมีคอลัมน์ `org_id` (FK &rarr; `organizations.id`) และต้องใส่ `WHERE org_id = ?` ในทุก Query ป้องกันข้อมูลรั่วไหลข้ามบริษัท
3. **ความแม่นยำของตัวเลขการเงิน (Monetary Fields):**  
   - จำนวนเงิน, ยอดรวม, ภาษี, และราคาทุกฟิลด์ถูกกำหนดเป็น `DECIMAL(18,2)` อย่างเข้มงวด ห้ามใช้ Float หรือ Double เพื่อป้องกันปัญหาเศษสตางค์คลาดเคลื่อน
4. **รหัสธุรกิจที่ผู้ใช้มองเห็น (Display Codes):**  
   - รหัสที่แสดงผลบนหน้าจอ เช่น `customer_code`, `project_code`, `invoice_no`, `po_no`, `branch.code` จะใช้เป็น **ข้อความตัวเลข 6 หลัก (Format 000001 - 999999)** ควบคุมการออกเลขด้วยตาราง `number_sequences` (Atomic Lock)
5. **การลบข้อมูลแบบ Soft Delete:**  
   - ตารางธุรกิจหลัก (Customers, Deals, Projects, Tasks, Invoices, Expenses) รองรับคอลัมน์ `deleted_at` เพื่อให้สามารถกู้คืนข้อมูลและตรวจสอบย้อนหลังได้
6. **ประวัติกิจกรรมที่ไม่สามารถแก้ไขได้ (Immutable Audit Trail):**  
   - ตาราง `audit_logs` และ `stock_movements` เป็นแบบ **Append-Only** ไม่อนุญาตให้แก้ไขหรือลบ เพื่อความโปร่งใสสูงสุดของระบบ ERP
