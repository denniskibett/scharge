<?php

namespace App\Modules\CRM\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Support\Countries;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Finds or creates the people (users) and companies behind website forms and CRM imports,
 * merging duplicates by email / phone and by company domain / name.
 */
class ContactService
{
    /** Role given to people who only exist as leads; it grants nothing beyond the dashboard shell. */
    public const LEAD_ROLE = 'guest';

    /** Sales-sheet fields kept in companies.crm_profile. */
    public const PROFILE_FIELDS = [
        'website', 'phone_alt', 'business_type', 'portfolio', 'scale_signal', 'opportunity', 'target_role', 'route',
        'linkedin', 'decision_maker', 'current_system', 'estimated_units', 'next_action', 'needs_verification', 'notes', 'lost_reason',
    ];

    /**
     * @param  array{name?: ?string, email?: ?string, phone?: ?string, country_code?: ?string, country_is_home?: bool}  $data
     * @return array{0: User, 1: bool} the person and whether they were created
     */
    public function upsertPerson(array $data, string $source): array
    {
        $code = $data['country_code'] ?? null;
        $name = Normalizer::personName($data['name'] ?? null);
        $email = Normalizer::email($data['email'] ?? null);
        $phone = Normalizer::phone($data['phone'] ?? null, Countries::dial($code));
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
                'country' => Countries::name($code),
                'country_code' => $code,
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
        $this->mergePerson($user, $name, $email, $phone, $code, $data['country_is_home'] ?? true);

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

        $sameName = fn (User $u) => $name && Str::lower((string) $u->name) === Str::lower($name);

        // A phone alone only identifies website leads. A real account (tenant, staff, admin) also
        // needs the same name, so typing someone's number can't attach a request to their account.
        $candidates = User::where('phone', 'like', '%'.$tail)
            ->orWhere('phone2', 'like', '%'.$tail)
            ->get()
            ->filter(fn (User $u) => $localPhone
                ? $this->sameTail($phone, $u)
                : Normalizer::phone($u->phone) === $phone || Normalizer::phone($u->phone2) === $phone)
            ->filter(fn (User $u) => $this->isLeadOnly($u) || $sameName($u))
            ->values();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return $candidates->first($sameName) ?? $candidates->first();
    }

    /** True when the user is only a lead (no role, or just the lead role) — safe to update from public forms. */
    public function isLeadOnly(User $user): bool
    {
        return $user->getRoleNames()->reject(fn ($r) => $r === self::LEAD_ROLE)->isEmpty() && ! $user->company_id;
    }

