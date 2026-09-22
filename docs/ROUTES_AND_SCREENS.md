# Routes and Screens by Phase

เอกสารนี้เป็น route/screen reference ของ implementation ปัจจุบัน. Source of truth คือ `backend/routes/web.php`; route ที่เป็น write action ต้องผ่าน permission, password confirmation และ throttle ตามที่ประกาศใน source.

## Route Style

- ใช้ Laravel web routes + Inertia pages.
- ทุก route หลัง login ต้องผ่าน auth, active user, org active และ permission middleware.
- API แยกเต็มรูปแบบยังไม่ทำใน MVP.

## Phase 1: Foundation

| Screen | Route | Permission |
| --- | --- | --- |
| Login | `GET /login` | public |
| Register | `GET /register` | public |
| Forgot/Reset Password | Breeze default | public |
| Invite Accept | `GET /invite/{user}/{token}` | public token validation |
| Admin Dashboard | `GET /dashboard` | `dashboard.view` |
| Organization Settings | `GET /settings/organization` | `settings.organization.view` |
| Branch/Division/Department read-only | `GET /settings/organization-structure` | `settings.structure.view` |
| Users | `GET /users` | `users.view` |
| Invite User | `POST /users/invite` | `users.create` |
| Update User | `PATCH /users/{user}` | `users.update` |
| Roles/Permissions | `GET /roles` | `roles.view` |
| Audit Log | `GET /audit-logs` | `audit.view` |


## Phase 1.1: Admin Master Data & Access Management

| Screen / Action | Route | Permission |
| --- | --- | --- |
| Organization Structure | `GET /settings/organization-structure` | `settings.structure.view` |
| Create Branch | `POST /settings/branches` | `settings.structure.update` |
| Update Branch | `PATCH /settings/branches/{branch}` | `settings.structure.update` |
| Disable Branch | `PATCH /settings/branches/{branch}/disable` | `settings.structure.update` |
| Set Head Office Branch | `PATCH /settings/branches/{branch}/head-office` | `settings.structure.update` |
| Delete Branch | `DELETE /settings/branches/{branch}` | `settings.structure.update` |
| Create Division | `POST /settings/divisions` | `settings.structure.update` |
| Update Division | `PATCH /settings/divisions/{division}` | `settings.structure.update` |
| Disable Division | `PATCH /settings/divisions/{division}/disable` | `settings.structure.update` |
| Delete Division | `DELETE /settings/divisions/{division}` | `settings.structure.update` |
| Create Department | `POST /settings/departments` | `settings.structure.update` |
| Update Department | `PATCH /settings/departments/{department}` | `settings.structure.update` |
| Disable Department | `PATCH /settings/departments/{department}/disable` | `settings.structure.update` |
| Delete Department | `DELETE /settings/departments/{department}` | `settings.structure.update` |
| Users List | `GET /users` | `users.view` |
| Invite User | `POST /users/invite` | `users.create` |
| Update User | `PATCH /users/{user}` | `users.update` |
| Disable User | `PATCH /users/{user}/disable` | `users.disable` |
| Re-enable User | `PATCH /users/{user}/enable` | `users.disable` |
| Roles Catalog / Matrix | `GET /roles` | `roles.view` |
| Create Role | `POST /roles` | `roles.manage` |
| Update Role | `PATCH /roles/{role}` | `roles.manage` |
| Update Role Permissions | `PATCH /roles/{role}/permissions` | `roles.manage` |
| Assign User Role | `PATCH /users/{user}/role` | `roles.update` |

## Phase 2: CRM / Sales

| Screen / Action | Route | Permission |
| --- | --- | --- |
| Sales Dashboard | `GET /sales-dashboard` | `sales.dashboard.view` |
| Customers List / Form | `GET /customers` | `customers.view` |
| Create Customer | `POST /customers` | `customers.create` |
| Update Customer | `PATCH /customers/{customer}` | `customers.update` |
| Delete-safe Customer | `DELETE /customers/{customer}` | `customers.delete` |
| Create Contact under Customer | `POST /customers/{customer}/contacts` | `contacts.create` |
| Update Contact | `PATCH /contacts/{contact}` | `contacts.update` |
| Delete-safe Contact | `DELETE /contacts/{contact}` | `contacts.delete` |
| Deals Pipeline / Form | `GET /deals` | `deals.view` |
| Create Deal | `POST /deals` | `deals.create` |
| Update Deal / Stage | `PATCH /deals/{deal}` | `deals.update` |
| Create Activity / Follow-up | `POST /activities` | `activities.create` |
| Update Activity | `PATCH /activities/{activity}` | `activities.update` |
| Complete Follow-up | `PATCH /activities/{activity}/complete` | `activities.update` |

