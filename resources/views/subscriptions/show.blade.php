{{-- resources/views/subscriptions/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Subscription Plan Details - ' . ($planData['name'] ?? 'Plan'))

@section('content')
<div x-data="{ activeTab: 'features' }" class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="{{ route('admin.subscriptions.plans.index') }}" class="inline-flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Plans
        </a>
    </div>

    <!-- Breadcrumb -->
    <nav class="flex mb-6 text-sm" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="{{ route('dashboard') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                    </svg>
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <a href="{{ route('admin.subscriptions.plans.index') }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white ml-1 md:ml-2">Plans</a>
                </div>
            </li>
            @if(isset($firstCompany) && $firstCompany)
            <li>
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <a href="{{ route('admin.companies.show', $firstCompany->id) }}" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white ml-1 md:ml-2">Companies</a>
                </div>
            </li>
            @endif
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-gray-700 dark:text-white ml-1 md:ml-2 font-medium">{{ $planData['name'] }}</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Plan Header -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-700 rounded-2xl p-6 shadow-lg mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                <div class="h-16 w-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ $planData['name'] }}</h1>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="inline-flex px-3 py-1 text-sm rounded-full {{ $planData['is_active'] ? 'bg-green-500/30 text-green-200' : 'bg-red-500/30 text-red-200' }}">
                            {{ $planData['is_active'] ? 'Active' : 'Inactive' }}
                        </span>
                        <span class="text-purple-100 text-sm">Slug: {{ $planData['slug'] }}</span>
                    </div>
                    @if($planData['description'])
                        <p class="text-purple-100 text-sm mt-1">{{ $planData['description'] }}</p>
                    @endif
                </div>
            </div>
            <div class="mt-4 md:mt-0 flex items-center gap-3">
                <button onclick="window.subscriptionsCreateModal?.openModal({{ $planData['id'] }})" class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg transition backdrop-blur-sm">
                    Edit Plan
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards Grid - Using the Card Component -->
    @include('partials.card.card-subscription', [
        'planData' => $planData,
        'invoices' => $invoices,
        'companies' => $companies,
        'accountManagers' => $accountManagers,
    ])

    <!-- Main Card with Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Tab Navigation inside the card -->
        <div class="border-b border-gray-200 dark:border-gray-700 px-4 pt-3">
            <nav class="flex flex-wrap gap-1 -mb-px">
                <button @click="activeTab = 'features'" 
                    :class="activeTab === 'features' ? 'border-purple-500 text-purple-600 dark:text-purple-400 bg-purple-50/50 dark:bg-purple-900/10' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'"
                    class="inline-flex items-center px-4 py-2.5 border-b-2 font-medium text-sm transition rounded-t-lg">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Features
                    <span class="ml-1.5 text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full">{{ count($planData['business_features'] ?? []) }}</span>
                </button>
                <button @click="activeTab = 'invoices'" 
                    :class="activeTab === 'invoices' ? 'border-purple-500 text-purple-600 dark:text-purple-400 bg-purple-50/50 dark:bg-purple-900/10' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'"
                    class="inline-flex items-center px-4 py-2.5 border-b-2 font-medium text-sm transition rounded-t-lg">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Invoices
                    <span class="ml-1.5 text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full">{{ $invoices->count() }}</span>
                </button>
                <button @click="activeTab = 'companies'" 
                    :class="activeTab === 'companies' ? 'border-purple-500 text-purple-600 dark:text-purple-400 bg-purple-50/50 dark:bg-purple-900/10' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'"
                    class="inline-flex items-center px-4 py-2.5 border-b-2 font-medium text-sm transition rounded-t-lg">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Companies
                    <span class="ml-1.5 text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full">{{ $companies->count() }}</span>
                </button>
                <button @click="activeTab = 'managers'" 
                    :class="activeTab === 'managers' ? 'border-purple-500 text-purple-600 dark:text-purple-400 bg-purple-50/50 dark:bg-purple-900/10' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'"
                    class="inline-flex items-center px-4 py-2.5 border-b-2 font-medium text-sm transition rounded-t-lg">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Account Managers
                    <span class="ml-1.5 text-xs bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full">{{ $accountManagers->count() }}</span>
                </button>
            </nav>
        </div>

        <!-- Tab Content - All Partials -->
        <div class="p-6">
            <!-- Features Tab -->
            <div x-show="activeTab === 'features'" x-cloak>
                @include('partials.table.table-subscriptions-features', ['planData' => $planData])
            </div>

            <!-- Invoices Tab -->
            <div x-show="activeTab === 'invoices'" x-cloak>
                @include('partials.table.table-subscriptions-invoices', [
                    'planData' => $planData,
                    'invoices' => $invoices,
                    'planId' => $planData['id']
                ])
            </div>

            <!-- Companies Tab -->
            <div x-show="activeTab === 'companies'" x-cloak>
                @include('partials.table.table-subscriptions-companies', [
                    'planData' => $planData,
                    'companies' => $companies,
                    'planId' => $planData['id']
                ])
            </div>

            <!-- Account Managers Tab -->
            <div x-show="activeTab === 'managers'" x-cloak>
                @include('partials.table.table-subscriptions-managers', [
                    'planData' => $planData,
                    'accountManagers' => $accountManagers,
                    'planId' => $planData['id']
                ])
            </div>
        </div>
    </div>
</div>

<!-- Include All Modals -->
@include('partials.modal.subscriptions-create-modal')
@include('partials.modal.subscriptions-invoices-create')
@include('partials.modal.subscriptions-companies-create')
@include('partials.modal.subscriptions-managers-create')

<style>
[x-cloak] { display: none !important; }
</style>

<script>
document.addEventListener('alpine:init', () => {
    if (!window.subscriptionsCreateModal) {
        console.warn('Subscriptions create modal not initialized');
    }
});
</script>

@push('scripts')
<script>
window.editPlan = function(planId) {
    if (window.subscriptionsCreateModal) {
        window.subscriptionsCreateModal.openModal(planId);
    }
};
</script>
@endpush

@endsection