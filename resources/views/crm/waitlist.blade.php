@extends('layouts.app')

@section('content')
@include('partials.card.crm-waitlist')
@php
    use App\Modules\CRM\Models\WaitlistEntry;
    use App\Modules\CRM\Support\Countries;
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp
<div>
    @include('crm.partials.nav', ['title' => 'Waitlist', 'subtitle' => 'People and demo requests for countries scharge is not live in yet. When you launch somewhere, move that country into the CRM in one click.'])

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @forelse ($byCountry as $row)
            <div class="{{ $card }} p-4">
                <a href="{{ route('crm.waitlist.index', ['country' => $row->country_code]) }}" class="block">
                    <span class="block text-xs font-medium text-gray-500 dark:text-gray-400">{{ Countries::name($row->country_code) }}</span>
                    <span class="mt-1 block text-2xl font-semibold text-gray-800 dark:text-white/90">{{ (int) $row->open }}</span>
                    <span class="text-xs text-gray-500">{{ (int) $row->demos }} want a demo · {{ $row->total }} all time</span>
                </a>
                @if ($row->open > 0)
                    <form method="POST" action="{{ route('crm.waitlist.launch') }}" class="mt-3" onsubmit="return confirm('Move {{ (int) $row->open }} {{ Countries::name($row->country_code) }} sign-ups into CRM follow-ups?')">
                        @csrf <input type="hidden" name="country_code" value="{{ $row->country_code }}">
                        <button class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">We're live here: move to CRM</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="{{ $card }} col-span-full p-6 text-center text-sm text-gray-500 dark:text-gray-400">Nobody on the waitlist yet. Sign-ups come from the homepage and from demo requests outside Kenya.</div>
        @endforelse
    </div>
    @php $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400'; @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Sign-ups</h3>
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                @include('partials.table.select', ['name' => 'country', 'aria' => 'Country', 'selected' => $filters['country'] ?? '',
                    'options' => ['' => 'All countries'] + collect(Countries::waitlist())->map(fn ($c) => $c['name'])->all()])
                @include('partials.table.select', ['name' => 'status', 'aria' => 'Status', 'selected' => $filters['status'] ?? '',
                    'options' => ['' => 'Any status'] + WaitlistEntry::STATUSES])
                @include('partials.table.search', ['value' => $filters['q'] ?? '', 'placeholder' => 'Search name, phone or email'])
                <a href="{{ route('crm.waitlist.export', $filters) }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">Export CSV</a>
            </div>
        </form>
        <div class="w-full overflow-x-auto">
        <table class="data-table w-full table-auto">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-800"><th class="{{ $th }}">Person</th><th class="{{ $th }}">Country</th><th class="{{ $th }}">Company</th><th class="{{ $th }}">Demo details</th><th class="{{ $th }}">Joined</th><th class="{{ $th }}">Status</th></tr>
            </thead>
            <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($entries as $entry)
                    @php $wlName = (string) $entry->user?->name; $wlInitials = collect(preg_split('/\s+/', trim($wlName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?'; @endphp
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                        <td class="p-4 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100"><span class="text-sm font-medium text-purple-600">{{ $wlInitials }}</span></div>
                                <div>
                                    <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $entry->user?->name }}</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ collect([$entry->user?->phone, $entry->user?->email])->filter()->join(' · ') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $entry->country_name }}</td>
                        <td class="p-4 whitespace-nowrap">@if ($entry->company)<a href="{{ route('crm.companies.show', $entry->company) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">{{ $entry->company->name }}</a>@else <span class="text-sm text-gray-500 dark:text-gray-400">—</span> @endif</td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ collect([$entry->detail('project_type'), $entry->detail('units') ? number_format($entry->detail('units')).' units' : null])->filter()->join(' · ') ?: '—' }}</td>
                        <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $entry->created_at->format('j M Y') }}</td>
                        <td class="p-4 whitespace-nowrap">
                            <form method="POST" action="{{ route('crm.waitlist.update', $entry) }}">@csrf @method('PATCH')
                                <select name="status" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" aria-label="Status">@foreach (WaitlistEntry::STATUSES as $key => $name)<option value="{{ $key }}" @selected($entry->status === $key)>{{ $name }}</option>@endforeach</select>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-4 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No sign-ups match</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters or search.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.table.pager', ['paginator' => $entries])
    </div>
</div>
@endsection
