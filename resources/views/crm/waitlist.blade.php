@extends('layouts.app')

@section('content')
@php
    use App\Modules\CRM\Models\WaitlistEntry;
    use App\Modules\CRM\Support\Countries;
    $card = 'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp
<div class="mx-auto max-w-screen-2xl">
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

    <form method="GET" class="{{ $card }} mb-4 grid grid-cols-1 gap-3 p-4 sm:grid-cols-[2fr_1fr_1fr_auto_auto]">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name, phone or email" class="{{ $field }}" aria-label="Search">
        <select name="country" class="{{ $field }}" aria-label="Country"><option value="">All countries</option>@foreach (Countries::waitlist() as $code => $c)<option value="{{ $code }}" @selected(($filters['country'] ?? '') === $code)>{{ $c['name'] }}</option>@endforeach</select>
        <select name="status" class="{{ $field }}" aria-label="Status"><option value="">Any status</option>@foreach (WaitlistEntry::STATUSES as $key => $name)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $name }}</option>@endforeach</select>
        <button class="h-11 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Filter</button>
        <a href="{{ route('crm.waitlist.export', $filters) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Export CSV</a>
    </form>

    <div class="{{ $card }} overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-100 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                <tr><th class="px-4 py-3 font-medium">Person</th><th class="px-4 py-3 font-medium">Country</th><th class="px-4 py-3 font-medium">Company</th><th class="px-4 py-3 font-medium">Demo details</th><th class="px-4 py-3 font-medium">Joined</th><th class="px-4 py-3 font-medium">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($entries as $entry)
                    <tr>
                        <td class="px-4 py-3"><div class="font-medium text-gray-800 dark:text-white/90">{{ $entry->user?->name }}</div><div class="text-xs text-gray-500">{{ collect([$entry->user?->phone, $entry->user?->email])->filter()->join(' · ') }}</div></td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $entry->country_name }}</td>
                        <td class="px-4 py-3">@if ($entry->company)<a href="{{ route('crm.companies.show', $entry->company) }}" class="text-brand-500">{{ $entry->company->name }}</a>@else <span class="text-gray-400">—</span> @endif</td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ collect([$entry->detail('project_type'), $entry->detail('units') ? number_format($entry->detail('units')).' units' : null])->filter()->join(' · ') ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $entry->created_at->format('j M Y') }}</td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('crm.waitlist.update', $entry) }}">@csrf @method('PATCH')
                                <select name="status" onchange="this.form.submit()" class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" aria-label="Status">@foreach (WaitlistEntry::STATUSES as $key => $name)<option value="{{ $key }}" @selected($entry->status === $key)>{{ $name }}</option>@endforeach</select>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">No sign-ups match.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($entries->hasPages())<div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">{{ $entries->links() }}</div>@endif
    </div>
</div>
@endsection
