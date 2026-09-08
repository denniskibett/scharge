<tr id="user-row-{{ $user->id }}" class="hover:bg-indigo-50 dark:hover:bg-indigo-900/10 transition-colors duration-150">
    <td class="px-4 py-3">
        <input type="checkbox" class="user-checkbox" data-id="{{ $user->id }}">
    </td>
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        #{{ $user->id }}
    </td>
    <td class="px-4 py-3">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                <span class="text-indigo-600 dark:text-indigo-400 font-medium text-sm">{{ substr($user->name ?? 'U', 0, 1) }}</span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-800 dark:text-white/90" data-field="name">{{ $user->name ?? 'Unknown' }}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $user->email ?? '' }}</p>
            </div>
        </div>
    </td>
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" data-field="email">
        <a href="mailto:{{ $user->email }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">
            {{ $user->email ?? '' }}
        </a>
    </td>
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" data-field="phone">
        {{ $user->phone ?? '-' }}
    </td>
    <td class="px-4 py-3">
        <select data-field="role_id" data-value="{{ $user->role_id }}" onchange="quickUpdate({{ $user->id }}, 'role_id', this.value)" class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected($user->role_id == $role->id)>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-4 py-3">
        <select data-field="company_id" data-value="{{ $user->company_id }}" onchange="quickUpdate({{ $user->id }}, 'company_id', this.value)" class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
            <option value="">N/A</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @selected($user->company_id == $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-4 py-3">
        <select data-field="status" data-value="{{ $user->status }}" onchange="quickUpdate({{ $user->id }}, 'status', this.value)" class="text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
            <option value="0" @selected($user->status == 0) class="text-emerald-600">🟢 Active</option>
            <option value="1" @selected($user->status == 1) class="text-rose-600">🔴 Inactive</option>
            <option value="2" @selected($user->status == 2) class="text-amber-600">🟡 Pending</option>
        </select>
    </td>
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
        {{ $user->created_at?->format('d/m/Y H:i') ?? 'N/A' }}
    </td>
    <td class="px-4 py-3">
        <div class="flex items-center gap-2">
            <button onclick="viewUser({{ $user->id }})" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300" title="View User">
                <i class="fas fa-eye"></i>
            </button>
            @if(is_null($user->email_verified_at))
            <button onclick="verifyUser({{ $user->id }})" class="text-emerald-600 hover:text-emerald-900 dark:text-emerald-400 dark:hover:text-emerald-300" title="Verify User">
                <i class="fas fa-check-circle"></i>
            </button>
            @endif
            <button onclick="deleteUser({{ $user->id }})" class="text-rose-600 hover:text-rose-900 dark:text-rose-400 dark:hover:text-rose-300" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </td>
</tr>

<script>
function viewUser(userId) {
    // Redirect to user profile or show modal
    window.location.href = '{{ url("admin/users") }}/' + userId;
}

function verifyUser(userId) {
    if (!confirm('Verify this user\'s email address?')) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        alert('CSRF token missing.');
        return;
    }

    fetch('{{ url("admin/users") }}/' + userId + '/verify', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(function() { window.location.reload(); }, 1000);
        } else {
            showToast(data.message || 'Verification failed.', 'error');
        }
    })
    .catch(function(error) {
        showToast('Error: ' + error.message, 'error');
    });
}

function deleteUser(userId) {
    if (!confirm('Delete this user? This action cannot be undone.')) return;

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        alert('CSRF token missing.');
        return;
    }

    fetch('{{ url("admin/users") }}/' + userId, {
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
            var row = document.getElementById('user-row-' + userId);
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