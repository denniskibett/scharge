{{-- resources/views/subscriptions/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Subscription Plan Details - ' . ($planData['name'] ?? 'Plan'))

@section('content')
<div class="container mx-auto px-4 py-8">
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
            @if($firstCompany)
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

    <!-- Plan Overview -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $planData['name'] }}</h1>
                        <span class="inline-flex px-3 py-1 text-sm rounded-full {{ $planData['is_active'] ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' }}">
                            {{ $planData['is_active'] ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Slug: {{ $planData['slug'] }}</p>
                    @if($planData['description'])
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">{{ $planData['description'] }}</p>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button onclick="window.subscriptionsCreateModal?.openModal({{ $planData['id'] }})" class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition">
                        Edit Plan
                    </button>
                </div>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Region</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $planData['region_name'] ?? 'N/A' }}</p>
                <p class="text-xs text-gray-400">{{ $planData['county_name'] ?? '' }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Subcounty</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $planData['subcounty_name'] ?? 'N/A' }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Price Per Unit</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white">KES {{ number_format($planData['price_per_unit'] ?? 0, 0) }}</p>
                <p class="text-xs text-gray-400">per unit / month</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Discount</p>
                <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $planData['discount_percentage'] ?? 0 }}%</p>
                <p class="text-xs text-gray-400">yearly billing</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Trial Days</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $planData['trial_days'] ?? 0 }} days</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Unit Range</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $planData['unit_range'] ?? 'Unlimited' }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Display Order</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $planData['display_order'] }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-lg p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Subscribers</p>
                <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $planData['subscriber_count'] ?? 0 }}</p>
                <p class="text-xs text-gray-400">active companies</p>
            </div>
        </div>

        <!-- Price Preview -->
        <div class="px-6 pb-6">
            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800/30">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📊 Price Preview (based on min units):</p>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Monthly Price</p>
                        <p class="text-lg font-bold text-purple-600 dark:text-purple-400">KES {{ number_format($planData['monthly_price'] ?? 0, 0) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Yearly Price</p>
                        <p class="text-lg font-bold text-purple-600 dark:text-purple-400">KES {{ number_format($planData['yearly_price'] ?? 0, 0) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Savings</p>
                        <p class="text-lg font-bold text-green-600 dark:text-green-400">KES {{ number_format((($planData['monthly_price'] ?? 0) * 12) - ($planData['yearly_price'] ?? 0), 0) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Features -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Features & Benefits</h2>
                </div>
                <div class="p-4">
                    @if(!empty($planData['features_list']) && count($planData['features_list']) > 0)
                        <ul class="space-y-2">
                            @foreach($planData['features_list'] as $feature)
                                <li class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ is_array($feature) ? ($feature['name'] ?? $feature[0] ?? '') : $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No features listed for this plan.</p>
                    @endif
                </div>
            </div>

            <!-- Invoices -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 mt-6">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Recent Invoices</h2>
                </div>
                <div class="p-4">
                    @if($invoices && $invoices->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Invoice #</th>
                                        <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Amount</th>
                                        <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Status</th>
                                        <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($invoices->take(10) as $invoice)
                                        <tr class="border-b border-gray-100 dark:border-gray-800">
                                            <td class="py-2 px-3 text-gray-800 dark:text-white">{{ $invoice->invoice_number }}</td>
                                            <td class="py-2 px-3 text-gray-800 dark:text-white">KES {{ number_format($invoice->amount, 0) }}</td>
                                            <td class="py-2 px-3">
                                                <span class="inline-flex px-2 py-0.5 text-xs rounded-full 
                                                    @if($invoice->status === 'paid') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400
                                                    @elseif($invoice->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400
                                                    @elseif($invoice->status === 'failed') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400
                                                    @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400 @endif">
                                                    {{ ucfirst($invoice->status) }}
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $invoice->created_at?->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($invoices->count() > 10)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">Showing 10 of {{ $invoices->count() }} invoices</p>
                        @endif
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No invoices found for this plan.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Regional Account Managers -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Account Managers</h2>
                </div>
                <div class="p-4">
                    @if($accountManagers && $accountManagers->count() > 0)
                        <div class="space-y-3">
                            @foreach($accountManagers as $manager)
                                <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <div class="w-10 h-10 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
                                        <span class="text-purple-600 dark:text-purple-400 font-semibold text-sm">
                                            {{ strtoupper(substr($manager->user?->name ?? 'U', 0, 2)) }}
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $manager->user?->name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $manager->title ?? 'Account Manager' }}</p>
                                        <p class="text-xs text-gray-400">{{ $manager->user?->email ?? '' }}</p>
                                        @if($manager->is_primary)
                                            <span class="inline-flex px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 mt-1">Primary</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No account managers assigned to this region.</p>
                    @endif
                </div>
            </div>

            <!-- Companies Using This Plan -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Companies</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $companies->count() }} companies using this plan</p>
                </div>
                <div class="p-4 max-h-64 overflow-y-auto">
                    @if($companies && $companies->count() > 0)
                        <div class="space-y-2">
                            @foreach($companies as $company)
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $company->name ?? 'N/A' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $company->email ?? '' }}</p>
                                    </div>
                                    <a href="{{ route('admin.companies.show', $company->id) }}" class="text-blue-500 hover:text-blue-700 text-sm">View</a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">No companies currently using this plan.</p>
                            <p class="text-xs text-gray-400">Companies will appear here once they subscribe.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Create/Edit Modal -->
@include('partials.modal.subscriptions-create-modal')

<script>
document.addEventListener('alpine:init', () => {
    // Make sure the modal is available
    if (!window.subscriptionsCreateModal) {
        console.warn('Subscriptions create modal not initialized');
    }
});
</script>

@push('scripts')
<script>
// For the edit button to work
window.editPlan = function(planId) {
    if (window.subscriptionsCreateModal) {
        window.subscriptionsCreateModal.openModal(planId);
    }
};
</script>
@endpush

@endsection