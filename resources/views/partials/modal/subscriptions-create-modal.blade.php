{{-- resources/views/partials/modal/subscriptions-create-modal.blade.php --}}
<!-- Subscriptions Create/Edit Slide-over Modal -->
<div x-data="subscriptionsCreateModal()" x-init="init()" x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
    <!-- Frosty Background Overlay -->
    <div class="absolute inset-0 bg-gray-500/50 backdrop-blur-sm transition-opacity" @click="closeModal()"></div>

    <!-- Slide-over Panel -->
    <div class="absolute inset-y-0 right-0 max-w-full flex">
        <div class="relative w-screen max-w-2xl">
            <div class="h-full flex flex-col bg-white dark:bg-gray-800 shadow-xl overflow-y-auto">
                
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-purple-600 to-indigo-700 px-6 py-4 sticky top-0 z-10">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-white/20 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-white" x-text="isEditing ? 'Edit Subscription Plan' : 'Create Subscription Plan'"></h3>
                                <p class="text-sm text-purple-100" x-text="isEditing ? 'Update plan details and pricing' : 'Add a new subscription plan'"></p>
                            </div>
                        </div>
                        <button @click="closeModal()" class="text-white hover:text-gray-200 transition-colors">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <form @submit.prevent="savePlan" class="flex-1 flex flex-col">
                    <div class="flex-1 px-6 py-6 space-y-6">
                        <!-- Basic Information -->
                        <div>
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Basic Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan Name *</label>
                                    <input type="text" x-model="form.name" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500" 
                                        required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Slug *</label>
                                    <input type="text" x-model="form.slug" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500" 
                                        required>
                                    <p class="text-xs text-gray-500 mt-1">Unique identifier (e.g., basic, professional, enterprise)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                            <textarea x-model="form.description" rows="3" 
                                class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500"
                                placeholder="Describe what this plan includes..."></textarea>
                        </div>

                        <!-- Pricing Method Toggle -->
                        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Pricing Method</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Choose how to calculate subscription price</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="pricingMethod = 'fixed'" 
                                        :class="pricingMethod === 'fixed' ? 'bg-purple-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                        class="px-4 py-2 text-sm rounded-lg transition-all">
                                        Fixed Price
                                    </button>
                                    <button type="button" @click="pricingMethod = 'per_unit'" 
                                        :class="pricingMethod === 'per_unit' ? 'bg-purple-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                        class="px-4 py-2 text-sm rounded-lg transition-all">
                                        Per Unit Pricing
                                    </button>
                                </div>
                            </div>

                            <!-- Fixed Pricing Fields -->
                            <div x-show="pricingMethod === 'fixed'" x-cloak>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monthly Price (KES) *</label>
                                        <input type="number" step="0.01" x-model="form.price_monthly" 
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500" 
                                            required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Yearly Price (KES) *</label>
                                        <input type="number" step="0.01" x-model="form.price_yearly" 
                                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500" 
                                            required>
                                        <p class="text-xs text-gray-500 mt-1">Save 16.7% vs monthly</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Per Unit Pricing Fields -->
                            <div x-show="pricingMethod === 'per_unit'" x-cloak>
                                <div class="space-y-4">
                                    <div class="bg-white dark:bg-gray-700/50 rounded-lg p-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Price Per Unit (KES/month)</label>
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-500 dark:text-gray-400">KES</span>
                                            <input type="number" step="0.01" x-model="form.price_per_unit" 
                                                class="flex-1 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500"
                                                placeholder="Enter rate per unit">
                                            <span class="text-gray-500 dark:text-gray-400">per unit / month</span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">
                                            Price will be calculated as: <strong>Price Per Unit × Number of Active Units</strong>
                                        </p>
                                    </div>

                                    <!-- Preview Calculation -->
                                    <div x-show="form.price_per_unit > 0" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Preview Calculation:</p>
                                        <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                            <p>For a company with <strong x-text="sampleUnitCount"></strong> active units:</p>
                                            <p class="text-lg font-semibold text-purple-600 dark:text-purple-400">
                                                = KES <span x-text="(form.price_per_unit * sampleUnitCount).toLocaleString()"></span> / month
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Hide fixed price fields when using per-unit pricing -->
                                    <input type="hidden" x-model="form.price_monthly" :value="form.price_per_unit ? form.price_per_unit * sampleUnitCount : 0">
                                    <input type="hidden" x-model="form.price_yearly" :value="form.price_per_unit ? (form.price_per_unit * sampleUnitCount * 12 * 0.833) : 0">
                                </div>
                            </div>
                        </div>

                        <!-- Plan Limits (from features_json) -->
                        <div>
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Plan Limits</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Properties</label>
                                    <input type="number" x-model="form.limits.max_properties" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="Unlimited = 0">
                                    <p class="text-xs text-gray-500 mt-1">Number of properties/estates allowed</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Units</label>
                                    <input type="number" x-model="form.limits.max_units" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="Unlimited = 0">
                                    <p class="text-xs text-gray-500 mt-1">Maximum rental units allowed</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Users</label>
                                    <input type="number" x-model="form.limits.max_users" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="Unlimited = 0">
                                    <p class="text-xs text-gray-500 mt-1">Maximum staff accounts</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Tenants</label>
                                    <input type="number" x-model="form.limits.max_tenants" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="Unlimited = 0">
                                    <p class="text-xs text-gray-500 mt-1">Maximum active tenants</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Storage (GB)</label>
                                    <input type="number" x-model="form.limits.storage_gb" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="Storage limit in GB">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Trial Days</label>
                                    <input type="number" x-model="form.trial_days" 
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500">
                                </div>
                            </div>
                        </div>

                        <!-- Features List -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Features & Benefits</label>
                            <div class="space-y-2">
                                <div x-data="{ newFeature: '' }" class="space-y-2">
                                    <div class="flex gap-2">
                                        <input type="text" x-model="newFeature" 
                                            class="flex-1 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500"
                                            placeholder="Enter a feature (e.g., 24/7 Support)">
                                        <button type="button" @click="if(newFeature.trim()) { addFeature(newFeature); newFeature = ''; }" 
                                            class="px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                            Add
                                        </button>
                                    </div>
                                    <div class="space-y-1">
                                        <template x-for="(feature, index) in form.features_json" :key="index">
                                            <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                                                <span class="text-sm text-gray-700 dark:text-gray-300" x-text="feature"></span>
                                                <button type="button" @click="removeFeature(index)" class="text-red-500 hover:text-red-700">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Display Settings -->
                        <div class="flex flex-col space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Display Order</label>
                                <input type="number" x-model="form.display_order" 
                                    class="w-32 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-purple-500 focus:border-purple-500">
                                <p class="text-xs text-gray-500 mt-1">Lower numbers appear first in listings</p>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" x-model="form.is_active" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active (visible to companies when subscribing)</label>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-gray-50 dark:bg-gray-900/50 px-6 py-4 flex justify-end gap-3 sticky bottom-0">
                        <button type="button" @click="closeModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="saving" 
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!saving" x-text="isEditing ? 'Save Changes' : 'Create Plan'"></span>
                            <span x-show="saving" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
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
        pricingMethod: 'fixed', // 'fixed' or 'per_unit'
        sampleUnitCount: 100, // Example for preview
        
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
                const response = await fetch(`/admin/subscriptions/plans/${planId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const plan = await response.json();
                    const features = plan.features_json || {};
                    
                    // Check if this plan uses per-unit pricing
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
                        features_json: plan.features_list || [],
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
            
            // Build features object
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
                // Store base per-unit price, actual monthly price will be calculated on the fly
                this.form.price_monthly = 0; // Will be calculated dynamically
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