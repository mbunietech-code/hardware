<?php

use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FinanceController;
use App\Http\Controllers\Web\PurchaseController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\SaleController;
use App\Http\Controllers\Web\SessionController;
use App\Http\Controllers\Web\StockController;
use App\Http\Controllers\Web\SystemController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// Language switch (EN / SW), available before and after login.
Route::get('locale/{locale}', function (string $locale) {
    abort_unless(isset(SetLocale::SUPPORTED[$locale]), 404);

    return back()->withCookie(cookie()->forever('locale', $locale));
})->name('locale.switch');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('forgot-password', [AuthController::class, 'forgotForm'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:login')->name('password.email');
    Route::get('reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('profile', [AuthController::class, 'profile'])->name('profile.edit');
    Route::put('profile/password', [AuthController::class, 'password'])->name('profile.password');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Daily sessions
    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('sessions', [SessionController::class, 'open'])->name('sessions.open');
    Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
    Route::post('sessions/{session}/close', [SessionController::class, 'close'])->name('sessions.close');
    Route::post('sessions/{session}/reopen', [SessionController::class, 'reopen'])->name('sessions.reopen');

    // Sales & purchases
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    Route::post('sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
    Route::post('sales/{sale}/returns', [SaleController::class, 'storeReturn'])->name('sales.returns.store');
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::post('purchases/{purchase}/void', [PurchaseController::class, 'void'])->name('purchases.void');

    // Money
    Route::get('expenses', [FinanceController::class, 'expenses'])->name('expenses.index');
    Route::post('expenses', [FinanceController::class, 'storeExpense'])->name('expenses.store');
    Route::post('expenses/{expense}/void', [FinanceController::class, 'voidExpense'])->name('expenses.void');
    Route::get('capital', [FinanceController::class, 'capital'])->name('capital.index');
    Route::post('capital', [FinanceController::class, 'storeCapital'])->name('capital.store');
    Route::post('capital/{capital}/void', [FinanceController::class, 'voidCapital'])->name('capital.void');
    Route::get('debts', [FinanceController::class, 'debts'])->name('debts.index');
    Route::post('debts', [FinanceController::class, 'storeDebt'])->name('debts.store');
    Route::get('debts/{debt}', [FinanceController::class, 'showDebt'])->name('debts.show');
    Route::post('debts/{debt}/payments', [FinanceController::class, 'payDebt'])->name('debts.pay');
    Route::post('debts/{debt}/cancel', [FinanceController::class, 'cancelDebt'])->name('debts.cancel');
    Route::post('debts/{debt}/sms', [FinanceController::class, 'smsDebt'])->name('debts.sms');

    // Inventory
    Route::get('products', [CatalogController::class, 'products'])->name('products.index');
    Route::get('products/create', [CatalogController::class, 'createProduct'])->name('products.create');
    Route::get('products/import', [CatalogController::class, 'importForm'])->name('products.import');
    Route::post('products/import', [CatalogController::class, 'import'])->name('products.import.store');
    Route::get('products/import/template', [CatalogController::class, 'importTemplate'])->name('products.import.template');
    Route::post('products', [CatalogController::class, 'storeProduct'])->name('products.store');
    Route::get('products/{product}', [CatalogController::class, 'showProduct'])->name('products.show');
    Route::get('products/{product}/edit', [CatalogController::class, 'editProduct'])->name('products.edit');
    Route::put('products/{product}', [CatalogController::class, 'updateProduct'])->name('products.update');
    Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
    Route::post('categories', [CatalogController::class, 'storeCategory'])->name('categories.store');
    Route::put('categories/{category}', [CatalogController::class, 'updateCategory'])->name('categories.update');
    Route::get('customers', [CatalogController::class, 'customers'])->name('customers.index');
    Route::post('customers', [CatalogController::class, 'storeCustomer'])->name('customers.store');
    Route::put('customers/{customer}', [CatalogController::class, 'updateCustomer'])->name('customers.update');
    Route::get('suppliers', [CatalogController::class, 'suppliers'])->name('suppliers.index');
    Route::post('suppliers', [CatalogController::class, 'storeSupplier'])->name('suppliers.store');
    Route::put('suppliers/{supplier}', [CatalogController::class, 'updateSupplier'])->name('suppliers.update');
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
    Route::get('stock-movements', [StockController::class, 'movements'])->name('movements.index');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('notifications', [SystemController::class, 'notifications'])->name('notifications.index');
    Route::post('notifications/read-all', [SystemController::class, 'readAll'])->name('notifications.read-all');
    Route::get('audit', [SystemController::class, 'audit'])->name('audit.index');

    Route::middleware('super_admin')->group(function () {
        Route::get('allocations', [ReportController::class, 'allocations'])->name('allocations.index');
        Route::post('allocations', [ReportController::class, 'generateAllocation'])->name('allocations.store');
        Route::post('allocations/{allocation}/{status}', [ReportController::class, 'allocationStatus'])
            ->whereIn('status', ['approved', 'cancelled'])->name('allocations.status');

        Route::get('shops', [AdminController::class, 'shops'])->name('shops.index');
        Route::post('shops', [AdminController::class, 'storeShop'])->name('shops.store');
        Route::put('shops/{shop}', [AdminController::class, 'updateShop'])->name('shops.update');
        Route::get('users', [AdminController::class, 'users'])->name('users.index');
        Route::get('users/create', [AdminController::class, 'createUser'])->name('users.create');
        Route::post('users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::get('users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::put('users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::get('expense-categories', [AdminController::class, 'expenseCategories'])->name('expense-categories.index');
        Route::post('expense-categories', [AdminController::class, 'storeExpenseCategory'])->name('expense-categories.store');
        Route::put('expense-categories/{expenseCategory}', [AdminController::class, 'updateExpenseCategory'])->name('expense-categories.update');
        Route::get('settings', [AdminController::class, 'settings'])->name('settings.edit');
        Route::put('settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('settings/test-email', [AdminController::class, 'testEmail'])->name('settings.test-email');
        Route::post('settings/test-sms', [AdminController::class, 'testSms'])->name('settings.test-sms');
        Route::get('sms', [AdminController::class, 'smsLog'])->name('sms.index');
        Route::get('devices', [AdminController::class, 'devices'])->name('devices.index');
        Route::get('backups', [AdminController::class, 'backups'])->name('backups.index');
        Route::post('backups', [AdminController::class, 'runBackup'])->name('backups.store');
        Route::get('backups/{name}', [AdminController::class, 'downloadBackup'])->where('name', '[A-Za-z0-9_\-.]+\.sql\.gz')->name('backups.download');
        Route::post('devices/{device}/toggle', [AdminController::class, 'toggleDevice'])->name('devices.toggle');
        Route::get('sync', [SystemController::class, 'sync'])->name('sync.index');
        Route::get('sync/{receipt}', [SystemController::class, 'syncShow'])->name('sync.show');
        Route::post('sync/{receipt}/resolve', [SystemController::class, 'resolve'])->name('sync.resolve');
    });
});
