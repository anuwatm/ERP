# Module: User / Role / Permission

| Meta | Value |
| --- | --- |
| Module code | `auth` / `rbac` |
| Version | V1 |
| Priority | P0 |
| Schema กลาง | [`../database/DATABASE.md`](../database/DATABASE.md) §3.5–3.9 |

---

## 1. ชื่อ Module

**User / Role / Permission** — ยืนยันตัวตนและควบคุมสิทธิ์

---

## 2. รายละเอียด / หน้าที่

- Login / logout / invite user
- จัดการผู้ใช้ใน org (active / inactive) และ assign branch/division/department
- กำหนด role เริ่มต้น (MVP): Owner, Admin, Sales, Project Manager, Finance, Member, Viewer  
  (ไม่มี role `Manager` แยก — ใช้ `project_manager` / `admin` ตามงาน)
- Permission ต่อ module + action: `view`, `create`, `update`, `delete`, `approve`, `export`
- บังคับ server-side permission check ทุก request
- Member เห็นเฉพาะงานที่ assign ได้ (policy ระดับแถว)

---

## 3. Workflow

### 3.1 Login (MVP: local Breeze)

```text
User กรอก email/password
→ ตรวจ users (org_id + email, status=active, auth_provider=local)
→ ตรวจ password ที่ hash แล้ว (users.password)
→ โหลด roles + permissions
→ สร้าง encrypted session + regenerate
→ เขียน audit_logs (login)
```

> หลัง MVP รองรับ OIDC: ตรวจ provider identity แล้ว map `auth_provider_user_id`

### 3.2 Invite user

```text
Admin ระบุ email + role + branch/division/department
→ validate branch/division/department อยู่ใน org เดียวกัน
→ สร้าง users (status=invited, password null)
→ ส่ง signed invite link
→ User ตั้งรหัสผ่าน (hash ลง users.password)
→ status = active, email_verified_at set
→ ผูก user_roles
```

### 3.3 Assign role / เปลี่ยนสิทธิ์

```text
Admin เลือก user
→ เพิ่ม/ลบ user_roles
→ (optional) ปรับ role_permissions ของ custom role
→ audit_logs บันทึก change permission
```

### 3.4 Authorization check (ทุก request)

```text
Request + user context
→ ตรวจ permission code (เช่น invoices.approve)
→ ถ้าผ่าน ตรวจ row-level (org_id + ownership policy)
→ allow / 403
```

---

## 4. Data Flow

```text
[Client]
   │ credentials / session
   ▼
Auth service
   │ read
   ▼
users ──► user_roles ──► roles ──► role_permissions ──► permissions
   │
   ├──► server-side session context (org_id, user_id, roles, permission set; ไม่ใช้ JWT ใน MVP)
   │
   └──► audit_logs (login, role change)
```

**Outbound**

| Target | ข้อมูล |
| --- | --- |
| ทุก module | actor `user_id` + permission gate |
| Audit Log | login / permission changes |
| Employees | optional map `employees.user_id` |
| Notifications | `user_id` เป้าหมาย |

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

| Table | Role |
| --- | --- |
| `users` | บัญชีผู้ใช้ใน org + profile (`display_name`, `person_id`, `position`) |
| `roles` | บทบาท |
| `permissions` | catalog สิทธิ์ global |
| `role_permissions` | map role ↔ permission |
| `user_roles` | map user ↔ role |

### Permission code pattern

```text
{module}.{action}
ตัวอย่าง: customers.view, deals.update, invoices.approve, reports.export
```

### Business rules

- Owner/Admin เห็น audit ได้
- Permission check ต้องอยู่ server-side เสมอ
- ห้าม user ข้าม `org_id`
- `branch_id`, `division_id`, `department_id` ของ user ต้องอยู่ใน hierarchy เดียวกัน
- System roles (`is_system=true`) ห้ามลบ
- effective permissions ของ user = UNION ของ permission จากทุก role ที่ผูกใน `user_roles`; ห้ามใช้เฉพาะ role แรกหรือ role ล่าสุด
- `owner` system role เป็น immutable: ห้ามแก้หรือลด `role_permissions` ผ่าน UI/API เพื่อป้องกัน lockout
- `person_id` เป็น PII อ่อนไหว เก็บตรงได้ตาม scope นี้ แต่ต้อง mask ตอนแสดงผล/log/export และไม่ใช้ตัดสินสิทธิ์
- `position` เป็นข้อมูลโปรไฟล์/ตำแหน่งงาน ไม่ใช้ตัดสินสิทธิ์
- รหัสผ่านเก็บเป็น hash เท่านั้น
---


