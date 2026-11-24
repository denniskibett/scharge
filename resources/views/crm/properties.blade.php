@extends('layouts.app')

@section('content')
@php
    use App\Modules\CRM\Services\DemoRequestService as Demo;
    $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
    $field = 'h-9 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $label = 'mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400';
    $tones = ['open' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', 'demo_done' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200', 'proposal' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200', 'won' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', 'lost' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'];
    $statusTabs = ['active' => 'Not closed', 'open' => 'Open', 'proposal' => 'Proposal sent', 'won' => 'Won', 'lost' => 'Lost', 'all' => 'All'];
    $url = fn ($over) => route('crm.properties.index', array_filter(array_merge(['status' => $status, 'class' => $class, 'owner' => $owner], $over)));
@endphp
<div>
    @include('crm.partials.nav', ['title' => 'Open properties', 'subtitle' => 'Every property a prospect listed on the demo form, until it is won or lost. Attach what to sell and book the next step.'])

    @include('partials.card.pills', ['pills' => [
        ['label' => 'Properties', 'value' => number_format($rows->count()), 'url' => null, 'tone' => 'gray'],
        ['label' => 'Units', 'value' => number_format($units), 'url' => null, 'tone' => 'gray'],
        ['label' => 'Worth / month (KES '.$price.' a unit)', 'value' => 'KES '.number_format($value), 'url' => null, 'tone' => 'gray'],
    ]])

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form method="GET" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <input type="hidden" name="status" value="{{ $status }}">
            @include('partials.table.tabs', ['tabs' => collect($statusTabs)->map(fn ($l, $k) => ['label' => $l, 'url' => $url(['status' => $k]), 'active' => $status === $k])->values()->all()])
            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                @include('partials.table.select', ['name' => 'class', 'aria' => 'Property type', 'size' => 'sm', 'min' => 'min-w-[130px]', 'selected' => $class ?? '',
                    'options' => ['' => 'All property types'] + array_combine(array_keys(Demo::TYPE_GROUPS), array_keys(Demo::TYPE_GROUPS))])
                @if ($staff->isNotEmpty())
                    @include('partials.table.select', ['name' => 'owner', 'aria' => 'Account manager', 'size' => 'sm', 'min' => 'min-w-[130px]', 'selected' => $owner ?? '',
                        'options' => ['' => 'Any account manager', 'none' => 'No owner'] + $staff->pluck('name', 'id')->all()])
                @endif
            </div>
        </form>

        <div class="w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="{{ $th }}">Property</th><th class="{{ $th }}">Company</th><th class="{{ $th }}">Units</th><th class="{{ $th }}">What to sell</th><th class="{{ $th }}">Status</th><th class="{{ $th }} text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                @forelse ($rows as $p)
                    @php($c = $p['company'])
                    <tbody x-data="{ open: false }" class="border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <td class="p-4 align-top">
                                <div class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $p['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ collect([$p['type'] ?? null, $p['location'] ?? null])->filter()->join(' · ') ?: '-' }}</div>
                                <div class="text-xs"><a href="{{ \App\Modules\CRM\Support\KenyaGeo::googleMapsUrl($p, $c->profile('city')) }}" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">Google Maps ↗</a>@if (! empty($p['county']))<span class="text-gray-400"> · {{ collect([$p['sub_county'] ?? null, $p['county']])->filter()->join(', ') }}</span>@endif</div>
                                <div class="text-xs text-gray-400">{{ $p['ref'] ?? '' }} · added {{ \Illuminate\Support\Carbon::parse($p['added_at'])->diffForHumans() }}</div>
                            </td>
                            <td class="p-4 align-top">
                                <a href="{{ route('crm.companies.show', $c) }}" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">{{ $c->name }}</a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $c->owner?->name ?? 'No owner' }}</div>
                            </td>
                            <td class="p-4 align-top whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                {{ number_format((int) ($p['units'] ?? 0)) }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">KES {{ number_format((int) ($p['units'] ?? 0) * $price) }} / mo</div>
                            </td>
                            <td class="p-4 align-top">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($p['offerings'] ?? [] as $o)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ Demo::INTERESTS[$o] ?? $o }}</span>
                                    @empty
                                        <span class="text-xs text-amber-600">Nothing attached yet</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-4 align-top whitespace-nowrap"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tones[$p['status'] ?? 'open'] ?? $tones['open'] }}">{{ Demo::PROPERTY_STATUSES[$p['status'] ?? 'open'] ?? 'Open' }}</span></td>
                            <td class="p-4 align-top whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" @click="$dispatch('open-playbook', { url: '{{ route('crm.companies.playbook', $c) }}' })" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-500 hover:bg-gray-100 hover:text-brand-600 dark:hover:bg-white/5" title="Actions" aria-label="Open actions"><x-icon name="light-bulb" class="h-5 w-5" /></button>
                                    @if (! empty($p['demo_id']))<a href="{{ route('crm.follow-ups.pdf', $p['demo_id']) }}" target="_blank" rel="noopener" data-no-spa x-data @click="if ($event.metaKey || $event.ctrlKey || $event.shiftKey) return; $event.preventDefault(); $dispatch('open-pdf', { url: $el.href, title: 'Demo request PDF' })" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/5" title="Their demo request PDF" aria-label="Demo request PDF"><x-icon name="document-text" class="h-4 w-4" /></a>@endif
                                    <button type="button" @click="open = !open" class="inline-flex h-8 items-center rounded-lg border border-gray-300 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" x-text="open ? 'Close' : 'Update'"></button>
                                </div>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="6" class="bg-gray-50 p-4 dark:bg-white/[0.02]">
                                <form method="POST" action="{{ route('crm.properties.update', [$c, $p['id']]) }}" class="space-y-3">
                                    @csrf @method('PATCH')
                                    <div>
                                        <span class="{{ $label }}">What to sell for this property</span>
                                        <div class="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach (Demo::INTERESTS as $key => $name)
                                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" name="offerings[]" value="{{ $key }}" @checked(in_array($key, $p['offerings'] ?? [], true))> {{ $name }}</label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                        <div><label class="{{ $label }}" for="st-{{ $p['id'] }}">Status</label>
                                            <select id="st-{{ $p['id'] }}" name="status" class="{{ $field }}">@foreach (Demo::PROPERTY_STATUSES as $k => $l)<option value="{{ $k }}" @selected(($p['status'] ?? 'open') === $k)>{{ $l }}</option>@endforeach</select></div>
                                        <div><label class="{{ $label }}" for="un-{{ $p['id'] }}">Units</label>
                                            <input id="un-{{ $p['id'] }}" name="units" type="number" min="0" value="{{ (int) ($p['units'] ?? 0) }}" class="{{ $field }}"></div>
                                        <div class="sm:col-span-2"><label class="{{ $label }}" for="fu-{{ $p['id'] }}">Book a follow-up (optional)</label>
                                            <input id="fu-{{ $p['id'] }}" name="follow_up_at" type="datetime-local" class="{{ $field }}"></div>
                                    </div>
                                    <div><label class="{{ $label }}" for="nt-{{ $p['id'] }}">Note for the thread (optional)</label>
                                        <input id="nt-{{ $p['id'] }}" name="note" maxlength="1000" placeholder="e.g. Sent pricing for water billing on Block A" class="{{ $field }}"></div>
                                    <button class="inline-flex h-9 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">Save</button>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="6" class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">No properties here yet. They appear when a prospect fills in the demo form from a cold email.</td></tr></tbody>
                @endforelse
            </table>
        </div>
    </div>
    @include('crm.partials.playbook-modal')
</div>
@endsection
