<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountManager;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccountManagerController extends Controller
{
    /**
     * Display a listing of account managers with statistics.
     */
    public function index()
    {
        $accountManagers = AccountManager::with(['user', 'company'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Calculate overall statistics
        $totalManagers = $accountManagers->count();
        $activeManagers = $accountManagers->where('is_active', true)->count();
        $primaryManagers = $accountManagers->where('is_primary', true)->count();
        $totalCompanies = $accountManagers->filter(function($manager) {
            return $manager->company_id !== null;
        })->count();
        
        // Get revenue, estate stats, and occupancy from companies
        $totalEstates = 0;
        $totalTenants = 0;
        $totalRevenue = 0;
        $totalUnits = 0;
        $totalInvoices = 0;
        $totalOccupiedUnits = 0;
        $totalUnoccupiedUnits = 0;
        
        foreach ($accountManagers as $manager) {
            if ($manager->company_id) {
                $summary = $manager->getCompanySummary($manager->company_id);
                if ($summary) {
                    $totalEstates += $summary->total_estates ?? 0;
                    $totalUnits += $summary->total_units ?? 0;
                    $totalTenants += $summary->total_tenants ?? 0;
                    $totalRevenue += $summary->total_revenue ?? 0;
                    $totalInvoices += $summary->total_invoices ?? 0;
                }
                
                // Get occupancy stats
                $occupancy = $manager->getUnitOccupancyStats($manager->company_id);
                foreach ($occupancy as $estate) {
                    $totalOccupiedUnits += $estate->occupied_units ?? 0;
                    $totalUnoccupiedUnits += $estate->unoccupied_units ?? 0;
                }
            }
        }
        
        // Calculate occupancy rate
        $occupancyRate = $totalUnits > 0 ? round(($totalOccupiedUnits / $totalUnits) * 100, 1) : 0;
        
        $stats = (object) [
            'total_managers' => $totalManagers,
            'active_managers' => $activeManagers,
            'primary_managers' => $primaryManagers,
            'total_companies' => $totalCompanies,
            'total_estates' => $totalEstates,
            'total_units' => $totalUnits,
            'total_tenants' => $totalTenants,
            'total_revenue' => $totalRevenue,
            'total_invoices' => $totalInvoices,
            'total_occupied_units' => $totalOccupiedUnits,
            'total_unoccupied_units' => $totalUnoccupiedUnits,
            'occupancy_rate' => $occupancyRate,
        ];
        
        return view('admin.account-managers.index', compact('accountManagers', 'stats'));
    }

    /**
     * Show the form for creating a new account manager.
     */
    public function create()
    {
        $users = User::whereIn('role_id', [1, 2, 3])->get();
        $companies = Company::orderBy('name')->get();
        
        return view('admin.account-managers.create', compact('users', 'companies'));
    }

    /**
     * Store a newly created account manager in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'company_id' => 'required|exists:companies,id',
            'title' => 'nullable|string|max:255',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $accountManager = AccountManager::create([
                'user_id' => $validated['user_id'],
                'company_id' => $validated['company_id'],
                'title' => $validated['title'] ?? 'Account Manager',
                'is_primary' => $validated['is_primary'] ?? 0,
                'is_active' => $validated['is_active'] ?? 1,
            ]);

            return redirect()
                ->route('admin.account-managers.index')
                ->with('success', 'Account Manager created successfully!');
                
        } catch (\Exception $e) {
            Log::error('Error creating account manager: ' . $e->getMessage());
            
            return redirect()
                ->back()
                ->with('error', 'Failed to create account manager: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified account manager.
     */
    public function show($id)
    {
        $accountManager = AccountManager::with(['user', 'company'])
            ->findOrFail($id);
        
        // Get overall stats
        $stats = $accountManager->getOverallStats();
        
        // Get managed companies with details
        $managedCompanies = $accountManager->getManagedCompaniesWithSummary();
        
        return view('admin.account-managers.show', compact('accountManager', 'stats', 'managedCompanies'));
    }

    /**
     * Show the form for editing the specified account manager.
     */
    public function edit($id)
    {
        $accountManager = AccountManager::with(['user', 'company'])
            ->findOrFail($id);
        
        $users = User::whereIn('role_id', [1, 2, 3])->get();
        $companies = Company::orderBy('name')->get();
        
        return view('admin.account-managers.edit', compact('accountManager', 'users', 'companies'));
    }

    /**
     * Update the specified account manager in storage.
     */
    public function update(Request $request, $id)
    {
        $accountManager = AccountManager::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'company_id' => 'required|exists:companies,id',
            'title' => 'nullable|string|max:255',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $accountManager->update([
                'user_id' => $validated['user_id'],
                'company_id' => $validated['company_id'],
                'title' => $validated['title'] ?? 'Account Manager',
                'is_primary' => $validated['is_primary'] ?? 0,
                'is_active' => $validated['is_active'] ?? 1,
            ]);

            return redirect()
                ->route('admin.account-managers.index')
                ->with('success', 'Account Manager updated successfully!');
                
        } catch (\Exception $e) {
            Log::error('Error updating account manager: ' . $e->getMessage());
            
            return redirect()
                ->back()
                ->with('error', 'Failed to update account manager: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified account manager from storage.
     */
    public function destroy($id)
    {
        try {
            $accountManager = AccountManager::findOrFail($id);
            $accountManager->delete();

            return redirect()
                ->route('admin.account-managers.index')
                ->with('success', 'Account Manager deleted successfully!');
                
        } catch (\Exception $e) {
            Log::error('Error deleting account manager: ' . $e->getMessage());
            
            return redirect()
                ->back()
                ->with('error', 'Failed to delete account manager: ' . $e->getMessage());
        }
    }
}