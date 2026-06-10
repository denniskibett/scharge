<?php
// app/Modules/Payments/Models/InvoiceItem.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'amount',
        'item_type',
        'paid_amount',
        'payment_id',
        'water_units_used',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'water_units_used' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
    
    /**
     * Get the remaining amount for this item
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->amount - ($this->paid_amount ?? 0));
    }
    
    /**
     * Check if item is fully paid
     */
    public function isFullyPaid(): bool
    {
        return ($this->paid_amount ?? 0) >= $this->amount;
    }
    
    /**
     * Get payment percentage
     */
    public function getPaymentPercentageAttribute(): float
    {
        if ($this->amount <= 0) return 100;
        return min(100, (($this->paid_amount ?? 0) / $this->amount) * 100);
    }
    
    /**
     * Record a payment against this item
     */
    public function recordPayment(float $amount, int $paymentId): bool
    {
        $newPaid = ($this->paid_amount ?? 0) + $amount;
        $this->paid_amount = min($newPaid, $this->amount);
        $this->payment_id = $paymentId;
        $this->save();
        
        return $this->isFullyPaid();
    }
    
    /**
     * Get the service category for this item
     */
    public function getServiceCategoryAttribute()
    {
        $utilityItems = ['water', 'power', 'internet'];
        $serviceChargeItems = ['security', 'garbage'];
        
        if (in_array($this->item_type, $utilityItems)) {
            return 'Utility';
        } elseif (in_array($this->item_type, $serviceChargeItems)) {
            return 'Service Charge';
        } elseif ($this->item_type === 'rent') {
            return 'Rent';
        }
        
        return 'Other';
    }
    
    /**
     * Get all available item types
     */
    public static function getItemTypes()
    {
        return [
            'rent' => 'Rent',
            'water' => 'Water',
            'power' => 'Power/Electricity',
            'internet' => 'Internet',
            'security' => 'Security',
            'garbage' => 'Garbage Collection',
            'service' => 'Service Charge',
            'other' => 'Other Charges'
        ];
    }
    
    /**
     * Check if this is a utility item
     */
    public function isUtility()
    {
        return in_array($this->item_type, ['water', 'power', 'internet']);
    }
    
    /**
     * Check if this is a service charge
     */
    public function isServiceCharge()
    {
        return in_array($this->item_type, ['security', 'garbage', 'service']);
    }
}