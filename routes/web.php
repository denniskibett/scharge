<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\EstateController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PayeeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\StaffController; // Legacy base controller (not used for admin)
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenancyController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\CleaningController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WaterReadingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MpesaController;
use App\Modules\Subscriptions\Controllers\SubscriptionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AccountManagerController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Campaign direct route
Route::get('/campaign/{id}', function($id) {
    try {
        $campaign = DB::table('sms_campaigns')->where('id', $id)->first();
        if (!$campaign) return response()->json(['error' => 'Campaign not found'], 404);

        $recipients = DB::table('campaign_recipients')->where('campaign_id', $id)->get()->map(function($r) {
            $tenant = null; $user = null; $unit = null; $estate = null;
            $tenantName = 'Unknown'; $unitNumber = 'N/A'; $estateName = 'N/A';

            if ($r->tenant_id) {
                $tenant = DB::table('tenants')->where('id', $r->tenant_id)->first();
                if ($tenant) {
                    $user = $tenant->user_id ? DB::table('users')->where('id', $tenant->user_id)->first() : null;
                    $tenantName = $user ? $user->name : ($tenant->name ?? 'Unknown');
                    $tenancy = DB::table('tenancies')->where('tenant_id', $tenant->id)->where('status', 'active')->first();
                    if ($tenancy && $tenancy->unit_id) {
                        $unit = DB::table('units')->where('id', $tenancy->unit_id)->first();
                        if ($unit) {
                            $unitNumber = $unit->unit_number ?? 'N/A';
                            if ($unit->estate_id) {
                                $estate = DB::table('estates')->where('id', $unit->estate_id)->first();
                                $estateName = $estate ? $estate->name : 'N/A';
                            }
                        }
                    }
                }
            } else {
                $phone = $r->phone_number;
                if (!empty($phone)) {
                    $clean = preg_replace('/[^0-9]/', '', $phone);
                    if (strlen($clean) >= 9) {
                        if (substr($clean,0,1)==='0') $clean = substr($clean,1);
                        if (substr($clean,0,3)!=='254') $clean = '254'.$clean;
                        $user = DB::table('users')->where('phone', 'like', '%'.substr($clean,-9))->orWhere('phone', $clean)->first();
                        if ($user) {
                            $tenantName = $user->name ?? 'Unknown';
                            $tenant = DB::table('tenants')->where('user_id', $user->id)->first();
                            if ($tenant) {
                                $tenancy = DB::table('tenancies')->where('tenant_id', $tenant->id)->where('status', 'active')->first();
                                if ($tenancy && $tenancy->unit_id) {
                                    $unit = DB::table('units')->where('id', $tenancy->unit_id)->first();
                                    if ($unit) {
                                        $unitNumber = $unit->unit_number ?? 'N/A';
                                        if ($unit->estate_id) {
                                            $estate = DB::table('estates')->where('id', $unit->estate_id)->first();
                                            $estateName = $estate ? $estate->name : 'N/A';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $network = $parts = $cost = $deliveredTime = '';
            $providerStatus = $r->provider_status ?? '';
            if ($r->provider_response) {
                try {
                    $data = json_decode($r->provider_response, true);
                    if (is_array($data)) {
                        $network = $data['network'] ?? $data['provider'] ?? '';
                        $parts = $data['parts'] ?? $data['message_parts'] ?? '';
                        $cost = $data['cost'] ?? '';
                        $deliveredTime = $data['delivered_at'] ?? $data['delivered_time'] ?? '';
                    }
                } catch (\Exception $e) {}
            }

            return (object) [
                'id' => $r->id,
                'tenant_id' => $r->tenant_id,
                'phone_number' => $r->phone_number,
                'message' => $r->message,
                'status' => $r->status,
                'sent_at' => $r->sent_at,
                'error_message' => $r->error_message,
                'provider_status' => $providerStatus,
                'provider_response' => $r->provider_response,
                'tenant_name' => $tenantName,
                'unit_number' => $unitNumber,
                'estate_name' => $estateName,
                'network' => $network,
                'parts' => $parts,
                'cost' => $cost,
                'delivered_time' => $deliveredTime,
            ];
        });

        return response()->json(['success'=>true, 'campaign'=>$campaign, 'recipients'=>$recipients, 'recipient_count'=>$recipients->count()]);
    } catch (\Exception $e) {
        Log::error('Campaign direct route error: '.$e->getMessage());
        return response()->json(['error'=>$e->getMessage()], 500);
    }
});

// Public
Route::get('/', function () { return view('welcome'); });

// Auth
require __DIR__.'/auth.php';
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('login.google');
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);

// Authenticated
Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['verified'])->name('dashboard');
    Route::get('mtickets', function () { return view('mtickets'); })->name('mtickets');

    // Profile
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
        Route::delete('/avatar', [ProfileController::class, 'deleteAvatar'])->name('delete-avatar');
        Route::put('/address', [ProfileController::class, 'updateAddress'])->name('address.update');
        Route::get('/data', [ProfileController::class, 'getUserData'])->name('data');
    });

    // Static pages
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

    // System
    Route::prefix('system')->name('system.')->group(function () {
        Route::get('/', [SystemController::class, 'index'])->name('index');
        Route::put('/update', [SystemController::class, 'update'])->name('update');
        Route::get('/clear-cache', [SystemController::class, 'clearCache'])->name('clear-cache');
        Route::get('/backup', [SystemController::class, 'backupDatabase'])->name('backup');
        Route::post('/toggle-maintenance', [SystemController::class, 'toggleMaintenance'])->name('toggle-maintenance');
        Route::post('/debug', [SystemController::class, 'debug'])->name('debug');
    });

    // Admin User Management
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->parameters(['users' => 'user']);
        Route::post('/users/filter', [UserController::class, 'filter'])->name('users.filter');
        Route::post('/users/bulk-action', [UserController::class, 'bulkAction'])->name('users.bulk-action');
        Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
        Route::post('/users/quick-update/{id}', [UserController::class, 'quickUpdate'])->name('users.quick-update');
        Route::post('/users/{user}/verify', [UserController::class, 'verify'])->name('users.verify');
        Route::post('/users/{user}/assign-company', [UserController::class, 'assignCompany'])->name('users.assign-company');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::get('/roles/list', [UserController::class, 'getRoles'])->name('roles.list');
    });

    // Admin Staff Management (FIXED – using Admin controller)
    Route::prefix('admin/staff')->name('admin.staff.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\StaffController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\StaffController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\StaffController::class, 'store'])->name('store');
        Route::post('/filter', [App\Http\Controllers\Admin\StaffController::class, 'filter'])->name('filter');
        Route::post('/bulk-action', [App\Http\Controllers\Admin\StaffController::class, 'bulkAction'])->name('bulk-action');
        Route::get('/export', [App\Http\Controllers\Admin\StaffController::class, 'export'])->name('export');
        Route::post('/quick-update/{id}', [App\Http\Controllers\Admin\StaffController::class, 'quickUpdate'])->name('quick-update');
        Route::get('/{id}', [App\Http\Controllers\Admin\StaffController::class, 'show'])->name('show');
        Route::delete('/{id}', [App\Http\Controllers\Admin\StaffController::class, 'destroy'])->name('destroy');
    });

    // Legacy staff redirect
    Route::get('/staff', function () {
        return redirect()->route('admin.staff.index');
    })->name('staff.index');

    // Roles (legacy)
    Route::prefix('admin/roles')->name('admin.roles.')->group(function () {
        Route::get('/list', [UserController::class, 'getRoles'])->name('list');
    });

    // API Users
    Route::prefix('api/users')->name('api.users.')->group(function () {
        Route::get('/', [UserController::class, 'getUsers'])->name('index');
        Route::get('/staff', [UserController::class, 'getStaffUsers'])->name('staff');
    });

    // Core resources
    Route::resource('estates', EstateController::class);
    Route::resource('units', UnitController::class);
    Route::get('/units/{unit}/water-reading', [UnitController::class, 'showWaterReadingForm'])->name('units.water-reading');
    Route::put('/units/{unit}/water-reading', [UnitController::class, 'updateWaterReading'])->name('units.water-reading.update');
    Route::get('/units/{unit}/meter-reading-data', [UnitController::class, 'getMeterReadingData'])->name('units.meter-reading-data');
    Route::get('/units/{unit}/meter-reading', [UnitController::class, 'showMeterReadingForm'])->name('units.meter-reading');
    Route::put('/units/{unit}/meter-reading', [UnitController::class, 'updateMeterReading'])->name('units.meter-reading.update');

    Route::resource('expenses', ExpenseController::class);
    Route::resource('expense-categories', ExpenseCategoryController::class);
    Route::resource('payees', PayeeController::class);

    Route::resource('tenants', TenantController::class);
    Route::post('/tenants/bulk-store', [TenantController::class, 'bulkStore'])->name('tenants.bulkStore');
    Route::post('/tenants/{tenant}/invoices', [TenantController::class, 'storeInvoice'])->name('tenants.store.invoice');
    Route::post('/tenants/{tenant}/payments', [TenantController::class, 'storePayment'])->name('tenants.store.payment');

    Route::resource('tenancies', TenancyController::class);
    Route::get('/tenancies/{tenancy}/invoice-data', [InvoiceController::class, 'getInvoiceData'])->name('tenancies.invoice-data');
    Route::get('/tenancies/{tenancy}/check-invoice-status', [InvoiceController::class, 'checkInvoiceGenerationStatus'])->name('tenancies.check-invoice-status');
    Route::post('/tenancies/{tenancy}/force-invoice', [InvoiceController::class, 'forceGenerateInvoice'])->name('tenancies.force-invoice');
    Route::get('/tenancies/{tenancy}/billing-history', [InvoiceController::class, 'getBillingHistory'])->name('tenancies.billing-history');
    Route::post('/tenancies/{tenancy}/generate-missing-invoices', [InvoiceController::class, 'generateMissingInvoices'])->name('tenancies.generate-missing-invoices');
    Route::post('/tenancies/{tenancy}/invoices/bulk-missing', [InvoiceController::class, 'generateMissingInvoicesBulk'])->name('tenancies.invoices.bulk-missing');
    Route::post('/tenancies/{tenancy}/payments', [PaymentController::class, 'store'])->name('tenancies.payments.store');

    Route::resource('invoices', InvoiceController::class);
    Route::post('/invoices/generate/single', [InvoiceController::class, 'generateSingleInvoice'])->name('invoices.generate.single');
    Route::post('/invoices/generate/all', [InvoiceController::class, 'generateAllInvoices'])->name('invoices.generate.all');
    Route::post('/invoices/payments', [InvoiceController::class, 'processPayment'])->name('invoices.payments.store');
    Route::post('/invoices/bulk-create', [InvoiceController::class, 'bulkCreate'])->name('invoices.bulk.create');
    Route::post('/invoices/check-existing', [InvoiceController::class, 'checkExistingInvoices'])->name('invoices.check.existing');
    Route::post('/invoices/resolve-duplicates', [InvoiceController::class, 'resolveDuplicates'])->name('invoices.resolve-duplicates');
    Route::post('/invoices/bulk-reconcile', [InvoiceController::class, 'bulkReconcileWaterCharges'])->name('invoices.bulk-reconcile');
    Route::get('/invoices/{invoice}/edit-data', [InvoiceController::class, 'getInvoiceForEditing'])->name('invoices.edit-data');
    Route::get('/invoices/{invoice}/details', [InvoiceController::class, 'getInvoiceDetails'])->name('invoices.details');

    Route::prefix('invoices/{invoice}')->group(function () {
        Route::post('/items', [InvoiceController::class, 'addItemToInvoice'])->name('invoices.items.store');
        Route::put('/items/{item}', [InvoiceController::class, 'updateInvoiceItem'])->name('invoices.items.update');
        Route::delete('/items/{item}', [InvoiceController::class, 'removeInvoiceItem'])->name('invoices.items.destroy');
    });

    Route::prefix('tenancies/{tenancy}')->name('tenancies.')->group(function () {
        Route::post('/invoices', [InvoiceController::class, 'storeForTenancy'])->name('invoices.store');
        Route::get('/invoices', [InvoiceController::class, 'indexForTenancy'])->name('invoices.index');
        Route::get('/invoices/check', [InvoiceController::class, 'getExistingInvoice'])->name('invoices.check');
    });

    Route::resource('payments', PaymentController::class);
    Route::post('/payments/bulk', [PaymentController::class, 'bulkStore'])->name('payments.bulk.store');
    Route::get('/payments/create-data', [PaymentController::class, 'getCreateData'])->name('payments.create-data');
    Route::get('/payments/tenant/{tenantId}/invoices', [PaymentController::class, 'getTenantInvoices'])->name('payments.tenant.invoices');
    Route::get('/api/invoices/{invoice}/details', [PaymentController::class, 'getInvoiceDetails'])->name('api.invoices.details');

    // Water
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

    // Meter Reader
    Route::middleware(['role:super_admin,admin,property_manager,meter_reader'])->group(function () {
        Route::get('/meter-readings', [UnitController::class, 'meterReadingsIndex'])->name('meter-readings.index');
        Route::get('/meter-readings/reports', [UnitController::class, 'meterReadingReports'])->name('meter-readings.reports');
    });

    // Cleaning
    Route::middleware(['role:super_admin,admin,property_manager,cleaning_staff'])->group(function () {
        Route::get('/cleaning/tasks', [CleaningController::class, 'index'])->name('cleaning.tasks');
        Route::put('/cleaning/tasks/{task}/complete', [CleaningController::class, 'markComplete'])->name('cleaning.tasks.complete');
        Route::get('/cleaning/schedule', [CleaningController::class, 'schedule'])->name('cleaning.schedule');
    });

    // Maintenance
    Route::resource('maintenance', MaintenanceController::class);
    Route::get('/maintenance/unit/{unit}/history', [MaintenanceController::class, 'getUnitHistory'])->name('maintenance.unit.history');
    Route::get('/maintenance/{id}/json', [MaintenanceController::class, 'showJson'])->name('maintenance.show.json');
    Route::get('/tenant/maintenance', [MaintenanceController::class, 'tenantRequests'])->name('tenant.maintenance');
    Route::get('/maintenance/{maintenance}/edit-data', [MaintenanceController::class, 'getEditData'])->name('maintenance.edit-data');
    Route::middleware(['role:super_admin,admin,property_manager,maintenance'])->group(function () {
        Route::get('/maintenance/assignments', [MaintenanceController::class, 'assignments'])->name('maintenance.assignments');
    });

    // Staff legacy redirect (already above – we keep one)
    Route::get('/staff', function () {
        return redirect()->route('admin.staff.index');
    })->name('staff.index');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/financial', [ReportController::class, 'financial'])->name('financial');
        Route::get('/invoices', [ReportController::class, 'invoices'])->name('invoices');
        Route::get('/payments', [ReportController::class, 'payments'])->name('payments');
    });

    // Wallet
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

    // API Wallet
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

    // Admin Wallet
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

    // Tenant Wallet
    Route::prefix('wallet')->name('tenant.wallet.')->group(function () {
        Route::post('/deposit', [App\Modules\Payments\Controllers\WalletController::class, 'deposit'])->name('deposit');
        Route::post('/withdraw', [App\Modules\Payments\Controllers\WalletController::class, 'withdraw'])->name('withdraw');
        Route::post('/transfer', [App\Modules\Payments\Controllers\WalletController::class, 'transfer'])->name('transfer');
        Route::post('/pay-invoice/{invoiceId}', [App\Modules\Payments\Controllers\WalletController::class, 'payInvoiceForm'])->name('pay-invoice');
        Route::get('/transactions/export', [App\Modules\Payments\Controllers\WalletController::class, 'exportTransactions'])->name('transactions.export');
    });

    // Subscriptions
    Route::prefix('admin/subscriptions')->name('admin.subscriptions.')->group(function () {
        Route::resource('plans', SubscriptionController::class)->parameters(['plans' => 'plan'])->only(['index', 'show', 'store', 'update', 'destroy']);
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

    // Companies
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

    // Account Managers
    Route::prefix('admin/account-managers')->name('admin.account-managers.')->group(function () {
        Route::get('/', [AccountManagerController::class, 'index'])->name('index');
        Route::get('/create', [AccountManagerController::class, 'create'])->name('create');
        Route::post('/', [AccountManagerController::class, 'store'])->name('store');
        Route::get('/{id}', [AccountManagerController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AccountManagerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AccountManagerController::class, 'update'])->name('update');
        Route::delete('/{id}', [AccountManagerController::class, 'destroy'])->name('destroy');
    });

    // Tenant specific
    Route::get('/my-invoices', [TenantController::class, 'myInvoices'])->name('tenant.invoices');
    Route::get('/my-payments', [TenantController::class, 'myPayments'])->name('tenant.payments');
    Route::post('/make-payment', [PaymentController::class, 'tenantPayment'])->name('tenant.payment');

    // M-PESA
    Route::prefix('payments/mpesa')->name('payments.mpesa.')->group(function () {
        Route::post('/stk-push', [PaymentController::class, 'initiateMpesaStkPush'])->name('stk-push');
        Route::get('/status', [PaymentController::class, 'checkMpesaStatus'])->name('status');
        Route::get('/pay', [MpesaController::class, 'showPaymentForm'])->name('form');
        Route::post('/pay', [MpesaController::class, 'stkPush'])->name('process');
    });

    // SMS API
    Route::prefix('api/sms')->group(function () {
        Route::get('/campaigns/kenyasms', [App\Modules\SMS\Controllers\CampaignController::class, 'listFromKenyaSMS']);
        Route::post('/campaigns/kenyasms/{campaignId}/import', [App\Modules\SMS\Controllers\CampaignController::class, 'importFromKenyaSMS']);
        Route::post('/campaigns/import-kenyasms', [App\Modules\SMS\Controllers\CampaignController::class, 'importKenyaSmsCampaigns']);

        Route::get('/campaigns', [App\Modules\SMS\Controllers\CampaignController::class, 'apiIndex']);
        Route::post('/campaigns', [App\Modules\SMS\Controllers\CampaignController::class, 'store']);
        Route::get('/campaigns/{id}', [App\Modules\SMS\Controllers\CampaignController::class, 'getDetails']);
        Route::post('/campaigns/{id}/send', [App\Modules\SMS\Controllers\CampaignController::class, 'send']);
        Route::post('/campaigns/{id}/retry', [App\Modules\SMS\Controllers\CampaignController::class, 'retry']);
        Route::delete('/campaigns/{id}', [App\Modules\SMS\Controllers\CampaignController::class, 'destroy']);
        Route::post('/campaigns/{id}/resend-failed', [App\Modules\SMS\Controllers\CampaignController::class, 'resendFailed']);
        Route::post('/campaigns/{id}/resend-pending', [App\Modules\SMS\Controllers\CampaignController::class, 'resendPending']);
        Route::post('/campaigns/{id}/check-pending', [App\Modules\SMS\Controllers\CampaignController::class, 'checkPendingStatus']);
        Route::post('/campaigns/{id}/sync-status', [App\Modules\SMS\Controllers\CampaignController::class, 'syncStatus']);
        Route::post('/campaigns/{id}/sync-recipients', [App\Modules\SMS\Controllers\CampaignController::class, 'syncRecipientsFromKenyaSMS']);
        Route::post('/recipients/{id}/resend', [App\Modules\SMS\Controllers\CampaignController::class, 'resendIndividualRecipient']);
        Route::get('/campaigns/{id}/status-summary', [App\Modules\SMS\Controllers\CampaignController::class, 'getStatusSummary']);
        Route::get('/campaigns/{id}/invalid-recipients', [App\Modules\SMS\Controllers\CampaignController::class, 'getInvalidRecipients']);
        Route::get('/campaigns/{id}/other-network-recipients', [App\Modules\SMS\Controllers\CampaignController::class, 'getOtherNetworkRecipients']);
        Route::put('/tenants/{tenantId}/phone', [App\Modules\SMS\Controllers\CampaignController::class, 'updateTenantPhone']);
        Route::post('/preview-invoices', [App\Modules\SMS\Controllers\CampaignController::class, 'previewInvoices']);
    });
});

