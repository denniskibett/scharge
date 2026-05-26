<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SystemController;

Route::middleware(['auth', 'role:super_admin,admin'])->prefix('system')->name('system.')->group(function () {
    Route::get('/', [SystemController::class, 'index'])->name('index');
    Route::put('/update', [SystemController::class, 'update'])->name('update');
    Route::get('/clear-cache', [SystemController::class, 'clearCache'])->name('clear-cache');
    Route::get('/backup', [SystemController::class, 'backupDatabase'])->name('backup');
    Route::post('/toggle-maintenance', [SystemController::class, 'toggleMaintenance'])->name('toggle-maintenance');
    Route::post('/debug', [SystemController::class, 'debug'])->name('debug');
});