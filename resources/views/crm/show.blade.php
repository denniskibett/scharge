@extends('layouts.app')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $area = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $btn = 'inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600';
    $btnGhost = 'inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
    $h = 'text-base font-semibold text-gray-800 dark:text-white/90';
    $openFollowUps = $company->followUps->whereNull('completed_at');
    $doneFollowUps = $company->followUps->whereNotNull('completed_at');
    $icons = ['note' => '📝', 'call' => '📞', 'email' => '✉️', 'whatsapp' => '💬', 'meeting' => '🤝', 'demo_request' => '📅', 'waitlist' => '🌍', 'stage_change' => '➡️', 'follow_up' => '✅', 'feedback' => '⭐', 'import' => '📥'];
    $tel = fn ($p) => 'tel:'.preg_replace('/[^\d+]/', '', (string) $p);
    $wa = fn ($p) => 'https://wa.me/'.preg_replace('/\D/', '', (string) $p);
@endphp
<div class="mx-auto max-w-screen-2xl">
    @include('crm.partials.nav', ['title' => $company->name, 'subtitle' => trim(($company->business_type ?? 'Prospect').($company->country ? ' · '.$company->country->name : ''))])

    {{-- Summary bar --}}
    <div class="{{ $card }} mb-6 flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="flex items-center gap-2">@include('crm.partials.tier-badge', ['tier' => $company->tier]) @include('crm.partials.stage-badge', ['stage' => $company->stage])</div>
        @if ($company->needs_verification)<span class="rounded bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Some details were marked “Verify” in the sales sheet</span>@endif
        @if ($company->phone)<a href="{{ $tel($company->phone) }}" class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">📞 {{ $company->phone }}</a>@endif
        @if ($company->phone_alt)<a href="{{ $tel($company->phone_alt) }}" class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">📞 {{ $company->phone_alt }}</a>@endif
        @if ($company->email)<a href="mailto:{{ $company->email }}" class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">✉️ {{ $company->email }}</a>@endif
        @if ($company->website)<a href="{{ $company->website }}" target="_blank" rel="noopener" class="text-sm text-gray-700 hover:text-brand-500 dark:text-gray-300">🌐 {{ $company->domain ?? $company->website }}</a>@endif
        @if ($company->customer)<span class="text-sm text-green-700">Customer: {{ $company->customer->name }}</span>@endif
        <span class="ml-auto text-sm text-gray-500 dark:text-gray-400">Last contact: {{ $company->last_contacted_at?->diffForHumans() ?? 'never' }}</span>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        {{-- LEFT --}}
        <div class="space-y-6">
            {{-- Stage & owner --}}
            <form method="POST" action="{{ route('crm.companies.update', $company) }}" class="{{ $card }} space-y-4" x-data="{ stage: '{{ old('stage', $company->stage) }}' }">
                @csrf @method('PUT')
                <h3 class="{{ $h }}">Status</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="{{ $label }}" for="c-stage">Stage</label>
                        <select id="c-stage" name="stage" x-model="stage" class="{{ $field }}">@foreach (\App\Modules\CRM\Models\CrmCompany::STAGES as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select></div>
                    <div><label class="{{ $label }}" for="c-tier">Tier</label>
                        <select id="c-tier" name="tier" class="{{ $field }}">@foreach ([1, 2, 3] as $t)<option value="{{ $t }}" @selected($company->tier == $t)>Tier {{ $t }}</option>@endforeach</select></div>
                </div>
                <div x-show="stage === 'lost'" x-cloak><label class="{{ $label }}" for="c-lost">Why did we lose it?</label><input id="c-lost" name="lost_reason" value="{{ old('lost_reason', $company->lost_reason) }}" class="{{ $field }}"></div>
                <div><label class="{{ $label }}" for="c-owner">Owner</label>
                    <select id="c-owner" name="owner_id" class="{{ $field }}"><option value="">Nobody</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected($company->owner_id == $owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
                <button class="{{ $btn }}">Save status</button>
            </form>

            {{-- Contacts --}}
            <div class="{{ $card }} space-y-4" x-data="{ open: {{ $errors->any() && old('_form') === 'contact' ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between"><h3 class="{{ $h }}">People</h3><button type="button" @click="open = !open" class="text-sm font-medium text-brand-500">+ Add person</button></div>
                @forelse ($company->contacts as $contact)
                    <div class="flex items-start justify-between gap-3 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.03]">
                        <div>
                            <div class="font-medium text-gray-800 dark:text-white/90">{{ $contact->name }} @if ($contact->pivot->is_primary)<span class="text-xs text-gray-500">· main contact</span>@endif</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $contact->pivot->job_title ?? 'Role not recorded' }}@if ($contact->lead_source) · {{ ['website_waitlist' => 'joined the website waitlist', 'website_demo' => 'booked a demo on the website', 'crm_manual' => 'added in the CRM'][$contact->lead_source] ?? $contact->lead_source }}@endif</div>
                        </div>
                        <div class="flex shrink-0 gap-2 text-sm">
                            @if ($contact->phone)<a href="{{ $tel($contact->phone) }}" class="text-gray-600 hover:text-brand-500 dark:text-gray-300" title="Call {{ $contact->phone }}">📞</a><a href="{{ $wa($contact->phone) }}" target="_blank" rel="noopener" class="text-gray-600 hover:text-brand-500 dark:text-gray-300" title="WhatsApp">💬</a>@endif
                            @if ($contact->email)<a href="mailto:{{ $contact->email }}" class="text-gray-600 hover:text-brand-500 dark:text-gray-300" title="{{ $contact->email }}">✉️</a>@endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">No named contact yet. Target role: {{ $company->target_role ?? 'not set' }}.</p>
                @endforelse
                <form x-show="open" x-cloak method="POST" action="{{ route('crm.companies.contacts.store', $company) }}" class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @csrf <input type="hidden" name="_form" value="contact">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $label }}" for="ct-name">Name</label><input id="ct-name" name="name" required value="{{ old('name') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="ct-title">Job title</label><input id="ct-title" name="job_title" value="{{ old('job_title') }}" placeholder="{{ \Illuminate\Support\Str::before((string) $company->target_role, ' /') }}" class="{{ $field }}"></div>
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
                    @foreach (['Portfolio' => $company->portfolio, 'Scale' => $company->scale_signal, 'Opportunity' => $company->opportunity, 'Target role' => $company->target_role, 'Route' => $company->route, 'Units' => $company->estimated_units ? number_format($company->estimated_units) : null, 'Source' => $company->source, 'Notes' => $company->notes] as $term => $value)
                        @if ($value)<dt class="text-gray-500 dark:text-gray-400">{{ $term }}</dt><dd class="whitespace-pre-line text-gray-800 dark:text-white/90">{{ $value }}</dd>@endif
                    @endforeach
                </dl>
                <div x-show="open" x-cloak class="space-y-3">
                    <div><label class="{{ $label }}" for="d-name">Name</label><input id="d-name" name="name" value="{{ $company->name }}" class="{{ $field }}"></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $label }}" for="d-phone">Phone</label><input id="d-phone" name="phone" value="{{ $company->phone }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-phone2">Other phone</label><input id="d-phone2" name="phone_alt" value="{{ $company->phone_alt }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-email">Email</label><input id="d-email" name="email" type="email" value="{{ $company->email }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-web">Website</label><input id="d-web" name="website" value="{{ $company->website }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-type">Business type</label><input id="d-type" name="business_type" value="{{ $company->business_type }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-units">Units</label><input id="d-units" name="estimated_units" type="number" min="0" value="{{ $company->estimated_units }}" class="{{ $field }}"></div>
                    </div>
                    <div><label class="{{ $label }}" for="d-role">Target role</label><input id="d-role" name="target_role" value="{{ $company->target_role }}" class="{{ $field }}"></div>
                    <div><label class="{{ $label }}" for="d-port">Portfolio</label><textarea id="d-port" name="portfolio" rows="2" class="{{ $area }}">{{ $company->portfolio }}</textarea></div>
                    <div><label class="{{ $label }}" for="d-opp">Opportunity</label><textarea id="d-opp" name="opportunity" rows="2" class="{{ $area }}">{{ $company->opportunity }}</textarea></div>
                    <div><label class="{{ $label }}" for="d-notes">Notes</label><textarea id="d-notes" name="notes" rows="3" class="{{ $area }}">{{ $company->notes }}</textarea></div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="hidden" name="needs_verification" value="0"><input type="checkbox" name="needs_verification" value="1" @checked($company->needs_verification)> Details still need verifying</label>
                    <button class="{{ $btn }}">Save details</button>
                </div>
            </form>

            {{-- Demo requests --}}
            @if ($company->demoRequests->isNotEmpty())
                <div class="{{ $card }} space-y-3">
                    <h3 class="{{ $h }}">Demo requests</h3>
                    @foreach ($company->demoRequests as $demo)
                        <div class="rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/[0.03]">
                            <div class="flex justify-between"><span class="font-medium text-gray-800 dark:text-white/90">{{ $demo->user?->name ?? 'Unknown' }}</span><span class="text-xs text-gray-500">{{ \App\Modules\CRM\Models\CrmDemoRequest::STATUSES[$demo->status] ?? $demo->status }}</span></div>
                            <div class="text-gray-600 dark:text-gray-300">{{ $demo->project_type }}@if ($demo->units) · {{ number_format($demo->units) }} units @endif · {{ $demo->country?->name }}</div>
                            @if ($demo->message)<div class="mt-1 text-gray-600 dark:text-gray-300">“{{ $demo->message }}”</div>@endif
                            <div class="mt-1 text-xs text-gray-500">Requested {{ $demo->created_at->format('D j M Y, H:i') }}@if ($demo->scheduled_at) · demo {{ $demo->scheduled_at->format('D j M, H:i') }}@endif · <a href="{{ route('crm.demos.index') }}" class="text-brand-500">manage</a></div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Feedback --}}
            <div class="{{ $card }} space-y-3">
                <h3 class="{{ $h }}">Feedback</h3>
                @if (session('feedback_link'))
                    <div class="rounded-xl bg-green-50 p-3 text-sm text-green-800 dark:bg-green-500/10 dark:text-green-300" x-data="{ copied: false }">
                        Send this private link to the customer:
                        <div class="mt-2 flex gap-2"><input readonly value="{{ session('feedback_link') }}" class="{{ $field }}" x-ref="link"><button type="button" class="{{ $btnGhost }}" @click="navigator.clipboard.writeText($refs.link.value); copied = true" x-text="copied ? 'Copied' : 'Copy'"></button></div>
                    </div>
                @endif
                @foreach ($company->feedback as $fb)
                    <div class="flex items-start justify-between gap-3 rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/[0.03]">
                        <div>
                            <div class="text-gray-800 dark:text-white/90">{{ \App\Modules\CRM\Models\CrmFeedback::CONTEXTS[$fb->context] ?? $fb->context }}@if ($fb->user) · {{ $fb->user->name }}@endif</div>
                            @if ($fb->comment)<div class="text-gray-600 dark:text-gray-300">“{{ $fb->comment }}”</div>@endif
                            <div class="text-xs text-gray-500">{{ $fb->submitted_at ? 'Answered '.$fb->submitted_at->diffForHumans() : 'Sent '.$fb->requested_at->diffForHumans().' · waiting for an answer' }}</div>
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
                    <div class="min-w-[160px] flex-1"><label class="{{ $label }}" for="fb-context">Ask for feedback</label><select id="fb-context" name="context" class="{{ $field }}">@foreach (\App\Modules\CRM\Models\CrmFeedback::CONTEXTS as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select></div>
                    @if ($company->contacts->isNotEmpty())
                        <div class="min-w-[160px] flex-1"><label class="{{ $label }}" for="fb-user">From</label><select id="fb-user" name="user_id" class="{{ $field }}">@foreach ($company->contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach</select></div>
                    @endif
                    <button class="{{ $btnGhost }}">Create link</button>
                </form>
                <p class="text-xs text-gray-500 dark:text-gray-400">A score of 6 or less opens a recovery follow-up for the owner automatically.</p>
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="space-y-6">
            {{-- Follow-ups --}}
            <div class="{{ $card }} space-y-4" x-data="{ adding: {{ $openFollowUps->isEmpty() || ($errors->any() && old('_form') === 'followup') ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between"><h3 class="{{ $h }}">Next steps</h3><button type="button" @click="adding = !adding" class="text-sm font-medium text-brand-500">+ Schedule follow-up</button></div>
                @if ($openFollowUps->isEmpty() && in_array($company->stage, \App\Modules\CRM\Models\CrmCompany::OPEN_STAGES, true))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">No next step is booked. Schedule one so this prospect doesn't go cold.</div>
                @endif
                <form x-show="adding" x-cloak method="POST" action="{{ route('crm.companies.follow-ups.store', $company) }}" class="space-y-3 rounded-xl bg-gray-50 p-4 dark:bg-white/[0.03]">
                    @csrf <input type="hidden" name="_form" value="followup">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_2fr]">
                        <div><label class="{{ $label }}" for="f-type">Type</label><select id="f-type" name="type" class="{{ $field }}">@foreach (\App\Modules\CRM\Models\CrmFollowUp::TYPES as $key => $name)<option value="{{ $key }}" @selected(old('type') === $key)>{{ $name }}</option>@endforeach</select></div>
                        <div><label class="{{ $label }}" for="f-title">What</label><input id="f-title" name="title" required value="{{ old('title', $company->next_action) }}" placeholder="Call the head of property management" class="{{ $field }}"></div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div><label class="{{ $label }}" for="f-due">When</label><input id="f-due" name="due_at" type="datetime-local" required value="{{ old('due_at', now()->addWeekday()->setTime(10, 0)->format('Y-m-d\TH:i')) }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="f-who">Who</label><select id="f-who" name="assigned_to" class="{{ $field }}">@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected(($company->owner_id ?? auth()->id()) == $owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
                        <div><label class="{{ $label }}" for="f-contact">With</label><select id="f-contact" name="user_id" class="{{ $field }}"><option value="">—</option>@foreach ($company->contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach</select></div>
                    </div>
                    <div><label class="{{ $label }}" for="f-notes">Notes</label><textarea id="f-notes" name="notes" rows="2" class="{{ $area }}">{{ old('notes') }}</textarea></div>
                    <button class="{{ $btn }}">Schedule</button>
                </form>
                @foreach ($openFollowUps as $followUp)
                    @include('crm.partials.follow-up-row', ['followUp' => $followUp, 'showCompany' => false])
                @endforeach
                @if ($doneFollowUps->isNotEmpty())
                    <details class="text-sm"><summary class="cursor-pointer text-gray-500 dark:text-gray-400">{{ $doneFollowUps->count() }} completed</summary>
                        <ul class="mt-2 space-y-1">@foreach ($doneFollowUps->sortByDesc('completed_at') as $done)<li class="text-gray-600 dark:text-gray-300">✅ {{ $done->title }} <span class="text-gray-400">— {{ $done->outcome }} · {{ $done->completed_at->format('j M') }}</span></li>@endforeach</ul>
                    </details>
                @endif
            </div>

            {{-- Log activity + timeline --}}
            <div class="{{ $card }} space-y-4">
                <h3 class="{{ $h }}">History</h3>
                <form method="POST" action="{{ route('crm.companies.activities.store', $company) }}" class="space-y-3 rounded-xl bg-gray-50 p-4 dark:bg-white/[0.03]">
                    @csrf
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_2fr]">
                        <div><label class="{{ $label }}" for="a-type">Log a</label><select id="a-type" name="type" class="{{ $field }}">@foreach (\App\Modules\CRM\Models\CrmActivity::MANUAL_TYPES as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select></div>
                        <div><label class="{{ $label }}" for="a-summary">Summary</label><input id="a-summary" name="summary" required placeholder="Spoke to the finance director; wants pricing for 400 units" class="{{ $field }}"></div>
                    </div>
                    <div><label class="{{ $label }}" for="a-body">Details</label><textarea id="a-body" name="body" rows="2" class="{{ $area }}"></textarea></div>
                    <div class="flex flex-wrap items-end gap-3">
                        @if ($company->contacts->isNotEmpty())<div class="min-w-[160px] flex-1"><label class="{{ $label }}" for="a-contact">With</label><select id="a-contact" name="user_id" class="{{ $field }}"><option value="">—</option>@foreach ($company->contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->name }}</option>@endforeach</select></div>@endif
                        <button class="{{ $btn }}">Log it</button>
                    </div>
                </form>
                <ol class="relative space-y-4 border-l border-gray-200 pl-5 dark:border-gray-800">
                    @forelse ($company->activities as $activity)
                        <li>
                            <span class="absolute -left-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs dark:bg-gray-900" aria-hidden="true">{{ $icons[$activity->type] ?? '•' }}</span>
                            <div class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $activity->summary }}</div>
                            @if ($activity->body)<div class="mt-0.5 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $activity->body }}</div>@endif
                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $activity->occurred_at->format('D j M Y, H:i') }}@if ($activity->author) · {{ $activity->author->name }}@endif @if ($activity->contact) · with {{ $activity->contact->name }}@endif</div>
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
