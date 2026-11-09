{{-- TailAdmin underline tabs with count pills (Tenancies table look), as links for server-side filters.
     $tabs: [['label' => ..., 'url' => ..., 'active' => bool, 'count' => int|null, 'tone' => 'gray'|'green'|'amber'|'red'|'blue']] --}}
@php
  $tones = [
    'gray' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    'red' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  ];
@endphp
<div class="-mb-px flex shrink-0 space-x-6">
  @foreach ($tabs as $t)
    <a href="{{ $t['url'] }}" class="inline-flex items-center whitespace-nowrap border-b-2 py-2 text-sm font-medium transition-colors {{ $t['active'] ? 'border-brand-500 text-brand-500 dark:border-brand-400 dark:text-brand-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
      {{ $t['label'] }}
      @if (isset($t['count']))
        <span class="ml-2 rounded-full px-2 py-0.5 text-xs {{ $tones[$t['tone'] ?? 'gray'] }}">{{ $t['count'] }}</span>
      @endif
    </a>
  @endforeach
</div>
