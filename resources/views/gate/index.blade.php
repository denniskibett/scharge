@extends('layouts.app')
@section('title', 'Guests & gate')

@section('content')
<div class="flex flex-col gap-5">
  @include('gate._nav', ['title' => 'Guests & gate', 'subtitle' => 'Send entry codes, check guests in and out, see who is inside.', 'active' => 'index'])
  @include('gate._flash')

  {{-- Verify a code: what the guard does first. A QR on the guest's pass opens /gate/verify?code=… directly. --}}
  <form method="GET" action="{{ route('gate.verify') }}" class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <label for="code" class="text-sm font-medium text-gray-700 dark:text-gray-300">Guest's code</label>
    <div class="mt-2 flex flex-col gap-3 sm:flex-row">
      <input id="code" name="code" inputmode="numeric" autocomplete="off" maxlength="7" placeholder="6-digit code" required
             class="h-14 w-full rounded-lg border border-gray-300 bg-transparent px-4 font-mono text-2xl tracking-widest text-gray-800 focus:border-brand-300 focus:outline-none sm:w-64 dark:border-gray-700 dark:text-white/90"
             @if (auth()->user()->hasRole('security')) autofocus @endif>
      <button class="h-14 rounded-lg bg-gray-900 px-6 text-sm font-medium text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900">Verify</button>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Or scan the QR on the guest's phone with your camera.</p>
  </form>

  <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
    @foreach ([['Inside now', $stats['inside']], ['Staying', $stats['staying']], ['Expected today', $stats['expected']], ['Checked in today', $stats['in_today']], ['Checked out today', $stats['out_today']]] as [$label, $value])
      <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
        <div class="mt-1 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ $value }}</div>
      </div>
    @endforeach
  </div>

  @php
    $tabUrl = fn ($key) => request()->fullUrlWithQuery(['tab' => $key, 'page' => null]);
    $tabs = [
      ['label' => 'Inside', 'url' => $tabUrl('inside'), 'active' => $tab === 'inside', 'count' => $stats['staying'], 'tone' => 'green'],
      ['label' => 'Expected', 'url' => $tabUrl('expected'), 'active' => $tab === 'expected', 'count' => $stats['expected'], 'tone' => 'blue'],
      ['label' => 'History', 'url' => $tabUrl('history'), 'active' => $tab === 'history'],
      ['label' => 'All', 'url' => $tabUrl('all'), 'active' => $tab === 'all'],
    ];
    $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400';
  @endphp
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="GET" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <input type="hidden" name="tab" value="{{ $tab }}">
      @include('partials.table.tabs', ['tabs' => $tabs])
      <div class="flex flex-wrap items-center gap-3">
        @if ($properties->count() > 1)
          @include('partials.table.select', ['name' => 'property_id', 'aria' => 'Property', 'selected' => request('property_id'), 'options' => ['' => 'All Properties'] + $properties->pluck('name', 'id')->all()])
        @endif
        @include('partials.table.search', ['value' => request('q'), 'placeholder' => 'Name, phone, code or plate…'])
      </div>
    </form>

    @if ($passes->isEmpty())
      <div class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
        {{ ['inside' => 'Nobody is checked in right now.', 'expected' => 'No guests expected.', 'history' => 'No past visits yet.'][$tab] ?? 'No passes yet.' }}
        @if ($canManage) <a href="{{ route('gate.passes.create') }}" class="font-medium text-brand-500 hover:text-brand-600">Send a code to a guest</a>@endif
      </div>
    @else
      <div class="w-full overflow-x-auto">
        <table class="data-table w-full table-auto">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-800">
              <th class="{{ $th }}">Guest</th><th class="{{ $th }}">Unit</th><th class="{{ $th }}">When</th><th class="{{ $th }}">Car</th><th class="{{ $th }}">Status</th><th class="{{ $th }} text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
            @foreach ($passes as $p)
              <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                <td class="p-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $p->guest_name }}@if ($p->guests_count > 1)<span class="text-gray-400"> +{{ $p->guests_count - 1 }}</span>@endif</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ $p->guest_phone }} · {{ $p->kindLabel() }}@if (($p->visitor->visit_count ?? 0) > 1) · <span class="text-brand-500">returning</span>@endif</div>
                </td>
                <td class="p-4 text-sm text-gray-700 dark:text-gray-300">
                  <div class="whitespace-nowrap">{{ $p->unit?->unit_number }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ $p->property?->name }}</div>
                </td>
                <td class="whitespace-nowrap p-4 text-sm text-gray-500 dark:text-gray-400">
                  @if ($p->checked_in_at)
                    <div>In {{ $p->checked_in_at->timezone('Africa/Nairobi')->format('j M H:i') }}</div>
                    @if ($p->checked_out_at)<div class="text-xs text-gray-500 dark:text-gray-400">Out {{ $p->checked_out_at->timezone('Africa/Nairobi')->format('j M H:i') }}</div>
                    @elseif ($p->valid_until)<div class="text-xs text-gray-500 dark:text-gray-400">Until {{ $p->valid_until->timezone('Africa/Nairobi')->format('j M H:i') }}</div>@endif
                  @else
                    <div>{{ $p->windowLabel() }}</div>
                  @endif
                </td>
                <td class="whitespace-nowrap p-4 text-sm font-medium text-gray-800 dark:text-white/90">{{ $p->vehicle_plate ?? '–' }}</td>
                <td class="p-4">@include('gate._pill', ['pass' => $p])</td>
                <td class="p-4 text-right"><a href="{{ route('gate.passes.show', $p) }}" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400">Open</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @include('partials.table.pager', ['paginator' => $passes])
    @endif
  </div>
</div>
@endsection
