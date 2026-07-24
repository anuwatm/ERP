# ERP Feature Plan

แผนนี้สรุปจากการเทียบแนวคิดของ ERP open-source หลายตัว เช่น BoomBigNose Company OS, ERPNext, IDURAR, FreshGerium, Hubleto, RKBM, Maruf ERP, Aureus ERP แล้วปรับให้เหมาะกับการทำระบบ ERP ใหม่แบบเริ่มเล็ก ใช้งานจริงได้ และค่อยขยาย

## เป้าหมายระบบ

ระบบนี้ควรเป็น `Company OS / Lightweight ERP` สำหรับธุรกิจ SME, ทีมบริการ, ทีมซอฟต์แวร์, เอเจนซี, สตูดิโอ, หรือบริษัทขนาดเล็กถึงกลาง

แกนหลัก:

```text
CRM -> Deal -> Invoice -> Payment -> Project -> Task -> Dashboard
```

แนวทางสำคัญ:

- เริ่มจาก workflow ธุรกิจจริง ไม่เริ่มจากบัญชีเต็มรูปแบบ
- ทำให้เจ้าของบริษัทเห็นภาพรวม เงินสด งาน ลูกค้า ดีล และรายได้
- รองรับทีมเล็กก่อน แล้วค่อยขยายเป็น multi-branch / multi-org
- แยกโมดูลชัดเจน เพื่อเพิ่ม feature ภายหลังง่าย
- ไม่ทำ ERP ใหญ่เกินตั้งแต่วันแรก

## ขอบเขตเวอร์ชัน

### V1: Core Company OS

V1 คือเวอร์ชันที่ควรทำก่อน เพื่อให้ใช้งานจริงได้เร็ว

- Dashboard
- CRM
- Sales / Deals
- Projects / Tasks
- Finance เบื้องต้น
- Product / Service Catalog
- Customers / Suppliers
- User / Role / Permission
- Audit Log
- Reports
- Automation เบื้องต้น
- Settings

### V2: Operations Expansion

V2 เพิ่มงานหลังบ้านที่เริ่มซับซ้อนขึ้น

- Inventory / Stock
- Purchase Order
- Supplier Billing
- HR เบื้องต้น
- Attendance / Leave
- Import / Export Excel
- Notification LINE / Email
- Accounting Integration
- AI Assistant

### V3: Advanced ERP

V3 คือส่วนที่เหมาะทำเมื่อระบบเริ่มนิ่ง

- Multi-org / Multi-branch เต็มรูปแบบ
- Manufacturing
- Payroll เต็มระบบ
- POS
- Asset Management
- Plugin System
- Workflow Builder
- Advanced BI
- Customer Portal
- API Marketplace

## Feature Priority

| Priority | ความหมาย |
| --- | --- |
| P0 | สำคัญต่อ Core Company OS (V1) — **ไม่เท่ากับต้องมีใน MVP ทุกรายการ** |
| P1 | ควรมีหลัง MVP ไม่นาน |
| P2 | ทำเมื่อ workflow หลักนิ่งแล้ว |
| P3 | ระยะยาว / enterprise |

> **Scope lock:** รายการที่ build รอบแรกดู [`MVP_SCOPE.md`](./MVP_SCOPE.md) และ [`PROJECT.md`](./PROJECT.md) เท่านั้น (AD-01).  
> ตัวอย่าง P0 ที่อยู่นอก MVP: Reports เต็ม, export PDF/CSV, Cash Balance

## 1. Dashboard

Priority: P0

หน้ารวมสถานะบริษัท ใช้ดูทุกเช้า

### คุณสมบัติ

- แสดงรายได้เดือนนี้
- แสดงค่าใช้จ่ายเดือนนี้
- แสดงกำไรขั้นต้น
- แสดงเงินสดคงเหลือ (V1+ เมื่อมี opening balance/bank recon เท่านั้น; **MVP ห้ามโชว์ Cash Balance ปลอม** — AD-02)
- แสดง invoice ที่ยังไม่ชำระ
- แสดงมูลค่า pipeline
- แสดง deal ตาม stage
- แสดง project ที่กำลังทำ
- แสดง task ที่ overdue
- แสดง follow-up ที่ต้องทำวันนี้
- แสดง top customers
- แสดง recent activity
- กรองตามช่วงเวลาได้ เช่น today, this week, this month, custom range
- export summary เป็น PDF หรือ CSV ได้ (Post-MVP)

