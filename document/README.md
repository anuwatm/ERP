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
| **12** | [Enterprise Document Lifecycle & Compliance](#12-enterprise-document-lifecycle--compliance) | Lifecycle | [`12_document_management_lifecycle.html`](./12_document_management_lifecycle.html) | [`specs/12_document_management_lifecycle.json`](./specs/12_document_management_lifecycle.json) |
| **13** | [Two-Factor Authentication & Privileged Access Flow](#13-two-factor-authentication--privileged-access-flow) | Sequence | [`13_two_factor_auth_sequence.html`](./13_two_factor_auth_sequence.html) | [`specs/13_two_factor_auth_sequence.json`](./specs/13_two_factor_auth_sequence.json) |
| **14** | [Approval Workflow Instance Lifecycle](#14-approval-workflow-instance-lifecycle) | Lifecycle | [`14_approval_workflow_engine.html`](./14_approval_workflow_engine.html) | [`specs/14_approval_workflow_engine.json`](./specs/14_approval_workflow_engine.json) |
| **15** | [PromptPay Thai QR & Payment Gateway Settlement Flow](#15-promptpay-thai-qr--payment-gateway-settlement-flow) | Sequence | [`15_payment_gateway_settlement.html`](./15_payment_gateway_settlement.html) | [`specs/15_payment_gateway_settlement.json`](./specs/15_payment_gateway_settlement.json) |
| **16** | [Master Dialog Standard & Editor Lifecycle](#16-master-dialog-standard--editor-lifecycle) | Lifecycle | [`16_master_dialog_editor_lifecycle.html`](./16_master_dialog_editor_lifecycle.html) | [`specs/16_master_dialog_editor_lifecycle.json`](./specs/16_master_dialog_editor_lifecycle.json) |
| **17** | [HR Employee, Attendance & Payroll Integration](#17-hr-employee-attendance--payroll-integration) | Workflow / Dataflow | [`17_hr_employee_attendance_payroll.md`](./17_hr_employee_attendance_payroll.md) | — |
| **18** | [e-Tax Invoice & RD Prep Gateway Architecture](#18-e-tax-invoice--rd-prep-gateway-architecture) | Sequence / Architecture | [`18_etax_gateway_pipeline.md`](./18_etax_gateway_pipeline.md) | — |
| **19** | [Cash Flow Forecasting & Financial Health Analytics](#19-cash-flow-forecasting--financial-health-analytics) | Workflow / Analytics | [`19_cash_flow_forecasting_engine.md`](./19_cash_flow_forecasting_engine.md) | — |
| **20** | [AI OCR Document Ingestion](#20-ai-ocr-document-ingestion) | Sequence / Architecture | [`20_ai_ocr_document_ingestion.md`](./20_ai_ocr_document_ingestion.md) | — |
| **21** | [Web Setup Wizard Architecture & Installer Lifecycle](#21-web-setup-wizard-architecture--installer-lifecycle) | Lifecycle / Security | [`21_setup_wizard_installer_lifecycle.md`](./21_setup_wizard_installer_lifecycle.md) | — |
| **All** | [Full Database ER Diagram (70+ Tables)](#22-full-database-er-diagram-70-ตาราง) | Database ERD | [`document/DATABASE_ERD.md`](./DATABASE_ERD.md) | [Central Database Schema](../docs/database/DATABASE.md) |
| **Groups** | [6 Domain Group Workflows (31 Modules)](#23-6-domain-group-workflows-31-modules) | Group Workflows | [`document/GROUPS_WORKFLOW.md`](./GROUPS_WORKFLOW.md) | — |

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

### 12. Enterprise Document Lifecycle & Compliance
- **ไฟล์ HTML:** [`12_document_management_lifecycle.html`](./12_document_management_lifecycle.html)
- **วัตถุประสงค์:** แสดง DMS ที่ implement แล้ว: private storage แยกองค์กร, SHA256 checksum, scan gate, version history, sensitivity 5 ระดับ, expiry/renewal และ compliance enforcement. ทุก document link/download ต้องผ่าน parent authorization; policy คำนวณ retention/legal hold, scheduler quarantine failed scan/archive และ purge ใช้ explicit command
- **Mermaid Preview:**

```mermaid
stateDiagram-v2
    [*] --> Uploaded: Private storage
    Uploaded --> Scanning: SHA256 and scan gate
    Scanning --> Active: scan_status = clean
    Active --> Expiring: Renewal Alert Window
    Expiring --> Active: Renewal date updated
    Scanning --> Quarantined: failed/infected scan
    Active --> Archived: retention due and no legal hold
    Archived --> Purged: explicit retention command
    Active --> Active: legal hold blocks archive/purge
```

---

### 13. Two-Factor Authentication & Privileged Access Flow
- **ไฟล์ HTML:** [`13_two_factor_auth_sequence.html`](./13_two_factor_auth_sequence.html)
- **วัตถุประสงค์:** แสดง 2FA/TOTP ที่กำหนดได้ใน Organization Settings. Policy ปิดเป็นค่าเริ่มต้น; เมื่อเปิด สามารถบังคับ privileged roles, ใช้ recovery code และ trusted device ตามระยะ 1-90 วันที่ตั้งค่าได้
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
    DB-->>Auth: valid credentials
    Auth->>Policy: shouldChallenge(user)
    Policy-->>Auth: challenge required only when enabled and enrolled
    Auth-->>User: 302 Redirect to /two-factor-challenge

    User->>Router: POST /two-factor-challenge (code, trust_device)
    Router->>Auth: verifyChallenge()
    Auth->>TOTP: verify(secret, code)
    TOTP-->>Auth: code valid
    opt Policy allows trusted devices
        Auth->>DB: store token hash and user-agent hash (1-90 days)
    end
    Auth-->>User: Set-Cookie + 302 Redirect to App

    User->>Router: GET /settings/organization
    Router->>Policy: EnsureTwoFactorEnrollment
    Policy-->>Router: authorized
    Router-->>User: Inertia::render('Settings/Organization')
```

---

### 14. Approval Workflow Instance Lifecycle
- **ไฟล์ HTML:** [`14_approval_workflow_engine.html`](./14_approval_workflow_engine.html)
- **ไฟล์ JSON Spec:** [`specs/14_approval_workflow_engine.json`](./specs/14_approval_workflow_engine.json)
- **วัตถุประสงค์:** แสดงกระบวนการทำงานของ Central Approval Workflow Engine (Phase 20) ตั้งแต่การส่งเอกสารเข้าตรวจ, การจับคู่นิยามและบันทึก snapshot, การตรวจสอบ SoD และการมอบหมายสิทธิ์แทน (Delegation), ลำดับการอนุมัติ (Sequential vs Parallel), ไปจนถึงการสะท้อนสถานะปลายทางและการบันทึกบัญชี GL / คืนสิทธิ์วันลา
- **Mermaid Preview:**

```mermaid
stateDiagram-v2
    [*] --> Draft: Subject Created
    Draft --> Pending: Submit to Workflow
    note right of Pending: Matched Definition & Subject Frozen in Snapshot
    Pending --> UnderReview: Queue Assigned Approvers
    note right of UnderReview: SoD Excludes Requester / Resolves Active Delegation
    UnderReview --> Approved: Final Step Approved
    note right of Approved: Target status updated & GL posted (Expenses)
    UnderReview --> Rejected: Rejected
    note right of Rejected: Subject marked rejected & Leave balance refunded
    UnderReview --> RevisionNeeded: Revision Requested
    RevisionNeeded --> Draft: Returned to Creator
    Approved --> [*]
    Rejected --> [*]
```

---

### 15. PromptPay Thai QR & Payment Gateway Settlement Flow
- **ไฟล์ HTML:** [`15_payment_gateway_settlement.html`](./15_payment_gateway_settlement.html)
- **ไฟล์ JSON Spec:** [`specs/15_payment_gateway_settlement.json`](./specs/15_payment_gateway_settlement.json)
- **วัตถุประสงค์:** แสดงกระบวนการรับชำระเงินผ่านเกตเวย์และพร้อมเพย์ (Phase 23) ตั้งแต่การขอสร้าง QR พร้อมเพย์ (Tag 29/30) ระบุยอดสตางค์, การสร้าง Charge และดาวน์โหลด Sanitized SVG จาก Opn, การยืนยัน Event ด้วย Secret Key ฝั่งเซิร์ฟเวอร์, ไปจนถึงการรับ Webhook ที่ลงลายมือชื่อ HMAC-SHA256 จาก Settlement Bridge เพื่อลงบัญชี Payment และสมุดรายวันทั่วไป (Double-Entry GL) ใน Transaction เดียวกัน
- **Mermaid Preview:**

```mermaid
sequenceDiagram
    participant Customer
    participant ERP
    participant Opn as Opn / Omise
    participant Bank as Banking App
    participant Bridge as Settlement Bridge
    participant GL as Ledger & DB

    Customer->>ERP: GET /invoice-payment/{id}
    ERP->>GL: Lock invoice & reserve intent (satang)
    opt Opn Provider Selected
        ERP->>Opn: POST /charges (PromptPay source)
        Opn-->>ERP: charge_id + scannable QR URI
    end
    ERP-->>Customer: Render sanitized SVG QR (30m expiry)
    Customer->>Bank: Scan QR & transfer via PromptPay
    Bank->>Opn: Interbank PromptPay clearing
    opt Opn Event Webhook
        Opn->>ERP: POST /api/gateway/{id}/opn (event_id)
        ERP->>Opn: GET /events & /charges (merchant credentials)
        ERP->>GL: Record provider_confirmed (No GL entry)
    end
    Bank->>Bridge: Bank statement credit confirmation
    Bridge->>ERP: POST /api/gateway/{id}/settlement (HMAC-SHA256)
    ERP->>ERP: Verify HMAC, timestamp window & dedupe
    ERP->>GL: Atomic Payment creation & Double-Entry GL
    ERP-->>Bridge: 200 OK (status: processed)
```

---

### 16. Master Dialog Standard & Editor Lifecycle
- **ไฟล์ HTML:** [`16_master_dialog_editor_lifecycle.html`](./16_master_dialog_editor_lifecycle.html)
- **ไฟล์ JSON Spec:** [`specs/16_master_dialog_editor_lifecycle.json`](./specs/16_master_dialog_editor_lifecycle.json)
- **วัตถุประสงค์:** วงจรสถานะของ Master Create/Edit Dialog and Single Active Editor Lifecycle (Phase 23.1): ควบคุมการเปิด Modal เพียง 1 ตัวผ่าน `useEditorCoordinator`, ตรวจจับการเปลี่ยนแปลงข้อมูล (Comprehensive Deep Dirty Detection), ยืนยันก่อนปิดเมื่อมีข้อมูลที่ยังไม่ได้บันทึก (`confirmUnsavedChanges`), ป้องกันการกดส่งซ้ำฝั่ง Client และรับประกัน Idempotent Backend Submission ผ่าน `EnsureFormSubmission` Middleware + `X-Submission-Key`
- **Mermaid Preview:**

```mermaid
stateDiagram-v2
    [*] --> Idle: พร้อมใช้งาน (No Active Editor)
    
    Idle --> Initializing: open(editorId, initialData)
    Initializing --> Clean: โหลดข้อมูลสำเร็จ / Deep Clone initial state
    
    Clean --> Dirty: ผู้ใช้แก้ไขข้อมูล (User Mutates Fields)
    Dirty --> Clean: Reset ข้อมูลกลับสู่ Initial State
    
    Clean --> Closed: Close Request (ESC / Cancel) - ปิดทันที
    Dirty --> ConfirmClose: Close Request (ESC / Cancel / Backdrop)
    
    ConfirmClose --> Dirty: ผู้ใช้กด "แก้ไขต่อ" (Keep Editing)
    ConfirmClose --> Discarded: ผู้ใช้ยืนยัน "ละทิ้งข้อมูล" (Discard Changes)
    Discarded --> Closed: Reset Form State
    
    Dirty --> Submitting: Submit Form (ตั้ง isSubmitting = true)
    Clean --> Submitting: Submit Form (เช่น สถานะเปลี่ยนทันที)
    
    Submitting --> ValidationFailed: Client / Server Validation Error
    ValidationFailed --> Dirty: แก้ไขข้อผิดพลาด
    
    Submitting --> Succeeded: บันทึกสำเร็จ (200/201 OK)
    Succeeded --> Clean: markClean() & แจ้งเตือน Toast
    Clean --> Closed: ปิด Dialog & Refresh Data Table
    
    Closed --> Idle: คืนสถานะ Active Editor ว่าง
```

---

### 17. HR Employee, Attendance & Payroll Integration
- **ไฟล์เอกสาร:** [`17_hr_employee_attendance_payroll.md`](./17_hr_employee_attendance_payroll.md)
- **วัตถุประสงค์:** สถาปัตยกรรม HR บุคลากร, ระบบตารางกะ (Shift Roster), นำเข้าเวลาทำงาน (Excel / CSV / Self-Punch), และการคำนวณเงินเดือนสำหรับพนักงานทั้งที่มีและไม่มีบัญชีผู้ใช้ระบบ (Non-User Employees) พร้อมจัดส่ง Payslip PDF ทางอีเมลส่วนตัว
- **Mermaid Preview:**

```mermaid
flowchart LR
    subgraph MasterData["Master Data & Personnel"]
        Position["Position Master\npositions table"] --> Employee["Employee Master\nDecoupled from User (AES-256)"]
        BulkImport["Bulk Employee Import\nExcel/CSV/TSV Service"] --> Employee
        BulkImport -. Auto-create .-> Position
    end

    subgraph ShiftRoster["Shift Scheduling"]
        Shift["Shift Master\nGrace / 1.5x, 2x, 3x OT Rates"] --> Roster["Shift Roster Matrix\nDaily / Monthly Schedules"]
        Employee --> Roster
    end

    subgraph TimeTracking["Time Tracking & Intake"]
        CSVLog["CSV / TSV Import"] --> RawLogs[("attendance_raw_logs")]
        ExcelLog["Excel Import\n.xlsx / .xls / .xml"] --> RawLogs
        SelfPunch["Self-service Clock-in\nSHA-256 IP / Opt-in GPS"] --> RawLogs
    end

    subgraph ReconEngine["Reconciliation Engine"]
        RawLogs --> Recon["Attendance Reconciliation\nOvernight Shifts & Late Deduction"]
        Roster --> Recon
        Recon --> Attendances["attendances\nWorked / Late / OT Hours"]
    end

    subgraph PayrollSettlement["Payroll & Settlement Bridge"]
        Attendances --> Summary["Locked Attendance Summary"]
        Summary --> PayrollItems["payroll_items\nDaily / Hourly / Monthly"]
        Shift -. Multi-rate OT .-> PayrollItems
        PayrollItems --> PayrollRun["Payroll Run Approval"]
        PayrollRun --> Payslip["Payslip PDF & PayslipMail\nDirect Email for Non-User"]
        PayrollRun --> GL["Double-Entry GL Posting\n2140 Payroll Payable"]
    end
```

---

### 18. e-Tax Invoice & RD Prep Gateway Architecture
- **ไฟล์เอกสาร:** [`18_etax_gateway_pipeline.md`](./18_etax_gateway_pipeline.md)
- **วัตถุประสงค์:** ไปป์ไลน์จัดเตรียมและนำส่งใบกำกับภาษีอิเล็กทรอนิกส์ e-Tax Invoice / Credit Note ตามมาตรฐาน ETDA พร้อม Enveloped XML-DSig Signature (RSA-SHA256), Queue Worker อัตโนมัติ, Webhook Callback (HMAC-SHA256) และ Cron Inquire Status ทุก 30 นาที
- **Mermaid Preview:**

```mermaid
sequenceDiagram
    autonumber
    actor Staff as Finance Staff
    participant System as ERP Invoicing Module
    participant ETaxAdapter as RdApiSubmissionAdapter
    participant SignEngine as ETaxXmlSignatureService
    participant Queue as Queue Worker (SubmitETaxDocument)
    participant Gateway as Certified Gateway / RD API
    participant Webhook as ETaxWebhookController
    participant Artisan as artisan etax:inquire-status

    Staff->>System: สั่งสร้าง e-Tax (Tax Invoice / Credit Note)
    System->>System: ตรวจสอบข้อมูลผู้ซื้อ/ผู้ขาย & Tax ID 13 หลัก
    System->>SignEngine: สร้าง ETDA XML มาตรฐาน & ส่งข้อมูล Sign
    Note over SignEngine: XML Canonicalization (C14N)<br/>RSA-SHA256 DigestValue<br/>X.509 KeyInfo Reference
    SignEngine-->>System: คืนค่า Enveloped XML-DSig Signed XML
    System->>System: บันทึก etax_documents (สถานะ: signed)
    
    alt โหมด Mock / Manual Export
        System-->>Staff: ดาวน์โหลด Signed XML / PDF e-Tax ทันที
    else โหมด Live Provider Gateway
        System->>Queue: Push Job SubmitETaxDocument (Queue: etax)
        Queue->>ETaxAdapter: ประมวลผลส่งผ่าน Gateway Adapter
        ETaxAdapter->>Gateway: POST /api/v1/etax/submit (Base64 Encoded XML)
        Gateway-->>ETaxAdapter: ตอบกลับ HTTP 202 Accepted + Submission UID
        ETaxAdapter->>System: อัปเดตสถานะ etax_documents เป็น submitted
        
        par Webhook Callback
            Gateway->>Webhook: POST /api/e-tax/{config}/callback (HMAC-SHA256)
            Webhook->>Webhook: ตรวจสอบ X-ETax-Signature & Multi-tenant Scope
            Webhook->>System: อัปเดตสถานะเอกสารเป็น accepted หรือ rejected
        and Async Scheduled Inquiry
            Artisan->>Gateway: GET /api/v1/etax/status/{uid} (ทุก 30 นาที)
            Gateway-->>Artisan: คืนสถานะเอกสารทางการจากกรมสรรพากร
            Artisan->>System: ซิงค์สถานะและเลขรับเอกสารทางการ RD Code
        end
    end
```

---

### 19. Cash Flow Forecasting & Financial Health Analytics
- **ไฟล์เอกสาร:** [`19_cash_flow_forecasting_engine.md`](./19_cash_flow_forecasting_engine.md)
- **วัตถุประสงค์:** เอนจินวิเคราะห์กระแสเงินสดล่วงหน้า 30-180 วัน จำลอง 3 สถานการณ์ (Base, Best, Worst Scenario) พร้อมการวิเคราะห์อัตราส่วนสภาพคล่อง (CR, QR, DSO, DPO, Runway) และบันทึก Snapshot รายวันอัตโนมัติ
- **Mermaid Preview:**

```mermaid
flowchart TD
    subgraph DataSources["Financial Inflow & Outflow Sources"]
        AR["AR Due Dates\nCustomer Invoices"]
        Deals["Sales Pipeline\nWeighted by Deal Probability"]
        AP["AP Approved Expenses\nVendor Invoices"]
        PO["Open Purchase Orders\nCommitted Costs"]
        Payroll["Recurring Monthly Payroll\nDay 25 Scheduled Run"]
    end

    subgraph ForecastEngine["CashFlowForecastService (30 - 180 Days Horizon)"]
        InflowCalc["Inflow Calculation Engine\nAR Cash + Deal Upside Factor"]
        OutflowCalc["Outflow Calculation Engine\nCommitted AP + PO + Payroll Buffer"]
        
        Scenarios["3 Simulation Scenarios\nforecast_scenarios"]
        Base["Base Scenario\nNormal Business Operations"]
        Best["Best Scenario\nHigh Deal Win Rate + Faster AR"]
        Worst["Worst Scenario\nCollection Delays + Cost Overruns"]
        
        Scenarios --> Base & Best & Worst
        AR & Deals --> InflowCalc
        AP & PO & Payroll --> OutflowCalc
        
        InflowCalc & OutflowCalc --> SimulationEngine["Trajectory Projection Engine\nDay-by-Day Balance Simulation"]
        Base & Best & Worst --> SimulationEngine
    end

    subgraph HealthAnalytics["Financial Health & Liquidity Ratios"]
        CR["Current Ratio\nCurrent Assets / Liabilities (>= 1.5x)"]
        QR["Quick Ratio\n(Cash + AR) / Liabilities (>= 1.0x)"]
        DSO["Days Sales Outstanding\n90 Days Sales Velocity"]
        DPO["Days Payable Outstanding\n90 Days Expense Velocity"]
        Runway["Working Capital Runway\nMonths before Zero Cash"]
    end

    subgraph Outputs["Visualization & Daily Automation"]
        SVGChart["Interactive SVG Trajectory Curve\nLowest Cash Point & Zero-line Alarm"]
        KPIBadge["Executive StatCards & Badges\nHealth Scores & Comparison Cards"]
        ArtisanCron["Artisan Scheduler\nphp artisan cashflow:snapshot (Daily 02:00)"]
        SnapshotDB[("cash_flow_forecast_snapshots\nfinancial_metric_logs")]
    end
```

---

### 20. AI OCR Document Ingestion
- **ไฟล์เอกสาร:** [`20_ai_ocr_document_ingestion.md`](./20_ai_ocr_document_ingestion.md)
- **วัตถุประสงค์:** สถาปัตยกรรมสกัดข้อมูลใบเสร็จและใบแจ้งหนี้ด้วย Universal Vision Hub (Local Ollama / Cloud GPT), PDF JPEG extraction, ตรวจสอบเลขประจำตัวผู้เสียภาษี 13 หลักตามสูตร Modulo 11, และหน้าจอ Split-Screen Human Reviewer ยืนยันก่อนแปลงเป็น Draft Expense (ห้าม Auto-GL)
- **Mermaid Preview:**

```mermaid
sequenceDiagram
    autonumber
    actor User as Accounting Staff
    participant UI as OcrReviewer.tsx (Split-Screen)
    participant Ctrl as OcrDocumentController
    participant Preprocess as Image & PDF Pre-processor
    participant AIHub as Universal OpenAI-Compatible Vision Hub
    participant Validator as ExtractionValidator & MathReconciler
    participant DMS as Central DMS Repository
    participant Expense as Expense/Bill Module (Draft Only)

    User->>UI: อัปโหลดรูปภาพใบเสร็จ / PDF สแกน
    UI->>Ctrl: POST /finance/ocr/upload (Multipart file)
    Ctrl->>Preprocess: ตรวจสอบ MIME & Format
    
    alt เป็นไฟล์ Scanned PDF
        Preprocess->>Preprocess: สกัด JPEG Stream ด้วย extractJpegFromPdf()
    else เป็นไฟล์ภาพ (JPEG, PNG, WEBP)
        Preprocess->>Preprocess: จัดเตรียม High-Detail Tile Buffer
    end
    
    Ctrl->>AIHub: ส่ง Vision Request พร้อม Base64 Payload
    Note over AIHub: รองรับทั้ง Local AI (Ollama qwen2.5-vl)<br/>และ Cloud Hubs (GPT-4o, Gemini, OpenRouter)<br/>บังคับ Native JSON Mode & max_tokens
    AIHub-->>Ctrl: ส่งคืน Structured JSON Object
    
    Ctrl->>Validator: ตรวจสอบความถูกต้องและกระทบยอดตัวเลข
    Note over Validator: 1. Thai Tax ID 13 หลัก (Modulo 11 Check)<br/>2. Math: Subtotal + VAT - WHT == Total Amount<br/>3. คำนวณค่า Confidence Score
    Validator-->>Ctrl: ผลการตรวจสอบและ Flag (math_mismatch, tax_id_valid)
    
    Ctrl->>DMS: จัดเก็บไฟล์เอกสารต้นฉบับลง DMS (Protected Tenant Path)
    Ctrl-->>UI: ส่งข้อมูลกลับแสดงผลในหน้า Split-Screen Reviewer
    
    User->>UI: ตรวจสอบเปรียบเทียบภาพต้นฉบับทางซ้าย กับฟอร์มแก้ไขทางขวา
    User->>UI: กดยืนยัน "Convert to Expense"
    UI->>Ctrl: POST /finance/ocr/{id}/convert
    Ctrl->>Expense: สร้าง Draft Expense ในระบบ (Assisted Draft Flow)
    Note over Expense: 🔒 Human-in-the-Loop Guard:<br/>ห้าม Auto-post ลงบัญชีแยกประเภท (GL)<br/>ต้องผ่านการอนุมัติก่อนเสมอ
    Expense-->>UI: Redirect ไปยังหน้ารายละเอียด Expense
```

---

### 21. Web Setup Wizard Architecture & Installer Lifecycle
- **ไฟล์เอกสาร:** [`21_setup_wizard_installer_lifecycle.md`](./21_setup_wizard_installer_lifecycle.md)
- **วัตถุประสงค์:** วงจรการติดตั้งระบบผ่านเว็บวิซาร์ด (Setup Wizard 4 ขั้นตอน) และหน้า System Requirements แบบไม่ใช้ token, การตรวจสอบความปลอดภัยล่วงหน้า (Preflight Check & Clean Release), การเขียน `.env` แบบ Atomic ด้วย File Lock (`install.lock`), การรัน Migration 58 ไฟล์, การสร้าง Owner Account พร้อม Master Data และการล็อกการเข้าถึงถาวรด้วย `installed.json`
- **Mermaid Preview:**

```mermaid
flowchart TD
    subgraph AccessGuard["1. Security Gate & Entry Points"]
        Req["Request: GET /setup"] --> CheckInstalled{"Is Installed?\nstorage/app/installer/installed.json"}
        CheckInstalled -- "Yes (Locked)" --> Resp404["HTTP 404 Not Found\nInstallation Closed"]
        CheckInstalled -- "No" --> CheckToken{"Is Enabled?\nactivationToken() !== null"}
        CheckToken -- "No" --> Resp403["HTTP 403 Forbidden\nInstaller is disabled"]
        CheckToken -- "Yes" --> WizardUI["Setup Wizard Steps (1-4)"]
        
        TokenSource["Activation Token (>= 32 chars)\n1. Server Env: SETUP_TOKEN\n2. File: backend/setup/enable.php"] -.-> CheckToken

        RequirementsReq["GET /setup/system-requirements.php\nNo Setup Token"] --> RequirementsLock{"Is Installed?\ninstalled.json"}
        RequirementsLock -- "Yes" --> Requirements404["HTTP 404 Not Found"]
        RequirementsLock -- "No" --> RequirementsReport["Public server requirements\nNo secrets / phpinfo()"]
        RequirementsReport --> Guidance["Failed/Warning guidance"]
        Guidance --> SafeFix["CSRF Safe Fix\nCreate only missing storage/ or bootstrap/cache/"]
    end

    subgraph StepFlow["2. Multi-Step Wizard Flow"]
        WizardUI --> Step1["Step 1: Security & Preflight\n• PHP 8.3+, PDO MySQL, OpenSSL, mbstring, fileinfo\n• DOM, SimpleXML, cURL\n• Writable: storage/, bootstrap/cache/, root\n• Clean Release: ! is_file(.env)\n• Verify Setup Token (hash_equals)"]
        Step1 --> Step2["Step 2: Server & Database\n• App Name & App URL (http/https)\n• Host, Port (1-65535), DB Name, Username\n• Test Connection: PDO SELECT 1"]
        Step2 --> Step3["Step 3: Organization & Owner\n• Org Name, Legal Name, Tax ID\n• Owner Name, Email\n• Owner Password (>= 12 chars)"]
        Step3 --> Step4["Step 4: Install Confirmation\n• Type 'INSTALL' to confirm execution"]
    end

    subgraph InstallEngine["3. Installer Execution Engine (Flock Lock)"]
        Step4 --> Lock["Acquire File Lock (install.lock)\nPrevent Concurrent Execution"]
        Lock --> EmptyCheck{"Check Target Database\nSHOW TABLES LIKE 'migrations'"}
        EmptyCheck -- "Migrations Found" --> AbortDB["Abort: Refusing to overwrite DB"]
        EmptyCheck -- "Empty DB" --> WriteEnv["Write .env Atomically\n• Clone .env.example\n• Generate random 32-byte APP_KEY\n• Set chmod 0600\n• Clear config/services cache"]
        
        WriteEnv --> RunMigrate["Run Database Migrations\nArtisan migrate --force"]
        RunMigrate --> Provision["Provision Owner & Master Data\n• OrganizationProvisioner::registerOwner()\n• Seed 7 System Roles & Permissions Catalog\n• Branch, Division, Department, Sequences"]
        
        Provision --> WriteLock["Write Install Lock\nstorage/app/installer/installed.json"]
        WriteLock --> Cleanup["Cleanup Activation File\n@unlink(backend/setup/enable.php)"]
        Cleanup --> Unlock["Release File Lock (install.lock)"]
        Unlock --> SuccessRedirect["Redirect: /login?installed=1\nRegenerate Session"]
    end
```

---

### 22. Full Database ER Diagram (70+ Tables)
- **ไฟล์เอกสาร:** [`DATABASE_ERD.md`](./DATABASE_ERD.md)
- **วัตถุประสงค์:** ผังโครงสร้างฐานข้อมูลฉบับสมบูรณ์ 70+ ตาราง ครอบคลุม 21 โดเมน พร้อม Data Types, Primary Keys (UUIDv7), Foreign Keys, กฎ Multi-Tenancy Scoping (`org_id`), และระบบป้องกันส่งซ้ำ `form_submissions`
- **Mermaid Preview (Master Cross-Domain):**

```mermaid
erDiagram
    ORGANIZATIONS ||--o{ BRANCHES : "1:N"
    BRANCHES ||--o{ DIVISIONS : "1:N"
    DIVISIONS ||--o{ DEPARTMENTS : "1:N"
    DEPARTMENTS ||--o{ USERS : "1:N"
    USERS ||--o{ FORM_SUBMISSIONS : "1:N (Phase 23.1)"
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
    ORGANIZATIONS ||--o{ POSITIONS : "1:N (Phase 23.3)"
    ORGANIZATIONS ||--o{ EMPLOYEES : "1:N (Phase 23.3)"
    POSITIONS ||--o{ EMPLOYEES : "1:N"
    EMPLOYEES ||--o{ EMPLOYEE_SHIFT_SCHEDULES : "1:N"
    SHIFT_TEMPLATES ||--o{ EMPLOYEE_SHIFT_SCHEDULES : "1:N"
    EMPLOYEES ||--o{ ATTENDANCES : "1:N"
    USERS ||--o| EMPLOYEE_PAYROLL_PROFILES : "0..1:1"
    PAYROLL_RUNS ||--o{ PAYROLL_ITEMS : "1:N"
    ORGANIZATIONS ||--o{ DOCUMENTS : "1:N"
    DOCUMENTS ||--o{ DOCUMENT_VERSIONS : "1:N"
    DOCUMENTS ||--o{ DOCUMENT_LINKS : "1:N"
    USERS ||--o{ TWO_FACTOR_TRUSTED_DEVICES : "1:N"
    ORGANIZATIONS ||--o{ WORKFLOW_DEFINITIONS : "1:N"
    WORKFLOW_DEFINITIONS ||--o{ WORKFLOW_STEPS : "1:N"
    WORKFLOW_DEFINITIONS ||--o{ WORKFLOW_INSTANCES : "1:N"
    WORKFLOW_INSTANCES ||--o{ WORKFLOW_APPROVALS : "1:N"
    ORGANIZATIONS ||--o{ WORKFLOW_DELEGATIONS : "1:N"
    ORGANIZATIONS ||--o{ NOTIFICATION_CHANNELS : "1:N"
    NOTIFICATION_CHANNELS ||--o{ NOTIFICATION_OUTBOX : "1:N"
    CUSTOMERS ||--o{ PORTAL_USERS : "1:N"
    PORTAL_USERS ||--o{ PORTAL_SESSIONS : "1:N"
    ORGANIZATIONS ||--o| PAYMENT_GATEWAY_CONFIGS : "1:1"
    PAYMENT_GATEWAY_CONFIGS ||--o{ GATEWAY_TRANSACTIONS : "1:N"
    PAYMENT_GATEWAY_CONFIGS ||--o{ WEBHOOK_EVENTS : "1:N"
    ORGANIZATIONS ||--o| ETAX_CONFIGURATIONS : "1:1 (Phase 24)"
    ETAX_CONFIGURATIONS ||--o{ ETAX_DOCUMENTS : "1:N"
    ETAX_DOCUMENTS ||--o{ ETAX_SUBMISSION_ATTEMPTS : "1:N"
    ORGANIZATIONS ||--o{ FORECAST_SCENARIOS : "1:N (Phase 25)"
    ORGANIZATIONS ||--o{ CASH_FLOW_FORECAST_SNAPSHOTS : "1:N"
    ORGANIZATIONS ||--o{ FINANCIAL_METRIC_LOGS : "1:N"
    ORGANIZATIONS ||--o| AI_CONFIGURATIONS : "1:1 (Phase 26)"
    ORGANIZATIONS ||--o{ OCR_EXTRACTIONS : "1:N"
    OCR_EXTRACTIONS ||--o{ OCR_FIELD_RESULTS : "1:N"
```

---

### 23. 6 Domain Group Workflows (31 Modules)
- **ไฟล์เอกสาร:** [`GROUPS_WORKFLOW.md`](./GROUPS_WORKFLOW.md)
- **วัตถุประสงค์:** ผังกระบวนการทำงานและวงจรสถานะเจาะลึก 6 กลุ่มงานหลัก ครอบคลุมทั้ง 31 โมดูล พร้อมส่วนต่อขยาย Phase 23.2-26 และ Setup Wizard (รวม 25+ Mermaid Diagrams พร้อมคำอธิบายภาษาไทย)
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
   - ผังฐานข้อมูล 61+ ตาราง: [`document/DATABASE_ERD.md`](./DATABASE_ERD.md)
3. **แก้ไขหรือสร้างใหม่:**
   - ไฟล์ JSON Specification อยู่ในโฟลเดอร์ [`document/specs/`](./specs/)
   - สามารถรันคำสั่ง `node <archify-path>/bin/archify.mjs deliver <type> <spec.json> <output.html> --quality showcase --json` เพื่อ render ใหม่ได้ตลอดเวลา
