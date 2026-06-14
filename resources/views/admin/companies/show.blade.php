{{-- resources/views/admin/companies/show.blade.php --}}
@extends('layouts.app')

@section('title', $company->name)

@section('content')
<div x-data="companyShowPage()" x-init="init()" class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="flex justify-between items-center">
                <div>
                    <a href="{{ route('admin.companies.index') }}" class="text-blue-600 hover:text-blue-900 mb-2 inline-block">
                        <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Companies
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $company->name }}</h1>
                    @if($company->registration_number)
                        <p class="text-gray-500 text-sm mt-1">Reg: {{ $company->registration_number }}</p>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button @click="openEditModal()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Company
                    </button>
                    <button @click="toggleStatus()" :class="companyStatus ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition">
                        <span x-text="companyStatus ? 'Deactivate' : 'Activate'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Company Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Company Staff</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalStaff">0</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Total Estates</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalEstates">0</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Total Units</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalUnits">0</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900/30">
                    <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Total Tenants</span>
                    <h3 class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90" x-text="stats.totalTenants">0</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900/30">
                    <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Subscription</span>
                    <h3 class="mt-2 text-lg font-bold text-gray-800 dark:text-white/90">
                        {{ $subscription?->plan->name ?? 'No Plan' }}
                    </h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-100 dark:bg-cyan-900/30">
                    <svg class="h-6 w-6 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
            </div>
            @if($subscription?->ends_at)
                <div class="mt-2 text-xs text-gray-500">Expires: {{ $subscription->ends_at->format('M d, Y') }}</div>
            @endif
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-5 pt-4 dark:border-gray-800">
            <div class="flex flex-wrap gap-2">
                <button @click="activeTab = 'info'" :class="activeTab === 'info' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Company Info
                </button>
                <button @click="activeTab = 'staff'; loadStaff()" :class="activeTab === 'staff' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    Company Staff (<span x-text="stats.totalStaff">0</span>)
                </button>
                <button @click="activeTab = 'estates'; loadEstatesAndTenants()" :class="activeTab === 'estates' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Estates & Tenants (<span x-text="stats.totalEstates">0</span>)
                </button>
                <button @click="activeTab = 'subscriptions'; loadSubscriptions()" :class="activeTab === 'subscriptions' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    Subscriptions
                </button>
                <button @click="activeTab = 'invoices'; loadInvoices()" :class="activeTab === 'invoices' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Invoices (<span x-text="stats.totalInvoices">0</span>)
                </button>
                <button @click="activeTab = 'payments'; loadPayments()" :class="activeTab === 'payments' ? 'border-brand-500 text-brand-600 dark:text-brand-400 border-b-2 -mb-px' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-2 text-sm font-medium transition-colors">
                    <svg class="inline w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                    </svg>
                    Payments (<span x-text="stats.totalPayments">0</span>)
                </button>
            </div>
        </div>
        
        <!-- Tab Content -->
        <div class="p-5">
            <!-- Company Info Tab -->
            <div x-show="activeTab === 'info'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Company Name</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->name }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Registration Number</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->registration_number ?? '-' }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Tax ID / VAT</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->tax_id ?? '-' }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Email Address</label>
                            <p class="mt-1 text-gray-900 dark:text-white">
                                @if($company->email)
                                    <a href="mailto:{{ $company->email }}" class="text-blue-600 hover:text-blue-700">{{ $company->email }}</a>
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Phone Number</label>
                            <p class="mt-1 text-gray-900 dark:text-white">
                                @if($company->phone)
                                    <a href="tel:{{ $company->phone }}" class="text-blue-600 hover:text-blue-700">{{ $company->phone }}</a>
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Physical Address</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->address ?? '-' }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Created Date</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->created_at->format('F d, Y H:i A') }}</p>
                        </div>
                        <div class="border-b pb-3">
                            <label class="block text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</label>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $company->updated_at->format('F d, Y H:i A') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Company Staff Tab -->
            <div x-show="activeTab === 'staff'" x-cloak>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Company Staff</h3>
                    <div class="flex gap-2">
                        <input type="text" x-model="staffSearch" @input="currentStaffPage = 1" placeholder="Search staff..." class="rounded-lg border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <button @click="openAddStaffModal()" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700">
                            Add Staff
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="staff in paginatedStaff" :key="staff.id">
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span class="text-blue-600 text-sm font-medium" x-text="staff.full_name?.charAt(0)"></span>
                                            </div>
                                            <span class="font-medium text-gray-900 dark:text-white" x-text="staff.full_name"></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="staff.email"></td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="staff.phone || '-'"></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-1 text-xs rounded-full" :class="staff.role_badge" x-text="staff.role_name"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300" x-text="staff.created_at_formatted"></td>
                                    <td class="px-4 py-3">
                                        <button @click="removeStaff(staff.id, staff.full_name)" class="text-red-600 hover:text-red-900 text-sm">Remove</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredStaff.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No staff members found. Add your first staff member above.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Staff Pagination -->
                <div x-show="filteredStaff.length > 0" class="mt-4 flex justify-between items-center">
                    <div class="text-sm text-gray-500">Showing <span x-text="staffShowingStart"></span> to <span x-text="staffShowingEnd"></span> of <span x-text="filteredStaff.length"></span></div>
                    <div class="flex gap-2">
                        <button @click="staffPrevPage()" :disabled="staffCurrentPage === 1" class="px-3 py-1 border rounded disabled:opacity-50">Previous</button>
                        <button @click="staffNextPage()" :disabled="staffCurrentPage === staffTotalPages" class="px-3 py-1 border rounded disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
            
            <!-- Estates & Tenants Tab - Hierarchical View -->
            <div x-show="activeTab === 'estates'" x-cloak>
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Estates under {{ $company->name }}</h3>
                    <p class="text-sm text-gray-500 mt-1">Click on any estate to view its units and tenants</p>
                </div>
                
                <div class="space-y-4">
                    <!-- Estate Cards -->
                    <div x-show="estates.length === 0" class="text-center py-8 text-gray-500">
                        No estates found for this company.
                    </div>
                    
                    <template x-for="estate in estates" :key="estate.id">
                        <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                            <!-- Estate Header -->
                            <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900 p-4 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition" @click="toggleEstate(estate.id)">
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white" x-text="estate.name"></h4>
                                            <p class="text-sm text-gray-500" x-text="estate.location || 'No location specified'"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-sm text-gray-500">
                                            <span class="font-semibold text-gray-700" x-text="estate.total_units"></span> units
                                            <span class="mx-1">•</span>
                                            <span class="font-semibold text-gray-700" x-text="estate.occupied_units"></span> occupied
                                        </div>
                                        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{'rotate-180': openEstates.includes(estate.id)}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Estate Content (Units & Tenants) -->
                            <div x-show="openEstates.includes(estate.id)" x-collapse class="border-t border-gray-200 dark:border-gray-700">
                                <div class="p-4 bg-white dark:bg-gray-900">
                                    <h5 class="text-md font-medium text-gray-800 dark:text-gray-200 mb-3">Units & Tenants</h5>
                                    
                                    <!-- Units Table -->
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                            <thead class="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Unit Number</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rent Amount</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Current Tenant</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                                <template x-for="unit in estate.units" :key="unit.id">
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                                        <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white" x-text="unit.unit_number"></td>
                                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400" x-text="unit.unit_type || '-'"></td>
                                                        <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400" x-text="formatCurrency(unit.rent_amount)"></td>
                                                        <td class="px-4 py-2">
                                                            <span :class="unit.status === 'occupied' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="px-2 py-1 text-xs rounded-full" x-text="unit.status"></span>
                                                        </td>
                                                        <td class="px-4 py-2 text-sm">
                                                            <div x-show="unit.current_tenant">
                                                                <div class="font-medium text-gray-900 dark:text-white" x-text="unit.current_tenant.name"></div>
                                                                <div class="text-xs text-gray-500" x-text="unit.current_tenant.email"></div>
                                                            </div>
                                                            <span x-show="!unit.current_tenant" class="text-gray-400">Vacant</span>
                                                        </td>
                                                        <td class="px-4 py-2 text-sm">
                                                            <div x-show="unit.current_tenant">
                                                                <a :href="'tel:' + unit.current_tenant.phone" class="text-blue-600 hover:text-blue-800" x-text="unit.current_tenant.phone || '-'"></a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </template>
                                                <tr x-show="estate.units.length === 0">
                                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500">No units found in this estate.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Summary Section -->
                                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                                        <div class="flex gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">Total Monthly Rent:</span>
                                                <span class="font-semibold text-gray-900 dark:text-white ml-2" x-text="formatCurrency(estate.total_monthly_rent || 0)"></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Occupancy Rate:</span>
                                                <span class="font-semibold text-gray-900 dark:text-white ml-2" x-text="estate.occupancy_rate + '%'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <!-- Subscriptions Tab -->
            <div x-show="activeTab === 'subscriptions'" x-cloak>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Subscription History</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Billing Cycle</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="subscription in subscriptions" :key="subscription.id">
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium" x-text="subscription.plan_name"></td>
                                    <td class="px-4 py-3 text-sm" x-text="subscription.billing_cycle"></td>
                                    <td class="px-4 py-3 text-sm" x-text="formatDate(subscription.starts_at)"></td>
                                    <td class="px-4 py-3 text-sm" x-text="formatDate(subscription.ends_at)"></td>
                                    <td class="px-4 py-3">
                                        <span :class="getStatusClass(subscription.status)" class="px-2 py-1 text-xs rounded-full" x-text="subscription.status"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium" x-text="formatCurrency(subscription.amount)"></td>
                                </tr>
                            </template>
                            <tr x-show="subscriptions.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No subscription history found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Invoices Tab -->
            <div x-show="activeTab === 'invoices'" x-cloak>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Invoices</h3>
                    <div class="flex gap-2">
                        <input type="text" x-model="invoiceSearch" placeholder="Search invoices..." class="rounded-lg border-gray-300 px-3 py-1 text-sm">
                        <select x-model="invoiceStatusFilter" class="rounded-lg border-gray-300 px-3 py-1 text-sm">
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="invoice in paginatedInvoices" :key="invoice.id">
                                <tr>
                                    <td class="px-4 py-3 text-sm" x-text="'#' + invoice.id"></td>
                                    <td class="px-4 py-3 text-sm" x-text="invoice.tenant_name"></td>
                                    <td class="px-4 py-3 text-sm" x-text="invoice.unit_number"></td>
                                    <td class="px-4 py-3 text-sm font-medium" x-text="formatCurrency(invoice.total_amount)"></td>
                                    <td class="px-4 py-3">
                                        <span :class="getInvoiceStatusClass(invoice.status)" class="px-2 py-1 text-xs rounded-full" x-text="invoice.status"></span>
                                    </td>
                                    <td class="px-4 py-3 text-sm" x-text="formatDate(invoice.created_at)"></td>
                                </tr>
                            </template>
                            <tr x-show="filteredInvoices.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No invoices found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Invoice Pagination -->
                <div x-show="filteredInvoices.length > 0" class="mt-4 flex justify-between items-center">
                    <div class="text-sm text-gray-500">Showing <span x-text="invoiceShowingStart"></span> to <span x-text="invoiceShowingEnd"></span> of <span x-text="filteredInvoices.length"></span></div>
                    <div class="flex gap-2">
                        <button @click="invoicePrevPage()" :disabled="invoiceCurrentPage === 1" class="px-3 py-1 border rounded disabled:opacity-50">Previous</button>
                        <button @click="invoiceNextPage()" :disabled="invoiceCurrentPage === invoiceTotalPages" class="px-3 py-1 border rounded disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
            
            <!-- Payments Tab -->
            <div x-show="activeTab === 'payments'" x-cloak>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Payment History</h3>
                    <div class="flex gap-2">
                        <input type="text" x-model="paymentSearch" placeholder="Search payments..." class="rounded-lg border-gray-300 px-3 py-1 text-sm">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-for="payment in payments" :key="payment.id">
                                <tr>
                                    <td class="px-4 py-3 text-sm" x-text="'#' + payment.id"></td>
                                    <td class="px-4 py-3 text-sm" x-text="payment.tenant_name"></td>
                                    <td class="px-4 py-3 text-sm" x-text="'#' + payment.invoice_id"></td>
                                    <td class="px-4 py-3 text-sm font-medium" x-text="formatCurrency(payment.amount)"></td>
                                    <td class="px-4 py-3 text-sm" x-text="payment.payment_method"></td>
                                    <td class="px-4 py-3 text-sm" x-text="formatDate(payment.payment_datetime)"></td>
                                </tr>
                            </template>
                            <tr x-show="payments.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No payments found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Company Modal (Slide-over) -->
