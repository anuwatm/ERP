# Module: API

  Meta   Value  
  ---   ---  
  Module code   `api`  
  Version   V1  
  Priority   P1  
  Schema กลาง   [`../database/DATABASE.md`](../database/DATABASE.md) §9.8  

---

## 1. ชื่อ Module

**API** — REST/RPC สำหรับ integration และ automation

---

## 2. รายละเอียด / หน้าที่

- เปิด endpoint หลักของระบบ
- API token authentication
- Webhook (ร่วม Automation)
- Rate limit
- Audit การใช้ API
- API docs (OpenAPI)

### Endpoint หลัก

customers, contacts, deals, projects, tasks, invoices, payments, expenses, products, reports

---

## 3. Workflow

### 3.1 ออก token

```text
Admin สร้าง api_tokens (name, scopes, expires)
→ แสดง token ครั้งเดียว
→ เก็บ token_hash เท่านั้น
```

### 3.2 เรียก API

```text
Client ส่ง Authorization: Bearer <token>
→ ตรวจ hash, revoked, scopes
→ enforce org_id จาก token
→ rate limit
→ เรียก domain service เดียวกับ UI
→ อัปเดต last_used_at
→ audit สำคัญ
```

### 3.3 Revoke

```text
revoked_at = now → token ใช้ไม่ได้ทันที
```

---

## 4. Data Flow

```text
External system
      │ Bearer token
      ▼
API Gateway / Middleware (auth, rate limit, scope)
      │
      ▼
Domain modules (customers, invoices, ...)
      │
      ├──► api_tokens.last_used_at
      └──► audit_logs
```

---

## 5. โครงสร้าง Database

### ตารางหลัก (own)

  Table   Role  
  ---   ---  
  `api_tokens`   credential ภายนอก  

### Field สำคัญ

`name`, `token_hash`, `scopes`, `last_used_at`, `expires_at`, `revoked_at`, `created_by`

### Business rules

- ห้ามเก็บ raw token ใน DB
- scope จำกัด module/action
- rate limit ต่อ token และต่อ org
- ใช้ permission model เดียวกับ UI ให้มากที่สุด
