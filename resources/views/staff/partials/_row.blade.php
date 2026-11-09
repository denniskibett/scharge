{{-- One row of the Staff table (admin/staff/index). Also returned by StaffController@update for in-place refresh. --}}
@php
  $role = $user->roles->first() ?? null;
  $statuses = [0 => ['Active', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'], 1 => ['No access', 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400'], 2 => ['Pending', 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400']];
  [$statusLabel, $statusClass] = $statuses[(int) $user->status] ?? $statuses[0];
  $initials = collect(explode(' ', (string) $user->name))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
@endphp
<tr id="staff-row-{{ $user->id }}">
  <td class="w-10"><input type="checkbox" class="staff-checkbox rounded border-gray-300 text-brand-500 dark:border-gray-600" data-id="{{ $user->id }}"
      onchange="this.checked ? selectedIds.add(this.dataset.id) : selectedIds.delete(this.dataset.id); updateSelectedCount(); updateMasterCheckbox();"></td>
  <td>
    <div class="flex items-center gap-3">
      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">{{ $initials }}</span>
      <a href="{{ route('staff.show', $user->id) }}" data-field="name" class="font-medium text-gray-800 hover:text-brand-500 dark:text-white/90">{{ $user->name }}</a>
    </div>
  </td>
  <td data-field="email" class="text-gray-700 dark:text-gray-300">{{ $user->email }}</td>
  <td class="text-gray-700 dark:text-gray-300"><span data-field="phone" class="hidden">{{ $user->phone }}</span><x-phone :number="$user->phone" /></td>
  <td data-field="role_id" data-value="{{ $role?->id }}" class="text-gray-700 dark:text-gray-300">{{ $role ? ucfirst(str_replace('_', ' ', $role->name)) : '—' }}</td>
  <td data-field="company_id" data-value="{{ $user->company_id }}" class="text-gray-700 dark:text-gray-300">{{ $user->company?->name ?? 'scharge' }}</td>
  <td data-field="status" data-value="{{ (int) $user->status }}"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</span></td>
  <td class="text-gray-500">{{ $user->last_login_at ? \Illuminate\Support\Carbon::parse($user->last_login_at)->format('j M Y, H:i') : 'Never' }}</td>
  <td class="text-gray-500">{{ optional($user->created_at)->format('j M Y') }}</td>
  <td>
    <div class="flex items-center gap-3">
      <a href="{{ route('staff.show', $user->id) }}" class="text-sm font-medium text-gray-700 hover:text-brand-500 dark:text-gray-300">View</a>
      <x-reverify-email :user="$user" label="Re-verify" />
      @if ((int) $user->status !== 0)
        <button type="button" onclick="quickUpdate({{ $user->id }}, 'status', 0)" class="text-sm font-medium text-emerald-600 hover:text-emerald-700">Activate</button>
      @elseif (! $user->is(auth()->user()))
        <button type="button" onclick="quickUpdate({{ $user->id }}, 'status', 1)" class="text-sm font-medium text-red-600 hover:text-red-700">Switch off</button>
      @endif
    </div>
  </td>
</tr>
