<?php

use App\Models\Company;
use App\Modules\CRM\Controllers\Admin\CrmCompanyController;
use App\Modules\CRM\Controllers\Admin\CrmFollowUpController;
use App\Modules\CRM\Controllers\Admin\CrmWaitlistController;
use App\Modules\CRM\Controllers\Site\SiteFeedbackController;
use App\Modules\CRM\Controllers\Site\SiteFormController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/web.php (inside the "web" middleware group).

// CRM pages work with prospects too, which the Company model hides by default.
Route::bind('crmCompany', fn ($id) => Company::withoutGlobalScope('customers')->findOrFail($id));

// ---------------------------------------------------------------------------
// Public website forms
// ---------------------------------------------------------------------------
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/waitlist', [SiteFormController::class, 'waitlist'])->name('site.waitlist.store');
    Route::post('/book-a-demo', [SiteFormController::class, 'demo'])->name('site.demo.store');
    Route::post('/sandbox-keys', [SiteFormController::class, 'apiKeys'])->name('site.api-keys.store');
    Route::post('/listings/request', [SiteFormController::class, 'listingRequest'])->name('site.listings.request');
    Route::post('/listings/alert', [SiteFormController::class, 'listingAlert'])->name('site.listings.alert');
});
Route::get('/feedback/{token}', [SiteFeedbackController::class, 'show'])->name('site.feedback.show');
Route::post('/feedback/{token}', [SiteFeedbackController::class, 'store'])->middleware('throttle:10,1')->name('site.feedback.store');

// ---------------------------------------------------------------------------
// CRM (scharge staff only)
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:sysadmin'])->prefix('crm')->name('crm.')->group(function () {
    Route::get('/', [CrmCompanyController::class, 'index'])->name('index');
    Route::post('/companies', [CrmCompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{crmCompany}', [CrmCompanyController::class, 'show'])->name('companies.show');
    Route::put('/companies/{crmCompany}', [CrmCompanyController::class, 'update'])->name('companies.update');
    Route::post('/companies/{crmCompany}/contacts', [CrmCompanyController::class, 'addContact'])->name('companies.contacts.store');
    Route::post('/companies/{crmCompany}/follow-ups', [CrmFollowUpController::class, 'store'])->name('companies.follow-ups.store');
    Route::post('/companies/{crmCompany}/feedback', [CrmFollowUpController::class, 'requestFeedback'])->name('companies.feedback.store');

    Route::get('/follow-ups', [CrmFollowUpController::class, 'index'])->name('follow-ups.index');
    Route::patch('/follow-ups/{followUp}', [CrmFollowUpController::class, 'update'])->name('follow-ups.update');
    Route::get('/requests', [CrmFollowUpController::class, 'requests'])->name('requests.index');
    Route::redirect('/demos', '/crm/requests');
    Route::get('/feedback', [CrmFollowUpController::class, 'feedback'])->name('feedback.index');

    Route::get('/waitlist', [CrmWaitlistController::class, 'index'])->name('waitlist.index');
    Route::get('/waitlist/export', [CrmWaitlistController::class, 'export'])->name('waitlist.export');
    Route::patch('/waitlist/{entry}', [CrmWaitlistController::class, 'update'])->name('waitlist.update');
    Route::post('/waitlist/launch', [CrmWaitlistController::class, 'launch'])->name('waitlist.launch');
});
