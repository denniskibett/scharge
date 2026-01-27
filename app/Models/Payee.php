<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payee extends Model
{
    protected $fillable = ['name','type','phone','email'];

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
