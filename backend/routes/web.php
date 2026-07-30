<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Delivery\ProjectController;
use App\Http\Controllers\Delivery\TaskController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sales\ActivityController;
use App\Http\Controllers\Sales\ContactController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\DealController;
use App\Http\Controllers\Sales\SalesDashboardController;
use App\Http\Controllers\Settings\OrganizationSettingsController;
use App\Http\Controllers\Settings\OrganizationStructureController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('dashboard');

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
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store'])
        ->middleware(['permission:payments.create', 'password.confirm', 'throttle:10,1'])
        ->name('invoices.payments.store');
    Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])
        ->middleware(['permission:payments.reverse', 'password.confirm', 'throttle:10,1'])
        ->name('payments.reverse');
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