### MVP visibility model

MVP ยังไม่มี `project_members`, `resource_assignments`, หรือ sharing table.

- Sales เห็น customer/deal/activity ที่ `owner_id = user.id` เท่านั้น.
- Project Manager เห็น project ที่ `owner_id = user.id` และ task ใน project นั้น.
- Member เห็น task ที่ `assignee_id = user.id` เท่านั้น.
- Finance เห็น finance data ตาม permission; ไม่ใช้ owner assignment.
- Post-MVP ถ้าต้องการ assign/share หลายคน ให้เพิ่ม `project_members` หรือ `resource_assignments` ก่อนขยาย rule.

## 6. Role Visibility Matrix (MVP)

| Role | เห็นข้อมูล | ทำได้ | ห้าม |
| --- | --- | --- | --- |
| Owner | ทุกข้อมูลใน org | จัดการ org, users, roles, settings, audit, finance | ห้ามถูก disable/delete โดย user อื่น |
| Admin | ทุกข้อมูลใน org ยกเว้น owner-only guard | จัดการ user, role, settings, audit, workflow หลัก | ห้ามลดสิทธิ์/disable Owner คนสุดท้าย |
| Sales | customers/deals/activities ที่ตัวเองเป็น owner | สร้าง/แก้ customer, contact, deal, activity ของตัวเอง | ห้ามเห็น payment/expense/dashboard finance เต็ม |
| Project Manager | projects ที่ตัวเองเป็น owner; tasks ใน project scope | สร้าง/แก้ project/task, assign task ใน project ที่ดูแล | ห้ามทำ payment/reversal/approve expense |
| Finance | invoices/payments/expenses/products และ customer ที่เกี่ยวกับ invoice | ออก invoice, รับ payment, reverse payment, approve/pay expense | ห้ามแก้ deal/project นอกส่วนประกอบการเงิน |
| Member | task ที่ assignee_id = user.id | update task status/comment ของงานตัวเอง | ห้ามเห็นข้อมูลรวม, finance, user management |
| Viewer | read-only ตาม permission ที่ให้ | ดูข้อมูลที่อนุญาต | ห้าม create/update/delete/approve/export |

---

## 7. Permission Matrix (MVP)

| Module / Action | Owner | Admin | Sales | Project Manager | Finance | Member | Viewer |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `users.view` | all | all | no | no | no | no | optional read-only |
| `users.invite` | yes | yes | no | no | no | no | no |
| `users.disable` | yes | yes, except owner guard | no | no | no | no | no |
| `roles.manage` | yes | yes, except owner guard | no | no | no | no | no |
| `settings.manage` | yes | yes | no | no | finance settings only if granted | no | no |
| `audit.view` | yes | yes | no | no | finance audit if granted | no | no |
| `customers.view` | all org | all org | owned | linked project only | invoice-related | no list; linked read-only on own task detail | granted read-only |
| `customers.create` | yes | yes | yes | no | no | no | no |
| `customers.update` | yes | yes | owned | no | billing fields only if granted | no | no |
| `deals.view` | all org | all org | owned | linked project only | no by default | no list; linked read-only on own task detail | granted read-only |
| `deals.create/update` | yes | yes | owned | no | no | no | no |
| `projects.view` | all org | all org | linked customer/deal only | owned | finance-linked only | own task only | granted read-only |
| `projects.create/update` | yes | yes | no | owned | no | no | no |
| `tasks.view` | all org | all org | linked only | project scope | no by default | own task only | granted read-only |
| `tasks.create/update` | yes | yes | no | project scope | no | own status/comment only | no |
| `products.view` | yes | yes | no by default | no by default | yes | no | granted read-only |
| `products.manage` | yes | yes | no | no | yes | no | no |
| `invoices.view` | all org | all org | no by default | project summary only | all finance | no | granted read-only |
| `invoices.create/update` | yes | yes | no | no | yes | no | no |
| `invoices.void` | yes + reauth | yes + reauth | no | no | yes + reauth | no | no |
| `payments.view` | all org | all org | no | no | all finance | no | granted read-only |
| `payments.create` | yes | yes | no | no | yes | no | no |
| `payments.reverse` | yes + reauth | yes + reauth | no | no | yes + reauth | no | no |
| `expenses.view` | all org | all org | no | project cost summary only | all finance | own submitted only if enabled | granted read-only |
| `expenses.create/update` | yes | yes | no | project expense draft if granted | yes | no | no |
| `expenses.approve/pay` | yes + reauth | yes + reauth | no | no | yes + reauth | no | no |
| `dashboard.view` | all widgets | all widgets | sales widgets | project widgets | finance widgets | own task widgets | granted read-only widgets |
| `export.*` | Post-MVP | Post-MVP | no | no | Post-MVP finance only | no | no |

