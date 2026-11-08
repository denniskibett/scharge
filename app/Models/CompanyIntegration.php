<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Provider settings for one company (company_id NULL = scharge platform).
 * Definitions live in config/integrations.php; credentials are encrypted with APP_KEY.
 */
class CompanyIntegration extends Model
{
    protected $table = 'company_integrations';

    protected $fillable = ['company_id', 'provider', 'credentials', 'enabled', 'updated_by', 'last_tested_at', 'last_test_ok', 'last_test_message'];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'enabled' => 'boolean',
        'last_test_ok' => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    protected $hidden = ['credentials'];

    public function definition(): array
    {
        return config("integrations.{$this->provider}") ?? [];
    }

    public function get(string $key, $default = null)
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return blank($value) ? $default : $value;
    }

    /** Non-secret values, safe to send to the browser. */
    public function publicValues(): array
    {
        return collect($this->definition()['fields'] ?? [])
            ->reject(fn ($f) => ! empty($f['secret']))
            ->map(fn ($f, $k) => ($this->credentials ?? [])[$k] ?? null)
            ->all();
    }

    /** Names of secret fields that have a saved value. */
    public function savedSecrets(): array
    {
        return collect($this->definition()['fields'] ?? [])
            ->filter(fn ($f, $k) => ! empty($f['secret']) && filled(($this->credentials ?? [])[$k] ?? null))
            ->keys()->values()->all();
    }

    public function mergeCredentials(array $values): void
    {
        $this->credentials = array_merge($this->credentials ?? [], $values);
    }
}
