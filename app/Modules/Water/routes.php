<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WaterReadingController;

Route::middleware(['auth'])->prefix('water')->name('water.')->group(function () {
    // Main routes
    Route::get('/', [WaterReadingController::class, 'index'])->name('index');
    Route::get('/unit/{unit}/statement', [WaterReadingController::class, 'statement'])->name('statement');
    
    // Reading operations
    Route::post('/readings', [WaterReadingController::class, 'store'])->name('readings.store');
    Route::post('/readings/bulk', [WaterReadingController::class, 'storeBulk'])->name('readings.bulk');
    Route::post('/readings/bulk-matrix', [WaterReadingController::class, 'storeBulkMatrix'])->name('readings.bulk-matrix');
    Route::post('/readings/multi-month', [WaterReadingController::class, 'storeMultiMonth'])->name('readings.multi-month');
    Route::put('/readings/{reading}/reconcile', [WaterReadingController::class, 'reconcile'])->name('readings.reconcile');
    
    // API routes
    Route::get('/last-reading/{unitId}', [WaterReadingController::class, 'getLastReading']);
    Route::get('/unit-history/{unitId}', [WaterReadingController::class, 'getUnitWaterHistory']);
    Route::get('/unit/{unit}/readings', [WaterReadingController::class, 'getUnitReadings']);
    Route::post('/report', [WaterReadingController::class, 'generateReport']);
    Route::get('/api/water/readings/bulk', [WaterReadingController::class, 'getBulkReadings']);
    Route::get('/api/water/unit-readings/{unitId}', [WaterReadingController::class, 'getUnitReadingsForMonthRange']);
});