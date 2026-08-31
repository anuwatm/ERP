# ERP Modules Documentation

เอกสารรายละเอียดแยกตาม module  
**Project lock:** [`../../PROJECT.md`](../../PROJECT.md) · **MVP:** [`../../MVP_SCOPE.md`](../../MVP_SCOPE.md)  
**โครงสร้างฐานข้อมูลกลาง:** [`../database/DATABASE.md`](../database/DATABASE.md) (sync กับ AD ก่อน)

> ทุก module ต้องอ้างอิงตารางจาก DATABASE.md + ARCHITECTURE_DECISIONS เท่านั้น  
> ห้ามนิยาม schema ใน module doc ให้ขัดกับ schema กลาง / AD  
> Priority P0 ≠ ต้อง implement ใน MVP — ดู AD-01  
> MVP implement เฉพาะรายการใน `MVP_SCOPE.md`; module อื่นเป็น backlog แม้มีเอกสารแล้ว

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
| [08-quotations.md](./08-quotations.md) | Quotations | V1 หลัง MVP | P1 |

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
| [13-suppliers.md](./13-suppliers.md) | Suppliers | V1 หลัง MVP | P1 |
| [14-invoices.md](./14-invoices.md) | Invoices | V1 | P0 |
| [15-payments.md](./15-payments.md) | Payments | V1 | P0 |
| [16-expenses.md](./16-expenses.md) | Expenses / Costs | V1 | P0 |

### Insights

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [17-dashboard.md](./17-dashboard.md) | Dashboard | V1 | P0 |
| [18-reports.md](./18-reports.md) | Reports | V1 หลัง MVP | P0 backlog |

### Platform

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [19-files.md](./19-files.md) | Files / Documents | V1 MVP subset / full หลัง MVP | P0 limited / P1 full |
| [20-notifications.md](./20-notifications.md) | Notifications | V1 หลัง MVP | P1 |
| [21-automation.md](./21-automation.md) | Automation | V1 หลัง MVP | P1 |
| [22-import-export.md](./22-import-export.md) | Import / Export | V1 หลัง MVP | P1 |
| [23-api.md](./23-api.md) | API | V1 หลัง MVP | P1 |

### Operations (V2+)

| File | Module | Version | Priority |
| --- | --- | --- | --- |
| [24-purchase-orders.md](./24-purchase-orders.md) | Purchase Orders | V2 | P2 |
| [25-inventory.md](./25-inventory.md) | Inventory / Stock | V2 | P2 |
| [26-employees.md](./26-employees.md) | HR: Employees | V1 light / V2 | P1 |
| [27-attendance-leave.md](./27-attendance-leave.md) | Attendance / Leave | V2 | P2 |
| [28-payroll.md](./28-payroll.md) | Payroll | Phase 16B implemented | Done |
| [29-ai-assistant.md](./29-ai-assistant.md) | AI Assistant | V2 | P2 |
| [30-accounting-integration.md](./30-accounting-integration.md) | Accounting Integration | V2 | P2 |
| [31-customer-portal.md](./31-customer-portal.md) | Customer Portal | V3 | P3 |

---

## Core Business Flow

```text
MVP phase order: Foundation -> CRM/Sales -> Finance (deal/manual invoice + payment) -> Delivery (project/task + project links) -> Executive dashboard
End-to-end business flow after MVP complete: Customer -> Deal -> Invoice -> Payment -> Project -> Task -> Executive Dashboard
Post-MVP: Quotations -> Milestones -> Reports / Export / Notifications / Automation
```

## Schema Change Rule

1. แก้ [`docs/database/DATABASE.md`](../database/DATABASE.md) ก่อน
2. อัปเดต module file ที่เกี่ยวข้อง
3. ตรวจ FK cross-module ให้ไม่ขัดกัน