### ข้อมูลที่ใช้

- invoices
- payments
- expenses
- deals
- projects
- tasks
- activities

### Metric หลัก

- Invoiced Revenue
- Cash In
- Outstanding AR
- Overdue AR
- Recognized Expense
- Cash Out
- Gross Profit
- Pipeline Value
- Win Rate
- Overdue Tasks
- Unpaid Invoices
- Project Margin
- Cash Balance (Post-MVP เท่านั้น เมื่อมี opening balance/bank recon)

## 2. CRM

Priority: P0

จัดการลูกค้า ผู้ติดต่อ และประวัติความสัมพันธ์

### คุณสมบัติ

- เพิ่ม แก้ไข ลบ ลูกค้า
- เพิ่มผู้ติดต่อหลายคนต่อหนึ่งลูกค้า
- เก็บข้อมูลบริษัท
- เก็บเบอร์โทร อีเมล LINE เว็บไซต์ ที่อยู่
- กำหนดประเภทลูกค้า เช่น lead, prospect, active, inactive
- กำหนด owner ผู้ดูแลลูกค้า
- บันทึก activity เช่น call, meeting, email, LINE, note
- ตั้ง follow-up date
- ดู timeline ของลูกค้า
- แนบไฟล์ (Post-MVP ยกเว้น attachment การเงินแบบจำกัด)หรือเอกสารสำคัญ
- tag ลูกค้า
- ค้นหาและกรองลูกค้า
- import/export CSV (Post-MVP)

### Field แนะนำ

- customer_code
- company_name
- tax_id
- customer_type
- status
- owner_id
- phone
- email
- line_id
- address
- source
- tags
- created_at
- updated_at

## 3. Contacts

Priority: P0

แยกคนออกจากบริษัท เพื่อรองรับลูกค้าหนึ่งรายมีหลาย contact

### คุณสมบัติ

- เพิ่ม contact ใต้ customer (supplier = Post-MVP)
- กำหนดตำแหน่งงาน
- กำหนด contact หลัก
- เก็บช่องทางติดต่อ
- บันทึก note เฉพาะคน
- ผูก contact กับ deal, project, invoice ได้

### Field แนะนำ

- customer_id
- supplier_id
- name
- position
- phone
- email
- line_id
- is_primary
- note

## 4. Leads / Deals / Sales Pipeline

Priority: P0

จัดการโอกาสขายตั้งแต่ lead ถึงปิดการขาย

### คุณสมบัติ

- สร้าง lead หรือ deal
- แสดง pipeline board แบบ kanban
- กำหนด stage เช่น new, contacted, proposal, negotiation, won, lost
- กำหนดมูลค่าดีล
- กำหนด probability
- กำหนด expected close date
- assign owner
- ผูกกับ customer/contact
- บันทึก activity และ follow-up
- สร้าง quotation จาก deal (Post-MVP)
- เมื่อ deal won ให้สร้าง project ได้
- เมื่อ deal lost ให้ใส่ reason
- รายงาน conversion rate (Post-MVP)
- รายงาน pipeline value (Dashboard mini ใน MVP; reports เต็ม = Post-MVP)

### Stage เริ่มต้น

- New
- Contacted
- Qualified
- Proposal
- Negotiation
- Won
- Lost

### Field แนะนำ

- title
- customer_id
- contact_id
- stage
- value_amount
- probability
- expected_close_date
- owner_id
- source
- lost_reason
- note

## 5. Quotations

Priority: P1

ใบเสนอราคาแบบง่าย ก่อนออก invoice

### คุณสมบัติ

- สร้าง quotation จาก deal (Post-MVP)
- เพิ่มรายการสินค้า/บริการ
- ใส่ quantity, unit price, discount, tax
- คำนวณ subtotal, tax, total
- กำหนดวันหมดอายุ
- สถานะ draft, sent, accepted, rejected, expired
- export PDF (Post-MVP)
- ส่ง link ให้ลูกค้า
- convert เป็น invoice หรือ project ได้
- versioning เบื้องต้น

### Field แนะนำ

- quotation_no
- customer_id
- deal_id
- status
- issue_date
- valid_until
- subtotal
- discount
- tax
- total
- notes

## 6. Projects

Priority: P0

จัดการงานส่งมอบหลังปิดการขาย

