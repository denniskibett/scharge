<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Users\Controllers\UserController;

Route::middleware(['auth'])->prefix('users')->name('users.')->group(function () {
    // CRUD - one liner for standard resource
    Route::resource('/', UserController::class)->parameters(['' => 'user']);
});