<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [
            // Dashboard
            'view dashboard',

            // Users
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Roles & Permissions
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'assign roles',
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',

            // Properties / Estates
            'view properties',
            'create properties',
            'edit properties',
            'delete properties',

            // Units
            'view units',
            'create units',
            'edit units',
            'delete units',

            // Tenants
            'view tenants',
            'create tenants',
            'edit tenants',
            'delete tenants',

            // Service Charges
            'view service charges',
            'create service charges',
            'edit service charges',
            'delete service charges',
            'collect service charges',

            // Rent
            'view rent',
            'create rent',
            'edit rent',
            'delete rent',
            'collect rent',

            // Invoices
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'send invoices',

            // Payments
            'view payments',
            'record payments',
            'edit payments',
            'delete payments',
            'refund payments',

            // Expenses
            'view expenses',
            'create expenses',
            'edit expenses',
            'delete expenses',
            'approve expenses',

            // Reports
            'view reports',
            'generate reports',
            'export reports',

            // Meter Readings
            'view meter readings',
            'create meter readings',
            'edit meter readings',
            'delete meter readings',

            // Settings
            'view settings',
            'edit settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $roles = [
            'admin',
            'property_manager',
            'accountant',
            'tenant',
            'meter_reader',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        */

        Role::findByName('admin', 'web')
            ->syncPermissions(Permission::where('guard_name', 'web')->get());

        /*
        |--------------------------------------------------------------------------
        | Property Manager
        |--------------------------------------------------------------------------
        */

        Role::findByName('property_manager', 'web')
            ->syncPermissions([
                'view dashboard',

                'view properties',
                'create properties',
                'edit properties',

                'view units',
                'create units',
                'edit units',

                'view tenants',
                'create tenants',
                'edit tenants',

                'view service charges',
                'create service charges',
                'edit service charges',
                'collect service charges',

                'view rent',
                'create rent',
                'edit rent',
                'collect rent',

                'view invoices',
                'create invoices',
                'edit invoices',
                'send invoices',

                'view payments',
                'record payments',

                'view expenses',
                'create expenses',
                'edit expenses',

                'view reports',
                'generate reports',
                'export reports',

                'view meter readings',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Accountant
        |--------------------------------------------------------------------------
        */

        Role::findByName('accountant', 'web')
            ->syncPermissions([
                'view dashboard',

                'view invoices',
                'create invoices',
                'edit invoices',
                'delete invoices',
                'send invoices',

                'view payments',
                'record payments',
                'edit payments',
                'delete payments',
                'refund payments',

                'view service charges',
                'collect service charges',

                'view rent',
                'collect rent',

                'view expenses',
                'create expenses',
                'edit expenses',
                'approve expenses',

                'view reports',
                'generate reports',
                'export reports',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Tenant
        |--------------------------------------------------------------------------
        */

        Role::findByName('tenant', 'web')
            ->syncPermissions([
                'view dashboard',
                'view invoices',
                'view payments',
                'view service charges',
                'view rent',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Meter Reader
        |--------------------------------------------------------------------------
        */

        Role::findByName('meter_reader', 'web')
            ->syncPermissions([
                'view dashboard',
                'view meter readings',
                'create meter readings',
                'edit meter readings',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Clear cache again
        |--------------------------------------------------------------------------
        */

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}