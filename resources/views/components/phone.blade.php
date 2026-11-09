{{-- A phone number, flagged when it is not Kenyan: <x-phone :number="$user->phone" /> (App\Helpers\PhoneHelper) --}}
@props(['number'])
@php $country = $number ? \App\Helpers\PhoneHelper::foreignCountry($number) : null; @endphp
@if ($number)
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
  <span>{{ $number }}</span>
  @if ($country)
    <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700 dark:bg-violet-500/15 dark:text-violet-300" title="International number: reach them on WhatsApp or email, not SMS">
      <x-icon name="globe-alt" class="h-3 w-3" />{{ $country }}
    </span>
  @endif
</span>
@else
<span class="text-gray-400">—</span>
@endif
