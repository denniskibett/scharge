<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * scharge API key (same model as Sharet Africa's). Format sk_live_<40> / sk_test_<40>.
 * Only the SHA-256 hash is stored, so a lost key is regenerated, never shown again.
 */
class ApiKey extends Model
{
    protected $table = 'api_keys';

    protected $fillable = ['company_id', 'user_id', 'name', 'environment', 'scopes', 'allowed_ips', 'prefix', 'last_four', 'hash', 'enabled', 'last_used_at'];

    /** read: look up properties, tenants, invoices. collect: create pay links. */
    public const SCOPES = ['read', 'collect'];

    protected $casts = ['enabled' => 'boolean', 'last_used_at' => 'datetime', 'scopes' => 'array', 'allowed_ips' => 'array'];

    protected $hidden = ['hash'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** Creates a new random secret and returns the plain key once. */
    public function issue(): string
    {
        $prefix = $this->environment === 'live' ? 'sk_live_' : 'sk_test_';
        $plain = $prefix.Str::random(40);

        $this->prefix = $prefix;
        $this->last_four = substr($plain, -4);
        $this->hash = hash('sha256', $plain);

        return $plain;
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? ['read'], true);
    }

    /** Empty allow-list = any address. */
    public function ipAllowed(?string $ip): bool
    {
        return empty($this->allowed_ips) || ($ip !== null && \Symfony\Component\HttpFoundation\IpUtils::checkIp($ip, $this->allowed_ips));
    }

    /** "41.90.12.7, 196.201.0.0/16" → a clean list, or null if any entry is not an IP or range. */
    public static function parseIps(string|array|null $raw): ?array
    {
        $items = collect(is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw))->map(fn ($i) => trim((string) $i))->filter()->unique()->values();
        foreach ($items as $item) {
            [$addr, $bits] = array_pad(explode('/', $item, 2), 2, null);
            $v6 = filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if (! filter_var($addr, FILTER_VALIDATE_IP) || ($bits !== null && (! ctype_digit($bits) || (int) $bits > ($v6 ? 128 : 32) || (int) $bits < ($v6 ? 32 : 8)))) {
                return null;
            }
        }

        return $items->take(20)->all();
    }

    public static function findByPlain(?string $plain): ?self
    {
        return $plain ? static::where('hash', hash('sha256', $plain))->first() : null;
    }

    public function masked(): string
    {
        return $this->prefix.str_repeat('•', 10).$this->last_four;
    }
}
