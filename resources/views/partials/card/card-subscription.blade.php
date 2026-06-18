{{-- resources/views/partials/card/card-subscription.blade.php --}}
@props([
    'label' => '',
    'value' => '0',
    'subValue' => '',
    'icon' => null,
    'iconBg' => 'bg-purple-100 dark:bg-purple-900',
    'iconColor' => 'text-purple-600 dark:text-purple-400',
    'extraInfo' => null,
    'badge' => null,
    'badgeColor' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
])

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] transition-all hover:shadow-md">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <h4 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">
                {{ $value }}
            </h4>
            @if($subValue)
                <p class="text-xs text-gray-400">{{ $subValue }}</p>
            @endif
        </div>
        <div class="flex h-12 w-12 items-center justify-center rounded-full {{ $iconBg }}">
            @if($icon)
                <svg class="h-6 w-6 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
                </svg>
            @else
                <svg class="h-6 w-6 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            @endif
        </div>
    </div>
    @if($extraInfo)
        <div class="mt-3 flex items-center gap-2 text-xs">
            {!! $extraInfo !!}
        </div>
    @endif
    @if($badge)
        <div class="mt-3">
            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeColor }}">
                {{ $badge }}
            </span>
        </div>
    @endif
</div>