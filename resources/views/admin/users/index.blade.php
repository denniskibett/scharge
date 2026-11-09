@extends('layouts.app')

@section('title', 'User Management')

@php
    $users = $users ?? collect([]);
    $roles = $roles ?? collect([]);
    $companies = $companies ?? collect([]);

    $totalUsers = $users->total() ?? 0;
    $activeCount = $users->where('status', 0)->count();
    $pendingCount = $users->where('status', 2)->count();
    $inactiveCount = $users->where('status', 1)->count();

    // Additional stats – role-based breakdown using Spatie
    $staffCount = $users->filter(function($u) { 
        $roles = $u->getRoleNames()->toArray();
        return !empty(array_intersect($roles, ['admin', 'property_manager', 'accountant', 'security', 'maintenance', 'meter_reader', 'cleaning_staff']));
    })->count();
    
    $tenantCount = $users->filter(function($u) { 
        return $u->hasRole('tenant'); 
    })->count();
    
    $verifiedCount = $users->filter(function($u) { 
        return !is_null($u->email_verified_at); 
    })->count();

    $currentRole = request('role_id', '');
    $currentCompany = request('company_id', '');
    $currentStatus = request('status', '');
    $currentSearch = request('search', '');
    $currentVerified = request('verified', '');
    $currentSort = request('sort', 'created_at');
    $currentDirection = request('direction', 'desc');
    $currentDateFrom = request('date_from', '');
    $currentDateTo = request('date_to', '');
@endphp