## Phase 3: Finance

| Screen | Route | Permission |
| --- | --- | --- |
| Finance Dashboard | `GET /finance-dashboard` | `expenses.view` |
| Products/Services | `GET /products` | `products.manage` |
| Invoices List | `GET /invoices` | `invoices.view` |
| Invoice create/edit | Inertia action on `/invoices` | `invoices.create/update` |
| Record Payment | `POST /invoices/{invoice}/payments` | `payments.create` |
| Reverse Payment | `POST /payments/{payment}/reverse` | `payments.reverse` + reauth |
| Expenses | `GET /expenses` | `expenses.view` |
| Approve/Pay Expense | `POST /expenses/{expense}/approve`, `POST /expenses/{expense}/pay` | `expenses.approve/pay` + reauth |

## Phase 4: Delivery

| Screen | Route | Permission |
| --- | --- | --- |
| Delivery Dashboard | `GET /dashboard` | `dashboard.view` |
| Projects List | `GET /projects` | `projects.view` |
| Project create/edit | Inertia action on `/projects` | `projects.create/update` |
| Tasks Board/List | `GET /tasks` | `tasks.view` |
| Task create/edit | Inertia action on `/tasks` | `tasks.create/update` |
| Link Invoice to Project | invoice edit/detail action | `invoices.update` |

**Member navigation:** Member ไม่เห็น Projects List ใน MVP. หลัง login ให้เข้า Tasks Board/List เป็นหลัก; จาก Task Detail แสดงข้อมูล project/customer แบบ linked read-only เท่าที่จำเป็น และห้ามพาไปหน้า project detail ที่จะ 403.

## Phase 5: UAT / Summary

| Screen | Route | Permission |
| --- | --- | --- |
| Executive Dashboard | `GET /dashboard` | `dashboard.view` |
| UAT Demo Flow | seed/demo data only | Owner/Admin |

## Phase 6: Reporting / Filters / Operational Polish

| Screen | Route | Permission |
| --- | --- | --- |
| Executive Dashboard Filters | `GET /dashboard?month=YYYY-MM` | `dashboard.view` |
| Finance Dashboard Filters | `GET /finance-dashboard?month=YYYY-MM` | `expenses.view` |
| Delivery Dashboard Filters | `GET /delivery-dashboard?month=YYYY-MM` | `dashboard.view` |

## Phase 23.2.5: Additional dashboards

| Screen | Route | Permission |
| --- | --- | --- |
| My Work | `GET /my-work` | `tasks.view`; assigned tasks only |
| Procurement / Inventory Dashboard | `GET /procurement-dashboard` | At least one of `purchase_orders.view`, `inventory.view`, `workflows.approve`; each widget checks its source permission |
| HR / Payroll Dashboard | `GET /hr-dashboard` | `payroll.view` or both `hr.manage` and `hr.self.view`; each widget checks its source permission |
| Vendor Payments (Finance cash-out drill-down) | `GET /vendor-payments?from=YYYY-MM-DD&to=YYYY-MM-DD` | `expenses.view`; read-only and organization-scoped |
| External Portal Dashboard | `GET /portal` | Valid scoped portal session; customer/supplier party grant, separate from staff dashboards |

All dashboard list links use GET filters on the destination; create/edit and transactional actions remain on the destination module. See `docs/PHASE_23_2_6_INTEGRATION.md`.

## Phase 7: Post-MVP Features

| Screen | Route | Permission |
| --- | --- | --- |
| Organization Numbering Settings | `GET /settings/organization`, `PATCH /settings/organization/numbering` | `settings.organization.view/update` |
| Suppliers | `GET /suppliers` | `suppliers.view` |
| Supplier Create/Edit/Delete | `POST /suppliers`, `PATCH /suppliers/{supplier}`, `DELETE /suppliers/{supplier}` | `suppliers.create/update/delete` |
| Purchase Orders | `GET /purchase-orders` | `purchase_orders.view` |
| Purchase Order Create/Edit | `POST /purchase-orders`, `PATCH /purchase-orders/{purchaseOrder}` | `purchase_orders.create/update` |
| Purchase Order Approve/Cancel | `POST /purchase-orders/{purchaseOrder}/approve`, `POST /purchase-orders/{purchaseOrder}/cancel` | `purchase_orders.approve/cancel` |
| Project Members | `POST /projects/{project}/members`, `DELETE /projects/{project}/members/{member}` | `projects.update` |

