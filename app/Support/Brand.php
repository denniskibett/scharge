<?php

namespace App\Support;

use App\Models\CompanyBrand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Which white label (if any) a page wears (docs/WHITE_LABEL.md):
 *  1. the web address: a request to a company's own domain (portal.danaff.co.ke) is that company's;
 *  2. otherwise the signed-in user's company, if it has a brand (never for scharge staff: they always see scharge);
 *  3. otherwise scharge.
 * Views read it as $brand (shared by App\Http\Middleware\ApplyBrand); null = scharge's own look.
 */
class Brand
{
    private static array $memo = [];

    public static function current(): ?CompanyBrand
    {
        $host = strtolower((string) request()->getHost());
        if ($host !== '' && ($byHost = self::forDomain($host))) {
            return $byHost;
        }
        $user = auth()->user();
        // scharge's own staff always see scharge on scharge's address, even when their account is linked to a company.
        if (! $user?->company_id || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['sysadmin', 'account_manager']))) {
            return null;
        }

        return self::forCompany((int) $user->company_id);
    }

    /** The brand whose own address this host is (only an exact, configured domain). */
    public static function forDomain(string $host): ?CompanyBrand
    {
        $map = self::map();
        $companyId = $map['domains'][strtolower($host)] ?? null;

        return $companyId ? self::forCompany($companyId) : null;
    }

    public static function forCompany(?int $companyId): ?CompanyBrand
    {
        if (! $companyId || ! in_array($companyId, self::map()['companies'], true)) {
            return null;
        }

        return self::$memo[$companyId] ??= CompanyBrand::where('company_id', $companyId)->first();
    }

    /** Is this request on a company's own address? */
    public static function onOwnDomain(): ?CompanyBrand
    {
        return self::forDomain(strtolower((string) request()->getHost()));
    }

    public static function forget(): void
    {
        self::$memo = [];
        Cache::forget('company-brands');
    }

    /** @return array{domains: array<string, int>, companies: int[]} */
    private static function map(): array
    {
        return Cache::remember('company-brands', 300, function () {
            if (! Schema::hasTable('company_brands')) {
                return ['domains' => [], 'companies' => []];
            }
            $rows = CompanyBrand::get(['company_id', 'domain']);

            return ['domains' => $rows->whereNotNull('domain')->mapWithKeys(fn ($b) => [strtolower($b->domain) => (int) $b->company_id])->all(),
                'companies' => $rows->pluck('company_id')->map(fn ($id) => (int) $id)->all()];
        });
    }

    /**
     * CSS that recolours scharge's brand utilities (Tailwind v3 compiles them to fixed colours) to the company's colour.
     * Shades are mixed from the one colour the company picks.
     */
    public static function css(?CompanyBrand $brand): string
    {
        if (! $brand || ! preg_match('/^#[0-9a-fA-F]{6}$/', $brand->primary_color)) {
            return '';
        }
        $rgb = sscanf($brand->primary_color, '#%02x%02x%02x');
        $mix = function (float $t) use ($rgb): string { // t < 0: towards white, t > 0: towards black
            return sprintf('#%02x%02x%02x', ...array_map(fn ($c) => (int) round($t < 0 ? $c + (255 - $c) * -$t : $c * (1 - $t)), $rgb));
        };
        $shades = [25 => -0.96, 50 => -0.92, 100 => -0.82, 200 => -0.65, 300 => -0.45, 400 => -0.2, 500 => 0.0, 600 => 0.12, 700 => 0.25, 800 => 0.4, 900 => 0.55, 950 => 0.7];
        $css = '';
        foreach ($shades as $n => $t) {
            $c = $mix($t);
            $css .= ".bg-brand-$n,.hover\\:bg-brand-$n:hover,.focus\\:bg-brand-$n:focus{background-color:$c}"
                .".text-brand-$n,.hover\\:text-brand-$n:hover{color:$c}"
                .".border-brand-$n,.focus\\:border-brand-$n:focus,.hover\\:border-brand-$n:hover{border-color:$c}"
                .".fill-brand-$n{fill:$c}.stroke-brand-$n{stroke:$c}.ring-brand-$n,.focus\\:ring-brand-$n:focus{--tw-ring-color:$c}"
                .".dark .dark\\:bg-brand-$n{background-color:$c}.dark .dark\\:text-brand-$n,.dark .dark\\:hover\\:text-brand-$n:hover{color:$c}.dark .dark\\:border-brand-$n{border-color:$c}";
        }

        return $css;
    }
}