// Module includes
require base_path('app/Modules/SMS/routes.php');
require base_path('app/Modules/Security/routes.php');

// Users module temporarily disabled
// Route::prefix('users')->group(function () { require base_path('app/Modules/Users/routes.php'); });

// Test SMS routes
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

// Debug routes
Route::get('/debug-mpesa', function () {
    try {
        $mpesa = new \App\Services\MpesaService();
        $result = $mpesa->getAccessTokenWithDebug();
        return response()->json([
            'environment' => env('MPESA_ENVIRONMENT'),
            'consumer_key' => env('MPESA_CONSUMER_KEY') ? substr(env('MPESA_CONSUMER_KEY'), 0, 20).'...' : 'MISSING',
            'consumer_secret' => env('MPESA_CONSUMER_SECRET') ? substr(env('MPESA_CONSUMER_SECRET'), 0, 20).'...' : 'MISSING',
            'base_url' => 'https://sandbox.safaricom.co.ke',
            'auth_result' => $result,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
    }
});

// Fallback API endpoints
Route::get('/water/api/water/readings/bulk', [WaterReadingController::class, 'getBulkReadings']);

Route::prefix('api/wallet')->middleware(['auth'])->group(function () {
    Route::get('/accountant/transactions', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetTransactionsForAccountant'])->name('api.wallet.accountant.transactions');
    Route::get('/pending-deposits', [App\Modules\Payments\Controllers\WalletController::class, 'apiGetPendingDeposits'])->name('api.wallet.pending-deposits');
    Route::post('/approve-deposit/{transactionId}', [App\Modules\Payments\Controllers\WalletController::class, 'apiApproveDeposit'])->name('api.wallet.approve-deposit');
    Route::post('/reject-deposit/{transactionId}', [App\Modules\Payments\Controllers\WalletController::class, 'apiRejectDeposit'])->name('api.wallet.reject-deposit');
});