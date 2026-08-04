<?php

namespace App\Modules\SMS\Controllers;

use App\Http\Controllers\Controller;
<<<<<<< HEAD
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
=======
use App\Modules\SMS\Models\SmsCampaign;
use App\Models\SmsTemplate;
use App\Models\Estate;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Services\CampaignService;
use App\Services\SmsStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
>>>>>>> campaign-v-3

class CampaignController extends Controller
{
    protected $campaignService;
<<<<<<< HEAD
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
=======

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    /**
     * Display a listing of campaigns (API endpoint - returns JSON)
     */
    public function index(Request $request)
    {
        try {
            Log::info('Campaigns API called', ['filters' => $request->all()]);
            
            $query = SmsCampaign::with(['template', 'creator'])
                ->orderBy('created_at', 'desc');
            
            if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
                $query->where('status', $request->status);
            }
            
            $campaigns = $query->get();
            $stats = $this->calculateStats($campaigns);
            
            return response()->json([
                'campaigns' => $campaigns,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch campaigns: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate campaign statistics
     */
    private function calculateStats($campaigns)
    {
        $stats = [
            'total' => $campaigns->count(),
            'sent' => 0,
            'pending' => 0,
            'failed' => 0
        ];

        foreach ($campaigns as $campaign) {
            if ($campaign->status === 'completed' || $campaign->status === 'sent') {
                $stats['sent']++;
            } else if ($campaign->status === 'pending' || $campaign->status === 'scheduled' || $campaign->status === 'sending') {
                $stats['pending']++;
            } else if ($campaign->status === 'failed') {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Show the form for creating a new campaign (Web view)
     */
    public function create()
    {
        $templates = SmsTemplate::all();
        $estates = Estate::all();
        $companies = Company::all();
        
        return view('sms.campaigns.create', compact('templates', 'estates', 'companies'));
    }

    /**
     * Store a newly created campaign (API endpoint)
     * Auto-creates recipients for the campaign with phone validation
     */
    public function store(Request $request)
    {
        try {
            Log::info('Store campaign called', $request->all());
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'template_id' => 'required|integer|exists:sms_templates,id',
                'description' => 'nullable|string',
                'filters' => 'nullable|array',
                'filters.estate_id' => 'nullable|exists:estates,id',
                'filters.company_id' => 'nullable|exists:companies,id',
                'filters.invoice_status' => 'nullable|string|in:paid,unpaid,partial,pending,overdue',
                'campaign_type' => 'nullable|string',
                'scheduled_at' => 'nullable|date|after:now',
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $template = SmsTemplate::find($request->template_id);
            
            // Get validated recipients
            $recipientsResult = $this->campaignService->getRecipientsWithValidation($request->filters ?? []);
            
            $campaignData = [
                'name' => $request->name,
                'description' => $request->description,
                'template_id' => $request->template_id,
                'filters' => $request->filters ?? [],
                'status' => $request->scheduled_at ? 'scheduled' : 'draft',
                'scheduled_at' => $request->scheduled_at,
                'created_by' => auth()->id(),
                'campaign_type' => $request->campaign_type ?? 'general',
            ];

            Log::info('Creating campaign with validation data', [
                'valid' => count($recipientsResult['valid']),
                'invalid' => count($recipientsResult['invalid']),
                'other_network' => count($recipientsResult['other_network'])
            ]);

            // Create campaign (this will auto-create recipients for valid numbers only)
            $campaign = $this->campaignService->createCampaign($campaignData);

            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully',
                'campaign' => $campaign->load(['template', 'creator']),
                'stats' => [
                    'valid' => count($recipientsResult['valid']),
                    'invalid' => count($recipientsResult['invalid']),
                    'other_network' => count($recipientsResult['other_network']),
                    'total' => $campaign->total_recipients
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Campaign creation failed: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign: ' . $e->getMessage()
            ], 500);
>>>>>>> campaign-v-3
        }
    }

    /**
<<<<<<< HEAD
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
=======
     * Preview recipients for a campaign (API endpoint)
     */
    public function preview(Request $request)
    {
        try {
            $query = Tenant::query()
                ->join('tenancies', 'tenancies.tenant_id', '=', 'tenants.id')
                ->join('units', 'units.id', '=', 'tenancies.unit_id')
                ->join('users', 'users.id', '=', 'tenants.user_id')
                ->join('estates', 'estates.id', '=', 'units.estate_id')
                ->select(
                    'tenants.*',
                    'tenancies.id as tenancy_id',
                    'tenancies.unit_id',
                    'units.unit_number',
                    'units.estate_id as unit_estate_id',
                    'users.phone',
                    'users.name as user_name',
                    'estates.name as estate_name'
                )
                ->where('tenancies.status', 'active');
            
            // Filter by estate
            if (!empty($request->filters['estate_id'])) {
                $query->where('units.estate_id', $request->filters['estate_id']);
            }
            
            // Filter by company
            if (!empty($request->filters['company_id'])) {
                $query->where('units.company_id', $request->filters['company_id']);
            }
            
            // Filter by invoice status
            if (!empty($request->filters['invoice_status'])) {
                $status = $request->filters['invoice_status'];
                
                $query->whereHas('tenancies.invoices', function($q) use ($status) {
                    if ($status === 'unpaid' || $status === 'overdue') {
                        $q->whereIn('status', ['unpaid', 'draft']);
                    } elseif ($status === 'paid') {
                        $q->where('status', 'paid');
                    } elseif ($status === 'partial') {
                        $q->where('status', 'partial');
                    }
                });
            }
            
            // Only get tenants with phone numbers
            $query->whereNotNull('users.phone')
                  ->where('users.phone', '!=', '')
                  ->where('users.phone', '!=', 'null');

            $tenants = $query->with([
                'tenancies.unit.waterReadings' => function($q) {
                    $q->latest()->limit(1);
                },
                'tenancies.invoices' => function($q) {
                    $q->latest()->limit(1);
                }
            ])->get();
            
            Log::info('Preview tenants found: ' . $tenants->count());
            
            $valid = 0;
            $invalid = 0;
            $tenantData = [];
            
            foreach ($tenants as $tenant) {
                // Get phone
                $phone = preg_replace('/[^0-9]/', '', $tenant->phone ?? '');
                $isValid = preg_match('/^(07|01|2547|2541)\d{8}$/', $phone) && strlen($phone) >= 10;
                
                if ($isValid) {
                    $valid++;
                } else {
                    $invalid++;
                }
                
                // Get tenancy
                $tenancy = $tenant->tenancies->first();
                $unit = $tenancy ? $tenancy->unit : null;
                
                // Get water reading
                $reading = $unit ? $unit->waterReadings->first() : null;
                
                // Get latest invoice
                $invoice = $tenancy ? $tenancy->invoices->first() : null;
                
                $tenantData[] = [
                    'id' => $tenant->id,
                    'name' => $tenant->user_name ?? $tenant->name ?? 'Tenant',
                    'phone' => $tenant->phone ?? '',
                    'unit_number' => $unit ? $unit->unit_number : 'N/A',
                    'estate_name' => $tenant->estate_name ?? 'N/A',
                    'estate_id' => $tenant->unit_estate_id ?? $tenant->estate_id,
                    'water_bill' => $reading ? $reading->charge : 0,
                    'water_consumption' => $reading ? $reading->consumption : 0,
                    'prev_read' => $reading ? $reading->previous_reading : 0,
                    'curr_read' => $reading ? $reading->current_reading : 0,
                    'due_date' => $invoice && isset($invoice->created_at) ? $invoice->created_at->addDays(14)->format('Y-m-d') : '',
                    'reading_month' => $reading && $reading->reading_date ? $reading->reading_date->format('F Y') : date('F Y'),
                    'payment_status' => $invoice ? $invoice->status : 'pending',
                    'invoice_number' => $invoice ? $invoice->id : null,
                    'invoice_status' => $invoice ? $invoice->status : 'no_invoice'
                ];
            }
            
            return response()->json([
                'success' => true,
                'total' => $tenants->count(),
                'valid' => $valid,
                'invalid' => $invalid,
                'tenants' => $tenantData
            ]);
            
        } catch (\Exception $e) {
            Log::error('Preview failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified campaign (Web view)
     */
    public function show($id)
    {
        $campaign = SmsCampaign::with(['recipients.tenant', 'template', 'creator'])
            ->findOrFail($id);
        return view('sms.campaigns.show', compact('campaign'));
    }

    /**
     * Get campaign details with recipient statuses (API endpoint)
     * UPDATED: Now includes message_id, provider_status, provider_response
     */
    public function getDetails($id)
    {
        try {
            $campaign = SmsCampaign::with([
                'template', 
                'creator',
                'recipients' => function($q) {
                    $q->with(['tenant' => function($q2) {
                        $q2->with([
                            'user',
                            'tenancies' => function($q3) {
                                $q3->where('status', 'active')->with([
                                    'unit' => function($q4) {
                                        $q4->with('estate');
                                    }
                                ]);
                            }
                        ]);
                    }]);
                }
            ])->findOrFail($id);
            
            // Get recipient status counts
            $statusCounts = [
                'sent' => $campaign->recipients->where('status', 'sent')->count(),
                'pending' => $campaign->recipients->where('status', 'pending')->count(),
                'failed' => $campaign->recipients->where('status', 'failed')->count(),
                'queued' => $campaign->recipients->where('status', 'queued')->count(),
                'delivered' => $campaign->recipients->where('status', 'delivered')->count(),
            ];
            
            // Format recipients with ALL columns
            $recipients = $campaign->recipients->map(function($recipient) {
                $tenant = $recipient->tenant;
                $user = $tenant ? $tenant->user : null;
                $tenancy = $tenant ? $tenant->tenancies->first() : null;
                $unit = $tenancy ? $tenancy->unit : null;
                $estate = $unit ? $unit->estate : null;
                
                return [
                    'id' => $recipient->id,
                    'tenant_id' => $recipient->tenant_id,
                    'tenant_name' => $user ? $user->name : ($tenant->name ?? 'Unknown'),
                    'tenant_phone' => $user ? $user->phone : '',
                    'unit_number' => $unit ? $unit->unit_number : 'N/A',
                    'estate_name' => $estate ? $estate->name : 'N/A',
                    'phone_number' => $recipient->phone_number,
                    'message' => $recipient->message,
                    'status' => $recipient->status,
                    'sent_at' => $recipient->sent_at,
                    'error_message' => $recipient->error_message,
                    'message_id' => $recipient->message_id,
                    'provider_status' => $recipient->provider_status,
                    'provider_response' => $recipient->provider_response,
                    'updated_at' => $recipient->updated_at
                ];
            });
            
            return response()->json([
                'id' => $campaign->id,
                'name' => $campaign->name,
                'description' => $campaign->description,
                'status' => $campaign->status,
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'status_counts' => $statusCounts,
                'recipients' => $recipients,
                'template' => $campaign->template,
                'creator' => $campaign->creator,
                'created_at' => $campaign->created_at,
                'scheduled_at' => $campaign->scheduled_at
            ]);
            
        } catch (\Exception $e) {
            Log::error('Campaign details failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found: ' . $e->getMessage()
            ], 404);
>>>>>>> campaign-v-3
        }
    }

    /**
<<<<<<< HEAD
     * Delete campaign
=======
     * Send a campaign (API endpoint)
     */
    public function send($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            // Check if campaign can be sent
            if (!in_array($campaign->status, ['draft', 'scheduled', 'failed', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign cannot be sent in its current state: ' . $campaign->status
                ], 400);
            }

            if ($campaign->total_recipients == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign has no recipients to send to'
                ], 400);
            }

            $result = $this->campaignService->sendCampaign($campaign->id);
            
            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign is being sent',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Campaign send failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed messages in a campaign (API endpoint)
     */
    public function retry($id)
    {
        try {
            $campaign = SmsCampaign::with(['recipients'])->findOrFail($id);
            
            $failedCount = $campaign->recipients()->where('status', 'failed')->count();
            
            if ($failedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No failed messages to retry'
                ], 400);
            }

            $result = $this->campaignService->retryFailed($campaign->id);
            
            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => "Retrying {$failedCount} failed messages",
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Campaign retry failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified campaign (API endpoint)
>>>>>>> campaign-v-3
     */
    public function destroy($id)
    {
        try {
<<<<<<< HEAD
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
=======
            $campaign = SmsCampaign::whereIn('status', ['draft', 'scheduled', 'failed'])
                ->findOrFail($id);
            
            $campaign->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Campaign delete failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // RESEND FAILED & STATUS SYNC METHODS
    // ============================================

    /**
     * Resend failed messages in a campaign (API endpoint)
     */
    public function resendFailed($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            $failedRecipients = \App\Models\CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'failed')
                ->get();
            
            if ($failedRecipients->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No failed messages to resend'
                ], 400);
            }

            Log::info('Resending failed messages', [
                'campaign_id' => $campaign->id,
                'count' => $failedRecipients->count()
            ]);

            $sent = 0;
            $failed = 0;
            $results = [];

            $template = SmsTemplate::find($campaign->template_id);
            $templateContent = $template ? $template->content : '';
            $smsService = app(\App\Modules\SMS\Services\KenyaSMS::class);

            foreach ($failedRecipients as $recipient) {
                try {
                    $tenant = Tenant::with(['user', 'tenancies.unit'])->find($recipient->tenant_id);
                    $message = $this->campaignService->renderTemplate($templateContent, $tenant);
                    
                    $result = $smsService->sendOne(
                        $recipient->phone_number,
                        $message,
                        'transactional',
                        $campaign->id
                    );
                    
                    if ($result['success']) {
                        $recipient->status = 'sent';
                        $recipient->sent_at = now();
                        $recipient->error_message = null;
                        $recipient->message_id = $result['message_id'] ?? null;
                        $recipient->provider_status = $result['status'] ?? 'sent';
                        $sent++;
                    } else {
                        $recipient->error_message = $result['error'] ?? 'Resend failed';
                        $recipient->provider_status = 'failed';
                        $failed++;
                    }
                    
                    $recipient->save();
                    $results[] = $result;
                    
                } catch (\Exception $e) {
                    Log::error('Resend failed for recipient', [
                        'recipient_id' => $recipient->id,
                        'error' => $e->getMessage()
                    ]);
                    $recipient->error_message = $e->getMessage();
                    $recipient->save();
                    $failed++;
                }
            }

            $campaign->sent_count = \App\Models\CampaignRecipient::where('campaign_id', $campaign->id)
                ->whereIn('status', ['sent', 'delivered'])
                ->count();
            $campaign->failed_count = \App\Models\CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'failed')
                ->count();
            $campaign->save();

            return response()->json([
                'success' => true,
                'message' => "Resent {$sent} messages, {$failed} failed",
                'data' => [
                    'sent' => $sent,
                    'failed' => $failed,
                    'total' => $failedRecipients->count(),
                    'results' => $results
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Resend failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend messages: ' . $e->getMessage()
            ], 500);
>>>>>>> campaign-v-3
        }
    }

    /**
<<<<<<< HEAD
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
=======
     * Sync status for a campaign (API endpoint)
     */
    public function syncStatus($id, SmsStatusService $statusService)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            Log::info('Syncing campaign status', ['campaign_id' => $id]);
            
            $result = $statusService->syncCampaignStatus($campaign->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Status sync completed',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            Log::error('Status sync failed: ' . $e->getMessage(), [
                'campaign_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync status: ' . $e->getMessage()
            ], 500);
>>>>>>> campaign-v-3
        }
    }

    /**
<<<<<<< HEAD
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
=======
     * Sync status for a single recipient (API endpoint)
     */
    public function syncRecipientStatus($id, SmsStatusService $statusService)
    {
        try {
            Log::info('Syncing recipient status', ['recipient_id' => $id]);
            
            $result = $statusService->syncRecipientStatus($id);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Recipient status sync failed: ' . $e->getMessage(), [
                'recipient_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync recipient status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status summary for a campaign (API endpoint)
     */
    public function getStatusSummary($id, SmsStatusService $statusService)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            Log::info('Getting status summary', ['campaign_id' => $id]);
            
            $summary = $statusService->getStatusSummary($campaign->id);
            
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'campaign_name' => $campaign->name,
                'summary' => $summary
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get status summary: ' . $e->getMessage(), [
                'campaign_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status summary: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // PHONE VALIDATION METHODS
    // ============================================

    /**
     * Get invalid recipients for a campaign (API endpoint)
     */
    public function getInvalidRecipients($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            $filters = json_decode($campaign->filters, true) ?? [];
            $invalidRecipients = $this->campaignService->getInvalidRecipients($filters);
            
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'count' => $invalidRecipients->count(),
                'recipients' => $invalidRecipients->map(function($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->user_name ?? $tenant->name ?? 'Unknown',
                        'phone' => $tenant->phone ?? '',
                        'formatted_phone' => $tenant->formatted_phone ?? '',
                        'unit_number' => $tenant->unit_number ?? 'N/A',
                        'estate_name' => $tenant->estate->name ?? 'N/A',
                        'error' => 'Invalid phone number format'
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get invalid recipients: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get invalid recipients: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get other network recipients for a campaign (API endpoint)
     */
    public function getOtherNetworkRecipients($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            $filters = json_decode($campaign->filters, true) ?? [];
            $otherNetworkRecipients = $this->campaignService->getOtherNetworkRecipients($filters);
            
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'count' => $otherNetworkRecipients->count(),
                'recipients' => $otherNetworkRecipients->map(function($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->user_name ?? $tenant->name ?? 'Unknown',
                        'phone' => $tenant->phone ?? '',
                        'formatted_phone' => $tenant->formatted_phone ?? '',
                        'unit_number' => $tenant->unit_number ?? 'N/A',
                        'estate_name' => $tenant->estate->name ?? 'N/A',
                        'error' => 'Other network (Airtel/Telkom)'
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get other network recipients: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get other network recipients: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update phone number for a tenant (API endpoint)
     */
    public function updateTenantPhone(Request $request, $tenantId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string|min:10|max:13',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tenant = Tenant::findOrFail($tenantId);
            $user = $tenant->user;
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for this tenant'
                ], 404);
            }
            
            $phone = \App\Modules\SMS\Helpers\PhoneHelper::clean($request->phone);
            
            if (!$phone || !\App\Modules\SMS\Helpers\PhoneHelper::isValid($phone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number format. Must be a valid Safaricom number (e.g., 0712345678)'
                ], 400);
            }
            
            $user->phone = $phone;
            $user->save();
            
            Log::info('Tenant phone updated', [
                'tenant_id' => $tenantId,
                'new_phone' => $phone
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phone number updated successfully',
                'phone' => $phone,
                'tenant_id' => $tenantId
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update tenant phone: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update phone: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // RESEND INDIVIDUAL RECIPIENT
    // ============================================

    /**
     * Resend an individual recipient (works for pending, failed, queued)
     * No message_id required - this actually sends the SMS
     */
    public function resendIndividualRecipient($id)
    {
        try {
            $recipient = \App\Models\CampaignRecipient::with(['campaign', 'tenant'])->findOrFail($id);
            $campaign = $recipient->campaign;
            
            // Check if recipient can be resent
            if (!in_array($recipient->status, ['failed', 'pending', 'queued'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipient status is ' . $recipient->status . '. Cannot resend.'
                ], 400);
            }
            
            // Check if tenant exists
            if (!$recipient->tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found for this recipient'
                ], 404);
            }
            
            // Get template
            $template = \App\Models\SmsTemplate::find($campaign->template_id);
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found for this campaign'
                ], 404);
            }
            
            $templateContent = $template->content;
            
            // Render message using CampaignService
            $campaignService = app(\App\Services\CampaignService::class);
            $message = $campaignService->renderMessage($templateContent, $recipient->tenant);
            
            Log::info('Resending SMS to individual recipient', [
                'recipient_id' => $recipient->id,
                'phone' => $recipient->phone_number,
                'campaign_id' => $campaign->id,
                'message_length' => strlen($message)
            ]);
            
            // ============================================
            // FIX: Determine valid message type
            // ============================================
            $messageType = ($campaign->campaign_type === 'promotional') ? 'promotional' : 'transactional';
            
            // Send the SMS
            $kenyaSms = app(\App\Modules\SMS\Services\KenyaSMS::class);
            $result = $kenyaSms->sendOne(
                $recipient->phone_number,
                $message,
                $messageType,
                $campaign->id
            );
            
            if ($result['success']) {
                // Update recipient status
                $recipient->status = 'sent';
                $recipient->sent_at = now();
                $recipient->message_id = $result['message_id'] ?? null;
                $recipient->error_message = null;
                $recipient->save();
                
                // Update campaign counts
                $campaign->sent_count = \App\Models\CampaignRecipient::where('campaign_id', $campaign->id)
                    ->whereIn('status', ['sent', 'delivered'])
                    ->count();
                $campaign->failed_count = \App\Models\CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')
                    ->count();
                $campaign->save();
                
                return response()->json([
                    'success' => true,
                    'message' => '✅ SMS resent successfully to ' . $recipient->phone_number,
                    'data' => $result
                ]);
            } else {
                // Update recipient with error
                $recipient->error_message = $result['error'] ?? 'Resend failed';
                $recipient->save();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resend: ' . ($result['error'] ?? 'Unknown error')
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to resend individual recipient: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend: ' . $e->getMessage()
            ], 500);
        }
>>>>>>> campaign-v-3
    }
}