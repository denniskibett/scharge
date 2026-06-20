<?php

namespace App\Modules\Expenses\Models;

use App\Models\Estate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'estate_id',
        'payee_id',
        'expense_category_id',
        'amount',
        'description',
        'expense_date',
        'status'
    ];

    public function estate()
    {
        return $this->belongsTo(Estate::class);
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