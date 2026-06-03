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
use App\Modules\SMS\Helpers\PhoneHelper;
use Carbon\Carbon;

class SmsController extends Controller
{
    public function create()
    {
        $tenants = Tenant::with(['user', 'activeTenancy.unit.estate'])
            ->get()
            ->map(function ($tenant) {
                $tenancy = $tenant->activeTenancy;
                $unit = $tenancy ? $tenancy->unit : null;
                $latestWaterReading = $unit ? WaterReading::where('unit_id', $unit->id)
                    ->latest('reading_date')
                    ->first() : null;
                
                $waterBill = $latestWaterReading ? (float) $latestWaterReading->charge : 0;
                $securityFee = 500;
                $garbageFee = 300;
                $total = $waterBill + $securityFee + $garbageFee;

                $rawPhone = $tenant->user->phone ?? null;
                $phone = $rawPhone ? PhoneHelper::clean($rawPhone) : null;
                $isKenyan = $phone && preg_match('/^254[0-9]{9}$/', $phone);
                $unitNumber = $unit->unit_number ?? '';

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->user->name ?? 'N/A',
                    'phone' => $phone,
                    'is_kenyan' => $isKenyan,
                    'unit_number' => $unitNumber,
                    'unit' => $unitNumber,
                    'estate_id' => $unit->estate_id ?? null,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'water_bill' => $waterBill,
                    'water_consumption' => $latestWaterReading ? (float) $latestWaterReading->consumption : 0,
                    'prev_read' => $latestWaterReading ? (float) $latestWaterReading->previous_reading : 0,
                    'curr_read' => $latestWaterReading ? (float) $latestWaterReading->current_reading : 0,
                    'security_fee' => $securityFee,
                    'garbage_fee' => $garbageFee,
                    'total' => $total,
                ];
            })
            ->filter(function ($tenant) {
                return !empty($tenant['phone']) && $tenant['is_kenyan'];
            })
            ->values();

        $internationalCount = Tenant::with('user')
            ->get()
            ->filter(function ($tenant) {
                $rawPhone = $tenant->user->phone ?? null;
                $phone = $rawPhone ? PhoneHelper::clean($rawPhone) : null;
                return $phone && !preg_match('/^254[0-9]{9}$/', $phone);
            })->count();

        $estates = Estate::orderBy('name')->get();
        $sandbox = config('sms.kenyasms.sandbox', true);
        $templates = SmsTemplate::orderBy('name')->get();
        $logs = SmsLog::orderBy('created_at', 'desc')->paginate(20);
        $campaigns = SmsCampaign::with('creator')->latest()->paginate(20);

        return view('sms.broadcast', compact('tenants', 'estates', 'sandbox', 'templates', 'logs', 'internationalCount', 'campaigns'));
    }

    public function send(Request $request, KenyaSMS $kenyaSms)
    {
        $request->validate([
            'recipients' => 'required|json',
            'template' => 'required|string|max:1600',
            'message_type' => 'nullable|in:transactional,promotional',
        ]);

        $recipients = json_decode($request->input('recipients'), true);
        $template = $request->input('template');
        $messageType = $request->input('message_type', 'transactional');

        if (empty($recipients)) {
            return back()->with('error', 'No valid recipients selected.');
        }

        $tenantIds = collect($recipients)->pluck('id')->filter()->unique();
        $tenantData = collect();
        if ($tenantIds->isNotEmpty()) {
            $tenantData = Tenant::whereIn('id', $tenantIds)
                ->with('activeTenancy.unit')
                ->get()
                ->keyBy('id');
        }

        // Default due date: 5th of current month
        $defaultDueDate = Carbon::now()->setDay(5)->format('Y-m-d');
        $defaultBillingMonth = Carbon::now()->subMonth()->format('F Y');

        foreach ($recipients as &$recipient) {
            if (isset($recipient['id']) && $tenantData->has($recipient['id'])) {
                $tenant = $tenantData->get($recipient['id']);
                $unitNumber = optional($tenant->activeTenancy->unit)->unit_number ?? '';
                $recipient['variables']['unit'] = $unitNumber;
            }

            if (!isset($recipient['variables']['due_date']) || empty($recipient['variables']['due_date'])) {
                $recipient['variables']['due_date'] = $defaultDueDate;
            }
            if (!isset($recipient['variables']['month']) || empty($recipient['variables']['month'])) {
                $recipient['variables']['month'] = $defaultBillingMonth;
            }
        }

        // Create campaign
        $campaign = SmsCampaign::create([
            'name' => 'Campaign ' . now()->format('Y-m-d H:i:s'),
            'template_id' => null,
            'total_recipients' => count($recipients),
            'status' => 'sending',
            'created_by' => auth()->id(),
        ]);

        foreach ($recipients as &$recipient) {
            $recipient['campaign_id'] = $campaign->id;
        }

        $response = $kenyaSms->sendPersonalized($template, $recipients, $messageType, $campaign->id);

        $campaign->update([
            'sent_count' => $response['data']['sent'] ?? 0,
            'failed_count' => $response['data']['failed'] ?? 0,
            'status' => 'completed',
        ]);

        if ($response['success']) {
            return redirect()->route('sms.broadcast')
                ->with('success', "SMS campaign sent successfully! Campaign ID: {$campaign->id}");
        } else {
            return redirect()->route('sms.broadcast')
                ->with('error', 'Failed to send SMS: ' . ($response['error'] ?? 'Unknown error'));
        }
    }

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

    public function updateSettings(Request $request)
    {
        $request->validate([
            'sandbox' => 'required|boolean',
        ]);

        return redirect()->route('sms.settings')
            ->with('info', 'To change sandbox mode, edit your .env file: set KENYASMS_SANDBOX=true or false and restart the server.');
    }

    public function showCampaign($id)
    {
        $campaign = SmsCampaign::with('logs', 'creator')->findOrFail($id);
        return view('sms.campaigns.show', compact('campaign'));
    }

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
}