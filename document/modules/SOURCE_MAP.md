# Source map — Controller และ Service ไปยังโมดูล

Snapshot 2026-10-24 · HEAD `b03c393c19762a09862b4a7aab688f1fe47dcdb3` รวม working tree

รายการนี้ช่วยค้นไฟล์ เป็นการจัดหมวดตามความรับผิดชอบ ไม่ใช่ automatic call graph หรือคำยืนยันว่าอ่านทุก method ครบ บางไฟล์ใช้ร่วมหลายโมดูล ให้ตาม dependencies ในบทปลายทางด้วย

## Controllers (80 files)

| Source | บทอ้างอิงหลัก |
| --- | --- |
| [Admin/AuditLogController.php](../../backend/app/Http/Controllers/Admin/AuditLogController.php) | [03](03_administration_audit.md) |
| [Admin/DashboardController.php](../../backend/app/Http/Controllers/Admin/DashboardController.php) | [22](22_dashboards_reporting.md) |
| [Admin/RoleController.php](../../backend/app/Http/Controllers/Admin/RoleController.php) | [03](03_administration_audit.md) |
| [Admin/UserController.php](../../backend/app/Http/Controllers/Admin/UserController.php) | [03](03_administration_audit.md) |
| [Api/ETaxWebhookController.php](../../backend/app/Http/Controllers/Api/ETaxWebhookController.php) | [11](11_tax_etax.md) |
| [Api/ExternalApprovalController.php](../../backend/app/Http/Controllers/Api/ExternalApprovalController.php) | [18](18_external_api_webhooks.md) |
| [Api/ExternalBusinessController.php](../../backend/app/Http/Controllers/Api/ExternalBusinessController.php) | [18](18_external_api_webhooks.md) |
| [Api/ExternalCatalogController.php](../../backend/app/Http/Controllers/Api/ExternalCatalogController.php) | [18](18_external_api_webhooks.md) |
| [Api/ExternalDocumentController.php](../../backend/app/Http/Controllers/Api/ExternalDocumentController.php) | [18](18_external_api_webhooks.md) |
| [Api/ExternalEmployeeController.php](../../backend/app/Http/Controllers/Api/ExternalEmployeeController.php) | [18](18_external_api_webhooks.md) |
| [Api/ExternalHrSummaryController.php](../../backend/app/Http/Controllers/Api/ExternalHrSummaryController.php) | [18](18_external_api_webhooks.md) |
| [Api/ExternalInventoryController.php](../../backend/app/Http/Controllers/Api/ExternalInventoryController.php) | [18](18_external_api_webhooks.md) |
| [Api/ExternalManufacturingController.php](../../backend/app/Http/Controllers/Api/ExternalManufacturingController.php) | [18](18_external_api_webhooks.md) |
| [Api/ExternalProcurementSummaryController.php](../../backend/app/Http/Controllers/Api/ExternalProcurementSummaryController.php) | [18](18_external_api_webhooks.md) |
| [Api/SummaryController.php](../../backend/app/Http/Controllers/Api/SummaryController.php) | [18](18_external_api_webhooks.md) |
| [Auth/AcceptInviteController.php](../../backend/app/Http/Controllers/Auth/AcceptInviteController.php) | [02](02_authentication_security.md) |
| [Auth/AuthenticatedSessionController.php](../../backend/app/Http/Controllers/Auth/AuthenticatedSessionController.php) | [02](02_authentication_security.md) |
| [Auth/ConfirmablePasswordController.php](../../backend/app/Http/Controllers/Auth/ConfirmablePasswordController.php) | [02](02_authentication_security.md) |
| [Auth/EmailVerificationNotificationController.php](../../backend/app/Http/Controllers/Auth/EmailVerificationNotificationController.php) | [02](02_authentication_security.md) |
| [Auth/EmailVerificationPromptController.php](../../backend/app/Http/Controllers/Auth/EmailVerificationPromptController.php) | [02](02_authentication_security.md) |
| [Auth/NewPasswordController.php](../../backend/app/Http/Controllers/Auth/NewPasswordController.php) | [02](02_authentication_security.md) |
| [Auth/PasswordController.php](../../backend/app/Http/Controllers/Auth/PasswordController.php) | [02](02_authentication_security.md) |
| [Auth/PasswordResetLinkController.php](../../backend/app/Http/Controllers/Auth/PasswordResetLinkController.php) | [02](02_authentication_security.md) |
| [Auth/RegisteredUserController.php](../../backend/app/Http/Controllers/Auth/RegisteredUserController.php) | [02](02_authentication_security.md) |
| [Auth/TwoFactorController.php](../../backend/app/Http/Controllers/Auth/TwoFactorController.php) | [02](02_authentication_security.md) |
| [Auth/VerifyEmailController.php](../../backend/app/Http/Controllers/Auth/VerifyEmailController.php) | [02](02_authentication_security.md) |
| [Controller.php](../../backend/app/Http/Controllers/Controller.php) | [00](00_architecture.md) |
| [Delivery/MyWorkDashboardController.php](../../backend/app/Http/Controllers/Delivery/MyWorkDashboardController.php) | [22](22_dashboards_reporting.md) |
| [Delivery/ProjectController.php](../../backend/app/Http/Controllers/Delivery/ProjectController.php) | [08](08_projects_tasks.md) |
| [Delivery/TaskController.php](../../backend/app/Http/Controllers/Delivery/TaskController.php) | [08](08_projects_tasks.md) |
| [DocumentController.php](../../backend/app/Http/Controllers/DocumentController.php) | [14](14_documents_files.md) |
| [FileController.php](../../backend/app/Http/Controllers/FileController.php) | [14](14_documents_files.md) |
| [Finance/BankAccountController.php](../../backend/app/Http/Controllers/Finance/BankAccountController.php) | [10](10_treasury_banking.md) |
| [Finance/BankStatementController.php](../../backend/app/Http/Controllers/Finance/BankStatementController.php) | [10](10_treasury_banking.md) |
| [Finance/BankTransferController.php](../../backend/app/Http/Controllers/Finance/BankTransferController.php) | [10](10_treasury_banking.md) |
| [Finance/CashFlowController.php](../../backend/app/Http/Controllers/Finance/CashFlowController.php) | [23](23_cash_flow_forecasting.md) |
| [Finance/CommercialDocumentController.php](../../backend/app/Http/Controllers/Finance/CommercialDocumentController.php) | [05](05_quotations_commercial_documents.md) |
| [Finance/CurrencyController.php](../../backend/app/Http/Controllers/Finance/CurrencyController.php) | [09](09_accounting_fx_assets.md) |
| [Finance/ETaxController.php](../../backend/app/Http/Controllers/Finance/ETaxController.php) | [11](11_tax_etax.md) |
| [Finance/ExpenseController.php](../../backend/app/Http/Controllers/Finance/ExpenseController.php) | [06](06_billing_payments_expenses.md) |
| [Finance/FixedAssetController.php](../../backend/app/Http/Controllers/Finance/FixedAssetController.php) | [09](09_accounting_fx_assets.md) |
| [Finance/GeneralLedgerController.php](../../backend/app/Http/Controllers/Finance/GeneralLedgerController.php) | [09](09_accounting_fx_assets.md) |
| [Finance/GoodsReceiptController.php](../../backend/app/Http/Controllers/Finance/GoodsReceiptController.php) | [07](07_procurement_inventory.md) |
| [Finance/InventoryOperationsController.php](../../backend/app/Http/Controllers/Finance/InventoryOperationsController.php) | [07](07_procurement_inventory.md) |
| [Finance/InvoiceController.php](../../backend/app/Http/Controllers/Finance/InvoiceController.php) | [06](06_billing_payments_expenses.md) |
| [Finance/OcrDocumentController.php](../../backend/app/Http/Controllers/Finance/OcrDocumentController.php) | [19](19_ai_ocr.md) |
| [Finance/PaymentController.php](../../backend/app/Http/Controllers/Finance/PaymentController.php) | [06](06_billing_payments_expenses.md) |
| [Finance/PayrollController.php](../../backend/app/Http/Controllers/Finance/PayrollController.php) | [12](12_hr_attendance_payroll.md) |
| [Finance/ProcurementDashboardController.php](../../backend/app/Http/Controllers/Finance/ProcurementDashboardController.php) | [22](22_dashboards_reporting.md) |
| [Finance/ProductController.php](../../backend/app/Http/Controllers/Finance/ProductController.php) | [07](07_procurement_inventory.md) |
| [Finance/PurchaseOrderController.php](../../backend/app/Http/Controllers/Finance/PurchaseOrderController.php) | [07](07_procurement_inventory.md) |
| [Finance/QuotationController.php](../../backend/app/Http/Controllers/Finance/QuotationController.php) | [05](05_quotations_commercial_documents.md) |
| [Finance/SupplierController.php](../../backend/app/Http/Controllers/Finance/SupplierController.php) | [07](07_procurement_inventory.md) |
| [Finance/TaxReportController.php](../../backend/app/Http/Controllers/Finance/TaxReportController.php) | [11](11_tax_etax.md) |
| [Finance/TreasuryOperationsController.php](../../backend/app/Http/Controllers/Finance/TreasuryOperationsController.php) | [10](10_treasury_banking.md) |
| [Finance/TreasuryReportController.php](../../backend/app/Http/Controllers/Finance/TreasuryReportController.php) | [10](10_treasury_banking.md) |
| [Finance/VendorPaymentController.php](../../backend/app/Http/Controllers/Finance/VendorPaymentController.php) | [06](06_billing_payments_expenses.md) |
| [GatewayController.php](../../backend/app/Http/Controllers/GatewayController.php) | [17](17_portal_gateway.md) |
| [HrController.php](../../backend/app/Http/Controllers/HrController.php) | [12](12_hr_attendance_payroll.md) |
| [HrDashboardController.php](../../backend/app/Http/Controllers/HrDashboardController.php) | [22](22_dashboards_reporting.md) |
| [Hr/AttendanceImportController.php](../../backend/app/Http/Controllers/Hr/AttendanceImportController.php) | [12](12_hr_attendance_payroll.md) |
| [Hr/EmployeeController.php](../../backend/app/Http/Controllers/Hr/EmployeeController.php) | [12](12_hr_attendance_payroll.md) |
| [Hr/PositionController.php](../../backend/app/Http/Controllers/Hr/PositionController.php) | [12](12_hr_attendance_payroll.md) |
| [Hr/RosterController.php](../../backend/app/Http/Controllers/Hr/RosterController.php) | [12](12_hr_attendance_payroll.md) |
| [Manufacturing/BillOfMaterialsController.php](../../backend/app/Http/Controllers/Manufacturing/BillOfMaterialsController.php) | [13](13_manufacturing.md) |
| [Manufacturing/WorkOrderController.php](../../backend/app/Http/Controllers/Manufacturing/WorkOrderController.php) | [13](13_manufacturing.md) |
| [PortalController.php](../../backend/app/Http/Controllers/PortalController.php) | [17](17_portal_gateway.md) |
| [ProfileController.php](../../backend/app/Http/Controllers/ProfileController.php) | [20](20_frontend_dialogs.md) |
| [Sales/ActivityController.php](../../backend/app/Http/Controllers/Sales/ActivityController.php) | [04](04_crm_sales.md) |
| [Sales/ContactController.php](../../backend/app/Http/Controllers/Sales/ContactController.php) | [04](04_crm_sales.md) |
| [Sales/CustomerController.php](../../backend/app/Http/Controllers/Sales/CustomerController.php) | [04](04_crm_sales.md) |
| [Sales/DealController.php](../../backend/app/Http/Controllers/Sales/DealController.php) | [04](04_crm_sales.md) |
| [Sales/SalesDashboardController.php](../../backend/app/Http/Controllers/Sales/SalesDashboardController.php) | [22](22_dashboards_reporting.md) |
| [Settings/AiConfigurationController.php](../../backend/app/Http/Controllers/Settings/AiConfigurationController.php) | [19](19_ai_ocr.md) |
| [Settings/IntegrationApiKeyController.php](../../backend/app/Http/Controllers/Settings/IntegrationApiKeyController.php) | [18](18_external_api_webhooks.md) |
| [Settings/IntegrationWebhookController.php](../../backend/app/Http/Controllers/Settings/IntegrationWebhookController.php) | [18](18_external_api_webhooks.md) |
| [Settings/NotificationPreferenceController.php](../../backend/app/Http/Controllers/Settings/NotificationPreferenceController.php) | [16](16_notifications_automation.md) |
| [Settings/OrganizationSettingsController.php](../../backend/app/Http/Controllers/Settings/OrganizationSettingsController.php) | [01](01_foundation_organization.md) |
| [Settings/OrganizationStructureController.php](../../backend/app/Http/Controllers/Settings/OrganizationStructureController.php) | [01](01_foundation_organization.md) |
| [WorkflowController.php](../../backend/app/Http/Controllers/WorkflowController.php) | [15](15_workflows_approvals.md) |

