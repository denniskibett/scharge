<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'amount',
        'item_type', // rent, power, water, security, garbage, internet, other
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