## Phase 8: Production Roadmap

| Screen | Route | Permission |
| --- | --- | --- |
| Invoice Official Print | `GET /invoices/{invoice}/print` | `invoices.view` |
| Invoice PDF Export | `GET /invoices/{invoice}/pdf` | `invoices.view` |
| Purchase Order Official Print | `GET /purchase-orders/{purchaseOrder}/print` | `purchase_orders.view` |
| Purchase Order PDF Export | `GET /purchase-orders/{purchaseOrder}/pdf` | `purchase_orders.view` |
| WHT 50-Tawi Certificate | `GET /expenses/{expense}/withholding-certificate` | `expenses.view` |
| Tax Reports | `GET /tax-reports` | `tax_reports.view` |
| Tax Reports CSV Export | `GET /tax-reports/{type}/export` | `tax_reports.view` |
| Tax Reports Excel-compatible Export | `GET /tax-reports/{type}/excel` | `tax_reports.view` |
| Goods Receipt List / GRN | `GET /goods-receipts` | `inventory.view` |
| Goods Receipt Create | `POST /goods-receipts` | `inventory.receive` |
| Stock Movement Adjustment / Return | `POST /stock-movements` | `inventory.adjust` |
| Notification Settings | `GET /settings/notifications`, `PATCH /settings/notifications`, `PATCH /settings/notifications/external-preferences` | authenticated user; personal preferences |
| Notification Channel Administration | `POST/PATCH /settings/notification-channels`, `POST /settings/notification-channels/{channel}/test` | `settings.organization.update` + reauth |

## Phase 16B: Payroll, Social Security & Tax

| Screen / Action | Route | Permission |
| --- | --- | --- |
| Payroll workspace | `GET /payroll` | `payroll.view` |
| Save employee payroll profile | `POST /payroll/profiles` | `payroll.manage` + reauth |
| Save new effective-dated policy version | `POST /payroll/policies` | `payroll.manage` + reauth |
| Create payroll run | `POST /payroll/runs` | `payroll.manage` + reauth |
| Calculate payroll run | `POST /payroll/runs/{payrollRun}/calculate` | `payroll.manage` + reauth |
| Approve and post GL | `POST /payroll/runs/{payrollRun}/approve` | `payroll.approve` + reauth |
| Mark paid and post settlement | `POST /payroll/runs/{payrollRun}/pay` | `payroll.pay` + reauth |
| Workpaper CSV | `GET /payroll/runs/{payrollRun}/exports/{type}` | `payroll.export` |
| Payslip print/PDF | `GET /payroll/payslips/{payrollItem}/print` or `/pdf` | authenticated owner or `payroll.view` |

## Phase 9-15, 17-18: Current operational routes

| Screen / Action | Route | Permission |
| --- | --- | --- |
| Quotations | `GET /quotations`, create/update/convert actions | `quotations.*` |
| Treasury accounts and statements | `GET /bank-accounts`, `GET /bank-statements`, import/match actions | `treasury.accounts.*`, `treasury.reconciliation.*` |
| General Ledger | `GET /general-ledger` and reporting actions | `accounting.*` |
| Commercial documents | `GET /commercial-documents`, print/PDF and create actions | document-specific permission |
| E-Tax | `GET /e-tax`, generate/download/submit/RD prep actions | `e_tax.*` |
| Fixed assets | `GET /fixed-assets`, category/depreciate/dispose actions | `fixed_assets.*` |
| Currency and FX | `GET /currencies`, rate/revaluation actions | `fx.*` |
| Inventory operations | `GET /inventory-operations`, `/inventory-scan`, warehouse/bin/lot/transfer/count actions | `inventory.*` |
| DMS workspace | `GET /documents`, upload/version/link/category/retention-policy actions | `documents.*` |
| 2FA setup/challenge | `GET|POST /two-factor/setup`, `GET|POST /two-factor-challenge` | authenticated + password confirm for setup; pending-login guest for challenge |
| Organization 2FA policy | `PATCH /settings/organization/two-factor` | `settings.organization.update` + password confirm |
| Phase 19: HR & Attendance | `GET /hr/attendance`, clock-in/out, leave requests, summary locks | `attendance.*` |
| Phase 20: Workflow definitions & inbox | `GET /workflows`, step config, delegations, `/workflows/inbox` actions | `workflows.*` |

## Phase 22: Customer & Supplier Self-Service Portal

