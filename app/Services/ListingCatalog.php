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
        return Cache::remember('public-listings-v2', now()->addMinutes(5), fn () => $this->build());
    }

    /** One listing by its page address (home.listing), or null when it is let / no longer advertised. */
    public function find(string $slug): ?array
    {
        return collect($this->items())->firstWhere('slug', $slug);
    }

    public static function forget(): void
    {
        Cache::forget('public-listings-v2');
    }

    private function build(): array
    {
        $rows = DB::table('units as u')->join('properties as p', 'p.id', '=', 'u.property_id')
            ->leftJoin('companies as c', 'c.id', '=', 'p.company_id')
            ->where('u.status', 'vacant')->where(fn ($q) => $q->whereNull('u.is_active')->orWhere('u.is_active', 1))
            ->where(fn ($q) => $q->whereNull('c.id')->orWhereNull('c.lifecycle')->orWhere('c.lifecycle', '!=', 'prospect'))
            ->selectRaw("p.id as pid, p.name as pname, p.location, p.water_rate, u.unit_type, u.stay_type,
                COALESCE(NULLIF(u.rent_amount, 0), 0) as rent, u.bnb_nightly_rate, MAX(u.service_charge) as service,
                MAX(u.security_deposit_amount) as deposit, MAX(u.furnishing_status) as furnishing, MAX(u.custom_water_rate) as custom_rate,
                COUNT(*) as n, MIN(u.id) as first_unit")
            ->groupBy('p.id', 'p.name', 'p.location', 'p.water_rate', 'u.unit_type', 'u.stay_type', 'rent', 'u.bnb_nightly_rate')
            ->orderBy('p.name')->get();

        $photos = Schema::hasTable('unit_photos')
            ? UnitPhoto::whereIn('property_id', $rows->pluck('pid')->unique())->whereIn('applies_to', ['type', 'property'])
                ->orderBy('sort')->get()->groupBy('property_id')
            : collect();

        return $rows->map(function ($r, $i) use ($photos) {
            $stay = $r->stay_type === 'bnb' && (float) $r->bnb_nightly_rate > 0;
            $price = $stay ? (float) $r->bnb_nightly_rate : (float) $r->rent;
            if ($price <= 0) {
                return null;   // no price set: not ready to advertise
            }

            $set = $photos->get($r->pid, collect());
            $urls = $set->where('applies_to', 'type')->where('unit_type', $r->unit_type)->merge($set->where('applies_to', 'property'))
                ->map(fn ($p) => Storage::disk('public')->url($p->path))->values()->all();

            $water = $r->custom_rate ?? $r->water_rate;
            $costs = collect([
                $water ? 'Water KES '.number_format((float) $water).'/m³' : null,
                (float) $r->service > 0 ? 'service KES '.number_format((float) $r->service) : null,
                (float) $r->deposit > 0 ? 'deposit KES '.number_format((float) $r->deposit) : null,
            ])->filter()->join(' · ') ?: 'Ask the caretaker for other costs';

            // Stable address for the listing's own page: survives other listings coming and going.
            $slug = \Illuminate\Support\Str::slug(($r->unit_type ?: 'house').' '.($r->location ?: $r->pname))
                .'-'.substr(md5($r->pid.'|'.$r->unit_type.'|'.$r->stay_type.'|'.$price), 0, 8);

            return [
                'k' => $stay ? self::KIND_STAY : self::KIND_RENT,
                'id' => $i + 1,
                'slug' => $slug,
                'url' => route('home.listing', $slug),
                'title' => trim(($r->unit_type ?: 'House').' · '.$r->pname),
                'area' => $r->location ?: $r->pname,
                'price' => $price,
                'avail' => $r->n > 1 ? $r->n.' available' : 'Available now',
                'costs' => $costs,
                'verified' => false,
                'owner' => $r->pname,
                'tags' => array_values(array_filter([$r->furnishing ? ucfirst((string) $r->furnishing) : null, $r->n > 1 ? $r->n.' identical units' : null])),
                'photo' => $urls[0] ?? $this->placeholder($r->unit_type, $r->pname),
                'photos' => $urls,
            ];
        })->filter()->values()->all();
    }

    /** A neutral picture for listings without photos yet (never a stock photo of someone else's house). */
    private function placeholder(?string $type, string $property): string
    {
        $label = htmlspecialchars(mb_strimwidth(($type ?: 'Home').' · '.$property, 0, 34, '…'), ENT_QUOTES);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="420"><rect width="640" height="420" fill="#F3E9EA"/>'
            .'<path d="M260 230 320 180l60 50v70h-40v-40h-40v40h-40z" fill="#DC2626" opacity=".85"/>'
            .'<text x="320" y="345" font-family="Arial,sans-serif" font-size="22" fill="#5C4A4E" text-anchor="middle">'.$label.'</text>'
            .'<text x="320" y="375" font-family="Arial,sans-serif" font-size="16" fill="#8A7A7D" text-anchor="middle">Photos coming soon</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
