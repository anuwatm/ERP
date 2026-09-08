# ERP Modules Documentation

เอกสารรายละเอียดแยกตาม module  
**Project lock:** [`../../PROJECT.md`](../../PROJECT.md) · **MVP:** [`../../MVP_SCOPE.md`](../../MVP_SCOPE.md)  
**Schema runtime:** migrations ใน `backend/database/migrations`; ภาพรวมที่ sync แล้ว: [`../../document/DATABASE_ERD.md`](../../document/DATABASE_ERD.md)

> Module docs ที่ยังระบุ MVP/V2 เป็น planning history. ก่อนเปลี่ยนระบบต้องตรวจ migration, routes และ test ปัจจุบันเสมอ

แผนภาพรวม: [`../../ERP_FEATURE_PLAN.md`](../../ERP_FEATURE_PLAN.md)

---

## Index

### Foundation

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [01-organization.md](./01-organization.md) | Organization / Branch / Division / Department | V1 | P0 (MVP foundation) |
| [02-user-role-permission.md](./02-user-role-permission.md) | User / Role / Permission | V1 | P0 |
| [03-settings.md](./03-settings.md) | Settings | V1 | P0 |
| [04-audit-log.md](./04-audit-log.md) | Audit Log | V1 | P0 |

### CRM & Sales

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [05-crm.md](./05-crm.md) | CRM (Customers) | V1 | P0 |
| [06-contacts.md](./06-contacts.md) | Contacts | V1 | P0 |
| [07-deals.md](./07-deals.md) | Leads / Deals / Sales Pipeline | V1 | P0 |
| [08-quotations.md](./08-quotations.md) | Quotations | Phase 9 implemented | Done |

### Delivery

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [09-projects.md](./09-projects.md) | Projects | V1 | P0 |
| [10-tasks.md](./10-tasks.md) | Tasks | V1 | P0 |
| [11-milestones.md](./11-milestones.md) | Milestones | V1 หลัง MVP | P1 |

### Finance

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [12-products.md](./12-products.md) | Product / Service Catalog | V1 | P0 |
| [13-suppliers.md](./13-suppliers.md) | Suppliers | Phase 7 implemented | Done |
| [14-invoices.md](./14-invoices.md) | Invoices | V1 | P0 |
| [15-payments.md](./15-payments.md) | Payments | V1 | P0 |
| [16-expenses.md](./16-expenses.md) | Expenses / Costs | V1 | P0 |

### Insights

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [17-dashboard.md](./17-dashboard.md) | Dashboard | V1 | P0 |
| [18-reports.md](./18-reports.md) | Reports | Partial: dashboard, tax, treasury and GL reports | Partial |

### Platform

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [19-files.md](./19-files.md) | Files / Documents | Phase 17 DMS current | P0 |
| [20-notifications.md](./20-notifications.md) | Notifications | Phase 8 in-app/email plus Phase 21 durable outbox, external channels and quiet hours | Done |
| [21-automation.md](./21-automation.md) | Automation | Planned | Planned |
| [22-import-export.md](./22-import-export.md) | Import / Export | Partial: tax/payroll/e-Tax exports and statement import | Partial |
| [23-api.md](./23-api.md) | API | V1 หลัง MVP | P1 |

### Operations (V2+)

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [24-purchase-orders.md](./24-purchase-orders.md) | Purchase Orders | Phase 7 implemented | Done |
| [25-inventory.md](./25-inventory.md) | Inventory / Stock | Phase 15 implemented | Done |
| [26-employees.md](./26-employees.md) | HR: Employees | V1 light / V2 | P1 |
| [27-attendance-leave.md](./27-attendance-leave.md) | Attendance / Leave | V2 | P2 |
| [28-payroll.md](./28-payroll.md) | Payroll | Phase 16B implemented | Done |
| [29-ai-assistant.md](./29-ai-assistant.md) | AI Assistant | V2 | P2 |
| [30-accounting-integration.md](./30-accounting-integration.md) | Accounting Integration | V2 | P2 |
| [31-customer-portal.md](./31-customer-portal.md) | Customer Portal | V3 | P3 |

---

## Core Business Flow

```text
Historical MVP order: Foundation -> CRM/Sales -> Finance -> Delivery -> Executive dashboard.
Current flow summary: Customer -> Deal -> Quotation/Invoice -> Payment -> Treasury/GL -> Project/Task -> Dashboard. See [`../CURRENT_IMPLEMENTATION.md`](../CURRENT_IMPLEMENTATION.md) for implemented modules and boundaries.
```

## Schema Change Rule

1. แก้ [`docs/database/DATABASE.md`](../database/DATABASE.md) ก่อน
2. อัปเดต module file ที่เกี่ยวข้อง
3. ตรวจ FK cross-module ให้ไม่ขัดกัน



