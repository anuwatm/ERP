# ทะเบียนไฟล์และจุดเข้า UI

สร้างจาก Laravel route:list ของ configuration ฐานสังเคราะห์และจุด Inertia::render ใน controller เมื่อ 7 พฤศจิกายน 2026 รายการนี้แยกหน้าที่เปิดผ่าน route ออกจาก component ย่อย หลักฐาน Chrome และข้อจำกัดอยู่ใน [ทะเบียนตรวจรับ](ui-acceptance-register.md)

สิทธิ์แสดงตาม middleware จริง การกรององค์กร สาขา และข้อมูลเพิ่มเติมยังอยู่ใน controller/service ของแต่ละหน้า

## หน้า Inertia ที่มี route

| หน้า | URL | Middleware / สิทธิ์ | สถานะหลักฐาน |
| --- | --- | --- | --- |
| [Accounting/ChartOfAccounts](../../backend/resources/js/Pages/Accounting/ChartOfAccounts.tsx) | `/accounting/chart-of-accounts` | web, auth, verified, App\Http\Middleware\EnsurePermission:accounting.chart_accounts.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Accounting/Journals](../../backend/resources/js/Pages/Accounting/Journals.tsx) | `/accounting/journals` | web, auth, verified, App\Http\Middleware\EnsurePermission:accounting.journals.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Accounting/Periods](../../backend/resources/js/Pages/Accounting/Periods.tsx) | `/accounting/periods` | web, auth, verified, App\Http\Middleware\EnsurePermission:accounting.periods.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Accounting/Reports](../../backend/resources/js/Pages/Accounting/Reports.tsx) | `/accounting/reports` | web, auth, verified, App\Http\Middleware\EnsurePermission:accounting.reports.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Admin/AuditLogs](../../backend/resources/js/Pages/Admin/AuditLogs.tsx) | `/audit-logs` | web, auth, verified, App\Http\Middleware\EnsurePermission:audit.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Admin/Pos](../../backend/resources/js/Pages/Admin/Pos.tsx) | `/pos-integration` | web, auth, verified, App\Http\Middleware\EnsurePermission:pos.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Admin/PosReconciliation](../../backend/resources/js/Pages/Admin/PosReconciliation.tsx) | `/pos-integration/{instance}/reconciliation` | web, auth, verified, App\Http\Middleware\EnsurePermission:pos.reconcile, Illuminate\Routing\Middleware\ThrottleRequests:30,1 | Chrome รายละเอียดและช่วงวัน Light/Dark 320/390/1440 |
| [Admin/Roles](../../backend/resources/js/Pages/Admin/Roles.tsx) | `/roles` | web, auth, verified, App\Http\Middleware\EnsurePermission:roles.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Admin/Users](../../backend/resources/js/Pages/Admin/Users.tsx) | `/users` | web, auth, verified, App\Http\Middleware\EnsurePermission:users.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Auth/AcceptInvite](../../backend/resources/js/Pages/Auth/AcceptInvite.tsx) | `/invites/{user}/{token}` | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Auth/ConfirmPassword](../../backend/resources/js/Pages/Auth/ConfirmPassword.tsx) | `/confirm-password` | web, auth | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Auth/ForgotPassword](../../backend/resources/js/Pages/Auth/ForgotPassword.tsx) | `/forgot-password` | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Auth/Login](../../backend/resources/js/Pages/Auth/Login.tsx) | `/login` | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Auth/Register](../../backend/resources/js/Pages/Auth/Register.tsx) | `/register` | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Auth/ResetPassword](../../backend/resources/js/Pages/Auth/ResetPassword.tsx) | `/reset-password/{token}` | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Auth/TwoFactorChallenge](../../backend/resources/js/Pages/Auth/TwoFactorChallenge.tsx) | `/two-factor-challenge` | web, Illuminate\Auth\Middleware\RedirectIfAuthenticated | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Auth/TwoFactorSetup](../../backend/resources/js/Pages/Auth/TwoFactorSetup.tsx) | `/two-factor/setup` | web, auth, Illuminate\Auth\Middleware\RequirePassword | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Auth/VerifyEmail](../../backend/resources/js/Pages/Auth/VerifyEmail.tsx) | `/verify-email` | web, auth | Chrome Auth Light/Dark รวมหน้าสมัคร กู้รหัส เชิญ ยืนยันอีเมล และ 2FA |
| [Dashboard](../../backend/resources/js/Pages/Dashboard.tsx) | `/dashboard`<br>`/delivery-dashboard`<br>`/executive-dashboard`<br>`/finance-dashboard` | web, auth, verified, App\Http\Middleware\EnsurePermission:dashboard.view, App\Http\Middleware\EnsurePermission:expenses.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Delivery/MyWorkDashboard](../../backend/resources/js/Pages/Delivery/MyWorkDashboard.tsx) | `/my-work` | web, auth, verified, App\Http\Middleware\EnsurePermission:tasks.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Delivery/Projects](../../backend/resources/js/Pages/Delivery/Projects.tsx) | `/projects` | web, auth, verified, App\Http\Middleware\EnsurePermission:projects.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Delivery/Tasks](../../backend/resources/js/Pages/Delivery/Tasks.tsx) | `/tasks` | web, auth, verified, App\Http\Middleware\EnsurePermission:tasks.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Documents/Index](../../backend/resources/js/Pages/Documents/Index.tsx) | `/documents` | web, auth, verified, App\Http\Middleware\EnsurePermission:documents.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/BankAccounts](../../backend/resources/js/Pages/Finance/BankAccounts.tsx) | `/bank-accounts` | web, auth, verified, App\Http\Middleware\EnsurePermission:treasury.accounts.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/BankStatements](../../backend/resources/js/Pages/Finance/BankStatements.tsx) | `/bank-statements` | web, auth, verified, App\Http\Middleware\EnsurePermission:treasury.reconciliation.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/CashFlow](../../backend/resources/js/Pages/Finance/CashFlow.tsx) | `/finance/cash-flow` | web, auth, verified, App\Http\Middleware\EnsurePermission:cash_flow.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/Cheques](../../backend/resources/js/Pages/Finance/Cheques.tsx) | `/cheques` | web, auth, verified, App\Http\Middleware\EnsurePermission:cheques.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/CommercialDocuments](../../backend/resources/js/Pages/Finance/CommercialDocuments.tsx) | `/commercial-documents` | web, auth, verified, App\Http\Middleware\EnsurePermission:billing_notes.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/Currencies](../../backend/resources/js/Pages/Finance/Currencies.tsx) | `/currencies` | web, auth, verified, App\Http\Middleware\EnsurePermission:fx.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/ETax](../../backend/resources/js/Pages/Finance/ETax.tsx) | `/e-tax` | web, auth, verified, App\Http\Middleware\EnsurePermission:e_tax.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/Expenses](../../backend/resources/js/Pages/Finance/Expenses.tsx) | `/expenses` | web, auth, verified, App\Http\Middleware\EnsurePermission:expenses.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/FixedAssets](../../backend/resources/js/Pages/Finance/FixedAssets.tsx) | `/fixed-assets` | web, auth, verified, App\Http\Middleware\EnsurePermission:fixed_assets.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/GoodsReceipts](../../backend/resources/js/Pages/Finance/GoodsReceipts.tsx) | `/goods-receipts` | web, auth, verified, App\Http\Middleware\EnsurePermission:inventory.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/InventoryOperations](../../backend/resources/js/Pages/Finance/InventoryOperations.tsx) | `/inventory-operations` | web, auth, verified, App\Http\Middleware\EnsurePermission:inventory.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/Invoices](../../backend/resources/js/Pages/Finance/Invoices.tsx) | `/invoices` | web, auth, verified, App\Http\Middleware\EnsurePermission:invoices.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/OcrIndex](../../backend/resources/js/Pages/Finance/OcrIndex.tsx) | `/finance/ocr` | web, auth, verified, App\Http\Middleware\EnsurePermission:ocr.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/OcrReviewer](../../backend/resources/js/Pages/Finance/OcrReviewer.tsx) | `/finance/ocr/{extraction}` | web, auth, verified, App\Http\Middleware\EnsurePermission:ocr.view | Chrome OCR รายละเอียด Light/Dark 320/1440 |
| [Finance/Payments](../../backend/resources/js/Pages/Finance/Payments.tsx) | `/payments` | web, auth, verified, App\Http\Middleware\EnsurePermission:payments.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/Payroll](../../backend/resources/js/Pages/Finance/Payroll.tsx) | `/payroll` | web, auth, verified, App\Http\Middleware\EnsurePermission:payroll.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/PettyCash](../../backend/resources/js/Pages/Finance/PettyCash.tsx) | `/petty-cash` | web, auth, verified, App\Http\Middleware\EnsurePermission:petty_cash.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/ProcurementDashboard](../../backend/resources/js/Pages/Finance/ProcurementDashboard.tsx) | `/procurement-dashboard` | web, auth, verified | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/Products](../../backend/resources/js/Pages/Finance/Products.tsx) | `/products` | web, auth, verified, App\Http\Middleware\EnsurePermission:products.manage | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/PurchaseOrders](../../backend/resources/js/Pages/Finance/PurchaseOrders.tsx) | `/purchase-orders` | web, auth, verified, App\Http\Middleware\EnsurePermission:purchase_orders.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/Quotations](../../backend/resources/js/Pages/Finance/Quotations.tsx) | `/quotations` | web, auth, verified, App\Http\Middleware\EnsurePermission:quotations.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/Suppliers](../../backend/resources/js/Pages/Finance/Suppliers.tsx) | `/suppliers` | web, auth, verified, App\Http\Middleware\EnsurePermission:suppliers.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/TaxReports](../../backend/resources/js/Pages/Finance/TaxReports.tsx) | `/tax-reports` | web, auth, verified, App\Http\Middleware\EnsurePermission:tax_reports.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/TreasuryReports](../../backend/resources/js/Pages/Finance/TreasuryReports.tsx) | `/treasury-reports` | web, auth, verified, App\Http\Middleware\EnsurePermission:treasury.reports.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Finance/VendorPayments](../../backend/resources/js/Pages/Finance/VendorPayments.tsx) | `/vendor-payments` | web, auth, verified, App\Http\Middleware\EnsurePermission:expenses.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Hr/AttendanceImport](../../backend/resources/js/Pages/Hr/AttendanceImport.tsx) | `/hr/attendance-import` | web, auth, verified, App\Http\Middleware\EnsurePermission:hr.attendance.import | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Hr/Dashboard](../../backend/resources/js/Pages/Hr/Dashboard.tsx) | `/hr-dashboard` | web, auth, verified | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Hr/Employees](../../backend/resources/js/Pages/Hr/Employees.tsx) | `/hr/employees` | web, auth, verified, App\Http\Middleware\EnsurePermission:hr.employees.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Hr/Index](../../backend/resources/js/Pages/Hr/Index.tsx) | `/hr` | web, auth, verified, App\Http\Middleware\EnsurePermission:hr.self.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Hr/Roster](../../backend/resources/js/Pages/Hr/Roster.tsx) | `/hr/roster` | web, auth, verified, App\Http\Middleware\EnsurePermission:hr.shifts.manage | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Manufacturing/BillOfMaterials](../../backend/resources/js/Pages/Manufacturing/BillOfMaterials.tsx) | `/manufacturing/boms` | web, auth, verified, App\Http\Middleware\EnsurePermission:manufacturing.bom.view | Chrome รายการและ dialog BOM/Work Order Light/Dark |
| [Manufacturing/WorkOrders](../../backend/resources/js/Pages/Manufacturing/WorkOrders.tsx) | `/manufacturing/work-orders` | web, auth, verified, App\Http\Middleware\EnsurePermission:manufacturing.work_orders.view | Chrome รายการและ dialog BOM/Work Order Light/Dark |
| [Portal/Dashboard](../../backend/resources/js/Pages/Portal/Dashboard.tsx) | `/portal` | web, Illuminate\Routing\Middleware\ThrottleRequests:60,1 | Chrome Customer และ Supplier Portal Light/Dark 390/1440 |
| [Portal/SignIn](../../backend/resources/js/Pages/Portal/SignIn.tsx) | `/portal/sign-in` | web | Chrome Customer และ Supplier Portal Light/Dark 390/1440 |
| [Profile/Edit](../../backend/resources/js/Pages/Profile/Edit.tsx) | `/profile` | web, auth, verified | Chrome Profile และ dialog Light/Dark 320/768/1440 |
| [Sales/Customers](../../backend/resources/js/Pages/Sales/Customers.tsx) | `/customers` | web, auth, verified, App\Http\Middleware\EnsurePermission:customers.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Sales/Dashboard](../../backend/resources/js/Pages/Sales/Dashboard.tsx) | `/sales-dashboard` | web, auth, verified, App\Http\Middleware\EnsurePermission:sales.dashboard.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Sales/Deals](../../backend/resources/js/Pages/Sales/Deals.tsx) | `/deals` | web, auth, verified, App\Http\Middleware\EnsurePermission:deals.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Settings/AiConfiguration](../../backend/resources/js/Pages/Settings/AiConfiguration.tsx) | `/settings/ai-configuration` | web, auth, verified, App\Http\Middleware\EnsurePermission:ai_settings.manage | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Settings/ApiAccess](../../backend/resources/js/Pages/Settings/ApiAccess.tsx) | `/settings/api-access` | web, auth, verified, App\Http\Middleware\EnsurePermission:api_keys.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Settings/ApiWebhooks](../../backend/resources/js/Pages/Settings/ApiWebhooks.tsx) | `/settings/api-access/webhooks` | web, auth, verified, App\Http\Middleware\EnsurePermission:api_keys.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Settings/NotificationPreferences](../../backend/resources/js/Pages/Settings/NotificationPreferences.tsx) | `/settings/notifications` | web, auth, verified | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Settings/Organization](../../backend/resources/js/Pages/Settings/Organization.tsx) | `/settings/organization` | web, auth, verified, App\Http\Middleware\EnsurePermission:settings.organization.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Settings/OrganizationStructure](../../backend/resources/js/Pages/Settings/OrganizationStructure.tsx) | `/settings/organization-structure` | web, auth, verified, App\Http\Middleware\EnsurePermission:settings.structure.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Settings/PaymentGateway](../../backend/resources/js/Pages/Settings/PaymentGateway.tsx) | `/settings/payment-gateway` | web, auth, verified, App\Http\Middleware\EnsurePermission:settings.organization.view | Chrome หน้ารายการ Light/Dark 390/1440 |
| [Workflows/Index](../../backend/resources/js/Pages/Workflows/Index.tsx) | `/workflows` | web, auth, verified, App\Http\Middleware\EnsurePermission:workflows.view | Chrome หน้ารายการ Light/Dark 390/1440 |

