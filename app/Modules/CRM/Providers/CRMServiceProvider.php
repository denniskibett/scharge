<?php

namespace App\Modules\CRM\Providers;

use App\Modules\CRM\Console\FindEmails;
use App\Modules\CRM\Console\GeocodeProspects;
use App\Modules\CRM\Console\ImportProspects;
use App\Modules\CRM\Console\LinkSites;
use App\Modules\CRM\Console\TagAreas;
use App\Modules\CRM\Console\ApiSandboxKey;
use App\Modules\CRM\Console\MailDelivery;
use App\Modules\CRM\Console\MailReplies;
use App\Modules\CRM\Console\DailyRecap;
use App\Modules\CRM\Console\CleanContacts;
use App\Modules\CRM\Console\PullNairobiLeads;
use App\Modules\CRM\Console\SendCampaign;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the CRM console commands (crm:import, crm:geocode, crm:campaign). Routes are loaded from routes/web.php (like the other
 * modules) and the website's country lists come from HomeController.
 */
class CRMServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportProspects::class, GeocodeProspects::class, SendCampaign::class, MailDelivery::class, MailReplies::class, DailyRecap::class, CleanContacts::class, PullNairobiLeads::class, FindEmails::class, LinkSites::class, TagAreas::class, ApiSandboxKey::class, \App\Modules\CRM\Console\OnboardingStalls::class, \App\Modules\CRM\Console\LateBounces::class, \App\Modules\CRM\Console\ResendReturning::class, \App\Modules\CRM\Console\BackfillOutOfOffice::class, \App\Modules\CRM\Console\MergeDemoDuplicates::class, \App\Modules\CRM\Console\ReadReplies::class, \App\Modules\CRM\Console\WinBackDrafts::class]);
        }
    }
}
