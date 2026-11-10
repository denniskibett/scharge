<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A page of a company's hosted website (docs/COMPANY_WEBSITE.md). Company staff edit the draft; scharge approves it,
 * which copies the draft into the live fields. Body is Markdown, rendered safely (no raw HTML).
 */
class CompanyPage extends Model
{
    protected $fillable = ['company_id', 'slug', 'template', 'title', 'hero_title', 'hero_text', 'hero_image', 'body', 'draft', 'status',
        'show_in_menu', 'sort', 'published_at', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'review_note'];

    protected $casts = ['draft' => 'array', 'show_in_menu' => 'boolean', 'published_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];

    public const CONTENT = ['title', 'hero_title', 'hero_text', 'hero_image', 'body'];

    public const TEMPLATES = ['home' => 'Home', 'about' => 'About us', 'rentals' => 'Homes to rent (automatic)', 'contact' => 'Contact', 'custom' => 'Custom page'];

    public const STATUSES = ['draft' => 'Draft', 'pending' => 'Waiting for scharge', 'published' => 'Live', 'changes_pending' => 'Live · changes waiting'];

    public function company()
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }

    public function isLive(): bool
    {
        return $this->published_at !== null;
    }

    /** Draft value if there is one, else the live one (what the editor shows). */
    public function edit(string $field): ?string
    {
        return array_key_exists($field, $this->draft ?? []) ? $this->draft[$field] : $this->{$field};
    }

    public function html(): string
    {
        return Str::markdown((string) $this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    public function heroUrl(?string $path = null): ?string
    {
        $path ??= $this->hero_image;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function path(): string
    {
        return '/'.$this->slug;
    }

    /** Pages every new site starts with (the scharge template), filled from the company's brand. */
    public static function starter(int $companyId, string $name, ?string $tagline): array
    {
        return [
            ['slug' => '', 'template' => 'home', 'sort' => 0, 'title' => 'Home', 'hero_title' => $name, 'hero_text' => $tagline ?: 'Homes managed with care.',
                'body' => "## Welcome\n\nWe manage homes and apartments with care, from viewing to move-in and every month after.\n\n- Pay rent on M-Pesa, any time\n- Report repairs from your phone\n- Water and service charges on one clear bill"],
            ['slug' => 'about', 'template' => 'about', 'sort' => 1, 'title' => 'About us', 'hero_title' => 'About '.$name, 'hero_text' => null,
                'body' => "Tell visitors who you are, how long you have managed property and what you stand for."],
            ['slug' => 'rentals', 'template' => 'rentals', 'sort' => 2, 'title' => 'Homes to rent', 'hero_title' => 'Homes to rent', 'hero_text' => 'Vacant units, updated automatically.', 'body' => null],
            ['slug' => 'contact', 'template' => 'contact', 'sort' => 3, 'title' => 'Contact', 'hero_title' => 'Talk to us', 'hero_text' => null,
                'body' => "Send us a message and we will get back to you, usually the same day."],
        ];
    }
}
