{{-- Status pill for a gate pass. $pass --}}
@php
  [$label, $tone] = $pass->stateLabel();
  $cls = [
    'green' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    'teal' => 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'indigo' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    'gray' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  ][$tone] ?? '';
@endphp
<span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $cls }}">{{ $label }}</span>
