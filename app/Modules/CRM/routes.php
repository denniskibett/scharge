<?php

use App\Modules\CRM\Controllers\Admin\CrmCompanyController;
use App\Modules\CRM\Controllers\Admin\CrmDemoController;
use App\Modules\CRM\Controllers\Admin\CrmFeedbackController;
use App\Modules\CRM\Controllers\Admin\CrmFollowUpController;
use App\Modules\CRM\Controllers\Admin\CrmWaitlistController;
use App\Modules\CRM\Controllers\Site\SiteFeedbackController;
use App\Modules\CRM\Controllers\Site\SiteFormController;
use Illuminate\Support\Facades\Route;

// Loaded by CRMServiceProvider inside the "web" middleware group.

// ---------------------------------------------------------------------------
// Public website forms
// ---------------------------------------------------------------------------
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/waitlist', [SiteFormController::class, 'waitlist'])->name('site.waitlist.store');
    Route::post('/book-a-demo', [SiteFormController::class, 'demo'])->name('site.demo.store');
});
Route::get('/feedback/{token}', [SiteFeedbackController::class, 'show'])->name('site.feedback.show');
Route::post('/feedback/{token}', [SiteFeedbackController::class, 'store'])->middleware('throttle:10,1')->name('site.feedback.store');

// ---------------------------------------------------------------------------
// CRM (scharge staff only)
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:sysadmin'])->prefix('crm')->name('crm.')->group(function () {
    Route::get('/', [CrmCompanyController::class, 'index'])->name('index');
    Route::post('/companies', [CrmCompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{company}', [CrmCompanyController::class, 'show'])->name('companies.show');
    Route::put('/companies/{company}', [CrmCompanyController::class, 'update'])->name('companies.update');
    Route::post('/companies/{company}/activities', [CrmCompanyController::class, 'logActivity'])->name('companies.activities.store');
    Route::post('/companies/{company}/contacts', [CrmCompanyController::class, 'addContact'])->name('companies.contacts.store');
    Route::post('/companies/{company}/follow-ups', [CrmFollowUpController::class, 'store'])->name('companies.follow-ups.store');
    Route::post('/companies/{company}/feedback', [CrmFeedbackController::class, 'store'])->name('companies.feedback.store');

    Route::get('/follow-ups', [CrmFollowUpController::class, 'index'])->name('follow-ups.index');
    Route::patch('/follow-ups/{followUp}/complete', [CrmFollowUpController::class, 'complete'])->name('follow-ups.complete');

    Route::get('/demos', [CrmDemoController::class, 'index'])->name('demos.index');
    Route::patch('/demos/{demo}', [CrmDemoController::class, 'update'])->name('demos.update');

    Route::get('/waitlist', [CrmWaitlistController::class, 'index'])->name('waitlist.index');
    Route::get('/waitlist/export', [CrmWaitlistController::class, 'export'])->name('waitlist.export');
    Route::patch('/waitlist/{entry}', [CrmWaitlistController::class, 'update'])->name('waitlist.update');

    Route::get('/feedback', [CrmFeedbackController::class, 'index'])->name('feedback.index');
});
