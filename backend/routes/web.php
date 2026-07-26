<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\OrganizationSettingsController;
use App\Http\Controllers\Settings\OrganizationStructureController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:dashboard.view'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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
    $fullPath = storage_path('app/public/'.$path);
    abort_unless(file_exists($fullPath), 404);

    return response()->file($fullPath);
})->where('path', '.*')->name('storage.local');

require __DIR__.'/auth.php';
