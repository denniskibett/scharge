<?php

namespace App\Console\Commands;

use App\Services\Listings\ListingMatcher;
use Illuminate\Console\Command;

/** SMS renters new listings that match their saved search (Open listings › SMS me new matches). Hourly, daytime. */
class MatchListings extends Command
{
    protected $signature = 'listings:match {--dry-run : Count who would get an SMS}';

    protected $description = 'Send listing alerts: new vacant units that match renters\' searches';

    public function handle(ListingMatcher $matcher): int
    {
        $r = $matcher->run((bool) $this->option('dry-run'));
        $this->line("{$r['alerts']} open alerts, {$r['sent']} SMS".($this->option('dry-run') ? ' would be sent' : ' sent').", {$r['closed']} expired.");

        return self::SUCCESS;
    }
}
