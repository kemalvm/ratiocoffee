<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\DelegationController;
use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes – Ratio Coffee ERP  v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware('log.activity')->group(function () {

    // ── Public ────────────────────────────────────────────────────────────
    Route::prefix('setup')->group(function () {
        Route::get('status',  [\App\Http\Controllers\Api\V1\SetupController::class, 'status']);
        Route::post('install', [\App\Http\Controllers\Api\V1\SetupController::class, 'install']);
    });

    Route::post('pos/midtrans/notification', [\App\Http\Controllers\Api\V1\MidtransController::class, 'notification']);

    Route::prefix('auth')->group(function () {
        Route::post('login',      [AuthController::class, 'login']);
        Route::post('mfa/verify', [AuthController::class, 'verifyMfa']);
        Route::post('mfa/otp',    [AuthController::class, 'sendMfaOtp']);
    });

    // ── Protected ─────────────────────────────────────────────────────────
    Route::middleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        'auth:sanctum,web',
        'log.activity'
    ])->prefix('auth')->group(function () {
        Route::post('logout',              [AuthController::class, 'logout']);
        Route::get('me',                   [AuthController::class, 'me']);
        Route::put('profile',              [AuthController::class, 'updateProfile']);
        Route::put('password',             [AuthController::class, 'changePassword']);

        // Security & MFA Settings
        Route::get('security',             [AuthController::class, 'getSecurityStatus']);
        Route::post('security/pin',        [AuthController::class, 'updatePin']);
        Route::delete('security/pin',      [AuthController::class, 'deletePin']);
        Route::post('security/otp',        [AuthController::class, 'toggleEmailOtp']);
        Route::post('security/fingerprint',[AuthController::class, 'registerFingerprint']);
        Route::delete('security/fingerprint',[AuthController::class, 'deleteFingerprint']);
    });

    Route::middleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        'auth:sanctum,web',
        'log.activity'
    ])->group(function () {

        // ── Brand Settings (Sidebar Identity & HQ Control Center) ─────────
        Route::get('brand-settings',       [\App\Http\Controllers\Api\V1\BrandSettingController::class, 'show']);
        Route::post('brand-settings',      [\App\Http\Controllers\Api\V1\BrandSettingController::class, 'update'])->middleware('permission:hq.update');
        Route::put('brand-settings',       [\App\Http\Controllers\Api\V1\BrandSettingController::class, 'update'])->middleware('permission:hq.update');
        Route::post('brand-settings/logo', [\App\Http\Controllers\Api\V1\BrandSettingController::class, 'uploadLogo'])->middleware('permission:hq.update');

        // ── Suppliers ─────────────────────────────────────────────────────
        Route::get('suppliers/categories',                  [\App\Http\Controllers\Api\V1\SupplierController::class, 'categories'])->middleware('permission:supplier.read');
        Route::get('suppliers',                             [\App\Http\Controllers\Api\V1\SupplierController::class, 'index'])->middleware('permission:supplier.read');
        Route::post('suppliers',                            [\App\Http\Controllers\Api\V1\SupplierController::class, 'store'])->middleware('permission:supplier.create');
        Route::get('suppliers/{supplier}',                  [\App\Http\Controllers\Api\V1\SupplierController::class, 'show'])->middleware('permission:supplier.read');
        Route::put('suppliers/{supplier}',                  [\App\Http\Controllers\Api\V1\SupplierController::class, 'update'])->middleware('permission:supplier.update');
        Route::patch('suppliers/{supplier}',                [\App\Http\Controllers\Api\V1\SupplierController::class, 'update'])->middleware('permission:supplier.update');
        Route::delete('suppliers/{supplier}',               [\App\Http\Controllers\Api\V1\SupplierController::class, 'destroy'])->middleware('permission:supplier.delete');
        Route::post('suppliers/{supplier}/extend-due-date', [\App\Http\Controllers\Api\V1\SupplierController::class, 'extendDueDate'])->middleware('permission:supplier.update');

        // ── Product Categories & Products ──────────────────────────────────
        Route::get('product-categories',                      [\App\Http\Controllers\Api\V1\ProductCategoryController::class, 'index'])->middleware('permission:product.read');
        Route::post('product-categories',                     [\App\Http\Controllers\Api\V1\ProductCategoryController::class, 'store'])->middleware('permission:product.create');
        Route::put('product-categories/{productCategory}',    [\App\Http\Controllers\Api\V1\ProductCategoryController::class, 'update'])->middleware('permission:product.update');
        Route::delete('product-categories/{productCategory}', [\App\Http\Controllers\Api\V1\ProductCategoryController::class, 'destroy'])->middleware('permission:product.delete');

        Route::get('products',                             [\App\Http\Controllers\Api\V1\ProductController::class, 'index'])->middleware('permission:product.read');
        Route::get('products/{product}',                   [\App\Http\Controllers\Api\V1\ProductController::class, 'show'])->middleware('permission:product.read');
        Route::post('products',                            [\App\Http\Controllers\Api\V1\ProductController::class, 'store'])->middleware('permission:product.create');
        Route::put('products/{product}',                   [\App\Http\Controllers\Api\V1\ProductController::class, 'update'])->middleware('permission:product.update');
        Route::delete('products/{product}',                [\App\Http\Controllers\Api\V1\ProductController::class, 'destroy'])->middleware('permission:product.delete');

        // Inventory Categories & Inventory Master
        Route::get('inventory-categories', [\App\Http\Controllers\Api\V1\IngredientController::class, 'categories']);
        Route::post('inventory-categories', [\App\Http\Controllers\Api\V1\IngredientController::class, 'storeCategory'])->middleware('permission:inventory.create');
        Route::put('inventory-categories/{category}', [\App\Http\Controllers\Api\V1\IngredientController::class, 'updateCategory'])->middleware('permission:inventory.update');
        Route::delete('inventory-categories/{category}', [\App\Http\Controllers\Api\V1\IngredientController::class, 'destroyCategory'])->middleware('permission:inventory.delete');

        Route::prefix('inventory')->middleware('permission:inventory.read')->group(function () {
            Route::get('dashboard',                  [\App\Http\Controllers\Api\V1\InventoryController::class, 'dashboard']);
            Route::get('ingredients',                [\App\Http\Controllers\Api\V1\IngredientController::class, 'index']);
            Route::get('ingredients/{ingredient}',   [\App\Http\Controllers\Api\V1\IngredientController::class, 'show']);
            Route::get('categories',                 [\App\Http\Controllers\Api\V1\IngredientController::class, 'categories']);
            Route::get('units',                      [\App\Http\Controllers\Api\V1\IngredientController::class, 'units']);
            Route::get('stock-movements',            [\App\Http\Controllers\Api\V1\InventoryController::class, 'movements']);
            Route::get('alerts',                     [\App\Http\Controllers\Api\V1\InventoryController::class, 'alerts']);
            Route::get('warehouses',                 fn() => response()->json(\App\Models\Warehouse::with('outlet')->get()));

            Route::middleware('permission:inventory.create')->group(function () {
                Route::post('ingredients',   [\App\Http\Controllers\Api\V1\IngredientController::class, 'store']);
                Route::post('categories',    [\App\Http\Controllers\Api\V1\IngredientController::class, 'storeCategory']);
                Route::post('units',         [\App\Http\Controllers\Api\V1\IngredientController::class, 'storeUnit']);
                Route::post('stock-in',      [\App\Http\Controllers\Api\V1\InventoryController::class, 'stockIn']);
                Route::post('ingredients/{ingredient}/stock-in', [\App\Http\Controllers\Api\V1\InventoryController::class, 'stockIn']);
                Route::post('stock-out',     [\App\Http\Controllers\Api\V1\InventoryController::class, 'stockOut']);
                Route::post('adjustment',    [\App\Http\Controllers\Api\V1\InventoryController::class, 'adjustment']);
                Route::post('waste',         [\App\Http\Controllers\Api\V1\InventoryController::class, 'waste']);
                Route::post('transfer',      [\App\Http\Controllers\Api\V1\InventoryController::class, 'transfer']);
                Route::post('opname',        [\App\Http\Controllers\Api\V1\InventoryController::class, 'opname']);
            });

            Route::middleware('permission:inventory.update')->group(function () {
                Route::patch('ingredients/{ingredient}', [\App\Http\Controllers\Api\V1\IngredientController::class, 'update']);
                Route::put('ingredients/{ingredient}',   [\App\Http\Controllers\Api\V1\IngredientController::class, 'update']);
                Route::put('categories/{category}',      [\App\Http\Controllers\Api\V1\IngredientController::class, 'updateCategory']);
                Route::put('units/{unit}',               [\App\Http\Controllers\Api\V1\IngredientController::class, 'updateUnit']);
                Route::put('stock-movements/{id}',       [\App\Http\Controllers\Api\V1\InventoryController::class, 'updateMovement']);
            });

            Route::middleware('permission:inventory.delete')->group(function () {
                Route::delete('ingredients/{ingredient}', [\App\Http\Controllers\Api\V1\IngredientController::class, 'destroy']);
                Route::delete('categories/{category}',     [\App\Http\Controllers\Api\V1\IngredientController::class, 'destroyCategory']);
                Route::delete('units/{unit}',              [\App\Http\Controllers\Api\V1\IngredientController::class, 'destroyUnit']);
                Route::delete('stock-movements/{id}',      [\App\Http\Controllers\Api\V1\InventoryController::class, 'deleteMovement']);
            });
        });

        Route::get('units',                [\App\Http\Controllers\Api\V1\IngredientController::class, 'units']);

        // ── CEO Dashboard & BI Chat ───────────────────────────────────────
        Route::prefix('dashboard')->group(function () {
            Route::get('ceo',              [DashboardController::class, 'ceo']);
            Route::get('sold-products',    [DashboardController::class, 'soldProducts']);
            Route::get('payment-methods',  [DashboardController::class, 'paymentMethods']);
            Route::get('average-stats',    [DashboardController::class, 'averageStats']);
            Route::post('bi-chat',         [DashboardController::class, 'biChat']);
        });

        // ── Users (CEO only) ──────────────────────────────────────────────
        Route::apiResource('users', UserController::class)->middleware('permission:user.manage');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('permission:user.manage');

        // ── Delegations (CEO only) ────────────────────────────────────────
        Route::apiResource('delegations', DelegationController::class)->middleware('permission:delegation.manage');

        // ── Activity Logs ─────────────────────────────────────────────────
        Route::get('activity-logs', [ActivityLogController::class, 'index']);

        // ── Roles (open list for dropdowns) ───────────────────────────────
        Route::get('roles', fn() => response()->json(\App\Models\Role::orderBy('name')->get()));

        // ── Research Products (Consultant + CEO) ───────────────────────────
        Route::prefix('research-products')->middleware('permission:research_product.read')->group(function () {
            Route::get('/',                 [\App\Http\Controllers\Api\V1\ResearchProductController::class, 'index']);
            Route::get('/{id}',             [\App\Http\Controllers\Api\V1\ResearchProductController::class, 'show']);
            Route::post('/',                [\App\Http\Controllers\Api\V1\ResearchProductController::class, 'store'])->middleware('permission:research_product.create');
            Route::put('/{id}',             [\App\Http\Controllers\Api\V1\ResearchProductController::class, 'update'])->middleware('permission:research_product.update');
            Route::delete('/{id}',          [\App\Http\Controllers\Api\V1\ResearchProductController::class, 'destroy'])->middleware('permission:research_product.delete');
            Route::post('/{id}/deduct-stock', [\App\Http\Controllers\Api\V1\ResearchProductController::class, 'deductStock'])->middleware('permission:research_product.update');
            Route::post('/{id}/convert-to-product', [\App\Http\Controllers\Api\V1\ResearchProductController::class, 'convertToProduct'])->middleware('permission:research_product.update');
        });

        // ── Admin Fee (CEO propose, Operator review) ──────────────────────
        Route::prefix('admin-fee')->middleware('permission:admin_fee.read')->group(function () {
            Route::get('settings',                      [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'settings']);
            Route::get('proposals',                     [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'proposals']);
            Route::post('proposals',                    [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'propose'])->middleware('permission:admin_fee.create');
            Route::put('proposals/{id}/review',         [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'review'])->middleware('permission:admin_fee.approve');
            Route::get('revenue-sharing',               [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'revenueSharing']);
            Route::post('pay-sharing',                  [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'paySharing']);
            Route::put('confirm-sharing/{expenseId}',   [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'confirmSharing']);
            Route::get('pending-verifications',         [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'pendingVerifications']);
            Route::put('verify-payment/{paymentId}',    [\App\Http\Controllers\Api\V1\AdminFeeController::class, 'verifyPayment'])->middleware('permission:admin_fee.approve');
        });

        // ── Expenses (CEO payment reminders & Categories fallback) ─────────
        Route::get('expense-categories', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'categories'])->middleware('permission:finance.read');
        Route::prefix('expenses')->middleware('permission:finance.read')->group(function () {
            Route::get('/',                   [\App\Http\Controllers\Api\V1\ExpenseController::class, 'index']);
            Route::post('/',                  [\App\Http\Controllers\Api\V1\ExpenseController::class, 'store'])->middleware('permission:finance.create');
            Route::put('/{id}',               [\App\Http\Controllers\Api\V1\ExpenseController::class, 'update'])->middleware('permission:finance.update');
            Route::delete('/{id}',            [\App\Http\Controllers\Api\V1\ExpenseController::class, 'destroy'])->middleware('permission:finance.delete');
            Route::get('/payments',           [\App\Http\Controllers\Api\V1\ExpenseController::class, 'payments']);
            Route::post('/{id}/mark-paid',    [\App\Http\Controllers\Api\V1\ExpenseController::class, 'markPaid'])->middleware('permission:finance.update');
            Route::post('/{id}/mark-unpaid',  [\App\Http\Controllers\Api\V1\ExpenseController::class, 'markUnpaid'])->middleware('permission:finance.update');
            Route::post('/{id}/confirm',      [\App\Http\Controllers\Api\V1\ExpenseController::class, 'confirmPayment']);
        });


        // ── Outlets ───────────────────────────────────────────────────────
        // Route::apiResource('outlets', \App\Http\Controllers\Api\V1\OutletController::class)
        //    ->middleware('permission:outlet.manage');

        // ── Purchasing ────────────────────────────────────────────────────
        Route::prefix('purchase')->middleware('permission:purchase.read')->group(function () {
            Route::apiResource('requests', \App\Http\Controllers\Api\V1\PurchaseRequestController::class);
            Route::apiResource('orders',   \App\Http\Controllers\Api\V1\PurchaseOrderController::class);
            Route::post('orders/{order}/approve',  [\App\Http\Controllers\Api\V1\PurchaseOrderController::class, 'approve'])
                ->middleware('permission:purchase.approve');
            Route::post('orders/{order}/receive',  [\App\Http\Controllers\Api\V1\PurchaseOrderController::class, 'receive']);
        });

        // ── Products & Recipes ────────────────────────────────────────────
        Route::middleware('permission:product.read')->group(function () {
            // Route::apiResource('products.variants', \App\Http\Controllers\Api\V1\ProductVariantController::class);
            Route::apiResource('recipes', \App\Http\Controllers\Api\V1\RecipeController::class);
            Route::post('recipes/product/{product}', [\App\Http\Controllers\Api\V1\RecipeController::class, 'storeProductRecipe'])
                ->middleware('permission:product.update');
            Route::post('recipes/{recipe}/recalculate', [\App\Http\Controllers\Api\V1\RecipeController::class, 'recalculate'])
                ->middleware('permission:product.update');
        });

        // ── POS ───────────────────────────────────────────────────────────
        Route::prefix('pos')->middleware('permission:pos.read')->group(function () {
            Route::get('orders',                     [\App\Http\Controllers\Api\V1\PosOrderController::class, 'index']);
            Route::get('orders/{order}',             [\App\Http\Controllers\Api\V1\PosOrderController::class, 'show']);
            Route::post('orders',                    [\App\Http\Controllers\Api\V1\PosOrderController::class, 'store'])->middleware('permission:pos.create');
            Route::post('orders/{order}/pay',        [\App\Http\Controllers\Api\V1\PosOrderController::class, 'pay'])->middleware('permission:pos.create');
            Route::post('orders/{order}/midtrans-token', [\App\Http\Controllers\Api\V1\MidtransController::class, 'getToken'])->middleware('permission:pos.create');
            Route::post('orders/{order}/update-status', [\App\Http\Controllers\Api\V1\PosOrderController::class, 'updateStatus'])->middleware('permission:pos.create');
            Route::put('orders/{order}/status', [\App\Http\Controllers\Api\V1\PosOrderController::class, 'updateStatus'])->middleware('permission:pos.create');
            Route::post('orders/{order}/update-type', [\App\Http\Controllers\Api\V1\PosOrderController::class, 'updateOrderType'])->middleware('permission:pos.create');
            Route::put('orders/{order}/update-type', [\App\Http\Controllers\Api\V1\PosOrderController::class, 'updateOrderType'])->middleware('permission:pos.create');
            Route::delete('orders/{order}',         [\App\Http\Controllers\Api\V1\PosOrderController::class, 'destroy'])->middleware('permission:pos.create');
            Route::post('orders/{order}/void',       [\App\Http\Controllers\Api\V1\PosOrderController::class, 'void'])->middleware('permission:pos.void');
            Route::post('orders/{order}/refund',     [\App\Http\Controllers\Api\V1\PosOrderController::class, 'refund'])->middleware('permission:pos.refund');
            Route::post('orders/{order}/hold',       [\App\Http\Controllers\Api\V1\PosOrderController::class, 'hold'])->middleware('permission:pos.create');
            Route::post('orders/{order}/resume',     [\App\Http\Controllers\Api\V1\PosOrderController::class, 'resume'])->middleware('permission:pos.create');
            Route::post('orders/{order}/add-items',  [\App\Http\Controllers\Api\V1\PosOrderController::class, 'addItems'])->middleware('permission:pos.create');
            Route::get('orders/{order}/receipt',     [\App\Http\Controllers\Api\V1\PosOrderController::class, 'receipt']);
            
            // Route::get('customers',                  [\App\Http\Controllers\Api\V1\CustomerController::class, 'index']);
            // Route::post('customers',                 [\App\Http\Controllers\Api\V1\CustomerController::class, 'store']);
        });

        // ── Finance ───────────────────────────────────────────────────────
        Route::prefix('finance')->middleware('permission:finance.read')->group(function () {
            Route::get('kpi',                    [DashboardController::class, 'ceo']);
            Route::get('summary',                [\App\Http\Controllers\Api\V1\FinanceController::class, 'summary']);
            Route::get('expense-categories',     [\App\Http\Controllers\Api\V1\ExpenseController::class, 'categories']);
            Route::post('expense-categories',    [\App\Http\Controllers\Api\V1\ExpenseController::class, 'storeCategory']);
            Route::apiResource('accounts',       \App\Http\Controllers\Api\V1\AccountController::class);
            Route::apiResource('journal-entries', \App\Http\Controllers\Api\V1\JournalEntryController::class);
            Route::apiResource('expenses',       \App\Http\Controllers\Api\V1\ExpenseController::class);
            Route::get('daily-closings',         [\App\Http\Controllers\Api\V1\FinanceController::class, 'dailyClosings']);
            Route::post('daily-closings',        [\App\Http\Controllers\Api\V1\FinanceController::class, 'closingStore']);
            Route::get('profit-loss',            [\App\Http\Controllers\Api\V1\FinanceController::class, 'profitLoss']);
            Route::get('cash-flow',              [\App\Http\Controllers\Api\V1\FinanceController::class, 'cashFlow']);
            Route::get('transactions',           [\App\Http\Controllers\Api\V1\FinanceController::class, 'transactions']);
        });



        // ── Tax Settings (Resto Tax - CEO Controlled) ─────────────────────
        Route::get('tax-settings',  [\App\Http\Controllers\Api\V1\TaxSettingController::class, 'show']);
        Route::post('tax-settings', [\App\Http\Controllers\Api\V1\TaxSettingController::class, 'update'])->middleware('permission:tax_settings.update');
        Route::put('tax-settings',  [\App\Http\Controllers\Api\V1\TaxSettingController::class, 'update'])->middleware('permission:tax_settings.update');

        // ── Reporting ─────────────────────────────────────────────────────────
        Route::prefix('reports')->middleware('permission:reports.read')->group(function () {
            Route::get('sales',      [\App\Http\Controllers\Api\V1\ReportController::class, 'sales']);
            Route::get('inventory',  [\App\Http\Controllers\Api\V1\ReportController::class, 'inventory']);
            Route::get('profit-loss',[\App\Http\Controllers\Api\V1\ReportController::class, 'profitLoss']);
            Route::get('top-menu',   [\App\Http\Controllers\Api\V1\ReportController::class, 'topMenu']);
            // legacy export fallback
            Route::get('export',     [\App\Http\Controllers\Api\V1\ReportController::class, 'exportExcel']);
        });

        // ── Global Search ─────────────────────────────────────────────────
        // Route::get('search', [\App\Http\Controllers\Api\V1\SearchController::class, 'global']);

        // ── Notifications ─────────────────────────────────────────────────
        Route::get('notifications',             [\App\Http\Controllers\Api\V1\DashboardController::class, 'notifications']);
        Route::patch('notifications/{id}/read', fn($id) => response()->json(['ok' => true]));
        Route::post('notifications/read-all',   fn() => response()->json(['ok' => true]));
    });
});