# Phase 23.1 — Form Inventory & Final Rollout Reconciliation

## 1. ประวัติการสำรวจตั้งต้น (Baseline Discovery Inventory — 2026-09-11)

ตรวจจาก source วันที่ 2026-09-11; **เป็นจำนวนที่สำรวจตั้งต้น ไม่ใช่จำนวนที่เปลี่ยนแล้วในขณะนั้น**.
- สำรวจ 60 Pages, 92 JS/TS source files, 252 registered routes (173 write routes).
- 91 form/action surfaces ที่สำรวจพบ: เปลี่ยนแล้ว 4, ต้องเปลี่ยน 66, มี integration gap 1, ข้อยกเว้น 20.
- ข้อมูลตารางด้านล่างในข้อ 3 คือ snapshot ประวัติ ณ วันเริ่มโครงการสำรวจ (Baseline Snapshot 2026-09-11) เพื่อการอ้างอิงย้อนหลัง.

---

## 2. สรุปผลการปรับปรุงและการตรวจรับสมบูรณ์ (Final Reconciliation & Closure — 2026-09-16)

ณ วันปิด Phase 23.1.6 (2026-09-16):
- **ขอบเขตการสำรวจ 60 หน้าจอ 92 ไฟล์ได้รับการ Reconcile ครบถ้วน 100%**:
  - **70 surfaces**: แปลงเป็น Modal Dialog ภายใต้มาตรฐาน `CreateEditDialog` ครบถ้วน พร้อมระบบ Discard Guard (`useDiscardGuard`), Submission Key (`useSubmissionKey`), และ Dirty Detection ละเอียดทุก field (รวมการแก้ไขข้อบกพร่อง E01 ใน `OrgStructureDialogs.tsx` ด้วย `orgStructureDirty.ts` ครอบคลุมทั้ง Branch, Division, Department).
  - **20 surfaces**: ได้รับการรับรองเป็น **Documented Exceptions** อย่างเป็นทางการ เนื่องจากเป็นกระบวนการเฉพาะทาง (Specialized/Action Workflows) ได้แก่ นโยบายความปลอดภัยองค์กร (Security 2FA Policy), การนำเข้าไฟล์สเตทเมนต์ธนาคาร (Bank Statement Import), การบันทึกเวลาเข้า-ออกงาน (Attendance Clock In/Out Events), การออกเอกสาร e-Tax แบบ Batch/Direct Generator, และคำสั่งการเงินที่ไม่สามารถย้อนคืนได้ (Irreversible Posting/Reimbursement/Rejection Actions) ซึ่งมี UI เฉพาะตัวที่เหมาะสมอยู่แล้ว.
  - **1 gap (G01)**: สะสางการเรียก Attendance Privacy Update ให้สอดคล้องกับ Controller และ Route Authorization.
  - **12 backend-only write routes**: คงสถานะ backend-only ตามสถาปัตยกรรมเดิมอย่างเคร่งครัด ไม่สร้าง UI แปลกปลอมโดยไม่มี requirement.
  - **Role Management**: เพิ่มการรองรับ Create Role (`roles.store`) และ Edit Role (`roles.update`) ภายใต้ `RolePermissionsDialog.tsx` โดยใช้ `CreateEditDialog` พร้อม Discard Guard ครบถ้วน และปรับปรุง Route Document ใน `docs/ROUTES_AND_SCREENS.md` ให้ตรงกับ `backend/routes/web.php` (`GET /roles` ใช้ `roles.view`, `POST/PATCH` ใช้ `roles.manage`).
  - **Organization Structure UI Gate (E03)**: เพิ่ม UI Permission Gate ใน `OrganizationStructure.tsx` สำหรับผู้ถือสิทธิ์ `settings.structure.update` เพื่อซ่อนปุ่ม Create/Edit/Disable/Delete และ Dialogs สำหรับผู้ใช้ที่มีเฉพาะสิทธิ์อ่าน (`settings.structure.view`).
- **สถานะการทดสอบล่าสุด (Validation Results — 2026-09-16)**:
  - Backend Regression: **316 passed, 2 skipped (2,609 assertions / 0 failures)** รันจริงผ่าน `php artisan test --compact` (เพิ่ม RoleEditorTest 5 tests / 34 assertions จาก baseline เดิม 311 tests).
  - Frontend Tests: **34 passed (0 failures)** รันจริงผ่าน `node --test backend/tests/Frontend/*.test.mjs` (เพิ่มชุดทดสอบ E01 Dirty Detection 11 assertions).
  - Static Analysis: TypeScript `tsc --noEmit` ผ่าน 0 errors, ESLint ผ่าน 0 warnings / 0 errors.

---

## 3. ตารางประวัติการสำรวจตั้งต้น (Baseline Discovery Details — 2026-09-11)

