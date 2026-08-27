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
| **All** | [Full Database ER Diagram (38+ Tables)](#-full-database-er-diagram-38-ตาราง) | Database ERD | [`document/DATABASE_ERD.md`](./DATABASE_ERD.md) | [Central Database Schema](../docs/database/DATABASE.md) |

---

## 🧩 ไดอะแกรมรายกลุ่มการทำงาน 6 กลุ่ม (31 Modules Workflow)

นอกจากภาพรวมสถาปัตยกรรมระดับระบบ 10 ชุดด้านบนแล้ว ยังมีเอกสารผังการทำงานเจาะลึกเฉพาะ **6 กลุ่มงานหลัก ครอบคลุม 31 โมดูลทั้งหมด** พร้อมคำอธิบายภาษาไทยและ Mermaid Diagrams รวม 18 ชุด:

📄 **ดูรายละเอียดฉบับเต็ม:** [`document/GROUPS_WORKFLOW.md`](./GROUPS_WORKFLOW.md)

| กลุ่มงาน (Domain Group) | โมดูลที่ครอบคลุม | หัวข้อไดอะแกรมหลัก |
|---|---|---|
| **Group 1: Foundation & Security** | `01-04` (Org, User/Role, Settings, Audit) | • Multi-Tenant Scope Flow<br>• RBAC 7 Roles Policy Gate<br>• Audit Trail Pipeline |
| **Group 2: CRM & Sales Pipeline** | `05-08` (Customers, Contacts, Deals, Quotations) | • Lead-to-Quote Workflow<br>• Deal Stage State Machine<br>• Quotation Issuance Sequence |
| **Group 3: Project Delivery & Execution** | `09-11` (Projects, Tasks, Milestones) | • Project Delivery Workflow<br>• Task Kanban Lifecycle<br>• Milestone Sign-off Trigger |
| **Group 4: Finance, Billing & Cost Control** | `12-16` (Products, Suppliers, Invoices, Payments, Expenses) | • Order-to-Cash & Expenses<br>• Invoice Anti-Overpay Sequence<br>• Project Cost Allocation |
| **Group 5: Insights, Platform & Automation** | `17-23` (Dashboard, Reports, Files, Notifications, Automation, API, Import/Export) | • Event & Automation Engine<br>• Executive Metric Lineage<br>• Tenant File Storage Flow |
| **Group 6: Operations & Advanced Extensions (V2+)** | `24-31` (PO, Inventory, HR, Attendance, Payroll, AI, Accounting, Portal) | • Procurement & Stock Movement<br>• HR & Payroll Calculation<br>• External Accounting & Portal Grid |

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

## 🛠️ วิธีการเปิดดูและใช้งาน

1. **เปิดผ่านเบราว์เซอร์:**
   - ดับเบิลคลิกไฟล์ [`document/index.html`](./index.html) หรือไฟล์ `.html` ใดๆ เพื่อเปิด interactive viewer ในเบราว์เซอร์
   - สามารถกดปุ่มสลับ **Light / Dark mode**, **Focus View**, **Pan & Zoom**, และ **Export เป็น SVG / PNG / WebP** ได้ทันที
2. **แก้ไขหรือสร้างใหม่:**
   - ไฟล์ JSON Specification อยู่ในโฟลเดอร์ [`document/specs/`](./specs/)
   - สามารถรันคำสั่ง `node <archify-path>/bin/archify.mjs deliver <type> <spec.json> <output.html> --quality showcase --json` เพื่อ render ใหม่ได้ตลอดเวลา
