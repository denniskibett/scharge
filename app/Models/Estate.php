<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Estate extends Model
{
    protected $fillable = ['name','location'];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

}
