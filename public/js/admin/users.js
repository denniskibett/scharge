// ============================================
// Admin User Management JavaScript
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // -------- Filtering --------
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const companyFilter = document.getElementById('companyFilter');
    const statusFilter = document.getElementById('statusFilter');
    const perPageSelect = document.getElementById('perPageSelect');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');

    function applyFilters() {
        const params = new URLSearchParams();
        if (searchInput.value) params.set('search', searchInput.value);
        if (roleFilter.value) params.set('role_id', roleFilter.value);
        if (companyFilter.value) params.set('company_id', companyFilter.value);
        if (statusFilter.value) params.set('status', statusFilter.value);
        if (perPageSelect.value) params.set('per_page', perPageSelect.value);
        const currentUrl = new URL(window.location.href);
        if (currentUrl.searchParams.get('sort')) params.set('sort', currentUrl.searchParams.get('sort'));
        if (currentUrl.searchParams.get('direction')) params.set('direction', currentUrl.searchParams.get('direction'));
        window.location.href = window.location.pathname + '?' + params.toString();
    }

    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilters, 300);
    });

    roleFilter.addEventListener('change', applyFilters);
    companyFilter.addEventListener('change', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    perPageSelect.addEventListener('change', applyFilters);

    clearFiltersBtn.addEventListener('click', function() {
        searchInput.value = '';
        roleFilter.value = '';
        companyFilter.value = '';
        statusFilter.value = '';
        applyFilters();
    });

    // -------- Bulk Actions --------
    const selectAllCheckbox = document.getElementById('selectAll');
    const selectAllTable = document.getElementById('selectAllTable');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const selectedCountEl = document.getElementById('selectedCount');
    const bulkActionSelect = document.getElementById('bulkAction');
    const applyBulkBtn = document.getElementById('applyBulkAction');

    function updateSelection() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        const count = checked.length;
        selectedCountEl.textContent = count + ' selected';
        selectedCountEl.classList.toggle('hidden', count === 0);
        applyBulkBtn.disabled = count === 0;
    }

    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        userCheckboxes.forEach(cb => cb.checked = isChecked);
        selectAllTable.checked = isChecked;
        updateSelection();
    });
    selectAllTable.addEventListener('change', function() {
        const isChecked = this.checked;
        userCheckboxes.forEach(cb => cb.checked = isChecked);
        selectAllCheckbox.checked = isChecked;
        updateSelection();
    });
    userCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = document.querySelectorAll('.user-checkbox:checked').length === userCheckboxes.length;
            selectAllCheckbox.checked = allChecked;
            selectAllTable.checked = allChecked;
            updateSelection();
        });
    });

    const bulkExtraDiv = document.getElementById('bulkExtraInputs');
    const bulkRoleSelect = document.getElementById('bulkRoleSelect');
    const bulkCompanySelect = document.getElementById('bulkCompanySelect');

    bulkActionSelect.addEventListener('change', function() {
        const val = this.value;
        document.querySelectorAll('#bulkExtraInputs > div').forEach(el => el.style.display = 'none');
        if (val === 'change_role') {
            document.getElementById('bulkRoleInput').style.display = 'block';
        } else if (val === 'change_company') {
            document.getElementById('bulkCompanyInput').style.display = 'block';
        }
        bulkExtraDiv.style.display = (val === 'change_role' || val === 'change_company') ? 'block' : 'none';
    });

    applyBulkBtn.addEventListener('click', function() {
        const action = bulkActionSelect.value;
        if (!action) { alert('Please select an action.'); return; }
        const selectedIds = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        if (selectedIds.length === 0) { alert('No users selected.'); return; }

        let confirmMsg = `Are you sure you want to ${action} ${selectedIds.length} user(s)?`;
        if (action === 'delete') confirmMsg = `Are you sure you want to delete ${selectedIds.length} user(s)? This action cannot be undone.`;
        if (!confirm(confirmMsg)) return;

        const formData = new FormData();
        formData.append('user_ids', JSON.stringify(selectedIds));
        formData.append('action', action);
        if (action === 'change_role') formData.append('role_id', bulkRoleSelect.value);
        if (action === 'change_company') formData.append('company_id', bulkCompanySelect.value);

        fetch('{{ route("admin.users.bulk-action") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Something went wrong.'));
            }
        })
        .catch(error => {
            console.error('Bulk action error:', error);
            alert('An error occurred. Please try again.');
        });
    });

    // -------- Quick Edit Modal --------
    window.openEditModal = function(userId) {
        fetch(`/admin/users/${userId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editName').value = user.name;
                document.getElementById('editEmail').value = user.email;
                document.getElementById('editPhone').value = user.phone || '';
                document.getElementById('editRole').value = user.role_id;
                document.getElementById('editCompany').value = user.company_id || '';
                document.getElementById('editStatus').value = user.status;
                document.getElementById('editUserModal').classList.remove('hidden');
            } else {
                alert('Failed to load user data.');
            }
        })
        .catch(error => {
            console.error('Error fetching user:', error);
            alert('Failed to load user data.');
        });
    };

    window.closeEditModal = function() {
        document.getElementById('editUserModal').classList.add('hidden');
    };

    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const userId = document.getElementById('editUserId').value;
        const formData = new FormData(this);
        formData.append('_method', 'POST');

        fetch(`/admin/users/quick-update/${userId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                const row = document.getElementById(`user-row-${userId}`);
                if (row) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.row;
                    const newRow = tempDiv.firstElementChild;
                    row.parentNode.replaceChild(newRow, row);
                }
                closeEditModal();
            } else if (data.errors) {
                let errorMsg = 'Validation errors:\n';
                for (let field in data.errors) {
                    errorMsg += `${field}: ${data.errors[field].join(', ')}\n`;
                }
                alert(errorMsg);
            } else {
                alert('Error: ' + (data.message || 'Something went wrong.'));
            }
        })
        .catch(error => {
            console.error('Quick update error:', error);
            alert('An error occurred. Please try again.');
        });
    });

    // -------- Delete User --------
    window.deleteUser = function(userId) {
        if (!confirm('Are you sure you want to delete this user?')) return;
        fetch(`/admin/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Something went wrong.'));
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            alert('An error occurred.');
        });
    };

    // -------- Sorting (global) --------
    window.sortTable = function(column) {
        let currentUrl = new URL(window.location.href);
        let sort = currentUrl.searchParams.get('sort');
        let direction = currentUrl.searchParams.get('direction');
        if (sort === column) {
            direction = direction === 'asc' ? 'desc' : 'asc';
        } else {
            sort = column;
            direction = 'asc';
        }
        currentUrl.searchParams.set('sort', sort);
        currentUrl.searchParams.set('direction', direction);
        window.location.href = currentUrl.toString();
    };
});