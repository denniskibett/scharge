<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Tenancy;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UnitTenantMigrationSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Starting Unit Tenant Migration Seeder...');
        
        $units = Unit::with(['estate', 'tenancies'])->get();
        
        foreach ($units as $unit) {
            $activeTenancy = $unit->tenancies->where('status', 'active')->first();
            
            if (!$activeTenancy) {
                $this->command->warn("Unit {$unit->unit_number} has no active tenancy");
                continue;
            }
            
            // Check if tenant already exists
            if ($activeTenancy->tenant_id) {
                $this->command->info("Unit {$unit->unit_number} already has tenant");
                continue;
            }
            
            // Create user and tenant
            DB::transaction(function() use ($unit, $activeTenancy) {
                $user = User::create([
                    'name' => "Tenant of {$unit->unit_number}",
                    'email' => "tenant.{$unit->id}@example.com",
                    'password' => Hash::make(Str::random(16)),
                    'role_id' => Role::where('name', 'tenant')->first()->id ?? 1,
                ]);
                
                $tenant = Tenant::create([
                    'user_id' => $user->id,
                    'notes' => "Auto-created from unit {$unit->unit_number}",
                ]);
                
                $activeTenancy->update(['tenant_id' => $tenant->id]);
                
                $this->command->info("✓ Migrated unit {$unit->unit_number}");
            });
        }
        
        $this->command->info('Migration completed!');
    }
}