### คุณสมบัติ

- สร้าง project จาก deal won
- กำหนด project owner
- กำหนด customer
- กำหนด start date / due date
- กำหนด budget
- กำหนด status เช่น planned, active, on_hold, completed, cancelled
- ดู project board
- แสดง progress
- ผูก invoice กับ project
- ผูก cost/expense กับ project
- คำนวณ project margin
- เก็บ milestone (Post-MVP)
- เก็บ document / link ที่เกี่ยวข้อง (Post-MVP ยกเว้น attachment การเงินแบบจำกัด)

### Field แนะนำ

- project_code
- name
- customer_id
- deal_id
- owner_id
- status
- start_date
- due_date
- budget_amount
- progress_percent
- description
- (project cost = derived จาก expenses; ห้าม cache `actual_cost` — AD-05)

## 7. Tasks

Priority: P0

งานย่อยใน project หรือ internal work

### คุณสมบัติ

- สร้าง task ใน project
- assign คนรับผิดชอบ
- กำหนด priority
- กำหนด status
- กำหนด due date
- checklist ย่อย
- comment / activity
- แนบไฟล์ (Post-MVP ยกเว้น attachment การเงินแบบจำกัด)
- mark overdue อัตโนมัติ
- recurring task ในอนาคต (Post-MVP)
- board view และ list view

### Status เริ่มต้น

- Todo
- In Progress
- Review
- Done
- Blocked

### Field แนะนำ

- title
- project_id (nullable; Phase 4 link)
- assignee_id
- status
- priority
- due_date
- completed_at
- description

## 8. Milestones

Priority: P1

จุดส่งมอบหลักใน project

### คุณสมบัติ

- สร้าง milestone ใน project
- กำหนด due date
- กำหนด payment milestone ได้
- ผูกกับ invoice ได้
- แสดงสถานะ complete / incomplete
- ใช้คำนวณ project progress

## 9. Finance: Invoices

Priority: P0

ออก invoice สำหรับงานขายหรือ project

### คุณสมบัติ

- Phase 3: สร้าง invoice จาก deal หรือสร้างเอง; Phase 4 ค่อยผูก project (quotation = Post-MVP)
- เลข invoice อัตโนมัติ
- รายการสินค้า/บริการหลายบรรทัด
- discount
- tax
- due date
- สถานะ draft, sent, partially_paid, paid, overdue, void
- export PDF (Post-MVP)
- export CSV (Post-MVP)
- บันทึกการชำระเงิน
- แจ้งเตือน invoice ใกล้ครบกำหนด (Post-MVP)
- aging view ใน invoice list (reports เต็ม = Post-MVP)
- ป้องกันแก้ไข invoice ที่ paid แล้วแบบไม่มี audit

### Field แนะนำ

- invoice_no
- customer_id
- project_id (nullable; Phase 4 link)
- quotation_id
- status
- issue_date
- due_date
- subtotal
- discount
- tax
- total
- paid_amount
- balance_due
- notes

## 10. Payments

Priority: P0

บันทึกเงินเข้า

### คุณสมบัติ

- บันทึก payment ให้ invoice
- รองรับ partial payment
- payment method เช่น bank transfer, cash, credit card, promptpay
- แนบหลักฐานการโอน
- payment date
- auto update invoice status
- payment report (Post-MVP; Dashboard mini ใช้ Cash In ใน MVP)

### Field แนะนำ

- invoice_id
- amount
- payment_date
- payment_method
- reference_no
- attachment_url
- note

## 11. Expenses / Costs

Priority: P0

บันทึกค่าใช้จ่ายบริษัทและต้นทุน project

### คุณสมบัติ

- เพิ่ม expense
- เลือก category
- ผูกกับ project ได้
- ผูกกับ supplier ได้ (Post-MVP)
- แนบ receipt
- สถานะ draft, approved, paid
- monthly expense report (Post-MVP; Dashboard mini ใช้ Recognized Expense/Cash Out ใน MVP)
- project cost report (Post-MVP; project page ใช้ derived cost ใน MVP)
- expense by category chart (Post-MVP)

### Category เริ่มต้น

- Salary
- Software
- Marketing
- Travel
- Office
- Contractor
- Hosting
- Misc

## 12. Product / Service Catalog

Priority: P0

รายการสินค้าหรือบริการที่ใช้ใน quote/invoice

