<?php

namespace App\Modules\Expenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payee extends Model
{
    use \App\Traits\CompanyScoped;

    /** The company's own rows plus the shared defaults (company_id NULL) every company starts from. */
    protected static function companyScope(\Illuminate\Database\Eloquent\Builder $query, array $ids): void
    {
        $query->where(fn ($q) => $q->whereIn('payees.company_id', $ids)->orWhereNull('payees.company_id'));
    }

    use HasFactory;

    protected $fillable = [
        'company_id',
        'slug',
        'name',
        'type',
        'phone',
        'email',
        'id_number',
        'kra_pin',
        'nssf_number',
        'sha_number'
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}