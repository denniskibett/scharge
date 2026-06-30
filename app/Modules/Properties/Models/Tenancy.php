<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Properties\Models\TenancyCharge;

class Tenancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 
        'unit_id', 
        'move_in_date', 
        'move_out_date', 
        'status',
        'notes',
    ];

    protected $casts = [
        'move_in_date' => 'date',
        'move_out_date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Invoice::class, 'tenancy_id', 'invoice_id', 'id', 'id');
    }
    
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'tenancy_id');
    }

    public function leaseAgreement()
    {
        return $this->hasOne(LeaseAgreement::class);
    }

    public function checklist()
    {
        return $this->hasMany(HouseChecklist::class);
    }

    public function charges()
    {
        return $this->hasMany(TenancyCharge::class);
    }

    // Helper method to check if tenancy is active
    public function isActive()
    {
        return $this->status === 'active';
    }

    // Helper method to check if tenancy is ended
    public function isEnded()
    {
        return $this->status === 'ended';
    }

    // Get the duration of the tenancy in days
    public function getDurationInDaysAttribute()
    {
        $endDate = $this->move_out_date ?? now();
        return $this->move_in_date->diffInDays($endDate);
    }

    // Get formatted duration
    public function getFormattedDurationAttribute()
    {
        $endDate = $this->move_out_date ?? now();
        return $this->move_in_date->diffForHumans($endDate, true);
    }

    // Get the total paid amount for this tenancy
    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }

    // Get the total invoiced amount for this tenancy
    public function getTotalInvoicedAttribute()
    {
        return $this->invoices()->sum('total_amount');
    }

    // Get the outstanding balance
    public function getOutstandingBalanceAttribute()
    {
        return $this->total_invoiced - $this->total_paid;
    }

    // Get deposit balance from charges
    public function getDepositBalanceAttribute()
    {
        $deposits = $this->charges()
            ->where('charge_type', 'deposit')
            ->where('status', 'paid')
            ->sum('amount');
        
        $refunds = $this->charges()
            ->where('charge_type', 'deposit')
            ->where('status', 'refunded')
            ->sum('amount');
        
        return $deposits - $refunds;
    }

    // Check if deposit is fully paid
    public function isDepositPaid()
    {
        $totalDeposit = $this->charges()
            ->where('charge_type', 'deposit')
            ->sum('amount');
        
        $paidDeposit = $this->charges()
            ->where('charge_type', 'deposit')
            ->where('status', 'paid')
            ->sum('amount');
        
        return $paidDeposit >= $totalDeposit;
    }
}