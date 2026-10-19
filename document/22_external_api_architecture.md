# External Read-only API Architecture

API สำหรับให้ backend ของแอปภายนอกอ่านข้อมูล ERP ผ่าน `/api/v1` โดยใช้ integration key, scopes และ visibility policy ไม่มี business Write API และไม่เปิด payroll/salary/bank/identity fields

สัญญา endpoints: [OpenAPI](../docs/openapi/external-read-v1.json) · การใช้งาน: [Read API Guide](../docs/EXTERNAL_READ_API.md) · ตัวอย่าง: [Client / Receiver](../examples/external-api)

## Architecture และขอบเขตข้อมูล

```mermaid
flowchart TB
    Admin[Admin session + Password Confirmation + 2FA] --> Manage[API Access / Webhook Management]
    Manage --> Key[Integration Key: hash-only secret]
    Manage --> Policy[Scope + Party / HR Branch / Document Policy]
    Client[External App Backend] -->|HTTPS GET / HEAD + Bearer| Auth[AuthenticateIntegrationKey]
    Key --> Auth
    Auth --> Gate[Expiry / Revoke / Organization / Rate Limit]
    Gate --> ACL[Scope + Resource Visibility + Parent Guards]
    Policy --> ACL
    ACL --> Summary[Sales / Finance / Projects / Inventory / HR / Procurement / Manufacturing Summaries]
    ACL --> Lists[Reference / Products / Employees / Sales / Procurement / Projects / Approvals / Manufacturing]
    ACL --> Documents[Document Metadata / Private Download]
    Summary --> DB[(ERP Data)]
    Lists --> DB
    Documents --> DB
    Documents --> Scan[Current Version + Clean Scan + Document ACL]
    Scan --> Storage[Private Storage]
    Summary --> Reply[Allowlisted JSON / Decimal Strings / no-store]
    Lists --> Reply
    Reply --> Client
```

- Organization มาจาก key ไม่รับ client เลือก `org_id`
- Customer/Supplier key ถูกจำกัด party และเอกสารที่เผยแพร่; HR ต้องระบุ branch policy; Documents ต้อง explicit document IDs/classification/link ACL
- Scope summary ไม่ให้สิทธิ์ list/detail; ราคา ต้นทุน สูตร และ download เป็นสิทธิ์แยก
- Employee ใช้ UUID/employee_code ไม่ส่ง national_id, salary, bank, private contact, GPS หรือเหตุผลลา
- Document download ตรวจ current version/scan/ACL ทุกครั้งและไม่คืน storage paths/public URLs

## Request Sequence

```mermaid
sequenceDiagram
    participant App as External Backend
    participant Auth as Integration Authentication
    participant Access as Resource Access
    participant Data as ERP Database / Private Storage
    App->>Auth: GET /api/v1/resource + Bearer key
    Auth->>Auth: Validate hash, expiry, revoke, org, rate limit
    alt Credential invalid / scope denied
        Auth-->>App: Sanitized 401 / 403 / 429 + no-store
    else Allowed credential and scope
        Auth->>Access: Key-derived organization + policy
        Access->>Access: Validate filters, current permissions and parent visibility
        Access->>Data: Bounded query / authorized file access
        Data-->>Access: Permitted records
        Access-->>App: Allowlisted data + request_id + pagination metadata
    end
```

## Key Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Configured: Admin selects scopes and visibility
    Configured --> Active: Create key / reveal secret once
    Active --> Active: Authorized read within scope
    Active --> Expired: expires_at reached
    Active --> Revoked: Admin revokes
    Active --> Replacement: Create new key with changed scopes / policy
    Replacement --> Revoked: Switch caller then revoke old key
    Expired --> [*]
    Revoked --> [*]
```

API key secret เก็บเฉพาะ hash ส่วน webhook signing secret เก็บแบบ encrypted เพื่อใช้ลงลายเซ็น Key management ใช้ admin session และ permission แยกจาก integration key

## Pagination และ Sync

```mermaid
flowchart LR
    Start[Bootstrap read: fixed filters] --> Page[Read bounded page]
    Page --> Save[Upsert by UUID]
    Save --> More{next_cursor?}
    More -->|Yes: same filters| Page
    More -->|No| Mark[Save fixed sync_watermark]
    Mark --> Delta{delta_supported?}
    Delta -->|Yes| Since[Next read: inclusive updated_since]
    Since --> Page
    Delta -->|No| Full[Period read / Full reconciliation]
    Full --> Page
    Recovery[Cursor expired / ACL changed / Hard delete / Derived change] --> Full
```

Default50/max100, encrypted cursor30นาทีผูก credential/path/filters, stable order `(updated_at,id)` สำหรับ row lists Watermark คงเดิมทั้งรอบ Resource ที่ไม่รองรับ delta ตอบ422 เมื่อส่ง updated_since Inventory balances ใช้ grouped tuple cursor

Live pagination ไม่ใช่ database snapshot ผู้รับต้องรับ duplicates และทำ full reconciliation เพื่อเอาข้อมูลที่ลบหรือไม่มีสิทธิ์แล้วออกจากสำเนา ไม่ใช้ generated_at ของหน้าสุดท้ายเป็น delta checkpoint

## Outbound Webhook / Transactional Outbox

```mermaid
sequenceDiagram
    participant Domain as Internal Domain Save
    participant DB as ERP Transaction
    participant Dispatch as Scheduled Dispatcher
    participant Receiver as Verified Receiver
    participant Read as Scoped Read API
    Domain->>DB: Save source + immutable event + deliveries
    DB-->>Domain: Commit together / rollback together
    Dispatch->>DB: Claim pending delivery with lease
    Dispatch->>Dispatch: Recheck key / party / row access and public DNS
    Dispatch->>Receiver: HTTPS POST minimal pointer + timestamped HMAC
    Receiver->>Receiver: Verify exact bytes / timestamp / dedupe event ID
    Receiver-->>Dispatch: 2xx after durable acceptance
    Dispatch->>DB: Delivered or bounded retry / dead-letter
    Receiver->>Read: GET current scoped resource
    Read-->>Receiver: Current allowlisted state
```

Events: invoice payment status, stock balance, task status, approval status ไม่มีชื่อ/ยอดเงิน/PII ใน payload การส่งเป็น at-least-once ต้อง dedupe และรับ out-of-order ได้ ใช้ model save paths ไม่ครอบคลุม bulk SQL/delete

ค่าเริ่มต้น `EXTERNAL_API_WEBHOOKS_ENABLED=false` Admin ต้องตั้ง endpoint และ verify ownership ก่อนเปิดใช้งาน HTTPS443/public DNS/IP pinning, no redirects/proxy, bounded time/response size และ encrypted signing secret การ rotate secret จะ pause จน verify ใหม่

POST ใน diagram คือการส่งสัญญาณออกไปยัง receiver; `/api/v1` ของ ERP มีเฉพาะ GET/HEAD ไม่มี task/document writes หรือ approve/reject actions
