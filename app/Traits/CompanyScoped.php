<?php
// app/Traits/CompanyScoped.php

namespace App\Traits;

use App\Support\CompanyAccess;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "company" global scope, the same rule as invoices and tenancies (App\Support\CompanyAccess): a signed-in company
 * user only finds their own company's rows; sysadmins, the scheduler, queue jobs, webhooks and public pages
 * (CompanyAccess::bypass) see everything; an account manager without a company sees the companies they own.
 *
 * By default the rule is "<table>.company_id is one of the user's companies". A model whose company_id is often empty
 * (older rows) or that reaches its company through a parent overrides companyScope().
 *
 * Skip it only on purpose: withoutGlobalScope('company') or CompanyAccess::bypass(), with a comment saying why.
 */
trait CompanyScoped
{
    protected static function bootCompanyScoped(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $ids = CompanyAccess::ids();
            if ($ids !== null) {
                static::companyScope($query, $ids);
            }
        });
    }

    /** @param array<int> $ids */
    protected static function companyScope(Builder $query, array $ids): void
    {
        $query->whereIn($query->getModel()->qualifyColumn('company_id'), $ids);
    }

    public function scopeWithoutCompanyScope(Builder $builder)
    {
        return $builder->withoutGlobalScope('company');
    }
}
