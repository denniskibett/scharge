<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MaintenanceController;

Route::middleware(['auth'])->prefix('maintenance')->name('maintenance.')->group(function () {
    // CRUD routes
    Route::get('/', [MaintenanceController::class, 'index'])->name('index');
    Route::get('/create', [MaintenanceController::class, 'create'])->name('create');
    Route::post('/', [MaintenanceController::class, 'store'])->name('store');
    Route::get('/{maintenance}', [MaintenanceController::class, 'show'])->name('show');
    Route::get('/{maintenance}/edit', [MaintenanceController::class, 'edit'])->name('edit');
    Route::put('/{maintenance}', [MaintenanceController::class, 'update'])->name('update');
    Route::delete('/{maintenance}', [MaintenanceController::class, 'destroy'])->name('destroy');
    
    // Custom routes
    Route::get('/unit/{unit}/history', [MaintenanceController::class, 'getUnitHistory'])->name('unit.history');
    Route::get('/{id}/json', [MaintenanceController::class, 'showJson'])->name('show.json');
    Route::put('/{request}/update', [MaintenanceController::class, 'update'])->name('update-status');
    Route::get('/assignments', [MaintenanceController::class, 'assignments'])->name('assignments');
    Route::get('/tenant/requests', [MaintenanceController::class, 'tenantRequests'])->name('tenant.requests');
});