# Phase 1.1: Admin Master Data & Access Management

เอกสารนี้กำหนด scope สำหรับ Phase 1.1 ก่อนเริ่ม coding. Phase นี้อยู่ระหว่าง Phase 1 Foundation และ Phase 2 CRM/Sales.

## Objective

ทำให้ Owner/Admin จัดการข้อมูลพื้นฐานและสิทธิ์การเข้าถึงที่จำเป็นก่อนเริ่ม CRM/Sales.

Phase 1.1 ครอบคลุม:

- Branch
- Division
- Department
- User edit / disable
- Role permission assignment

โครงสร้างหลัก:

```text
Company / Organization -> Branch -> Division -> Department -> User -> Role -> Permission
```

## Why Phase 1.1 Exists

Phase 1 สร้าง schema, auth, RBAC, invite และ default organization structure แล้ว แต่ยังเน้น foundation/admin dashboard.

ก่อนเริ่ม Phase 2 ต้องมี master data และ access management ที่แก้ได้จริง เพราะ Customer, Deal, User assignment และ Dashboard ต่อไปจะอ้างอิง Branch/Division/Department และ role permission.

ถ้าไม่ทำ Phase 1.1 ก่อน Phase 2 จะเกิดปัญหา:

- ต้องแก้ default branch/division/department ใน database ตรง
- assign user/customer/deal ไปโครงสร้างองค์กรจริงไม่ได้
- report/dashboard ตาม branch/division/department ใช้ไม่ได้เต็มที่
- ปรับสิทธิ์ทีมก่อนเริ่มใช้งานจริงไม่ได้
- audit trail ของ master data และ access change ไม่ครบ

## In Scope

### Company Identity & Information

- Edit organization display/legal name, tax id, email, phone, address
- Upload company logo from `/settings/organization`
- Logo file accepts JPG, PNG, WebP, max 2MB
- Store logo in public storage and save display URL in `organizations.logo_url`
- Organization update audit must include `logo_url` before/after when changed

### Branch

- List branches
- Create branch
- Edit branch
- Disable branch
- Delete branch เฉพาะกรณีไม่มีข้อมูลอ้างอิง
- Head office flag มีได้หนึ่ง branch ต่อ organization
- Set head office ต้องทำใน transaction เดียว: unset branch เดิม + set branch ใหม่ + audit `branch.set_head_office`
- Branch code ใช้ text 6 หลัก เช่น `000001` และต้อง auto-generate จาก `number_sequences`; ไม่เปิดให้ผู้ใช้พิมพ์เอง

### Division

- List divisions
- Create division ใต้ branch
- Edit division
- Disable division
- Delete division เฉพาะกรณีไม่มีข้อมูลอ้างอิง
- Validate ว่า division ต้องอยู่ใต้ branch ใน org เดียวกัน
- Division code ใช้ text 6 หลัก และต้อง auto-generate จาก `number_sequences`; ไม่เปิดให้ผู้ใช้พิมพ์เอง

### Department

- List departments
- Create department ใต้ branch + division
- Edit department
- Disable department
- Delete department เฉพาะกรณีไม่มีข้อมูลอ้างอิง
- Validate chain: department.branch_id ต้องตรงกับ division.branch_id
- Department code ใช้ text 6 หลัก และต้อง auto-generate จาก `number_sequences`; ไม่เปิดให้ผู้ใช้พิมพ์เอง

### User Management

- List users
- Invite user
- Edit user profile:
  - name
  - email
  - phone
  - position
  - person_id
  - branch_id
  - division_id
  - department_id
- Assign/change user roles
- Disable user
- Re-enable user ถ้าไม่ขัด security policy
- ห้าม hard delete user ใน MVP
- Validate user hierarchy chain ทุกครั้ง
- `person_id` เก็บ plaintext แต่ต้อง mask ใน UI/log/Inertia props ตาม permission

### Role / Permission Management

