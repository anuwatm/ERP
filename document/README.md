# Company OS / Lightweight ERP — System Diagrams & Architecture

เอกสารนี้รวบรวม **Diagram ทั้งหมดของระบบ** ครอบคลุมทั้งระดับสถาปัตยกรรม (Architecture), เวิร์กโฟลว์ธุรกิจ (Workflow), ลำดับการทำงาน (Sequence), การไหลของข้อมูล (Dataflow), และวงจรสถานะ (Lifecycle) โดยถูกสร้างขึ้นด้วย **Archify Showcase Standard** และบันทึกเป็น Interactive Standalone HTML พร้อม JSON Spec

🌐 **หน้าเมนูรวมไดอะแกรมทั้งหมด:** เปิดไฟล์ [`document/index.html`](./index.html) ในเบราว์เซอร์

---

## 📑 สารบัญไดอะแกรมทั้งหมด (Table of Diagrams)

| # | ชื่อไดอะแกรม | ประเภท | ไฟล์ Interactive HTML | ไฟล์ JSON Spec |
|---|---|---|---|---|
| **01** | [System & Tech Stack Architecture](#01-system--tech-stack-architecture) | Architecture | [`01_system_architecture.html`](./01_system_architecture.html) | [`specs/01_system_architecture.json`](./specs/01_system_architecture.json) |
| **02** | [Multi-Tenancy & Org Hierarchy](#02-multi-tenancy--org-hierarchy) | Architecture | [`02_multitenancy_org_hierarchy.html`](./02_multitenancy_org_hierarchy.html) | [`specs/02_multitenancy_org_hierarchy.json`](./specs/02_multitenancy_org_hierarchy.json) |
| **03** | [Core Business Workflow](#03-core-business-workflow) | Workflow | [`03_business_core_workflow.html`](./03_business_core_workflow.html) | [`specs/03_business_core_workflow.json`](./specs/03_business_core_workflow.json) |
| **04** | [Auth, Session & RBAC Flow](#04-auth-session--rbac-flow) | Sequence | [`04_auth_session_rbac_sequence.html`](./04_auth_session_rbac_sequence.html) | [`specs/04_auth_session_rbac_sequence.json`](./specs/04_auth_session_rbac_sequence.json) |
| **05** | [Invoice Creation & Payment Settlement](#05-invoice-creation--payment-settlement) | Sequence | [`05_invoice_creation_payment_sequence.html`](./05_invoice_creation_payment_sequence.html) | [`specs/05_invoice_creation_payment_sequence.json`](./specs/05_invoice_creation_payment_sequence.json) |
| **06** | [ERP Data Lineage & Pipeline](#06-erp-data-lineage--pipeline) | Dataflow | [`06_erp_data_lineage_flow.html`](./06_erp_data_lineage_flow.html) | [`specs/06_erp_data_lineage_flow.json`](./specs/06_erp_data_lineage_flow.json) |
| **07** | [Sales Opportunity & Deal Lifecycle](#07-sales-opportunity--deal-lifecycle) | Lifecycle | [`07_deal_sales_lifecycle.html`](./07_deal_sales_lifecycle.html) | [`specs/07_deal_sales_lifecycle.json`](./specs/07_deal_sales_lifecycle.json) |
| **08** | [Invoice Status & Payment Lifecycle](#08-invoice-status--payment-lifecycle) | Lifecycle | [`08_invoice_lifecycle.html`](./08_invoice_lifecycle.html) | [`specs/08_invoice_lifecycle.json`](./specs/08_invoice_lifecycle.json) |
| **09** | [Task Status Lifecycle](#09-task-status-lifecycle) | Lifecycle | [`09_task_project_lifecycle.html`](./09_task_project_lifecycle.html) | [`specs/09_task_project_lifecycle.json`](./specs/09_task_project_lifecycle.json) |
| **10** | [Database ER & Domain Model](#10-database-er--domain-model) | Architecture | [`10_database_er_domain_model.html`](./10_database_er_domain_model.html) | [`specs/10_database_er_domain_model.json`](./specs/10_database_er_domain_model.json) |
| **11** | [Payroll Policy, Approval & GL Flow](#11-payroll-policy-approval--gl-flow) | Workflow | [`11_payroll_policy_gl_flow.html`](./11_payroll_policy_gl_flow.html) | [`specs/11_payroll_policy_gl_flow.json`](./specs/11_payroll_policy_gl_flow.json) |
| **12** | [Enterprise Document Lifecycle & Retention](#12-enterprise-document-lifecycle--retention-governance) | Lifecycle | [`12_document_management_lifecycle.html`](./12_document_management_lifecycle.html) | [`specs/12_document_management_lifecycle.json`](./specs/12_document_management_lifecycle.json) |
| **13** | [Two-Factor Authentication & Privileged Access Flow](#13-two-factor-authentication--privileged-access-flow) | Sequence | [`13_two_factor_auth_sequence.html`](./13_two_factor_auth_sequence.html) | [`specs/13_two_factor_auth_sequence.json`](./specs/13_two_factor_auth_sequence.json) |
| **All** | [Full Database ER Diagram (50+ Tables)](#14-full-database-er-diagram-50-ตาราง) | Database ERD | [`document/DATABASE_ERD.md`](./DATABASE_ERD.md) | [Central Database Schema](../docs/database/DATABASE.md) |

---

## 🧩 ไดอะแกรมรายกลุ่มการทำงาน 6 กลุ่ม (31 Modules Workflow)

นอกจากภาพรวมสถาปัตยกรรมระดับระบบ 13 ชุดด้านบนแล้ว ยังมีเอกสารผังการทำงานเจาะลึกเฉพาะ **6 กลุ่มงานหลัก ครอบคลุม 31 โมดูลทั้งหมด** พร้อมคำอธิบายภาษาไทยและ Mermaid Diagrams รวม 19 ชุด:

📄 **ดูรายละเอียดฉบับเต็ม:** [`document/GROUPS_WORKFLOW.md`](./GROUPS_WORKFLOW.md)

| กลุ่มงาน (Domain Group) | โมดูลที่ครอบคลุม | หัวข้อไดอะแกรมหลัก |
|---|---|---|
| **Group 1: Foundation & Security** | `01-04` (Org, User/Role, Settings, Audit) | • Multi-Tenant Scope Flow<br>• RBAC 7 Roles Policy Gate<br>• Audit Trail Pipeline |
| **Group 2: CRM & Sales Pipeline** | `05-08` (Customers, Contacts, Deals, Quotations) | • Lead-to-Quote Workflow<br>• Deal Stage State Machine<br>• Quotation Issuance Sequence |
| **Group 3: Project Delivery & Execution** | `09-11` (Projects, Tasks, Milestones) | • Project Delivery Workflow<br>• Task Kanban Lifecycle<br>• Milestone Sign-off Trigger |
| **Group 4: Finance, Billing & Cost Control** | `12-16` (Products, Suppliers, Invoices, Payments, Expenses) | • Order-to-Cash & Expenses<br>• Invoice Anti-Overpay Sequence<br>• Project Cost Allocation |
| **Group 5: Insights, Platform & Automation** | `17-23` (Dashboard, Reports, Files, Notifications, Automation, API, Import/Export) | • Event & Automation Engine<br>• Executive Metric Lineage<br>• Tenant File Storage Flow |
| **Group 6: Operations & Advanced Extensions (Phase 7-18)** | `24-31` (PO, Inventory, Payroll, AI, Accounting, Portal) | • Procurement & Stock Movement<br>• Payroll Policy, Payslip & GL<br>• External Accounting & Portal Grid |

---

## รายละเอียดแต่ละไดอะแกรม (Diagram Details)

### 01. System & Tech Stack Architecture
- **ไฟล์ HTML:** [`01_system_architecture.html`](./01_system_architecture.html)
- **วัตถุประสงค์:** แสดง Full-Stack Monolith: React + Inertia + Vite + Tailwind CSS ทำงานกับ Laravel ผ่าน Inertia Protocol พร้อม database session, rate limiter, audit log และฐานข้อมูล MySQL/MariaDB
- **Mermaid Preview:**

```mermaid
flowchart LR
    Client["Browser / Mobile Client"] -->|HTTPS| ReactInertia["React 18 + Inertia UI"]
    ReactInertia -->|Login| BreezeAuth["Breeze Local Auth"]
    BreezeAuth -->|Create session| SessionStore["Laravel Database Session"]
    ReactInertia -->|Inertia XHR| LaravelApp["Laravel 13 Core (PHP 8.3)"]
    LaravelApp -->|Read session| SessionStore
    LaravelApp -->|Eloquent ORM| MariaDB[("MySQL / MariaDB (Ordered UUID)")]
    LaravelApp -->|Attachments| FileStorage["File Storage"]
    LaravelApp -->|Audit Trail| AuditLog[("Application Audit Logs")]
```

---

### 02. Multi-Tenancy & Org Hierarchy
- **ไฟล์ HTML:** [`02_multitenancy_org_hierarchy.html`](./02_multitenancy_org_hierarchy.html)
- **วัตถุประสงค์:** แสดงลำดับชั้นขององค์กรและการแบ่งสิทธิ์ (Tenant Isolation ผ่าน `org_id` และ 7 Built-in Roles: `owner`, `admin`, `sales`, `project_manager`, `finance`, `member`, `viewer`)
- **Mermaid Preview:**

```mermaid
flowchart TD
    Org["Organization (org_id - Root Tenant)"] --> Branch["Branch (รหัส 6 หลัก e.g. 000001)"]
    Branch --> Division["Division (สายงาน)"]
    Division --> Department["Department (ฝ่าย/แผนก)"]
    Department --> User["User (1 User = 1 Org)"]
    
    Org -.->|Constrain org_id| OrgScope["Controller / Service Query Guard"]
    OrgScope --> TenantDB[("Tenant Tables (WHERE org_id)")]
    
    User --> RBACGate["RBAC Policy Gate"]
    RBACGate --> Roles["7 Built-in Roles"]
    RBACGate --> Perms["module.action Permissions"]
```

---

### 03. Core Business Workflow
- **ไฟล์ HTML:** [`03_business_core_workflow.html`](./03_business_core_workflow.html)
- **วัตถุประสงค์:** เวิร์กโฟลว์แกนธุรกิจหลัก ตั้งแต่รับลูกค้า &rarr; ดีลการขาย &rarr; ส่งมอบโปรเจกต์ &rarr; ออกใบแจ้งหนี้ &rarr; รับเงิน &rarr; รายงาน Dashboard
- **Mermaid Preview:**

```mermaid
flowchart LR
    subgraph Sales ["Sales & CRM"]
        Lead["Capture Lead"] --> Deal["Qualify Deal"] --> Won["Win Deal"]
        Deal -.-> Lost["Deal Lost"]
    end

    subgraph Delivery ["Project Delivery"]
        Won --> Project["Init Project"] --> Tasks["Deliver Tasks"]
    end

    subgraph Finance ["Finance & Billing"]
        Project -.->|optional project reference| Invoice["Issue Invoice (DECIMAL 18,2)"] --> Receipt["Receipt Payment (Anti-Overpay)"]
        Invoice -.-> Void["Void when no payment collected"]
    end

    subgraph Management ["Executive"]
        Receipt --> KPI["Executive KPIs (Invoiced vs Real Cash)"]
    end
```

---

### 04. Auth, Session & RBAC Flow
- **ไฟล์ HTML:** [`04_auth_session_rbac_sequence.html`](./04_auth_session_rbac_sequence.html)
- **วัตถุประสงค์:** ลำดับการยืนยันตัวตน, Rate Limit ป้องกันการสุ่มรหัสผ่าน, การสร้าง Session ในฐานข้อมูล และการตรวจสิทธิ์ก่อนส่ง Inertia Props
- **Mermaid Preview:**

```mermaid
sequenceDiagram
    autonumber
    actor User as Browser Client
    participant Router as Laravel Router
    participant Limiter as Rate Limiter
    participant Auth as Auth Service
    participant DB as MariaDB
    participant Session as Session DB
    participant Guard as RBAC Guard

    User->>Router: POST /login (credentials)
    Router->>Limiter: check attempts
    Limiter-->>Router: rate limit OK
    Router->>Auth: authenticate()
    Auth->>DB: find user & password hash
    DB-->>Auth: user record & roles
    Auth->>Session: write database session
    Session-->>Auth: session id
    Auth-->>User: Set-Cookie + 302 Redirect
    
    User->>Router: GET /dashboard (Cookie)
    Router->>Guard: enforce org_id & roles
    Guard-->>Router: authorized
    Router-->>User: Inertia::render('Dashboard', props)
```

---

### 05. Invoice Creation & Payment Settlement
- **ไฟล์ HTML:** [`05_invoice_creation_payment_sequence.html`](./05_invoice_creation_payment_sequence.html)
- **วัตถุประสงค์:** ลำดับการออกบิล, การคำนวณเงินระดับ `DECIMAL(18,2)`, การบันทึกรับเงินพร้อม Anti-Overpayment Guard และ Audit Log
- **Mermaid Preview:**

```mermaid
sequenceDiagram
    autonumber
    actor Finance as Finance User
    participant API as Invoice Controller
    participant Money as Money Guard (DECIMAL 18,2)
    participant PaymentSvc as Payment Service
    participant DB as MariaDB (Atomic Transaction)
    participant Audit as Application Audit Log

    Finance->>API: POST /invoices (items, customer_id)
    API->>Money: validate items & precision
    Money-->>API: valid totals
    API->>DB: INSERT invoice (Status: sent)
    DB-->>Finance: redirect with invoice_no

    Finance->>API: POST /invoices/{id}/payments (amount)
    API->>PaymentSvc: processPayment(amount)
    PaymentSvc->>DB: SELECT sum(amount) for invoice
    DB-->>PaymentSvc: remaining balance
    Note over PaymentSvc: Check: amount <= remaining_balance
    PaymentSvc->>DB: INSERT payment & update balance/status
    PaymentSvc->>Audit: record payment_received event
    Audit-->>PaymentSvc: logged
    PaymentSvc-->>Finance: redirect with receipt result
```

---

### 06. ERP Data Lineage & Pipeline
- **ไฟล์ HTML:** [`06_erp_data_lineage_flow.html`](./06_erp_data_lineage_flow.html)
- **วัตถุประสงค์:** แสดงการเดินทางของข้อมูลใน 5 ลำดับขั้น (Master Data &rarr; Sales Pipeline &rarr; Delivery &rarr; Transactions &rarr; Dashboards)
- **Mermaid Preview:**

```mermaid
flowchart LR
    subgraph S0 ["1. Master Data"]
        Customers["Customers Master"]
        Catalog["Price Book Catalog"]
    end

    subgraph S1 ["2. Sales Pipeline"]
        Deals["Deals Opportunities"]
        Quotes["Quotations"]
    end

    subgraph S2 ["3. Delivery"]
        Projects["Projects & Tasks"]
    end

    subgraph S3 ["4. Transactions"]
        Invoices["Invoices (Billed)"]
        Payments["Payments (Cash In)"]
    end

    subgraph S4 ["5. Dashboards"]
        ExecDash["Executive Dashboard"]
        FinanceDash["Finance Metric"]
    end

    Customers --> Deals
    Catalog --> Quotes
    Deals --> Quotes
    Quotes --> Projects
    Projects -. optional reference .-> Invoices
    Invoices --> Payments
    Invoices --> ExecDash
    Payments --> FinanceDash
    FinanceDash --> ExecDash
```

---

### 07. Sales Opportunity & Deal Lifecycle
- **ไฟล์ HTML:** [`07_deal_sales_lifecycle.html`](./07_deal_sales_lifecycle.html)
- **วัตถุประสงค์:** แสดงสถานะ Deal ที่ระบบรองรับ: `new`, `contacted`, `qualified`, `proposal`, `negotiation`, `won`, `lost`
- **Mermaid Preview:**

```mermaid
stateDiagram-v2
    [*] --> New
    New --> Contacted
    Contacted --> Qualified
    Qualified --> Proposal
    Proposal --> Negotiation
    Negotiation --> Won
    Negotiation --> Lost
    Won --> [*]
    Lost --> [*]
```

---

### 08. Invoice Status & Payment Lifecycle
- **ไฟล์ HTML:** [`08_invoice_lifecycle.html`](./08_invoice_lifecycle.html)
- **วัตถุประสงค์:** แสดงสถานะ Invoice ที่ระบบรองรับ: `draft`, `sent`, `partially_paid`, `paid`, `overdue`, `void`
- **Mermaid Preview:**

```mermaid
stateDiagram-v2
    [*] --> Draft
    Draft --> Sent
    Sent --> PartiallyPaid
    PartiallyPaid --> Paid
    Sent --> Overdue
    Draft --> Void
    Sent --> Void
    Paid --> [*]
    Void --> [*]
```

---

### 09. Task Status Lifecycle
- **ไฟล์ HTML:** [`09_task_project_lifecycle.html`](./09_task_project_lifecycle.html)
- **วัตถุประสงค์:** แสดงสถานะ Task ที่ระบบรองรับ: `todo`, `in_progress`, `blocked`, `done`, `cancelled`
- **Mermaid Preview:**

```mermaid
stateDiagram-v2
    [*] --> Todo
    Todo --> InProgress
    InProgress --> Done
    InProgress --> Blocked
    Blocked --> InProgress
    Todo --> Cancelled
    Done --> [*]
    Cancelled --> [*]
```

---

### 10. Database ER & Domain Model
- **ไฟล์ HTML:** [`10_database_er_domain_model.html`](./10_database_er_domain_model.html)
- **วัตถุประสงค์:** แสดงความสัมพันธ์ Entity หลักของ ERP: identity, CRM, project, finance, treasury และ general ledger โดยใช้ ordered UUID, `org_id` tenant scope และ decimal money fields
- **Mermaid Preview:**

```mermaid
erDiagram
    ORGANIZATIONS ||--o{ BRANCHES : has
    BRANCHES ||--o{ DIVISIONS : has
    DIVISIONS ||--o{ DEPARTMENTS : has
    DEPARTMENTS ||--o{ USERS : contains
    
    ORGANIZATIONS ||--o{ CUSTOMERS : owns
    CUSTOMERS ||--o{ DEALS : generates
    DEALS ||--o{ PROJECTS : initiates
    PROJECTS ||--o{ TASKS : contains
    PROJECTS o|--o{ INVOICES : optional_reference
    INVOICES ||--o{ PAYMENTS : settles
    INVOICES ||--o{ JOURNAL_ENTRIES : posts
    PAYMENTS ||--o{ JOURNAL_ENTRIES : posts
    PAYMENTS }o--o{ BANK_OPERATIONS : settles
    
    USERS ||--o{ AUDIT_LOGS : performs
    INVOICES ||--o{ AUDIT_LOGS : tracks
```

---

### 11. Payroll Policy, Approval & GL Flow
- **ไฟล์ HTML:** [`11_payroll_policy_gl_flow.html`](./11_payroll_policy_gl_flow.html)
- **วัตถุประสงค์:** แสดง Payroll Phase 16B ตามที่ใช้งานจริง: โปรไฟล์เงินเดือนและ policy แบบ effective-dated, การคำนวณ item snapshot, การอนุมัติ, การบันทึก GL แยก accrual/settlement และ output payslip/CSV
- **ขอบเขต:** ใช้ `users`, `employee_payroll_profiles`, `payroll_runs`, `payroll_items` โดย PND1/SSO CSV เป็น workpaper สำหรับตรวจทานก่อนยื่น

---

### 12. Enterprise Document Lifecycle & Retention Governance
- **ไฟล์ HTML:** [`12_document_management_lifecycle.html`](./12_document_management_lifecycle.html)
- **วัตถุประสงค์:** แสดงวงจรชีวิตเอกสารองค์กร (Phase 17 DMS): การอัปโหลดไฟล์เก็บ private storage แยก tenant, ตรวจสอบความปลอดภัยด้วย SHA256 checksum และ AV scan, สถานะ Active พร้อมผูกความสัมพันธ์กับ Invoices/Expenses/Assets/Contracts/Employees, การแจ้งเตือนใกล้หมดอายุ (Expiring), การทบทวน Retention Policy และ Legal Hold, จนถึงการจัดเก็บถาวร (Archive) หรือทำลายตามนโยบาย (Purged)
- **Mermaid Preview:**

```mermaid
stateDiagram-v2
    [*] --> Uploaded
    Uploaded --> Scanning: Upload v1 to Private Storage
    Scanning --> Active: Checksum & AV Pass
    Scanning --> Quarantined: Infected / Blocked
    Active --> Expiring: Renewal Alert Window
    Expiring --> RetentionReview: Policy Expiry Due
    RetentionReview --> Archived: Compliant Retention Archive
    RetentionReview --> Purged: Purged (No Legal Hold)
    Archived --> [*]
    Purged --> [*]
    Quarantined --> [*]
```

---

### 13. Two-Factor Authentication & Privileged Access Flow
- **ไฟล์ HTML:** [`13_two_factor_auth_sequence.html`](./13_two_factor_auth_sequence.html)
- **วัตถุประสงค์:** แสดงกระบวนการยืนยันตัวตนสองขั้นตอน (Phase 18 2FA/TOTP): การตรวจสอบรหัสผ่าน, ประเมินนโยบายความปลอดภัยระดับองค์กร (บังคับใช้กับ Owner, Admin, Finance), การส่งคำขอ OTP (RFC 6238) หรือรหัสกู้คืนฉุกเฉิน (Recovery Codes), การออกโทเค็น Trusted Device 30 วัน, และการบังคับผ่าน `EnsureTwoFactorEnrollment` Middleware
- **Mermaid Preview:**

```mermaid
sequenceDiagram
    autonumber
    actor User as Browser / User Device
    participant Router as Laravel Router (web.php)
    participant Auth as Auth Controller
    participant Policy as TwoFactorPolicyService
    participant TOTP as TOTP Engine (RFC 6238)
    participant DB as MariaDB (Users & Devices)

    User->>Router: POST /login (credentials)
    Router->>Auth: authenticate()
    Auth->>DB: verify password & roles
    DB-->>Auth: valid credentials (privileged role)
    Auth->>Policy: shouldChallenge(user)
    Policy-->>Auth: challenge required (2FA enabled)
    Auth-->>User: 302 Redirect to /two-factor-challenge

    User->>Router: POST /two-factor-challenge (code, trust_device)
    Router->>Auth: verifyChallenge()
    Auth->>TOTP: verify(secret, code)
    TOTP-->>Auth: code valid
    Auth->>DB: store trusted device token (30 days)
    Auth-->>User: Set-Cookie + 302 Redirect to App

    User->>Router: GET /settings/organization
    Router->>Policy: EnsureTwoFactorEnrollment
    Policy-->>Router: authorized
    Router-->>User: Inertia::render('Settings/Organization')
```

---

### 14. Full Database ER Diagram (50+ Tables)
- **ไฟล์เอกสาร:** [`DATABASE_ERD.md`](./DATABASE_ERD.md)
- **วัตถุประสงค์:** ผังโครงสร้างฐานข้อมูลฉบับสมบูรณ์ทั้ง 50+ ตาราง ครอบคลุม 12 โดเมน พร้อม Data Types, Primary Keys (UUIDv7), Foreign Keys, และกฎ Multi-Tenancy Scoping (`org_id`)
- **Mermaid Preview (Master Cross-Domain):**

```mermaid
erDiagram
    ORGANIZATIONS ||--o{ BRANCHES : "1:N"
    BRANCHES ||--o{ DIVISIONS : "1:N"
    DIVISIONS ||--o{ DEPARTMENTS : "1:N"
    DEPARTMENTS ||--o{ USERS : "1:N"
    ORGANIZATIONS ||--o{ CUSTOMERS : "1:N"
    CUSTOMERS ||--o{ CONTACTS : "1:N"
    CUSTOMERS ||--o{ DEALS : "1:N"
    DEALS ||--o{ PROJECTS : "1:N"
    PROJECTS ||--o{ TASKS : "1:N"
    CUSTOMERS ||--o{ INVOICES : "1:N"
    INVOICES ||--o{ PAYMENTS : "1:N"
    SUPPLIERS ||--o{ PURCHASE_ORDERS : "1:N"
    WAREHOUSES ||--o{ WAREHOUSE_BINS : "1:N"
    WAREHOUSES ||--o{ STOCK_MOVEMENTS : "1:N"
    USERS ||--o| EMPLOYEE_PAYROLL_PROFILES : "0..1:1"
    PAYROLL_RUNS ||--o{ PAYROLL_ITEMS : "1:N"
    ORGANIZATIONS ||--o{ DOCUMENTS : "1:N"
    DOCUMENTS ||--o{ DOCUMENT_VERSIONS : "1:N"
    DOCUMENTS ||--o{ DOCUMENT_LINKS : "1:N"
    USERS ||--o{ TWO_FACTOR_TRUSTED_DEVICES : "1:N"
```

---

### 15. 6 Domain Group Workflows (31 Modules)
- **ไฟล์เอกสาร:** [`GROUPS_WORKFLOW.md`](./GROUPS_WORKFLOW.md)
- **วัตถุประสงค์:** ผังกระบวนการทำงานและวงจรสถานะเจาะลึก 6 กลุ่มงานหลัก ครอบคลุมทั้ง 31 โมดูล (รวม 19 Mermaid Diagrams พร้อมคำอธิบายภาษาไทย)
- **Mermaid Preview (Inter-Group Interaction):**

```mermaid
flowchart LR
    G1["1. Foundation & Security"] --> G2["2. CRM & Sales"]
    G2 --> G3["3. Project Delivery"]
    G2 --> G4["4. Finance & Billing"]
    G3 -. Milestone Done .-> G4
    G4 --> G5["5. Insights & Dashboard"]
    G4 -. Procure/Sync .-> G6["6. Operations & Extensions"]
```

---

## 🛠️ วิธีการเปิดดูและใช้งาน

1. **เปิดผ่านเบราว์เซอร์:**
   - ดับเบิลคลิกไฟล์ [`document/index.html`](./index.html) หรือไฟล์ `.html` ใดๆ เพื่อเปิด interactive viewer ในเบราว์เซอร์
   - สามารถกดปุ่มสลับ **Light / Dark mode**, **Focus View**, **Pan & Zoom**, และ **Export เป็น SVG / PNG / WebP** ได้ทันที
2. **เปิดดูเอกสารผังรายกลุ่มและ ERD:**
   - ผังการทำงาน 6 กลุ่มโมดูล (31 Modules): [`document/GROUPS_WORKFLOW.md`](./GROUPS_WORKFLOW.md)
   - ผังฐานข้อมูล 38+ ตาราง: [`document/DATABASE_ERD.md`](./DATABASE_ERD.md)
3. **แก้ไขหรือสร้างใหม่:**
   - ไฟล์ JSON Specification อยู่ในโฟลเดอร์ [`document/specs/`](./specs/)
   - สามารถรันคำสั่ง `node <archify-path>/bin/archify.mjs deliver <type> <spec.json> <output.html> --quality showcase --json` เพื่อ render ใหม่ได้ตลอดเวลา