## Component, partial และไฟล์นอก route

แยก component ที่มีผู้เรียกใช้จากหน้าทดสอบและไฟล์เดิมที่ไม่มี route

| ไฟล์ | ชนิด / ผู้เรียกใช้ |
| --- | --- |
| [Admin/PosSetup.tsx](../../backend/resources/js/Pages/Admin/PosSetup.tsx) | Component / partial ของ [Pos.tsx](../../backend/resources/js/Pages/Admin/Pos.tsx) |
| [Admin/RolePermissionsDialog.tsx](../../backend/resources/js/Pages/Admin/RolePermissionsDialog.tsx) | Component / partial ของ [Roles.tsx](../../backend/resources/js/Pages/Admin/Roles.tsx) |
| [Admin/UserDialog.tsx](../../backend/resources/js/Pages/Admin/UserDialog.tsx) | Component / partial ของ [Users.tsx](../../backend/resources/js/Pages/Admin/Users.tsx) |
| [Delivery/ProjectEditorDialog.tsx](../../backend/resources/js/Pages/Delivery/ProjectEditorDialog.tsx) | Component / partial ของ [Projects.tsx](../../backend/resources/js/Pages/Delivery/Projects.tsx) |
| [Delivery/ProjectMembersDialog.tsx](../../backend/resources/js/Pages/Delivery/ProjectMembersDialog.tsx) | Component / partial ของ [Projects.tsx](../../backend/resources/js/Pages/Delivery/Projects.tsx) |
| [Delivery/TaskDetailDialogs.tsx](../../backend/resources/js/Pages/Delivery/TaskDetailDialogs.tsx) | Component / partial ของ [Tasks.tsx](../../backend/resources/js/Pages/Delivery/Tasks.tsx) |
| [Delivery/TaskEditorDialog.tsx](../../backend/resources/js/Pages/Delivery/TaskEditorDialog.tsx) | Component / partial ของ [Tasks.tsx](../../backend/resources/js/Pages/Delivery/Tasks.tsx) |
| [Documents/DocumentDialogs.tsx](../../backend/resources/js/Pages/Documents/DocumentDialogs.tsx) | Component / partial ของ [Index.tsx](../../backend/resources/js/Pages/Documents/Index.tsx) |
| [Finance/DeliveryOrderDialog.tsx](../../backend/resources/js/Pages/Finance/DeliveryOrderDialog.tsx) | Component / partial ของ [CommercialDocuments.tsx](../../backend/resources/js/Pages/Finance/CommercialDocuments.tsx) |
| [Finance/ExpenseEditorDialog.tsx](../../backend/resources/js/Pages/Finance/ExpenseEditorDialog.tsx) | Component / partial ของ [Expenses.tsx](../../backend/resources/js/Pages/Finance/Expenses.tsx) |
| [Finance/FixedAssetAttachmentDialog.tsx](../../backend/resources/js/Pages/Finance/FixedAssetAttachmentDialog.tsx) | Component / partial ของ [FixedAssets.tsx](../../backend/resources/js/Pages/Finance/FixedAssets.tsx) |
| [Finance/FixedAssetCreateDialog.tsx](../../backend/resources/js/Pages/Finance/FixedAssetCreateDialog.tsx) | Component / partial ของ [FixedAssets.tsx](../../backend/resources/js/Pages/Finance/FixedAssets.tsx) |
| [Finance/InventoryDialogs.tsx](../../backend/resources/js/Pages/Finance/InventoryDialogs.tsx) | Component / partial ของ [InventoryOperations.tsx](../../backend/resources/js/Pages/Finance/InventoryOperations.tsx) |
| [Finance/PayrollDialogs.tsx](../../backend/resources/js/Pages/Finance/PayrollDialogs.tsx) | Component / partial ของ [Payroll.tsx](../../backend/resources/js/Pages/Finance/Payroll.tsx) |
| [Finance/PettyCashDialog.tsx](../../backend/resources/js/Pages/Finance/PettyCashDialog.tsx) | Component / partial ของ [PettyCash.tsx](../../backend/resources/js/Pages/Finance/PettyCash.tsx) |
| [Finance/ReceiveGoodsDialog.tsx](../../backend/resources/js/Pages/Finance/ReceiveGoodsDialog.tsx) | Component / partial ของ [GoodsReceipts.tsx](../../backend/resources/js/Pages/Finance/GoodsReceipts.tsx) |
| [Finance/StockMovementDialog.tsx](../../backend/resources/js/Pages/Finance/StockMovementDialog.tsx) | Component / partial ของ [GoodsReceipts.tsx](../../backend/resources/js/Pages/Finance/GoodsReceipts.tsx) |
| [Finance/VoucherAttachmentDialog.tsx](../../backend/resources/js/Pages/Finance/VoucherAttachmentDialog.tsx) | Component / partial ของ [CommercialDocuments.tsx](../../backend/resources/js/Pages/Finance/CommercialDocuments.tsx) |
| [Hr/HrDialogs.tsx](../../backend/resources/js/Pages/Hr/HrDialogs.tsx) | Component / partial ของ [Index.tsx](../../backend/resources/js/Pages/Hr/Index.tsx) |
| [Hr/Partials/EmployeeImportDialog.tsx](../../backend/resources/js/Pages/Hr/Partials/EmployeeImportDialog.tsx) | Component / partial ของ [Employees.tsx](../../backend/resources/js/Pages/Hr/Employees.tsx) |
| [Manufacturing/Icons.tsx](../../backend/resources/js/Pages/Manufacturing/Icons.tsx) | Component / partial ของ [BillOfMaterials.tsx](../../backend/resources/js/Pages/Manufacturing/BillOfMaterials.tsx), [WorkOrders.tsx](../../backend/resources/js/Pages/Manufacturing/WorkOrders.tsx) |
| [Portal/VendorBillDialog.tsx](../../backend/resources/js/Pages/Portal/VendorBillDialog.tsx) | Component / partial ของ [Dashboard.tsx](../../backend/resources/js/Pages/Portal/Dashboard.tsx) |
| [Profile/Partials/DeleteUserForm.tsx](../../backend/resources/js/Pages/Profile/Partials/DeleteUserForm.tsx) | Component / partial ของ [Edit.tsx](../../backend/resources/js/Pages/Profile/Edit.tsx) |
| [Profile/Partials/UpdatePasswordForm.tsx](../../backend/resources/js/Pages/Profile/Partials/UpdatePasswordForm.tsx) | Component / partial ของ [Edit.tsx](../../backend/resources/js/Pages/Profile/Edit.tsx) |
| [Profile/Partials/UpdateProfileInformationForm.tsx](../../backend/resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.tsx) | Component / partial ของ [Edit.tsx](../../backend/resources/js/Pages/Profile/Edit.tsx) |
| [Sales/ActivityDialog.tsx](../../backend/resources/js/Pages/Sales/ActivityDialog.tsx) | Component / partial ของ [Deals.tsx](../../backend/resources/js/Pages/Sales/Deals.tsx) |
| [Sales/ContactDialog.tsx](../../backend/resources/js/Pages/Sales/ContactDialog.tsx) | Component / partial ของ [Customers.tsx](../../backend/resources/js/Pages/Sales/Customers.tsx), [Deals.tsx](../../backend/resources/js/Pages/Sales/Deals.tsx) |
| [Sales/CustomerDialog.tsx](../../backend/resources/js/Pages/Sales/CustomerDialog.tsx) | Component / partial ของ [Customers.tsx](../../backend/resources/js/Pages/Sales/Customers.tsx) |
| [Sales/DealDialog.tsx](../../backend/resources/js/Pages/Sales/DealDialog.tsx) | Component / partial ของ [Deals.tsx](../../backend/resources/js/Pages/Sales/Deals.tsx) |
| [Sales/DealDialogFields.tsx](../../backend/resources/js/Pages/Sales/DealDialogFields.tsx) | ไม่พบ route หรือ import โดยตรงในโค้ดปัจจุบัน |
| [Settings/OrgStructureDialogs.tsx](../../backend/resources/js/Pages/Settings/OrgStructureDialogs.tsx) | Component / partial ของ [OrganizationStructure.tsx](../../backend/resources/js/Pages/Settings/OrganizationStructure.tsx) |
| [Testing/DialogStandard.tsx](../../backend/resources/js/Pages/Testing/DialogStandard.tsx) | หน้าทดสอบแยกจากเมนูใช้งาน |
| [Welcome.tsx](../../backend/resources/js/Pages/Welcome.tsx) | ไม่พบ route หรือ import โดยตรงในโค้ดปัจจุบัน |
| [Workflows/WorkflowDialogs.tsx](../../backend/resources/js/Pages/Workflows/WorkflowDialogs.tsx) | Component / partial ของ [Index.tsx](../../backend/resources/js/Pages/Workflows/Index.tsx) |

