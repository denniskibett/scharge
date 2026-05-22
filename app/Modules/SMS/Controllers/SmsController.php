<?php

namespace App\Modules\SMS\Controllers;

use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\WaterReading;
use App\Services\KenyaSMS;
use App\Models\SmsLog;
use Carbon\Carbon;

class SmsController extends Controller
{
    /**
     * Show the SMS broadcast form
     */
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

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->user->name ?? 'N/A',
                    'phone' => $tenant->user->phone ?? null,
                    'unit_number' => $unit->unit_number ?? 'N/A',
                    'estate_id' => $unit->estate_id ?? null,
                    'estate_name' => $unit->estate->name ?? 'N/A',
                    'water_bill' => $latestWaterReading ? (float) $latestWaterReading->charge : 0,
                    'water_consumption' => $latestWaterReading ? (float) $latestWaterReading->consumption : 0,
                ];
            })
            ->filter(function ($tenant) {
                return !empty($tenant['phone']);
            })
            ->values();

        $estates = \App\Models\Estate::orderBy('name')->get();
        $sandbox = config('sms.kenyasms.sandbox', true);

        return view('sms.broadcast', compact('tenants', 'estates', 'sandbox'));
    }

    /**
     * Send personalized SMS to selected tenants
     */
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

    /**
     * Send SMS to a single custom phone number
     */
    public function sendCustom(Request $request, KenyaSMS $kenyaSms)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:1600',
            'message_type' => 'nullable|in:transactional,promotional',
        ]);

        $phone = \App\Helpers\PhoneHelper::clean($request->phone);
        if (!$phone) {
            return back()->with('error', 'Invalid Kenyan phone number. Please use format like 0712345678 or 254712345678.');
        }

        $response = $kenyaSms->sendOne($phone, $request->message, $request->message_type);

        if ($response['success']) {
            return redirect()->route('sms.broadcast')
                ->with('success', "SMS sent successfully to {$phone}");
        } else {
            return redirect()->route('sms.broadcast')
                ->with('error', 'Failed to send SMS: ' . ($response['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Show SMS logs with filters
     */
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
}