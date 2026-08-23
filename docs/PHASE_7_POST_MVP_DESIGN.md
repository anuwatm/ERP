# Phase 7 Post-MVP Design

สถานะ: Design ready / implementation pending

ที่มา: `gemini.md` หมวด Pending Fixes & Improvements

เป้าหมายของ Phase 7 คือออกแบบงาน Post-MVP ที่ต่อจาก Phase 6 โดยยังไม่บังคับลง implementation ทันที งานหลักมี 4 ชุด:

1. Configurable Number Sequences
2. Inclusive VAT UI Display
3. Project Members Assignment
4. Suppliers and Purchase Orders

---

## 1. Configurable Number Sequences

### Problem

Phase 6 ขยาย `invoice_no` และ `expense_no` เป็น `VARCHAR(30)` แล้ว แต่ `NumberSequenceService` ยังออกเลขแบบเดิม:

```text
000001
```

ระบบควรรองรับ format ที่องค์กรกำหนดเอง เช่น:

```text
INV-YYYYMM-00001
EXP-YY-0001
PO-BR-YYYY-000001
```

### Design Goals

- รองรับ format ต่อ document type
- รองรับ token วันเวลา
- รองรับ reset รายเดือน/ปี/ไม่ reset
- รองรับ branch scope แบบ optional
- ยังคง backward compatible กับเลขเดิม
- กันเลขซ้ำด้วย DB constraint
- จำกัดผลลัพธ์ไม่เกิน 30 ตัวอักษร

### Settings Schema

ใช้ `settings` table เดิม โดยเพิ่ม key:

```text
document_numbering.formats
```

ตัวอย่าง `value_json`:

```json
{
  "invoice": {
    "format": "INV-{YYYY}{MM}-{SEQ:5}",
    "reset": "monthly",
    "scope": "organization",
    "enabled": true
  },
  "expense": {
    "format": "EXP-{YY}-{SEQ:4}",
    "reset": "yearly",
    "scope": "organization",
    "enabled": true
  },
  "po": {
    "format": "PO-{BRANCH}-{YYYY}-{SEQ:6}",
    "reset": "yearly",
    "scope": "branch",
    "enabled": true
  }
}
```

### Supported Tokens

| Token | Meaning | Example |
| --- | --- | --- |
| `{YYYY}` | 4 digit year | `2026` |
| `{YY}` | 2 digit year | `26` |
| `{MM}` | 2 digit month | `08` |
| `{DD}` | 2 digit day | `21` |
| `{BRANCH}` | branch code | `000001` |
| `{SEQ:n}` | padded sequence | `{SEQ:5}` -> `00001` |

### Reset Rules

| reset | sequence key |
| --- | --- |
| `none` | `all` |
| `yearly` | `YYYY` |
| `monthly` | `YYYYMM` |
| `daily` | `YYYYMMDD` |

### Database Design

Current `number_sequences` supports `year_key` only. To support monthly/daily reset cleanly:

```text
number_sequences
  id
  org_id
  branch_id nullable
  branch_key
  doc_type
  period_key varchar(20) default 'all'
  last_number int
  created_at
  updated_at
```

Recommended unique:

```text
UNIQUE(org_id, branch_key, doc_type, period_key)
```

Keep `year` / `year_key` temporarily for backward compatibility, then stop using them in new code.

### Service Design

Add methods:

```php
NumberSequenceService::next(string $orgId, string $docType, ?string $branchId = null, ?CarbonInterface $date = null): string
NumberSequenceService::preview(string $orgId, string $docType, ?string $branchId = null, ?CarbonInterface $date = null): string
```

Flow:

```text
load format setting
derive period_key
lock sequence row
increment last_number
render format tokens
validate length <= 30
return number
```

### Validation

- Format must contain exactly one `{SEQ:n}`
- `n` must be between `1` and `10`
- Final output length must be `<= 30`
- Unknown tokens rejected
- `scope=branch` requires branch id/code
- Generated value must be unique under target table unique constraint

### UI Design

Location: Organization Settings

Controls:

- Document type tabs: Invoice, Expense, Project, Customer, Supplier, PO
- Format input
- Reset select: none/yearly/monthly/daily
- Scope select: organization/branch
- Preview panel: next number
- Validation message under format input

### Tests