| Screen / Action | Route | Access |
| --- | --- | --- |
| Portal access link consumption | `GET /portal/access/{token}` | one-time Magic Link, throttled |
| Portal sign-in page | `GET /portal/sign-in` | public guest page |
| Portal dashboard | `GET /portal` | valid scoped portal session |
| Portal sign out | `POST /portal/logout` | valid portal session; revokes session |
| Customer quotation acceptance | `POST /portal/quotations/{quotation}/accept` | scoped customer session, throttled |
| Supplier vendor bill intake | `POST /portal/vendor-bills` | scoped supplier session, throttled |
| Private linked document download | `GET /portal/document-versions/{version}/download` | scoped portal session; clean scan only |

## Empty States

Phase 23 payment screens:

| Screen / Action | Route | Access |
| --- | --- | --- |
| Payment gateway settings / event review | `GET /settings/payment-gateway` | `settings.organization.view` |
| Configure receiver / signing secret | `PUT /settings/payment-gateway` | `settings.organization.update`, reauth, throttle |
| Customer invoice payment QR | `GET /invoice-payment/{invoice}` | scoped portal customer or verified staff with `invoices.view` |
| Signed settlement bridge | `POST /api/gateway/{config}/settlement` | HMAC + timestamp + throttle; not a native provider webhook |
| Opn event verification | `POST /api/gateway/{config}/opn` | Server-side merchant API lookup; throttle; provider confirmation only, no receipt/GL |

See `PHASE_23_PAYMENT_GATEWAY.md` for the integration contract and remaining provider/MySQL release gates.

## Phase 23.1 Master Dialog Standardization

Create/Edit entry points ทั้งหมด 60 หน้า 92 ไฟล์ได้รับการปรับปรุงสู่มาตรฐาน `CreateEditDialog` และ `useEditorCoordinator` กลาง:

- **Single Active Editor Rule:** หน้าจอ Master (Inventory, Payroll, Treasury ฯลฯ) อนุญาตให้เปิด editor ได้ครั้งละ 1 รายการ พร้อมระบบ Discard Guard เมื่อมี draft ค้าง
- **Idempotent Submission:** แบบฟอร์มทุกตัวรองรับ `submission_key` ป้องกัน double-submission และรองรับ safe retry
- **Documented Exceptions:**
  - `Profile/Edit` (UpdateProfileInformation, UpdatePassword, DeleteUser) คงสถานะ Standalone account settings page พร้อม re-auth / 2FA flow เดิม
  - `Settings/Organization` และ `Settings/NotificationPreferences` คงสถานะ in-page tab forms
  - `BankStatements.import` และ `ETax.generate` คง specialized action dialog flow
- **Form Inventory & Map:** ดูรายละเอียดรายฟอร์ม 91 รายการได้ใน `docs/PHASE_23_1_FORM_INVENTORY.md`

## Phase 23.3: HR Employee, Roster & Attendance Import (Complete & Closed)

| Screen / Action | Route | Permission |
| --- | --- | --- |
| Employee Directory | `GET /hr/employees` | `hr.employees.view` |
| Create / update Employee | `POST/PATCH /hr/employees...` | `hr.employees.manage` + password confirm |
| Link Employee account | `PATCH /hr/employees/account/{employee}` | `hr.employees.manage` + password confirm |
| Position Master List | `GET /hr/positions` | `hr.employees.view` |
| Create / update Position | `POST /hr/positions` | `hr.employees.manage` |
| Bulk Employee Import | `POST /hr/employees/import` | `hr.employees.manage` |
| Roster Matrix Calendar | `GET /hr/roster` | `hr.shifts.manage` |
| Schedule / batch roster | `POST /hr/roster/schedules`, `POST /hr/roster/batch` | `hr.shifts.manage` + password confirm |
| Attendance import / template | `GET /hr/attendance-import`, `/template` | `hr.attendance.import` |
| Import (.xlsx, .xls, .xml, .csv) | `POST /hr/attendance-import` | `hr.attendance.import` + password confirm |
| Reconcile (Overnight + 1.5x/2x/3x OT) | `POST /hr/attendance-import/reconcile` | `hr.attendance.import` + password confirm |
| Attendance Correction | `POST /hr/attendance-import/{attendance}/correct` | `hr.attendance.import` + password confirm |
| Email payslip (Non-User direct) | `POST /payroll/payslips/{payrollItem}/email` | `payroll.manage` + password confirm |

## Phase 24: e-Tax Invoice & RD Prep Gateway (Complete & Closed)