@section('content')
<div>

    <!-- CSRF Meta -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- ============================================================ -->
    <!-- HEADER – Distinct Purple/Indigo Theme -->
    <!-- ============================================================ -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <i class="fas fa-users text-indigo-600 dark:text-indigo-400"></i>
                User Management
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage all system users, their roles, and access across the platform.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="exportUsers()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                <i class="fas fa-file-export"></i> Export
            </button>
            <button onclick="exportSelectedUsers()" id="exportSelectedBtn" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                <i class="fas fa-file-export"></i> Export Selected
            </button>
        </div>
    </div>

    {{-- Stat cards in the shared look (ids kept: the page script updates them), secondary figures as pills --}}
    @include('partials.card.row', ['cards' => [
        ['label' => 'Total users', 'value' => $totalUsers, 'icon' => 'users', 'tone' => 'brand', 'hint' => 'All accounts', 'id' => 'statsTotal'],
        ['label' => 'Active', 'value' => $activeCount, 'icon' => 'check-circle', 'tone' => 'green', 'hint' => 'Can sign in', 'id' => 'statsActive'],
        ['label' => 'Pending', 'value' => $pendingCount, 'icon' => 'clock', 'tone' => 'amber', 'hint' => 'Awaiting verification', 'id' => 'statsPending'],
        ['label' => 'Inactive', 'value' => $inactiveCount, 'icon' => 'no-symbol', 'tone' => 'red', 'hint' => 'Switched off', 'id' => 'statsInactive'],
    ]])
    @include('partials.card.pills', ['pills' => [
        ['label' => 'Staff', 'value' => $staffCount],
        ['label' => 'Tenants', 'value' => $tenantCount],
        ['label' => 'Verified', 'value' => $verifiedCount, 'tone' => 'green'],
        ['label' => 'Companies', 'value' => $companies->count()],
    ]])

    <!-- ============================================================ -->
    <!-- ACTIVE FILTER BAR -->
    <!-- ============================================================ -->
    @if($currentRole || $currentCompany || $currentStatus !== '' || $currentSearch || $currentVerified || $currentDateFrom || $currentDateTo)
    <div class="mb-4 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 p-3 border border-indigo-200 dark:border-indigo-800 flex flex-wrap items-center gap-2 text-sm">
        <i class="fas fa-filter text-indigo-600 dark:text-indigo-400"></i>
        <span class="font-medium text-indigo-700 dark:text-indigo-300">Active filters:</span>
        @if($currentRole)
            <span class="inline-flex items-center rounded-full bg-indigo-200 dark:bg-indigo-800 px-3 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                Role: {{ $roles->where('id', $currentRole)->first()?->name ?? 'Unknown' }}
            </span>
        @endif
        @if($currentCompany)
            <span class="inline-flex items-center rounded-full bg-indigo-200 dark:bg-indigo-800 px-3 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                Company: {{ $companies->where('id', $currentCompany)->first()?->name ?? 'Unknown' }}
            </span>
        @endif
        @if($currentStatus !== '')
            <span class="inline-flex items-center rounded-full bg-indigo-200 dark:bg-indigo-800 px-3 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                Status: {{ $currentStatus == 0 ? 'Active' : ($currentStatus == 1 ? 'Inactive' : 'Pending') }}
            </span>
        @endif
        @if($currentSearch)
            <span class="inline-flex items-center rounded-full bg-indigo-200 dark:bg-indigo-800 px-3 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                Search: "{{ $currentSearch }}"
            </span>
        @endif
        @if($currentVerified)
            <span class="inline-flex items-center rounded-full bg-indigo-200 dark:bg-indigo-800 px-3 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                Verified: {{ $currentVerified == 'verified' ? 'Verified' : 'Unverified' }}
            </span>
        @endif
        @if($currentDateFrom)
            <span class="inline-flex items-center rounded-full bg-indigo-200 dark:bg-indigo-800 px-3 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                From: {{ \Carbon\Carbon::parse($currentDateFrom)->format('d M Y') }}
            </span>
        @endif
        @if($currentDateTo)
            <span class="inline-flex items-center rounded-full bg-indigo-200 dark:bg-indigo-800 px-3 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200">
                To: {{ \Carbon\Carbon::parse($currentDateTo)->format('d M Y') }}
            </span>
        @endif
        <a href="{{ route('users.index') }}" class="ml-auto text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium text-xs">
            <i class="fas fa-times"></i> Clear All
        </a>
    </div>
    @endif

    <!-- ============================================================ -->
    <!-- USERS TABLE (filters, bulk actions, table, pager in one card) -->
    <!-- ============================================================ -->
    @php
        $userTab = fn ($v) => request()->fullUrlWithQuery(['status' => $v, 'page' => null]);
        $userTabs = [
            ['label' => 'All', 'url' => $userTab(null), 'active' => $currentStatus === ''],
            ['label' => 'Active', 'url' => $userTab('0'), 'active' => $currentStatus === '0', 'tone' => 'green'],
            ['label' => 'Pending', 'url' => $userTab('2'), 'active' => $currentStatus === '2', 'tone' => 'amber'],
            ['label' => 'Inactive', 'url' => $userTab('1'), 'active' => $currentStatus === '1', 'tone' => 'red'],
        ];
        $menuItem = 'text-theme-xs flex w-full items-center rounded-lg px-3 py-2 text-left font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300';
    @endphp
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <form id="filterForm" method="GET" action="{{ route('users.index') }}" class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-700">
            <input type="hidden" name="status" id="statusFilter" value="{{ $currentStatus }}">
            @include('partials.table.tabs', ['tabs' => $userTabs])

            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                <div class="relative">
                    <select name="role_id" id="roleFilter" aria-label="Role" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="dark:bg-dark-900 h-11 w-full min-w-[110px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected($currentRole == $role->id)>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
                </div>
                <div class="relative">
                    <select name="company_id" id="companyFilter" aria-label="Company" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="dark:bg-dark-900 h-11 w-full min-w-[140px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">All Companies</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected($currentCompany == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
                </div>
                <div class="relative">
                    <select name="verified" id="verifiedFilter" aria-label="Verified" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="dark:bg-dark-900 h-11 w-full min-w-[110px] appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-8 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">Any email</option>
                        <option value="verified" @selected($currentVerified == 'verified')>Verified</option>
                        <option value="unverified" @selected($currentVerified == 'unverified')>Unverified</option>
                    </select>
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.293 7.293C5.68342 6.90258 6.31658 6.90258 6.707 7.293L10 10.586L13.293 7.293C13.6834 6.90258 14.3166 6.90258 14.707 7.293C15.0974 7.68342 15.0974 8.31658 14.707 8.707L10.707 12.707C10.3166 13.0974 9.68342 13.0974 9.293 12.707L5.293 8.707C4.90258 8.31658 4.90258 7.68342 5.293 7.293Z" fill=""/></svg></span>
                </div>
                <input type="date" name="date_from" id="dateFrom" value="{{ $currentDateFrom }}" aria-label="Joined from" title="Joined from" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <input type="date" name="date_to" id="dateTo" value="{{ $currentDateTo }}" aria-label="Joined to" title="Joined to" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <div class="relative">
                    <span class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"><svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37363C3.04199 5.87693 5.87735 3.04199 9.37533 3.04199C12.8733 3.04199 15.7087 5.87693 15.7087 9.37363C15.7087 12.8703 12.8733 15.7053 9.37533 15.7053C5.87735 15.7053 3.04199 12.8703 3.04199 9.37363ZM9.37533 1.54199C5.04926 1.54199 1.54199 5.04817 1.54199 9.37363C1.54199 13.6991 5.04926 17.2053 9.37533 17.2053C11.2676 17.2053 13.0032 16.5344 14.3572 15.4176L17.1773 18.238C17.4702 18.5309 17.945 18.5309 18.2379 18.238C18.5308 17.9451 18.5309 17.4703 18.238 17.1773L15.4182 14.3573C16.5367 13.0033 17.2087 11.2669 17.2087 9.37363C17.2087 5.04817 13.7014 1.54199 9.37533 1.54199Z" fill=""/></svg></span>
                    <input type="search" name="search" id="searchInput" size="14" value="{{ $currentSearch }}" placeholder="Search users…" aria-label="Search users" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full min-w-[140px] rounded-lg border border-gray-300 bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                </div>
                @if($currentRole || $currentCompany || $currentStatus !== '' || $currentSearch || $currentVerified || $currentDateFrom || $currentDateTo)
                    <a href="{{ route('users.index') }}" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" title="Clear filters">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear
                    </a>
                @endif
                <button type="button" onclick="openAddUserDrawer()" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add User</button>
            </div>
        </form>

        <!-- Bulk actions -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 px-5 py-3 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <button type="button" id="selectAllBtn" onclick="selectAllUsers()" class="inline-flex h-9 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800">
                    <i class="fas fa-check-double"></i> Select All
                </button>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    <span id="selectedCount" class="font-semibold text-gray-800 dark:text-white/90">0</span> selected
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select id="bulkAction" aria-label="Bulk action" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="change_role">Change Role</option>
                    <option value="change_company">Change Company</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="button" id="applyBulkAction" onclick="applyBulkAction()" class="inline-flex h-9 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                    Apply
                </button>
                <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">Show</span>
                <select id="perPage" aria-label="Rows per page" class="h-9 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" onchange="changePerPage()">
                    <option value="10" @selected($users->perPage() == 10)>10</option>
                    <option value="20" @selected($users->perPage() == 20)>20</option>
                    <option value="50" @selected($users->perPage() == 50)>50</option>
                    <option value="100" @selected($users->perPage() == 100)>100</option>
                </select>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="data-table w-full table-auto">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800">
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 w-10">
                            <input type="checkbox" id="masterCheckbox" onchange="toggleMasterCheckbox()" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" onclick="sortTable('id')">
                            ID <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" onclick="sortTable('name')">
                            User <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" onclick="sortTable('email')">
                            Email <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" onclick="sortTable('phone')">
                            Phone <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" onclick="sortTable('role_id')">
                            Role <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" onclick="sortTable('company_id')">
                            Company <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" onclick="sortTable('status')">
                            Status <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="p-4 text-left text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300" onclick="sortTable('created_at')">
                            Joined <i class="fas fa-sort ml-1 text-xs"></i>
                        </th>
                        <th class="p-4 text-right text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody id="usersTableBody" class="divide-x divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($users as $user)
                        @php
                            $primaryRole = $user->getRoleNames()->first() ?? 'No Role';
                            $roleNames = $user->getRoleNames()->toArray();
                            $isSysadmin = $user->hasRole('sysadmin');
                            $isTenant = $user->hasRole('tenant');
                            $verified = !is_null($user->email_verified_at);
                            $statusLabel = $user->status == 0 ? 'Active' : ($user->status == 1 ? 'Inactive' : 'Pending');
                            $statusColor = $user->status == 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($user->status == 1 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200');
                            $verifiedIcon = $verified ? 'fa-check-circle text-emerald-500' : 'fa-clock text-amber-500';
                        @endphp
                        <tr id="user-row-{{ $user->id }}" class="transition hover:bg-gray-50 dark:hover:bg-gray-900">
                            <td class="p-4 whitespace-nowrap">
                                <input type="checkbox" class="user-checkbox rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" data-id="{{ $user->id }}">
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                #{{ $user->id }}
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    @if($user->avatar)
                                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="h-8 w-8 flex-shrink-0 rounded-full object-cover">
                                    @else
                                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
                                            <span class="text-sm font-medium text-purple-600">{{ $user->getInitialsAttribute() ?? substr($user->name, 0, 1) }}</span>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="text-sm font-medium text-gray-800 dark:text-white/90" data-field="name">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                            <i class="fas {{ $verifiedIcon }} text-xs"></i>
                                            {{ $verified ? 'Verified' : 'Unverified' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" data-field="email">{{ $user->email }}</td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" data-field="phone">{{ $user->phone ?? '-' }}</td>
                            <td class="p-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    {{ $primaryRole }}
                                </span>
                                @if(count($roleNames) > 1)
                                    <span class="text-xs text-gray-400 dark:text-gray-500">+{{ count($roleNames) - 1 }}</span>
                                @endif
                                <span data-field="role_id" data-value="{{ $user->roles->first()?->id ?? '' }}" style="display:none;">{{ $user->roles->first()?->id ?? '' }}</span>
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $user->company->name ?? '-' }}
                                <span data-field="company_id" data-value="{{ $user->company_id ?? '' }}" style="display:none;">{{ $user->company_id ?? '' }}</span>
                            </td>
                            <td class="p-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColor }}">
                                    {{ $statusLabel }}
                                </span>
                                <span data-field="status" data-value="{{ $user->status }}" style="display:none;">{{ $user->status }}</span>
                            </td>
                            <td class="p-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $user->created_at->format('d M Y') }}
                            </td>
                            <td class="p-4 whitespace-nowrap text-right">
                                <div x-data="{ open: false, x: 0, y: 0 }" class="relative" @click.outside="open = false" @scroll.window="open = false">
                                  <button type="button" @click="const r = $el.getBoundingClientRect(); x = r.right; y = r.bottom + 4; open = !open" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" aria-label="Actions"><svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill=""/></svg></button>
                                  <div x-show="open" x-cloak :style="`position:fixed;top:${y}px;left:${x - 160}px`" class="shadow-theme-lg dark:bg-gray-dark z-50 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 dark:border-gray-800">
                                    <a href="{{ route('users.show', $user->id) }}" class="{{ $menuItem }}">View</a>
                                    <a href="{{ route('users.edit', $user->id) }}" class="{{ $menuItem }}">Edit</a>
                                    @if(!$verified)
                                      <button type="button" @click="open = false" onclick="verifyUser({{ $user->id }})" class="{{ $menuItem }}">Verify</button>
                                    @endif
                                    <button type="button" @click="open = false" onclick="openAssignModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->email }}', '{{ $primaryRole }}')" class="{{ $menuItem }}">Assign company</button>
                                    @if(!$isSysadmin)
                                      <button type="button" @click="open = false" onclick="if(confirm('Delete user {{ $user->name }}?')) { fetch('{{ route('users.destroy', $user->id) }}', { method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }).then(r=>r.json()).then(d=>{ if(d.success){ (window.schargeRefresh || (() => location.reload()))(); } else { alert(d.message); } }); }" class="text-theme-xs flex w-full rounded-lg px-3 py-2 text-left font-medium text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-500/10">Delete</button>
                                    @endif
                                  </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="p-4 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No users found</h3>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters or search.</p>
  </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($users, 'lastPage'))
            @include('partials.table.pager', ['paginator' => $users->appends(request()->query())])
        @endif
    </div>
</div>

<!-- ============================================================ -->
<!-- ADD USER DRAWER -->
<!-- ============================================================ -->
<div id="addUserDrawer" style="display: none;">
    <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" onclick="closeAddUserDrawer()"></div>
    <div class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999 transform transition-transform duration-300 ease-in-out" style="width: 42rem; max-width: calc(100% - 2rem); transform: translateX(100%);">
        <div class="p-6 lg:p-10">
            <button type="button" onclick="closeAddUserDrawer()" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11" aria-label="Close"><svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" /></svg></button>
            <h4 class="mb-1 text-lg font-medium text-gray-800 dark:text-white/90">Add User</h4>
            <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Create a new system user</p>
            <form id="addUserForm" onsubmit="submitAddUser(event)">
                @csrf
                <div class="space-y-4">
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                            <i class="fas fa-user-circle text-indigo-500"></i> Personal Information
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="userName" name="name" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="userEmail" name="email" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                                <input type="text" id="userPhone" name="phone" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                            </div>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                            <i class="fas fa-lock text-indigo-500"></i> Password
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="userPassword" name="password" required minlength="8" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="userPasswordConfirmation" name="password_confirmation" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                            </div>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                            <i class="fas fa-briefcase text-indigo-500"></i> Role &amp; Company
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Role <span class="text-red-500">*</span>
                                </label>
                                <select id="userRole" name="role_id" required class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                                    <option value="">Select Role...</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company</label>
                                <select id="userCompany" name="company_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                                    <option value="">No Company</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                <select id="userStatus" name="status" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition">
                                    <option value="0">Active</option>
                                    <option value="1">Inactive</option>
                                    <option value="2">Pending</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="button" onclick="closeAddUserDrawer()" class="px-5 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition">Cancel</button>
                        <button type="submit" id="submitUserBtn" class="px-6 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition flex items-center gap-2 shadow-sm">
                            <i class="fas fa-save"></i> Create User
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODALS -->
<!-- ============================================================ -->
<div id="changeRoleModal" style="display: none;">
    <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" onclick="closeModal('changeRoleModal')"></div>
    <div class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999" style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" onclick="closeModal('changeRoleModal')" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11" aria-label="Close"><svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" /></svg></button>
            <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Change Role</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select new role for <span id="changeRoleCount" class="font-semibold">0</span> user(s).</p>
            <select id="newRoleId" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Select Role...</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                @endforeach
            </select>
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="closeModal('changeRoleModal')" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">Cancel</button>
                <button type="button" onclick="executeBulkAction('change_role')" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">Apply</button>
            </div>
        </div>
    </div>
</div>

<div id="changeCompanyModal" style="display: none;">
    <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999" onclick="closeModal('changeCompanyModal')"></div>
    <div class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999" style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" onclick="closeModal('changeCompanyModal')" class="group absolute right-3 top-3 z-99999 flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11" aria-label="Close"><svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" /></svg></button>
            <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Change Company</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select new company for <span id="changeCompanyCount" class="font-semibold">0</span> user(s).</p>
            <select id="newCompanyId" class="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Select Company...</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="closeModal('changeCompanyModal')" class="shadow-theme-xs flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">Cancel</button>
                <button type="button" onclick="executeBulkAction('change_company')" class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-3 text-sm font-medium text-white transition">Apply</button>
            </div>
        </div>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px]" onclick="closeModal('deleteModal')"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">Confirm Delete</h3>
                <button onclick="closeModal('deleteModal')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">Are you sure you want to delete <span id="deleteCount" class="font-semibold text-red-600">0</span> user(s)? This action cannot be undone.</p>
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

