# Routes and Screens by Phase

เอกสารนี้เป็นรายการหน้าและ route ระดับ MVP เพื่อกัน scope บานตอน coding.

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
| Invite Accept | `GET /invites/{token}` | signed |
| Admin Dashboard | `GET /dashboard` | `dashboard.view` |
| Organization Settings | `GET /settings/organization` | `settings.manage` |
| Branch/Division/Department read-only | `GET /settings/organization-structure` | `settings.structure.view` |
| Users | `GET /users` | `users.view` |
| Invite User | `POST /users/invite` | `users.create` |
| User Detail/Edit | `GET /users/{user}` | `users.view` |
| Roles/Permissions | `GET /roles` | `roles.manage` |
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
| Roles / Permissions | `GET /roles` | `roles.manage` |
| Update Role Permissions | `PATCH /roles/{role}/permissions` | `roles.manage` |

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
| Finance Dashboard | `GET /dashboard` | `dashboard.view` |
| Products/Services | `GET /products` | `products.manage` |
| Invoices List | `GET /invoices` | `invoices.view` |
| Invoice Create Manual/From Deal | `GET /invoices/create` | `invoices.create` |
| Invoice Detail | `GET /invoices/{invoice}` | `invoices.view` |
| Record Payment | `POST /invoices/{invoice}/payments` | `payments.create` |
| Reverse Payment | `POST /payments/{payment}/reverse` | `payments.reverse` + reauth |
| Expenses | `GET /expenses` | `expenses.view` |
| Approve/Pay Expense | `POST /expenses/{expense}/approve`, `POST /expenses/{expense}/pay` | `expenses.approve/pay` + reauth |

## Phase 4: Delivery

| Screen | Route | Permission |
| --- | --- | --- |
| Delivery Dashboard | `GET /dashboard` | `dashboard.view` |
| Projects List | `GET /projects` | `projects.view` |
| Project Create/Edit | `GET /projects/create`, `GET /projects/{project}/edit` | `projects.create/update` |
| Project Detail | `GET /projects/{project}` | `projects.view` |
| Tasks Board/List | `GET /tasks` | `tasks.view` |
| Task Detail | `GET /tasks/{task}` | `tasks.view` |
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
| Notification Settings | `GET /settings/notifications`, `PATCH /settings/notifications` | `settings.organization.view/update` |

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

## Empty States

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