<div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
    <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showEditModal = false"></div>
    <div class="fixed inset-y-0 right-0 max-w-full flex">
        <div class="relative w-screen max-w-md">
            <div class="h-full flex flex-col bg-white shadow-xl overflow-y-auto dark:bg-gray-900">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-white">Edit Company</h2>
                        <button @click="showEditModal = false" class="text-white hover:text-gray-200">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <form @submit.prevent="saveCompany" class="flex-1">
                    <div class="px-6 py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company Name *</label>
                            <input type="text" x-model="editForm.name" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Registration Number</label>
                            <input type="text" x-model="editForm.registration_number" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tax ID</label>
                            <input type="text" x-model="editForm.tax_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input type="email" x-model="editForm.email" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input type="text" x-model="editForm.phone" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                            <textarea x-model="editForm.address" rows="3" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" x-model="editForm.is_active" class="rounded border-gray-300 text-blue-600">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal (Slide-over) -->
<div x-show="showAddStaffModal" x-cloak class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
    <div class="absolute inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddStaffModal = false"></div>
    <div class="fixed inset-y-0 right-0 max-w-full flex">
        <div class="relative w-screen max-w-md">
            <div class="h-full flex flex-col bg-white shadow-xl overflow-y-auto dark:bg-gray-900">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-blue-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-white">Add Staff Member</h2>
                        <button @click="showAddStaffModal = false" class="text-white hover:text-gray-200">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <form @submit.prevent="addStaff" class="flex-1">
                    <div class="px-6 py-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name *</label>
                            <input type="text" x-model="newStaff.first_name" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name *</label>
                            <input type="text" x-model="newStaff.last_name" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                            <input type="email" x-model="newStaff.email" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input type="text" x-model="newStaff.phone" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role *</label>
                            <select x-model="newStaff.role_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                                <option value="">Select Role</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role->id }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Temporary Password *</label>
                            <input type="password" x-model="newStaff.password" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white" required>
                            <p class="text-xs text-gray-500 mt-1">User should change this after first login</p>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                        <button type="button" @click="showAddStaffModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                        <button type="submit" :disabled="addingStaff" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                            <span x-show="!addingStaff">Add Staff</span>
                            <span x-show="addingStaff">Adding...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
