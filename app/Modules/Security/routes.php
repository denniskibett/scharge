<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecurityController;

Route::middleware(['auth'])->prefix('security')->name('security.')->group(function () {
    // Main routes
    Route::get('/', [SecurityController::class, 'index'])->name('index');
    Route::get('/logs', [SecurityController::class, 'logs'])->name('logs');
    Route::get('/access', [SecurityController::class, 'accessRecords'])->name('access');
    Route::post('/incidents', [SecurityController::class, 'reportIncident'])->name('incidents');
    
    // CRUD operations
    Route::get('/logs', [SecurityController::class, 'index'])->name('logs.index');
    Route::get('/logs/{id}', [SecurityController::class, 'show'])->name('logs.show');
    Route::post('/logs', [SecurityController::class, 'store'])->name('logs.store');
    Route::put('/logs/{id}', [SecurityController::class, 'update'])->name('logs.update');
    Route::delete('/logs/{id}', [SecurityController::class, 'destroy'])->name('logs.destroy');
    
    // Access operations
    Route::post('/access', [SecurityController::class, 'store'])->name('access.store');
    Route::put('/access/{accessLog}', [SecurityController::class, 'update'])->name('access.update');
    
    // API Routes (keep as is)
    Route::get('/estates', [SecurityController::class, 'getEstates'])->name('estates');
    Route::get('/units', [SecurityController::class, 'getUnitsByEstate'])->name('units');
    Route::get('/tenants', [SecurityController::class, 'getTenantsByUnit'])->name('tenants');
    Route::get('/visitors', [SecurityController::class, 'getVisitorsByTenant'])->name('visitors');
    Route::get('/logs-by-tenant', [SecurityController::class, 'getSecurityLogsByTenant'])->name('logs-by-tenant');
    
    // Tenant access logs
    Route::get('/tenant/access-logs', [SecurityController::class, 'tenantAccessLogs'])->name('tenant.access-logs');
});