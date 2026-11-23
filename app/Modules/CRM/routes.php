<?php

use App\Modules\CRM\Controllers\Admin\CrmCompanyController;
use App\Modules\CRM\Controllers\Admin\CrmFollowUpController;
use App\Modules\CRM\Controllers\Admin\CrmInboxController;
use App\Modules\CRM\Controllers\Admin\CrmOutreachController;
use App\Modules\CRM\Controllers\Admin\CrmMapController;
use App\Modules\CRM\Controllers\Admin\CrmCampaignController;
use App\Modules\CRM\Controllers\Admin\CrmPropertyController;
use App\Modules\CRM\Controllers\Admin\CrmReplyController;
use App\Modules\CRM\Controllers\Admin\CrmTeamController;
use App\Modules\CRM\Controllers\Admin\CrmFunnelController;
use App\Modules\CRM\Controllers\Admin\CrmSalesController;
use App\Modules\CRM\Controllers\Admin\CrmWaitlistController;
use App\Modules\CRM\Controllers\Site\DemoRequestController;
use App\Modules\CRM\Controllers\Site\SiteFeedbackController;
use App\Modules\CRM\Controllers\Site\SiteFormController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/web.php (inside the "web" middleware group).

// {crmCompany} (prospects included) is bound in AppServiceProvider: a binding made here is lost once routes are cached.

// ---------------------------------------------------------------------------
// Public website forms
// ---------------------------------------------------------------------------
Route::middleware('throttle:6,1,site-forms')->group(function () {
    Route::post('/waitlist', [SiteFormController::class, 'waitlist'])->name('site.waitlist.store');
    Route::post('/book-a-demo', [SiteFormController::class, 'demo'])->name('site.demo.store');
    Route::post('/sandbox-keys', [SiteFormController::class, 'apiKeys'])->name('site.api-keys.store');
    Route::post('/listings/request', [SiteFormController::class, 'listingRequest'])->name('site.listings.request');
    Route::post('/listings/alert', [SiteFormController::class, 'listingAlert'])->name('site.listings.alert');
});
// Private demo form linked from each cold email: /demo/SC-XXXXXX
Route::get('/demo/{ref}', [DemoRequestController::class, 'show'])->where('ref', \App\Modules\CRM\Support\Playbook::REF_PATTERN)->name('site.demo-request');
Route::post('/demo/{ref}', [DemoRequestController::class, 'store'])->where('ref', \App\Modules\CRM\Support\Playbook::REF_PATTERN)->middleware('throttle:6,1,demo-form')->name('site.demo-request.store');
Route::post('/demo/{ref}/draft', [DemoRequestController::class, 'draft'])->where('ref', \App\Modules\CRM\Support\Playbook::REF_PATTERN)->middleware('throttle:40,1,demo-draft')->name('site.demo-request.draft');
Route::get('/demo/{ref}/thanks', [DemoRequestController::class, 'thanks'])->where('ref', \App\Modules\CRM\Support\Playbook::REF_PATTERN)->name('site.demo-request.thanks');
Route::get('/e/{token}.gif', [DemoRequestController::class, 'opened'])->where('token', '[A-Za-z0-9]{24}')->middleware('throttle:60,1,email-open')->name('site.email-open');
Route::get('/feedback/{token}', [SiteFeedbackController::class, 'show'])->name('site.feedback.show');
Route::post('/feedback/{token}', [SiteFeedbackController::class, 'store'])->middleware('throttle:10,1,feedback')->name('site.feedback.store');

