<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;

Route::middleware(['auth'])->prefix('payments')->name('payments.')->group(function () {
    // Invoice CRUD
    Route::resource('invoices', InvoiceController::class);
    Route::resource('payments', PaymentController::class);
    
    // Invoice custom routes
    Route::post('/invoices/generate/single', [InvoiceController::class, 'generateForCurrentMonth'])->name('invoices.generate.single');
    Route::post('/invoices/generate/all', [InvoiceController::class, 'generateAllMonthlyInvoices'])->name('invoices.generate.all');
    Route::post('/invoices/payments', [InvoiceController::class, 'processPayment'])->name('invoices.payments.store');
    Route::post('/invoices/bulk-create', [InvoiceController::class, 'bulkCreate'])->name('invoices.bulk.create');
    Route::post('/invoices/check-existing', [InvoiceController::class, 'checkExistingInvoices'])->name('invoices.check.existing');
    Route::get('/invoices/{invoice}/details', [InvoiceController::class, 'getInvoiceDetails'])->name('invoices.details');
    Route::get('/invoices/{invoice}/edit-data', [InvoiceController::class, 'getInvoiceForEditing'])->name('invoices.edit-data');
    
    // Invoice items
    Route::post('/invoices/{invoice}/add-item', [InvoiceController::class, 'addItemToInvoice'])->name('invoices.items.store');
    Route::put('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'updateInvoiceItem'])->name('invoices.items.update');
    Route::delete('/invoices/{invoice}/items/{item}', [InvoiceController::class, 'removeInvoiceItem'])->name('invoices.items.destroy');
    
    // Tenancy invoice routes
    Route::prefix('tenancies/{tenancy}')->group(function () {
        Route::post('/invoices', [InvoiceController::class, 'storeForTenancy'])->name('tenancies.invoices.store');
        Route::get('/invoices', [InvoiceController::class, 'indexForTenancy'])->name('tenancies.invoices.index');
        Route::get('/invoices/check', [InvoiceController::class, 'getExistingInvoice'])->name('tenancies.invoices.check');
        Route::get('/invoice-data', [InvoiceController::class, 'getInvoiceData'])->name('tenancies.invoice-data');
        Route::get('/check-invoice-status', [InvoiceController::class, 'checkInvoiceGenerationStatus'])->name('tenancies.check-invoice-status');
        Route::post('/force-invoice', [InvoiceController::class, 'forceGenerateInvoice'])->name('tenancies.force-invoice');
        Route::get('/billing-history', [InvoiceController::class, 'getBillingHistory'])->name('tenancies.billing-history');
        Route::post('/generate-missing-invoices', [InvoiceController::class, 'generateMissingInvoices'])->name('tenancies.generate-missing-invoices');
        Route::post('/invoices/bulk-missing', [InvoiceController::class, 'generateMissingInvoicesBulk'])->name('tenancies.invoices.bulk-missing');
        Route::post('/payments', [PaymentController::class, 'store'])->name('tenancies.payments.store');
    });
    
    // Payment custom routes
    Route::post('/payments/bulk', [PaymentController::class, 'bulkStore'])->name('payments.bulk.store');
    Route::get('/payments/create-data', [PaymentController::class, 'getCreateData'])->name('payments.create-data');
    Route::post('/invoices/resolve-duplicates', [InvoiceController::class, 'resolveDuplicates'])->name('invoices.resolve-duplicates');
    
    // API routes
    Route::get('/api/invoices/{invoice}/details', [PaymentController::class, 'getInvoiceDetails'])->name('api.invoices.details');
});