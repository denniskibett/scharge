<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone2',
        'bio',
        'avatar',
        'country',
        'city',
        'state',
        'postal_code',
        'tax_id',
        'social',
        'first_name',
        'last_name',
        'role_id',  // Add role_id to fillable
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'social' => 'array',
    ];

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }
        return $this->name;
    }

    /**
     * Get the user's initials.
     */
    public function getInitialsAttribute(): string
    {
        $name = $this->full_name;
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }
        return substr($initials, 0, 2);
    }

    /**
     * Get the user's avatar URL.
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        
        $name = urlencode($this->full_name);
        return "https://ui-avatars.com/api/?name={$name}&background=0D8F81&color=fff&size=128";
    }

    /**
     * Get social links with proper URLs
     */
    public function getSocialLinksAttribute(): array
    {
        $social = $this->social ?: [];
        
        $links = [];
        
        if (!empty($social['facebook'])) {
            $links['facebook'] = $this->getSocialUrl($social['facebook'], 'https://facebook.com/');
        }
        
        if (!empty($social['twitter'])) {
            $links['twitter'] = $this->getSocialUrl($social['twitter'], 'https://twitter.com/');
        }
        
        if (!empty($social['instagram'])) {
            $links['instagram'] = $this->getSocialUrl($social['instagram'], 'https://instagram.com/');
        }
        
        if (!empty($social['linkedin'])) {
            $links['linkedin'] = $this->getSocialUrl($social['linkedin'], 'https://linkedin.com/in/');
        }
        
        if (!empty($social['whatsapp'])) {
            $links['whatsapp'] = $this->getSocialUrl($social['whatsapp'], 'https://wa.me/');
        }
        
        return $links;
    }

    /**
     * Get social usernames from URLs
     */
    public function getSocialUsernamesAttribute(): array
    {
        $social = $this->social ?: [];
        $usernames = [];
        
        foreach ($social as $platform => $value) {
            $usernames[$platform] = $this->extractUsername($value);
        }
        
        return $usernames;
    }

    /**
     * Helper to extract username from URL
     */
    private function extractUsername(string $url): string
    {
        if (empty($url)) {
            return '';
        }
        
        if (!str_contains($url, '.') && !str_contains($url, '/')) {
            return $url;
        }
        
        $patterns = [
            'facebook' => [
                '/^https?:\/\/(www\.)?facebook\.com\//',
                '/^https?:\/\/fb\.com\//'
            ],
            'twitter' => [
                '/^https?:\/\/(www\.)?twitter\.com\//',
                '/^https?:\/\/x\.com\//'
            ],
            'instagram' => [
                '/^https?:\/\/(www\.)?instagram\.com\//'
            ],
            'linkedin' => [
                '/^https?:\/\/(www\.)?linkedin\.com\/in\//'
            ],
            'whatsapp' => [
                '/^https?:\/\/(www\.)?wa\.me\//',
                '/^https?:\/\/(www\.)?whatsapp\.com\//'
            ]
        ];
        
        foreach ($patterns as $platformPatterns) {
            foreach ($platformPatterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    return preg_replace($pattern, '', $url);
                }
            }
        }
        
        return $url;
    }

    /**
     * Get social URL from username or URL
     */
    private function getSocialUrl(?string $value, string $baseUrl): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        return rtrim($baseUrl, '/') . '/' . ltrim($value, '/');
    }

    /**
     * Prepare social data for storage
     */
    public function prepareSocialData(array $data): array
    {
        $social = [];
        
        foreach ($data as $platform => $value) {
            if (empty($value)) {
                continue;
            }
            
            $value = trim($value);
            
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $social[$platform] = $value;
            } else {
                $social[$platform] = $this->cleanUsername($value);
            }
        }
        
        return $social;
    }

    /**
     * Clean username (remove @ symbol)
     */
    private function cleanUsername(string $username): string
    {
        return ltrim(trim($username), '@');
    }

    // ========== ROLE MANAGEMENT (Using role_id directly) ==========

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(...$roles): bool
    {
        if (!$this->role) return false;
        
        if (is_array($roles[0])) {
            $roles = $roles[0];
        }
        return in_array($this->role->name, $roles);
    }

    /**
     * Check if user has all of the given roles
     */
    public function hasAllRoles(...$roles): bool
    {
        if (!$this->role) return false;
        
        if (is_array($roles[0])) {
            $roles = $roles[0];
        }
        return in_array($this->role->name, $roles);
    }

    /**
     * Get user's role name
     */
    public function getRoleNameAttribute(): ?string
    {
        return $this->role ? $this->role->name : null;
    }

    /**
     * Get user's role badge for display
     */
    public function getRoleBadgeAttribute(): string
    {
        if (!$this->role) return '<span class="badge bg-secondary">No Role</span>';
        
        $roleColors = [
            'super_admin' => 'danger',
            'admin' => 'danger',
            'property_manager' => 'primary',
            'accountant' => 'success',
            'meter_reader' => 'info',
            'cleaning_staff' => 'warning',
            'maintenance' => 'warning',
            'security' => 'secondary',
            'tenant' => 'dark',
            'guest' => 'light',
        ];
        
        $color = $roleColors[$this->role->name] ?? 'secondary';
        return "<span class='badge bg-{$color}'>{$this->role->name}</span>";
    }

    /**
     * Scope for users with specific role
     */
    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('role', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    /**
     * Scope for users with any of the given roles
     */
    public function scopeWithAnyRole($query, array $roleNames)
    {
        return $query->whereHas('role', function ($q) use ($roleNames) {
            $q->whereIn('name', $roleNames);
        });
    }

    // ========== PERMISSION CHECKS (Role-based) ==========

    /**
     * Check if user can access meter reading features
     */
    public function canReadMeters(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'property_manager', 'meter_reader']);
    }

    /**
     * Check if user can manage cleaning tasks
     */
    public function canManageCleaning(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'property_manager', 'cleaning_staff']);
    }

    /**
     * Check if user can manage maintenance
     */
    public function canManageMaintenance(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'property_manager', 'maintenance']);
    }

    /**
     * Check if user can manage finances
     */
    public function canManageFinances(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'accountant']);
    }

    /**
     * Check if user can manage properties
     */
    public function canManageProperties(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'property_manager']);
    }

    /**
     * Check if user can manage users
     */
    public function canManageUsers(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Check if user can view reports
     */
    public function canViewReports(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'property_manager', 'accountant']);
    }

    /**
     * Check if user is a tenant
     */
    public function isTenant(): bool
    {
        return $this->hasRole('tenant');
    }

    /**
     * Check if user is staff
     */
    public function isStaff(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'property_manager', 'accountant', 'meter_reader', 'cleaning_staff', 'maintenance', 'security']);
    }

    /**
     * Check if user is admin level
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    // ========== RELATIONSHIPS ==========

    /**
     * Get tenant record if user is a tenant
     */
    public function tenant()
    {
        return $this->hasOne(Tenant::class);

        
    }

    /**
     * Get tenancies for this user (if tenant)
     */
    public function tenancies()
    {
        return $this->hasManyThrough(Tenancy::class, Tenant::class, 'user_id', 'tenant_id');
    }

    /**
     * Get active tenancy for this user (if tenant)
     */
    public function activeTenancy()
    {
        return $this->hasOneThrough(Tenancy::class, Tenant::class, 'user_id', 'tenant_id')
            ->where('tenancies.status', 'active');
    }

    /**
     * Get invoices for this user (if tenant)
     */
    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Tenancy::class, 'tenant_id', 'tenancy_id');
    }

    /**
     * Get payments for this user (if tenant)
     */
    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Invoice::class, 'tenancy_id', 'invoice_id');
    }

    /**
     * Get current unit for tenant
     */
    public function getCurrentUnitAttribute()
    {
        if (!$this->isTenant() || !$this->tenant) {
            return null;
        }
        
        $activeTenancy = $this->tenant->activeTenancy;
        return $activeTenancy ? $activeTenancy->unit : null;
    }

    /**
     * Get current estate for tenant
     */
    public function getCurrentEstateAttribute()
    {
        $unit = $this->current_unit;
        return $unit ? $unit->estate : null;
    }

    // ========== UTILITY METHODS ==========

    /**
     * Get dashboard route based on user's role
     */
    public function getDashboardRoute(): string
    {
        if ($this->isAdmin()) {
            return route('dashboard.admin');
        }
        
        if ($this->hasRole('property_manager')) {
            return route('dashboard.property-manager');
        }
        
        if ($this->hasRole('accountant')) {
            return route('dashboard.accountant');
        }
        
        if ($this->isTenant()) {
            return route('dashboard.tenant');
        }
        
        if ($this->hasRole('meter_reader')) {
            return route('dashboard.meter-reader');
        }
        
        if ($this->hasRole('cleaning_staff')) {
            return route('dashboard.cleaning');
        }
        
        if ($this->hasRole('maintenance')) {
            return route('dashboard.maintenance');
        }
        
        if ($this->hasRole('security')) {
            return route('dashboard.security');
        }
        
        return route('dashboard');
    }

    /**
     * Get dashboard title based on user's role
     */
    public function getDashboardTitleAttribute(): string
    {
        if ($this->isAdmin()) {
            return 'Administrator Dashboard';
        }
        
        if ($this->hasRole('property_manager')) {
            return 'Property Manager Dashboard';
        }
        
        if ($this->hasRole('accountant')) {
            return 'Accountant Dashboard';
        }
        
        if ($this->isTenant()) {
            return 'Tenant Dashboard';
        }
        
        if ($this->hasRole('meter_reader')) {
            return 'Meter Reader Dashboard';
        }
        
        if ($this->hasRole('cleaning_staff')) {
            return 'Cleaning Staff Dashboard';
        }
        
        if ($this->hasRole('maintenance')) {
            return 'Maintenance Dashboard';
        }
        
        if ($this->hasRole('security')) {
            return 'Security Dashboard';
        }
        
        return 'Dashboard';
    }


    
}