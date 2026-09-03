# ผังแบบจำลองฐานข้อมูลทั้งระบบ (Complete Database ER Diagram — 50+ Tables)

เอกสารนี้รวบรวม **Entity Relationship (ER) Diagram ฉบับสมบูรณ์ของระบบ Company OS / Lightweight ERP ทั้งหมด 50+ ตาราง** อ้างอิงตามโครงสร้างฐานข้อมูลกลาง [`docs/database/DATABASE.md`](file:///c:/LocalDevine/www/ERP/docs/database/DATABASE.md) (Single Source of Truth) ครอบคลุมฟังก์ชันตั้งแต่ Phase 0 ถึง Phase 18 พร้อมคำอธิบายความสัมพันธ์, Primary Keys, Foreign Keys, Constraints, และกฎทางธุรกิจภาษาไทยอย่างละเอียด

---

## 📑 สารบัญโดเมนฐานข้อมูล (Database Domains)

1. [ภาพรวมความสัมพันธ์ระหว่างโดเมนทั้งระบบ (Master System-Wide ERD)](#1-ภาพรวมความสัมพันธ์ระหว่างโดเมนทั้งระบบ-master-system-wide-erd)
2. [Domain 1: โครงสร้างองค์กรและระบบผู้ใช้ (Foundation & IAM)](#2-domain-1-โครงสร้างองค์กรและระบบผู้ใช้-foundation--iam)
3. [Domain 2: ลูกค้าสัมพันธ์และโอกาสการขาย (CRM & Sales)](#3-domain-2-ลูกค้าสัมพันธ์และโอกาสการขาย-crm--sales)
4. [Domain 3: การบริหารโครงการและงานส่งมอบ (Projects & Delivery)](#4-domain-3-การบริหารโครงการและงานส่งมอบ-projects--delivery)
5. [Domain 4: การเงิน ใบแจ้งหนี้ รับชำระ และการจัดซื้อ (Finance, Billing & Procurement)](#5-domain-4-การเงิน-ใบแจ้งหนี้-รับชำระ-และการจัดซื้อ-finance-billing--procurement)
6. [Domain 5: คลังสินค้าและสินค้าคงคลัง (Warehouse & Inventory - Phase 8, 15)](#6-domain-5-คลังสินค้าและสินค้าคงคลัง-warehouse--inventory)
7. [Domain 6: Payroll & สวัสดิการ (Phase 16B)](#7-domain-6-payroll-phase-16b)
8. [Domain 7: สินทรัพย์ถาวรและค่าเสื่อมราคา (Fixed Assets & Depreciation - Phase 13)](#8-domain-7-สินทรัพย์ถาวรและค่าเสื่อมราคา-fixed-assets--depreciation---phase-13)
9. [Domain 8: สกุลเงินและอัตราแลกเปลี่ยน (Multi-Currency & FX - Phase 14)](#9-domain-8-สกุลเงินและอัตราแลกเปลี่ยน-multi-currency--fx---phase-14)
10. [Domain 9: ใบกำกับภาษีอิเล็กทรอนิกส์ (E-Tax & Invoicing - Phase 12)](#10-domain-9-ใบกำกับภาษีอิเล็กทรอนิกส์-e-tax--invoicing---phase-12)
11. [Domain 10: ระบบจัดการเอกสารองค์กร (Enterprise DMS & Retention - Phase 17)](#11-domain-10-ระบบจัดการเอกสารองค์กร-enterprise-dms--retention---phase-17)
12. [Domain 11: ความปลอดภัยและการยืนยันตัวตน 2FA (Security & Two-Factor - Phase 18)](#12-domain-11-ความปลอดภัยและการยืนยันตัวตน-2fa-security--two-factor---phase-18)
13. [Domain 12: แพลตฟอร์ม บันทึกประวัติ และการเชื่อมต่อระบบ (Platform & Integrations)](#13-domain-12-แพลตฟอร์ม-บันทึกประวัติ-และการเชื่อมต่อระบบ-platform--integrations)
14. [มาตรฐานและกฎข้อบังคับของฐานข้อมูล (Database Conventions & Strict Rules)](#14-มาตรฐานและกฎข้อบังคับของฐานข้อมูล-database-conventions--strict-rules)

---

## 1. ภาพรวมความสัมพันธ์ระหว่างโดเมนทั้งระบบ (Master System-Wide ERD)

ผังแสดงความสัมพันธ์ระดับแกนกลาง (Core Entities) ระหว่าง 12 โดเมนหลักในระบบ

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
    ORGANIZATIONS ||--o{ FIXED_ASSETS : "1:N ทะเบียนสินทรัพย์"
    ORGANIZATIONS ||--o{ DOCUMENTS : "1:N เอกสารองค์กร"

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
    INVOICES ||--o| ETAX_DOCUMENTS : "1:0..1 ใบกำกับภาษี e-Tax"
    PRODUCTS ||--o{ INVOICE_ITEMS : "1:N อ้างอิงราคาขาย"
    
    SUPPLIERS ||--o{ PURCHASE_ORDERS : "1:N สั่งซื้อสินค้า"
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : "1:N รายการสั่งซื้อ"
    PRODUCTS ||--o{ PURCHASE_ORDER_ITEMS : "1:N สินค้าที่สั่งซื้อ"
    WAREHOUSES ||--o{ WAREHOUSE_BINS : "1:N ตำแหน่งจัดเก็บ"
    WAREHOUSES ||--o{ STOCK_MOVEMENTS : "1:N ความเคลื่อนไหวสต็อก"
    PRODUCTS ||--o{ INVENTORY_LOTS : "1:N รุ่นผลิต/วันหมดอายุ"

    USERS ||--o| EMPLOYEE_PAYROLL_PROFILES : "0..1:1 profile"
    PAYROLL_TAX_POLICIES ||--o{ PAYROLL_RUNS : "locked policy"
    SOCIAL_SECURITY_POLICIES ||--o{ PAYROLL_RUNS : "locked policy"
    PAYROLL_RUNS ||--o{ PAYROLL_ITEMS : "1:N calculation"
    USERS ||--o{ PAYROLL_ITEMS : "receives payslip"
    USERS ||--o{ TWO_FACTOR_TRUSTED_DEVICES : "1:N trusted devices"
    DOCUMENTS ||--o{ DOCUMENT_VERSIONS : "1:N version history"
    DOCUMENTS ||--o{ DOCUMENT_LINKS : "1:N polymorphic links"
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
        text two_factor_secret "เข้ารหัส AES-256 (Nullable)"
        text two_factor_recovery_codes "เข้ารหัส JSON Single-use codes"
        timestamp two_factor_confirmed_at "วันเวลาที่ยืนยันเปิดใช้งาน 2FA"
    }

    TWO_FACTOR_TRUSTED_DEVICES {
        uuid id PK
        uuid org_id FK
        uuid user_id FK "อ้างอิงผู้ใช้งาน"
        varchar device_name "ชื่ออุปกรณ์ที่เชื่อถือ"
        varchar device_token_hash "แฮชโทเค็นอุปกรณ์ 30 วัน"
        varchar ip_address "IP Address ล่าสุด"
        text user_agent "เบราว์เซอร์และระบบปฏิบัติการ"
        timestamp last_used_at "ใช้งานล่าสุด"
        timestamp expires_at "วันหมดอายุ (30 วัน)"
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
    USERS ||--o{ TWO_FACTOR_TRUSTED_DEVICES : "trusts"
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

## 6. Domain 5: คลังสินค้าและสินค้าคงคลัง (Warehouse & Inventory - Phase 8, 15)

ครอบคลุมคลังสินค้า, ตำแหน่งจัดเก็บย่อย (Bin Locations), การโอนย้ายสต็อก (Stock Transfers), การควบคุมรุ่นผลิตและวันหมดอายุ (Inventory Lots), และประวัติการเคลื่อนไหวสต็อก (Stock Movement Ledger)

```mermaid
erDiagram
    WAREHOUSES {
        uuid id PK
        uuid org_id FK
        uuid branch_id FK "สาขาที่ตั้ง"
        char warehouse_code "รหัสคลัง 6 หลัก"
        varchar name "ชื่อคลังสินค้า"
        text address "สถานที่ตั้ง"
        boolean is_active
    }

    WAREHOUSE_BINS {
        uuid id PK
        uuid org_id FK
        uuid warehouse_id FK "คลังสินค้า"
        char code "รหัส Bin"
        varchar name "ชื่อตำแหน่งเก็บ"
        varchar aisle "แถว"
        varchar rack "ชั้นวาง"
        varchar shelf "ระดับชั้น"
        varchar bin "ช่องเก็บ"
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

    INVENTORY_LOTS {
        uuid id PK
        uuid org_id FK
        uuid product_id FK "สินค้า"
        uuid warehouse_id FK "คลังสินค้า"
        uuid warehouse_bin_id FK "ตำแหน่งจัดเก็บ"
        varchar lot_number "หมายเลข Lot"
        varchar batch_number "หมายเลข Batch"
        date manufactured_date "วันที่ผลิต"
        date expiry_date "วันหมดอายุ"
        decimal quantity_received "ยอดรับเข้า"
        decimal quantity_remaining "ยอดคงเหลือ"
        decimal cost_price "ต้นทุนต่อหน่วย"
        varchar status "active, expired, quarantined, exhausted"
    }

    STOCK_TRANSFERS {
        uuid id PK
        uuid org_id FK
        varchar transfer_no "เลขที่ใบโอนย้าย TRF-YYYYMM-XXXX"
        uuid source_warehouse_id FK "คลังต้นทาง"
        uuid dest_warehouse_id FK "คลังปลายทาง"
        uuid source_bin_id FK "Bin ต้นทาง"
        uuid dest_bin_id FK "Bin ปลายทาง"
        uuid product_id FK "สินค้าที่โอน"
        uuid inventory_lot_id FK "Lot สินค้า"
        decimal quantity "จำนวนที่โอน"
        varchar status "draft, in_transit, completed, cancelled"
        date transfer_date "วันที่โอน"
        text notes
        uuid created_by FK
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
        varchar reference_type "purchase_order, invoice, manual, stock_transfer"
        uuid reference_id "ID เอกสารอ้างอิง"
        uuid created_by FK "ผู้บันทึกรายการ"
        timestamp created_at
    }

    WAREHOUSES ||--o{ WAREHOUSE_BINS : "locates"
    WAREHOUSES ||--o{ STOCK_LEVELS : "holds"
    PRODUCTS ||--o{ STOCK_LEVELS : "tracked_in"
    PRODUCTS ||--o{ INVENTORY_LOTS : "batches"
    WAREHOUSES ||--o{ STOCK_MOVEMENTS : "records"
    PRODUCTS ||--o{ STOCK_MOVEMENTS : "moves"
    WAREHOUSES ||--o{ STOCK_TRANSFERS : "transfers_out"
    WAREHOUSES ||--o{ STOCK_TRANSFERS : "transfers_in"
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

## 8. Domain 7: สินทรัพย์ถาวรและค่าเสื่อมราคา (Fixed Assets & Depreciation - Phase 13)

ครอบคลุมหมวดหมู่สินทรัพย์, ทะเบียนสินทรัพย์ถาวร, การคำนวณค่าเสื่อมราคารายเดือนเส้นตรง (Straight-Line), การบันทึกลงบัญชีแยกประเภททั่วไป (General Ledger) และการจำหน่าย/ตัดจำหน่ายสินทรัพย์

```mermaid
erDiagram
    ASSET_CATEGORIES {
        uuid id PK
        uuid org_id FK
        char code "รหัสหมวดสินทรัพย์"
        varchar name "ชื่อหมวดสินทรัพย์"
        varchar depreciation_method "straight_line"
        int useful_life_years "อายุการใช้งาน (ปี)"
        decimal salvage_value_percent "มูลค่าซาก (%)"
        uuid asset_account_id FK "ผังบัญชีราคาทุนสินทรัพย์"
        uuid accum_deprec_account_id FK "ผังบัญชีค่าเสื่อมราคาสะสม"
        uuid deprec_expense_account_id FK "ผังบัญชีค่าใช้จ่ายค่าเสื่อมราคา"
        boolean is_active
    }

    FIXED_ASSETS {
        uuid id PK
        uuid org_id FK
        uuid category_id FK "หมวดหมู่สินทรัพย์"
        varchar asset_number "รหัสสินทรัพย์ FA-YYYYMM-XXXX"
        varchar name "ชื่อสินทรัพย์"
        text description
        date acquisition_date "วันที่ได้มา"
        decimal acquisition_cost "ราคาทุนที่ได้มา"
        decimal salvage_value "มูลค่าซาก"
        int useful_life_months "อายุการใช้งาน (เดือน)"
        varchar status "active, disposed, written_off"
        varchar location "สถานที่ติดตั้ง/ใช้งาน"
        uuid custodian_user_id FK "ผู้ครอบครอง/ดูแล"
        uuid expense_id FK "อ้างอิง Expense ตั้งเบิก (Nullable)"
        uuid goods_receipt_id FK "อ้างอิงใบรับสินค้า GRN (Nullable)"
        date disposal_date "วันที่จำหน่าย"
        decimal disposal_price "ราคาขายจำหน่าย"
        decimal disposal_gain_loss "กำไร/ขาดทุนจากการจำหน่าย"
    }

    ASSET_DEPRECIATIONS {
        uuid id PK
        uuid org_id FK
        uuid fixed_asset_id FK "สินทรัพย์"
        uuid accounting_period_id FK "งวดบัญชี"
        date depreciation_date "วันที่คำนวณค่าเสื่อม"
        varchar period_key "YYYY-MM"
        decimal depreciation_amount "ค่าเสื่อมงวดนี้"
        decimal accumulated_amount "ค่าเสื่อมสะสม"
        decimal book_value "มูลค่าตามบัญชีสุทธิ"
        uuid journal_entry_id FK "สมุดรายวัน GL (Idempotent Posting)"
    }

    ASSET_CATEGORIES ||--o{ FIXED_ASSETS : "classifies"
    FIXED_ASSETS ||--o{ ASSET_DEPRECIATIONS : "depreciates"
```

---

## 9. Domain 8: สกุลเงินและอัตราแลกเปลี่ยน (Multi-Currency & FX - Phase 14)

ครอบคลุมมาสเตอร์สกุลเงิน, อัตราแลกเปลี่ยนย้อนหลังตามวันทำรายการ, Snapshot อัตราแลกเปลี่ยนในเอกสาร และการประเมินมูลค่าย้อนหลังสิ้นงวด (FX Revaluation)

```mermaid
erDiagram
    CURRENCIES {
        char code PK "THB, USD, EUR, JPY"
        varchar name "ชื่อสกุลเงิน"
        varchar symbol "สัญลักษณ์ $, ฿, €"
        int decimal_places "ทศนิยม (ค่าเริ่มต้น 2)"
        boolean is_active
    }

    ORGANIZATION_CURRENCIES {
        uuid id PK
        uuid org_id FK
        char currency_code FK "รหัสสกุลเงิน"
        boolean is_base "เป็นสกุลเงินหลักองค์กรหรือไม่"
        boolean is_active
    }

    EXCHANGE_RATES {
        uuid id PK
        uuid org_id FK
        char from_currency "สกุลเงินต้นทาง"
        char to_currency "สกุลเงินปลายทาง (Base Currency)"
        date rate_date "วันที่ของอัตราแลกเปลี่ยน"
        decimal rate "อัตราแลกเปลี่ยน DECIMAL(18,6)"
        varchar source "manual, bot_api, custom"
    }

    FX_REVALUATIONS {
        uuid id PK
        uuid org_id FK
        uuid accounting_period_id FK "งวดบัญชีสิ้นงวด"
        date revaluation_date "วันประเมินราคา"
        varchar period_key "YYYY-MM"
        uuid account_id FK "บัญชีลูกหนี้/เงินฝากต่างประเทศ"
        varchar entity_type "invoices, bank_accounts"
        uuid entity_id "ID รายการ"
        char currency_code "สกุลเงินต่างประเทศ"
        decimal foreign_amount "ยอดเงินตราต่างประเทศ"
        decimal book_base_amount "มูลค่าตามบัญชีเดิม"
        decimal revalued_base_amount "มูลค่าใหม่ตามอัตราสิ้นงวด"
        decimal unrealized_gain_loss "กำไร/ขาดทุนที่ยังไม่เกิดขึ้น"
        uuid journal_entry_id FK "Journal GL สิ้นงวด"
        uuid reversal_journal_entry_id FK "Journal GL กลับรายการต้นงวดถัดไป"
    }

    CURRENCIES ||--o{ ORGANIZATION_CURRENCIES : "configures"
    ORGANIZATIONS ||--o{ EXCHANGE_RATES : "records"
    ORGANIZATIONS ||--o{ FX_REVALUATIONS : "revalues"
```

---

## 10. Domain 9: ใบกำกับภาษีอิเล็กทรอนิกส์ (E-Tax & Invoicing - Phase 12)

ครอบคลุมการออกใบกำกับภาษีและใบเสร็จอิเล็กทรอนิกส์ e-Tax Invoice / Receipt ตามมาตรฐานขมธอ. (ETDA), XML Generation, การจัดเก็บ Private Storage, SHA256 Integrity Hash, และการส่งออกข้อมูล RD Prep สำหรับยื่นกรมสรรพากร

```mermaid
erDiagram
    ETAX_CONFIGS {
        uuid id PK
        uuid org_id FK
        varchar provider "inet, direct_rd, mock"
        varchar mode "disabled, staging, live"
        varchar signer_certificate_ref "เลขอ้างอิงใบรับรองดิจิทัล"
        varchar api_endpoint "URL บริการ Service Provider"
        text api_key_encrypted "กุญแจเชื่อมต่อ API เข้ารหัส"
        boolean is_active
    }

    ETAX_DOCUMENTS {
        uuid id PK
        uuid org_id FK
        uuid invoice_id FK "อ้างอิงใบแจ้งหนี้"
        varchar doc_type "tax_invoice, receipt, credit_note, debit_note"
        varchar document_number "เลขที่เอกสาร e-Tax"
        varchar xml_storage_key "คีย์ไฟล์ XML ใน Private Storage"
        varchar xml_hash_sha256 "SHA256 แฮชตรวจสอบความถูกต้อง"
        varchar status "draft, signed, submitted, accepted, rejected"
        timestamp submitted_at
    }

    ETAX_SUBMISSION_ATTEMPTS {
        uuid id PK
        uuid org_id FK
        uuid etax_document_id FK "เอกสาร e-Tax"
        int attempt_number "ครั้งที่พยายามส่ง"
        varchar response_status "HTTP / API Status"
        text response_payload "ข้อความตอบกลับ"
        text error_message "ข้อผิดพลาด (ถ้ามี)"
        timestamp attempted_at
    }

    ETAX_CONFIGS ||--o{ ETAX_DOCUMENTS : "governs"
    ETAX_DOCUMENTS ||--o{ ETAX_SUBMISSION_ATTEMPTS : "tracks"
```

---

## 11. Domain 10: ระบบจัดการเอกสารองค์กร (Enterprise DMS & Retention - Phase 17)

ครอบคลุมระบบคลังเอกสารอิเล็กทรอนิกส์ (DMS), การแบ่งหมวดหมู่, การควบคุมระดับชั้นความลับ (Sensitivity RBAC), ประวัติเวอร์ชันเอกสารพร้อม SHA256 Checksum, การเชื่อมโยงแบบ Polymorphic กับเอกสารธุรกิจ, และนโยบายการจัดเก็บ/ทำลาย (Retention Policy) พร้อม Legal Hold

```mermaid
erDiagram
    DOCUMENT_CATEGORIES {
        uuid id PK
        uuid org_id FK
        varchar name "ชื่อหมวดหมู่เอกสาร (e.g. สัญญา, ใบรับรอง, ภาษี)"
        varchar code "รหัสหมวดหมู่"
        text description
        boolean is_active
    }

    RETENTION_POLICIES {
        uuid id PK
        uuid org_id FK
        varchar name "ชื่อนโยบายการเก็บรักษา"
        int retention_period_days "ระยะเวลาเก็บรักษา (วัน)"
        varchar action "archive, purge"
        text description
        boolean is_active
    }

    DOCUMENTS {
        uuid id PK
        uuid org_id FK
        uuid category_id FK "หมวดหมู่เอกสาร"
        uuid retention_policy_id FK "นโยบายเก็บรักษา"
        uuid owner_user_id FK "ผู้รับผิดชอบเอกสาร"
        varchar document_no "เลขที่เอกสาร DOC-YYYYMM-XXXX"
        varchar title "ชื่อหัวข้อเอกสาร"
        varchar sensitivity "public, internal, confidential, restricted"
        varchar status "active, archived, expired"
        date expires_at "วันหมดอายุเอกสาร (Nullable)"
        int renewal_alert_days "แจ้งเตือนล่วงหน้า (วัน)"
        boolean legal_hold "ระงับการทำลายตามกฎหมาย"
        timestamp retention_until "เก็บรักษาถึงวันเวลา"
        uuid current_version_id FK "เวอร์ชันปัจจุบัน"
    }

    DOCUMENT_VERSIONS {
        uuid id PK
        uuid org_id FK
        uuid document_id FK "เอกสารหลัก"
        int version_no "หมายเลขเวอร์ชัน (1, 2, 3...)"
        varchar storage_key "ตำแหน่งเก็บใน Private Storage"
        varchar original_name "ชื่อไฟล์ต้นฉบับ"
        varchar mime_type "image/jpeg, application/pdf"
        bigint size_bytes "ขนาดไฟล์"
        varchar checksum_sha256 "SHA256 แฮชตรวจสอบความถูกต้อง"
        varchar scan_status "pending, clean, infected"
        text change_note "บันทึกการแก้ไขในเวอร์ชัน"
        uuid uploaded_by FK "ผู้อัปโหลด"
    }

    DOCUMENT_LINKS {
        uuid id PK
        uuid org_id FK
        uuid document_id FK "เอกสาร"
        varchar documentable_type "invoices, expenses, fixed_assets, users"
        uuid documentable_id "ID ข้อมูลเป้าหมายที่ผูก"
        varchar link_type "attachment, contract, reference, proof"
        uuid created_by FK
    }

    DOCUMENT_CATEGORIES ||--o{ DOCUMENTS : "classifies"
    RETENTION_POLICIES ||--o{ DOCUMENTS : "applies"
    DOCUMENTS ||--o{ DOCUMENT_VERSIONS : "versions"
    DOCUMENTS ||--o{ DOCUMENT_LINKS : "links_to"
```

---

## 12. Domain 11: ความปลอดภัยและการยืนยันตัวตน 2FA (Security & Two-Factor - Phase 18)

ครอบคลุมความปลอดภัยการเข้าสู่ระบบแบบยืนยันตัวตนสองขั้นตอน (TOTP RFC 6238), การเข้ารหัส Secret Key (AES-256-GCM), รหัสกู้คืนฉุกเฉินครั้งเดียว (Single-Use Recovery Codes), และทะเบียนอุปกรณ์ที่เชื่อถือได้ (Trusted Devices 30 วัน)

```mermaid
erDiagram
    TWO_FACTOR_TRUSTED_DEVICES {
        uuid id PK
        uuid org_id FK
        uuid user_id FK "อ้างอิงผู้ใช้งาน"
        varchar device_name "ชื่ออุปกรณ์ e.g. Chrome on Windows"
        varchar device_token_hash "แฮชโทเค็นอุปกรณ์ 30 วัน (SHA256)"
        varchar ip_address "IP Address ล่าสุด"
        text user_agent "เบราว์เซอร์และระบบปฏิบัติการ"
        timestamp last_used_at "ใช้งานล่าสุด"
        timestamp expires_at "วันหมดอายุโทเค็น (30 วัน)"
    }

    USERS ||--o{ TWO_FACTOR_TRUSTED_DEVICES : "trusts"
```

---

## 13. Domain 12: แพลตฟอร์ม บันทึกประวัติ และการเชื่อมต่อระบบ (Platform & Integrations)

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

## 14. มาตรฐานและกฎข้อบังคับของฐานข้อมูล (Database Conventions & Strict Rules)

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
