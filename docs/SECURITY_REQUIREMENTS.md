# Security Requirements: MVP + Enabled Features

เอกสารนี้เป็นข้อกำหนดบังคับสำหรับ Phase 1-5 และอยู่คู่กับ [`ARCHITECTURE_DECISIONS.md`](./ARCHITECTURE_DECISIONS.md).

## 1. Authentication

- MVP: Laravel Breeze local เป็น auth provider (`auth_provider=local`) — รองรับ password hashing, email verification, password reset
- เก็บได้เฉพาะ password ที่ **hash แล้ว** ใน `users.password`; ห้าม plaintext password, ห้าม log session token / authorization header
- เตรียมคอลัมน์ `auth_provider`, `auth_provider_user_id` สำหรับ OIDC/SSO ภายหลัง; MFA/2FA บังคับก่อน privileged production rollout
- session ใช้ `HttpOnly`, `Secure`, `SameSite=Lax` cookie; rotate session หลัง login, role change และ password reset
- บังคับ re-authentication (password confirmation) ก่อน action เสี่ยง: เปลี่ยน role, invite/disable user, void invoice, reverse payment
- ป้องกัน brute force: rate limit login, invite, password reset และ lock/step-up challenge หลังพยายามผิดซ้ำ

## 2. Authorization and tenant isolation

- ทุก API ตรวจ authentication, permission และ `org_id` ฝั่ง server ตามลำดับ.
- ห้ามเชื่อ `org_id`, `user_id`, `role`, price, total หรือ status ที่ client ส่งมา.
- Read/update/delete ทุก query ต้อง bind `org_id` จาก session context, ไม่รับจาก request body/query string.
- ตรวจ row ownership เพิ่มเติมตาม Role Visibility Matrix ใน `docs/modules/02-user-role-permission.md` เช่น owner, assignee, project scope, finance scope.
- Permission changes มีผลทันที: invalidate session หรือ refresh server-side permission context.
- `owner` system role และ permission mapping ของ role นี้ต้อง immutable ผ่าน UI/API; ห้ามลบ/ถอนสิทธิ์เพื่อป้องกัน admin lockout.

### 2.1 Tenant scope implementation

- ใช้ Laravel policy + shared tenant scope/trait กับ business model ที่มี `org_id`.
- Bypass tenant scope ได้เฉพาะ system job ที่ระบุชัดและมี log/audit.

### 2.2 Branch / Division / Department visibility

- `org_id` เป็น security boundary หลักเสมอ.
- `branch_id`, `division_id`, `department_id` เป็น visibility filter เสริมเท่านั้น.
- ทุกการ assign/move user ต้อง validate hierarchy ใน server.
- request ที่พยายามย้าย resource/user ไป branch/division/department นอก org หรือ chain ไม่ตรงต้อง reject.
- Dashboard/report filter ตาม branch/division/department ต้อง apply หลัง `org_id` scope แล้วเท่านั้น.

## 3. Financial integrity

- invoice/payment/expense transition ใช้ server-side state machine; reject transition ที่ไม่อนุญาต.
- สร้าง payment/reversal ใน DB transaction พร้อม row lock ที่ invoice; ป้องกัน concurrent overpay.
- payment ที่ post แล้วแก้หรือลบไม่ได้; ใช้ reversal entry พร้อม audit log.
- จำนวนเงินใช้ `DECIMAL(18,2)` เท่านั้น; server recalculate ทุกยอดก่อนบันทึก.
- endpoint การเงินใช้ idempotency key เพื่อกัน double submit/retry ซ้ำ.

## 4. Input, API and browser security

- Validate schema, type, length, enum และ foreign key ownership ทุก request.
- ใช้ parameterized query/ORM; ห้ามต่อ SQL จาก input.
- Escape output ทุกครั้ง; sanitize rich text หากรองรับ markdown/HTML เพื่อกัน XSS.
- ใช้ CSRF protection สำหรับ cookie-based state-changing request.
- ตั้ง security headers: CSP, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `X-Frame-Options`/`frame-ancestors`.
- API error ห้ามเผย stack trace, SQL, token หรือข้อมูล tenant อื่น.


### 4.1 Phase 1 dev/prod browser security notes

Development (`http://localhost/ERP/`):

