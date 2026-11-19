{{--
  The company manual (App\Support\OnboardingGuide). $guide required; $page true on /guide (no hide button, all chapters listed open-able).
  Uses only classes in the live CSS build, plus inline styles for the progress bar.
--}}
@php
  $chapters = $guide->chapters();
  $progress = $guide->progress();
  $price = \App\Support\OnboardingGuide::pricing();
  $firstOpen = collect($chapters)->firstWhere('done', false)['key'] ?? null;
  $page = $page ?? false;
  $kes = fn ($v) => 'KES '.number_format($v);
@endphp
<style>.guide-body{padding-left:1.25rem}@media (min-width:640px){.guide-body{padding-left:4.75rem}}</style>
<div class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" id="guide">
  <div class="border-b border-gray-200 px-5 py-5 dark:border-gray-800">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="max-w-2xl">
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-500">Your scharge manual</p>
        <h3 class="mt-1 text-xl font-semibold text-gray-800 dark:text-white/90">
          @if ($progress['done'] === 0) Set up {{ $guide->company()->name }} in {{ $progress['total'] }} short chapters
          @elseif ($progress['done'] < $progress['total']) {{ $progress['done'] }} of {{ $progress['total'] }} done: keep going
          @else All set up. This manual stays here for reference
          @endif
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">What scharge does for you and how to do each thing. Each chapter has a button to the right page and ticks itself when your data shows it is done.</p>
        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
          <div class="h-2 rounded-full bg-brand-500" style="width: {{ max(3, $progress['percent']) }}%"></div>
        </div>
      </div>
      <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-400" style="max-width: 24rem">
        <p class="font-semibold text-gray-800 dark:text-white/90">What it costs</p>
        <p class="mt-1">Apartments {{ $kes($price['apartments'][3]) }} to {{ $kes($price['apartments'][0]) }} per unit a month, by area. Short stays from {{ $kes($price['short_stays'][3]) }} per listing; offices from {{ $kes($price['offices'][3]) }} per suite.</p>
        <p class="mt-1">Minimum {{ $kes($price['minimum']) }} per property. Vacant units half price. Moving your data in is free.</p>
      </div>
    </div>
    @unless ($page)
      <div class="mt-4 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('guide') }}" class="font-medium text-brand-500 hover:text-brand-600">Open the full manual</a>
        <form method="POST" action="{{ route('guide.visibility') }}">@csrf<input type="hidden" name="show" value="0"><button class="text-gray-500 hover:text-gray-700 dark:text-gray-400">Hide from my dashboard</button></form>
      </div>
    @endunless
  </div>

  <ol class="divide-y divide-gray-200 dark:divide-gray-800">
    @foreach ($chapters as $ch)
      <li x-data="{ open: {{ $ch['key'] === $firstOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open" class="flex w-full items-center gap-4 px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-white/5">
          @if ($ch['done'])
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-200">
              <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
          @else
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-brand-500 text-sm font-semibold text-brand-500">{{ $ch['number'] }}</span>
          @endif
          <span class="min-w-0 flex-1">
            <span class="block text-base font-semibold text-gray-800 dark:text-white/90">{{ $ch['title'] }}@if ($ch['optional']) <span class="ml-1 text-xs font-normal text-gray-400">optional</span>@endif</span>
            <span class="block text-sm text-gray-500 dark:text-gray-400">{{ $ch['marked'] && ! $ch['data_done'] ? ($ch['readable'] ? 'Read' : 'Not for us') : $ch['progress'] }}</span>
          </span>
          <svg class="h-5 w-5 shrink-0 text-gray-400 transition" :style="open ? 'transform: rotate(180deg)' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="open" x-cloak class="guide-body px-5 pb-5">
          <p class="text-sm text-gray-700 dark:text-gray-300">{{ $ch['what'] }}</p>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400"><span class="font-medium text-gray-700 dark:text-gray-300">Why it matters:</span> {{ $ch['why'] }}</p>
          <ol class="mt-3 space-y-2">
            @foreach ($ch['steps'] as $i => $step)
              <li class="flex gap-3 text-sm text-gray-700 dark:text-gray-300">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $i + 1 }}</span>
                <span>{!! $step !!}</span>
              </li>
            @endforeach
          </ol>
          <div class="mt-4 flex flex-wrap items-center gap-2">
            @foreach ($ch['actions'] as $i => $a)
              <a href="{{ $a['url'] }}" @if (str_ends_with($a['url'], '.csv')) data-no-spa @endif
                 @class(['inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition',
                   'bg-brand-500 text-white hover:bg-brand-600' => $i === 0,
                   'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' => $i > 0])>{{ $i === 0 && ! $ch['done'] ? 'Do it now: ' : '' }}{{ $a['label'] }}</a>
            @endforeach
            @if (! $ch['data_done'] && auth()->user()->hasAnyRole(['admin', 'property_manager']))
              <form method="POST" action="{{ route('guide.mark') }}" class="ml-auto">
                @csrf
                <input type="hidden" name="chapter" value="{{ $ch['key'] }}">
                <input type="hidden" name="on" value="{{ $ch['marked'] ? 0 : 1 }}">
                <button class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">
                  {{ $ch['marked'] ? 'Undo' : ($ch['readable'] ? 'Got it' : ($ch['optional'] ? 'Not for us' : 'Mark as done')) }}
                </button>
              </form>
            @endif
          </div>
        </div>
      </li>
    @endforeach
  </ol>
</div>
