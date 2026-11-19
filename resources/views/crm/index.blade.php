@extends('layouts.app')

@section('content')
@include('partials.card.crm')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $kpis = [
        ['Open prospects', $stats['open'], route('crm.index', ['stage' => 'open']), 'text-gray-800 dark:text-white/90'],
        ['Replies to answer', $stats['replied'], route('crm.index', ['attention' => 'replied']), $stats['replied'] ? 'text-red-600' : 'text-gray-800 dark:text-white/90'],
        ['Overdue follow-ups', $stats['overdue'], route('crm.follow-ups.index', ['view' => 'overdue']), $stats['overdue'] ? 'text-red-600' : 'text-gray-800 dark:text-white/90'],
        ['Due today', $stats['due_today'], route('crm.follow-ups.index', ['view' => 'today']), 'text-gray-800 dark:text-white/90'],
        ['No next step', $stats['unattended'], route('crm.index', ['attention' => 'unattended']), $stats['unattended'] ? 'text-amber-600' : 'text-gray-800 dark:text-white/90'],
        ['New requests', $stats['new_demos'], route('crm.requests.index'), 'text-gray-800 dark:text-white/90'],
        ['On the waitlist', $stats['waitlist'], route('crm.waitlist.index'), 'text-gray-800 dark:text-white/90'],
        ['NPS', $stats['nps'] ?? '–', route('crm.feedback.index'), 'text-gray-800 dark:text-white/90'],
    ];
