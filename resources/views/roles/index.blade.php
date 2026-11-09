{{-- Roles & permissions (Sharet design). Data: RoleController@index as JSON. Built-in roles keep their names;
     their permissions can change. Editing opens a slide-over: permissions grouped by area, switches, "turn all on". --}}
@extends('layouts.app')
@section('title', 'Roles & permissions')

@section('content')
<div x-data="rolesPage('{{ route('roles.index') }}')">
  <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Roles & permissions</h1>
      <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Choose what each role can open, change and delete. Changes apply on the person's next page. Invite people and give them a role on <a href="{{ route('team.index') }}" class="underline">Team &amp; access</a>.</p>
    </div>
    <button type="button" @click="edit(null)" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-800 dark:bg-brand-500">+ New role</button>
  </div>

  <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <template x-for="s in [['Roles', stats.roles, stats.roles_hint], ['Custom roles', stats.custom, stats.custom_hint], ['Permissions', stats.permissions, stats.permissions_hint], ['People with a role', stats.people, stats.people_hint]]">
      <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="s[0]"></p>
        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white" x-text="s[1] ?? '—'"></p>
        <p class="mt-1 text-xs text-gray-500" x-text="s[2]"></p>
      </div>
    </template>
  </div>

  <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Roles</h3>
      <input type="search" x-model="search" placeholder="Search roles…" aria-label="Search roles" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 sm:w-[280px]">
    </div>
    <div class="overflow-x-auto">
      <table class="data-table min-w-full text-left text-sm">
        <thead class="border-y border-gray-100 bg-gray-50 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
          <tr><th class="px-6 py-3 font-medium">Role</th><th class="px-6 py-3 font-medium">Access</th><th class="px-6 py-3 font-medium">People</th><th class="px-6 py-3 font-medium">Type</th><th class="px-6 py-3 text-right font-medium">Action</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <tr x-show="loading"><td colspan="5" class="px-6 py-12 text-center text-gray-500">Loading…</td></tr>
          <template x-for="r in filtered" :key="r.id">
            <tr class="text-gray-700 hover:bg-gray-50/60 dark:text-gray-400 dark:hover:bg-white/[0.02]">
              <td class="px-6 py-3"><p class="font-medium text-gray-800 dark:text-white/90" x-text="r.label"></p><p class="max-w-md text-xs text-gray-500 dark:text-gray-400" x-text="r.description"></p></td>
              <td class="whitespace-nowrap px-6 py-3">
                <div class="flex items-center gap-3">
                  <div class="h-1.5 w-24 rounded-full bg-gray-100 dark:bg-white/5"><div class="h-1.5 rounded-full bg-gray-900 dark:bg-brand-500" :style="`width: ${r.name === 'sysadmin' ? 100 : (total ? Math.round(r.permissions.length / total * 100) : 0)}%`"></div></div>
                  <span class="text-xs" x-text="r.name === 'sysadmin' ? 'Everything' : r.permissions.length + ' of ' + total"></span>
                </div>
              </td>
              <td class="whitespace-nowrap px-6 py-3" x-text="r.users"></td>
              <td class="whitespace-nowrap px-6 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="r.protected ? 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' : 'bg-sky-50 text-sky-700'" x-text="r.protected ? 'Built-in' : 'Custom'"></span></td>
              <td class="whitespace-nowrap px-6 py-3 text-right">
                <button type="button" @click="edit(r)" class="text-sm font-medium text-gray-800 hover:underline dark:text-gray-200" x-text="r.name === 'sysadmin' ? 'View' : 'Edit permissions'"></button>
                <a :href="'{{ route('team.index') }}?role=' + r.name" class="ml-3 text-sm text-gray-500 hover:underline">People</a>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>

  {{-- Slide-over editor --}}
  <div x-show="open" x-cloak class="fixed inset-0 z-[99999]" @keydown.escape.window="open = false">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="open = false"></div>
    <aside x-show="open" x-transition class="absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-white shadow-xl dark:bg-gray-900">
      <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="form.id ? 'Edit ' + form.label : 'New role'"></h3>
        <button type="button" @click="open = false" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5" aria-label="Close"><x-icon name="x-mark" class="inline-block h-4 w-4 shrink-0 align-[-0.15em]" /></button>
      </div>
      <form id="role-form" @submit.prevent="save()" class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="role-name">Role name</label>
          <input id="role-name" x-model="form.name" :disabled="form.protected" required class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm disabled:opacity-60 dark:border-gray-700 dark:text-white/90" placeholder="finance_officer">
          <p class="mt-1 text-xs text-gray-500">Lowercase with underscores. Built-in roles cannot be renamed.</p>
          <p x-show="errors.name" class="mt-1 text-xs text-red-600" x-text="errors.name"></p>
        </div>
        <p x-show="form.name === 'sysadmin'" class="rounded-xl bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">The system admin always has every permission.</p>
        <input type="search" x-model="permQuery" placeholder="Find a permission…" aria-label="Find a permission" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:text-white/90">
        <template x-for="g in visibleGroups" :key="g.key">
          <section class="rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
              <div><h4 class="font-medium text-gray-900 dark:text-white" x-text="g.label"></h4>
                <p class="text-xs text-gray-500" x-text="g.permissions.filter(p => form.permissions.includes(p.id)).length + ' of ' + g.permissions.length + ' on'"></p></div>
              <button type="button" x-show="form.name !== 'sysadmin'" @click="toggleGroup(g)" class="text-xs font-medium text-gray-700 hover:underline dark:text-gray-300" x-text="groupOn(g) ? 'Turn all off' : 'Turn all on'"></button>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
              <template x-for="p in g.permissions.filter(p => match(p))" :key="p.id">
                <li class="flex items-center justify-between gap-4 px-4 py-3">
                  <span class="min-w-0"><span class="block text-sm text-gray-800 dark:text-gray-200" x-text="p.label"></span><code class="text-[11px] text-gray-400" x-text="p.name"></code></span>
                  <button type="button" role="switch" :aria-checked="on(p.id)" :disabled="form.name === 'sysadmin'" @click="toggle(p.id)"
                          class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-60" :class="on(p.id) ? 'bg-gray-900 dark:bg-brand-500' : 'bg-gray-200 dark:bg-white/10'">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition" :class="on(p.id) ? 'translate-x-5' : 'translate-x-0.5'"></span>
                  </button>
                </li>
              </template>
            </ul>
          </section>
        </template>
      </form>
      <div class="flex items-center gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-800">
        <button type="button" x-show="form.id && !form.protected" @click="destroy()" class="mr-auto rounded-lg px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Delete role</button>
        <span x-show="message" class="mr-auto text-sm" :class="ok ? 'text-emerald-600' : 'text-red-600'" x-text="message"></span>
        <button type="button" @click="open = false" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
        <button type="submit" form="role-form" x-show="form.name !== 'sysadmin'" :disabled="busy" class="h-11 rounded-lg bg-gray-900 px-5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-brand-500" x-text="form.id ? 'Save changes' : 'Create role'"></button>
      </div>
    </aside>
  </div>
