<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * The live `roles` table is Spatie's standard one (no `description` column), so role
 * descriptions shown in the UI come from here. If a description column is ever added,
 * a saved description wins.
 */
class RoleDescriptions
{
    public const DESCRIPTIONS = [
        'sysadmin' => 'scharge staff: full access to every company and system setting.',
        'account_manager' => 'scharge staff: manages a book of companies, their leads and revenue.',
        'admin' => 'Company admin (landlord side): runs the company\'s properties, staff and money.',
        'property_manager' => 'Runs properties: units, tenants, tenancies, repairs and notices.',
        'accountant' => 'Finance: invoices, payments, payees and expenses.',
        'meter_reader' => 'Reads water and electricity meters on assigned properties.',
        'cleaning_staff' => 'Cleaning and turnover tasks.',
        'maintenance' => 'Repairs and work orders assigned to them.',
        'security' => 'Gate and visitor logs.',
        'tenant' => 'Pays rent and bills, reports repairs, sees their own tenancy.',
        'bnb_host' => 'Runs their own BnB unit(s) inside a building; bookings and earnings stay private.',
        'guest' => 'Signed up, not yet assigned to a company or role.',
    ];

    public static function hasColumn(): bool
    {
        static $has;

        return $has ??= Schema::hasColumn('roles', 'description');
    }

    public static function for(object $role): string
    {
        $saved = self::hasColumn() ? ($role->description ?? null) : null;

        return $saved ?: (self::DESCRIPTIONS[$role->name] ?? ucfirst(str_replace('_', ' ', $role->name)).' role');
    }
}
