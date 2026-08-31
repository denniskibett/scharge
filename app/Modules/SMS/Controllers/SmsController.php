<?php

namespace App\Modules\SMS\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Water\Models\WaterReading;
use App\Modules\SMS\Services\KenyaSMS;
use App\Modules\SMS\Models\SmsLog;
use App\Modules\SMS\Models\SmsTemplate;
use App\Modules\SMS\Models\SmsCampaign;
use App\Modules\Properties\Models\Estate;
use App\Models\Company;
use App\Modules\SMS\Helpers\PhoneHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SmsController extends Controller
{
    /**
     * Display the SMS broadcast page (OPTIMIZED – eager loading)
     */
    public function create()
    {
        // Increase timeout to handle large datasets
        set_time_limit(120);

        // ============================================
        // Get tenants with active tenancies and phone numbers
        // Eager load relationships to avoid N+1 queries
        // ============================================
        $tenants = Tenant::with([
            'user',
            'activeTenancy.unit.estate',
            'activeTenancy.invoices',
            'activeTenancy.unit.waterReadings',
        ])
        ->whereHas('activeTenancy', function($q) {
            $q->where('status', 'active');
        })
        ->whereHas('user', function($q) {
            $q->whereNotNull('phone')->where('phone', '!=', '');
        })
        ->get();

        // ============================================
        // Transform data with robust phone cleaning
        // ============================================
        $tenantData = $tenants->map(function ($tenant) {
            $user = $tenant->user;
            $tenancy = $tenant->activeTenancy;
            $unit = $tenancy ? $tenancy->unit : null;
            $estate = $unit ? $unit->estate : null;

            // Clean phone
            $rawPhone = $user ? $user->phone : '';
            $phone = preg_replace('/[^0-9]/', '', $rawPhone);
            if (strlen($phone) >= 9) {
                if (substr($phone, 0, 1) === '0') {
                    $phone = '254' . substr($phone, 1);
                } elseif (substr($phone, 0, 3) !== '254') {
                    $phone = '254' . $phone;
                }
            } else {
                return null;
            }

            if (strlen($phone) < 12) {
                return null;
            }

            // Get latest reading from eager-loaded collection
            $reading = $unit ? $unit->waterReadings->sortByDesc('reading_date')->first() : null;

            $waterBill = $reading ? (float) $reading->charge : 0;
            $prevRead = $reading ? (float) $reading->previous_reading : 0;
            $currRead = $reading ? (float) $reading->current_reading : 0;
            $readingDate = $reading ? $reading->reading_date : null;
            $waterConsumption = $reading ? (float) $reading->consumption : 0;
            if ($waterConsumption == 0 && $prevRead > 0 && $currRead > 0) {
                $waterConsumption = $currRead - $prevRead;
            }

            $securityFee = 500;
            $garbageFee = 300;
            $total = $waterBill + $securityFee + $garbageFee;

            $isKenyan = preg_match('/^2547[0-9]{8}$/', $phone);
            $readingMonth = $readingDate ? Carbon::parse($readingDate)->format('F Y') : Carbon::now()->format('F Y');
            $baseDate = $readingDate ? Carbon::parse($readingDate) : Carbon::now();
            $dueDate = $baseDate->copy()->addMonth()->day(5)->format('Y-m-d');

            // Payment status from invoices (eager-loaded)
            $paymentStatus = $this->getPaymentStatusFromInvoices($tenancy);

            return [
                'id' => $tenant->id,
                'name' => $user ? $user->name : 'N/A',
                'phone' => $phone,
                'is_kenyan' => $isKenyan,
                'unit_number' => $unit ? $unit->unit_number : 'N/A',
                'unit' => $unit ? $unit->unit_number : 'N/A',
                'estate_id' => $estate ? $estate->id : null,
                'estate_name' => $estate ? $estate->name : 'N/A',
                'water_bill' => $waterBill,
                'water_consumption' => $waterConsumption,
                'prev_read' => $prevRead,
                'curr_read' => $currRead,
                'reading_date' => $readingDate,
                'reading_month' => $readingMonth,
                'month' => $readingMonth,
                'due_date' => $dueDate,
                'security_fee' => $securityFee,
                'garbage_fee' => $garbageFee,
                'total' => $total,
                'payment_status' => $paymentStatus,
                'status' => $paymentStatus,
            ];
        })
        ->filter()
        ->values();

        // Fallback if empty
        if ($tenantData->isEmpty()) {
            $sample = $tenants->take(2)->map(function($t) {
                return [
                    'id' => $t->id,
                    'user_phone' => $t->user ? $t->user->phone : null,
                    'cleaned' => preg_replace('/[^0-9]/', '', $t->user ? $t->user->phone : ''),
                ];
            });
            \Log::error('Tenant data empty. Sample raw data: ', $sample->toArray());

            $tenantData = collect([
                [
                    'id' => 999,
                    'name' => 'No tenants found',
                    'phone' => '254712345678',
                    'is_kenyan' => true,
                    'unit_number' => 'N/A',
                    'unit' => 'N/A',
                    'estate_id' => null,
                    'estate_name' => 'N/A',
                    'water_bill' => 0,
                    'water_consumption' => 0,
                    'prev_read' => 0,
                    'curr_read' => 0,
                    'reading_date' => now(),
                    'reading_month' => now()->format('F Y'),
                    'month' => now()->format('F Y'),
                    'due_date' => now()->addDays(14)->format('Y-m-d'),
                    'security_fee' => 0,
                    'garbage_fee' => 0,
                    'total' => 0,
                    'payment_status' => 'pending',
                    'status' => 'pending',
                ]
            ]);
        }

        // Other data for view
        $estates = Estate::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        $templates = SmsTemplate::orderBy('name')->get();
        $logs = SmsLog::orderBy('created_at', 'desc')->paginate(20);
        $campaigns = SmsCampaign::with('creator')->latest()->paginate(20);
        $sandbox = config('sms.kenyasms.sandbox', true);

        $internationalCount = Tenant::with('user')
            ->get()
            ->filter(function ($tenant) {
                $rawPhone = $tenant->user->phone ?? null;
                $phone = preg_replace('/[^0-9]/', '', $rawPhone);
                return $phone && !preg_match('/^2547[0-9]{8}$/', $phone);
            })->count();

        return view('sms.broadcast', [
            'tenants' => $tenantData,
            'estates' => $estates,
            'companies' => $companies,
            'templates' => $templates,
            'logs' => $logs,
            'campaigns' => $campaigns,
            'sandbox' => $sandbox,
            'internationalCount' => $internationalCount,
        ]);
    }

    /**
     * Get payment status from eager-loaded invoices
     */
    private function getPaymentStatusFromInvoices($tenancy)
    {
        if (!$tenancy) {
            return 'pending';
        }
        $invoices = $tenancy->invoices;
        $unpaid = $invoices->where('status', 'unpaid')->count();
        $paid = $invoices->where('status', 'paid')->count();
        if ($paid > 0 && $unpaid == 0) {
            return 'paid';
        } elseif ($unpaid > 0) {
            return 'unpaid';
        }
        return 'pending';
    }

    /**
     * Get payment status for a tenant (fallback for single tenant queries)
     */
    private function getPaymentStatusForTenant($tenantId)
    {
        try {
            $tenant = Tenant::with(['activeTenancy.invoices'])->find($tenantId);
            if (!$tenant || !$tenant->activeTenancy) {
                return 'pending';
            }
            $invoices = $tenant->activeTenancy->invoices;
            $unpaid = $invoices->where('status', 'unpaid')->count();
            $paid = $invoices->where('status', 'paid')->count();
            if ($paid > 0 && $unpaid == 0) {
                return 'paid';
            } elseif ($unpaid > 0) {
                return 'unpaid';
            }
            return 'pending';
        } catch (\Exception $e) {
            return 'pending';
        }
    }

    /**
     * Build tenant variables for placeholder replacement (used in sendCustom)
     */
    private function buildTenantVariables($tenant)
    {
        $activeTenancy = $tenant->activeTenancy;
        $unit = $activeTenancy ? $activeTenancy->unit : null;
        $estate = $unit ? $unit->estate : null;

        $reading = $unit ? WaterReading::where('unit_id', $unit->id)
            ->latest('reading_date')
            ->first() : null;

        $readingDate = $reading ? $reading->reading_date : null;
        $readingMonth = $readingDate ? $readingDate->format('F Y') : Carbon::now()->format('F Y');
        $dueDate = $readingDate ? Carbon::parse($readingDate)->addMonth()->day(5)->format('Y-m-d') : Carbon::now()->addMonth()->day(5)->format('Y-m-d');
        $consumption = $reading ? (float) $reading->consumption : 0;
        if ($consumption == 0 && $reading && (float) $reading->previous_reading > 0 && (float) $reading->current_reading > 0) {
            $consumption = (float) $reading->current_reading - (float) $reading->previous_reading;
        }
        $waterBill = $reading ? (float) $reading->charge : 0;

        $variables = [
            'name' => $tenant->user->name ?? 'Tenant',
            'unit' => $unit ? $unit->unit_number : 'N/A',
            'unit_number' => $unit ? $unit->unit_number : 'N/A',
            'estate_name' => $estate ? $estate->name : 'N/A',
            'estate' => $estate ? $estate->name : 'N/A',
            'water_bill' => $waterBill,
            'water_consumption' => $consumption,
            'prev_read' => $reading ? (float) $reading->previous_reading : 0,
            'curr_read' => $reading ? (float) $reading->current_reading : 0,
            'month' => $readingMonth,
            'reading_month' => $readingMonth,
            'due_date' => $dueDate,
            'payment_status' => $this->getPaymentStatusForTenant($tenant->id),
            'status' => $this->getPaymentStatusForTenant($tenant->id),
        ];

        // Fetch invoices for this tenant (unpaid/partial/overdue)
        $invoices = DB::table('invoices')
            ->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')
            ->where('tenancies.tenant_id', $tenant->id)
            ->whereIn('invoices.status', ['unpaid', 'partial', 'overdue'])
            ->select('invoices.*')
            ->orderBy('invoices.billing_month', 'asc')
            ->get();

        $currentMonthY = Carbon::parse($variables['month'] ?? now()->format('F Y'))->format('Y-m');
        $today = Carbon::today();

        $olderInvoices = $invoices->filter(function($inv) use ($currentMonthY, $today) {
            $billingMonthY = Carbon::parse($inv->billing_month)->format('Y-m');
            if ($billingMonthY >= $currentMonthY) return false;
            $dueDate = Carbon::parse($inv->billing_month)->addMonth()->day(5);
            return $dueDate->lte($today);
        })->values();

        $olderCount = $olderInvoices->count();
        $olderTotal = $olderInvoices->sum('total_amount');

        $currentBill = (int) ($variables['water_bill'] ?? 0);
        $unpaidTotal = $olderTotal;
        $totalDue = $currentBill + $olderTotal;

        $unpaidList = $olderInvoices->map(function($inv) {
            $billingMonth = Carbon::parse($inv->billing_month)->format('F Y');
            $status = $inv->status;
            $prefix = '';
            if ($status !== 'unpaid') {
                $prefix = ucfirst($status) . ' ';
            }
            return $prefix . '(' . $billingMonth . '): KES ' . number_format($inv->total_amount, 2);
        })->implode("\n");

        $unpaidSection = $olderCount > 0 ? "Unpaid:\n" . $unpaidList . "\n" : '';

        $variables['unpaid_count'] = $olderCount;
        $variables['unpaid_total'] = number_format($unpaidTotal, 2);
        $variables['unpaid_list'] = $unpaidList;
        $variables['unpaid_section'] = $unpaidSection;
        $variables['total_due'] = number_format($totalDue, 2);

        return $variables;
    }

    /**
     * Clean and truncate message to max 2 SMS parts (preserves one blank line)
     */
    protected function cleanAndTruncateMessage($message)
    {
        $lines = explode("\n", $message);
        $lines = array_map(function($line) {
            return trim(preg_replace('/[ \t]+/', ' ', $line));
        }, $lines);
        $cleaned = implode("\n", $lines);
        $cleaned = preg_replace("/\n{2,}/", "\n", $cleaned);
        $cleaned = trim($cleaned);

        if (mb_strlen($cleaned) > 300) {
            $cleaned = mb_substr($cleaned, 0, 297) . '...';
        }
        return $cleaned;
    }

    // ============================================
    // SEND BULK SMS (UPDATED – matches JavaScript preview)
    // ============================================
    public function send(Request $request, KenyaSMS $kenyaSms)
    {
        $request->validate([
            'recipients' => 'required|json',
            'message_type' => 'nullable|in:transactional,promotional',
        ]);

        $recipients = json_decode($request->input('recipients'), true);
        $messageType = $request->input('message_type', 'transactional');

        if (empty($recipients)) {
            return back()->with('error', 'No valid recipients selected.');
        }

        // Default template – compact version with {{unpaid_section}}
        $template = $request->input('template');
        if (empty($template)) {
            $template = "{{estate_name}} {{month}} Water Bill - ({{water_consumption}}units (last {{prev_read}}→ new {{curr_read}})
Paybill: 7263733
Acc: {{unit}}
Amount: KES {{water_bill}}
Due: {{due_date}}
Status: {{payment_status}}

{{unpaid_section}}Total Due: KES {{total_due}}
Queries: 0701262902";
        }

        $tenantIds = collect($recipients)->pluck('id')->filter()->unique()->values()->toArray();
        $tenantData = collect();
        if (!empty($tenantIds)) {
            $tenantData = Tenant::whereIn('id', $tenantIds)
                ->with(['activeTenancy.unit.estate'])
                ->get()
                ->keyBy('id');
        }

        // Fetch all unpaid/partial invoices
        $unpaidInvoices = DB::table('invoices')
            ->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')
            ->join('tenants', 'tenants.id', '=', 'tenancies.tenant_id')
            ->whereIn('tenants.id', $tenantIds)
            ->whereIn('invoices.status', ['unpaid', 'partial', 'overdue'])
            ->select(
                'tenants.id as tenant_id',
                'invoices.id as invoice_id',
                'invoices.total_amount as amount',
                'invoices.billing_month',
                'invoices.status'
            )
            ->orderBy('tenants.id')
            ->orderBy('invoices.billing_month', 'asc')
            ->get();

        $transformedInvoices = $unpaidInvoices->map(function ($inv) {
            $dueDate = Carbon::parse($inv->billing_month)->addMonth()->day(5);
            return (object) [
                'tenant_id'    => $inv->tenant_id,
                'invoice_id'   => $inv->invoice_id,
                'amount'       => $inv->amount,
                'status'       => $inv->status,
                'due_date'     => $dueDate->format('Y-m-d'),
                'due_date_fmt' => $dueDate->format('d M Y'),
                'billing_month'=> $inv->billing_month,
            ];
        });

        $groupedInvoices = $transformedInvoices->groupBy('tenant_id');

        $preparedRecipients = [];
        $skippedNoPhone = 0;
        $today = Carbon::today();

        foreach ($recipients as $recipient) {
            // Skip tenants with no phone number
            if (empty($recipient['phone'])) {
                $skippedNoPhone++;
                continue;
            }

            $variables = $recipient['variables'] ?? [];
            $tenantId = $recipient['id'] ?? null;
            $paymentStatus = $recipient['payment_status'] ?? 'pending';

            // If tenant found, fetch their data
            if ($tenantId && $tenantData->has($tenantId)) {
                $tenant = $tenantData->get($tenantId);
                $activeTenancy = $tenant->activeTenancy;
                $unit = $activeTenancy ? $activeTenancy->unit : null;
                $unitNumber = $unit ? $unit->unit_number : '';
                $estateName = $unit && $unit->estate ? $unit->estate->name : '';
                
                $reading = $unit ? WaterReading::where('unit_id', $unit->id)
                    ->latest('reading_date')
                    ->first() : null;
                $readingDate = $reading ? $reading->reading_date : null;
                $readingMonth = $readingDate ? $readingDate->format('F Y') : Carbon::now()->format('F Y');
                $dueDate = $readingDate ? Carbon::parse($readingDate)->addMonth()->day(5)->format('Y-m-d') : Carbon::now()->addMonth()->day(5)->format('Y-m-d');
                $consumption = $reading ? (float) $reading->consumption : 0;
                if ($consumption == 0 && $reading && (float) $reading->previous_reading > 0 && (float) $reading->current_reading > 0) {
                    $consumption = (float) $reading->current_reading - (float) $reading->previous_reading;
                }
                $waterBill = $reading ? (float) $reading->charge : 0;

                // Get actual payment status (including paid)
                $paymentStatus = $this->getPaymentStatusForTenant($tenantId);

                $variables['unit'] = $unitNumber;
                $variables['unit_number'] = $unitNumber;
                $variables['estate_name'] = $estateName;
                $variables['name'] = $variables['name'] ?? $tenant->user->name ?? 'Tenant';
                $variables['water_bill'] = (int) ($variables['water_bill'] ?? $waterBill);
                $variables['water_consumption'] = (int) ($variables['water_consumption'] ?? $consumption);
                $variables['prev_read'] = (int) ($variables['prev_read'] ?? ($reading ? (float) $reading->previous_reading : 0));
                $variables['curr_read'] = (int) ($variables['curr_read'] ?? ($reading ? (float) $reading->current_reading : 0));
                $variables['month'] = $readingMonth;
                $variables['reading_month'] = $readingMonth;
                $variables['due_date'] = $dueDate;
                $variables['payment_status'] = $paymentStatus;
                $variables['status'] = $paymentStatus;
            }

            // Fetch invoices for this tenant
            $invoices = $tenantId ? ($groupedInvoices->get($tenantId) ?? collect([])) : collect([]);

            // Determine current month
            $currentMonthY = '';
            if ($invoices->isNotEmpty()) {
                $latest = $invoices->sortByDesc('billing_month')->first();
                if ($latest && $latest->billing_month) {
                    $currentMonthY = Carbon::parse($latest->billing_month)->format('Y-m');
                }
            }
            if (empty($currentMonthY)) {
                $currentMonthY = Carbon::parse($variables['month'] ?? now()->format('F Y'))->format('Y-m');
            }

            // Filter older invoices: billing_month < currentMonthY AND due_date <= today
            $olderInvoices = $invoices->filter(function($inv) use ($currentMonthY, $today) {
                $billingMonthY = Carbon::parse($inv->billing_month)->format('Y-m');
                if ($billingMonthY >= $currentMonthY) return false;
                $dueDate = Carbon::parse($inv->due_date);
                return $dueDate->lte($today);
            })->values();

            $olderCount = $olderInvoices->count();
            $olderTotal = $olderInvoices->sum('amount');

            // Current month's bill
            $currentBill = (int) ($variables['water_bill'] ?? 0);

            // If tenant is PAID, zero everything
            if (strtolower($paymentStatus) === 'paid') {
                $currentBill = 0;
                $olderTotal = 0;
            }

            $unpaidTotal = $olderTotal;
            $totalDue = $currentBill + $unpaidTotal;

            // Build unpaid list with status prefix only if not 'unpaid'
            $unpaidList = $olderInvoices->map(function($inv) {
                $billingMonth = Carbon::parse($inv->billing_month)->format('F Y');
                $status = $inv->status;
                $prefix = '';
                if ($status !== 'unpaid') {
                    $prefix = ucfirst($status) . ' ';
                }
                return $prefix . '(' . $billingMonth . '): KES ' . number_format($inv->amount, 2);
            })->implode("\n");

            // Build unpaid section (only if there are older invoices, ends with newline)
            $unpaidSection = $olderCount > 0 ? "Unpaid:\n" . $unpaidList . "\n" : '';

            $variables['unpaid_count'] = $olderCount;
            $variables['unpaid_total'] = number_format($unpaidTotal, 2);
            $variables['unpaid_list'] = $unpaidList;
            $variables['unpaid_section'] = $unpaidSection;
            $variables['total_due'] = number_format($totalDue, 2);
            $variables['payment_status'] = $paymentStatus;
            $variables['status'] = $paymentStatus;

            // Fallback defaults
            if (!isset($variables['name']) || empty($variables['name'])) {
                $variables['name'] = $recipient['name'] ?? 'Tenant';
            }
            if (!isset($variables['water_bill']) || empty($variables['water_bill'])) {
                $variables['water_bill'] = (int) ($recipient['water_bill'] ?? 0);
            }
            if (!isset($variables['unit']) || empty($variables['unit'])) {
                $variables['unit'] = $recipient['unit'] ?? 'N/A';
            }
            if (!isset($variables['estate_name']) || empty($variables['estate_name'])) {
                $variables['estate_name'] = $recipient['estate'] ?? 'N/A';
            }

            // Replace placeholders
            $message = $template;
            foreach ($variables as $key => $value) {
                if ($value !== null) {
                    $message = str_replace('{{' . $key . '}}', $value, $message);
                }
            }

            // Remove any remaining unreplaced placeholders
            $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);

            // Clean and truncate
            $message = $this->cleanAndTruncateMessage($message);

            $preparedRecipients[] = [
                'phone' => $recipient['phone'],
                'message' => $message,
                'variables' => $variables,
                'id' => $recipient['id'] ?? null,
            ];
        }

        if ($skippedNoPhone > 0) {
            \Log::info("Skipped {$skippedNoPhone} tenants with no phone number");
        }

        if (empty($preparedRecipients)) {
            return back()->with('error', 'No valid recipients to send to.');
        }

        // Create campaign
        $campaign = SmsCampaign::create([
            'name' => 'Campaign ' . now()->format('Y-m-d H:i:s'),
            'template_id' => null,
            'total_recipients' => count($preparedRecipients),
            'status' => 'sending',
            'created_by' => auth()->id(),
        ]);

        foreach ($preparedRecipients as &$recipient) {
            $recipient['campaign_id'] = $campaign->id;
        }

        $response = $kenyaSms->sendPersonalized($template, $preparedRecipients, $messageType, $campaign->id);

        $campaign->update([
            'sent_count' => $response['data']['sent'] ?? 0,
            'failed_count' => $response['data']['failed'] ?? 0,
            'status' => 'completed',
        ]);

        if ($response['success']) {
            $successMsg = "SMS campaign sent successfully! Sent: {$response['data']['sent']}, Failed: {$response['data']['failed']}";
            return redirect()->route('sms.broadcast')->with('success', $successMsg);
        } else {
            return redirect()->route('sms.broadcast')
                ->with('error', 'Failed to send SMS: ' . ($response['error'] ?? 'Unknown error'));
        }
    }

    // ============================================
    // SEND CUSTOM SMS (WITH PLACEHOLDER SUPPORT)
    // ============================================
    public function sendCustom(Request $request, KenyaSMS $kenyaSms)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:1600',
            'message_type' => 'nullable|in:transactional,promotional',
        ]);

        $phone = PhoneHelper::clean($request->phone);
        if (!$phone || !preg_match('/^254[0-9]{9}$/', $phone)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'Invalid Kenyan phone number.']);
            }
            return back()->with('error', 'Invalid Kenyan phone number. Please use format like 0712345678 or 254712345678.');
        }

        // Find tenant by phone number (last 9 digits)
        $tenant = Tenant::whereHas('user', function($q) use ($phone) {
            $q->where('phone', 'like', '%' . substr($phone, -9));
        })->with(['user', 'activeTenancy.unit.estate'])->first();

        $message = $request->message;

        // If tenant found, replace placeholders
        if ($tenant) {
            $variables = $this->buildTenantVariables($tenant);
            foreach ($variables as $key => $value) {
                if ($value !== null) {
                    $message = str_replace('{{' . $key . '}}', $value, $message);
                }
            }
            $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);
            $message = $this->cleanAndTruncateMessage($message);
        }

        $response = $kenyaSms->sendOne($phone, $message, $request->message_type);

        if ($request->expectsJson()) {
            return response()->json($response);
        }

        if ($response['success']) {
            return redirect()->route('sms.broadcast')
                ->with('success', "SMS sent successfully to {$phone}");
        } else {
            return redirect()->route('sms.broadcast')
                ->with('error', 'Failed to send SMS: ' . ($response['error'] ?? 'Unknown error'));
        }
    }

    // ============================================
    // SMS LOGS
    // ============================================
    public function logs(Request $request)
    {
        $query = SmsLog::orderBy('created_at', 'desc');
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('status') && in_array($request->status, ['pending', 'sent', 'failed', 'delivered'])) {
            $query->where('status', $request->status);
        }
        if ($request->filled('phone')) {
            $query->where('recipient_phone', 'like', '%' . $request->phone . '%');
        }
        $logs = $query->paginate(50)->withQueryString();
        return view('sms.logs', compact('logs'));
    }

    // ============================================
    // EXPORT SMS LOGS
    // ============================================
    public function export(Request $request)
    {
        $query = SmsLog::orderBy('created_at', 'desc');
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('status') && in_array($request->status, ['pending', 'sent', 'failed', 'delivered'])) {
            $query->where('status', $request->status);
        }
        if ($request->filled('phone')) {
            $query->where('recipient_phone', 'like', '%' . $request->phone . '%');
        }
        $logs = $query->get();

        $filename = 'sms_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://output', 'w');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        fputcsv($handle, ['ID', 'Phone', 'Message', 'Status', 'Provider ID', 'Failure Reason', 'Sent At', 'Campaign ID']);
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->recipient_phone,
                $log->message,
                $log->status,
                $log->provider_message_id,
                $log->failure_reason,
                $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
                $log->campaign_id,
            ]);
        }
        fclose($handle);
        exit;
    }

    // ============================================
    // SMS SETTINGS
    // ============================================
    public function settings(KenyaSMS $kenyaSms)
    {
        $sandbox = config('sms.kenyasms.sandbox', true);
        $senderId = config('sms.kenyasms.sender_id', 'DANAFFKENYA');
        $defaultType = config('sms.kenyasms.default_type', 'transactional');
        $apiKeyConfigured = !empty(config('sms.kenyasms.api_key'));
        $balanceInfo = ['success' => false, 'balance' => null, 'error' => null];
        if ($apiKeyConfigured) {
            $balanceInfo = $kenyaSms->getBalance();
        }
        return view('sms.settings', compact('sandbox', 'senderId', 'defaultType', 'apiKeyConfigured', 'balanceInfo'));
    }

    // ============================================
    // UPDATE SMS SETTINGS
    // ============================================
    public function updateSettings(Request $request)
    {
        $request->validate([
            'sandbox' => 'required|boolean',
        ]);
        return redirect()->route('sms.settings')
            ->with('info', 'To change sandbox mode, edit your .env file: set KENYASMS_SANDBOX=true or false and restart the server.');
    }

    // ============================================
    // SHOW CAMPAIGN
    // ============================================
    public function showCampaign($id)
    {
        $campaign = SmsCampaign::with('logs', 'creator')->findOrFail($id);
        return view('sms.campaigns.show', compact('campaign'));
    }

    // ============================================
    // RESEND FAILED
    // ============================================
    public function resendFailed(Request $request, $campaignId, KenyaSMS $kenyaSms)
    {
        $campaign = SmsCampaign::with('logs')->findOrFail($campaignId);
        $failedLogs = $campaign->logs()->where('status', 'failed')->get();
        if ($failedLogs->isEmpty()) {
            return redirect()->route('sms.campaigns.show', $campaign->id)
                ->with('error', 'No failed messages to resend.');
        }
        $sent = 0;
        $failed = 0;
        foreach ($failedLogs as $log) {
            $result = $kenyaSms->sendOne($log->recipient_phone, $log->message, null, $campaign->id);
            if ($result['success']) {
                $sent++;
                $log->update(['status' => 'sent']);
            } else {
                $failed++;
            }
        }
        $campaign->sent_count += $sent;
        $campaign->failed_count = $campaign->failed_count - $sent;
        $campaign->save();
        return redirect()->route('sms.campaigns.show', $campaign->id)
            ->with('success', "Resent {$sent} messages successfully. {$failed} still failed.");
    }

    // ============================================
    // GET TENANT PAYMENT STATUS
    // ============================================
    public function getTenantPaymentStatus($tenantId)
    {
        $tenant = Tenant::with(['activeTenancy.invoices'])->findOrFail($tenantId);
        $status = 'pending';
        if ($tenant->activeTenancy) {
            $invoices = $tenant->activeTenancy->invoices;
            $unpaid = $invoices->where('status', 'unpaid')->count();
            $paid = $invoices->where('status', 'paid')->count();
            if ($paid > 0 && $unpaid == 0) {
                $status = 'paid';
            } elseif ($unpaid > 0) {
                $status = 'overdue';
            }
        }
        return response()->json(['status' => $status]);
    }
}