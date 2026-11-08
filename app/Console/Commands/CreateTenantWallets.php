<?php

namespace App\Console\Commands;

use App\Modules\Tenants\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Makes sure every tenant has a bavix "default" wallet.
 *
 * Goes through bavix itself ($tenant->wallet) so holder_type always matches the
 * morph map in AppServiceProvider. The old version wrote rows by hand with the
 * module class name (creating duplicate wallets) and had a --force flag that
 * deleted wallets with their balances; both are gone.
 */
class CreateTenantWallets extends Command
{
    protected $signature = 'tenants:create-wallets {--dry-run}';

    protected $description = 'Create the default wallet for any tenant that does not have one';

    public function handle(): int
    {
        $created = 0;

        Tenant::query()->whereDoesntHave('wallet')->chunkById(200, function ($tenants) use (&$created) {
            foreach ($tenants as $tenant) {
                if (! $this->option('dry-run')) {
                    $tenant->wallet->save();
                }
                $created++;
            }
        });

        $this->info(($this->option('dry-run') ? 'Would create' : 'Created')." {$created} wallet(s).");

        return self::SUCCESS;
    }
}
