<?php

namespace App\Support;

use App\Helpers\SystemHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which currency figures are shown in (docs/CURRENCY.md).
 *
 *  book()        the currency of every stored amount without its own currency column (KES; never changes)
 *  platform()    the sysadmin's default on System settings (`system.currency`), if Sharet quotes it; else the book
 *  forCompany()  a company's own display currency (companies.display_currency), else the platform default
 *  display()     the one for whoever is signed in
 *
 * Display only. Stored amounts, invoices and what anyone is charged keep their own currency.
 */
class Currency
{
    private static array $memo = [];

    public static function book(): string
    {
        return strtoupper((string) config('currency.book', 'KES'));
    }

    /** @return array<string, array{name: string, symbol: string, decimals: int}> */
    public static function all(): array
    {
        return (array) config('currency.currencies', []);
    }

    public static function supported(?string $code): bool
    {
        return $code !== null && isset(self::all()[strtoupper($code)]);
    }

    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return self::supported($code) ? $code : self::book();
    }

    public static function decimals(string $code): int
    {
        return (int) (self::all()[strtoupper($code)]['decimals'] ?? 2);
    }

    public static function name(string $code): string
    {
        return (string) (self::all()[strtoupper($code)]['name'] ?? strtoupper($code));
    }

    public static function platform(): string
    {
        return self::$memo['platform'] ??= self::normalize(rescue(fn () => SystemHelper::get('currency'), null, false));
    }

    public static function hasCompanyColumn(): bool
    {
        return self::$memo['column'] ??= (bool) rescue(fn () => Schema::hasColumn('companies', 'display_currency'), false, false);
    }

    /** The company's chosen display currency, or null when it follows the platform default. */
    public static function companyChoice(?int $companyId): ?string
    {
        if (! $companyId || ! self::hasCompanyColumn()) {
            return null;
        }
        $key = 'company.'.$companyId;
        if (! array_key_exists($key, self::$memo)) {
            $code = DB::table('companies')->where('id', $companyId)->value('display_currency');
            self::$memo[$key] = self::supported($code) ? strtoupper($code) : null;
        }

        return self::$memo[$key];
    }

    public static function forCompany(?int $companyId): string
    {
        return self::companyChoice($companyId) ?? self::platform();
    }

    /** Display currency for the signed-in user: their company's, else the platform default. */
    public static function display(): string
    {
        $user = auth()->user();

        return self::forCompany($user?->company_id ? (int) $user->company_id : null);
    }

    public static function setForCompany(int $companyId, ?string $code): void
    {
        if (! self::hasCompanyColumn()) {
            throw new \RuntimeException('Apply database/schema/2026_11_20_display_currency.sql first.');
        }
        $code = $code ? strtoupper($code) : null;
        if ($code !== null && ! self::supported($code)) {
            throw new \InvalidArgumentException("$code is not a supported currency.");
        }
        DB::table('companies')->where('id', $companyId)->update(['display_currency' => $code]);
        self::flush();
    }

    public static function flush(): void
    {
        self::$memo = [];
    }
}
