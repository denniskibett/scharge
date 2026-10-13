<?php

namespace App\Modules\CRM\Services;

use App\Models\User;
use App\Modules\CRM\Models\Country;
use App\Modules\CRM\Models\CrmActivity;
use App\Modules\CRM\Models\CrmCompany;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Finds or creates the people (users) and prospect companies (crm_companies) behind website
 * forms and CRM imports, merging duplicates by email / phone and by company domain / name.
 */
class ContactService
{
    /** Role given to people who only exist as leads; it grants nothing beyond the dashboard shell. */
    public const LEAD_ROLE = 'guest';

    /**
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, country?: ?Country}  $data
     * @return array{0: User, 1: bool} the person and whether they were created
     */
    public function upsertPerson(array $data, string $source): array
    {
        $country = $data['country'] ?? null;
        $dial = $country?->dial_code ?? '254';
        $name = Normalizer::personName($data['name'] ?? null);
        $email = Normalizer::email($data['email'] ?? null);
        $phone = Normalizer::phone($data['phone'] ?? null, $dial);
        $localPhone = Normalizer::isLocalPhone($data['phone'] ?? null);

        $user = $this->findPerson($email, $phone, $name, $localPhone);

        if (! $user) {
            [$first, $last] = $this->splitName($name);
            $user = User::create([
                'name' => $name ?? ($email ?? $phone ?? 'Website lead'),
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone,
                'country' => $country?->name,
                'country_id' => $country?->id,
                'lead_source' => $source,
                // Leads can't log in until they register or reset their password.
                'password' => Hash::make(Str::random(40)),
            ]);
            if (Role::where('name', self::LEAD_ROLE)->exists()) {
                $user->assignRole(self::LEAD_ROLE);
            }

            return [$user, true];
        }

        if ($localPhone && $this->sameTail($phone, $user)) {
            $phone = null; // same number typed without a country code: keep the stored one
        }
        $this->mergePerson($user, $name, $email, $phone, $country, $data['country_is_home'] ?? true);

        return [$user, false];
    }

    /**
     * Email is the strongest match. A phone only matches when it points at one person
     * (several tenants can share a number), or when the name also matches.
     */
    public function findPerson(?string $email, ?string $phone, ?string $name = null, bool $localPhone = false): ?User
    {
        if ($email && ($user = User::whereRaw('LOWER(email) = ?', [$email])->first())) {
            return $user;
        }

        $tail = Normalizer::phoneTail($phone);
        if (! $tail) {
            return null;
        }

        $candidates = User::where('phone', 'like', '%'.$tail)
            ->orWhere('phone2', 'like', '%'.$tail)
            ->get()
            ->filter(fn (User $u) => $localPhone
                ? $this->sameTail($phone, $u)
                : Normalizer::phone($u->phone) === $phone || Normalizer::phone($u->phone2) === $phone)
            ->values();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }
        if ($name) {
            $sameName = $candidates->first(fn (User $u) => Str::lower((string) $u->name) === Str::lower($name));
            if ($sameName) {
                return $sameName;
            }
        }