- View role list
- View permission list
- View permissions assigned to each role
- Update role-permission assignment
- Owner role immutable
- ห้ามลดสิทธิ์หรือปิด role Owner คนสุดท้าย
- ห้าม CRUD permission code ผ่าน UI ใน MVP

Permission code เป็น system-level config. เพิ่ม/ลบ/เปลี่ยน code ต้องทำผ่าน migration/seeder เท่านั้น เพื่อกัน middleware หรือ policy พัง.

### Admin UX

- ใช้ design system กลางจาก `docs/DESIGN_SYSTEM.md`
- ใช้ `PageHeader`, `Card`, `DataTable`, `Badge`
- หน้า organization profile ต้องมี company logo preview และ upload control
- หน้า organization structure ต้องใช้ Tab Navigation: Branches / Divisions / Departments
- หน้า users ต้องแก้ profile, hierarchy และ role ได้ใน flow เดียว
- หน้า roles ต้องแสดง permission matrix ที่อ่านง่าย
- มี empty state และ inline validation error
- มี confirm dialog ก่อน disable/delete/change sensitive permission

## Out of Scope

Phase 1.1 ยังไม่ทำ:

- Customer / Contact / Deal
- Product / Invoice / Payment
- Project / Task
- Import/export master data
- Bulk upload
- Approval workflow สำหรับแก้ master data หรือ permission
- Full soft-delete recovery UI
- CRUD permission code จาก UI
- Hard delete user

## Permissions

ใช้ permission catalog เดิมถ้าเพียงพอ:

- `settings.structure.view`
- `settings.structure.update`
- `users.view`
- `users.create`
- `users.update`
- `users.disable`
- `roles.manage`

ถ้าต้องแยกละเอียด ให้เพิ่มภายหลัง:

- `branches.create`
- `branches.update`
- `branches.delete`
- `divisions.create`
- `divisions.update`
- `divisions.delete`
- `departments.create`
- `departments.update`
- `departments.delete`
- `role_permissions.update`

Decision สำหรับ Phase 1.1 MVP:

- ใช้ `settings.structure.update` สำหรับ branch/division/department create/edit/disable/delete
- ใช้ `users.update` สำหรับแก้ user profile/hierarchy/role
- ใช้ `users.disable` สำหรับ disable/re-enable user
- ใช้ `roles.manage` สำหรับแก้ role-permission assignment
- Owner/Admin ได้สิทธิ์จัดการ
- Role อื่นดูได้ตาม permission ที่ได้รับ

## Routes / Screens

| Screen / Action | Route | Method | Permission |
| --- | --- | --- | --- |
| Organization Structure | `/settings/organization-structure` | GET | `settings.structure.view` |
| Create Branch | `/settings/branches` | POST | `settings.structure.update` |
| Update Branch | `/settings/branches/{branch}` | PATCH | `settings.structure.update` |
| Disable Branch | `/settings/branches/{branch}/disable` | PATCH | `settings.structure.update` |
| Set Head Office Branch | `/settings/branches/{branch}/head-office` | PATCH | `settings.structure.update` |
| Delete Branch | `/settings/branches/{branch}` | DELETE | `settings.structure.update` |
| Create Division | `/settings/divisions` | POST | `settings.structure.update` |
| Update Division | `/settings/divisions/{division}` | PATCH | `settings.structure.update` |
| Disable Division | `/settings/divisions/{division}/disable` | PATCH | `settings.structure.update` |
| Delete Division | `/settings/divisions/{division}` | DELETE | `settings.structure.update` |
| Create Department | `/settings/departments` | POST | `settings.structure.update` |
| Update Department | `/settings/departments/{department}` | PATCH | `settings.structure.update` |
| Disable Department | `/settings/departments/{department}/disable` | PATCH | `settings.structure.update` |
| Delete Department | `/settings/departments/{department}` | DELETE | `settings.structure.update` |
| Users List | `/users` | GET | `users.view` |
| Invite User | `/users/invite` | POST | `users.create` |
| Update User | `/users/{user}` | PATCH | `users.update` |
| Disable User | `/users/{user}/disable` | PATCH | `users.disable` |
| Re-enable User | `/users/{user}/enable` | PATCH | `users.disable` |
| Roles / Permissions | `/roles` | GET | `roles.manage` |
| Update Role Permissions | `/roles/{role}/permissions` | PATCH | `roles.manage` |

