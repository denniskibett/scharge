<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Tenancy;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\Unit;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::with(['user', 'tenancies' => function($query) {
                $query->with('unit.estate')->orderBy('created_at', 'desc');
            }])->get();
        
        // Get vacant units for dropdown
        $vacantUnits = Unit::where('status', 'vacant')
            ->with('estate')
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'rent_amount' => $unit->rent_amount,
                ];
            });
        
        // Get existing users without tenant records (for dropdown in modal)
        $existingUsersWithoutTenant = User::whereDoesntHave('tenant')
            ->where(function($q) {
                $q->whereHas('role', function($r) {
                    $r->where('name', 'tenant');
                })->orWhereNull('role_id');
            })
            ->get(['id', 'name', 'email', 'phone']);
        
        // Format data for Alpine.js
        $tenantsData = $tenants->map(function ($tenant) {
            $activeTenancy = $tenant->tenancies->where('status', 'active')->first();
            
            return [
                'id' => $tenant->id,
                'name' => $tenant->user->name ?? '',
                'email' => $tenant->user->email ?? '',
                'phone' => $tenant->user->phone ?? '',
                'phone2' => $tenant->user->phone2 ?? null,
                'id_number' => $tenant->id_number,
                'emergency_contact' => $tenant->emergency_contact,
                'notes' => $tenant->notes,
                'user_id' => $tenant->user_id,
                'current_unit' => $activeTenancy ? [
                    'id' => $activeTenancy->unit_id,
                    'unit_number' => $activeTenancy->unit->unit_number ?? null,
                    'estate_name' => $activeTenancy->unit->estate->name ?? null,
                    'move_in_date' => $activeTenancy->move_in_date,
                    'move_in_date_formatted' => $activeTenancy->move_in_date 
                        ? \Carbon\Carbon::parse($activeTenancy->move_in_date)->format('M d, Y') 
                        : null,
                    'status' => $activeTenancy->status,
                ] : null,
                'tenancies_count' => $tenant->tenancies->count(),
                'created_at' => $tenant->created_at,
                'created_at_formatted' => $tenant->created_at->format('M d, Y'),
            ];
        });
        
        // Get stats
        $totalTenants = $tenants->count();
        $activeTenants = $tenants->filter(fn($t) => $t->tenancies->where('status', 'active')->count() > 0)->count();
        $vacantTenants = $totalTenants - $activeTenants;
        
        return view('tenants.index', compact(
            'tenantsData',
            'totalTenants',
            'activeTenants',
            'vacantTenants',
            'vacantUnits',
            'existingUsersWithoutTenant'
        ));
    }

    public function show(Tenant $tenant)
    {
        $tenant->load([
            'user', 
            'tenancies.unit.estate',
            'tenancies' => function($query) {
                $query->orderBy('move_in_date', 'desc');
            }
        ]);
        
        // Get active tenancy
        $activeTenancy = $tenant->tenancies->where('status', 'active')->first();
        
        // Load invoices for active tenancy if exists
        $invoices = collect();
        $payments = collect();
        $totalPaid = 0;
        $totalBalance = 0;
        $currentMonthInvoice = null;
        
        if ($activeTenancy) {
            $invoices = Invoice::with(['items', 'payments'])
                ->where('tenancy_id', $activeTenancy->id)
                ->orderBy('billing_month', 'desc')
                ->get();
            
            $payments = Payment::whereHas('invoice', function($query) use ($activeTenancy) {
                    $query->where('tenancy_id', $activeTenancy->id);
                })
                ->orderBy('payment_datetime', 'desc')
                ->get();
            
            $totalPaid = $payments->sum('amount');
            $totalBalance = $invoices->sum('total_amount') - $payments->sum('amount');
            
            // Get current month invoice
            $currentMonth = now()->format('Y-m');
            $currentMonthInvoice = $invoices->firstWhere('billing_month', 'like', "{$currentMonth}%");
        }
        
        // Calculate stats
        $totalTenancies = $tenant->tenancies->count();
        $activeTenancies = $tenant->tenancies->where('status', 'active')->count();
        $pastTenancies = $tenant->tenancies->where('status', 'ended')->count();
        
        // Get all estates this tenant has lived in
        $estates = $tenant->tenancies->map(function($tenancy) {
            return $tenancy->unit->estate ?? null;
        })->filter()->unique('id')->values();
        
        return view('tenants.show', compact(
            'tenant',
            'activeTenancy',
            'invoices',
            'payments',
            'totalPaid',
            'totalBalance',
            'currentMonthInvoice',
            'totalTenancies',
            'activeTenancies',
            'pastTenancies',
            'estates'
        ));
    }

