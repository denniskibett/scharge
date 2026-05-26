<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CleaningController;

Route::middleware(['auth'])->prefix('cleaning')->name('cleaning.')->group(function () {
    Route::get('/tasks', [CleaningController::class, 'index'])->name('tasks');
    Route::put('/tasks/{task}/complete', [CleaningController::class, 'markComplete'])->name('tasks.complete');
    Route::get('/schedule', [CleaningController::class, 'schedule'])->name('schedule');
});