<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccountManager extends Model
{
    protected $table = 'account_managers';

    protected $fillable = [
        'user_id',
        'company_id',
        'subcounty_id',
        'title',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function subcounty()
    {
        return $this->belongsTo(Subcounty::class);
    }

    public function estates()
    {
        return $this->hasManyThrough(
            Estate::class,
            Company::class,
            'id',
            'company_id',
            'company_id',
            'id'
        );
    }

    /**
     * Get the company summary (estates, units, tenants, revenue)
     */
    public function getCompanySummary($companyId)
    {
        return DB::table('companies')
            ->leftJoin('estates', 'estates.company_id', '=', 'companies.id')
            ->leftJoin('units', 'units.estate_id', '=', 'estates.id')
            ->leftJoin('tenancies', function($join) {
                $join->on('tenancies.unit_id', '=', 'units.id')
                     ->where('tenancies.status', '=', 'active');
            })
            ->leftJoin('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
            ->leftJoin('invoices', function($join) {
                $join->on('invoices.tenancy_id', '=', 'tenancies.id')
                     ->where('invoices.status', '=', 'paid');
            })
            ->select(
                'companies.id',
                'companies.name',
                DB::raw('COUNT(DISTINCT estates.id) as total_estates'),
                DB::raw('COUNT(DISTINCT units.id) as total_units'),
                DB::raw('COUNT(DISTINCT tenants.id) as total_tenants'),
                DB::raw('COUNT(DISTINCT invoices.id) as total_invoices'),
                DB::raw('COALESCE(SUM(invoices.total_amount), 0) as total_revenue')
            )
            ->where('companies.id', $companyId)
            ->groupBy('companies.id', 'companies.name')
            ->first();
    }

    /**
     * Get all companies managed by this account manager with summaries
     */
    public function getManagedCompaniesWithSummary()
    {
        // Get all companies managed by this account manager
        $companies = Company::where('id', $this->company_id)->get();
        
        $result = [];
        foreach ($companies as $company) {
            $summary = $this->getCompanySummary($company->id);
            if ($summary) {
                $result[] = $summary;
            }
        }
        
        return $result;
    }

    /**
     * Get overall statistics for this account manager
     */
    public function getOverallStats()
    {
        $companies = $this->getManagedCompaniesWithSummary();
        
        $totalCompanies = count($companies);
        $totalEstates = 0;
        $totalUnits = 0;
        $totalTenants = 0;
        $totalRevenue = 0;
        $totalInvoices = 0;
        
        foreach ($companies as $company) {
            $totalEstates += $company->total_estates ?? 0;
            $totalUnits += $company->total_units ?? 0;
            $totalTenants += $company->total_tenants ?? 0;
            $totalRevenue += $company->total_revenue ?? 0;
            $totalInvoices += $company->total_invoices ?? 0;
        }
        
        return (object) [
            'total_companies' => $totalCompanies,
            'total_estates' => $totalEstates,
            'total_units' => $totalUnits,
            'total_tenants' => $totalTenants,
            'total_revenue' => $totalRevenue,
            'total_invoices' => $totalInvoices,
            'companies' => $companies,
        ];
    }

    /**
     * Get active tenants count for a company
     */
    public function getActiveTenantsCount($companyId)
    {
        return DB::table('tenancies')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('estates', 'estates.id', '=', 'units.estate_id')
            ->where('estates.company_id', $companyId)
            ->where('tenancies.status', 'active')
            ->count();
    }

    /**
     * Get total revenue for a company
     */
    public function getCompanyRevenue($companyId)
    {
        return DB::table('invoices')
            ->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('estates', 'estates.id', '=', 'units.estate_id')
            ->where('estates.company_id', $companyId)
            ->where('invoices.status', 'paid')
            ->sum('invoices.total_amount') ?? 0;
    }

    /**
     * Get outstanding payments for a company
     */
    public function getOutstandingPayments($companyId)
    {
        return DB::table('invoices')
            ->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')
            ->join('units', 'units.id', '=', 'tenancies.unit_id')
            ->join('estates', 'estates.id', '=', 'units.estate_id')
            ->where('estates.company_id', $companyId)
            ->whereIn('invoices.status', ['unpaid', 'partial'])
            ->sum(DB::raw('invoices.total_amount - COALESCE(invoices.total_paid, 0)')) ?? 0;
    }

    /**
     * Get unit occupancy statistics for a company
     * Returns total, occupied, and unoccupied units per estate
     */
    public function getUnitOccupancyStats($companyId)
    {
        $results = DB::table('units')
            ->leftJoin('estates', 'estates.id', '=', 'units.estate_id')
            ->leftJoin('tenancies', function($join) {
                $join->on('tenancies.unit_id', '=', 'units.id')
                     ->where('tenancies.status', '=', 'active');
            })
            ->where('estates.company_id', $companyId)
            ->select(
                'estates.id as estate_id',
                'estates.name as estate_name',
                DB::raw('COUNT(DISTINCT units.id) as total_units'),
                DB::raw('COUNT(DISTINCT CASE WHEN tenancies.id IS NOT NULL THEN units.id END) as occupied_units'),
                DB::raw('COUNT(DISTINCT CASE WHEN tenancies.id IS NULL THEN units.id END) as unoccupied_units')
            )
            ->groupBy('estates.id', 'estates.name')
            ->get();
        
        return $results;
    }

    /**
     * Get total unoccupied units across all estates for a company
     */
    public function getTotalUnoccupiedUnits($companyId)
    {
        $occupancy = $this->getUnitOccupancyStats($companyId);
        $totalUnoccupied = 0;
        
        foreach ($occupancy as $estate) {
            $totalUnoccupied += $estate->unoccupied_units;
        }
        
        return $totalUnoccupied;
    }

    /**
     * Get total occupied units across all estates for a company
     */
    public function getTotalOccupiedUnits($companyId)
    {
        $occupancy = $this->getUnitOccupancyStats($companyId);
        $totalOccupied = 0;
        
        foreach ($occupancy as $estate) {
            $totalOccupied += $estate->occupied_units;
        }
        
        return $totalOccupied;
    }

    /**
     * Get occupancy rate for a company
     */
    public function getOccupancyRate($companyId)
    {
        $occupancy = $this->getUnitOccupancyStats($companyId);
        $totalUnits = 0;
        $occupiedUnits = 0;
        
        foreach ($occupancy as $estate) {
            $totalUnits += $estate->total_units;
            $occupiedUnits += $estate->occupied_units;
        }
        
        if ($totalUnits == 0) {
            return 0;
        }
        
        return round(($occupiedUnits / $totalUnits) * 100, 1);
    }
}