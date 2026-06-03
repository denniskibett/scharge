<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class CreateWalletsForExistingTenants extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::all() as $tenant) {
            if (!$tenant->wallet) {
                Wallet::create([
                    'tenant_id' => $tenant->id,
                    'balance' => 0.00,
                ]);
            }
        }
    }
}