        return $candidates->first(fn (User $u) => $this->isLeadOnly($u));
    }

    private function sameTail(?string $phone, User $user): bool
    {
        $tail = Normalizer::phoneTail($phone);

        return $tail && in_array($tail, [Normalizer::phoneTail($user->phone), Normalizer::phoneTail($user->phone2)], true);
    }

    /** True when the user is only a lead (no role, or just the lead role) — safe to update from public forms. */
    public function isLeadOnly(User $user): bool
    {
        return $user->getRoleNames()->reject(fn ($r) => $r === self::LEAD_ROLE)->isEmpty() && ! $user->company_id;
    }

    /**
     * Update a matched person with newer details. Only lead-only records change: anyone can type an
     * email into a public form, so real accounts (tenants, staff, admins) are never modified here.
     * $countryIsHome: false when the country is only one they asked about (waitlist), so it just fills a blank.
     */
    private function mergePerson(User $user, ?string $name, ?string $email, ?string $phone, ?Country $country, bool $countryIsHome = true): void
    {
        if (! $this->isLeadOnly($user)) {
            return;
        }
        $changes = [];

        if ($name && $user->name !== $name) {
            [$first, $last] = $this->splitName($name);
            $changes += ['name' => $name, 'first_name' => $first, 'last_name' => $last];
        }

        if ($email && Str::lower((string) $user->email) !== $email && ! User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $changes['email'] = $email;
        }

        if ($phone && Normalizer::phone($user->phone) !== $phone && Normalizer::phone($user->phone2) !== $phone) {
            // Newest number first; keep the old one as the second phone rather than losing it.
            $changes += ['phone' => $phone, 'phone2' => $user->phone ? ($user->phone2 ?: $user->phone) : $user->phone2];
        }

        if ($country && ($countryIsHome || (! $user->country_id && blank($user->country)))) {
            $changes += ['country_id' => $country->id, 'country' => $country->name];
        }

        // Re-format a stored phone (e.g. "0712...") to the clean international format.
        if (! isset($changes['phone']) && $user->phone && ($clean = Normalizer::phone($user->phone)) && $clean !== $user->phone) {
            $changes['phone'] = $clean;
        }

        if ($changes) {
            $user->forceFill($changes)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data  crm_companies columns; name is required
     * @return array{0: CrmCompany, 1: bool} the company and whether it was created
     */
    public function upsertCompany(array $data, string $source): array
    {
        $name = Normalizer::companyName($data['name'] ?? null);
        $website = Normalizer::website($data['website'] ?? null);
        $email = Normalizer::email($data['email'] ?? null);
        $domain = Normalizer::domain($website, $email);
        $key = Normalizer::companyKey($name);

        $company = $this->findCompany($domain, $key);

        $clean = array_filter([
            'name' => $name,
            'name_key' => $key,
            'domain' => $domain,
            'website' => $website,
            'email' => $email,
        ] + array_diff_key($data, array_flip(['name', 'website', 'email'])), fn ($v) => ! Normalizer::blank(is_scalar($v) ? (string) $v : null) || is_bool($v));

        if (! $company) {
            $company = CrmCompany::create($clean + ['source' => $source, 'stage' => $data['stage'] ?? 'new']);

            return [$company, true];
        }

        // Existing company: fill blanks only, keep the higher priority, collect extra sources and notes.
        $changes = [];
        foreach ($clean as $field => $value) {
            if (in_array($field, ['name', 'name_key', 'stage', 'source', 'notes', 'tier', 'needs_verification'], true)) {
                continue;
            }
            if (blank($company->{$field})) {
                $changes[$field] = $value;
            }
        }
        if (isset($clean['tier']) && $clean['tier'] < $company->tier) {
            $changes['tier'] = $clean['tier'];
        }
        $newSource = ! str_contains((string) $company->source, $source);
        if ($newSource) {
            $changes['source'] = trim($company->source ? $company->source.'; '.$source : $source);
        }
        if (! empty($clean['notes']) && ! str_contains((string) $company->notes, $clean['notes'])) {
            $changes['notes'] = trim($company->notes."\n".$clean['notes']);
        }
        // A new source that says "verify" flags the company; re-imports don't undo a check staff already did.
        if ($newSource && ! empty($clean['needs_verification'])) {
            $changes['needs_verification'] = true;
        }
        if ($changes) {
            $company->fill($changes)->save();
        }

        return [$company, false];
    }

    public function findCompany(?string $domain, ?string $key): ?CrmCompany
    {
        if ($domain && ($company = CrmCompany::where('domain', $domain)->first())) {
            return $company;
        }

        return $key ? CrmCompany::where('name_key', $key)->first() : null;
    }

    public function attachContact(CrmCompany $company, User $user, ?string $jobTitle = null): void
    {
        $existing = $company->contacts()->where('users.id', $user->id)->first();
        if ($existing) {
            if ($jobTitle && ! $existing->pivot->job_title) {
                $company->contacts()->updateExistingPivot($user->id, ['job_title' => $jobTitle]);
            }

            return;
        }

        $company->contacts()->attach($user->id, [
            'job_title' => $jobTitle,
            'is_primary' => ! $company->contacts()->wherePivot('is_primary', true)->exists(),
        ]);
    }

    public function log(CrmCompany $company, string $type, string $summary, ?string $body = null, array $meta = [], ?User $contact = null, ?int $authorId = null): CrmActivity
    {
        return $company->activities()->create([
            'user_id' => $contact?->id,
            'created_by' => $authorId,
            'type' => $type,
            'summary' => Str::limit($summary, 250),
            'body' => $body,
            'meta' => $meta ?: null,
            'occurred_at' => now(),
        ]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function splitName(?string $name): array
    {
        if (! $name) {
            return [null, null];
        }
        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? null];
    }
}