## Blade และหน้า PHP แยก

| ไฟล์ | ประเภท |
| --- | --- |
| [backend/resources/views/app.blade.php](../../backend/resources/views/app.blade.php) | หน้าใช้งาน / entry |
| [backend/resources/views/auth/recovery-codes.blade.php](../../backend/resources/views/auth/recovery-codes.blade.php) | หน้าใช้งาน / entry |
| [backend/resources/views/documents/commercial-print.blade.php](../../backend/resources/views/documents/commercial-print.blade.php) | เอกสาร / พิมพ์ |
| [backend/resources/views/documents/official-print.blade.php](../../backend/resources/views/documents/official-print.blade.php) | เอกสาร / พิมพ์ |
| [backend/resources/views/documents/paper-colors.blade.php](../../backend/resources/views/documents/paper-colors.blade.php) | Partial / layout |
| [backend/resources/views/documents/payment-qr.blade.php](../../backend/resources/views/documents/payment-qr.blade.php) | หน้าใช้งาน / entry |
| [backend/resources/views/documents/pdf-fonts.blade.php](../../backend/resources/views/documents/pdf-fonts.blade.php) | เอกสาร / พิมพ์ |
| [backend/resources/views/documents/pdf-layout.blade.php](../../backend/resources/views/documents/pdf-layout.blade.php) | เอกสาร / พิมพ์ |
| [backend/resources/views/documents/withholding-certificate.blade.php](../../backend/resources/views/documents/withholding-certificate.blade.php) | เอกสาร / พิมพ์ |
| [backend/resources/views/emails/erp-notification.blade.php](../../backend/resources/views/emails/erp-notification.blade.php) | หน้าใช้งาน / entry |
| [backend/resources/views/errors/minimal.blade.php](../../backend/resources/views/errors/minimal.blade.php) | HTTP error |
| [backend/resources/views/layouts/standalone-theme.blade.php](../../backend/resources/views/layouts/standalone-theme.blade.php) | Partial / layout |
| [backend/resources/views/payroll/payslip.blade.php](../../backend/resources/views/payroll/payslip.blade.php) | เอกสาร / พิมพ์ |
| [backend/resources/views/pos-membership.blade.php](../../backend/resources/views/pos-membership.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/configuration.blade.php](../../pos/resources/views/configuration.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/deliveries.blade.php](../../pos/resources/views/deliveries.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/delivery-slip.blade.php](../../pos/resources/views/delivery-slip.blade.php) | เอกสาร / พิมพ์ |
| [pos/resources/views/errors/minimal.blade.php](../../pos/resources/views/errors/minimal.blade.php) | HTTP error |
| [pos/resources/views/membership.blade.php](../../pos/resources/views/membership.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/menu.blade.php](../../pos/resources/views/menu.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/offline.blade.php](../../pos/resources/views/offline.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/partials/paper.blade.php](../../pos/resources/views/partials/paper.blade.php) | Partial / layout |
| [pos/resources/views/partials/theme-preference.blade.php](../../pos/resources/views/partials/theme-preference.blade.php) | Partial / layout |
| [pos/resources/views/partials/workspace-theme.blade.php](../../pos/resources/views/partials/workspace-theme.blade.php) | Partial / layout |
| [pos/resources/views/patients.blade.php](../../pos/resources/views/patients.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/pos.blade.php](../../pos/resources/views/pos.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/setup.blade.php](../../pos/resources/views/setup.blade.php) | หน้าใช้งาน / entry |
| [pos/resources/views/tax-document.blade.php](../../pos/resources/views/tax-document.blade.php) | เอกสาร / พิมพ์ |
| [backend/public/setup/index.php](../../backend/public/setup/index.php) | ERP Installer / requirements |
| [setup/system-requirements.php](../../setup/system-requirements.php) | ERP Installer / requirements |

## ขอบเขตการตรวจรับ

การพบ route และผ่าน build ยืนยันจุดเข้าและการ compile หน้าที่มีข้อมูลรายละเอียด สิทธิ์เฉพาะ สถานะผิดพลาด และการใช้คีย์บอร์ดต้องอ้างอิงผล workflow ของหน้านั้น [งานตรวจรับที่ยังเปิด](../../checklist.md#checklist-ปรับ-layout-ทั้งระบบ--local-complete)

หน้าตัวอย่างในสภาพแวดล้อมทดสอบและ partial ยังคงแยกจากเมนูใช้งานจริง
