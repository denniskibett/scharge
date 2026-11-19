{{-- Assistant: drafts written by Claude, waiting for a person (docs/ASSISTANT.md). --}}
@extends('layouts.app')
@section('title', 'Assistant')
@section('page_title', 'Assistant')

@section('content')
@php
  $q = request()->filled('company') ? ['company' => request('company')] : [];
  $input = 'w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $btn = 'inline-flex h-9 items-center rounded-lg px-3 text-sm font-medium';
  $kinds = \App\Http\Controllers\AssistantController::KINDS;
  $visibleKinds = $company ? array_intersect_key($kinds, array_flip(['tenant_email', 'arrears', 'cleanup'])) : array_intersect_key($kinds, array_flip(['prospect_reply', 'prospect_followup', 'billing']));
  $channels = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'action' => 'Fix'];
@endphp

@foreach (['status' => 'emerald', 'error' => 'amber'] as $key => $tone)
  @if (session($key))
    <div class="mb-5 rounded-xl border border-{{ $tone }}-200 bg-{{ $tone }}-50 px-4 py-3 text-sm text-{{ $tone }}-800 dark:border-{{ $tone }}-500/30 dark:bg-{{ $tone }}-500/10 dark:text-{{ $tone }}-200">{{ session($key) }}</div>
  @endif
@endforeach

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <p class="max-w-2xl text-sm text-gray-500 dark:text-gray-400">
    @if ($company)
      Claude drafts replies to {{ $company->name }}'s tenants, payment follow-ups and data fixes. Nothing is sent or changed until someone here presses Send or Apply.
    @else
      Claude drafts answers to prospects' replies, due CRM follow-ups and subscription reminders for scharge. Nothing is sent until someone presses Send.
    @endif
  </p>
  <div class="flex items-center gap-2">
    @if ($companies->isNotEmpty())
      <select class="h-9 rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-700 dark:bg-gray-900" onchange="location.search = this.value ? '?company=' + this.value : ''">
        <option value="">scharge (CRM & billing)</option>
        @foreach ($companies as $c)<option value="{{ $c->id }}" @selected($company?->id === $c->id)>{{ $c->name }}</option>@endforeach
      </select>
    @endif
    @if ($installed && $enabled)
      <form method="POST" action="{{ route('assistant.run', $q) }}">@csrf
        <button class="{{ $btn }} border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">{{ $company ? 'Scan now' : 'Check now' }}</button>
      </form>
    @endif
  </div>
</div>

@if (! $installed || ! $hasKey || ! $enabled)
  <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
    @if (! $installed) The assistant is not installed on this server yet (database/schema/2026_11_20_ai_drafts.sql).
    @elseif (! $hasKey) scharge has not added its Anthropic key yet (Integrations › Claude assistant (scharge)).
    @else The Claude assistant is off. <a class="font-medium underline" href="{{ route('integrations.index', $company ? ['company' => $company->id] : []) }}">Switch it on in Integrations</a>.
    @endif
  </div>
@endif

