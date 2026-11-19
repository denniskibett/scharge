{{-- Drawn by the layout above every page except the Dashboard (App\Support\PageHeader). --}}
@php
    $h = \App\Support\PageHeader::resolve($pageTitle ?? null, $docTitle ?? null);
@endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="min-w-0">
        <h2 class="truncate text-xl font-semibold text-gray-800 dark:text-white/90">{{ $h['title'] }}</h2>
        @if (! empty($h['sub']))<p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $h['sub'] }}</p>@endif
    </div>
    <nav aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm">
            @foreach (['Home' => url('/dashboard')] + $h['crumbs'] as $label => $url)
                <li>
                    <a href="{{ $url }}" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90">
                        {{ $label }}
                        <svg class="stroke-current" width="17" height="16" viewBox="0 0 17 16" fill="none" aria-hidden="true"><path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </li>
            @endforeach
            <li class="max-w-[18rem] truncate text-gray-800 dark:text-white/90" aria-current="page">{{ $h['title'] }}</li>
        </ol>
    </nav>
</div>
