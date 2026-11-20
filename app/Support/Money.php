<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Money in views: format an amount in its own currency, or convert it to the display currency at Sharet's rate.
 *
 *   Money::format(1500, 'KES')          "KES 1,500.00"     (its own currency, never converted)
 *   Money::show(1500)                   "USD 11.63"        (book amount in the viewer's display currency)
 *   Money::show(25, 'USD', 'KES')       "KES 3,225.81"
 *   Money::convert('1500', 'KES', 'USD') "11.63"           (string, rounded half-up to the currency's decimals)
 *   Money::note()                       "Shown in USD at Sharet's rate of 20 Nov 2026 (1 KES = 0.0078 USD)."
 *
 * Conversions are for display and totals only. Nothing here changes a stored amount or what someone is charged.
 * With no rate for a currency the figure stays in its own currency: we never show a guessed number.
 */
class Money
{
    /**
     * $amount converted from $from to $to, as a decimal string rounded half away from zero to $to's decimals.
     * Null when Sharet has no rate for the pair.
     */
    public static function convert(string|int|float|null $amount, ?string $from = null, ?string $to = null): ?string
    {
        $from = Currency::normalize($from ?? Currency::book());
        $to = Currency::normalize($to ?? Currency::display());
        $amount = self::decimal($amount);
        $rate = FxRates::rate($from, $to);
        if ($rate === null) {
            return null;
        }

        return self::round(bcmul($amount, $rate, 12), Currency::decimals($to));
    }

    /** Round a decimal string half away from zero to $places. */
    public static function round(string $value, int $places): string
    {
        $half = '0.'.str_repeat('0', $places).'5';
        $value = bccomp($value, '0', 12) < 0 ? bcsub($value, $half, 12) : bcadd($value, $half, 12);
        $out = bcadd($value, '0', $places); // bcadd truncates toward zero

        return bccomp($out, '0', $places) === 0 ? bcadd('0', '0', $places) : $out; // no "-0.00"
    }

    /** "KES 1,500.00" in the amount's own currency (its code is kept as stored, even one we can't convert). */
    public static function format(string|int|float|null $amount, ?string $currency = null, ?int $decimals = null): string
    {
        $currency = strtoupper(trim((string) ($currency ?: Currency::book())));
        $decimals ??= Currency::decimals($currency);

        return $currency.' '.number_format((float) self::round(self::decimal($amount), $decimals), $decimals);
    }

    /**
     * An amount (in $from, the book currency by default) shown in $to (the viewer's display currency by default).
     * Falls back to the amount's own currency when there is no rate.
     */
    public static function show(string|int|float|null $amount, ?string $from = null, ?string $to = null, ?int $decimals = null): string
    {
        $from = strtoupper(trim((string) ($from ?: Currency::book())));
        $to = Currency::normalize($to ?? Currency::display());
        $converted = Currency::supported($from) ? self::convert($amount, $from, $to) : null;

        return $converted === null ? self::format($amount, $from, $decimals) : self::format($converted, $to, $decimals);
    }

    /** Sum [amount, currency] pairs into $to. Amounts with no rate are left out and listed in 'missing'. */
    public static function total(iterable $items, ?string $to = null): array
    {
        $to = Currency::normalize($to ?? Currency::display());
        $sum = '0';
        $missing = [];
        foreach ($items as $item) {
            [$amount, $cur] = [$item[0] ?? $item['amount'] ?? 0, $item[1] ?? $item['currency'] ?? null];
            $c = self::convert($amount, $cur, $to);
            if ($c === null) {
                $missing[] = Currency::normalize($cur);

                continue;
            }
            $sum = bcadd($sum, $c, 12);
        }

        return ['amount' => self::round($sum, Currency::decimals($to)), 'currency' => $to, 'missing' => array_values(array_unique($missing))];
    }

    /** Is $to different from $from and convertible? (whether a figure on screen is a converted one) */
    public static function converts(?string $from = null, ?string $to = null): bool
    {
        $from = Currency::normalize($from ?? Currency::book());
        $to = Currency::normalize($to ?? Currency::display());

        return $from !== $to && FxRates::rate($from, $to) !== null;
    }

    /**
     * The line that goes next to converted figures: which rate and its date, and whether it is the last known one.
     * Null when nothing is converted (display currency = $from).
     */
    public static function note(?string $from = null, ?string $to = null): ?string
    {
        $from = Currency::normalize($from ?? Currency::book());
        $to = Currency::normalize($to ?? Currency::display());
        if ($from === $to) {
            return null;
        }
        $rate = FxRates::rate($from, $to);
        $snap = FxRates::snapshot();
        if ($rate === null || $snap === null) {
            return "No Sharet rate for $from to $to yet, so amounts are shown in $from.";
        }
        $date = Carbon::parse($snap['date'])->format('j M Y');
        $line = "Shown in $to at Sharet's rate of $date (1 $from = ".self::rateLabel($rate)." $to).";

        return ! empty($snap['stale']) ? rtrim($line, '.').'; Sharet could not be reached, so this is the last known rate.' : $line;
    }

    /** For charts and Alpine: {code, rate, decimals, note} so JS can show book amounts in the display currency. */
    public static function js(?string $from = null, ?string $to = null): array
    {
        $from = Currency::normalize($from ?? Currency::book());
        $to = Currency::normalize($to ?? Currency::display());
        $rate = FxRates::rate($from, $to);

        return $rate === null
            ? ['code' => $from, 'rate' => 1, 'decimals' => Currency::decimals($from), 'note' => self::note($from, $to)]
            : ['code' => $to, 'rate' => (float) $rate, 'decimals' => Currency::decimals($to), 'note' => self::note($from, $to)];
    }

    protected static function rateLabel(string $rate): string
    {
        // Enough significant digits to be useful: 0.007752 / 28.65 / 129.0323
        $f = (float) $rate;
        $dp = $f >= 100 ? 2 : ($f >= 1 ? 4 : max(4, (int) ceil(-log10($f)) + 3));

        return rtrim(rtrim(number_format($f, $dp, '.', ','), '0'), '.');
    }

    protected static function decimal(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0';
        }
        if (is_float($amount)) {
            $amount = sprintf('%.10F', $amount);
        }
        $amount = str_replace([',', ' '], '', (string) $amount);

        return is_numeric($amount) ? $amount : '0';
    }
}
