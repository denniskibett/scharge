<?php

namespace App\Modules\CRM\Support;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Services\Analytics\GeoIp;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every open and click of a cold email: when, roughly where (town, from the offline DB-IP database in GeoIp) and on
 * what device / mail app, so account managers follow up at the time and on the channel the prospect actually reads.
 * Table: crm_email_events (database/schema/2026_11_20_crm_email_events.sql).
 *
 * Mail-app image proxies hide the reader: Gmail (GoogleImageProxy) fetches the image when the email is opened, so the
 * time is real but place and device are Google's; Apple Mail Privacy Protection (Apple's 17.0.0.0/8) may fetch it in
 * the background, so neither time nor place is the reader's. Those rows are marked in `via` and carry no place.
 * Privacy: only a shortened IP is stored, town-level place at most, and rows older than 12 months are deleted (prune).
 */
class EmailEngagement
{
    public const KEEP_MONTHS = 12;

    public const TZ = 'Africa/Nairobi';

    public static function installed(): bool
    {
        static $memo = [];

        return $memo[spl_object_id(app())] ??= Schema::hasTable('crm_email_events');
    }

    /** Record one open or click (never throws: tracking must not break the image or the form). */
    public static function record(?CrmFollowUp $email, string $kind, Request $request): void
    {
        if (! $email || ! in_array($kind, ['open', 'click'], true) || ! self::installed()) {
            return;
        }
        try {
            $ip = (string) $request->ip();
            $ua = (string) $request->userAgent();
            $agent = self::agent($ua, $ip);
            $place = $agent['via'] === 'direct' ? app(GeoIp::class)->locate($ip, $request->header('CF-IPCountry')) : [];
            DB::table('crm_email_events')->insert([
                'follow_up_id' => $email->id, 'company_id' => $email->company_id, 'kind' => $kind, 'occurred_at' => now(),
                'via' => $agent['via'], 'ip_prefix' => self::prefix($ip),
                'country' => $place['country_code'] ?? null, 'region' => self::cut($place['region'] ?? null, 80), 'city' => self::cut($place['city'] ?? null, 80),
                'device' => $agent['device'], 'os' => $agent['os'], 'client' => $agent['client'], 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * What a user agent (and the address) says about the reader.
     *
     * @return array{via: string, device: ?string, os: ?string, client: ?string}
     */
    public static function agent(string $ua, string $ip = ''): array
    {
        if (stripos($ua, 'GoogleImageProxy') !== false || stripos($ua, 'ggpht.com') !== false) {
            return ['via' => 'gmail', 'device' => null, 'os' => null, 'client' => 'Gmail'];
        }
        if (stripos($ua, 'YahooMailProxy') !== false) {
            return ['via' => 'yahoo', 'device' => null, 'os' => null, 'client' => 'Yahoo Mail'];
        }
        if (self::isApple($ip)) {
            return ['via' => 'apple', 'device' => null, 'os' => null, 'client' => 'Apple Mail'];
        }

        $os = match (true) {
            (bool) preg_match('/iPad/i', $ua) => 'iPadOS',
            (bool) preg_match('/iPhone|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Macintosh|Mac OS X/i', $ua) => 'macOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => null,
        };
        $device = match (true) {
            $os === 'iPadOS', preg_match('/Tablet|Android(?!.*Mobile)/i', $ua) && $os === 'Android' => 'tablet',
            in_array($os, ['iOS', 'Android'], true) => 'phone',
            $os !== null => 'computer',
            default => null,
        };
        $client = match (true) {
            (bool) preg_match('/Microsoft Outlook|ms-office|MSOffice|Outlook-iOS|Outlook-Android/i', $ua) => 'Outlook',
            (bool) preg_match('/Thunderbird/i', $ua) => 'Thunderbird',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/Edg\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/Firefox\//i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome\/|CriOS\//i', $ua) => 'Chrome',
            (bool) preg_match('/Version\/[\d.]+.*Safari\//i', $ua) => 'Safari',
            (bool) preg_match('/AppleWebKit/i', $ua) && in_array($os, ['macOS', 'iOS', 'iPadOS'], true) => 'Apple Mail',
            default => null,
        };

        return ['via' => 'direct', 'device' => $device, 'os' => $os, 'client' => $client];
    }

    /** Apple's own 17.0.0.0/8 (Mail Privacy Protection fetches images from there). */
    public static function isApple(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && str_starts_with($ip, '17.');
    }

    /** The address shortened: IPv4 a.b.c.0, IPv6 the first 48 bits. Never the full address. */
    public static function prefix(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);

            return $bin === false ? null : inet_ntop(substr($bin, 0, 6).str_repeat("\0", 10));
        }

        return null;
    }

    /** All events for one email or one company, newest first. */
    public static function events(?int $followUpId = null, ?int $companyId = null, int $limit = 50): Collection
    {
        if (! self::installed() || (! $followUpId && ! $companyId)) {
            return collect();
        }

        return DB::table('crm_email_events')
            ->when($followUpId, fn ($q) => $q->where('follow_up_id', $followUpId))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('occurred_at')->limit($limit)->get()
            ->map(function ($e) {
                $e->at = Carbon::parse($e->occurred_at, config('app.timezone'))->tz(self::TZ);

                return $e;
            });
    }

    /**
     * One line an account manager can act on, e.g. "3 opens · 1 click · last Tue 8:12am · Nairobi · Android phone, Gmail".
     *
     * @return array{opens: int, clicks: int, last: ?Carbon, place: ?string, device: ?string, best: ?string, hidden: bool}|null
     */
    public static function summary(?int $followUpId = null, ?int $companyId = null): ?array
    {
        $all = self::events($followUpId, $companyId, 200);
        if ($all->isEmpty()) {
            return null;
        }
        $real = $all->where('via', '!=', 'apple');          // Apple's background fetches say nothing about when they read
        $located = $all->where('via', 'direct')->first(fn ($e) => $e->city || $e->country);
        $deviced = $all->where('via', 'direct')->first(fn ($e) => $e->device || $e->os);
        $client = $all->first(fn ($e) => $e->client)?->client;

        return [
            'opens' => $all->where('kind', 'open')->count(),
            'clicks' => $all->where('kind', 'click')->count(),
            'last' => $all->first()->at,
            'place' => $located ? self::place($located) : null,
            'device' => self::deviceLabel($deviced, $client),
            'best' => self::bestTime($real),
            'hidden' => $located === null && $all->whereIn('via', ['gmail', 'apple', 'yahoo'])->isNotEmpty(),
        ];
    }

    public static function place(object $e): ?string
    {
        $parts = array_values(array_unique(array_filter([$e->city ?? null, $e->region ?? null])));
        if ($e->country && $e->country !== 'KE') {
            $parts[] = $e->country;   // Kenya is the default; other countries are named
        }

        return $parts ? implode(', ', $parts) : null;
    }

    public static function deviceLabel(?object $e, ?string $client = null): ?string
    {
        $device = $e ? trim(($e->os ?? '').' '.($e->device ?? '')) : '';
        $parts = array_filter([$device ?: null, $client ?? $e?->client]);

        return $parts ? implode(', ', array_unique($parts)) : null;
    }

    /** When they usually read: the most common weekday group and hour, e.g. "Weekdays around 8am". */
    public static function bestTime(Collection $events): ?string
    {
        if ($events->count() < 2) {
            return $events->isEmpty() ? null : 'Read '.$events->first()->at->format('D').' around '.$events->first()->at->format('ga');
        }
        $hour = $events->groupBy(fn ($e) => (int) $e->at->format('G'))->sortByDesc->count()->keys()->first();
        $weekend = $events->filter(fn ($e) => $e->at->isWeekend())->count();
        $days = $weekend > $events->count() / 2 ? 'Weekends' : ($weekend === 0 ? 'Weekdays' : 'Usually');

        return $days.' around '.Carbon::createFromTime($hour, 0, 0, self::TZ)->format('ga');
    }

    /** Delete events older than KEEP_MONTHS (scheduled daily in routes/console.php). */
    public static function prune(): int
    {
        return self::installed()
            ? DB::table('crm_email_events')->where('occurred_at', '<', now()->subMonths(self::KEEP_MONTHS))->delete()
            : 0;
    }

    private static function cut(?string $v, int $n): ?string
    {
        return $v === null ? null : mb_substr($v, 0, $n);
    }
}
