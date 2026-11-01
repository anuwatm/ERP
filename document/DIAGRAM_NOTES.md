# Diagram notes — รายละเอียดประกอบ — 2026-10-29

เก็บรายละเอียด cards จาก diagram เดิมครบ เพื่อให้ HTML เน้นเส้นทางหลักและไม่ล้นจอ. [Portal](index.html) · [POS Config](POS_CONFIGURATION.md). คำอธิบายเดิมคงภาษาต้นฉบับ.

## 01_system_architecture

[เปิด diagram](01_system_architecture.html)

### Frontend & Monolith Layer

- React 18 + Inertia.js + TypeScript + Vite
- No decoupled REST latency - direct Inertia page props and server state
- Responsive UI designed for SME Company OS operations

### Backend Core (PHP 8.3 / Laravel 13)

- Multi-tenant isolation enforced via org_id on all queries
- RBAC permission enforcement with 7 distinct business roles
- Business rules safeguard money with strict DECIMAL(18,2)

### Security & Audit Governance

- Local Breeze auth with rate limiting and database-backed sessions
- Authenticated requests resolve the active session and organization
- Application audit logs record supported business actions; database-level immutability is not assumed
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 02_multitenancy_org_hierarchy

[เปิด diagram](02_multitenancy_org_hierarchy.html)

### Structured Hierarchy

- Clear organizational chain: Organization -> Branch -> Division -> Department -> User
- Display codes use clean 6-digit formatting (e.g. 000001)
- 1 User strictly belongs to 1 Organization in MVP to ensure clean scoping

### Tenant Security & Scoping

- Organization-owned records are filtered with org_id in controllers and services
- Model binding is guarded before organization-scoped mutations
- Authenticated requests use the user's organization context

### 7 Built-in RBAC Roles

- Owner, Admin, Sales, Project Manager, Finance, Member, Viewer
- Fine-grained permissions format: module.action (e.g., invoices.create)
- Centralized authorization gates protect UI routes and backend mutations
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 03_business_core_workflow

[เปิด diagram](03_business_core_workflow.html)

### End-to-End Business Rail

- Connected loop from Lead acquisition through Delivery to Cash-in
- Eliminates silos between Sales, Delivery, and Finance teams
- Single source of truth for SME management

### Strict Financial Integrity

- Invoiced Revenue vs Real Cash In tracked separately
- Strict anti-overpayment guard ensures received sum never exceeds invoice balance
- Payment reversals create auditable counterbalance entries

### Executive Observability

- KPI dashboards support Admin, Sales, PM, and Finance views
- Supported business actions write application audit records
- Company OS provides operational visibility without heavy enterprise bloat
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 04_auth_session_rbac_sequence

[เปิด diagram](04_auth_session_rbac_sequence.html)

### Secure Local Auth Flow

- Rate-limiting protection prevents brute-force credential attacks
- Passwords hashed using bcrypt/argon2 standards
- Database session stores authenticated user context

### Multi-Tenant & RBAC Shield

- Authenticated requests resolve the user's organization context
- RBAC Middleware verifies user roles and explicit module.action permissions
- Unauthorized requests are stopped before reaching business controllers

### Inertia Monolith Integration

- Seamless session-based authentication without fragile external JWT tokens
- Initial page props (user, permissions, flash messages) injected server-side
- Full CSRF protection on all mutating state transitions
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 05_invoice_creation_payment_sequence

[เปิด diagram](05_invoice_creation_payment_sequence.html)

### Strict Monetary Precision

- All calculations locked to DECIMAL(18,2) to prevent floating-point rounding errors
- Subtotal, VAT (7%), and discount calculated strictly per line item
- Display codes formatted with uniform 6-digit padded strings (e.g. 000001)

### Anti-Overpayment Guard

- Total paid sum can never exceed the gross invoice amount
- Supports partial payments (status transitions to Partially Paid)
- Fully paid threshold automatically marks invoice status as Paid

### Transactional Audit Integrity

- Executed inside atomic MariaDB transactions with database locks
- Payment creates an application audit trail entry
- Reversals and adjustments produce distinct counter-records rather than hard deletes
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 06_erp_data_lineage_flow

[เปิด diagram](06_erp_data_lineage_flow.html)

### Business Data Lineage

- Data travels cleanly from Customer/Product catalogs into Deals and Quotations
- Signed contracts initiate Project milestones and task execution structures
- Invoices can be created directly and may optionally reference a project or deal

### Revenue vs Cash Segregation

