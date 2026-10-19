<?php

use App\Http\Controllers\Api\ExternalApprovalController;
use App\Http\Controllers\Api\ExternalBusinessController;
use App\Http\Controllers\Api\ExternalCatalogController;
use App\Http\Controllers\Api\ExternalDocumentController;
use App\Http\Controllers\Api\ExternalEmployeeController;
use App\Http\Controllers\Api\ExternalHrSummaryController;
use App\Http\Controllers\Api\ExternalInventoryController;
use App\Http\Controllers\Api\ExternalManufacturingController;
use App\Http\Controllers\Api\ExternalProcurementSummaryController;
use App\Http\Middleware\AuthenticateIntegrationKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/summaries/manufacturing', [ExternalManufacturingController::class, 'summary'])->middleware(AuthenticateIntegrationKey::class.':summary:manufacturing:read')->name('summaries.manufacturing');
    foreach (['boms', 'work-orders'] as $resource) {
        foreach (['' => 'index', '/{id}' => 'show', '/{id}/items' => 'items'] as $suffix => $method) {
            Route::get('/manufacturing/'.$resource.$suffix, [ExternalManufacturingController::class, $method])->defaults('resource', $resource)
                ->middleware(AuthenticateIntegrationKey::class.':manufacturing:'.$resource.':read')->name('manufacturing.'.$resource.'.'.$method);
        }
    }
    Route::get('/documents', [ExternalDocumentController::class, 'index'])->middleware(AuthenticateIntegrationKey::class.':documents:read')->name('documents.index');
    Route::get('/documents/{id}', [ExternalDocumentController::class, 'show'])->middleware(AuthenticateIntegrationKey::class.':documents:read')->name('documents.show');
    Route::get('/documents/{id}/download', [ExternalDocumentController::class, 'download'])->middleware(AuthenticateIntegrationKey::class.':documents:download')->name('documents.download');
    Route::get('/approvals', [ExternalApprovalController::class, 'index'])->middleware(AuthenticateIntegrationKey::class.':approvals:read')->name('approvals.index');
    Route::get('/approvals/{id}', [ExternalApprovalController::class, 'show'])->middleware(AuthenticateIntegrationKey::class.':approvals:read')->name('approvals.show');
    Route::get('/summaries/procurement', [ExternalProcurementSummaryController::class, 'show'])
        ->middleware(AuthenticateIntegrationKey::class.':summary:procurement:read')->name('summaries.procurement');
    foreach (ExternalBusinessController::RESOURCES as $resource => [$scope, $fields]) {
        Route::get('/'.$resource, [ExternalBusinessController::class, 'index'])->defaults('resource', $resource)
            ->middleware(AuthenticateIntegrationKey::class.':'.$scope)->name($resource.'.index');
        Route::get('/'.$resource.'/{id}', [ExternalBusinessController::class, 'show'])->defaults('resource', $resource)
            ->middleware(AuthenticateIntegrationKey::class.':'.$scope)->name($resource.'.show');
        if (isset(ExternalBusinessController::ITEMS[$resource])) {
            Route::get('/'.$resource.'/{id}/items', [ExternalBusinessController::class, 'items'])->defaults('resource', $resource)
                ->middleware(AuthenticateIntegrationKey::class.':'.$scope)->name($resource.'.items');
        }
    }
    Route::get('/projects/{id}/tasks', [ExternalBusinessController::class, 'projectTasks'])
        ->middleware(AuthenticateIntegrationKey::class.':tasks:read')->name('projects.tasks');
    foreach (['hr', 'attendance', 'leaves', 'roster'] as $domain) {
        Route::get('/summaries/'.$domain, [ExternalHrSummaryController::class, 'show'])->defaults('domain', $domain)
            ->middleware(AuthenticateIntegrationKey::class.':summary:'.$domain.':read')->name('summaries.'.$domain);
    }
    Route::get('/employees', [ExternalEmployeeController::class, 'index'])->middleware(AuthenticateIntegrationKey::class.':employees:read')->name('employees.index');
    Route::get('/employees/{id}', [ExternalEmployeeController::class, 'show'])->middleware(AuthenticateIntegrationKey::class.':employees:read')->name('employees.show');
    foreach (['attendance' => 'attendance', 'roster' => 'roster', 'leave-balances' => 'leaves', 'leave-requests' => 'leaves'] as $resource => $scope) {
        Route::get('/employees/{id}/'.$resource, [ExternalEmployeeController::class, 'operational'])->defaults('resource', $resource)
            ->middleware(AuthenticateIntegrationKey::class.':employees:'.$scope.':read')->name('employees.'.$resource);
    }
    foreach (ExternalCatalogController::REFERENCES as $resource => $definition) {
        $scope = match ($resource) {
            'warehouses' => 'inventory:read',
            'currencies' => 'reference:currencies:read',
            default => 'reference:organization:read',
        };
        Route::get('/reference/'.$resource, [ExternalCatalogController::class, 'reference'])
            ->defaults('resource', $resource)->middleware(AuthenticateIntegrationKey::class.':'.$scope)->name('reference.'.$resource);
    }
    Route::get('/products', [ExternalCatalogController::class, 'products'])->middleware(AuthenticateIntegrationKey::class.':products:read')->name('products.index');
    Route::get('/products/{id}', [ExternalCatalogController::class, 'product'])->middleware(AuthenticateIntegrationKey::class.':products:read')->name('products.show');
    Route::get('/inventory/balances', [ExternalInventoryController::class, 'balances'])->middleware(AuthenticateIntegrationKey::class.':inventory:read')->name('inventory.balances');
    Route::get('/summaries/inventory', [ExternalInventoryController::class, 'summary'])->middleware(AuthenticateIntegrationKey::class.':summary:inventory:read')->name('summaries.inventory');
});
