<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An agreement a company accepted in the dashboard (who, when, from where, which version). */
class CompanyAgreement extends Model
{
    protected $fillable = ['company_id', 'agreement', 'version', 'accepted_by', 'accepted_name', 'accepted_title', 'ip', 'accepted_at'];

    protected $casts = ['accepted_at' => 'datetime'];

    /** The data-processing agreement's current version: bump it (and the text) when the terms change. */
    public const DPA_VERSION = '2026-11';

    public static function dpaAccepted(int $companyId): ?self
    {
        return self::where('company_id', $companyId)->where('agreement', 'dpa')->where('version', self::DPA_VERSION)->first();
    }
}