// ---------------------------------------------------------------------------
// CRM (scharge staff only)
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:sysadmin|account_manager|sales', 'access:crm'])->prefix('crm')->name('crm.')->group(function () {
    Route::get('/', [CrmCompanyController::class, 'index'])->name('index');
    Route::get('/map', [CrmMapController::class, 'index'])->name('map.index');
    Route::get('/map/data.json', [CrmMapController::class, 'data'])->name('map.data');
    Route::get('/map/sites-summary.json', [CrmMapController::class, 'sitesSummary'])->name('map.sites-summary');
    Route::get('/map/sites.json', [CrmMapController::class, 'sites'])->middleware('throttle:120,1')->name('map.sites');
    Route::get('/sales', [CrmSalesController::class, 'index'])->name('sales.index');
    Route::get('/properties', [CrmPropertyController::class, 'index'])->name('properties.index');
    Route::get('/campaigns', [CrmCampaignController::class, 'index'])->name('campaigns.index');
    Route::patch('/companies/{crmCompany}/properties/{property}', [CrmPropertyController::class, 'update'])->where('property', '[a-z0-9]{8}')->name('properties.update');
    Route::get('/sales/commission.csv', [CrmSalesController::class, 'export'])->name('sales.export');
    Route::put('/sales/commission', [CrmSalesController::class, 'updateCommission'])->name('sales.commission.update');
    Route::post('/companies', [CrmCompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{crmCompany}', [CrmCompanyController::class, 'show'])->name('companies.show');
    Route::put('/companies/{crmCompany}', [CrmCompanyController::class, 'update'])->name('companies.update');
    Route::get('/companies/{crmCompany}/playbook', [CrmOutreachController::class, 'show'])->name('companies.playbook');
    Route::get('/companies/{crmCompany}/playbook/preview', [CrmOutreachController::class, 'preview'])->name('companies.playbook.preview');
    Route::post('/companies/{crmCompany}/playbook/compose', [CrmOutreachController::class, 'compose'])->name('companies.playbook.compose');
    Route::put('/companies/{crmCompany}/playbook/draft', [CrmOutreachController::class, 'saveDraft'])->name('companies.playbook.draft');
    Route::post('/companies/{crmCompany}/playbook/send', [CrmOutreachController::class, 'send'])->name('companies.playbook.send');
    Route::post('/companies/{crmCompany}/reply', [CrmReplyController::class, 'answer'])->middleware('throttle:20,1')->name('companies.reply.answer');
    Route::post('/companies/{crmCompany}/reply/handled', [CrmReplyController::class, 'handled'])->name('companies.reply.handled');
    Route::get('/team', [CrmTeamController::class, 'index'])->name('team.index');
    Route::get('/funnel', [CrmFunnelController::class, 'index'])->name('funnel.index');
    Route::post('/companies/{crmCompany}/contacts', [CrmCompanyController::class, 'addContact'])->name('companies.contacts.store');
    Route::post('/companies/{crmCompany}/follow-ups', [CrmFollowUpController::class, 'store'])->name('companies.follow-ups.store');
    Route::post('/companies/{crmCompany}/feedback', [CrmFollowUpController::class, 'requestFeedback'])->name('companies.feedback.store');

    Route::get('/follow-ups', [CrmFollowUpController::class, 'index'])->name('follow-ups.index');
    Route::get('/follow-ups/{followUp}/pdf', [DemoRequestController::class, 'pdf'])->name('follow-ups.pdf');
    Route::get('/follow-ups/{followUp}/file', [DemoRequestController::class, 'file'])->name('follow-ups.file');
    Route::patch('/follow-ups/{followUp}', [CrmFollowUpController::class, 'update'])->name('follow-ups.update');
    Route::get('/requests', [CrmFollowUpController::class, 'requests'])->name('requests.index');
    Route::redirect('/demos', '/crm/requests');
    Route::get('/feedback', [CrmFollowUpController::class, 'feedback'])->name('feedback.index');

    // Inbox: the sharet.africa work mailboxes (own + shared), read and answered here.
    Route::get('/inbox', [CrmInboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/counts', [CrmInboxController::class, 'counts'])->name('inbox.counts');
    Route::post('/inbox/mailboxes', [CrmInboxController::class, 'store'])->name('inbox.mailboxes.store');
    Route::put('/inbox/{mailbox}', [CrmInboxController::class, 'update'])->name('inbox.mailboxes.update');
    Route::post('/inbox/{mailbox}/connect', [CrmInboxController::class, 'connect'])->middleware('throttle:10,1')->name('inbox.connect');
    Route::delete('/inbox/{mailbox}/connect', [CrmInboxController::class, 'disconnect'])->name('inbox.disconnect');
    Route::post('/inbox/{mailbox}/send', [CrmInboxController::class, 'send'])->middleware('throttle:30,1')->name('inbox.send');
    Route::post('/inbox/{mailbox}/messages/{uid}/trash', [CrmInboxController::class, 'trash'])->whereNumber('uid')->name('inbox.trash');
    Route::post('/inbox/{mailbox}/messages/{uid}/unread', [CrmInboxController::class, 'unread'])->whereNumber('uid')->name('inbox.unread');
    Route::get('/inbox/{mailbox}/messages/{uid}/attachment', [CrmInboxController::class, 'attachment'])->whereNumber('uid')->name('inbox.attachment');

    Route::get('/waitlist', [CrmWaitlistController::class, 'index'])->name('waitlist.index');
    Route::get('/waitlist/export', [CrmWaitlistController::class, 'export'])->name('waitlist.export');
    Route::patch('/waitlist/{entry}', [CrmWaitlistController::class, 'update'])->name('waitlist.update');
    Route::post('/waitlist/launch', [CrmWaitlistController::class, 'launch'])->name('waitlist.launch');
});
