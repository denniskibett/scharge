<?php

namespace App\Services\Properties;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One record per building. Before a property is created (by a BnB host or a management company),
 * look for the same building already in scharge: similar name in a similar location.
 *   "Kings Serenity Residence, Rongai" ~ "Kings Serenity Apartments - Rongai, Kajiado"
 */
class PropertyRegistry
{
    /** Words that say nothing about which building it is. */
    private const NOISE = ['apartment', 'apartments', 'apts', 'apt', 'flats', 'flat', 'court', 'residence', 'residences', 'residency',
        'towers', 'tower', 'heights', 'estate', 'estates', 'homes', 'house', 'houses', 'plaza', 'block', 'the', 'and', 'of', 'ltd', 'limited'];

    private const PLACE_NOISE = ['road', 'rd', 'street', 'st', 'avenue', 'ave', 'off', 'along', 'near', 'opposite', 'county', 'kenya', 'nairobi'];

    public static function nameKey(?string $name): string
    {
        return self::key($name, self::NOISE);
    }

    public static function locationKey(?string $location): string
    {
        return self::key($location, self::PLACE_NOISE);
    }

    private static function key(?string $text, array $noise): string
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii((string) $text)), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_diff($words, $noise));
        sort($words);

        return implode(' ', array_unique($words));
    }

    /**
     * Properties that are probably the same building, best first.
     *
     * @return Collection<int, array{property: Property, score: int}>
     */
    public function matches(string $name, ?string $location = null, ?int $ignoreId = null, ?array $pin = null): Collection
    {
        $pins = $pin && \App\Support\PropertyPin::enabled();
        $nk = self::nameKey($name);
        $lk = self::locationKey($location);
        if ($nk === '') {
            return collect();
        }

        return Property::query()->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->get()
            ->map(function (Property $p) use ($nk, $lk, $pin, $pins) {
                $pn = $p->name_key ?: self::nameKey($p->name);
                $pl = $p->location_key ?: self::locationKey($p->location);
                similar_text($nk, $pn, $nameScore);
                $sameWords = $nk === $pn;

                // Location: share at least one place word, or one side has no location at all.
                $placeOk = $lk === '' || $pl === '' || count(array_intersect(explode(' ', $lk), explode(' ', $pl))) > 0;
                $score = (int) round($sameWords ? 100 : $nameScore);
                $score = $placeOk ? $score : (int) round($score * 0.6);

                // Both pinned: the map decides. Same spot (75 m) with a similar name is the same building; 3 km apart is not.
                $there = $pins ? \App\Support\PropertyPin::of($p) : null;
                $metres = $there ? (int) round(\App\Support\PropertyPin::metres($pin, $there)) : null;
                if ($metres !== null && $metres <= 75 && ($nameScore >= 70 || str_contains($pn, $nk) || str_contains($nk, $pn))) {
                    $score = max($score, 95);
                } elseif ($metres !== null && $metres > 3000) {
                    $score = (int) round(min($score, 100) * 0.6);
                }

                return ['property' => $p, 'score' => $score, 'metres' => $metres];
            })
            ->filter(fn ($m) => $m['score'] >= 80)
            ->sortByDesc('score')->values();
    }

    /** Save the matching keys on a property (kept in step on create/update). */
    public static function index(Property $property): void
    {
        if (! Schema::hasColumn('properties', 'name_key')) {
            return;
        }
        $property->forceFill([
            'name_key' => self::nameKey($property->name),
            'location_key' => self::locationKey($property->location),
        ])->saveQuietly();
    }

    public static function present(Property $p, int $score): array
    {
        return [
            'id' => $p->id, 'name' => $p->name, 'location' => $p->location, 'score' => $score,
            'pinned' => \App\Support\PropertyPin::of($p) !== null,
            'managed' => (bool) $p->company_id && ($p->claim_status ?? 'managed') === 'managed',
            'units' => \App\Support\CompanyAccess::bypass(fn () => $p->units()->count()), // the real size, whoever asks
        ];
    }
}
