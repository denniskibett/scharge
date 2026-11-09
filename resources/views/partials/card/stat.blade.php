{{-- One TailAdmin stat card (equal height in its row; the hint shows as a pill). $card: label, value, icon, tone, hint, delta (% vs last month or null), upIsGood, optional id (on the number, for scripts that update it). --}}
@php
  $tones = [
    'brand' => 'text-brand-500 bg-brand-50 dark:bg-brand-500/15',
    'sky' => 'text-sky-500 bg-sky-50 dark:bg-sky-500/15',
    'green' => 'text-emerald-500 bg-emerald-50 dark:bg-emerald-500/15',
    'amber' => 'text-amber-500 bg-amber-50 dark:bg-amber-500/15',
    'red' => 'text-red-500 bg-red-50 dark:bg-red-500/15',
  ];
  $d = $card['delta'] ?? null;
  $good = $d === null ? null : (($d >= 0) === ($card['upIsGood'] ?? true));
@endphp
<div class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
  <div class="flex items-start justify-between gap-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tones[$card['tone'] ?? 'brand'] ?? $tones['brand'] }}">
      <x-icon :name="$card['icon']" class="h-5 w-5" />
    </span>
  </div>
  <p @if (! empty($card['id'])) id="{{ $card['id'] }}" @endif class="mt-1 truncate text-title-sm font-bold text-gray-800 dark:text-white/90" title="{{ $card['value'] }}">{{ $card['value'] }}</p>
  {{-- Secondary data as a pill, pinned to the bottom so every card in the row lines up. --}}
  <div class="mt-auto flex items-center justify-between gap-2 pt-3 text-sm">
    @if (filled($card['hint'] ?? null))
      <span class="inline-flex min-w-0 items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400" title="{{ $card['hint'] }}"><span class="truncate">{{ $card['hint'] }}</span></span>
    @else
      <span></span>
    @endif
    @if ($d !== null)
      <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $good ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400' }}" title="vs last month">
        <x-icon :name="$d >= 0 ? 'arrow-up' : 'arrow-down'" class="h-3 w-3" />{{ abs($d) }}%
      </span>
    @endif
  </div>
</div>
