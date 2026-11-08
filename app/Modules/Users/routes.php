<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;

Route::middleware(['auth', 'role:sysadmin|admin|accountant'])->prefix('users')->name('users.')->group(function () {
    // CRUD - one liner for standard resource
    Route::resource('/', UserController::class)->parameters(['' => 'user']);
});