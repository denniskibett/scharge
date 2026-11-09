{{-- Secondary figures as one line of pills, under a page's stat-card row (so pages don't show two rows of cards).
     $pills: [['label' => 'Overdue', 'value' => 3, 'url' => optional, 'tone' => 'gray'|'red'|'amber'|'green'|'blue']] --}}
@php
  $pillTones = [
    'gray' => 'border-gray-200 bg-white text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300',
    'red' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300',
    'amber' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300',
    'green' => 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-900/20 dark:text-green-300',
    'blue' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-300',
  ];
@endphp
@if (! empty($pills))
  <div class="mb-6 flex flex-wrap items-center gap-2">
    @foreach ($pills as $pill)
      @php $cls = 'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm '.($pillTones[$pill['tone'] ?? 'gray'] ?? $pillTones['gray']); @endphp
      @if (! empty($pill['url']))
        <a href="{{ $pill['url'] }}" class="{{ $cls }} transition hover:border-gray-300 dark:hover:border-gray-700">
      @else
        <span class="{{ $cls }}">
      @endif
          <span class="text-gray-500 dark:text-gray-400">{{ $pill['label'] }}</span>
          <span class="font-semibold">{{ $pill['value'] }}</span>
      @if (! empty($pill['url']))</a>@else</span>@endif
    @endforeach
  </div>
@endif
