@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
<main class="p-4 md:p-6">
  <div x-data="{ pageName: `Calendar`}">
    @include('partials.breadcrumb')
  </div>

  {{-- What this person can see; untick to hide a kind of event. --}}
  <div class="mb-4 flex flex-wrap items-center gap-2" x-data="{ off: [] }"
       x-init="$watch('off', v => window.schargeCalendarTypes && window.schargeCalendarTypes(v))">
    @php
      $dot = ['Warning' => 'bg-amber-500', 'Success' => 'bg-emerald-500', 'Primary' => 'bg-brand-500', 'Danger' => 'bg-red-500'];
    @endphp
    @foreach ($types as $key => [$label, $level])
      <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
             :class="off.includes('{{ $key }}') && 'opacity-50'">
        <input type="checkbox" class="sr-only" :checked="!off.includes('{{ $key }}')"
               @change="off = $event.target.checked ? off.filter(t => t !== '{{ $key }}') : [...off, '{{ $key }}']">
        <span class="h-2.5 w-2.5 rounded-full {{ $dot[$level] }}"></span> {{ $label }}
      </label>
    @endforeach
    @if ($types->isEmpty())
      <p class="text-sm text-gray-500 dark:text-gray-400">Nothing is scheduled for your role yet.</p>
    @endif
  </div>

  @if ($types->has('meetings'))
  <div x-data="meetingForm('{{ route('calendar.meetings.store') }}', '{{ route('calendar.people') }}')" @meeting-new.window="openNew($event.detail.date)" @meeting-open.window="openEdit($event.detail)" class="mb-4 flex justify-end">
    <button type="button" @click="openNew()" class="inline-flex h-10 items-center rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-800 dark:bg-brand-500">+ New meeting</button>

    <div x-show="open" x-cloak class="fixed inset-0 z-[99999] flex items-center justify-center overflow-y-auto p-4" @keydown.escape.window="open = false">
      <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="open = false"></div>
      <form @submit.prevent="save()" class="relative w-full max-w-[560px] rounded-3xl bg-white p-6 text-left dark:bg-gray-900 lg:p-8">
        <h4 class="mb-4 text-xl font-semibold text-gray-800 dark:text-white/90" x-text="form.id ? (form.mine ? 'Edit meeting' : form.title) : 'New meeting'"></h4>
        <fieldset :disabled="form.id && !form.mine" class="space-y-3">
          <input x-model="form.title" required maxlength="160" placeholder="Title, e.g. Weekly collections review" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
          <div class="grid grid-cols-2 gap-3">
            <label class="text-xs text-gray-500">Starts<input type="datetime-local" x-model="form.starts_at" required class="mt-1 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"></label>
            <label class="text-xs text-gray-500">Ends<input type="datetime-local" x-model="form.ends_at" class="mt-1 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"></label>
          </div>
          <input x-model="form.location" maxlength="255" placeholder="Where: room, address or meeting link" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
          <textarea x-model="form.description" rows="3" placeholder="Agenda / notes" class="w-full rounded-lg border border-gray-300 bg-transparent p-3 text-sm dark:border-gray-700 dark:text-white/90"></textarea>
          <div class="grid grid-cols-2 gap-3 text-sm text-gray-700 dark:text-gray-300">
            <label>Type <select x-model="form.kind" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-2 dark:border-gray-700"><option value="meeting">Meeting</option><option value="reminder">Reminder</option></select></label>
            <label>Who sees it <select x-model="form.visibility" class="mt-1 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-2 dark:border-gray-700"><option value="attendees">Invited people</option><option value="company">Whole team</option>@role('sysadmin|account_manager')<option value="staff">scharge staff</option>@endrole</select></label>
          </div>
          <div>
            <p class="mb-1 text-xs text-gray-500">Invite</p>
            <div class="max-h-36 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-800">
              <template x-for="p in people" :key="p.id">
                <label class="flex items-center gap-2 py-0.5 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" :value="p.id" x-model.number="form.attendees"> <span x-text="p.name"></span></label>
              </template>
              <p x-show="!people.length" class="text-xs text-gray-400">No team members yet.</p>
            </div>
          </div>
        </fieldset>
        <p x-show="message" class="mt-3 text-sm" :class="ok ? 'text-emerald-600' : 'text-red-600'" x-text="message"></p>
        <div class="mt-6 flex gap-3">
          <button type="button" x-show="form.id && form.mine" @click="remove()" class="mr-auto rounded-lg px-3 text-sm font-medium text-red-600 hover:bg-red-50">Cancel meeting</button>
          <button type="button" @click="open = false" class="h-11 flex-1 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Close</button>
          <button type="submit" x-show="!form.id || form.mine" :disabled="busy" class="h-11 flex-1 rounded-lg bg-gray-900 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-brand-500">Save</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function meetingForm(storeUrl, peopleUrl) {
      const send = async (method, url, body) => {
        const r = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: body ? JSON.stringify(body) : undefined });
        const d = await r.json().catch(() => ({})); if (!r.ok) throw new Error(d.message || Object.values(d.errors || {})[0]?.[0] || 'Could not save'); return d;
      };
      const pad = (n) => String(n).padStart(2, '0');
      const local = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
      return {
        open: false, busy: false, message: '', ok: true, people: [], form: {},
        async loadPeople() { if (!this.people.length) this.people = (await (await fetch(peopleUrl, { headers: { Accept: 'application/json' } })).json()).people || []; },
        openNew(date) {
          const start = date ? new Date(date + (date.length <= 10 ? 'T09:00' : '')) : new Date(Date.now() + 3600e3);
          this.form = { id: null, mine: true, title: '', description: '', location: '', kind: 'meeting', visibility: 'attendees', starts_at: local(start), ends_at: local(new Date(start.getTime() + 3600e3)), attendees: [] };
          this.message = ''; this.open = true; this.loadPeople();
        },
        openEdit(m) { this.form = { ...m, attendees: [...(m.attendees || [])] }; this.message = ''; this.open = true; this.loadPeople(); },
        async save() {
          this.busy = true; this.message = '';
          try {
            const d = await send(this.form.id ? 'PUT' : 'POST', this.form.id ? `${storeUrl}/${this.form.id}` : storeUrl, this.form);
            this.ok = true; this.message = d.message; window.schargeCalendarRefresh && window.schargeCalendarRefresh(); setTimeout(() => this.open = false, 600);
          } catch (e) { this.ok = false; this.message = e.message; }
          this.busy = false;
        },
        async remove() {
          if (!confirm('Cancel this meeting for everyone invited?')) return;
          try { await send('DELETE', `${storeUrl}/${this.form.id}`); this.open = false; window.schargeCalendarRefresh && window.schargeCalendarRefresh(); } catch (e) { this.ok = false; this.message = e.message; }
        },
      };
    }
  </script>
  @endif

  <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div id="calendar" class="min-h-screen" data-feed="{{ route('calendar.events') }}" data-types="{{ $types->keys()->join(',') }}"></div>
  </div>
</main>
@endsection
