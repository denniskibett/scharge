<?php

namespace App\Console\Commands;

use App\Support\FirstBill;
use Illuminate\Console\Command;

/** New accounts' first scharge bill (App\Support\FirstBill): issue the ones now due, open months for paid ones. */
class FirstBills extends Command
{
    protected $signature = 'billing:first-bills';

    protected $description = 'Issue first bills (one month, gives three) for new companies with units and a staff-set price';

    public function handle(): int
    {
        if (! FirstBill::installed()) {
            $this->warn('Run database/schema/2026_11_23_first_bill.sql first.');

            return self::SUCCESS;
        }
        $r = FirstBill::run();
        $this->info("First bills issued: {$r['issued']}; paid first bills opened: {$r['opened']}.");

        return self::SUCCESS;
    }
}
