<?php
// app/Modules/Payments/Models/Transfer.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasFactory;

    protected $table = 'transfers';

    protected $fillable = [
        'from_id',
        'to_id',
        'from_type',
        'to_type',
        'amount',
        'status',
        'reference',
        'description',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function from()
    {
        return $this->morphTo();
    }

    public function to()
    {
        return $this->morphTo();
    }
}