@endphp
<div x-data="{ adding: {{ $errors->any() && old('_form') === 'add' ? 'true' : 'false' }} }">
    @include('crm.partials.nav', ['title' => 'Sales CRM', 'subtitle' => 'Every prospect, who owns it, and the next step. Nothing open should be without a follow-up.'])

    {{-- Secondary figures as pills (the stat-card row above already shows the headline numbers) --}}
    @include('partials.card.pills', ['pills' => collect($kpis)->map(fn ($k) => [
        'label' => $k[0], 'value' => $k[1], 'url' => $k[2],
        'tone' => str_contains($k[3], 'red') ? 'red' : (str_contains($k[3], 'amber') ? 'amber' : 'gray'),
    ])->all()])

    {{-- Pipeline by stage: clickable pills that filter the table (click again to clear) --}}
    @include('partials.card.pills', ['pills' => collect(\App\Models\Company::CRM_STAGES)->map(fn ($name, $key) => [
        'label' => $name, 'value' => $stageCounts[$key] ?? 0,
        'url' => route('crm.index', array_merge($filters, ['stage' => ($filters['stage'] ?? null) === $key ? null : $key])),
        'tone' => ($filters['stage'] ?? null) === $key ? 'blue' : 'gray',
    ])->values()->all()])

    {{-- Table (TailAdmin, like Tenancies): tabs + filters + search + add on one header row --}}
    @php
        $att = $filters['attention'] ?? null;
        $tabUrl = fn ($key) => route('crm.index', array_filter(array_merge($filters, ['attention' => $key])));
        $tabs = [
            ['label' => 'All', 'url' => $tabUrl(null), 'active' => ! $att, 'count' => $att ? null : $companies->total()],
            ['label' => 'Replies', 'url' => $tabUrl('replied'), 'active' => $att === 'replied', 'count' => $stats['replied'] ?: null, 'tone' => 'red'],
            ['label' => 'Opened', 'url' => $tabUrl('opened'), 'active' => $att === 'opened', 'count' => $att === 'opened' ? $companies->total() : null, 'tone' => 'green'],
            ['label' => 'Overdue', 'url' => $tabUrl('overdue'), 'active' => $att === 'overdue', 'count' => $att === 'overdue' ? $companies->total() : null, 'tone' => 'red'],
            ['label' => 'No next step', 'url' => $tabUrl('unattended'), 'active' => $att === 'unattended', 'count' => $stats['unattended'], 'tone' => 'amber'],
            ['label' => 'To verify', 'url' => $tabUrl('verify'), 'active' => $att === 'verify', 'count' => $att === 'verify' ? $companies->total() : null],
            ['label' => 'Unsent', 'url' => $tabUrl('unsent'), 'active' => $att === 'unsent', 'count' => $att === 'unsent' ? $companies->total() : null, 'tone' => 'amber'],
        ];
        $initials = fn ($n) => collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
        $th = 'px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400';
    @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            @if ($att)<input type="hidden" name="attention" value="{{ $att }}">@endif
            @include('partials.table.tabs', ['tabs' => $tabs])
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                @include('partials.table.select', ['name' => 'stage', 'size' => 'sm', 'aria' => 'Stage', 'min' => 'min-w-[110px]', 'selected' => $filters['stage'] ?? '',
                    'options' => ['' => 'All stages', 'open' => 'Open'] + \App\Models\Company::CRM_STAGES])
                @include('partials.table.select', ['name' => 'tier', 'size' => 'sm', 'aria' => 'Tier', 'min' => 'min-w-[80px]', 'selected' => $filters['tier'] ?? '',
                    'options' => ['' => 'All tiers', 1 => 'Tier 1', 2 => 'Tier 2', 3 => 'Tier 3']])
                @include('partials.table.select', ['name' => 'ptype', 'aria' => 'Property type', 'size' => 'sm', 'min' => 'min-w-[130px]', 'selected' => $filters['ptype'] ?? '',
                    'options' => ['' => 'All property types'] + array_combine(\App\Modules\CRM\Support\PropertyClass::CLASSES, \App\Modules\CRM\Support\PropertyClass::CLASSES) + ['none' => 'Not classified']])
                @include('partials.table.select', ['name' => 'owner', 'size' => 'sm', 'aria' => 'Owner', 'min' => 'min-w-[110px]', 'selected' => $filters['owner'] ?? '',
                    'options' => ['' => 'Any owner', 'none' => 'No owner'] + $owners->pluck('name', 'id')->all()])
                @if (array_filter(\Illuminate\Support\Arr::except($filters, ['attention'])))
                    <a href="{{ route('crm.index', array_filter(['attention' => $att])) }}" class="shadow-theme-xs flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear
                    </a>
                @endif
                @include('partials.table.search', ['size' => 'sm', 'value' => $filters['q'] ?? '', 'min' => 'min-w-[140px]', 'placeholder' => 'Search…'])
                <button type="button" @click="adding = true" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-9 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Prospect
                </button>
            </div>
        </form>

        {{-- Fixed column widths (inline, so no CSS rebuild): the table fits the screen and never scrolls sideways. --}}
        <div class="w-full">
            <table class="data-table w-full" style="table-layout: fixed">
                <colgroup><col style="width: 30%"><col style="width: 12%"><col style="width: 18%"><col style="width: 9%"><col style="width: 12%"><col style="width: 13%"><col style="width: 6%"></colgroup>
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="{{ $th }}">Company</th><th class="{{ $th }}">Property type</th><th class="{{ $th }}">Location</th><th class="{{ $th }}">Stage</th><th class="{{ $th }}">Account manager</th><th class="{{ $th }}">Next step</th><th class="{{ $th }} text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($companies as $company)
                        @php
                            $overdue = $company->next_due_at && \Illuminate\Support\Carbon::parse($company->next_due_at)->isPast();
                            $ptype = \App\Modules\CRM\Support\PropertyClass::of($company);
                            $place = collect([$company->profile('area'), $company->profile('city')])->filter()->unique()->join(', ');
                            // "Wanjiru Kamau" -> "W. Kamau"
                            $words = preg_split('/\s+/', trim((string) $company->owner?->name));
                            $manager = $company->owner ? (count($words) > 1 ? mb_substr($words[0], 0, 1).'. '.end($words) : $words[0]) : null;
                        @endphp
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100"><span class="text-sm font-medium text-purple-600">{{ $initials($company->name) }}</span></div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <a href="{{ route('crm.companies.show', $company) }}" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90" style="overflow-wrap: anywhere">{{ $company->name }}</a>
                                            @include('crm.partials.tier-badge', ['tier' => $company->tier])
                                            @if ($company->profile('reply_pending'))
                                                @php($reply = $company->profile('reply') ?? [])
                                                <a href="{{ route('crm.companies.show', $company) }}#reply" class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 border border-red-200 hover:bg-red-100 dark:text-red-200" title="{{ ($reply['name'] ?? '') ?: ($reply['from'] ?? '') }}: {{ $reply['snippet'] ?? '' }}"><span class="h-2 w-2 animate-pulse rounded-full bg-red-500"></span>{{ ! empty($reply['opt_out']) ? 'asked to stop' : 'replied' }} {{ ! empty($reply['at']) ? \Illuminate\Support\Carbon::parse($reply['at'])->diffForHumans(short: true) : '' }}</a>
                                            @endif
                                            @if ($company->profile('needs_verification'))<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200" title="Some details in the sheet were marked 'Verify'">verify</span>@endif
                                        </div>
                                        @if ($company->profile('reply_pending'))<p class="truncate text-xs text-red-700 dark:text-red-200" title="{{ $company->profile('reply.snippet') }}">“{{ $company->profile('reply.snippet') }}”</p>@endif
                                        <p class="truncate text-xs text-gray-500 dark:text-gray-400" title="{{ collect([$company->profile('business_type'), $contactNames[$company->id] ?? null])->filter()->join(' · ') }}">{{ collect([$company->profile('business_type'), $contactNames[$company->id] ?? null])->filter()->join(' · ') ?: '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if ($ptype)<a href="{{ request()->fullUrlWithQuery(['ptype' => $ptype, 'page' => null]) }}" class="rounded-full bg-gray-100 px-2 py-0.5 font-medium whitespace-nowrap text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300">{{ $ptype }}</a>@else<span class="text-gray-400">-</span>@endif
                                @if ($company->profile('segment'))<p class="mt-1 truncate text-gray-500 dark:text-gray-400" title="{{ $company->profile('segment') }}">{{ $company->profile('segment') }}</p>@endif
                            </td>
                            <td class="px-3 py-3 text-xs">
                                <p class="truncate text-gray-700 dark:text-gray-300" title="{{ $place }}">{{ $place ?: '-' }}</p>
                                <p class="truncate text-gray-500 dark:text-gray-400">@if ($company->profile('sub_county_id'))<a href="{{ request()->fullUrlWithQuery(['sub_county_id' => $company->profile('sub_county_id'), 'page' => null]) }}" class="hover:text-blue-600" title="{{ $company->profile('ward') ? $company->profile('ward').' ward' : '' }}">{{ $company->profile('sub_county') }}</a>@if ($company->profile('county')), @endif @endif @if ($company->profile('county_id'))<a href="{{ request()->fullUrlWithQuery(['county_id' => $company->profile('county_id'), 'page' => null]) }}" class="hover:text-blue-600">{{ $company->profile('county') }}</a>@elseif ($company->profile('county')){{ $company->profile('county') }}@endif</p>
                            </td>
                            <td class="px-3 py-3">@include('crm.partials.stage-badge', ['stage' => $company->crm_stage])@if ($company->lifecycle === 'customer') <span class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">customer</span>@endif</td>
                            <td class="px-3 py-3 text-xs text-gray-600 dark:text-gray-400"><span class="block truncate" title="{{ $company->owner?->name }}">{{ $manager ?? '-' }}</span></td>
                            <td class="px-3 py-3 text-xs">
                                @if ($company->next_due_at)
                                    <span class="font-medium {{ $overdue ? 'text-red-600' : 'text-gray-700 dark:text-gray-300' }}">{{ \Illuminate\Support\Carbon::parse($company->next_due_at)->format('D j M, H:i') }}@if ($overdue) · overdue @endif</span>
                                @elseif (in_array($company->crm_stage, \App\Models\Company::OPEN_CRM_STAGES, true))
                                    <span class="font-medium text-amber-600" title="{{ $company->profile('next_action') ? 'Sheet: '.$company->profile('next_action') : '' }}">No follow-up</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                                <p class="truncate text-gray-400">Last: {{ $company->last_contact_at ? \Illuminate\Support\Carbon::parse($company->last_contact_at)->diffForHumans(short: true) : 'never' }}</p>
                            </td>
                            <td class="px-1 py-3 whitespace-nowrap">
                                <div class="flex items-center justify-end gap-0.5">
                                    <button type="button" @click="$dispatch('open-playbook', { url: '{{ route('crm.companies.playbook', $company) }}' })" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-brand-500 hover:bg-gray-100 hover:text-brand-600 dark:hover:bg-white/5" title="Playbook: approach, what to sell, cold email, verify" aria-label="Open playbook for {{ $company->name }}"><x-icon name="sparkles" class="h-4 w-4" /></button>
                                    <div x-data="{ open: false, top: 0, right: 0 }" class="relative">
                                        <button type="button" @click="const r = $el.getBoundingClientRect(); top = r.bottom + 4; right = window.innerWidth - r.right; open = !open" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white" aria-label="Actions for {{ $company->name }}" :aria-expanded="open"><x-icon name="ellipsis-horizontal" class="h-4 w-4" /></button>
                                        <div x-show="open" x-cloak @click.outside="open = false" @keydown.escape.window="open = false" @scroll.window="open = false" :style="`top:${top}px; right:${right}px`" class="fixed z-50 w-56 rounded-xl border border-gray-200 bg-white py-1 text-sm shadow-lg dark:border-gray-800 dark:bg-gray-900">
                                            <a href="{{ route('crm.companies.show', $company) }}" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"><x-icon name="eye" class="h-4 w-4" /> View</a>
                                            <button type="button" @click="open = false; $dispatch('assign-manager', { url: '{{ route('crm.companies.update', $company) }}', name: @js($company->name), owner: '{{ $company->owner_id }}' })" class="flex w-full items-center gap-2 px-3 py-2 text-left text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"><x-icon name="user-circle" class="h-4 w-4" /> Assign account manager</button>
                                            <a href="{{ route('crm.companies.show', $company) }}?edit=1#details" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"><x-icon name="pencil-square" class="h-4 w-4" /> Edit</a>
                                            @if ($company->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $company->phone) }}" class="flex items-center gap-2 px-3 py-2 text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"><x-icon name="phone" class="h-4 w-4" /> Call {{ $company->phone }}</a>@endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No prospects found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters, or add a prospect.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 pt-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
            <label for="per-page">Rows per page</label>
            <select id="per-page" onchange="const u = new URL(location.href); u.searchParams.set('per_page', this.value); u.searchParams.delete('page'); location.href = u" class="h-9 w-auto rounded-lg border border-gray-300 bg-transparent px-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                @foreach ([25, 50, 100, 250, 500, 1000] as $n)<option value="{{ $n }}" @selected($companies->perPage() === $n)>{{ $n }}</option>@endforeach
            </select>
        </div>
        @include('partials.table.pager', ['paginator' => $companies])
    </div>

    {{-- Add prospect --}}
    {{-- Add a prospect: right-side slide-over --}}
    <div x-show="adding" x-cloak x-transition:leave="duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-100" class="fixed inset-0 z-99999" role="dialog" aria-modal="true" aria-labelledby="add-prospect-title" @keydown.escape.window="adding = false">
        <div x-show="adding" x-transition.opacity.duration.200ms class="absolute inset-0 modal-overlay" @click="adding = false"></div>
        <form method="POST" action="{{ route('crm.companies.store') }}" x-show="adding"
              x-transition:enter="transform transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
              x-transition:leave="transform transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
              class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col bg-white shadow-2xl dark:bg-gray-900">
            @csrf
            <input type="hidden" name="_form" value="add">
            <div class="flex items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-800">
                <div>
                    <h3 id="add-prospect-title" class="text-lg font-semibold text-gray-800 dark:text-white/90">Add a prospect</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">If the company is already in the system (same website or name, prospect or customer), we open it instead of creating a copy.</p>
                </div>
                <button type="button" @click="adding = false" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/5" aria-label="Close"><x-icon name="x-mark" class="h-5 w-5" /></button>
            </div>
            <div class="flex-1 space-y-4 overflow-y-auto p-5">
            <div><label class="{{ $label }}" for="p-name">Company name</label><input id="p-name" name="name" required value="{{ old('name') }}" class="{{ $field }}"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="p-tier">Tier</label><select id="p-tier" name="tier" class="{{ $field }}">@foreach ([1, 2, 3] as $t)<option value="{{ $t }}" @selected(old('tier', 2) == $t)>Tier {{ $t }}</option>@endforeach</select></div>
                <div><label class="{{ $label }}" for="p-owner">Owner</label><select id="p-owner" name="owner_id" class="{{ $field }}"><option value="">Me</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
            </div>
            <div><label class="{{ $label }}" for="p-website">Website</label><input id="p-website" name="website" value="{{ old('website') }}" placeholder="example.co.ke" class="{{ $field }}"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="p-phone">Phone</label><input id="p-phone" name="phone" value="{{ old('phone') }}" class="{{ $field }}"></div>
                <div><label class="{{ $label }}" for="p-email">Email</label><input id="p-email" name="email" type="email" value="{{ old('email') }}" class="{{ $field }}"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="p-type">Business type</label><input id="p-type" name="business_type" value="{{ old('business_type') }}" placeholder="Developer, property manager…" class="{{ $field }}"></div>
                <div><label class="{{ $label }}" for="p-country">Country</label><select id="p-country" name="country_code" class="{{ $field }}">@foreach (\App\Modules\CRM\Support\Countries::ordered() as $code => $c)<option value="{{ $code }}" @selected(old('country_code', 'KE') === $code)>{{ $c['name'] }}</option>@endforeach</select></div>
            </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">With a business email, the prospect gets its property type's cold email in the next weekday-morning campaign.</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 p-5 dark:border-gray-800">
                <button type="button" @click="adding = false" class="h-11 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
                <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">Add prospect</button>
            </div>
        </form>
    </div>
