@extends('layouts.app')

@section('title', 'Staff Management')

@php
    $staff = $staff ?? collect([]);
    $roles = $roles ?? collect([]);
    $companies = $companies ?? collect([]);
    $debugSql = $debugSql ?? '';
    $debugBindings = $debugBindings ?? [];

    $totalStaff = $staff->total() ?? 0;
    $activeCount = $staff->where('status', 0)->count();
    $pendingCount = $staff->where('status', 2)->count();
    $inactiveCount = $staff->where('status', 1)->count();

    $currentRole = request('role_name', '');
    $currentCompany = request('company_id', '');
    $currentStatus = request('status', '');
    $currentSearch = request('search', '');
    $currentSort = request('sort', 'created_at');
    $currentDirection = request('direction', 'desc');
    $currentDateFrom = request('date_from', '');
    $currentDateTo = request('date_to', '');
@endphp

@section('content')
<div class="container mx-auto px-4 py-6 max-w-screen-2xl">

    <!-- CSRF Meta -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-users-cog text-blue-600 dark:text-blue-400"></i>
                Staff Management
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your organization's staff members and their roles.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="exportStaff()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                <i class="fas fa-file-export"></i> Export
            </button>
            <button onclick="exportSelected()" id="exportSelectedBtn" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                <i class="fas fa-file-export"></i> Export Selected
            </button>
            <a href="{{ route('admin.staff.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 transition">
                <i class="fas fa-plus"></i> Add Staff
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Staff</p>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white" id="statsTotal">{{ $totalStaff }}</p>
                </div>
                <div class="h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</p>
                    <p class="text-2xl font-bold text-green-600" id="statsActive">{{ $activeCount }}</p>
                </div>
                <div class="h-12 w-12 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pending</p>
                    <p class="text-2xl font-bold text-yellow-600" id="statsPending">{{ $pendingCount }}</p>
                </div>
                <div class="h-12 w-12 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-xl"></i>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Inactive</p>
                    <p class="text-2xl font-bold text-red-600" id="statsInactive">{{ $inactiveCount }}</p>
                </div>
                <div class="h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                    <i class="fas fa-user-slash text-red-600 dark:text-red-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Filter Bar -->
    @if($currentRole || $currentCompany || $currentStatus !== '' || $currentSearch || $currentDateFrom || $currentDateTo)
    <div class="mb-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 p-3 border border-blue-200 dark:border-blue-800 flex flex-wrap items-center gap-2 text-sm">
        <i class="fas fa-filter text-blue-600 dark:text-blue-400"></i>
        <span class="font-medium text-blue-700 dark:text-blue-300">Active filters:</span>
        @if($currentRole)
            <span class="inline-flex items-center rounded-full bg-blue-200 dark:bg-blue-800 px-3 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-200">
                Role: {{ ucfirst(str_replace('_', ' ', $currentRole)) }}
            </span>
        @endif
        @if($currentCompany)
            <span class="inline-flex items-center rounded-full bg-blue-200 dark:bg-blue-800 px-3 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-200">
                Company: {{ $companies->where('id', $currentCompany)->first()?->name ?? 'Unknown' }}
            </span>
        @endif
        @if($currentStatus !== '')
            <span class="inline-flex items-center rounded-full bg-blue-200 dark:bg-blue-800 px-3 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-200">
                Status: {{ $currentStatus == 0 ? 'Active' : ($currentStatus == 1 ? 'Inactive' : 'Pending') }}
            </span>
        @endif
        @if($currentSearch)
            <span class="inline-flex items-center rounded-full bg-blue-200 dark:bg-blue-800 px-3 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-200">
                Search: "{{ $currentSearch }}"
            </span>
        @endif
        @if($currentDateFrom)
            <span class="inline-flex items-center rounded-full bg-blue-200 dark:bg-blue-800 px-3 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-200">
                From: {{ \Carbon\Carbon::parse($currentDateFrom)->format('d M Y') }}
            </span>
        @endif
        @if($currentDateTo)
            <span class="inline-flex items-center rounded-full bg-blue-200 dark:bg-blue-800 px-3 py-0.5 text-xs font-medium text-blue-800 dark:text-blue-200">
                To: {{ \Carbon\Carbon::parse($currentDateTo)->format('d M Y') }}
            </span>
        @endif
        <a href="{{ route('admin.staff.index') }}" class="ml-auto text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium text-xs">
            <i class="fas fa-times"></i> Clear All
        </a>
    </div>
    @endif

    <!-- Filter Form -->
    <div class="mb-4 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-sm">
        <form id="filterForm" method="GET" action="{{ route('admin.staff.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2">
            <div class="relative">
                <input type="text" name="search" id="searchInput" value="{{ $currentSearch }}" placeholder="🔍 Search..." class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 pl-8 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition h-9">
                <div class="absolute left-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 text-xs">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            <select name="role_name" id="roleFilter" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition h-9">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}" @selected($currentRole == $role->name)>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                @endforeach
            </select>
            <select name="company_id" id="companyFilter" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition h-9">
                <option value="">All Companies</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected($currentCompany == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <select name="status" id="statusFilter" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition h-9">
                <option value="">All Status</option>
                <option value="0" @selected($currentStatus === '0')>Active</option>
                <option value="1" @selected($currentStatus === '1')>Inactive</option>
                <option value="2" @selected($currentStatus === '2')>Pending</option>
            </select>
            <input type="date" name="date_from" id="dateFrom" value="{{ $currentDateFrom }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition h-9">
            <input type="date" name="date_to" id="dateTo" value="{{ $currentDateTo }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition h-9">
            <div class="flex gap-1.5">
                <button type="submit" class="flex-1 inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-blue-700 transition h-9">
                    <i class="fas fa-search mr-1.5 text-xs"></i> Apply
                </button>
                <a href="{{ route('admin.staff.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition h-9 w-9" title="Clear filters">
                    <i class="fas fa-undo text-xs"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <button id="selectAllBtn" onclick="selectAllStaff()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <i class="fas fa-check-double"></i> Select All
            </button>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                <span id="selectedCount" class="font-semibold text-blue-600 dark:text-blue-400">0</span> selected
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <select id="bulkAction" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition">
                <option value="">Bulk Actions</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="change_role">Change Role</option>
                <option value="change_company">Change Company</option>
                <option value="delete">Delete</option>
            </select>
            <button id="applyBulkAction" onclick="applyBulkAction()" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-blue-700 transition">
                Apply
            </button>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
        <div class="w-full overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-10">
                            <input type="checkbox" id="masterCheckbox" onchange="toggleMasterCheckbox()" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition" onclick="sortTable('name')">
                            Name <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition" onclick="sortTable('email')">
                            Email <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition" onclick="sortTable('phone')">
                            Phone <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition" onclick="sortTable('role_id')">
                            Role <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition" onclick="sortTable('company_id')">
                            Company <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition" onclick="sortTable('status')">
                            Status <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition" onclick="sortTable('last_login_at')">
                            Last Login <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 transition" onclick="sortTable('created_at')">
                            Created <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody id="staffTableBody" class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($staff as $user)
                        @include('admin.staff.partials._row', ['user' => $user])
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-inbox text-3xl block mb-2 text-gray-300 dark:text-gray-600"></i>
                            No staff members found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-4 p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-600 dark:text-gray-400">Show:</span>
                <select id="perPage" class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-2 py-1 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition" onchange="changePerPage()">
                    <option value="10" @selected($staff->perPage() == 10)>10</option>
                    <option value="20" @selected($staff->perPage() == 20)>20</option>
                    <option value="50" @selected($staff->perPage() == 50)>50</option>
                    <option value="100" @selected($staff->perPage() == 100)>100</option>
                </select>
                <span class="text-sm text-gray-600 dark:text-gray-400">entries</span>
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400">
                {{ $staff->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Modals (Change Role, Change Company, Delete) -->
<div id="changeRoleModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('changeRoleModal')"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Change Role</h3>
                <button onclick="closeModal('changeRoleModal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select new role for <span id="changeRoleCount" class="font-semibold">0</span> staff member(s).</p>
                <select id="newRoleId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition">
                    <option value="">Select Role...</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <button onclick="closeModal('changeRoleModal')" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">Cancel</button>
                <button onclick="executeBulkAction('change_role')" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Apply</button>
            </div>
        </div>
    </div>
</div>

<div id="changeCompanyModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('changeCompanyModal')"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Change Company</h3>
                <button onclick="closeModal('changeCompanyModal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select new company for <span id="changeCompanyCount" class="font-semibold">0</span> staff member(s).</p>
                <select id="newCompanyId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition">
                    <option value="">Select Company...</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <button onclick="closeModal('changeCompanyModal')" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">Cancel</button>
                <button onclick="executeBulkAction('change_company')" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Apply</button>
            </div>
        </div>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal('deleteModal')"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">Confirm Delete</h3>
                <button onclick="closeModal('deleteModal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Are you sure you want to delete <span id="deleteCount" class="font-semibold text-red-600">0</span> staff member(s)? This action cannot be undone.</p>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                <button onclick="closeModal('deleteModal')" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition">Cancel</button>
                <button onclick="executeBulkAction('delete')" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-4 right-4 z-50 hidden">
    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 shadow-lg border border-green-200 dark:border-green-800 flex items-center gap-2">
        <i class="fas fa-check-circle text-green-600 dark:text-green-400"></i>
        <p id="toastMessage" class="text-sm font-medium text-green-800 dark:text-green-200"></p>
    </div>
</div>

<script>
    console.log('🔍 Staff Management script loaded');

    let selectedIds = new Set();
    let allIds = [];

    const masterCheckbox = document.getElementById('masterCheckbox');
    const selectedCountSpan = document.getElementById('selectedCount');
    const bulkActionSelect = document.getElementById('bulkAction');

    // Helper: Toast
    function showToast(message, type) {
        type = type || 'success';
        const toast = document.getElementById('toast');
        const toastMsg = document.getElementById('toastMessage');
        if (!toast || !toastMsg) {
            console.warn('Toast elements missing, using alert');
            alert(message);
            return;
        }
        toastMsg.textContent = message;
        toast.classList.remove('hidden');

        const colors = {
            success: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200',
            error: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200',
            warning: 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-200'
        };
        const container = toast.querySelector('div');
        if (container) {
            container.className = 'rounded-lg px-4 py-3 shadow-lg border flex items-center gap-2 ' + (colors[type] || colors.success);
            var icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle');
            container.innerHTML = '<i class="fas ' + icon + '"></i><p class="text-sm font-medium">' + message + '</p>';
        }

        clearTimeout(window.toastTimeout);
        window.toastTimeout = setTimeout(function() {
            toast.classList.add('hidden');
        }, 5000);
    }

    // Update selected count
    function updateSelectedCount() {
        if (selectedCountSpan) {
            selectedCountSpan.textContent = selectedIds.size;
        }
    }

    // Master checkbox state
    function updateMasterCheckbox() {
        if (!masterCheckbox) return;
        var checkboxes = document.querySelectorAll('.staff-checkbox');
        var checked = document.querySelectorAll('.staff-checkbox:checked');

        if (allIds.length > 0 && checked.length === checkboxes.length && checked.length === allIds.length) {
            masterCheckbox.checked = true;
            masterCheckbox.indeterminate = false;
        } else if (checkboxes.length === 0) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
        } else if (checked.length === checkboxes.length) {
            masterCheckbox.checked = true;
            masterCheckbox.indeterminate = false;
        } else if (checked.length > 0) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = true;
        } else {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
        }
    }

    // Toggle master checkbox
    function toggleMasterCheckbox() {
        console.log('🔄 Master checkbox toggled');
        var checkboxes = document.querySelectorAll('.staff-checkbox');
        if (masterCheckbox.checked) {
            checkboxes.forEach(function(cb) {
                cb.checked = true;
                selectedIds.add(cb.dataset.id);
            });
        } else {
            checkboxes.forEach(function(cb) {
                cb.checked = false;
                selectedIds.delete(cb.dataset.id);
            });
            allIds = [];
        }
        updateSelectedCount();
        updateMasterCheckbox();
    }

    // Individual checkboxes
    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('staff-checkbox')) {
            var id = e.target.dataset.id;
            if (e.target.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
                if (allIds.length > 0) {
                    allIds = [];
                    if (masterCheckbox) masterCheckbox.checked = false;
                }
            }
            updateSelectedCount();
            updateMasterCheckbox();
        }
    });

    // Select All
    function selectAllStaff() {
        console.log('🔄 Select All button clicked');
        var btn = document.getElementById('selectAllBtn');
        var originalText = btn ? btn.innerHTML : 'Select All';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        }

        var url = new URL(window.location.href);
        url.searchParams.set('select_all', 'true');

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function(data) {
            console.log('📥 Select All response:', data);
            if (data.ids) {
                allIds = data.ids;
                document.querySelectorAll('.staff-checkbox').forEach(function(cb) {
                    cb.checked = true;
                    selectedIds.add(cb.dataset.id);
                });
                selectedIds = new Set(allIds);
                updateSelectedCount();
                if (masterCheckbox) masterCheckbox.checked = true;
                showToast('Selected ' + allIds.length + ' staff members across all pages.', 'success');
            } else {
                showToast('No IDs returned.', 'error');
            }
        })
        .catch(function(error) {
            console.error('❌ Select All error:', error);
            showToast('Error loading all IDs: ' + error.message, 'error');
        })
        .finally(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }

    // Apply Bulk Action
    function applyBulkAction() {
        console.log('🔄 Apply Bulk Action clicked');
        var action = bulkActionSelect ? bulkActionSelect.value : '';
        if (!action) {
            showToast('Please select a bulk action.', 'warning');
            return;
        }

        if (selectedIds.size === 0) {
            showToast('Please select at least one staff member.', 'warning');
            return;
        }

        console.log('📊 Selected IDs:', Array.from(selectedIds));

        switch(action) {
            case 'activate':
                if (confirm('Activate ' + selectedIds.size + ' staff member(s)?')) {
                    executeBulkAction('activate');
                }
                break;
            case 'deactivate':
                if (confirm('Deactivate ' + selectedIds.size + ' staff member(s)?')) {
                    executeBulkAction('deactivate');
                }
                break;
            case 'change_role':
                document.getElementById('changeRoleCount').textContent = selectedIds.size;
                document.getElementById('changeRoleModal').style.display = 'block';
                break;
            case 'change_company':
                document.getElementById('changeCompanyCount').textContent = selectedIds.size;
                document.getElementById('changeCompanyModal').style.display = 'block';
                break;
            case 'delete':
                document.getElementById('deleteCount').textContent = selectedIds.size;
                document.getElementById('deleteModal').style.display = 'block';
                break;
            default:
                showToast('Invalid action.', 'error');
        }
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    function executeBulkAction(action) {
        console.log('🔄 Executing bulk action:', action);
        var ids = Array.from(selectedIds);
        var data = { user_ids: ids, action: action };

        if (action === 'change_role') {
            var roleId = document.getElementById('newRoleId').value;
            if (!roleId) {
                showToast('Please select a role.', 'warning');
                return;
            }
            data.role_id = roleId;
            closeModal('changeRoleModal');
        }

        if (action === 'change_company') {
            var companyId = document.getElementById('newCompanyId').value;
            if (!companyId) {
                showToast('Please select a company.', 'warning');
                return;
            }
            data.company_id = companyId;
            closeModal('changeCompanyModal');
        }

        if (action === 'delete') {
            closeModal('deleteModal');
            if (!confirm('Are you sure you want to delete these staff members?')) {
                return;
            }
        }

        var btn = document.getElementById('applyBulkAction');
        var originalText = btn ? btn.innerHTML : 'Apply';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : null;
        if (!csrfToken) {
            showToast('CSRF token missing.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = originalText; }
            return;
        }

        fetch('{{ route("admin.staff.bulk-action") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            console.log('📥 Bulk action response:', data);
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(function() { window.location.reload(); }, 1500);
            } else {
                showToast(data.error || 'Something went wrong.', 'error');
            }
        })
        .catch(function(error) {
            console.error('❌ Bulk action error:', error);
            showToast('Error: ' + error.message, 'error');
        })
        .finally(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }

    // Quick Update
    function quickUpdate(userId, field, value) {
        console.log('🔄 Quick update:', userId, field, value);
        var row = document.querySelector('#staff-row-' + userId);
        if (!row) {
            showToast('Row not found.', 'error');
            return;
        }

        var nameEl = row.querySelector('[data-field="name"]');
        var emailEl = row.querySelector('[data-field="email"]');
        var phoneEl = row.querySelector('[data-field="phone"]');
        var roleEl = row.querySelector('[data-field="role_id"]');
        var companyEl = row.querySelector('[data-field="company_id"]');
        var statusEl = row.querySelector('[data-field="status"]');

        var name = nameEl ? nameEl.innerText : '';
        var email = emailEl ? emailEl.innerText : '';
        var phone = phoneEl ? phoneEl.innerText : '';
        var roleId = roleEl ? roleEl.dataset.value : '';
        var companyId = companyEl ? companyEl.dataset.value : '';
        var status = statusEl ? statusEl.dataset.value : '';

        var payload = {
            name: name,
            email: email,
            phone: phone,
            role_id: parseInt(roleId) || 0,
            company_id: companyId ? parseInt(companyId) : null,
            status: parseInt(status) || 0
        };

        if (field === 'status') {
            payload.status = parseInt(value);
        } else if (field === 'role_id') {
            payload.role_id = parseInt(value);
        } else if (field === 'company_id') {
            payload.company_id = value ? parseInt(value) : null;
        } else {
            payload[field] = value;
        }

        var csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : null;
        if (!csrfToken) {
            showToast('CSRF token missing.', 'error');
            return;
        }

        fetch('{{ url("admin/staff/quick-update") }}/' + userId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message, 'success');
                if (data.row) {
                    var rowEl = document.getElementById('staff-row-' + userId);
                    if (rowEl) rowEl.outerHTML = data.row;
                }
                updateStats();
            } else {
                showToast(data.message || 'Update failed.', 'error');
            }
        })
        .catch(function(error) {
            console.error('❌ Quick update error:', error);
            showToast('Error: ' + error.message, 'error');
        });
    }

    // Export
    function exportStaff() {
        console.log('🔄 Export All called');
        var url = new URL('{{ route("admin.staff.export") }}');

        var search = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        var role = document.getElementById('roleFilter') ? document.getElementById('roleFilter').value : '';
        var company = document.getElementById('companyFilter') ? document.getElementById('companyFilter').value : '';
        var status = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
        var dateFrom = document.getElementById('dateFrom') ? document.getElementById('dateFrom').value : '';
        var dateTo = document.getElementById('dateTo') ? document.getElementById('dateTo').value : '';

        if (search) url.searchParams.append('search', search);
        if (role) url.searchParams.append('role_name', role);
        if (company) url.searchParams.append('company_id', company);
        if (status !== '') url.searchParams.append('status', status);
        if (dateFrom) url.searchParams.append('date_from', dateFrom);
        if (dateTo) url.searchParams.append('date_to', dateTo);

        console.log('📤 Export URL:', url.toString());
        window.location.href = url.toString();
    }

    // Export Selected
    function exportSelected() {
        console.log('🔄 Export Selected called');
        if (selectedIds.size === 0) {
            showToast('Please select at least one staff member.', 'warning');
            return;
        }

        var ids = Array.from(selectedIds);
        var url = new URL('{{ route("admin.staff.export") }}');
        url.searchParams.append('ids', ids.join(','));

        var search = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        var role = document.getElementById('roleFilter') ? document.getElementById('roleFilter').value : '';
        var company = document.getElementById('companyFilter') ? document.getElementById('companyFilter').value : '';
        var status = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
        var dateFrom = document.getElementById('dateFrom') ? document.getElementById('dateFrom').value : '';
        var dateTo = document.getElementById('dateTo') ? document.getElementById('dateTo').value : '';

        if (search) url.searchParams.append('search', search);
        if (role) url.searchParams.append('role_name', role);
        if (company) url.searchParams.append('company_id', company);
        if (status !== '') url.searchParams.append('status', status);
        if (dateFrom) url.searchParams.append('date_from', dateFrom);
        if (dateTo) url.searchParams.append('date_to', dateTo);

        console.log('📤 Export URL:', url.toString());
        window.location.href = url.toString();
    }

    // Update stats
    function updateStats() {
        console.log('🔄 Updating stats');
        var url = new URL('{{ route("admin.staff.index") }}');
        url.searchParams.set('stats_only', '1');

        var search = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        var role = document.getElementById('roleFilter') ? document.getElementById('roleFilter').value : '';
        var company = document.getElementById('companyFilter') ? document.getElementById('companyFilter').value : '';
        var status = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
        var dateFrom = document.getElementById('dateFrom') ? document.getElementById('dateFrom').value : '';
        var dateTo = document.getElementById('dateTo') ? document.getElementById('dateTo').value : '';

        if (search) url.searchParams.append('search', search);
        if (role) url.searchParams.append('role_name', role);
        if (company) url.searchParams.append('company_id', company);
        if (status !== '') url.searchParams.append('status', status);
        if (dateFrom) url.searchParams.append('date_from', dateFrom);
        if (dateTo) url.searchParams.append('date_to', dateTo);

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.stats) {
                document.getElementById('statsTotal').textContent = data.stats.total;
                document.getElementById('statsActive').textContent = data.stats.active;
                document.getElementById('statsPending').textContent = data.stats.pending;
                document.getElementById('statsInactive').textContent = data.stats.inactive;
                console.log('📊 Stats updated:', data.stats);
            }
        })
        .catch(function() {});
    }

    // Sort table
    function sortTable(column) {
        console.log('🔄 Sorting by:', column);
        var currentUrl = new URL(window.location.href);
        var currentSort = currentUrl.searchParams.get('sort');
        var currentDir = currentUrl.searchParams.get('direction') || 'desc';

        var newDir = 'asc';
        if (currentSort === column) {
            newDir = currentDir === 'asc' ? 'desc' : 'asc';
        }

        currentUrl.searchParams.set('sort', column);
        currentUrl.searchParams.set('direction', newDir);
        window.location.href = currentUrl.toString();
    }

    // Change per page
    function changePerPage() {
        var perPage = document.getElementById('perPage').value;
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', perPage);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    // Initialise
    updateSelectedCount();
    updateMasterCheckbox();
    console.log('✅ Staff Management JS initialised successfully.');
</script>
@endsection