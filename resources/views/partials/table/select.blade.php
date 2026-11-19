{{-- TailAdmin header select with chevron, submits its form on change. $name, $options [value => label], $selected, optional $min, optional $size ('sm' = compact) --}}
<div class="relative">
  <select name="{{ $name }}" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" aria-label="{{ $aria ?? $name }}"
    class="dark:bg-dark-900 {{ ($size ?? null) === 'sm' ? 'h-9 px-3 py-1.5' : 'h-11 px-4 py-2.5' }} w-full {{ $min ?? 'min-w-[140px]' }} appearance-none rounded-lg border border-gray-300 bg-transparent pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
    @foreach ($options as $value => $text)
      <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
    @endforeach
  </select>

</div>