Sensitive write routes should use:

- `auth`
- `verified`
- permission middleware ตาม action
- `password.confirm`
- `throttle:10,1`

## Validation Rules

### Common

- `org_id` ห้ามรับจาก client; server derive จาก authenticated user เท่านั้น
- `code` ต้องเป็น string 6 หลัก: `regex:/^[0-9]{6}$/` และสร้างฝั่ง server จาก `number_sequences` เท่านั้น
- `name` required, string, max 255
- `status` ใช้ `active` / `inactive`
- ห้าม duplicate `code` ภายใน org และ parent scope ที่กำหนด

### Branch

- `code` unique per `org_id`
- `is_head_office` boolean
- ถ้าตั้ง head office ใหม่ ต้อง unset head office เดิมและ set head office ใหม่ใน transaction เดียว พร้อม audit `branch.set_head_office`
- ห้าม disable branch ถ้ายังมี active division, department หรือ user อยู่ใต้ branch และต้องแสดง error message ที่ชัดเจน
- ห้าม delete branch ถ้ายังมี division, department หรือ user อ้างอิง ไม่ว่าจะ active/inactive

### Division

- `branch_id` required และต้องอยู่ใน org เดียวกัน
- `code` unique per `org_id + branch_id`
- ห้าม disable division ถ้ายังมี active department หรือ user อยู่ใต้ division และต้องแสดง error message ที่ชัดเจน
- ห้าม delete division ถ้ายังมี department หรือ user อ้างอิง ไม่ว่าจะ active/inactive

### Department

- `branch_id` required และต้องอยู่ใน org เดียวกัน
- `division_id` required และต้องอยู่ใต้ branch เดียวกัน
- `code` unique per `org_id + division_id`
- ห้าม disable department ถ้ายังมี active user อยู่ใต้ department และต้องแสดง error message ที่ชัดเจน
- ห้าม delete department ถ้ายังมี user อ้างอิง ไม่ว่าจะ active/inactive

### User

- `email` unique per system หรือ per org ตาม schema ปัจจุบัน
- `person_id` nullable/required ตาม business rule; ถ้ามีต้องเป็นตัวเลข 13 หลัก
- `position` string max 255
- `branch_id`, `division_id`, `department_id` ต้องอยู่ใน org เดียวกัน
- `department_id` ต้องอยู่ใต้ division/branch ที่เลือก
- ห้าม disable owner คนสุดท้าย
- ห้าม user แก้ role/permission ของตัวเองจนทำให้ไม่มี admin/owner เหลือ

### Role Permission

- role ต้องอยู่ใน org หรือเป็น system role ที่อนุญาตให้แก้ assignment ได้
- permission code ต้องมีอยู่ใน permission catalog
- ห้ามแก้ permission ของ Owner role ถ้า policy กำหนด immutable
- ห้ามลบ permission สำคัญจนไม่มี role ใดดูแล users/roles ได้

## Delete Policy

ใช้แนวทาง conservative:

- ถ้าไม่มี reference ใด ๆ: delete master data ได้
- Delete guard ต้องตรวจทุก reference รวม inactive user/sub-structure/future business data
- Disable guard ตรวจ active child/user เพื่อไม่ให้โครงสร้างที่ยังใช้งานอยู่ถูกปิด
- User ห้าม hard delete ใน MVP; ใช้ disable เท่านั้น
- Permission code ห้าม delete ผ่าน UI
- Disable แล้วไม่ควรให้เลือกเป็นค่าใหม่ใน form แต่ยังแสดงใน historical data ได้

## Audit Logs

ต้องบันทึก audit log ทุก write action:

