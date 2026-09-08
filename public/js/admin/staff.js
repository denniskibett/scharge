// ============================================
// Staff Management JavaScript
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Staff.js loaded');

    // -------- Bulk Actions --------
    const selectAllCheckbox = document.getElementById('staffSelectAll');
    const selectAllTable = document.getElementById('staffSelectAllTable');
    const userCheckboxes = document.querySelectorAll('.staff-checkbox');
    const selectedCountEl = document.getElementById('staffSelectedCount');
    const bulkActionSelect = document.getElementById('staffBulkAction');
    const applyBulkBtn = document.getElementById('staffApplyBulkAction');

    function updateSelection() {
        const checked = document.querySelectorAll('.staff-checkbox:checked');
        const count = checked.length;
        if (selectedCountEl) {
            selectedCountEl.textContent = count + ' selected';
            selectedCountEl.classList.toggle('hidden', count === 0);
        }
        if (applyBulkBtn) {
            applyBulkBtn.disabled = count === 0;
        }
    }

    // Initial state
    updateSelection();

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            userCheckboxes.forEach(cb => cb.checked = isChecked);
            if (selectAllTable) selectAllTable.checked = isChecked;
            updateSelection();
        });
    }
    if (selectAllTable) {
        selectAllTable.addEventListener('change', function() {
            const isChecked = this.checked;
            userCheckboxes.forEach(cb => cb.checked = isChecked);
            if (selectAllCheckbox) selectAllCheckbox.checked = isChecked;
            updateSelection();
        });
    }
    userCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = document.querySelectorAll('.staff-checkbox:checked').length === userCheckboxes.length;
            if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
            if (selectAllTable) selectAllTable.checked = allChecked;
            updateSelection();
        });
    });

    const bulkExtraDiv = document.getElementById('staffBulkExtraInputs');
    const bulkRoleSelect = document.getElementById('staffBulkRoleSelect');
    const bulkCompanySelect = document.getElementById('staffBulkCompanySelect');

    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            const val = this.value;
            document.querySelectorAll('#staffBulkExtraInputs > div').forEach(el => el.style.display = 'none');
            if (val === 'change_role') {
                const el = document.getElementById('staffBulkRoleInput');
                if (el) el.style.display = 'block';
            } else if (val === 'change_company') {
                const el = document.getElementById('staffBulkCompanyInput');
                if (el) el.style.display = 'block';
            }
            if (bulkExtraDiv) {
                bulkExtraDiv.style.display = (val === 'change_role' || val === 'change_company') ? 'block' : 'none';
            }
        });
    }

    if (applyBulkBtn) {
        applyBulkBtn.addEventListener('click', function() {
            const action = bulkActionSelect ? bulkActionSelect.value : '';
            if (!action) {
                alert('Please select an action.');
                return;
            }
            const selectedIds = Array.from(document.querySelectorAll('.staff-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                alert('No staff selected.');
                return;
            }

            let confirmMsg = 'Are you sure you want to ' + action + ' ' + selectedIds.length + ' staff member(s)?';
            if (action === 'delete') {
                confirmMsg = 'Are you sure you want to delete ' + selectedIds.length + ' staff member(s)? This cannot be undone.';
            }
            if (!confirm(confirmMsg)) return;

            const formData = new FormData();
            formData.append('user_ids', JSON.stringify(selectedIds));
            formData.append('action', action);
            if (action === 'change_role') {
                formData.append('role_id', bulkRoleSelect.value);
            }
            if (action === 'change_company') {
                formData.append('company_id', bulkCompanySelect.value);
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('/admin/staff/bulk-action', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
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
    }

    // -------- Quick Edit Modal --------
    window.staffOpenEditModal = function(userId) {
        fetch('/admin/staff/' + userId, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('staffEditUserId').value = user.id;
                document.getElementById('staffEditName').value = user.name;
                document.getElementById('staffEditEmail').value = user.email;
                document.getElementById('staffEditPhone').value = user.phone || '';
                document.getElementById('staffEditRole').value = user.role_id;
                document.getElementById('staffEditCompany').value = user.company_id || '';
                document.getElementById('staffEditStatus').value = user.status;
                document.getElementById('staffEditModal').classList.remove('hidden');
            } else {
                alert('Failed to load staff data.');
            }
        })
        .catch(error => {
            console.error('Error fetching staff:', error);
            alert('Failed to load staff data.');
        });
    };

    window.staffCloseEditModal = function() {
        document.getElementById('staffEditModal').classList.add('hidden');
    };

    const editForm = document.getElementById('staffEditForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('staffEditUserId').value;
            const formData = new FormData(this);
            formData.append('_method', 'POST');

            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('/admin/staff/quick-update/' + userId, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    const row = document.getElementById('staff-row-' + userId);
                    if (row) {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = data.row;
                        const newRow = tempDiv.firstElementChild;
                        row.parentNode.replaceChild(newRow, row);
                    }
                    staffCloseEditModal();
                } else if (data.errors) {
                    let errorMsg = 'Validation errors:\n';
                    for (let field in data.errors) {
                        errorMsg += field + ': ' + data.errors[field].join(', ') + '\n';
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
    }

    // -------- Delete Staff --------
    window.staffDeleteUser = function(userId) {
        if (!confirm('Are you sure you want to delete this staff member?')) return;
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch('/admin/staff/' + userId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': token,
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

    // -------- Sorting --------
    window.staffSort = function(column) {
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