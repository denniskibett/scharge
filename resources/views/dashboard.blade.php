@extends('layouts.app')

@section('title', Auth::user()->dashboard_title)

@section('content')
<div x-data="dashboard()" x-init="init()">
    <div class="container-fluid px-4 py-4">


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
            'paymentMethods' => $paymentMethods ?? [],
            'monthlyRevenueExpense' => $monthlyRevenueExpense ?? [],
            'invoiceStatus' => $invoiceStatus ?? [],
            'collectionRate' => $collectionRate ?? 85,
            'performanceMetrics' => $performanceMetrics ?? []
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