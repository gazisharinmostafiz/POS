<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Areas\AreaController;
use App\Http\Controllers\Counter\CounterBillingController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Kitchen\KitchenDisplayController;
use App\Http\Controllers\Platform\BackupController as PlatformBackupController;
use App\Http\Controllers\Platform\ServerMigrationController;
use App\Http\Controllers\Platform\MaintenanceController;
use App\Http\Controllers\Platform\ReleaseNoteController as PlatformReleaseNoteController;
use App\Http\Controllers\Platform\VendorController;
use App\Http\Controllers\Tenant\BackupController as TenantBackupController;
use App\Http\Controllers\Tenant\ChatMessageController;
use App\Http\Controllers\Tenant\DashboardReportController;
use App\Http\Controllers\Tenant\MenuCategoryController;
use App\Http\Controllers\Tenant\MenuItemController;
use App\Http\Controllers\Tenant\PrinterController;
use App\Http\Controllers\Tenant\RestaurantSettingsController;
use App\Http\Controllers\Tenant\ReleaseNoteController as TenantReleaseNoteController;
use App\Http\Controllers\Tenant\VendorBillingController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\Waiter\WaiterPosController;
use App\Support\Roles;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::prefix('platform')
        ->name('platform.')
        ->middleware(['role:'.Roles::PLATFORM_OWNER, 'can:access-platform-area'])
        ->group(function () {
            Route::get('/', [DashboardController::class, 'overview'])->name('overview');
            Route::get('/health', [DashboardController::class, 'health'])->name('health');
            Route::get('/subscriptions', [DashboardController::class, 'subscriptions'])->name('subscriptions');
            Route::get('/support-access-logs', [DashboardController::class, 'supportAccessLogs'])->name('support-access-logs');
            Route::get('/backups', [PlatformBackupController::class, 'index'])->name('backups.index');
            Route::post('/backups', [PlatformBackupController::class, 'store'])->name('backups.store');
            Route::get('/backups/{backup}/download', [PlatformBackupController::class, 'download'])->name('backups.download');
            Route::post('/backups/{backup}/restore', [PlatformBackupController::class, 'restore'])->name('backups.restore');
            Route::get('/releases', [PlatformReleaseNoteController::class, 'index'])->name('releases.index');
            Route::post('/releases', [PlatformReleaseNoteController::class, 'store'])->name('releases.store');
            Route::get('/maintenance', [MaintenanceController::class, 'show'])->name('maintenance.show');
            Route::post('/maintenance', [MaintenanceController::class, 'update'])->name('maintenance.update');
            Route::get('/migrations', [ServerMigrationController::class, 'index'])->name('migrations.index');
            Route::post('/migrations', [ServerMigrationController::class, 'store'])->name('migrations.store');
            Route::get('/migrations/{migration}/download', [ServerMigrationController::class, 'download'])->name('migrations.download');
            Route::post('/migrations/{migration}/restore', [ServerMigrationController::class, 'restore'])->name('migrations.restore');
            Route::post('/migrations/credentials', [ServerMigrationController::class, 'credentials'])->name('migrations.credentials');
            Route::post('/migrations/maintenance', [ServerMigrationController::class, 'maintenance'])->name('migrations.maintenance');

            Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
            Route::get('/vendors/create', [VendorController::class, 'create'])->name('vendors.create');
            Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
            Route::get('/vendors/{tenant}/settings', [RestaurantSettingsController::class, 'edit'])->name('vendors.settings.edit');
            Route::put('/vendors/{tenant}/settings', [RestaurantSettingsController::class, 'update'])->name('vendors.settings.update');
            Route::get('/vendors/{tenant}/menu/categories', [MenuCategoryController::class, 'index'])->name('vendors.menu.categories.index');
            Route::get('/vendors/{tenant}/menu/categories/create', [MenuCategoryController::class, 'create'])->name('vendors.menu.categories.create');
            Route::post('/vendors/{tenant}/menu/categories', [MenuCategoryController::class, 'store'])->name('vendors.menu.categories.store');
            Route::get('/vendors/menu/categories/{category}/edit', [MenuCategoryController::class, 'edit'])->name('vendors.menu.categories.edit');
            Route::put('/vendors/menu/categories/{category}', [MenuCategoryController::class, 'update'])->name('vendors.menu.categories.update');
            Route::delete('/vendors/menu/categories/{category}', [MenuCategoryController::class, 'destroy'])->name('vendors.menu.categories.destroy');
            Route::get('/vendors/{tenant}/menu/items', [MenuItemController::class, 'index'])->name('vendors.menu.items.index');
            Route::get('/vendors/{tenant}/menu/items/create', [MenuItemController::class, 'create'])->name('vendors.menu.items.create');
            Route::post('/vendors/{tenant}/menu/items', [MenuItemController::class, 'store'])->name('vendors.menu.items.store');
            Route::get('/vendors/menu/items/{item}/edit', [MenuItemController::class, 'edit'])->name('vendors.menu.items.edit');
            Route::put('/vendors/menu/items/{item}', [MenuItemController::class, 'update'])->name('vendors.menu.items.update');
            Route::patch('/vendors/menu/items/{item}/availability', [MenuItemController::class, 'toggleAvailability'])->name('vendors.menu.items.availability');
            Route::patch('/vendors/menu/items/{item}/visibility', [MenuItemController::class, 'toggleActive'])->name('vendors.menu.items.visibility');
            Route::delete('/vendors/menu/items/{item}', [MenuItemController::class, 'destroy'])->name('vendors.menu.items.destroy');
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
            Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');
            Route::patch('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
            Route::patch('/users/{user}/password', [UserManagementController::class, 'resetPassword'])->name('users.password');
            Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
            Route::get('/vendors/{tenant}/users', [UserManagementController::class, 'index'])->name('vendors.users.index');
            Route::get('/vendors/{tenant}/users/create', [UserManagementController::class, 'create'])->name('vendors.users.create');
            Route::post('/vendors/{tenant}/users', [UserManagementController::class, 'store'])->name('vendors.users.store');
            Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
            Route::get('/vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('vendors.edit');
            Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
            Route::patch('/vendors/{vendor}/suspend', [VendorController::class, 'suspend'])->name('vendors.suspend');
            Route::patch('/vendors/{vendor}/reactivate', [VendorController::class, 'reactivate'])->name('vendors.reactivate');
        });

    Route::get('/tenant/admin', [DashboardReportController::class, 'index'])
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN])
        ->can('access-tenant-admin-area')
        ->name('areas.tenant-admin');

    Route::get('/tenant/reports/data', [DashboardReportController::class, 'data'])
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN])
        ->can('access-tenant-admin-area')
        ->name('tenant.reports.data');

    Route::get('/tenant/release-notes', [TenantReleaseNoteController::class, 'index'])
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN])
        ->can('access-tenant-admin-area')
        ->name('tenant.release-notes.index');

    Route::prefix('tenant/billing')
        ->name('tenant.billing.')
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN, 'can:access-tenant-admin-area'])
        ->group(function () {
            Route::get('/', [VendorBillingController::class, 'show'])->name('show');
            Route::post('/subscribe', [VendorBillingController::class, 'subscribe'])->name('subscribe');
            Route::patch('/plan', [VendorBillingController::class, 'changePlan'])->name('plan');
            Route::delete('/subscription', [VendorBillingController::class, 'cancel'])->name('cancel');
        });

    Route::prefix('tenant/backups')
        ->name('tenant.backups.')
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN, 'can:access-tenant-admin-area'])
        ->group(function () {
            Route::get('/', [TenantBackupController::class, 'index'])->name('index');
            Route::post('/', [TenantBackupController::class, 'store'])->name('store');
            Route::get('/{backup}/download', [TenantBackupController::class, 'download'])->name('download');
            Route::post('/{backup}/restore', [TenantBackupController::class, 'restore'])->name('restore');
        });

    Route::get('/tenant/settings/restaurant', [RestaurantSettingsController::class, 'edit'])
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN])
        ->can('access-tenant-admin-area')
        ->name('tenant.settings.restaurant.edit');

    Route::prefix('tenant/chat')
        ->name('tenant.chat.')
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN.','.Roles::WAITER.','.Roles::COUNTER.','.Roles::KITCHEN])
        ->group(function () {
            Route::get('/rooms', [ChatMessageController::class, 'rooms'])->name('rooms');
            Route::get('/rooms/{room}/messages', [ChatMessageController::class, 'index'])->name('messages.index');
            Route::post('/rooms/{room}/messages', [ChatMessageController::class, 'store'])->name('messages.store');
        });

    Route::put('/tenant/settings/restaurant', [RestaurantSettingsController::class, 'update'])
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN])
        ->can('access-tenant-admin-area')
        ->name('tenant.settings.restaurant.update');

    Route::prefix('tenant/menu')
        ->name('tenant.menu.')
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN, 'can:access-tenant-admin-area'])
        ->group(function () {
            Route::get('/categories', [MenuCategoryController::class, 'index'])->name('categories.index');
            Route::get('/categories/create', [MenuCategoryController::class, 'create'])->name('categories.create');
            Route::post('/categories', [MenuCategoryController::class, 'store'])->name('categories.store');
            Route::get('/categories/{category}/edit', [MenuCategoryController::class, 'edit'])->name('categories.edit');
            Route::put('/categories/{category}', [MenuCategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [MenuCategoryController::class, 'destroy'])->name('categories.destroy');

            Route::get('/items', [MenuItemController::class, 'index'])->name('items.index');
            Route::get('/items/create', [MenuItemController::class, 'create'])->name('items.create');
            Route::post('/items', [MenuItemController::class, 'store'])->name('items.store');
            Route::get('/items/{item}/edit', [MenuItemController::class, 'edit'])->name('items.edit');
            Route::put('/items/{item}', [MenuItemController::class, 'update'])->name('items.update');
            Route::patch('/items/{item}/availability', [MenuItemController::class, 'toggleAvailability'])->name('items.availability');
            Route::patch('/items/{item}/visibility', [MenuItemController::class, 'toggleActive'])->name('items.visibility');
            Route::delete('/items/{item}', [MenuItemController::class, 'destroy'])->name('items.destroy');
        });

    Route::prefix('tenant/printers')
        ->name('tenant.printers.')
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN, 'can:access-tenant-admin-area'])
        ->group(function () {
            Route::get('/', [PrinterController::class, 'index'])->name('index');
            Route::post('/', [PrinterController::class, 'store'])->name('store');
            Route::put('/{printer}', [PrinterController::class, 'update'])->name('update');
            Route::post('/{printer}/test', [PrinterController::class, 'test'])->name('test');
            Route::post('/{printer}/connection-test', [PrinterController::class, 'connectionTest'])->name('connection-test');
            Route::post('/jobs/{job}/retry', [PrinterController::class, 'retry'])->name('jobs.retry');
        });

    Route::prefix('tenant/users')
        ->name('tenant.users.')
        ->middleware(['tenant.required', 'role:'.Roles::ADMIN.','.Roles::SUPER_ADMIN, 'can:access-tenant-admin-area'])
        ->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::get('/create', [UserManagementController::class, 'create'])->name('create');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            Route::patch('/{user}/activate', [UserManagementController::class, 'activate'])->name('activate');
            Route::patch('/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('deactivate');
            Route::patch('/{user}/password', [UserManagementController::class, 'resetPassword'])->name('password');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
        });

    Route::get('/waiter/pos', [AreaController::class, 'waiter'])
        ->middleware(['tenant.required', 'role:'.Roles::WAITER])
        ->can('access-waiter-pos')
        ->name('areas.waiter');

    Route::prefix('waiter/pos')
        ->name('waiter.pos.')
        ->middleware(['tenant.required', 'role:'.Roles::WAITER, 'can:access-waiter-pos'])
        ->group(function () {
            Route::get('/data', [WaiterPosController::class, 'data'])->name('data');
            Route::post('/orders', [WaiterPosController::class, 'store'])->name('orders.store');
            Route::post('/orders/addon', [WaiterPosController::class, 'storeAddon'])->name('orders.addon');
            Route::get('/tables/{tableNumber}/active-order', [WaiterPosController::class, 'activeTableOrder'])->name('tables.active-order');
        });

    Route::get('/counter', [AreaController::class, 'counter'])
        ->middleware(['tenant.required', 'role:'.Roles::COUNTER.','.Roles::ADMIN.','.Roles::SUPER_ADMIN])
        ->can('access-counter-screen')
        ->name('areas.counter');

    Route::prefix('counter')
        ->name('counter.')
        ->middleware(['tenant.required', 'role:'.Roles::COUNTER.','.Roles::ADMIN.','.Roles::SUPER_ADMIN, 'can:access-counter-screen'])
        ->group(function () {
            Route::get('/data', [CounterBillingController::class, 'data'])->name('data');
            Route::get('/bills/{order}', [CounterBillingController::class, 'bill'])->name('bills.show');
            Route::post('/bills/{order}/discount', [CounterBillingController::class, 'discount'])->name('bills.discount');
            Route::post('/bills/{order}/split', [CounterBillingController::class, 'split'])->name('bills.split');
            Route::post('/bills/{order}/payment', [CounterBillingController::class, 'payment'])->name('bills.payment');
        });

    Route::get('/kitchen', [AreaController::class, 'kitchen'])
        ->middleware(['tenant.required', 'role:'.Roles::KITCHEN])
        ->can('access-kitchen-screen')
        ->name('areas.kitchen');

    Route::prefix('kitchen')
        ->name('kitchen.')
        ->middleware(['tenant.required', 'role:'.Roles::KITCHEN, 'can:access-kitchen-screen'])
        ->group(function () {
            Route::get('/data', [KitchenDisplayController::class, 'data'])->name('data');
            Route::patch('/tickets/{ticket}/status', [KitchenDisplayController::class, 'updateStatus'])->name('tickets.status');
        });
});