- Default format remains `000001`
- Custom invoice format renders `INV-202608-00001`
- Monthly reset starts a new sequence per month
- Branch scope separates sequence per branch
- Concurrent requests do not duplicate numbers
- Invalid format is rejected
- Over-30-character output is rejected

---

## 2. Inclusive VAT UI Display

### Problem

Backend now calculates header discount allocation before VAT. But UI still mostly shows:

```text
Subtotal
Tax
Total
```

For inclusive VAT, user should see the hidden VAT and net subtotal clearly.

### Design Goals

- UI preview must match backend calculation
- Separate gross amount, net amount, VAT, discount, final total
- Keep exclusive/no_tax display simple
- Avoid tax wording ambiguity

### Display Rules

#### Exclusive VAT

```text
Subtotal before tax
Header discount
Taxable base
VAT
Total
```

Example:

```text
Subtotal before tax: 2,000.00
Header discount: -100.00
Taxable base: 1,900.00
VAT 7%: 133.00
Total: 2,033.00
```

#### Inclusive VAT

```text
Gross subtotal (VAT included)
Header discount
Gross after discount
Net subtotal before VAT
VAT included
Total
```

Example:

```text
Gross subtotal: 1,070.00
Header discount: -107.00
Gross after discount: 963.00
Net subtotal before VAT: 900.00
VAT included: 63.00
Total: 963.00
```

#### No Tax

```text
Subtotal
Header discount
Total
```

### Backend Shape

Keep current persisted columns:

```text
subtotal
discount_amount
tax_amount
total
```

Add computed props for Inertia response:

```json
{
  "tax_summary": {
    "gross_subtotal": "1070.00",
    "header_discount": "107.00",
    "gross_after_discount": "963.00",
    "net_subtotal": "900.00",
    "tax_amount": "63.00",
    "total": "963.00"
  }
}
```

No schema change required.

### Frontend Design

In `Finance/Invoices.tsx`:

- Replace simple totals card with mode-aware totals card
- Add line preview per item:
  - line total
  - allocated header discount
  - taxable base
  - VAT
- Keep `Line Discount` field visible

### Print/Export Wording

For inclusive VAT:

```text
Prices include VAT. VAT amount is calculated from the discounted gross amount.
```

For exclusive VAT:

```text
VAT is calculated after line and header discounts.
```

### Tests

- Frontend preview matches backend values for exclusive VAT
- Frontend preview matches backend values for inclusive VAT
- Header discount allocated proportionally across multiple tax rates
- Rounding residual assigned to last line
- No tax mode sets VAT to zero

---

## 3. Project Members Assignment

### Problem

Current access model:

- Owner/Admin sees all
- Project Manager sees owned projects
- Member sees assigned tasks

This is enough for MVP, but weak for real projects with multiple contributors.

### Design Goals

- A project can have many members
- Members can see project context without being project owner
- Role inside project is separate from global RBAC
- Existing owner/assignee behavior must keep working

### Database Design

New table:

```text
project_members
  id uuid primary
  org_id uuid not null
  project_id uuid not null
  user_id uuid not null
  role varchar(30) not null default 'member'
  created_by uuid nullable
  updated_by uuid nullable
  created_at
  updated_at
```

Roles:

```text
manager
contributor
viewer
```

Constraints:

```text
UNIQUE(org_id, project_id, user_id)
INDEX(org_id, user_id)
INDEX(org_id, project_id)
```

### Access Rules

| Actor | Project view | Project update | Task view | Task update |
| --- | --- | --- | --- | --- |
| Owner/Admin | all | all | all | all |
| Project owner | owned | owned | project tasks | project tasks |
| Project member manager | member projects | limited project fields | project tasks | project tasks |
| Project member contributor | member projects | no | project tasks | assigned task status/comment |
| Project member viewer | member projects | no | project tasks | no |
| Task assignee only | no project list unless member | no | assigned task | status/comment |

### Support Class Changes

`ProjectAccess::scopeProjects()`:

```text
org scoped
owner/admin: all
else: owner_id = user.id OR exists project_members row
```

`TaskAccess::scopeTasks()`:

```text
org scoped
owner/admin: all
else: assignee_id = user.id OR project owner OR project member
```

### UI Design

Project page:

- Members panel
- Add member select
- Project role select
- Remove member action
- Badge per project role

Write actions require:

- `projects.update` for project owner/admin
- new permission `projects.members.manage` for managing members

### Migration Path

