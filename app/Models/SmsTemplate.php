<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use \App\Traits\CompanyScoped;

    /** The company's own rows plus the shared defaults (company_id NULL) every company starts from. */
    protected static function companyScope(\Illuminate\Database\Eloquent\Builder $query, array $ids): void
    {
        $query->where(fn ($q) => $q->whereIn('sms_templates.company_id', $ids)->orWhereNull('sms_templates.company_id'));
    }

    //
}