</div>
{{-- Assign account manager (one dialog, filled by the row's Actions menu) --}}
<div x-data="{ show: false, url: '', name: '', owner: '' }" @assign-manager.window="url = $event.detail.url; name = $event.detail.name; owner = $event.detail.owner; show = true" x-show="show" x-cloak class="fixed inset-0 z-99999 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="assign-title" @keydown.escape.window="show = false">
    <div class="absolute inset-0 modal-overlay" @click="show = false"></div>
    <form method="POST" :action="url" class="relative w-full max-w-md space-y-4 rounded-2xl bg-white p-5 shadow-2xl dark:bg-gray-900">
        @csrf @method('PUT')
        <div>
            <h3 id="assign-title" class="text-lg font-semibold text-gray-800 dark:text-white/90">Assign account manager</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400" x-text="name"></p>
        </div>
        <select name="owner_id" x-model="owner" class="{{ $field }}" aria-label="Account manager">
            <option value="">Nobody</option>
            @foreach ($owners as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach
        </select>
        <p class="text-xs text-gray-500 dark:text-gray-400">Open follow-ups move to the new account manager.</p>
        <div class="flex justify-end gap-2">
            <button type="button" @click="show = false" class="h-10 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Assign</button>
        </div>
    </form>
</div>
@include('crm.partials.playbook-modal')
@endsection
