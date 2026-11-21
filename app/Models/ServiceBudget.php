<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A building's service-charge budget for one year (Budgets page). Company users only see their own company's. */
class ServiceBudget extends Model
{
    protected $fillable = ['company_id', 'property_id', 'year', 'sinking_fund_percent', 'notes', 'created_by'];

    protected $casts = ['sinking_fund_percent' => 'float', 'year' => 'integer'];

    /** Lines a new budget starts with; the manager changes amounts or removes them. */
    public const SUGGESTED_LINES = ['Security', 'Cleaning', 'Garbage collection', 'Common area electricity', 'Common area water',
        'Repairs and maintenance', 'Insurance', 'Management fee'];

    protected static function booted(): void
    {
        static::addGlobalScope('company', function ($query) {
            $ids = \App\Support\CompanyAccess::ids();
            if ($ids !== null) {
                $query->whereIn('service_budgets.company_id', $ids);
            }
        });
        static::creating(function ($budget) {
            $budget->company_id ??= auth()->user()?->company_id;
        });
    }

    public function lines()
    {
        return $this->hasMany(ServiceBudgetLine::class)->orderBy('sort')->orderBy('id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
