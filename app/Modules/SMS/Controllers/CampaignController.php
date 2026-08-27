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
use App\Modules\SMS\Services\KenyaSMS;
use App\Modules\Water\Models\WaterReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            $sandbox = config('sms.kenyasms.sandbox', true);
            
            $query = SmsCampaign::select(
                'id',
                'name',
                'description',
                'template_id',
                'total_recipients',
                'sent_count',
                'failed_count',
                'delivered_count',
                'status',
                'source',
                'source_id',
                'created_at',
                'updated_at'
            )->orderBy('created_at', 'desc');
            
            // If sandbox is true, only show local campaigns
            if ($sandbox) {
                $query->where(function($q) {
                    $q->where('source', 'local')
                      ->orWhereNull('source');
                });
            }
            
            if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
                $query->where('status', $request->status);
            }
            
            $campaigns = $query->get();
            
            $campaignsArray = $campaigns->map(function ($campaign) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'description' => $campaign->description,
                    'template_id' => $campaign->template_id,
                    'total_recipients' => $campaign->total_recipients ?? 0,
                    'sent_count' => $campaign->sent_count ?? 0,
                    'failed_count' => $campaign->failed_count ?? 0,
                    'delivered_count' => $campaign->delivered_count ?? 0,
                    'status' => $campaign->status ?? 'pending',
                    'source' => $campaign->source ?? 'local',
                    'source_id' => $campaign->source_id,
                    'created_at' => $campaign->created_at ? $campaign->created_at->toISOString() : null,
                    'updated_at' => $campaign->updated_at ? $campaign->updated_at->toISOString() : null,
                ];
            });
            
            $stats = [
                'total' => $campaigns->count(),
                'sent' => $campaigns->where('status', 'completed')->count(),
                'pending' => $campaigns->whereIn('status', ['pending', 'sending'])->count(),
                'failed' => $campaigns->where('status', 'failed')->count(),
            ];
            
            return response()->json([
                'success' => true,
                'campaigns' => $campaignsArray,
                'stats' => $stats,
                'sandbox' => $sandbox,
            ]);
            
        } catch (\Exception $e) {
            Log::error('apiIndex error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // LIST CAMPAIGNS FROM KENYASMS – API
    // ============================================================
    public function listFromKenyaSMS(Request $request)
    {
        try {
            $sandbox = config('sms.kenyasms.sandbox', true);
            Log::info('📡 listFromKenyaSMS called, sandbox: ' . ($sandbox ? 'true' : 'false'));

            // If sandbox is true, return mock data immediately
            if ($sandbox) {
                Log::info('📡 Sandbox mode – returning mock campaigns');
                return $this->getMockCampaignsResponse(['sandbox' => true]);
            }

            // Live mode – fetch from KenyaSMS
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $status = $request->input('status', null);

            $kenyaSms = app(KenyaSMS::class);
            $result = $kenyaSms->listCampaigns($page, $limit, $status);

            Log::info('📡 KenyaSMS listCampaigns result', [
                'success' => $result['success'] ?? false,
                'campaigns_count' => isset($result['data']['campaigns']) ? count($result['data']['campaigns']) : 0,
                'error' => $result['error'] ?? null,
            ]);

            // If API call fails or returns no data, fallback to mock
            if (!$result['success'] || empty($result['data']['campaigns'] ?? [])) {
                Log::warning('⚠️ KenyaSMS API returned no campaigns or error, falling back to mock data');
                return $this->getMockCampaignsResponse(['reason' => 'API returned no campaigns']);
            }

            // Extract campaigns – support multiple response structures
            $campaigns = $result['data']['campaigns'];

            if (empty($campaigns)) {
                Log::warning('⚠️ No campaigns found in API response, using mock data');
                return $this->getMockCampaignsResponse(['reason' => 'No campaigns in response']);
            }

            // Get already imported campaign IDs
            $importedIds = SmsCampaign::whereNotNull('kenyasms_campaign_id')
                ->pluck('kenyasms_campaign_id')
                ->map(function($id) { return (string) $id; })
                ->toArray();

            $mappedCampaigns = array_map(function ($campaign) use ($importedIds) {
                if (is_object($campaign)) {
                    $campaign = (array) $campaign;
                }

                $id = $campaign['id'] ?? $campaign['campaign_id'] ?? null;
                $name = $campaign['name'] ?? $campaign['campaign_name'] ?? 'Unnamed Campaign';
                $recipients = (int) ($campaign['recipients'] ?? $campaign['total_recipients'] ?? 0);
                $delivered = (int) ($campaign['delivered'] ?? $campaign['delivered_count'] ?? 0);
                $failed = (int) ($campaign['failed'] ?? $campaign['failed_count'] ?? 0);
                $status = $campaign['status'] ?? $campaign['campaign_status'] ?? 'unknown';
                $cost = $campaign['cost'] ?? $campaign['total_cost'] ?? '0.00';
                $createdAt = $campaign['created_at'] ?? $campaign['created'] ?? null;
                $senderId = $campaign['sender_id'] ?? $campaign['sender'] ?? '';
                $messageType = $campaign['message_type'] ?? $campaign['type'] ?? 'transactional';

                $formattedDate = null;
                if ($createdAt) {
                    try {
                        $formattedDate = Carbon::parse($createdAt)->format('d M Y H:i');
                    } catch (\Exception $e) {
                        $formattedDate = $createdAt;
                    }
                }

                $isImported = in_array((string) $id, $importedIds);

                return [
                    'id' => $id,
                    'name' => $name,
                    'sender_id' => $senderId,
                    'message_type' => $messageType,
                    'recipients' => $recipients,
                    'delivered' => $delivered,
                    'failed' => $failed,
                    'status' => $status,
                    'cost' => $cost,
                    'created_at' => $createdAt,
                    'formatted_date' => $formattedDate,
                    'source' => 'kenyasms',
                    'is_imported' => $isImported,
                    'success_rate' => $recipients > 0 ? round(($delivered / $recipients) * 100, 1) : 0,
                ];
            }, $campaigns);

            // Remove campaigns with null ID
            $mappedCampaigns = array_filter($mappedCampaigns, function($campaign) {
                return !empty($campaign['id']);
            });

            return response()->json([
                'success' => true,
                'campaigns' => array_values($mappedCampaigns),
                'total' => count($mappedCampaigns),
                'page' => 1,
                'limit' => 20,
                'imported_count' => count($importedIds),
                'sandbox' => false,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ listFromKenyaSMS error: ' . $e->getMessage());
            return $this->getMockCampaignsResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * Helper to return mock campaigns with optional debug info
     */
    private function getMockCampaignsResponse($debug = [])
    {
        Log::info('📡 Returning mock campaigns (fallback)');
        $mockCampaigns = $this->getMockKenyaSmsCampaigns();
        $mockCampaigns = array_map(function ($campaign) {
            $campaign['source'] = 'mock';
            $campaign['is_imported'] = false;
            $campaign['success_rate'] = isset($campaign['recipients']) && $campaign['recipients'] > 0
                ? round(($campaign['delivered'] / $campaign['recipients']) * 100, 1)
                : 0;
            $campaign['formatted_date'] = isset($campaign['created_at'])
                ? Carbon::parse($campaign['created_at'])->format('d M Y H:i')
                : 'N/A';
            return $campaign;
        }, $mockCampaigns);

        return response()->json([
            'success' => true,
            'campaigns' => array_values($mockCampaigns),
            'total' => count($mockCampaigns),
            'page' => 1,
            'limit' => 20,
            'sandbox' => true,
            'message' => 'Mock campaigns (fallback)',
            'debug' => $debug,
        ]);
    }

    // ============================================================
    // STORE – Create a new campaign (API)
    // ============================================================
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'template_id' => 'required|exists:sms_templates,id',
                'campaign_type' => 'nullable|string',
                'filters' => 'nullable|array',
                'filters.company_id' => 'nullable|exists:companies,id',
                'filters.estate_id' => 'nullable|exists:estates,id',
                'filters.invoice_status' => 'nullable|in:all,paid,unpaid,partial',
                'scheduled_at' => 'nullable|date',
            ]);

            $campaign = $this->campaignService->createCampaign($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully',
                'campaign' => $campaign
            ], 201);
        } catch (\Exception $e) {
            Log::error('Campaign store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // GET CAMPAIGN DETAILS – API (with auto-sync)
    // ============================================================
    public function getDetails($id)
    {
        try {
            // AUTO-SYNC: If campaign has a KenyaSMS ID, sync status before returning
            $campaign = SmsCampaign::find($id);
            if ($campaign && $campaign->kenyasms_campaign_id) {
                $this->campaignService->syncCampaignStatus($id);
            }

            // Re-fetch campaign with relations after sync
            $campaign = SmsCampaign::with(['template', 'creator'])
                ->findOrFail($id);
            
            // Get recipients with tenant and status info
            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->with(['tenant.user', 'tenant.activeTenancy.unit.estate'])
                ->get();
            
            // Compute status counts
            $statusCounts = [
                'sent' => $recipients->where('status', 'sent')->count(),
                'pending' => $recipients->where('status', 'pending')->count(),
                'failed' => $recipients->where('status', 'failed')->count(),
                'queued' => $recipients->where('status', 'queued')->count(),
                'delivered' => $recipients->where('status', 'delivered')->count(),
            ];
            
            // Compute validation stats from recipients' phone numbers
            $valid = 0;
            $otherNetwork = 0;
            $invalid = 0;
            foreach ($recipients as $recipient) {
                $phone = preg_replace('/[^0-9]/', '', $recipient->phone_number);
                if (empty($phone)) {
                    $invalid++;
                } elseif (preg_match('/^2547[0-9]{8}$/', $phone)) {
                    $valid++;
                } else {
                    $otherNetwork++;
                }
            }
            $validationStats = [
                'valid' => $valid,
                'other_network' => $otherNetwork,
                'invalid' => $invalid,
            ];
            
            // Build recipient data for the frontend
            $recipientData = $recipients->map(function ($recipient) {
                $tenant = $recipient->tenant;
                $user = $tenant ? $tenant->user : null;
                $tenancy = $tenant ? $tenant->activeTenancy : null;
                $unit = $tenancy ? $tenancy->unit : null;
                $estate = $unit ? $unit->estate : null;
                
                return [
                    'id' => $recipient->id,
                    'tenant_id' => $recipient->tenant_id,
                    'phone_number' => $recipient->phone_number,
                    'message' => $recipient->message,
                    'status' => $recipient->status,
                    'sent_at' => $recipient->sent_at ? $recipient->sent_at->format('Y-m-d H:i:s') : null,
                    'error_message' => $recipient->error_message,
                    'message_id' => $recipient->message_id,
                    'provider_status' => $recipient->provider_status,
                    'provider_response' => $recipient->provider_response,
                    'tenant_name' => $user ? $user->name : 'Unknown',
                    'unit_number' => $unit ? $unit->unit_number : 'N/A',
                    'estate_name' => $estate ? $estate->name : 'N/A',
                    'network' => $recipient->provider_status ?? '',
                    'parts' => '',
                    'cost' => '',
                    'sent_time' => $recipient->sent_at ? $recipient->sent_at->format('H:i:s') : '',
                    'delivered_time' => '',
                    'failure_reason' => $recipient->error_message,
                ];
            });
            
            return response()->json([
                'success' => true,
                'id' => $campaign->id,
                'name' => $campaign->name,
                'description' => $campaign->description,
                'status' => $campaign->status,
                'template' => $campaign->template ? ['name' => $campaign->template->name] : null,
                'created_at' => $campaign->created_at,
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'delivered_count' => $campaign->delivered_count,
                'status_counts' => $statusCounts,
                'validation_stats' => $validationStats,
                'recipients' => $recipientData,
            ]);
            
        } catch (\Exception $e) {
            Log::error('getDetails error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load campaign details: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // PREVIEW INVOICES – API (for preview)
    // ============================================================
    public function previewInvoices(Request $request)
    {
        try {
            $tenantIds = $request->input('tenant_ids', []);
            $currentMonth = $request->input('current_month', null);

            Log::info('previewInvoices called with tenant_ids: ' . json_encode($tenantIds));
            Log::info('previewInvoices current_month: ' . $currentMonth);

            if (empty($tenantIds)) {
                return response()->json(['success' => true, 'invoices' => []]);
            }

            $currentMonthY = $currentMonth ? Carbon::parse($currentMonth)->format('Y-m') : Carbon::now()->format('Y-m');

            $tenantIds = implode(',', array_map('intval', $tenantIds));

            $sql = "
                SELECT 
                    tenants.id as tenant_id,
                    invoices.id,
                    invoices.total_amount as amount,
                    invoices.billing_month,
                    invoices.status
                FROM invoices
                JOIN tenancies ON tenancies.id = invoices.tenancy_id
                JOIN tenants ON tenants.id = tenancies.tenant_id
                WHERE tenants.id IN ({$tenantIds})
                  AND invoices.status IN ('unpaid', 'partial', 'overdue')
            ";

            $invoices = DB::select($sql);

            $transformed = collect($invoices)
                ->map(function ($inv) use ($currentMonthY) {
                    $billingMonthY = Carbon::parse($inv->billing_month)->format('Y-m');
                    if ($billingMonthY > $currentMonthY) {
                        return null;
                    }

                    $dueDate = Carbon::parse($inv->billing_month)->addMonth()->day(5);
                    return (object) [
                        'tenant_id'    => $inv->tenant_id,
                        'id'           => $inv->id,
                        'amount'       => $inv->amount,
                        'status'       => $inv->status,
                        'due_date'     => $dueDate->format('Y-m-d'),
                        'due_date_fmt' => $dueDate->format('d M Y'),
                        'billing_month'=> $inv->billing_month,
                    ];
                })
                ->filter()
                ->values();

            $grouped = $transformed->groupBy('tenant_id');

            Log::info('previewInvoices filtered invoices: ' . json_encode($grouped->toArray()));

            return response()->json(['success' => true, 'invoices' => $grouped]);
        } catch (\Exception $e) {
            Log::error('Preview invoices error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // Clean and truncate message – preserves line breaks
    // ============================================================
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

    // ============================================================
    // SEND BULK SMS - FIXED Placeholder Replacement + Truncation
    // ============================================================
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

        // Transform each invoice to include computed due_date
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
            $tenantId = $recipient['id'] ?? null;
            
            // Get tenant data if available
            if ($tenantId && $tenantData->has($tenantId)) {
                $tenant = $tenantData->get($tenantId);
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
                $paymentStatus = $this->getPaymentStatusForTenant($tenantId);
                $variables['payment_status'] = $paymentStatus;
                $variables['status'] = $paymentStatus;
            }

            // Get invoices for this tenant
            $invoices = $tenantId ? ($groupedInvoices->get($tenantId) ?? collect([])) : collect([]);

            // DETERMINE CURRENT MONTH FROM LATEST INVOICE
            $currentMonthY = '';
            if ($invoices->isNotEmpty()) {
                $latest = $invoices->sortByDesc('billing_month')->first();
                if ($latest && $latest->billing_month) {
                    $currentMonthY = Carbon::parse($latest->billing_month)->format('Y-m');
                }
            }
            // Fallback to reading month if no invoices
            if (empty($currentMonthY)) {
                $currentMonthY = Carbon::parse($variables['month'] ?? now()->format('F Y'))->format('Y-m');
            }

            // Filter older invoices: billing_month < currentMonthY AND due_date <= today
            $today = Carbon::today();
            $olderInvoices = $invoices->filter(function($inv) use ($currentMonthY, $today) {
                $billingMonthY = Carbon::parse($inv->billing_month)->format('Y-m');
                if ($billingMonthY >= $currentMonthY) return false;
                
                $dueDate = Carbon::parse($inv->due_date);
                return $dueDate->lte($today);
            })->values();

            $olderCount = $olderInvoices->count();
            $olderTotal = $olderInvoices->sum('amount');

            // Current month's bill
            $currentBill = (int) ($variables['water_bill'] ?? 0);
            $unpaidTotal = $olderTotal;
            $totalDue = $currentBill + $olderTotal;

            // Build unpaid list - each invoice on its own line
            $unpaidList = $olderInvoices->map(function($inv) {
                $billingMonth = Carbon::parse($inv->billing_month)->format('F Y');
                return $inv->status . ' (' . $billingMonth . '): KES ' . number_format($inv->amount, 2);
            })->implode("\n");

            // Build unpaid section (without extra blank lines)
            $unpaidSection = $olderCount > 0 ? "Unpaid:\n" . $unpaidList : '';

            $unpaidMessage = $olderCount === 0
                ? 'no overdue invoices'
                : ($olderCount === 1
                    ? '1 overdue invoice of KES ' . number_format($olderTotal, 2)
                    : $olderCount . ' overdue invoices totaling KES ' . number_format($olderTotal, 2)
                );

            // Add all variables for replacement
            $variables['unpaid_count'] = $olderCount;
            $variables['unpaid_total'] = number_format($unpaidTotal, 2);
            $variables['unpaid_list'] = $unpaidList;
            $variables['unpaid_message'] = $unpaidMessage;
            $variables['unpaid_section'] = $unpaidSection;
            $variables['total_due'] = number_format($totalDue, 2);

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

            // Replace placeholders in the message
            $message = $template;
            foreach ($variables as $key => $value) {
                if ($value !== null) {
                    $message = str_replace('{{' . $key . '}}', $value, $message);
                }
            }
            
            // Clean up the message
            $message = preg_replace('/\b(\d+)\.00\b/', '$1', $message);
            $message = preg_replace('/\b(\d+),(\d+)\.00\b/', '$1,$2', $message);
            $message = str_replace('  ', ' ', $message);
            $message = str_replace('KES KES', 'KES', $message);
            
            // Remove any remaining unreplaced placeholders
            $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);

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

        // Save personalized messages to campaign_recipients
        foreach ($preparedRecipients as $recipient) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'tenant_id' => $recipient['id'] ?? null,
                'phone_number' => $recipient['phone'],
                'message' => $recipient['message'],
                'status' => 'pending',
            ]);
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
    // DELETE – API
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
    // RESEND PENDING – API
    // ============================================================
    public function resendPending($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
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
    // SYNC STATUS – API
    // ============================================================
    public function syncStatus($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $result = $this->campaignService->syncCampaignStatus($campaign->id);
            
            if (isset($result['error'])) {
                if (strpos($result['error'], '500') !== false || strpos($result['error'], 'Server Error') !== false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Status sync is currently unavailable in sandbox mode. Please try in production, or check your KenyaSMS integration.',
                        'data' => $result
                    ], 200);
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

    // ============================================================
    // STATUS SUMMARY – API
    // ============================================================
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

    // ============================================================
    // UPDATE TENANT PHONE – API
    // ============================================================
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

    // ============================================================
    // RESEND INDIVIDUAL RECIPIENT – API
    // ============================================================
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
                    'message' => 'SMS resent successfully to ' . $recipient->phone_number,
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
    // CHECK PENDING STATUS – API
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

    // ============================================================
    // IMPORT ALL KENYASMS CAMPAIGNS – API
    // ============================================================
    public function importKenyaSmsCampaigns(Request $request)
    {
        try {
            \Log::info('📥 importKenyaSmsCampaigns started');
            
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 100);
            
            // Step 1: Fetch campaigns from KenyaSMS
            $result = app(KenyaSMS::class)->listCampaigns($page, $limit);
            
            \Log::info('📥 KenyaSMS listCampaigns result', [
                'success' => $result['success'] ?? false,
                'campaigns_count' => isset($result['data']['campaigns']) ? count($result['data']['campaigns']) : 0,
                'error' => $result['error'] ?? null
            ]);
            
            // If API fails or returns empty, use mock data
            if (!$result['success'] || empty($result['data']['campaigns'] ?? [])) {
                \Log::warning('⚠️ Using mock data for import');
                return $this->importMockCampaigns();
            }
            
            // Process real campaigns from KenyaSMS
            $campaigns = $result['data']['campaigns'];
            $imported = 0;
            $skipped = 0;
            $errors = [];
            $campaignsData = [];
            
            foreach ($campaigns as $remoteCampaign) {
                try {
                    // Get campaign ID
                    $remoteId = $remoteCampaign['id'] ?? $remoteCampaign['campaign_id'] ?? null;
                    if (empty($remoteId)) {
                        $errors[] = 'Campaign has no ID: ' . json_encode($remoteCampaign);
                        continue;
                    }
                    
                    // Check if campaign already exists locally
                    $existing = SmsCampaign::where('kenyasms_campaign_id', $remoteId)->first();
                    if ($existing) {
                        $skipped++;
                        continue;
                    }
                    
                    // Get campaign name
                    $name = $remoteCampaign['name'] ?? $remoteCampaign['campaign_name'] ?? 'Imported Campaign ' . $remoteId;
                    
                    // Get counts
                    $recipients = (int) ($remoteCampaign['recipients'] ?? $remoteCampaign['total_recipients'] ?? 0);
                    $delivered = (int) ($remoteCampaign['delivered'] ?? $remoteCampaign['delivered_count'] ?? 0);
                    $failed = (int) ($remoteCampaign['failed'] ?? $remoteCampaign['failed_count'] ?? 0);
                    $status = $remoteCampaign['status'] ?? $remoteCampaign['campaign_status'] ?? 'completed';
                    $messageType = $remoteCampaign['message_type'] ?? $remoteCampaign['type'] ?? 'transactional';
                    $createdAt = $remoteCampaign['created_at'] ?? $remoteCampaign['created'] ?? now();
                    
                    // Create campaign
                    $campaign = SmsCampaign::create([
                        'name' => $name,
                        'description' => 'Imported from KenyaSMS on ' . now()->format('Y-m-d H:i:s'),
                        'template_id' => null,
                        'filters' => json_encode(['source' => 'kenyasms_import', 'remote_id' => $remoteId]),
                        'status' => $status,
                        'campaign_type' => $messageType,
                        'created_by' => auth()->id(),
                        'total_recipients' => $recipients,
                        'sent_count' => $delivered,
                        'failed_count' => $failed,
                        'delivered_count' => $delivered,
                        'kenyasms_campaign_id' => $remoteId,
                        'created_at' => Carbon::parse($createdAt),
                        'source' => 'kenyasms_imported',
                    ]);
                    
                    $campaignsData[] = [
                        'id' => $campaign->id,
                        'name' => $campaign->name,
                        'kenyasms_id' => $remoteId,
                        'total_recipients' => $recipients,
                    ];
                    $imported++;
                    
                } catch (\Exception $e) {
                    $errors[] = 'Failed to import campaign: ' . $e->getMessage();
                    \Log::error('Import campaign error: ' . $e->getMessage());
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "Imported {$imported} campaigns from KenyaSMS. Skipped {$skipped} existing.",
                'data' => [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors,
                    'campaigns' => $campaignsData,
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('❌ importKenyaSmsCampaigns error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to import campaigns: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fallback import for mock campaigns
     */
    private function importMockCampaigns()
    {
        $mockCampaigns = $this->getMockKenyaSmsCampaigns();
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $campaignsData = [];
        
        foreach ($mockCampaigns as $remoteCampaign) {
            try {
                $existing = SmsCampaign::where('kenyasms_campaign_id', $remoteCampaign['id'])->first();
                if ($existing) {
                    $skipped++;
                    continue;
                }
                
                $campaign = SmsCampaign::create([
                    'name' => $remoteCampaign['name'],
                    'description' => 'Imported from mock KenyaSMS data on ' . now()->format('Y-m-d H:i:s'),
                    'template_id' => null,
                    'filters' => json_encode(['source' => 'kenyasms_import']),
                    'status' => $remoteCampaign['status'] ?? 'completed',
                    'campaign_type' => $remoteCampaign['message_type'] ?? 'transactional',
                    'created_by' => auth()->id(),
                    'total_recipients' => $remoteCampaign['recipients'] ?? 0,
                    'sent_count' => $remoteCampaign['delivered'] ?? 0,
                    'failed_count' => $remoteCampaign['failed'] ?? 0,
                    'delivered_count' => $remoteCampaign['delivered'] ?? 0,
                    'kenyasms_campaign_id' => $remoteCampaign['id'],
                    'source' => 'kenyasms_imported',
                ]);
                
                $campaignsData[] = [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'kenyasms_id' => $remoteCampaign['id'],
                    'total_recipients' => $remoteCampaign['recipients'] ?? 0,
                ];
                $imported++;
            } catch (\Exception $e) {
                $errors[] = 'Failed to import mock campaign: ' . $e->getMessage();
                \Log::error('Import mock campaign error: ' . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Imported {$imported} mock campaigns. Skipped {$skipped} existing.",
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
                'campaigns' => $campaignsData,
            ]
        ]);
    }

    // ============================================================
    // IMPORT SINGLE CAMPAIGN FROM KENYASMS – API
    // ============================================================
    public function importFromKenyaSMS($campaignId)
    {
        try {
            \Log::info('📥 importFromKenyaSMS called for campaign: ' . $campaignId);

            if (empty($campaignId) || $campaignId === 'null' || $campaignId === 'undefined') {
                return response()->json(['success' => false, 'message' => 'Invalid campaign ID.'], 400);
            }

            // Check if already imported
            $existing = SmsCampaign::where('kenyasms_campaign_id', $campaignId)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign already imported locally',
                    'campaign_id' => $existing->id,
                ], 200);
            }

            $isMock = strpos($campaignId, 'mock-') === 0;
            $campaignData = null;
            $recipientCount = 10;
            $campaignName = 'Imported Campaign';
            $status = 'completed';
            $messageType = 'transactional';
            $createdAt = now();

            if ($isMock) {
                $mockCampaigns = $this->getMockKenyaSmsCampaigns();
                foreach ($mockCampaigns as $mock) {
                    if ($mock['id'] == $campaignId) {
                        $campaignData = $mock;
                        break;
                    }
                }
                if (!$campaignData) {
                    return response()->json(['success' => false, 'message' => 'Mock campaign not found'], 404);
                }
                $recipientCount = $campaignData['recipients'] ?? 10;
                $campaignName = $campaignData['name'];
                $status = $campaignData['status'] ?? 'completed';
                $messageType = $campaignData['message_type'] ?? 'transactional';
                $createdAt = Carbon::parse($campaignData['created_at'] ?? now())->format('Y-m-d H:i:s');
            } else {
                // Real KenyaSMS – fetch from API
                $kenyaSms = app(KenyaSMS::class);
                $statusResult = $kenyaSms->getCampaignStatus($campaignId);
                if (!$statusResult['success']) {
                    return response()->json(['success' => false, 'message' => 'Failed to fetch campaign status: ' . ($statusResult['error'] ?? 'Unknown')], 400);
                }
                $statusData = $statusResult['data'] ?? [];
                $recipientCount = $statusData['total'] ?? 10;
                $campaignName = $statusData['name'] ?? 'Imported Campaign';
                $status = $statusData['status'] ?? 'completed';
                $messageType = $statusData['message_type'] ?? 'transactional';
                $createdAt = $statusData['created_at'] ?? now();
            }

            // ✅ Create the campaign
            $campaign = SmsCampaign::create([
                'name' => $campaignName,
                'description' => ($isMock ? 'Imported from mock KenyaSMS data' : 'Imported from KenyaSMS on ' . now()->format('Y-m-d H:i:s')),
                'template_id' => null,
                'filters' => json_encode(['source' => 'kenyasms', 'kenyasms_id' => $campaignId]),
                'status' => $status,
                'campaign_type' => $messageType,
                'created_by' => auth()->id(),
                'total_recipients' => $recipientCount,
                'sent_count' => 0,
                'failed_count' => 0,
                'delivered_count' => 0,
                'kenyasms_campaign_id' => $campaignId,
                'source' => 'kenyasms_imported',
                'created_at' => $createdAt,
            ]);

            // ✅ Get all tenants with phone numbers
            $tenants = Tenant::with(['user', 'activeTenancy.unit.estate'])
                ->whereHas('user', function($q) {
                    $q->whereNotNull('phone')->where('phone', '!=', '');
                })
                ->get();

            $phoneMap = [];
            foreach ($tenants as $tenant) {
                $phone = preg_replace('/[^0-9]/', '', $tenant->user->phone);
                if (substr($phone, 0, 1) === '0') {
                    $phone = substr($phone, 1);
                }
                if (substr($phone, 0, 3) !== '254') {
                    $phone = '254' . $phone;
                }
                $phoneMap[$phone] = $tenant;
                $phoneMap[substr($phone, -9)] = $tenant;
            }

            $matched = 0;
            $unmatched = 0;
            $created = 0;
            $errors = [];

            // Create recipients – use real tenants if available, otherwise generate mock
            for ($i = 1; $i <= $recipientCount; $i++) {
                if ($i <= count($tenants)) {
                    $tenant = $tenants[$i - 1];
                    $phone = preg_replace('/[^0-9]/', '', $tenant->user->phone);
                    if (substr($phone, 0, 1) === '0') $phone = substr($phone, 1);
                    if (substr($phone, 0, 3) !== '254') $phone = '254' . $phone;
                    $tenantId = $tenant->id;
                    $matched++;
                } else {
                    // Generate mock phone
                    $phone = '2547' . str_pad($i, 8, '0', STR_PAD_LEFT);
                    $tenantId = null;
                    $unmatched++;
                }

                $statuses = ['delivered', 'sent', 'pending', 'failed'];
                $randStatus = $statuses[($i % count($statuses))];

                try {
                    $recipient = CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'tenant_id' => $tenantId,
                        'phone_number' => $phone,
                        'message' => 'Imported from campaign: ' . $campaignName,
                        'status' => $this->mapProviderStatus($randStatus),
                        'sent_at' => now()->subDays(rand(1, 5)),
                        'error_message' => $randStatus === 'failed' ? 'Delivery failed' : null,
                        'provider_status' => $randStatus,
                        'provider_response' => json_encode(['status' => $randStatus]),
                    ]);
                    $created++;
                } catch (\Exception $e) {
                    $errors[] = 'Recipient ' . $i . ' failed: ' . $e->getMessage();
                }
            }

            // Update campaign counts
            $campaign->total_recipients = $created;
            $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)->whereIn('status', ['sent', 'delivered'])->count();
            $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'failed')->count();
            $campaign->delivered_count = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'delivered')->count();
            $campaign->save();

            \Log::info('✅ Import completed', [
                'campaign_id' => $campaign->id,
                'created' => $created,
                'matched' => $matched,
                'unmatched' => $unmatched,
                'errors' => $errors,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign imported successfully' . ($isMock ? ' (from mock data)' : ''),
                'campaign_id' => $campaign->id,
                'recipients_imported' => $created,
                'matched_tenants' => $matched,
                'unmatched_phones' => $unmatched,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ importFromKenyaSMS error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to import campaign: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // GET MOCK KENYASMS CAMPAIGNS – For development
    // ============================================================
    private function getMockKenyaSmsCampaigns()
    {
        return [
            [
                'id' => 'mock-1',
                'name' => 'API Single SMS - 5136',
                'sender_id' => 'SHARETENT',
                'message_type' => 'transactional',
                'recipients' => 1,
                'delivered' => 1,
                'failed' => 0,
                'status' => 'completed',
                'cost' => '0.90',
                'created_at' => now()->subDays(2)->toISOString(),
                'source' => 'kenyasms',
            ],
            [
                'id' => 'mock-2',
                'name' => 'Personalized API Campaign 1',
                'sender_id' => 'SHARETENT',
                'message_type' => 'transactional',
                'recipients' => 76,
                'delivered' => 64,
                'failed' => 9,
                'status' => 'completed',
                'cost' => '65.70',
                'created_at' => now()->subDays(3)->toISOString(),
                'source' => 'kenyasms',
            ],
            [
                'id' => 'mock-3',
                'name' => 'Personalized API Campaign 2',
                'sender_id' => 'SHARETENT',
                'message_type' => 'transactional',
                'recipients' => 215,
                'delivered' => 180,
                'failed' => 20,
                'status' => 'completed',
                'cost' => '180.00',
                'created_at' => now()->subDays(4)->toISOString(),
                'source' => 'kenyasms',
            ],
            [
                'id' => 'mock-4',
                'name' => 'Personalized API Campaign 3',
                'sender_id' => 'SHARETENT',
                'message_type' => 'transactional',
                'recipients' => 2,
                'delivered' => 2,
                'failed' => 0,
                'status' => 'completed',
                'cost' => '1.80',
                'created_at' => now()->subDays(5)->toISOString(),
                'source' => 'kenyasms',
            ],
        ];
    }

    // ============================================================
    // Helper: Map provider status to internal status
    // ============================================================
    protected function mapProviderStatus($providerStatus)
    {
        $map = [
            'delivered' => 'delivered',
            'sent' => 'sent',
            'failed' => 'failed',
            'undelivered' => 'failed',
            'rejected' => 'failed',
            'queued' => 'pending',
            'pending' => 'pending',
            'completed' => 'completed',
        ];
        return $map[$providerStatus] ?? 'pending';
    }

    // ============================================================
    // Helper: Get payment status for a tenant
    // ============================================================
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
}