- `branch.create`
- `branch.update`
- `branch.disable`
- `branch.set_head_office`
- `branch.delete`
- `division.create`
- `division.update`
- `division.disable`
- `division.delete`
- `department.create`
- `department.update`
- `department.disable`
- `department.delete`
- `user.invite`
- `user.update`
- `user.disable`
- `user.enable`
- `user.role_update`
- `role.permission_update`

Audit payload:

- `org_id`
- `actor_user_id`
- `action`
- `entity_type`
- `entity_id`
- `before_json`
- `after_json`
- `ip_address`
- `user_agent`

ต้อง mask `person_id` ใน audit display และ Inertia props เว้นแต่ user มี permission ดูเลขเต็ม.

## Dashboard Impact

Admin Dashboard หลัง Phase 1.1 ควรแสดง:

- Branch count
- Division count
- Department count
- Inactive master data count
- Active/inactive user count
- Role count
- Permission assignment change count หรือ recent role changes
- Recent Audit ยังเห็น master data และ access changes

## Tests Required

- Owner/Admin can create branch/division/department
- Member cannot create/edit/delete structure
- Branch/Division/Department code must be auto-generated 6 digits from `number_sequences`
- Division must belong to selected branch
- Department must belong to selected branch/division chain
- Cannot delete branch/division/department with users attached
- Disable hides item from future assignment lists
- Head office switch unsets old branch and sets new branch in one transaction
- Owner/Admin can update user profile/hierarchy/role
- Member cannot update another user
- Cannot disable last owner
- Cannot hard delete user
- Can update role-permission assignment with `roles.manage`
- Cannot CRUD permission code from UI
- Permission assignment update creates audit log
- Cross-org access returns 404
- Every write action creates audit log, including `branch.set_head_office`
- Sensitive write actions require password confirmation

## Exit Criteria

Phase 1.1 ผ่านเมื่อ:

- Owner/Admin แก้ Company Identity & Information และ upload company logo ได้
- ระบบ validate logo เฉพาะ JPG/PNG/WebP และขนาดไม่เกิน 2MB
- Owner/Admin เพิ่ม/แก้/ปิดใช้งาน/delete-safe Branch ได้
- Owner/Admin เพิ่ม/แก้/ปิดใช้งาน/delete-safe Division ได้
- Owner/Admin เพิ่ม/แก้/ปิดใช้งาน/delete-safe Department ได้
- Owner/Admin แก้ user profile/hierarchy/role และ disable/re-enable user ได้
- ระบบห้าม hard delete user
- Owner/Admin แก้ role-permission assignment ได้
- ระบบห้าม CRUD permission code จาก UI
- Server validate hierarchy chain และ strict reference guard ทุกครั้ง
- Server enforce tenant `org_id` ทุก query
- Delete guard กันลบข้อมูลที่มี reference ทุกสถานะ และ disable guard กันปิดข้อมูลที่มี active child/user
- Audit log ครบทุก create/update/disable/delete/access change
- UI ใช้ design system กลาง
- Tests ผ่านครบ
- อัปเดต `README.md` หลังจบ Phase 1.1

## Implementation Notes

- ใช้ table เดิมจาก Phase 1; ไม่ควรต้องเพิ่ม migration ใหญ่
- `organizations.logo_url` รองรับ company logo อยู่แล้ว; upload ใช้ public storage path `/storage/org-logos/{org_id}/...`
- อาจต้องเพิ่ม unique indexes ถ้า schema ปัจจุบันยังไม่ครอบ parent scope ตาม validation
- Branch/Division/Department create ต้องใช้ `NumberSequence` service สร้าง code 6 หลักอัตโนมัติ
- ถ้าต้องเพิ่ม column สำหรับ soft delete ให้พิจารณาก่อน coding; MVP สามารถใช้ `status = inactive` ก่อน
- Permission code ต้องเพิ่มผ่าน migration/seeder เท่านั้น ไม่เปิด CRUD จาก UI
- Phase 1.1 ต้องเสร็จก่อนเริ่ม Phase 2 CRM/Sales


