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

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Features Tab -->
            <div x-show="activeTab === 'features'" x-cloak>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Features & Benefits</h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($planData['business_features'] ?? []) }} features included</span>
                    </div>
                    @if(!empty($planData['business_features']) && count($planData['business_features']) > 0)
                        <ul class="space-y-3">
                            @foreach($planData['business_features'] as $feature)
                                <li class="flex items-start gap-3 text-gray-700 dark:text-gray-300 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ is_array($feature) ? ($feature['name'] ?? $feature[0] ?? '') : $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">No features listed for this plan.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Invoices Tab -->
            <div x-show="activeTab === 'invoices'" x-cloak>
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Invoices</h3>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-500 dark:text-gray-400">Total:</span>
                            <span class="font-semibold text-gray-800 dark:text-white">KES {{ number_format($invoices->sum('amount'), 0) }}</span>
                        </div>
                    </div>
                    @if($invoices && $invoices->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-2.5 px-3 text-gray-600 dark:text-gray-400 font-medium">Invoice #</th>
                                        <th class="text-left py-2.5 px-3 text-gray-600 dark:text-gray-400 font-medium">Amount</th>
                                        <th class="text-left py-2.5 px-3 text-gray-600 dark:text-gray-400 font-medium">Status</th>
                                        <th class="text-left py-2.5 px-3 text-gray-600 dark:text-gray-400 font-medium">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoices as $invoice)
                                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                            <td class="py-2.5 px-3 text-gray-800 dark:text-white font-medium">#{{ $invoice->invoice_number }}</td>
                                            <td class="py-2.5 px-3 text-gray-800 dark:text-white">KES {{ number_format($invoice->amount, 0) }}</td>
                                            <td class="py-2.5 px-3">
                                                <span class="inline-flex px-2.5 py-1 text-xs rounded-full font-medium 
                                                    @if($invoice->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                                    @elseif($invoice->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400
                                                    @elseif($invoice->status === 'failed') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                                    @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400 @endif">
                                                    {{ ucfirst($invoice->status) }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">{{ $invoice->created_at?->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">No invoices found for this plan.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Companies Tab -->
            <div x-show="activeTab === 'companies'" x-cloak>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Companies Using This Plan</h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $companies->count() }} companies actively subscribed</span>
                    </div>
                    @if($companies && $companies->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($companies as $company)
                                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-100 dark:border-gray-700 hover:border-purple-300 dark:hover:border-purple-700 transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                            <span class="text-purple-600 dark:text-purple-400 font-semibold text-sm">
                                                {{ strtoupper(substr($company->name ?? 'C', 0, 2)) }}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $company->name ?? 'N/A' }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $company->email ?? '' }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ route('admin.companies.show', $company->id) }}" class="text-purple-500 hover:text-purple-700 text-sm font-medium">
                                        View →
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">No companies currently using this plan.</p>
                            <p class="text-xs text-gray-400 mt-1">Companies will appear here once they subscribe.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Account Managers Tab -->
            <div x-show="activeTab === 'managers'" x-cloak>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Account Managers</h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $accountManagers->count() }} managers assigned to this region</span>
                    </div>
                    @if($accountManagers && $accountManagers->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($accountManagers as $manager)
                                <div class="flex items-start gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-100 dark:border-gray-700">
                                    <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
                                        <span class="text-purple-600 dark:text-purple-400 font-semibold text-base">
                                            {{ strtoupper(substr($manager->user?->name ?? 'U', 0, 2)) }}
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $manager->user?->name ?? 'Unknown' }}</p>
                                            @if($manager->is_primary)
                                                <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">Primary</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $manager->title ?? 'Account Manager' }}</p>
                                        <p class="text-xs text-gray-400">{{ $manager->user?->email ?? '' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">No account managers assigned to this region.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Create/Edit Modal -->
@include('partials.modal.subscriptions-create-modal')

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