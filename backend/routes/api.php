<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\OperationsController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('ping', fn () => ['ok' => true, 'server_time' => now()->toIso8601String()]);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', [OperationsController::class, 'dashboard']);
        Route::get('shops', [AdminController::class, 'shops']);

        Route::middleware('super_admin')->group(function () {
            Route::get('users', [AdminController::class, 'users']);
            Route::post('users', [AdminController::class, 'storeUser']);
            Route::patch('users/{user}', [AdminController::class, 'updateUser']);
            Route::patch('users/{user}/status', [AdminController::class, 'userStatus']);
            Route::post('shops', [AdminController::class, 'storeShop']);
            Route::patch('shops/{shop}', [AdminController::class, 'updateShop']);
            Route::get('settings', [AdminController::class, 'settings']);
            Route::put('settings', [AdminController::class, 'updateSettings']);
        });

        Route::get('categories', [CatalogController::class, 'categories']);
        Route::post('categories', [CatalogController::class, 'storeCategory']);
        Route::get('expense-categories', [CatalogController::class, 'expenseCategories']);
        Route::get('products', [CatalogController::class, 'products']);
        Route::post('products', [CatalogController::class, 'storeProduct']);
        Route::get('products/{product}', [CatalogController::class, 'showProduct']);
        Route::patch('products/{product}', [CatalogController::class, 'updateProduct']);
        Route::get('customers', [CatalogController::class, 'customers']);
        Route::post('customers', [CatalogController::class, 'storeCustomer']);
        Route::get('suppliers', [CatalogController::class, 'suppliers']);
        Route::post('suppliers', [CatalogController::class, 'storeSupplier']);

        Route::get('sales', [TransactionController::class, 'sales']);
        Route::post('sales', [TransactionController::class, 'storeSale']);
        Route::get('sales/{sale}', [TransactionController::class, 'showSale']);
        Route::post('sales/{sale}/void', [TransactionController::class, 'voidSale']);
        Route::post('sales/{sale}/returns', [TransactionController::class, 'returnSale']);
        Route::get('purchases', [TransactionController::class, 'purchases']);
        Route::post('purchases', [TransactionController::class, 'storePurchase']);
        Route::get('purchases/{purchase}', [TransactionController::class, 'showPurchase']);
        Route::post('purchases/{purchase}/void', [TransactionController::class, 'voidPurchase']);
        Route::get('expenses', [TransactionController::class, 'expenses']);
        Route::post('expenses', [TransactionController::class, 'storeExpense']);
        Route::post('expenses/{expense}/void', [TransactionController::class, 'voidExpense']);
        Route::get('capital', [TransactionController::class, 'capital']);
        Route::post('capital', [TransactionController::class, 'storeCapital']);
        Route::post('capital/{capital}/void', [TransactionController::class, 'voidCapital']);
        Route::get('debts', [TransactionController::class, 'debts']);
        Route::post('debts', [TransactionController::class, 'storeDebt']);
        Route::get('debts/{debt}', [TransactionController::class, 'showDebt']);
        Route::post('debts/{debt}/payments', [TransactionController::class, 'payDebt']);

        Route::get('stock', [OperationsController::class, 'stock']);
        Route::post('stock-adjustments', [OperationsController::class, 'adjustStock']);
        Route::get('stock-movements', [OperationsController::class, 'movements']);

        Route::get('daily-sessions', [OperationsController::class, 'sessions']);
        Route::get('daily-sessions/current', [OperationsController::class, 'currentSession']);
        Route::post('daily-sessions/open', [OperationsController::class, 'openSession']);
        Route::post('daily-sessions/{session}/close', [OperationsController::class, 'closeSession']);

        Route::post('sync/push', [OperationsController::class, 'syncPush']);
        Route::get('sync/pull', [OperationsController::class, 'syncPull']);
        Route::post('sync/status', [OperationsController::class, 'syncStatus']);

        Route::get('reports/{type}', [OperationsController::class, 'report']);
        Route::get('notifications', [OperationsController::class, 'notifications']);
        Route::post('notifications/{notification}/read', [OperationsController::class, 'readNotification']);
        Route::get('audit-logs', [OperationsController::class, 'auditLogs']);
    });
});
