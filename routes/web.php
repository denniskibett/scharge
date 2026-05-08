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
use App\Http\Controllers\UserController;
use App\Http\Controllers\TenancyController; 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CleaningController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WaterReadingController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
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
    // Invoice generation routes
    Route::post('/invoices/generate/single', [InvoiceController::class, 'generateForCurrentMonth'])->name('invoices.generate.single');
    Route::post('/invoices/generate/all', [InvoiceController::class, 'generateAllMonthlyInvoices'])->name('invoices.generate.all');
    // Payments
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

    // In routes/web.php
    Route::get('/units/{unit}/water-reading', [UnitController::class, 'showWaterReadingForm'])->name('units.water-reading');
    Route::put('/units/{unit}/water-reading', [UnitController::class, 'updateWaterReading'])->name('units.water-reading.update');
    // Get invoice data for a tenancy (includes water readings)
    Route::get('/tenancies/{tenancy}/invoice-data', [InvoiceController::class, 'getInvoiceData'])->name('tenancies.invoice-data');     
    
    // In your routes/web.php file
    Route::post('/tenants/bulk-store', [TenantController::class, 'bulkStore'])->name('tenants.bulkStore');
    
    // Invoices and Payments for Tenants
    Route::post('/tenants/{tenant}/invoices', [TenantController::class, 'storeInvoice'])->name('tenants.store.invoice');
    Route::post('/tenants/{tenant}/payments', [TenantController::class, 'storePayment'])->name('tenants.store.payment');

    // Add these routes after the existing invoice routes
    Route::post('/invoices/{invoice}/add-item', [InvoiceController::class, 'addItemToInvoice'])->name('invoices.items.store');
    Route::put('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'updateInvoiceItem'])->name('invoices.items.update');
    Route::delete('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'removeInvoiceItem'])->name('invoices.items.destroy');
    Route::get('/invoices/{invoice}/edit-data', [InvoiceController::class, 'getInvoiceForEditing'])->name('invoices.edit-data');
    Route::get('/tenancies/{tenancy}/check-invoice-status', [InvoiceController::class, 'checkInvoiceGenerationStatus'])->name('tenancies.check-invoice-status');
    Route::post('/tenancies/{tenancy}/force-invoice', [InvoiceController::class, 'forceGenerateInvoice'])->name('tenancies.force-invoice');


    // Billing history and missing months
    Route::get('/tenancies/{tenancy}/billing-history', [InvoiceController::class, 'getBillingHistory'])->name('tenancies.billing-history');
    Route::post('/tenancies/{tenancy}/generate-missing-invoices', [InvoiceController::class, 'generateMissingInvoices'])->name('tenancies.generate-missing-invoices');
    Route::get('/invoices/{invoice}/details', [InvoiceController::class, 'getInvoiceDetails'])->name('invoices.details');

    // Add this route after the existing invoice routes
    Route::post('/tenancies/{tenancy}/invoices/bulk-missing', [InvoiceController::class, 'generateMissingInvoicesBulk'])->name('tenancies.invoices.bulk-missing');
    Route::post('/invoices/resolve-duplicates', [InvoiceController::class, 'resolveDuplicates'])->name('invoices.resolve-duplicates');

    Route::get('/payments/create-data', [PaymentController::class, 'getCreateData'])->name('payments.create-data');
    // In routes/web.php
    Route::post('/invoices/check-existing', [InvoiceController::class, 'checkExistingInvoices'])->name('invoices.check.existing');
    // In routes/web.php, add this route:

    Route::post('/tenancies/{tenancy}/payments', [PaymentController::class, 'store'])->name('tenancies.payments.store');

        // Add these routes after the existing tenancy routes
        Route::prefix('tenancies/{tenancy}')->group(function () {
            Route::post('/invoices', [InvoiceController::class, 'storeForTenancy'])->name('tenancies.invoices.store');
            Route::get('/invoices', [InvoiceController::class, 'indexForTenancy'])->name('tenancies.invoices.index');
            Route::get('/invoices/check', [InvoiceController::class, 'getExistingInvoice'])->name('tenancies.invoices.check');
        });

        // Add invoice item management routes
        Route::prefix('invoices/{invoice}')->group(function () {
            Route::post('/items', [InvoiceController::class, 'addItemToInvoice'])->name('invoices.items.store');
            Route::put('/items/{item}', [InvoiceController::class, 'updateInvoiceItem'])->name('invoices.items.update');
            Route::delete('/items/{item}', [InvoiceController::class, 'removeInvoiceItem'])->name('invoices.items.destroy');
        });

        Route::resource('users', UserController::class);
        Route::resource('expense-categories', ExpenseCategoryController::class);
        Route::resource('staff', StaffController::class);

        // Meter Reader specific routes
        Route::middleware(['auth', 'role:super_admin,admin,property_manager,meter_reader'])->group(function () {
            Route::get('/meter-readings', [UnitController::class, 'meterReadingsIndex'])->name('meter-readings.index');
            Route::get('/units/{unit}/meter-reading', [UnitController::class, 'showMeterReadingForm'])->name('units.meter-reading');
            Route::put('/units/{unit}/meter-reading', [UnitController::class, 'updateMeterReading'])->name('units.meter-reading.update');
            Route::get('/meter-readings/reports', [UnitController::class, 'meterReadingReports'])->name('meter-readings.reports');
        });

        
// In your water prefix group, add these routes:
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
    
    // NEW ROUTES
    Route::get('/api/water/readings/bulk', [WaterReadingController::class, 'getBulkReadings']);
    Route::get('/api/water/unit-readings/{unitId}', [WaterReadingController::class, 'getUnitReadingsForMonthRange']);

    // These are the actual routes your modal expects:
    Route::post('/readings/bulk-matrix', [WaterReadingController::class, 'storeBulkMatrix'])->name('water.readings.bulk-matrix');
    Route::post('/readings/multi-month', [WaterReadingController::class, 'storeMultiMonth'])->name('water.readings.multi-month');
    Route::get('/api/readings/bulk', [WaterReadingController::class, 'getBulkReadings'])->name('water.api.bulk');
    Route::get('/api/unit-readings/{unitId}', [WaterReadingController::class, 'getUnitReadingsForMonthRange'])->name('water.api.unit-readings');
});

