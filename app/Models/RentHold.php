<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Reminders (and penalties) paused for one tenancy, e.g. an agreed payment plan or a dispute. */
class RentHold extends Model
{
    use \App\Traits\CompanyScoped;

    protected $fillable = ['company_id', 'tenancy_id', 'until', 'reason', 'created_by'];

    protected $casts = ['until' => 'date'];

    public function tenancy()
    {
        return $this->belongsTo(Tenancy::class);
    }

    /** Tenancy ids with reminders paused today. @return int[] */
    public static function activeTenancyIds(int $companyId): array
    {
        return self::where('company_id', $companyId)
            ->where(fn ($q) => $q->whereNull('until')->orWhereDate('until', '>=', now(\App\Services\Rent\RentSettings::TZ)->toDateString()))
            ->pluck('tenancy_id')->map(fn ($id) => (int) $id)->all();
    }
}
