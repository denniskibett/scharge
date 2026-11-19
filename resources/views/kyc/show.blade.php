{{-- Settings › Verify your business (KycController). docs/ONBOARDING.md --}}
@extends('layouts.app')
@section('title', 'Verify your business')

@section('content')
@php
  $btn = 'inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium';
  $primary = $btn.' bg-brand-500 text-white hover:bg-brand-600';
  $outline = $btn.' border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5';
  $field = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
  $card = 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6';
  $tone = ['ok' => 'text-emerald-700 dark:text-emerald-300', 'warn' => 'text-amber-700 dark:text-amber-300', 'fail' => 'text-red-700 dark:text-red-300'];
  $statusTone = ['draft' => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300', 'submitted' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    'verified' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'rejected' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'];
@endphp
@php $locked = in_array($kyc->status, ['submitted', 'verified'], true) && ! auth()->user()->hasRole('sysadmin'); @endphp
<div class="space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      @section('page_title')
Verify your business
@endsection
      <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ $company->name }}. Take photos of your documents with your phone and upload them here.@if ($readerOn) scharge reads them and fills in the details for you.@endif Verified landlords get a <strong>Verified</strong> badge on their listings.</p>
    </div>
    <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusTone[$kyc->status] }}">{{ \App\Models\CompanyKyc::STATUSES[$kyc->status] }}</span>
  </div>

  @if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200">{{ $errors->first() }}</div>@endif
  @if ($kyc->status === 'rejected' && $kyc->review_note)<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"><strong>scharge asks:</strong> {{ $kyc->review_note }}</div>@endif

  <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
      @unless ($locked)
      <form method="POST" action="{{ route('kyc.upload', $q) }}" enctype="multipart/form-data" class="{{ $card }} space-y-4">
        @csrf
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">1. Upload documents</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="{{ $label }}">Document<select name="kind" required class="{{ $field }} mt-1.5">@foreach (\App\Models\KycDocument::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
          <label class="{{ $label }}">Property (title deeds)<select name="property_id" class="{{ $field }} mt-1.5"><option value="">Not for one property</option>@foreach ($properties as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></label>
        </div>
        <label class="{{ $label }}">Photos or PDF (front and back of an ID: two photos)
          <input type="file" name="files[]" multiple required accept="image/jpeg,image/png,image/webp,application/pdf" capture="environment" class="mt-1.5 block w-full text-sm text-gray-600 dark:text-gray-400"></label>
        <button class="{{ $primary }}">Upload</button>
      </form>
      @endunless

      <div class="{{ $card }}">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Your documents</h2>
        <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
          @forelse ($documents as $d)
            <div class="py-3 text-sm">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <a href="{{ route('kyc.file', $d) }}" target="_blank" rel="noopener" class="font-medium text-gray-800 hover:underline dark:text-white/90">{{ \App\Models\KycDocument::KINDS[$d->kind] ?? $d->kind }}{{ $d->property ? ' · '.$d->property->name : '' }}</a>
                <span class="text-xs text-gray-500">{{ ['pending' => 'Reading…', 'read' => 'Read', 'failed' => 'Could not read', 'off' => 'Uploaded'][$d->read_status] ?? $d->read_status }}</span>
              </div>
              @if ($d->read_status === 'read')
                <dl class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 text-xs sm:grid-cols-2">
                  @foreach (\App\Services\Kyc\DocumentReader::FIELDS[$d->kind] ?? [] as $f)@if (filled($d->extracted[$f] ?? null))<div><dt class="inline text-gray-500">{{ ucfirst(str_replace('_', ' ', $f)) }}:</dt> <dd class="inline text-gray-800 dark:text-white/90">{{ is_array($d->extracted[$f]) ? implode(', ', $d->extracted[$f]) : $d->extracted[$f] }}</dd></div>@endif @endforeach
                </dl>
              @elseif ($d->read_error)<p class="mt-1 text-xs text-gray-500">{{ $d->read_error }}</p>@endif
              @unless ($locked)
                <div class="mt-2 flex gap-3 text-xs">
                  @if ($readerOn && $d->read_status !== 'pending')<form method="POST" action="{{ route('kyc.reread', $d) }}">@csrf<button class="text-brand-600 hover:underline dark:text-brand-400">Read again</button></form>@endif
                  <form method="POST" action="{{ route('kyc.destroy', $d) }}" onsubmit="return confirm('Remove this document?')">@csrf<button class="text-red-600 hover:underline dark:text-red-400">Remove</button></form>
                </div>
              @endunless
            </div>
          @empty
            <p class="py-3 text-sm text-gray-500">Nothing uploaded yet. You need: the owner's (or a director's) ID or passport, the KRA PIN certificate, and for a company its registration certificate. Title deeds help us confirm you can let the property.</p>
          @endforelse
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('kyc.update', $q) }}" class="{{ $card }} space-y-4">
      @csrf
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">2. Check the details</h2>
      @foreach (['owner_name' => 'Owner or director (as on ID)', 'owner_id_number' => 'ID or passport number', 'kra_pin' => 'KRA PIN', 'business_name' => 'Registered business name', 'registration_number' => 'Registration number'] as $k => $l)
        <label class="{{ $label }}">{{ $l }}<input name="{{ $k }}" value="{{ old($k, $kyc->{$k}) }}" @disabled($locked) class="{{ $field }} mt-1.5"></label>
      @endforeach
      @if ($kyc->checks)
        <ul class="space-y-1 text-sm">@foreach ($kyc->checks as $c)<li class="{{ $tone[$c['level']] ?? '' }}">{{ $c['level'] === 'ok' ? '✓' : ($c['level'] === 'fail' ? '✕' : '!') }} {{ $c['message'] }}</li>@endforeach</ul>
      @endif
      @unless ($locked)
        <div class="flex flex-wrap gap-2"><button class="{{ $outline }}">Save</button><button name="submit" value="1" class="{{ $primary }}">3. Send for review</button></div>
      @endunless
    </form>
  </div>
</div>
@endsection