const csrfToken = "{{ csrf_token() }}";
const companyId = {{ $company->id }};
const currencySymbol = "{{ SystemHelper::currencySymbol() }}";
const availableRolesData = @json($availableRoles);

document.addEventListener('alpine:init', () => {
    Alpine.data('companyShowPage', () => ({
        // Tab state
        activeTab: 'info',
        
        // Stats
        stats: {
            totalStaff: 0,
            totalEstates: 0,
            totalUnits: 0,
            totalTenants: 0,
            totalRevenue: 0,
            totalInvoices: 0,
            totalPayments: 0
        },
        
        // Company data
        showEditModal: false,
        companyStatus: {{ $company->is_active ? 'true' : 'false' }},
        
        // Staff data
        staff: [],
        staffSearch: '',
        staffCurrentPage: 1,
        staffItemsPerPage: 10,
        showAddStaffModal: false,
        addingStaff: false,
        newStaff: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            role_id: '',
            password: ''
        },
        
        // Estates data with nested units and tenants
        estates: [],
        openEstates: [],
        
        // Subscriptions
        subscriptions: [],
        
        // Invoices
        invoices: [],
        invoiceSearch: '',
        invoiceStatusFilter: '',
        invoiceCurrentPage: 1,
        invoiceItemsPerPage: 10,
        
        // Payments
        payments: [],
        paymentSearch: '',
        
        // Edit form
        editForm: {
            name: '{{ addslashes($company->name) }}',
            registration_number: '{{ $company->registration_number }}',
            tax_id: '{{ $company->tax_id }}',
            email: '{{ $company->email }}',
            phone: '{{ $company->phone }}',
            address: '{{ addslashes($company->address) }}',
            is_active: {{ $company->is_active ? 'true' : 'false' }}
        },
        
        // Staff computed properties
        get filteredStaff() {
            let filtered = this.staff;
            if (this.staffSearch) {
                const search = this.staffSearch.toLowerCase();
                filtered = filtered.filter(s => 
                    s.full_name?.toLowerCase().includes(search) ||
                    s.email?.toLowerCase().includes(search)
                );
            }
            return filtered;
        },
        
        get paginatedStaff() {
            const start = (this.staffCurrentPage - 1) * this.staffItemsPerPage;
            return this.filteredStaff.slice(start, start + this.staffItemsPerPage);
        },
        
        get staffTotalPages() {
            return Math.ceil(this.filteredStaff.length / this.staffItemsPerPage);
        },
        
        get staffShowingStart() {
            return this.filteredStaff.length ? (this.staffCurrentPage - 1) * this.staffItemsPerPage + 1 : 0;
        },
        
        get staffShowingEnd() {
            return Math.min(this.staffCurrentPage * this.staffItemsPerPage, this.filteredStaff.length);
        },
        
        // Invoices computed properties
        get filteredInvoices() {
            let filtered = this.invoices;
            if (this.invoiceSearch) {
                const search = this.invoiceSearch.toLowerCase();
                filtered = filtered.filter(i => 
                    i.tenant_name?.toLowerCase().includes(search) ||
                    i.unit_number?.toLowerCase().includes(search) ||
                    i.id.toString().includes(search)
                );
            }
            if (this.invoiceStatusFilter) {
                filtered = filtered.filter(i => i.status === this.invoiceStatusFilter);
            }
            return filtered;
        },
        
        get paginatedInvoices() {
            const start = (this.invoiceCurrentPage - 1) * this.invoiceItemsPerPage;
            return this.filteredInvoices.slice(start, start + this.invoiceItemsPerPage);
        },
        
        get invoiceTotalPages() {
            return Math.ceil(this.filteredInvoices.length / this.invoiceItemsPerPage);
        },
        
        get invoiceShowingStart() {
            return this.filteredInvoices.length ? (this.invoiceCurrentPage - 1) * this.invoiceItemsPerPage + 1 : 0;
        },
        
        get invoiceShowingEnd() {
            return Math.min(this.invoiceCurrentPage * this.invoiceItemsPerPage, this.filteredInvoices.length);
        },
        
        // Methods
        init() {
            this.loadStaff();
            this.loadEstatesAndTenants();
            this.loadSubscriptions();
            this.loadInvoices();
            this.loadPayments();
        },
        
        formatCurrency(value) {
            return currencySymbol + ' ' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2 });
        },
        
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        
        getStatusClass(status) {
            const classes = {
                'active': 'bg-green-100 text-green-800',
                'trial': 'bg-blue-100 text-blue-800',
                'cancelled': 'bg-red-100 text-red-800',
                'past_due': 'bg-yellow-100 text-yellow-800',
                'expired': 'bg-gray-100 text-gray-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },
        
        getInvoiceStatusClass(status) {
            const classes = {
                'paid': 'bg-green-100 text-green-800',
                'unpaid': 'bg-red-100 text-red-800',
                'partial': 'bg-yellow-100 text-yellow-800',
                'draft': 'bg-gray-100 text-gray-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },
        
        openEditModal() {
            this.showEditModal = true;
        },
        
        openAddStaffModal() {
            this.newStaff = {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                role_id: '',
                password: ''
            };
            this.showAddStaffModal = true;
        },
        
        toggleEstate(estateId) {
            const index = this.openEstates.indexOf(estateId);
            if (index === -1) {
                this.openEstates.push(estateId);
            } else {
                this.openEstates.splice(index, 1);
            }
        },
        
        async toggleStatus() {
            const newStatus = !this.companyStatus;
            try {
                const response = await fetch(`/admin/companies/${companyId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ...this.editForm, is_active: newStatus })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.companyStatus = newStatus;
                    this.editForm.is_active = newStatus;
                    alert(`Company ${newStatus ? 'activated' : 'deactivated'} successfully!`);
                } else {
                    alert(result.message || 'Error updating company status');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error updating company status');
            }
        },
        
        async saveCompany() {
            try {
                const response = await fetch(`/admin/companies/${companyId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.editForm)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showEditModal = false;
                    window.location.reload();
                } else {
                    alert(result.message || 'Error updating company');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error updating company');
            }
        },
        
        async loadStaff() {
            try {
                const response = await fetch(`/admin/companies/${companyId}/staff`, {
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.staff = data.staff || data.users || [];
                this.stats.totalStaff = data.total || this.staff.length;
            } catch (error) {
                console.error('Error loading staff:', error);
                this.staff = [];
            }
        },
        
        async loadEstatesAndTenants() {
            try {
                const response = await fetch(`/admin/companies/${companyId}/estates-with-tenants`, {
                    headers: { 
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                this.estates = data.estates || [];
                this.stats.totalEstates = this.estates.length;
                this.stats.totalUnits = data.total_units || 0;
                this.stats.totalTenants = data.total_tenants || 0;
                
                // Auto-open first estate if any
                if (this.estates.length > 0 && this.openEstates.length === 0) {
                    this.openEstates.push(this.estates[0].id);
                }
            } catch (error) {
                console.error('Error loading estates:', error);
                this.estates = [];
            }
        },
        
        async addStaff() {
            if (!this.newStaff.first_name || !this.newStaff.last_name || !this.newStaff.email || !this.newStaff.role_id || !this.newStaff.password) {
                alert('Please fill in all required fields.');
                return;
            }
            
            this.addingStaff = true;
            
            try {
                const response = await fetch(`/admin/companies/${companyId}/users`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.newStaff)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showAddStaffModal = false;
                    await this.loadStaff();
                    alert(result.message);
                } else {
                    alert(result.message || 'Error adding staff member');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error adding staff member');
            } finally {
                this.addingStaff = false;
            }
        },
        
        async removeStaff(userId, userName) {
            if (!confirm(`Remove ${userName} from the company?`)) {
                return;
            }
            
            try {
                const response = await fetch(`/admin/companies/${companyId}/users/${userId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await this.loadStaff();
                    alert(result.message);
                } else {
                    alert(result.message || 'Error removing staff member');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error removing staff member');
            }
        },
        
        staffPrevPage() {
            if (this.staffCurrentPage > 1) {
                this.staffCurrentPage--;
            }
        },
        
        staffNextPage() {
            if (this.staffCurrentPage < this.staffTotalPages) {
                this.staffCurrentPage++;
            }
        },
        
        async loadSubscriptions() {
            try {
                const response = await fetch(`/admin/companies/${companyId}/subscriptions`, {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });
                const data = await response.json();
                this.subscriptions = data.subscriptions || [];
            } catch (error) {
                console.error('Error loading subscriptions:', error);
            }
        },
        
        async loadInvoices() {
            try {
                const response = await fetch(`/admin/companies/${companyId}/invoices`, {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });
                const data = await response.json();
                this.invoices = data.invoices || [];
                this.stats.totalInvoices = this.invoices.length;
                this.stats.totalRevenue = data.total_revenue || 0;
            } catch (error) {
                console.error('Error loading invoices:', error);
            }
        },
        
        invoicePrevPage() {
            if (this.invoiceCurrentPage > 1) {
                this.invoiceCurrentPage--;
            }
        },
        
        invoiceNextPage() {
            if (this.invoiceCurrentPage < this.invoiceTotalPages) {
                this.invoiceCurrentPage++;
            }
        },
        
        async loadPayments() {
            try {
                const response = await fetch(`/admin/companies/${companyId}/payments`, {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });
                const data = await response.json();
                this.payments = data.payments || [];
                this.stats.totalPayments = this.payments.length;
            } catch (error) {
                console.error('Error loading payments:', error);
            }
        }
    }));
});
</script>
@endsection