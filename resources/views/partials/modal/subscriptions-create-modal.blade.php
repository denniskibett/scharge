{{-- resources/views/partials/modal/subscriptions-create-modal.blade.php --}}
<!-- Subscriptions Create/Edit Slide-over Modal -->
<div x-data="subscriptionsCreateModal()" x-init="init()" x-show="showModal" x-cloak class="fixed inset-0 z-99999 overflow-hidden" style="display: none;">
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
                                <p class="text-sm text-purple-200" x-text="isEditing ? 'Update plan details and pricing' : 'Add a new region-based subscription plan'"></p>
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
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Region <span class="text-red-500">*</span></label>
                                    <select x-model="form.region_id" @change="onRegionChange()" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" required>
                                        <option value="">Select Region</option>
                                        <template x-for="region in regions" :key="region.id">
                                            <option :value="region.id" x-text="region.display_name || region.name"></option>
                                        </template>
                                    </select>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="selectedCountyName">County: <span class="font-medium" x-text="selectedCountyName"></span></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Constituency <span class="text-red-500">*</span></label>
                                    <select x-model="form.subcounty" @change="onConstituencyChange()" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" required>
                                        <option value="">Select Constituency</option>
                                        <template x-for="subcounty in filteredSubcounties" :key="subcounty.id">
                                            <option :value="subcounty.subcounty" x-text="subcounty.display_name"></option>
                                        </template>
                                    </select>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="filteredSubcounties.length === 0 && form.region_id">No subcounties found for this county</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Plan Name <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="form.name" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="e.g., Starter"
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Slug <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="form.slug" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="e.g., nairobi-cbd-starter"
                                        required>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Unique identifier</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                                <textarea x-model="form.description" rows="2" 
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                    placeholder="Describe what this plan includes..."></textarea>
                            </div>
                        </div>

                        <!-- Wards Selection -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                Wards Covered
                                <span class="ml-2 text-xs bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 px-2 py-0.5 rounded-full" x-text="selectedWards.length + ' selected'"></span>
                            </h4>
                            
                            <div x-show="!form.subcounty" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-8 w-8 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm">Select a constituency first to load available wards</p>
                            </div>
                            
                            <div x-show="form.subcounty && loadingWards" class="text-center py-4">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto"></div>
                                <p class="text-sm text-gray-500 mt-2">Loading wards...</p>
                            </div>
                            
                            <div x-show="form.subcounty && !loadingWards && availableWards.length === 0" class="text-center py-4 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-8 w-8 mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm">No wards found for this constituency</p>
                            </div>
                            
                            <div x-show="form.subcounty && !loadingWards && availableWards.length > 0">
                                <!-- Select All / Deselect All -->
                                <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-200 dark:border-gray-700">
                                    <span class="text-sm text-gray-600 dark:text-gray-400" x-text="availableWards.length + ' wards available'"></span>
                                    <div class="flex gap-2">
                                        <button type="button" @click="selectAllWards()" class="text-xs text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 font-medium">
                                            Select All
                                        </button>
                                        <span class="text-gray-300 dark:text-gray-600">|</span>
                                        <button type="button" @click="deselectAllWards()" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 font-medium">
                                            Deselect All
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Wards Table -->
                                <div class="overflow-x-auto max-h-48 overflow-y-auto">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 bg-gray-50 dark:bg-gray-800/80">
                                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400 font-medium w-10">
                                                    <input type="checkbox" @change="toggleAllWards()" :checked="selectedWards.length === availableWards.length && availableWards.length > 0"
                                                        class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                                                </th>
                                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400 font-medium">Ward Name</th>
                                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400 font-medium">Alias</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="ward in availableWards" :key="ward.id">
                                                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                                    <td class="py-2 px-3">
                                                        <input type="checkbox" :value="ward.id" x-model="selectedWards"
                                                            class="rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                                                    </td>
                                                    <td class="py-2 px-3 text-gray-700 dark:text-gray-300" x-text="ward.ward"></td>
                                                    <td class="py-2 px-3 text-gray-500 dark:text-gray-400" x-text="ward.alias || '-'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Selected Wards Summary -->
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <template x-for="wardId in selectedWards" :key="wardId">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">
                                            <span x-text="getWardName(wardId)"></span>
                                            <button type="button" @click="removeWard(wardId)" class="hover:text-purple-600 dark:hover:text-purple-300">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                    <span x-show="selectedWards.length === 0" class="text-xs text-gray-400">No wards selected</span>
                                </div>
                            </div>
                        </div>

                        <!-- Product Capabilities -->
                        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-xl p-5 border border-purple-200 dark:border-purple-800/30">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2 mb-4">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                                </svg>
                                Product Capabilities
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Units</label>
                                    <input type="number" x-model="form.product_capabilities.max_units" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Maximum units allowed</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Users</label>
                                    <input type="number" x-model="form.product_capabilities.max_users" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Maximum user accounts</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Tenants</label>
                                    <input type="number" x-model="form.product_capabilities.max_tenants" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Maximum tenant records</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Storage (GB)</label>
                                    <input type="number" x-model="form.product_capabilities.storage_gb" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Storage in GB</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Properties</label>
                                    <input type="number" x-model="form.product_capabilities.max_properties" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Maximum properties</p>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing & Additional Settings -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Pricing & Settings
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Price Per Unit (KES) <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400">KES</span>
                                        <input type="number" step="0.01" x-model="form.price_per_unit" 
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition pl-12"
                                            placeholder="0.00"
                                            required>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Per unit per month</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Trial Days</label>
                                    <input type="number" x-model="form.trial_days" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = No trial">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Free trial period in days</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Discount %</label>
                                    <input type="number" step="0.01" x-model="form.discount_percentage" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Yearly billing discount</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center space-x-3">
                                <div class="relative inline-flex items-center">
                                    <input type="checkbox" x-model="form.is_active" 
                                        class="w-5 h-5 rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-2 focus:ring-purple-500 transition">
                                    <label class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Active</label>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Visible to companies when subscribing</span>
                            </div>
                        </div>

                        <!-- Business Features -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                Business Features
                            </h4>
                            <div x-data="{ newFeature: '' }" class="space-y-3">
                                <div class="flex gap-2">
                                    <input type="text" x-model="newFeature" 
                                        class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="Enter a feature (e.g., 24/7 Support)"
                                        @keydown.enter.prevent="if(newFeature.trim()) { addBusinessFeature(newFeature); newFeature = ''; }">
                                    <button type="button" @click="if(newFeature.trim()) { addBusinessFeature(newFeature); newFeature = ''; }" 
                                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition shadow-sm hover:shadow-md">
                                        Add
                                    </button>
                                </div>
                                <div class="space-y-2 max-h-40 overflow-y-auto">
                                    <template x-for="(feature, index) in form.business_features" :key="index">
                                        <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg px-4 py-2.5 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition">
                                            <span class="text-sm text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span x-text="feature"></span>
                                            </span>
                                            <button type="button" @click="removeBusinessFeature(index)" class="text-red-400 hover:text-red-600 transition p-1 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                    <p x-show="form.business_features.length === 0" class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">
                                        No features added yet. Add features to highlight plan benefits.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Price Preview -->
                        <div x-show="form.price_per_unit > 0" class="bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800/30">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📊 Price Preview:</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div class="bg-white dark:bg-gray-800/50 rounded p-2 text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Per Unit</p>
                                    <p class="text-lg font-bold text-purple-600 dark:text-purple-400">
                                        KES <span x-text="form.price_per_unit?.toLocaleString() || 0"></span>
                                    </p>
                                </div>
                                <div class="bg-white dark:bg-gray-800/50 rounded p-2 text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Monthly (min 1 unit)</p>
                                    <p class="text-lg font-bold text-purple-600 dark:text-purple-400">
                                        KES <span x-text="(form.price_per_unit || 0).toLocaleString()"></span>
                                    </p>
                                </div>
                                <div class="bg-white dark:bg-gray-800/50 rounded p-2 text-center">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Yearly</p>
                                    <p class="text-lg font-bold text-purple-600 dark:text-purple-400">
                                        KES <span x-text="((form.price_per_unit || 0) * 12 * (1 - (form.discount_percentage || 0) / 100)).toLocaleString()"></span>
                                    </p>
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
        regions: [],
        allSubcounties: [],
        filteredSubcounties: [],
        availableWards: [],
        selectedWards: [],
        loadingWards: false,
        selectedCountyName: '',
        
        form: {
            region_id: '',
            subcounty: '',
            name: '',
            slug: '',
            description: '',
            price_per_unit: 0,
            trial_days: 0,
            discount_percentage: 0,
            is_active: true,
            product_capabilities: {
                max_units: 0,
                max_users: 0,
                max_tenants: 0,
                storage_gb: 0,
                max_properties: 0
            },
            business_features: []
        },
        
        init() {
            window.subscriptionsCreateModal = this;
            this.loadRegions();
            this.loadSubcounties();
        },
        
        async loadRegions() {
            try {
                const response = await fetch('/admin/subscriptions/api/regions', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (response.ok) {
                    const data = await response.json();
                    this.regions = data.regions || [];
                }
            } catch (error) {
                console.error('Error loading regions:', error);
            }
        },
        
        async loadSubcounties() {
            try {
                const response = await fetch('/admin/subscriptions/api/subcounties', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (response.ok) {
                    const data = await response.json();
                    this.allSubcounties = data.subcounties || [];
                    this.filteredSubcounties = this.allSubcounties;
                }
            } catch (error) {
                console.error('Error loading subcounties:', error);
            }
        },
        
        onRegionChange() {
            const selectedRegion = this.regions.find(r => r.id === parseInt(this.form.region_id));
            
            if (selectedRegion && selectedRegion.county_id) {
                this.filteredSubcounties = this.allSubcounties.filter(
                    s => s.county_id === selectedRegion.county_id
                );
                this.selectedCountyName = selectedRegion.county_name || '';
                
                // Reset constituency if not in filtered list
                const stillValid = this.filteredSubcounties.some(
                    s => s.subcounty === this.form.subcounty
                );
                if (!stillValid) {
                    this.form.subcounty = '';
                    this.availableWards = [];
                    this.selectedWards = [];
                }
            } else {
                this.filteredSubcounties = this.allSubcounties;
                this.selectedCountyName = '';
            }
        },
        
        async onConstituencyChange() {
            this.selectedWards = [];
            this.availableWards = [];
            
            if (!this.form.subcounty) {
                return;
            }
            
            this.loadingWards = true;
            
            try {
                const response = await fetch(`/admin/subscriptions/api/subcounties/${encodeURIComponent(this.form.subcounty)}/wards`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.availableWards = data.wards || [];
                }
            } catch (error) {
                console.error('Error loading wards:', error);
                this.availableWards = [];
            } finally {
                this.loadingWards = false;
            }
        },
        
        selectAllWards() {
            this.selectedWards = this.availableWards.map(w => w.id);
        },
        
        deselectAllWards() {
            this.selectedWards = [];
        },
        
        toggleAllWards() {
            if (this.selectedWards.length === this.availableWards.length) {
                this.deselectAllWards();
            } else {
                this.selectAllWards();
            }
        },
        
        getWardName(wardId) {
            const ward = this.availableWards.find(w => w.id === wardId);
            return ward ? ward.ward : 'Unknown';
        },
        
        removeWard(wardId) {
            this.selectedWards = this.selectedWards.filter(id => id !== wardId);
        },
        
        addBusinessFeature(feature) {
            if (feature.trim()) {
                this.form.business_features.push(feature.trim());
            }
        },
        
        removeBusinessFeature(index) {
            this.form.business_features.splice(index, 1);
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
            this.form = {
                region_id: '',
                subcounty: '',
                name: '',
                slug: '',
                description: '',
                price_per_unit: 0,
                trial_days: 0,
                discount_percentage: 0,
                is_active: true,
                product_capabilities: {
                    max_units: 0,
                    max_users: 0,
                    max_tenants: 0,
                    storage_gb: 0,
                    max_properties: 0
                },
                business_features: []
            };
            this.selectedWards = [];
            this.availableWards = [];
            this.filteredSubcounties = this.allSubcounties;
            this.selectedCountyName = '';
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
                    
                    // Set all form fields
                    this.form.region_id = plan.region_id || '';
                    this.form.subcounty = plan.subcounty || '';
                    this.form.name = plan.name || '';
                    this.form.slug = plan.slug || '';
                    this.form.description = plan.description || '';
                    this.form.price_per_unit = parseFloat(plan.price_per_unit) || 0;
                    this.form.trial_days = parseInt(plan.trial_days) || 0;
                    this.form.discount_percentage = parseFloat(plan.discount_percentage) || 0;
                    this.form.is_active = plan.is_active !== undefined ? plan.is_active : true;
                    this.form.product_capabilities = plan.product_capabilities || {
                        max_units: 0,
                        max_users: 0,
                        max_tenants: 0,
                        storage_gb: 0,
                        max_properties: 0
                    };
                    this.form.business_features = plan.business_features || [];
                    
                    // Set selected wards
                    this.selectedWards = plan.wards || [];
                    
                    // IMPORTANT: Filter subcounties based on region first
                    if (this.form.region_id) {
                        const selectedRegion = this.regions.find(r => r.id === parseInt(this.form.region_id));
                        if (selectedRegion && selectedRegion.county_id) {
                            this.filteredSubcounties = this.allSubcounties.filter(
                                s => s.county_id === selectedRegion.county_id
                            );
                            this.selectedCountyName = selectedRegion.county_name || '';
                        }
                    }
                    
                    // IMPORTANT: Load wards if constituency is set
                    if (this.form.subcounty) {
                        this.loadingWards = true;
                        try {
                            const wardsResponse = await fetch(`/admin/subscriptions/api/subcounties/${encodeURIComponent(this.form.subcounty)}/wards`, {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });
                            
                            if (wardsResponse.ok) {
                                const wardsData = await wardsResponse.json();
                                this.availableWards = wardsData.wards || [];
                                
                                // Re-select previously selected wards
                                if (plan.wards && plan.wards.length > 0) {
                                    this.selectedWards = plan.wards;
                                }
                            }
                        } catch (error) {
                            console.error('Error loading wards in edit mode:', error);
                            this.availableWards = [];
                        } finally {
                            this.loadingWards = false;
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading plan:', error);
                alert('Error loading plan details');
            }
        },
        
        async savePlan() {
            if (!this.form.region_id || !this.form.name || !this.form.slug) {
                alert('Please fill in all required fields (Region, Name, Slug)');
                return;
            }
            
            if (this.form.price_per_unit <= 0) {
                alert('Price per unit must be greater than 0');
                return;
            }
            
            this.saving = true;
            
            try {
                const payload = {
                    region_id: parseInt(this.form.region_id),
                    subcounty: this.form.subcounty || null,
                    wards: this.selectedWards,
                    name: this.form.name,
                    slug: this.form.slug,
                    description: this.form.description || null,
                    price_per_unit: parseFloat(this.form.price_per_unit),
                    trial_days: parseInt(this.form.trial_days) || 0,
                    discount_percentage: parseFloat(this.form.discount_percentage) || 0,
                    is_active: this.form.is_active,
                    product_capabilities: this.form.product_capabilities,
                    business_features: this.form.business_features
                };
                
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