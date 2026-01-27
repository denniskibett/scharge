<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Users with this role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * Scope for guest role.
     */
    public function scopeGuest($query)
    {
        return $query->where('name', 'guest');
    }

    /**
     * Scope for staff roles.
     */
    public function scopeStaffRoles($query)
    {
        return $query->where('name', '!=', 'guest');
    }
}