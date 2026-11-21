<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a service-charge budget, e.g. Security KES 360,000 a year, matched to an expense category for "spent". */
class ServiceBudgetLine extends Model
{
    protected $fillable = ['service_budget_id', 'name', 'expense_category_id', 'annual_amount', 'sort'];

    protected $casts = ['annual_amount' => 'float'];

    public function budget()
    {
        return $this->belongsTo(ServiceBudget::class, 'service_budget_id');
    }
}
