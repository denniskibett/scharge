{{-- Outline icon: <x-icon name="check-circle" class="h-4 w-4" />. Paths: App\Support\Icons (Heroicons, MIT). --}}
@props(['name'])
@php $d = \App\Support\Icons::path($name); @endphp
@if ($d)
<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}"/></svg>
@endif