</div>

<script>
  function rolesPage(url) {
    const send = async (method, u, body) => {
      const r = await fetch(u, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: body ? JSON.stringify(body) : undefined });
      const d = await r.json().catch(() => ({})); if (!r.ok) { const e = new Error(d.message || 'Failed'); e.errors = d.errors || {}; throw e; } return d;
    };
    return {
      roles: [], groups: [], stats: {}, loading: true, search: '', permQuery: '', busy: false, errors: {}, open: false, message: '', ok: true,
      form: { id: null, name: '', label: '', protected: false, permissions: [] },
      async init() { await this.load(); },
      async load() { const b = await (await fetch(url, { headers: { Accept: 'application/json' } })).json(); Object.assign(this, { roles: b.roles, groups: b.groups, stats: b.stats, loading: false }); },
      get total() { return this.groups.reduce((n, g) => n + g.permissions.length, 0); },
      get filtered() { const q = this.search.toLowerCase(); return this.roles.filter(r => (r.label + r.description).toLowerCase().includes(q)); },
      match(p) { const q = this.permQuery.toLowerCase(); return !q || (p.label + p.name).toLowerCase().includes(q); },
      get visibleGroups() { return this.groups.filter(g => g.permissions.some(p => this.match(p))); },
      on(id) { return this.form.name === 'sysadmin' || this.form.permissions.includes(id); },
      edit(r) {
        this.errors = {}; this.permQuery = ''; this.message = '';
        this.form = r ? { id: r.id, name: r.name, label: r.label, protected: r.protected, users: r.users, permissions: [...r.permissions] } : { id: null, name: '', label: '', protected: false, permissions: [] };
        this.open = true;
      },
      toggle(id) { const i = this.form.permissions.indexOf(id); i >= 0 ? this.form.permissions.splice(i, 1) : this.form.permissions.push(id); },
      groupOn(g) { return g.permissions.every(p => this.form.permissions.includes(p.id)); },
      toggleGroup(g) { const ids = g.permissions.map(p => p.id); this.form.permissions = this.groupOn(g) ? this.form.permissions.filter(id => !ids.includes(id)) : [...new Set([...this.form.permissions, ...ids])]; },
      async save() {
        this.busy = true; this.errors = {}; this.message = '';
        try {
          const d = await send(this.form.id ? 'PUT' : 'POST', this.form.id ? `${url}/${this.form.id}` : url, { name: this.form.name, permissions: this.form.permissions });
          this.ok = true; this.message = d.message; await this.load(); setTimeout(() => this.open = false, 700);
        } catch (e) { this.ok = false; this.message = e.message; this.errors = Object.fromEntries(Object.entries(e.errors || {}).map(([k, v]) => [k, v[0]])); }
        this.busy = false;
      },
      async destroy() {
        if (!confirm(`Delete ${this.form.label}? People with only this role lose the access it gave them.`)) return;
        try { await send('DELETE', `${url}/${this.form.id}`); this.open = false; await this.load(); } catch (e) { this.ok = false; this.message = e.message; }
      },
    };
  }
</script>
@endsection
