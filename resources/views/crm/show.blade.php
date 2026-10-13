@extends('layouts.app')

@section('content')
@php
    use App\Models\Company;
    use App\Modules\CRM\Models\CrmFollowUp;
    use App\Modules\CRM\Support\Countries;

    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $area = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $btn = 'inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600';
    $btnGhost = 'inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
    $h = 'text-base font-semibold text-gray-800 dark:text-white/90';
    $icons = ['note' => '📝', 'call' => '📞', 'email' => '✉️', 'whatsapp' => '💬', 'meeting' => '🤝', 'demo' => '📅', 'stage' => '➡️', 'feedback' => '⭐', 'import' => '📥', 'contact' => '👤'];
    $tel = fn ($p) => 'tel:'.preg_replace('/[^\d+]/', '', (string) $p);
    $wa = fn ($p) => 'https://wa.me/'.preg_replace('/\D/', '', (string) $p);
    $sources = ['website_waitlist' => 'joined the website waitlist', 'website_demo' => 'booked a demo on the website', 'crm_manual' => 'added in the CRM'];
    $contactPeople = $contacts->map(fn ($r) => $r->contact)->filter();
    $website = $company->profile('website');
@endphp
<div class="mx-auto max-w-screen-2xl">
    @include('crm.partials.nav', ['title' => $company->name, 'subtitle' => collect([$company->profile('business_type') ?? 'Prospect', Countries::name($company->country_code)])->filter()->join(' · ')])

    {{-- Summary bar --}}
    <div class="{{ $card }} mb-6 flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="flex items-center gap-2">
            @include('crm.partials.tier-badge', ['tier' => $company->tier ?? 3])
            @include('crm.partials.stage-badge', ['stage' => $company->crm_stage])
            <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', 'bg-green-50 text-green-700' => $company->lifecycle === 'customer', 'bg-gray-100 text-gray-600' => $company->lifecycle !== 'customer'])>{{ ucfirst($company->lifecycle) }}</span>
        </div>
        @if ($company->profile('needs_verification'))<span class="rounded bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Some details were marked “Verify” in the sales sheet</span>@endif
        @if ($company->phone)<a href="{{ $tel($company->phone) }}" class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">📞 {{ $company->phone }}</a>@endif
        @if ($company->profile('phone_alt'))<a href="{{ $tel($company->profile('phone_alt')) }}" class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">📞 {{ $company->profile('phone_alt') }}</a>@endif
        @if ($company->email)<a href="mailto:{{ $company->email }}" class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">✉️ {{ $company->email }}</a>@endif
        @if ($website)<a href="{{ $website }}" target="_blank" rel="noopener" class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">🌐 {{ $company->domain ?? $website }}</a>@endif
        <span class="ml-auto text-sm text-gray-500 dark:text-gray-400">Owner: {{ $company->owner?->name ?? 'nobody' }}</span>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        {{-- LEFT --}}
        <div class="space-y-6">
            {{-- Status --}}
            <form method="POST" action="{{ route('crm.companies.update', $company) }}" class="{{ $card }} space-y-4" x-data="{ stage: '{{ old('crm_stage', $company->crm_stage) }}' }">
                @csrf @method('PUT')
                <h3 class="{{ $h }}">Status</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="{{ $label }}" for="c-stage">Stage</label>
                        <select id="c-stage" name="crm_stage" x-model="stage" class="{{ $field }}">@foreach (Company::CRM_STAGES as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select></div>
                    <div><label class="{{ $label }}" for="c-tier">Tier</label>
                        <select id="c-tier" name="tier" class="{{ $field }}">@foreach ([1, 2, 3] as $t)<option value="{{ $t }}" @selected(($company->tier ?? 3) == $t)>Tier {{ $t }}</option>@endforeach</select></div>
                </div>
                <div x-show="stage === 'lost'" x-cloak><label class="{{ $label }}" for="c-lost">Why did we lose it?</label><input id="c-lost" name="lost_reason" value="{{ old('lost_reason', $company->profile('lost_reason')) }}" class="{{ $field }}"></div>
                <p x-show="stage === 'won' && '{{ $company->lifecycle }}' === 'prospect'" x-cloak class="text-xs text-green-700">Saving as Won turns this prospect into a customer (same record, history kept). Then set up their subscription in Admin › Companies.</p>
                <div><label class="{{ $label }}" for="c-owner">Owner</label>
                    <select id="c-owner" name="owner_id" class="{{ $field }}"><option value="">Nobody</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected($company->owner_id == $owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
                <button class="{{ $btn }}">Save status</button>
            </form>

            {{-- People --}}
            <div class="{{ $card }} space-y-4" x-data="{ open: {{ $errors->any() && old('_form') === 'contact' ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between"><h3 class="{{ $h }}">People</h3><button type="button" @click="open = !open" class="text-sm font-medium text-brand-500">+ Add person</button></div>
                @forelse ($contacts as $row)
                    @php $person = $row->contact; @endphp
                    @continue(! $person)
                    <div class="flex items-start justify-between gap-3 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
                        <div>
                            <div class="font-medium text-gray-800 dark:text-white/90">{{ $person->name }} @if ($row->detail('primary'))<span class="text-xs text-gray-500">· main contact</span>@endif</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row->detail('job_title') ?? 'Role not recorded' }}@if ($person->lead_source) · {{ $sources[$person->lead_source] ?? $person->lead_source }}@endif</div>
                        </div>
                        <div class="flex shrink-0 gap-2 text-sm">
                            @if ($person->phone)<a href="{{ $tel($person->phone) }}" title="Call {{ $person->phone }}">📞</a><a href="{{ $wa($person->phone) }}" target="_blank" rel="noopener" title="WhatsApp">💬</a>@endif
                            @if ($person->email)<a href="mailto:{{ $person->email }}" title="{{ $person->email }}">✉️</a>@endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No named contact yet. Target role: {{ $company->profile('target_role') ?? 'not set' }}.</p>
                @endforelse
                <form x-show="open" x-cloak method="POST" action="{{ route('crm.companies.contacts.store', $company) }}" class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @csrf <input type="hidden" name="_form" value="contact">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $label }}" for="ct-name">Name</label><input id="ct-name" name="name" required value="{{ old('name') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="ct-title">Job title</label><input id="ct-title" name="job_title" value="{{ old('job_title') }}" placeholder="{{ \Illuminate\Support\Str::before((string) $company->profile('target_role'), ' /') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="ct-phone">Phone</label><input id="ct-phone" name="phone" value="{{ old('phone') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="ct-email">Email</label><input id="ct-email" name="email" type="email" value="{{ old('email') }}" class="{{ $field }}"></div>
                    </div>
                    <p class="text-xs text-gray-500">If this person is already in the system (same email or phone), we link them instead of creating a copy.</p>
                    <button class="{{ $btn }}">Add person</button>
                </form>
            </div>

            {{-- Details --}}
            <form method="POST" action="{{ route('crm.companies.update', $company) }}" class="{{ $card }} space-y-3" x-data="{ open: false }">
                @csrf @method('PUT')
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between"><h3 class="{{ $h }}">Company details</h3><span class="text-sm text-brand-500" x-text="open ? 'Close' : 'Edit'"></span></button>
                <dl x-show="!open" class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-[140px_1fr]">
                    @foreach (['Portfolio' => 'portfolio', 'Scale' => 'scale_signal', 'Opportunity' => 'opportunity', 'Target role' => 'target_role', 'Route' => 'route', 'Units' => 'estimated_units', 'Sheet next step' => 'next_action', 'Notes' => 'notes'] as $term => $key)
                        @if ($company->profile($key))<dt class="text-gray-500 dark:text-gray-400">{{ $term }}</dt><dd class="whitespace-pre-line text-gray-800 dark:text-white/90">{{ $key === 'estimated_units' ? number_format((int) $company->profile($key)) : $company->profile($key) }}</dd>@endif
                    @endforeach
                    @if ($company->source)<dt class="text-gray-500 dark:text-gray-400">Source</dt><dd class="text-gray-800 dark:text-white/90">{{ $company->source }}</dd>@endif
                </dl>
                <div x-show="open" x-cloak class="space-y-3">
                    <div><label class="{{ $label }}" for="d-name">Name</label><input id="d-name" name="name" value="{{ $company->name }}" class="{{ $field }}"></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $label }}" for="d-phone">Phone</label><input id="d-phone" name="phone" value="{{ $company->phone }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-phone2">Other phone</label><input id="d-phone2" name="profile[phone_alt]" value="{{ $company->profile('phone_alt') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-email">Email</label><input id="d-email" name="email" type="email" value="{{ $company->email }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-web">Website</label><input id="d-web" name="profile[website]" value="{{ $website }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-type">Business type</label><input id="d-type" name="profile[business_type]" value="{{ $company->profile('business_type') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-units">Units</label><input id="d-units" name="profile[estimated_units]" inputmode="numeric" value="{{ $company->profile('estimated_units') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-country">Country</label><select id="d-country" name="country_code" class="{{ $field }}">@foreach (Countries::ordered() as $code => $c)<option value="{{ $code }}" @selected(($company->country_code ?? 'KE') === $code)>{{ $c['name'] }}</option>@endforeach</select></div>
                        <div><label class="{{ $label }}" for="d-role">Target role</label><input id="d-role" name="profile[target_role]" value="{{ $company->profile('target_role') }}" class="{{ $field }}"></div>
                    </div>
                    <div><label class="{{ $label }}" for="d-port">Portfolio</label><textarea id="d-port" name="profile[portfolio]" rows="2" class="{{ $area }}">{{ $company->profile('portfolio') }}</textarea></div>
                    <div><label class="{{ $label }}" for="d-opp">Opportunity</label><textarea id="d-opp" name="profile[opportunity]" rows="2" class="{{ $area }}">{{ $company->profile('opportunity') }}</textarea></div>
                    <div><label class="{{ $label }}" for="d-notes">Notes</label><textarea id="d-notes" name="profile[notes]" rows="3" class="{{ $area }}">{{ $company->profile('notes') }}</textarea></div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="profile[needs_verification]" value="1" @checked($company->profile('needs_verification'))> Details still need verifying</label>
                    <button class="{{ $btn }}">Save details</button>
                </div>
            </form>

            {{-- Feedback --}}
            <div class="{{ $card }} space-y-3">
                <h3 class="{{ $h }}">Feedback</h3>
                @if (session('feedback_link'))
                    <div class="rounded-xl bg-green-50 p-3 text-sm text-green-800 dark:bg-green-500/10 dark:text-green-300" x-data="{ copied: false }">
                        Send this private link to the customer:
                        <div class="mt-2 flex gap-2"><input readonly value="{{ session('feedback_link') }}" class="{{ $field }}" x-ref="link"><button type="button" class="{{ $btnGhost }}" @click="navigator.clipboard.writeText($refs.link.value); copied = true" x-text="copied ? 'Copied' : 'Copy'"></button></div>
                    </div>
                @endif
                @foreach ($feedback as $fb)
                    <div class="flex items-start justify-between gap-3 rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/[0.03]">
                        <div>
                            <div class="text-gray-800 dark:text-white/90">{{ CrmFollowUp::FEEDBACK_CONTEXTS[$fb->detail('context')] ?? 'Feedback' }}@if ($fb->contact) · {{ $fb->contact->name }}@endif</div>
                            @if ($fb->notes && $fb->status === 'done')<div class="text-gray-600 dark:text-gray-300">“{{ $fb->notes }}”</div>@endif
                            <div class="text-xs text-gray-500">{{ $fb->status === 'done' ? 'Answered '.$fb->done_at->diffForHumans() : 'Sent '.$fb->created_at->diffForHumans().' · waiting for an answer' }}</div>
                        </div>
                        @if ($fb->score !== null)
                            <span @class(['rounded-lg px-2 py-1 text-sm font-semibold', 'bg-green-50 text-green-700' => $fb->score >= 9, 'bg-amber-50 text-amber-700' => $fb->score >= 7 && $fb->score <= 8, 'bg-red-50 text-red-700' => $fb->score <= 6])>{{ $fb->score }}/10</span>
                        @else
                            <button type="button" class="text-xs font-medium text-brand-500" onclick="navigator.clipboard.writeText('{{ route('site.feedback.show', $fb->token) }}'); this.textContent='Link copied'">Copy link</button>
                        @endif
                    </div>
                @endforeach
                <form method="POST" action="{{ route('crm.companies.feedback.store', $company) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="min-w-[160px] flex-1"><label class="{{ $label }}" for="fb-context">Ask for feedback</label><select id="fb-context" name="context" class="{{ $field }}">@foreach (CrmFollowUp::FEEDBACK_CONTEXTS as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select></div>
                    @if ($contactPeople->isNotEmpty())
                        <div class="min-w-[160px] flex-1"><label class="{{ $label }}" for="fb-user">From</label><select id="fb-user" name="user_id" class="{{ $field }}">@foreach ($contactPeople as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
                    @endif
                    <button class="{{ $btnGhost }}">Create link</button>
                </form>
                <p class="text-xs text-gray-500 dark:text-gray-400">A score of 6 or less opens a recovery call for the owner automatically.</p>
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="space-y-6">
            {{-- Next steps --}}
            <div class="{{ $card }} space-y-4">
                <h3 class="{{ $h }}">Next steps</h3>
                @if ($todo->isEmpty() && in_array($company->crm_stage, Company::OPEN_CRM_STAGES, true))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">No next step is booked. Schedule one below so this prospect doesn't go cold.</div>
                @endif
                @foreach ($todo as $followUp)
                    @include('crm.partials.follow-up-row', ['followUp' => $followUp, 'showCompany' => false])
                @endforeach
            </div>

            {{-- Log or schedule + history --}}
            <div class="{{ $card }} space-y-4" x-data="{ mode: '{{ old('mode', $todo->isEmpty() ? 'schedule' : 'log') }}' }">
                <div class="flex items-center justify-between">
                    <h3 class="{{ $h }}">History</h3>
                    <div class="flex rounded-lg bg-gray-100 p-1 text-sm dark:bg-gray-900">
                        <button type="button" @click="mode = 'log'" :class="mode === 'log' ? 'bg-white shadow-theme-xs dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 font-medium">Log what happened</button>
                        <button type="button" @click="mode = 'schedule'" :class="mode === 'schedule' ? 'bg-white shadow-theme-xs dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 font-medium">Schedule next step</button>
                    </div>
                </div>
                <form method="POST" action="{{ route('crm.companies.follow-ups.store', $company) }}" class="space-y-3 rounded-xl bg-gray-50 p-4 dark:bg-white/[0.03]">
                    @csrf <input type="hidden" name="mode" :value="mode">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_2fr]">
                        <div><label class="{{ $label }}" for="a-type">Type</label><select id="a-type" name="type" class="{{ $field }}">@foreach (CrmFollowUp::ACTION_TYPES as $type)<option value="{{ $type }}" @selected(old('type') === $type)>{{ CrmFollowUp::TYPES[$type] }}</option>@endforeach</select></div>
                        <div><label class="{{ $label }}" for="a-title">What</label><input id="a-title" name="title" required value="{{ old('title') }}" :placeholder="mode === 'log' ? 'Spoke to the finance director; wants pricing for 400 units' : 'Call the head of property management'" class="{{ $field }}"></div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" x-show="mode === 'schedule'" x-cloak>
                        <div><label class="{{ $label }}" for="a-due">When</label><input id="a-due" name="due_at" type="datetime-local" value="{{ old('due_at', now()->addWeekday()->setTime(10, 0)->format('Y-m-d\TH:i')) }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="a-who">Who</label><select id="a-who" name="assigned_to" class="{{ $field }}">@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected(($company->owner_id ?? auth()->id()) == $owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
                        @if ($contactPeople->isNotEmpty())<div><label class="{{ $label }}" for="a-with">With</label><select id="a-with" name="user_id" class="{{ $field }}"><option value="">—</option>@foreach ($contactPeople as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>@endif
                    </div>
                    <div><label class="{{ $label }}" for="a-notes">Details</label><textarea id="a-notes" name="notes" rows="2" class="{{ $area }}">{{ old('notes') }}</textarea></div>
                    <button class="{{ $btn }}" x-text="mode === 'log' ? 'Log it' : 'Schedule'"></button>
                </form>
                <ol class="relative space-y-4 border-l border-gray-200 pl-5 dark:border-gray-800">
                    @forelse ($history as $row)
                        <li>
                            <span class="absolute -left-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs dark:bg-gray-900" aria-hidden="true">{{ $icons[$row->type] ?? '•' }}</span>
                            <div class="text-sm font-medium text-gray-800 dark:text-white/90">
                                {{ $row->title }}
                                @if (in_array($row->status, ['missed', 'cancelled'], true))<span class="ml-1 text-xs font-normal text-red-600">({{ $row->status }})</span>@endif
                            </div>
                            @if ($row->outcome && $row->type !== 'feedback')<div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">→ {{ $row->outcome }}</div>@endif
                            @if ($row->notes)<div class="mt-0.5 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $row->notes }}</div>@endif
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ ($row->done_at ?? $row->created_at)->format('D j M Y, H:i') }}@if ($row->author) · {{ $row->author->name }}@endif @if ($row->contact && $row->type !== 'contact') · with {{ $row->contact->name }}@endif</div>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">Nothing yet.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
