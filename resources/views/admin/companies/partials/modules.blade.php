{{-- Admin › Companies › {company} › Modules: paid modules for this company (App\Support\Modules). Sysadmins switch
     each one on or off (optionally until a date) or hand it back to the plan; account managers see it read-only. --}}
@php
  use App\Http\Controllers\Admin\CompanyModuleController as CM;
  use App\Support\Modules;
  $states = Modules::states($company->id);
  $rows = collect($states)->map(fn ($s, $k) => CM::present($k, $s))->values();
  $planList = Modules::planModules($company->id);
  $canEdit = auth()->user()->hasRole('sysadmin');
  $core = collect(Modules::CATALOGUE)->filter(fn ($m) => ! empty($m['core']))->pluck('label');
  $field = 'h-9 rounded-lg border border-gray-300 bg-transparent px-2.5 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp
<script>
function billingGrace(b, companyId) {
  return {
    b, until: '', note: '', saving: false, today: new Date().toISOString().slice(0, 10),
    async save(until) {
      this.saving = true;
      try {
        const res = await fetch(`/admin/companies/${companyId}/billing-grace`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
          body: JSON.stringify({ until, note: this.note || null }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Could not save.');
        this.b = data.billing; this.until = ''; this.note = '';
        if (data.log) window.dispatchEvent(new CustomEvent('module-log', { detail: data.log }));
      } catch (e) { alert(e.message); } finally { this.saving = false; }
    },
  };
}
function companyModules(rows, logs, companyId) {
  return {
    rows: rows.map(r => ({ ...r, draft: null, noteDraft: '', saving: false })),
    logs,
    today: new Date().toISOString().slice(0, 10),
    async save(m) {
      m.saving = true;
      try {
        const res = await fetch(`/admin/companies/${companyId}/modules/${m.key}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || window.csrfToken },
          body: JSON.stringify({ state: m.draft ?? m.state, expires_at: (m.draft ?? m.state) === 'plan' ? null : (m.expires_at || null), note: m.noteDraft || null }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Could not save.');
        Object.assign(m, data.state, { draft: null, noteDraft: '' });
        if (data.log) this.logs.unshift(data.log);
        window.toast ? window.toast(data.message) : null;
      } catch (e) {
        alert(e.message);
      } finally {
        m.saving = false;
      }
    },
  };
}
</script>
<div x-data="companyModules(@js($rows), @js(CM::logs($company->id)), {{ $company->id }})" @module-log.window="logs.unshift($event.detail)" class="space-y-5">
  <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @include('partials.card.stat', ['card' => ['label' => 'Modules on', 'value' => $rows->where('on', true)->count().' of '.$rows->count(), 'icon' => 'puzzle-piece', 'tone' => 'brand', 'hint' => 'paid add-ons']])
    @include('partials.card.stat', ['card' => ['label' => 'Locked', 'value' => $rows->where('on', false)->count(), 'icon' => 'lock-closed', 'tone' => 'amber', 'hint' => 'hidden from this company']])
    @include('partials.card.stat', ['card' => ['label' => 'Overrides', 'value' => $rows->where('source', 'override')->count(), 'icon' => 'adjustments-horizontal', 'tone' => 'sky', 'hint' => 'set by scharge staff']])
    @include('partials.card.stat', ['card' => ['label' => 'Plan', 'value' => $subscription?->plan->name ?? 'No plan', 'icon' => 'receipt-percent', 'tone' => 'green',
      'hint' => $planList === null ? 'includes every module' : count($planList).' modules in the plan']])
  </div>

  @unless (Modules::installed())
    <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Run <code>database/schema/2026_11_20_company_modules.sql</code> to switch modules per company. Until then every module is on.</p>
  @endunless

  @php $bill = \App\Support\BillingLock::status($company->id); @endphp
  <div x-data="billingGrace(@js($bill), {{ $company->id }})" class="rounded-2xl border p-4 text-sm"
       :class="b.locked ? 'border-red-200 bg-red-50/60 dark:border-red-500/30 dark:bg-red-500/10' : (b.overdue.length ? 'border-amber-200 bg-amber-50/60 dark:border-amber-500/30 dark:bg-amber-500/10' : 'border-gray-200 dark:border-gray-800')">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="font-semibold text-gray-800 dark:text-white/90">scharge bill</h3>
        <p class="mt-0.5 text-gray-600 dark:text-gray-300">
          <span x-show="b.locked">Locked: an unpaid bill is past due. Everyone in the company sees only the Billing page.</span>
          <span x-show="!b.locked && b.overdue.length && b.grace_until" x-text="'Overdue, but in grace until ' + (b.grace_until || '').slice(0, 10) + '.'"></span>
          <span x-show="!b.locked && b.overdue.length && !b.grace_until">Overdue. The billing lock is off, so nobody is locked yet.</span>
          <span x-show="!b.overdue.length" x-text="b.unpaid.length ? 'Unpaid bills, none past due.' : 'Nothing unpaid.'"></span>
          <span class="text-gray-500 dark:text-gray-400" x-show="b.unpaid.length" x-text="' Due: ' + b.currency + ' ' + Number(b.total_due).toLocaleString()"></span>
        </p>
      </div>
      @if ($canEdit)
        <div class="flex flex-wrap items-center gap-2">
          <input type="date" x-model="until" :min="today" class="{{ $field }}" aria-label="Grace until">
          <input type="text" x-model="note" placeholder="Note (optional)" maxlength="255" class="{{ $field }} w-40">
          <button type="button" @click="save(until)" :disabled="saving || !until" class="inline-flex h-9 items-center rounded-lg bg-brand-500 px-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">Give grace</button>
          <button type="button" x-show="b.grace_until" @click="save(null)" :disabled="saving" class="inline-flex h-9 items-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">End grace</button>
        </div>
      @endif
    </div>
  </div>

  <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
      <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Paid modules</h3>
      <p class="text-xs text-gray-500 dark:text-gray-400">Always included: {{ $core->implode(', ') }}.</p>
    </div>
    <div class="divide-y divide-gray-100 dark:divide-gray-800">
      <template x-for="m in rows" :key="m.key">
        <div class="flex flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="m.label"></span>
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="m.on ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'" x-text="m.on ? 'On' : 'Locked'"></span>
              <span class="rounded-full px-2 py-0.5 text-xs" :class="m.source === 'override' ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' : 'bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400'"
                    x-text="m.source === 'override' ? 'Override' + (m.expires_at ? ' until ' + m.expires_at : '') : 'From plan'"></span>
            </div>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" x-text="m.blurb"></p>
            <p class="mt-0.5 text-xs text-gray-400" x-show="m.changed" x-text="'Changed by ' + m.changed + (m.note ? ' · ' + m.note : '')"></p>
            <p class="mt-0.5 text-xs text-gray-400" x-show="m.source === 'override'" x-text="'Plan default: ' + (m.plan ? 'on' : 'off')"></p>
          </div>
          @if ($canEdit)
            <div class="flex flex-wrap items-center gap-2">
              <div class="inline-flex overflow-hidden rounded-lg border border-gray-300 text-sm dark:border-gray-700" role="group" :aria-label="m.label">
                <template x-for="opt in [['plan','Plan'],['on','On'],['off','Off']]" :key="opt[0]">
                  <button type="button" @click="m.draft = opt[0]" class="px-3 py-1.5"
                          :class="(m.draft ?? m.state) === opt[0] ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5'" x-text="opt[1]"></button>
                </template>
              </div>
              <input type="date" x-show="(m.draft ?? m.state) !== 'plan'" x-model="m.expires_at" :min="today" class="{{ $field }}" title="Until (optional)" aria-label="Until (optional)">
              <input type="text" x-show="(m.draft ?? m.state) !== 'plan'" x-model="m.noteDraft" placeholder="Note (optional)" maxlength="255" class="{{ $field }} w-40">
              <button type="button" @click="save(m)" :disabled="m.saving" class="inline-flex h-9 items-center rounded-lg bg-brand-500 px-3 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">Save</button>
            </div>
          @endif
        </div>
      </template>
    </div>
  </div>

  <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-800">
    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
      <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Changes</h3>
    </div>
    <template x-if="!logs.length"><p class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">No changes yet: every module follows the plan.</p></template>
    <ul class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
      <template x-for="(l, i) in logs" :key="i">
        <li class="flex flex-wrap gap-x-3 px-4 py-2">
          <span class="text-gray-500 dark:text-gray-400" x-text="l.at"></span>
          <span class="font-medium text-gray-800 dark:text-white/90" x-text="l.module"></span>
          <span class="text-gray-600 dark:text-gray-300" x-text="l.change + ' → ' + l.to"></span>
          <span class="text-gray-500 dark:text-gray-400" x-text="'by ' + l.who + (l.note ? ' · ' + l.note : '')"></span>
        </li>
      </template>
    </ul>
  </div>
</div>
