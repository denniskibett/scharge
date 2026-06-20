<?php

namespace App\Modules\Expenses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payee extends Model
{
    use HasFactory;

    protected $fillable = [
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