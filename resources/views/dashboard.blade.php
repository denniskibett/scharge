@extends('layouts.app')

@section('title', Auth::user()->dashboard_title)

@section('content')
<div x-data="dashboard()" x-init="init()">
    <div class="container-fluid px-4 py-4">

    <!-- Welcome Card -->
    <div class="row mb-6">
        <div class="col-12">
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary to-secondary p-6 shadow-lg">
                <div class="absolute inset-0 opacity-10">
                    <svg class="absolute -right-20 -top-20 h-64 w-64 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                
                <div class="relative flex flex-col md:flex-row md:items-center md:justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <div class="h-16 w-16 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                <img src="{{ Auth::user()->avatar ? Storage::url(Auth::user()->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode(Auth::user()->name) . '&background=ffffff&color=6366f1' }}" 
                                     alt="avatar" class="h-14 w-14 rounded-full">
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-white">Welcome back, <span x-text="userName"></span>!</h2>
                                <p class="text-brand-100 mt-1" x-text="currentDate"></p>
                                
                                <!-- Company Info Display (for non-sysadmin) -->
                                @if(!auth()->user()->hasRole('sysadmin') && Auth::user()->company)
                                <div class="mt-2 flex items-center gap-2 text-brand-100 text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <span>Managed by: <strong>{{ Auth::user()->company->name }}</strong></span>
                                    
                                    @if(Auth::user()->company->currentSubscription)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-white/20">
                                            {{ ucfirst(Auth::user()->company->currentSubscription->plan->name ?? 'Plan') }}
                                        </span>
                                    @endif
                                </div>
                                @elseif(auth()->user()->hasRole('sysadmin'))
                                <div class="mt-2 flex items-center gap-2 text-brand-100 text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span>System Administrator</span>
                                </div>
                                @else
                                <div class="mt-2 flex items-center gap-2 text-brand-100 text-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                    </svg>
                                    <span>No company assigned</span>
                                </div>
                                @endif
                                
                                <p class="text-brand-50 text-sm mt-2" x-text="welcomeMessage"></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <p class="text-sm text-brand-100">Your Role</p>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 text-white backdrop-blur-sm" x-text="userRole"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Cards - Only for non-sysadmin roles -->
    @if(!auth()->user()->hasRole('sysadmin'))
    <div class="mt-6 mb-6">
        @include('partials.card.card-dashboard', [
            'stats' => $stats ?? [],
            'outstandingBalance' => $outstandingBalance ?? 0,
            'totalPaid' => $totalPaid ?? 0
        ])
    </div>
    @endif

    <!-- Role-Based Dashboard Content - Using Partials -->
    
    <!-- SYSADMIN DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('sysadmin'))
        @include('partials.dashboard.sys-admin', [
            'stats' => $stats ?? [],
            'companies' => $companies ?? [],
            'pendingUsers' => $pendingUsers ?? [],
            'pendingCompanyUsers' => $pendingCompanyUsers ?? [],
            'inactiveUsers' => $inactiveUsers ?? []
        ])
    @endif
    @endauth

    <!-- ADMIN DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('admin'))
        @include('partials.dashboard.admin', [
            'stats' => $stats ?? [],
            'roleData' => $roleData ?? [],
            'company' => $company ?? null,
            'recentInvoices' => $roleData['recentInvoices'] ?? [],
            'recentPayments' => $roleData['recentPayments'] ?? [],
            'waterReadings' => $roleData['recentReadings'] ?? [],
            'monthlyRevenue' => $monthlyRevenue ?? []
        ])
    @endif
    @endauth

    <!-- PROPERTY MANAGER DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('property_manager'))
        @include('partials.dashboard.property-manager', [
            'stats' => $stats ?? [],
            'roleData' => $roleData ?? [],
            'company' => $company ?? null
        ])
    @endif
    @endauth

    <!-- ACCOUNTANT DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('accountant'))
        @include('partials.dashboard.accountant', [
            'stats' => $stats ?? [],
            'roleData' => $roleData ?? [],
            'company' => $company ?? null,
            'monthlyRevenue' => $monthlyRevenue ?? [],
            'paymentMethods' => $paymentMethods ?? []
        ])
    @endif
    @endauth

    <!-- TENANT DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('tenant'))
        @include('partials.dashboard.tenant', [
            'stats' => $stats ?? [],
            'roleData' => $roleData ?? [],
            'company' => $company ?? null,
            'outstandingBalance' => $outstandingBalance ?? 0,
            'totalPaid' => $totalPaid ?? 0,
            'units' => $units ?? [],
            'estates' => $estates ?? [],
            'currentUnit' => $currentUnit ?? null
        ])
    @endif
    @endauth

    <!-- METER READER DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('meter_reader'))
        @include('partials.dashboard.meter-reader', [
            'stats' => $stats ?? [],
            'roleData' => $roleData ?? [],
            'company' => $company ?? null,
            'units' => $roleData['units'] ?? [],
            'estates' => $roleData['estates'] ?? []
        ])
    @endif
    @endauth

    <!-- MAINTENANCE DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('maintenance'))
        @include('partials.dashboard.maintenance', [
            'stats' => $stats ?? [],
            'roleData' => $roleData ?? [],
            'company' => $company ?? null
        ])
    @endif
    @endauth

    <!-- SECURITY DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('security'))
    <div class="mt-6">
        @include('partials.table.table-security', [
            'logs' => $roleData['accessLogs'] ?? [],
            'units' => $units ?? [],
            'totalLogs' => ($roleData['accessLogs'] ?? collect())->count(),
            'pendingCount' => ($roleData['pendingLogs'] ?? collect())->count(),
            'approvedCount' => ($roleData['accessLogs'] ?? collect())->filter(function($log) { 
                return in_array($log['status'], ['approved', 'granted']); 
            })->count(),
            'deniedCount' => ($roleData['accessLogs'] ?? collect())->filter(function($log) { 
                return $log['status'] === 'denied'; 
            })->count()
        ])
    </div>
    @endif
    @endauth

    <!-- CLEANING STAFF DASHBOARD -->
    @auth
    @if(auth()->user()->hasRole('cleaning_staff'))
        @include('partials.dashboard.cleaning-staff', [
            'stats' => $stats ?? [],
            'roleData' => $roleData ?? [],
            'company' => $company ?? null
        ])
    @endif
    @endauth

    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
