<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecurityController;

Route::middleware(['auth'])->prefix('security')->name('security.')->group(function () {
    
    // ============================================
    // 📊 DASHBOARD - Main page
    // ============================================
    Route::get('/', [SecurityController::class, 'index'])->name('index');
    Route::get('/stats', [SecurityController::class, 'getStats'])->name('stats');
    
    // ============================================
    // 📥 CHECK-IN / CHECK-OUT (API)
    // ============================================
    Route::get('/api/checked-in', [SecurityController::class, 'getCheckedIn'])->name('api.checked-in');
    Route::post('/api/check-out', [SecurityController::class, 'checkOut'])->name('api.check-out');
    
    // ============================================
    // 📝 QUICK ENTRY - Your new form
    // ============================================
    Route::get('/quick-entry', [SecurityController::class, 'quickEntryView'])->name('quick-entry');
    Route::post('/quick-entry', [SecurityController::class, 'quickEntryStore'])->name('quick-entry.store');
    
    // ============================================
    // 📋 FULL ENTRY - Your new form
    // ============================================
    Route::get('/full-entry', [SecurityController::class, 'fullEntryView'])->name('full-entry');
    Route::post('/full-entry', [SecurityController::class, 'fullEntryStore'])->name('full-entry.store');
    
    // ============================================
    // 📊 REPORTS
    // ============================================
    Route::get('/reports/daily', [SecurityController::class, 'dailyReport'])->name('reports.daily');
    Route::get('/reports/trends', [SecurityController::class, 'trendsReport'])->name('reports.trends');
    
    // ============================================
    // 🔍 VISITOR SEARCH
    // ============================================
    Route::get('/visitors/search', [SecurityController::class, 'searchVisitor'])->name('visitors.search');
    
    // ============================================
    // 📋 LOGS - View all logs with filtering
    // ============================================
    Route::get('/logs', [SecurityController::class, 'viewLogs'])->name('logs.index');
    Route::get('/logs/{id}', [SecurityController::class, 'show'])->name('logs.show');
    
    // ============================================
    // 🏢 API Routes - Estates, Units, Tenants
    // ============================================
    Route::get('/estates', [SecurityController::class, 'getEstates'])->name('estates');
    Route::get('/units', [SecurityController::class, 'getUnitsByEstate'])->name('units');
    Route::get('/tenants', [SecurityController::class, 'getTenantsByUnit'])->name('tenants');
    Route::get('/visitors', [SecurityController::class, 'getVisitorsByTenant'])->name('visitors');
});