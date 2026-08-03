<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkPrefix extends Model
{
    protected $table = 'network_prefixes';

    protected $fillable = [
        'prefix',
        'network',
        'network_code',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the network for a given prefix
     */
    public static function getNetwork($prefix)
    {
        $record = self::where('prefix', $prefix)->first();
        return $record ? $record->network : null;
    }

    /**
     * Check if a prefix belongs to Safaricom
     */
    public static function isSafaricom($prefix)
    {
        return self::where('prefix', $prefix)
            ->where('network', 'Safaricom')
            ->exists();
    }

    /**
     * Check if a prefix belongs to Airtel
     */
    public static function isAirtel($prefix)
    {
        return self::where('prefix', $prefix)
            ->where('network', 'Airtel')
            ->exists();
    }

    /**
     * Check if a prefix belongs to Telkom
     */
    public static function isTelkom($prefix)
    {
        return self::where('prefix', $prefix)
            ->where('network', 'Telkom')
            ->exists();
    }

    /**
     * Check if a prefix is active (all prefixes are active)
     */
    public static function isActivePrefix($prefix)
    {
        return self::where('prefix', $prefix)->exists();
    }
}