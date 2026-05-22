<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    // Role Constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN = 'admin';
    const ROLE_PROPERTY_MANAGER = 'property_manager';
    const ROLE_ACCOUNTANT = 'accountant';
    const ROLE_TENANT = 'tenant';
    const ROLE_METER_READER = 'meter_reader';
    const ROLE_CLEANING_STAFF = 'cleaning_staff';
    const ROLE_MAINTENANCE = 'maintenance';
    const ROLE_SECURITY = 'security';
    const ROLE_GUEST = 'guest';

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
     * Check if user has a specific role.
     */
    public function hasRole($role)
    {
        return $this->name === $role;
    }

    /**
     * Scope for guest role.
     */
    public function scopeGuest($query)
    {
        return $query->where('name', self::ROLE_GUEST);
    }

    /**
     * Scope for staff roles.
     */
    public function scopeStaffRoles($query)
    {
        return $query->where('name', '!=', self::ROLE_GUEST);
    }
    
    /**
     * Get all available role constants as an array.
     */
    public static function getRoleConstants()
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_PROPERTY_MANAGER,
            self::ROLE_ACCOUNTANT,
            self::ROLE_TENANT,
            self::ROLE_METER_READER,
            self::ROLE_CLEANING_STAFF,
            self::ROLE_MAINTENANCE,
            self::ROLE_SECURITY,
            self::ROLE_GUEST,
        ];
    }
}