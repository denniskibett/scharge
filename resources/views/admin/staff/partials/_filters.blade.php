<form method="GET" action="{{ route('admin.staff.index') }}" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <!-- Search -->
        <div class="col-span-1 md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, phone..." class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
        </div>

        <!-- Role Filter – sends role_name (string) -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role</label>
            <select name="role_name" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}" {{ request('role_name') == $role->name ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                @endforeach
            </select>
        </div>

        <!-- Company Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company</label>
            <select name="company_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <option value="">All Companies</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Status Filter -->
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
            <select name="status" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <option value="">All</option>
                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Active</option>
                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Inactive</option>
                <option value="2" {{ request('status') === '2' ? 'selected' : '' }}>Pending</option>
            </select>
        </div>
    </div>

    <div class="mt-3 flex justify-end gap-2">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-search mr-1"></i> Apply Filters
        </button>
        <a href="{{ route('admin.staff.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <i class="fas fa-times mr-1"></i> Clear Filters
        </a>
    </div>
</form>