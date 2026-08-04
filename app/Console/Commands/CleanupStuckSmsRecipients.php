<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CampaignRecipient;
use Carbon\Carbon;

class CleanupStuckSmsRecipients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:cleanup-stuck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move stuck pending recipients to failed after a timeout';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Consider any recipient with attempted_at older than 10 minutes as stuck
        $timeout = Carbon::now()->subMinutes(10);

        $stuck = CampaignRecipient::where('status', 'pending')
            ->whereNotNull('attempted_at')
            ->where('attempted_at', '<', $timeout)
            ->get();

        $count = 0;
        foreach ($stuck as $recipient) {
            $recipient->status = 'failed';
            $recipient->error_message = 'Send attempt timed out (cleanup job)';
            $recipient->save();
            $count++;
        }

        if ($count > 0) {
            $this->info("✅ Moved {$count} stuck recipients to failed.");
        } else {
            $this->info("ℹ️ No stuck recipients found.");
        }

        return Command::SUCCESS;
    }
}