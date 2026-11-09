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
  @php $menuItem = 'text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300'; @endphp
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]" x-data="{ q: '', role: '', st: '' }">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Team directory</h3>
        @if ($filterRole)<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Showing {{ str_replace('_', ' ', $filterRole) }} only · <a href="?company={{ $scopeParam }}" class="underline">show everyone</a></p>@endif
      </div>
      <div class="flex flex-wrap items-center gap-3 lg:justify-end">
        <div class="relative">
          <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
          <input type="search" x-model="q"  placeholder="Search name, email or phone…" aria-label="Search name, email or phone…" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        <div class="relative">
          <select x-model="role" aria-label="Role" class="dark:bg-dark-900 h-11 w-full min-w-[140px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="">All roles</option>
            @foreach ($byRole->keys() as $r)<option value="{{ $r }}">{{ $label($r) }}</option>@endforeach
          </select>
          <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
        </div>
        <div class="relative">
          <select x-model="st" aria-label="Status" class="dark:bg-dark-900 h-11 w-full min-w-[140px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="">Any status</option><option value="active">Active</option><option value="invited">Invited</option><option value="off">No access</option>
          </select>
          <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
        </div>
        <button type="button" @click="openForm(null)" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add member</button>
      </div>
    </div>
    <div class="w-full overflow-x-auto">
      <table class="data-table w-full table-auto">
        <thead><tr class="border-b border-gray-200 dark:border-gray-800"><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Member</th><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Role</th><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Phone</th><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Status</th><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Joined</th><th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Action</th></tr></thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          @forelse ($people as $p)
            @php $m = $p['m']; $search = mb_strtolower($m->name.' '.$m->email.' '.$m->phone); $editData = ['id' => $m->id, 'name' => $m->name, 'phone' => $m->phone, 'role' => $p['role'], 'active' => $p['status'] !== 'off']; @endphp
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900" x-show="(!q || @js($search).includes(q.toLowerCase())) && (!role || role === @js($p['role'] ?? 'none')) && (!st || st === @js($p['status']))">
              <td class="p-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  @if ($m->avatar)
                    <img src="{{ \Illuminate\Support\Str::startsWith($m->avatar, ['http', '/']) ? $m->avatar : asset('storage/'.$m->avatar) }}" alt="" class="h-8 w-8 flex-shrink-0 rounded-full object-cover">
                  @else
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100"><span class="text-sm font-medium text-purple-600">{{ collect(explode(' ', $m->name))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('') }}</span></div>
                  @endif
                  <div class="min-w-0">
                    <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $m->name }} @if ($m->is(auth()->user()))<span class="text-xs font-normal text-gray-400">(you)</span>@endif</span>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $m->email }}
                      @if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'email_confirmed_at'))
                        · <span class="{{ $m->email_confirmed_at ? 'text-emerald-600' : 'text-amber-600' }}">{{ $m->email_confirmed_at ? 'email confirmed' : 'email not confirmed' }}</span>
                      @endif
                    </p>
                  </div>
                </div>
              </td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $label($p['role']) }}</td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><x-phone :number="$m->phone" /></td>
              <td class="p-4 whitespace-nowrap"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $pill[$p['status']][1] }}">{{ $pill[$p['status']][0] }}</span></td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ optional($m->created_at)->format('j M, Y') ?? '—' }}</td>
              <td class="p-4 whitespace-nowrap text-right">
                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions"><svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg></button>
  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
    <x-reverify-email :user="$m" label="Re-verify email" class="flex w-full rounded-lg px-3 py-2 text-left" />
    <button type="button" class="{{ $menuItem }}" @click="open = false; openForm({{ \Illuminate\Support\Js::from($editData) }})">Edit</button>
    @if ($p['status'] !== 'off' && ! $m->is(auth()->user()))
      <button type="button" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10" @click="open = false; switchOff({{ $m->id }}, @js($m->name))">Switch off</button>
    @endif
  </div>
</div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="p-4 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Nobody yet</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Add your first team member.</p>
  </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- What each role can do --}}
  @if ($scopeParam !== 'staff')
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">What each role can do</h3>
      <p class="text-sm text-gray-500 dark:text-gray-400">Full = open, change and delete · Edit = open and change · View = open only.@role('sysadmin') Change these on <a href="{{ route('roles.index') }}" class="underline">Roles &amp; permissions</a>.@endrole</p>
    </div>
    <div class="overflow-x-auto">
      <table class="data-table w-full table-auto">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-800"><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Area</th>@foreach (\App\Support\Access::COMPANY_ROLES as $r => $label)<th class="p-4 text-center text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">{{ ucfirst(str_replace('_', ' ', $r)) }}</th>@endforeach</tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          @foreach ($matrix as $area => $byRole)
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900"><td class="p-4 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">{{ $area }}</td>
              @foreach ($byRole as $level)
                <td class="p-4 whitespace-nowrap text-center">@if ($level)<span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ ['full' => 'bg-emerald-50 text-emerald-700', 'edit' => 'bg-sky-50 text-sky-700', 'view' => 'bg-gray-100 text-gray-600'][$level] }}">{{ ucfirst($level) }}</span>@else<span class="text-gray-300">—</span>@endif</td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Invite / edit --}}
  <div @keydown.escape.window="formOpen = false">
    <div x-show="formOpen" x-cloak @click="formOpen = false" class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
    <div x-show="formOpen" x-cloak x-transition:enter="transition transform ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999" style="width: 32rem; max-width: calc(100% - 2rem);">
    <div class="p-6 lg:p-10">
    <button type="button" @click="formOpen = false" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11" aria-label="Close"><svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" /></svg></button>
    <form @submit.prevent="save()">
      <h4 class="mb-1 text-lg font-medium text-gray-800 dark:text-white/90" x-text="form.id ? 'Edit ' + form.name : 'Invite someone'"></h4>
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
      <div class="mt-7 flex justify-end gap-3">
        <button type="button" @click="formOpen = false" class="h-11 rounded-lg px-4 border border-gray-300 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
        <button type="submit" :disabled="busy" class="h-11 rounded-lg px-5 bg-gray-900 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-brand-500" x-text="busy ? 'Saving…' : (form.id ? 'Save' : 'Send invitation')"></button>
      </div>
    </form>
    </div>
    </div>
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