- Strict separation between Invoiced Revenue (Accrual) and Cash Received (Real money)
- Zero simulated cash balance — Dashboards present authentic cash-in figures
- All transactions enforce DECIMAL(18,2) precision with full tenant isolation

### Real-Time Decision Intelligence

- Executive Dashboard consumes aggregated streams for instant KPI visibility
- Finance Dashboard tracks outstanding aging balances and collection ratios
- Audit trail guarantees full historical traceability across the entire data lifecycle
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 07_deal_sales_lifecycle

[เปิด diagram](07_deal_sales_lifecycle.html)

### Configured Deal Stages

- Open stages: New -> Contacted -> Qualified -> Proposal -> Negotiation
- Closed stages: Won and Lost
- Each deal may carry value, probability, expected close date, and an owner

### Commercial Handover

- A won deal can be linked to one project
- Invoices and quotations can reference a deal
- Project creation is an explicit business action, not an automatic model transition

### Accountable Lost Tracking

- Lost reason, timestamp, and note can be recorded on a lost deal
- The model has no On Hold or Cancelled stage
- Stage ordering is presented as a common workflow, not a database-enforced transition rule
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 08_invoice_lifecycle

[เปิด diagram](08_invoice_lifecycle.html)

### Strict Financial Precision

- Status values: Draft -> Sent -> Partially Paid -> Paid
- Only Draft invoices can be edited after GL posting is enabled
- Payments decrement remaining balance in real time using DECIMAL(18,2)

### Aging & Overdue Tracking

- Automated overdue flagging based on credit terms and due dates
- Aging reports categorize receivables into 30, 60, and 90+ day buckets
- Payment posting recalculates balance and may move the invoice to Partially Paid or Paid

### Void and Audit Rules

- Voiding a Sent invoice reverses its GL posting
- An invoice cannot be voided after payment has been collected
- Write-off is not an invoice status in the current implementation
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 09_task_project_lifecycle

[เปิด diagram](09_task_project_lifecycle.html)

### Delivery & Kanban Rhythm

- Task status values: To Do, In Progress, Blocked, Done, and Cancelled
- Task completion does not automatically create an invoice
- Tasks belong to projects and support checklists and comments

### Blocker Visibility

- Blocked is a configured task status
- A task can be returned to In Progress after resolving a dependency
- Project workflow details such as QA and client sign-off are not task statuses

### Scope Control & Governance

- Cancelled is a configured task status
- Rejected Scope is not a task status in the current implementation
- Task status ordering is a workflow convention, not a database-enforced transition rule
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 10_database_er_domain_model

[เปิด diagram](10_database_er_domain_model.html)

### Database Conventions

- Business models use ordered UUID primary keys
- Human-visible reference codes use zero-padded sequences where applicable
- Soft deletes are enabled on selected business models

### Tenant Scoping

- Organization-owned domain records carry org_id
- Controllers and services apply org_id constraints for tenant access
- Organization guards run before supported scoped mutations

### Financial Posting

- Money columns use decimal casts and DECIMAL schema columns
- Invoice and payment actions post or reverse general-ledger journal entries
- Payment processing protects outstanding balances inside database transactions
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 11_payroll_policy_gl_flow

[เปิด diagram](11_payroll_policy_gl_flow.html)

### Calculation Integrity

- Only active profiles are calculated
- Tax and social-security policy IDs are locked on the run
- Each payroll item preserves a calculation snapshot

### Accounting Boundary

- Approval records expense and payroll liabilities
- Payment settles payroll payable against mapped cash or bank
- Payroll never uses VendorPayment

### Privacy & Compliance

- Payslip is guarded by owner or payroll.view
- PND1 and SSO files are review workpapers
- Current rules and filing format require operational verification
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 12_document_management_lifecycle

[เปิด diagram](12_document_management_lifecycle.html)

### Versioned Private Storage

- All uploaded documents stored in private tenant-isolated storage
- Every added file creates an append-only version record with a SHA256 checksum
- Document links use an allowlisted linkable type and an organization-scoped target

### Sensitivity & Download Gate

- Five levels: org_internal, department_restricted, finance_confidential, hr_confidential, executive_confidential
- Download checks organization, documents.download, sensitivity, clean scan status and every linked parent entity
- Creating a document link requires visibility of its allowlisted parent entity

### Retention & Scan Enforcement

- Expiry-enabled categories can notify the owner before expires_at
- Category policy calculates retention_until, default renewal and mandatory legal hold
- Scheduled enforcement quarantines failed scans and archives due documents; purge is explicit and legal hold blocks both
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 13_two_factor_auth_sequence

