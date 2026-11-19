<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

/**
 * The property class of a CRM company: what they own or manage. A demo-form submission sets it exactly
 * (crm_profile.property_class, from the properties they listed); until then it follows from the lead's segment.
 */
class PropertyClass
{
    public const CLASSES = ['Residential', 'Short-stay', 'Commercial', 'Mixed use', 'Mixed portfolio', 'Land & other', 'Utilities', 'Agency'];

    /** Lead segments (import sheets) → class. */
    public const SEGMENTS = [
        'Residential' => ['Property Manager / Agency', 'Residential Property (named building/estate)', 'Residential Building (site)', 'Developer', 'Affordable Housing', 'High-end Residential', 'Gated Community', 'Upmarket Agency'],
        'Short-stay' => ['Short-stay / Airbnb Manager', 'Short-stay / Airbnb', 'Hotel / Serviced Accommodation', 'Serviced Apartments'],
        'Commercial' => ['Commercial Property / Mall', 'Office / Commercial', 'Retail / Mall', 'Industrial / Warehouse Park', 'Industrial / Factory (utility & metering)', 'Industrial / Logistics'],
        'Utilities' => ['Water Company', 'Private Water Supplier', 'Electricity / KPLC & Metering'],
        // Agencies market and let other people's buildings; they link to the one property row (property_agents).
        'Agency' => ['Real Estate Agency'],
    ];

    public static function of(Company $company): ?string
    {
        if ($class = $company->profile('property_class')) {
            return $class;
        }
        $segment = (string) $company->profile('segment');
        foreach (self::SEGMENTS as $class => $segments) {
            if (in_array($segment, $segments, true)) {
                return $class;
            }
        }

        return match ($company->kind) { 'bnb_host' => 'Short-stay', 'agency' => 'Agency', default => null };
    }

    /** Filter companies by class: the exact class when known, else the segment's class. */
    /** companies.crm_class: the same answer as of(), stored and indexed (database/schema/2026_11_19_crm_class.sql). */
    public static function hasColumn(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::hasColumn('companies', 'crm_class');
    }

    public static function scope(Builder $query, string $class): Builder
    {
        if (self::hasColumn()) {
            return $class === 'none' ? $query->whereNull('companies.crm_class') : $query->where('companies.crm_class', $class);
        }
        if ($class === 'none') {
            return $query->whereNull('crm_profile->property_class')
                ->where(fn ($q) => $q->whereNull('crm_profile->segment')->orWhereNotIn('crm_profile->segment', array_merge(...array_values(self::SEGMENTS))));
        }

        return $query->where(function ($q) use ($class) {
            $q->where('crm_profile->property_class', $class);
            if ($segments = self::SEGMENTS[$class] ?? null) {
                $q->orWhere(fn ($w) => $w->whereNull('crm_profile->property_class')->whereIn('crm_profile->segment', $segments));
            }
            if ($class === 'Short-stay') {
                $q->orWhere(fn ($w) => $w->whereNull('crm_profile->property_class')->where('kind', 'bnb_host'));
            }
        });
    }
}
