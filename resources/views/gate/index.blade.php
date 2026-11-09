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

  <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
    <form method="GET" class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <div class="inline-flex w-fit items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-800">
        @foreach (['inside' => 'Inside', 'expected' => 'Expected', 'history' => 'History', 'all' => 'All'] as $key => $label)
          <a href="{{ request()->fullUrlWithQuery(['tab' => $key, 'page' => null]) }}" class="whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium {{ $tab === $key ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">{{ $label }}</a>
        @endforeach
      </div>
      <div class="flex flex-col gap-2 sm:flex-row">
        @if ($properties->count() > 1)
          <select name="property_id" onchange="this.form.submit()" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            <option value="">All properties</option>
            @foreach ($properties as $p)<option value="{{ $p->id }}" @selected(request('property_id') == $p->id)>{{ $p->name }}</option>@endforeach
          </select>
        @endif
        <input name="q" value="{{ request('q') }}" placeholder="Name, phone, code or plate" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none sm:w-64 dark:border-gray-700 dark:text-white/90">
      </div>
    </form>

    @if ($passes->isEmpty())
      <div class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
        {{ ['inside' => 'Nobody is checked in right now.', 'expected' => 'No guests expected.', 'history' => 'No past visits yet.'][$tab] ?? 'No passes yet.' }}
        @if ($canManage) <a href="{{ route('gate.passes.create') }}" class="font-medium text-brand-500 hover:text-brand-600">Send a code to a guest</a>@endif
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">
              <th class="px-5 py-3">Guest</th><th class="px-5 py-3">Unit</th><th class="px-5 py-3">When</th><th class="px-5 py-3">Car</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($passes as $p)
              <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                <td class="px-5 py-3">
                  <div class="font-medium text-gray-800 dark:text-white/90">{{ $p->guest_name }}@if ($p->guests_count > 1)<span class="text-gray-400"> +{{ $p->guests_count - 1 }}</span>@endif</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ $p->guest_phone }} · {{ $p->kindLabel() }}@if (($p->visitor->visit_count ?? 0) > 1) · <span class="text-brand-500">returning</span>@endif</div>
                </td>
                <td class="px-5 py-3 text-gray-700 dark:text-gray-300">
                  <div class="whitespace-nowrap">{{ $p->unit?->unit_number }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400">{{ $p->property?->name }}</div>
                </td>
                <td class="whitespace-nowrap px-5 py-3 text-gray-700 dark:text-gray-300">
                  @if ($p->checked_in_at)
                    <div>In {{ $p->checked_in_at->timezone('Africa/Nairobi')->format('j M H:i') }}</div>
                    @if ($p->checked_out_at)<div class="text-xs text-gray-500 dark:text-gray-400">Out {{ $p->checked_out_at->timezone('Africa/Nairobi')->format('j M H:i') }}</div>
                    @elseif ($p->valid_until)<div class="text-xs text-gray-500 dark:text-gray-400">Until {{ $p->valid_until->timezone('Africa/Nairobi')->format('j M H:i') }}</div>@endif
                  @else
                    <div>{{ $p->windowLabel() }}</div>
                  @endif
                </td>
                <td class="whitespace-nowrap px-5 py-3 font-mono text-gray-700 dark:text-gray-300">{{ $p->vehicle_plate ?? '–' }}</td>
                <td class="px-5 py-3">@include('gate._pill', ['pass' => $p])</td>
                <td class="px-5 py-3 text-right"><a href="{{ route('gate.passes.show', $p) }}" class="font-medium text-brand-500 hover:text-brand-600">Open</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if ($passes->hasPages())<div class="border-t border-gray-200 px-5 py-3 dark:border-gray-800">{{ $passes->links() }}</div>@endif
    @endif
  </div>
</div>
@endsection