@if ($installed)
  <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @foreach (array_slice($visibleKinds, 0, 3, true) as $k => $label)
      @include('partials.card.stat', ['card' => ['label' => $label, 'value' => number_format($counts[$k] ?? 0), 'icon' => $k === 'cleanup' ? 'wrench-screwdriver' : ($k === 'arrears' || $k === 'billing' ? 'banknotes' : 'envelope'), 'tone' => 'brand', 'hint' => 'waiting']])
    @endforeach
    @include('partials.card.stat', ['card' => ['label' => 'Sent this week', 'value' => number_format($sentWeek), 'icon' => 'paper-airplane', 'tone' => 'green']])
  </div>

  <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 dark:border-gray-800">
      <nav class="flex gap-5 text-sm">
        @foreach (['draft' => 'To review', 'sent' => 'Sent', 'applied' => 'Fixed', 'failed' => 'Failed', 'discarded' => 'Discarded'] as $key => $label)
          <a href="{{ route('assistant.index', $q + array_filter(['status' => $key, 'kind' => $kind])) }}"
             class="border-b-2 py-4 font-medium {{ $status === $key ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">{{ $label }}</a>
        @endforeach
      </nav>
      <div class="flex flex-wrap gap-2 py-3 text-xs">
        <a href="{{ route('assistant.index', $q + ['status' => $status]) }}" class="rounded-full px-3 py-1 {{ ! $kind ? 'bg-gray-900 text-white dark:bg-white/20' : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300' }}">All</a>
        @foreach ($visibleKinds as $k => $label)
          <a href="{{ route('assistant.index', $q + ['status' => $status, 'kind' => $k]) }}" class="rounded-full px-3 py-1 {{ $kind === $k ? 'bg-gray-900 text-white dark:bg-white/20' : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300' }}">{{ $label }}</a>
        @endforeach
      </div>
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-800">
      @forelse ($drafts as $d)
        @php $ctx = json_decode((string) $d->context, true) ?: []; $editable = in_array($d->status, ['draft', 'failed'], true); @endphp
        <div class="p-5" x-data="{ redo: false }">
          <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-[#D97757]/10 px-2.5 py-0.5 font-medium text-[#B4532F] dark:text-[#E9A285]">{{ $kinds[$d->kind] ?? $d->kind }}</span>
            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $channels[$d->channel] ?? $d->channel }}</span>
            @if ($d->to_name || $d->to_address)<span class="text-gray-600 dark:text-gray-300">To {{ $d->to_name }} @if ($d->to_address)<span class="text-gray-400">&lt;{{ $d->to_address }}&gt;</span>@endif</span>@endif
            <span class="ml-auto text-gray-400">{{ \Carbon\Carbon::parse($d->created_at)->timezone('Africa/Nairobi')->format('j M, H:i') }}</span>
          </div>

          @if ($d->kind === 'cleanup')
            <h4 class="mb-1 text-sm font-semibold text-gray-800 dark:text-white/90">{{ $d->subject }}</h4>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $d->body }}</p>
            @if (! empty($ctx['facts']['examples']))
              <ul class="mt-2 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                @foreach ($ctx['facts']['examples'] as $ex)<li>· {{ collect($ex)->filter(fn ($v) => $v !== null && $v !== '')->implode(' · ') }}</li>@endforeach
              </ul>
            @endif
            <p class="mt-2 text-xs font-medium text-gray-700 dark:text-gray-200">Fix: {{ $ctx['facts']['proposed_fix'] ?? '' }}</p>
            @if ($editable && ($ctx['action'] ?? null))
              <div class="mt-3 flex gap-2">
                <form method="POST" action="{{ route('assistant.send', $q + ['id' => $d->id]) }}">@csrf<button class="{{ $btn }} bg-gray-900 text-white hover:bg-gray-700 dark:bg-brand-500">Apply fix</button></form>
                <form method="POST" action="{{ route('assistant.discard', $q + ['id' => $d->id]) }}">@csrf<button class="{{ $btn }} text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Ignore</button></form>
              </div>
            @elseif ($editable)
              <form class="mt-3" method="POST" action="{{ route('assistant.discard', $q + ['id' => $d->id]) }}">@csrf<button class="{{ $btn }} text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Done, hide it</button></form>
            @endif
          @else
            @if (! empty($ctx['facts']['their_reply']['text']) || ! empty($ctx['facts']['their_email']['text']))
              <details class="mb-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                <summary class="cursor-pointer font-medium">Their message</summary>
                <p class="mt-2 whitespace-pre-line">{{ $ctx['facts']['their_reply']['text'] ?? $ctx['facts']['their_email']['text'] }}</p>
              </details>
            @endif
            @if ($d->note)<p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">Check: {{ $d->note }}</p>@endif
            @if ($d->error)<p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $d->error }}</p>@endif

            @if ($editable)
              <form method="POST" action="{{ route('assistant.send', $q + ['id' => $d->id]) }}" class="space-y-2">
                @csrf
                @if ($d->channel === 'email')<input name="subject" value="{{ $d->subject }}" class="{{ $input }}" placeholder="Subject">@endif
                <textarea name="body" rows="{{ $d->channel === 'email' ? 9 : 3 }}" class="{{ $input }}">{{ $d->body }}</textarea>
                <div class="flex flex-wrap items-center gap-2">
                  <button class="{{ $btn }} bg-gray-900 text-white hover:bg-gray-700 dark:bg-brand-500">Send {{ $channels[$d->channel] ?? '' }}</button>
                  <button type="button" @click="redo = !redo" class="{{ $btn }} border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Redraft…</button>
                  <button form="discard-{{ $d->id }}" class="{{ $btn }} text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Discard</button>
                  @if ($d->channel !== 'email')<span class="text-xs text-gray-400">SMS: keep it under 160 characters for one message.</span>@endif
                </div>
              </form>
              <form id="discard-{{ $d->id }}" method="POST" action="{{ route('assistant.discard', $q + ['id' => $d->id]) }}">@csrf</form>
              <form x-show="redo" x-cloak method="POST" action="{{ route('assistant.redraft', $q + ['id' => $d->id]) }}" class="mt-2 flex gap-2">
                @csrf
                <input name="instruction" required class="{{ $input }}" placeholder="e.g. shorter, in Swahili, offer to call them, mention the water repair">
                <button class="{{ $btn }} shrink-0 border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Redraft</button>
              </form>
            @else
              @if ($d->subject)<p class="mb-1 text-sm font-medium text-gray-800 dark:text-white/90">{{ $d->subject }}</p>@endif
              <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $d->body }}</p>
              @if ($d->status === 'discarded')
                <form class="mt-2" method="POST" action="{{ route('assistant.discard', $q + ['id' => $d->id, 'undo' => 1]) }}">@csrf<button class="{{ $btn }} text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5">Undo</button></form>
              @endif
            @endif
          @endif
        </div>
      @empty
        <p class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Nothing here.</p>
      @endforelse
    </div>
    @if (method_exists($drafts, 'links'))<div class="px-5 pb-5">{{ $drafts->links() }}</div>@endif
  </div>
@endif
@endsection