### คุณสมบัติ

- เพิ่มสินค้า/บริการ
- กำหนด type: product, service, package
- กำหนด price
- กำหนด cost
- กำหนด unit
- เปิด/ปิดใช้งาน
- category
- SKU optional
- ใช้ซ้ำใน invoice (quotation = Post-MVP)

### Field แนะนำ

- sku
- name
- type
- category
- unit
- price
- cost
- is_active

## 13. Suppliers

Priority: P1

จัดการผู้ขาย/คู่ค้า

### คุณสมบัติ

- เพิ่ม supplier
- เก็บ contact
- เก็บที่อยู่
- เก็บ payment terms
- ผูกกับ expense
- ผูกกับ purchase order ใน V2
- supplier report

## 14. Purchase Orders

Priority: P2

เริ่มทำเมื่อมีการซื้อของหรือจ้าง supplier บ่อย

### คุณสมบัติ

- สร้าง purchase order
- เลือก supplier
- เพิ่มรายการสินค้า/บริการ
- สถานะ draft, sent, approved, received, cancelled
- ผูกกับ expense
- ผูกกับ inventory ใน V2
- export PDF (Post-MVP)

## 15. Inventory / Stock

Priority: P2

สำหรับธุรกิจที่มีสินค้า

### คุณสมบัติ

- สินค้าคงคลัง
- stock in / stock out
- warehouse
- low stock alert
- stock adjustment
- barcode / QR code
- damage management
- inventory valuation
- stock movement history

### ยังไม่ต้องทำใน V1

ถ้าธุรกิจหลักเป็นบริการ ให้เลื่อน inventory ไป V2

## 16. HR: Employees

Priority: P1

จัดการข้อมูลพนักงาน

### คุณสมบัติ

- เพิ่มพนักงาน
- ข้อมูลติดต่อ
- ตำแหน่ง
- department
- branch
- employment status
- start date
- salary base optional
- role mapping กับ user account
- employee document

## 17. Attendance / Leave

Priority: P2

ระบบเวลาและลา

### คุณสมบัติ

- clock in / clock out
- leave request
- leave approval
- leave balance
- attendance report
- calendar view

## 18. Payroll

Priority: P3

ควรทำหลัง HR นิ่งแล้ว

### คุณสมบัติ

- salary calculation
- allowance
- deduction
- payslip
- payroll approval
- payroll report

### หมายเหตุ

ไม่ควรทำ payroll เต็มในช่วงแรก เพราะมีกฎหมายและภาษีเกี่ยวข้อง

## 19. User / Role / Permission

Priority: P0

ระบบสิทธิ์พื้นฐาน

### Role เริ่มต้น

- Owner
- Admin
- Sales
- Project Manager
- Finance
- Member
- Viewer

### คุณสมบัติ

- login/logout
- invite user
- assign role
- active/inactive user
- permission per module
- permission per action: view, create, update, delete, approve, export
- owner/admin เห็น audit
- member เห็นเฉพาะงานตัวเองได้

## 20. Organization / Branch / Division / Department

Priority: P0 (MVP foundation)

รองรับโครงสร้างบริษัท: บริษัท -> สาขา -> ฝ่าย -> แผนก -> ผู้ใช้งาน

### คุณสมบัติ

- company profile
- branch
- department
- team
- assign user to branch/division/department
- filter dashboard by branch/division/department (Post-MVP สำหรับ reports เต็ม)
- invoice numbering per branch optional

## 21. Audit Log

Priority: P0

บันทึกความเคลื่อนไหวสำคัญ

### คุณสมบัติ

- บันทึก create/update/delete
- บันทึก user
- บันทึก timestamp
- บันทึก entity type และ entity id
- บันทึก before/after เฉพาะ field สำคัญ
- filter by user/module/date
- export ได้เฉพาะ admin (Post-MVP)

### Event ที่ควรเก็บ

- login
- create customer
- update deal
- change invoice status
- record payment
- delete task
- approve expense
- change permission

## 22. Reports

Priority: P0

รายงานเพื่อช่วยตัดสินใจ

### รายงาน V1

- Revenue report
- Expense report
- Profit report
- Invoice aging
- Unpaid invoices
- Pipeline report
- Deal win/loss report
- Project profitability
- Task overdue report
- Customer activity report

### คุณสมบัติ

