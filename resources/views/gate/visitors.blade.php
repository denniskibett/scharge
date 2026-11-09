@extends('layouts.app')
@section('title', 'Visitors')

@section('content')
<div class="flex flex-col gap-5">
  @include('gate._nav', ['title' => 'Visitors', 'subtitle' => 'Everyone who has visited your units: how often, when they were last in and out, and their car.', 'active' => 'visitors'])
  @include('gate._flash')

  <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <form method="GET" class="flex justify-end border-b border-gray-200 px-5 py-4 dark:border-gray-800">
      <input name="q" value="{{ request('q') }}" placeholder="Name, phone or plate" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none sm:w-64 dark:border-gray-700 dark:text-white/90">
    </form>
    @if ($places->isEmpty())
      <div class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No visitors yet. They appear here after their first pass.</div>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">
              <th class="px-5 py-3">Visitor</th><th class="px-5 py-3">Unit</th><th class="px-5 py-3">Visits</th><th class="px-5 py-3">Last in</th><th class="px-5 py-3">Last out</th><th class="px-5 py-3">Car</th><th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($places as $p)
              <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                <td class="px-5 py-3">
                  <div class="font-medium text-gray-800 dark:text-white/90">{{ $p->visitor?->name }}@if ($p->is_blocked) <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Blocked</span>@endif</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ $p->visitor?->phone }}</div>
                </td>
                <td class="px-5 py-3 text-gray-700 dark:text-gray-300"><div class="whitespace-nowrap">{{ $p->unit?->unit_number }}</div><div class="text-xs text-gray-500 dark:text-gray-400">{{ $p->property?->name }}</div></td>
                <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $p->visit_count }}</td>
                <td class="whitespace-nowrap px-5 py-3 text-gray-700 dark:text-gray-300">{{ $p->last_check_in_at?->timezone('Africa/Nairobi')->format('j M Y H:i') ?? '–' }}</td>
                <td class="whitespace-nowrap px-5 py-3 text-gray-700 dark:text-gray-300">{{ $p->last_check_out_at?->timezone('Africa/Nairobi')->format('j M Y H:i') ?? '–' }}</td>
                <td class="whitespace-nowrap px-5 py-3 font-mono text-gray-700 dark:text-gray-300">{{ $p->last_vehicle_plate ?? '–' }}</td>
                <td class="whitespace-nowrap px-5 py-3 text-right">
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
      @if ($places->hasPages())<div class="border-t border-gray-200 px-5 py-3 dark:border-gray-800">{{ $places->links() }}</div>@endif
    @endif
  </div>
</div>
@endsection
