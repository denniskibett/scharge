@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
<div>
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

    <div x-show="open" x-cloak @click="open = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"></div>
    <div x-show="open" x-cloak @keydown.escape.window="open = false"
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 32rem; max-width: calc(100% - 2rem);">
      <div class="p-6 lg:p-10">
        <button type="button" @click="open = false"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>
      <form @submit.prevent="save()" class="text-left">
        <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90" x-text="form.id ? (form.mine ? 'Edit meeting' : form.title) : 'New meeting'"></h4>
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
        <div class="flex items-center justify-end w-full gap-3 mt-6">
          <button type="button" x-show="form.id && form.mine" @click="remove()" class="mr-auto rounded-lg px-3 py-3 text-sm font-medium text-red-600 hover:bg-red-50">Cancel meeting</button>
          <button type="button" @click="open = false" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Close</button>
          <button type="submit" x-show="!form.id || form.mine" :disabled="busy" class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">Save</button>
        </div>
      </form>
      </div>
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
</div>
@endsection
