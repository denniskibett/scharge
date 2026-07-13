<!-- resources/views/partials/modal/security-quick-entry-modal.blade.php -->
<div x-data="securityQuickEntryModal()" x-init="init()">
    <div 
        x-show="open" 
        x-cloak
        class="fixed inset-0 z-50 overflow-hidden"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
    >
        <div class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="closeModal()"></div>

        <div 
            class="fixed right-0 top-0 h-full w-full max-w-4xl bg-white dark:bg-gray-900 shadow-2xl"
            x-transition:enter="transform transition ease-in-out duration-300 sm:duration-500"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in-out duration-300 sm:duration-500"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Check-In</h3>
                    </div>
                </div>
                <button @click="closeModal()" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="h-[calc(100%-120px)] overflow-y-auto p-4">
                <!-- Search -->
                <div class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                        <div>
                            <select x-model="form.lookup_by" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="phone">Phone</option>
                                <option value="id_number">ID Number</option>
                                <option value="name">Name</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <input type="text" x-model="form.lookup_value" placeholder="Search visitor..." @keyup.enter="searchVisitor()" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        </div>
                        <div>
                            <button @click="searchVisitor()" :disabled="!form.lookup_value || searching" class="w-full rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                                <span x-show="!searching">Search</span>
                                <span x-show="searching" class="flex items-center justify-center gap-1">
                                    <svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Searching...
                                </span>
                            </button>
                        </div>
                    </div>
                    <div x-show="visitorFound" class="mt-2 rounded border border-green-200 bg-green-50 p-2 dark:border-green-800 dark:bg-green-900/20">
                        <p class="text-sm font-medium text-green-800 dark:text-green-300">✅ Found: <span x-text="foundVisitor.name"></span></p>
                    </div>
                    <div x-show="searchPerformed && !visitorFound" class="mt-2 rounded border border-yellow-200 bg-yellow-50 p-2 dark:border-yellow-800 dark:bg-yellow-900/20">
                        <p class="text-sm text-yellow-800 dark:text-yellow-300">Not found. Fill in details below.</p>
                    </div>
                </div>

                <!-- Form -->
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" x-model="form.person_name" placeholder="Full name" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Phone <span class="text-red-500">*</span></label>
                        <input type="text" x-model="form.phone" placeholder="0712345678" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" x-model="form.email" placeholder="email@example.com" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">ID Number</label>
                        <input type="text" x-model="form.id_number" placeholder="ID number" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Visitor Type</label>
                        <select x-model="form.visitor_type" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="guest">Guest</option>
                            <option value="family">Family</option>
                            <option value="employee">Employee</option>
                            <option value="contractor">Contractor</option>
                            <option value="delivery">Delivery</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Access Type</label>
                        <select x-model="form.access_type" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="entry">Entry</option>
                            <option value="guest">Guest Visit</option>
                            <option value="delivery">Delivery</option>
                            <option value="contractor">Contractor</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>
                </div>

                <!-- Location -->
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Estate <span class="text-red-500">*</span></label>
                        <select x-model="form.estate_id" @change="loadUnitsByEstate()" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Select Estate</option>
                            <template x-for="estate in estates" :key="estate.id">
                                <option :value="estate.id" x-text="estate.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Unit <span class="text-red-500">*</span></label>
                        <select x-model="form.unit_id" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Select Unit</option>
                            <template x-for="unit in filteredUnits" :key="unit.id">
                                <option :value="unit.id" x-text="unit.unit_number"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Vehicle -->
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Vehicle Reg</label>
                        <input type="text" x-model="form.vehicle_registration" placeholder="KCA 123A" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Make/Model</label>
                        <select x-model="form.vehicle_model" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Select</option>
                            <option value="Toyota Camry">Toyota Camry</option>
                            <option value="Toyota Corolla">Toyota Corolla</option>
                            <option value="Toyota Hilux">Toyota Hilux</option>
                            <option value="Honda Civic">Honda Civic</option>
                            <option value="Mercedes C-Class">Mercedes C-Class</option>
                            <option value="BMW 3 Series">BMW 3 Series</option>
                            <option value="Nissan X-Trail">Nissan X-Trail</option>
                            <option value="Subaru Forester">Subaru Forester</option>
                            <option value="Ford Ranger">Ford Ranger</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Color</label>
                        <select x-model="form.vehicle_color" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Select</option>
                            <option value="White">White</option>
                            <option value="Black">Black</option>
                            <option value="Silver">Silver</option>
                            <option value="Blue">Blue</option>
                            <option value="Red">Red</option>
                            <option value="Green">Green</option>
                            <option value="Grey">Grey</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Purpose & Status -->
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Purpose</label>
                        <input type="text" x-model="form.purpose" placeholder="Purpose of visit" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select x-model="form.status" class="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="approved">✅ Approved</option>
                            <option value="pending">⏳ Pending</option>
                            <option value="denied">❌ Denied</option>
                        </select>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Notes</label>
                    <textarea x-model="form.notes" rows="1" placeholder="Additional notes..." class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>

                <!-- Security Officer -->
                <div class="mt-3 rounded border border-blue-200 bg-blue-50 p-2 dark:border-blue-800 dark:bg-blue-900/20">
                    <div class="flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-400">
                        <span><strong>👮 Checked by:</strong> <span class="text-blue-700 dark:text-blue-400">{{ auth()->user()->name ?? 'Security Officer' }}</span></span>
                        <span><strong>🕐 Time:</strong> <span class="text-blue-700 dark:text-blue-400" x-text="currentTime"></span></span>
                        <span><strong>📋 Status:</strong> <span class="text-blue-700 dark:text-blue-400" x-text="form.status === 'approved' ? '✅ Approved' : form.status === 'pending' ? '⏳ Pending' : '❌ Denied'"></span></span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="border-t-2 border-green-500 bg-white px-4 py-3 dark:bg-gray-900">
                <div class="flex gap-3 justify-end">
                    <button @click="closeModal()" class="rounded-lg border border-gray-300 bg-white px-6 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Cancel</button>
                    <button @click="submitCheckIn()" :disabled="submitting || !form.person_name || !form.phone || !form.unit_id || !form.estate_id" class="flex items-center gap-2 rounded-lg bg-green-600 px-6 py-2 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-50 shadow-lg shadow-green-600/30">
                        <span x-show="!submitting"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg></span>
                        <span x-show="submitting" class="flex items-center gap-2"><svg class="h-5 w-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></span>
                        <span x-show="!submitting">✅ CHECK IN</span>
                        <span x-show="submitting">Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
