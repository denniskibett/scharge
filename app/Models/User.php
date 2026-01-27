<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'bio',
        'avatar',
        'country',
        'city',
        'postal_code',
        'tax_id',
        'social',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'social' => 'array',
    ];

    /**
     * Get social links with proper URLs
     */
    public function getSocialLinksAttribute()
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
        
        return $links;
    }

    /**
     * Extract username from URL or return as-is
     */
    public function getSocialUsernamesAttribute()
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
    private function extractUsername($url)
    {
        if (empty($url)) {
            return '';
        }
        
        // If it's already a username (no dots, no slashes), return it
        if (!str_contains($url, '.') && !str_contains($url, '/')) {
            return $url;
        }
        
        // Remove protocol and domain
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
            ]
        ];
        
        // Try to match known patterns
        foreach ($patterns as $platformPatterns) {
            foreach ($platformPatterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    return preg_replace($pattern, '', $url);
                }
            }
        }
        
        // If no match, return as-is
        return $url;
    }


    private function getSocialUrl($value, $baseUrl)
    {
        if (empty($value)) {
            return null;
        }
        
        // If it's already a URL, return it
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        // Otherwise, append to base URL
        return rtrim($baseUrl, '/') . '/' . ltrim($value, '/');
    }


    public function prepareSocialData($data)
    {
        $social = [];
        
        foreach ($data as $platform => $value) {
            if (empty($value)) {
                continue;
            }
            
            // Clean the value
            $value = trim($value);
            
            // If it's a full URL, store as-is
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $social[$platform] = $value;
            } else {
                // Store as username
                $social[$platform] = $this->cleanUsername($value);
            }
        }
        
        return $social;
    }


    private function cleanUsername($username)
    {
        return ltrim(trim($username), '@');
    }

        public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function tenancies()
    {
        return $this->hasMany(Tenancy::class);
    }

    
}