@extends('layouts.app')

@section('content')
@php
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400';
    $kpis = [
        ['Open prospects', $stats['open'], route('crm.index', ['stage' => 'open']), 'text-gray-800 dark:text-white/90'],
        ['Overdue follow-ups', $stats['overdue'], route('crm.follow-ups.index', ['view' => 'overdue']), $stats['overdue'] ? 'text-red-600' : 'text-gray-800 dark:text-white/90'],
        ['Due today', $stats['due_today'], route('crm.follow-ups.index', ['view' => 'today']), 'text-gray-800 dark:text-white/90'],
        ['No next step', $stats['unattended'], route('crm.index', ['attention' => 'unattended']), $stats['unattended'] ? 'text-amber-600' : 'text-gray-800 dark:text-white/90'],
        ['New demo requests', $stats['new_demos'], route('crm.demos.index', ['status' => 'new']), 'text-gray-800 dark:text-white/90'],
        ['On the waitlist', $stats['waitlist'], route('crm.waitlist.index'), 'text-gray-800 dark:text-white/90'],
        ['NPS', $stats['nps'] ?? '–', route('crm.feedback.index'), 'text-gray-800 dark:text-white/90'],
    ];
@endphp
<div class="mx-auto max-w-screen-2xl" x-data="{ adding: {{ $errors->any() && old('_form') === 'add' ? 'true' : 'false' }} }">
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

    {{-- Filters --}}
    <form method="GET" class="{{ $card }} mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto]">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search company, project, phone or email" class="{{ $field }}" aria-label="Search">
        <select name="stage" class="{{ $field }}" aria-label="Stage">
            <option value="">All stages</option>
            <option value="open" @selected(($filters['stage'] ?? '') === 'open')>Open (not won/lost/nurture)</option>
            @foreach (\App\Models\Company::CRM_STAGES as $key => $name)
                <option value="{{ $key }}" @selected(($filters['stage'] ?? '') === $key)>{{ $name }}</option>
            @endforeach
        </select>
        <select name="tier" class="{{ $field }}" aria-label="Tier">
            <option value="">All tiers</option>
            @foreach ([1, 2, 3] as $t)<option value="{{ $t }}" @selected(($filters['tier'] ?? '') == $t)>Tier {{ $t }}</option>@endforeach
        </select>
        <select name="owner" class="{{ $field }}" aria-label="Owner">
            <option value="">Any owner</option>
            <option value="none" @selected(($filters['owner'] ?? '') === 'none')>No owner</option>
            @foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected(($filters['owner'] ?? '') == $owner->id)>{{ $owner->name }}</option>@endforeach
        </select>
        <select name="attention" class="{{ $field }}" aria-label="Needs attention">
            <option value="">Everything</option>
            <option value="overdue" @selected(($filters['attention'] ?? '') === 'overdue')>Overdue follow-up</option>
            <option value="unattended" @selected(($filters['attention'] ?? '') === 'unattended')>No next step</option>
            <option value="verify" @selected(($filters['attention'] ?? '') === 'verify')>Details to verify</option>
        </select>
        <div class="flex gap-2">
            <button class="h-11 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Filter</button>
            <button type="button" @click="adding = true" class="h-11 whitespace-nowrap rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">+ Prospect</button>
        </div>
    </form>

    {{-- Table --}}
    <div class="{{ $card }} overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Company</th>
                        <th class="px-4 py-3 font-medium">Stage</th>
                        <th class="px-4 py-3 font-medium">Owner</th>
                        <th class="px-4 py-3 font-medium">Next step</th>
                        <th class="px-4 py-3 font-medium">Last contact</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($companies as $company)
                        @php $overdue = $company->next_due_at && \Illuminate\Support\Carbon::parse($company->next_due_at)->isPast(); @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @include('crm.partials.tier-badge', ['tier' => $company->tier])
                                    <a href="{{ route('crm.companies.show', $company) }}" class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $company->name }}</a>
                                    @if ($company->profile('needs_verification'))<span class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300" title="Some details in the sheet were marked 'Verify'">verify</span>@endif
                                </div>
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ collect([$company->profile('business_type'), $contactNames[$company->id] ?? null])->filter()->join(' · ') }}
                                </div>
                            </td>
                            <td class="px-4 py-3">@include('crm.partials.stage-badge', ['stage' => $company->crm_stage])@if ($company->lifecycle === 'customer') <span class="ml-1 text-[11px] font-medium text-green-700">customer</span>@endif</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $company->owner?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($company->next_due_at)
                                    <span @class(['font-medium', 'text-red-600' => $overdue, 'text-gray-700 dark:text-gray-300' => ! $overdue])>
                                        {{ \Illuminate\Support\Carbon::parse($company->next_due_at)->format('D j M, H:i') }}@if ($overdue) · overdue @endif
                                    </span>
                                @elseif (in_array($company->crm_stage, \App\Models\Company::OPEN_CRM_STAGES, true))
                                    <span class="text-xs font-medium text-amber-600">No follow-up booked</span>
                                    @if ($company->profile('next_action'))<div class="text-xs text-gray-500 dark:text-gray-400">Sheet: {{ \Illuminate\Support\Str::limit($company->profile('next_action'), 60) }}</div>@endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $company->last_contact_at ? \Illuminate\Support\Carbon::parse($company->last_contact_at)->diffForHumans() : 'Never' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No prospects match. Import the sales sheets with <code>php artisan crm:import</code> or add one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($companies->hasPages())<div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">{{ $companies->links() }}</div>@endif
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
