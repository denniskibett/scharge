<?php

namespace App\Services\App;

use App\Models\CompanyIntegration;
use Illuminate\Support\Facades\Schema;

/** scharge app settings (Integrations › scharge app, platform): Firebase push, and the oldest app version allowed. docs/APP_DATA.md */
class AppSettings
{
    public static function row(): ?CompanyIntegration
    {
        return Schema::hasTable('company_integrations')
            ? CompanyIntegration::withoutGlobalScope('company')->where('provider', 'mobile_app')->whereNull('company_id')->where('enabled', true)->latest('id')->first() : null;
    }

    public static function minVersion(): ?string
    {
        return self::row()?->get('min_version') ?: null;
    }

    /** "1.2.3" style comparison; true when the app must be updated. */
    public static function tooOld(?string $version): bool
    {
        $min = self::minVersion();

        return $min && $version && preg_match('/^\d+(\.\d+){0,3}/', $version, $m) && version_compare($m[0], $min, '<');
    }
}
