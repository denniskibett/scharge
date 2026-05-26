<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenantController;

Route::middleware(['auth'])->prefix('tenants')->name('tenants.')->group(function () {
    // CRUD routes
    Route::get('/', [TenantController::class, 'index'])->name('index');
    Route::get('/create', [TenantController::class, 'create'])->name('create');
    Route::post('/', [TenantController::class, 'store'])->name('store');
    Route::get('/{tenant}', [TenantController::class, 'show'])->name('show');
    Route::get('/{tenant}/edit', [TenantController::class, 'edit'])->name('edit');
    Route::put('/{tenant}', [TenantController::class, 'update'])->name('update');
    Route::delete('/{tenant}', [TenantController::class, 'destroy'])->name('destroy');
    
    // Custom routes
    Route::post('/bulk-store', [TenantController::class, 'bulkStore'])->name('bulkStore');
    Route::post('/{tenant}/invoices', [TenantController::class, 'storeInvoice'])->name('store.invoice');
    Route::post('/{tenant}/payments', [TenantController::class, 'storePayment'])->name('store.payment');
    
    // Tenant specific views
    Route::get('/my-invoices', [TenantController::class, 'myInvoices'])->name('my-invoices');
    Route::get('/my-payments', [TenantController::class, 'myPayments'])->name('my-payments');
});