<!-- ============================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================ -->
<script>
    console.log('User Management script loaded');

    var selectedIds = new Set();
    var allIds = [];

    var masterCheckbox = document.getElementById('masterCheckbox');
    var selectedCountSpan = document.getElementById('selectedCount');
    var bulkActionSelect = document.getElementById('bulkAction');
    var drawer = document.getElementById('addUserDrawer');
    var drawerPanel = drawer ? drawer.querySelector('.fixed.right-0') : null;

    function openAddUserDrawer() {
        if (!drawer) return;
        drawer.style.display = 'block';
        setTimeout(function() {
            if (drawerPanel) drawerPanel.style.transform = 'translateX(0)';
        }, 10);
        document.body.style.overflow = 'hidden';
    }

    function closeAddUserDrawer() {
        if (!drawer || !drawerPanel) return;
        drawerPanel.style.transform = 'translateX(100%)';
        setTimeout(function() {
            drawer.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }

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

    function verifyUser(userId) {
        if (!confirm('Verify this user?')) return;
        
        fetch('/admin/users/' + userId + '/verify', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => (window.schargeRefresh || (() => location.reload()))(), 1500);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Error: ' + error.message, 'error');
        });
    }

    function openAssignModal(userId, userName, userEmail, userRole) {
        if (window.userAssignModal) {
            window.userAssignModal.openModal(userId, userName, userEmail, userRole);
        } else {
            showToast('Assign modal not loaded. Please refresh.', 'warning');
        }
    }

    function submitAddUser(event) {
        event.preventDefault();
        console.log('Add User form submitted');

        const form = document.getElementById('addUserForm');
        const submitBtn = document.getElementById('submitUserBtn');
        const originalText = submitBtn.innerHTML;

        const formData = new FormData(form);
        const data = {
            name: formData.get('name'),
            email: formData.get('email'),
            password: formData.get('password'),
            password_confirmation: formData.get('password_confirmation'),
            phone: formData.get('phone'),
            role_id: parseInt(formData.get('role_id')),
            company_id: formData.get('company_id') ? parseInt(formData.get('company_id')) : null,
            status: parseInt(formData.get('status')) || 0,
        };

        if (!data.name || !data.email || !data.password || !data.role_id) {
            showToast('Please fill in all required fields.', 'warning');
            return;
        }
        if (data.password !== data.password_confirmation) {
            showToast('Passwords do not match.', 'error');
            return;
        }
        if (data.password.length < 8) {
            showToast('Password must be at least 8 characters.', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrfToken) {
            showToast('CSRF token missing. Please refresh the page.', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            return;
        }

        const url = '{{ route("users.store") }}';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(async response => {
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response. Check logs.');
            }
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || JSON.stringify(errorData.errors || 'Validation error'));
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast(data.message || 'User created successfully!', 'success');
                closeAddUserDrawer();
                form.reset();
                setTimeout(() => (window.schargeRefresh || (() => location.reload()))(), 1000);
            } else {
                let errorMsg = data.message || 'Something went wrong.';
                if (data.errors && Array.isArray(data.errors)) {
                    errorMsg = data.errors.join('\n');
                }
                showToast(errorMsg, 'error');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showToast('Error: ' + error.message, 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    function updateSelectedCount() {
        if (selectedCountSpan) {
            selectedCountSpan.textContent = selectedIds.size;
        }
    }

    function updateMasterCheckbox() {
        if (!masterCheckbox) return;
        var checkboxes = document.querySelectorAll('.user-checkbox');
        var checked = document.querySelectorAll('.user-checkbox:checked');

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

    function toggleMasterCheckbox() {
        var checkboxes = document.querySelectorAll('.user-checkbox');
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

    document.addEventListener('change', function(e) {
        if (e.target.classList && e.target.classList.contains('user-checkbox')) {
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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddUserDrawer();
            closeModal('changeRoleModal');
            closeModal('changeCompanyModal');
            closeModal('deleteModal');
        }
    });

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    function selectAllUsers() {
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
            if (data.ids) {
                allIds = data.ids;
                document.querySelectorAll('.user-checkbox').forEach(function(cb) {
                    cb.checked = true;
                    selectedIds.add(cb.dataset.id);
                });
                selectedIds = new Set(allIds);
                updateSelectedCount();
                if (masterCheckbox) masterCheckbox.checked = true;
                showToast('Selected ' + allIds.length + ' users across all pages.', 'success');
            } else {
                showToast('No IDs returned.', 'error');
            }
        })
        .catch(function(error) {
            console.error('Select All error:', error);
            showToast('Error loading all IDs: ' + error.message, 'error');
        })
        .finally(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }

    function applyBulkAction() {
        var action = bulkActionSelect ? bulkActionSelect.value : '';
        if (!action) {
            showToast('Please select a bulk action.', 'warning');
            return;
        }
        if (selectedIds.size === 0) {
            showToast('Please select at least one user.', 'warning');
            return;
        }

        switch(action) {
            case 'activate':
                if (confirm('Activate ' + selectedIds.size + ' user(s)?')) {
                    executeBulkAction('activate');
                }
                break;
            case 'deactivate':
                if (confirm('Deactivate ' + selectedIds.size + ' user(s)?')) {
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

    function executeBulkAction(action) {
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
            if (!confirm('Are you sure you want to delete these users?')) {
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

        fetch('{{ route("users.create") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(function() { (window.schargeRefresh || (() => location.reload()))(); }, 1500);
            } else {
                showToast(data.error || 'Something went wrong.', 'error');
            }
        })
        .catch(function(error) {
            console.error('Bulk action error:', error);
            showToast('Error: ' + error.message, 'error');
        })
        .finally(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }

    function quickUpdate(userId, field, value) {
        var row = document.querySelector('#user-row-' + userId);
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

        fetch('{{ url("admin/users/quick-update") }}/' + userId, {
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
                    var rowEl = document.getElementById('user-row-' + userId);
                    if (rowEl) rowEl.outerHTML = data.row;
                }
                updateStats();
            } else {
                showToast(data.message || 'Update failed.', 'error');
            }
        })
        .catch(function(error) {
            console.error('Quick update error:', error);
            showToast('Error: ' + error.message, 'error');
        });
    }

    function exportSelectedUsers() {
        if (selectedIds.size === 0) {
            showToast('Please select at least one user.', 'warning');
            return;
        }

        var ids = Array.from(selectedIds);
        var url = new URL('{{ route("users.create") }}');
        url.searchParams.append('ids', ids.join(','));

        var search = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        var role = document.getElementById('roleFilter') ? document.getElementById('roleFilter').value : '';
        var company = document.getElementById('companyFilter') ? document.getElementById('companyFilter').value : '';
        var status = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
        var verified = document.getElementById('verifiedFilter') ? document.getElementById('verifiedFilter').value : '';
        var dateFrom = document.getElementById('dateFrom') ? document.getElementById('dateFrom').value : '';
        var dateTo = document.getElementById('dateTo') ? document.getElementById('dateTo').value : '';

        if (search) url.searchParams.append('search', search);
        if (role) url.searchParams.append('role_id', role);
        if (company) url.searchParams.append('company_id', company);
        if (status !== '') url.searchParams.append('status', status);
        if (verified) url.searchParams.append('verified', verified);
        if (dateFrom) url.searchParams.append('date_from', dateFrom);
        if (dateTo) url.searchParams.append('date_to', dateTo);

        window.location.href = url.toString(); // export: a normal download
    }

    function exportUsers() {
        var url = new URL('{{ route("users.create") }}');

        var search = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        var role = document.getElementById('roleFilter') ? document.getElementById('roleFilter').value : '';
        var company = document.getElementById('companyFilter') ? document.getElementById('companyFilter').value : '';
        var status = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
        var verified = document.getElementById('verifiedFilter') ? document.getElementById('verifiedFilter').value : '';
        var dateFrom = document.getElementById('dateFrom') ? document.getElementById('dateFrom').value : '';
        var dateTo = document.getElementById('dateTo') ? document.getElementById('dateTo').value : '';

        if (search) url.searchParams.append('search', search);
        if (role) url.searchParams.append('role_id', role);
        if (company) url.searchParams.append('company_id', company);
        if (status !== '') url.searchParams.append('status', status);
        if (verified) url.searchParams.append('verified', verified);
        if (dateFrom) url.searchParams.append('date_from', dateFrom);
        if (dateTo) url.searchParams.append('date_to', dateTo);

        window.location.href = url.toString(); // export: a normal download
    }

    function updateStats() {
        var url = new URL('{{ route("users.index") }}');
        url.searchParams.set('stats_only', '1');

        var search = document.getElementById('searchInput') ? document.getElementById('searchInput').value : '';
        var role = document.getElementById('roleFilter') ? document.getElementById('roleFilter').value : '';
        var company = document.getElementById('companyFilter') ? document.getElementById('companyFilter').value : '';
        var status = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';
        var verified = document.getElementById('verifiedFilter') ? document.getElementById('verifiedFilter').value : '';
        var dateFrom = document.getElementById('dateFrom') ? document.getElementById('dateFrom').value : '';
        var dateTo = document.getElementById('dateTo') ? document.getElementById('dateTo').value : '';

        if (search) url.searchParams.append('search', search);
        if (role) url.searchParams.append('role_id', role);
        if (company) url.searchParams.append('company_id', company);
        if (status !== '') url.searchParams.append('status', status);
        if (verified) url.searchParams.append('verified', verified);
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
            }
        })
        .catch(function() {});
    }

    function sortTable(column) {
        var currentUrl = new URL(window.location.href);
        var currentSort = currentUrl.searchParams.get('sort');
        var currentDir = currentUrl.searchParams.get('direction') || 'desc';

        var newDir = 'asc';
        if (currentSort === column) {
            newDir = currentDir === 'asc' ? 'desc' : 'asc';
        }

        currentUrl.searchParams.set('sort', column);
        currentUrl.searchParams.set('direction', newDir);
        (window.spaVisit || ((u) => { window.location.href = u; }))(currentUrl.toString());
    }

    function changePerPage() {
        var perPage = document.getElementById('perPage').value;
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', perPage);
        url.searchParams.delete('page');
        (window.spaVisit || ((u) => { window.location.href = u; }))(url.toString());
    }

    updateSelectedCount();
    updateMasterCheckbox();
    console.log('User Management JS initialised successfully.');
</script>
@endsection