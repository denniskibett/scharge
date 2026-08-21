<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\EstateController;
use App\Http\Controllers\UnitController; 
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PayeeController;
use App\Http\Controllers\PaymentController; 
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenancyController; 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CleaningController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WaterReadingController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MpesaController;
use App\Modules\Subscriptions\Controllers\SubscriptionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AccountManagerController;
use App\Http\Controllers\PublicInvoiceController;

require __DIR__ . '/mpesa.php';

// ============================================
// PUBLIC ROUTES
// ============================================
Route::get('/', function () {
    return view('welcome');
});

// ============================================
// PUBLIC INVOICE PAYMENT ROUTES (No Auth Required)
// ============================================
Route::prefix('public')->name('public.')->group(function () {
    Route::get('/invoice/{invoice}', [PublicInvoiceController::class, 'show'])->name('invoice.show');
    Route::post('/invoice/{invoice}/pay', [PublicInvoiceController::class, 'pay'])->name('invoice.pay');
    Route::get('/invoice/{invoice}/status/{checkoutId}', [PublicInvoiceController::class, 'checkStatus'])->name('invoice.status');
});

// Email Verification Routes
// Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
// Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');
// Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

// ============================================
// AUTHENTICATION ROUTES
// ============================================
require __DIR__.'/auth.php';

// Social Login
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('login.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

// ============================================
// AUTHENTICATED ROUTES
// ============================================
Route::middleware(['auth'])->group(function () {

// ============================================
    // DASHBOARD
    // ============================================
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(['verified'])
        ->name('dashboard');

    Route::get('mtickets', function () {
        return view('mtickets');
    })->name('mtickets');


    // ============================================
    // PROFILE ROUTES
    // ============================================
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('delete-avatar');
        Route::put('/address', [ProfileController::class, 'updateAddress'])->name('address.update');
        Route::get('/data', [ProfileController::class, 'getUserData'])->name('data');
    });

    // ============================================
    // STATIC / DEMO PAGES
    // ============================================
    Route::get('/index', function () { return view('index'); })->name('index');
    Route::get('/invoice', function () { return view('invoice'); })->name('invoice');
    Route::get('/404', function () { return view('404'); })->name('404');
    Route::get('/messages', function () { return view('messages'); })->name('messages');
    Route::get('/alerts', function () { return view('alerts'); })->name('alerts');
    Route::get('/blank', function () { return view('blank'); })->name('blank');
    Route::get('/calendar', function () { return view('calendar'); })->name('calendar');
    Route::get('/form-elements', function () { return view('form-elements'); })->name('form-elements');
    Route::get('/basic-tables', function () { return view('basic-tables'); })->name('basic-tables');
    Route::get('/avatars', function () { return view('avatars'); })->name('avatars');
    Route::get('/badge', function () { return view('badge'); })->name('badge');
    Route::get('/buttons', function () { return view('buttons'); })->name('buttons');
    Route::get('/images', function () { return view('images'); })->name('images');
    Route::get('/videos', function () { return view('videos'); })->name('videos');
    Route::get('/signin', function () { return view('signin'); })->name('signin');
    Route::get('/signup', function () { return view('signup'); })->name('signup');
    Route::get('/image', function () { return view('image'); });
    Route::get('/line-chart', function () { return view('line-chart'); })->name('line-chart');
    Route::get('/bar-chart', function () { return view('bar-chart'); })->name('bar-chart');
    Route::get('/dash', function () { return view('dash'); })->name('dash');

    // ============================================
    // SYSTEM ROUTES
    // ============================================
    Route::prefix('system')->name('system.')->group(function () {
        Route::get('/', [SystemController::class, 'index'])->name('index');
        Route::put('/update', [SystemController::class, 'update'])->name('update');
        Route::get('/clear-cache', [SystemController::class, 'clearCache'])->name('clear-cache');
        Route::get('/backup', [SystemController::class, 'backupDatabase'])->name('backup');
        Route::post('/toggle-maintenance', [SystemController::class, 'toggleMaintenance'])->name('toggle-maintenance');
        Route::post('/debug', [SystemController::class, 'debug'])->name('debug');
    });

    // ============================================
    // ADMIN USER MANAGEMENT ROUTES
    // ============================================
    Route::prefix('admin/users')->name('admin.users.')->group(function () {
        Route::resource('users', App\Http\Controllers\Admin\UserController::class)
            ->parameters(['users' => 'user']);
        
        Route::post('/{user}/verify', [App\Http\Controllers\Admin\UserController::class, 'verify'])->name('verify');
        Route::post('/{user}/assign-company', [App\Http\Controllers\Admin\UserController::class, 'assignCompany'])->name('assign-company');
        Route::post('/{user}/suspend', [App\Http\Controllers\Admin\UserController::class, 'suspend'])->name('suspend');
        Route::post('/{user}/activate', [App\Http\Controllers\Admin\UserController::class, 'activate'])->name('activate');
    });

    // ============================================
    // ADMIN ROLES ROUTES
    // ============================================
    Route::prefix('admin/roles')->name('admin.roles.')->group(function () {
        Route::get('/list', [App\Http\Controllers\Admin\UserController::class, 'getRoles'])->name('list');
    });

    // ============================================
    // API USERS ROUTES
    // ============================================
    Route::prefix('api/users')->name('api.users.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\UserController::class, 'getUsers'])->name('index');
        Route::get('/staff', [App\Http\Controllers\Admin\UserController::class, 'getStaffUsers'])->name('staff');
    });

    // ============================================
    // ESTATE ROUTES
    // ============================================
    Route::resource('estates', EstateController::class);

    // ============================================
    // UNIT ROUTES
    // ============================================
    Route::resource('units', UnitController::class);
    Route::get('/units/{unit}/water-reading', [UnitController::class, 'showWaterReadingForm'])->name('units.water-reading');
    Route::put('/units/{unit}/water-reading', [UnitController::class, 'updateWaterReading'])->name('units.water-reading.update');
    Route::get('/units/{unit}/meter-reading-data', [UnitController::class, 'getMeterReadingData'])->name('units.meter-reading-data');
    Route::get('/units/{unit}/meter-reading', [UnitController::class, 'showMeterReadingForm'])->name('units.meter-reading');
    Route::put('/units/{unit}/meter-reading', [UnitController::class, 'updateMeterReading'])->name('units.meter-reading.update');

    // ============================================
    // EXPENSE ROUTES
    // ============================================
    Route::resource('expenses', ExpenseController::class);
    Route::resource('expense-categories', ExpenseCategoryController::class);
    Route::resource('payees', PayeeController::class);

    // ============================================
    // TENANT ROUTES
    // ============================================
    Route::resource('tenants', TenantController::class);
    Route::post('/tenants/bulk-store', [TenantController::class, 'bulkStore'])->name('tenants.bulkStore');
    Route::post('/tenants/{tenant}/invoices', [TenantController::class, 'storeInvoice'])->name('tenants.store.invoice');
    Route::post('/tenants/{tenant}/payments', [TenantController::class, 'storePayment'])->name('tenants.store.payment');

    // ============================================
    // TENANCY ROUTES
    // ============================================
    Route::resource('tenancies', TenancyController::class);
    Route::get('/tenancies/{tenancy}/invoice-data', [InvoiceController::class, 'getInvoiceData'])->name('tenancies.invoice-data');
    Route::get('/tenancies/{tenancy}/check-invoice-status', [InvoiceController::class, 'checkInvoiceGenerationStatus'])->name('tenancies.check-invoice-status');
    Route::post('/tenancies/{tenancy}/force-invoice', [InvoiceController::class, 'forceGenerateInvoice'])->name('tenancies.force-invoice');
    Route::get('/tenancies/{tenancy}/billing-history', [InvoiceController::class, 'getBillingHistory'])->name('tenancies.billing-history');
    Route::post('/tenancies/{tenancy}/generate-missing-invoices', [InvoiceController::class, 'generateMissingInvoices'])->name('tenancies.generate-missing-invoices');
    Route::post('/tenancies/{tenancy}/invoices/bulk-missing', [InvoiceController::class, 'generateMissingInvoicesBulk'])->name('tenancies.invoices.bulk-missing');
    Route::post('/tenancies/{tenancy}/payments', [PaymentController::class, 'store'])->name('tenancies.payments.store');

    // ============================================
    // INVOICE ROUTES
    // ============================================
    Route::resource('invoices', InvoiceController::class);

    // Invoice generation routes
    Route::post('/invoices/generate/single', [InvoiceController::class, 'generateSingleInvoice'])->name('invoices.generate.single');
    Route::post('/invoices/generate/all', [InvoiceController::class, 'generateAllInvoices'])->name('invoices.generate.all');

    // Invoice payment routes
    Route::post('/invoices/payments', [InvoiceController::class, 'processPayment'])->name('invoices.payments.store');

    // Bulk invoice operations
    Route::post('/invoices/bulk-create', [InvoiceController::class, 'bulkCreate'])->name('invoices.bulk.create');
    Route::post('/invoices/check-existing', [InvoiceController::class, 'checkExistingInvoices'])->name('invoices.check.existing');
    Route::post('/invoices/resolve-duplicates', [InvoiceController::class, 'resolveDuplicates'])->name('invoices.resolve-duplicates');
    Route::post('/invoices/bulk-reconcile', [InvoiceController::class, 'bulkReconcileWaterCharges'])->name('invoices.bulk-reconcile');

    // Invoice data routes
    Route::get('/invoices/{invoice}/edit-data', [InvoiceController::class, 'getInvoiceForEditing'])->name('invoices.edit-data');
    Route::get('/invoices/{invoice}/details', [InvoiceController::class, 'getInvoiceDetails'])->name('invoices.details');

    // Invoice item routes - MAIN (using the prefix group)
    Route::prefix('invoices/{invoice}')->group(function () {
        // CRUD operations for invoice items
        Route::post('/items', [InvoiceController::class, 'addItemToInvoice'])->name('invoices.items.store');
        Route::put('/items/{item}', [InvoiceController::class, 'updateInvoiceItem'])->name('invoices.items.update');
        Route::delete('/items/{item}', [InvoiceController::class, 'removeInvoiceItem'])->name('invoices.items.destroy');
    });

    // Tenancy-specific invoice routes
    Route::prefix('tenancies/{tenancy}')->name('tenancies.')->group(function () {
        Route::post('/invoices', [InvoiceController::class, 'storeForTenancy'])->name('invoices.store');
        Route::get('/invoices', [InvoiceController::class, 'indexForTenancy'])->name('invoices.index');
        Route::get('/invoices/check', [InvoiceController::class, 'getExistingInvoice'])->name('invoices.check');
    });

    // ============================================
    // PAYMENT ROUTES
    // ============================================
    Route::resource('payments', PaymentController::class);
    Route::post('/payments/bulk', [PaymentController::class, 'bulkStore'])->name('payments.bulk.store');
    Route::get('/payments/create-data', [PaymentController::class, 'getCreateData'])->name('payments.create-data');
    Route::get('/payments/tenant/{tenantId}/invoices', [PaymentController::class, 'getTenantInvoices'])->name('payments.tenant.invoices');
    Route::get('/api/invoices/{invoice}/details', [PaymentController::class, 'getInvoiceDetails'])->name('api.invoices.details');

    // ============================================
    // WATER READING ROUTES
    // ============================================
    Route::prefix('water')->name('water.')->group(function () {
        Route::get('/', [WaterReadingController::class, 'index'])->name('index');
        Route::post('/readings', [WaterReadingController::class, 'store'])->name('readings.store');
        Route::post('/readings/bulk', [WaterReadingController::class, 'storeBulk'])->name('readings.bulk');
        Route::post('/readings/bulk-matrix', [WaterReadingController::class, 'storeBulkMatrix'])->name('readings.bulk-matrix');
        Route::post('/readings/multi-month', [WaterReadingController::class, 'storeMultiMonth'])->name('readings.multi-month');
        Route::put('/readings/{reading}/reconcile', [WaterReadingController::class, 'reconcile'])->name('readings.reconcile');
        Route::get('/last-reading/{unitId}', [WaterReadingController::class, 'getLastReading']);
        Route::get('/unit-history/{unitId}', [WaterReadingController::class, 'getUnitWaterHistory']);
        Route::get('/unit/{unit}/statement', [WaterReadingController::class, 'statement'])->name('statement');
        Route::get('/unit/{unit}/readings', [WaterReadingController::class, 'getUnitReadings']);
        Route::post('/report', [WaterReadingController::class, 'generateReport']);
        Route::get('/api/readings/bulk', [WaterReadingController::class, 'getBulkReadings']);
        Route::get('/api/unit-readings/{unitId}', [WaterReadingController::class, 'getUnitReadingsForMonthRange']);
        Route::post('/unit/{unit}/auto-fill', [WaterReadingController::class, 'autoFillMissingMonths'])->name('unit.auto-fill');
        Route::post('/estate/auto-fill', [WaterReadingController::class, 'autoFillEstate'])->name('estate.auto-fill');
    });

    Route::get('/api/units/with-water-readings', [WaterReadingController::class, 'getUnitsWithWaterReadings'])->name('api.units.with-water-readings');

    // Meter Reader specific routes
    Route::middleware(['role:super_admin,admin,property_manager,meter_reader'])->group(function () {
        Route::get('/meter-readings', [UnitController::class, 'meterReadingsIndex'])->name('meter-readings.index');
        Route::get('/meter-readings/reports', [UnitController::class, 'meterReadingReports'])->name('meter-readings.reports');
    });

    // ============================================
    // CLEANING ROUTES
    // ============================================
    Route::middleware(['role:super_admin,admin,property_manager,cleaning_staff'])->group(function () {
        Route::get('/cleaning/tasks', [CleaningController::class, 'index'])->name('cleaning.tasks');
        Route::put('/cleaning/tasks/{task}/complete', [CleaningController::class, 'markComplete'])->name('cleaning.tasks.complete');
        Route::get('/cleaning/schedule', [CleaningController::class, 'schedule'])->name('cleaning.schedule');
    });

    // ============================================
    // MAINTENANCE ROUTES
    // ============================================
    Route::resource('maintenance', MaintenanceController::class);
    Route::get('/maintenance/unit/{unit}/history', [MaintenanceController::class, 'getUnitHistory'])->name('maintenance.unit.history');
    Route::get('/maintenance/{id}/json', [MaintenanceController::class, 'showJson'])->name('maintenance.show.json');
    Route::get('/tenant/maintenance', [MaintenanceController::class, 'tenantRequests'])->name('tenant.maintenance');
    Route::get('/maintenance/{maintenance}/edit-data', [MaintenanceController::class, 'getEditData'])->name('maintenance.edit-data');

    // Maintenance Staff routes
    Route::middleware(['role:super_admin,admin,property_manager,maintenance'])->group(function () {
        Route::get('/maintenance/assignments', [MaintenanceController::class, 'assignments'])->name('maintenance.assignments');
    });

    // ============================================
    // STAFF ROUTES
    // ============================================
    Route::resource('staff', StaffController::class);

    // ============================================
    // REPORT ROUTES
    // ============================================
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/financial', [ReportController::class, 'financial'])->name('financial');
        Route::get('/invoices', [ReportController::class, 'invoices'])->name('invoices');
        Route::get('/payments', [ReportController::class, 'payments'])->name('payments');
    });

    // ============================================
    // WALLET MODULE ROUTES - COMPLETE
    // ============================================
    
    // ===== TENANT / CUSTOMER WALLET ROUTES =====
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [App\Modules\Payments\Controllers\WalletController::class, 'index'])->name('index');
        Route::get('/balance', [App\Modules\Payments\Controllers\WalletController::class, 'getBalance'])->name('balance');
        Route::post('/deposit', [App\Modules\Payments\Controllers\WalletController::class, 'deposit'])->name('deposit');
        Route::post('/withdraw', [App\Modules\Payments\Controllers\WalletController::class, 'withdraw'])->name('withdraw');
        Route::post('/transfer', [App\Modules\Payments\Controllers\WalletController::class, 'transfer'])->name('transfer');
        Route::get('/transfer/verify/{reference}', [App\Modules\Payments\Controllers\WalletController::class, 'verifyTransfer'])->name('transfer.verify');
        Route::post('/pay-invoice/{invoice}', [App\Modules\Payments\Controllers\WalletController::class, 'payInvoice'])->name('pay-invoice');
        Route::post('/pay-multiple', [App\Modules\Payments\Controllers\WalletController::class, 'payMultipleInvoices'])->name('pay-multiple');
        Route::get('/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/export', [App\Modules\Payments\Controllers\WalletController::class, 'exportTransactions'])->name('transactions.export');
        Route::get('/statement', [App\Modules\Payments\Controllers\WalletController::class, 'statement'])->name('statement');
        Route::get('/statement/pdf', [App\Modules\Payments\Controllers\WalletController::class, 'downloadStatement'])->name('statement.pdf');
        Route::get('/funding-sources', [App\Modules\Payments\Controllers\WalletController::class, 'fundingSources'])->name('funding-sources');
        Route::post('/funding-sources', [App\Modules\Payments\Controllers\WalletController::class, 'addFundingSource'])->name('funding-sources.add');
        Route::delete('/funding-sources/{source}', [App\Modules\Payments\Controllers\WalletController::class, 'removeFundingSource'])->name('funding-sources.remove');
        Route::put('/funding-sources/{source}/default', [App\Modules\Payments\Controllers\WalletController::class, 'setDefaultSource'])->name('funding-sources.default');
        Route::get('/cards', [App\Modules\Payments\Controllers\WalletController::class, 'cards'])->name('cards');
        Route::post('/cards', [App\Modules\Payments\Controllers\WalletController::class, 'addCard'])->name('cards.add');
        Route::delete('/cards/{card}', [App\Modules\Payments\Controllers\WalletController::class, 'removeCard'])->name('cards.remove');
        Route::put('/cards/{card}/default', [App\Modules\Payments\Controllers\WalletController::class, 'setDefaultCard'])->name('cards.default');
        Route::post('/notifications/read', [App\Modules\Payments\Controllers\WalletController::class, 'markNotificationsRead'])->name('notifications.read');
    });
    
    // ===== API WALLET ROUTES (AJAX) =====
    Route::prefix('api/wallet')->name('api.wallet.')->group(function () {
        Route::get('/balance', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetBalance'])->name('balance');
        Route::get('/tenant-details', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetTenantDetails'])->name('tenant-details');
        Route::get('/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetTransactions'])->name('transactions');
        Route::get('/statement', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetStatement'])->name('statement');
        Route::post('/deposit', [App\Modules\Payments\Controllers\WalletController::class, 'apiDeposit'])->name('deposit');
        Route::get('/pending-deposits', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetPendingDeposits'])->name('pending-deposits');
        Route::post('/approve-deposit/{transactionId}', [App\Modules\Payments\Controllers\WalletController::class, 'apiApproveDeposit'])->name('approve-deposit');
        Route::post('/reject-deposit/{transactionId}', [App\Modules\Payments\Controllers\WalletController::class, 'apiRejectDeposit'])->name('reject-deposit');
        Route::post('/transfer', [App\Modules\Payments\Controllers\WalletController::class, 'apiTransfer'])->name('transfer');
        Route::get('/pending-invoices', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetPendingInvoices'])->name('pending-invoices');
        Route::post('/pay-invoice/{invoiceId}', [App\Modules\Payments\Controllers\WalletController::class, 'apiPayInvoice'])->name('pay-invoice');
        Route::post('/pay-multiple', [App\Modules\Payments\Controllers\WalletController::class, 'apiPayMultipleInvoices'])->name('pay-multiple');
        Route::get('/invoice/{invoice}/details', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetInvoiceDetails'])->name('invoice.details');
        Route::post('/verify-pin', [App\Modules\Payments\Controllers\WalletController::class, 'verifyPin'])->name('verify-pin');
    });
    
    // ===== ADMIN WALLET MANAGEMENT ROUTES =====
    Route::prefix('admin/wallets')->name('admin.wallets.')->group(function () {
        Route::get('/', [App\Modules\Payments\Controllers\WalletController::class, 'index'])->name('index');
        Route::get('/report', [App\Modules\Payments\Controllers\WalletController::class, 'report'])->name('report');
        Route::get('/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'allTransactions'])->name('transactions');
        Route::get('/export', [App\Modules\Payments\Controllers\WalletController::class, 'export'])->name('export');
        Route::get('/{user}/details', [App\Modules\Payments\Controllers\WalletController::class, 'show'])->name('show');
        Route::post('/{user}/adjust', [App\Modules\Payments\Controllers\WalletController::class, 'adjustBalance'])->name('adjust');
        Route::post('/{user}/freeze', [App\Modules\Payments\Controllers\WalletController::class, 'freeze'])->name('freeze');
        Route::post('/{user}/unfreeze', [App\Modules\Payments\Controllers\WalletController::class, 'unfreeze'])->name('unfreeze');
    });

    // ===== TENANT WALLET WEB ROUTES (form submissions) =====
    Route::prefix('wallet')->name('tenant.wallet.')->group(function () {
        Route::post('/deposit', [App\Modules\Payments\Controllers\WalletController::class, 'deposit'])->name('deposit');
        Route::post('/withdraw', [App\Modules\Payments\Controllers\WalletController::class, 'withdraw'])->name('withdraw');
        Route::post('/transfer', [App\Modules\Payments\Controllers\WalletController::class, 'transfer'])->name('transfer');
        Route::post('/pay-invoice/{invoiceId}', [App\Modules\Payments\Controllers\WalletController::class, 'payInvoiceForm'])->name('pay-invoice');
        Route::get('/transactions/export', [App\Modules\Payments\Controllers\WalletController::class, 'exportTransactions'])->name('transactions.export');
    });

    // ============================================
    // SUBSCRIPTION MODULE ROUTES - Admin Section
    // ============================================
    Route::prefix('admin/subscriptions')->name('admin.subscriptions.')->group(function () {
        Route::resource('plans', SubscriptionController::class)
            ->parameters(['plans' => 'plan'])
            ->only(['index', 'show', 'store', 'update', 'destroy']);
        
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::get('/company/{company}/dashboard', [SubscriptionController::class, 'companyShow'])->name('company.dashboard');
        Route::put('/plans/{plan}/features', [SubscriptionController::class, 'updateFeatures'])->name('plans.features');
        Route::get('/api/plans/{plan}/available-companies', [SubscriptionController::class, 'getAvailableCompanies'])->name('api.plans.available-companies');
        Route::post('/plans/{plan}/assign-companies', [SubscriptionController::class, 'assignCompanies'])->name('plans.assign-companies');
        Route::get('/api/users', [SubscriptionController::class, 'getUsers'])->name('api.users');
        Route::get('/api/counties', [SubscriptionController::class, 'getCounties'])->name('api.counties');
        Route::get('/api/subcounties', [SubscriptionController::class, 'getSubcounties'])->name('api.subcounties');
        Route::get('/api/subcounties/{countyId}', [SubscriptionController::class, 'getSubcountiesByCounty'])->name('api.subcounties.by-county');
        Route::get('/api/estates', [SubscriptionController::class, 'getEstates'])->name('api.estates');
        Route::get('/api/managers/{manager}', [SubscriptionController::class, 'getManager'])->name('api.manager');
        Route::post('/plans/{plan}/managers', [SubscriptionController::class, 'assignManager'])->name('plans.managers.assign');
        Route::put('/managers/{manager}', [SubscriptionController::class, 'updateManager'])->name('managers.update');
        Route::delete('/plans/{plan}/managers/{manager}', [SubscriptionController::class, 'removeManager'])->name('plans.managers.remove');
        Route::get('/api/companies', [SubscriptionController::class, 'getCompanies'])->name('api.companies');
        Route::get('/api/invoices/{invoice}', [SubscriptionController::class, 'getInvoice'])->name('api.invoice');
        Route::post('/plans/{plan}/invoices', [SubscriptionController::class, 'generateInvoice'])->name('plans.invoices.generate');
        Route::put('/invoices/{invoice}', [SubscriptionController::class, 'updateInvoice'])->name('invoices.update');
        Route::post('/invoices/{invoice}/mark-paid', [SubscriptionController::class, 'markInvoicePaid'])->name('invoices.mark-paid');
        Route::post('/subscription/{subscription}/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('subscription.cancel');
        Route::post('/subscription/{subscription}/resume', [SubscriptionController::class, 'resumeSubscription'])->name('subscription.resume');
        
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/plans/data', [SubscriptionController::class, 'getPlansData'])->name('plans.data');
            Route::get('/plans/{plan}', [SubscriptionController::class, 'getPlan'])->name('plans.show');
            Route::get('/plans/{plan}/subscribers', [SubscriptionController::class, 'getSubscribers'])->name('plans.subscribers');
            Route::get('/company-subscriptions', [SubscriptionController::class, 'getCompanySubscriptions'])->name('company-subscriptions');
        });
    });

    // ============================================
    // COMPANY MANAGEMENT ROUTES - Admin Section
    // ============================================
    Route::prefix('admin/companies')->name('admin.companies.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CompanyController::class, 'index'])->name('index');
        Route::get('/data', [App\Http\Controllers\Admin\CompanyController::class, 'getCompaniesData'])->name('data');
        Route::post('/', [App\Http\Controllers\Admin\CompanyController::class, 'store'])->name('store');
        Route::get('/{company}/estates', [App\Http\Controllers\Admin\CompanyController::class, 'getEstates'])->name('estates');
        Route::get('/{company}/tenancies', [App\Http\Controllers\Admin\CompanyController::class, 'getTenancies'])->name('tenancies');
        Route::get('/{company}/users', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyUsers'])->name('get-users');
        Route::get('/{company}/staff', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyStaff'])->name('staff');
        Route::get('/{company}/subscriptions', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanySubscriptions'])->name('subscriptions');
        Route::get('/{company}/subscription-invoices', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanySubscriptionInvoices'])->name('subscription-invoices');
        Route::get('/{company}/invoices', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyInvoices'])->name('invoices');
        Route::get('/{company}/expenses', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyExpenses'])->name('expenses');
        Route::post('/{company}/users', [App\Http\Controllers\Admin\CompanyController::class, 'addUser'])->name('add-user');
        Route::delete('/{company}/users/{user}', [App\Http\Controllers\Admin\CompanyController::class, 'removeUser'])->name('remove-user');
        Route::put('/{company}/users/{user}/role', [App\Http\Controllers\Admin\CompanyController::class, 'updateUserRole'])->name('update-user-role');
        Route::get('/{company}', [App\Http\Controllers\Admin\CompanyController::class, 'show'])->name('show');
        Route::put('/{company}', [App\Http\Controllers\Admin\CompanyController::class, 'update'])->name('update');
        Route::delete('/{company}', [App\Http\Controllers\Admin\CompanyController::class, 'destroy'])->name('destroy');
    });

    // ============================================
    // ACCOUNT MANAGER MANAGEMENT ROUTES - Admin Section
    // ============================================
    Route::prefix('admin/account-managers')->name('admin.account-managers.')->group(function () {
        Route::get('/', [AccountManagerController::class, 'index'])->name('index');
        Route::get('/create', [AccountManagerController::class, 'create'])->name('create');
        Route::post('/', [AccountManagerController::class, 'store'])->name('store');
        Route::get('/{id}', [AccountManagerController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AccountManagerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AccountManagerController::class, 'update'])->name('update');
        Route::delete('/{id}', [AccountManagerController::class, 'destroy'])->name('destroy');
    });

    // ============================================
    // TENANT SPECIFIC ROUTES
    // ============================================
    Route::get('/my-invoices', [TenantController::class, 'myInvoices'])->name('tenant.invoices');
    Route::get('/my-payments', [TenantController::class, 'myPayments'])->name('tenant.payments');
    Route::post('/make-payment', [PaymentController::class, 'tenantPayment'])->name('tenant.payment');

    // ============================================
    // 📱 M-PESA STK PUSH PAYMENT ROUTES
    // ============================================
    Route::prefix('payments/mpesa')->name('payments.mpesa.')->group(function () {
        Route::post('/stk-push', [PaymentController::class, 'initiateMpesaStkPush'])->name('stk-push');
        Route::get('/status', [PaymentController::class, 'checkMpesaStatus'])->name('status');
        Route::get('/pay', [MpesaController::class, 'showPaymentForm'])->name('form');
        Route::post('/pay', [MpesaController::class, 'stkPush'])->name('process');
    });

});

// ============================================
// MODULE ROUTES
// ============================================

// SMS Module Routes
require base_path('app/Modules/SMS/routes.php');

// Security Module Routes
require base_path('app/Modules/Security/routes.php');

// ============================================
// 📱 TEST SMS ROUTES (Temporary - Remove after testing)
// ============================================

Route::get('/test-sms-config', function () {
    return response()->json([
        'sms_config' => config('sms.kenyasms'),
        'api_key_exists' => !empty(config('sms.kenyasms.api_key')),
        'sandbox_mode' => config('sms.kenyasms.sandbox'),
        'sender_id' => config('sms.kenyasms.sender_id'),
        'base_url' => config('sms.kenyasms.base_url'),
    ]);
});

Route::prefix('test-sms')->group(function () {
    Route::get('/send', [App\Http\Controllers\TestSMSController::class, 'testSendSms']);
    Route::get('/phone', [App\Http\Controllers\TestSMSController::class, 'testPhoneFormat']);
    Route::get('/parts', [App\Http\Controllers\TestSMSController::class, 'testMessageParts']);
    Route::get('/balance', [App\Http\Controllers\TestSMSController::class, 'testBalance']);
    Route::get('/quiet-hours', [App\Http\Controllers\TestSMSController::class, 'testQuietHours']);
    Route::get('/preview', [App\Http\Controllers\TestSMSController::class, 'testCampaignPreview']);
});

// ============================================
// 🔍 DEBUG ROUTES (Temporary - Remove after testing)
// ============================================

Route::get('/debug-mpesa', function () {
    try {
        $mpesa = new \App\Services\MpesaService();
        $result = $mpesa->getAccessTokenWithDebug();
        
        return response()->json([
            'environment' => env('MPESA_ENVIRONMENT'),
            'consumer_key' => env('MPESA_CONSUMER_KEY') ? substr(env('MPESA_CONSUMER_KEY'), 0, 20) . '...' : 'MISSING',
            'consumer_secret' => env('MPESA_CONSUMER_SECRET') ? substr(env('MPESA_CONSUMER_SECRET'), 0, 20) . '...' : 'MISSING',
            'base_url' => 'https://sandbox.safaricom.co.ke',
            'auth_result' => $result,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Users Module Routes
Route::prefix('users')->group(function () {
    require base_path('app/Modules/Users/routes.php');
});

// ============================================
// API FALLBACK ROUTES (outside auth middleware)
// ============================================
Route::get('/water/api/water/readings/bulk', [WaterReadingController::class, 'getBulkReadings']);

// Accountant transaction endpoints
Route::prefix('api/wallet')->middleware(['auth'])->group(function () {
    Route::get('/accountant/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetTransactionsForAccountant'])->name('api.wallet.accountant.transactions');
    Route::get('/pending-deposits', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetPendingDeposits'])->name('api.wallet.pending-deposits');
    Route::post('/approve-deposit/{transactionId}', [App\Modules\Payments\Controllers\WalletController::class, 'apiApproveDeposit'])->name('api.wallet.approve-deposit');
    Route::post('/reject-deposit/{transactionId}', [App\Modules\Payments\Controllers\WalletController::class, 'apiRejectDeposit'])->name('api.wallet.reject-deposit');
});