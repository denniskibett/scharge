<div class="flex flex-wrap items-center gap-3 mb-4">
    <div class="flex items-center gap-2">
        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="selectAll" class="text-sm text-gray-600 dark:text-gray-400">Select All</label>
    </div>
    <div id="selectedCount" class="text-sm text-gray-600 dark:text-gray-400 hidden">0 selected</div>

    <div class="flex items-center gap-2 ml-auto">
        <select id="bulkAction" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-1.5 text-sm">
            <option value="">Bulk Actions</option>
            <option value="activate">Activate</option>
            <option value="deactivate">Deactivate</option>
            <option value="change_role">Change Role</option>
            <option value="change_company">Change Company</option>
            <option value="delete">Delete</option>
        </select>
        <button id="applyBulkAction" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            Apply
        </button>
    </div>
</div>

<!-- Hidden fields for bulk action extra inputs -->
<div id="bulkExtraInputs" style="display: none;">
    <div class="mt-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg" id="bulkRoleInput">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Role</label>
        <select id="bulkRoleSelect" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm">
            @foreach($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mt-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg" id="bulkCompanyInput">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Company</label>
        <select id="bulkCompanySelect" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm">
            @foreach($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
    </div>
</div>