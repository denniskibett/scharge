{{-- Photos for this house. Shared photos (all houses of this type, or the whole property) show on every
     similar house and on the public Open listings page for prospective tenants. --}}
@hasanyrole('sysadmin|admin|property_manager')
<div class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700"
     x-data="unitPhotos('{{ route('units.photos.index', $unit) }}', '{{ $unit->unit_type }}')" x-init="load()">
  <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
    <div>
      <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Photos</h3>
      <p class="text-xs text-gray-500 dark:text-gray-400">Shown to prospective tenants on the Open listings page while this house is vacant.</p>
    </div>
    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="photos.length + ' photo' + (photos.length === 1 ? '' : 's')"></span>
  </div>

  <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4" x-show="photos.length">
    <template x-for="p in photos" :key="p.id">
      <figure class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
        <img :src="p.url" :alt="p.caption || 'Photo of the house'" class="h-28 w-full object-cover" loading="lazy">
        <figcaption class="flex items-center justify-between gap-2 px-2 py-1 text-[11px] text-gray-600 dark:text-gray-300">
          <span x-text="p.scope_label + (p.from_other_unit ? ' · from another house' : '')"></span>
          <button type="button" @click="remove(p)" class="text-red-600 hover:underline" aria-label="Remove photo">Remove</button>
        </figcaption>
      </figure>
    </template>
  </div>
  <p x-show="!photos.length" class="mb-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">No photos yet. Add a few: one upload can cover every similar house.</p>

  <form @submit.prevent="upload($event)" class="flex flex-col gap-3 rounded-xl border border-dashed border-gray-300 p-4 dark:border-gray-700">
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required class="text-sm text-gray-700 dark:text-gray-300">
    <fieldset class="flex flex-wrap gap-3 text-sm text-gray-700 dark:text-gray-300">
      <legend class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Use these photos for</legend>
      <label class="inline-flex items-center gap-2"><input type="radio" name="applies_to" value="type" checked>
        All <span x-text="similar"></span> {{ $unit->unit_type ?: 'similar' }} houses here</label>
      <label class="inline-flex items-center gap-2"><input type="radio" name="applies_to" value="unit"> This house only</label>
      <label class="inline-flex items-center gap-2"><input type="radio" name="applies_to" value="property"> Every house (compound, gate, parking)</label>
    </fieldset>
    <input type="text" name="caption" maxlength="140" placeholder="Caption (optional), e.g. Kitchen" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90">
    <div class="flex items-center gap-3">
      <button type="submit" :disabled="busy" class="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50" x-text="busy ? 'Uploading…' : 'Add photos'"></button>
      <span class="text-xs" :class="ok ? 'text-emerald-600' : 'text-red-600'" x-text="message"></span>
    </div>
  </form>
</div>

<script>
  function unitPhotos(base, type) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    return {
      photos: [], similar: 0, busy: false, message: '', ok: true,
      async load() { const d = await (await fetch(base, { headers: { Accept: 'application/json' } })).json(); this.photos = d.photos; this.similar = d.similar; },
      async upload(e) {
        this.busy = true; this.message = '';
        const res = await fetch(base, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: new FormData(e.target) });
        const d = await res.json().catch(() => ({}));
        this.ok = res.ok; this.message = d.message || 'Upload failed.';
        if (res.ok) { this.photos = d.photos; e.target.reset(); }
        this.busy = false;
      },
      async remove(p) {
        if (!confirm(p.applies_to === 'unit' ? 'Remove this photo?' : 'Remove this shared photo from every house that uses it?')) return;
        const res = await fetch(`${base}/${p.id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        const d = await res.json().catch(() => ({})); if (res.ok) this.photos = d.photos; this.ok = res.ok; this.message = d.message || '';
      },
    };
  }
</script>
@endhasanyrole
