<?php

namespace App\Modules\SMS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SMS\Models\Campaign;
use App\Modules\SMS\Models\CampaignRecipient;
use App\Modules\SMS\Services\CampaignService;
use App\Modules\SMS\Services\KenyaSMSService;
use App\Modules\Properties\Models\Unit;
use App\Models\Estate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    protected $campaignService;
    protected $smsService;

    /**
     * Constructor
     */
    public function __construct(CampaignService $campaignService, KenyaSMSService $smsService)
    {
        $this->campaignService = $campaignService;
        $this->smsService = $smsService;
    }

    /**
     * Display a listing of campaigns
     */
    public function index(Request $request)
    {
        $query = Campaign::with(['estate', 'createdBy']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('estate_id')) {
            $query->where('estate_id', $request->estate_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('billing_month', 'LIKE', "%{$search}%");
            });
        }

        $campaigns = $query->orderBy('created_at', 'desc')->paginate(20);
        $estates = Estate::all();

        return view('sms.campaigns.index', compact('campaigns', 'estates'));
    }

    /**
     * Show form to create a new campaign
     */
    public function create()
    {
        $estates = Estate::all();
        
        // Get templates from database
        $templates = \App\Modules\SMS\Models\SmsTemplate::all();
        
        // If no templates exist in database, create default ones
        if ($templates->isEmpty()) {
            $templates = collect([
                (object) [
                    'id' => 1, 
                    'name' => 'Water Bill Reminder', 
                    'content' => "Dear {{unit}},\n\nYour {{month}} water bill of KES {{water_bill}} is due on {{due_date}}.\nPaybill: 7263733\nAccount: {{unit}}\n\nThank you."
                ],
                (object) [
                    'id' => 2, 
                    'name' => 'Water Bill Overdue', 
                    'content' => "URGENT: Your {{month}} water bill of KES {{water_bill}} is OVERDUE.\nPaybill: 7263733\nAccount: {{unit}}\nPlease pay immediately to avoid disconnection."
                ],
                (object) [
                    'id' => 3, 
                    'name' => 'Water Consumption Summary', 
                    'content' => "{{estate_name}} - {{month}} Water Bill\nUnit: {{unit}}\nConsumption: {{water_consumption}} units\nAmount: KES {{water_bill}}\nDue: {{due_date}}\nPaybill: 7263733 Acc: {{unit}}"
                ],
            ]);
        }
        
        return view('sms.campaigns.create', compact('estates', 'templates'));
    }

    /**
     * Store a newly created campaign
     */
    public function store(Request $request)
    {
        // Debug: Log the request data
        Log::info('STORE REQUEST DATA', $request->all());
        
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'estate_id' => 'required|exists:estates,id',
                'billing_month' => 'required|string|max:50',
                'message' => 'required|string',
                'sender_id' => 'nullable|string|max:11',
                'message_type' => 'nullable|string|in:transactional,promotional',
                'scheduled_at' => 'nullable|date|after:now',
                'filters' => 'nullable|array',
                'filters.payment_status' => 'nullable|string|in:unpaid,paid,pending',
                'filters.min_bill_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                Log::warning('VALIDATION FAILED', $validator->errors()->all());
                return redirect()->back()->withErrors($validator)->withInput();
            }

            Log::info('VALIDATION PASSED');

            // Get recipients based on filters
            $recipients = $this->getRecipients($request->estate_id, $request->filters ?? []);
            
            Log::info('RECIPIENTS FOUND', ['count' => count($recipients)]);
            
            if (empty($recipients)) {
                Log::warning('NO RECIPIENTS FOUND');
                return redirect()->back()
                    ->with('error', 'No recipients found for this campaign. Please check your filters.')
                    ->withInput();
            }

            DB::beginTransaction();

            // Create campaign
            $campaign = Campaign::create([
                'name' => $request->name,
                'estate_id' => $request->estate_id,
                'billing_month' => $request->billing_month,
                'message' => $request->message,
                'sender_id' => $request->sender_id ?? config('sms.sender_id', 'SHARETENT'),
                'message_type' => $request->message_type ?? 'transactional',
                'scheduled_at' => $request->scheduled_at,
                'filters' => $request->filters,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'cost_per_sms' => config('sms.cost_per_sms', 0.60),
            ]);

            Log::info('CAMPAIGN CREATED', ['id' => $campaign->id, 'name' => $campaign->name]);

            // Create recipient records
            $recipientCount = 0;
            foreach ($recipients as $recipient) {
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $recipient['tenant_id'] ?? null,
                    'unit_id' => $recipient['unit_id'] ?? null,
                    'phone' => $recipient['phone'],
                    'unit_number' => $recipient['unit_number'] ?? null,
                    'tenant_name' => $recipient['tenant_name'] ?? null,
                    'message' => $this->personalizeMessage($request->message, $recipient),
                    'reading_date' => $recipient['reading_date'] ?? now()->format('Y-m-d'),
                    'previous_reading' => $recipient['previous_reading'] ?? null,
                    'current_reading' => $recipient['current_reading'] ?? null,
                    'consumption' => $recipient['consumption'] ?? null,
                    'water_bill' => $recipient['water_bill'] ?? 0,
                    'payment_status' => $recipient['payment_status'] ?? 'pending',
                    'due_date' => $recipient['due_date'] ?? null,
                    'status' => 'pending',
                    'cost_per_sms' => $campaign->cost_per_sms,
                ]);
                $recipientCount++;
            }

            Log::info('RECIPIENTS CREATED', ['count' => $recipientCount]);

            // Update campaign totals
            $campaign->total_recipients = $campaign->recipients()->count();
            $campaign->estimated_cost = $campaign->total_recipients * $campaign->cost_per_sms * ceil(strlen($campaign->message) / 160);
            $campaign->save();

            Log::info('CAMPAIGN UPDATED', [
                'total_recipients' => $campaign->total_recipients,
                'estimated_cost' => $campaign->estimated_cost
            ]);

            DB::commit();

            Log::info('CAMPAIGN STORE SUCCESS', ['id' => $campaign->id]);

            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('success', 'Campaign created successfully! ' . $campaign->total_recipients . ' recipients added.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('STORE ERROR: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return redirect()->back()
                ->with('error', 'Failed to create campaign: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display campaign details
     */
    public function show($id)
    {
        $campaign = Campaign::with(['estate', 'createdBy'])->findOrFail($id);

        // Get recipients with pagination
        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->paginate(20);

        // Calculate stats
        $stats = [
            'total' => $campaign->total_recipients ?? 0,
            'delivered' => $campaign->delivered_count ?? 0,
            'failed' => $campaign->failed_count ?? 0,
            'pending' => ($campaign->total_recipients ?? 0) - ($campaign->delivered_count ?? 0) - ($campaign->failed_count ?? 0),
            'sent' => $campaign->sent_count ?? 0,
            'success_rate' => $campaign->total_recipients > 0 
                ? round(($campaign->delivered_count / $campaign->total_recipients) * 100) 
                : 0,
        ];

        return view('sms.campaigns.show', compact('campaign', 'stats', 'recipients'));
    }

    /**
     * Show form to edit campaign
     */
    public function edit($id)
    {
        $campaign = Campaign::findOrFail($id);

        // Only draft campaigns can be edited
        if ($campaign->status != 'draft') {
            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('error', 'Only draft campaigns can be edited.');
        }

        $estates = Estate::all();
        return view('sms.campaigns.edit', compact('campaign', 'estates'));
    }

    /**
     * Update campaign
     */
    public function update(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        // Only draft campaigns can be updated
        if ($campaign->status != 'draft') {
            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('error', 'Only draft campaigns can be updated.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'estate_id' => 'required|exists:estates,id',
            'billing_month' => 'required|string|max:50',
            'message' => 'required|string',
            'sender_id' => 'nullable|string|max:11',
            'message_type' => 'nullable|string|in:transactional,promotional',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            DB::beginTransaction();

            // Update campaign
            $campaign->update([
                'name' => $request->name,
                'estate_id' => $request->estate_id,
                'billing_month' => $request->billing_month,
                'message' => $request->message,
                'sender_id' => $request->sender_id ?? $campaign->sender_id,
                'message_type' => $request->message_type ?? $campaign->message_type,
                'scheduled_at' => $request->scheduled_at,
            ]);

            // Recalculate cost
            $campaign->estimated_cost = $campaign->total_recipients * $campaign->cost_per_sms * ceil(strlen($campaign->message) / 160);
            $campaign->save();

            DB::commit();

            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('success', 'Campaign updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update campaign: ' . $e->getMessage());
        }
    }

    /**
     * Delete campaign
     */
    public function destroy($id)
    {
        try {
            $campaign = Campaign::find($id);
            
            if (!$campaign) {
                return redirect()->route('sms.campaigns.index')
                    ->with('error', 'Campaign not found.');
            }

            // Only draft campaigns can be deleted
            if ($campaign->status != 'draft') {
                return redirect()->route('sms.campaigns.index')
                    ->with('error', 'Only draft campaigns can be deleted.');
            }

            DB::beginTransaction();

            // Delete recipients first
            $campaign->recipients()->delete();

            // Delete campaign
            $campaign->delete();

            DB::commit();

            return redirect()->route('sms.campaigns.index')
                ->with('success', 'Campaign deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('sms.campaigns.index')
                ->with('error', 'Failed to delete campaign: ' . $e->getMessage());
        }
    }

    /**
     * Send campaign
     */
    public function send($id)
    {
        $campaign = Campaign::findOrFail($id);

        // Only draft or scheduled campaigns can be sent
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('error', 'This campaign cannot be sent.');
        }

        try {
            $this->campaignService->sendCampaign($campaign);

            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('success', 'Campaign is being sent!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to send campaign: ' . $e->getMessage());
        }
    }

    /**
     * Duplicate campaign
     */
    public function duplicate($id)
    {
        $campaign = Campaign::findOrFail($id);

        try {
            DB::beginTransaction();

            // Create new campaign
            $newCampaign = $campaign->replicate();
            $newCampaign->name = $campaign->name . ' (Copy)';
            $newCampaign->status = 'draft';
            $newCampaign->created_by = Auth::id();
            $newCampaign->sent_count = 0;
            $newCampaign->delivered_count = 0;
            $newCampaign->failed_count = 0;
            $newCampaign->actual_cost = 0;
            $newCampaign->sent_at = null;
            $newCampaign->completed_at = null;
            $newCampaign->save();

            // Duplicate recipients
            foreach ($campaign->recipients as $recipient) {
                $newRecipient = $recipient->replicate();
                $newRecipient->campaign_id = $newCampaign->id;
                $newRecipient->status = 'pending';
                $newRecipient->sent_at = null;
                $newRecipient->delivered_at = null;
                $newRecipient->failed_at = null;
                $newRecipient->failure_reason = null;
                $newRecipient->save();
            }

            $newCampaign->total_recipients = $newCampaign->recipients()->count();
            $newCampaign->estimated_cost = $newCampaign->total_recipients * $newCampaign->cost_per_sms * ceil(strlen($newCampaign->message) / 160);
            $newCampaign->save();

            DB::commit();

            return redirect()->route('sms.campaigns.show', $newCampaign)
                ->with('success', 'Campaign duplicated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to duplicate campaign: ' . $e->getMessage());
        }
    }

    /**
     * Cancel scheduled campaign
     */
    public function cancel($id)
    {
        $campaign = Campaign::findOrFail($id);

        if (!in_array($campaign->status, ['scheduled', 'queued'])) {
            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('error', 'Only scheduled or queued campaigns can be cancelled.');
        }

        $campaign->status = 'cancelled';
        $campaign->save();

        return redirect()->route('sms.campaigns.show', $campaign)
            ->with('success', 'Campaign cancelled successfully!');
    }

    /**
     * Resend failed messages
     */
    public function resendFailed($id)
    {
        $campaign = Campaign::findOrFail($id);

        if ($campaign->status != 'completed') {
            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('error', 'Campaign must be completed to resend failed messages.');
        }

        try {
            $this->campaignService->resendFailed($campaign);

            return redirect()->route('sms.campaigns.show', $campaign)
                ->with('success', 'Failed messages are being resent!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to resend: ' . $e->getMessage());
        }
    }

    /**
     * Export recipients to CSV
     */
    public function export($id)
    {
        $campaign = Campaign::findOrFail($id);
        $recipients = $campaign->recipients;

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=campaign-{$campaign->id}-recipients.csv",
        ];

        $callback = function() use ($recipients) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Unit', 'Tenant', 'Phone', 'Amount', 'Status', 'Sent At', 'Failure Reason']);

            foreach ($recipients as $recipient) {
                fputcsv($file, [
                    $recipient->unit_number ?? 'N/A',
                    $recipient->tenant_name ?? 'N/A',
                    $recipient->phone,
                    $recipient->water_bill ?? 0,
                    $recipient->status,
                    $recipient->sent_at ?? 'N/A',
                    $recipient->failure_reason ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get recipients based on filters
     */
    private function getRecipients($estateId, $filters = [])
    {
        Log::info('GET RECIPIENTS CALLED', ['estate_id' => $estateId, 'filters' => $filters]);
        
        try {
            // Get all tenants with their unit and user
            $tenants = \App\Modules\Tenants\Models\Tenant::with(['unit', 'user'])->get();
            
            $recipients = [];
            foreach ($tenants as $tenant) {
                // Get the unit through the relationship
                $unit = $tenant->unit;
                
                // Skip if no unit or unit is not in the selected estate
                if (!$unit || $unit->estate_id != $estateId) continue;
                
                $user = $tenant->user;
                
                // Get the latest water reading
                $reading = $unit->waterReadings()->latest()->first();
                
                $recipients[] = [
                    'tenant_id' => $tenant->id,
                    'unit_id' => $unit->id,
                    'phone' => $user->phone ?? $tenant->emergency_contact ?? '',
                    'unit_number' => $unit->unit_number,
                    'tenant_name' => $user->name ?? 'N/A',
                    'reading_date' => $reading->reading_date ?? now()->format('Y-m-d'),
                    'previous_reading' => $reading->previous_reading ?? 0,
                    'current_reading' => $reading->current_reading ?? 0,
                    'consumption' => $reading->consumption ?? 0,
                    'water_bill' => $reading->water_bill ?? $reading->charge ?? 0,
                    'payment_status' => 'pending',
                    'due_date' => now()->addDays(7)->format('Y-m-d'),
                    'estate_name' => $unit->estate->name ?? '',
                    'month' => now()->format('F Y'),
                ];
            }
            
            // If no recipients found, return sample data
            if (empty($recipients)) {
                Log::warning('No real recipients found for estate ' . $estateId . ', using sample data');
                return $this->getSampleRecipients();
            }
            
            Log::info('REAL RECIPIENTS RETURNED', ['count' => count($recipients)]);
            return $recipients;
            
        } catch (\Exception $e) {
            Log::error('GET RECIPIENTS ERROR: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            // Return sample data as fallback
            return $this->getSampleRecipients();
        }
    }

    /**
     * Get sample recipients for testing
     */
    private function getSampleRecipients()
    {
        return [
            [
                'tenant_id' => 1,
                'unit_id' => 1,
                'phone' => '254727371496',
                'unit_number' => 'A-101',
                'tenant_name' => 'John Doe',
                'reading_date' => now()->format('Y-m-d'),
                'previous_reading' => 100,
                'current_reading' => 125,
                'consumption' => 25,
                'water_bill' => 1500.00,
                'payment_status' => 'pending',
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'estate_name' => 'Danaff Towers',
                'month' => 'July 2026',
            ],
            [
                'tenant_id' => 2,
                'unit_id' => 2,
                'phone' => '254727371497',
                'unit_number' => 'A-102',
                'tenant_name' => 'Jane Smith',
                'reading_date' => now()->format('Y-m-d'),
                'previous_reading' => 50,
                'current_reading' => 80,
                'consumption' => 30,
                'water_bill' => 1800.00,
                'payment_status' => 'pending',
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'estate_name' => 'Danaff Towers',
                'month' => 'July 2026',
            ],
            [
                'tenant_id' => 3,
                'unit_id' => 3,
                'phone' => '254727371498',
                'unit_number' => 'A-103',
                'tenant_name' => 'Bob Johnson',
                'reading_date' => now()->format('Y-m-d'),
                'previous_reading' => 200,
                'current_reading' => 240,
                'consumption' => 40,
                'water_bill' => 2400.00,
                'payment_status' => 'pending',
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'estate_name' => 'Danaff Towers',
                'month' => 'July 2026',
            ],
        ];
    }

    /**
     * Personalize message with placeholders
     */
    private function personalizeMessage($message, $data)
    {
        try {
            $placeholders = [
                '{{estate_name}}' => $data['estate_name'] ?? '',
                '{{month}}' => $data['month'] ?? '',
                '{{unit}}' => $data['unit_number'] ?? '',
                '{{unit_number}}' => $data['unit_number'] ?? '',
                '{{water_bill}}' => number_format($data['water_bill'] ?? 0, 2),
                '{{water_consumption}}' => $data['consumption'] ?? 0,
                '{{prev_read}}' => $data['previous_reading'] ?? '',
                '{{curr_read}}' => $data['current_reading'] ?? '',
                '{{due_date}}' => $data['due_date'] ?? '',
                '{{payment_status}}' => $data['payment_status'] ?? '',
                '{{status}}' => $data['payment_status'] ?? '',
                '{{tenant_name}}' => $data['tenant_name'] ?? '',
                '{{name}}' => $data['tenant_name'] ?? '',
            ];

            return str_replace(array_keys($placeholders), array_values($placeholders), $message);
        } catch (\Exception $e) {
            Log::error('PERSONALIZE MESSAGE ERROR: ' . $e->getMessage());
            return $message;
        }
    }

    /**
     * Display campaign history from KenyaSMS
     */
    public function history(Request $request)
    {
        // Base query for campaigns
        $query = DB::table('sms_campaign_history as c')
            ->leftJoin('estates as e', 'c.estate_id', '=', 'e.id')
            ->select(
                'c.*',
                'e.name as estate_name',
                DB::raw("DATE_FORMAT(c.sent_at, '%Y-%m') as month")
            );
        
        // Base query for summary (same filters)
        $summaryQuery = DB::table('sms_campaign_history');
        
        // Apply filters to both queries
        if ($request->filled('estate_id')) {
            $query->where('c.estate_id', $request->estate_id);
            $summaryQuery->where('estate_id', $request->estate_id);
        }
        
        if ($request->filled('month')) {
            $query->where(DB::raw("DATE_FORMAT(c.sent_at, '%Y-%m')"), $request->month);
            $summaryQuery->where(DB::raw("DATE_FORMAT(sent_at, '%Y-%m')"), $request->month);
        }
        
        // ====== FIXED STATUS FILTER ======
        if ($request->filled('status')) {
            if ($request->status === 'failed') {
                // When filtering by 'failed', show campaigns with failed_count > 0
                $query->where('c.failed_count', '>', 0);
                $summaryQuery->where('failed_count', '>', 0);
            } else {
                $query->where('c.status', $request->status);
                $summaryQuery->where('status', $request->status);
            }
        }
        // ====== END FIXED STATUS FILTER ======
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('c.name', 'LIKE', "%{$search}%")
                  ->orWhere('c.message', 'LIKE', "%{$search}%")
                  ->orWhere('c.sender_id', 'LIKE', "%{$search}%");
            });
            $summaryQuery->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('message', 'LIKE', "%{$search}%")
                  ->orWhere('sender_id', 'LIKE', "%{$search}%");
            });
        }
        
        // Get paginated campaigns
        $campaigns = $query->orderBy('c.sent_at', 'desc')->paginate(20);
        
        // Calculate filtered summary stats
        $summary = $summaryQuery
            ->select(
                DB::raw('COUNT(*) as total_campaigns'),
                DB::raw('SUM(total_recipients) as total_recipients'),
                DB::raw('SUM(sent_count) as total_sent'),
                DB::raw('SUM(delivered_count) as total_delivered'),
                DB::raw('SUM(failed_count) as total_failed'),
                DB::raw('SUM(actual_cost) as total_cost')
            )
            ->first();
        
        // Get estates for filter (always all)
        $estates = Estate::all();
        
        // Get available months from data (always all)
        $months = DB::table('sms_campaign_history')
            ->select(DB::raw("DATE_FORMAT(sent_at, '%Y-%m') as month"))
            ->whereNotNull('sent_at')
            ->distinct()
            ->orderBy('month', 'desc')
            ->pluck('month');
        
        return view('sms.campaigns.history', compact('campaigns', 'estates', 'months', 'summary'));
    }
}