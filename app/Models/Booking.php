<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A short stay in one unit: made here (direct) or imported from a channel calendar. App\Services\Bookings\BookingCalendar. */
class Booking extends Model
{
    use \App\Traits\CompanyScoped;

    protected $fillable = ['company_id', 'unit_id', 'source', 'external_uid', 'guest_name', 'guest_phone', 'guests', 'check_in', 'check_out',
        'amount', 'status', 'notes', 'created_by'];

    protected $casts = ['check_in' => 'date', 'check_out' => 'date', 'amount' => 'decimal:2', 'guests' => 'integer'];

    public const SOURCES = ['direct' => 'Direct', 'airbnb' => 'Airbnb', 'booking_com' => 'Booking.com', 'vrbo' => 'Vrbo', 'other' => 'Other'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function getNightsAttribute(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }
}
