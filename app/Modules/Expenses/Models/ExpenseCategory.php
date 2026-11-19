<?php

namespace App\Modules\Expenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpenseCategory extends Model
{
    use \App\Traits\CompanyScoped;

    /** The company's own rows plus the shared defaults (company_id NULL) every company starts from. */
    protected static function companyScope(\Illuminate\Database\Eloquent\Builder $query, array $ids): void
    {
        $query->where(fn ($q) => $q->whereIn('expense_categories.company_id', $ids)->orWhereNull('expense_categories.company_id'));
    }

    protected $fillable = ['company_id', 'name', 'description'];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
