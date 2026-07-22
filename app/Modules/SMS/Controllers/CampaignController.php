<?php

namespace App\Modules\SMS\Controllers;

use App\Http\Controllers\Controller;
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

class CampaignController extends Controller
{
    protected $campaignService;

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
        }
    }

    /**
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
        }
    }

    /**
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
     */
    public function destroy($id)
    {
        try {
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
        }
    }

    /**
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
        }
    }

    /**
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
}