| ID | Module / form | สถานะ | Entry point / route action | เดิม → เป้าหมาย |
| --- | --- | --- | --- | --- |
| F001 | Accounting/ChartOfAccounts: Account | ต้องเปลี่ยน | backend/resources/js/Pages/Accounting/ChartOfAccounts.tsx:35; POST /accounting/chart-of-accounts (accounting.chart-of-accounts.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F002 | Accounting/Journals: Manual journal | ต้องเปลี่ยน | backend/resources/js/Pages/Accounting/Journals.tsx:56; POST /accounting/journals (accounting.journals.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F003 | Accounting/Periods: Accounting period | ต้องเปลี่ยน | backend/resources/js/Pages/Accounting/Periods.tsx:27; POST /accounting/periods (accounting.periods.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F004 | Admin/Roles: Role permissions | ต้องเปลี่ยน | backend/resources/js/Pages/Admin/Roles.tsx:168; PATCH /roles/{role}/permissions (roles.permissions.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F005 | Admin/Users: Invite user | ต้องเปลี่ยน | backend/resources/js/Pages/Admin/Users.tsx:114; POST /users/invite (users.invite) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F006 | Admin/Users: Edit user | ต้องเปลี่ยน | backend/resources/js/Pages/Admin/Users.tsx:486; PATCH /users/{user} (users.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F007 | Delivery/Projects: Project | ต้องเปลี่ยน | backend/resources/js/Pages/Delivery/Projects.tsx:261; POST /projects (projects.store); PATCH /projects/{project} (projects.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F008 | Delivery/Projects: Project member | ต้องเปลี่ยน | backend/resources/js/Pages/Delivery/Projects.tsx:402; POST /projects/{project}/members (projects.members.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F009 | Delivery/Tasks: Task | ต้องเปลี่ยน | backend/resources/js/Pages/Delivery/Tasks.tsx:383; POST /tasks (tasks.store); PATCH /tasks/{task} (tasks.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F010 | Delivery/Tasks: Task checklist item | ต้องเปลี่ยน | backend/resources/js/Pages/Delivery/Tasks.tsx:487; POST /tasks/{task}/checklists (tasks.checklists.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F011 | Delivery/Tasks: Task comment | ต้องเปลี่ยน | backend/resources/js/Pages/Delivery/Tasks.tsx:527; POST /tasks/{task}/comments (tasks.comments.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F012 | Documents/Index: Document and initial version | ต้องเปลี่ยน | backend/resources/js/Pages/Documents/Index.tsx:45; POST /documents (documents.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F013 | Documents/Index: Retention policy | ต้องเปลี่ยน | backend/resources/js/Pages/Documents/Index.tsx:119; POST /documents/retention-policies (documents.retention-policies.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F014 | Documents/Index: Document category | ต้องเปลี่ยน | backend/resources/js/Pages/Documents/Index.tsx:165; POST /documents/categories (documents.categories.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F015 | Documents/Index: Document version | ต้องเปลี่ยน | backend/resources/js/Pages/Documents/Index.tsx:284; POST /documents/{document}/versions (documents.versions.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F016 | Documents/Index: Document link | ต้องเปลี่ยน | backend/resources/js/Pages/Documents/Index.tsx:310; POST /documents/{document}/links (documents.links.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F017 | Finance/BankAccounts: Bank account | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/BankAccounts.tsx:197; POST /bank-accounts (bank-accounts.store); PATCH /bank-accounts/{bankAccount} (bank-accounts.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F018 | Finance/BankStatements: Statement import | ข้อยกเว้น | backend/resources/js/Pages/Finance/BankStatements.tsx:65; POST /bank-statements/import (bank-statements.import) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F019 | Finance/Cheques: Cheque | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Cheques.tsx:74; POST /cheques (cheques.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F020 | Finance/CommercialDocuments: Delivery order | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/CommercialDocuments.tsx:140; POST /delivery-orders (delivery-orders.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F021 | Finance/Currencies: Currency | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Currencies.tsx:80; POST /currencies (currencies.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F022 | Finance/Currencies: Exchange rate | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Currencies.tsx:115; POST /exchange-rates (exchange-rates.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F023 | Finance/ETax: ETax configuration | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/ETax.tsx:105; PATCH /e-tax/config (e-tax.config.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F024 | Finance/ETax: Generate eTax document | ข้อยกเว้น | backend/resources/js/Pages/Finance/ETax.tsx:185; POST /e-tax/documents (e-tax.documents.generate) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F025 | Finance/Expenses: Expense | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Expenses.tsx:414; POST /expenses (expenses.store); PATCH /expenses/{expense} (expenses.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F026 | Finance/Expenses: Reject expense | ข้อยกเว้น | backend/resources/js/Pages/Finance/Expenses.tsx:621; POST /expenses/{expense}/reject (expenses.reject) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F027 | Finance/FixedAssets: Asset category | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/FixedAssets.tsx:144; POST /fixed-assets/categories (fixed-assets.categories.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F028 | Finance/FixedAssets: Capitalize asset | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/FixedAssets.tsx:215; POST /fixed-assets (fixed-assets.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F029 | Finance/FixedAssets: Asset attachment | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/FixedAssets.tsx:450; POST /fixed-assets/{fixedAsset}/attachment (fixed-assets.attachment.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F030 | Finance/GoodsReceipts: Goods receipt | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/GoodsReceipts.tsx:376; POST /goods-receipts (goods-receipts.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F031 | Finance/GoodsReceipts: Stock adjustment | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/GoodsReceipts.tsx:551; POST /stock-movements (stock-movements.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F032 | Finance/InventoryOperations: Warehouse | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/InventoryOperations.tsx:123; POST /warehouses (warehouses.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F033 | Finance/InventoryOperations: Warehouse bin | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/InventoryOperations.tsx:161; POST /warehouses/{warehouse}/bins (warehouses.bins.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F034 | Finance/InventoryOperations: Inventory lot | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/InventoryOperations.tsx:208; POST /inventory-lots (inventory-lots.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F035 | Finance/InventoryOperations: Stock transfer | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/InventoryOperations.tsx:274; POST /stock-transfers (stock-transfers.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F036 | Finance/InventoryOperations: Stock count | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/InventoryOperations.tsx:372; POST /stock-counts (stock-counts.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F037 | Finance/Invoices: Record invoice payment | ข้อยกเว้น | backend/resources/js/Pages/Finance/Invoices.tsx:517; POST /invoices/{invoice}/payments (invoices.payments.store) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F038 | Finance/Invoices: Invoice | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Invoices.tsx:685; POST /invoices (invoices.store); PATCH /invoices/{invoice} (invoices.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F039 | Finance/Payroll: Payroll profile | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Payroll.tsx:97; POST /payroll/profiles (payroll.profiles.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F040 | Finance/Payroll: Payroll run | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Payroll.tsx:185; POST /payroll/runs (payroll.runs.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F041 | Finance/Payroll: Payroll tax and social security policy | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Payroll.tsx:418; POST /payroll/policies (payroll.policies.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F042 | Finance/PettyCash: Petty cash fund | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/PettyCash.tsx:57; POST /petty-cash/funds (petty-cash.funds.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F043 | Finance/PettyCash: Petty cash request | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/PettyCash.tsx:101; POST /petty-cash/requests (petty-cash.requests.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F044 | Finance/PettyCash: Petty cash reimbursement | ข้อยกเว้น | backend/resources/js/Pages/Finance/PettyCash.tsx:148; POST /petty-cash/reimbursements (petty-cash.reimbursements.store) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F045 | Finance/Products: Product | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Products.tsx:202; POST /products (products.store); PATCH /products/{product} (products.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F046 | Finance/PurchaseOrders: Purchase order | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/PurchaseOrders.tsx:249; POST /purchase-orders (purchase-orders.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F047 | Finance/Quotations: Quotation | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Quotations.tsx:250; POST /quotations (quotations.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F048 | Finance/Suppliers: Supplier | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/Suppliers.tsx:166; POST /suppliers (suppliers.store); PATCH /suppliers/{supplier} (suppliers.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F049 | Hr/Index: Clock in/out | ข้อยกเว้น | backend/resources/js/Pages/Hr/Index.tsx:133; POST /hr/clock-in (hr.clock-in); POST /hr/clock-out (hr.clock-out) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F050 | Hr/Index: Leave request | ต้องเปลี่ยน | backend/resources/js/Pages/Hr/Index.tsx:221; POST /hr/leave-requests (hr.leave-requests.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F051 | Hr/Index: Attendance summary | ต้องเปลี่ยน | backend/resources/js/Pages/Hr/Index.tsx:383; POST /hr/summaries (hr.summaries.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F052 | Hr/Index: Employee shift | ต้องเปลี่ยน | backend/resources/js/Pages/Hr/Index.tsx:499; POST /hr/shifts (hr.shifts.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F053 | Hr/Index: Leave type | ต้องเปลี่ยน | backend/resources/js/Pages/Hr/Index.tsx:552; POST /hr/leave-types (hr.leave-types.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F054 | Hr/Index: Employee work profile | ต้องเปลี่ยน | backend/resources/js/Pages/Hr/Index.tsx:610; POST /hr/work-profiles (hr.work-profiles.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F055 | Hr/Index: Holiday | ต้องเปลี่ยน | backend/resources/js/Pages/Hr/Index.tsx:688; POST /hr/holidays (hr.holidays.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F056 | Portal/Dashboard: Vendor bill intake | ต้องเปลี่ยน | backend/resources/js/Pages/Portal/Dashboard.tsx:217; POST /portal/vendor-bills (portal.vendor-bills.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F057 | Profile/Partials/UpdateProfileInformationForm: Profile information | ต้องเปลี่ยน | backend/resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.tsx:45; PATCH /profile (profile.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F058 | Settings/NotificationPreferences: Notification preferences | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/NotificationPreferences.tsx:134; PATCH /settings/notifications (settings.notifications.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F059 | Settings/NotificationPreferences: External notification preferences | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/NotificationPreferences.tsx:197; PATCH /settings/notifications/external-preferences (settings.notifications.external-preferences.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F060 | Settings/NotificationPreferences: Notification channel | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/NotificationPreferences.tsx:287; POST /settings/notification-channels (settings.notification-channels.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F061 | Settings/Organization: Organization information | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/Organization.tsx:122; PATCH /settings/organization (settings.organization.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F062 | Settings/Organization: Document numbering formats | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/Organization.tsx:245; PATCH /settings/organization/numbering (settings.organization.numbering.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F063 | Settings/Organization: Organization 2FA policy | ข้อยกเว้น | backend/resources/js/Pages/Settings/Organization.tsx:392; PATCH /settings/organization/two-factor (settings.organization.two-factor.update) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F064 | Settings/Organization: Attendance privacy policy | ต้องแก้ integration + เปลี่ยน | backend/resources/js/Pages/Settings/Organization.tsx:515; MISSING MISSING (settings.organization.attendance-privacy.update) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F065 | Settings/OrganizationStructure: Create branch | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/OrganizationStructure.tsx:2087; POST /settings/branches (settings.branches.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F066 | Settings/OrganizationStructure: Create division | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/OrganizationStructure.tsx:2292; POST /settings/divisions (settings.divisions.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F067 | Settings/OrganizationStructure: Create department | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/OrganizationStructure.tsx:2459; POST /settings/departments (settings.departments.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F068 | Settings/OrganizationStructure: Edit branch | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/OrganizationStructure.tsx:2672; PATCH /settings/branches/{branch} (settings.branches.update) | existing modal → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F069 | Settings/OrganizationStructure: Edit division | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/OrganizationStructure.tsx:2760; PATCH /settings/divisions/{division} (settings.divisions.update) | existing modal → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F070 | Settings/OrganizationStructure: Edit department | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/OrganizationStructure.tsx:2825; PATCH /settings/departments/{department} (settings.departments.update) | existing modal → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F071 | Settings/PaymentGateway: Payment gateway configuration | ต้องเปลี่ยน | backend/resources/js/Pages/Settings/PaymentGateway.tsx:48; PUT /settings/payment-gateway (gateway.configure) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F072 | Workflows/Index: Workflow delegation | ต้องเปลี่ยน | backend/resources/js/Pages/Workflows/Index.tsx:225; POST /workflow-delegations (workflows.delegations.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F073 | Workflows/Index: Workflow definition and steps | ต้องเปลี่ยน | backend/resources/js/Pages/Workflows/Index.tsx:290; POST /workflows/definitions (workflows.definitions.store) | embedded page/card/sidebar form → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F074 | Sales/Customers: Customer | เปลี่ยนแล้ว · รอ browser | backend/resources/js/Pages/Sales/Customers.tsx:120; POST /customers (customers.store); PATCH /customers/{customer} (customers.update) | Sales sidebar/inline ก่อน rollout → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F075 | Sales/Customers: Contact | เปลี่ยนแล้ว · รอ browser | backend/resources/js/Pages/Sales/Customers.tsx:123; POST /customers/{customer}/contacts (contacts.store); PATCH /contacts/{contact} (contacts.update) | Sales sidebar/inline ก่อน rollout → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F076 | Sales/Deals: Deal | เปลี่ยนแล้ว · รอ browser | backend/resources/js/Pages/Sales/Deals.tsx:148; POST /deals (deals.store); PATCH /deals/{deal} (deals.update) | Sales sidebar/inline ก่อน rollout → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F077 | Sales/Deals: Activity | เปลี่ยนแล้ว · รอ browser | backend/resources/js/Pages/Sales/Deals.tsx:149; POST /activities (activities.store); PATCH /activities/{activity} (activities.update) | Sales sidebar/inline ก่อน rollout → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F078 | Finance/CommercialDocuments: Voucher attachment | ต้องเปลี่ยน | backend/resources/js/Pages/Finance/CommercialDocuments.tsx:362; POST /vouchers/{voucher}/attachment (vouchers.attachment.store) | inline upload button → shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| F079 | Auth/AcceptInvite: AcceptInvite | ข้อยกเว้น | backend/resources/js/Pages/Auth/AcceptInvite.tsx:30; PATCH /invites/{user}/{token} (invites.accept.update) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F080 | Auth/ConfirmPassword: ConfirmPassword | ข้อยกเว้น | backend/resources/js/Pages/Auth/ConfirmPassword.tsx:31; POST /confirm-password (password.confirm) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F081 | Auth/ForgotPassword: ForgotPassword | ข้อยกเว้น | backend/resources/js/Pages/Auth/ForgotPassword.tsx:35; POST /forgot-password (password.email) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F082 | Auth/Login: Login | ข้อยกเว้น | backend/resources/js/Pages/Auth/Login.tsx:119; POST /login (login) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F083 | Auth/Register: Register | ข้อยกเว้น | backend/resources/js/Pages/Auth/Register.tsx:36; POST /register (register) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F084 | Auth/ResetPassword: ResetPassword | ข้อยกเว้น | backend/resources/js/Pages/Auth/ResetPassword.tsx:35; POST /reset-password (password.store) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F085 | Auth/TwoFactorChallenge: TwoFactorChallenge | ข้อยกเว้น | backend/resources/js/Pages/Auth/TwoFactorChallenge.tsx:14; POST /two-factor-challenge (two-factor.challenge.verify) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F086 | Auth/TwoFactorSetup: TwoFactorSetup | ข้อยกเว้น | backend/resources/js/Pages/Auth/TwoFactorSetup.tsx:34; POST /two-factor/setup (two-factor.setup.confirm) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F087 | Auth/VerifyEmail: VerifyEmail | ข้อยกเว้น | backend/resources/js/Pages/Auth/VerifyEmail.tsx:33; POST /email/verification-notification (verification.send) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F088 | Dashboard: Dashboard | ข้อยกเว้น | backend/resources/js/Pages/Dashboard.tsx:256; GET|HEAD /executive-dashboard (executive.dashboard); GET|HEAD /finance-dashboard (finance.dashboard); GET|HEAD /delivery-dashboard (delivery.dashboard); GET|HEAD /dashboard (dashboard) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F089 | Finance/TaxReports: TaxReports | ข้อยกเว้น | backend/resources/js/Pages/Finance/TaxReports.tsx:123; GET|HEAD /tax-reports (tax-reports.index); GET|HEAD /tax-reports/{type}/export (tax-reports.export); GET|HEAD /tax-reports/{type}/excel (tax-reports.excel) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F090 | Profile/Partials/DeleteUserForm: DeleteUserForm | ข้อยกเว้น | backend/resources/js/Pages/Profile/Partials/DeleteUserForm.tsx:72; DELETE /profile (profile.destroy) | existing modal → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| F091 | Profile/Partials/UpdatePasswordForm: UpdatePasswordForm | ข้อยกเว้น | backend/resources/js/Pages/Profile/Partials/UpdatePasswordForm.tsx:64; PUT /password (password.update) | embedded page/card/sidebar form → คง specialized/security/action flow; ไม่บังคับ CRUD dialog |

## Field / reference / state / permission contracts

### F001 Account

| Contract | รายละเอียด |
| --- | --- |
| Page | Accounting/ChartOfAccounts |
| Fields | code, name, account_type, parent_id, is_postable, description |
| UI fields (static) | code, name, account_type, is_postable |
| References | parent_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | accounting.chart-of-accounts.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:accounting.chart_accounts.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_if($parent->is_postable, 422, 'Parent account must not be postable.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/GeneralLedgerController.php:33 (storeAccount), backend/app/Http/Controllers/Finance/GeneralLedgerController.php:141 (audit) |
### F002 Manual journal

| Contract | รายละเอียด |
| --- | --- |
| Page | Accounting/Journals |
| Fields | posting_date, description, idempotency_key, lines, lines.*.account_code, lines.*.debit, lines.*.credit, lines.*.description |
| UI fields (static) | — |
| References | lines.*.account_code |
| Route model bindings (context) | — |
| Line items / nested payload | lines, lines.*.account_code, lines.*.debit, lines.*.credit, lines.*.description |
| Attachment | — |
| Permission / middleware | accounting.journals.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:accounting.journals.post, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create แล้ว post ผ่าน JournalPostingService; lines ต้อง debit=credit, บัญชี active/postable, period เปิด; posted แก้ผ่าน reverse เท่านั้น ไม่มี Edit route. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/GeneralLedgerController.php:104 (storeJournal), backend/app/Services/JournalPostingService.php (service state guards), backend/app/Services/AccountingPeriodService.php (service state guards) |
### F003 Accounting period

| Contract | รายละเอียด |
| --- | --- |
| Page | Accounting/Periods |
| Fields | name, start_date, end_date |
| UI fields (static) | name, start_date, end_date |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | accounting.periods.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:accounting.periods.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create ห้ามช่วงทับซ้อน; close เฉพาะ open และไม่มี draft journal; ไม่มี Edit route. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/GeneralLedgerController.php:72 (storePeriod), backend/app/Http/Controllers/Finance/GeneralLedgerController.php:141 (audit) |
### F004 Role permissions

| Contract | รายละเอียด |
| --- | --- |
| Page | Admin/Roles |
| Fields | permission_ids, permission_ids.* |
| UI fields (static) | permission_ids |
| References | permission_ids |
| Route model bindings (context) | role |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | roles.permissions.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:roles.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | owner role immutable; ไม่สร้าง editor ให้ owner. บันทึก permission_ids ของ role เดิม. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Admin/RoleController.php:66 (updatePermissions), backend/app/Http/Controllers/Admin/RoleController.php:115 (audit) |
### F005 Invite user

| Contract | รายละเอียด |
| --- | --- |
| Page | Admin/Users |
| Fields | name, email, position, person_id, branch_id, division_id, department_id, role_id |
| UI fields (static) | name, email, position, person_id, role_id |
| References | branch_id, division_id, department_id, role_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | users.invite: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:users.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | สร้าง invitation ผ่าน users.invite; token acceptance เป็น security exception แยก. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Admin/UserController.php:51 (invite), backend/app/Http/Controllers/Admin/UserController.php:232 (validateHierarchy), backend/app/Http/Controllers/Admin/UserController.php:269 (audit) |
### F006 Edit user

| Contract | รายละเอียด |
| --- | --- |
| Page | Admin/Users |
| Fields | name, email, position, phone, branch_id, division_id, department_id, role_id, person_id |
| UI fields (static) | name, email, position, phone, person_id, role_id |
| References | branch_id, division_id, department_id, role_id |
| Route model bindings (context) | user |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | users.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:users.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | org scope, branch/division/department chain, ห้ามถอด owner คนสุดท้าย; role/person_id อยู่ใน payload เดิม. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Admin/UserController.php:115 (update), backend/app/Http/Controllers/Admin/UserController.php:232 (validateHierarchy), backend/app/Http/Controllers/Admin/UserController.php:253 (wouldRemoveLastOwner), backend/app/Http/Controllers/Admin/UserController.php:269 (audit) |
### F007 Project

| Contract | รายละเอียด |
| --- | --- |
| Page | Delivery/Projects |
| Fields | name, customer_id, deal_id, owner_id, status, start_date, due_date, progress_percent, budget_amount, currency, note |
| UI fields (static) | name, customer_id, deal_id, owner_id, status, start_date, due_date, progress_percent, budget_amount, currency, note |
| References | customer_id, deal_id, owner_id |
| Route model bindings (context) | project |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | projects.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:projects.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; projects.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:projects.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create/update ตาม route ที่มีจริง; abort_unless($deal->stage === 'won', 422, 'Only won deals can be linked to projects.'); / abort_unless($deal->customer_id === $validated['customer_id'], 422, 'Deal must belong to selected customer.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Delivery/ProjectController.php:81 (store), backend/app/Http/Controllers/Delivery/ProjectController.php:182 (validateProject), backend/app/Http/Controllers/Delivery/ProjectController.php:201 (assertDealMatchesCustomer), backend/app/Http/Controllers/Delivery/ProjectController.php:221 (audit), backend/app/Http/Controllers/Delivery/ProjectController.php:216 (snapshot), backend/app/Http/Controllers/Delivery/ProjectController.php:97 (update) |
### F008 Project member

| Contract | รายละเอียด |
| --- | --- |
| Page | Delivery/Projects |
| Fields | user_id, role |
| UI fields (static) | user_id, role |
| References | user_id |
| Route model bindings (context) | project |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | projects.members.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:projects.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless(ProjectAccess::canSeeAll($user) \|\| $project->owner_id === $user->id, 403); / abort_if($validated['user_id'] === $project->owner_id, 422, 'Project owner is already included.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Delivery/ProjectController.php:142 (storeMember) |
### F009 Task

| Contract | รายละเอียด |
| --- | --- |
| Page | Delivery/Tasks |
| Fields | project_id, title, description, status, priority, assignee_id, due_date |
| UI fields (static) | project_id, title, description, status, priority, assignee_id, due_date |
| References | project_id, assignee_id |
| Route model bindings (context) | task |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | tasks.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:tasks.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; tasks.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:tasks.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create/update ตาม route ที่มีจริง; abort_unless(TaskAccess::canManageProjectTasks($user, $project), 403); / $validated['completed_at'] = $validated['status'] === 'done' ? now() : null; / $validated['completed_at'] = $validated['status'] === 'done' ? ($task->completed_at ?? now()) : null; |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Delivery/TaskController.php:62 (store), backend/app/Http/Controllers/Delivery/TaskController.php:158 (validateTask), backend/app/Http/Controllers/Delivery/TaskController.php:171 (projectFor), backend/app/Http/Controllers/Delivery/TaskController.php:206 (audit), backend/app/Http/Controllers/Delivery/TaskController.php:180 (snapshot), backend/app/Http/Controllers/Delivery/TaskController.php:185 (notifyAssignee), backend/app/Http/Controllers/Delivery/TaskController.php:81 (update) |
### F010 Task checklist item

| Contract | รายละเอียด |
| --- | --- |
| Page | Delivery/Tasks |
| Fields | title |
| UI fields (static) | title |
| References | — |
| Route model bindings (context) | task |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | tasks.checklists.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:tasks.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Delivery/TaskController.php:106 (storeChecklist), backend/app/Http/Controllers/Delivery/TaskController.php:206 (audit) |
### F011 Task comment

| Contract | รายละเอียด |
| --- | --- |
| Page | Delivery/Tasks |
| Fields | body |
| UI fields (static) | body |
| References | — |
| Route model bindings (context) | task |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | tasks.comments.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:tasks.comment, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Delivery/TaskController.php:140 (storeComment), backend/app/Http/Controllers/Delivery/TaskController.php:206 (audit) |
### F012 Document and initial version

| Contract | รายละเอียด |
| --- | --- |
| Page | Documents/Index |
| Fields | title, category_id, sensitivity, expires_at, renewal_alert_days, file, change_note |
| UI fields (static) | title, category_id, sensitivity, expires_at, renewal_alert_days, file, change_note |
| References | category_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | file |
| Permission / middleware | documents.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:documents.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/DocumentController.php:30 (store), backend/app/Http/Controllers/DocumentController.php:116 (audit), backend/app/Services/DocumentService.php (service state guards) |
### F013 Retention policy

| Contract | รายละเอียด |
| --- | --- |
| Page | Documents/Index |
| Fields | code, name, minimum_retention_days, effective_from, legal_hold_required |
| UI fields (static) | code, name, minimum_retention_days, effective_from, legal_hold_required |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | documents.retention-policies.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:documents.retention.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/DocumentController.php:68 (storeRetentionPolicy) |
### F014 Document category

| Contract | รายละเอียด |
| --- | --- |
| Page | Documents/Index |
| Fields | code, name, retention_policy_id, default_sensitivity, expiry_tracking_enabled, default_renewal_alert_days |
| UI fields (static) | code, name, retention_policy_id, default_sensitivity, expiry_tracking_enabled, default_renewal_alert_days |
| References | retention_policy_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | documents.categories.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:documents.retention.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/DocumentController.php:76 (storeCategory) |
### F015 Document version

| Contract | รายละเอียด |
| --- | --- |
| Page | Documents/Index |
| Fields | file, change_note |
| UI fields (static) | file, change_note |
| References | — |
| Route model bindings (context) | document |
| Line items / nested payload | — |
| Attachment | file |
| Permission / middleware | documents.versions.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:documents.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | DocumentService::addVersion: document ต้อง org เดียวกันและ active; ห้าม overwrite version เดิม; upload ใหม่. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/DocumentController.php:49 (addVersion), backend/app/Http/Controllers/DocumentController.php:116 (audit), backend/app/Services/DocumentService.php (service state guards) |
### F016 Document link

| Contract | รายละเอียด |
| --- | --- |
| Page | Documents/Index |
| Fields | linkable_type, linkable_id, role |
| UI fields (static) | linkable_type, linkable_id, role |
| References | linkable_type, linkable_id |
| Route model bindings (context) | document |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | documents.links.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:documents.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | DocumentService ตรวจ supported type + parentAccess + org; ต้องคง role และ parent id. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/DocumentController.php:59 (storeLink), backend/app/Http/Controllers/DocumentController.php:116 (audit), backend/app/Services/DocumentService.php (service state guards) |
### F017 Bank account

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/BankAccounts |
| Fields | branch_id, chart_of_account_id, bank_name, bank_code, branch_name, account_name, account_type, currency, is_cash_account, status, opening_balance, opening_balance_date, account_number |
| UI fields (static) | branch_id, chart_of_account_id, bank_name, bank_code, branch_name, account_name, account_number, account_type, currency, opening_balance, opening_balance_date, status, is_cash_account |
| References | branch_id, chart_of_account_id, bank_code |
| Route model bindings (context) | bankAccount |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | bank-accounts.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:treasury.accounts.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; bank-accounts.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:treasury.accounts.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create/update ตาม route ที่มีจริง; abort_if($duplicate, 422, 'Bank account number already exists in this organization.'); / abort_unless($bankAccount->org_id === $request->user()->org_id, 404); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/BankAccountController.php:36 (store), backend/app/Http/Controllers/Finance/BankAccountController.php:66 (validateAccount), backend/app/Http/Controllers/Finance/BankAccountController.php:97 (accountNumberHash), backend/app/Http/Controllers/Finance/BankAccountController.php:143 (audit), backend/app/Http/Controllers/Finance/BankAccountController.php:124 (auditPayload), backend/app/Http/Controllers/Finance/BankAccountController.php:49 (update) |
### F018 Statement import

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/BankStatements |
| Fields | bank_account_id, statement |
| UI fields (static) | bank_account_id, statement |
| References | bank_account_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | statement |
| Permission / middleware | bank-statements.import: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:treasury.reconciliation.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:5,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_if(count($rows) > 5000, 422, 'Statement import is limited to 5,000 rows.'); / abort_if($newRows->isEmpty(), 422, 'All statement rows were already imported.'); / abort_unless($handle, 422, 'Cannot read statement file.'); / abort_unless($headers === self::HEADERS, 422, 'CSV headers must be: '.implode(',', self::HEADERS)); / abort_unless(count($data) === count(self::HEADERS), 422, 'Invalid CSV row.'); / abort_unless(strtotime($date) !== false && is_numeric($amount) && ($balance === '' \|\| is_numeric($balance)), 422, 'Invalid statement row value.'); / abort_if(empty($rows), 422, 'Statement has no data rows.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | file import workflow |
| Evidence | backend/app/Http/Controllers/Finance/BankStatementController.php:40 (import), backend/app/Http/Controllers/Finance/BankStatementController.php:100 (csvRows) |
### F019 Cheque

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Cheques |
| Fields | bank_account_id, direction, cheque_no, bank_name, drawer_or_payee, amount, issue_date, due_date |
| UI fields (static) | bank_account_id, direction, cheque_no, bank_name, drawer_or_payee, amount, issue_date, due_date |
| References | bank_account_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | cheques.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:cheques.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/TreasuryOperationsController.php:146 (storeCheque), backend/app/Http/Controllers/Finance/TreasuryOperationsController.php:204 (audit) |
### F020 Delivery order

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/CommercialDocuments |
| Fields | invoice_id, delivery_date, status, receiver_name, warehouse_id, note |
| UI fields (static) | invoice_id, warehouse_id, status, delivery_date, receiver_name, note |
| References | invoice_id, warehouse_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | delivery-orders.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:delivery_orders.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_if($invoice->status === 'void', 422, 'Cannot create delivery order from void invoice.'); / abort_if(DeliveryOrder::where('org_id', $orgId)->where('invoice_id', $invoice->id)->where('status', 'delivered')->lockForUpdate()->exists(), 422, 'Invoice has already been delivered.'); / 'delivered_at' => $validated['status'] === 'delivered' ? now() : null, / if ($validated['status'] === 'delivered' && $item->product_id) { / abort_if((float) $item->quantity > $onHand, 422, 'Delivery order cannot make stock negative.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/CommercialDocumentController.php:164 (storeDeliveryOrder), backend/app/Http/Controllers/Finance/CommercialDocumentController.php:340 (notifyLowStock), backend/app/Http/Controllers/Finance/CommercialDocumentController.php:355 (audit) |
### F021 Currency

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Currencies |
| Fields | code, name, decimal_places, status |
| UI fields (static) | code, name |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | currencies.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:fx.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/CurrencyController.php:36 (storeCurrency) |
### F022 Exchange rate

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Currencies |
| Fields | quote_currency, rate_date, rate, source |
| UI fields (static) | quote_currency, rate_date, rate |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | exchange-rates.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:fx.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_if($base === strtoupper($data['quote_currency']), 422, 'Quote currency must differ from base currency.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/CurrencyController.php:44 (storeRate) |
### F023 ETax configuration

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/ETax |
| Fields | mode, provider_code, certificate_reference, certificate_expires_at, signature_mode |
| UI fields (static) | mode, provider_code, certificate_reference, certificate_expires_at |
| References | provider_code |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | e-tax.config.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:e_tax.manage, Illuminate\Auth\Middleware\RequirePassword |
| Business state | Create/update ตาม route ที่มีจริง; throw ValidationException::withMessages(['mode' => 'Live provider delivery is not implemented. Use disabled, manual export or mock mode.']); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/ETaxController.php:45 (updateConfig) |
### F024 Generate eTax document

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/ETax |
| Fields | source_type, source_id, document_type |
| UI fields (static) | source_type, source_id, document_type |
| References | source_type, source_id, document_type |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | e-tax.documents.generate: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:e_tax.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; } catch (ImmutableETaxDocumentException $exception) { |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | posting/payment/generation/decision command; ไม่ใช่แก้ entity ทั่วไป |
| Evidence | backend/app/Http/Controllers/Finance/ETaxController.php:61 (generate) |
### F025 Expense

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Expenses |
| Fields | category, title, amount, currency, tax_mode, tax_invoice_no, withholding_tax_rate, withholding_tax_form, expense_date, project_id, supplier_id, purchase_order_id, receipt, note |
| UI fields (static) | category, title, amount, currency, tax_mode, tax_invoice_no, withholding_tax_rate, withholding_tax_form, expense_date, supplier_id, note, receipt |
| References | project_id, supplier_id, purchase_order_id |
| Route model bindings (context) | expense |
| Line items / nested payload | — |
| Attachment | receipt |
| Permission / middleware | expenses.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:expenses.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; expenses.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:expenses.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Edit เฉพาะ draft; approve/pay/reject เป็นคนละ action; เก็บ receipt และ supplier/project/PO เดิม. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/ExpenseController.php:64 (store), backend/app/Http/Controllers/Finance/ExpenseController.php:239 (validateExpense), backend/app/Http/Controllers/Finance/ExpenseController.php:322 (withholdingPayload), backend/app/Http/Controllers/Finance/ExpenseController.php:292 (taxPayload), backend/app/Http/Controllers/Finance/ExpenseController.php:339 (audit), backend/app/Http/Controllers/Finance/ExpenseController.php:287 (snapshot), backend/app/Http/Controllers/Finance/ExpenseController.php:96 (update) |
### F026 Reject expense

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Expenses |
| Fields | note |
| UI fields (static) | note |
| References | — |
| Route model bindings (context) | expense |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | expenses.reject: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:expenses.reject, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless($expense->org_id === $request->user()->org_id, 403); / abort_unless(in_array($expense->status, ['draft', 'approved'], true), 422, 'Only draft or approved expenses can be rejected.'); / if ($expense->status === 'approved') { |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | posting/payment/generation/decision command; ไม่ใช่แก้ entity ทั่วไป |
| Evidence | backend/app/Http/Controllers/Finance/ExpenseController.php:181 (reject), backend/app/Http/Controllers/Finance/ExpenseController.php:287 (snapshot), backend/app/Http/Controllers/Finance/ExpenseController.php:276 (appendStatusNote), backend/app/Http/Controllers/Finance/ExpenseController.php:339 (audit) |
### F027 Asset category

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/FixedAssets |
| Fields | code, name, asset_account_id, accumulated_depreciation_account_id, depreciation_expense_account_id, default_useful_life_months, description |
| UI fields (static) | code, name, asset_account_id, accumulated_depreciation_account_id, depreciation_expense_account_id, default_useful_life_months |
| References | asset_account_id, accumulated_depreciation_account_id, depreciation_expense_account_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | fixed-assets.categories.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:fixed_assets.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_if($data['asset_account_id'] === $data['accumulated_depreciation_account_id'], 422, 'Asset and accumulated depreciation accounts must be different.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/FixedAssetController.php:49 (storeCategory) |
### F028 Capitalize asset

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/FixedAssets |
| Fields | asset_category_id, source_type, source_id, name, description, available_for_use_date, salvage_value, useful_life_months, location, custodian_user_id |
| UI fields (static) | asset_category_id, name, available_for_use_date, salvage_value, useful_life_months, location, custodian_user_id |
| References | asset_category_id, source_type, source_id, custodian_user_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | fixed-assets.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:fixed_assets.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | FixedAssetService: source ต้องยังไม่ capitalize; expense ต้อง approved/paid หรือ goods receipt มีต้นทุน; salvage < cost; ไม่มี Edit asset route. Dispose/write-off เฉพาะ active เป็น command แยก. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/FixedAssetController.php:68 (storeAsset), backend/app/Services/FixedAssetService.php (service state guards), backend/app/Services/JournalPostingService.php (service state guards) |
### F029 Asset attachment

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/FixedAssets |
| Fields | attachment |
| UI fields (static) | attachment |
| References | — |
| Route model bindings (context) | fixedAsset |
| Line items / nested payload | — |
| Attachment | attachment |
| Permission / middleware | fixed-assets.attachment.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:fixed_assets.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless($fixedAsset->org_id === $request->user()->org_id, 404); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/FixedAssetController.php:123 (storeAttachment) |
### F030 Goods receipt

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/GoodsReceipts |
| Fields | purchase_order_id, received_date, warehouse_id, warehouse_bin_id, inventory_lot_id, note, items, items.*.purchase_order_item_id, items.*.quantity, items.*.inventory_lot_id |
| UI fields (static) | purchase_order_id, received_date, warehouse_id, items, note |
| References | purchase_order_id, warehouse_id, warehouse_bin_id, inventory_lot_id, items.*.purchase_order_item_id, items.*.inventory_lot_id |
| Route model bindings (context) | — |
| Line items / nested payload | items, items.*.purchase_order_item_id, items.*.quantity, items.*.inventory_lot_id |
| Attachment | — |
| Permission / middleware | goods-receipts.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:inventory.receive, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | อ้าง PO/PO item; รับไม่เกิน remaining quantity; lot ต้องตรง product และ bin ต้องตรง warehouse; บันทึก stock/GL ไม่สร้าง Edit GRN. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/GoodsReceiptController.php:91 (store), backend/app/Http/Controllers/Finance/GoodsReceiptController.php:274 (validateStorageLocation), backend/app/Http/Controllers/Finance/GoodsReceiptController.php:264 (receivedQuantity), backend/app/Http/Controllers/Finance/GoodsReceiptController.php:257 (poReceiptStatus) |
### F031 Stock adjustment

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/GoodsReceipts |
| Fields | product_id, movement_type, movement_date, quantity, unit_cost, warehouse_id, warehouse_bin_id, inventory_lot_id, note |
| UI fields (static) | product_id, warehouse_id, movement_type, movement_date, quantity, unit_cost, note |
| References | product_id, movement_type, warehouse_id, warehouse_bin_id, inventory_lot_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | stock-movements.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:inventory.adjust, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_if($isOutbound && $quantity > $onHand, 422, 'Stock movement cannot make on-hand quantity negative.'); / abort_unless(! empty($validated['warehouse_id']), 422, 'Warehouse is required when bin is selected.'); / abort_unless(WarehouseBin::where('org_id', $orgId)->where('warehouse_id', $validated['warehouse_id'])->whereKey($validated['warehouse_bin_id'])->exists(), 422, 'Bin does not belong to warehouse.'); / abort_unless(InventoryLot::where('org_id', $orgId)->where('product_id', $validated['product_id'])->whereKey($validated['inventory_lot_id'])->exists(), 422, 'Lot does not belong to product.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/GoodsReceiptController.php:203 (storeMovement), backend/app/Http/Controllers/Finance/GoodsReceiptController.php:274 (validateStorageLocation), backend/app/Http/Controllers/Finance/GoodsReceiptController.php:269 (onHand), backend/app/Http/Controllers/Finance/GoodsReceiptController.php:301 (averageCost), backend/app/Http/Controllers/Finance/GoodsReceiptController.php:285 (notifyLowStock) |
### F032 Warehouse

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/InventoryOperations |
| Fields | code, name |
| UI fields (static) | code, name |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | warehouses.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:inventory.adjust, Illuminate\Auth\Middleware\RequirePassword |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/InventoryOperationsController.php:37 (storeWarehouse) |
### F033 Warehouse bin

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/InventoryOperations |
| Fields | code, name, warehouse_id |
| UI fields (static) | warehouse_id, code, name |
| References | warehouse_id |
| Route model bindings (context) | warehouse |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | warehouses.bins.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:inventory.adjust, Illuminate\Auth\Middleware\RequirePassword |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless($warehouse->org_id === $request->user()->org_id, 404); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/InventoryOperationsController.php:45 (storeBin) |
### F034 Inventory lot

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/InventoryOperations |
| Fields | product_id, lot_no, manufactured_at, expires_at, barcode |
| UI fields (static) | product_id, lot_no, manufactured_at, expires_at, barcode |
| References | product_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | inventory-lots.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:inventory.receive, Illuminate\Auth\Middleware\RequirePassword |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/InventoryOperationsController.php:54 (storeLot) |
### F035 Stock transfer

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/InventoryOperations |
| Fields | product_id, source_warehouse_id, destination_warehouse_id, quantity, transfer_date, inventory_lot_id, note |
| UI fields (static) | product_id, inventory_lot_id, source_warehouse_id, destination_warehouse_id, quantity, transfer_date |
| References | product_id, source_warehouse_id, destination_warehouse_id, inventory_lot_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | stock-transfers.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:inventory.adjust, Illuminate\Auth\Middleware\RequirePassword |
| Business state | source/destination และ lot ต้องตรง product/org; stock ต้นทางต้องพอ; บันทึก movements คู่กัน ไม่มี Edit route. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/InventoryOperationsController.php:67 (transfer) |
### F036 Stock count

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/InventoryOperations |
| Fields | warehouse_id, count_date, items, items.*.product_id, items.*.counted_quantity, product_id, counted_quantity |
| UI fields (static) | warehouse_id, product_id, counted_quantity, count_date |
| References | warehouse_id, items.*.product_id, product_id |
| Route model bindings (context) | — |
| Line items / nested payload | items, items.*.product_id, items.*.counted_quantity |
| Attachment | — |
| Permission / middleware | stock-counts.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:inventory.adjust, Illuminate\Auth\Middleware\RequirePassword |
| Business state | ส่ง items[] จากฟอร์ม product/count; backend บันทึก stock_count เป็น posted พร้อม adjustment; ไม่มี Edit posted count route. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/InventoryOperationsController.php:104 (stockCount) |
### F037 Record invoice payment

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Invoices |
| Fields | amount, payment_date, payment_method, bank_account_id, reference_no, note, idempotency_key, attachment |
| UI fields (static) | amount, payment_date, payment_method, bank_account_id, reference_no, note, attachment |
| References | bank_account_id |
| Route model bindings (context) | invoice |
| Line items / nested payload | — |
| Attachment | attachment |
| Permission / middleware | invoices.payments.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:payments.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless($invoice->org_id === $request->user()->org_id, 403); / $lockedInvoice = $this->lockInvoice($request, $invoice->id); / $this->assertReceiptIdempotencyMatch($existing, $lockedInvoice, $validated); / abort_if($lockedInvoice->status === 'void', 422, 'Cannot record payment on a void invoice.'); / $balanceDue = round((float) $lockedInvoice->balance_due, 2); / abort_if($amount > $balanceDue, 422, 'Payment exceeds balance due.'); / abort_if($bankAccount && strtoupper($bankAccount->currency) !== strtoupper($lockedInvoice->currency), 422, 'Bank account currency must match invoice currency.'); / $baseSnapshot = $fxRates->snapshot($lockedInvoice->org_id, $lockedInvoice->currency, $validated['payment_date'], ['amount' => $amount]); / $invoiceBaseBalance = (float) $lockedInvoice->base_balance_due ?: (float) $lockedInvoice->balance_due; / 'org_id' => $lockedInvoice->org_id, / 'invoice_id' => $lockedInvoice->id, / 'currency' => $lockedInvoice->currency, / $this->recalculateInvoicePaymentState($lockedInvoice, $request->user()->id); / $this->audit($request, 'payment.receipt', $payment, null, $this->paymentSnapshot($payment, $lockedInvoice)); / abort_if(is_string($headerIdempotencyKey) && strlen($headerIdempotencyKey) > 100, 422, 'Idempotency-Key must not be greater than 100 characters.'); / throw $exception; / throw ValidationException::withMessages([ |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | posting/payment/generation/decision command; ไม่ใช่แก้ entity ทั่วไป |
| Evidence | backend/app/Http/Controllers/Finance/PaymentController.php:24 (store), backend/app/Http/Controllers/Finance/PaymentController.php:210 (idempotencyKey), backend/app/Http/Controllers/Finance/PaymentController.php:220 (existingIdempotentPayment), backend/app/Http/Controllers/Finance/PaymentController.php:183 (assertReceiptIdempotencyMatch), backend/app/Http/Controllers/Finance/PaymentController.php:227 (lockInvoice), backend/app/Http/Controllers/Finance/PaymentController.php:235 (recalculateInvoicePaymentState), backend/app/Http/Controllers/Finance/PaymentController.php:306 (audit), backend/app/Http/Controllers/Finance/PaymentController.php:279 (paymentSnapshot), backend/app/Http/Controllers/Finance/PaymentController.php:262 (throwValidationIfUniqueViolation), backend/app/Http/Controllers/Finance/PaymentController.php:273 (isUniqueConstraintViolation) |
### F038 Invoice

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Invoices |
| Fields | customer_id, deal_id, project_id, status, tax_mode, issue_date, due_date, discount_amount, currency, notes, items, items.*.product_id, items.*.description, items.*.quantity, items.*.unit, items.*.unit_price, items.*.discount_amount, items.*.tax_rate |
| UI fields (static) | customer_id, deal_id, project_id, status, tax_mode, issue_date, due_date, discount_amount, items |
| References | customer_id, deal_id, project_id, items.*.product_id |
| Route model bindings (context) | invoice |
| Line items / nested payload | items, items.*.product_id, items.*.description, items.*.quantity, items.*.unit, items.*.unit_price, items.*.discount_amount, items.*.tax_rate |
| Attachment | — |
| Permission / middleware | invoices.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:invoices.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; invoices.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:invoices.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Edit: backend อนุญาต draft เท่านั้น; UI ปัจจุบันแสดง Edit เมื่อไม่ void และ paid_amount <= 0 จึงกว้างกว่า backend (G02). Create เลือก draft/sent; sent มี GL posting. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/InvoiceController.php:80 (store), backend/app/Http/Controllers/Finance/InvoiceController.php:258 (validateInvoice), backend/app/Http/Controllers/Finance/InvoiceController.php:284 (assertDealMatchesCustomer), backend/app/Http/Controllers/Finance/InvoiceController.php:297 (assertProjectMatchesCustomer), backend/app/Http/Controllers/Finance/InvoiceController.php:332 (calculateTotals), backend/app/Http/Controllers/Finance/InvoiceController.php:370 (taxSummaryFromLines), backend/app/Http/Controllers/Finance/InvoiceController.php:444 (moneyValue), backend/app/Http/Controllers/Finance/InvoiceController.php:310 (invoicePayload), backend/app/Http/Controllers/Finance/InvoiceController.php:463 (syncItems), backend/app/Http/Controllers/Finance/InvoiceController.php:486 (audit), backend/app/Http/Controllers/Finance/InvoiceController.php:481 (snapshot), backend/app/Http/Controllers/Finance/InvoiceController.php:113 (update) |
### F039 Payroll profile

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Payroll |
| Fields | user_id, monthly_salary, fixed_allowance, fixed_deduction, annual_tax_allowance, tax_id, social_security_enabled, payment_method, payment_reference, status |
| UI fields (static) | user_id, monthly_salary, fixed_allowance, fixed_deduction, annual_tax_allowance, payment_method, payment_reference, social_security_enabled, status |
| References | user_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | payroll.profiles.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:payroll.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/PayrollController.php:46 (storeProfile), backend/app/Http/Controllers/Finance/PayrollController.php:149 (audit) |
### F040 Payroll run

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Payroll |
| Fields | period_start, period_end, payment_date, bank_account_id |
| UI fields (static) | period_start, period_end, payment_date, bank_account_id |
| References | bank_account_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | payroll.runs.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:payroll.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | UI สร้าง run; calculate ได้ draft/calculated, approve ได้ calculated, pay ได้ approved; ห้ามเปิด Edit run approved/paid. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/PayrollController.php:60 (storeRun), backend/app/Http/Controllers/Finance/PayrollController.php:149 (audit), backend/app/Services/PayrollService.php (service state guards) |
### F041 Payroll tax and social security policy

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Payroll |
| Fields | effective_from, employment_expense_rate, employment_expense_cap, brackets_json, employee_rate, employer_rate, wage_ceiling, source_url |
| UI fields (static) | effective_from, employment_expense_rate, employment_expense_cap, wage_ceiling, employee_rate, employer_rate, source_url, brackets_json |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | brackets_json |
| Attachment | — |
| Permission / middleware | payroll.policies.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:payroll.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:5,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless(is_array($brackets) && count($brackets) > 0, 422, 'Tax brackets must be a non-empty JSON array.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/PayrollController.php:98 (storePolicies) |
### F042 Petty cash fund

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/PettyCash |
| Fields | custodian_user_id, bank_account_id, imprest_amount |
| UI fields (static) | custodian_user_id, bank_account_id, imprest_amount |
| References | custodian_user_id, bank_account_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | petty-cash.funds.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:petty_cash.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/TreasuryOperationsController.php:39 (storeFund), backend/app/Http/Controllers/Finance/TreasuryOperationsController.php:204 (audit) |
### F043 Petty cash request

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/PettyCash |
| Fields | petty_cash_fund_id, amount, expense_date, purpose |
| UI fields (static) | petty_cash_fund_id, amount, expense_date, purpose |
| References | petty_cash_fund_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | petty-cash.requests.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:petty_cash.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/TreasuryOperationsController.php:58 (storeRequest), backend/app/Http/Controllers/Finance/TreasuryOperationsController.php:204 (audit) |
### F044 Petty cash reimbursement

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/PettyCash |
| Fields | petty_cash_fund_id, bank_account_id, amount, reimbursed_at, note |
| UI fields (static) | petty_cash_fund_id, bank_account_id, amount, reimbursed_at, note |
| References | petty_cash_fund_id, bank_account_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | petty-cash.reimbursements.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:petty_cash.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | posting/payment/generation/decision command; ไม่ใช่แก้ entity ทั่วไป |
| Evidence | backend/app/Http/Controllers/Finance/TreasuryOperationsController.php:114 (reimburse), backend/app/Http/Controllers/Finance/TreasuryOperationsController.php:204 (audit) |
### F045 Product

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Products |
| Fields | sku, barcode, reorder_point, name, type, category, unit, price, cost, is_active, description |
| UI fields (static) | sku, barcode, reorder_point, name, type, category, unit, price, cost, is_active |
| References | — |
| Route model bindings (context) | product |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | products.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:products.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; products.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:products.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create/update ตาม route ที่มีจริง; abort_unless($product->org_id === $request->user()->org_id, 403); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/ProductController.php:40 (store), backend/app/Http/Controllers/Finance/ProductController.php:84 (validateProduct), backend/app/Http/Controllers/Finance/ProductController.php:113 (audit), backend/app/Http/Controllers/Finance/ProductController.php:108 (trackedFields), backend/app/Http/Controllers/Finance/ProductController.php:57 (update) |
### F046 Purchase order

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/PurchaseOrders |
| Fields | supplier_id, project_id, status, order_date, expected_date, tax_mode, discount_amount, currency, note, items, items.*.product_id, items.*.description, items.*.quantity, items.*.unit, items.*.unit_price, items.*.discount_amount, items.*.tax_rate |
| UI fields (static) | supplier_id, order_date, tax_mode, items |
| References | supplier_id, project_id, items.*.product_id |
| Route model bindings (context) | — |
| Line items / nested payload | items, items.*.product_id, items.*.description, items.*.quantity, items.*.unit, items.*.unit_price, items.*.discount_amount, items.*.tax_rate |
| Attachment | — |
| Permission / middleware | purchase-orders.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:purchase_orders.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | UI มี Create เท่านั้น; backend update อนุญาต draft เท่านั้น; approve draft/sent, cancel ห้าม received/closed/cancelled. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/PurchaseOrderController.php:41 (store), backend/app/Http/Controllers/Finance/PurchaseOrderController.php:170 (validatePurchaseOrder), backend/app/Http/Controllers/Finance/PurchaseOrderController.php:195 (calculateTotals), backend/app/Http/Controllers/Finance/PurchaseOrderController.php:211 (syncItems), backend/app/Http/Controllers/Finance/PurchaseOrderController.php:229 (notifyApprovers) |
### F047 Quotation

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Quotations |
| Fields | customer_id, deal_id, status, tax_mode, issue_date, valid_until, discount_amount, currency, notes, items, items.*.product_id, items.*.description, items.*.quantity, items.*.unit, items.*.unit_price, items.*.discount_amount, items.*.tax_rate |
| UI fields (static) | customer_id, deal_id, issue_date, valid_until, tax_mode, discount_amount, items |
| References | customer_id, deal_id, items.*.product_id |
| Route model bindings (context) | — |
| Line items / nested payload | items, items.*.product_id, items.*.description, items.*.quantity, items.*.unit, items.*.unit_price, items.*.discount_amount, items.*.tax_rate |
| Attachment | — |
| Permission / middleware | quotations.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:quotations.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | UI มี Create เท่านั้น; backend update อนุญาต draft/sent. ห้ามเพิ่ม Edit ให้ approved/converted/rejected. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/QuotationController.php:49 (store), backend/app/Http/Controllers/Finance/QuotationController.php:176 (validateQuotation), backend/app/Http/Controllers/Finance/QuotationController.php:201 (assertDealMatchesCustomer), backend/app/Http/Controllers/Finance/QuotationController.php:229 (calculateTotals), backend/app/Http/Controllers/Finance/QuotationController.php:210 (quotationPayload), backend/app/Http/Controllers/Finance/QuotationController.php:245 (syncItems), backend/app/Http/Controllers/Finance/QuotationController.php:268 (audit), backend/app/Http/Controllers/Finance/QuotationController.php:263 (snapshot) |
### F048 Supplier

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/Suppliers |
| Fields | name, supplier_code, tax_id, email, phone, address, status |
| UI fields (static) | name, tax_id, email, phone, status, address |
| References | supplier_code |
| Route model bindings (context) | supplier |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | suppliers.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:suppliers.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; suppliers.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:suppliers.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create/update ตาม route ที่มีจริง; abort_unless($supplier->org_id === $request->user()->org_id, 404); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/SupplierController.php:35 (store), backend/app/Http/Controllers/Finance/SupplierController.php:67 (validateSupplier), backend/app/Http/Controllers/Finance/SupplierController.php:48 (update) |
### F049 Clock in/out

| Contract | รายละเอียด |
| --- | --- |
| Page | Hr/Index |
| Fields | source, privacy_consent, latitude, longitude, gps_accuracy_meters |
| UI fields (static) | source, privacy_consent, latitude, longitude |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | hr.clock-in: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:hr.self.view, Illuminate\Routing\Middleware\ThrottleRequests:10,1; hr.clock-out: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:hr.self.view, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | attendance event command |
| Evidence | backend/app/Http/Controllers/HrController.php:48 (clockIn), backend/app/Http/Controllers/HrController.php:187 (attendanceRules), backend/app/Http/Controllers/HrController.php:203 (audit), backend/app/Http/Controllers/HrController.php:57 (clockOut) |
### F050 Leave request

| Contract | รายละเอียด |
| --- | --- |
| Page | Hr/Index |
| Fields | leave_type_id, starts_on, ends_on, reason |
| UI fields (static) | leave_type_id, starts_on, ends_on, reason |
| References | leave_type_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | hr.leave-requests.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:hr.self.view, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | สร้าง draft; submit draft เท่านั้นพร้อม overlap/balance checks; cancel ได้ submitted; ไม่มี Edit route. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/HrController.php:116 (storeLeaveRequest), backend/app/Http/Controllers/HrController.php:203 (audit), backend/app/Services/HrAttendanceService.php (service state guards) |
### F051 Attendance summary

| Contract | รายละเอียด |
| --- | --- |
| Page | Hr/Index |
| Fields | user_id, period_start, period_end, cutoff_date |
| UI fields (static) | user_id, period_start, period_end, cutoff_date |
| References | user_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | hr.summaries.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:hr.summary.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | สร้าง summary พร้อม cutoff; lock/reverse เป็นคำสั่งแยก; ไม่มี Edit route สำหรับ locked summary. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/HrController.php:148 (storeSummary), backend/app/Http/Controllers/HrController.php:203 (audit), backend/app/Services/HrAttendanceService.php (service state guards) |
### F052 Employee shift

| Contract | รายละเอียด |
| --- | --- |
| Page | Hr/Index |
| Fields | code, name, starts_at, ends_at, break_minutes, work_minutes_per_day, is_active |
| UI fields (static) | code, name, starts_at, ends_at, break_minutes, work_minutes_per_day, is_active |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | hr.shifts.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:hr.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/HrController.php:66 (storeShift), backend/app/Http/Controllers/HrController.php:203 (audit) |
### F053 Leave type

| Contract | รายละเอียด |
| --- | --- |
| Page | Hr/Index |
| Fields | code, name, annual_entitlement_days, carry_over_limit_days, is_paid, is_active |
| UI fields (static) | code, name, annual_entitlement_days, carry_over_limit_days, is_paid, is_active |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | hr.leave-types.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:hr.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/HrController.php:97 (storeLeaveType), backend/app/Http/Controllers/HrController.php:203 (audit) |
### F054 Employee work profile

| Contract | รายละเอียด |
| --- | --- |
| Page | Hr/Index |
| Fields | user_id, employee_shift_id, manager_user_id, employee_code, employment_type, started_on, ended_on |
| UI fields (static) | user_id, employee_code, employee_shift_id, manager_user_id, employment_type, started_on |
| References | user_id, employee_shift_id, manager_user_id, employee_code |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | hr.work-profiles.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:hr.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/HrController.php:75 (storeWorkProfile), backend/app/Http/Controllers/HrController.php:203 (audit) |
### F055 Holiday

| Contract | รายละเอียด |
| --- | --- |
| Page | Hr/Index |
| Fields | holiday_date, name |
| UI fields (static) | holiday_date, name |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | hr.holidays.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:hr.manage, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/HrController.php:88 (storeHoliday), backend/app/Http/Controllers/HrController.php:203 (audit) |
### F056 Vendor bill intake

| Contract | รายละเอียด |
| --- | --- |
| Page | Portal/Dashboard |
| Fields | vendor_invoice_no, invoice_date, amount, currency, note, file |
| UI fields (static) | vendor_invoice_no, invoice_date, amount, currency, file, note |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | file |
| Permission / middleware | portal.vendor-bills.store: web, Illuminate\Routing\Middleware\ThrottleRequests:5,1 |
| Business state | Portal grant/session, supplier/org และไฟล์; ไม่ใช้ internal auth.permissions แทน portal authorization. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/PortalController.php:93 (submitVendorBill), backend/app/Http/Controllers/PortalController.php:137 (user) |
### F057 Profile information

| Contract | รายละเอียด |
| --- | --- |
| Page | Profile/Partials/UpdateProfileInformationForm |
| Fields | name, email |
| UI fields (static) | name, email |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | profile.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified |
| Business state | Create/update ตาม route ที่มีจริง; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/ProfileController.php:30 (update), backend/app/Http/Requests/ProfileUpdateRequest.php:17 (rules) |
### F058 Notification preferences

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/NotificationPreferences |
| Fields | preferences, preferences.*.type, preferences.*.email_enabled, preferences.*.in_app_enabled |
| UI fields (static) | preferences |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | preferences, preferences.*.type, preferences.*.email_enabled, preferences.*.in_app_enabled |
| Attachment | — |
| Permission / middleware | settings.notifications.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified |
| Business state | Create/update ตาม route ที่มีจริง; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/NotificationPreferenceController.php:65 (update) |
### F059 External notification preferences

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/NotificationPreferences |
| Fields | preferences, preferences.*.channel, preferences.*.enabled, preferences.*.quiet_hours_start, preferences.*.quiet_hours_end, preferences.*.urgent_only |
| UI fields (static) | preferences |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | preferences, preferences.*.channel, preferences.*.enabled, preferences.*.quiet_hours_start, preferences.*.quiet_hours_end, preferences.*.urgent_only |
| Attachment | — |
| Permission / middleware | settings.notifications.external-preferences.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified |
| Business state | Create/update ตาม route ที่มีจริง; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/NotificationPreferenceController.php:90 (updateExternal) |
### F060 Notification channel

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/NotificationPreferences |
| Fields | channel, name, config, config.webhook_url, config.access_token, config.target_id, config.bot_token, config.chat_id |
| UI fields (static) | channel, name, config |
| References | config.target_id, config.chat_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.notification-channels.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.organization.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create channel พร้อม encrypted config; UI update ปัจจุบันเป็น toggle is_enabled เท่านั้น ไม่ใช่ full-config Edit. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/NotificationPreferenceController.php:112 (storeChannel), backend/app/Http/Controllers/Settings/NotificationPreferenceController.php:155 (validateChannelConfig), backend/app/Http/Controllers/Settings/NotificationPreferenceController.php:171 (audit) |
### F061 Organization information

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/Organization |
| Fields | name, legal_name, tax_id, email, phone, address, logo |
| UI fields (static) | — |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | logo |
| Permission / middleware | settings.organization.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.organization.update, Illuminate\Auth\Middleware\RequirePassword |
| Business state | Create/update ตาม route ที่มีจริง; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationSettingsController.php:42 (update), backend/app/Http/Controllers/Settings/OrganizationSettingsController.php:205 (deleteOldLogo) |
### F062 Document numbering formats

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/Organization |
| Fields | formats, formats.*.enabled, formats.*.format, formats.*.reset, formats.*.scope |
| UI fields (static) | formats |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | formats, formats.*.enabled, formats.*.format, formats.*.reset, formats.*.scope |
| Attachment | — |
| Permission / middleware | settings.organization.numbering.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.organization.update, Illuminate\Auth\Middleware\RequirePassword |
| Business state | Create/update ตาม route ที่มีจริง; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationSettingsController.php:83 (updateNumbering) |
### F063 Organization 2FA policy

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/Organization |
| Fields | enabled, required_for_privileged_roles, allow_trusted_devices, trusted_device_days |
| UI fields (static) | enabled, required_for_privileged_roles, allow_trusted_devices, trusted_device_days |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.organization.two-factor.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.organization.update, Illuminate\Auth\Middleware\RequirePassword |
| Business state | Create/update ตาม route ที่มีจริง; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | security policy |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationSettingsController.php:114 (updateTwoFactor), backend/app/Http/Controllers/Settings/OrganizationSettingsController.php:189 (twoFactorPolicy) |
### F064 Attendance privacy policy

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/Organization |
| Fields | capture_ip, capture_gps, require_employee_consent, retention_days |
| UI fields (static) | capture_ip, capture_gps, require_employee_consent, retention_days |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.organization.attendance-privacy.update: — |
| Business state | G01: controller updateAttendancePrivacy มีอยู่ แต่ไม่มี named route settings.organization.attendance-privacy.update; ต้องแก้ route integration ก่อน rollout. |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static audit พบ missing route; ยังไม่แก้/ไม่ทดสอบ browser |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationSettingsController.php:145 (updateAttendancePrivacy), backend/app/Http/Controllers/Settings/OrganizationSettingsController.php:197 (attendancePrivacyPolicy) |
### F065 Create branch

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/OrganizationStructure |
| Fields | name, address, phone, is_head_office |
| UI fields (static) | name, phone, address, is_head_office |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.branches.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.structure.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationStructureController.php:33 (storeBranch), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:274 (setHeadOfficeInTransaction), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:327 (audit) |
### F066 Create division

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/OrganizationStructure |
| Fields | branch_id, name |
| UI fields (static) | branch_id, name |
| References | branch_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.divisions.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.structure.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationStructureController.php:141 (storeDivision), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:327 (audit) |
### F067 Create department

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/OrganizationStructure |
| Fields | branch_id, division_id, name |
| UI fields (static) | branch_id, division_id, name |
| References | branch_id, division_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.departments.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.structure.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless($division->branch_id === $branchId, 422, 'Division does not belong to branch.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationStructureController.php:205 (storeDepartment), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:280 (validateDivisionBranch), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:327 (audit) |
### F068 Edit branch

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/OrganizationStructure |
| Fields | name, address, phone, is_head_office |
| UI fields (static) | name, phone, address, is_head_office |
| References | — |
| Route model bindings (context) | branch |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.branches.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.structure.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create/update ตาม route ที่มีจริง; abort_unless($entityOrgId === $actorOrgId, 404); |
| Current UI | existing modal |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationStructureController.php:68 (updateBranch), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:322 (assertSameOrg), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:274 (setHeadOfficeInTransaction), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:327 (audit) |
### F069 Edit division

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/OrganizationStructure |
| Fields | branch_id, name |
| UI fields (static) | branch_id, name |
| References | branch_id |
| Route model bindings (context) | division |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.divisions.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.structure.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create/update ตาม route ที่มีจริง; abort_unless($entityOrgId === $actorOrgId, 404); |
| Current UI | existing modal |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationStructureController.php:163 (updateDivision), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:322 (assertSameOrg), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:327 (audit) |
### F070 Edit department

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/OrganizationStructure |
| Fields | branch_id, division_id, name |
| UI fields (static) | branch_id, division_id, name |
| References | branch_id, division_id |
| Route model bindings (context) | department |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | settings.departments.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.structure.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | Create/update ตาม route ที่มีจริง; abort_unless($entityOrgId === $actorOrgId, 404); / abort_unless($division->branch_id === $branchId, 422, 'Division does not belong to branch.'); |
| Current UI | existing modal |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Settings/OrganizationStructureController.php:230 (updateDepartment), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:322 (assertSameOrg), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:280 (validateDivisionBranch), backend/app/Http/Controllers/Settings/OrganizationStructureController.php:327 (audit) |
### F071 Payment gateway configuration

| Contract | รายละเอียด |
| --- | --- |
| Page | Settings/PaymentGateway |
| Fields | enabled, qr_type, recipient_id, webhook_secret, bank_account_id, provider, provider_secret, livemode |
| UI fields (static) | provider, livemode, provider_secret, enabled, qr_type, recipient_id, bank_account_id, webhook_secret |
| References | recipient_id, bank_account_id |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | gateway.configure: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:settings.organization.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; throw ValidationException::withMessages(['provider_secret' => 'An Opn secret key matching test/live mode is required.']); / throw ValidationException::withMessages(['provider' => 'Resolve pending transactions before changing provider or mode.']); / throw ValidationException::withMessages(['recipient_id' => 'Recipient and signing secret are required.']); / throw ValidationException::withMessages(['recipient_id' => 'Recipient length must match the selected QR type.']); / throw ValidationException::withMessages(['recipient_id' => 'Wait for active QR intents to expire before changing the recipient.']); |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/GatewayController.php:34 (configure) |
### F072 Workflow delegation

| Contract | รายละเอียด |
| --- | --- |
| Page | Workflows/Index |
| Fields | delegate_user_id, subject_type, starts_at, ends_at |
| UI fields (static) | delegate_user_id, subject_type, starts_at, ends_at |
| References | delegate_user_id, subject_type |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | workflows.delegations.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:workflows.approve, Illuminate\Auth\Middleware\RequirePassword |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/WorkflowController.php:90 (storeDelegation), backend/app/Http/Controllers/WorkflowController.php:102 (audit), backend/app/Services/WorkflowEngineService.php (service state guards) |
### F073 Workflow definition and steps

| Contract | รายละเอียด |
| --- | --- |
| Page | Workflows/Index |
| Fields | code, name, subject_type, amount_min, amount_max, steps, steps.*.assignment_type, steps.*.approver_user_id, steps.*.approver_role_code, steps.*.execution_mode |
| UI fields (static) | code, name, subject_type, amount_min, amount_max |
| References | subject_type, steps.*.assignment_type, steps.*.approver_user_id, steps.*.approver_role_code |
| Route model bindings (context) | — |
| Line items / nested payload | steps, steps.*.assignment_type, steps.*.approver_user_id, steps.*.approver_role_code, steps.*.execution_mode |
| Attachment | — |
| Permission / middleware | workflows.definitions.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:workflows.manage, Illuminate\Auth\Middleware\RequirePassword |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/WorkflowController.php:54 (storeDefinition), backend/app/Http/Controllers/WorkflowController.php:102 (audit), backend/app/Services/WorkflowEngineService.php (service state guards) |
### F074 Customer

| Contract | รายละเอียด |
| --- | --- |
| Page | Sales/Customers |
| Fields | company_name, tax_id, customer_type, status, owner_id, phone, email, line_id, website, address, source, note |
| UI fields (static) | company_name, tax_id, customer_type, status, owner_id, phone, email, line_id, website, address, source, note |
| References | owner_id, line_id |
| Route model bindings (context) | customer |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | customers.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:customers.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; customers.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:customers.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | SalesAccess org/owner; เก็บ owner เดิมที่ไม่อยู่ในตัวเลือก; delete มี dependency guard แยก. |
| Current UI | CreateEditDialog: normal/expanded/minimized + draft guard |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | ผลรอบก่อน: Sales 12 tests/196 assertions + history 9 tests; TypeScript/ESLint/build ผ่าน; browser regression ยังไม่ผ่านการตรวจ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Sales/CustomerController.php:45 (store), backend/app/Http/Controllers/Sales/CustomerController.php:87 (validateCustomer), backend/app/Http/Controllers/Sales/CustomerController.php:110 (audit), backend/app/Http/Controllers/Sales/CustomerController.php:105 (trackedFields), backend/app/Http/Controllers/Sales/CustomerController.php:60 (update) |
### F075 Contact

| Contract | รายละเอียด |
| --- | --- |
| Page | Sales/Customers |
| Fields | name, position, phone, email, line_id, is_primary, note, customer_id |
| UI fields (static) | name, position, phone, email, line_id, is_primary, note, customer_id |
| References | line_id, customer_id |
| Route model bindings (context) | customer, contact |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | contacts.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:contacts.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; contacts.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:contacts.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | SalesAccess ผ่าน parent customer; update ไม่เปลี่ยน parent; is_primary มี uniqueness handling. |
| Current UI | CreateEditDialog: normal/expanded/minimized + draft guard |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | ผลรอบก่อน: Sales 12 tests/196 assertions + history 9 tests; TypeScript/ESLint/build ผ่าน; browser regression ยังไม่ผ่านการตรวจ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Sales/ContactController.php:16 (store), backend/app/Http/Controllers/Sales/ContactController.php:72 (validateContact), backend/app/Http/Controllers/Sales/ContactController.php:90 (audit), backend/app/Http/Controllers/Sales/ContactController.php:85 (trackedFields), backend/app/Http/Controllers/Sales/ContactController.php:37 (update) |
### F076 Deal

| Contract | รายละเอียด |
| --- | --- |
| Page | Sales/Deals |
| Fields | title, customer_id, contact_id, stage, value_amount, currency, probability, expected_close_date, owner_id, source, lost_reason, note |
| UI fields (static) | title, customer_id, contact_id, stage, value_amount, currency, probability, expected_close_date, owner_id, source, lost_reason, note |
| References | customer_id, contact_id, owner_id |
| Route model bindings (context) | deal |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | deals.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:deals.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; deals.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:deals.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | SalesAccess ตรวจ org/owner และ customer/contact chain; lost ต้อง reason; won/lost timestamps เป็น backend; ไม่พบ stage lock สำหรับ update. |
| Current UI | CreateEditDialog: normal/expanded/minimized + draft guard |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | ผลรอบก่อน: Sales 12 tests/196 assertions + history 9 tests; TypeScript/ESLint/build ผ่าน; browser regression ยังไม่ผ่านการตรวจ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Sales/DealController.php:51 (store), backend/app/Http/Controllers/Sales/DealController.php:117 (validateDeal), backend/app/Http/Controllers/Sales/DealController.php:135 (assertContactBelongsToCustomer), backend/app/Http/Controllers/Sales/DealController.php:144 (applyStageRules), backend/app/Http/Controllers/Sales/DealController.php:168 (audit), backend/app/Http/Controllers/Sales/DealController.php:163 (trackedFields), backend/app/Http/Controllers/Sales/DealController.php:69 (update) |
### F077 Activity

| Contract | รายละเอียด |
| --- | --- |
| Page | Sales/Deals |
| Fields | entity_type, entity_id, activity_type, subject, body, activity_at, follow_up_at, completed_at, owner_id |
| UI fields (static) | entity_type, entity_id, activity_type, subject, body, activity_at, follow_up_at, completed_at, owner_id |
| References | entity_type, entity_id, owner_id |
| Route model bindings (context) | activity |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | activities.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:activities.create, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1; activities.update: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:activities.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | SalesAccess ทั้ง original/target entity; owner/org; entity_type/entity_id ต้องคงเดิม; completed_at เก็บเดิม; Done เป็น command. |
| Current UI | CreateEditDialog: normal/expanded/minimized + draft guard |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | ผลรอบก่อน: Sales 12 tests/196 assertions + history 9 tests; TypeScript/ESLint/build ผ่าน; browser regression ยังไม่ผ่านการตรวจ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Sales/ActivityController.php:18 (store), backend/app/Http/Controllers/Sales/ActivityController.php:58 (validateActivity), backend/app/Http/Controllers/Sales/ActivityController.php:73 (assertEntityVisible), backend/app/Http/Controllers/Sales/ActivityController.php:104 (audit), backend/app/Http/Controllers/Sales/ActivityController.php:99 (trackedFields), backend/app/Http/Controllers/Sales/ActivityController.php:32 (update) |
### F078 Voucher attachment

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/CommercialDocuments |
| Fields | attachment |
| UI fields (static) | attachment |
| References | — |
| Route model bindings (context) | voucher |
| Line items / nested payload | — |
| Attachment | attachment |
| Permission / middleware | vouchers.attachment.store: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:vouchers.update, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:10,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless($voucher->org_id === $request->user()->org_id, 404); / $lockedVoucher = Voucher::where('org_id', $request->user()->org_id)->lockForUpdate()->findOrFail($voucher->id); / $previousFileId = $lockedVoucher->attachment_file_id; / $files->delete($lockedVoucher->attachment); / $file = $files->store($request, $request->file('attachment'), 'voucher', $lockedVoucher->id, 'voucher_proof'); / $lockedVoucher->update(['attachment_file_id' => $file->id]); / $this->audit($request, 'voucher.attachment.upload', 'voucher', $lockedVoucher->id, ['attachment_file_id' => $previousFileId], ['attachment_file_id' => $file->id]); |
| Current UI | inline upload button |
| New UI | shared CreateEditDialog; คง payload/permission/state; ใช้ searchable reference; ไม่เพิ่ม action ที่ไม่มีใน UI |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | — |
| Evidence | backend/app/Http/Controllers/Finance/CommercialDocumentController.php:297 (storeVoucherAttachment), backend/app/Http/Controllers/Finance/CommercialDocumentController.php:355 (audit) |
### F079 AcceptInvite

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/AcceptInvite |
| Fields | password, password_confirmation |
| UI fields (static) | password, password_confirmation |
| References | — |
| Route model bindings (context) | user, token |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | invites.accept.update: web, Illuminate\Auth\Middleware\RedirectIfAuthenticated |
| Business state | Create/update ตาม route ที่มีจริง; abort_unless($this->validToken($user, $token), 404); / return $user->status === 'invited' |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/AcceptInviteController.php:31 (update), backend/app/Http/Controllers/Auth/AcceptInviteController.php:63 (validToken) |
### F080 ConfirmPassword

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/ConfirmPassword |
| Fields | password |
| UI fields (static) | password |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | password.confirm: web, Illuminate\Auth\Middleware\Authenticate |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; throw ValidationException::withMessages([ |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/ConfirmablePasswordController.php:26 (store) |
### F081 ForgotPassword

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/ForgotPassword |
| Fields | email |
| UI fields (static) | email |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | password.email: web, Illuminate\Auth\Middleware\RedirectIfAuthenticated |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; throw ValidationException::withMessages([ |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/PasswordResetLinkController.php:30 (store) |
### F082 Login

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/Login |
| Fields | email, password, remember |
| UI fields (static) | email, password, remember |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | login: web, Illuminate\Auth\Middleware\RedirectIfAuthenticated |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/AuthenticatedSessionController.php:28 (store), backend/app/Http/Controllers/Auth/AuthenticatedSessionController.php:73 (hasTrustedDevice), backend/app/Http/Requests/Auth/LoginRequest.php:15 (authorize), backend/app/Http/Requests/Auth/LoginRequest.php:23 (rules) |
### F083 Register

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/Register |
| Fields | organization_name, name, email, password, password_confirmation |
| UI fields (static) | organization_name, name, email, password, password_confirmation |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | register: web, Illuminate\Auth\Middleware\RedirectIfAuthenticated |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/RegisteredUserController.php:26 (store) |
### F084 ResetPassword

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/ResetPassword |
| Fields | token, email, password, password_confirmation |
| UI fields (static) | email, password, password_confirmation, token |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | password.store: web, Illuminate\Auth\Middleware\RedirectIfAuthenticated |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; throw ValidationException::withMessages([ |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/NewPasswordController.php:35 (store) |
### F085 TwoFactorChallenge

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/TwoFactorChallenge |
| Fields | code, trust_device |
| UI fields (static) | code, trust_device |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | two-factor.challenge.verify: web, Illuminate\Auth\Middleware\RedirectIfAuthenticated, Illuminate\Routing\Middleware\ThrottleRequests:5,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many authenticator attempts.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/TwoFactorController.php:53 (verifyChallenge), backend/app/Http/Controllers/Auth/TwoFactorController.php:94 (consumeRecoveryCode), backend/app/Http/Controllers/Auth/TwoFactorController.php:109 (audit) |
### F086 TwoFactorSetup

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/TwoFactorSetup |
| Fields | code |
| UI fields (static) | code |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | two-factor.setup.confirm: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\RequirePassword, Illuminate\Routing\Middleware\ThrottleRequests:5,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_unless($policy->isEnabled($request->user()), 404); / abort_unless($secret && $totp->verify($secret, $data['code']), 422, 'Invalid authenticator code.'); |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/TwoFactorController.php:32 (confirmSetup), backend/app/Http/Controllers/Auth/TwoFactorController.php:109 (audit) |
### F087 VerifyEmail

| Contract | รายละเอียด |
| --- | --- |
| Page | Auth/VerifyEmail |
| Fields | — |
| UI fields (static) | — |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | verification.send: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Routing\Middleware\ThrottleRequests:6,1 |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/EmailVerificationNotificationController.php:14 (store) |
### F088 Dashboard

| Contract | รายละเอียด |
| --- | --- |
| Page | Dashboard |
| Fields | period, month, year, from, to |
| UI fields (static) | period, month, year, from, to |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | executive.dashboard: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:dashboard.view; finance.dashboard: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:expenses.view; delivery.dashboard: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:dashboard.view; dashboard: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:dashboard.view |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; abort_if($scope === 'executive' && ! $this->canViewExecutiveDashboard($user), 403); / abort_if($scope === 'finance' && ! $this->canViewFinanceDashboard($user), 403); / abort_if($scope === 'delivery' && ! $this->canViewDeliveryDashboard($user), 403); / $now = CarbonImmutable::now(); / $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfDay(); / $start = CarbonImmutable::create((int) $year, 1, 1)->startOfDay(); / $start = $from !== '' ? CarbonImmutable::parse($from)->startOfDay() : null; / $end = $to !== '' ? CarbonImmutable::parse($to)->endOfDay() : null; / $openTaskStatuses = ['todo', 'in_progress', 'blocked']; / $start = CarbonImmutable::parse($filters['start_date'])->startOfDay(); / $end = CarbonImmutable::parse($filters['end_date'])->endOfDay(); |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | search/filter/report ไม่ใช่ Create/Edit |
| Evidence | backend/app/Http/Controllers/Admin/DashboardController.php:29 (__invoke), backend/app/Http/Controllers/Admin/DashboardController.php:83 (canViewExecutiveDashboard), backend/app/Http/Controllers/Admin/DashboardController.php:89 (canViewFinanceDashboard), backend/app/Http/Controllers/Admin/DashboardController.php:94 (canViewDeliveryDashboard), backend/app/Http/Controllers/Admin/DashboardController.php:312 (dashboardFilters), backend/app/Http/Controllers/Admin/DashboardController.php:99 (executiveSummary), backend/app/Http/Controllers/Admin/DashboardController.php:161 (financeSummary), backend/app/Http/Controllers/Admin/DashboardController.php:173 (financeMetricSummary), backend/app/Http/Controllers/Admin/DashboardController.php:384 (applyDateRange), backend/app/Http/Controllers/Admin/DashboardController.php:365 (previousDashboardFilters), backend/app/Http/Controllers/Admin/DashboardController.php:397 (overBudgetProjectIds), backend/app/Http/Controllers/Admin/DashboardController.php:235 (deliverySummary) |
### F089 TaxReports

| Contract | รายละเอียด |
| --- | --- |
| Page | Finance/TaxReports |
| Fields | date_from, date_to, branch_id, status, customer_id, supplier_id, withholding |
| UI fields (static) | — |
| References | branch_id, customer_id, supplier_id |
| Route model bindings (context) | type |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | tax-reports.index: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:tax_reports.view; tax-reports.export: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:tax_reports.view; tax-reports.excel: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified, App\Http\Middleware\EnsurePermission:tax_reports.view |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; ->when($filters['status'], fn ($query, $status) => $status === 'posted' ? $query->where('status', $status) : $query) / abort_unless(in_array($type, ['sales', 'purchase', 'withholding'], true), HttpResponse::HTTP_NOT_FOUND); / abort_unless(in_array($type, ['sales', 'purchase', 'withholding', 'ar-aging', 'ap-aging'], true), HttpResponse::HTTP_NOT_FOUND); |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | search/filter/report ไม่ใช่ Create/Edit |
| Evidence | backend/app/Http/Controllers/Finance/TaxReportController.php:27 (index), backend/app/Http/Controllers/Finance/TaxReportController.php:128 (filters), backend/app/Http/Controllers/Finance/TaxReportController.php:152 (salesRows), backend/app/Http/Controllers/Finance/TaxReportController.php:430 (money), backend/app/Http/Controllers/Finance/TaxReportController.php:221 (purchaseRows), backend/app/Http/Controllers/Finance/TaxReportController.php:323 (withholdingRows), backend/app/Http/Controllers/Finance/TaxReportController.php:354 (arAgingRows), backend/app/Http/Controllers/Finance/TaxReportController.php:410 (agingRow), backend/app/Http/Controllers/Finance/TaxReportController.php:383 (apAgingRows), backend/app/Http/Controllers/Finance/TaxReportController.php:44 (export), backend/app/Http/Controllers/Finance/TaxReportController.php:91 (excel), backend/app/Http/Controllers/Finance/TaxReportController.php:438 (reportHeaders) |
### F090 DeleteUserForm

| Contract | รายละเอียด |
| --- | --- |
| Page | Profile/Partials/DeleteUserForm |
| Fields | password |
| UI fields (static) | password |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | profile.destroy: web, Illuminate\Auth\Middleware\Authenticate, Illuminate\Auth\Middleware\EnsureEmailIsVerified |
| Business state | เฉพาะ action ที่ระบุ; ไม่เพิ่ม Edit route; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | existing modal |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/ProfileController.php:46 (destroy) |
### F091 UpdatePasswordForm

| Contract | รายละเอียด |
| --- | --- |
| Page | Profile/Partials/UpdatePasswordForm |
| Fields | current_password, password, password_confirmation |
| UI fields (static) | current_password, password, password_confirmation |
| References | — |
| Route model bindings (context) | — |
| Line items / nested payload | — |
| Attachment | — |
| Permission / middleware | password.update: web, Illuminate\Auth\Middleware\Authenticate |
| Business state | Create/update ตาม route ที่มีจริง; คง validation/reference scope และ service authorization ตาม source contract; ไม่อนุมานสิทธิ์จากปุ่ม UI |
| Current UI | embedded page/card/sidebar form |
| New UI | คง specialized/security/action flow; ไม่บังคับ CRUD dialog |
| Test | static source/route audit เท่านั้น; ไม่ได้รัน runtime/browser ของฟอร์มนี้ |
| Exception | authentication/password/verification/deletion security flow |
| Evidence | backend/app/Http/Controllers/Auth/PasswordController.php:16 (update) |

## Backend-only routes: ไม่มี editor ให้ย้ายใน UI ปัจจุบัน

ไม่ตีความว่า backend ใช้ไม่ได้; เป็นขอบเขตที่ต้องอนุมัติเป็น feature เพิ่มก่อนสร้าง UI ใหม่. ทุก action มี field/guard contract ใน JSON.

| Route | Controller |
| --- | --- |
| PATCH /accounting/chart-of-accounts/{chartOfAccount} (accounting.chart-of-accounts.update) | App\Http\Controllers\Finance\GeneralLedgerController@updateAccount |
| POST /bank-transfers (bank-transfers.store) | App\Http\Controllers\Finance\BankTransferController@store |
| POST /billing-notes (billing-notes.store) | App\Http\Controllers\Finance\CommercialDocumentController@storeBillingNote |
| POST /credit-debit-notes (credit-debit-notes.store) | App\Http\Controllers\Finance\CommercialDocumentController@storeCreditDebitNote |
| POST /hr/leave-balances (hr.leave-balances.store) | App\Http\Controllers\HrController@storeLeaveBalance |
| POST /portal-users (portal-users.store) | App\Http\Controllers\PortalController@provision |
| PATCH /purchase-orders/{purchaseOrder} (purchase-orders.update) | App\Http\Controllers\Finance\PurchaseOrderController@update |
| POST /purchase-requests (purchase-requests.store) | App\Http\Controllers\Finance\CommercialDocumentController@storePurchaseRequest |
| PATCH /quotations/{quotation} (quotations.update) | App\Http\Controllers\Finance\QuotationController@update |
| PATCH /users/{user}/role (users.role.update) | App\Http\Controllers\Admin\RoleController@assign |
| PATCH /users/{user}/structure (users.structure.update) | App\Http\Controllers\Admin\UserController@updateStructure |
| POST /vouchers (vouchers.store) | App\Http\Controllers\Finance\CommercialDocumentController@storeVoucher |

## Write-route coverage / non-form actions

คำสั่ง approve/post/pay/reverse/delete/disable/enable/complete/accept/test, state toggle, security และ webhook คง flow/confirmation เดิม. Upload subforms ที่เป็นงานบันทึก metadata แยกไว้ใน inventory.

| Route | Classification | UI consumers |
| --- | --- | --- |
| POST /accounting/chart-of-accounts (accounting.chart-of-accounts.store) | form inventory | backend/resources/js/Pages/Accounting/ChartOfAccounts.tsx |
| PATCH /accounting/chart-of-accounts/{chartOfAccount} (accounting.chart-of-accounts.update) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /accounting/journals (accounting.journals.store) | form inventory | backend/resources/js/Pages/Accounting/Journals.tsx |
| POST /accounting/journals/{journalEntry}/reverse (accounting.journals.reverse) | exception: command/security/integration | backend/resources/js/Pages/Accounting/Journals.tsx |
| POST /accounting/periods (accounting.periods.store) | form inventory | backend/resources/js/Pages/Accounting/Periods.tsx |
| POST /accounting/periods/{accountingPeriod}/close (accounting.periods.close) | exception: command/security/integration | backend/resources/js/Pages/Accounting/Periods.tsx |
| POST /activities (activities.store) | form inventory | backend/resources/js/Pages/Sales/Deals.tsx |
| PATCH /activities/{activity} (activities.update) | form inventory | backend/resources/js/Pages/Sales/Deals.tsx |
| PATCH /activities/{activity}/complete (activities.complete) | exception: command/security/integration | backend/resources/js/Pages/Sales/Deals.tsx |
| POST /api/gateway/{config}/opn (gateway.opn) | exception: command/security/integration | — |
| POST /api/gateway/{config}/settlement (gateway.webhook) | exception: command/security/integration | — |
| POST /bank-accounts (bank-accounts.store) | form inventory | backend/resources/js/Pages/Finance/BankAccounts.tsx |
| PATCH /bank-accounts/{bankAccount} (bank-accounts.update) | form inventory | backend/resources/js/Pages/Finance/BankAccounts.tsx |
| POST /bank-statement-lines/{line}/match (bank-statement-lines.match) | exception: command/security/integration | — |
| POST /bank-statement-lines/{line}/unmatch (bank-statement-lines.unmatch) | exception: command/security/integration | — |
| POST /bank-statements/import (bank-statements.import) | form inventory | backend/resources/js/Pages/Finance/BankStatements.tsx |
| POST /bank-transfers (bank-transfers.store) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /billing-notes (billing-notes.store) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /cheques (cheques.store) | form inventory | backend/resources/js/Pages/Finance/Cheques.tsx |
| POST /cheques/{cheque}/transition (cheques.transition) | exception: command/security/integration | backend/resources/js/Pages/Finance/Cheques.tsx |
| POST /confirm-password (unnamed) | exception: command/security/integration | — |
| PATCH /contacts/{contact} (contacts.update) | form inventory | backend/resources/js/Pages/Sales/Customers.tsx |
| DELETE /contacts/{contact} (contacts.destroy) | exception: command/security/integration | — |
| POST /credit-debit-notes (credit-debit-notes.store) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /currencies (currencies.store) | form inventory | backend/resources/js/Pages/Finance/Currencies.tsx |
| POST /customers (customers.store) | form inventory | backend/resources/js/Pages/Sales/Customers.tsx |
| PATCH /customers/{customer} (customers.update) | form inventory | backend/resources/js/Pages/Sales/Customers.tsx |
| DELETE /customers/{customer} (customers.destroy) | exception: command/security/integration | backend/resources/js/Pages/Sales/Customers.tsx |
| POST /customers/{customer}/contacts (contacts.store) | form inventory | backend/resources/js/Pages/Sales/Customers.tsx |
| POST /deals (deals.store) | form inventory | backend/resources/js/Pages/Sales/Deals.tsx |
| PATCH /deals/{deal} (deals.update) | form inventory | backend/resources/js/Pages/Sales/Deals.tsx |
| POST /deals/{deal}/project (deals.projects.store) | exception: command/security/integration | backend/resources/js/Pages/Sales/Deals.tsx |
| POST /delivery-orders (delivery-orders.store) | form inventory | backend/resources/js/Pages/Finance/CommercialDocuments.tsx |
| POST /documents (documents.store) | form inventory | backend/resources/js/Pages/Documents/Index.tsx |
| POST /documents/categories (documents.categories.store) | form inventory | backend/resources/js/Pages/Documents/Index.tsx |
| POST /documents/retention-policies (documents.retention-policies.store) | form inventory | backend/resources/js/Pages/Documents/Index.tsx |
| POST /documents/{document}/links (documents.links.store) | form inventory | backend/resources/js/Pages/Documents/Index.tsx |
| POST /documents/{document}/versions (documents.versions.store) | form inventory | backend/resources/js/Pages/Documents/Index.tsx |
| PATCH /e-tax/config (e-tax.config.update) | form inventory | backend/resources/js/Pages/Finance/ETax.tsx |
| POST /e-tax/documents (e-tax.documents.generate) | form inventory | backend/resources/js/Pages/Finance/ETax.tsx |
| POST /e-tax/documents/{document}/submit (e-tax.documents.submit) | exception: command/security/integration | backend/resources/js/Pages/Finance/ETax.tsx |
| POST /email/verification-notification (verification.send) | form inventory | backend/resources/js/Pages/Auth/VerifyEmail.tsx, backend/resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.tsx |
| POST /exchange-rates (exchange-rates.store) | form inventory | backend/resources/js/Pages/Finance/Currencies.tsx |
| POST /expenses (expenses.store) | form inventory | backend/resources/js/Pages/Finance/Expenses.tsx |
| PATCH /expenses/{expense} (expenses.update) | form inventory | backend/resources/js/Pages/Finance/Expenses.tsx |
| POST /expenses/{expense}/approve (expenses.approve) | exception: command/security/integration | backend/resources/js/Pages/Finance/Expenses.tsx |
| POST /expenses/{expense}/pay (expenses.pay) | exception: command/security/integration | — |
| POST /expenses/{expense}/reject (expenses.reject) | form inventory | backend/resources/js/Pages/Finance/Expenses.tsx |
| POST /expenses/{expense}/vendor-payments (expenses.vendor-payments.store) | exception: command/security/integration | backend/resources/js/Pages/Finance/Expenses.tsx |
| POST /fixed-assets (fixed-assets.store) | form inventory | backend/resources/js/Pages/Finance/FixedAssets.tsx |
| POST /fixed-assets/categories (fixed-assets.categories.store) | form inventory | backend/resources/js/Pages/Finance/FixedAssets.tsx |
| POST /fixed-assets/depreciate (fixed-assets.depreciate) | exception: command/security/integration | backend/resources/js/Pages/Finance/FixedAssets.tsx |
| POST /fixed-assets/{fixedAsset}/attachment (fixed-assets.attachment.store) | form inventory | backend/resources/js/Pages/Finance/FixedAssets.tsx |
| POST /fixed-assets/{fixedAsset}/dispose (fixed-assets.dispose) | exception: command/security/integration | backend/resources/js/Pages/Finance/FixedAssets.tsx |
| POST /forgot-password (password.email) | form inventory | backend/resources/js/Pages/Auth/ForgotPassword.tsx |
| POST /fx-revaluations (fx-revaluations.store) | exception: command/security/integration | backend/resources/js/Pages/Finance/Currencies.tsx |
| POST /fx-revaluations/reverse (fx-revaluations.reverse) | exception: command/security/integration | backend/resources/js/Pages/Finance/Currencies.tsx |
| POST /goods-receipts (goods-receipts.store) | form inventory | backend/resources/js/Pages/Finance/GoodsReceipts.tsx |
| POST /hr/clock-in (hr.clock-in) | form inventory | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/clock-out (hr.clock-out) | form inventory | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/holidays (hr.holidays.store) | form inventory | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/leave-balances (hr.leave-balances.store) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /hr/leave-requests (hr.leave-requests.store) | form inventory | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/leave-requests/{leaveRequest}/cancel (hr.leave-requests.cancel) | exception: command/security/integration | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/leave-requests/{leaveRequest}/submit (hr.leave-requests.submit) | exception: command/security/integration | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/leave-types (hr.leave-types.store) | form inventory | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/shifts (hr.shifts.store) | form inventory | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/summaries (hr.summaries.store) | form inventory | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/summaries/{attendanceSummary}/lock (hr.summaries.lock) | exception: command/security/integration | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/summaries/{attendanceSummary}/reverse (hr.summaries.reverse) | exception: command/security/integration | backend/resources/js/Pages/Hr/Index.tsx |
| POST /hr/work-profiles (hr.work-profiles.store) | form inventory | backend/resources/js/Pages/Hr/Index.tsx |
| POST /inventory-lots (inventory-lots.store) | form inventory | backend/resources/js/Pages/Finance/InventoryOperations.tsx |
| PATCH /invites/{user}/{token} (invites.accept.update) | form inventory | backend/resources/js/Pages/Auth/AcceptInvite.tsx |
| POST /invoices (invoices.store) | form inventory | backend/resources/js/Pages/Finance/Invoices.tsx |
| PATCH /invoices/{invoice} (invoices.update) | form inventory | backend/resources/js/Pages/Finance/Invoices.tsx |
| POST /invoices/{invoice}/payments (invoices.payments.store) | form inventory | backend/resources/js/Pages/Finance/Invoices.tsx |
| PATCH /invoices/{invoice}/void (invoices.void) | exception: command/security/integration | backend/resources/js/Pages/Finance/Invoices.tsx |
| POST /login (unnamed) | exception: command/security/integration | — |
| POST /logout (logout) | exception: command/security/integration | backend/resources/js/Layouts/AuthenticatedLayout.tsx, backend/resources/js/Pages/Auth/VerifyEmail.tsx |
| PUT /password (password.update) | form inventory | backend/resources/js/Pages/Profile/Partials/UpdatePasswordForm.tsx |
| POST /payments/{payment}/reverse (payments.reverse) | exception: command/security/integration | backend/resources/js/Pages/Finance/Invoices.tsx |
| POST /payroll/policies (payroll.policies.store) | form inventory | backend/resources/js/Pages/Finance/Payroll.tsx |
| POST /payroll/profiles (payroll.profiles.store) | form inventory | backend/resources/js/Pages/Finance/Payroll.tsx |
| POST /payroll/runs (payroll.runs.store) | form inventory | backend/resources/js/Pages/Finance/Payroll.tsx |
| POST /payroll/runs/{payrollRun}/approve (payroll.runs.approve) | exception: command/security/integration | backend/resources/js/Pages/Finance/Payroll.tsx |
| POST /payroll/runs/{payrollRun}/calculate (payroll.runs.calculate) | exception: command/security/integration | backend/resources/js/Pages/Finance/Payroll.tsx |
| POST /payroll/runs/{payrollRun}/pay (payroll.runs.pay) | exception: command/security/integration | backend/resources/js/Pages/Finance/Payroll.tsx |
| POST /petty-cash/funds (petty-cash.funds.store) | form inventory | backend/resources/js/Pages/Finance/PettyCash.tsx |
| POST /petty-cash/reimbursements (petty-cash.reimbursements.store) | form inventory | backend/resources/js/Pages/Finance/PettyCash.tsx |
| POST /petty-cash/requests (petty-cash.requests.store) | form inventory | backend/resources/js/Pages/Finance/PettyCash.tsx |
| POST /petty-cash/requests/{pettyCashRequest}/approve (petty-cash.requests.approve) | exception: command/security/integration | backend/resources/js/Pages/Finance/PettyCash.tsx |
| POST /petty-cash/requests/{pettyCashRequest}/pay (petty-cash.requests.pay) | exception: command/security/integration | backend/resources/js/Pages/Finance/PettyCash.tsx |
| POST /petty-cash/requests/{pettyCashRequest}/reject (petty-cash.requests.reject) | exception: command/security/integration | backend/resources/js/Pages/Finance/PettyCash.tsx |
| POST /portal-users (portal-users.store) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /portal/logout (portal.logout) | exception: command/security/integration | backend/resources/js/Pages/Portal/Dashboard.tsx |
| POST /portal/quotations/{quotation}/accept (portal.quotations.accept) | exception: command/security/integration | backend/resources/js/Pages/Portal/Dashboard.tsx |
| POST /portal/vendor-bills (portal.vendor-bills.store) | form inventory | backend/resources/js/Pages/Portal/Dashboard.tsx |
| POST /products (products.store) | form inventory | backend/resources/js/Pages/Finance/Products.tsx |
| PATCH /products/{product} (products.update) | form inventory | backend/resources/js/Pages/Finance/Products.tsx |
| DELETE /products/{product} (products.destroy) | exception: command/security/integration | backend/resources/js/Pages/Finance/Products.tsx |
| PATCH /profile (profile.update) | form inventory | backend/resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.tsx |
| DELETE /profile (profile.destroy) | form inventory | backend/resources/js/Pages/Profile/Partials/DeleteUserForm.tsx |
| POST /projects (projects.store) | form inventory | backend/resources/js/Pages/Delivery/Projects.tsx |
| PATCH /projects/{project} (projects.update) | form inventory | backend/resources/js/Pages/Delivery/Projects.tsx |
| POST /projects/{project}/members (projects.members.store) | form inventory | backend/resources/js/Pages/Delivery/Projects.tsx |
| DELETE /projects/{project}/members/{member} (projects.members.destroy) | exception: command/security/integration | backend/resources/js/Pages/Delivery/Projects.tsx |
| POST /purchase-orders (purchase-orders.store) | form inventory | backend/resources/js/Pages/Finance/PurchaseOrders.tsx |
| PATCH /purchase-orders/{purchaseOrder} (purchase-orders.update) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /purchase-orders/{purchaseOrder}/approve (purchase-orders.approve) | exception: command/security/integration | backend/resources/js/Pages/Finance/PurchaseOrders.tsx |
| POST /purchase-orders/{purchaseOrder}/cancel (purchase-orders.cancel) | exception: command/security/integration | backend/resources/js/Pages/Finance/PurchaseOrders.tsx |
| POST /purchase-requests (purchase-requests.store) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /purchase-requests/{purchaseRequest}/approve (purchase-requests.approve) | exception: command/security/integration | — |
| POST /purchase-requests/{purchaseRequest}/convert-to-po (purchase-requests.convert-to-po) | exception: command/security/integration | — |
| POST /quotations (quotations.store) | form inventory | backend/resources/js/Pages/Finance/Quotations.tsx |
| PATCH /quotations/{quotation} (quotations.update) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /quotations/{quotation}/approve (quotations.approve) | exception: command/security/integration | backend/resources/js/Pages/Finance/Quotations.tsx |
| POST /quotations/{quotation}/convert-to-invoice (quotations.convert-to-invoice) | exception: command/security/integration | backend/resources/js/Pages/Finance/Quotations.tsx |
| POST /quotations/{quotation}/reject (quotations.reject) | exception: command/security/integration | backend/resources/js/Pages/Finance/Quotations.tsx |
| POST /register (unnamed) | exception: command/security/integration | — |
| POST /reset-password (password.store) | form inventory | backend/resources/js/Pages/Auth/ResetPassword.tsx |
| PATCH /roles/{role}/permissions (roles.permissions.update) | form inventory | backend/resources/js/Pages/Admin/Roles.tsx |
| POST /settings/branches (settings.branches.store) | form inventory | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| PATCH /settings/branches/{branch} (settings.branches.update) | form inventory | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| DELETE /settings/branches/{branch} (settings.branches.destroy) | exception: command/security/integration | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| PATCH /settings/branches/{branch}/disable (settings.branches.disable) | exception: command/security/integration | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| PATCH /settings/branches/{branch}/head-office (settings.branches.head-office) | exception: command/security/integration | — |
| POST /settings/departments (settings.departments.store) | form inventory | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| PATCH /settings/departments/{department} (settings.departments.update) | form inventory | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| DELETE /settings/departments/{department} (settings.departments.destroy) | exception: command/security/integration | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| PATCH /settings/departments/{department}/disable (settings.departments.disable) | exception: command/security/integration | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| POST /settings/divisions (settings.divisions.store) | form inventory | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| PATCH /settings/divisions/{division} (settings.divisions.update) | form inventory | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| DELETE /settings/divisions/{division} (settings.divisions.destroy) | exception: command/security/integration | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| PATCH /settings/divisions/{division}/disable (settings.divisions.disable) | exception: command/security/integration | backend/resources/js/Pages/Settings/OrganizationStructure.tsx |
| POST /settings/notification-channels (settings.notification-channels.store) | form inventory | backend/resources/js/Pages/Settings/NotificationPreferences.tsx |
| PATCH /settings/notification-channels/{channel} (settings.notification-channels.update) | exception: command/security/integration | backend/resources/js/Pages/Settings/NotificationPreferences.tsx |
| POST /settings/notification-channels/{channel}/test (settings.notification-channels.test) | exception: command/security/integration | backend/resources/js/Pages/Settings/NotificationPreferences.tsx |
| PATCH /settings/notifications (settings.notifications.update) | form inventory | backend/resources/js/Pages/Settings/NotificationPreferences.tsx |
| PATCH /settings/notifications/external-preferences (settings.notifications.external-preferences.update) | form inventory | backend/resources/js/Pages/Settings/NotificationPreferences.tsx |
| PATCH /settings/organization (settings.organization.update) | form inventory | backend/resources/js/Pages/Settings/Organization.tsx |
| PATCH /settings/organization/numbering (settings.organization.numbering.update) | form inventory | backend/resources/js/Pages/Settings/Organization.tsx |
| PATCH /settings/organization/two-factor (settings.organization.two-factor.update) | form inventory | backend/resources/js/Pages/Settings/Organization.tsx |
| PUT /settings/payment-gateway (gateway.configure) | form inventory | backend/resources/js/Pages/Settings/PaymentGateway.tsx |
| POST /stock-counts (stock-counts.store) | form inventory | backend/resources/js/Pages/Finance/InventoryOperations.tsx |
| POST /stock-movements (stock-movements.store) | form inventory | backend/resources/js/Pages/Finance/GoodsReceipts.tsx |
| POST /stock-transfers (stock-transfers.store) | form inventory | backend/resources/js/Pages/Finance/InventoryOperations.tsx |
| PUT /storage/{path} (storage.local.upload) | exception: command/security/integration | — |
| POST /suppliers (suppliers.store) | form inventory | backend/resources/js/Pages/Finance/Suppliers.tsx |
| PATCH /suppliers/{supplier} (suppliers.update) | form inventory | backend/resources/js/Pages/Finance/Suppliers.tsx |
| DELETE /suppliers/{supplier} (suppliers.destroy) | exception: command/security/integration | backend/resources/js/Pages/Finance/Suppliers.tsx |
| PATCH /task-checklists/{checklist} (task-checklists.update) | exception: command/security/integration | backend/resources/js/Pages/Delivery/Tasks.tsx |
| POST /tasks (tasks.store) | form inventory | backend/resources/js/Pages/Delivery/Tasks.tsx |
| PATCH /tasks/{task} (tasks.update) | form inventory | backend/resources/js/Pages/Delivery/Tasks.tsx |
| POST /tasks/{task}/checklists (tasks.checklists.store) | form inventory | backend/resources/js/Pages/Delivery/Tasks.tsx |
| POST /tasks/{task}/comments (tasks.comments.store) | form inventory | backend/resources/js/Pages/Delivery/Tasks.tsx |
| POST /two-factor-challenge (two-factor.challenge.verify) | form inventory | backend/resources/js/Pages/Auth/TwoFactorChallenge.tsx |
| POST /two-factor/setup (two-factor.setup.confirm) | form inventory | backend/resources/js/Pages/Auth/TwoFactorSetup.tsx |
| POST /users/invite (users.invite) | form inventory | backend/resources/js/Pages/Admin/Users.tsx |
| PATCH /users/{user} (users.update) | form inventory | backend/resources/js/Pages/Admin/Users.tsx |
| PATCH /users/{user}/disable (users.disable) | exception: command/security/integration | backend/resources/js/Pages/Admin/Users.tsx |
| PATCH /users/{user}/enable (users.enable) | exception: command/security/integration | backend/resources/js/Pages/Admin/Users.tsx |
| PATCH /users/{user}/role (users.role.update) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| PATCH /users/{user}/structure (users.structure.update) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /users/{user}/two-factor/reset (two-factor.reset) | exception: command/security/integration | — |
| POST /vendor-payments/{vendorPayment}/reverse (vendor-payments.reverse) | exception: command/security/integration | — |
| POST /vouchers (vouchers.store) | backend-only; ไม่มี UI editor ปัจจุบัน | — |
| POST /vouchers/{voucher}/attachment (vouchers.attachment.store) | form inventory | backend/resources/js/Pages/Finance/CommercialDocuments.tsx |
| POST /warehouses (warehouses.store) | form inventory | backend/resources/js/Pages/Finance/InventoryOperations.tsx |
| POST /warehouses/{warehouse}/bins (warehouses.bins.store) | form inventory | backend/resources/js/Pages/Finance/InventoryOperations.tsx |
| POST /workflow-approvals/{workflowApproval}/act (workflows.approvals.act) | exception: command/security/integration | backend/resources/js/Pages/Workflows/Index.tsx |
| POST /workflow-delegations (workflows.delegations.store) | form inventory | backend/resources/js/Pages/Workflows/Index.tsx |
| POST /workflows/definitions (workflows.definitions.store) | form inventory | backend/resources/js/Pages/Workflows/Index.tsx |
| POST /workflows/submit/{subjectType}/{subjectId} (workflows.submit) | exception: command/security/integration | backend/resources/js/Pages/Finance/CommercialDocuments.tsx, backend/resources/js/Pages/Finance/Expenses.tsx, backend/resources/js/Pages/Finance/PurchaseOrders.tsx, backend/resources/js/Pages/Hr/Index.tsx |

## Page และ shared entry-point coverage

| Source | Forms | Inventory IDs | Classification |
| --- | --- | --- | --- |
| backend/resources/js/Pages/Accounting/ChartOfAccounts.tsx | 1 | F001 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Accounting/Journals.tsx | 1 | F002 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Accounting/Periods.tsx | 1 | F003 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Accounting/Reports.tsx | 0 | — | read-only/report/filter/navigation/wrapper; no standalone CRUD form |
| backend/resources/js/Pages/Admin/AuditLogs.tsx | 0 | — | read-only/report/filter/navigation/wrapper; no standalone CRUD form |
| backend/resources/js/Pages/Admin/Roles.tsx | 1 | F004 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Admin/Users.tsx | 2 | F005, F006 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/AcceptInvite.tsx | 1 | F079 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/ConfirmPassword.tsx | 1 | F080 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/ForgotPassword.tsx | 1 | F081 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/Login.tsx | 1 | F082 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/Register.tsx | 1 | F083 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/ResetPassword.tsx | 1 | F084 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/TwoFactorChallenge.tsx | 1 | F085 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/TwoFactorSetup.tsx | 1 | F086 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Auth/VerifyEmail.tsx | 1 | F087 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Dashboard.tsx | 1 | F088 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Delivery/Projects.tsx | 2 | F007, F008 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Delivery/Tasks.tsx | 3 | F009, F010, F011 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Documents/Index.tsx | 5 | F012, F013, F014, F015, F016 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/BankAccounts.tsx | 1 | F017 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/BankStatements.tsx | 1 | F018 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/Cheques.tsx | 1 | F019 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/CommercialDocuments.tsx | 1 | F020, F078 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/Currencies.tsx | 2 | F021, F022 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/ETax.tsx | 2 | F023, F024 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/Expenses.tsx | 2 | F025, F026 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/FixedAssets.tsx | 3 | F027, F028, F029 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/GoodsReceipts.tsx | 2 | F030, F031 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/InventoryOperations.tsx | 5 | F032, F033, F034, F035, F036 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/Invoices.tsx | 2 | F037, F038 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/Payroll.tsx | 3 | F039, F040, F041 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/PettyCash.tsx | 3 | F042, F043, F044 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/Products.tsx | 1 | F045 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/PurchaseOrders.tsx | 1 | F046 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/Quotations.tsx | 1 | F047 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/Suppliers.tsx | 1 | F048 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/TaxReports.tsx | 1 | F089 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Finance/TreasuryReports.tsx | 0 | — | read-only/report/filter/navigation/wrapper; no standalone CRUD form |
| backend/resources/js/Pages/Hr/Index.tsx | 7 | F049, F050, F051, F052, F053, F054, F055 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Portal/Dashboard.tsx | 1 | F056 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Portal/SignIn.tsx | 0 | — | read-only/report/filter/navigation/wrapper; no standalone CRUD form |
| backend/resources/js/Pages/Profile/Edit.tsx | 0 | — | read-only/report/filter/navigation/wrapper; no standalone CRUD form |
| backend/resources/js/Pages/Profile/Partials/DeleteUserForm.tsx | 1 | F090 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Profile/Partials/UpdatePasswordForm.tsx | 1 | F091 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.tsx | 1 | F057 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Sales/ActivityDialog.tsx | 0 | — | Sales shared dialog body/fields |
| backend/resources/js/Pages/Sales/ContactDialog.tsx | 0 | — | Sales shared dialog body/fields |
| backend/resources/js/Pages/Sales/CustomerDialog.tsx | 0 | — | Sales shared dialog body/fields |
| backend/resources/js/Pages/Sales/Customers.tsx | 0 | F074, F075 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Sales/Dashboard.tsx | 0 | — | read-only/report/filter/navigation/wrapper; no standalone CRUD form |
| backend/resources/js/Pages/Sales/DealDialog.tsx | 0 | — | Sales shared dialog body/fields |
| backend/resources/js/Pages/Sales/DealDialogFields.tsx | 0 | — | Sales shared dialog body/fields |
| backend/resources/js/Pages/Sales/Deals.tsx | 0 | F076, F077 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Settings/NotificationPreferences.tsx | 3 | F058, F059, F060 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Settings/Organization.tsx | 4 | F061, F062, F063, F064 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Settings/OrganizationStructure.tsx | 6 | F065, F066, F067, F068, F069, F070 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Settings/PaymentGateway.tsx | 1 | F071 | form/shared-dialog participant (see rows) |
| backend/resources/js/Pages/Welcome.tsx | 0 | — | read-only/report/filter/navigation/wrapper; no standalone CRUD form |
| backend/resources/js/Pages/Workflows/Index.tsx | 2 | F072, F073 | form/shared-dialog participant (see rows) |

Dashboard/Executive/Finance/Delivery ใช้ DashboardController และ Dashboard.tsx: filter/deep links คง query/return context; Sales/Dashboard เป็น summary ไม่มี CRUD entry. Layout nav ชี้ index ของโมดูล; ไม่สร้าง editor ซ้ำ. Deals → invoices.index เก็บ customer_id/deal_id, Deals → projects เป็น command. Portal แยก vendor bill form ออกจาก accept/logout/download.

- backend/resources/js/Components/UI/CreateEditDialog.tsx: 1 form; routes —
- backend/resources/js/Layouts/AuthenticatedLayout.tsx: 0 form; routes dashboard, executive.dashboard, sales.dashboard, customers.index, deals.index, finance.dashboard, invoices.index, quotations.index, commercial-documents.index, expenses.index, tax-reports.index, e-tax.index, suppliers.index, purchase-orders.index, bank-accounts.index, bank-statements.index, petty-cash.index, cheques.index, treasury-reports.index, payroll.index, hr.index, workflows.index, accounting.chart-of-accounts.index, accounting.periods.index, accounting.journals.index, accounting.reports.index, fixed-assets.index, currencies.index, goods-receipts.index, inventory-operations.index, products.index, delivery.dashboard, tasks.index, projects.index, users.index, roles.index, audit-logs.index, gateway.settings, settings.organization.edit, settings.structure.index, settings.notifications.edit, profile.edit, logout

## Acceptance และ rollout ต่อไป

ใช้ลำดับ 0–7 และ acceptance ใน [rollout plan](PHASE_23_1_DIALOG_ROLLOUT.md). แต่ละ pending/gap row ต้องย้ายฟอร์ม + ตรวจ payload/reference/permission/state/draft/attachment + browser desktop/mobile ก่อนเปลี่ยนเป็น accepted; migrated ยังไม่เท่ากับ accepted. backend-only และ command exceptions คงข้อจำกัดเดิม.
