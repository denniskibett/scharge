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
     * Display the SMS broadcast page
     */
    public function create()
    {
        // ============================================
        // Get tenants with active tenancies and phone numbers
        // ============================================
        $tenants = Tenant::with(['user', 'activeTenancy.unit.estate'])
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

            // Clean phone: remove all non-digits, then ensure it starts with 254
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

            $reading = $unit ? WaterReading::where('unit_id', $unit->id)
                        ->latest('reading_date')
                        ->first() : null;

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

            $paymentStatus = $this->getPaymentStatusForTenant($tenant->id);

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

        // ============================================
        // If still empty, show a debug message
        // ============================================
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

        // ============================================
        // Other data for the view
        // ============================================
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
     * Get payment status from invoices for a tenant
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

    // ============================================
    // Clean and truncate message – preserves line breaks
    // ============================================
 protected function cleanAndTruncateMessage($message)
{
    // Split lines, clean each line, rejoin
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
    // SEND BULK SMS
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

        $template = $request->input('template');
        if (empty($template)) {
            $template = "{{estate_name}} {{month}} Water Bill - ({{water_consumption}} units (Last: {{prev_read}}-New: {{curr_read}}))\n\nPaybill: 7263733\nAcc: {{unit}}\nAmount: KES {{water_bill}}\nDue: {{due_date}}\nStatus: {{status}}\n\nFor queries: 0701262902";
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
        $skippedPaid = 0;
        $skippedNoPhone = 0;
        $today = Carbon::today();

        foreach ($recipients as $recipient) {
            if (empty($recipient['phone'])) {
                $skippedNoPhone++;
                continue;
            }

            $paymentStatus = $recipient['payment_status'] ?? 'pending';
            if (strtolower($paymentStatus) === 'paid') {
                $skippedPaid++;
                continue;
            }

            $variables = $recipient['variables'] ?? [];
            if (isset($recipient['id']) && $tenantData->has($recipient['id'])) {
                $tenant = $tenantData->get($recipient['id']);
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
                $paymentStatus = $this->getPaymentStatusForTenant($recipient['id']);
                $variables['payment_status'] = $paymentStatus;
                $variables['status'] = $paymentStatus;
            }

            // Get invoices for this tenant
            $tenantId = $recipient['id'] ?? null;
            $invoices = $tenantId ? ($groupedInvoices->get($tenantId) ?? collect([])) : collect([]);

            // Exclude current month's invoice
            $currentMonth = Carbon::parse($variables['month'] ?? now()->format('F Y'))->format('Y-m');
            $invoices = $invoices->filter(function($inv) use ($currentMonth) {
                return Carbon::parse($inv->billing_month)->format('Y-m') !== $currentMonth;
            })->values();

            // Filter out invoices whose due date is in the future
            $invoices = $invoices->filter(function($inv) use ($today) {
                return Carbon::parse($inv->due_date)->lte($today);
            })->values();

            $unpaidCount = $invoices->count();
            $unpaidTotal = $invoices->sum('amount');
            $unpaidList = $invoices->map(function($inv) {
                return $inv->status . ' (' . $inv->due_date_fmt . '): KES ' . number_format($inv->amount, 2);
            })->implode("\n");

            $unpaidMessage = $unpaidCount === 0
                ? 'no pending invoices'
                : ($unpaidCount === 1
                    ? '1 unpaid invoice of KES ' . number_format($unpaidTotal, 2)
                    : $unpaidCount . ' unpaid invoices totaling KES ' . number_format($unpaidTotal, 2)
                );

            $variables['unpaid_count'] = $unpaidCount;
            $variables['unpaid_total'] = number_format($unpaidTotal, 2);
            $variables['unpaid_list'] = $unpaidList;
            $variables['unpaid_message'] = $unpaidMessage;

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
            if (!isset($variables['payment_status']) || empty($variables['payment_status'])) {
                $variables['payment_status'] = 'pending';
            }
            $variables['status'] = $variables['payment_status'];

            $message = $template;
            foreach ($variables as $key => $value) {
                if ($value !== null) {
                    $message = str_replace('{{' . $key . '}}', $value, $message);
                }
            }
            $message = preg_replace('/\b(\d+)\.00\b/', '$1', $message);
            $message = preg_replace('/\b(\d+),(\d+)\.00\b/', '$1,$2', $message);
            $message = str_replace('  ', ' ', $message);
            $message = str_replace('KES KES', 'KES', $message);

            // Clean and truncate to max 2 SMS parts
            $message = $this->cleanAndTruncateMessage($message);

            $preparedRecipients[] = [
                'phone' => $recipient['phone'],
                'message' => $message,
                'variables' => $variables,
                'id' => $recipient['id'] ?? null,
            ];
        }

        if ($skippedPaid > 0) {
            \Log::info("Skipped {$skippedPaid} tenants with 'paid' status");
        }
        if ($skippedNoPhone > 0) {
            \Log::info("Skipped {$skippedNoPhone} tenants with no phone number");
        }

        if (empty($preparedRecipients)) {
            $message = 'No valid recipients to send to.';
            if ($skippedPaid > 0) {
                $message .= " Skipped {$skippedPaid} tenants with 'paid' status.";
            }
            return back()->with('error', $message);
        }

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
            if ($skippedPaid > 0) {
                $successMsg .= " (Skipped {$skippedPaid} paid tenants)";
            }
            return redirect()->route('sms.broadcast')
                ->with('success', $successMsg);
        } else {
            return redirect()->route('sms.broadcast')
                ->with('error', 'Failed to send SMS: ' . ($response['error'] ?? 'Unknown error'));
        }
    }

    // ============================================
    // SEND CUSTOM SMS
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

        $response = $kenyaSms->sendOne($phone, $request->message, $request->message_type);

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
        $senderId = config('sms.kenyasms.sender_id', 'SHARETENT');
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