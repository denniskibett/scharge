<?php

namespace App\Modules\Expenses\Models;

use App\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'property_id',
        'payee_id',
        'expense_category_id',
        'amount',
        'description',
        'expense_date',
        'status'
    ];

    /** Shown with the expense: "Cleaning company · Danaff Kenya · November 2026" (payee · client · month). */
    protected $appends = ['label'];

    public function getLabelAttribute(): string
    {
        $client = $this->company?->name ?? $this->property?->company?->name;

        return collect([
            $this->payee?->name,
            $client ? \App\Services\Expenses\BusinessBooks::shortName($client) : null,
            $this->expense_date ? \Carbon\Carbon::parse($this->expense_date)->format('F Y') : null,
        ])->filter()->implode(' · ');
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function payee()
    {
        return $this->belongsTo(Payee::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function payments()
    {
        return $this->hasMany(ExpensePayment::class);
    }
}