{{-- resources/views/partials/modal/subscriptions-create-modal.blade.php --}}
<!-- Subscriptions Create/Edit Slide-over Modal -->
<div x-data="subscriptionsCreateModal()" x-init="init()" x-show="showModal" x-cloak class="fixed inset-0 z-999999 overflow-hidden" style="display: none;">
    <!-- Frosty Background Overlay -->
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" @click="closeModal()"></div>

    <!-- Slide-over Panel -->
    <div class="absolute inset-y-0 right-0 max-w-full flex">
        <div class="relative w-screen max-w-2xl">
            <div class="h-full flex flex-col bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto">
                
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-purple-600 to-indigo-700 px-6 py-5 sticky top-0 z-50 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white" x-text="isEditing ? 'Edit Subscription Plan' : 'Create Subscription Plan'"></h3>
                                <p class="text-sm text-purple-200" x-text="isEditing ? 'Update plan details and pricing' : 'Add a new subscription plan to the system'"></p>
                            </div>
                        </div>
                        <button @click="closeModal()" class="text-white/80 hover:text-white transition-colors p-2 hover:bg-white/10 rounded-lg">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <form @submit.prevent="savePlan" class="flex-1 flex flex-col overflow-hidden">
                    <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6">
                        <!-- Basic Information -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Basic Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Plan Name <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="form.name" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="e.g., Professional"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Slug <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="form.slug" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="e.g., professional"
                                        required>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Unique identifier, lowercase with hyphens</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                                <textarea x-model="form.description" rows="2" 
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                    placeholder="Describe what this plan includes..."></textarea>
                            </div>
                        </div>

                        <!-- Pricing Method Toggle -->
                        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-xl p-5 border border-purple-200 dark:border-purple-800/30">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Pricing Method
                                    </h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Choose how to calculate subscription price</p>
                                </div>
                                <div class="flex gap-2 bg-white dark:bg-gray-800 rounded-lg p-1 shadow-sm">
                                    <button type="button" @click="pricingMethod = 'fixed'" 
                                        :class="pricingMethod === 'fixed' ? 'bg-purple-600 text-white shadow-md' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                        class="px-4 py-1.5 text-sm rounded-lg transition-all duration-200 font-medium">
                                        Fixed Price
                                    </button>
                                    <button type="button" @click="pricingMethod = 'per_unit'" 
                                        :class="pricingMethod === 'per_unit' ? 'bg-purple-600 text-white shadow-md' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                        class="px-4 py-1.5 text-sm rounded-lg transition-all duration-200 font-medium">
                                        Per Unit
                                    </button>
                                </div>
                            </div>

                            <!-- Fixed Pricing Fields -->
                            <div x-show="pricingMethod === 'fixed'" x-cloak class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Monthly Price (KES) <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">KES</span>
                                            <input type="number" step="0.01" x-model="form.price_monthly" 
                                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition pl-12"
                                                placeholder="0.00"
                                                required>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Yearly Price (KES) <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">KES</span>
                                            <input type="number" step="0.01" x-model="form.price_yearly" 
                                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition pl-12"
                                                placeholder="0.00"
                                                required>
                                        </div>
                                        <p class="text-xs text-green-600 dark:text-green-400 mt-1">✓ Save 16.7% with yearly billing</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Per Unit Pricing Fields -->
                            <div x-show="pricingMethod === 'per_unit'" x-cloak class="space-y-4">
                                <div class="bg-white dark:bg-gray-800/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Price Per Unit <span class="text-red-500">*</span></label>
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex-1">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">KES</span>
                                            <input type="number" step="0.01" x-model="form.price_per_unit" 
                                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition pl-12"
                                                placeholder="Enter rate per unit">
                                        </div>
                                        <span class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">per unit / month</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Price = <strong>Price Per Unit</strong> × <strong>Number of Active Units</strong>
                                    </p>
                                </div>

                                <!-- Preview Calculation -->
                                <div x-show="form.price_per_unit > 0" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800/30">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📊 Preview Calculation:</p>
                                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                        <p>For a company with <strong class="text-purple-600 dark:text-purple-400" x-text="sampleUnitCount"></strong> active units:</p>
                                        <div class="bg-white dark:bg-gray-800/50 rounded p-3 mt-2">
                                            <p class="text-gray-500 dark:text-gray-400 text-xs">Monthly Subscription Price</p>
                                            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                                KES <span x-text="(form.price_per_unit * sampleUnitCount).toLocaleString()"></span>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                <span x-text="form.price_per_unit"></span> × <span x-text="sampleUnitCount"></span> units
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Plan Limits -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                Plan Limits
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Properties</label>
                                    <input type="number" x-model="form.limits.max_properties" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Properties/estates allowed</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Units</label>
                                    <input type="number" x-model="form.limits.max_units" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Rental units allowed</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Users</label>
                                    <input type="number" x-model="form.limits.max_users" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Staff accounts allowed</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Tenants</label>
                                    <input type="number" x-model="form.limits.max_tenants" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Active tenants allowed</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Storage (GB)</label>
                                    <input type="number" x-model="form.limits.storage_gb" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="Storage limit in GB">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Trial Days</label>
                                    <input type="number" x-model="form.trial_days" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = No trial">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Free trial period in days</p>
                                </div>
                            </div>
                        </div>

                        <!-- Features List -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                Features & Benefits
                            </h4>
                            <div x-data="{ newFeature: '' }" class="space-y-3">
                                <div class="flex gap-2">
                                    <input type="text" x-model="newFeature" 
                                        class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="Enter a feature (e.g., 24/7 Support)"
                                        @keydown.enter.prevent="if(newFeature.trim()) { addFeature(newFeature); newFeature = ''; }">
                                    <button type="button" @click="if(newFeature.trim()) { addFeature(newFeature); newFeature = ''; }" 
                                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition shadow-sm hover:shadow-md">
                                        Add
                                    </button>
                                </div>
                                <div class="space-y-2 max-h-40 overflow-y-auto">
                                    <template x-for="(feature, index) in form.features_json" :key="index">
                                        <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg px-4 py-2.5 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition">
                                            <span class="text-sm text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span x-text="feature"></span>
                                            </span>
                                            <button type="button" @click="removeFeature(index)" class="text-red-400 hover:text-red-600 transition p-1 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                    <p x-show="form.features_json.length === 0" class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">
                                        No features added yet. Add features to highlight plan benefits.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Display Settings -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                </svg>
                                Display Settings
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Display Order</label>
                                    <input type="number" x-model="form.display_order" 
                                        class="w-32 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Lower numbers appear first</p>
                                </div>
                                <div class="flex items-center space-x-3 pt-2">
                                    <div class="relative inline-flex items-center">
                                        <input type="checkbox" x-model="form.is_active" 
                                            class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-2 focus:ring-purple-500 transition">
                                        <label class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Active</label>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Visible to companies when subscribing</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-gray-100 dark:bg-gray-800/80 px-6 py-4 flex justify-end gap-3 sticky bottom-0 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
                        <button type="button" @click="closeModal()" 
                            class="px-5 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition font-medium">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving" 
                            class="px-6 py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed font-medium flex items-center gap-2">
                            <span x-show="!saving" x-text="isEditing ? '💾 Save Changes' : '✨ Create Plan'"></span>
                            <span x-show="saving" class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('subscriptionsCreateModal', () => ({
        showModal: false,
        isEditing: false,
        editingId: null,
        saving: false,
        pricingMethod: 'fixed',
        sampleUnitCount: 100,
        
        form: {
            name: '',
            slug: '',
            description: '',
            price_monthly: 0,
            price_yearly: 0,
            price_per_unit: 0,
            trial_days: 0,
            display_order: 0,
            is_active: true,
            features_json: [],
            limits: {
                max_properties: 0,
                max_units: 0,
                max_users: 0,
                max_tenants: 0,
                storage_gb: 0
            }
        },
        
        init() {
            window.subscriptionsCreateModal = this;
        },
        
        addFeature(feature) {
            if (feature.trim()) {
                this.form.features_json.push(feature.trim());
            }
        },
        
        removeFeature(index) {
            this.form.features_json.splice(index, 1);
        },
        
        openModal(planId = null) {
            if (planId) {
                this.isEditing = true;
                this.editingId = planId;
                this.loadPlan(planId);
            } else {
                this.resetForm();
                this.isEditing = false;
                this.editingId = null;
            }
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.showModal = false;
            this.resetForm();
            document.body.style.overflow = '';
        },
        
        resetForm() {
            this.pricingMethod = 'fixed';
            this.form = {
                name: '',
                slug: '',
                description: '',
                price_monthly: 0,
                price_yearly: 0,
                price_per_unit: 0,
                trial_days: 0,
                display_order: 0,
                is_active: true,
                features_json: [],
                limits: {
                    max_properties: 0,
                    max_units: 0,
                    max_users: 0,
                    max_tenants: 0,
                    storage_gb: 0
                }
            };
        },
        
        async loadPlan(planId) {
            try {
                const response = await fetch(`/admin/subscriptions/api/plans/${planId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const plan = await response.json();
                    const features = plan.features || {};
                    
                    if (features.pricing_type === 'per_unit' || (plan.price_per_unit && plan.price_per_unit > 0)) {
                        this.pricingMethod = 'per_unit';
                        this.form.price_per_unit = plan.price_per_unit || features.price_per_unit || 0;
                    } else {
                        this.pricingMethod = 'fixed';
                        this.form.price_monthly = parseFloat(plan.price_monthly);
                        this.form.price_yearly = parseFloat(plan.price_yearly);
                    }
                    
                    this.form = {
                        name: plan.name,
                        slug: plan.slug,
                        description: plan.description || '',
                        price_monthly: parseFloat(plan.price_monthly),
                        price_yearly: parseFloat(plan.price_yearly),
                        price_per_unit: plan.price_per_unit || 0,
                        trial_days: plan.trial_days || 0,
                        display_order: plan.display_order || 0,
                        is_active: plan.is_active,
                        features_json: plan.features_json || [],
                        limits: {
                            max_properties: features.max_properties || 0,
                            max_units: features.max_units || 0,
                            max_users: features.max_users || 0,
                            max_tenants: features.max_tenants || 0,
                            storage_gb: features.storage_gb || 0
                        }
                    };
                }
            } catch (error) {
                console.error('Error loading plan:', error);
                alert('Error loading plan details');
            }
        },
        
        async savePlan() {
            if (!this.form.name || !this.form.slug) {
                alert('Please fill in all required fields');
                return;
            }
            
            const features = {
                max_properties: parseInt(this.form.limits.max_properties) || 0,
                max_units: parseInt(this.form.limits.max_units) || 0,
                max_users: parseInt(this.form.limits.max_users) || 0,
                max_tenants: parseInt(this.form.limits.max_tenants) || 0,
                storage_gb: parseInt(this.form.limits.storage_gb) || 0,
                features_list: this.form.features_json,
                pricing_type: this.pricingMethod
            };
            
            if (this.pricingMethod === 'per_unit') {
                features.price_per_unit = parseFloat(this.form.price_per_unit) || 0;
                this.form.price_monthly = 0;
                this.form.price_yearly = 0;
            }
            
            const payload = {
                name: this.form.name,
                slug: this.form.slug,
                description: this.form.description,
                price_monthly: this.form.price_monthly,
                price_yearly: this.form.price_yearly,
                price_per_unit: this.pricingMethod === 'per_unit' ? this.form.price_per_unit : null,
                trial_days: this.form.trial_days,
                display_order: this.form.display_order,
                is_active: this.form.is_active,
                features_json: features
            };
            
            this.saving = true;
            
            try {
                const url = this.isEditing 
                    ? `/admin/subscriptions/plans/${this.editingId}`
                    : '/admin/subscriptions/plans';
                const method = this.isEditing ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.closeModal();
                    if (window.subscriptionsTable) {
                        await window.subscriptionsTable.loadPlans();
                    }
                    alert(result.message || (this.isEditing ? 'Plan updated successfully!' : 'Plan created successfully!'));
                } else {
                    alert(result.message || 'Error saving plan');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving plan');
            } finally {
                this.saving = false;
            }
        }
    }));
});
</script>

<style>
[x-cloak] { display: none !important; }
</style>