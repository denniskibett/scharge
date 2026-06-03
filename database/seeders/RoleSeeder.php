<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Define all roles
        $roles = [
            // Super Admin - Full system access
            [
                'name' => 'sys_admin',
                'description' => 'System Administrator - Manages companies, subscriptions, and system-wide settings.'
            ],

            [
                'name' => 'super_admin',
                'description' => 'Full system access with all permissions. Can manage users, roles, and all system settings.'
            ],
            
            // Admin - Administrative access
            [
                'name' => 'admin',
                'description' => 'Administrative access to most features except role management and system settings.'
            ],
            
            // Property Manager - Manage properties and units
            [
                'name' => 'property_manager',
                'description' => 'Can manage estates, units, tenancies, and view tenant information.'
            ],
            
            // Accountant - Handle finances
            [
                'name' => 'accountant',
                'description' => 'Can manage invoices, payments, expenses, and financial reports.'
            ],
            
            // Meter Reader - Specifically for reading water/electricity meters
            [
                'name' => 'meter_reader',
                'description' => 'Can view and update water meter readings for units.'
            ],
            
            // Cleaning Staff - View cleaning schedules and report completion
            [
                'name' => 'cleaning_staff',
                'description' => 'Can view assigned cleaning tasks, mark as completed, and report issues.'
            ],
            
            // Maintenance Staff - Handle maintenance requests
            [
                'name' => 'maintenance',
                'description' => 'Can view and respond to maintenance requests.'
            ],
            
            // Security Staff - Monitor security and access logs
            [
                'name' => 'security',
                'description' => 'Can view security logs, access records, and report incidents.'
            ],
            
            // Tenant - Resident access
            [
                'name' => 'tenant',
                'description' => 'Can view own invoices, make payments, and submit maintenance requests.'
            ],
            
            // Guest - Read-only access
            [
                'name' => 'guest',
                'description' => 'Read-only access to basic information.'
            ],
        ];

        // Create roles
        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }

        $this->command->info('✓ Roles seeded successfully!');
        $this->command->newLine();
        $this->command->info('Available Roles:');
        
        $roles = Role::all();
        foreach ($roles as $role) {
            $this->command->info("   - {$role->name}: {$role->description}");
        }
    }
}