{{-- Settings › API keys (same design as Sharet Africa's API keys page). The full key appears once only. --}}
@extends('layouts.app')
@section('title', 'API keys')

@section('content')
@php
    $input = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30';
    $api = url('/api/v1');
    $scopes = ['collect' => 'Create pay links', 'meters' => 'Meter readings'];
@endphp

<div x-data="apiKeysPage('{{ url('api-keys') }}')" class="p-4 md:p-6">
  <div class="mb-6">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">API keys</h1>
    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
      Keys let your own systems (your website, app or back office) call scharge. Send one as
      <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-white/10">Authorization: Bearer sk_…</code>.
      We keep only a fingerprint of each key, so copy it when it is shown.
    </p>
  </div>

  @if ($company)
    <div class="mb-6 rounded-2xl border border-gray-200 bg-white px-5 py-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
      Your keys act for <b class="text-gray-900 dark:text-white">{{ $company->name }}</b> and only see its properties, tenants and invoices.
    </div>
  @endif

  {{-- Newly issued key: shown once --}}
  <div x-show="plain" x-cloak x-transition class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-500/30 dark:bg-emerald-500/10">
    <p class="mb-3 text-sm font-medium text-emerald-800 dark:text-emerald-200">Copy this key now. It will not be shown again. The examples below now use it.</p>
    <div class="flex flex-col gap-2 sm:flex-row">
      <input readonly :value="plain" class="{{ $input }} font-mono" @focus="$event.target.select()">
      <button type="button" @click="copy(plain)" class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700">Copy</button>
      <button type="button" @click="plain = null" class="h-11 shrink-0 rounded-lg px-4 text-sm font-medium text-emerald-800 hover:bg-emerald-100 dark:text-emerald-200 dark:hover:bg-emerald-500/10">I've saved it</button>
    </div>
  </div>

  {{-- How to use a key --}}
  <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">How to use your key</h3>
    <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">Show your tenants' bills on your own site, or send pay links from your own system. Calls carry your company's name.</p>
    <div x-data="{ lang: 'curl', get key() { return plain || 'sk_test_your_key_here' } }" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
      <ol class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
        @foreach ([
          ['Start with a test key', '(sk_test_…). It works while scharge payments run in the sandbox and never moves real money.'],
          ['Keep it on your server.', 'Put it in an environment variable. Never in a mobile app, a web page or a public repository.'],
          ['Send it on every request', 'as Authorization: Bearer <key>, with Accept: application/json.'],
          ['Check it works', 'with GET /me, then GET /invoices/{id} and POST /invoices/{id}/pay-link (needs "Create pay links").'],
          ['If a key leaks,', 'regenerate it from the table below: the old one stops working immediately.'],
        ] as $i => [$strong, $rest])
          <li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-bold text-white dark:bg-brand-500">{{ $i + 1 }}</span>
            <span><strong>{{ $strong }}</strong> {{ $rest }}</span></li>
        @endforeach
      </ol>

      <div class="min-w-0 space-y-4">
        <div class="flex items-center justify-between gap-3">
          <p class="text-sm font-medium text-gray-800 dark:text-white/90">Request</p>
          <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
            @foreach (['curl' => 'cURL', 'php' => 'PHP', 'js' => 'JavaScript'] as $k => $label)
              <button type="button" @click="lang = '{{ $k }}'" :class="lang === '{{ $k }}' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500'" class="rounded-md px-3 py-1.5 text-xs font-medium">{{ $label }}</button>
            @endforeach
          </div>
        </div>
        <pre class="overflow-x-auto rounded-xl bg-gray-950 p-4 text-xs leading-relaxed text-gray-100"><code x-show="lang === 'curl'" x-text="`curl -X POST {{ $api }}/invoices/123/pay-link \\\n  -H 'Authorization: Bearer ${key}' \\\n  -H 'Accept: application/json'`"></code><code x-show="lang === 'php'" x-cloak x-text="`$link = Http::withToken(env('SCHARGE_API_KEY')) // ${key}\n    ->acceptJson()\n    ->post('{{ $api }}/invoices/123/pay-link')\n    ->json('url');`"></code><code x-show="lang === 'js'" x-cloak x-text="`const res = await fetch('{{ $api }}/invoices/123/pay-link', {\n  method: 'POST',\n  headers: { Authorization: 'Bearer ' + process.env.SCHARGE_API_KEY, Accept: 'application/json' },\n}); // key: ${key}\nconst { url } = await res.json();`"></code></pre>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <p class="mb-2 text-sm font-medium text-gray-800 dark:text-white/90">Response <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">201</span></p>
            <pre class="overflow-x-auto rounded-xl bg-gray-950 p-4 text-xs text-gray-100">{
  "object": "pay_link",
  "provider": "sharet",
  "amount": "35000.00",
  "url": "https://sharet.africa/r/…",
  "invoice": { "id": 123, "balance": "35000.00" }
}</pre>
          </div>
          <div>
            <p class="mb-2 text-sm font-medium text-gray-800 dark:text-white/90">Bad or disabled key <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">401</span></p>
            <pre class="overflow-x-auto rounded-xl bg-gray-950 p-4 text-xs text-gray-100">{
  "error": "invalid_api_key",
  "message": "Send a valid, enabled key…"
}</pre>
          </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">Base URL <code>{{ $api }}</code> · 60 requests a minute per key.</p>
      </div>
    </div>
  </div>

  <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center">
      <div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Your keys</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Test keys work while payments run in the sandbox. Live keys move real money.</p>
      </div>
      @if ($company)
        <button type="button" @click="creating = true; form = { name: '', environment: 'test', scopes: [], allowed_ips: '' }; errors = {}"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-800 dark:bg-brand-500">+ Add API key</button>
      @endif
    </div>

    <p x-show="!keys.length" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No keys yet.</p>

    <div x-show="keys.length" class="overflow-x-auto">
      <table class="data-table min-w-full text-left">
        <thead>
          <tr class="border-b border-gray-100 text-xs font-medium text-gray-500 dark:border-gray-800 dark:text-gray-400">
            <th class="px-5 py-3">Name</th><th class="px-5 py-3">Status</th>
            @if ($isAdmin)<th class="px-5 py-3">Company</th>@endif
            <th class="px-5 py-3">Created</th><th class="px-5 py-3">Last used</th><th class="px-5 py-3">On / off</th><th class="px-5 py-3">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <template x-for="k in keys" :key="k.id">
            <tr class="text-sm">
              <td class="whitespace-nowrap px-5 py-3">
                <p class="mb-1 flex items-center gap-2 text-gray-800 dark:text-gray-200">
                  <span x-text="k.name"></span>
                  <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase" :class="k.environment === 'live' ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-700'" x-text="k.environment"></span>
                </p>
                <code class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400" x-text="k.masked"></code>
                <div class="mt-1.5 flex flex-wrap gap-1">
                  <template x-for="sc in k.scopes"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300" x-text="sc"></span></template>
                  <span x-show="k.allowed_ips.length" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600 dark:bg-white/10 dark:text-gray-300" x-text="k.allowed_ips.length + ' IP' + (k.allowed_ips.length > 1 ? 's' : '')"></span>
                </div>
              </td>
              <td class="whitespace-nowrap px-5 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="k.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'" x-text="k.enabled ? 'Active' : 'Disabled'"></span></td>
              @if ($isAdmin)<td class="whitespace-nowrap px-5 py-3 text-gray-500 dark:text-gray-400" x-text="k.owner"></td>@endif
              <td class="whitespace-nowrap px-5 py-3 text-gray-500 dark:text-gray-400" x-text="k.created_at"></td>
              <td class="whitespace-nowrap px-5 py-3 text-gray-500 dark:text-gray-400" x-text="k.last_used_at"></td>
              <td class="whitespace-nowrap px-5 py-3">
                <button type="button" role="switch" :aria-checked="k.enabled" @click="toggle(k)" class="relative h-6 w-11 rounded-full transition" :class="k.enabled ? 'bg-gray-900 dark:bg-brand-500' : 'bg-gray-200 dark:bg-white/10'">
                  <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition" :class="k.enabled && 'translate-x-5'"></span>
                </button>
              </td>
              <td class="whitespace-nowrap px-5 py-3">
                <div class="flex gap-3 text-sm">
                  <button type="button" class="text-gray-700 hover:underline dark:text-gray-300" @click="editing = k; access = { scopes: k.scopes.filter(x => x !== 'read'), allowed_ips: k.allowed_ips.join(', ') }; errors = {}">Access</button>
                  <button type="button" class="text-gray-700 hover:underline dark:text-gray-300" @click="rotate(k)">Regenerate</button>
                  <button type="button" class="text-red-600 hover:underline" @click="destroy(k)">Delete</button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>

  {{-- Create --}}
  <div x-show="creating" x-cloak class="fixed inset-0 z-[99999] flex items-center justify-center p-4" @keydown.escape.window="creating = false">
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="creating = false"></div>
    <form x-show="creating" x-transition @submit.prevent="create()" class="relative w-full max-w-[560px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-8">
      <h4 class="mb-1 text-xl font-semibold text-gray-800 dark:text-white/90">Generate API key</h4>
      <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Name it after the system that will use it, so you can tell keys apart later.</p>
      <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="key-name">Application name</label>
      <input id="key-name" x-model="form.name" class="{{ $input }}" placeholder="e.g. Our website" maxlength="80" required>
      <p x-show="errors.name" class="mt-1 text-xs text-red-600" x-text="errors.name"></p>
      <fieldset class="mt-5">
        <legend class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">Environment</legend>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="flex cursor-pointer gap-3 rounded-xl border p-4 text-sm" :class="form.environment === 'test' ? 'border-gray-900 bg-gray-50 dark:border-brand-500 dark:bg-white/5' : 'border-gray-300 dark:border-gray-700'">
            <input type="radio" value="test" x-model="form.environment" class="mt-0.5"><span><span class="block font-medium text-gray-900 dark:text-white">Test · sandbox</span><span class="mt-1 block text-xs text-gray-500">Starts with sk_test_. No real money.</span></span>
          </label>
          <label class="flex cursor-pointer gap-3 rounded-xl border p-4 text-sm" :class="form.environment === 'live' ? 'border-red-500 bg-red-50 dark:bg-red-500/10' : 'border-gray-300 dark:border-gray-700'">
            <input type="radio" value="live" x-model="form.environment" class="mt-0.5"><span><span class="block font-medium text-gray-900 dark:text-white">Live</span><span class="mt-1 block text-xs text-gray-500">Starts with sk_live_. Real tenants, real money: only on your server.</span></span>
          </label>
        </div>
      </fieldset>
      <fieldset class="mt-5">
        <legend class="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">What it can do <span class="font-normal text-gray-500">(every key can read)</span></legend>
        @foreach ($scopes as $sc => $label)
          <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-gray-300 p-3 text-sm dark:border-gray-700"><input type="checkbox" value="{{ $sc }}" x-model="form.scopes" class="rounded"> {{ $label }}</label>
        @endforeach
      </fieldset>
      <div class="mt-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Only allow these IP addresses <span class="font-normal text-gray-500">(optional)</span></label>
        <input x-model="form.allowed_ips" class="{{ $input }}" placeholder="41.90.12.7, 196.201.0.0/16">
        <p x-show="errors.allowed_ips" class="mt-1 text-xs text-red-600" x-text="errors.allowed_ips"></p>
      </div>
      <div class="mt-8 flex gap-3">
        <button type="button" @click="creating = false" class="h-11 flex-1 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Close</button>
        <button type="submit" :disabled="busy" class="h-11 flex-1 rounded-lg bg-gray-900 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-brand-500">Generate API key</button>
      </div>
    </form>
  </div>

  {{-- Access: scopes and allowed IPs --}}
  <div x-show="editing" x-cloak class="fixed inset-0 z-[99999] flex items-center justify-center p-4" @keydown.escape.window="editing = null">
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" @click="editing = null"></div>
    <form x-show="editing" x-transition @submit.prevent="saveAccess()" class="relative w-full max-w-[560px] rounded-3xl bg-white p-6 dark:bg-gray-900 lg:p-8">
      <h4 class="mb-1 text-xl font-semibold text-gray-800 dark:text-white/90">Access for <span x-text="editing?.name"></span></h4>
      <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Changes apply to the next request.</p>
      <fieldset class="mt-5">
        @foreach ($scopes as $sc => $label)
          <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-gray-300 p-3 text-sm dark:border-gray-700"><input type="checkbox" value="{{ $sc }}" x-model="access.scopes" class="rounded"> {{ $label }}</label>
        @endforeach
      </fieldset>
      <div class="mt-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Only allow these IP addresses</label>
        <input x-model="access.allowed_ips" class="{{ $input }}" placeholder="41.90.12.7, 196.201.0.0/16">
        <p x-show="errors.allowed_ips" class="mt-1 text-xs text-red-600" x-text="errors.allowed_ips"></p>
      </div>
      <div class="mt-8 flex gap-3">
        <button type="button" @click="editing = null" class="h-11 flex-1 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Close</button>
        <button type="submit" :disabled="busy" class="h-11 flex-1 rounded-lg bg-gray-900 text-sm font-medium text-white hover:bg-gray-800 disabled:opacity-50 dark:bg-brand-500">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
  window.schargeSend = window.schargeSend || async function (method, url, body) {
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { const err = new Error(data.message || 'Request failed'); err.errors = data.errors || {}; throw err; }
    return data;
  };

  function apiKeysPage(base) {
    const firstErrors = e => Object.fromEntries(Object.entries(e.errors || {}).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
    return {
      keys: [], plain: null, creating: false, editing: null, form: { scopes: [] }, access: { scopes: [], allowed_ips: '' }, errors: {}, busy: false,
      async init() { this.keys = (await (await fetch(base, { headers: { Accept: 'application/json' } })).json()).keys; },
      put(k) { this.keys = this.keys.some(x => x.id === k.id) ? this.keys.map(x => x.id === k.id ? k : x) : [k, ...this.keys]; },
      copy(text) { navigator.clipboard?.writeText(text); },
      async create() {
        this.busy = true; this.errors = {};
        try { const r = await schargeSend('POST', base, this.form); this.put(r.key); this.plain = r.plain; this.creating = false; } catch (e) { this.errors = firstErrors(e); }
        this.busy = false;
      },
      async saveAccess() {
        this.busy = true; this.errors = {};
        try { const r = await schargeSend('PATCH', `${base}/${this.editing.id}/settings`, this.access); this.put(r.key); this.editing = null; } catch (e) { this.errors = firstErrors(e); }
        this.busy = false;
      },
      async toggle(k) { try { const r = await schargeSend('PATCH', `${base}/${k.id}/toggle`); this.put(r.key); } catch (e) {} },
      async rotate(k) {
        if (!confirm(`Regenerate "${k.name}"? The current key stops working immediately.`)) return;
        try { const r = await schargeSend('POST', `${base}/${k.id}/rotate`); this.put(r.key); this.plain = r.plain; window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) {}
      },
      async destroy(k) {
        if (!confirm(`Delete "${k.name}"? Requests using it get 401 from now on.`)) return;
        try { await schargeSend('DELETE', `${base}/${k.id}`); this.keys = this.keys.filter(x => x.id !== k.id); } catch (e) {}
      },
    };
  }
</script>
@endsection
