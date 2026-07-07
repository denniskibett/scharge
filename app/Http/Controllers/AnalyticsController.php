<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Estate;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Properties\Models\Unit;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // Get filters
        $dateFrom = $request->input('date_from', Carbon::now()->subMonths(6)->format('Y-m-d'));
        $dateTo = $request->input('date_to', Carbon::now()->format('Y-m-d'));
        $estateFilter = $request->input('estate_id');
        
        // ============================================
        // 1. SMS STATISTICS
        // ============================================
        
        $smsQuery = DB::table('sms_campaign_history')
            ->whereBetween('sent_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        
        if ($estateFilter) {
            $smsQuery->where('estate_id', $estateFilter);
        }
        
        $smsStats = $smsQuery->select(
                DB::raw('SUM(total_recipients) as total_sms'),
                DB::raw('SUM(sent_count) as total_sent'),
                DB::raw('SUM(delivered_count) as total_delivered'),
                DB::raw('SUM(failed_count) as total_failed'),
                DB::raw('SUM(actual_cost) as total_cost')
            )
            ->first();
        
        // SMS by month (line chart)
        $smsByMonth = DB::table('sms_campaign_history')
            ->select(
                DB::raw("DATE_FORMAT(sent_at, '%Y-%m') as month"),
                DB::raw('SUM(total_recipients) as total'),
                DB::raw('SUM(delivered_count) as delivered'),
                DB::raw('SUM(failed_count) as failed')
            )
            ->whereBetween('sent_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->whereNotNull('sent_at')
            ->groupBy(DB::raw("DATE_FORMAT(sent_at, '%Y-%m')"))
            ->orderBy('month', 'asc')
            ->limit(12)
            ->get();
        
        // SMS by estate (pie chart)
        $smsByEstate = DB::table('sms_campaign_history as c')
            ->leftJoin('estates as e', 'c.estate_id', '=', 'e.id')
            ->select(
                'e.name as estate_name',
                DB::raw('SUM(c.total_recipients) as total')
            )
            ->whereBetween('c.sent_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->whereNotNull('c.estate_id')
            ->groupBy('e.name')
            ->orderBy('total', 'desc')
            ->get();
        
        $deliveryByEstate = DB::table('sms_campaign_history as c')
            ->leftJoin('estates as e', 'c.estate_id', '=', 'e.id')
            ->select(
                'e.name as estate_name',
                DB::raw('SUM(c.sent_count) as total_sent'),
                DB::raw('SUM(c.delivered_count) as total_delivered'),
                DB::raw('SUM(c.failed_count) as total_failed'),
                DB::raw('ROUND(SUM(c.delivered_count) / NULLIF(SUM(c.sent_count), 0) * 100) as delivery_rate')
            )
            ->whereBetween('c.sent_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->whereNotNull('c.estate_id')
            ->groupBy('e.name')
            ->orderBy('delivery_rate', 'desc')
            ->get();
        
        $recentCampaigns = DB::table('sms_campaign_history as c')
            ->leftJoin('estates as e', 'c.estate_id', '=', 'e.id')
            ->select(
                'c.id',
                'c.name',
                'e.name as estate_name',
                'c.total_recipients',
                'c.sent_count',
                'c.delivered_count',
                'c.failed_count',
                'c.sent_at',
                'c.status'
            )
            ->whereBetween('c.sent_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->orderBy('c.sent_at', 'desc')
            ->limit(10)
            ->get();
        
        // ============================================
        // 2. PROPERTY STATISTICS
        // ============================================
        
        $totalUnits = Unit::count();
        $occupiedUnits = Unit::where('status', 'occupied')->count();
        $vacantUnits = Unit::where('status', 'vacant')->count();
        
        $propertyStats = [
            'total_estates' => Estate::count(),
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'vacant_units' => $vacantUnits,
        ];
        
        $propertyStats['occupancy_rate'] = $totalUnits > 0 
            ? round(($occupiedUnits / $totalUnits) * 100) 
            : 0;
        
        // Units by estate (pie chart)
        $unitsByEstate = Estate::select('name', DB::raw('(SELECT COUNT(*) FROM units WHERE units.estate_id = estates.id) as total'))
            ->having('total', '>', 0)
            ->orderBy('total', 'desc')
            ->get();
        
        // Units by type (pie chart)
        $unitsByType = DB::table('units')
            ->select('unit_type', DB::raw('COUNT(*) as total'))
            ->whereNotNull('unit_type')
            ->groupBy('unit_type')
            ->orderBy('total', 'desc')
            ->get();
        
        // ============================================
        // 3. TENANT STATISTICS
        // ============================================
        
        $totalTenants = DB::table('tenants')->count();
        
        $activeTenants = DB::table('tenants')
            ->join('users', 'tenants.user_id', '=', 'users.id')
            ->where('users.status', 1)
            ->count();
        
        $inactiveTenants = DB::table('tenants')
            ->join('users', 'tenants.user_id', '=', 'users.id')
            ->where('users.status', 0)
            ->count();
        
        $tenantsWithActiveTenancy = DB::table('tenancies')
            ->where('status', 'active')
            ->distinct('tenant_id')
            ->count('tenant_id');
        
        $tenantStats = [
            'total_tenants' => $totalTenants,
            'active_tenants' => $tenantsWithActiveTenancy > 0 ? $tenantsWithActiveTenancy : $activeTenants,
            'inactive_tenants' => $totalTenants - ($tenantsWithActiveTenancy > 0 ? $tenantsWithActiveTenancy : $activeTenants),
        ];
        
        // Tenants by estate (pie chart)
        $tenantsByEstate = DB::table('tenants')
            ->join('estates', 'tenants.estate_id', '=', 'estates.id')
            ->select('estates.name as estate_name', DB::raw('COUNT(*) as total'))
            ->whereNotNull('tenants.estate_id')
            ->groupBy('estates.name')
            ->orderBy('total', 'desc')
            ->get();
        
        // ============================================
        // 4. PAYMENT STATISTICS
        // ============================================
        
        $paymentStats = DB::table('invoices')
            ->select(
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN total_amount ELSE 0 END) as paid_amount'),
                DB::raw('SUM(CASE WHEN status = "unpaid" THEN total_amount ELSE 0 END) as unpaid_amount'),
                DB::raw('SUM(CASE WHEN status = "partial" THEN total_amount ELSE 0 END) as partial_amount'),
                DB::raw('COUNT(*) as total_invoices'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) as paid_invoices'),
                DB::raw('SUM(CASE WHEN status = "unpaid" THEN 1 ELSE 0 END) as unpaid_invoices'),
                DB::raw('SUM(CASE WHEN status = "partial" THEN 1 ELSE 0 END) as partial_invoices'),
                DB::raw('SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as draft_invoices')
            )
            ->first();
        
        $totalAmount = $paymentStats->total_amount ?? 0;
        $paidAmount = $paymentStats->paid_amount ?? 0;
        
        $paymentSummary = [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $paymentStats->unpaid_amount ?? 0,
            'partial_amount' => $paymentStats->partial_amount ?? 0,
            'pending_amount' => ($paymentStats->unpaid_amount ?? 0) + ($paymentStats->partial_amount ?? 0),
            'collection_rate' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100) : 0,
            'total_invoices' => $paymentStats->total_invoices ?? 0,
            'paid_invoices' => $paymentStats->paid_invoices ?? 0,
            'unpaid_invoices' => $paymentStats->unpaid_invoices ?? 0,
            'partial_invoices' => $paymentStats->partial_invoices ?? 0,
            'draft_invoices' => $paymentStats->draft_invoices ?? 0,
            'pending_invoices' => ($paymentStats->unpaid_invoices ?? 0) + ($paymentStats->partial_invoices ?? 0),
        ];
        
        // Payments by month (line chart)
        $paymentsByMonth = DB::table('invoices')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN total_amount ELSE 0 END) as paid')
            )
            ->whereNotNull('created_at')
            ->where('status', '!=', 'draft')
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month', 'asc')
            ->limit(12)
            ->get();
        
        // Payment status distribution (pie chart)
        $paymentStatusDistribution = DB::table('invoices')
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as amount'))
            ->where('status', '!=', 'draft')
            ->groupBy('status')
            ->get();
        
        // Payments by estate (bar chart)
        $paymentsByEstate = DB::table('invoices as i')
            ->leftJoin('tenancies as t', 'i.tenancy_id', '=', 't.id')
            ->leftJoin('units as u', 't.unit_id', '=', 'u.id')
            ->leftJoin('estates as e', 'u.estate_id', '=', 'e.id')
            ->select(
                'e.name as estate_name',
                DB::raw('SUM(i.total_amount) as total_amount'),
                DB::raw('SUM(CASE WHEN i.status = "paid" THEN i.total_amount ELSE 0 END) as paid_amount')
            )
            ->whereNotNull('e.id')
            ->where('i.status', '!=', 'draft')
            ->groupBy('e.name')
            ->orderBy('total_amount', 'desc')
            ->get();
        
        $recentPayments = DB::table('invoices')
            ->select(
                'id',
                'invoice_type',
                'total_amount',
                'status',
                'billing_month',
                'created_at'
            )
            ->where('status', '!=', 'draft')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        // ============================================
        // 5. RECENT ACTIVITY
        // ============================================
        
        $recentActivity = collect();
        
        foreach ($recentCampaigns as $campaign) {
            $recentActivity->push([
                'type' => 'sms',
                'title' => $campaign->name,
                'subtitle' => $campaign->estate_name ?? 'Unknown Estate',
                'details' => $campaign->total_recipients . ' recipients',
                'status' => $campaign->status,
                'time' => $campaign->sent_at,
                'icon' => '📱',
                'color' => 'blue',
                'link' => route('sms.campaigns.show', $campaign->id)
            ]);
        }
        
        foreach ($recentPayments as $payment) {
            $recentActivity->push([
                'type' => 'payment',
                'title' => 'Invoice #' . $payment->id,
                'subtitle' => 'KES ' . number_format($payment->total_amount, 2),
                'details' => ucfirst($payment->status) . ' - ' . ($payment->invoice_type ?? 'monthly'),
                'status' => $payment->status,
                'time' => $payment->created_at,
                'icon' => '💰',
                'color' => $payment->status == 'paid' ? 'green' : 'red',
                'link' => route('invoices.show', $payment->id)
            ]);
        }
        
        $recentActivity = $recentActivity->sortByDesc('time')->take(10);
        
        // ============================================
        // 6. COMPILE ALL DATA
        // ============================================
        
        $totalSent = $smsStats->total_sent ?? 0;
        $totalDelivered = $smsStats->total_delivered ?? 0;
        
        $smsSummary = [
            'total_sms' => $smsStats->total_sms ?? 0,
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_failed' => $smsStats->total_failed ?? 0,
            'total_cost' => $smsStats->total_cost ?? 0,
            'delivery_rate' => $totalSent > 0 
                ? round(($totalDelivered / $totalSent) * 100) 
                : 0,
        ];
        
        // Get estates for filter
        $estates = Estate::all();
        
        return view('analytics.index', compact(
            'smsSummary',
            'smsByMonth',
            'smsByEstate',
            'deliveryByEstate',
            'recentCampaigns',
            'propertyStats',
            'unitsByEstate',
            'unitsByType',
            'tenantStats',
            'tenantsByEstate',
            'paymentSummary',
            'paymentsByMonth',
            'paymentStatusDistribution',
            'paymentsByEstate',
            'recentPayments',
            'recentActivity',
            'estates',
            'dateFrom',
            'dateTo',
            'estateFilter'
        ));
    }
}