[x-cloak] { display: none !important; }
.h-\[calc\(100\%-120px\)\]::-webkit-scrollbar {
    width: 5px;
}
.h-\[calc\(100\%-120px\)\]::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}
.h-\[calc\(100\%-120px\)\]::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}
.h-\[calc\(100\%-120px\)\]::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
.dark .h-\[calc\(100\%-120px\)\]::-webkit-scrollbar-track {
    background: #1f1f1f;
}
.dark .h-\[calc\(100\%-120px\)\]::-webkit-scrollbar-thumb {
    background: #404040;
}
.dark .h-\[calc\(100\%-120px\)\]::-webkit-scrollbar-thumb:hover {
    background: #555555;
}
</style>

<script>
document.addEventListener('alpine:init', function() {
    Alpine.data('securityQuickEntryModal', function() {
        return {
            open: false,
            searching: false,
            submitting: false,
            searchPerformed: false,
            visitorFound: false,
            foundVisitor: null,
            currentTime: '',
            estates: [],
            allUnits: [],
            filteredUnits: [],
            form: {
                lookup_by: 'phone',
                lookup_value: '',
                person_name: '',
                phone: '',
                email: '',
                id_number: '',
                visitor_type: 'guest',
                estate_id: '',
                unit_id: '',
                access_type: 'entry',
                status: 'approved',
                vehicle_registration: '',
                vehicle_model: '',
                vehicle_color: '',
                purpose: '',
                notes: ''
            },

            init() {
                this.updateTime();
                setInterval(() => this.updateTime(), 1000);
                this.loadData();
                window.securityQuickEntryModal = this;
                this.open = false;
            },

            updateTime() {
                const now = new Date();
                this.currentTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            },

            openModal() {
                this.open = true;
                this.resetForm();
                document.body.style.overflow = 'hidden';
                this.loadData();
            },

            closeModal() {
                this.open = false;
                this.resetForm();
                document.body.style.overflow = 'auto';
            },

            resetForm() {
                this.form = {
                    lookup_by: 'phone',
                    lookup_value: '',
                    person_name: '',
                    phone: '',
                    email: '',
                    id_number: '',
                    visitor_type: 'guest',
                    estate_id: '',
                    unit_id: '',
                    access_type: 'entry',
                    status: 'approved',
                    vehicle_registration: '',
                    vehicle_model: '',
                    vehicle_color: '',
                    purpose: '',
                    notes: ''
                };
                this.visitorFound = false;
                this.foundVisitor = null;
                this.searchPerformed = false;
                this.filteredUnits = [];
            },

            async loadData() {
                try {
                    // Load estates
                    const estateResponse = await fetch('/security/estates-data');
                    const estateData = await estateResponse.json();
                    if (estateData.success) {
                        this.estates = estateData.estates;
                    }

                    // Load all units
                    const unitResponse = await fetch('/security/all-units-data');
                    const unitData = await unitResponse.json();
                    if (unitData.success) {
                        this.allUnits = unitData.units;
                    }
                } catch (error) {
                    console.error('Error loading data:', error);
                }
            },

            loadUnitsByEstate() {
                if (this.form.estate_id) {
                    this.filteredUnits = this.allUnits.filter(unit => unit.estate_id == this.form.estate_id);
                    this.form.unit_id = '';
                } else {
                    this.filteredUnits = [];
                }
            },

            async searchVisitor() {
                if (!this.form.lookup_value) return;
                this.searching = true;
                this.searchPerformed = true;
                this.visitorFound = false;
                
                try {
                    const response = await fetch('/security/search-visitor', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            lookup_by: this.form.lookup_by,
                            lookup_value: this.form.lookup_value
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.visitor) {
                        this.visitorFound = true;
                        this.foundVisitor = data.visitor;
                        this.form.person_name = data.visitor.name || '';
                        this.form.phone = data.visitor.phone || '';
                        this.form.email = data.visitor.email || '';
                        this.form.id_number = data.visitor.id_number || '';
                        this.form.visitor_type = data.visitor.visitor_type || 'guest';
                        if (data.visitor.estate_id) {
                            this.form.estate_id = data.visitor.estate_id;
                            this.loadUnitsByEstate();
                        }
                        if (data.visitor.unit_id) {
                            this.form.unit_id = data.visitor.unit_id;
                        }
                        if (data.visitor.vehicle_registration) {
                            this.form.vehicle_registration = data.visitor.vehicle_registration;
                        }
                    }
                } catch (error) {
                    console.error('Error searching visitor:', error);
                } finally {
                    this.searching = false;
                }
            },

            async submitCheckIn() {
                if (!this.form.person_name) { alert('Please enter the full name'); return; }
                if (!this.form.phone) { alert('Please enter the phone number'); return; }
                if (!this.form.estate_id) { alert('Please select an estate'); return; }
                if (!this.form.unit_id) { alert('Please select a unit'); return; }

                this.submitting = true;

                try {
                    const response = await fetch('/security/quick-entry', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            person_name: this.form.person_name,
                            phone: this.form.phone,
                            email: this.form.email || null,
                            id_number: this.form.id_number || null,
                            visitor_type: this.form.visitor_type || 'guest',
                            estate_id: this.form.estate_id,
                            unit_id: this.form.unit_id,
                            access_type: this.form.access_type || 'entry',
                            status: this.form.status || 'approved',
                            vehicle_registration: this.form.vehicle_registration || null,
                            vehicle_model: this.form.vehicle_model || null,
                            vehicle_color: this.form.vehicle_color || null,
                            purpose: this.form.purpose || null,
                            notes: this.form.notes || null,
                            visitor_id: this.foundVisitor?.id || null
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert(data.message || '✅ Visitor checked in successfully!');
                        this.closeModal();
                        window.location.reload();
                    } else {
                        alert(data.message || '❌ Error checking in visitor');
                    }
                } catch (error) {
                    console.error('Error checking in:', error);
                    alert('❌ An error occurred. Please try again.');
                } finally {
                    this.submitting = false;
                }
            }
        };
    });
});

window.securityQuickEntryModal = null;
</script>