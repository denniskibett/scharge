<?php

namespace App\Modules\Expenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpenseCategory extends Model
{
    protected $fillable = ['name','description'];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
