{{--
    Page title on the left, breadcrumb on the right (Home › Section › This page), like the Calendar header.
    Use it instead of "Back to …" links:
        @include('partials.page-header', ['title' => $company->name, 'crumbs' => ['Companies' => route('admin.companies.index')]])
    $title  : this page's name (the last crumb, not a link)
    $crumbs : [label => url] between Home and this page (optional)
    $sub    : optional one-line text under the title
--}}
@php($crumbs = $crumbs ?? [])
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="min-w-0">
        <h2 class="truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h2>
        @if (! empty($sub))<p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $sub }}</p>@endif
    </div>
    <nav aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5 text-xs">
            @foreach (['Home' => url('/dashboard')] + $crumbs as $label => $url)
                <li>
                    <a href="{{ $url }}" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90">
                        {{ $label }}
                        <svg class="stroke-current" width="14" height="14" viewBox="0 0 17 16" fill="none" aria-hidden="true"><path d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </li>
            @endforeach
            <li class="max-w-[16rem] truncate text-gray-800 dark:text-white/90" aria-current="page">{{ $title }}</li>
        </ol>
    </nav>
</div>
