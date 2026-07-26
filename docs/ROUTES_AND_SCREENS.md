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

| Screen | Route | Permission |
| --- | --- | --- |
| Sales Dashboard | `GET /dashboard` | `dashboard.view` |
| Customers List | `GET /customers` | `customers.view` |
| Customer Create/Edit | `GET /customers/create`, `GET /customers/{customer}/edit` | `customers.create/update` |
| Customer Detail | `GET /customers/{customer}` | `customers.view` |
| Contacts | nested under customer | `contacts.view/create/update` |
| Deals Pipeline | `GET /deals` | `deals.view` |
| Deal Detail | `GET /deals/{deal}` | `deals.view` |
| Activities/Follow-ups | nested under customer/deal | `activities.*` |

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
| Update customer | `PUT /customers/{customer}` | `customers.update` |
| Create deal | `POST /deals` | `deals.create` |
| Update deal / stage | `PUT /deals/{deal}` | `deals.update` |
| Create product | `POST /products` | `products.manage` |
| Create invoice | `POST /invoices` | `invoices.create` |
| Update invoice | `PUT /invoices/{invoice}` | `invoices.update` |
| Link invoice to project | `POST /invoices/{invoice}/link-project` | `invoices.update` |
| Create expense | `POST /expenses` | `expenses.create` |
| Create project | `POST /projects` | `projects.create` |
| Create task | `POST /tasks` | `tasks.create` |


