<?php

namespace App\Modules\Expenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpensePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'payment_method',
        'transaction_id',
        'transaction_message',
        'paid_by',
        'payment_datetime',
        'amount'
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}