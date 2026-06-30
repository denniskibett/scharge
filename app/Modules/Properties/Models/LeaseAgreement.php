<?php

namespace App\Modules\Properties\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Properties\Models\Tenancy;
use App\Models\User;
use App\Modules\Tenants\Models\Tenant; 

class LeaseAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenancy_id',
        'agreement_number',
        'signed_date',
        'start_date',
        'end_date',
        'rent_amount',
        'deposit_amount',
        'terms',
        'status',
        'signed_by_tenant',
        'signed_by_landlord',
        'file_path',
        'tenant_signature',
        'landlord_signature',
        'witness_name',
        'witness_phone',
        'special_conditions',
    ];

    protected $casts = [
        'signed_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'rent_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'signed_by_tenant' => 'boolean',
        'signed_by_landlord' => 'boolean',
    ];

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    public function tenant()
    {
        return $this->hasOneThrough(Tenant::class, Tenancy::class, 'id', 'id', 'tenancy_id', 'tenant_id');
    }

    // Generate agreement number
    public static function generateAgreementNumber()
    {
        $year = date('Y');
        $prefix = 'LAG';
        $last = self::whereYear('created_at', $year)->count() + 1;
        return $prefix . $year . str_pad($last, 4, '0', STR_PAD_LEFT);
    }
}