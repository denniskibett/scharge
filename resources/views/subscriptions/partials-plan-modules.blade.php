{{-- Plan › Modules: which paid modules this plan includes (subscription_plans.features["modules"], App\Support\Modules).
     "Every module" = no list, so companies on this plan keep everything. Sysadmins only can change it. --}}
@php
  use App\Support\Modules;
  $raw = \Illuminate\Support\Facades\DB::table('subscription_plans')->where('id', $planData['id'])->value('features');
  $list = Modules::modulesFromFeatures($raw);
  $canEdit = auth()->user()->hasRole('sysadmin');
  $paid = collect(Modules::paid())->map(fn ($k) => ['key' => $k, 'label' => Modules::label($k), 'blurb' => Modules::CATALOGUE[$k]['blurb'] ?? ''])->values();
@endphp
<script>
function planModules(all, picked, planId) {
  return {
    all, picked, saving: false, saved: '',
    async save() {
      this.saving = true; this.saved = '';
      try {
        const res = await fetch(`/admin/subscriptions/plans/${planId}/modules`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
          body: JSON.stringify({ all: this.all, modules: this.picked }),
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Could not save.');
        this.saved = data.message;
      } catch (e) { alert(e.message); } finally { this.saving = false; }
    },
  };
}
</script>
<div x-data="planModules(@js($list === null), @js($list ?? Modules::paid()), {{ (int) $planData['id'] }})" class="space-y-4 text-sm">
  <p class="text-gray-500 dark:text-gray-400">Companies on this plan get these modules by default. Staff can still switch a module on or off for one company on its Modules tab. Always included: {{ collect(Modules::CATALOGUE)->filter(fn ($m) => ! empty($m['core']))->pluck('label')->implode(', ') }}.</p>
  <label class="inline-flex items-center gap-2 text-gray-800 dark:text-white/90">
    <input type="checkbox" x-model="all" @disabled(! $canEdit) class="rounded border-gray-300 text-brand-500 dark:border-gray-700">
    Every module (no limits)
  </label>
  <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3" :class="all && 'opacity-50'">
    @foreach ($paid as $m)
      <label class="flex items-start gap-2 rounded-xl border border-gray-200 p-3 dark:border-gray-800">
        <input type="checkbox" value="{{ $m['key'] }}" x-model="picked" :disabled="all || {{ $canEdit ? 'false' : 'true' }}" class="mt-0.5 rounded border-gray-300 text-brand-500 dark:border-gray-700">
        <span><span class="block font-medium text-gray-800 dark:text-white/90">{{ $m['label'] }}</span><span class="block text-xs text-gray-500 dark:text-gray-400">{{ $m['blurb'] }}</span></span>
      </label>
    @endforeach
  </div>
  @if ($canEdit)
    <div class="flex items-center gap-3">
      <button type="button" @click="save()" :disabled="saving" class="inline-flex h-9 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">Save plan modules</button>
      <span class="text-xs text-emerald-600 dark:text-emerald-400" x-text="saved"></span>
    </div>
  @endif
</div>
