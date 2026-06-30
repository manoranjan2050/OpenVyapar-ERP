<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseInvoiceController;
use App\Http\Controllers\Api\SalesInvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TallyController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\TrashController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BackupSyncController;
use App\Http\Controllers\Api\ChallanController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/backups/download/{filename}', [BackupController::class, 'download'])
    ->middleware('auth:sanctum');

// Authenticated
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout',          [AuthController::class, 'logout']);
    Route::get('/auth/me',               [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Company
    Route::apiResource('companies', CompanyController::class)->except(['destroy']);

    // Products
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    Route::get('/products/{product}/stock', [ProductController::class, 'stock']);
    Route::apiResource('products', ProductController::class);

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Sales Invoices
    Route::post('/sales-invoices/{salesInvoice}/cancel', [SalesInvoiceController::class, 'cancel']);
    Route::post('/sales-invoices/{salesInvoice}/payment', [PaymentController::class, 'storeSales']);
    Route::apiResource('sales-invoices', SalesInvoiceController::class)->except(['update']);

    // Reports
    Route::get('/reports/gstr1', [ReportController::class, 'gstr1']);
    Route::get('/reports/itc', [ReportController::class, 'itc']);
    Route::get('/reports/tax-liability', [ReportController::class, 'taxLiability']);

    // Purchase Invoices
    Route::post('/purchase-invoices/{purchaseInvoice}/payment', [PaymentController::class, 'storePurchase']);
    Route::apiResource('purchase-invoices', PurchaseInvoiceController::class)->except(['update', 'destroy']);

    // Ledger
    Route::get('/ledger/customers',             [LedgerController::class, 'customersSummary']);
    Route::get('/ledger/customers/{customer}',  [LedgerController::class, 'customer']);
    Route::get('/ledger/suppliers',             [LedgerController::class, 'suppliersSummary']);
    Route::get('/ledger/suppliers/{supplier}',  [LedgerController::class, 'supplier']);
    Route::post('/ledger/payment',              [LedgerController::class, 'recordPayment']);
    Route::post('/ledger/credit-due-alert',     [LedgerController::class, 'sendCreditDueAlert']);
    Route::get('/ledger/statement/{type}/{id}', [LedgerController::class, 'statement']);

    // Stock Adjustments
    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index']);
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store']);
    Route::get('/stock-adjustments/history/{product}', [StockAdjustmentController::class, 'history']);

    // Credit Notes
    Route::get('/credit-notes', [CreditNoteController::class, 'index']);
    Route::post('/credit-notes', [CreditNoteController::class, 'store']);

    // Users & Roles
    Route::post('/users/{user}/avatar', [UserController::class, 'uploadAvatar']);
    Route::apiResource('users', UserController::class)->except(['show']);

    // Company Logo
    Route::post('/companies/{company}/logo', [CompanyController::class, 'uploadLogo']);

    // Delivery Challans
    Route::post('/challans/{challan}/dispatch', [ChallanController::class, 'dispatch']);
    Route::post('/challans/{challan}/convert',  [ChallanController::class, 'convert']);
    Route::post('/challans/{challan}/cancel',   [ChallanController::class, 'cancel']);
    Route::get('/challans/{challan}',           [ChallanController::class, 'show']);
    Route::apiResource('challans', ChallanController::class)->only(['index', 'store']);

    // Tally Export
    Route::get('/tally/export', [TallyController::class, 'export']);

    // Alerts & Notifications
    Route::get('/alerts/settings', [AlertController::class, 'settings']);
    Route::post('/alerts/settings', [AlertController::class, 'saveSettings']);
    Route::get('/alerts/rules', [AlertController::class, 'rules']);
    Route::post('/alerts/rules', [AlertController::class, 'saveRule']);
    Route::put('/alerts/rules/{id}', [AlertController::class, 'updateRule']);
    Route::delete('/alerts/rules/{id}', [AlertController::class, 'deleteRule']);
    Route::post('/alerts/test', [AlertController::class, 'test']);
    Route::post('/alerts/run/stock', [AlertController::class, 'runStockCheck']);
    Route::post('/alerts/run/overdue', [AlertController::class, 'runOverdueCheck']);

    // Activity Log
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/stats', [ActivityLogController::class, 'stats']);
    Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show']);

    // Trash / Recycle Bin
    Route::get('/trash', [TrashController::class, 'index']);
    Route::post('/trash/restore', [TrashController::class, 'restore']);
    Route::post('/trash/restore-all', [TrashController::class, 'restoreAll']);
    Route::delete('/trash/item', [TrashController::class, 'forceDelete']);
    Route::delete('/trash/empty', [TrashController::class, 'empty']);

    // Backup Sync Providers
    Route::get('/backup-sync',                      [BackupSyncController::class, 'index']);
    Route::post('/backup-sync/save',                [BackupSyncController::class, 'save']);
    Route::post('/backup-sync/test',                [BackupSyncController::class, 'test']);
    Route::post('/backup-sync/sync-now',            [BackupSyncController::class, 'syncNow']);
    Route::get('/backup-sync/google/auth-url',      [BackupSyncController::class, 'googleAuthUrl']);
    Route::get('/backup-sync/google/callback',      [BackupSyncController::class, 'googleCallback']);
    Route::get('/backup-sync/browse-folder',        [BackupSyncController::class, 'browseFolder']);

    // Backup & Restore
    Route::get('/backups', [BackupController::class, 'index']);
    Route::get('/backups/stats', [BackupController::class, 'stats']);
    Route::post('/backups/create', [BackupController::class, 'create']);
    Route::delete('/backups/{filename}', [BackupController::class, 'destroy']);
    Route::post('/backups/restore', [BackupController::class, 'restore']);
    Route::get('/backups/settings', [BackupController::class, 'getSettings']);
    Route::post('/backups/settings', [BackupController::class, 'saveSettings']);
});
