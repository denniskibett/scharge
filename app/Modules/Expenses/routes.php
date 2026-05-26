<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\PayeeController;

Route::middleware(['auth'])->prefix('expenses')->name('expenses.')->group(function () {
    // CRUD routes - one liner for standard resources
    Route::resource('/', ExpenseController::class)->parameters(['' => 'expense']);
    
    // Custom routes
    Route::get('/categories', [ExpenseCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [ExpenseCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [ExpenseCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [ExpenseCategoryController::class, 'destroy'])->name('categories.destroy');
    
    Route::get('/payees', [PayeeController::class, 'index'])->name('payees.index');
    Route::post('/payees', [PayeeController::class, 'store'])->name('payees.store');
    Route::put('/payees/{payee}', [PayeeController::class, 'update'])->name('payees.update');
    Route::delete('/payees/{payee}', [PayeeController::class, 'destroy'])->name('payees.destroy');
});