// API routes for units
// Route::get('/api/units/with-water-readings', [WaterReadingController::class, 'getUnitsWithWaterReadings']);
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
        Route::middleware(['auth', 'role:super_admin,admin,security'])->group(function () {
            Route::get('/security/logs', [SecurityController::class, 'logs'])->name('security.logs');
            Route::get('/security/access', [SecurityController::class, 'accessRecords'])->name('security.access');
            Route::post('/security/incidents', [SecurityController::class, 'reportIncident'])->name('security.incidents');
        });

        // Accountant routes (finance only)
        Route::middleware(['auth', 'role:super_admin,admin,accountant'])->group(function () {
            Route::get('/reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
            Route::get('/reports/invoices', [ReportController::class, 'invoices'])->name('reports.invoices');
            Route::get('/reports/payments', [ReportController::class, 'payments'])->name('reports.payments');
        });

        // Tenant specific routes
        Route::middleware(['auth', 'role:tenant'])->group(function () {
            Route::get('/my-invoices', [TenantController::class, 'myInvoices'])->name('tenant.invoices');
            Route::get('/my-payments', [TenantController::class, 'myPayments'])->name('tenant.payments');
            Route::post('/make-payment', [PaymentController::class, 'tenantPayment'])->name('tenant.payment');
            Route::get('/submit-request', [MaintenanceController::class, 'tenantRequest'])->name('tenant.maintenance');
        });

        // Get unit data for meter reading form
        Route::get('/units/{unit}/meter-reading-data', [UnitController::class, 'getMeterReadingData'])->middleware(['auth'])->name('units.meter-reading-data');
        // Maintenance Routes
        Route::resource('maintenance', App\Http\Controllers\MaintenanceController::class);
        Route::get('/tenant/maintenance', [App\Http\Controllers\MaintenanceController::class, 'tenantRequests'])->name('tenant.maintenance');
        Route::get('/tenant/access-logs', [App\Http\Controllers\SecurityController::class, 'tenantAccessLogs'])->name('tenant.access-logs');

        // Security Routes
        Route::resource('security', App\Http\Controllers\SecurityController::class);
        Route::post('/security/access', [App\Http\Controllers\SecurityController::class, 'store'])->name('security.access.store');
        Route::put('/security/access/{accessLog}', [App\Http\Controllers\SecurityController::class, 'update'])->name('security.access.update');

        Route::get('/maintenance/unit/{unit}/history', [MaintenanceController::class, 'getUnitHistory'])
            ->middleware(['auth'])
            ->name('maintenance.unit.history');
        Route::get('/maintenance/{id}/json', [MaintenanceController::class, 'showJson'])->name('maintenance.show.json');
        // Security Logs Routes
        Route::prefix('security')->middleware(['auth'])->group(function () {
            Route::get('/logs', [SecurityController::class, 'index'])->name('security.logs.index');
            Route::get('/logs/{id}', [SecurityController::class, 'show'])->name('security.logs.show');
            Route::post('/logs', [SecurityController::class, 'store'])->name('security.logs.store');
            Route::put('/logs/{id}', [SecurityController::class, 'update'])->name('security.logs.update');
            Route::delete('/logs/{id}', [SecurityController::class, 'destroy'])->name('security.logs.destroy');
        });
        
});


Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('login.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

require __DIR__.'/auth.php';
