<tr id="staff-row-{{ $user->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150">
    <td class="px-4 py-3">
        <input type="checkbox" class="staff-checkbox" data-id="{{ $user->id }}">
    </td>
    <td class="px-4 py-3">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                <span class="text-blue-600 font-medium text-sm">{{ substr($user->name ?? 'U', 0, 1) }}</span>
            </div>
            <span class="text-sm font-medium text-gray-800 dark:text-white/90" data-field="name">{{ $user->name ?? 'Unknown' }}</span>
        </div>
    </td>
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" data-field="email">{{ $user->email ?? '' }}</td>
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" data-field="phone">{{ $user->phone ?? '-' }}</td>
    <td class="px-4 py-3">
        <select data-field="role_id" data-value="{{ $user->role_id }}" onchange="quickUpdate({{ $user->id }}, 'role_id', this.value)" class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1">
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected($user->role_id == $role->id)>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-4 py-3">
        <select data-field="company_id" data-value="{{ $user->company_id }}" onchange="quickUpdate({{ $user->id }}, 'company_id', this.value)" class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1">
            <option value="">No Company</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @selected($user->company_id == $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-4 py-3">
        <select data-field="status" data-value="{{ $user->status }}" onchange="quickUpdate({{ $user->id }}, 'status', this.value)" class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1">
            <option value="0" @selected($user->status == 0)>Active</option>
            <option value="1" @selected($user->status == 1)>Inactive</option>
            <option value="2" @selected($user->status == 2)>Pending</option>
        </select>
    </td>
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
    </td>
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $user->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
    <td class="px-4 py-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.staff.show', $user->id) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="View">
                <i class="fas fa-eye"></i>
            </a>
            <button onclick="deleteStaff({{ $user->id }})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </td>
</tr>

<script>
function deleteStaff(userId) {
    if (!confirm('Delete this staff member?')) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : null;
    if (!csrfToken) {
        alert('CSRF token missing.');
        return;
    }

    fetch('{{ url("admin/staff") }}/' + userId, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message, 'success');
            var row = document.getElementById('staff-row-' + userId);
            if (row) row.remove();
            updateStats();
        } else {
            showToast(data.message || 'Delete failed.', 'error');
        }
    })
    .catch(function(error) {
        showToast('Error: ' + error.message, 'error');
    });
}
</script>