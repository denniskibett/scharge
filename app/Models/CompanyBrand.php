<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** A company's white label: name, logos, colour, own web address. App\Support\Brand picks the one for each request. */
class CompanyBrand extends Model
{
    use \App\Traits\CompanyScoped;

    protected $fillable = ['company_id', 'display_name', 'domain', 'slug', 'logo_path', 'logo_dark_path', 'icon_path', 'primary_color',
        'tagline', 'support_phone', 'support_email', 'show_powered_by'];

    protected $casts = ['show_powered_by' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\Brand::forget());
        static::deleted(fn () => \App\Support\Brand::forget());
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function logoDarkUrl(): ?string
    {
        return $this->logo_dark_path ? Storage::disk('public')->url($this->logo_dark_path) : $this->logoUrl();
    }

    public function iconUrl(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }

    /** https://portal.example.co.ke, or null when the company has no own address yet. */
    public function baseUrl(): ?string
    {
        return $this->domain ? 'https://'.$this->domain : null;
    }
}
