<?php

namespace App\Modules\SMS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SMS\Models\Campaign;
use App\Modules\SMS\Models\CampaignRecipient;
use App\Modules\SMS\Services\CampaignService;
use App\Modules\SMS\Services\KenyaSMSService;
use App\Modules\Properties\Models\Unit;
use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
    public function create(Request $request)
    {
        $estates = Estate::all();
        
        // 🆕 Get filter from query parameter
        $filter = $request->query('filter');
        $preFilter = null;
        if ($filter && in_array($filter, ['paid', 'unpaid', 'partial', 'pending'])) {
            $preFilter = $filter;
        }
        
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
                    'content' => "{{estate_name}} - {{month}} Water Bill\nUnit: {{unit}}\nConsumption: {{water_consumption}} units\nAmount: KES {{water_bill}}\nDue: {{due_date}}\nPaybill: 7263733 Acc: {{unit}}\n\n{{payment_status}}\n\nFor queries: 0701262902"
                ],
            ]);
        }
        
        return view('sms.campaigns.create', compact('estates', 'templates', 'preFilter'));
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
                'filters.payment_status' => 'nullable|string|in:unpaid,paid,pending,partial',
                'filters.min_bill_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                Log::warning('VALIDATION FAILED', $validator->errors()->all());
                return redirect()->back()->withErrors($validator)->withInput();
            }

            Log::info('VALIDATION PASSED');

            // ✅ Convert billing month to Y-m format for invoice lookup
            $billingMonth = $request->billing_month;
            if (!preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
                try {
                    $billingMonth = Carbon::parse($billingMonth)->format('Y-m');
                } catch (\Exception $e) {
                    Log::warning('Could not parse billing month: ' . $billingMonth . ', using current month');
                    $billingMonth = now()->format('Y-m');
                }
            }
            Log::info('BILLING MONTH CONVERTED', ['original' => $request->billing_month, 'converted' => $billingMonth]);

            // ✅ Pass the converted billing_month to getRecipients
            $recipients = $this->getRecipients($request->estate_id, $request->filters ?? [], $billingMonth);
            
            Log::info('RECIPIENTS FOUND', ['count' => count($recipients)]);
            
            if (empty($recipients)) {
                Log::warning('NO RECIPIENTS FOUND');
                return redirect()->back()
                    ->with('error', 'No recipients found for this campaign. Please check your filters.')
                    ->withInput();
            }

            DB::beginTransaction();

            // Create campaign with the converted billing month
            $campaign = Campaign::create([
                'name' => $request->name,
                'estate_id' => $request->estate_id,
                'billing_month' => $billingMonth,
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
                    'pending_count' => $recipient['pending_count'] ?? 0,
                    'pending_months' => $recipient['pending_months'] ?? null,
                    'total_pending_amount' => $recipient['total_pending_amount'] ?? 0,
                    'has_pending' => $recipient['has_pending'] ?? false,
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

            // Convert billing month to Y-m format
            $billingMonth = $request->billing_month;
            if (!preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
                try {
                    $billingMonth = Carbon::parse($billingMonth)->format('Y-m');
                } catch (\Exception $e) {
                    $billingMonth = $campaign->billing_month;
                }
            }

            // Update campaign
            $campaign->update([
                'name' => $request->name,
                'estate_id' => $request->estate_id,
                'billing_month' => $billingMonth,
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
     * Resend failed messages (Bulk)
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
     * 🆕 Resend a single recipient (Supports failed AND pending for testing)
     */
    public function resendRecipient($campaignId, $recipientId)
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);
            $recipient = CampaignRecipient::findOrFail($recipientId);
            
            // ✅ Allow pending AND failed recipients for testing
            if (!in_array($recipient->status, ['failed', 'pending'])) {
                return redirect()->back()->with('error', 'Only failed or pending recipients can be resent.');
            }
            
            // Reset recipient status
            $recipient->status = 'pending';
            $recipient->failure_reason = null;
            $recipient->failed_at = null;
            $recipient->retry_count = ($recipient->retry_count ?? 0) + 1;
            $recipient->last_retry_at = now();
            $recipient->save();
            
            // Send SMS to this single recipient
            $messages = [
                [
                    'phone' => $recipient->phone,
                    'message' => $recipient->message,
                ]
            ];
            
            $response = $this->smsService->sendPersonalized($messages, [
                'sender_id' => $campaign->sender_id,
                'message_type' => $campaign->message_type ?? 'transactional',
                'callback_url' => route('sms.webhook.dlr'),
            ]);
            
            // Update recipient status based on response
            if (isset($response['success']) && $response['success']) {
                $recipient->status = 'sent';
                $recipient->sent_at = now();
                
                // Store external message ID if available
                if (isset($response['data']['messages'][0]['id'])) {
                    $recipient->external_message_id = $response['data']['messages'][0]['id'];
                }
                
                $recipient->save();
                
                // Update campaign stats
                $campaign->sent_count = ($campaign->sent_count ?? 0) + 1;
                $campaign->save();
                
                return redirect()->back()->with('success', '✅ SMS sent successfully to ' . ($recipient->tenant_name ?? $recipient->phone) . '!');
            } else {
                $recipient->status = 'failed';
                $recipient->failure_reason = $response['message'] ?? 'Unknown error';
                $recipient->failed_at = now();
                $recipient->save();
                
                return redirect()->back()->with('error', 'Failed to send SMS: ' . ($response['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            Log::error('RESEND RECIPIENT ERROR: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return redirect()->back()->with('error', 'Failed to send SMS: ' . $e->getMessage());
        }
    }

    /**
     * 🆕 Send a test SMS to the authenticated user only
     */
    public function testSend($campaignId)
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);
            $user = Auth::user();
            
            if (!$user || !$user->phone) {
                return redirect()->back()->with('error', 'You don\'t have a phone number set in your profile. Please update your profile.');
            }
            
            // Get a sample recipient for data
            $sampleRecipient = $campaign->recipients()->first();
            
            if (!$sampleRecipient) {
                return redirect()->back()->with('error', 'No recipients found in this campaign.');
            }
            
            // Create test data with sample values
            $testData = [
                'estate_name' => $campaign->estate->name ?? 'Test Estate',
                'month' => $campaign->billing_month ?? now()->format('F Y'),
                'unit' => 'TEST-001',
                'unit_number' => 'TEST-001',
                'water_bill' => '500.00',
                'water_consumption' => '25',
                'previous_reading' => '100',
                'current_reading' => '125',
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'payment_status' => 'pending',
                'tenant_name' => $user->name ?? 'Test User',
                'name' => $user->name ?? 'Test User',
            ];
            
            // Personalize the message
            $personalizedMessage = $this->personalizeMessage($campaign->message, $testData);
            
            // Add test header
            $finalMessage = "🧪 TEST SMS\n━━━━━━━━━━━━━━━━━━\n" . 
                            $personalizedMessage . 
                            "\n━━━━━━━━━━━━━━━━━━\n📱 Test from " . config('app.name');
            
            // Send the test SMS
            $messages = [
                [
                    'phone' => $user->phone,
                    'message' => $finalMessage,
                ]
            ];
            
            $response = $this->smsService->sendPersonalized($messages, [
                'sender_id' => $campaign->sender_id,
                'message_type' => $campaign->message_type ?? 'transactional',
                'callback_url' => route('sms.webhook.dlr'),
            ]);
            
            if (isset($response['success']) && $response['success']) {
                return redirect()->back()->with('success', '✅ Test SMS sent successfully to ' . $user->phone . '! Please check your phone.');
            } else {
                return redirect()->back()->with('error', 'Failed to send test SMS: ' . ($response['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            Log::error('TEST SEND ERROR: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to send test SMS: ' . $e->getMessage());
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
     * 🆕 Get full invoice status with details for SMS
     * 
     * @param int|null $tenantId
     * @param int|null $unitId
     * @param string $billingMonth (format: Y-m)
     * @return array
     */
    private function getInvoiceStatusForTenant($tenantId, $unitId, $billingMonth)
    {
        try {
            // ✅ Convert billing month to Y-m format if needed
            if (!preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
                try {
                    $billingMonth = Carbon::parse($billingMonth)->format('Y-m');
                } catch (\Exception $e) {
                    Log::warning('Could not parse billing month: ' . $billingMonth . ', using current month');
                    $billingMonth = now()->format('Y-m');
                }
            }
            
            Log::info('🔍 getInvoiceStatusForTenant called', [
                'tenant_id' => $tenantId,
                'unit_id' => $unitId,
                'billing_month' => $billingMonth
            ]);
            
            if (!$tenantId || !$unitId) {
                return [
                    'status' => 'pending',
                    'message' => 'Pending',
                    'status_icon' => '🟠',
                    'pending_count' => 0,
                    'pending_months' => [],
                    'total_pending_amount' => 0,
                    'remaining_amount' => 0,
                    'has_pending' => false,
                    'display_text' => 'Status: Pending'
                ];
            }
            
            // Find the tenancy
            $tenancy = Tenancy::where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->where('status', 'active')
                ->first();
            
            if (!$tenancy) {
                $tenancy = Tenancy::where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->first();
                
                if (!$tenancy) {
                    return [
                        'status' => 'no_tenancy',
                        'message' => 'No Active Tenancy',
                        'status_icon' => '❌',
                        'pending_count' => 0,
                        'pending_months' => [],
                        'total_pending_amount' => 0,
                        'remaining_amount' => 0,
                        'has_pending' => false,
                        'display_text' => 'Status: No Active Tenancy'
                    ];
                }
            }
            
            // ✅ Use the converted billing month
            $billingMonthDate = $billingMonth . '-01';
            
            // Get current month invoice
            $currentInvoice = Invoice::where('tenancy_id', $tenancy->id)
                ->where('billing_month', $billingMonthDate)
                ->first();
            
            Log::info('📊 Invoice lookup result', [
                'tenancy_id' => $tenancy->id,
                'billing_month' => $billingMonthDate,
                'has_invoice' => $currentInvoice ? true : false,
                'invoice_status' => $currentInvoice ? $currentInvoice->status : 'none'
            ]);
            
            // Get all pending/unpaid invoices (excluding current month)
            $pendingInvoices = Invoice::where('tenancy_id', $tenancy->id)
                ->where('status', 'unpaid')
                ->where('billing_month', '!=', $billingMonthDate)
                ->orderBy('billing_month', 'asc')
                ->get();
            
            $pendingCount = $pendingInvoices->count();
            $pendingMonths = $pendingInvoices->map(function($invoice) {
                return Carbon::parse($invoice->billing_month)->format('F Y');
            })->toArray();
            $totalPendingAmount = $pendingInvoices->sum('total_amount');
            
            // Determine current status
            $currentStatus = 'pending';
            $remainingAmount = 0;
            $statusMessage = 'Pending';
            $statusIcon = '🟠';
            $displayText = 'Status: Pending';
            
            if ($currentInvoice) {
                $currentStatus = $currentInvoice->status;
                $remainingAmount = $currentInvoice->total_amount - $currentInvoice->total_paid;
                
                switch ($currentStatus) {
                    case 'paid':
                        $statusMessage = 'PAID';
                        $statusIcon = '✅';
                        $displayText = '✅ Payment Status: PAID';
                        break;
                    case 'unpaid':
                        $statusMessage = 'UNPAID';
                        $statusIcon = '⚠️';
                        $displayText = '⚠️ Payment Status: UNPAID';
                        break;
                    case 'partial':
                        $statusMessage = 'PARTIAL';
                        $statusIcon = '🟡';
                        $displayText = "🟡 Payment Status: PARTIAL (KES " . number_format($remainingAmount, 2) . " remaining)";
                        break;
                    default:
                        $statusMessage = 'Pending';
                        $statusIcon = '🟠';
                        $displayText = '🟠 Payment Status: Pending';
                }
            }
            
            // Build the full display text
            $fullDisplayText = $displayText;
            
            // Add pending invoices warning if any
            if ($pendingCount > 0) {
                $monthsList = implode(', ', $pendingMonths);
                $fullDisplayText .= "\n\n⚠️ You have {$pendingCount} unpaid invoice(s) from: {$monthsList}";
                $fullDisplayText .= "\nTotal outstanding: KES " . number_format($totalPendingAmount, 2);
                
                if ($currentStatus === 'paid') {
                    $fullDisplayText .= "\n\nPlease clear your outstanding balance.";
                } elseif ($currentStatus === 'partial') {
                    $fullDisplayText .= "\n\nPlease complete your payment.";
                } else {
                    $fullDisplayText .= "\n\nPlease pay your outstanding balance to avoid disconnection.";
                }
            }
            
            Log::info('📊 Invoice status result', [
                'tenant_id' => $tenantId,
                'status' => $currentStatus,
                'display_text' => $fullDisplayText,
                'pending_count' => $pendingCount
            ]);
            
            return [
                'status' => $currentStatus,
                'message' => $statusMessage,
                'status_icon' => $statusIcon,
                'pending_count' => $pendingCount,
                'pending_months' => $pendingMonths,
                'total_pending_amount' => $totalPendingAmount,
                'remaining_amount' => $remainingAmount,
                'has_pending' => $pendingCount > 0,
                'display_text' => $fullDisplayText,
                'current_invoice' => $currentInvoice,
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting invoice status: ' . $e->getMessage());
            return [
                'status' => 'pending',
                'message' => 'Pending',
                'status_icon' => '🟠',
                'pending_count' => 0,
                'pending_months' => [],
                'total_pending_amount' => 0,
                'remaining_amount' => 0,
                'has_pending' => false,
                'display_text' => 'Status: Pending',
                'current_invoice' => null,
            ];
        }
    }

    /**
     * Get recipients based on filters
     * 
     * @param int $estateId
     * @param array $filters
     * @param string|null $billingMonth (format: Y-m)
     * @return array
     */
    private function getRecipients($estateId, $filters = [], $billingMonth = null)
    {
        Log::info('GET RECIPIENTS CALLED', [
            'estate_id' => $estateId, 
            'filters' => $filters,
            'billing_month' => $billingMonth
        ]);
        
        try {
            // Get all tenants with their unit and user
            $tenants = \App\Modules\Tenants\Models\Tenant::with(['unit', 'user'])->get();
            
            $recipients = [];
            // ✅ Use the passed billing month or default to current month
            $billingMonth = $billingMonth ?? now()->format('Y-m');
            
            // 🆕 Get the payment status filter
            $filterPaymentStatus = $filters['payment_status'] ?? 'all';
            
            foreach ($tenants as $tenant) {
                // Get the unit through the relationship
                $unit = $tenant->unit;
                
                // Skip if no unit or unit is not in the selected estate
                if (!$unit || $unit->estate_id != $estateId) continue;
                
                $user = $tenant->user;
                
                // Get the latest water reading
                $reading = $unit->waterReadings()->latest()->first();
                
                // 🆕 Get full invoice status for this tenant
                $invoiceData = $this->getInvoiceStatusForTenant($tenant->id, $unit->id, $billingMonth);
                
                // 🆕 Skip if payment status doesn't match filter
                if ($filterPaymentStatus !== 'all') {
                    $status = $invoiceData['status'];
                    if ($status !== $filterPaymentStatus) {
                        continue;
                    }
                }
                
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
                    'payment_status' => $invoiceData['display_text'],
                    'pending_count' => $invoiceData['pending_count'],
                    'pending_months' => json_encode($invoiceData['pending_months']),
                    'total_pending_amount' => $invoiceData['total_pending_amount'],
                    'has_pending' => $invoiceData['has_pending'],
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
     * Now dynamically gets real invoice status
     */
    private function getSampleRecipients()
    {
        // Try to get a real tenant with invoice data
        try {
            $realTenant = \App\Modules\Tenants\Models\Tenant::with(['unit', 'user'])->first();
            
            if ($realTenant && $realTenant->unit) {
                $user = $realTenant->user;
                $unit = $realTenant->unit;
                $billingMonth = now()->format('Y-m');
                
                // Get invoice status for this real tenant
                $invoiceData = $this->getInvoiceStatusForTenant($realTenant->id, $unit->id, $billingMonth);
                
                // Get a sample reading or create one
                $reading = $unit->waterReadings()->latest()->first();
                
                return [
                    [
                        'tenant_id' => $realTenant->id,
                        'unit_id' => $unit->id,
                        'phone' => $user->phone ?? '254727371496',
                        'unit_number' => $unit->unit_number ?? 'A-101',
                        'tenant_name' => $user->name ?? 'John Doe',
                        'reading_date' => $reading->reading_date ?? now()->format('Y-m-d'),
                        'previous_reading' => $reading->previous_reading ?? 100,
                        'current_reading' => $reading->current_reading ?? 125,
                        'consumption' => $reading->consumption ?? 25,
                        'water_bill' => $reading->water_bill ?? $reading->charge ?? 1500.00,
                        'payment_status' => $invoiceData['display_text'],
                        'pending_count' => $invoiceData['pending_count'],
                        'pending_months' => json_encode($invoiceData['pending_months']),
                        'total_pending_amount' => $invoiceData['total_pending_amount'],
                        'has_pending' => $invoiceData['has_pending'],
                        'due_date' => now()->addDays(7)->format('Y-m-d'),
                        'estate_name' => $unit->estate->name ?? 'Danaff Towers',
                        'month' => now()->format('F Y'),
                    ]
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Could not get real tenant for sample: ' . $e->getMessage());
        }
        
        // Fallback sample data with realistic status
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
                'payment_status' => '⚠️ Payment Status: UNPAID',
                'pending_count' => 1,
                'pending_months' => '["June 2026"]',
                'total_pending_amount' => 750.00,
                'has_pending' => 1,
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'estate_name' => 'Danaff Towers',
                'month' => now()->format('F Y'),
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
                'payment_status' => '✅ Payment Status: PAID',
                'pending_count' => 0,
                'pending_months' => '[]',
                'total_pending_amount' => 0,
                'has_pending' => 0,
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'estate_name' => 'Danaff Towers',
                'month' => now()->format('F Y'),
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
                'payment_status' => '🟡 Payment Status: PARTIAL (KES 800.00 remaining)',
                'pending_count' => 0,
                'pending_months' => '[]',
                'total_pending_amount' => 0,
                'has_pending' => 0,
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'estate_name' => 'Danaff Towers',
                'month' => now()->format('F Y'),
            ],
        ];
    }

    /**
     * Personalize message with placeholders
     */
    private function personalizeMessage($message, $data)
    {
        try {
            // Build the full payment status display
            $paymentStatusDisplay = $data['payment_status'] ?? 'Status: Pending';
            
            // If there are pending invoices, add them to the message
            if (isset($data['has_pending']) && $data['has_pending']) {
                $pendingMonths = json_decode($data['pending_months'] ?? '[]', true);
                $pendingCount = $data['pending_count'] ?? 0;
                
                if ($pendingCount > 0) {
                    $monthsList = implode(', ', $pendingMonths);
                    $paymentStatusDisplay .= "\n\n⚠️ You have {$pendingCount} unpaid invoice(s) from: {$monthsList}";
                    if (isset($data['total_pending_amount']) && $data['total_pending_amount'] > 0) {
                        $paymentStatusDisplay .= "\nTotal outstanding: KES " . number_format($data['total_pending_amount'], 2);
                    }
                }
            }
            
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
                '{{payment_status}}' => $paymentStatusDisplay,
                '{{status}}' => $data['status_message'] ?? '',
                '{{status_icon}}' => $data['status_icon'] ?? '🟠',
                '{{tenant_name}}' => $data['tenant_name'] ?? '',
                '{{name}}' => $data['tenant_name'] ?? '',
                '{{remaining_amount}}' => isset($data['remaining_amount']) && $data['remaining_amount'] > 0 ? 'KES ' . number_format($data['remaining_amount'], 2) : '',
                '{{pending_count}}' => $data['pending_count'] ?? 0,
                '{{pending_months}}' => isset($data['pending_months']) ? implode(', ', json_decode($data['pending_months'] ?? '[]', true)) : '',
                '{{total_pending_amount}}' => isset($data['total_pending_amount']) && $data['total_pending_amount'] > 0 ? 'KES ' . number_format($data['total_pending_amount'], 2) : '',
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
        
        if ($request->filled('status')) {
            if ($request->status === 'failed') {
                $query->where('c.failed_count', '>', 0);
                $summaryQuery->where('failed_count', '>', 0);
            } else {
                $query->where('c.status', $request->status);
                $summaryQuery->where('status', $request->status);
            }
        }
        
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

    /**
     * Get recipient details for AJAX modal
     */
    public function getRecipient($id)
    {
        try {
            $recipient = CampaignRecipient::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'recipient' => $recipient,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}