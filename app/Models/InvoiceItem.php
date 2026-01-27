<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description', // e.g., Rent, Security, Trash, Utilities
        'amount',
        'item_type', // rent, utility, service_charge
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
