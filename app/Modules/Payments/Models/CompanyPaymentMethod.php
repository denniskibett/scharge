<?php
// app/Modules/Payments/Models/CompanyPaymentMethod.php

namespace App\Modules\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Company;

class CompanyPaymentMethod extends Model
{
    protected $table = 'company_payment_methods';
    
    protected $fillable = [
        'company_id', 'type', 'provider', 'account_name', 'account_number',
        'paybill_number', 'till_number', 'bank_name', 'branch_name',
        'swift_code', 'crypto_network', 'wallet_address', 'instructions',
        'is_default', 'is_active'
    ];
    
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
    
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    
    public function getDisplayNumberAttribute(): string
    {
        if ($this->type === 'mobile_money') {
            return $this->paybill_number ?? $this->till_number ?? 'N/A';
        } elseif ($this->type === 'bank') {
            return $this->account_number ?? 'N/A';
        }
        return 'N/A';
    }
    
    public function getProviderNameAttribute(): string
    {
        if ($this->type === 'mobile_money') {
            return $this->provider ?? 'M-Pesa';
        } elseif ($this->type === 'bank') {
            return $this->bank_name ?? $this->provider ?? 'Bank Transfer';
        }
        return $this->provider ?? 'Payment';
    }
}