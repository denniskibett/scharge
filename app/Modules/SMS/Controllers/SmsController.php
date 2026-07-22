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

class SmsController extends Controller
{
    /**
     * Display the SMS broadcast page
     */
    public function create()
    {
        // ============================================
        // GET ALL TENANTS WITH THEIR DATA
        // ============================================
        $tenants = Tenant::with(['user', 'activeTenancy.unit.estate'])
            ->get()
            ->map(function ($tenant) {
                $tenancy = $tenant->activeTenancy;
                $unit = $tenancy ? $tenancy->unit : null;
                
                $latestWaterReading = $unit ? WaterReading::where('unit_id', $unit->id)
                    ->latest('reading_date')
                    ->first() : null;
                
                $waterBill = $latestWaterReading ? (float) $latestWaterReading->charge : 0;
                $waterConsumption = $latestWaterReading ? (float) $latestWaterReading->consumption : 0;
                $prevRead = $latestWaterReading ? (float) $latestWaterReading->previous_reading : 0;
                $currRead = $latestWaterReading ? (float) $latestWaterReading->current_reading : 0;
                $readingDate = $latestWaterReading ? $latestWaterReading->reading_date : null;
                
                $securityFee = 500;
                $garbageFee = 300;
                $total = $waterBill + $securityFee + $garbageFee;

                $rawPhone = $tenant->user->phone ?? null;
                $phone = $rawPhone ? PhoneHelper::clean($rawPhone) : null;
                $isKenyan = $phone && preg_match('/^254[0-9]{9}$/', $phone);
                $unitNumber = $unit ? $unit->unit_number : '';
                $estateName = $unit && $unit->estate ? $unit->estate->name : 'N/A';

                $readingMonth = $readingDate ? $readingDate->format('F Y') : Carbon::now()->format('F Y');
                $baseDate = $readingDate ? Carbon::parse($readingDate) : Carbon::now();
                $dueDate = $baseDate->copy()->addMonth()->day(5)->format('Y-m-d');

                $paymentStatus = $this->getPaymentStatusForTenant($tenant->id);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->user->name ?? 'N/A',
                    'phone' => $phone,
                    'is_kenyan' => $isKenyan,
                    'unit_number' => $unitNumber,
                    'unit' => $unitNumber,
                    'estate_id' => $unit->estate_id ?? null,
                    'estate_name' => $estateName,
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
            ->filter(function ($tenant) {
                return !empty($tenant['phone']) && $tenant['is_kenyan'];
            })
            ->values();

        // ============================================
        // INTERNATIONAL COUNT
        // ============================================
        $internationalCount = Tenant::with('user')
            ->get()
            ->filter(function ($tenant) {
                $rawPhone = $tenant->user->phone ?? null;
                $phone = $rawPhone ? PhoneHelper::clean($rawPhone) : null;
                return $phone && !preg_match('/^254[0-9]{9}$/', $phone);
            })->count();

        // ============================================
        // ALL DATA FOR TABS
        // ============================================
        $estates = Estate::orderBy('name')->get();
        $companies = Company::orderBy('name')->get();
        $templates = SmsTemplate::orderBy('name')->get();
        $logs = SmsLog::orderBy('created_at', 'desc')->paginate(20);
        $campaigns = SmsCampaign::with('creator')->latest()->paginate(20);
        $sandbox = config('sms.kenyasms.sandbox', true);

        // ============================================
        // RETURN VIEW WITH ALL DATA
        // ============================================
        return view('sms.broadcast', [
            'tenants' => $tenants,
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

        $preparedRecipients = [];

        foreach ($recipients as $recipient) {
            if (empty($recipient['phone'])) {
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
                
                $variables['unit'] = $unitNumber;
                $variables['unit_number'] = $unitNumber;
                $variables['estate_name'] = $estateName;
                $variables['name'] = $variables['name'] ?? $tenant->user->name ?? 'Tenant';
                $variables['water_bill'] = $variables['water_bill'] ?? ($reading ? (float) $reading->charge : 0);
                $variables['water_consumption'] = $variables['water_consumption'] ?? ($reading ? (float) $reading->consumption : 0);
                $variables['prev_read'] = $variables['prev_read'] ?? ($reading ? (float) $reading->previous_reading : 0);
                $variables['curr_read'] = $variables['curr_read'] ?? ($reading ? (float) $reading->current_reading : 0);
                $variables['month'] = $readingMonth;
                $variables['reading_month'] = $readingMonth;
                $variables['due_date'] = $dueDate;
                
                $paymentStatus = $this->getPaymentStatusForTenant($recipient['id']);
                $variables['payment_status'] = $paymentStatus;
                $variables['status'] = $paymentStatus;
            }

            if (!isset($variables['name']) || empty($variables['name'])) {
                $variables['name'] = $recipient['name'] ?? 'Tenant';
            }
            if (!isset($variables['water_bill']) || empty($variables['water_bill'])) {
                $variables['water_bill'] = $recipient['water_bill'] ?? '0.00';
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

            $preparedRecipients[] = [
                'phone' => $recipient['phone'],
                'message' => $message,
                'variables' => $variables,
                'id' => $recipient['id'] ?? null,
            ];
        }

        if (empty($preparedRecipients)) {
            return back()->with('error', 'No valid recipients with phone numbers found.');
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
            return redirect()->route('sms.broadcast')
                ->with('success', "SMS campaign sent successfully! Sent: {$response['data']['sent']}, Failed: {$response['data']['failed']}");
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