<?php

namespace App\Services;

use App\Models\UnitPhoto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Vacant houses for the public Open listings page, in the shape public/assets/js/main.js reads:
 * one card per property + house type + rent (e.g. "Two Bedroom · Membley · 12 available"),
 * with the photos shared for that type (or the property). Cached for a few minutes.
 */
class ListingCatalog
{
    public const KIND_RENT = 0;

    public const KIND_STAY = 1;

    public function items(): array
    {
        return Cache::remember('public-listings-v3', now()->addMinutes(5), fn () => $this->build());
    }

    /** One listing by its page address (home.listing), or null when it is let / no longer advertised. */
    public function find(string $slug): ?array
    {
        return collect($this->items())->firstWhere('slug', $slug);
    }

    public static function forget(): void
    {
        Cache::forget('public-listings-v3');
    }

    private function build(): array
    {
        $rows = DB::table('units as u')->join('properties as p', 'p.id', '=', 'u.property_id')
            ->leftJoin('companies as c', 'c.id', '=', 'p.company_id')
            ->where('u.status', 'vacant')->where(fn ($q) => $q->whereNull('u.is_active')->orWhere('u.is_active', 1))
            ->where(fn ($q) => $q->whereNull('c.id')->orWhereNull('c.lifecycle')->orWhere('c.lifecycle', '!=', 'prospect'))
            // Only properties whose owner (or scharge) switched "Show on Open listings" on.
            ->when(Schema::hasColumn('properties', 'listed_publicly'), fn ($q) => $q->where('p.listed_publicly', 1))
            ->selectRaw("p.id as pid, p.company_id as cid, p.name as pname, p.location, p.water_rate, u.unit_type, u.stay_type,
                COALESCE(NULLIF(u.rent_amount, 0), 0) as rent, u.bnb_nightly_rate, MAX(u.service_charge) as service,
                MAX(u.security_deposit_amount) as deposit, MAX(u.furnishing_status) as furnishing, MAX(u.custom_water_rate) as custom_rate,
                COUNT(*) as n, MIN(u.id) as first_unit")
            ->groupBy('p.id', 'p.company_id', 'p.name', 'p.location', 'p.water_rate', 'u.unit_type', 'u.stay_type', 'rent', 'u.bnb_nightly_rate')
            ->orderBy('p.name')->get();

        $photos = Schema::hasTable('unit_photos')
            ? UnitPhoto::whereIn('property_id', $rows->pluck('pid')->unique())->whereIn('applies_to', ['type', 'property'])
                ->orderBy('sort')->get()->groupBy('property_id')
            : collect();

        // A listing that is one house (a host's flat) also shows that house's own photos, first. Listings that stand
        // for several identical houses keep to the shared photos, so one flat's photos never advertise another.
        $own = Schema::hasTable('unit_photos')
            ? UnitPhoto::whereIn('unit_id', $rows->where('n', 1)->pluck('first_unit'))->where('applies_to', 'unit')
                ->orderBy('sort')->orderBy('id')->get()->groupBy('unit_id')
            : collect();

        // Who hosts a short stay (the BnB company that registered the unit, e.g. "Urban Nest Airbnbs"), by unit.
        $hosts = Schema::hasTable('unit_managements')
            ? DB::table('unit_managements as m')->join('companies as h', 'h.id', '=', 'm.company_id')
                ->whereIn('m.unit_id', $rows->pluck('first_unit'))->where('m.role', 'bnb_host')->where('m.status', 'active')
                ->orderBy('m.id')->pluck('h.name', 'm.unit_id')
            : collect();

        // Landlords whose KYC scharge verified get the "Verified" badge (docs/ONBOARDING.md).
        $verified = Schema::hasTable('company_kyc') ? DB::table('company_kyc')->where('status', 'verified')->pluck('company_id')->map(fn ($id) => (int) $id)->all() : [];

        return $rows->map(function ($r, $i) use ($photos, $own, $verified, $hosts) {
            $stay = $r->stay_type === 'bnb' && (float) $r->bnb_nightly_rate > 0;
            $price = $stay ? (float) $r->bnb_nightly_rate : (float) $r->rent;
            if ($price <= 0) {
                return null;   // no price set: not ready to advertise
            }

            $set = $photos->get($r->pid, collect());
            $mine = (int) $r->n === 1 ? $own->get($r->first_unit, collect()) : collect();
            $urls = $mine->concat($set->where('applies_to', 'type')->where('unit_type', $r->unit_type))->concat($set->where('applies_to', 'property'))
                ->map(fn ($p) => Storage::disk('public')->url($p->path))->values()->all();

            // A unit's own water rate wins only when set (> 0); a 0 there must not hide the building's rate.
            $water = (float) $r->custom_rate > 0 ? (float) $r->custom_rate : (float) $r->water_rate;
            $costs = collect([
                $water > 0 ? 'Water KES '.number_format($water).'/m³' : null,
                (float) $r->service > 0 ? 'service KES '.number_format((float) $r->service) : null,
                (float) $r->deposit > 0 ? 'deposit KES '.number_format((float) $r->deposit) : null,
            ])->filter()->join(' · ') ?: ($stay ? 'Ask the host about cleaning and other costs' : 'Ask the caretaker for other costs');

            // Stable address for the listing's own page: survives other listings coming and going.
            $slug = \Illuminate\Support\Str::slug(($r->unit_type ?: 'house').' '.($r->location ?: $r->pname))
                .'-'.substr(md5($r->pid.'|'.$r->unit_type.'|'.$r->stay_type.'|'.$price), 0, 8);

            return [
                'k' => $stay ? self::KIND_STAY : self::KIND_RENT,
                'property_id' => (int) $r->pid, // routes enquiries to the landlord (LeadService::requestListing)
                'id' => $i + 1,
                'slug' => $slug,
                'url' => route('home.listing', $slug),
                // A short stay says so in its name, so it is never taken for a house to rent by the month.
                'title' => trim(($r->unit_type ?: 'House').($stay ? ' BnB' : '').' · '.$r->pname),
                'area' => $r->location ?: $r->pname,
                'price' => $price,
                'avail' => $r->n > 1 ? $r->n.' available' : 'Available now',
                'costs' => $costs,
                'verified' => $r->cid && in_array((int) $r->cid, $verified, true),
                'owner' => $r->pname,
                'host' => $stay ? ($hosts[$r->first_unit] ?? null) : null,   // the BnB company, shown as "Hosted by"
                // A short stay is furnished by nature, whatever the unit record's default says.
                'tags' => array_values(array_filter([$stay ? 'Short stay (BnB)' : null, $stay ? 'Furnished' : ($r->furnishing ? ucfirst((string) $r->furnishing) : null),
                    $r->n > 1 ? $r->n.' identical units' : null])),
                'photo' => $urls[0] ?? $this->placeholder($r->unit_type, $r->pname),
                'photos' => $urls,
            ];
        })->filter()->values()->all();
    }

    /** Listings without photos yet show the scharge logo (never a stock photo of someone else's house). */
    private function placeholder(?string $type, string $property): string
    {
        return asset('assets/images/listings/no-photo.svg');
    }
}
