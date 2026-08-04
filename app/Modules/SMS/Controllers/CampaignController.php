<?php

namespace App\Modules\SMS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SMS\Models\SmsCampaign;
use App\Models\SmsTemplate;
use App\Models\Estate;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\CampaignRecipient;
use App\Modules\SMS\Services\CampaignService;
use App\Modules\SMS\Services\SmsStatusService;
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

    // ============================================================
    // INDEX – HTML view (no AJAX)
    // ============================================================
    public function index(Request $request)
    {
        $query = SmsCampaign::with(['template', 'creator'])
            ->orderBy('created_at', 'desc');
        
        if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
            $query->where('status', $request->status);
        }
        
        $campaigns = $query->paginate(20);
        
        return view('sms.campaigns.index', compact('campaigns'));
    }

    // ============================================================
    // API INDEX – returns JSON (used by broadcast tab)
    // ============================================================
    public function apiIndex(Request $request)
    {
        try {
            $query = SmsCampaign::with(['template', 'creator'])
                ->orderBy('created_at', 'desc');
            
            if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
                $query->where('status', $request->status);
            }
            
            $campaigns = $query->get();
            $stats = $this->calculateStats($campaigns);
            
            return response()->json([
                'success' => true,
                'campaigns' => $campaigns,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // STATS HELPER
    // ============================================================
    private function calculateStats($campaigns)
    {
        $stats = [
            'total' => $campaigns->count(),
            'sent' => 0,
            'pending' => 0,
            'failed' => 0
        ];

        foreach ($campaigns as $campaign) {
            if ($campaign->status === 'completed') {
                $stats['sent']++;
            } else if ($campaign->status === 'pending' || $campaign->status === 'sending') {
                $stats['pending']++;
            } else if ($campaign->status === 'failed') {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    // ============================================================
    // CREATE – Web view
    // ============================================================
    public function create()
    {
        $templates = SmsTemplate::all();
        $estates = Estate::all();
        $companies = Company::all();
        
        return view('sms.campaigns.create', compact('templates', 'estates', 'companies'));
    }

    // ============================================================
    // STORE – API
    // ============================================================
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
            
            $recipientsResult = $this->campaignService->getRecipientsWithValidation($request->filters ?? []);
            
            $campaignData = [
                'name' => $request->name,
                'description' => $request->description,
                'template_id' => $request->template_id,
                'filters' => $request->filters ?? [],
                'status' => 'pending',
                'scheduled_at' => $request->scheduled_at,
                'created_by' => auth()->id(),
                'campaign_type' => $request->campaign_type ?? 'general',
            ];

            Log::info('Creating campaign with validation data', [
                'valid' => count($recipientsResult['valid']),
                'invalid' => count($recipientsResult['invalid']),
                'other_network' => count($recipientsResult['other_network'])
            ]);

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

    // ============================================================
    // PREVIEW – API
    // ============================================================
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
            
            if (!empty($request->filters['estate_id'])) {
                $query->where('units.estate_id', $request->filters['estate_id']);
            }
            
            if (!empty($request->filters['company_id'])) {
                $query->where('units.company_id', $request->filters['company_id']);
            }
            
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
                $phone = preg_replace('/[^0-9]/', '', $tenant->phone ?? '');
                $isValid = preg_match('/^(07|01|2547|2541)\d{8}$/', $phone) && strlen($phone) >= 10;
                
                if ($isValid) {
                    $valid++;
                } else {
                    $invalid++;
                }
                
                $tenancy = $tenant->tenancies->first();
                $unit = $tenancy ? $tenancy->unit : null;
                $reading = $unit ? $unit->waterReadings->first() : null;
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

    // ============================================================
    // SHOW – Web view
    // ============================================================
    public function show($id)
    {
        $campaign = SmsCampaign::with(['recipients.tenant', 'template', 'creator'])
            ->findOrFail($id);
        return view('sms.campaigns.show', compact('campaign'));
    }

    // ============================================================
// GET DETAILS – API (for AJAX)
// ============================================================
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
        
        // Status counts
        $statusCounts = [
            'sent' => $campaign->recipients->where('status', 'sent')->count(),
            'pending' => $campaign->recipients->where('status', 'pending')->count(),
            'failed' => $campaign->recipients->where('status', 'failed')->count(),
            'delivered' => $campaign->delivered_count ?? 0,
        ];
        
        // Build recipient data, parsing provider_response
        $recipients = $campaign->recipients->map(function($recipient) {
            $tenant = $recipient->tenant;
            $user = $tenant ? $tenant->user : null;
            $tenancy = $tenant ? $tenant->tenancies->first() : null;
            $unit = $tenancy ? $tenancy->unit : null;
            $estate = $unit ? $unit->estate : null;
            
            // Decode provider_response JSON
            $providerData = json_decode($recipient->provider_response, true) ?? [];
            
            // Format cost
            $costDisplay = null;
            if (isset($providerData['cost_kes'])) {
                $costDisplay = number_format($providerData['cost_kes'], 2) . ' KES';
            } elseif (isset($providerData['cost'])) {
                $costDisplay = $providerData['cost'] . ' SMS';
            }
            
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
                'updated_at' => $recipient->updated_at,
                
                // Delivery report fields – corrected mapping
                'network' => $providerData['network'] ?? null,
                'parts' => $providerData['parts'] ?? null,
                'cost' => $costDisplay,
                'sent_time' => $providerData['sent_at'] ?? null,
                'delivered_time' => $providerData['delivered_at'] ?? null,
                'failure_reason' => $providerData['error_code'] ?? null,
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
            'delivered_count' => $campaign->delivered_count,
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

    // ============================================================
    // SEND – API
    // ============================================================
    public function send($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            if (!in_array($campaign->status, ['pending', 'failed'])) {
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

    // ============================================================
    // DUPLICATE – API
    // ============================================================
    public function duplicate($id)
    {
        try {
            $campaign = SmsCampaign::with('recipients')->findOrFail($id);
            
            $newCampaign = $campaign->replicate();
            $newCampaign->name = $campaign->name . ' (Copy)';
            $newCampaign->status = 'pending';
            $newCampaign->sent_count = 0;
            $newCampaign->failed_count = 0;
            $newCampaign->created_by = auth()->id();
            $newCampaign->save();

            foreach ($campaign->recipients as $recipient) {
                $newRecipient = $recipient->replicate();
                $newRecipient->campaign_id = $newCampaign->id;
                $newRecipient->status = 'pending';
                $newRecipient->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Campaign duplicated successfully',
                'campaign' => $newCampaign
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to duplicate campaign: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // EXPORT – CSV download
    // ============================================================
    public function export($id)
    {
        try {
            $campaign = SmsCampaign::with('recipients')->findOrFail($id);
            $filename = 'campaign_' . $campaign->id . '_' . date('Y-m-d') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function() use ($campaign) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Phone', 'Message', 'Status', 'Sent At', 'Error']);

                foreach ($campaign->recipients as $recipient) {
                    fputcsv($handle, [
                        $recipient->phone_number,
                        $recipient->message,
                        $recipient->status,
                        $recipient->sent_at,
                        $recipient->error_message,
                    ]);
                }
                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
            
        } catch (\Exception $e) {
            Log::error('Failed to export campaign: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export campaign');
        }
    }

    // ============================================================
    // RETRY FAILED – API
    // ============================================================
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

    // ============================================================
    // DELETE – API (FIXED – now allows any status)
    // ============================================================
    public function destroy($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
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

    // ============================================================
    // RESEND FAILED – API
    // ============================================================
    public function resendFailed($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            $failedRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
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

            $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'sent')
                ->count();
            $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
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

 // ============================================================
// RESEND PENDING – API (Uses new personalized endpoint)
// ============================================================
public function resendPending($id)
{
    // 🔵 ADD THIS LINE TO CONFIRM THE CONTROLLER IS CALLED
    Log::info('🚀 Step 2: resendPending controller called for campaign: ' . $id);

    try {
        $campaign = SmsCampaign::findOrFail($id);
        
        // Check if there are pending recipients
        $pendingCount = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();
        
        if ($pendingCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No pending messages to resend.'
            ], 400);
        }
        
        $result = $this->campaignService->resendPending($campaign->id);
        
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => "Resent {$result['sent']} pending messages",
            'data' => $result
        ]);
        
    } catch (\Exception $e) {
        Log::error('Resend pending failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to resend pending messages: ' . $e->getMessage()
        ], 500);
    }
}
   // ============================================================
// STATUS SYNC – API (Uses new KenyaSMS campaign sync)
// ============================================================
public function syncStatus($id)
{
    try {
        $campaign = SmsCampaign::findOrFail($id);
        $result = $this->campaignService->syncCampaignStatus($campaign->id);
        
        if (isset($result['error'])) {
            // If the error is a 500 from KenyaSMS, return a friendly message
            if (strpos($result['error'], '500') !== false || strpos($result['error'], 'Server Error') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status sync is currently unavailable in sandbox mode. Please try in production, or check your KenyaSMS integration.',
                    'data' => $result
                ], 200); // Return 200 so the UI doesn't show a hard error
            }
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Status sync completed',
            'data' => $result
        ]);
    } catch (\Exception $e) {
        Log::error('Status sync failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to sync status: ' . $e->getMessage()
        ], 500);
    }
}

    public function getStatusSummary($id, SmsStatusService $statusService)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $summary = $statusService->getStatusSummary($campaign->id);
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'campaign_name' => $campaign->name,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get status summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status summary: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // PHONE VALIDATION – API
    // ============================================================
    public function getInvalidRecipients($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $filters = json_decode($campaign->filters, true) ?? [];
            $invalidRecipients = $this->campaignService->getInvalidRecipients($filters);
            
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'count' => count($invalidRecipients),
                'recipients' => $invalidRecipients
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get invalid recipients: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get invalid recipients: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getOtherNetworkRecipients($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $filters = json_decode($campaign->filters, true) ?? [];
            $otherNetworkRecipients = $this->campaignService->getOtherNetworkRecipients($filters);
            
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'count' => count($otherNetworkRecipients),
                'recipients' => $otherNetworkRecipients
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get other network recipients: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get other network recipients: ' . $e->getMessage()
            ], 500);
        }
    }

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

    public function resendIndividualRecipient($id)
    {
        try {
            $recipient = CampaignRecipient::with(['campaign', 'tenant'])->findOrFail($id);
            $campaign = $recipient->campaign;
            
            if (!in_array($recipient->status, ['failed', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recipient status is ' . $recipient->status . '. Cannot resend.'
                ], 400);
            }
            
            if (!$recipient->tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant not found for this recipient'
                ], 404);
            }
            
            $template = SmsTemplate::find($campaign->template_id);
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found for this campaign'
                ], 404);
            }
            
            $templateContent = $template->content;
            $campaignService = app(CampaignService::class);
            $message = $campaignService->renderMessage($templateContent, $recipient->tenant);
            
            Log::info('Resending SMS to individual recipient', [
                'recipient_id' => $recipient->id,
                'phone' => $recipient->phone_number,
                'campaign_id' => $campaign->id,
                'message_length' => strlen($message)
            ]);
            
            $messageType = ($campaign->campaign_type === 'promotional') ? 'promotional' : 'transactional';
            $kenyaSms = app(\App\Modules\SMS\Services\KenyaSMS::class);
            $result = $kenyaSms->sendOne(
                $recipient->phone_number,
                $message,
                $messageType,
                $campaign->id
            );
            
            if ($result['success']) {
                $recipient->status = 'sent';
                $recipient->sent_at = now();
                $recipient->message_id = $result['message_id'] ?? null;
                $recipient->error_message = null;
                $recipient->save();
                
                $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'sent')
                    ->count();
                $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')
                    ->count();
                $campaign->save();
                
                return response()->json([
                    'success' => true,
                    'message' => '✅ SMS resent successfully to ' . $recipient->phone_number,
                    'data' => $result
                ]);
            } else {
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
    }

    // ============================================================
    // CHECK PENDING STATUS – API (NEW)
    // ============================================================
    public function checkPendingStatus($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $result = $this->campaignService->checkPendingStatus($campaign->id);
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Check pending status failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check pending status: ' . $e->getMessage()
            ], 500);
        }
    }
}