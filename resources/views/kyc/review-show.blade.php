{{-- scharge staff › KYC review of one company (KycReviewController@show). --}}
@extends('layouts.app')
@section('title', 'KYC: '.($kyc->company?->name ?? 'company'))

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
<div class="space-y-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $kyc->company?->name }}</h1>
      <p class="mt-1 text-sm text-gray-500">Sent {{ $kyc->submitted_at?->timezone('Africa/Nairobi')->format('j M Y, H:i') ?? '–' }}</p></div>
    <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusTone[$kyc->status] }}">{{ \App\Models\CompanyKyc::STATUSES[$kyc->status] }}</span>
  </div>
  <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-4 xl:col-span-2">
      @foreach ($documents as $d)
        <div class="{{ $card }}">
          <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ \App\Models\KycDocument::KINDS[$d->kind] ?? $d->kind }}{{ $d->property ? ' · '.$d->property->name : '' }}</h2>
            <a href="{{ route('kyc.file', $d) }}" target="_blank" rel="noopener" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">Open file</a></div>
          @if (str_starts_with($d->mime, 'image/'))<img src="{{ route('kyc.file', $d) }}" alt="" class="mt-3 max-h-72 rounded-lg border border-gray-200 dark:border-gray-800">@endif
          @if ($d->read_status === 'read')
            <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1 text-xs sm:grid-cols-2">@foreach ($d->extracted ?? [] as $f => $v)@if (filled($v) && ! is_bool($v))<div><dt class="inline text-gray-500">{{ ucfirst(str_replace('_', ' ', $f)) }}:</dt> <dd class="inline text-gray-800 dark:text-white/90">{{ is_array($v) ? implode(', ', $v) : $v }}</dd></div>@endif @endforeach</dl>
          @else<p class="mt-2 text-xs text-gray-500">{{ $d->read_error ?? 'Not read automatically.' }}</p>@endif
        </div>
      @endforeach
    </div>
    <div class="space-y-4">
      <div class="{{ $card }} text-sm">
        <h2 class="font-semibold text-gray-900 dark:text-white">Details given</h2>
        <dl class="mt-2 space-y-1">@foreach (['owner_name' => 'Owner', 'owner_id_number' => 'ID / passport', 'kra_pin' => 'KRA PIN', 'business_name' => 'Business', 'registration_number' => 'Reg. no.'] as $k => $l)<div><dt class="inline text-gray-500">{{ $l }}:</dt> <dd class="inline text-gray-800 dark:text-white/90">{{ $kyc->{$k} ?: '–' }}</dd></div>@endforeach</dl>
        <ul class="mt-3 space-y-1">@foreach ($kyc->checks ?? [] as $c)<li class="{{ $tone[$c['level']] ?? '' }}">{{ $c['level'] === 'ok' ? '✓' : ($c['level'] === 'fail' ? '✕' : '!') }} {{ $c['message'] }}</li>@endforeach</ul>
      </div>
      <form method="POST" action="{{ route('kyc.review.decide', $kyc) }}" class="{{ $card }} space-y-3">
        @csrf
        <label class="{{ $label }}">Note to the company (needed to send back)<textarea name="note" rows="3" maxlength="500" class="{{ $field }} mt-1.5 h-auto py-2">{{ old('note') }}</textarea></label>
        @if ($errors->any())<p class="text-sm text-red-600">{{ $errors->first() }}</p>@endif
        <div class="flex flex-wrap gap-2"><button name="decision" value="verified" class="{{ $primary }}">Verify</button><button name="decision" value="rejected" class="{{ $outline }}">Send back</button></div>
      </form>
    </div>
  </div>
</div>
@endsection