---

## 8. Row-level Policy Rules

ทุก policy ต้องตรวจตามลำดับนี้:

```text
authenticated
→ user.status = active
→ organizations.status = active
→ permission code
→ org_id match
→ branch/division/department hierarchy match ถ้าใช้ scope ภายใน
→ resource ownership / ownership rule
```

### Ownership rules

| Resource | Row-level rule |
| --- | --- |
| customers | `org_id` match; Sales เห็นเฉพาะ `owner_id = user.id` |
| contacts | MVP: ตาม customer parent ที่ user เห็นได้; Post-MVP ค่อยรวม supplier parent |
| deals | `owner_id = user.id` สำหรับ Sales; PM เห็นเฉพาะ linked project; Member ไม่มี deal list และเห็นเฉพาะ linked read-only ผ่าน task detail |
| activities | ตาม parent entity visibility + `org_id` |
| projects | PM เห็น `projects.owner_id = user.id`; ยังไม่มี `project_members` ใน MVP |
| tasks | Member เห็นเฉพาะ `assignee_id = user.id`; PM เห็น task ใน project scope |
| invoices | Owner/Admin/Finance เท่านั้น; PM เห็น project invoice summary แบบไม่เห็น payment detail ถ้า granted |
| payments | Owner/Admin/Finance เท่านั้น |
| expenses | Owner/Admin/Finance; PM เห็น project cost summary ไม่ใช่รายละเอียดทั้งหมด ถ้า granted |
| audit_logs | Owner/Admin; Finance เห็นเฉพาะ finance audit ถ้า granted |
| files | ตาม parent entity visibility; download ต้องตรวจ permission ทุกครั้ง |

### Hierarchy scope rules

- `org_id` คือ security boundary หลัก ห้ามใช้ branch/division/department แทน `org_id`.
- `branch_id`, `division_id`, `department_id` เป็น visibility filter เสริม.
- user อยู่ได้ 1 branch / 1 division / 1 department ใน MVP.
- การ assign/move user ต้อง validate chain:

```text
branch.org_id = user.org_id
division.org_id = user.org_id
division.branch_id = branch.id
department.org_id = user.org_id
department.branch_id = branch.id
department.division_id = division.id
```

- request body ที่ส่ง `org_id`, `role`, `branch_id`, `division_id`, `department_id` ต้องถูก ignore/reject ถ้าไม่ผ่าน server validation.

---

## 9. Dashboard Widget Visibility

| Widget | Owner | Admin | Sales | Project Manager | Finance | Member | Viewer |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Invoiced Revenue | yes | yes | no | no | yes | no | optional |
| Cash In | yes | yes | no | no | yes | no | optional |
| Outstanding AR / Overdue AR | yes | yes | no | no | yes | no | optional |
| Recognized Expense / Cash Out | yes | yes | no | project summary only | yes | no | optional |
| Gross Profit | yes | yes | no | project scope only | yes | no | optional |
| Pipeline Value | yes | yes | owned only | no | no | no | optional |
| Deal by Stage | yes | yes | owned only | no | no | no | optional |
| Active Projects | yes | yes | linked only | owned | finance-linked only | no by default | optional |
| Overdue Tasks | yes | yes | linked only | project scope | no | own task only | optional |
| Follow-ups Today | yes | yes | owned only | no | no | no | optional |
| Recent Activity | yes | yes | visible entities only | visible entities only | finance entities only | own entities only | optional |

---

## 10. Sensitive Action Rules

ต้องใช้ password confirmation / re-authentication และ audit log:

- invite user
- disable user
- assign/remove role
- change permission
- change organization hierarchy of a user
- void invoice
- create payment reversal
- approve/pay expense
- change finance settings / invoice numbering

Audit log ต้องบันทึก:

```text
org_id, actor_user_id, action, entity_type, entity_id,
before_json, after_json, ip_address, user_agent, created_at
```


