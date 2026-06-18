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
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WaterReadingController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WalletController; // Add this

Route::get('/', function () {
    return view('welcome');
});

// Email Verification Routes
Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');
Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('mtickets', function () {
    return view('mtickets');
})->name('mtickets');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/index', function () {
        return view('index');
    })->name('index');    
    Route::get('/invoice', function () {
        return view('invoice');
    })->name('invoice');
    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');
    Route::get('/404', function () {
        return view('404');
    })->name('404');
    Route::get('/messages', function () {
        return view('messages');
    })->name('messages');
    Route::get('/alerts', function () {
        return view('alerts');
    })->name('alerts');
    Route::get('/blank', function () {
        return view('blank');
    })->name('blank');
    Route::get('/calendar', function () {
        return view('calendar');
    })->name('calendar');
    Route::get('/form-elements', function () {
        return view('form-elements');
    })->name('form-elements');
    Route::get('/basic-tables', function () {
        return view('basic-tables');
    })->name('basic-tables');
    Route::get('/avatars', function () {
        return view('avatars');
    })->name('avatars');
    Route::get('/badge', function () {
        return view('badge');
    })->name('badge');
    Route::get('/buttons', function () {
        return view('buttons');
    })->name('buttons');
    Route::get('/images', function () {
        return view('images');
    })->name('images');
    Route::get('/videos', function () {
        return view('videos');
    })->name('videos');
    Route::get('/signin', function () {
        return view('signin');
    })->name('signin');
    Route::get('/signup', function () {
        return view('signup');
    })->name('signup');
    Route::get('/image', function () {
        return view('image');
    });
    Route::get('/line-chart', function () {
        return view('line-chart');
    })->name('line-chart');
    Route::get('/bar-chart', function () {
        return view('bar-chart');
    })->name('bar-chart');
    Route::get('/dash', function () {
        return view('dash');
    })->name('dash');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.delete-avatar');
    Route::put('/profile/address', [ProfileController::class, 'updateAddress'])->name('profile.address.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/data', [ProfileController::class, 'getUserData'])->name('profile.data');

    // System Settings
    Route::prefix('system')->name('system.')->group(function () {
        Route::get('/', [SystemController::class, 'index'])->name('index');
        Route::put('/update', [SystemController::class, 'update'])->name('update');
        Route::get('/clear-cache', [SystemController::class, 'clearCache'])->name('clear-cache');
        Route::get('/backup', [SystemController::class, 'backupDatabase'])->name('backup');
        Route::post('/toggle-maintenance', [SystemController::class, 'toggleMaintenance'])->name('toggle-maintenance');
        Route::post('/debug', [SystemController::class, 'debug'])->name('debug');
    });

    Route::resource('invoices', InvoiceController::class);
    Route::post('/invoices/generate/single', [InvoiceController::class, 'generateForCurrentMonth'])->name('invoices.generate.single');
    Route::post('/invoices/generate/all', [InvoiceController::class, 'generateAllMonthlyInvoices'])->name('invoices.generate.all');
    Route::post('/invoices/payments', [InvoiceController::class, 'processPayment'])->name('invoices.payments.store');
    Route::post('/invoices/bulk-create', [InvoiceController::class, 'bulkCreate'])->name('invoices.bulk.create');
    Route::post('/invoices/check-existing', [InvoiceController::class, 'checkExistingInvoices'])->name('invoices.check.existing');
    Route::post('/invoices/generate/all', [InvoiceController::class, 'generateAllInvoices'])->name('invoices.generate.all');
    Route::post('/invoices/generate/single', [InvoiceController::class, 'generateSingleInvoice'])->name('invoices.generate.single');

    Route::resource('estates', EstateController::class);
    Route::resource('expenses', ExpenseController::class);
    Route::resource('payees', PayeeController::class);
    Route::resource('payments', PaymentController::class); 
    Route::post('/payments/bulk', [PaymentController::class, 'bulkStore'])->name('payments.bulk.store');
    Route::put('/payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    Route::get('/api/invoices/{invoice}/details', [PaymentController::class, 'getInvoiceDetails'])->name('api.invoices.details');
        
    Route::resource('units', UnitController::class);
    Route::resource('tenancies', TenancyController::class);
    Route::resource('tenants', TenantController::class);

    Route::get('/units/{unit}/water-reading', [UnitController::class, 'showWaterReadingForm'])->name('units.water-reading');
    Route::put('/units/{unit}/water-reading', [UnitController::class, 'updateWaterReading'])->name('units.water-reading.update');
    Route::get('/tenancies/{tenancy}/invoice-data', [InvoiceController::class, 'getInvoiceData'])->name('tenancies.invoice-data');     
    Route::post('/tenants/bulk-store', [TenantController::class, 'bulkStore'])->name('tenants.bulkStore');
    Route::post('/tenants/{tenant}/invoices', [TenantController::class, 'storeInvoice'])->name('tenants.store.invoice');
    Route::post('/tenants/{tenant}/payments', [TenantController::class, 'storePayment'])->name('tenants.store.payment');

    Route::post('/invoices/{invoice}/add-item', [InvoiceController::class, 'addItemToInvoice'])->name('invoices.items.store');
    Route::put('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'updateInvoiceItem'])->name('invoices.items.update');
    Route::delete('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'removeInvoiceItem'])->name('invoices.items.destroy');
    Route::get('/invoices/{invoice}/edit-data', [InvoiceController::class, 'getInvoiceForEditing'])->name('invoices.edit-data');
    Route::get('/tenancies/{tenancy}/check-invoice-status', [InvoiceController::class, 'checkInvoiceGenerationStatus'])->name('tenancies.check-invoice-status');
    Route::post('/tenancies/{tenancy}/force-invoice', [InvoiceController::class, 'forceGenerateInvoice'])->name('tenancies.force-invoice');

    Route::get('/tenancies/{tenancy}/billing-history', [InvoiceController::class, 'getBillingHistory'])->name('tenancies.billing-history');
    Route::post('/tenancies/{tenancy}/generate-missing-invoices', [InvoiceController::class, 'generateMissingInvoices'])->name('tenancies.generate-missing-invoices');
    Route::get('/invoices/{invoice}/details', [InvoiceController::class, 'getInvoiceDetails'])->name('invoices.details');
    Route::post('/tenancies/{tenancy}/invoices/bulk-missing', [InvoiceController::class, 'generateMissingInvoicesBulk'])->name('tenancies.invoices.bulk-missing');
    Route::post('/invoices/resolve-duplicates', [InvoiceController::class, 'resolveDuplicates'])->name('invoices.resolve-duplicates');

    Route::get('/payments/create-data', [PaymentController::class, 'getCreateData'])->name('payments.create-data');
    Route::post('/invoices/check-existing', [InvoiceController::class, 'checkExistingInvoices'])->name('invoices.check.existing');
    Route::post('/tenancies/{tenancy}/payments', [PaymentController::class, 'store'])->name('tenancies.payments.store');

    Route::prefix('tenancies/{tenancy}')->group(function () {
        Route::post('/invoices', [InvoiceController::class, 'storeForTenancy'])->name('tenancies.invoices.store');
        Route::get('/invoices', [InvoiceController::class, 'indexForTenancy'])->name('tenancies.invoices.index');
        Route::get('/invoices/check', [InvoiceController::class, 'getExistingInvoice'])->name('tenancies.invoices.check');
    });

    Route::prefix('invoices/{invoice}')->group(function () {
        Route::post('/items', [InvoiceController::class, 'addItemToInvoice'])->name('invoices.items.store');
        Route::put('/items/{item}', [InvoiceController::class, 'updateInvoiceItem'])->name('invoices.items.update');
        Route::delete('/items/{item}', [InvoiceController::class, 'removeInvoiceItem'])->name('invoices.items.destroy');
    });

    Route::resource('expense-categories', ExpenseCategoryController::class);
    Route::resource('staff', StaffController::class);

    // Meter Reader specific routes
    Route::middleware(['auth', 'role:super_admin,admin,property_manager,meter_reader'])->group(function () {
        Route::get('/meter-readings', [UnitController::class, 'meterReadingsIndex'])->name('meter-readings.index');
        Route::get('/units/{unit}/meter-reading', [UnitController::class, 'showMeterReadingForm'])->name('units.meter-reading');
        Route::put('/units/{unit}/meter-reading', [UnitController::class, 'updateMeterReading'])->name('units.meter-reading.update');
        Route::get('/meter-readings/reports', [UnitController::class, 'meterReadingReports'])->name('meter-readings.reports');
    });
        
    // Water routes
    Route::prefix('water')->group(function () {
        Route::get('/', [WaterReadingController::class, 'index'])->name('water.index');
        Route::post('/readings', [WaterReadingController::class, 'store'])->name('water.readings.store');
        Route::post('/readings/bulk', [WaterReadingController::class, 'storeBulk'])->name('water.readings.bulk');
        Route::post('/readings/bulk-matrix', [WaterReadingController::class, 'storeBulkMatrix'])->name('water.readings.bulk-matrix');
        Route::post('/readings/multi-month', [WaterReadingController::class, 'storeMultiMonth'])->name('water.readings.multi-month');
        Route::put('/readings/{reading}/reconcile', [WaterReadingController::class, 'reconcile'])->name('water.readings.reconcile');
        Route::get('/last-reading/{unitId}', [WaterReadingController::class, 'getLastReading']);
        Route::get('/unit-history/{unitId}', [WaterReadingController::class, 'getUnitWaterHistory']);
        Route::get('/unit/{unit}/statement', [WaterReadingController::class, 'statement'])->name('water.statement');
        Route::get('/unit/{unit}/readings', [WaterReadingController::class, 'getUnitReadings']);
        Route::post('/report', [WaterReadingController::class, 'generateReport']);
        Route::get('/api/water/readings/bulk', [WaterReadingController::class, 'getBulkReadings']);
        Route::get('/api/water/unit-readings/{unitId}', [WaterReadingController::class, 'getUnitReadingsForMonthRange']);
    });

    Route::get('/api/units/with-water-readings', [WaterReadingController::class, 'getUnitsWithWaterReadings'])->name('api.units.with-water-readings');
    Route::get('/water/api/water/readings/bulk', [WaterReadingController::class, 'getBulkReadings']);

    // Cleaning Staff routes
    Route::middleware(['auth', 'role:super_admin,admin,property_manager,cleaning_staff'])->group(function () {
        Route::get('/cleaning/tasks', [CleaningController::class, 'index'])->name('cleaning.tasks');
        Route::put('/cleaning/tasks/{task}/complete', [CleaningController::class, 'markComplete'])->name('cleaning.tasks.complete');
        Route::get('/cleaning/schedule', [CleaningController::class, 'schedule'])->name('cleaning.schedule');
    });

    // Maintenance Staff routes
    Route::middleware(['auth', 'role:super_admin,admin,property_manager,maintenance'])->group(function () {
        Route::get('/maintenance/requests', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::put('/maintenance/requests/{request}/update', [MaintenanceController::class, 'update'])->name('maintenance.update');
        Route::get('/maintenance/assignments', [MaintenanceController::class, 'assignments'])->name('maintenance.assignments');
    });

    // Security Staff routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/security/logs', [SecurityController::class, 'logs'])->name('security.logs');
        Route::get('/security/access', [SecurityController::class, 'accessRecords'])->name('security.access');
        Route::post('/security/incidents', [SecurityController::class, 'reportIncident'])->name('security.incidents');
    });

    // Accountant routes (finance only)
    Route::middleware(['auth'])->group(function () {
        Route::get('/reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
        Route::get('/reports/invoices', [ReportController::class, 'invoices'])->name('reports.invoices');
        Route::get('/reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
    });

    // Tenant specific routes (including wallet)
    Route::middleware(['auth'])->group(function () {
        Route::get('/my-invoices', [TenantController::class, 'myInvoices'])->name('tenant.invoices');
        Route::get('/my-payments', [TenantController::class, 'myPayments'])->name('tenant.payments');
        Route::post('/make-payment', [PaymentController::class, 'tenantPayment'])->name('tenant.payment');
        Route::get('/submit-request', [MaintenanceController::class, 'tenantRequest'])->name('tenant.maintenance');
    });

    // =============================================
    // WALLET MODULE ROUTES
    // =============================================
    
    // Tenant/Customer Wallet Routes
    Route::prefix('wallet')->name('wallet.')->group(function () {
        // Main wallet view
        Route::get('/', [App\Modules\Payments\Controllers\WalletController::class, 'index'])->name('index');
        
        // Balance operations
        Route::get('/balance', [App\Modules\Payments\Controllers\WalletController::class, 'getBalance'])->name('balance');
        Route::post('/deposit', [App\Modules\Payments\Controllers\WalletController::class, 'deposit'])->name('deposit');
        Route::post('/withdraw', [App\Modules\Payments\Controllers\WalletController::class, 'withdraw'])->name('withdraw');
        
        // Transfer operations
        Route::post('/transfer', [App\Modules\Payments\Controllers\WalletController::class, 'transfer'])->name('transfer');
        Route::get('/transfer/verify/{reference}', [App\Modules\Payments\Controllers\WalletController::class, 'verifyTransfer'])->name('transfer.verify');
        
        // Payment operations
        Route::post('/pay-invoice/{invoice}', [App\Modules\Payments\Controllers\WalletController::class, 'payInvoice'])->name('pay-invoice');
        Route::post('/pay-multiple', [App\Modules\Payments\Controllers\WalletController::class, 'payMultipleInvoices'])->name('pay-multiple');
        
        // Transaction history
        Route::get('/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/export', [App\Modules\Payments\Controllers\WalletController::class, 'exportTransactions'])->name('transactions.export');
        
        // Statements
        Route::get('/statement', [App\Modules\Payments\Controllers\WalletController::class, 'statement'])->name('statement');
        Route::get('/statement/pdf', [App\Modules\Payments\Controllers\WalletController::class, 'downloadStatement'])->name('statement.pdf');
        
        // Funding sources
        Route::get('/funding-sources', [App\Modules\Payments\Controllers\WalletController::class, 'fundingSources'])->name('funding-sources');
        Route::post('/funding-sources', [App\Modules\Payments\Controllers\WalletController::class, 'addFundingSource'])->name('funding-sources.add');
        Route::delete('/funding-sources/{source}', [App\Modules\Payments\Controllers\WalletController::class, 'removeFundingSource'])->name('funding-sources.remove');
        Route::put('/funding-sources/{source}/default', [App\Modules\Payments\Controllers\WalletController::class, 'setDefaultSource'])->name('funding-sources.default');
        
        // Cards management
        Route::get('/cards', [App\Modules\Payments\Controllers\WalletController::class, 'cards'])->name('cards');
        Route::post('/cards', [App\Modules\Payments\Controllers\WalletController::class, 'addCard'])->name('cards.add');
        Route::delete('/cards/{card}', [App\Modules\Payments\Controllers\WalletController::class, 'removeCard'])->name('cards.remove');
        Route::put('/cards/{card}/default', [App\Modules\Payments\Controllers\WalletController::class, 'setDefaultCard'])->name('cards.default');
        
        // Notifications
        Route::post('/notifications/read', [App\Modules\Payments\Controllers\WalletController::class, 'markNotificationsRead'])->name('notifications.read');
    });
    
    Route::prefix('api/wallet')->name('api.wallet.')->group(function () {
        Route::get('/balance', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetBalance'])->name('balance');
        Route::get('/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetTransactions'])->name('transactions');
        Route::get('/statement', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetStatement'])->name('statement');
        Route::post('/deposit', [App\Modules\Payments\Controllers\WalletController::class, 'apiDeposit'])->name('deposit');
        Route::post('/transfer', [App\Modules\Payments\Controllers\WalletController::class, 'apiTransfer'])->name('transfer');
        Route::post('/verify-pin', [App\Modules\Payments\Controllers\WalletController::class, 'verifyPin'])->name('verify-pin');
        Route::post('/pay-invoice/{invoice}', [App\Modules\Payments\Controllers\WalletController::class, 'apiPayInvoice'])->name('pay-invoice');
        Route::post('/pay-multiple', [App\Modules\Payments\Controllers\WalletController::class, 'apiPayMultipleInvoices'])->name('pay-multiple');
        Route::get('/invoice/{invoice}/details', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetInvoiceDetails'])->name('invoice.details');
        Route::get('/tenant-details', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetTenantDetails'])->name('tenant-details');
    });

    Route::get('/units/{unit}/meter-reading-data', [UnitController::class, 'getMeterReadingData'])->middleware(['auth'])->name('units.meter-reading-data');
    Route::resource('maintenance', App\Http\Controllers\MaintenanceController::class);
    Route::get('/tenant/maintenance', [App\Http\Controllers\MaintenanceController::class, 'tenantRequests'])->name('tenant.maintenance');
    Route::get('/tenant/access-logs', [App\Http\Controllers\SecurityController::class, 'tenantAccessLogs'])->name('tenant.access-logs');

    Route::resource('security', App\Http\Controllers\SecurityController::class);
    Route::post('/security/access', [App\Http\Controllers\SecurityController::class, 'store'])->name('security.access.store');
    Route::put('/security/access/{accessLog}', [App\Http\Controllers\SecurityController::class, 'update'])->name('security.access.update');

    Route::get('/maintenance/unit/{unit}/history', [MaintenanceController::class, 'getUnitHistory'])
        ->middleware(['auth'])
        ->name('maintenance.unit.history');
    Route::get('/maintenance/{id}/json', [MaintenanceController::class, 'showJson'])->name('maintenance.show.json');
    
    Route::prefix('security')->middleware(['auth'])->group(function () {
        Route::get('/logs', [SecurityController::class, 'index'])->name('security.logs.index');
        Route::get('/logs/{id}', [SecurityController::class, 'show'])->name('security.logs.show');
        Route::post('/logs', [SecurityController::class, 'store'])->name('security.logs.store');
        Route::put('/logs/{id}', [SecurityController::class, 'update'])->name('security.logs.update');
        Route::delete('/logs/{id}', [SecurityController::class, 'destroy'])->name('security.logs.destroy');
    });

    // Security API Routes
    Route::middleware(['auth'])->prefix('security')->name('security.')->group(function () {
        Route::get('/estates', [SecurityController::class, 'getEstates'])->name('estates');
        Route::get('/units', [SecurityController::class, 'getUnitsByEstate'])->name('units');
        Route::get('/tenants', [SecurityController::class, 'getTenantsByUnit'])->name('tenants');
        Route::get('/visitors', [SecurityController::class, 'getVisitorsByTenant'])->name('visitors');
        Route::get('/logs-by-tenant', [SecurityController::class, 'getSecurityLogsByTenant'])->name('logs-by-tenant');
    });

    // =============================================
    // SUBSCRIPTION MODULE ROUTES - Admin Section
    // =============================================
    Route::prefix('admin/subscriptions')->name('admin.subscriptions.')->middleware(['auth'])->group(function () {
        
        // API Routes for AJAX calls
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/plans/data', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'getPlansData'])->name('plans.data');
            Route::get('/plans/{plan}', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'getPlan'])->name('plans.show');
            Route::get('/plans/{plan}/subscribers', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'getSubscribers'])->name('plans.subscribers');
            Route::get('/company-subscriptions', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'getCompanySubscriptions'])->name('company-subscriptions');
            Route::get('/company-subscription/{id}', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'getCompanySubscription'])->name('company-subscription.show');
        });
        
        // Subscription Plans CRUD
        Route::get('/plans', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'plansIndex'])->name('plans.index');
        Route::post('/plans', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'storePlan'])->name('plans.store');
        Route::put('/plans/{plan}', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'updatePlan'])->name('plans.update');
        Route::delete('/plans/{plan}', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'deletePlan'])->name('plans.destroy');
        
        // Company Subscriptions
        Route::get('/', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'index'])->name('index');
        Route::get('/company/{company}', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'show'])->name('show');
        Route::post('/company/{company}/subscribe', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::post('/subscription/{subscription}/cancel', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'cancel'])->name('cancel');
        Route::post('/subscription/{subscription}/resume', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'resume'])->name('resume');
        Route::get('/subscription/{subscription}/invoices', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'invoices'])->name('invoices');
        Route::get('/invoice/{invoice}/download', [App\Modules\Subscriptions\Controllers\SubscriptionController::class, 'downloadInvoice'])->name('invoice.download');
    });


    // Company Management Routes - UPDATED VERSION
