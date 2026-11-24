@extends('layouts.app')

@section('content')
@php
    use App\Models\Company;
    use App\Modules\CRM\Models\CrmFollowUp;
    use App\Modules\CRM\Support\Countries;

    // Layout "A · Command centre" (24 Nov 2026): header with one-click contact and the stage as steps; the work on
    // the left (do next, coming up, activity); the facts on the right, each in one place.
    $card = 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $area = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $btn = 'inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600';
    $btnGhost = 'inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
    $action = 'inline-flex h-11 items-center gap-2 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
    $h = 'text-base font-semibold text-gray-800 dark:text-white/90';
    $icons = ['note' => 'pencil-square', 'call' => 'phone', 'email' => 'envelope', 'whatsapp' => 'chat-bubble-left-ellipsis', 'meeting' => 'user-group', 'demo' => 'calendar-days', 'stage' => 'arrow-right', 'feedback' => 'star', 'import' => 'arrow-down-tray', 'contact' => 'user'];
    $tel = fn ($p) => 'tel:'.preg_replace('/[^\d+]/', '', (string) $p);
    $wa = fn ($p) => 'https://wa.me/'.preg_replace('/\D/', '', (string) $p);
    $sources = ['website_waitlist' => 'joined the website waitlist', 'website_demo' => 'booked a demo on the website', 'crm_manual' => 'added in the CRM'];
    $contactPeople = $contacts->map(fn ($r) => $r->contact)->filter();
    $website = $company->profile('website');

    // Who to reach first: the main contact, else the company's own lines.
    $main = $contactPeople->first();
    $callTo = $main?->phone ?: $company->phone;
    $mailTo = $main?->email ?: $company->email;

    // The stage as steps along the open path; lost / nurture show as a flag instead.
    $path = ['new', 'contacted', 'qualified', 'demo_booked', 'proposal', 'negotiation', 'won'];
    $at = array_search($company->crm_stage, $path, true);

    // Units and the monthly fee: the fee typed on the deal, else units × their price (KES 40 when none is set).
    $units = (int) ($company->profile('estimated_units') ?: 0);
    $dealValue = (int) preg_replace('/\D/', '', (string) $company->profile('deal_value'));
    $rate = (float) ($company->price_per_unit ?? 40) ?: 40;
    $fee = $dealValue ?: ($units ? (int) round($units * $rate) : null);
    $listed = collect($company->profile('prospect_properties') ?? [])->filter(fn ($p) => is_array($p));

    $doNext = $todo->first();
    $comingUp = $todo->slice(1);
    $kindOf = fn ($row) => match ($row->type) { 'email', 'whatsapp' => 'messages', 'call', 'note', 'meeting' => 'calls', default => 'other' };
