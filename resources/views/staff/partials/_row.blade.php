{{-- One row of the Staff table (admin/staff/index), in the Tenancies table look. Also returned by StaffController@update
     for in-place refresh, so keep the data-field / data-value attributes and the row id. --}}
@php
  $role = $user->roles->first() ?? null;
  $statuses = [
    0 => ['Active', 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'],
    1 => ['No access', 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'],
    2 => ['Pending', 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'],
  ];
  [$statusLabel, $statusClass] = $statuses[(int) $user->status] ?? $statuses[0];
  $initials = collect(explode(' ', (string) $user->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
  $cell = 'p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400';
  $menuItem = 'text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300';
@endphp
<tr id="staff-row-{{ $user->id }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
  <td class="w-10 p-4"><input type="checkbox" class="staff-checkbox rounded border-gray-300 text-brand-500 dark:border-gray-600" data-id="{{ $user->id }}"
      onchange="this.checked ? selectedIds.add(this.dataset.id) : selectedIds.delete(this.dataset.id); updateSelectedCount(); updateMasterCheckbox();"></td>
  <td class="p-4 whitespace-nowrap">
    <div class="flex items-center gap-3">
      <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100"><span class="text-sm font-medium text-purple-600">{{ $initials ?: '?' }}</span></div>
      <a href="{{ route('staff.show', $user->id) }}" data-field="name" class="text-sm font-medium text-gray-800 hover:text-blue-600 dark:text-white/90">{{ $user->name }}</a>
    </div>
  </td>
  <td data-field="email" class="{{ $cell }}">{{ $user->email }}</td>
  <td class="{{ $cell }}"><span data-field="phone" class="hidden">{{ $user->phone }}</span><x-phone :number="$user->phone" /></td>
  <td data-field="role_id" data-value="{{ $role?->id }}" class="{{ $cell }}">{{ $role ? ucfirst(str_replace('_', ' ', $role->name)) : '-' }}</td>
  <td data-field="company_id" data-value="{{ $user->company_id }}" class="{{ $cell }}">{{ $user->company?->name ?? 'scharge' }}</td>
  <td data-field="status" data-value="{{ (int) $user->status }}" class="p-4 whitespace-nowrap"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</span></td>
  <td class="{{ $cell }}">{{ $user->last_login_at ? \Illuminate\Support\Carbon::parse($user->last_login_at)->format('j M Y, H:i') : 'Never' }}</td>
  <td class="{{ $cell }}">{{ optional($user->created_at)->format('j M Y') }}</td>
  <td class="p-4 whitespace-nowrap text-right">
    <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
      <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions">
        <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg>
      </button>
      <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 text-left dark:border-gray-800">
        <a href="{{ route('staff.show', $user->id) }}" class="{{ $menuItem }}">View</a>
        <div class="{{ $menuItem }}"><x-reverify-email :user="$user" label="Re-verify email" /></div>
        @if ((int) $user->status !== 0)
          <button type="button" onclick="quickUpdate({{ $user->id }}, 'status', 0)" @click="open = false" class="{{ $menuItem }}">Activate</button>
        @elseif (! $user->is(auth()->user()))
          <button type="button" onclick="quickUpdate({{ $user->id }}, 'status', 1)" @click="open = false" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Switch off</button>
        @endif
      </div>
    </div>
  </td>
</tr>
