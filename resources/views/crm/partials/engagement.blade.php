{{-- How a prospect engaged with our emails (App\Modules\CRM\Support\EmailEngagement::summary). $e = summary array or null;
     optional $events (collection) lists every open and click under a "details" toggle. --}}
@if (! empty($e))
  @php
    $bits = array_filter([
      $e['opens'] ? $e['opens'].' open'.($e['opens'] === 1 ? '' : 's') : null,
      $e['clicks'] ? $e['clicks'].' click'.($e['clicks'] === 1 ? '' : 's') : null,
      $e['last'] ? 'last '.$e['last']->format('D j M, g:ia') : null,
      $e['place'],
      $e['device'],
    ]);
  @endphp
  <div class="mt-1 text-xs text-gray-600 dark:text-gray-400" x-data="{ more: false }">
    <span class="inline-flex flex-wrap items-center gap-x-1.5">
      <x-icon name="eye" class="h-3.5 w-3.5 text-blue-500" />{{ implode(' · ', $bits) }}
      @if ($e['hidden'])<span class="text-gray-400">· place hidden by mail app</span>@endif
    </span>
    @if ($e['best'])<span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">Best time: {{ $e['best'] }}</span>@endif
    @if (! empty($events) && $events->count() > 0)
      <button type="button" class="ml-1 text-brand-500 hover:underline" @click="more = ! more" x-text="more ? 'hide' : 'details'">details</button>
      <ul x-show="more" x-cloak class="mt-1 space-y-0.5 border-l border-gray-200 pl-3 dark:border-gray-800">
        @foreach ($events as $ev)
          <li>
            {{ $ev->kind === 'click' ? 'Clicked' : 'Opened' }} {{ $ev->at->format('D j M, g:ia') }}
            @if ($ev->via === 'direct')
              @php $p = \App\Modules\CRM\Support\EmailEngagement::place($ev); $d = \App\Modules\CRM\Support\EmailEngagement::deviceLabel($ev); @endphp
              @if ($p) · {{ $p }} @endif
              @if ($d) · {{ $d }} @endif
            @else
              · <span class="text-gray-400">{{ ['gmail' => 'via Gmail (place and device hidden)', 'apple' => 'via Apple Mail (may be an automatic preload)', 'yahoo' => 'via Yahoo Mail (place hidden)'][$ev->via] ?? $ev->via }}</span>
            @endif
          </li>
        @endforeach
      </ul>
    @endif
  </div>
@endif
