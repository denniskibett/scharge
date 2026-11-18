{{-- CRM tabs + success/error pop-ups for every CRM page. --}}
@include('partials.modal.success-modal')
@include('partials.modal.error-modal')
@php
    $crmTabs = [
        'crm.index' => ['Pipeline', null],
        'crm.map.index' => ['Map', null],
        'crm.sales.index' => ['Sales tracker', null],
        'crm.campaigns.index' => ['Campaigns', null],
        'crm.properties.index' => ['Open properties', null],
        'crm.follow-ups.index' => ['Follow-ups', \App\Modules\CRM\Models\CrmFollowUp::overdue()->count()],
        'crm.requests.index' => ['Requests', \App\Modules\CRM\Models\CrmFollowUp::whereIn('type', ['demo', 'api_keys', 'viewing'])->where('status', 'open')->count()],
        'crm.waitlist.index' => ['Waitlist', null],
        'crm.feedback.index' => ['Feedback', null],
    ];
@endphp
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h2>
        @isset($subtitle)<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>@endisset
    </div>
    <nav class="flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-900" aria-label="CRM sections">
        @foreach ($crmTabs as $route => [$label, $badge])
            <a href="{{ route($route) }}" @class([
                'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
                'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' => request()->routeIs($route),
                'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => ! request()->routeIs($route),
            ]) @if (request()->routeIs($route)) aria-current="page" @endif>
                {{ $label }}
                @if ($badge)<span class="rounded-full bg-red-600 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white">{{ $badge }}</span>@endif
            </a>
        @endforeach
    </nav>
</div>
@php($mapFilter = request()->routeIs('crm.index') ? array_filter(request()->only(['county', 'sub_county', 'segment', 'verified']), fn ($v) => $v !== null && $v !== '') : [])
@if ($mapFilter)
    <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
        <span class="font-medium">From the map:</span>
        <span>{{ collect([
            $mapFilter['sub_county'] ?? null ? (($mapFilter['sub_county'] === 'none' ? 'no sub-county' : $mapFilter['sub_county'].' sub-county')) : null,
            $mapFilter['county'] ?? null ? $mapFilter['county'].' County' : null,
            $mapFilter['segment'] ?? null,
            isset($mapFilter['verified']) ? ($mapFilter['verified'] === '1' ? 'verified' : 'to verify') : null,
        ])->filter()->join(' · ') }}</span>
        <a href="{{ route('crm.index', request()->except(['county', 'sub_county', 'segment', 'verified', 'page'])) }}" class="ml-auto font-medium underline">Clear</a>
        <a href="{{ route('crm.map.index') }}" class="font-medium underline">Back to map</a>
    </div>
@endif
<script>
document.addEventListener('alpine:initialized', () => {
    @if (session('success'))
        window.successModal?.simple('Done', @json(session('success')));
    @endif
    @if ($errors->any())
        window.errorModal?.show('Please check the form', 'Some details need fixing:', @json($errors->all()));
    @endif
});
</script>
