<?php

namespace App\Services\Analytics;

use MaxMind\Db\Reader;

/**
 * IP address → approximate city, region and country, offline: DB-IP's free "IP to City Lite" database
 * (CC BY 4.0, https://db-ip.com), kept in storage/app/geo and refreshed monthly by `php artisan geo:update`.
 * No IP leaves the server. Private and local addresses have no location. Without the database file, only the
 * country from a CDN header (Cloudflare's CF-IPCountry) is used, if there is one.
 */
class GeoIp
{
    private ?Reader $reader = null;

    private bool $tried = false;

    public static function path(): string
    {
        return storage_path('app/geo/dbip-city-lite.mmdb');
    }

    /** @return array{country_code: ?string, country: ?string, region: ?string, city: ?string} */
    public function locate(?string $ip, ?string $cdnCountry = null): array
    {
        $none = ['country_code' => null, 'country' => null, 'region' => null, 'city' => null];
        $cdn = preg_match('/^[A-Z]{2}$/', strtoupper((string) $cdnCountry)) && strtoupper((string) $cdnCountry) !== 'XX' ? strtoupper($cdnCountry) : null;
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return ['country_code' => $cdn] + $none;
        }
        try {
            $row = $this->reader()?->get($ip);
        } catch (\Throwable) {
            $row = null;
        }
        if (! is_array($row)) {
            return ['country_code' => $cdn] + $none;
        }

        return [
            'country_code' => $row['country']['iso_code'] ?? $cdn,
            'country' => $row['country']['names']['en'] ?? null,
            'region' => $row['subdivisions'][0]['names']['en'] ?? null,
            'city' => $row['city']['names']['en'] ?? null,
        ];
    }

    private function reader(): ?Reader
    {
        if (! $this->tried) {
            $this->tried = true;
            $this->reader = is_file(self::path()) ? new Reader(self::path()) : null;
        }

        return $this->reader;
    }
}
