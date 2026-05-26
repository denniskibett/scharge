<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EstateController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\TenancyController;
use App\Http\Controllers\WaterReadingController;

Route::middleware(['auth'])->prefix('properties')->name('properties.')->group(function () {
    // Estates CRUD
    Route::resource('estates', EstateController::class);
    
    // Units CRUD
    Route::resource('units', UnitController::class);
    
    // Tenancies CRUD
    Route::resource('tenancies', TenancyController::class);
    
    // Unit custom routes
    Route::get('/units/{unit}/water-reading', [UnitController::class, 'showWaterReadingForm'])->name('units.water-reading');
    Route::put('/units/{unit}/water-reading', [UnitController::class, 'updateWaterReading'])->name('units.water-reading.update');
    Route::get('/units/{unit}/meter-reading', [UnitController::class, 'showMeterReadingForm'])->name('units.meter-reading');
    Route::put('/units/{unit}/meter-reading', [UnitController::class, 'updateMeterReading'])->name('units.meter-reading.update');
    Route::get('/units/{unit}/meter-reading-data', [UnitController::class, 'getMeterReadingData'])->name('units.meter-reading-data');
    
    // Meter reader routes
    Route::get('/meter-readings', [UnitController::class, 'meterReadingsIndex'])->name('meter-readings.index');
    Route::get('/meter-readings/reports', [UnitController::class, 'meterReadingReports'])->name('meter-readings.reports');

    
    
    // API routes
    Route::get('/api/units/with-water-readings', [WaterReadingController::class, 'getUnitsWithWaterReadings'])->name('api.units.with-water-readings');
});