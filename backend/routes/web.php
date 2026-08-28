<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Delivery\ProjectController;
use App\Http\Controllers\Delivery\TaskController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Finance\BankAccountController;
use App\Http\Controllers\Finance\BankStatementController;
use App\Http\Controllers\Finance\CommercialDocumentController;
use App\Http\Controllers\Finance\ETaxController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\GeneralLedgerController;
use App\Http\Controllers\Finance\GoodsReceiptController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\ProductController;
use App\Http\Controllers\Finance\PurchaseOrderController;
use App\Http\Controllers\Finance\QuotationController;
use App\Http\Controllers\Finance\SupplierController;
use App\Http\Controllers\Finance\TaxReportController;
use App\Http\Controllers\Finance\TreasuryOperationsController;
use App\Http\Controllers\Finance\TreasuryReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sales\ActivityController;
use App\Http\Controllers\Sales\ContactController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\DealController;
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\OrganizationSettingsController;
use App\Http\Controllers\Settings\OrganizationStructureController;
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

Route::middleware(['auth', 'verified'])->group(function () {
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
        ->middleware(['permission:customers.create', 'password.confirm', 'throttle:10,1'])
        ->name('customers.store');
    Route::patch('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware(['permission:customers.update', 'password.confirm', 'throttle:10,1'])
        ->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware(['permission:customers.delete', 'password.confirm', 'throttle:10,1'])
        ->name('customers.destroy');

    Route::post('/customers/{customer}/contacts', [ContactController::class, 'store'])
        ->middleware(['permission:contacts.create', 'password.confirm', 'throttle:10,1'])
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
        ->middleware(['permission:deals.create', 'password.confirm', 'throttle:10,1'])
        ->name('deals.store');
    Route::patch('/deals/{deal}', [DealController::class, 'update'])
        ->middleware(['permission:deals.update', 'password.confirm', 'throttle:10,1'])
        ->name('deals.update');

    Route::post('/activities', [ActivityController::class, 'store'])
        ->middleware(['permission:activities.create', 'password.confirm', 'throttle:10,1'])
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
        ->middleware(['permission:products.manage', 'password.confirm', 'throttle:10,1'])
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
        ->middleware(['permission:suppliers.create', 'password.confirm', 'throttle:10,1'])
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
        ->middleware(['permission:purchase_orders.create', 'password.confirm', 'throttle:10,1'])
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
    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware(['permission:invoices.create', 'password.confirm', 'throttle:10,1'])
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
        ->middleware(['permission:quotations.create', 'password.confirm', 'throttle:10,1'])
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
        ->middleware(['permission:treasury.accounts.manage', 'password.confirm', 'throttle:10,1'])
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
    Route::post('/bank-statement-lines/{line}/match', [BankStatementController::class, 'match'])
        ->middleware(['permission:treasury.reconciliation.manage', 'password.confirm', 'throttle:10,1'])
        ->name('bank-statement-lines.match');
    Route::post('/bank-statement-lines/{line}/unmatch', [BankStatementController::class, 'unmatch'])
        ->middleware(['permission:treasury.reconciliation.manage', 'password.confirm', 'throttle:10,1'])
        ->name('bank-statement-lines.unmatch');
    Route::get('/petty-cash', [TreasuryOperationsController::class, 'pettyCash'])->middleware('permission:petty_cash.view')->name('petty-cash.index');
    Route::post('/petty-cash/funds', [TreasuryOperationsController::class, 'storeFund'])->middleware(['permission:petty_cash.manage', 'password.confirm', 'throttle:10,1'])->name('petty-cash.funds.store');
    Route::post('/petty-cash/requests', [TreasuryOperationsController::class, 'storeRequest'])->middleware(['permission:petty_cash.manage', 'password.confirm', 'throttle:10,1'])->name('petty-cash.requests.store');
    Route::post('/petty-cash/requests/{pettyCashRequest}/approve', [TreasuryOperationsController::class, 'approveRequest'])->middleware(['permission:petty_cash.approve', 'password.confirm', 'throttle:10,1'])->name('petty-cash.requests.approve');
    Route::post('/petty-cash/requests/{pettyCashRequest}/reject', [TreasuryOperationsController::class, 'rejectRequest'])->middleware(['permission:petty_cash.approve', 'password.confirm', 'throttle:10,1'])->name('petty-cash.requests.reject');
    Route::post('/petty-cash/requests/{pettyCashRequest}/pay', [TreasuryOperationsController::class, 'payRequest'])->middleware(['permission:petty_cash.manage', 'password.confirm', 'throttle:10,1'])->name('petty-cash.requests.pay');
    Route::post('/petty-cash/reimbursements', [TreasuryOperationsController::class, 'reimburse'])->middleware(['permission:petty_cash.manage', 'password.confirm', 'throttle:10,1'])->name('petty-cash.reimbursements.store');
    Route::get('/cheques', [TreasuryOperationsController::class, 'cheques'])->middleware('permission:cheques.view')->name('cheques.index');
    Route::post('/cheques', [TreasuryOperationsController::class, 'storeCheque'])->middleware(['permission:cheques.manage', 'password.confirm', 'throttle:10,1'])->name('cheques.store');
    Route::post('/cheques/{cheque}/transition', [TreasuryOperationsController::class, 'transitionCheque'])->middleware(['permission:cheques.manage', 'password.confirm', 'throttle:10,1'])->name('cheques.transition');
    Route::get('/treasury-reports', [TreasuryReportController::class, 'index'])->middleware('permission:treasury.reports.view')->name('treasury-reports.index');
    Route::get('/accounting/chart-of-accounts', [GeneralLedgerController::class, 'chartOfAccounts'])->middleware('permission:accounting.chart_accounts.view')->name('accounting.chart-of-accounts.index');
    Route::post('/accounting/chart-of-accounts', [GeneralLedgerController::class, 'storeAccount'])->middleware(['permission:accounting.chart_accounts.manage', 'password.confirm', 'throttle:10,1'])->name('accounting.chart-of-accounts.store');
    Route::patch('/accounting/chart-of-accounts/{chartOfAccount}', [GeneralLedgerController::class, 'updateAccount'])->middleware(['permission:accounting.chart_accounts.manage', 'password.confirm', 'throttle:10,1'])->name('accounting.chart-of-accounts.update');
    Route::get('/accounting/periods', [GeneralLedgerController::class, 'periods'])->middleware('permission:accounting.periods.view')->name('accounting.periods.index');
    Route::post('/accounting/periods', [GeneralLedgerController::class, 'storePeriod'])->middleware(['permission:accounting.periods.manage', 'password.confirm', 'throttle:10,1'])->name('accounting.periods.store');
    Route::post('/accounting/periods/{accountingPeriod}/close', [GeneralLedgerController::class, 'closePeriod'])->middleware(['permission:accounting.periods.manage', 'password.confirm', 'throttle:10,1'])->name('accounting.periods.close');
    Route::get('/accounting/journals', [GeneralLedgerController::class, 'journals'])->middleware('permission:accounting.journals.view')->name('accounting.journals.index');
    Route::post('/accounting/journals', [GeneralLedgerController::class, 'storeJournal'])->middleware(['permission:accounting.journals.post', 'password.confirm', 'throttle:10,1'])->name('accounting.journals.store');
    Route::post('/accounting/journals/{journalEntry}/reverse', [GeneralLedgerController::class, 'reverseJournal'])->middleware(['permission:accounting.journals.reverse', 'password.confirm', 'throttle:10,1'])->name('accounting.journals.reverse');
    Route::get('/accounting/reports', [GeneralLedgerController::class, 'reports'])->middleware('permission:accounting.reports.view')->name('accounting.reports.index');
    Route::post('/credit-debit-notes', [CommercialDocumentController::class, 'storeCreditDebitNote'])
        ->middleware(['permission:credit_debit_notes.create', 'password.confirm', 'throttle:10,1'])
        ->name('credit-debit-notes.store');
    Route::post('/billing-notes', [CommercialDocumentController::class, 'storeBillingNote'])
        ->middleware(['permission:billing_notes.create', 'password.confirm', 'throttle:10,1'])
        ->name('billing-notes.store');
    Route::post('/delivery-orders', [CommercialDocumentController::class, 'storeDeliveryOrder'])
        ->middleware(['permission:delivery_orders.create', 'password.confirm', 'throttle:10,1'])
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
    Route::post('/e-tax/documents/{document}/submit', [ETaxController::class, 'submit'])->middleware(['permission:e_tax.submit', 'password.confirm', 'throttle:5,1'])->name('e-tax.documents.submit');
    Route::get('/e-tax/rd-prep', [ETaxController::class, 'rdPrep'])->middleware('permission:e_tax.view')->name('e-tax.rd-prep');
    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])
        ->middleware('permission:inventory.view')
        ->name('goods-receipts.index');
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])
        ->middleware(['permission:inventory.receive', 'password.confirm', 'throttle:10,1'])
        ->name('goods-receipts.store');
    Route::post('/stock-movements', [GoodsReceiptController::class, 'storeMovement'])
        ->middleware(['permission:inventory.adjust', 'password.confirm', 'throttle:10,1'])
        ->name('stock-movements.store');
    Route::get('/expenses', [ExpenseController::class, 'index'])
        ->middleware('permission:expenses.view')
        ->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])
        ->middleware(['permission:expenses.create', 'password.confirm', 'throttle:10,1'])
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
        ->middleware(['permission:projects.create', 'password.confirm', 'throttle:10,1'])
        ->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])
        ->middleware(['permission:projects.update', 'password.confirm', 'throttle:10,1'])
        ->name('projects.update');
    Route::post('/deals/{deal}/project', [ProjectController::class, 'storeFromDeal'])
        ->middleware(['permission:projects.create', 'password.confirm', 'throttle:10,1'])
        ->name('deals.projects.store');
    Route::post('/projects/{project}/members', [ProjectController::class, 'storeMember'])
        ->middleware(['permission:projects.update', 'password.confirm', 'throttle:10,1'])
        ->name('projects.members.store');
    Route::delete('/projects/{project}/members/{member}', [ProjectController::class, 'destroyMember'])
        ->middleware(['permission:projects.update', 'password.confirm', 'throttle:10,1'])
        ->name('projects.members.destroy');
    Route::get('/tasks', [TaskController::class, 'index'])
        ->middleware('permission:tasks.view')
        ->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])
        ->middleware(['permission:tasks.create', 'password.confirm', 'throttle:10,1'])
        ->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])
        ->middleware(['permission:tasks.update', 'password.confirm', 'throttle:10,1'])
        ->name('tasks.update');
    Route::post('/tasks/{task}/checklists', [TaskController::class, 'storeChecklist'])
        ->middleware(['permission:tasks.update', 'password.confirm', 'throttle:10,1'])
        ->name('tasks.checklists.store');
    Route::patch('/task-checklists/{checklist}', [TaskController::class, 'toggleChecklist'])
        ->middleware(['permission:tasks.update', 'password.confirm', 'throttle:10,1'])
        ->name('task-checklists.update');
    Route::post('/tasks/{task}/comments', [TaskController::class, 'storeComment'])
        ->middleware(['permission:tasks.comment', 'password.confirm', 'throttle:10,1'])
        ->name('tasks.comments.store');
    Route::get('/files/{file}/download', [FileController::class, 'download'])
        ->name('files.download');
    Route::get('/settings/organization', [OrganizationSettingsController::class, 'edit'])
        ->middleware('permission:settings.organization.view')
        ->name('settings.organization.edit');
    Route::patch('/settings/organization', [OrganizationSettingsController::class, 'update'])
        ->middleware(['permission:settings.organization.update', 'password.confirm'])
        ->name('settings.organization.update');
    Route::patch('/settings/organization/numbering', [OrganizationSettingsController::class, 'updateNumbering'])
        ->middleware(['permission:settings.organization.update', 'password.confirm'])
        ->name('settings.organization.numbering.update');
    Route::get('/settings/notifications', [NotificationPreferenceController::class, 'edit'])
        ->middleware('permission:settings.organization.view')
        ->name('settings.notifications.edit');
    Route::patch('/settings/notifications', [NotificationPreferenceController::class, 'update'])
        ->middleware(['permission:settings.organization.update', 'password.confirm'])
        ->name('settings.notifications.update');

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
