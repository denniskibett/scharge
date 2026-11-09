{{-- Team & access (Brevemaster pattern): people + invite, then what each role can do. Data: TeamController@index --}}
@extends('layouts.app')
@section('title', 'Team & access')

@section('content')
<div class="p-4 md:p-6 space-y-6" x-data="teamPage('{{ route('team.index') }}', '{{ $scopeParam }}')">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Team & access</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Everyone who can sign in to {{ $scopeName }}, and what each role can do.</p>
    </div>
    @if ($companies->isNotEmpty())
      <select class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90 sm:w-72" onchange="location.search='?company='+this.value" aria-label="Team for">
        <option value="staff" @selected($scopeParam === 'staff')>scharge team (staff)</option>
        @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $scopeParam === (string) $c->id)>{{ $c->name }}</option>@endforeach
      </select>
    @endif
  </div>

  <div x-show="notice" x-cloak class="rounded-xl px-4 py-3 text-sm" :class="ok ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'">
    <span x-text="notice"></span>
    <div x-show="link" class="mt-2 flex gap-2"><input readonly :value="link" class="h-9 flex-1 rounded border border-gray-300 px-2 text-xs"><button type="button" class="text-xs underline" @click="navigator.clipboard?.writeText(link)">Copy</button></div>
  </div>

  {{-- People --}}
  <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white/90">People <span class="font-medium text-gray-400">({{ $members->filter(fn ($m) => (int) $m->status !== 1)->count() }})</span>@if ($filterRole) <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ str_replace('_', ' ', $filterRole) }} · <a href="?company={{ $scopeParam }}" class="underline">show all</a></span>@endif</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Invite people with their email; they set their own password.</p>
      </div>
      <button type="button" @click="openForm(null)" class="inline-flex h-11 items-center justify-center rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-800 dark:bg-brand-500">+ Invite someone</button>
    </div>
    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
      @forelse ($members as $m)
        @php $role = $m->roles->first()?->name; $off = (int) $m->status === 1; @endphp
        <li class="flex items-center gap-3 px-5 py-3.5">
          <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ collect(explode(' ', $m->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-gray-900 dark:text-white/90">{{ $m->name }} @if ($m->is(auth()->user()))<span class="text-xs text-gray-400">(you)</span>@endif</p>
            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $m->email }}{{ $m->phone ? ' · '.$m->phone : '' }}</p>
          </div>
          <span class="hidden rounded-full px-2.5 py-0.5 text-xs font-medium sm:inline {{ in_array($role, ['admin', 'sysadmin'], true) ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' }}">{{ $role ? ucfirst(str_replace('_', ' ', $role)) : 'No role' }}</span>
          @if ($off)<span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700">No access</span>@endif
          @php $editData = ['id' => $m->id, 'name' => $m->name, 'phone' => $m->phone, 'role' => $role, 'active' => ! $off]; @endphp
          <button type="button" class="text-sm text-gray-700 hover:underline dark:text-gray-300" @click="openForm({{ \Illuminate\Support\Js::from($editData) }})">Edit</button>
          @if (! $off && ! $m->is(auth()->user()))
            <button type="button" class="text-sm text-red-600 hover:underline" @click="switchOff({{ $m->id }}, @js($m->name))">Switch off</button>
          @endif
        </li>
      @empty
        <li class="px-5 py-10 text-center text-sm text-gray-500">Nobody yet. Invite your first team member.</li>
      @endforelse
    </ul>
  </div>

  {{-- What each role can do --}}
  @if ($scopeParam !== 'staff')
  <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white/90">What each role can do</h3>
      <p class="text-sm text-gray-500 dark:text-gray-400">Full = open, change and delete · Edit = open and change · View = open only.@role('sysadmin') Change these on <a href="{{ route('roles.index') }}" class="underline">Roles &amp; permissions</a>.@endrole</p>
    </div>
    <div class="overflow-x-auto">
      <table class="data-table min-w-full text-left text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-gray-900 dark:text-gray-400">
          <tr><th class="px-5 py-3 font-medium">Area</th>@foreach (\App\Support\Access::COMPANY_ROLES as $r => $label)<th class="px-3 py-3 text-center font-medium">{{ ucfirst(str_replace('_', ' ', $r)) }}</th>@endforeach</tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          @foreach ($matrix as $area => $byRole)
            <tr><td class="whitespace-nowrap px-5 py-2.5 text-gray-800 dark:text-gray-200">{{ $area }}</td>
              @foreach ($byRole as $level)
                <td class="px-3 py-2.5 text-center">@if ($level)<span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ ['full' => 'bg-emerald-50 text-emerald-700', 'edit' => 'bg-sky-50 text-sky-700', 'view' => 'bg-gray-100 text-gray-600'][$level] }}">{{ ucfirst($level) }}</span>@else<span class="text-gray-300">—</span>@endif</td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Invite / edit --}}
  <div x-show="formOpen" x-cloak class="fixed inset-0 z-[99999] flex items-center justify-center p-4" @keydown.escape.window="formOpen = false">
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="formOpen = false"></div>
    <form @submit.prevent="save()" class="relative w-full max-w-[520px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-8">
      <h4 class="mb-1 text-xl font-semibold text-gray-800 dark:text-white/90" x-text="form.id ? 'Edit ' + form.name : 'Invite someone'"></h4>
      <p class="mb-5 text-sm text-gray-500" x-text="form.id ? 'Change their role or switch their login off.' : 'They get an email (and SMS if you add a phone) to set their own password.'"></p>
      <div class="space-y-4">
        <div><label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label><input x-model="form.name" required maxlength="120" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"><p x-show="errors.name" class="text-xs text-red-600" x-text="errors.name"></p></div>
        <div x-show="!form.id"><label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label><input x-model="form.email" type="email" :required="!form.id" maxlength="190" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"><p x-show="errors.email" class="text-xs text-red-600" x-text="errors.email"></p></div>
        <div><label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone (optional)</label><input x-model="form.phone" type="tel" maxlength="40" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90"></div>
        <div><label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
          <select x-model="form.role" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
            @foreach ($roles as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
          </select><p x-show="errors.role" class="text-xs text-red-600" x-text="errors.role"></p></div>
        <label x-show="form.id" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" x-model="form.active"> Can sign in</label>
      </div>
      <div class="mt-7 flex gap-3">
        <button type="button" @click="formOpen = false" class="h-11 flex-1 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
        <button type="submit" :disabled="busy" class="h-11 flex-1 rounded-lg bg-gray-900 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-brand-500" x-text="busy ? 'Saving…' : (form.id ? 'Save' : 'Send invitation')"></button>
      </div>
    </form>
  </div>
</div>

<script>
  function teamPage(base, scope) {
    const send = async (method, url, body) => {
      const r = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: body ? JSON.stringify(body) : undefined });
      const d = await r.json().catch(() => ({})); if (!r.ok) { const e = new Error(d.message || 'Failed'); e.errors = d.errors || {}; throw e; } return d;
    };
    return {
      formOpen: false, busy: false, errors: {}, notice: '', ok: true, link: null, form: {},
      openForm(m) { this.errors = {}; this.form = m ? { ...m } : { id: null, name: '', email: '', phone: '', role: @js(array_key_first($roles)), active: true }; this.formOpen = true; },
      async save() {
        this.busy = true; this.errors = {};
        try {
          const d = this.form.id ? await send('PUT', `${base}/${this.form.id}?company=${scope}`, this.form) : await send('POST', `${base}?company=${scope}`, this.form);
          this.formOpen = false; this.ok = true; this.notice = d.message; this.link = d.link || null;
          (window.schargeRefresh || (() => location.reload()))();
        } catch (e) { this.errors = Object.fromEntries(Object.entries(e.errors || {}).map(([k, v]) => [k, v[0]])); if (!Object.keys(this.errors).length) { this.ok = false; this.notice = e.message; this.formOpen = false; } }
        this.busy = false;
      },
      async switchOff(id, name) {
        if (!confirm(`Switch off ${name}'s login? Their records stay.`)) return;
        try { const d = await send('DELETE', `${base}/${id}?company=${scope}`); this.ok = true; this.notice = d.message; (window.schargeRefresh || (() => location.reload()))(); }
        catch (e) { this.ok = false; this.notice = e.message; }
      },
    };
  }
</script>
@endsection
