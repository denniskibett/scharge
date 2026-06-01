<?php

namespace App\Modules\SMS\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Water\Models\WaterReading;
use App\Modules\SMS\Services\KenyaSMS;
use App\Modules\SMS\Models\SmsLog;
use App\Modules\SMS\Models\SmsTemplate;
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

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->user->name ?? 'N/A',
                    'phone' => $tenant->user->phone ?? null,
                    'unit_number' => $unit->unit_number ?? 'N/A',
                    'estate_id' => $unit->estate_id ?? null,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'water_bill' => $waterBill,
                    'water_consumption' => $latestWaterReading ? (float) $latestWaterReading->consumption : 0,
                    'security_fee' => $securityFee,
                    'garbage_fee' => $garbageFee,
                    'total' => $total,
                ];
            })
            ->filter(function ($tenant) {
                return !empty($tenant['phone']);
            })
            ->values();

        $estates = Estate::orderBy('name')->get();
        $sandbox = config('sms.kenyasms.sandbox', true);
        $templates = SmsTemplate::orderBy('name')->get();

        // ADD THIS LINE – fetch SMS logs for the history tab
        $logs = SmsLog::orderBy('created_at', 'desc')->paginate(20);

        // ADD 'logs' TO THE compact() array
        return view('sms.broadcast', compact('tenants', 'estates', 'sandbox', 'templates', 'logs'));
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

        $response = $kenyaSms->sendPersonalized($template, $recipients, $messageType);

        if ($response['success']) {
            $campaignId = $response['data']['campaign_id'] ?? null;
            return redirect()->route('sms.broadcast')
                ->with('success', "SMS campaign sent successfully! Campaign ID: {$campaignId}");
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
        if (!$phone) {
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

        fputcsv($handle, ['ID', 'Phone', 'Message', 'Status', 'Provider ID', 'Failure Reason', 'Sent At']);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->id,
                $log->recipient_phone,
                $log->message,
                $log->status,
                $log->provider_message_id,
                $log->failure_reason,
                $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
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
}