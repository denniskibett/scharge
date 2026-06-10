<?php
// app/Modules/Payments/Models/Invoice.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Properties\Models\Tenancy;

class Invoice extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'tenancy_id',
        'invoice_number',
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
    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get remaining amount to be paid
     */
    public function getRemainingAmountAttribute(): float
    {
        $paidAmount = $this->total_paid ?? 0;
        return max(0, $this->total_amount - $paidAmount);
    }
    
    /**
     * Get payment percentage
     */
    public function getPaymentPercentageAttribute(): float
    {
        if ($this->total_amount <= 0) return 100;
        return min(100, (($this->total_paid ?? 0) / $this->total_amount) * 100);
    }
    
    /**
     * Check if invoice is fully paid
     */
    public function isFullyPaid(): bool
    {
        return $this->remaining_amount <= 0;
    }
    
    /**
     * Check if invoice has partial payment
     */
    public function hasPartialPayment(): bool
    {
        return ($this->total_paid ?? 0) > 0 && !$this->isFullyPaid();
    }

    /**
     * Update invoice status based on payment
     */
    public function updateStatus(): self
    {
        $totalPaid = $this->total_paid ?? 0;
        
        if ($totalPaid >= $this->total_amount) {
            $this->status = 'paid';
        } elseif ($totalPaid > 0 && $totalPaid < $this->total_amount) {
            $this->status = 'partial';
        } else {
            $this->status = 'unpaid';
        }
        
        $this->saveQuietly(); // Save without triggering events
        return $this;
    }
}