function dashboard() {
    return {
        roleData: @json($roleData ?? []),
        stats: @json($stats ?? []),
        userName: '{{ Auth::user()->first_name ?: Auth::user()->name }}',
        userRole: '{{ ucfirst(str_replace("_", " ", Auth::user()->role->name ?? "User")) }}',
        dashboardTitle: '{{ Auth::user()->dashboard_title ?? 'Dashboard' }}',
        currentDate: new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        welcomeMessage: '',
        
        // Tab states for different roles (used by partials)
        activeSysTab: 'companies',
        activeAdminTab: 'invoices',
        activePMTab: 'readings',
        activeAccTab: 'overdue',
        activeTenantTab: 'invoices',
        activeMeterTab: 'pending',  
        activeMaintTab: 'open',
        activeSecurityTab: 'pending',
        
        init() {
            this.setWelcomeMessage();
            
            console.log('========== DASHBOARD DEBUG ==========');
            console.log('User Role:', this.userRole);
            console.log('Role Data keys:', Object.keys(this.roleData));
            console.log('=====================================');
        },
        
        setWelcomeMessage() {
            const hour = new Date().getHours();
            if (hour < 12) {
                this.welcomeMessage = 'Good morning! Here\'s your property management overview.';
            } else if (hour < 18) {
                this.welcomeMessage = 'Good afternoon! Here\'s your property management overview.';
            } else {
                this.welcomeMessage = 'Good evening! Here\'s your property management overview.';
            }
        }
    };
}

// Sysadmin Functions
function editCompany(companyId) {
    window.location.href = `/admin/companies/${companyId}/edit`;
}

function viewCompany(companyId) {
    window.location.href = `/admin/companies/${companyId}`;
}

function createCompany() {
    window.location.href = '/admin/companies/create';
}

function verifyUser(userId) {
    if (confirm('Verify this user?')) {
        fetch(`/admin/users/${userId}/verify`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  location.reload();
              } else {
                  alert('Failed to verify user');
              }
          });
    }
}

function assignCompany(userId) {
    const companyId = prompt('Enter Company ID to assign:');
    if (companyId) {
        fetch(`/admin/users/${userId}/assign-company`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ company_id: companyId })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  location.reload();
              } else {
                  alert('Failed to assign company');
              }
          });
    }
}

function saveSystemSettings() {
    const settings = {
        default_water_rate: document.getElementById('defaultWaterRate')?.value || 50,
        invoice_due_days: document.getElementById('dueDays')?.value || 30,
        late_fee_percentage: document.getElementById('lateFee')?.value || 5,
        maintenance_sla_days: document.getElementById('maintenanceSla')?.value || 3
    };
    
    fetch('/admin/system-settings', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(settings)
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              alert('Settings saved successfully!');
          } else {
              alert('Failed to save settings');
          }
      });
}

// Global functions for other roles
function editReading(unitId) {
    if (window.waterReadingModal) {
        window.waterReadingModal.openModal(unitId);
    } else if (typeof openCreateReadingModal === 'function') {
        openCreateReadingModal(unitId);
    } else {
        alert('Please refresh the page to record readings.');
    }
}

function openCreateReadingModal(unitId) {
    if (typeof window.openCreateReadingModal === 'function') {
        window.openCreateReadingModal(unitId);
    } else if (window.waterReadingModal) {
        window.waterReadingModal.openModal(unitId);
    } else {
        alert('Please refresh the page to record readings.');
    }
}

function viewPayment(paymentId) {
    window.location.href = `/payments/${paymentId}`;
}

function deletePayment(paymentId) {
    if (confirm('Are you sure you want to delete this payment?')) {
        fetch(`/payments/${paymentId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) location.reload();
          });
    }
}

function updateRequestStatus(requestId) {
    window.location.href = `/maintenance/${requestId}/edit`;
}

function viewRequest(requestId) {
    window.location.href = `/maintenance/${requestId}`;
}

function viewLog(logId) {
    window.location.href = `/security/logs/${logId}`;
}

function markTaskComplete(taskId) {
    if (confirm('Mark this task as complete?')) {
        fetch(`/cleaning/tasks/${taskId}/complete`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  location.reload();
              } else {
                  alert('Failed to mark task as complete');
              }
          });
    }
}

function approveAccessLog(logId) {
    if (confirm('Approve this access request?')) {
        fetch(`/security/logs/${logId}/approve`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  location.reload();
              } else {
                  alert('Failed to approve access');
              }
          });
    }
}

function rejectAccessLog(logId) {
    if (confirm('Reject this access request?')) {
        fetch(`/security/logs/${logId}/reject`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  location.reload();
              } else {
                  alert('Failed to reject access');
              }
          });
    }
}

function closeModal() {
    if (typeof window.closeModal === 'function') {
        window.closeModal();
    }
}
</script>
@endsection