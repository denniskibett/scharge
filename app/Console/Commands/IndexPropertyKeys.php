<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Properties\PropertyRegistry;
use Illuminate\Console\Command;

/** Fill properties.name_key / location_key (used to spot the same building registered twice). */
class IndexPropertyKeys extends Command
{
    protected $signature = 'properties:index-keys';

    protected $description = 'Compute duplicate-matching keys for every property';

    public function handle(): int
    {
        $n = 0;
        Property::query()->each(function (Property $p) use (&$n) {
            PropertyRegistry::index($p);
            $n++;
        });
        $this->info("Indexed {$n} properties.");

        return self::SUCCESS;
    }
}
