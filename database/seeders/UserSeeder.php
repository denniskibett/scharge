<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = '00000000';
        
        // Define users with their role names
        $users = [
            // Super Admin
            [
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@sharet.africa',
                'phone' => '+254700000001',
                'role_name' => 'super_admin',
            ],
            
            // Admin
            [
                'name' => 'Administrator',
                'first_name' => 'John',
                'last_name' => 'Administrator',
                'email' => 'administrator@sharet.africa',
                'phone' => '+254700000002',
                'role_name' => 'admin',
            ],
            
            // Property Manager
            [
                'name' => 'Property Manager',
                'first_name' => 'Jane',
                'last_name' => 'Property',
                'email' => 'property.manager@sharet.africa',
                'phone' => '+254700000003',
                'role_name' => 'property_manager',
            ],
            
            // Accountant
            [
                'name' => 'Accountant',
                'first_name' => 'Alice',
                'last_name' => 'Accountant',
                'email' => 'accountant@sharet.africa',
                'phone' => '+254700000004',
                'role_name' => 'accountant',
            ],
            
            // Meter Reader
            [
                'name' => 'Meter Reader',
                'first_name' => 'Peter',
                'last_name' => 'Meter',
                'email' => 'meter.reader@sharet.africa',
                'phone' => '+254700000005',
                'role_name' => 'meter_reader',
            ],
            
            // Cleaning Staff
            [
                'name' => 'Cleaning Staff',
                'first_name' => 'Mary',
                'last_name' => 'Cleaner',
                'email' => 'cleaning@sharet.africa',
                'phone' => '+254700000006',
                'role_name' => 'cleaning_staff',
            ],
            
            // Maintenance Staff
            [
                'name' => 'Maintenance Staff',
                'first_name' => 'James',
                'last_name' => 'Repair',
                'email' => 'maintenance@sharet.africa',
                'phone' => '+254700000007',
                'role_name' => 'maintenance',
            ],
            
            // Security Staff
            [
                'name' => 'Security Staff',
                'first_name' => 'David',
                'last_name' => 'Guard',
                'email' => 'security@sharet.africa',
                'phone' => '+254700000008',
                'role_name' => 'security',
            ],
            
            // Tenant 1
            [
                'name' => 'John Tenant',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.tenant@sharet.africa',
                'phone' => '+254700000009',
                'role_name' => 'tenant',
            ],
            
            // Tenant 2
            [
                'name' => 'Jane Tenant',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.tenant@sharet.africa',
                'phone' => '+254700000010',
                'role_name' => 'tenant',
            ],
            
            // Tenant 3
            [
                'name' => 'Michael Tenant',
                'first_name' => 'Michael',
                'last_name' => 'Johnson',
                'email' => 'michael.tenant@sharet.africa',
                'phone' => '+254700000011',
                'role_name' => 'tenant',
            ],
            
            // Guest
            [
                'name' => 'Guest User',
                'first_name' => 'Guest',
                'last_name' => 'User',
                'email' => 'guest@sharet.africa',
                'phone' => '+254700000012',
                'role_name' => 'guest',
            ],
        ];

        // Additional sample tenants
        $sampleTenants = [
            ['Sarah Wilson', 'sarah.wilson@sharet.africa', '+254700000013'],
            ['Robert Brown', 'robert.brown@sharet.africa', '+254700000014'],
            ['Emily Davis', 'emily.davis@sharet.africa', '+254700000015'],
            ['Daniel Miller', 'daniel.miller@sharet.africa', '+254700000016'],
            ['Lisa Garcia', 'lisa.garcia@sharet.africa', '+254700000017'],
            ['Paul Rodriguez', 'paul.rodriguez@sharet.africa', '+254700000018'],
            ['Karen Martinez', 'karen.martinez@sharet.africa', '+254700000019'],
            ['Kevin Anderson', 'kevin.anderson@sharet.africa', '+254700000020'],
            ['Nancy Taylor', 'nancy.taylor@sharet.africa', '+254700000021'],
            ['Brian Thomas', 'brian.thomas@sharet.africa', '+254700000022'],
        ];

        foreach ($sampleTenants as $tenant) {
            $nameParts = explode(' ', $tenant[0]);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? 'Tenant';
            
            $users[] = [
                'name' => $tenant[0],
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $tenant[1],
                'phone' => $tenant[2],
                'role_name' => 'tenant',
            ];
        }

        // Create users
        foreach ($users as $userData) {
            $roleName = $userData['role_name'];
            unset($userData['role_name']);
            
            // Find the role ID
            $role = Role::where('name', $roleName)->first();
            
            if (!$role) {
                $this->command->error("Role '{$roleName}' not found! Run RoleSeeder first.");
                continue;
            }
            
            // Check if user already exists
            $user = User::where('email', $userData['email'])->first();
            
            if (!$user) {
                User::create([
                    'name' => $userData['name'],
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'email' => $userData['email'],
                    'password' => Hash::make($defaultPassword),
                    'phone' => $userData['phone'],
                    'role_id' => $role->id,
                    'email_verified_at' => now(),
                ]);
                $this->command->info("✓ Created user: {$userData['email']} ({$roleName})");
            } else {
                // Update existing user's role if needed
                if ($user->role_id !== $role->id) {
                    $user->update(['role_id' => $role->id]);
                    $this->command->info("✓ Updated role for existing user: {$userData['email']} ({$roleName})");
                }
            }
        }
        
        $this->command->newLine();
        $this->command->info('🎉 User seeding completed!');
        $this->command->newLine();
        $this->command->info('📋 Login Credentials:');
        $this->command->info('   Password: 00000000');
        $this->command->newLine();
        $this->command->info('📧 Test Accounts:');
        
        $roles = Role::all();
        foreach ($roles as $role) {
            $count = User::where('role_id', $role->id)->count();
            $this->command->info("   - {$role->name}: {$count} user(s)");
        }
        
        $this->command->newLine();
        $this->command->info('🔑 Quick Login Emails:');
        $this->command->info('   Super Admin: admin@sharet.africa');
        $this->command->info('   Admin: administrator@sharet.africa');
        $this->command->info('   Property Manager: property.manager@sharet.africa');
        $this->command->info('   Accountant: accountant@sharet.africa');
        $this->command->info('   Meter Reader: meter.reader@sharet.africa');
        $this->command->info('   Cleaning Staff: cleaning@sharet.africa');
        $this->command->info('   Maintenance: maintenance@sharet.africa');
        $this->command->info('   Security: security@sharet.africa');
        $this->command->info('   Tenant: john.tenant@sharet.africa');
        $this->command->info('   Guest: guest@sharet.africa');
    }
}