## Services (55 files)

| Source | บทอ้างอิงหลัก |
| --- | --- |
| [AccountingPeriodService.php](../../backend/app/Services/AccountingPeriodService.php) | [09](09_accounting_fx_assets.md) |
| [AttendanceReconciliationService.php](../../backend/app/Services/AttendanceReconciliationService.php) | [12](12_hr_attendance_payroll.md) |
| [BahtText.php](../../backend/app/Services/BahtText.php) | [05](05_quotations_commercial_documents.md) |
| [ChartOfAccountProvisioner.php](../../backend/app/Services/ChartOfAccountProvisioner.php) | [09](09_accounting_fx_assets.md) |
| [DocumentParentAccessService.php](../../backend/app/Services/DocumentParentAccessService.php) | [14](14_documents_files.md) |
| [DocumentRetentionService.php](../../backend/app/Services/DocumentRetentionService.php) | [14](14_documents_files.md) |
| [DocumentService.php](../../backend/app/Services/DocumentService.php) | [14](14_documents_files.md) |
| [ETax/DisabledETaxSignatureAdapter.php](../../backend/app/Services/ETax/DisabledETaxSignatureAdapter.php) | [11](11_tax_etax.md) |
| [ETax/DisabledETaxSubmissionAdapter.php](../../backend/app/Services/ETax/DisabledETaxSubmissionAdapter.php) | [11](11_tax_etax.md) |
| [ETax/ETaxDocumentService.php](../../backend/app/Services/ETax/ETaxDocumentService.php) | [11](11_tax_etax.md) |
| [ETax/ETaxProviderGatewayService.php](../../backend/app/Services/ETax/ETaxProviderGatewayService.php) | [11](11_tax_etax.md) |
| [ETax/ETaxSignatureAdapter.php](../../backend/app/Services/ETax/ETaxSignatureAdapter.php) | [11](11_tax_etax.md) |
| [ETax/ETaxSubmissionAdapter.php](../../backend/app/Services/ETax/ETaxSubmissionAdapter.php) | [11](11_tax_etax.md) |
| [ETax/ETaxXmlSignatureService.php](../../backend/app/Services/ETax/ETaxXmlSignatureService.php) | [11](11_tax_etax.md) |
| [ETax/ImmutableETaxDocumentException.php](../../backend/app/Services/ETax/ImmutableETaxDocumentException.php) | [11](11_tax_etax.md) |
| [ETax/MockETaxSubmissionAdapter.php](../../backend/app/Services/ETax/MockETaxSubmissionAdapter.php) | [11](11_tax_etax.md) |
| [ETax/RDPrepExportService.php](../../backend/app/Services/ETax/RDPrepExportService.php) | [11](11_tax_etax.md) |
| [ETax/RdApiSubmissionAdapter.php](../../backend/app/Services/ETax/RdApiSubmissionAdapter.php) | [11](11_tax_etax.md) |
| [ExternalHrSummaryService.php](../../backend/app/Services/ExternalHrSummaryService.php) | [18](18_external_api_webhooks.md) |
| [ExternalSummaryService.php](../../backend/app/Services/ExternalSummaryService.php) | [18](18_external_api_webhooks.md) |
| [ExternalWebhookService.php](../../backend/app/Services/ExternalWebhookService.php) | [18](18_external_api_webhooks.md) |
| [Finance/CashFlowForecastService.php](../../backend/app/Services/Finance/CashFlowForecastService.php) | [23](23_cash_flow_forecasting.md) |
| [FinancialJournalService.php](../../backend/app/Services/FinancialJournalService.php) | [09](09_accounting_fx_assets.md) |
| [FixedAssetService.php](../../backend/app/Services/FixedAssetService.php) | [09](09_accounting_fx_assets.md) |
| [FxRateService.php](../../backend/app/Services/FxRateService.php) | [09](09_accounting_fx_assets.md) |
| [FxRevaluationService.php](../../backend/app/Services/FxRevaluationService.php) | [09](09_accounting_fx_assets.md) |
| [GatewaySettlementService.php](../../backend/app/Services/GatewaySettlementService.php) | [17](17_portal_gateway.md) |
| [HrAttendanceService.php](../../backend/app/Services/HrAttendanceService.php) | [12](12_hr_attendance_payroll.md) |
| [Hr/EmployeeImportService.php](../../backend/app/Services/Hr/EmployeeImportService.php) | [12](12_hr_attendance_payroll.md) |
| [JournalPostingService.php](../../backend/app/Services/JournalPostingService.php) | [09](09_accounting_fx_assets.md) |
| [NotificationOutboxService.php](../../backend/app/Services/NotificationOutboxService.php) | [16](16_notifications_automation.md) |
| [NotificationService.php](../../backend/app/Services/NotificationService.php) | [16](16_notifications_automation.md) |
| [Notifications/LineMessagingDriver.php](../../backend/app/Services/Notifications/LineMessagingDriver.php) | [16](16_notifications_automation.md) |
| [Notifications/NotificationChannelDriver.php](../../backend/app/Services/Notifications/NotificationChannelDriver.php) | [16](16_notifications_automation.md) |
| [Notifications/SlackWebhookDriver.php](../../backend/app/Services/Notifications/SlackWebhookDriver.php) | [16](16_notifications_automation.md) |
| [Notifications/TelegramBotDriver.php](../../backend/app/Services/Notifications/TelegramBotDriver.php) | [16](16_notifications_automation.md) |
| [NumberSequenceService.php](../../backend/app/Services/NumberSequenceService.php) | [01](01_foundation_organization.md) |
| [Ocr/AiConnectionTesterService.php](../../backend/app/Services/Ocr/AiConnectionTesterService.php) | [19](19_ai_ocr.md) |
| [Ocr/MockOcrAdapter.php](../../backend/app/Services/Ocr/MockOcrAdapter.php) | [19](19_ai_ocr.md) |
| [Ocr/OcrProcessingService.php](../../backend/app/Services/Ocr/OcrProcessingService.php) | [19](19_ai_ocr.md) |
| [Ocr/OcrProviderInterface.php](../../backend/app/Services/Ocr/OcrProviderInterface.php) | [19](19_ai_ocr.md) |
| [Ocr/RobustJsonCleaner.php](../../backend/app/Services/Ocr/RobustJsonCleaner.php) | [19](19_ai_ocr.md) |
| [Ocr/ThaiTaxIdValidator.php](../../backend/app/Services/Ocr/ThaiTaxIdValidator.php) | [19](19_ai_ocr.md) |
| [Ocr/UniversalOpenAiVisionAdapter.php](../../backend/app/Services/Ocr/UniversalOpenAiVisionAdapter.php) | [19](19_ai_ocr.md) |
| [OpnEventService.php](../../backend/app/Services/OpnEventService.php) | [17](17_portal_gateway.md) |
| [OrganizationProvisioner.php](../../backend/app/Services/OrganizationProvisioner.php) | [01](01_foundation_organization.md) |
| [PayrollService.php](../../backend/app/Services/PayrollService.php) | [12](12_hr_attendance_payroll.md) |
| [PayslipMailService.php](../../backend/app/Services/PayslipMailService.php) | [12](12_hr_attendance_payroll.md) |
| [PortalAccessService.php](../../backend/app/Services/PortalAccessService.php) | [17](17_portal_gateway.md) |
| [ProductionCostingService.php](../../backend/app/Services/ProductionCostingService.php) | [13](13_manufacturing.md) |
| [PromptPayQrService.php](../../backend/app/Services/PromptPayQrService.php) | [17](17_portal_gateway.md) |
| [TotpService.php](../../backend/app/Services/TotpService.php) | [02](02_authentication_security.md) |
| [TwoFactorPolicyService.php](../../backend/app/Services/TwoFactorPolicyService.php) | [02](02_authentication_security.md) |
| [WebhookDestinationResolver.php](../../backend/app/Services/WebhookDestinationResolver.php) | [18](18_external_api_webhooks.md) |
| [WorkflowEngineService.php](../../backend/app/Services/WorkflowEngineService.php) | [15](15_workflows_approvals.md) |

## เส้นทางร่วมที่ควรอ่าน

- [web.php](../../backend/routes/web.php), [auth.php](../../backend/routes/auth.php), [gateway.php](../../backend/routes/gateway.php), [external.php](../../backend/routes/external.php), [summaries.php](../../backend/routes/summaries.php), [console.php](../../backend/routes/console.php)
- [Models](../../backend/app/Models), [Support](../../backend/app/Support), [Policies](../../backend/app/Policies), [Jobs](../../backend/app/Jobs), [Migrations](../../backend/database/migrations)
- [React Pages](../../backend/resources/js/Pages), [Hooks](../../backend/resources/js/Hooks), [Tests](../../backend/tests)

กลับ [สารบัญ](README.md)
