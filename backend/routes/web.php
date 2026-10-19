<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Delivery\MyWorkDashboardController;
use App\Http\Controllers\Delivery\ProjectController;
use App\Http\Controllers\Delivery\TaskController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Finance\BankAccountController;
use App\Http\Controllers\Finance\BankStatementController;
use App\Http\Controllers\Finance\BankTransferController;
use App\Http\Controllers\Finance\CashFlowController;
use App\Http\Controllers\Finance\CommercialDocumentController;
use App\Http\Controllers\Finance\CurrencyController;
use App\Http\Controllers\Finance\ETaxController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FixedAssetController;
use App\Http\Controllers\Finance\GeneralLedgerController;
use App\Http\Controllers\Finance\GoodsReceiptController;
use App\Http\Controllers\Finance\InventoryOperationsController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\OcrDocumentController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\PayrollController;
use App\Http\Controllers\Finance\ProcurementDashboardController;
use App\Http\Controllers\Finance\ProductController;
use App\Http\Controllers\Finance\PurchaseOrderController;
use App\Http\Controllers\Finance\QuotationController;
use App\Http\Controllers\Finance\SupplierController;
use App\Http\Controllers\Finance\TaxReportController;
use App\Http\Controllers\Finance\TreasuryOperationsController;
use App\Http\Controllers\Finance\TreasuryReportController;
use App\Http\Controllers\Finance\VendorPaymentController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\Hr\AttendanceImportController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\PositionController;
use App\Http\Controllers\Hr\RosterController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\HrDashboardController;
use App\Http\Controllers\Manufacturing\BillOfMaterialsController;
use App\Http\Controllers\Manufacturing\WorkOrderController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sales\ActivityController;
use App\Http\Controllers\Sales\ContactController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\DealController;
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Settings\AiConfigurationController;
use App\Http\Controllers\Settings\IntegrationApiKeyController;
use App\Http\Controllers\Settings\IntegrationWebhookController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\OrganizationSettingsController;
use App\Http\Controllers\Settings\OrganizationStructureController;
use App\Http\Controllers\WorkflowController;
use App\Http\Middleware\EnsureFormSubmission;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('dashboard');
Route::get('/executive-dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('executive.dashboard');
Route::get('/finance-dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:expenses.view'])
    ->name('finance.dashboard');
