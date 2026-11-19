<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A landlord company's verification (KYC): the confirmed details, automatic checks and scharge's review. */
class CompanyKyc extends Model
{
    use \App\Traits\CompanyScoped;

    protected $table = 'company_kyc';

    protected $fillable = ['company_id', 'status', 'owner_name', 'owner_id_number', 'kra_pin', 'business_name', 'registration_number',
        'checks', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected $casts = ['checks' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public const STATUSES = ['draft' => 'Not sent yet', 'submitted' => 'With scharge for review', 'verified' => 'Verified', 'rejected' => 'Needs changes'];

    public function company()
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }

    public function documents()
    {
        return $this->hasMany(KycDocument::class, 'company_id', 'company_id');
    }

    public static function isVerified(?int $companyId): bool
    {
        return $companyId && self::where('company_id', $companyId)->where('status', 'verified')->exists();
    }
}