- filter by date
- filter by customer
- filter by owner
- export CSV (Post-MVP)
- export PDF (Post-MVP)
- saved views ใน V2

## 23. Automation

Priority: P1

ลดงานซ้ำ

### คุณสมบัติ

- reminder ตาม follow-up date
- reminder invoice due date
- webhook endpoint
- cron job
- outbound event queue
- notification via email / LINE ใน V2
- automation log

### ตัวอย่าง automation

- ถ้า invoice overdue ให้สร้าง reminder
- ถ้า task due วันนี้ แจ้ง assignee
- ถ้า deal ไม่มี activity 7 วัน แจ้ง owner
- ถ้า payment complete ให้ update invoice status

## 24. AI Assistant

Priority: P2

เพิ่มเมื่อข้อมูลเริ่มเยอะ

### คุณสมบัติ

- summarize deal
- draft follow-up message
- summarize meeting notes
- suggest next action
- generate project brief from deal
- classify customer notes
- search knowledge base

### ข้อควรระวัง

- ต้องมี fallback ถ้าไม่มี API key
- ห้ามส่งข้อมูล sensitive โดยไม่แจ้ง
- ควร log การใช้งาน AI

## 25. Files / Documents

Priority: P1

เอกสารแนบกับลูกค้า งาน และการเงิน

### คุณสมบัติ

- upload file
- attach to customer/deal/project/invoice/expense
- preview basic
- file permission
- file activity log
- file category

## 26. Notifications

Priority: P1

แจ้งเตือนสิ่งที่ต้องทำ

### คุณสมบัติ

- in-app notification
- unread count
- email notification
- LINE notification ใน V2
- notification preferences
- mark read

### Event ที่ควรแจ้ง

- assigned task
- task overdue
- deal follow-up due
- invoice overdue
- expense approval needed
- payment received

## 27. Settings

Priority: P0

ตั้งค่าระบบ

### คุณสมบัติ

- company profile
- logo
- address
- tax id
- currency
- timezone
- invoice numbering
- quotation numbering
- default payment terms
- roles and permissions
- webhook secret
- email sender settings
- integration settings

## 28. Accounting Integration

Priority: P2

เชื่อมระบบบัญชีภายนอก ไม่ทำบัญชีภาษีเต็มเองตอนแรก

### ระบบที่ควรเชื่อม

- FlowAccount
- PEAK
- Xero
- QuickBooks optional

### คุณสมบัติ

- sync customer
- sync invoice
- sync payment
- sync expense
- sync status
- mapping account/category
- sync log
- retry failed sync

### ขอบเขต

ระบบเราเป็น operational layer ส่วนบัญชีภาษีให้ external accounting system เป็น book of record

## 29. Import / Export

Priority: P1

ย้ายข้อมูลเข้าออกง่าย

### คุณสมบัติ

- import customers CSV
- import products CSV
- import invoices CSV
- validate data before import
- preview before confirm
- export CSV (Post-MVP)
- export Excel ใน V2
- export PDF (Post-MVP) สำหรับ invoice/quotation/report (Post-MVP)
- ป้องกัน CSV formula injection

## 30. API

Priority: P1

รองรับ integration และ automation

### คุณสมบัติ

- REST API หรือ RPC endpoint
- API token
- webhook
- rate limit
- audit API usage
- API docs

### Endpoint หลัก

- customers
- contacts
- deals
- projects
- tasks
- invoices
- payments
- expenses
- products
- reports

## 31. Customer Portal

Priority: P3

ให้ลูกค้าดูเอกสารเอง

### คุณสมบัติ

- login ลูกค้า
- view quotations
- approve quotation
- view invoices
- upload file
- comment ใน project
- payment link

## Data Model เบื้องต้น

ตารางหลักที่ควรมี:

- organizations
- branches
- departments
- users
- roles
- permissions
- customers
- contacts
- suppliers
- products
- deals
- deal_activities
- quotations
- quotation_items
- projects
- milestones
- tasks
- invoices
- invoice_items
- payments
- expenses
- files
- notifications
- audit_logs
- automation_rules
- webhook_events
- settings

ทุกตารางหลักควรมี:

- id
- org_id
- created_at
- updated_at
- created_by
- updated_by

## Security Requirements

### ต้องมี

- Authentication
- Role-based access control
- Row-level organization isolation
- Audit log
- Secure env handling
- Server-side permission check
- Input validation
- File upload validation
- Rate limit endpoint สำคัญ

