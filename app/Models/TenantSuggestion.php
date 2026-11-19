<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An anonymous suggestion from a tenant to their landlord company. Never linked to a person or unit. */
class TenantSuggestion extends Model
{
    use \App\Traits\CompanyScoped;

    public const TOPICS = ['general' => 'General', 'security' => 'Security', 'cleanliness' => 'Cleanliness', 'water' => 'Water & power',
        'noise' => 'Noise & neighbours', 'management' => 'Management'];

    protected $fillable = ['company_id', 'topic', 'body'];

    protected $casts = ['read_at' => 'datetime'];
}