Route::prefix('admin/companies')->name('admin.companies.')->middleware(['auth'])->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\CompanyController::class, 'index'])->name('index');
    Route::get('/data', [App\Http\Controllers\Admin\CompanyController::class, 'getCompaniesData'])->name('data');
    Route::post('/', [App\Http\Controllers\Admin\CompanyController::class, 'store'])->name('store');
    
    // IMPORTANT: These specific routes MUST come BEFORE the {company} parameter route
    Route::get('/{company}/estates-with-tenants', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyEstatesWithTenants'])->name('estates-with-tenants');
    Route::get('/{company}/estates', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyEstates'])->name('estates');
    Route::get('/{company}/staff', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyStaff'])->name('staff');
    Route::get('/{company}/subscriptions', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanySubscriptions'])->name('subscriptions');
    Route::get('/{company}/invoices', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyInvoices'])->name('invoices');
    Route::get('/{company}/payments', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyPayments'])->name('payments');
    Route::get('/{company}/users', [App\Http\Controllers\Admin\CompanyController::class, 'getCompanyUsers'])->name('get-users');
    
    // User management routes
    Route::post('/{company}/users', [App\Http\Controllers\Admin\CompanyController::class, 'addUser'])->name('add-user');
    Route::delete('/{company}/users/{user}', [App\Http\Controllers\Admin\CompanyController::class, 'removeUser'])->name('remove-user');
    Route::put('/{company}/users/{user}/role', [App\Http\Controllers\Admin\CompanyController::class, 'updateUserRole'])->name('update-user-role');
    
    // This MUST be last - the catch-all company route
    Route::get('/{company}', [App\Http\Controllers\Admin\CompanyController::class, 'show'])->name('show');
    Route::put('/{company}', [App\Http\Controllers\Admin\CompanyController::class, 'update'])->name('update');
    Route::delete('/{company}', [App\Http\Controllers\Admin\CompanyController::class, 'destroy'])->name('destroy');
});



    // ========== ADMIN WALLET ROUTES ==========
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        // Admin wallet management
        Route::get('/wallets', [App\Modules\Payments\Controllers\WalletController::class, 'index'])->name('wallets.index');
        Route::get('/wallets/{user}/details', [App\Modules\Payments\Controllers\WalletController::class, 'show'])->name('wallets.show');
        Route::post('/wallets/{user}/adjust', [App\Modules\Payments\Controllers\WalletController::class, 'adjustBalance'])->name('wallets.adjust');
        Route::post('/wallets/{user}/freeze', [App\Modules\Payments\Controllers\WalletController::class, 'freeze'])->name('wallets.freeze');
        Route::post('/wallets/{user}/unfreeze', [App\Modules\Payments\Controllers\WalletController::class, 'unfreeze'])->name('wallets.unfreeze');
        Route::get('/wallets/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'allTransactions'])->name('wallets.transactions');
        Route::get('/wallets/export', [App\Modules\Payments\Controllers\WalletController::class, 'export'])->name('wallets.export');
        Route::get('/wallets/report', [App\Modules\Payments\Controllers\WalletController::class, 'report'])->name('wallets.report');
        Route::get('/wallet/transactions/export', [App\Modules\Payments\Controllers\WalletController::class, 'exportTransactions'])->name('tenant.wallet.transactions.export');
        Route::get('/api/wallet/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetTransactions'])->middleware('auth');
        Route::get('/api/wallet/pending-invoices', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetPendingInvoices'])->middleware('auth');
        Route::post('/api/wallet/pay-multiple-invoices', [App\Modules\Payments\Controllers\WalletController::class, 'apiPayMultipleInvoices'])->middleware('auth');
        Route::get('/api/wallet/pending-invoices', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetPendingInvoices'])->middleware('auth');
        Route::post('/api/wallet/pay-invoice/{invoiceId}', [App\Modules\Payments\Controllers\WalletController::class, 'apiPayInvoice'])->middleware('auth');
    });

    Route::get('/payments/tenant/{tenantId}/invoices', [PaymentController::class, 'getTenantInvoices'])->name('payments.tenant.invoices');
    Route::get('/invoices/{invoice}/details', [InvoiceController::class, 'getInvoiceDetails'])->name('invoices.details');

}); 




Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('login.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

// SMS Module Routes
require base_path('app/Modules/SMS/routes.php');

Route::prefix('users')->group(function () {
    require base_path('app/Modules/Users/routes.php');
});

require __DIR__.'/auth.php';