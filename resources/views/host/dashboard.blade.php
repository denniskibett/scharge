@extends('layouts.app')
@section('title', 'My BnB units')

@section('content')
<div class="p-4 md:p-6">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">My BnB units</h1>
      <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">These units are yours to manage. If your building's management joins scharge, they handle the building's service charge and water; your bookings and earnings stay private to you.</p>
    </div>
  </div>

  @if (session('success'))
    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
  @endif

  <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($units as $u)
      <a href="{{ route('units.show', $u) }}" class="rounded-2xl border border-gray-200 bg-white p-5 transition hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <p class="text-xs text-gray-500">{{ $u->property?->name }} · {{ $u->property?->location }}</p>
        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $u->unit_number }} · {{ $u->unit_type }}</p>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">KES {{ number_format((float) $u->bnb_nightly_rate) }} / night</p>
        <p class="mt-3 text-xs text-gray-500">{{ $u->property?->claim_status === 'unclaimed' ? 'Building not managed on scharge yet' : 'Building managed on scharge' }} · Add photos on the unit page</p>
      </a>
    @empty
      <p class="text-sm text-gray-500">No units yet.</p>
    @endforelse
  </div>

  <form method="POST" action="{{ route('host.units.store') }}" class="max-w-2xl rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
    @csrf
    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Add another unit</h2>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <input name="property_name" placeholder="Building name" maxlength="160" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
      <input name="location" placeholder="Area" maxlength="160" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
      <input name="unit_number" placeholder="Unit number" required maxlength="30" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
      <select name="unit_type" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">@foreach (['Studio','One Bedroom','Two Bedroom','Three Bedroom','Four Bedroom','Villa','Cottage'] as $t)<option>{{ $t }}</option>@endforeach</select>
      <input name="nightly_rate" type="number" min="500" step="50" placeholder="Price per night (KES)" required class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
    </div>
    <p class="mt-2 text-xs text-gray-500">Same building as one of yours? Use the same name and area and we add it there.</p>
    <button class="mt-4 h-11 rounded-lg bg-gray-900 px-5 text-sm font-medium text-white hover:bg-gray-800 dark:bg-brand-500">Add unit</button>
  </form>
</div>
@endsection
