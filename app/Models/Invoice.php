<?php
// app/Models/Invoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'tenancy_id',
        'invoice_type',
        'billing_month',
        'total_amount',
        'total_paid',
        'status',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'billing_month' => 'date',
        'total_amount' => 'decimal:2',
        'total_paid' => 'decimal:2',
    ];

    /**
     * Relationships
     */

    // Invoice belongs to a tenancy
    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    // Invoice has many items
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // Invoice has many payments
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getRemainingAmountAttribute()
    {
        $paidAmount = $this->total_paid ?? 0;
        return max(0, $this->total_amount - $paidAmount);
    }

    public function updateStatus()
    {
        $totalPaid = $this->total_paid ?? 0;
        
        if ($totalPaid >= $this->total_amount) {
            $this->status = 'paid';
        } elseif ($totalPaid > 0 && $totalPaid < $this->total_amount) {
            $this->status = 'partial';
        } else {
            $this->status = 'unpaid';
        }
        
        $this->save();
        return $this;
    }
}