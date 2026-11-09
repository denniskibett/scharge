{{-- Water report: usage chart, not-read list, by property, highest usage. Needs $w (PortfolioReport::water()) and $card (card classes). --}}
<div id="water" class="{{ ($heading ?? true) ? 'mt-8' : '' }} scroll-mt-24">
  @if ($heading ?? true)
  <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
    <div>
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Water</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">Meter readings in the period: usage, charges and units still to read this month.</p>
    </div>
    <a href="{{ route('water.index') }}" class="inline-flex h-9 items-center gap-1 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"><x-icon name="pencil-square" class="h-4 w-4" />Record readings</a>
  </div>
  @endif
  <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
    <div class="{{ $card }} xl:col-span-2">
      <div class="mb-2 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Usage and charges</h3>
        <span class="text-xs text-gray-500">Units used · KES charged, by month</span>
      </div>
      <div id="report-water" class="-ml-2 min-h-[300px]"></div>
    </div>
    <div class="{{ $card }}">
      <div class="mb-3 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Not read this month</h3>
        <span class="rounded-full {{ $w['unread_count'] ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' }} px-2.5 py-0.5 text-xs font-medium">{{ number_format($w['unread_count']) }} units</span>
      </div>
      <ul class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($w['unread'] as $u)
          <li class="flex items-center justify-between py-2 text-sm">
            <span class="text-gray-800 dark:text-white/90">{{ $u['unit_number'] }} <span class="text-gray-500">· {{ $u['property'] }}</span></span>
            <span class="text-xs text-gray-500">{{ $u['last_reading_date'] ? 'Last '.\Carbon\Carbon::parse($u['last_reading_date'])->format('j M') : 'Never read' }}</span>
          </li>
        @empty
          <li class="py-6 text-center text-sm text-gray-500">Every occupied unit has a reading this month.</li>
        @endforelse
      </ul>
      @if ($w['unread_count'] > count($w['unread']))
        <p class="mt-2 text-xs text-gray-500">and {{ number_format($w['unread_count'] - count($w['unread'])) }} more.</p>
      @endif
    </div>
  </div>
  <div class="mt-4 grid grid-cols-1 gap-4 2xl:grid-cols-2">
    <div class="{{ $card }}">
      <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Water by property</h3>
      <div class="overflow-x-auto">
        <table class="data-table w-full">
          <thead><tr><th>Property</th><th class="!text-right">Units read</th><th class="!text-right">Units used</th><th class="!text-right">Per unit</th><th class="!text-right">Charged (KES)</th></tr></thead>
          <tbody>
            @forelse ($w['properties'] as $p)
              <tr><td class="font-medium text-gray-800 dark:text-white/90">{{ $p['name'] }}</td><td class="text-right">{{ number_format($p['units_read']) }}</td><td class="text-right">{{ number_format($p['consumption']) }}</td><td class="text-right">{{ number_format($p['per_unit'], 1) }}</td><td class="text-right">{{ number_format($p['charge']) }}</td></tr>
            @empty
              <tr><td colspan="5" class="py-6 text-center text-gray-500">No readings in this period.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    <div class="{{ $card }}">
      <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Highest usage</h3>
      <div class="overflow-x-auto">
        <table class="data-table w-full">
          <thead><tr><th>Unit</th><th class="!text-right">Readings</th><th class="!text-right">Units used</th><th class="!text-right">Charged (KES)</th></tr></thead>
          <tbody>
            @forelse ($w['top_units'] as $u)
              <tr><td><span class="font-medium text-gray-800 dark:text-white/90">{{ $u['unit'] }}</span><span class="block text-xs text-gray-500">{{ $u['property'] }}</span></td><td class="text-right">{{ $u['readings'] }}</td><td class="text-right">{{ number_format($u['consumption']) }}</td><td class="text-right">{{ number_format($u['charge']) }}</td></tr>
            @empty
              <tr><td colspan="4" class="py-6 text-center text-gray-500">No readings in this period.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
