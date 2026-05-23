<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;
    
    protected $table = 'invoice_items';
    
    protected $fillable = [
        'invoice_id',
        'item_type',
        'description',
        'amount',
        'paid_amount',
        'payment_id',
        'water_units_used'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'water_units_used' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Relationships
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    
}