- Keep `projects.owner_id`
- Do not auto-create `project_members` for owner
- Access code treats owner as implicit manager
- Existing tests should continue passing

### Tests

- Member can see project if in `project_members`
- Contributor cannot edit project fields
- Manager can update tasks in member project
- Viewer cannot update tasks
- Cross-org member assignment rejected
- Removing member removes project visibility but not assigned task visibility

---

## 4. Suppliers and Purchase Orders

### Problem

Expenses currently allow `supplier_id` as nullable UUID without FK. This was acceptable for MVP, but finance and inventory need real supplier/PO records.

### Design Goals

- Supplier master data
- Purchase order lifecycle
- Link PO to supplier and product/service lines
- Allow expenses to reference supplier safely
- Prepare future inventory receiving without implementing inventory now

### Suppliers Schema

```text
suppliers
  id uuid primary
  org_id uuid not null
  supplier_code varchar(30) not null
  name varchar(255) not null
  tax_id varchar(50) nullable
  email varchar(255) nullable
  phone varchar(50) nullable
  address text nullable
  payment_terms varchar(100) nullable
  status varchar(20) default 'active'
  note text nullable
  created_by uuid nullable
  updated_by uuid nullable
  created_at
  updated_at
  deleted_at
```

Unique:

```text
UNIQUE(org_id, supplier_code)
```

### Purchase Order Schema

```text
purchase_orders
  id uuid primary
  org_id uuid not null
  po_no varchar(30) not null
  supplier_id uuid not null
  status varchar(30) default 'draft'
  issue_date date not null
  expected_date date nullable
  subtotal decimal(18,2) default 0
  discount_amount decimal(18,2) default 0
  tax_amount decimal(18,2) default 0
  total decimal(18,2) default 0
  currency char(3) default 'THB'
  notes text nullable
  approved_by uuid nullable
  approved_at timestamp nullable
  cancelled_at timestamp nullable
  created_by uuid nullable
  updated_by uuid nullable
  created_at
  updated_at
  deleted_at
```

```text
purchase_order_items
  id uuid primary
  org_id uuid not null
  purchase_order_id uuid not null
  product_id uuid nullable
  description varchar(500) not null
  quantity decimal(18,4) not null
  unit varchar(30) nullable
  unit_price decimal(18,2) not null
  discount_amount decimal(18,2) default 0
  tax_rate decimal(5,2) default 0
  line_total decimal(18,2) not null
  sort_order int default 0
  created_at
  updated_at
```

Statuses:

```text
draft
sent
approved
partially_received
received
cancelled
closed
```

### Expense Link

Phase 7 implementation should migrate:

```text
expenses.supplier_id -> FK suppliers.id nullable nullOnDelete
expenses.purchase_order_id nullable FK purchase_orders.id nullOnDelete
```

Rules:

- Expense supplier must be same org
- Expense PO must be same supplier if both selected
- Paid/rejected expenses cannot change supplier/PO link

### UI Routes

```text
/suppliers
/purchase-orders
```

### Permission Catalog

```text
suppliers.view
suppliers.create
suppliers.update
suppliers.delete
purchase_orders.view
purchase_orders.create
purchase_orders.update
purchase_orders.approve
purchase_orders.cancel
```

Default role mapping:

- owner/admin: all
- finance: all supplier/PO finance permissions
- project_manager: `suppliers.view`, `purchase_orders.view`
- viewer: none by default

### Tests

- Supplier CRUD org scoped
- Supplier code unique per org
- PO totals server-calculated
- PO status transitions enforce rules
- Expense supplier/PO chain validation
- Cross-org supplier/PO rejected
- Approved PO cannot be edited except allowed status transitions

---

## Implementation Order

Recommended order:

1. Inclusive VAT UI Display
2. Configurable Number Sequences
3. Suppliers
4. Purchase Orders
5. Project Members

Reason:

- VAT UI is lowest schema risk and closes current finance UX gap
- Numbering uses existing `VARCHAR(30)` work from Phase 6
- Suppliers should exist before Purchase Orders
- Project Members touches access control broadly, so do after finance additions

---

## Phase 7 Exit Criteria

Design phase is complete when:

- checklist has all design tasks checked
- this document exists and covers schema, UI, permissions, migration path, and tests
- implementation can start from the recommended order without re-opening product decisions

Implementation is a separate follow-up phase unless explicitly started.
