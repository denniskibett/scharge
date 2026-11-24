@extends('layouts.app')

@section('content')
@php
    $sections = \App\Modules\CRM\Support\ClientFaq::sections();
    $card = 'overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]';
    // Answers are our own text; only **bold** is turned into markup, after escaping.
    $fmt = fn (string $t) => preg_replace('/\*\*(.+?)\*\*/', '<strong class="font-semibold text-gray-900 dark:text-white">$1</strong>', e($t));
@endphp
<div x-data="{ q: '', match(el) { const s = this.q.trim().toLowerCase(); return ! s || el.dataset.text.includes(s); } }">
    @include('crm.partials.nav', ['title' => 'Client FAQ', 'subtitle' => 'Understand the client before you sell: what to ask on the call, how to talk about price, vacant units and white label, and what clients ask.'])

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start">
        <div class="relative w-full lg:max-w-md">
            <label for="faq-search" class="sr-only">Search the FAQ</label>
            <input id="faq-search" type="search" x-model="q" placeholder="Search: vacant, white label, budget…"
                   class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
        </div>
        <nav class="flex flex-wrap gap-2" aria-label="FAQ sections">
            @foreach ($sections as $s)
                <a href="#{{ $s['id'] }}" class="rounded-full border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">{{ $s['title'] }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100">
        <p class="font-semibold">The call in one line</p>
        <p class="mt-1">Total units → how they receive and send information → free tenant and staff apps (outside their fee) → full price for units in use, vacant units 25% off after 6 months and 50% off after a year → white label last.</p>
    </div>

    <div class="space-y-6">
        @foreach ($sections as $s)
            @php($sectionText = strtolower($s['title'].' '.collect($s['items'])->map(fn ($i) => $i['q'].' '.implode(' ', $i['a']))->join(' ')))
            <section id="{{ $s['id'] }}" class="{{ $card }} scroll-mt-24" data-text="{{ $sectionText }}" x-show="match($el)">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $s['title'] }}</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $s['intro'] }}</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($s['items'] as $item)
                        <details class="group px-5 py-3" data-text="{{ strtolower($item['q'].' '.implode(' ', $item['a'])) }}" x-show="match($el)" :open="q.trim() !== ''">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 py-1 text-sm font-medium text-gray-800 dark:text-white/90">
                                <span>{{ $item['q'] }}</span>
                                <svg class="h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                            </summary>
                            <div class="mt-2 space-y-2 pb-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                @foreach ($item['a'] as $para)
                                    <p>{!! $fmt($para) !!}</p>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endforeach
        <p x-show="q.trim() !== '' && ! [...$root.querySelectorAll('section[data-text]')].some(el => match(el))" x-cloak class="text-sm text-gray-500 dark:text-gray-400">Nothing matches that search.</p>
    </div>
</div>
@endsection
