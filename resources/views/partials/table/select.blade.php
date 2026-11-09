{{-- TailAdmin header select with chevron, submits its form on change. $name, $options [value => label], $selected, optional $min --}}
<div class="relative">
  <select name="{{ $name }}" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" aria-label="{{ $aria ?? $name }}"
    class="dark:bg-dark-900 h-11 w-full {{ $min ?? 'min-w-[140px]' }} appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
    @foreach ($options as $value => $text)
      <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
    @endforeach
  </select>
  <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
    <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg>
  </span>
</div>