### ควรมี

- 2FA สำหรับ admin
- Email confirmation
- Password policy
- Session management
- API token rotation
- Backup policy

## MVP User Flow

### Flow 1: Lead to Cash

```text
Create Customer
-> Create Deal
-> Add Activities
-> Move Deal to Won
-> Create Invoice (deal/manual; project optional later)
-> Record Payment
-> Dashboard Updated
```

### Flow 2: Project Delivery

```text
Create Project from won deal or manual
-> Link existing invoice/expense if needed
-> Add Tasks
-> Assign Team
-> Track Progress
-> Add Costs
-> Complete Project
-> Review Profitability
```

### Flow 3: Finance Tracking

```text
Create Invoice
-> Send Invoice
-> Follow Due Date
-> Record Payment
-> Track Unpaid
-> Dashboard Updated / Track Unpaid
```

## MVP Screens

หน้าที่ต้องมีใน **MVP** (ตาม `MVP_SCOPE.md`):

- Login / Register / Invite accept
- Dashboard ตาม Phase (Admin/Sales/Finance/Delivery/Executive — ไม่มี Cash Balance จริง)
- Customers / Customer Detail
- Contacts
- Deals Pipeline / Deal Detail
- Projects / Project Detail
- Tasks
- Invoices / Invoice Detail
- Payments
- Expenses
- Products / Services
- Audit Log
- Settings
- Users / Roles

หน้า **V1 หลัง MVP** (P0 backlog):

- Reports (เต็ม)
- Export PDF/CSV
- Files module เต็ม (MVP อาจแนบไฟล์ payment แบบจำกัดได้ตาม implement)

## Not In Scope for V1

สิ่งที่ยังไม่ควรทำ:

- Manufacturing
- Payroll เต็มระบบ
- Tax invoice ตามกฎหมาย
- POS เต็ม
- Asset depreciation
- Accounting ledger ลึก
- Plugin marketplace
- Native mobile app
- Complex workflow builder
- Multi-currency accounting

## Recommended Build Order

### Phase 1: Foundation

1. Auth
2. Organization
3. User / Role
4. Layout / Navigation
5. Audit Log
6. Admin Dashboard

### Phase 2: CRM and Sales

1. Customers
2. Contacts
3. Deals
4. Activities
5. Follow-up
6. Sales Dashboard

### Phase 3: Finance

1. Products / Services
2. Invoices
3. Payments
4. Expenses
5. Finance Dashboard

### Phase 4: Delivery

1. Projects
2. Tasks
3. Milestones (Post-MVP)
4. Delivery Dashboard

### Phase 5: Executive Dashboard and UAT

1. Executive Dashboard summary
2. End-to-end tests
3. UAT dataset
4. Permission/widget visibility verification
5. Bug fix / polish before MVP release

### Post-MVP Backlog

1. Reminders
2. Webhooks
3. Notifications
4. Export CSV/PDF
5. AI assistant optional

## Success Criteria

MVP ถือว่าสำเร็จถ้า:

- เจ้าของบริษัทเห็น pipeline, project, Cash In และ invoice ได้ใน dashboard เดียว
- ทีมขายสร้าง deal และ follow-up ได้
- ทีมส่งมอบเห็น project/task และ deadline ได้
- ฝ่ายการเงินออก invoice และบันทึก payment ได้
- มี Dashboard ตาม Phase สำหรับ revenue/cash/AR/expense/pipeline/project/task
- มี audit log สำหรับ action สำคัญ
- Export ข้อมูลหลักอยู่ใน V1 หลัง MVP
- permission พื้นฐานทำงานถูกต้อง

### V1 หลัง MVP ถือว่าสำเร็จถ้า:

- มี reports เต็ม
- export PDF/CSV ได้
- มี notifications/files/automation ตาม backlog ที่เลือก

## Reference Repositories

- https://github.com/Boom-Vitt/boombignose-erp
- https://github.com/frappe/erpnext
- https://github.com/idurar/idurar-erp-crm
- https://github.com/hossainchisty/FreshGerium-ERP-Platform
- https://github.com/hubleto/erp
- https://github.com/auroravirtuoso/erp-crm
- https://github.com/nareshkumaralaria/rkbm-erp-software
- https://github.com/maruf-pfc/erp-system
- https://github.com/aureuserp/aureuserp