    /**
     * @param  array<string, mixed>  $data  name (required), email, phone, country_code, tier, owner_id, crm_stage + PROFILE_FIELDS
     * @return array{0: Company, 1: bool} the company and whether it was created
     */
    public function upsertCompany(array $data, string $source): array
    {
        $name = Normalizer::companyName($data['name'] ?? null);
        $website = Normalizer::website($data['website'] ?? null);
        $email = Normalizer::email($data['email'] ?? null);
        $domain = Normalizer::domain($website, $email);
        $key = Normalizer::companyKey($name);
        $profile = array_filter(
            ['website' => $website] + array_intersect_key($data, array_flip(self::PROFILE_FIELDS)),
            fn ($v) => is_bool($v) || ! Normalizer::blank(is_scalar($v) ? (string) $v : null)
        );

        $company = $this->findCompany($domain, $key);

        if (! $company) {
            $company = Company::withoutGlobalScope('customers')->create(array_filter([
                'name' => $name,
                'email' => $email,
                'phone' => $data['phone'] ?? null,
                'is_active' => false,
                'lifecycle' => 'prospect',
                'crm_stage' => $data['crm_stage'] ?? 'new',
                'tier' => $data['tier'] ?? 3,
                'owner_id' => $data['owner_id'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'domain' => $domain,
                'name_key' => $key,
                'source' => $source,
                'crm_profile' => $profile ?: null,
            ], fn ($v) => $v !== null));

            return [$company, true];
        }

        // Existing company (prospect or customer): fill blanks, keep the higher priority, collect sources.
        $changes = [];
        foreach (['email' => $email, 'phone' => $data['phone'] ?? null, 'country_code' => $data['country_code'] ?? null, 'domain' => $domain, 'owner_id' => $data['owner_id'] ?? null] as $field => $value) {
            if ($value && blank($company->{$field})) {
                $changes[$field] = $value;
            }
        }
        if (! $company->name_key) {
            $changes['name_key'] = $key;
        }
        if (! $company->crm_stage) {
            // A customer we hadn't tracked: bring it into the CRM without touching its lifecycle.
            $changes['crm_stage'] = $company->lifecycle === 'prospect' ? ($data['crm_stage'] ?? 'new') : 'won';
        }
        if (! empty($data['tier']) && (! $company->tier || $data['tier'] < $company->tier)) {
            $changes['tier'] = $data['tier'];
        }
        $newSource = ! str_contains((string) $company->source, $source);
        if ($newSource) {
            $changes['source'] = trim($company->source ? $company->source.'; '.$source : $source);
        }

        $current = $company->crm_profile ?? [];
        $merged = $current;
        foreach ($profile as $field => $value) {
            if ($field === 'notes') {
                if (! str_contains((string) ($current['notes'] ?? ''), (string) $value)) {
                    $merged['notes'] = trim(($current['notes'] ?? '')."\n".$value);
                }
            } elseif ($field === 'needs_verification') {
                // A new source that says "verify" flags it; re-imports don't undo a check staff already did.
                if ($value && $newSource) {
                    $merged['needs_verification'] = true;
                }
            } elseif (blank($current[$field] ?? null)) {
                $merged[$field] = $value;
            }
        }
        if ($merged !== $current) {
            $changes['crm_profile'] = $merged;
        }

        if ($changes) {
            $company->fill($changes)->save();
        }

        return [$company, false];
    }

    /** Match on website / email domain first, then on the normalised name, across prospects and customers. */
    public function findCompany(?string $domain, ?string $key): ?Company
    {
        $query = fn () => Company::withoutGlobalScope('customers');

        if ($domain && ($company = $query()->where('domain', $domain)->first())) {
            return $company;
        }

        return $key ? $query()->where('name_key', $key)->first() : null;
    }

    /** Link a person to a company: a `contact` row in the CRM pivot (once per person). */
    public function attachContact(Company $company, User $user, ?string $jobTitle = null, ?int $authorId = null): void
    {
        $row = $company->followUps()->where('type', 'contact')->where('user_id', $user->id)->first();
        if ($row) {
            if ($jobTitle && ! $row->detail('job_title')) {
                $row->update(['details' => array_merge($row->details ?? [], ['job_title' => $jobTitle])]);
            }

            return;
        }

        $company->followUps()->create([
            'user_id' => $user->id,
            'created_by' => $authorId,
            'type' => 'contact',
            'status' => 'done',
            'title' => "{$user->name} linked".($jobTitle ? " ({$jobTitle})" : ''),
            'done_at' => now(),
            'details' => array_filter(['job_title' => $jobTitle, 'primary' => ! $company->followUps()->where('type', 'contact')->exists()]),
        ]);
    }

    /** Write a history row (done). */
    public function log(Company $company, string $type, string $title, ?string $notes = null, array $details = [], ?User $contact = null, ?int $authorId = null): CrmFollowUp
    {
        return $company->followUps()->create([
            'user_id' => $contact?->id,
            'created_by' => $authorId,
            'type' => $type,
            'status' => 'done',
            'title' => Str::limit($title, 250),
            'notes' => $notes,
            'done_at' => now(),
            'details' => $details ?: null,
        ]);
    }

    /** Move a company to another stage and record it. */
    public function setStage(Company $company, string $stage, ?int $authorId = null, ?string $reason = null): void
    {
        if ($company->crm_stage === $stage) {
            return;
        }
        $from = $company->crm_stage;
        $company->crm_stage = $stage;
        if ($stage === 'lost' && $reason) {
            $company->crm_profile = array_merge($company->crm_profile ?? [], ['lost_reason' => $reason]);
        }
        $company->save();

        $label = fn ($s) => Company::CRM_STAGES[$s] ?? ucfirst((string) $s);
        $this->log($company, 'stage', 'Stage: '.($from ? $label($from) : 'none').' → '.$label($stage), $reason, ['from' => $from, 'to' => $stage], null, $authorId);
    }

    private function sameTail(?string $phone, User $user): bool
    {
        $tail = Normalizer::phoneTail($phone);

        return $tail && in_array($tail, [Normalizer::phoneTail($user->phone), Normalizer::phoneTail($user->phone2)], true);
    }

    /**
     * Update a matched person with newer details. Only lead-only records change: anyone can type an
     * email into a public form, so real accounts (tenants, staff, admins) are never modified here.
     * $countryIsHome: false when the country is only one they asked about (waitlist), so it just fills a blank.
     */
    private function mergePerson(User $user, ?string $name, ?string $email, ?string $phone, ?string $code, bool $countryIsHome = true): void
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

        if ($code && ($countryIsHome || (! $user->country_code && blank($user->country)))) {
            $changes += ['country_code' => $code, 'country' => Countries::name($code)];
        }

        // Re-format a stored phone (e.g. "0712...") to the clean international format.
        if (! isset($changes['phone']) && $user->phone && ($clean = Normalizer::phone($user->phone)) && $clean !== $user->phone) {
            $changes['phone'] = $clean;
        }

        if ($changes) {
            $user->forceFill($changes)->save();
        }
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
