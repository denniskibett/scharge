@extends('layouts.app')
@section('title', 'New gate pass')

@php
  $now = now('Africa/Nairobi');
  $input = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
  $label = 'mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300';
@endphp

@section('content')
<div class="flex max-w-3xl flex-col gap-5" x-data="gatePassForm(@js($prefill), @js(route('gate.lookup')))">
  <div>
    <a href="{{ route('gate.index') }}" class="text-sm text-gray-500 hover:text-gray-800 dark:text-gray-400">← Guests & gate</a>
    <h1 class="mt-2 text-xl font-semibold text-gray-800 dark:text-white/90">Send an entry code</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The guest gets a 6-digit code by SMS and email to show the guard, and a link to check out from their phone.</p>
  </div>
  @include('gate._flash')

  @if ($units->isEmpty())
    <div class="rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">You don't have any units to send codes for yet.</div>
  @else
  <form method="POST" action="{{ route('gate.passes.store') }}" class="flex flex-col gap-5">
    @csrf
    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
      <h2 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Guest</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="{{ $label }}" for="guest_phone">Mobile number</label>
          <input id="guest_phone" name="guest_phone" x-model="f.guest_phone" @change="lookup()" @blur="lookup()" required inputmode="tel" placeholder="0712 345 678 or +44…" class="{{ $input }}">
        </div>
        <div>
          <label class="{{ $label }}" for="guest_name">Name</label>
          <input id="guest_name" name="guest_name" x-model="f.guest_name" required maxlength="160" class="{{ $input }}">
        </div>
        <div>
          <label class="{{ $label }}" for="guest_email">Email <span class="font-normal text-gray-400">(optional)</span></label>
          <input id="guest_email" type="email" name="guest_email" x-model="f.guest_email" class="{{ $input }}">
        </div>
        <div>
          <label class="{{ $label }}" for="guests_count">People</label>
          <input id="guests_count" type="number" min="1" max="50" name="guests_count" x-model="f.guests_count" class="{{ $input }}">
        </div>
      </div>

      {{-- Returning visitor: what this business already knows --}}
      <template x-if="known">
        <div class="mt-4 rounded-xl border px-4 py-3 text-sm" :class="known.blocked ? 'border-red-200 bg-red-50 text-red-800' : 'border-gray-200 bg-brand-50 text-gray-700'">
          <div class="font-medium" x-text="known.blocked ? known.name + ' is blocked for this unit.' : 'Returning visitor: ' + known.name"></div>
          <div class="mt-0.5 text-xs" x-show="!known.blocked">
            <span x-text="known.visits + (known.visits === 1 ? ' visit' : ' visits')"></span>
            <template x-for="p in known.places"><span x-text="' · ' + (p.unit || '') + (p.last_in ? ' last in ' + p.last_in : '')"></span></template>
          </div>
          <div class="mt-2 flex flex-wrap gap-2" x-show="known.vehicles.length">
            <template x-for="v in known.vehicles"><button type="button" @click="f.vehicle_plate = v.plate; f.vehicle_desc = v.desc || f.vehicle_desc" class="rounded-md border border-gray-300 bg-white px-2 py-1 font-mono text-xs text-gray-700" x-text="v.plate"></button></template>
          </div>
        </div>
      </template>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
      <h2 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Visit</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="{{ $label }}" for="unit_id">Unit</label>
          <select id="unit_id" name="unit_id" x-model="f.unit_id" @change="lookup()" required class="{{ $input }}">
            <option value="">Choose a unit</option>
            @foreach ($units->groupBy(fn ($u) => $u->property?->name ?? 'Other') as $prop => $list)
              <optgroup label="{{ $prop }}">
                @foreach ($list as $u)<option value="{{ $u->id }}">{{ $u->unit_number }}</option>@endforeach
              </optgroup>
            @endforeach
          </select>
        </div>
        <div>
          <label class="{{ $label }}" for="kind">Type</label>
          <select id="kind" name="kind" x-model="f.kind" class="{{ $input }}">
            @foreach (\App\Modules\Security\Models\GatePass::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
          </select>
        </div>
        <div></div>
        <div>
          <label class="{{ $label }}" for="valid_from" x-text="f.kind === 'stay' ? 'Check-in' : 'Arrives'"></label>
          <input id="valid_from" type="datetime-local" name="valid_from" value="{{ old('valid_from', $now->format('Y-m-d\TH:i')) }}" required class="{{ $input }}">
        </div>
        <div>
          <label class="{{ $label }}" for="valid_until"><span x-text="f.kind === 'stay' ? 'Check-out' : 'Valid until'"></span> <span class="font-normal text-gray-400" x-text="f.kind === 'stay' ? '(default: next day)' : '(default: end of day)'"></span></label>
          <input id="valid_until" type="datetime-local" name="valid_until" value="{{ old('valid_until') }}" class="{{ $input }}">
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
      <h2 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Car <span class="font-normal text-gray-400">(optional)</span></h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="{{ $label }}" for="vehicle_plate">Number plate</label>
          <input id="vehicle_plate" name="vehicle_plate" x-model="f.vehicle_plate" maxlength="20" placeholder="KDA 123A" class="{{ $input }} font-mono uppercase">
        </div>
        <div>
          <label class="{{ $label }}" for="vehicle_desc">Make / colour</label>
          <input id="vehicle_desc" name="vehicle_desc" x-model="f.vehicle_desc" maxlength="80" placeholder="White Toyota Axio" class="{{ $input }}">
        </div>
        <div class="sm:col-span-2">
          <label class="{{ $label }}" for="notes">Note for the guard <span class="font-normal text-gray-400">(optional)</span></label>
          <input id="notes" name="notes" maxlength="500" value="{{ old('notes') }}" class="{{ $input }}">
        </div>
      </div>
    </section>

    <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
      <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-700 dark:text-gray-300">
        <input type="hidden" name="send_sms" value="0"><label class="inline-flex items-center gap-2"><input type="checkbox" name="send_sms" value="1" checked class="rounded border-gray-300"> SMS</label>
        <input type="hidden" name="send_email" value="0"><label class="inline-flex items-center gap-2"><input type="checkbox" name="send_email" value="1" checked class="rounded border-gray-300"> Email</label>
        @if (\App\Services\Gate\Gate::staff(auth()->user(), 'manage'))
          <label class="inline-flex items-center gap-2"><input type="checkbox" name="check_in_now" value="1" class="rounded border-gray-300"> At the gate now: check in</label>
        @endif
      </div>
      <button class="h-11 rounded-lg bg-brand-500 px-6 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Send code</button>
    </div>
  </form>
  @endif
</div>

<script>
function gatePassForm(prefill, url) {
  return {
    f: Object.assign({ unit_id: '', kind: 'visitor', guest_name: '', guest_phone: '', guest_email: '', guests_count: 1, vehicle_plate: '', vehicle_desc: '' }, prefill || {}, @js(old() ? \Illuminate\Support\Arr::except(old(), ['_token']) : [])),
    known: null,
    last: '',
    async lookup() {
      const phone = (this.f.guest_phone || '').trim();
      if (phone.replace(/\D/g, '').length < 9) { this.known = null; return; }
      const key = phone + '|' + this.f.unit_id;
      if (key === this.last) return;
      this.last = key;
      try {
        const r = await fetch(url + '?' + new URLSearchParams({ phone, unit_id: this.f.unit_id || '' }), { headers: { Accept: 'application/json' } });
        const v = (await r.json()).visitor;
        this.known = v;
        if (v) {
          if (!this.f.guest_name) this.f.guest_name = v.name || '';
          if (!this.f.guest_email) this.f.guest_email = v.email || '';
          if (!this.f.vehicle_plate && v.plate) this.f.vehicle_plate = v.plate;
        }
      } catch (e) { this.known = null; }
    },
  };
}
</script>
@endsection
