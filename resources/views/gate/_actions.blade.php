{{-- Gate / Visitors switch and New pass, at the right of the table's search box. $active ('index' | 'visitors') --}}
<div class="flex flex-wrap items-center gap-2">
  <div class="inline-flex h-11 items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
    @foreach (['index' => 'Gate', 'visitors' => 'Visitors'] as $route => $label)
      <a href="{{ route('gate.'.$route) }}" class="rounded-md px-3 py-2 text-sm font-medium {{ $active === $route ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}" @if ($active === $route) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
  </div>
  @if ($canManage ?? false)
    <a href="{{ route('gate.passes.create') }}" class="inline-flex h-11 items-center gap-2 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14m7-7H5"/></svg>
      New pass
    </a>
  @endif
</div>
