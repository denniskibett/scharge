<?php

namespace App\Traits;

use App\Support\CompanyAccess;
use App\Support\TenantSelf;
use Illuminate\Database\Eloquent\Builder;

/**
 * Records that hang off a unit (repairs, gate log, water readings): a signed-in person finds only their own company's,
 * in every list, count, report and /{id} URL. Theirs = saved with their company, or on a unit they can see (the Unit
 * scope: own, managed, or granted). Sysadmins, the scheduler, queue and callbacks see all (CompanyAccess::ids() null).
 *
 * A model can narrow residents further with residentFilter() (their own repairs, their own unit's readings).
 */
trait OwnCompanyRecords
{
    protected static function bootOwnCompanyRecords(): void
    {
        static::addGlobalScope('company', function (Builder $q) {
            $ids = CompanyAccess::ids();
            if ($ids === null) {
                return;
            }
            $table = $q->getModel()->getTable();
            $q->where(fn ($w) => $w->whereIn("$table.company_id", $ids ?: [0])->orWhereHas('unit'));

            if (($self = TenantSelf::id()) !== null) {
                static::residentFilter($q, $table, $self);
            }
        });
    }

    /** Extra narrowing for a resident (tenant record id $self); none by default. */
    protected static function residentFilter(Builder $q, string $table, int $self): void
    {
    }
}