- CSRF ใช้ Laravel middleware ของ web routes ทุก state-changing request.
- Session cookie ใช้ `HttpOnly` และ `SameSite=Lax`.
- `SESSION_SECURE_COOKIE=false` ได้เฉพาะ local HTTP เท่านั้น.
- `APP_DEBUG=true` ใช้ได้เฉพาะเครื่อง dev; ห้ามใช้กับข้อมูลจริง.

Production / UAT with real users:

- ต้องใช้ HTTPS และ `SESSION_SECURE_COOKIE=true`.
- ต้องใช้ `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`.
- ต้องตั้ง `APP_DEBUG=false`, `APP_ENV=production`, `APP_KEY` จริง และ mail provider จริง.
- ต้องเพิ่ม middleware/header layer สำหรับ CSP, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, และ frame protection ก่อนเปิด public internet.

## 5. Files and exports (เมื่อ feature เปิดใช้)

- allowlist MIME type + extension, จำกัดขนาด, เปลี่ยนชื่อไฟล์เป็น generated storage key.
- `storage_key` ต้องสร้างฝั่ง server ด้วย random UUID/cryptographic random เท่านั้น เช่น `tenants/{org_id}/{year}/{month}/{uuid}.{ext}`; ห้ามใช้ชื่อไฟล์เดิมหรือ path จากผู้ใช้เป็น storage path โดยตรง.
- scan malware ก่อนให้ไฟล์ดาวน์โหลด/แชร์; เก็บ private object storage.
- download ใช้ signed URL อายุสั้น หรือ endpoint ที่ตรวจ permission ทุกครั้ง.
- ป้องกัน CSV formula injection: prefix cell ที่เริ่ม `=`, `+`, `-`, `@` ก่อน export (Post-MVP; MVP ไม่มี PDF/CSV export).
- ห้ามรับไฟล์ executable/script ใน MVP.

## 6. Data protection and secrets

- TLS ทุก environment ที่มี user data; redirect HTTP ไป HTTPS.
- encrypt database/storage backup; จำกัด access ตาม least privilege.
- secrets อยู่ใน secret manager/environment variables; ห้าม commit `.env`, API key, webhook secret หรือ production dump.
- ลด PII ใน logs; redact email, phone, `person_id`, token, authorization header และ payment reference ที่อ่อนไหว.
- `person_id` เลขบัตรประชาชน 13 หลักเก็บตรงได้ตาม scope นี้ แต่ต้อง mask ใน UI/log/export, จำกัดการเข้าถึงเลขเต็มเฉพาะ Owner/Admin หรือ permission เฉพาะ, และห้ามใช้ตัดสินสิทธิ์.
- กำหนด retention สำหรับ audit log, files, backups และ soft-deleted data.

## 7. Audit and monitoring

- audit log ต้อง append-only สำหรับ login, role/permission change, invite/disable user, invoice/payment/expense actions; export audit ใช้เมื่อเปิด feature export หลัง MVP.
- log `org_id`, actor, action, entity, timestamp, request/correlation id และ result; ไม่ log secret.
- แจ้งเตือน/monitor เมื่อ login fail ผิดปกติ, permission change, payment reversal, error rate สูง; export ปริมาณมากใช้เมื่อเปิด feature export หลัง MVP.
- backup ต้องเข้ารหัสและมี restore test ตามรอบที่กำหนด.

## 8. Security Acceptance Tests

- User จาก org A อ่าน/แก้ resource ของ org B ไม่ได้ แม้เดา UUID ถูก.
- Member เรียก endpoint Admin/Finance ไม่ได้.
- Sales เห็น customer/deal เฉพาะ `owner_id = user.id`; PM เห็น project เฉพาะ `owner_id = user.id` และ task ใน project scope; Member เห็นเฉพาะ task ที่ `assignee_id = user.id`; Finance เห็น finance data เท่านั้น.
- branch/division/department ที่ส่งมาไม่อยู่ใน chain เดียวกันต้องถูก reject.
- request ที่แก้ `org_id`, `total`, `paid_amount`, `status` ถูก ignore/reject.
- payment สอง request พร้อมกันไม่ทำให้ยอดเกิน invoice total.
- payment post แล้ว `PATCH`/`DELETE` ไม่สำเร็จ; reversal ถูก audit.
- upload file ผิด type/เกินขนาด/download โดยไม่มี permission ไม่สำเร็จ.
- login/reset ถูก rate limit; export rate limit ใช้เมื่อเปิด feature export หลัง MVP; API ไม่เผย secret/stack trace.