Route::get('/delivery-dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('delivery.dashboard');
Route::get('/my-work', MyWorkDashboardController::class)
    ->middleware(['auth', 'verified', 'permission:tasks.view'])
    ->name('my-work.dashboard');
Route::get('/procurement-dashboard', ProcurementDashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('procurement.dashboard');
Route::get('/hr-dashboard', HrDashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('hr.dashboard');

Route::get('/portal/access/{token}', [PortalController::class, 'consume'])->middleware('throttle:10,1')->name('portal.consume');
Route::get('/portal/sign-in', [PortalController::class, 'signIn'])->name('portal.sign-in');
Route::get('/invoice-payment/{invoice}', [GatewayController::class, 'qr'])->middleware('throttle:30,1')->name('gateway.qr');
Route::get('/portal', [PortalController::class, 'dashboard'])->middleware('throttle:60,1')->name('portal.dashboard');
Route::post('/portal/logout', [PortalController::class, 'logout'])->middleware('throttle:10,1')->name('portal.logout');
Route::post('/portal/quotations/{quotation}/accept', [PortalController::class, 'acceptQuotation'])->middleware('throttle:10,1')->name('portal.quotations.accept');
Route::post('/portal/vendor-bills', [PortalController::class, 'submitVendorBill'])->middleware('throttle:5,1')->name('portal.vendor-bills.store');
Route::get('/portal/document-versions/{version}/download', [PortalController::class, 'download'])->middleware('throttle:30,1')->name('portal.documents.download');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/settings/api-access', [IntegrationApiKeyController::class, 'index'])
        ->middleware('permission:api_keys.view')->name('settings.api-access.index');
    Route::get('/settings/api-access/webhooks', [IntegrationWebhookController::class, 'index'])
        ->middleware('permission:api_keys.view')->name('settings.api-webhooks.index');
    Route::post('/settings/api-access/webhooks', [IntegrationWebhookController::class, 'store'])
        ->middleware(['permission:api_keys.manage', 'password.confirm', 'throttle:10,1'])->name('settings.api-webhooks.store');
    foreach (['verify', 'pause', 'rotate'] as $action) {
        Route::post('/settings/api-access/webhooks/{webhook}/'.$action, [IntegrationWebhookController::class, $action])
            ->middleware(['permission:api_keys.manage', 'password.confirm', 'throttle:10,1'])->name('settings.api-webhooks.'.$action);
    }
    Route::get('/settings/api-access/parties', [IntegrationApiKeyController::class, 'parties'])
        ->middleware(['permission:api_keys.manage', 'throttle:30,1'])->name('settings.api-access.parties');
    Route::get('/settings/api-access/documents', [IntegrationApiKeyController::class, 'documents'])
        ->middleware(['permission:api_keys.manage', 'throttle:30,1'])->name('settings.api-access.documents');
    Route::post('/settings/api-access/keys', [IntegrationApiKeyController::class, 'store'])
        ->middleware(['permission:api_keys.manage', 'password.confirm', 'throttle:10,1'])->name('settings.api-access.store');
    Route::patch('/settings/api-access/keys/{apiKey}', [IntegrationApiKeyController::class, 'update'])
        ->middleware(['permission:api_keys.manage', 'password.confirm', 'throttle:10,1'])->name('settings.api-access.update');
    Route::post('/settings/api-access/keys/{apiKey}/revoke', [IntegrationApiKeyController::class, 'revoke'])
        ->middleware(['permission:api_keys.manage', 'password.confirm', 'throttle:10,1'])->name('settings.api-access.revoke');
    Route::get('/settings/payment-gateway', [GatewayController::class, 'settings'])->middleware('permission:settings.organization.view')->name('gateway.settings');
    Route::put('/settings/payment-gateway', [GatewayController::class, 'configure'])->middleware(['permission:settings.organization.update', 'password.confirm', 'throttle:10,1'])->name('gateway.configure');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/sales-dashboard', SalesDashboardController::class)
        ->middleware('permission:sales.dashboard.view')
        ->name('sales.dashboard');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:customers.view')
        ->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware(['permission:customers.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('customers.store');
    Route::patch('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware(['permission:customers.update', 'password.confirm', 'throttle:10,1'])
        ->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware(['permission:customers.delete', 'password.confirm', 'throttle:10,1'])
        ->name('customers.destroy');

    Route::post('/customers/{customer}/contacts', [ContactController::class, 'store'])
        ->middleware(['permission:contacts.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('contacts.store');
    Route::patch('/contacts/{contact}', [ContactController::class, 'update'])
        ->middleware(['permission:contacts.update', 'password.confirm', 'throttle:10,1'])
        ->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])
        ->middleware(['permission:contacts.delete', 'password.confirm', 'throttle:10,1'])
        ->name('contacts.destroy');

    Route::get('/deals', [DealController::class, 'index'])
        ->middleware('permission:deals.view')
        ->name('deals.index');
    Route::post('/deals', [DealController::class, 'store'])
        ->middleware(['permission:deals.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('deals.store');
    Route::patch('/deals/{deal}', [DealController::class, 'update'])
        ->middleware(['permission:deals.update', 'password.confirm', 'throttle:10,1'])
        ->name('deals.update');

    Route::post('/activities', [ActivityController::class, 'store'])
        ->middleware(['permission:activities.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('activities.store');
    Route::patch('/activities/{activity}', [ActivityController::class, 'update'])
        ->middleware(['permission:activities.update', 'password.confirm', 'throttle:10,1'])
        ->name('activities.update');
    Route::patch('/activities/{activity}/complete', [ActivityController::class, 'complete'])
        ->middleware(['permission:activities.update', 'password.confirm', 'throttle:10,1'])
        ->name('activities.complete');
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:products.manage')
        ->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware(['permission:products.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('products.store');
    Route::patch('/products/{product}', [ProductController::class, 'update'])
        ->middleware(['permission:products.manage', 'password.confirm', 'throttle:10,1'])
        ->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware(['permission:products.manage', 'password.confirm', 'throttle:10,1'])
        ->name('products.destroy');
    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->middleware('permission:suppliers.view')
        ->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->middleware(['permission:suppliers.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('suppliers.store');
    Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->middleware(['permission:suppliers.update', 'password.confirm', 'throttle:10,1'])
        ->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->middleware(['permission:suppliers.delete', 'password.confirm', 'throttle:10,1'])
        ->name('suppliers.destroy');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware('permission:purchase_orders.view')
        ->name('purchase-orders.index');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware(['permission:purchase_orders.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('purchase-orders.store');
    Route::patch('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->middleware(['permission:purchase_orders.update', 'password.confirm', 'throttle:10,1'])
        ->name('purchase-orders.update');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware(['permission:purchase_orders.approve', 'password.confirm', 'throttle:10,1'])
        ->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->middleware(['permission:purchase_orders.cancel', 'password.confirm', 'throttle:10,1'])
        ->name('purchase-orders.cancel');
    Route::get('/purchase-orders/{purchaseOrder}/print', [PurchaseOrderController::class, 'print'])
        ->middleware('permission:purchase_orders.view')
        ->name('purchase-orders.print');
    Route::get('/purchase-orders/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'pdf'])
        ->middleware('permission:purchase_orders.view')
        ->name('purchase-orders.pdf');
    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->middleware('permission:invoices.view')
        ->name('invoices.index');
    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('permission:payments.view')
        ->name('payments.index');
    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware(['permission:invoices.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('invoices.store');
    Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update'])
        ->middleware(['permission:invoices.update', 'password.confirm', 'throttle:10,1'])
        ->name('invoices.update');
    Route::patch('/invoices/{invoice}/void', [InvoiceController::class, 'void'])
        ->middleware(['permission:invoices.void', 'password.confirm', 'throttle:10,1'])
        ->name('invoices.void');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])
        ->middleware('permission:invoices.view')
        ->name('invoices.print');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
        ->middleware('permission:invoices.view')
        ->name('invoices.pdf');
    Route::get('/quotations', [QuotationController::class, 'index'])
        ->middleware('permission:quotations.view')
        ->name('quotations.index');
    Route::post('/quotations', [QuotationController::class, 'store'])
        ->middleware(['permission:quotations.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('quotations.store');
    Route::patch('/quotations/{quotation}', [QuotationController::class, 'update'])
        ->middleware(['permission:quotations.update', 'password.confirm', 'throttle:10,1'])
        ->name('quotations.update');
    Route::post('/quotations/{quotation}/approve', [QuotationController::class, 'approve'])
        ->middleware(['permission:quotations.approve', 'password.confirm', 'throttle:10,1'])
        ->name('quotations.approve');
    Route::post('/quotations/{quotation}/reject', [QuotationController::class, 'reject'])
        ->middleware(['permission:quotations.approve', 'password.confirm', 'throttle:10,1'])
        ->name('quotations.reject');
    Route::post('/quotations/{quotation}/convert-to-invoice', [QuotationController::class, 'convertToInvoice'])
        ->middleware(['permission:quotations.convert', 'password.confirm', 'throttle:10,1'])
        ->name('quotations.convert-to-invoice');
    Route::get('/commercial-documents', [CommercialDocumentController::class, 'index'])
        ->middleware('permission:billing_notes.view')
        ->name('commercial-documents.index');
    Route::get('/bank-accounts', [BankAccountController::class, 'index'])
        ->middleware('permission:treasury.accounts.view')
        ->name('bank-accounts.index');
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])
        ->middleware(['permission:treasury.accounts.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('bank-accounts.store');
    Route::patch('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])
        ->middleware(['permission:treasury.accounts.manage', 'password.confirm', 'throttle:10,1'])
        ->name('bank-accounts.update');
    Route::get('/bank-statements', [BankStatementController::class, 'index'])
        ->middleware('permission:treasury.reconciliation.view')
        ->name('bank-statements.index');
    Route::post('/bank-statements/import', [BankStatementController::class, 'import'])
        ->middleware(['permission:treasury.reconciliation.manage', 'password.confirm', 'throttle:5,1'])
        ->name('bank-statements.import');
    Route::post('/bank-transfers', [BankTransferController::class, 'store'])
        ->middleware(['permission:treasury.accounts.manage', 'password.confirm', 'throttle:10,1'])
        ->name('bank-transfers.store');
    Route::post('/bank-statement-lines/{line}/match', [BankStatementController::class, 'match'])
        ->middleware(['permission:treasury.reconciliation.manage', 'password.confirm', 'throttle:10,1'])
        ->name('bank-statement-lines.match');
    Route::post('/bank-statement-lines/{line}/unmatch', [BankStatementController::class, 'unmatch'])
        ->middleware(['permission:treasury.reconciliation.manage', 'password.confirm', 'throttle:10,1'])
        ->name('bank-statement-lines.unmatch');
    Route::get('/petty-cash', [TreasuryOperationsController::class, 'pettyCash'])->middleware('permission:petty_cash.view')->name('petty-cash.index');
    Route::post('/petty-cash/funds', [TreasuryOperationsController::class, 'storeFund'])->middleware(['permission:petty_cash.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('petty-cash.funds.store');
    Route::post('/petty-cash/requests', [TreasuryOperationsController::class, 'storeRequest'])->middleware(['permission:petty_cash.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('petty-cash.requests.store');
    Route::post('/petty-cash/requests/{pettyCashRequest}/approve', [TreasuryOperationsController::class, 'approveRequest'])->middleware(['permission:petty_cash.approve', 'password.confirm', 'throttle:10,1'])->name('petty-cash.requests.approve');
    Route::post('/petty-cash/requests/{pettyCashRequest}/reject', [TreasuryOperationsController::class, 'rejectRequest'])->middleware(['permission:petty_cash.approve', 'password.confirm', 'throttle:10,1'])->name('petty-cash.requests.reject');
    Route::post('/petty-cash/requests/{pettyCashRequest}/pay', [TreasuryOperationsController::class, 'payRequest'])->middleware(['permission:petty_cash.manage', 'password.confirm', 'throttle:10,1'])->name('petty-cash.requests.pay');
    Route::post('/petty-cash/reimbursements', [TreasuryOperationsController::class, 'reimburse'])->middleware(['permission:petty_cash.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('petty-cash.reimbursements.store');
    Route::get('/cheques', [TreasuryOperationsController::class, 'cheques'])->middleware('permission:cheques.view')->name('cheques.index');
    Route::post('/cheques', [TreasuryOperationsController::class, 'storeCheque'])->middleware(['permission:cheques.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('cheques.store');
    Route::post('/cheques/{cheque}/transition', [TreasuryOperationsController::class, 'transitionCheque'])->middleware(['permission:cheques.manage', 'password.confirm', 'throttle:10,1'])->name('cheques.transition');
    Route::get('/treasury-reports', [TreasuryReportController::class, 'index'])->middleware('permission:treasury.reports.view')->name('treasury-reports.index');
    Route::get('/accounting/chart-of-accounts', [GeneralLedgerController::class, 'chartOfAccounts'])->middleware('permission:accounting.chart_accounts.view')->name('accounting.chart-of-accounts.index');
    Route::post('/accounting/chart-of-accounts', [GeneralLedgerController::class, 'storeAccount'])->middleware(['permission:accounting.chart_accounts.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('accounting.chart-of-accounts.store');
    Route::patch('/accounting/chart-of-accounts/{chartOfAccount}', [GeneralLedgerController::class, 'updateAccount'])->middleware(['permission:accounting.chart_accounts.manage', 'password.confirm', 'throttle:10,1'])->name('accounting.chart-of-accounts.update');
    Route::get('/accounting/periods', [GeneralLedgerController::class, 'periods'])->middleware('permission:accounting.periods.view')->name('accounting.periods.index');
    Route::post('/accounting/periods', [GeneralLedgerController::class, 'storePeriod'])->middleware(['permission:accounting.periods.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('accounting.periods.store');
    Route::post('/accounting/periods/{accountingPeriod}/close', [GeneralLedgerController::class, 'closePeriod'])->middleware(['permission:accounting.periods.manage', 'password.confirm', 'throttle:10,1'])->name('accounting.periods.close');
    Route::get('/accounting/journals', [GeneralLedgerController::class, 'journals'])->middleware('permission:accounting.journals.view')->name('accounting.journals.index');
    Route::post('/accounting/journals', [GeneralLedgerController::class, 'storeJournal'])->middleware(['permission:accounting.journals.post', 'password.confirm', 'throttle:10,1'])->name('accounting.journals.store');
    Route::post('/accounting/journals/{journalEntry}/reverse', [GeneralLedgerController::class, 'reverseJournal'])->middleware(['permission:accounting.journals.reverse', 'password.confirm', 'throttle:10,1'])->name('accounting.journals.reverse');
    Route::get('/accounting/reports', [GeneralLedgerController::class, 'reports'])->middleware('permission:accounting.reports.view')->name('accounting.reports.index');
    Route::get('/currencies', [CurrencyController::class, 'index'])->middleware('permission:fx.view')->name('currencies.index');
    Route::post('/currencies', [CurrencyController::class, 'storeCurrency'])->middleware(['permission:fx.manage', 'password.confirm', 'throttle:10,1'])->name('currencies.store');
    Route::post('/exchange-rates', [CurrencyController::class, 'storeRate'])->middleware(['permission:fx.manage', 'password.confirm', 'throttle:10,1'])->name('exchange-rates.store');
    Route::post('/fx-revaluations', [CurrencyController::class, 'revalue'])->middleware(['permission:fx.revalue', 'password.confirm', 'throttle:5,1'])->name('fx-revaluations.store');
    Route::post('/fx-revaluations/reverse', [CurrencyController::class, 'reverse'])->middleware(['permission:fx.revalue', 'password.confirm', 'throttle:5,1'])->name('fx-revaluations.reverse');
    Route::get('/fixed-assets', [FixedAssetController::class, 'index'])->middleware('permission:fixed_assets.view')->name('fixed-assets.index');
    Route::post('/fixed-assets/categories', [FixedAssetController::class, 'storeCategory'])->middleware(['permission:fixed_assets.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('fixed-assets.categories.store');
    Route::post('/fixed-assets', [FixedAssetController::class, 'storeAsset'])->middleware(['permission:fixed_assets.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('fixed-assets.store');
    Route::post('/fixed-assets/depreciate', [FixedAssetController::class, 'depreciate'])->middleware(['permission:fixed_assets.depreciate', 'password.confirm', 'throttle:5,1'])->name('fixed-assets.depreciate');
    Route::post('/fixed-assets/{fixedAsset}/dispose', [FixedAssetController::class, 'dispose'])->middleware(['permission:fixed_assets.dispose', 'password.confirm', 'throttle:10,1'])->name('fixed-assets.dispose');
    Route::post('/fixed-assets/{fixedAsset}/attachment', [FixedAssetController::class, 'storeAttachment'])->middleware(['permission:fixed_assets.manage', 'password.confirm', 'throttle:10,1'])->name('fixed-assets.attachment.store');
    Route::get('/payroll', [PayrollController::class, 'index'])->middleware('permission:payroll.view')->name('payroll.index');
    Route::get('/hr', [HrController::class, 'index'])->middleware('permission:hr.self.view')->name('hr.index');
    Route::get('/hr/employees', [EmployeeController::class, 'index'])->middleware('permission:hr.employees.view')->name('hr.employees.index');
    Route::get('/hr/employees/template', [EmployeeController::class, 'template'])->middleware('permission:hr.employees.manage')->name('hr.employees.template');
    Route::post('/hr/employees/import', [EmployeeController::class, 'import'])->middleware(['permission:hr.employees.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.employees.import');
    Route::post('/hr/employees', [EmployeeController::class, 'store'])->middleware(['permission:hr.employees.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.employees.store');
    Route::patch('/hr/employees/{employee}', [EmployeeController::class, 'update'])->middleware(['permission:hr.employees.manage', 'password.confirm', 'throttle:10,1'])->name('hr.employees.update');
    Route::patch('/hr/employees/account/{employee}', [EmployeeController::class, 'linkAccount'])->middleware(['permission:hr.employees.manage', 'password.confirm', 'throttle:10,1'])->name('hr.employees.account.link');
    Route::get('/hr/positions', [PositionController::class, 'index'])->middleware('permission:hr.employees.view')->name('hr.positions.index');
    Route::post('/hr/positions', [PositionController::class, 'store'])->middleware(['permission:hr.employees.manage', 'throttle:30,1'])->name('hr.positions.store');
    Route::get('/hr/roster', [RosterController::class, 'index'])->middleware('permission:hr.shifts.manage')->name('hr.roster.index');
    Route::post('/hr/roster/schedules', [RosterController::class, 'storeSchedule'])->middleware(['permission:hr.shifts.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.roster.schedules.store');
    Route::post('/hr/roster/batch', [RosterController::class, 'batchAssign'])->middleware(['permission:hr.shifts.manage', 'password.confirm', 'throttle:5,1', EnsureFormSubmission::class])->name('hr.roster.batch.store');
    Route::get('/hr/attendance-import', [AttendanceImportController::class, 'index'])->middleware('permission:hr.attendance.import')->name('hr.attendance-import.index');
    Route::get('/hr/attendance-import/template', [AttendanceImportController::class, 'template'])->middleware('permission:hr.attendance.import')->name('hr.attendance-import.template');
    Route::post('/hr/attendance-import', [AttendanceImportController::class, 'import'])->middleware(['permission:hr.attendance.import', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.attendance-import.store');
    Route::post('/hr/attendance-import/reconcile', [AttendanceImportController::class, 'reconcile'])->middleware(['permission:hr.attendance.import', 'password.confirm', 'throttle:5,1', EnsureFormSubmission::class])->name('hr.attendance-import.reconcile');
    Route::post('/hr/attendance-import/{attendance}/correct', [AttendanceImportController::class, 'correct'])->middleware(['permission:hr.attendance.import', 'password.confirm', 'throttle:10,1'])->name('hr.attendance-import.correct');
    Route::get('/workflows', [WorkflowController::class, 'index'])->middleware('permission:workflows.view')->name('workflows.index');
    Route::post('/workflows/definitions', [WorkflowController::class, 'storeDefinition'])->middleware(['permission:workflows.manage', 'password.confirm', EnsureFormSubmission::class])->name('workflows.definitions.store');
    Route::post('/workflows/submit/{subjectType}/{subjectId}', [WorkflowController::class, 'submit'])->middleware(['permission:workflows.view', 'password.confirm'])->name('workflows.submit');
    Route::post('/workflow-approvals/{workflowApproval}/act', [WorkflowController::class, 'act'])->middleware(['permission:workflows.approve', 'password.confirm'])->name('workflows.approvals.act');
    Route::post('/workflow-delegations', [WorkflowController::class, 'storeDelegation'])->middleware(['permission:workflows.approve', 'password.confirm', EnsureFormSubmission::class])->name('workflows.delegations.store');
    Route::post('/hr/clock-in', [HrController::class, 'clockIn'])->middleware(['permission:hr.self.view', 'throttle:10,1'])->name('hr.clock-in');
    Route::post('/hr/clock-out', [HrController::class, 'clockOut'])->middleware(['permission:hr.self.view', 'throttle:10,1'])->name('hr.clock-out');
    Route::post('/hr/leave-requests', [HrController::class, 'storeLeaveRequest'])->middleware(['permission:hr.self.view', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.leave-requests.store');
    Route::post('/hr/leave-requests/{leaveRequest}/submit', [HrController::class, 'submitLeaveRequest'])->middleware(['permission:hr.self.view', 'throttle:10,1'])->name('hr.leave-requests.submit');
    Route::post('/hr/leave-requests/{leaveRequest}/cancel', [HrController::class, 'cancelLeaveRequest'])->middleware(['permission:hr.self.view', 'throttle:10,1'])->name('hr.leave-requests.cancel');
    Route::post('/hr/shifts', [HrController::class, 'storeShift'])->middleware(['permission:hr.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.shifts.store');
    Route::post('/hr/work-profiles', [HrController::class, 'storeWorkProfile'])->middleware(['permission:hr.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.work-profiles.store');
    Route::post('/hr/holidays', [HrController::class, 'storeHoliday'])->middleware(['permission:hr.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.holidays.store');
    Route::post('/hr/leave-types', [HrController::class, 'storeLeaveType'])->middleware(['permission:hr.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.leave-types.store');
    Route::post('/hr/leave-balances', [HrController::class, 'storeLeaveBalance'])->middleware(['permission:hr.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.leave-balances.store');
    Route::post('/hr/summaries', [HrController::class, 'storeSummary'])->middleware(['permission:hr.summary.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('hr.summaries.store');
    Route::post('/hr/summaries/{attendanceSummary}/lock', [HrController::class, 'lockSummary'])->middleware(['permission:hr.summary.manage', 'password.confirm', 'throttle:10,1'])->name('hr.summaries.lock');
    Route::post('/hr/summaries/{attendanceSummary}/reverse', [HrController::class, 'reverseSummary'])->middleware(['permission:hr.summary.manage', 'password.confirm', 'throttle:10,1'])->name('hr.summaries.reverse');
    Route::get('/documents', [DocumentController::class, 'index'])->middleware('permission:documents.view')->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->middleware(['permission:documents.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('documents.store');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'addVersion'])->middleware(['permission:documents.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('documents.versions.store');
    Route::post('/documents/{document}/links', [DocumentController::class, 'storeLink'])->middleware(['permission:documents.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('documents.links.store');
    Route::post('/documents/categories', [DocumentController::class, 'storeCategory'])->middleware(['permission:documents.retention.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('documents.categories.store');
    Route::post('/documents/retention-policies', [DocumentController::class, 'storeRetentionPolicy'])->middleware(['permission:documents.retention.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('documents.retention-policies.store');
    Route::get('/document-versions/{version}/download', [DocumentController::class, 'download'])->middleware('permission:documents.download')->name('documents.versions.download');
    Route::post('/payroll/profiles', [PayrollController::class, 'storeProfile'])->middleware(['permission:payroll.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('payroll.profiles.store');
    Route::post('/payroll/policies', [PayrollController::class, 'storePolicies'])->middleware(['permission:payroll.manage', 'password.confirm', 'throttle:5,1', EnsureFormSubmission::class])->name('payroll.policies.store');
    Route::post('/payroll/runs', [PayrollController::class, 'storeRun'])->middleware(['permission:payroll.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])->name('payroll.runs.store');
    Route::post('/payroll/runs/{payrollRun}/calculate', [PayrollController::class, 'calculate'])->middleware(['permission:payroll.manage', 'password.confirm', 'throttle:5,1'])->name('payroll.runs.calculate');
    Route::post('/payroll/runs/{payrollRun}/approve', [PayrollController::class, 'approve'])->middleware(['permission:payroll.approve', 'password.confirm', 'throttle:5,1'])->name('payroll.runs.approve');
    Route::post('/payroll/runs/{payrollRun}/pay', [PayrollController::class, 'pay'])->middleware(['permission:payroll.pay', 'password.confirm', 'throttle:5,1'])->name('payroll.runs.pay');
    Route::get('/payroll/runs/{payrollRun}/exports/{type}', [PayrollController::class, 'export'])->middleware('permission:payroll.export')->name('payroll.runs.export');
    Route::get('/payroll/payslips/{payrollItem}/print', [PayrollController::class, 'printPayslip'])->name('payroll.payslips.print');
    Route::get('/payroll/payslips/{payrollItem}/pdf', [PayrollController::class, 'payslipPdf'])->name('payroll.payslips.pdf');
    Route::post('/payroll/payslips/{payrollItem}/email', [PayrollController::class, 'emailPayslip'])->middleware(['permission:payroll.manage', 'password.confirm', 'throttle:10,1'])->name('payroll.payslips.email');
    Route::post('/credit-debit-notes', [CommercialDocumentController::class, 'storeCreditDebitNote'])
        ->middleware(['permission:credit_debit_notes.create', 'password.confirm', 'throttle:10,1'])
        ->name('credit-debit-notes.store');
    Route::post('/billing-notes', [CommercialDocumentController::class, 'storeBillingNote'])
        ->middleware(['permission:billing_notes.create', 'password.confirm', 'throttle:10,1'])
        ->name('billing-notes.store');
    Route::post('/delivery-orders', [CommercialDocumentController::class, 'storeDeliveryOrder'])
        ->middleware(['permission:delivery_orders.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('delivery-orders.store');
    Route::post('/purchase-requests', [CommercialDocumentController::class, 'storePurchaseRequest'])
        ->middleware(['permission:purchase_requests.create', 'password.confirm', 'throttle:10,1'])
        ->name('purchase-requests.store');
    Route::post('/purchase-requests/{purchaseRequest}/approve', [CommercialDocumentController::class, 'approvePurchaseRequest'])
        ->middleware(['permission:purchase_requests.approve', 'password.confirm', 'throttle:10,1'])
        ->name('purchase-requests.approve');
    Route::post('/purchase-requests/{purchaseRequest}/convert-to-po', [CommercialDocumentController::class, 'convertPurchaseRequest'])
        ->middleware(['permission:purchase_requests.approve', 'password.confirm', 'throttle:10,1'])
        ->name('purchase-requests.convert-to-po');
    Route::post('/vouchers', [CommercialDocumentController::class, 'storeVoucher'])
        ->middleware(['permission:vouchers.create', 'password.confirm', 'throttle:10,1'])
        ->name('vouchers.store');
    Route::post('/vouchers/{voucher}/attachment', [CommercialDocumentController::class, 'storeVoucherAttachment'])
        ->middleware(['permission:vouchers.update', 'password.confirm', 'throttle:10,1'])
        ->name('vouchers.attachment.store');
    Route::get('/commercial-documents/{type}/{id}/print', [CommercialDocumentController::class, 'print'])
        ->middleware('permission:billing_notes.view')
        ->name('commercial-documents.print');
    Route::get('/commercial-documents/{type}/{id}/pdf', [CommercialDocumentController::class, 'pdf'])
        ->middleware('permission:billing_notes.view')
        ->name('commercial-documents.pdf');
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])
        ->middleware(['permission:payments.create', 'password.confirm', 'throttle:10,1'])
        ->name('invoices.payments.store');
    Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])
        ->middleware(['permission:payments.reverse', 'password.confirm', 'throttle:10,1'])
        ->name('payments.reverse');
    Route::get('/tax-reports', [TaxReportController::class, 'index'])
        ->middleware('permission:tax_reports.view')
        ->name('tax-reports.index');
    Route::get('/tax-reports/{type}/export', [TaxReportController::class, 'export'])
        ->middleware('permission:tax_reports.view')
        ->name('tax-reports.export');
    Route::get('/tax-reports/{type}/excel', [TaxReportController::class, 'excel'])
        ->middleware('permission:tax_reports.view')
        ->name('tax-reports.excel');
    Route::get('/e-tax', [ETaxController::class, 'index'])->middleware('permission:e_tax.view')->name('e-tax.index');
    Route::patch('/e-tax/config', [ETaxController::class, 'updateConfig'])->middleware(['permission:e_tax.manage', 'password.confirm'])->name('e-tax.config.update');
    Route::post('/e-tax/documents', [ETaxController::class, 'generate'])->middleware(['permission:e_tax.manage', 'password.confirm', 'throttle:10,1'])->name('e-tax.documents.generate');
    Route::get('/e-tax/documents/{document}/download', [ETaxController::class, 'download'])->middleware('permission:e_tax.view')->name('e-tax.documents.download');
    Route::post('/e-tax/documents/{document}/sign', [ETaxController::class, 'sign'])->middleware(['permission:e_tax.manage', 'password.confirm', 'throttle:10,1'])->name('e-tax.documents.sign');
    Route::post('/e-tax/documents/{document}/submit', [ETaxController::class, 'submit'])->middleware(['permission:e_tax.submit', 'password.confirm', 'throttle:5,1'])->name('e-tax.documents.submit');
    Route::post('/e-tax/documents/{document}/inquire', [ETaxController::class, 'inquire'])->middleware(['permission:e_tax.submit', 'throttle:10,1'])->name('e-tax.documents.inquire');
    Route::get('/e-tax/rd-prep', [ETaxController::class, 'rdPrep'])->middleware('permission:e_tax.view')->name('e-tax.rd-prep');
    Route::get('/finance/cash-flow', [CashFlowController::class, 'index'])->middleware('permission:cash_flow.view')->name('finance.cash-flow.index');
    Route::post('/finance/cash-flow/snapshot', [CashFlowController::class, 'snapshot'])->middleware(['permission:cash_flow.manage', 'throttle:10,1'])->name('finance.cash-flow.snapshot');
    Route::patch('/finance/cash-flow/scenarios/{scenario}', [CashFlowController::class, 'updateScenario'])->middleware(['permission:cash_flow.manage', 'throttle:10,1'])->name('finance.cash-flow.scenarios.update');
    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])
        ->middleware('permission:inventory.view')
        ->name('goods-receipts.index');
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])
        ->middleware(['permission:inventory.receive', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('goods-receipts.store');
    Route::post('/stock-movements', [GoodsReceiptController::class, 'storeMovement'])
        ->middleware(['permission:inventory.adjust', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('stock-movements.store');
    Route::get('/inventory-operations', [InventoryOperationsController::class, 'index'])->middleware('permission:inventory.view')->name('inventory-operations.index');
    Route::post('/warehouses', [InventoryOperationsController::class, 'storeWarehouse'])->middleware(['permission:inventory.adjust', 'password.confirm', EnsureFormSubmission::class])->name('warehouses.store');
    Route::post('/warehouses/{warehouse}/bins', [InventoryOperationsController::class, 'storeBin'])->middleware(['permission:inventory.adjust', 'password.confirm', EnsureFormSubmission::class])->name('warehouses.bins.store');
    Route::post('/inventory-lots', [InventoryOperationsController::class, 'storeLot'])->middleware(['permission:inventory.receive', 'password.confirm', EnsureFormSubmission::class])->name('inventory-lots.store');
    Route::post('/stock-transfers', [InventoryOperationsController::class, 'transfer'])->middleware(['permission:inventory.adjust', 'password.confirm', EnsureFormSubmission::class])->name('stock-transfers.store');
    Route::post('/stock-counts', [InventoryOperationsController::class, 'stockCount'])->middleware(['permission:inventory.adjust', 'password.confirm', EnsureFormSubmission::class])->name('stock-counts.store');
    Route::get('/inventory-scan', [InventoryOperationsController::class, 'scan'])->middleware('permission:inventory.view')->name('inventory.scan');

    // Manufacturing & Bill of Materials (BOM)
    Route::get('/manufacturing/boms', [BillOfMaterialsController::class, 'index'])
        ->middleware('permission:manufacturing.bom.view')
        ->name('manufacturing.boms.index');
    Route::post('/manufacturing/boms', [BillOfMaterialsController::class, 'store'])
        ->middleware(['permission:manufacturing.bom.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('manufacturing.boms.store');
    Route::patch('/manufacturing/boms/{billOfMaterial}', [BillOfMaterialsController::class, 'update'])
        ->middleware(['permission:manufacturing.bom.manage', 'password.confirm', 'throttle:10,1'])
        ->name('manufacturing.boms.update');
    Route::post('/manufacturing/boms/{billOfMaterial}/duplicate', [BillOfMaterialsController::class, 'duplicate'])
        ->middleware(['permission:manufacturing.bom.manage', 'password.confirm', 'throttle:10,1'])
        ->name('manufacturing.boms.duplicate');
    Route::delete('/manufacturing/boms/{billOfMaterial}', [BillOfMaterialsController::class, 'destroy'])
        ->middleware(['permission:manufacturing.bom.manage', 'password.confirm', 'throttle:10,1'])
        ->name('manufacturing.boms.destroy');
    Route::get('/manufacturing/boms/{billOfMaterial}/explosion', [BillOfMaterialsController::class, 'explosion'])
        ->middleware('permission:manufacturing.bom.view')
        ->name('manufacturing.boms.explosion');

    // Manufacturing Work Orders
    Route::get('/manufacturing/work-orders', [WorkOrderController::class, 'index'])
        ->middleware('permission:manufacturing.work_orders.view')
        ->name('manufacturing.work-orders.index');
    Route::post('/manufacturing/work-orders', [WorkOrderController::class, 'store'])
        ->middleware(['permission:manufacturing.work_orders.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('manufacturing.work-orders.store');
    Route::patch('/manufacturing/work-orders/{workOrder}', [WorkOrderController::class, 'update'])
        ->middleware(['permission:manufacturing.work_orders.manage', 'password.confirm', 'throttle:10,1'])
        ->name('manufacturing.work-orders.update');
    Route::post('/manufacturing/work-orders/{workOrder}/plan', [WorkOrderController::class, 'plan'])
        ->middleware(['permission:manufacturing.work_orders.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('manufacturing.work-orders.plan');
    Route::post('/manufacturing/work-orders/{workOrder}/release', [WorkOrderController::class, 'release'])
        ->middleware(['permission:manufacturing.work_orders.execute', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('manufacturing.work-orders.release');
    Route::post('/manufacturing/work-orders/{workOrder}/quality-check', [WorkOrderController::class, 'qualityCheck'])
        ->middleware(['permission:manufacturing.work_orders.execute', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('manufacturing.work-orders.quality-check');
    Route::post('/manufacturing/work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete'])
        ->middleware(['permission:manufacturing.work_orders.execute', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('manufacturing.work-orders.complete');
    Route::post('/manufacturing/work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])
        ->middleware(['permission:manufacturing.work_orders.execute', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('manufacturing.work-orders.cancel');
    Route::post('/manufacturing/work-orders/{workOrder}/costs', [WorkOrderController::class, 'addCost'])
        ->middleware(['permission:manufacturing.work_orders.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('manufacturing.work-orders.costs.store');
    Route::get('/expenses', [ExpenseController::class, 'index'])
        ->middleware('permission:expenses.view')
        ->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])
        ->middleware(['permission:expenses.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('expenses.store');
    Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])
        ->middleware(['permission:expenses.update', 'password.confirm', 'throttle:10,1'])
        ->name('expenses.update');
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])
        ->middleware(['permission:expenses.approve', 'password.confirm', 'throttle:10,1'])
        ->name('expenses.approve');
    Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'pay'])
        ->middleware(['permission:expenses.pay', 'password.confirm', 'throttle:10,1'])
        ->name('expenses.pay');
    Route::post('/expenses/{expense}/vendor-payments', [VendorPaymentController::class, 'store'])
        ->middleware(['permission:expenses.pay', 'password.confirm', 'throttle:10,1'])
        ->name('expenses.vendor-payments.store');
    Route::post('/vendor-payments/{vendorPayment}/reverse', [VendorPaymentController::class, 'reverse'])
        ->middleware(['permission:expenses.pay', 'password.confirm', 'throttle:10,1'])
        ->name('vendor-payments.reverse');
    Route::get('/vendor-payments', [VendorPaymentController::class, 'index'])
        ->middleware('permission:expenses.view')
        ->name('vendor-payments.index');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])
        ->middleware(['permission:expenses.reject', 'password.confirm', 'throttle:10,1'])
        ->name('expenses.reject');
    Route::get('/expenses/{expense}/withholding-certificate', [ExpenseController::class, 'withholdingCertificate'])
        ->middleware('permission:expenses.view')
        ->name('expenses.withholding-certificate');
    Route::get('/projects', [ProjectController::class, 'index'])
        ->middleware('permission:projects.view')
        ->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])
        ->middleware(['permission:projects.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])
        ->middleware(['permission:projects.update', 'password.confirm', 'throttle:10,1'])
        ->name('projects.update');
    Route::post('/deals/{deal}/project', [ProjectController::class, 'storeFromDeal'])
        ->middleware(['permission:projects.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('deals.projects.store');
    Route::post('/projects/{project}/members', [ProjectController::class, 'storeMember'])
        ->middleware(['permission:projects.update', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('projects.members.store');
    Route::delete('/projects/{project}/members/{member}', [ProjectController::class, 'destroyMember'])
        ->middleware(['permission:projects.update', 'password.confirm', 'throttle:10,1'])
        ->name('projects.members.destroy');
    Route::get('/tasks', [TaskController::class, 'index'])
        ->middleware('permission:tasks.view')
        ->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])
        ->middleware(['permission:tasks.create', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])
        ->middleware(['permission:tasks.update', 'password.confirm', 'throttle:10,1'])
        ->name('tasks.update');
    Route::post('/tasks/{task}/checklists', [TaskController::class, 'storeChecklist'])
        ->middleware(['permission:tasks.update', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('tasks.checklists.store');
    Route::patch('/task-checklists/{checklist}', [TaskController::class, 'toggleChecklist'])
        ->middleware(['permission:tasks.update', 'password.confirm', 'throttle:10,1'])
        ->name('task-checklists.update');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'storeComment'])
        ->middleware(['permission:tasks.comment', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('tasks.comments.store');
    Route::get('/files/{file}/download', [FileController::class, 'download'])
        ->name('files.download');
    Route::get('/files/{file}/preview', [FileController::class, 'preview'])
        ->name('files.preview');

    // Phase 26: AI Configuration Routes
    Route::get('/settings/ai-configuration', [AiConfigurationController::class, 'index'])
        ->middleware('permission:ai_settings.manage')
        ->name('settings.ai-configuration.index');
    Route::post('/settings/ai-configuration', [AiConfigurationController::class, 'store'])
        ->middleware(['permission:ai_settings.manage', 'throttle:10,1'])
        ->name('settings.ai-configuration.store');
    Route::post('/settings/ai-configuration/test', [AiConfigurationController::class, 'testConnection'])
        ->middleware(['permission:ai_settings.manage', 'throttle:10,1'])
        ->name('settings.ai-configuration.test');

    // Phase 26: AI OCR Document Ingestion Routes
    Route::get('/finance/ocr', [OcrDocumentController::class, 'index'])
        ->middleware('permission:ocr.view')
        ->name('ocr.index');
    Route::post('/finance/ocr/upload', [OcrDocumentController::class, 'upload'])
        ->middleware(['permission:ocr.upload', 'throttle:20,1'])
        ->name('ocr.upload');
    Route::get('/finance/ocr/{extraction}', [OcrDocumentController::class, 'show'])
        ->middleware('permission:ocr.view')
        ->name('ocr.show');
    Route::post('/finance/ocr/{extraction}/convert-expense', [OcrDocumentController::class, 'convertExpense'])
        ->middleware(['permission:ocr.convert', 'throttle:15,1'])
        ->name('ocr.convert-expense');
    Route::post('/finance/ocr/{extraction}/convert-vendor-bill', [OcrDocumentController::class, 'convertVendorBill'])
        ->middleware(['permission:ocr.convert', 'throttle:15,1'])
        ->name('ocr.convert-vendor-bill');
    Route::post('/finance/ocr/{extraction}/reject', [OcrDocumentController::class, 'reject'])
        ->middleware(['permission:ocr.convert', 'throttle:15,1'])
        ->name('ocr.reject');

    Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])
        ->middleware('permission:settings.organization.view')
        ->name('settings.organization.edit');
    Route::patch('/settings/organization', [OrganizationSettingsController::class, 'update'])
        ->middleware(['permission:settings.organization.update', 'password.confirm'])
        ->name('settings.organization.update');
    Route::patch('/settings/organization/numbering', [OrganizationSettingsController::class, 'updateNumbering'])
        ->middleware(['permission:settings.organization.update', 'password.confirm'])
        ->name('settings.organization.numbering.update');
    Route::patch('/settings/organization/attendance-privacy', [OrganizationSettingsController::class, 'updateAttendancePrivacy'])
        ->middleware(['permission:settings.organization.update', 'password.confirm'])
        ->name('settings.organization.attendance-privacy.update');
    Route::patch('/settings/organization/two-factor', [OrganizationSettingsController::class, 'updateTwoFactor'])
        ->middleware(['permission:settings.organization.update', 'password.confirm'])
        ->name('settings.organization.two-factor.update');
    Route::get('/settings/notifications', [NotificationPreferenceController::class, 'edit'])
        ->name('settings.notifications.edit');
    Route::patch('/settings/notifications', [NotificationPreferenceController::class, 'update'])
        ->name('settings.notifications.update');
    Route::patch('/settings/notifications/external-preferences', [NotificationPreferenceController::class, 'updateExternal'])
        ->name('settings.notifications.external-preferences.update');
    Route::post('/settings/notification-channels', [NotificationPreferenceController::class, 'storeChannel'])
        ->middleware(['permission:settings.organization.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.notification-channels.store');
    Route::patch('/settings/notification-channels/{channel}', [NotificationPreferenceController::class, 'updateChannel'])
        ->middleware(['permission:settings.organization.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.notification-channels.update');
    Route::post('/settings/notification-channels/{channel}/test', [NotificationPreferenceController::class, 'testChannel'])
        ->middleware(['permission:settings.organization.update', 'password.confirm', 'throttle:5,1'])
        ->name('settings.notification-channels.test');

    Route::get('/settings/organization-structure', [OrganizationStructureController::class, 'index'])
        ->middleware('permission:settings.structure.view')
        ->name('settings.structure.index');
    Route::post('/settings/branches', [OrganizationStructureController::class, 'storeBranch'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.branches.store');
    Route::patch('/settings/branches/{branch}', [OrganizationStructureController::class, 'updateBranch'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.branches.update');
    Route::patch('/settings/branches/{branch}/head-office', [OrganizationStructureController::class, 'setHeadOffice'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.branches.head-office');
    Route::patch('/settings/branches/{branch}/disable', [OrganizationStructureController::class, 'disableBranch'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.branches.disable');
    Route::delete('/settings/branches/{branch}', [OrganizationStructureController::class, 'destroyBranch'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.branches.destroy');

    Route::post('/settings/divisions', [OrganizationStructureController::class, 'storeDivision'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.divisions.store');
    Route::patch('/settings/divisions/{division}', [OrganizationStructureController::class, 'updateDivision'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.divisions.update');
    Route::patch('/settings/divisions/{division}/disable', [OrganizationStructureController::class, 'disableDivision'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.divisions.disable');
    Route::delete('/settings/divisions/{division}', [OrganizationStructureController::class, 'destroyDivision'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.divisions.destroy');

    Route::post('/settings/departments', [OrganizationStructureController::class, 'storeDepartment'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.departments.store');
    Route::patch('/settings/departments/{department}', [OrganizationStructureController::class, 'updateDepartment'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.departments.update');
    Route::patch('/settings/departments/{department}/disable', [OrganizationStructureController::class, 'disableDepartment'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.departments.disable');
    Route::delete('/settings/departments/{department}', [OrganizationStructureController::class, 'destroyDepartment'])
        ->middleware(['permission:settings.structure.update', 'password.confirm', 'throttle:10,1'])
        ->name('settings.departments.destroy');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');
    Route::post('/users/invite', [UserController::class, 'invite'])
        ->middleware(['permission:users.create', 'password.confirm', 'throttle:10,1'])
        ->name('users.invite');
    Route::post('/portal-users', [PortalController::class, 'provision'])
        ->middleware(['permission:users.create', 'password.confirm', 'throttle:10,1'])
        ->name('portal-users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])
        ->middleware(['permission:users.update', 'password.confirm', 'throttle:10,1'])
        ->name('users.update');
    Route::patch('/users/{user}/disable', [UserController::class, 'disable'])
        ->middleware(['permission:users.disable', 'password.confirm', 'throttle:10,1'])
        ->name('users.disable');
    Route::patch('/users/{user}/enable', [UserController::class, 'enable'])
        ->middleware(['permission:users.disable', 'password.confirm', 'throttle:10,1'])
        ->name('users.enable');
    Route::patch('/users/{user}/structure', [UserController::class, 'updateStructure'])
        ->middleware(['permission:users.update', 'password.confirm', 'throttle:10,1'])
        ->name('users.structure.update');

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware(['permission:roles.manage', 'password.confirm', 'throttle:10,1', EnsureFormSubmission::class])
        ->name('roles.store');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])
        ->middleware(['permission:roles.manage', 'password.confirm', 'throttle:10,1'])
        ->name('roles.update');
    Route::patch('/users/{user}/role', [RoleController::class, 'assign'])
        ->middleware(['permission:roles.update', 'password.confirm', 'throttle:10,1'])
        ->name('users.role.update');
    Route::patch('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
        ->middleware(['permission:roles.manage', 'password.confirm', 'throttle:10,1'])
        ->name('roles.permissions.update');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit-logs.index');
});

Route::get('/storage/{path}', function (string $path) {
    $basePath = realpath(storage_path('app/public'));
    $fullPath = realpath(storage_path('app/public/'.$path));

    abort_unless($basePath && $fullPath && str_starts_with($fullPath, $basePath) && file_exists($fullPath), 404);

    return response()->file($fullPath);
})->where('path', '.*')->name('storage.local');

require __DIR__.'/auth.php';
