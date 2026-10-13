<?php

namespace App\Modules\CRM\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CRM\Models\Country;
use App\Modules\CRM\Models\CrmActivity;
use App\Modules\CRM\Models\CrmCompany;
use App\Modules\CRM\Models\CrmDemoRequest;
use App\Modules\CRM\Models\CrmFeedback;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Models\WaitlistEntry;
use App\Modules\CRM\Services\ContactService;
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

        $companies = CrmCompany::query()
            ->with(['owner:id,name', 'contacts:id,name'])
            ->withCount(['followUps as open_follow_ups_count' => fn ($q) => $q->whereNull('completed_at')])
            ->withMin(['followUps as next_due_at' => fn ($q) => $q->whereNull('completed_at')], 'due_at')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('portfolio', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $stage === 'open' ? $q->open() : $q->where('stage', $stage))
            ->when($filters['tier'] ?? null, fn ($q, $tier) => $q->where('tier', $tier))
            ->when($filters['owner'] ?? null, fn ($q, $owner) => $owner === 'none' ? $q->whereNull('owner_id') : $q->where('owner_id', $owner))
            ->when(($filters['attention'] ?? null) === 'overdue', fn ($q) => $q->whereHas('followUps', fn ($f) => $f->overdue()))
            ->when(($filters['attention'] ?? null) === 'unattended', fn ($q) => $q->unattended())
            ->when(($filters['attention'] ?? null) === 'verify', fn ($q) => $q->where('needs_verification', true))
            ->orderBy('tier')
            ->orderByRaw('next_due_at IS NULL, next_due_at')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'open' => CrmCompany::open()->count(),
            'overdue' => CrmFollowUp::overdue()->count(),
            'due_today' => CrmFollowUp::open()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'unattended' => CrmCompany::unattended()->count(),
            'new_demos' => CrmDemoRequest::where('status', 'new')->count(),
            'waitlist' => WaitlistEntry::where('status', 'waiting')->count(),
            'nps' => $this->nps(),
        ];
        $stageCounts = CrmCompany::selectRaw('stage, COUNT(*) as total')->groupBy('stage')->pluck('total', 'stage');

        return view('crm.index', [
            'companies' => $companies,
            'filters' => $filters,
            'stats' => $stats,
            'stageCounts' => $stageCounts,
            'owners' => $this->staff(),
            'countries' => Country::active()->get(),
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
            'country_id' => ['nullable', 'exists:countries,id'],
            'owner_id' => ['nullable', Rule::in($this->staff()->pluck('id'))],
        ]);
        $input['phone'] = \App\Modules\CRM\Support\Normalizer::phone($input['phone'] ?? null);
        $input['owner_id'] ??= $request->user()->id;

        [$company, $created] = $this->contacts->upsertCompany($input, 'Added by '.$request->user()->name);
        if ($created) {
            $this->contacts->log($company, 'note', 'Prospect added', null, [], null, $request->user()->id);
        }

        return redirect()->route('crm.companies.show', $company)
            ->with('success', $created ? 'Prospect added.' : 'That company is already in the CRM, so we opened it and filled in any missing details.');
    }

    public function show(CrmCompany $company): View
    {
        $company->load([
            'owner:id,name', 'country', 'customer:id,name',
            'contacts' => fn ($q) => $q->orderByDesc('crm_company_user.is_primary'),
            'activities' => fn ($q) => $q->with('author:id,name', 'contact:id,name')->limit(100),
            'followUps' => fn ($q) => $q->with('assignee:id,name', 'contact:id,name')->orderByRaw('completed_at IS NOT NULL, due_at'),
            'demoRequests.user:id,name', 'demoRequests.country:id,name',
            'feedback.user:id,name',
        ]);

        return view('crm.show', [
            'company' => $company,
            'owners' => $this->staff(),
            'countries' => Country::active()->get(),
        ]);
    }

    public function update(Request $request, CrmCompany $company): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'stage' => ['sometimes', 'required', Rule::in(array_keys(CrmCompany::STAGES))],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'tier' => ['sometimes', 'required', 'integer', 'between:1,3'],
            'owner_id' => ['nullable', Rule::in($this->staff()->pluck('id'))],
            'next_action' => ['nullable', 'string', 'max:255'],
            'next_action_at' => ['nullable', 'date'],
            'business_type' => ['nullable', 'string', 'max:150'],
            'portfolio' => ['nullable', 'string', 'max:5000'],
            'opportunity' => ['nullable', 'string', 'max:5000'],
            'target_role' => ['nullable', 'string', 'max:255'],
            'estimated_units' => ['nullable', 'integer', 'min:0'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_alt' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'exists:countries,id'],
            'needs_verification' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        foreach (['phone', 'phone_alt'] as $field) {
            if (array_key_exists($field, $input)) {
                $input[$field] = \App\Modules\CRM\Support\Normalizer::phone($input[$field]);
            }
        }
        if (array_key_exists('email', $input)) {
            $input['email'] = \App\Modules\CRM\Support\Normalizer::email($input['email']);
        }
        if (array_key_exists('website', $input)) {
            $input['website'] = \App\Modules\CRM\Support\Normalizer::website($input['website']);
            $input['domain'] = \App\Modules\CRM\Support\Normalizer::domain($input['website'], $input['email'] ?? $company->email) ?? $company->domain;
        }
        if (isset($input['name'])) {
            $input['name'] = \App\Modules\CRM\Support\Normalizer::companyName($input['name']);
            $input['name_key'] = \App\Modules\CRM\Support\Normalizer::companyKey($input['name']);
        }

        $from = $company->stage;
        $fromOwner = $company->owner?->name;
        $company->fill($input);

        if ($company->isDirty('stage')) {
            $label = CrmCompany::STAGES[$company->stage];
            $this->contacts->log($company, 'stage_change', "Stage: ".(CrmCompany::STAGES[$from] ?? $from)." → {$label}",
                $company->stage === 'lost' ? ($input['lost_reason'] ?? null) : null, ['from' => $from, 'to' => $company->stage], null, $request->user()->id);
        }
        if ($company->isDirty('owner_id')) {
            $to = User::find($company->owner_id)?->name ?? 'nobody';
            $this->contacts->log($company, 'note', "Owner: ".($fromOwner ?? 'nobody')." → {$to}", null, [], null, $request->user()->id);
            // Open follow-ups move with the account.
            $company->followUps()->whereNull('completed_at')->update(['assigned_to' => $company->owner_id]);
        }
        $company->save();

        return back()->with('success', 'Saved.');
    }

    public function logActivity(Request $request, CrmCompany $company): RedirectResponse
    {
        $input = $request->validate([
            'type' => ['required', Rule::in(array_keys(CrmActivity::MANUAL_TYPES))],
            'summary' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'user_id' => ['nullable', Rule::in($company->contacts()->pluck('users.id'))],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $activity = $this->contacts->log($company, $input['type'], $input['summary'], $input['body'] ?? null, [],
            isset($input['user_id']) ? User::find($input['user_id']) : null, $request->user()->id);
        if (! empty($input['occurred_at'])) {
            $activity->update(['occurred_at' => $input['occurred_at']]);
        }
        if ($input['type'] !== 'note') {
            $company->update(['last_contacted_at' => $activity->occurred_at]);
            if ($company->stage === 'new') {
                $company->update(['stage' => 'contacted']);
                $this->contacts->log($company, 'stage_change', 'Stage: New → Contacted', null, ['from' => 'new', 'to' => 'contacted'], null, $request->user()->id);
            }
        }

        return back()->with('success', 'Activity logged.');
    }

    public function addContact(Request $request, CrmCompany $company): RedirectResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'job_title' => ['nullable', 'string', 'max:150'],
        ]);

        [$user, $created] = $this->contacts->upsertPerson($input + ['country' => $company->country], 'crm_manual');
        $this->contacts->attachContact($company, $user, $input['job_title'] ?? null);
        $this->contacts->log($company, 'note', "Contact added: {$user->name}".(! empty($input['job_title']) ? " ({$input['job_title']})" : ''), null, ['created_user' => $created], $user, $request->user()->id);

        return back()->with('success', $created ? 'Contact added.' : 'That person was already in the system, so we linked them and updated their details.');
    }

    /** Net Promoter Score over submitted feedback: % promoters (9-10) minus % detractors (0-6). */
    private function nps(): ?int
    {
        $scores = CrmFeedback::whereNotNull('score')->pluck('score');
        if ($scores->isEmpty()) {
            return null;
        }

        return (int) round(($scores->filter(fn ($s) => $s >= 9)->count() - $scores->filter(fn ($s) => $s <= CrmFeedback::LOW_SCORE)->count()) / $scores->count() * 100);
    }

    /** People who can own CRM accounts: scharge sysadmins. */
    private function staff()
    {
        return User::role('sysadmin')->orderBy('name')->get(['users.id', 'users.name']);
    }
}
