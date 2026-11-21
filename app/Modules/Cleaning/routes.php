<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CleaningController;

Route::middleware(['auth'])->prefix('cleaning')->name('cleaning.')->group(function () {
});