{{-- Roles & permissions (Sharet design). Data: RoleController@index as JSON. Built-in roles keep their names;
     their permissions can change. Editing opens a slide-over: permissions grouped by area, switches, "turn all on". --}}
@extends('layouts.app')
@section('title', 'Roles & permissions')

@section('content')
@include('partials.card.roles')
<div x-data="rolesPage('{{ route('roles.index') }}')">
  <div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Roles & permissions</h1>
    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">Choose what each role can open, change and delete. Changes apply on the person's next page. Invite people and give them a role on <a href="{{ route('team.index') }}" class="underline">Team &amp; access</a>.</p>
  </div>

  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Roles</h3>
      <div class="flex flex-wrap items-center gap-3 lg:justify-end">
        <div class="relative">
          <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
          <input type="search" x-model="search"  placeholder="Search roles…" aria-label="Search roles…" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[200px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
        </div>
        <button type="button" @click="edit(null)" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> New role</button>
      </div>
    </div>
    <div class="w-full overflow-x-auto">
      <table class="data-table w-full table-auto">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-800"><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Role</th><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Access</th><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">People</th><th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Type</th><th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Action</th></tr>
        </thead>
        <tbody class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
          <tr x-show="loading"><td colspan="5" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">Loading…</td></tr>
          <tr x-show="!loading && filtered.length === 0" x-cloak>
            <td colspan="5" class="p-4 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No roles found</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try a different search.</p>
  </td>
          </tr>
          <template x-for="r in filtered" :key="r.id">
            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
              <td class="p-4"><p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="r.label"></p><p class="max-w-md text-xs text-gray-500 dark:text-gray-400" x-text="r.description"></p></td>
              <td class="p-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <div class="h-1.5 w-24 rounded-full bg-gray-100 dark:bg-white/5"><div class="h-1.5 rounded-full bg-gray-900 dark:bg-brand-500" :style="`width: ${r.name === 'sysadmin' ? 100 : (total ? Math.round(r.permissions.length / total * 100) : 0)}%`"></div></div>
                  <span class="text-xs text-gray-500 dark:text-gray-400" x-text="r.name === 'sysadmin' ? 'Everything' : r.permissions.length + ' of ' + total"></span>
                </div>
              </td>
              <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="r.users"></td>
              <td class="p-4 whitespace-nowrap"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="r.protected ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'" x-text="r.protected ? 'Built-in' : 'Custom'"></span></td>
              <td class="p-4 whitespace-nowrap text-right">
                <button type="button" @click="edit(r)" class="text-sm font-medium text-blue-600 hover:text-blue-900 dark:text-blue-400" x-text="r.name === 'sysadmin' ? 'View' : 'Edit permissions'"></button>
                <a :href="'{{ route('team.index') }}?role=' + r.name" class="ml-3 text-sm text-gray-500 hover:underline dark:text-gray-400">People</a>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>

  {{-- Slide-over editor --}}
  <div @keydown.escape.window="open = false">
    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
    <aside x-show="open" x-cloak x-transition:enter="transition transform ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition transform ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full" class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999" style="width: 42rem; max-width: calc(100% - 2rem);">
     <div class="p-6 lg:p-10">
      <button type="button" @click="open = false" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11" aria-label="Close"><svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" /></svg></button>
      <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90" x-text="form.id ? 'Edit ' + form.label : 'New role'"></h4>
      <form id="role-form" @submit.prevent="save()" class="space-y-6">
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
      <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-6 dark:border-gray-800">
        <button type="button" x-show="form.id && !form.protected" @click="destroy()" class="mr-auto rounded-lg px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Delete role</button>
        <span x-show="message" class="mr-auto text-sm" :class="ok ? 'text-emerald-600' : 'text-red-600'" x-text="message"></span>
        <button type="button" @click="open = false" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
        <button type="submit" form="role-form" x-show="form.name !== 'sysadmin'" :disabled="busy" class="h-11 rounded-lg bg-gray-900 px-5 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-brand-500" x-text="form.id ? 'Save changes' : 'Create role'"></button>
      </div>
      </form>
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
