@extends('layouts.app')
@section('title', 'Visitors')

@section('content')
<div class="flex flex-col gap-5">
  @include('gate._nav', ['title' => 'Visitors', 'subtitle' => 'Everyone who has visited your units: how often, when they were last in and out, and their car.', 'active' => 'visitors'])
  @include('gate._flash')

  @php $th = 'p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400'; @endphp
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <form method="GET" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      @include('partials.table.tabs', ['tabs' => [['label' => 'All visitors', 'url' => route('gate.visitors'), 'active' => true, 'count' => $places->total()]]])
      @include('partials.table.search', ['value' => request('q'), 'placeholder' => 'Name, phone or plate…'])
    </form>
    @if ($places->isEmpty())
      <div class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No visitors yet. They appear here after their first pass.</div>
    @else
      <div class="w-full overflow-x-auto">
        <table class="data-table w-full table-auto">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-800">
              <th class="{{ $th }}">Visitor</th><th class="{{ $th }}">Unit</th><th class="{{ $th }}">Visits</th><th class="{{ $th }}">Last in</th><th class="{{ $th }}">Last out</th><th class="{{ $th }}">Car</th><th class="{{ $th }} text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
            @foreach ($places as $p)
              <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                <td class="p-4">
                  <div class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $p->visitor?->name }}@if ($p->is_blocked) <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Blocked</span>@endif</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ $p->visitor?->phone }}</div>
                </td>
                <td class="p-4 text-sm text-gray-700 dark:text-gray-300"><div class="whitespace-nowrap">{{ $p->unit?->unit_number }}</div><div class="text-xs text-gray-500 dark:text-gray-400">{{ $p->property?->name }}</div></td>
                <td class="p-4 text-sm text-gray-700 dark:text-gray-300">{{ $p->visit_count }}</td>
                <td class="whitespace-nowrap p-4 text-sm text-gray-500 dark:text-gray-400">{{ $p->last_check_in_at?->timezone('Africa/Nairobi')->format('j M Y H:i') ?? '–' }}</td>
                <td class="whitespace-nowrap p-4 text-sm text-gray-500 dark:text-gray-400">{{ $p->last_check_out_at?->timezone('Africa/Nairobi')->format('j M Y H:i') ?? '–' }}</td>
                <td class="whitespace-nowrap p-4 text-sm font-medium text-gray-800 dark:text-white/90">{{ $p->last_vehicle_plate ?? '–' }}</td>
                <td class="whitespace-nowrap p-4 text-right">
                  @if ($canManage)
                    <div class="flex items-center justify-end gap-4">
                      @if ($p->last_pass_id && ! $p->is_blocked)<a href="{{ route('gate.passes.create', ['again' => $p->last_pass_id]) }}" class="font-medium text-brand-500 hover:text-brand-600">New pass</a>@endif
                      <form method="POST" action="{{ route('gate.visitors.block', $p) }}" onsubmit="return confirm('{{ $p->is_blocked ? 'Unblock' : 'Block' }} {{ e($p->visitor?->name) }} for this unit?')">@csrf
                        <button class="text-gray-500 hover:text-red-600">{{ $p->is_blocked ? 'Unblock' : 'Block' }}</button>
                      </form>
                    </div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @include('partials.table.pager', ['paginator' => $places])
    @endif
  </div>
</div>
@endsection
