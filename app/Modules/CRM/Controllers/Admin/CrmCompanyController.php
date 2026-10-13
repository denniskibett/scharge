<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Models\WaitlistEntry;
use App\Modules\CRM\Services\ContactService;
use App\Modules\CRM\Support\Countries;
use App\Modules\CRM\Support\Normalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmCompanyController extends Controller
{
    public function __construct(private ContactService $contacts)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'stage', 'tier', 'owner', 'attention']);
        $todo = fn ($q) => $q->whereIn('status', ['open', 'scheduled'])->whereNotIn('type', ['contact', 'feedback']);

        $companies = Company::crm()
            ->with(['owner:id,name'])
            ->withMin(['followUps as next_due_at' => $todo], 'due_at')
            ->withMax(['followUps as last_contact_at' => fn ($q) => $q->whereIn('type', CrmFollowUp::ACTION_TYPES)->where('type', '!=', 'note')->where('status', 'done')], 'done_at')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")->orWhere('crm_profile', 'like', "%{$term}%")))
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $stage === 'open' ? $q->whereIn('crm_stage', Company::OPEN_CRM_STAGES) : $q->where('crm_stage', $stage))
            ->when($filters['tier'] ?? null, fn ($q, $tier) => $q->where('tier', $tier))
            ->when($filters['owner'] ?? null, fn ($q, $owner) => $owner === 'none' ? $q->whereNull('owner_id') : $q->where('owner_id', $owner))
            ->when(($filters['attention'] ?? null) === 'overdue', fn ($q) => $q->whereHas('followUps', fn ($f) => $f->overdue()))
            ->when(($filters['attention'] ?? null) === 'unattended', fn ($q) => $q->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->whereDoesntHave('followUps', $todo))
            ->when(($filters['attention'] ?? null) === 'verify', fn ($q) => $q->where('crm_profile->needs_verification', true))
            ->orderBy('tier')
            ->orderByRaw('next_due_at IS NULL, next_due_at')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Contact names for the visible page in one query.
        $contactNames = CrmFollowUp::where('type', 'contact')->whereIn('company_id', $companies->pluck('id'))
            ->with('contact:id,name')->get()->groupBy('company_id')
            ->map(fn ($rows) => $rows->pluck('contact.name')->filter()->unique()->join(', '));

        $stats = [
            'open' => Company::crm()->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->count(),
            'overdue' => CrmFollowUp::overdue()->count(),
            'due_today' => CrmFollowUp::dueToday()->count(),
            'unattended' => Company::crm()->whereIn('crm_stage', Company::OPEN_CRM_STAGES)->whereDoesntHave('followUps', $todo)->count(),
            'new_demos' => CrmFollowUp::where('type', 'demo')->where('status', 'open')->count(),
            'waitlist' => WaitlistEntry::whereIn('status', ['waiting', 'demo_requested'])->count(),
            'nps' => self::nps(CrmFollowUp::where('type', 'feedback')->whereNotNull('score')->pluck('score')),
        ];

        return view('crm.index', [
            'companies' => $companies,
            'contactNames' => $contactNames,
            'filters' => $filters,
            'stats' => $stats,
            'stageCounts' => Company::crm()->selectRaw('crm_stage, COUNT(*) as total')->groupBy('crm_stage')->pluck('total', 'crm_stage'),
            'owners' => self::staff(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'integer', 'between:1,3'],
            'website' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:150'],
            'country_code' => ['required', Rule::in(array_keys(Countries::africa()))],
            'owner_id' => ['nullable', Rule::in(self::staff()->pluck('id'))],
        ]);
        $input['phone'] = Normalizer::phone($input['phone'] ?? null, Countries::dial($input['country_code']));
        $input['owner_id'] ??= $request->user()->id;

        [$company, $created] = $this->contacts->upsertCompany($input, 'Added by '.$request->user()->name);
        if ($created) {
            $this->contacts->log($company, 'note', 'Prospect added', null, [], null, $request->user()->id);
        }

        return redirect()->route('crm.companies.show', $company)
            ->with('success', $created ? 'Prospect added.' : 'That company is already in the system, so we opened it and filled in any missing details.');
    }

    public function show(Company $crmCompany): View
    {
        $company = $crmCompany->load('owner:id,name');
        $rows = $company->followUps()->with(['contact:id,name,phone,email,lead_source', 'assignee:id,name', 'author:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        return view('crm.show', [
            'company' => $company,
            'contacts' => $rows->where('type', 'contact')->sortByDesc(fn ($r) => (bool) $r->detail('primary')),
            'todo' => $rows->filter(fn ($r) => $r->is_open && ! in_array($r->type, ['contact', 'feedback'], true))->sortBy('due_at'),
            'feedback' => $rows->where('type', 'feedback'),
            'history' => $rows->filter(fn ($r) => ! $r->is_open)->sortByDesc(fn ($r) => $r->done_at ?? $r->created_at),
            'owners' => self::staff(),
        ]);
    }

    public function update(Request $request, Company $crmCompany): RedirectResponse
    {
        $company = $crmCompany;
        $input = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'crm_stage' => ['sometimes', 'required', Rule::in(array_keys(Company::CRM_STAGES))],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'tier' => ['sometimes', 'required', 'integer', 'between:1,3'],
            'owner_id' => ['sometimes', 'nullable', Rule::in(self::staff()->pluck('id'))],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'country_code' => ['sometimes', 'nullable', Rule::in(array_keys(Countries::africa()))],
            'profile' => ['sometimes', 'array'],
            'profile.*' => ['nullable', 'string', 'max:5000'],
        ]);
        $author = $request->user()->id;

        if (isset($input['crm_stage'])) {
            $this->contacts->setStage($company, $input['crm_stage'], $author, $input['lost_reason'] ?? null);
            // Won = they signed: the same row becomes a customer. Their history stays attached.
            if ($input['crm_stage'] === 'won' && $company->lifecycle === 'prospect') {
                $company->update(['lifecycle' => 'customer', 'is_active' => true]);
                $this->contacts->log($company, 'note', 'Became a customer', 'Set up their subscription from Admin › Companies.', [], null, $author);
            }
        }

        if (array_key_exists('owner_id', $input) && $input['owner_id'] != $company->owner_id) {
            $from = $company->owner?->name ?? 'nobody';
            $company->update(['owner_id' => $input['owner_id']]);
            $to = $company->fresh()->owner?->name ?? 'nobody';
            $this->contacts->log($company, 'note', "Owner: {$from} → {$to}", null, [], null, $author);
            // Open work moves with the account.
            $company->followUps()->whereIn('status', ['open', 'scheduled'])->update(['assigned_to' => $input['owner_id']]);
        }

        $changes = array_intersect_key($input, array_flip(['tier', 'email', 'country_code']));
        if (array_key_exists('phone', $input)) {
            $changes['phone'] = Normalizer::phone($input['phone'], Countries::dial($input['country_code'] ?? $company->country_code));
        }
        if (isset($input['name'])) {
            $changes['name'] = Normalizer::companyName($input['name']);
            $changes['name_key'] = Normalizer::companyKey($changes['name']);
        }
        if (isset($input['profile'])) {
            $profile = array_merge($company->crm_profile ?? [], array_intersect_key($input['profile'], array_flip(ContactService::PROFILE_FIELDS)));
            $profile['needs_verification'] = $request->boolean('profile.needs_verification');
            if (array_key_exists('website', $input['profile'])) {
                $profile['website'] = Normalizer::website($input['profile']['website']);
                $changes['domain'] = Normalizer::domain($profile['website'], $changes['email'] ?? $company->email) ?? $company->domain;
            }
            $changes['crm_profile'] = array_filter($profile, fn ($v) => $v !== null && $v !== '');
        }
        if ($changes) {
            $company->update($changes);
        }

        return back()->with('success', 'Saved.');
    }

    public function addContact(Request $request, Company $crmCompany): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'job_title' => ['nullable', 'string', 'max:150'],
        ]);

        [$user, $created] = $this->contacts->upsertPerson($input + ['country_code' => $crmCompany->country_code], 'crm_manual');
        $this->contacts->attachContact($crmCompany, $user, $input['job_title'] ?? null, $request->user()->id);

        return back()->with('success', $created ? 'Contact added.' : 'That person was already in the system, so we linked them instead of creating a copy.');
    }

    /** Net Promoter Score: % promoters (9-10) minus % detractors (0-6). */
    public static function nps($scores): ?int
    {
        if ($scores->isEmpty()) {
            return null;
        }

        return (int) round(($scores->filter(fn ($s) => $s >= 9)->count() - $scores->filter(fn ($s) => $s <= CrmFollowUp::LOW_SCORE)->count()) / $scores->count() * 100);
    }

    /** People who can own CRM accounts: scharge sysadmins. */
    public static function staff()
    {
        return User::role('sysadmin')->orderBy('name')->get(['users.id', 'users.name']);
    }
}
