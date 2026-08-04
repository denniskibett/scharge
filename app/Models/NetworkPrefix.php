<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkPrefix extends Model
{
    protected $table = 'network_prefixes';

    protected $fillable = [
        'prefix',
        'network',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope a query to only include active prefixes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include Safaricom prefixes
     */
    public function scopeSafaricom($query)
    {
        return $query->where('network', 'Safaricom');
    }

    /**
     * Get the network for a given prefix
     */
    public static function getNetwork($prefix)
    {
        $record = self::where('prefix', $prefix)
            ->where('status', 'active')
            ->first();

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
     * Check if a prefix is active
     */
    public static function isActivePrefix($prefix)
    {
        return self::where('prefix', $prefix)
            ->where('status', 'active')
            ->exists();
    }
}