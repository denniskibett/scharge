{{-- resources/views/partials/modal/subscriptions-features-create.blade.php --}}
<!-- Features Create/Edit Modal -->
<div x-data="subscriptionsFeaturesModal()" x-init="init()">
    <!-- Backdrop -->
    <div x-show="showModal" x-cloak @click="closeModal()"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] transition-opacity z-99999"></div>

    <!-- Slide-over panel (slides in from the right) -->
    <div x-show="showModal" x-cloak
        x-transition:enter="transition transform ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition transform ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto z-999999"
        style="width: 32rem; max-width: calc(100% - 2rem);">
        <div class="p-6 lg:p-10">
            <button type="button" @click="closeModal()"
                class="group absolute right-3 top-3 z-99999 flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-500 transition-colors hover:bg-gray-300 hover:text-gray-500 dark:bg-gray-800 dark:hover:bg-gray-700 sm:right-6 sm:top-6 sm:h-11 sm:w-11">
                <svg class="transition-colors fill-current group-hover:text-gray-600 dark:group-hover:text-gray-200" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5413C5.65237 16.9318 5.65237 17.565 6.04289 17.9555C6.43342 18.346 7.06658 18.346 7.45711 17.9555L11.9987 13.4139L16.5408 17.956C16.9313 18.3466 17.5645 18.3466 17.955 17.956C18.3455 17.5655 18.3455 16.9323 17.955 16.5418L13.4129 11.9997L17.955 7.4576C18.3455 7.06707 18.3455 6.43391 17.955 6.04338C17.5645 5.65286 16.9313 5.65286 16.5408 6.04338L11.9987 10.5855L7.45711 6.0439C7.06658 5.65338 6.43342 5.65338 6.04289 6.0439C5.65237 6.43442 5.65237 7.06759 6.04289 7.45811L10.5845 11.9997L6.04289 16.5413Z" />
                </svg>
            </button>

            <h4 class="mb-6 text-lg font-medium text-gray-800 dark:text-white/90">Manage Features</h4>
            <div>
                    <div x-data="{ newFeature: '' }" class="space-y-4">
                        <div class="flex gap-2">
                            <input type="text" x-model="newFeature" 
                                class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-2 focus:ring-purple-500"
                                placeholder="Enter feature name..."
                                @keydown.enter.prevent="addFeature()">
                            <button @click="addFeature()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                Add
                            </button>
                        </div>
                        
                        <div class="space-y-2 max-h-80 overflow-y-auto">
                            <template x-for="(feature, index) in features" :key="index">
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/50 rounded-lg px-4 py-2.5 border border-gray-200 dark:border-gray-700">
                                    <span x-text="feature"></span>
                                    <button @click="removeFeature(index)" class="text-red-400 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <p x-show="features.length === 0" class="text-center text-gray-500 py-8">No features added yet.</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end w-full gap-3 mt-6">
                    <button @click="closeModal()" class="flex w-full justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-theme-xs transition-colors hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200 sm:w-auto">Cancel</button>
                    <button @click="saveFeatures()" :disabled="saving" 
                        class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-medium text-white rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed sm:w-auto">
                        <span x-show="!saving">Save Features</span>
                        <span x-show="saving">Saving...</span>
                    </button>
                </div>
            </div>
        </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('subscriptionsFeaturesModal', () => ({
        showModal: false,
        planId: null,
        features: [],
        saving: false,
        
        init() {
            window.subscriptionsFeaturesModal = this;
        },
        
        openModal(planId) {
            this.planId = planId;
            this.loadFeatures();
            this.showModal = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeModal() {
            this.showModal = false;
            document.body.style.overflow = '';
        },
        
        async loadFeatures() {
            try {
                const response = await fetch(`/admin/subscriptions/api/plans/${this.planId}`);
                const data = await response.json();
                this.features = data.business_features || [];
            } catch (error) {
                console.error('Error loading features:', error);
            }
        },
        
        addFeature() {
            const input = document.querySelector('[x-model="newFeature"]');
            if (input && input.value.trim()) {
                this.features.push(input.value.trim());
                input.value = '';
            }
        },
        
        removeFeature(index) {
            this.features.splice(index, 1);
        },
        
        async saveFeatures() {
            this.saving = true;
            try {
                const response = await fetch(`/admin/subscriptions/plans/${this.planId}/features`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ business_features: this.features })
                });
                
                const result = await response.json();
                if (result.success) {
                    this.closeModal();
                    (window.schargeRefresh || (() => location.reload()))();
                } else {
                    alert(result.message || 'Error saving features');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving features');
            } finally {
                this.saving = false;
            }
        }
    }));
});
</script>