| Screen / Action | Route | Permission |
| --- | --- | --- |
| e-Tax Dashboard & Documents | `GET /e-tax` | `e_tax.view` |
| Update e-Tax Configuration | `PATCH /e-tax/config` | `e_tax.manage` + password confirm |
| Generate e-Tax Document | `POST /e-tax/documents` | `e_tax.generate` |
| Sign XML-DSig | `POST /e-tax/documents/{document}/sign` | `e_tax.sign` |
| Submit to Gateway / RD | `POST /e-tax/documents/{document}/submit` | `e_tax.submit` |
| Download Signed XML / PDF | `GET /e-tax/documents/{document}/download` | `e_tax.view` |
| Inquire RD Status | `POST /e-tax/documents/{document}/inquire` | `e_tax.view` |
| Export RD Prep Archive | `GET /e-tax/rd-prep` | `e_tax.view` |
| Inbound Webhook Callback | `POST /api/e-tax/{config}/callback` | Public (HMAC-SHA256 signature verified) |

## Phase 25: Cash Flow Forecasting & Financial Health Analytics (Complete & Closed)

| Screen / Action | Route | Permission |
| --- | --- | --- |
| Cash Flow Dashboard & Trajectory | `GET /finance/cash-flow` | `cash_flow.view` |
| Update Simulation Scenario (Base/Best/Worst) | `PATCH /finance/cash-flow/scenarios/{scenario}` | `cash_flow.manage` |
| Manual Snapshot Generation | `POST /finance/cash-flow/snapshot` | `cash_flow.manage` |

## Phase 26: AI OCR Document Ingestion & Verification (Complete & Closed)

| Screen / Action | Route | Permission |
| --- | --- | --- |
| OCR Queue & Processing Center | `GET /finance/ocr` | `ocr.view` |
| Upload Receipt / Invoice (Image or PDF) | `POST /finance/ocr/upload` | `ocr.upload` |
| Split-Screen Reviewer | `GET /finance/ocr/{extraction}` | `ocr.view` |
| Convert to Draft Expense | `POST /finance/ocr/{extraction}/convert-expense` | `ocr.convert` (Human-in-the-Loop, No Auto-GL) |
| Convert to Draft Vendor Bill | `POST /finance/ocr/{extraction}/convert-vendor-bill` | `ocr.convert` (Human-in-the-Loop, No Auto-GL) |
| Reject Extraction | `POST /finance/ocr/{extraction}/reject` | `ocr.convert` |
| AI Configuration Center | `GET /settings/ai-configuration` | `settings.ai.view` |
| Save AI Settings (Ollama / OpenAI) | `POST /settings/ai-configuration` | `settings.ai.manage` + password confirm |
| Test AI Connectivity | `POST /settings/ai-configuration/test` | `settings.ai.manage` |

## Web Setup Wizard & Installer Lifecycle

| Screen / Action | Route | Access Control |
| --- | --- | --- |
| Web Setup Wizard (Steps 1-4) | `GET /setup` | Guarded: `SETUP_TOKEN` or `enable.php`; HTTP 404 if `installed.json` exists |
| Submit Setup Step / Execute Install | `POST /setup` | Guarded: Setup Session Token, File Lock `install.lock` |


- ไม่มี user invited: แสดงปุ่ม invite.
- ไม่มี customer: แสดงปุ่ม create customer.
- ไม่มี deal: แสดง pipeline ว่าง + create deal.
- ไม่มี invoice/payment: แสดง finance widgets เป็น 0 ไม่ซ่อนตัวเลขสำคัญ.
- ไม่มี project/task: แสดง Delivery dashboard แบบว่างและปุ่ม create project หลัง Phase 4.

## Resource write map

| Action | Route | Permission |
| --- | --- | --- |
| Register | `POST /register` | public |
| Login | `POST /login` | public |
| Logout | `POST /logout` | auth |
| Create customer | `POST /customers` | `customers.create` |
| Update customer | `PATCH /customers/{customer}` | `customers.update` |
| Create deal | `POST /deals` | `deals.create` |
| Update deal / stage | `PATCH /deals/{deal}` | `deals.update` |
| Create product | `POST /products` | `products.manage` |
| Create invoice | `POST /invoices` | `invoices.create` |
| Update invoice | `PUT /invoices/{invoice}` | `invoices.update` |
| Link invoice to project | `POST /invoices/{invoice}/link-project` | `invoices.update` |
| Create expense | `POST /expenses` | `expenses.create` |
| Create project | `POST /projects` | `projects.create` |
| Create task | `POST /tasks` | `tasks.create` |


