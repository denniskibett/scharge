<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An uploaded KYC document (private disk), and what was read from it (App\Services\Kyc\DocumentReader). */
class KycDocument extends Model
{
    protected $fillable = ['company_id', 'property_id', 'kind', 'path', 'original_name', 'mime', 'size', 'read_status', 'extracted', 'read_error', 'uploaded_by'];

    protected $casts = ['extracted' => 'array'];

    public const KINDS = [
        'national_id' => 'National ID (owner or director)',
        'passport' => 'Passport',
        'kra_pin' => 'KRA PIN certificate',
        'business_registration' => 'Business registration / incorporation certificate',
        'cr12' => 'CR12 (company directors)',
        'title_deed' => 'Title deed or lease (per property)',
        'other' => 'Other',
    ];

    public function property()
    {
        return $this->belongsTo(\App\Modules\Properties\Models\Property::class);
    }
}
