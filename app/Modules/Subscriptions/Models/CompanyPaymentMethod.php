<?php
// app/Modules/Subscriptions/Models/CompanyPaymentMethod.php

namespace App\Modules\Subscriptions\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyPaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'company_payment_methods';

    protected $fillable = [
        'company_id', 'type', 'last_four', 'expiry_month', 'expiry_year',
        'payment_provider', 'provider_customer_id', 'provider_payment_method_id',
        'is_default', 'is_active'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function setAsDefault()
    {
        // Remove default from other payment methods
        self::where('company_id', $this->company_id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
        
        $this->is_default = true;
        $this->save();
    }
}