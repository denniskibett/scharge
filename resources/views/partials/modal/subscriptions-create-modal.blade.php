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
                                    <select x-model="form.region_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition" required>
                                        <option value="">Select Region</option>
                                        <template x-for="region in regions" :key="region.id">
                                            <option :value="region.id" x-text="region.display_name || region.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Subcounty</label>
                                    <select x-model="form.subcounty_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition">
                                        <option value="">Optional</option>
                                        <template x-for="subcounty in subcounties" :key="subcounty.id">
                                            <option :value="subcounty.id" x-text="subcounty.name"></option>
                                        </template>
                                    </select>
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
                                        placeholder="e.g., kileleshwa-starter"
                                        required>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Unique identifier, region-plan combination</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                                <textarea x-model="form.description" rows="2" 
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                    placeholder="Describe what this plan includes..."></textarea>
                            </div>
                        </div>

                        <!-- Pricing & Limits -->
                        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-xl p-5 border border-purple-200 dark:border-purple-800/30">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2 mb-4">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Pricing & Limits
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
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Min Units</label>
                                    <input type="number" x-model="form.min_units" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="1">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Minimum units required</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Max Units</label>
                                    <input type="number" x-model="form.max_units" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0 = Unlimited">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">0 = Unlimited</p>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Settings -->
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                </svg>
                                Additional Settings
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Display Order</label>
                                    <input type="number" x-model="form.display_order" 
                                        class="w-32 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500 focus:border-transparent transition"
                                        placeholder="0">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Lower numbers appear first</p>
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
                                    <template x-for="(feature, index) in form.features" :key="index">
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
                                    <p x-show="form.features.length === 0" class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">
                                        No features added yet. Add features to highlight plan benefits.
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
        subcounties: [],
        
        form: {
            region_id: '',
            subcounty_id: '',
            name: '',
            slug: '',
            description: '',
            price_per_unit: 0,
            min_units: 1,
            max_units: 0,
            trial_days: 0,
            discount_percentage: 0,
            display_order: 0,
            is_active: true,
            features: []
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
                    this.subcounties = data.subcounties || [];
                }
            } catch (error) {
                console.error('Error loading subcounties:', error);
            }
        },
        
        addFeature(feature) {
            if (feature.trim()) {
                this.form.features.push(feature.trim());
            }
        },
        
        removeFeature(index) {
            this.form.features.splice(index, 1);
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
                subcounty_id: '',
                name: '',
                slug: '',
                description: '',
                price_per_unit: 0,
                min_units: 1,
                max_units: 0,
                trial_days: 0,
                discount_percentage: 0,
                display_order: 0,
                is_active: true,
                features: []
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
                    this.form = {
                        region_id: plan.region_id || '',
                        subcounty_id: plan.subcounty_id || '',
                        name: plan.name || '',
                        slug: plan.slug || '',
                        description: plan.description || '',
                        price_per_unit: parseFloat(plan.price_per_unit) || 0,
                        min_units: parseInt(plan.min_units) || 1,
                        max_units: parseInt(plan.max_units) || 0,
                        trial_days: parseInt(plan.trial_days) || 0,
                        discount_percentage: parseFloat(plan.discount_percentage) || 0,
                        display_order: parseInt(plan.display_order) || 0,
                        is_active: plan.is_active !== undefined ? plan.is_active : true,
                        features: plan.features || []
                    };
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
                    subcounty_id: this.form.subcounty_id ? parseInt(this.form.subcounty_id) : null,
                    name: this.form.name,
                    slug: this.form.slug,
                    description: this.form.description || null,
                    price_per_unit: parseFloat(this.form.price_per_unit),
                    min_units: parseInt(this.form.min_units) || 1,
                    max_units: parseInt(this.form.max_units) || 0,
                    trial_days: parseInt(this.form.trial_days) || 0,
                    discount_percentage: parseFloat(this.form.discount_percentage) || 0,
                    display_order: parseInt(this.form.display_order) || 0,
                    is_active: this.form.is_active,
                    features: this.form.features
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