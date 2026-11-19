<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One unit's calendar on a channel (Airbnb, Booking.com…): its iCal export link, imported by ChannelSync. */
class UnitChannel extends Model
{
    use \App\Traits\CompanyScoped;

    protected $fillable = ['company_id', 'unit_id', 'channel', 'import_url', 'last_synced_at', 'last_status', 'last_error'];

    protected $casts = ['import_url' => 'encrypted', 'last_synced_at' => 'datetime'];

    protected $hidden = ['import_url'];

    public const CHANNELS = ['airbnb' => 'Airbnb', 'booking_com' => 'Booking.com', 'vrbo' => 'Vrbo'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
