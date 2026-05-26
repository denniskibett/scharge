<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CompanyController;

Route::middleware(['auth', 'role:super_admin,admin'])->prefix('admin/companies')->name('admin.companies.')->group(function () {
    Route::get('/', [CompanyController::class, 'index'])->name('index');
    Route::get('/data', [CompanyController::class, 'getCompaniesData'])->name('data');
    Route::get('/{company}', [CompanyController::class, 'show'])->name('show');
    Route::post('/', [CompanyController::class, 'store'])->name('store');
    Route::put('/{company}', [CompanyController::class, 'update'])->name('update');
    Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('destroy');
    
    Route::get('/{company}/users', [CompanyController::class, 'getCompanyUsers'])->name('get-users');
    Route::post('/{company}/users', [CompanyController::class, 'addUser'])->name('add-user');
    Route::delete('/{company}/users/{user}', [CompanyController::class, 'removeUser'])->name('remove-user');
    Route::put('/{company}/users/{user}/role', [CompanyController::class, 'updateUserRole'])->name('update-user-role');
    Route::get('/{company}/estates', [CompanyController::class, 'getCompanyEstates'])->name('estates');
    Route::get('/{company}/subscriptions', [CompanyController::class, 'getCompanySubscriptions'])->name('subscriptions');
    Route::get('/{company}/invoices', [CompanyController::class, 'getCompanyInvoices'])->name('invoices');
    Route::get('/{company}/payments', [CompanyController::class, 'getCompanyPayments'])->name('payments');
    Route::get('/{company}/staff', [CompanyController::class, 'getCompanyStaff'])->name('staff');
});