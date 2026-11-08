<?php

namespace App\Console\Commands;

use App\Services\Sharet\SharetCollections;
use Illuminate\Console\Command;

/** Poll Sharet for open pay links; a safety net for missed webhooks and for connected-mode requests. */
class SharetSync extends Command
{
    protected $signature = 'sharet:sync {--limit=200}';

    protected $description = 'Check open Sharet pay links and apply any that were paid, expired or cancelled';

    public function handle(SharetCollections $collections): int
    {
        if (! SharetCollections::available()) {
            $this->info('Sharet is not configured; nothing to do.');

            return self::SUCCESS;
        }

        $this->info('Updated: '.$collections->syncOpen((int) $this->option('limit')));

        return self::SUCCESS;
    }
}
