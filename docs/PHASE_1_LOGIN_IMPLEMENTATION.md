# Phase 1: Login Implementation

## Stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13 / PHP 8.3 |
| Frontend | React 18 + TypeScript + Inertia + Vite |
| Database | MariaDB 11.x / MySQL-compatible |
| Authentication | Laravel Breeze **local** (`auth_provider=local`); future OIDC/SSO supported |
| Session | Encrypted server-side database session + secure cookie |

Application source: [`../backend`](../backend) (สร้างตอนเริ่ม implement)

Project lock: [`../PROJECT.md`](../PROJECT.md) · Auth rules: AD-03

## Login Surface

Laravel Breeze provides:

- register, login, logout
- password policy and hashed password (`users.password`)
- session regeneration after login
- login rate limiting
- password reset flow
- email verification route with signed URL and throttle
- password confirmation for sensitive actions
- React/TypeScript auth pages with CSRF protection

## MariaDB / MySQL Setup

1. Create database `erp` with `utf8mb4` (local dev already uses `127.0.0.1:3306`, user `root`, password `root`).
2. Copy values from `backend/.env.mysql.example` into `backend/.env` (เมื่อ scaffold แล้ว). ใช้ `DB_CONNECTION=mysql` ทั้ง MariaDB และ MySQL.
3. Set a real MySQL username/password. Never commit `backend/.env`.
4. Run `php artisan migrate` with project `php.ini` loaded.

## Required Schema Extensions

Keep Laravel's `users` table shape แล้ว map ตาม schema กลาง (`org_id` ไม่ใช่ `organization_id`):

| Table | Required fields |
| --- | --- |
| `organizations` | `id`, `name`, `currency`, `timezone`, `status` |
| `branches` | `id`, `org_id`, `code`, `name`, `is_head_office`, `status` |
| `divisions` | `id`, `org_id`, `branch_id`, `code`, `name`, `status` |
| `departments` | `id`, `org_id`, `branch_id`, `division_id`, `code`, `name`, `status` |
| `users` | `org_id`, `branch_id`, `division_id`, `department_id`, `status`, `auth_provider`, `auth_provider_user_id`, `password`, `remember_token`, `last_login_at`, `display_name`, `person_id`, `position`, `email_verified_at` |
| `roles` | `org_id`, `code`, `name`, `is_system` |
| `permissions` | `code`, `module` |
| `role_permissions` | `role_id`, `permission_id` |
| `user_roles` | `user_id`, `role_id` |
| `audit_logs` | `org_id`, `actor_user_id`, `action`, `entity_type`, `entity_id`, `before_json`, `after_json`, `ip_address`, `user_agent`, `request_id` |
| Laravel framework | `sessions`, `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `failed_jobs` |

### System roles (seed ตอน register)

`owner`, `admin`, `sales`, `project_manager`, `finance`, `member`, `viewer`

## Registration Transaction

```text
validate input
  -> DB transaction
  -> create organization (currency=THB, timezone=Asia/Bangkok)
  -> create head office branch
  -> create default division
  -> create default department
  -> create user(status=active, org_id, branch_id, division_id, department_id, auth_provider=local, password hashed)
  -> create system roles and permission mappings
  -> attach Owner role to user
  -> append audit log: auth.register
  -> commit
  -> send verification email
  -> regenerate session
```

## Future Module Contract

- business model owns `org_id`
- server derives tenant from authenticated user; never request payload
- routes declare permission middleware: `permission:invoices.create`
- Member routes also check resource owner/assignee
- audit sensitive create/update/delete and every financial state transition

## Security Gates

- `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SameSite=Lax` in production
- production requires `APP_DEBUG=false`, HTTPS, non-default `APP_KEY`, real mail provider
- do not expose `password`, `remember_token`, session ID, authorization header or secrets in Inertia props/logs
- use Laravel validation + authorization policy for every future endpoint
- use DB transaction/idempotency key for financial actions
- add 2FA or external OIDC/SSO before privileged production rollout

## Implementation checklist

| Item | Status |
| --- | --- |
| Scaffold Laravel + Breeze (React/TS) | Done |
| Register creates org hierarchy + Owner + system roles + permissions | Done |
| Login active-only, last_login_at, audit `auth.login` | Done |
| Permission catalog + role defaults | Done |
| Middleware `permission:*` | Done |
| Invite user + one-time accept link | Done |
| Disable user (status inactive) | Done |
| Admin Dashboard widgets | Done |
| Feature tests | Done: 51 tests / 235 assertions |

## Tests Before Release

- valid login regenerates session and reaches dashboard
- invalid password is rate limited
- inactive user cannot login
- invited user cannot login until accept
- active user without `email_verified_at` cannot reach dashboard; can access verify/resend only
- user from organization A cannot change users in organization B
- Member cannot invite / cannot open Team page
- assign user ต้อง reject branch/division/department ที่ไม่อยู่ใน hierarchy เดียวกัน
- password reset and email verification tokens expire and cannot be replayed
- privileged action should require password confirmation for sensitive finance actions when those routes are enabled

## Related Implementation Docs

- [VALIDATION_RULES.md](./VALIDATION_RULES.md)
- [PHASE_ACCEPTANCE_CRITERIA.md](./PHASE_ACCEPTANCE_CRITERIA.md)
- [ROUTES_AND_SCREENS.md](./ROUTES_AND_SCREENS.md)
- [SEED_DATA.md](./SEED_DATA.md)







## Frontend structure

```text
backend/resources/js/
  Components/
  Layouts/
  Pages/
  Types/
  Utils/
```

TypeScript page props ต้องมี shared types สำหรับ Inertia props หลัก.


## Current Implementation Status (2026-07-25)

Phase 1 coding started and core foundation is implemented in `backend/`.

Verified:

- MariaDB migration passes with project `php.ini`
- Root web URL `http://localhost/ERP/` returns 200
- Login URL `http://localhost/ERP/login` returns 200
- PHPUnit: 43 tests, 166 assertions
- ESLint, Prettier check, TypeScript/Vite build pass

Remaining Phase 1 checklist items are tracked in root [`../checklist.md`](../checklist.md). Do not treat this document as the source of truth for task completion.