[เปิด diagram](13_two_factor_auth_sequence.html)

### Offline RFC 6238 TOTP Engine

- Native PHP TOTP implementation without external cloud API dependencies
- Secret keys stored with AES-256-GCM encryption in users table
- Time window verification with clock drift tolerance

### Trusted Device & Recovery

- Trusted devices are optional and use the policy duration (1-90 days)
- 8 single-use recovery codes generated during initial 2FA enrollment
- Used recovery codes are permanently consumed to prevent replay attacks

### Organization-Wide Governance

- 2FA is disabled by default; an organization can enable and require it for Owner, Admin, and Finance
- EnsureTwoFactorEnrollment middleware halts access to all business screens until enrolled
- Company Owners can securely reset 2FA credentials for locked-out employees
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 14_approval_workflow_engine

[เปิด diagram](14_approval_workflow_engine.html)

### Versioned Definition & Snapshot Integrity

- Active workflow definitions match on organization, subject type, and amount threshold [min, max]
- Submission freezes both definition configuration and subject payload into immutable JSON snapshots
- Subsequent edits to definition steps do not alter currently running workflow instances

### Segregation of Duties (SoD) & Delegations

- Requesters are strictly excluded from approval assignees to prevent self-approval deadlocks
- Assignees can be explicit users, organizational reporting managers, or privileged roles
- Active delegations allow appointed delegates to act during a configured time window without permission leakage

### Execution Modes & Boundary Transitions

- Parallel steps execute on first-response, marking remaining peer assignees as superseded
- Sequential steps require every ordered step to approve before advancing
- Final approval transitions subject status and posts double-entry GL journals (Expense) or refunds deducted balances (Leave rejection)
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 15_payment_gateway_settlement

[เปิด diagram](15_payment_gateway_settlement.html)

### Exact Satang & Intent Locking

- Invoices generate PromptPay QR Tag 29 (Tax ID) or Tag 30 (Biller ID) with exact satang amount
- Active intents are reused across Invoice Print, PDF exports, and Customer Portal sessions
- Pessimistic locking prevents duplicate intents and blocks changes to bank accounts while active

### Dual-Stage Provider Verification

- Opn webhook notifications deliver event IDs only; the server retrieves and verifies details using merchant credentials
- Provider confirmation records paid status timestamp without triggering premature accounting entries
- Test-mode transactions and creation timeouts are strictly guarded to prevent unauthorized financial postings

### Atomic Settlement & Double-Entry GL

- Settlement bridge requires valid HMAC-SHA256 signature and fresh timestamp window (<= 300s)
- Payload deduplication rejects conflicting replay events with HTTP 409 Conflict
- Successful settlement creates AR Payment receipt and posts balanced double-entry GL journal in one transaction
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 16_master_dialog_editor_lifecycle

[เปิด diagram](16_master_dialog_editor_lifecycle.html)

### Single Active Editor Architecture

- useEditorCoordinator enforces maximum 1 active editor draft across all 60 pages / 92 files
- Attempts to switch form or navigate back with unsaved changes trigger DiscardChangesDialog
- User choice 'Keep' preserves draft in memory; 'Discard' resets cleanly and opens requested editor

### Comprehensive Dirty Detection

- Tracks scalar inputs, dates, numbers, checkboxes, and barcode scanner inputs
- Multi-warehouse, lot, and bin location pickers accurately detected on value change
- Line items, indexed monetary values, and DMS file attachments tracked against initial state

### Idempotent Submission Protection

- EnsureFormSubmission middleware records submission_key and sha256 fingerprint in form_submissions
- Duplicate POST attempts return cached JSON receipt without duplicate database creation
- HTTP 422 validation errors never consume submission key, preserving draft for immediate retry
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 22_external_api_architecture

[เปิด diagram](22_external_api_architecture.html)

### มี implementation แล้ว

- GET /api/v1/summaries/{sales|finance|projects}
- Key แยกองค์กรและ scope; key เดิมไม่รับ scope ใหม่อัตโนมัติ

### ข้อจำกัดและแผนถัดไป

- HR / Employee / Inventory และ list/detail ยังเป็นแผน
- Browser UAT, Round 2 acceptance และ load SLA ยังไม่ปิด
- Baseline 2026-10-29: POS Config local; Gemini PASS 2026-10-27 covers earlier code. See diagram 23.

## 23_pos_server_configuration

[เปิด diagram](23_pos_server_configuration.html)
