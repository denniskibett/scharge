<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One reading of a meter, from a reader app, a smart meter or the API. */
class MeterReading extends Model
{
    protected $table = 'meter_readings';

    protected $fillable = ['meter_id', 'company_id', 'unit_id', 'utility', 'reading', 'previous_reading', 'consumption', 'rate_applied', 'charge',
        'read_at', 'source', 'recorded_by', 'photo_path', 'status', 'flag_reason', 'client_ref', 'water_reading_id', 'note', 'raw'];

    protected $casts = [
        'reading' => 'float', 'previous_reading' => 'float', 'consumption' => 'float', 'rate_applied' => 'float', 'charge' => 'float',
        'read_at' => 'datetime', 'raw' => 'array',
    ];

    public function meter()
    {
        return $this->belongsTo(Meter::class);
    }
}
