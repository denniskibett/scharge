{{-- Team & access (Brevemaster pattern): people + invite, then what each role can do. Data: TeamController@index --}}
@extends('layouts.app')
@section('title', 'Team & access')

@section('content')
@php
  $statusOf = fn ($m) => (int) $m->status === 1 ? 'off' : (isset($invited[$m->email]) ? 'invited' : 'active');
  $roleOf = fn ($m) => $m->roles->first()?->name;
  $people = $members->map(fn ($m) => ['m' => $m, 'role' => $roleOf($m), 'status' => $statusOf($m)]);
  $counts = $people->countBy('status');
  $byRole = $people->groupBy(fn ($p) => $p['role'] ?? 'none')->map->count()->sortDesc();
  $palette = ['bg-brand-500', 'bg-sky-400', 'bg-orange-400', 'bg-pink-400', 'bg-emerald-500', 'bg-violet-400', 'bg-gray-400'];
  $label = fn ($r) => $r === 'none' || ! $r ? 'No role' : ucfirst(str_replace('_', ' ', $r));
  $joinedThisMonth = $members->filter(fn ($m) => $m->created_at && $m->created_at->gte(now()->startOfMonth()))->count();
  $cards = [
    ['label' => 'Team members', 'value' => number_format($members->count()), 'icon' => 'user-group', 'tone' => 'brand', 'hint' => 'Everyone who can be given access'],
    ['label' => 'Active', 'value' => number_format($counts['active'] ?? 0), 'icon' => 'check-circle', 'tone' => 'green', 'hint' => 'Can sign in now'],
    ['label' => 'Invited', 'value' => number_format($counts['invited'] ?? 0), 'icon' => 'envelope', 'tone' => 'amber', 'hint' => 'Password not set yet'],
    ['label' => 'No access', 'value' => number_format($counts['off'] ?? 0), 'icon' => 'no-symbol', 'tone' => 'red', 'hint' => 'Switched off'],
  ];
  $pill = ['active' => ['Active', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'], 'invited' => ['Invited', 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400'], 'off' => ['No access', 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400']];
@endphp
<div class="space-y-6" x-data="teamPage('{{ route('team.index') }}', '{{ $scopeParam }}')">
  {{-- Header --}}
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Team &amp; access</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Here's who can sign in to {{ $scopeName }}, and what each role can do.</p>
    </div>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      @if ($companies->isNotEmpty())
        <select class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 sm:w-64" onchange="location.search='?company='+this.value" aria-label="Team for">
          <option value="staff" @selected($scopeParam === 'staff')>scharge team (staff)</option>
          @foreach ($companies as $c)<option value="{{ $c->id }}" @selected((string) $scopeParam === (string) $c->id)>{{ $c->name }}</option>@endforeach
        </select>
      @endif
      <button type="button" @click="openForm(null)" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
        Add member
      </button>
    </div>
  </div>

  <div x-show="notice" x-cloak class="rounded-xl px-4 py-3 text-sm" :class="ok ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'">
    <span x-text="notice"></span>
    <div x-show="link" class="mt-2 flex gap-2"><input readonly :value="link" class="h-9 flex-1 rounded border border-gray-300 px-2 text-xs"><button type="button" class="text-xs underline" @click="navigator.clipboard?.writeText(link)">Copy</button></div>
  </div>

  {{-- Quick stats --}}
  @include('partials.card.row', ['cards' => $cards])

  {{-- People by role --}}
  <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">People by role</h3>
        <p class="mt-1 flex items-baseline gap-2"><span class="text-title-sm font-bold text-gray-800 dark:text-white/90">{{ number_format($members->count()) }}</span>
          <span class="text-sm text-gray-500">{{ $joinedThisMonth ? $joinedThisMonth.' joined this month' : 'nobody new this month' }}</span></p>
      </div>
    </div>
    @if ($members->isNotEmpty())
      <div class="mb-4 flex h-8 w-full gap-1 overflow-hidden rounded-lg">
        @foreach ($byRole as $r => $n)
          <div class="{{ $palette[$loop->index % count($palette)] }} h-full first:rounded-l-lg last:rounded-r-lg" style="width: {{ round($n / $members->count() * 100, 2) }}%" title="{{ $label($r) }}: {{ $n }}"></div>
        @endforeach
      </div>
      <ul class="divide-y divide-gray-100 rounded-xl border border-gray-100 dark:divide-gray-800 dark:border-gray-800">
        @foreach ($byRole as $r => $n)
          <li class="flex items-center justify-between px-4 py-3 text-sm">
            <a href="?company={{ $scopeParam }}&role={{ $r === 'none' ? '' : $r }}" class="flex items-center gap-2 font-medium text-gray-800 hover:text-brand-500 dark:text-white/90"><span class="h-2.5 w-2.5 rounded-full {{ $palette[$loop->index % count($palette)] }}"></span>{{ $label($r) }}</a>
            <span class="text-gray-800 dark:text-white/90"><span class="font-semibold">{{ round($n / $members->count() * 100) }}%</span> <span class="text-gray-500">({{ $n }})</span></span>
          </li>
        @endforeach
      </ul>
    @endif
  </div>

  {{-- Directory --}}
  <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="{ q: '', role: '', st: '' }">
    <div class="flex flex-col gap-3 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Team directory</h3>
        @if ($filterRole)<p class="mt-1 text-xs text-gray-500">Showing {{ str_replace('_', ' ', $filterRole) }} only · <a href="?company={{ $scopeParam }}" class="underline">show everyone</a></p>@endif
      </div>
      <div class="flex flex-col gap-3 sm:flex-row">
        <div class="relative">
          <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
          <input x-model="q" type="search" placeholder="Search name, email or phone…" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent pl-10 pr-3 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-700 dark:text-white/90 sm:w-72">
        </div>
        <select x-model="role" class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
          <option value="">All roles</option>
          @foreach ($byRole->keys() as $r)<option value="{{ $r }}">{{ $label($r) }}</option>@endforeach
        </select>
        <select x-model="st" class="h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
          <option value="">Any status</option><option value="active">Active</option><option value="invited">Invited</option><option value="off">No access</option>
        </select>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="data-table min-w-full">
        <thead><tr><th>Member</th><th>Role</th><th>Phone</th><th>Status</th><th>Joined</th><th class="!text-right">Action</th></tr></thead>
        <tbody>
          @forelse ($people as $p)
            @php $m = $p['m']; $search = mb_strtolower($m->name.' '.$m->email.' '.$m->phone); $editData = ['id' => $m->id, 'name' => $m->name, 'phone' => $m->phone, 'role' => $p['role'], 'active' => $p['status'] !== 'off']; @endphp
            <tr x-show="(!q || @js($search).includes(q.toLowerCase())) && (!role || role === @js($p['role'] ?? 'none')) && (!st || st === @js($p['status']))">
              <td>
                <div class="flex items-center gap-3">
                  @if ($m->avatar)
                    <img src="{{ \Illuminate\Support\Str::startsWith($m->avatar, ['http', '/']) ? $m->avatar : asset('storage/'.$m->avatar) }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
                  @else
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">{{ collect(explode(' ', $m->name))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span>
                  @endif
                  <div class="min-w-0">
                    <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ $m->name }} @if ($m->is(auth()->user()))<span class="text-xs font-normal text-gray-400">(you)</span>@endif</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $m->email }}</p>
                  </div>
                </div>
              </td>
              <td class="text-gray-700 dark:text-gray-300">{{ $label($p['role']) }}</td>
              <td class="text-gray-700 dark:text-gray-300"><x-phone :number="$m->phone" /></td>
              <td><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pill[$p['status']][1] }}">{{ $pill[$p['status']][0] }}</span></td>
              <td class="text-gray-700 dark:text-gray-300">{{ optional($m->created_at)->format('j M, Y') ?? '—' }}</td>
              <td class="text-right">
                <div class="inline-flex items-center gap-3">
                  <button type="button" class="text-sm font-medium text-gray-700 hover:text-brand-500 dark:text-gray-300" @click="openForm({{ \Illuminate\Support\Js::from($editData) }})">Edit</button>
                  @if ($p['status'] !== 'off' && ! $m->is(auth()->user()))
                    <button type="button" class="text-sm font-medium text-red-600 hover:text-red-700" @click="switchOff({{ $m->id }}, @js($m->name))">Switch off</button>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="py-10 text-center text-sm text-gray-500">Nobody yet. Add your first team member.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
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
