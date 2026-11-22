<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;

// Every user on scharge, any role, any company: sysadmins only (company admins manage their own people on /team and /staff).
Route::middleware(['auth', 'role:sysadmin'])->prefix('users')->name('users.')->group(function () {
    // CRUD - one liner for standard resource
    Route::resource('/', UserController::class)->parameters(['' => 'user']);
});