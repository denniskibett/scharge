{{-- Reports & analytics at a glance (this month vs last month, 6-month trend). Full page: /reports. --}}
@php
  $user = auth()->user();
  $summary = null;
  if ($user && $user->can('reports.view') && \Illuminate\Support\Facades\Route::has('reports.index')) {
      $ids = \App\Services\Reports\PortfolioReport::companyIdsFor($user);
      $summary = \Illuminate\Support\Facades\Cache::remember('dash-analytics:'.($ids === null ? 'all' : implode(',', $ids)), 300, function () use ($ids) {
          $now = new \App\Services\Reports\PortfolioReport($ids, null, 'this_month');
          $prev = new \App\Services\Reports\PortfolioReport($ids, null, 'last_month');
          $trend = new \App\Services\Reports\PortfolioReport($ids, null, 'last_6');

          return ['now' => $now->kpis(), 'prev' => $prev->kpis(), 'trend' => $trend->monthly()];
      });
  }
@endphp
@if ($summary)
  @php
    [$n, $p] = [$summary['now'], $summary['prev']];
    $delta = function ($a, $b) { if (! $b) return null; return round(($a - $b) / $b * 100); };
    $tiles = [
      ['Billed this month', 'KES '.number_format($n['billed']), $delta($n['billed'], $p['billed'])],
      ['Collected this month', 'KES '.number_format($n['collected']), $delta($n['collected'], $p['collected'])],
      ['Arrears now', 'KES '.number_format($n['arrears']), null],
      ['Occupancy', $n['occupancy'] === null ? '—' : $n['occupancy'].'%', null],
    ];
  @endphp
  <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Reports &amp; analytics</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">This month against last month · billed vs collected over 6 months</p>
      </div>
      <a href="{{ route('reports.index') }}" class="inline-flex h-9 items-center gap-1 rounded-lg bg-gray-900 px-3 text-sm font-medium text-white hover:bg-gray-800 dark:bg-brand-500">Open reports →</a>
    </div>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
      <div class="grid grid-cols-2 gap-3 lg:col-span-2">
        @foreach ($tiles as [$label, $value, $change])
          <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
            @if ($change !== null)
              <p class="text-xs {{ $change >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}% vs last month</p>
            @endif
          </div>
        @endforeach
      </div>
      <div class="lg:col-span-3"><div id="dash-analytics-chart" class="-ml-2 min-h-[180px]"></div></div>
    </div>
    <script type="application/json" id="dash-analytics-data">@json($summary['trend'])</script>
  </div>
@endif