public function store(Request $request)
{
    $mode = $request->input('mode', 'new');
    
    if ($mode === 'existing') {
        // Handle existing user mode
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'id_number' => 'nullable|string|max:50',
            'emergency_contact' => 'nullable|string|max:255',
            'unit_id' => 'nullable|exists:units,id',
            'notes' => 'nullable|string',
        ]);
        
        // Check if user already has a tenant record
        $existingTenant = Tenant::where('user_id', $validated['user_id'])->first();
        if ($existingTenant) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user already has a tenant record.'
                ], 422);
            }
            return redirect()->back()->with('error', 'This user already has a tenant record.');
        }
        
        // Get the user
        $user = User::findOrFail($validated['user_id']);
        
        // Assign tenant role if not already assigned (using role_id directly)
        $tenantRole = Role::where('name', 'tenant')->first();
        if ($tenantRole && $user->role_id !== $tenantRole->id) {
            $user->update(['role_id' => $tenantRole->id]);
        }
        
        // Create tenant record
        $tenant = Tenant::create([
            'user_id' => $user->id,
            'id_number' => $validated['id_number'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);
        
        // Create tenancy if unit is provided
        if (!empty($validated['unit_id'])) {
            $tenancy = Tenancy::create([
                'tenant_id' => $tenant->id,
                'unit_id' => $validated['unit_id'],
                'move_in_date' => now()->format('Y-m-d'),
                'status' => 'active',
            ]);
            
            // Update unit status
            Unit::find($validated['unit_id'])->update(['status' => 'occupied']);
        }
        
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully from existing user',
                'tenant' => $tenant->load('user')
            ]);
        }
        
        return redirect()->route('tenants.index')->with('success', 'Tenant created successfully');
        
    } else {
        // Handle new user mode (original logic)
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|unique:users,email',
            'phone2' => 'nullable|string|max:20',
            'id_number' => 'nullable|string|max:50',
            'emergency_contact' => 'nullable|string|max:255',
            'unit_id' => 'nullable|exists:units,id',
            'notes' => 'nullable|string',
        ]);

        // Generate unique email if not provided
        if (empty($validated['email'])) {
            $baseEmail = strtolower(str_replace(' ', '.', $validated['name'])) . '.' . substr($validated['phone'], -4);
            $email = $baseEmail . '@tenant.com';
            
            // Check if email already exists, if yes, add index
            $counter = 1;
            while (User::where('email', $email)->exists()) {
                $email = $baseEmail . $counter . '@tenant.com';
                $counter++;
            }
            $validated['email'] = $email;
        }

        // Get tenant role ID
        $tenantRole = Role::where('name', 'tenant')->first();
        
        // Create user account for the tenant
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'phone2' => $validated['phone2'] ?? null,
            'password' => Hash::make('00000000'), // Default password
            'email_verified_at' => now(),
            'role_id' => $tenantRole ? $tenantRole->id : null, // Assign tenant role directly
        ]);

        // Create tenant record
        $tenant = Tenant::create([
            'user_id' => $user->id,
            'id_number' => $validated['id_number'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Create tenancy if unit is provided
        if (!empty($validated['unit_id'])) {
            $tenancy = Tenancy::create([
                'tenant_id' => $tenant->id,
                'unit_id' => $validated['unit_id'],
                'move_in_date' => now()->format('Y-m-d'),
                'status' => 'active',
            ]);

            // Update unit status
            Unit::find($validated['unit_id'])->update(['status' => 'occupied']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true, 
                'message' => 'Tenant created successfully',
                'tenant' => $tenant->load('user')
            ]);
        }

        return redirect()->route('tenants.index')->with('success', 'Tenant created successfully');
    }
}

    public function edit(Tenant $tenant)
    {
        $tenant->load('user');
        
        if (request()->wantsJson()) {
            return response()->json([
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->user->name,
                    'email' => $tenant->user->email,
                    'phone' => $tenant->user->phone,
                    'phone2' => $tenant->user->phone2,
                    'id_number' => $tenant->id_number,
                    'emergency_contact' => $tenant->emergency_contact,
                    'notes' => $tenant->notes,
                ]
            ]);
        }
        
        return view('tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $tenant->user_id,
            'phone' => 'required|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'id_number' => 'nullable|string|max:50',
            'emergency_contact' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        // Update user
        $tenant->user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'phone2' => $validated['phone2'] ?? null,
        ]);

        // Update tenant
        $tenant->update([
            'id_number' => $validated['id_number'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true, 
                'message' => 'Tenant updated successfully',
                'tenant' => $tenant->fresh('user')
            ]);
        }

        return redirect()->route('tenants.show', $tenant)->with('success', 'Tenant updated successfully');
    }

    public function destroy(Tenant $tenant)
    {
        // End any active tenancies
        $tenant->tenancies()->where('status', 'active')->update(['status' => 'ended']);
        
        // Set associated units to vacant
        $activeTenancies = $tenant->tenancies()->where('status', 'active')->get();
        foreach ($activeTenancies as $tenancy) {
            if ($tenancy->unit) {
                $tenancy->unit->update(['status' => 'vacant']);
            }
        }

        // Delete user account
        $tenant->user->delete();

        // Delete tenant
        $tenant->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Tenant deleted successfully']);
        }

        return redirect()->route('tenants.index')->with('success', 'Tenant deleted successfully');
    }

    public function storeInvoice(Request $request, Tenant $tenant)
    {
        $activeTenancy = $tenant->tenancies()->where('status', 'active')->first();
        
        if (!$activeTenancy) {
            return redirect()->back()->with('error', 'No active tenancy found for this tenant.');
        }

        $validated = $request->validate([
            'billing_month' => 'required|date_format:Y-m',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.item_type' => 'required|in:rent,power,water,security,garbage,internet,other',
        ]);

        // Calculate total amount
        $totalAmount = collect($validated['items'])->sum('amount');

        // Create invoice
        $invoice = Invoice::create([
            'tenancy_id' => $activeTenancy->id,
            'invoice_type' => 'monthly',
            'billing_month' => $validated['billing_month'] . '-01',
            'total_amount' => $totalAmount,
            'status' => 'unpaid',
        ]);

        // Create invoice items
        foreach ($validated['items'] as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'amount' => $item['amount'],
                'item_type' => $item['item_type'],
            ]);
        }

        return redirect()->back()->with('success', 'Invoice created successfully.');
    }

    public function storePayment(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:mpesa,bank,cash',
            'transaction_id' => 'nullable|string|max:255',
            'transaction_message' => 'nullable|string',
            'paid_to' => 'nullable|string|max:255',
            'payer_name' => 'nullable|string|max:255',
            'payment_datetime' => 'required|date',
            'payment_month' => 'required|string|max:255',
        ]);

        // Get the invoice
        $invoice = Invoice::findOrFail($validated['invoice_id']);
        
        // Check if invoice belongs to this tenant's active tenancy
        $activeTenancy = $tenant->tenancies()->where('status', 'active')->first();
        if (!$activeTenancy || $invoice->tenancy_id !== $activeTenancy->id) {
            return redirect()->back()->with('error', 'Invalid invoice for this tenant.');
        }

        // Create payment
        $payment = Payment::create([
            'invoice_id' => $validated['invoice_id'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'transaction_id' => $validated['transaction_id'],
            'transaction_message' => $validated['transaction_message'],
            'paid_to' => $validated['paid_to'],
            'payer_name' => $validated['payer_name'],
            'payment_datetime' => $validated['payment_datetime'],
            'payment_month' => $validated['payment_month'],
        ]);

        // Update invoice status
        $totalPaid = $invoice->payments()->sum('amount');
        if ($totalPaid >= $invoice->total_amount) {
            $invoice->status = 'paid';
        } elseif ($totalPaid > 0) {
            $invoice->status = 'partial';
        } else {
            $invoice->status = 'unpaid';
        }
        $invoice->save();

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'tenants' => 'required|array|min:1',
            'tenants.*.name' => 'required|string|max:255',
            'tenants.*.phone' => 'required|string|max:20',
            'tenants.*.unit_id' => 'nullable|exists:units,id',
        ]);

        $created = [];
        $errors = [];
        
        // Get tenant role
        $tenantRole = Role::where('name', 'tenant')->first();

        foreach ($validated['tenants'] as $index => $data) {
            try {
                // Generate unique email
                $baseEmail = strtolower(str_replace(' ', '.', $data['name'])) . '.' . substr($data['phone'], -4);
                $email = $baseEmail . '@tenant.com';
                
                $counter = 1;
                while (User::where('email', $email)->exists()) {
                    $email = $baseEmail . $counter . '@tenant.com';
                    $counter++;
                }

                // Create user account
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $email,
                    'phone' => $data['phone'],
                    'password' => Hash::make('00000000'),
                    'email_verified_at' => now(),
                ]);

                // Assign tenant role
                if ($tenantRole) {
                    $user->roles()->attach($tenantRole->id);
                }

                // Create tenant record
                $tenant = Tenant::create([
                    'user_id' => $user->id,
                ]);

                // Create tenancy if unit is provided
                if (isset($data['unit_id'])) {
                    $tenancy = Tenancy::create([
                        'tenant_id' => $tenant->id,
                        'unit_id' => $data['unit_id'],
                        'move_in_date' => now()->format('Y-m-d'),
                        'status' => 'active',
                    ]);

                    Unit::find($data['unit_id'])->update(['status' => 'occupied']);
                }

                $created[] = [
                    'index' => $index,
                    'tenant' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'tenant' => $data['name'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' tenants created successfully',
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    // API endpoint to search tenants
    public function searchTenants(Request $request)
    {
        if ($request->ajax()) {
            $search = $request->get('search', '');
            
            $tenants = Tenant::with('user')
                ->whereHas('user', function($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%");
                })
                ->orWhere('id_number', 'like', "%{$search}%")
                ->select('id', 'user_id', 'id_number')
                ->limit(10)
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->user->name ?? '',
                        'email' => $tenant->user->email ?? '',
                        'phone' => $tenant->user->phone ?? '',
                        'id_number' => $tenant->id_number,
                        'label' => "{$tenant->user->name} ({$tenant->user->email})"
                    ];
                });
            
            return response()->json($tenants);
        }
    }

    // Check if email already exists
    public function checkEmail(Request $request)
    {
        if ($request->ajax()) {
            $email = $request->get('email');
            $excludeUserId = $request->get('exclude_user_id');
            
            $query = User::where('email', $email);
            
            if ($excludeUserId) {
                $query->where('id', '!=', $excludeUserId);
            }
            
            $exists = $query->exists();
            
            return response()->json([
                'exists' => $exists,
                'message' => $exists ? 'Email already exists' : 'Email available'
            ]);
        }
    }

    // Get tenant's tenancy history
    public function getTenancyHistory(Tenant $tenant)
    {
        $tenancies = $tenant->tenancies()
            ->with(['unit.estate'])
            ->orderBy('move_in_date', 'desc')
            ->get()
            ->map(function ($tenancy) {
                return [
                    'id' => $tenancy->id,
                    'unit_number' => $tenancy->unit->unit_number,
                    'estate_name' => $tenancy->unit->estate->name,
                    'move_in_date' => $tenancy->move_in_date,
                    'move_in_date_formatted' => \Carbon\Carbon::parse($tenancy->move_in_date)->format('M d, Y'),
                    'move_out_date' => $tenancy->move_out_date,
                    'move_out_date_formatted' => $tenancy->move_out_date 
                        ? \Carbon\Carbon::parse($tenancy->move_out_date)->format('M d, Y') 
                        : null,
                    'status' => $tenancy->status,
                    'duration' => $tenancy->move_out_date 
                        ? \Carbon\Carbon::parse($tenancy->move_in_date)->diffForHumans(\Carbon\Carbon::parse($tenancy->move_out_date), true)
                        : \Carbon\Carbon::parse($tenancy->move_in_date)->diffForHumans(now(), true),
                ];
            });
        
        return response()->json($tenancies);
    }
}