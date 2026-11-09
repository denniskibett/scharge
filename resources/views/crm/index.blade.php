@extends('layouts.app')

@section('content')
@include('partials.card.crm')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $kpis = [
        ['Open prospects', $stats['open'], route('crm.index', ['stage' => 'open']), 'text-gray-800 dark:text-white/90'],
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

    {{-- KPIs --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
        @foreach ($kpis as [$name, $value, $link, $colour])
            <a href="{{ $link }}" class="{{ $card }} block p-4 transition hover:border-gray-300 dark:hover:border-gray-700">
                <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ $name }}</span>
                <span class="mt-1 block text-2xl font-semibold {{ $colour }}">{{ $value }}</span>
            </a>
        @endforeach
    </div>

    {{-- Pipeline by stage --}}
    <div class="{{ $card }} mb-6 overflow-x-auto p-2">
        <div class="flex min-w-max gap-1">
            @foreach (\App\Models\Company::CRM_STAGES as $key => $name)
                <a href="{{ route('crm.index', array_merge($filters, ['stage' => ($filters['stage'] ?? null) === $key ? null : $key])) }}" @class([
                    'flex min-w-[110px] flex-1 flex-col rounded-xl px-3 py-2 transition',
                    'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => ($filters['stage'] ?? null) === $key,
                    'hover:bg-gray-50 dark:hover:bg-white/5' => ($filters['stage'] ?? null) !== $key,
                ])>
                    <span class="text-xs opacity-70">{{ $name }}</span>
                    <span class="text-lg font-semibold">{{ $stageCounts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Table (TailAdmin, like Tenancies): tabs + filters + search + add on one header row --}}
    @php
        $att = $filters['attention'] ?? null;
        $tabUrl = fn ($key) => route('crm.index', array_filter(array_merge($filters, ['attention' => $key])));
        $tabs = [
            ['label' => 'All', 'url' => $tabUrl(null), 'active' => ! $att, 'count' => $att ? null : $companies->total()],
            ['label' => 'Overdue', 'url' => $tabUrl('overdue'), 'active' => $att === 'overdue', 'count' => $att === 'overdue' ? $companies->total() : null, 'tone' => 'red'],
            ['label' => 'No next step', 'url' => $tabUrl('unattended'), 'active' => $att === 'unattended', 'count' => $stats['unattended'], 'tone' => 'amber'],
            ['label' => 'To verify', 'url' => $tabUrl('verify'), 'active' => $att === 'verify', 'count' => $att === 'verify' ? $companies->total() : null],
        ];
        $initials = fn ($n) => collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
        $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            @if ($att)<input type="hidden" name="attention" value="{{ $att }}">@endif
            @include('partials.table.tabs', ['tabs' => $tabs])
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                @include('partials.table.select', ['name' => 'stage', 'aria' => 'Stage', 'min' => 'min-w-[110px]', 'selected' => $filters['stage'] ?? '',
                    'options' => ['' => 'All stages', 'open' => 'Open'] + \App\Models\Company::CRM_STAGES])
                @include('partials.table.select', ['name' => 'tier', 'aria' => 'Tier', 'min' => 'min-w-[80px]', 'selected' => $filters['tier'] ?? '',
                    'options' => ['' => 'All tiers', 1 => 'Tier 1', 2 => 'Tier 2', 3 => 'Tier 3']])
                @include('partials.table.select', ['name' => 'owner', 'aria' => 'Owner', 'min' => 'min-w-[110px]', 'selected' => $filters['owner'] ?? '',
                    'options' => ['' => 'Any owner', 'none' => 'No owner'] + $owners->pluck('name', 'id')->all()])
                @if (array_filter(\Illuminate\Support\Arr::except($filters, ['attention'])))
                    <a href="{{ route('crm.index', array_filter(['attention' => $att])) }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear
                    </a>
                @endif
                @include('partials.table.search', ['value' => $filters['q'] ?? '', 'min' => 'min-w-[140px]', 'placeholder' => 'Search…'])
                <button type="button" @click="adding = true" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Prospect
                </button>
            </div>
        </form>

        <div class="w-full overflow-x-auto">
            <table class="data-table w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="{{ $th }}">Company</th><th class="{{ $th }}">Stage</th><th class="{{ $th }}">Owner</th><th class="{{ $th }}">Next step</th><th class="{{ $th }}">Last contact</th>
                    </tr>
                </thead>
                <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($companies as $company)
                        @php $overdue = $company->next_due_at && \Illuminate\Support\Carbon::parse($company->next_due_at)->isPast(); @endphp
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100"><span class="text-sm font-medium text-purple-600">{{ $initials($company->name) }}</span></div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('crm.companies.show', $company) }}" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">{{ $company->name }}</a>
                                            @include('crm.partials.tier-badge', ['tier' => $company->tier])
                                            @if ($company->profile('needs_verification'))<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200" title="Some details in the sheet were marked 'Verify'">verify</span>@endif
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ collect([$company->profile('business_type'), $contactNames[$company->id] ?? null])->filter()->join(' · ') ?: '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap">@include('crm.partials.stage-badge', ['stage' => $company->crm_stage])@if ($company->lifecycle === 'customer') <span class="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">customer</span>@endif</td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $company->owner?->name ?? '-' }}</td>
                            <td class="p-4 text-sm">
                                @if ($company->next_due_at)
                                    <span class="whitespace-nowrap font-medium {{ $overdue ? 'text-red-600' : 'text-gray-700 dark:text-gray-300' }}">{{ \Illuminate\Support\Carbon::parse($company->next_due_at)->format('D j M, H:i') }}@if ($overdue) · overdue @endif</span>
                                @elseif (in_array($company->crm_stage, \App\Models\Company::OPEN_CRM_STAGES, true))
                                    <span class="text-xs font-medium text-amber-600">No follow-up booked</span>
                                    @if ($company->profile('next_action'))<p class="text-xs text-gray-500 dark:text-gray-400">Sheet: {{ \Illuminate\Support\Str::limit($company->profile('next_action'), 60) }}</p>@endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $company->last_contact_at ? \Illuminate\Support\Carbon::parse($company->last_contact_at)->diffForHumans() : 'Never' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No prospects found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters, or add a prospect.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.table.pager', ['paginator' => $companies])
    </div>

    {{-- Add prospect --}}
    <div x-show="adding" x-cloak class="fixed inset-0 z-99999 flex items-center justify-center bg-gray-900/50 p-4" @keydown.escape.window="adding = false">
        <form method="POST" action="{{ route('crm.companies.store') }}" @click.outside="adding = false" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 dark:bg-gray-900">
            @csrf
            <input type="hidden" name="_form" value="add">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Add a prospect</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">If the company is already in the system (same website or name, prospect or customer), we open it instead of creating a copy.</p>
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
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="adding = false" class="h-11 rounded-lg border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
                <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600">Add prospect</button>
            </div>
        </form>
    </div>
</div>
@endsection
