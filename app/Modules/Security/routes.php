<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecurityController;

Route::middleware(['auth', 'access:security'])->prefix('security')->name('security.')->group(function () {
    
    // ============================================
    // DASHBOARD - Main page
    // ============================================
    Route::get('/', [SecurityController::class, 'index'])->name('index');
    Route::get('/stats', [SecurityController::class, 'getStats'])->name('stats');
    
    // ============================================
    // CHECK-IN / CHECK-OUT (API)
    // ============================================
    Route::get('/api/checked-in', [SecurityController::class, 'getCheckedIn'])->name('api.checked-in');
    Route::post('/api/check-out', [SecurityController::class, 'checkOut'])->name('api.check-out');
    
    // ============================================
    // QUICK ENTRY - Your new form
    // ============================================
    Route::get('/quick-entry', [SecurityController::class, 'quickEntryView'])->name('quick-entry');
    Route::post('/quick-entry', [SecurityController::class, 'quickEntryStore'])->name('quick-entry.store');
    
    // ============================================
    // FULL ENTRY - Your new form
    // ============================================
    Route::get('/full-entry', [SecurityController::class, 'fullEntryView'])->name('full-entry');
    Route::post('/full-entry', [SecurityController::class, 'fullEntryStore'])->name('full-entry.store');
    
    // ============================================
    // REPORTS
    // ============================================
    Route::get('/reports/daily', [SecurityController::class, 'dailyReport'])->name('reports.daily');
    Route::get('/reports/trends', [SecurityController::class, 'trendsReport'])->name('reports.trends');
    
    // ============================================
    // VISITOR SEARCH
    // ============================================
    Route::get('/visitors/search', [SecurityController::class, 'searchVisitor'])->name('visitors.search');
    
    // ============================================
    // LOGS - View all logs with filtering
    // ============================================
    Route::get('/logs', [SecurityController::class, 'viewLogs'])->name('logs.index');
    Route::get('/logs/{id}', [SecurityController::class, 'show'])->name('logs.show');
    
    // ============================================
    // API Routes - Properties, Units, Tenants
    // ============================================
    Route::get('/properties', [SecurityController::class, 'getProperties'])->name('properties');
    Route::get('/units', [SecurityController::class, 'getUnitsByProperty'])->name('units');
    Route::get('/tenants', [SecurityController::class, 'getTenantsByUnit'])->name('tenants');
    Route::get('/visitors', [SecurityController::class, 'getVisitorsByTenant'])->name('visitors');
});
// ============================================
// GUESTS & GATE: entry codes for every business (BnB hosts, managers, tenants) and the guard's verify screen.
// Scoping by unit is done in GateController / App\Services\Gate\Gate.
// ============================================
Route::middleware('auth')->prefix('gate')->name('gate.')->group(function () {
    Route::get('/', [App\Http\Controllers\GateController::class, 'index'])->name('index');
    Route::get('/verify', [App\Http\Controllers\GateController::class, 'verify'])->middleware('throttle:60,1')->name('verify');
    Route::get('/visitors', [App\Http\Controllers\GateController::class, 'visitors'])->name('visitors');
    Route::post('/visitors/{place}/block', [App\Http\Controllers\GateController::class, 'block'])->name('visitors.block');
    Route::get('/lookup', [App\Http\Controllers\GateController::class, 'lookup'])->middleware('throttle:60,1')->name('lookup');
    Route::get('/passes/create', [App\Http\Controllers\GateController::class, 'create'])->name('passes.create');
    Route::post('/passes', [App\Http\Controllers\GateController::class, 'store'])->name('passes.store');
    Route::get('/passes/{pass}', [App\Http\Controllers\GateController::class, 'show'])->name('passes.show');
    Route::post('/passes/{pass}', [App\Http\Controllers\GateController::class, 'act'])->name('passes.act');
});

// The guest's own pass page: no login, the 40-character token is the key.
Route::get('/pass/{token}', [App\Http\Controllers\PublicPassController::class, 'show'])->middleware('throttle:30,1')->name('pass.show');
Route::post('/pass/{token}', [App\Http\Controllers\PublicPassController::class, 'act'])->middleware('throttle:10,1')->name('pass.act');
