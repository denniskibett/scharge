<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees what in the CRM.
 *
 * - sysadmin: everything.
 * - account_manager: the companies they own (companies.owner_id) plus
 *   unassigned ones, so they can pick up new inbound leads; and the follow-ups
 *   on those companies, follow-ups assigned to them, and unassigned inbound
 *   requests that have no company yet (viewings, listing alerts).
 * - anyone given "crm.all" (Access::EXTRA): every prospect, follow-up and the whole pipeline.
 *   Sales, revenue and commission of other people stay sysadmin-only (seesAll).
 */
class CrmAccess
{
    public static function seesAll(?Authenticatable $user): bool
    {
        return $user && $user->hasRole('sysadmin');
    }

    /** Sees every prospect and the whole pipeline (sysadmin, or given "crm.all"). */
    public static function seesPipeline(?Authenticatable $user): bool
    {
        return self::seesAll($user) || ($user && $user->can('crm.all'));
    }

    public static function companies(Builder $query, ?Authenticatable $user): Builder
    {
        if (self::seesPipeline($user)) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('companies.owner_id', $user?->id)->orWhereNull('companies.owner_id'));
    }

    public static function followUps(Builder $query, ?Authenticatable $user): Builder
    {
        if (self::seesPipeline($user)) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->where('assigned_to', $user?->id)
            ->orWhereIn('company_id', self::companies(Company::query(), $user)->select('companies.id'))
            ->orWhere(fn ($w) => $w->whereNull('company_id')->whereNull('assigned_to')));
    }

    public static function authorizeCompany(Company $company, ?Authenticatable $user): void
    {
        abort_unless(
            self::seesPipeline($user) || $company->owner_id === null || $company->owner_id === $user?->id,
            403,
            'This account belongs to another account manager.'
        );
    }
}