@endphp
<div>
    @include('crm.partials.nav', ['title' => $company->name, 'subtitle' => collect([$company->profile('business_type') ?? 'Prospect', Countries::name($company->country_code)])->filter()->join(' · ')])

    {{-- Header: where they stand and one-click contact --}}
    <section class="{{ $card }} mb-6 space-y-5" aria-label="Summary">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <div aria-hidden="true" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-lg font-bold text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">{{ \Illuminate\Support\Str::of($company->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</div>
                <div class="min-w-0 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        @include('crm.partials.tier-badge', ['tier' => $company->tier ?? 3])
                        @include('crm.partials.stage-badge', ['stage' => $company->crm_stage])
                        <span @class(['rounded-full px-2.5 py-0.5 text-xs font-medium', 'bg-green-50 text-green-700' => $company->lifecycle === 'customer', 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300' => $company->lifecycle !== 'customer'])>{{ ucfirst($company->lifecycle) }}</span>
                        @if ($company->profile('needs_verification'))<span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Details to verify</span>@endif
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ collect([$company->profile('business_type'), collect([$company->profile('area'), $company->profile('city')])->filter()->join(', ') ?: null, $units ? number_format($units).' units' : null])->filter()->join(' · ') }}@if ($company->owner) · Owner <span class="font-medium text-gray-800 dark:text-white/90">{{ $company->owner->name }}</span>@else · <span class="font-medium text-amber-700">No owner yet</span>@endif</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($callTo)<a href="{{ $tel($callTo) }}" class="{{ $action }}" title="Call {{ $callTo }}"><x-icon name="phone" class="h-4 w-4" /> Call</a>
                    <a href="{{ $wa($callTo) }}" target="_blank" rel="noopener" class="{{ $action }}"><x-icon name="chat-bubble-left-ellipsis" class="h-4 w-4" /> WhatsApp</a>@endif
                @if ($mailTo)<a href="mailto:{{ $mailTo }}" class="{{ $action }}" title="{{ $mailTo }}"><x-icon name="envelope" class="h-4 w-4" /> Email</a>@endif
                <button type="button" x-data x-init="if (new URLSearchParams(location.search).has('playbook')) $nextTick(() => $dispatch('open-playbook', { url: '{{ route('crm.companies.playbook', $company) }}' }))" @click="$dispatch('open-playbook', { url: '{{ route('crm.companies.playbook', $company) }}' })" class="inline-flex h-11 items-center gap-2 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600"><x-icon name="light-bulb" class="h-4 w-4" /> Playbook &amp; email</button>
            </div>
        </div>

        @if (in_array($company->crm_stage, ['lost', 'nurture'], true))
            <p class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:bg-white/[0.03] dark:text-gray-300">{{ $company->crm_stage === 'lost' ? 'Lost'.($company->profile('lost_reason') ? ': '.$company->profile('lost_reason') : '') : 'Nurture: kept warm for later' }}</p>
        @else
            <ol aria-label="Stage" class="flex flex-wrap gap-1.5 text-xs font-medium">
                @foreach ($path as $i => $key)
                    <li @if ($i === $at) aria-current="step" @endif @class([
                        'flex-1 basis-24 whitespace-nowrap rounded-lg px-2.5 py-2',
                        'bg-brand-500 text-white' => $i === $at,
                        'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' => $at !== false && $i < $at,
                        'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => $at === false || $i > $at,
                    ])>{{ Company::CRM_STAGES[$key] }}</li>
                @endforeach
            </ol>
        @endif
    </section>

    @if ($company->profile('reply_pending'))@include('crm.partials.reply-alert')@endif

    <div class="flex flex-wrap items-start gap-6">
        {{-- LEFT: what to do, then what happened --}}
        <div class="min-w-0 space-y-6" style="flex: 999 1 560px">
            {{-- Do next --}}
            <section class="{{ $card }} space-y-3" aria-labelledby="do-next">
                <h2 id="do-next" @class(['text-xs font-semibold uppercase tracking-wider', 'text-red-700 dark:text-red-400' => $doNext?->is_overdue, 'text-gray-500 dark:text-gray-400' => ! $doNext?->is_overdue])>Do next{{ $doNext?->is_overdue ? ' · overdue' : '' }}</h2>
                @if ($doNext)
                    @include('crm.partials.follow-up-row', ['followUp' => $doNext, 'showCompany' => false])
                @elseif (in_array($company->crm_stage, Company::OPEN_CRM_STAGES, true))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">No next step is booked. Schedule one below so this prospect doesn't go cold.</div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nothing to do right now.</p>
                @endif
            </section>

            @if ($comingUp->isNotEmpty())
                <section class="{{ $card }} space-y-3" aria-labelledby="coming-up">
                    <h2 id="coming-up" class="{{ $h }}">Coming up <span class="text-sm font-normal text-gray-500">· {{ $comingUp->count() }}</span></h2>
                    @foreach ($comingUp as $followUp)
                        @include('crm.partials.follow-up-row', ['followUp' => $followUp, 'showCompany' => false])
                    @endforeach
                </section>
            @endif

            {{-- Activity: log or schedule, then the history --}}
            <section class="{{ $card }} space-y-4" aria-labelledby="activity" x-data="{ mode: '{{ old('mode', $todo->isEmpty() ? 'schedule' : 'log') }}', show: 'all' }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="activity" class="{{ $h }}">Activity <span class="text-sm font-normal text-gray-500">· {{ $history->count() }}</span></h2>
                    <div class="flex rounded-lg bg-gray-100 p-1 text-sm dark:bg-gray-900" role="group" aria-label="Show">
                        @foreach (['all' => 'All', 'messages' => 'Emails', 'calls' => 'Calls & notes'] as $k => $l)
                            <button type="button" @click="show = '{{ $k }}'" :class="show === '{{ $k }}' ? 'bg-white shadow-theme-xs dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500'" class="rounded-md px-3 py-1 font-medium">{{ $l }}</button>
                        @endforeach
                    </div>
                </div>
                <form method="POST" action="{{ route('crm.companies.follow-ups.store', $company) }}" class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    @csrf <input type="hidden" name="mode" :value="mode">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm text-gray-600 dark:text-gray-400" x-text="mode === 'log' ? 'Log a call, note or meeting' : 'Schedule the next step'"></span>
                        <div class="flex rounded-lg bg-gray-100 p-1 text-xs dark:bg-gray-900">
                            <button type="button" @click="mode = 'log'" :class="mode === 'log' ? 'bg-white shadow-theme-xs dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500'" class="rounded-md px-2.5 py-1 font-medium">Log what happened</button>
                            <button type="button" @click="mode = 'schedule'" :class="mode === 'schedule' ? 'bg-white shadow-theme-xs dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500'" class="rounded-md px-2.5 py-1 font-medium">Schedule next step</button>
                        </div>
                    </div>
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
                    <div class="flex justify-end"><button class="{{ $btn }}" x-text="mode === 'log' ? 'Log it' : 'Schedule'"></button></div>
                </form>
                <ol class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($history as $row)
                        @if ($row->type === 'email')
                            <li class="py-2" x-show="show === 'all' || show === 'messages'">@include('crm.partials.email-entry', ['row' => $row, 'company' => $company])</li>
                            @continue
                        @endif
                        <li class="grid grid-cols-[88px_minmax(0,1fr)] gap-4 py-3.5" x-show="show === 'all' || show === '{{ $kindOf($row) }}'">
                            <div class="text-xs leading-5 text-gray-500 dark:text-gray-400">{{ ($row->done_at ?? $row->created_at)->format('j M') }}<br>{{ ($row->done_at ?? $row->created_at)->format('H:i') }}</div>
                            <div class="min-w-0">
                                <div class="flex items-start gap-2 text-sm font-medium text-gray-800 dark:text-white/90">
                                    <x-icon :name="$icons[$row->type] ?? 'information-circle'" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                    <span class="min-w-0">{{ $row->title }}
                                        @if (in_array($row->status, ['missed', 'cancelled'], true))<span class="ml-1 text-xs font-normal text-red-600">({{ $row->status }})</span>@endif
                                        @if ($row->type === 'email' && $row->detail('track'))@php($es = \App\Modules\CRM\Support\EmailTracking::status($row))<span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Modules\CRM\Support\EmailTracking::TONES[$es] }}" title="{{ $row->detail('delivery_reply') }}">{{ \App\Modules\CRM\Support\EmailTracking::LABELS[$es] }}</span>@endif
                                    </span>
                                </div>
                                @if ($row->type === 'email' && $row->detail('track'))
                                    @include('crm.partials.engagement', ['e' => \App\Modules\CRM\Support\EmailEngagement::summary($row->id), 'events' => \App\Modules\CRM\Support\EmailEngagement::events($row->id)])
                                @endif
                                @if ($row->detail('file'))<a href="{{ route('crm.follow-ups.file', $row) }}" data-no-spa class="mt-1 inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"><x-icon name="document-text" class="h-3.5 w-3.5" /> {{ $row->detail('file_name') ?: 'Uploaded file' }}</a>@endif
                                @if ($row->detail('pdf'))<a href="{{ route('crm.follow-ups.pdf', $row) }}" target="_blank" rel="noopener" data-no-spa x-data @click="if ($event.metaKey || $event.ctrlKey || $event.shiftKey) return; $event.preventDefault(); $dispatch('open-pdf', { url: $el.href, title: $el.textContent.trim() })" class="mt-1 inline-flex items-center gap-1 rounded-md border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"><x-icon name="document-text" class="h-3.5 w-3.5" /> {{ $row->type === 'demo' && ! $row->detail('form') ? 'Demo request' : ($row->detail('form.name') ?? 'Submission') }} PDF{{ $row->detail('ref') ? ' · '.$row->detail('ref') : '' }}</a>@endif
                                @if ($row->outcome && $row->type !== 'feedback')<div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">→ {{ $row->outcome }}</div>@endif
                                @if ($row->notes)<div class="mt-0.5 line-clamp-4 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300" x-data="{ more: false }" :class="more && '!line-clamp-none'" @click="more = true">{{ $row->notes }}</div>@endif
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ ($row->done_at ?? $row->created_at)->format('D j M Y') }}@if ($row->author) · {{ $row->author->name }}@endif @if ($row->contact && $row->type !== 'contact') · with {{ $row->contact->name }}@endif</div>
                            </div>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-gray-500">Nothing yet.</li>
                    @endforelse
                </ol>
            </section>
        </div>

        {{-- RIGHT: the facts, each in one place --}}
        <aside class="min-w-0 space-y-6" style="flex: 1 1 340px">
            {{-- Deal: the numbers, and stage / tier / owner --}}
            <form method="POST" action="{{ route('crm.companies.update', $company) }}" class="{{ $card }} space-y-4" x-data="{ stage: '{{ old('crm_stage', $company->crm_stage) }}', edit: false }" aria-labelledby="deal">
                @csrf @method('PUT')
                <div class="flex items-center justify-between"><h2 id="deal" class="{{ $h }}">Deal</h2><button type="button" @click="edit = !edit" class="text-sm font-medium text-brand-500" x-text="edit ? 'Close' : 'Change stage, tier or owner'"></button></div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div><dt class="text-gray-500 dark:text-gray-400">Units</dt><dd class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $units ? number_format($units) : '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Fee a month</dt><dd class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $fee ? 'KES '.number_format($fee) : '—' }}</dd>@if ($fee && ! $dealValue)<dd class="text-xs text-gray-500">est. at KES {{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }} a unit</dd>@endif</div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Stage</dt><dd class="font-medium text-gray-800 dark:text-white/90">{{ Company::CRM_STAGES[$company->crm_stage] ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Tier</dt><dd class="font-medium text-gray-800 dark:text-white/90">Tier {{ $company->tier ?? 3 }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Owner</dt><dd class="font-medium text-gray-800 dark:text-white/90">{{ $company->owner?->name ?? 'Nobody' }}</dd></div>
                    <div><dt class="text-gray-500 dark:text-gray-400">Expected close</dt><dd class="font-medium text-gray-800 dark:text-white/90">{{ $company->profile('expected_close') ? \Illuminate\Support\Carbon::parse($company->profile('expected_close'))->format('j M Y') : '—' }}</dd></div>
                </dl>
                <div x-show="edit" x-cloak class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-800">
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
                    <button class="{{ $btn }}">Save</button>
                </div>
            </form>

            {{-- People --}}
            <section class="{{ $card }} space-y-3" x-data="{ open: {{ $errors->any() && old('_form') === 'contact' ? 'true' : 'false' }} }" aria-labelledby="people">
                <div class="flex items-center justify-between"><h2 id="people" class="{{ $h }}">People</h2><button type="button" @click="open = !open" class="text-sm font-medium text-brand-500">+ Add person</button></div>
                @forelse ($contacts as $row)
                    @php($person = $row->contact)
                    @continue(! $person)
                    <div class="flex items-start gap-3">
                        <div aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ \Illuminate\Support\Str::of($person->name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</div>
                        <div class="min-w-0 flex-1 text-sm">
                            <div class="font-medium text-gray-800 dark:text-white/90">{{ $person->name }} @if ($row->detail('primary'))<span class="text-xs font-normal text-gray-500">· main contact</span>@endif</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row->detail('job_title') ?? 'Role not recorded' }}@if ($person->lead_source) · {{ $sources[$person->lead_source] ?? $person->lead_source }}@endif</div>
                            @if ($person->phone)<div class="mt-1 flex flex-wrap gap-x-3 text-gray-700 dark:text-gray-300"><a href="{{ $tel($person->phone) }}" class="hover:text-brand-500">{{ $person->phone }}</a><a href="{{ $wa($person->phone) }}" target="_blank" rel="noopener" class="text-brand-500">WhatsApp</a></div>@endif
                            @if ($person->email)<a href="mailto:{{ $person->email }}" class="block truncate text-brand-500">{{ $person->email }}</a>@endif
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
            </section>

            {{-- Properties they listed --}}
            @if ($listed->isNotEmpty())
                <section class="{{ $card }} space-y-3" aria-labelledby="properties">
                    <div class="flex items-center justify-between"><h2 id="properties" class="{{ $h }}">Properties</h2><a href="{{ route('crm.properties.index', ['status' => 'all']) }}" class="text-sm font-medium text-brand-500">Open properties</a></div>
                    @foreach ($listed as $p)
                        <div class="rounded-xl border border-gray-200 px-3.5 py-3 text-sm dark:border-gray-800">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $p['name'] ?? 'Property' }}</span>
                                <span class="shrink-0 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-500/15 dark:text-green-300">{{ \App\Modules\CRM\Services\DemoRequestService::PROPERTY_STATUSES[$p['status'] ?? 'open'] ?? 'Open' }}</span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ collect([$p['type'] ?? null, $p['location'] ?? null, ! empty($p['units']) ? number_format((int) $p['units']).' units' : null])->filter()->join(' · ') }}</div>
                            @if (! empty($p['sub_county']) || ! empty($p['county']))<div class="text-xs text-gray-400">{{ collect([! empty($p['sub_county']) ? $p['sub_county'].' sub-county' : null, ! empty($p['county']) ? $p['county'].' County' : null])->filter()->join(', ') }}{{ ($p['geo_precision'] ?? null) === 'exact' ? '' : ' (approx.)' }}</div>@endif
                            <a href="{{ \App\Modules\CRM\Support\KenyaGeo::googleMapsUrl($p, $company->profile('city')) }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs font-medium text-brand-500">Google Maps ↗</a>
                        </div>
                    @endforeach
                </section>
            @endif

            {{-- The company's own lines --}}
            @if ($company->phone || $company->profile('phone_alt') || $company->email || $website)
                <section class="{{ $card }} space-y-2 text-sm" aria-labelledby="company-contact">
                    <h2 id="company-contact" class="{{ $h }}">Company contact</h2>
                    @if ($company->phone)<div><a href="{{ $tel($company->phone) }}" class="text-gray-700 hover:text-brand-500 dark:text-gray-300">{{ $company->phone }}</a>{!! \App\Helpers\PhoneHelper::isForeign($company->phone) ? '<span class="ml-1 rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700">'.e(\App\Helpers\PhoneHelper::foreignCountry($company->phone)).'</span>' : '' !!}</div>@endif
                    @if ($company->profile('phone_alt'))<div><a href="{{ $tel($company->profile('phone_alt')) }}" class="text-gray-700 hover:text-brand-500 dark:text-gray-300">{{ $company->profile('phone_alt') }}</a></div>@endif
                    @if ($company->email)<div><a href="mailto:{{ $company->email }}" class="break-all text-brand-500">{{ $company->email }}</a></div>@endif
                    @if ($website)<div><a href="{{ $website }}" target="_blank" rel="noopener" class="break-all text-brand-500">{{ $company->domain ?? $website }}</a></div>@endif
                </section>
            @endif

            {{-- Details: folded until needed --}}
            @php($detailCount = collect(['deal_value', 'expected_close', 'segment', 'property_class', 'property_mix', 'requester_role', 'clientele', 'portfolio', 'scale_signal', 'opportunity', 'target_role', 'route', 'estimated_units', 'next_action', 'notes'])->filter(fn ($k) => $company->profile($k))->count())
            <form id="details" method="POST" action="{{ route('crm.companies.update', $company) }}" class="{{ $card }} space-y-3" x-data="{ view: new URLSearchParams(location.search).has('edit') ? 'edit' : 'closed' }">
                @csrf @method('PUT')
                <div class="flex items-center justify-between gap-2">
                    <button type="button" @click="view = view === 'closed' ? 'read' : 'closed'" class="flex items-center gap-2 text-left" :aria-expanded="view !== 'closed'"><h2 class="{{ $h }}">Company details</h2><span class="text-sm text-gray-500">· {{ $detailCount }}</span><x-icon name="chevron-down" class="h-4 w-4 text-gray-400 transition" ::class="view !== 'closed' && 'rotate-180'" /></button>
                    <button type="button" @click="view = view === 'edit' ? 'read' : 'edit'" class="text-sm font-medium text-brand-500" x-text="view === 'edit' ? 'Done' : 'Edit'"></button>
                </div>
                <dl x-show="view === 'read'" x-cloak class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-[130px_1fr]">
                    @foreach (['Expected fee (KES/mo)' => 'deal_value', 'Expected close' => 'expected_close', 'Segment' => 'segment', 'Property type' => 'property_class', 'Property mix' => 'property_mix', 'Requested by' => 'requester_role', 'Location' => 'location_label', 'Clientele' => 'clientele', 'Portfolio' => 'portfolio', 'Scale' => 'scale_signal', 'Opportunity' => 'opportunity', 'Target role' => 'target_role', 'Route' => 'route', 'Units' => 'estimated_units', 'Sheet next step' => 'next_action', 'Notes' => 'notes'] as $term => $key)
                        @php($value = $key === 'location_label' ? collect([$company->profile('area'), $company->profile('city')])->filter()->join(', ') : $company->profile($key))
                        @if ($value && $key === 'deal_value')<dt class="text-gray-500 dark:text-gray-400">{{ $term }}</dt><dd class="font-medium text-gray-800 dark:text-white/90">{{ number_format((int) preg_replace('/\D/', '', $value)) }}</dd>
                        @elseif ($value && $key === 'location_label')<dt class="text-gray-500 dark:text-gray-400">{{ $term }}</dt><dd class="text-gray-800 dark:text-white/90">{{ $value }}</dd>
                        @elseif ($company->profile($key))<dt class="text-gray-500 dark:text-gray-400">{{ $term }}</dt><dd class="whitespace-pre-line text-gray-800 dark:text-white/90">{{ $key === 'estimated_units' ? number_format((int) $company->profile($key)) : $company->profile($key) }}</dd>@endif
                    @endforeach
                    @if ($company->source)<dt class="text-gray-500 dark:text-gray-400">Source</dt><dd class="text-gray-800 dark:text-white/90">{{ $company->source }}</dd>@endif
                </dl>
                <div x-show="view === 'edit'" x-cloak class="space-y-3">
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
                        <div><label class="{{ $label }}" for="d-value">Expected per-unit fees (KES / month)</label><input id="d-value" name="profile[deal_value]" inputmode="numeric" value="{{ $company->profile('deal_value') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-close">Expected close</label><input id="d-close" name="profile[expected_close]" type="date" value="{{ $company->profile('expected_close') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-seg">Segment</label><input id="d-seg" name="profile[segment]" value="{{ $company->profile('segment') }}" placeholder="Office / Commercial, Serviced Apartments…" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-client">Clientele (as the firm states it)</label><input id="d-client" name="profile[clientele]" value="{{ $company->profile('clientele') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-city">City</label><input id="d-city" name="profile[city]" value="{{ $company->profile('city') }}" class="{{ $field }}"></div>
                        <div><label class="{{ $label }}" for="d-area">Area</label><input id="d-area" name="profile[area]" value="{{ $company->profile('area') }}" class="{{ $field }}"></div>
                    </div>
                    <div><label class="{{ $label }}" for="d-port">Portfolio</label><textarea id="d-port" name="profile[portfolio]" rows="2" class="{{ $area }}">{{ $company->profile('portfolio') }}</textarea></div>
                    <div><label class="{{ $label }}" for="d-opp">Opportunity</label><textarea id="d-opp" name="profile[opportunity]" rows="2" class="{{ $area }}">{{ $company->profile('opportunity') }}</textarea></div>
                    <div><label class="{{ $label }}" for="d-notes">Notes</label><textarea id="d-notes" name="profile[notes]" rows="3" class="{{ $area }}">{{ $company->profile('notes') }}</textarea></div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="profile[needs_verification]" value="1" @checked($company->profile('needs_verification'))> Details still need verifying</label>
                    <button class="{{ $btn }}">Save details</button>
                </div>
            </form>

            {{-- Feedback: folded unless there is some, or a link was just made --}}
            <section class="{{ $card }} space-y-3" x-data="{ open: {{ $feedback->isNotEmpty() || session('feedback_link') ? 'true' : 'false' }} }" aria-labelledby="feedback-h">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left" :aria-expanded="open"><h2 id="feedback-h" class="{{ $h }}">Feedback @if ($feedback->isNotEmpty())<span class="text-sm font-normal text-gray-500">· {{ $feedback->count() }}</span>@endif</h2><x-icon name="chevron-down" class="h-4 w-4 text-gray-400 transition" ::class="open && 'rotate-180'" /></button>
                <div x-show="open" x-cloak class="space-y-3">
                    @if (session('feedback_link'))
                        <div class="rounded-xl bg-green-50 p-3 text-sm text-green-800 dark:bg-green-500/10 dark:text-green-300" x-data="{ copied: false }">
                            Send this private link to the customer:
                            <div class="mt-2 flex gap-2"><input readonly value="{{ session('feedback_link') }}" class="{{ $field }}" x-ref="link" aria-label="Feedback link"><button type="button" class="{{ $btnGhost }}" @click="navigator.clipboard.writeText($refs.link.value); copied = true" x-text="copied ? 'Copied' : 'Copy'"></button></div>
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
                        <div class="min-w-[140px] flex-1"><label class="{{ $label }}" for="fb-context">Ask for feedback</label><select id="fb-context" name="context" class="{{ $field }}">@foreach (CrmFollowUp::FEEDBACK_CONTEXTS as $key => $name)<option value="{{ $key }}">{{ $name }}</option>@endforeach</select></div>
                        @if ($contactPeople->isNotEmpty())
                            <div class="min-w-[140px] flex-1"><label class="{{ $label }}" for="fb-user">From</label><select id="fb-user" name="user_id" class="{{ $field }}">@foreach ($contactPeople as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
                        @endif
                        <button class="{{ $btnGhost }}">Create link</button>
                    </form>
                    <p class="text-xs text-gray-500 dark:text-gray-400">A score of 6 or less opens a recovery call for the owner automatically.</p>
                </div>
            </section>
        </aside>
    </div>
</div>
@include('crm.partials.playbook-modal')
@endsection
