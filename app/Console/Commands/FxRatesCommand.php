<?php

namespace App\Console\Commands;

use App\Support\Currency;
use App\Support\FxRates;
use Illuminate\Console\Command;

/** Fetch Sharet's FX reference rates now (also run hourly by the scheduler) and show what scharge will use. */
class FxRatesCommand extends Command
{
    protected $signature = 'fx:rates';

    protected $description = "Refresh Sharet's FX rates used to show amounts in other currencies";

    public function handle(): int
    {
        $snap = FxRates::refresh();
        if ($snap === null) {
            $this->warn('Sharet gave no rates and none are stored yet: amounts stay in '.Currency::book().'. URL: '.config('currency.rates.url'));

            return self::FAILURE;
        }
        $this->line(($snap['stale'] ? 'Sharet unreachable, using the last known rates' : 'Rates from Sharet').' of '.$snap['date'].' (1 '.$snap['base'].' =):');
        foreach ($snap['rates'] as $code => $rate) {
            $this->line("  $code $rate");
        }
        $this->line('Platform display currency: '.Currency::platform());

        return $snap['stale'] ? self::FAILURE : self::SUCCESS;
    }
}
