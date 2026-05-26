<?php
// app/Modules/Subscriptions/Routes/web.php

use Illuminate\Support\Facades\Route;
use App\Modules\Subscriptions\Controllers\SubscriptionController;
use App\Modules\Subscriptions\Controllers\PlanController;
use App\Modules\Subscriptions\Controllers\PaymentController;
use App\Modules\Subscriptions\Controllers\Admin\PlanController as AdminPlanController;

Route::middleware(['web', 'auth'])->prefix('admin/subscriptions')->name('admin.subscriptions.')->group(function () {
    
    // API Routes for AJAX calls
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/plans/data', [AdminPlanController::class, 'getData'])->name('plans.data');
        Route::get('/plans/{plan}', [AdminPlanController::class, 'show'])->name('plans.show');
        Route::get('/plans/{plan}/subscribers', [AdminPlanController::class, 'getSubscribers'])->name('plans.subscribers');
    });
    
    // Subscription Plans CRUD
    Route::resource('plans', AdminPlanController::class);
    
    // Company Subscriptions
    Route::get('/companies', [SubscriptionController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [SubscriptionController::class, 'show'])->name('companies.show');
    Route::post('/companies/{company}/subscribe', [SubscriptionController::class, 'subscribe'])->name('companies.subscribe');
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::post('/subscriptions/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');
    
    // Invoices
    Route::get('/subscriptions/{subscription}/invoices', [SubscriptionController::class, 'invoices'])->name('invoices.index');
    Route::get('/invoices/{invoice}/download', [SubscriptionController::class, 'downloadInvoice'])->name('invoices.download');
    
    // Payment Methods
    Route::get('/companies/{company}/payment-methods', [PaymentController::class, 'index'])->name('payment-methods.index');
    Route::get('/companies/{company}/payment-methods/create', [PaymentController::class, 'create'])->name('payment-methods.create');
    Route::post('/companies/{company}/payment-methods', [PaymentController::class, 'store'])->name('payment-methods.store');
    Route::put('/payment-methods/{paymentMethod}/default', [PaymentController::class, 'setDefault'])->name('payment-methods.set-default');
    Route::delete('/payment-methods/{paymentMethod}', [PaymentController::class, 'destroy'])->name('payment-methods.destroy');
});