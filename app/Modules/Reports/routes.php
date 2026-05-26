<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;

Route::middleware(['auth'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/financial', [ReportController::class, 'financial'])->name('financial');
    Route::get('/invoices', [ReportController::class, 'invoices'])->name('invoices');
    Route::get('/payments', [ReportController::class, 'payments'])->name('payments');
});