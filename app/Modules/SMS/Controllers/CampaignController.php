<?php

namespace App\Modules\SMS\Controllers;

use App\Jobs\ImportKenyaSmsCampaignsJob;
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
                'kenyasms_campaign_id',
                'created_at',
                'updated_at'
            )->orderBy('created_at', 'desc');

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
                    'kenyasms_campaign_id' => $campaign->kenyasms_campaign_id,
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

            if ($sandbox) {
                Log::info('📡 Sandbox mode – returning mock campaigns');
                return $this->getMockCampaignsResponse(['sandbox' => true]);
            }

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

            if (!$result['success'] || empty($result['data']['campaigns'] ?? [])) {
                Log::warning('⚠️ KenyaSMS API returned no campaigns or error, falling back to mock data');
                return $this->getMockCampaignsResponse(['reason' => 'API returned no campaigns']);
            }

            $campaigns = $result['data']['campaigns'];
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
            Log::error($e->getTraceAsString());

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
    // GET CAMPAIGN DETAILS – API (with debug logging)
    // ============================================================
    public function getDetails($id)
    {
        try {
            Log::info('🔍 getDetails called for campaign: ' . $id);

            $campaign = SmsCampaign::find($id);
            if ($campaign && $campaign->kenyasms_campaign_id) {
                $this->campaignService->syncCampaignStatus($id);
            }

            $campaign = SmsCampaign::with(['template', 'creator'])
                ->findOrFail($id);

            Log::info('📦 Campaign found', [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'source' => $campaign->source,
                'template_id' => $campaign->template_id,
            ]);

            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->with(['tenant.user', 'tenant.activeTenancy.unit.estate'])
                ->get();

            Log::info('📋 Found ' . $recipients->count() . ' recipients');

            $template = $campaign->template;
            $templateContent = $template ? $template->content : null;

            Log::info('📝 Template content present: ' . ($templateContent ? 'YES' : 'NO'));

            $isImported = ($campaign->source === 'kenyasms_imported' || strpos($campaign->source ?? '', 'import') !== false);

            Log::info('🏷️ Is imported: ' . ($isImported ? 'YES' : 'NO'));

            // Compute status counts and validation stats
            $statusCounts = [
                'sent' => $recipients->where('status', 'sent')->count(),
                'pending' => $recipients->where('status', 'pending')->count(),
                'failed' => $recipients->where('status', 'failed')->count(),
                'queued' => $recipients->where('status', 'queued')->count(),
                'delivered' => $recipients->where('status', 'delivered')->count(),
            ];

            $validationStats = [
                'valid' => $recipients->filter(function($r) {
                    $phone = preg_replace('/[^0-9]/', '', $r->phone_number);
                    return !empty($phone) && preg_match('/^2547[0-9]{8}$/', $phone);
                })->count(),
                'other_network' => $recipients->filter(function($r) {
                    $phone = preg_replace('/[^0-9]/', '', $r->phone_number);
                    return !empty($phone) && !preg_match('/^2547[0-9]{8}$/', $phone);
                })->count(),
                'invalid' => $recipients->filter(function($r) {
                    $phone = preg_replace('/[^0-9]/', '', $r->phone_number);
                    return empty($phone);
                })->count(),
            ];

            $recipientData = $recipients->map(function ($recipient) use ($templateContent, $isImported) {
                $tenant = $recipient->tenant;
                $message = $recipient->message;
                $regenerated = false;

                Log::info('🔄 Processing recipient ID ' . $recipient->id, [
                    'tenant_id' => $recipient->tenant_id,
                    'phone' => $recipient->phone_number,
                    'message' => $message,
                    'has_tenant' => $tenant ? 'YES' : 'NO',
                    'has_template' => $templateContent ? 'YES' : 'NO',
                ]);

                // If tenant_id is null, try to match by phone number
                if (!$tenant && !empty($recipient->phone_number)) {
                    $phone = preg_replace('/[^0-9]/', '', $recipient->phone_number);
                    if (substr($phone, 0, 1) === '0') $phone = substr($phone, 1);
                    if (substr($phone, 0, 3) !== '254') $phone = '254' . $phone;

                    $user = \App\Models\User::where('phone', 'like', '%' . substr($phone, -9))
                        ->orWhere('phone', $phone)
                        ->first();

                    if ($user) {
                        $tenant = Tenant::where('user_id', $user->id)->first();
                        if ($tenant) {
                            $recipient->tenant_id = $tenant->id;
                            $recipient->save();
                            $tenant->load(['user', 'activeTenancy.unit.estate']);
                        }
                    }
                }

                // --- Regenerate message if possible ---
                if ($templateContent && $tenant) {
                    $storedMessage = trim($message ?? '');
                    $shouldRegenerate = $isImported 
                        || strpos($storedMessage, 'Imported') !== false 
                        || empty($storedMessage) 
                        || strpos($storedMessage, '{{') !== false;

                    Log::info('🔁 Should regenerate? ' . ($shouldRegenerate ? 'YES' : 'NO'), [
                        'isImported' => $isImported,
                        'contains_Imported' => strpos($storedMessage, 'Imported') !== false,
                        'empty' => empty($storedMessage),
                        'contains_placeholder' => strpos($storedMessage, '{{') !== false,
                    ]);

                    if ($shouldRegenerate) {
                        try {
                            $placeholders = $this->campaignService->buildPlaceholders($tenant);
                            $newMessage = $templateContent;
                            foreach ($placeholders as $key => $value) {
                                if ($value !== null) {
                                    $newMessage = str_replace('{{' . $key . '}}', $value, $newMessage);
                                }
                            }
                            $newMessage = preg_replace('/\{\{[^}]*\}\}/', '', $newMessage);
                            $newMessage = $this->cleanAndTruncateMessage($newMessage);

                            Log::info('📝 Regenerated message', [
                                'old' => $message,
                                'new' => $newMessage,
                            ]);

                            if (!empty($newMessage) && $newMessage !== $message) {
                                $message = $newMessage;
                                $regenerated = true;
                                $recipient->message = $message;
                                $recipient->save();
                                Log::info('💾 Saved regenerated message for recipient ' . $recipient->id);
                            } else {
                                Log::info('⏭️ New message is empty or same as old, skipping save');
                            }
                        } catch (\Exception $e) {
                            Log::error('❌ Failed to regenerate message: ' . $e->getMessage(), [
                                'recipient_id' => $recipient->id,
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }
                } else {
                    Log::info('⏭️ Skipping regeneration: template or tenant missing');
                }

                $user = $tenant ? $tenant->user : null;
                $tenancy = $tenant ? $tenant->activeTenancy : null;
                $unit = $tenancy ? $tenancy->unit : null;
                $estate = $unit ? $unit->estate : null;

                return [
                    'id' => $recipient->id,
                    'tenant_id' => $recipient->tenant_id,
                    'phone_number' => $recipient->phone_number,
                    'message' => $message,
                    'status' => $recipient->status,
                    'sent_at' => $recipient->sent_at ? $recipient->sent_at->format('Y-m-d H:i:s') : null,
                    'error_message' => $recipient->error_message,
                    'failure_reason' => $recipient->failure_reason,
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
                    'regenerated' => $regenerated,
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

            $invoices = $tenantId ? ($groupedInvoices->get($tenantId) ?? collect([])) : collect([]);

            $currentMonthY = '';
            if ($invoices->isNotEmpty()) {
                $latest = $invoices->sortByDesc('billing_month')->first();
                if ($latest && $latest->billing_month) {
                    $currentMonthY = Carbon::parse($latest->billing_month)->format('Y-m');
                }
            }
            if (empty($currentMonthY)) {
                $currentMonthY = Carbon::parse($variables['month'] ?? now()->format('F Y'))->format('Y-m');
            }

            $today = Carbon::today();
            $olderInvoices = $invoices->filter(function($inv) use ($currentMonthY, $today) {
                $billingMonthY = Carbon::parse($inv->billing_month)->format('Y-m');
                if ($billingMonthY >= $currentMonthY) return false;
                
                $dueDate = Carbon::parse($inv->due_date);
                return $dueDate->lte($today);
            })->values();

            $olderCount = $olderInvoices->count();
            $olderTotal = $olderInvoices->sum('amount');

            $currentBill = (int) ($variables['water_bill'] ?? 0);
            $unpaidTotal = $olderTotal;
            $totalDue = $currentBill + $olderTotal;

            $unpaidList = $olderInvoices->map(function($inv) {
                $billingMonth = Carbon::parse($inv->billing_month)->format('F Y');
                return $inv->status . ' (' . $billingMonth . '): KES ' . number_format($inv->amount, 2);
            })->implode("\n");

            $unpaidSection = $olderCount > 0 ? "Unpaid bills:\n" . $unpaidList . "\n" : '';

            $unpaidMessage = $olderCount === 0
                ? 'no overdue invoices'
                : ($olderCount === 1
                    ? '1 overdue invoice of KES ' . number_format($olderTotal, 2)
                    : $olderCount . ' overdue invoices totaling KES ' . number_format($olderTotal, 2)
                );

            $variables['unpaid_count'] = $olderCount;
            $variables['unpaid_total'] = number_format($unpaidTotal, 2);
            $variables['unpaid_list'] = $unpaidList;
            $variables['unpaid_message'] = $unpaidMessage;
            $variables['unpaid_section'] = $unpaidSection;
            $variables['total_due'] = number_format($totalDue, 2);

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

            $message = $template;
            foreach ($variables as $key => $value) {
                if ($value !== null) {
                    $message = str_replace('{{' . $key . '}}', $value, $message);
                }
            }
            
            $message = preg_replace('/\b(\d+)\.00\b/', '$1', $message);
            $message = preg_replace('/\b(\d+),(\d+)\.00\b/', '$1,$2', $message);
            $message = str_replace('  ', ' ', $message);
            $message = str_replace('KES KES', 'KES', $message);
            $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);

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
    // RESEND FAILED – API (WITH BATCH SUPPORT & FALLBACK MESSAGE)
    // ============================================================
    public function resendFailed($id, Request $request)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $batchSize = (int) $request->input('batch_size', 50);

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
            $skipped = 0;
            $smsService = app(KenyaSMS::class);

            foreach ($failedRecipients->chunk($batchSize) as $batch) {
                foreach ($batch as $recipient) {
                    // Validate phone
                    $phone = $smsService->formatPhoneNumber($recipient->phone_number);
                    if (empty($phone)) {
                        $recipient->error_message = 'Invalid phone number';
                        $recipient->save();
                        $skipped++;
                        continue;
                    }

                    // Get message
                    $message = null;

                    // 1. Stored message
                    if (!empty(trim($recipient->message))) {
                        $message = $recipient->message;
                    }

                    // 2. Render from template if available
                    if (empty(trim($message)) && $campaign->template_id) {
                        $template = SmsTemplate::find($campaign->template_id);
                        if ($template && $recipient->tenant_id) {
                            $tenant = Tenant::with(['user', 'tenancies.unit'])->find($recipient->tenant_id);
                            if ($tenant) {
                                $message = $this->campaignService->renderTemplate($template->content, $tenant);
                            }
                        }
                    }

                    // 3. Fallback default
                    if (empty(trim($message))) {
                        $unitNumber = $recipient->tenant?->activeTenancy?->unit?->unit_number ?? 'N/A';
                        $message = "Your payment is due. Paybill: 7263733 Acc: {$unitNumber}";
                    }

                    try {
                        $result = $smsService->sendOne($phone, $message, 'transactional', $campaign->id);

                        if ($result['success']) {
                            $recipient->status = 'sent';
                            $recipient->sent_at = now();
                            $recipient->error_message = null;
                            $recipient->message_id = $result['message_id'] ?? null;
                            if (empty(trim($recipient->message))) {
                                $recipient->message = $message;
                            }
                            $sent++;
                        } else {
                            $recipient->error_message = $result['error'] ?? 'Resend failed';
                            $failed++;
                        }
                        $recipient->save();

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

                // Update campaign counts after batch
                $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'sent')
                    ->count();
                $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')
                    ->count();
                $campaign->save();

                usleep(500000); // 0.5 sec pause
            }

            return response()->json([
                'success' => true,
                'message' => "Resent {$sent} messages, {$failed} failed, {$skipped} skipped.",
                'data' => compact('sent', 'failed', 'skipped')
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
    // RESEND PENDING – API (WITH BATCH SUPPORT & FALLBACK MESSAGE)
    // ============================================================
    public function resendPending($id, Request $request)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $batchSize = (int) $request->input('batch_size', 50);

            $pendingRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->get();

            if ($pendingRecipients->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending messages to resend.'
                ], 400);
            }

            Log::info('Resending pending messages', [
                'campaign_id' => $campaign->id,
                'count' => $pendingRecipients->count()
            ]);

            $sent = 0;
            $failed = 0;
            $skipped = 0;
            $smsService = app(KenyaSMS::class);

            foreach ($pendingRecipients->chunk($batchSize) as $batch) {
                foreach ($batch as $recipient) {
                    // Validate phone
                    $phone = $smsService->formatPhoneNumber($recipient->phone_number);
                    if (empty($phone)) {
                        $recipient->error_message = 'Invalid phone number';
                        $recipient->save();
                        $skipped++;
                        continue;
                    }

                    // Get message
                    $message = null;

                    if (!empty(trim($recipient->message))) {
                        $message = $recipient->message;
                    }

                    if (empty(trim($message)) && $campaign->template_id) {
                        $template = SmsTemplate::find($campaign->template_id);
                        if ($template && $recipient->tenant_id) {
                            $tenant = Tenant::with(['user', 'tenancies.unit'])->find($recipient->tenant_id);
                            if ($tenant) {
                                $message = $this->campaignService->renderTemplate($template->content, $tenant);
                            }
                        }
                    }

                    if (empty(trim($message))) {
                        $unitNumber = $recipient->tenant?->activeTenancy?->unit?->unit_number ?? 'N/A';
                        $message = "Your payment is due. Paybill: 7263733 Acc: {$unitNumber}";
                    }

                    try {
                        $result = $smsService->sendOne($phone, $message, 'transactional', $campaign->id);

                        if ($result['success']) {
                            $recipient->status = 'sent';
                            $recipient->sent_at = now();
                            $recipient->error_message = null;
                            $recipient->message_id = $result['message_id'] ?? null;
                            if (empty(trim($recipient->message))) {
                                $recipient->message = $message;
                            }
                            $sent++;
                        } else {
                            $recipient->error_message = $result['error'] ?? 'Resend failed';
                            $failed++;
                        }
                        $recipient->save();

                    } catch (\Exception $e) {
                        Log::error('Resend pending failed for recipient', [
                            'recipient_id' => $recipient->id,
                            'error' => $e->getMessage()
                        ]);
                        $recipient->error_message = $e->getMessage();
                        $recipient->save();
                        $failed++;
                    }
                }

                // Update campaign counts after batch
                $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'sent')
                    ->count();
                $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')
                    ->count();
                $campaign->save();

                usleep(500000);
            }

            return response()->json([
                'success' => true,
                'message' => "Resent {$sent} pending messages, {$failed} failed, {$skipped} skipped.",
                'data' => compact('sent', 'failed', 'skipped')
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
    // SYNC STATUS – API (with sandbox fallback)
    // ============================================================
    public function syncStatus($id)
    {
        try {
            $sandbox = config('sms.kenyasms.sandbox', true);

            if ($sandbox) {
                Log::info('Status sync skipped – sandbox mode');
                return response()->json([
                    'success' => true,
                    'message' => 'Sandbox mode: Status sync simulated.',
                    'data' => [
                        'sent' => 0,
                        'failed' => 0,
                        'delivered' => 0,
                        'status' => 'pending',
                        'synced' => 0,
                        'status_changes' => []
                    ]
                ]);
            }

            $campaign = SmsCampaign::findOrFail($id);

            // ✅ Skip mock campaigns
            if ($campaign->kenyasms_campaign_id && strpos($campaign->kenyasms_campaign_id, 'mock-') === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is a mock campaign (not a real KenyaSMS campaign). Sync is not available.'
                ], 200);
            }

            $result = $this->campaignService->syncCampaignStatus($campaign->id);

            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sync error: ' . $result['error'],
                    'data' => $result
                ], 200);
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
    // PHONE VALIDATION – API (FIXED: uses campaign recipients directly)
    // ============================================================
    public function getInvalidRecipients($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->with(['tenant.user', 'tenant.activeTenancy.unit.estate'])
                ->get();
            
            $invalid = [];
            foreach ($recipients as $recipient) {
                $phone = preg_replace('/[^0-9]/', '', $recipient->phone_number);
                // Invalid if empty or not a valid Safaricom number (2547...)
                if (empty($phone) || !preg_match('/^2547[0-9]{8}$/', $phone)) {
                    $invalid[] = [
                        'id' => $recipient->id,
                        'tenant_id' => $recipient->tenant_id,
                        'name' => $recipient->tenant?->user?->name ?? 'Unknown',
                        'phone' => $recipient->phone_number,
                        'unit_number' => $recipient->tenant?->activeTenancy?->unit?->unit_number ?? 'N/A',
                        'estate_name' => $recipient->tenant?->activeTenancy?->unit?->estate?->name ?? 'N/A',
                        'error' => empty($phone) ? 'Missing phone number' : 'Invalid Safaricom number',
                        'status' => $recipient->status,
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'count' => count($invalid),
                'recipients' => $invalid
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
            
            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->with(['tenant.user', 'tenant.activeTenancy.unit.estate'])
                ->get();
            
            $other = [];
            foreach ($recipients as $recipient) {
                $phone = preg_replace('/[^0-9]/', '', $recipient->phone_number);
                // Other network: not empty, not Safaricom (2547...), but could be 254... or other
                if (!empty($phone) && !preg_match('/^2547[0-9]{8}$/', $phone)) {
                    $other[] = [
                        'id' => $recipient->id,
                        'tenant_id' => $recipient->tenant_id,
                        'name' => $recipient->tenant?->user?->name ?? 'Unknown',
                        'phone' => $recipient->phone_number,
                        'unit_number' => $recipient->tenant?->activeTenancy?->unit?->unit_number ?? 'N/A',
                        'estate_name' => $recipient->tenant?->activeTenancy?->unit?->estate?->name ?? 'N/A',
                        'error' => 'Other network (Airtel/Telkom)',
                        'status' => $recipient->status,
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'count' => count($other),
                'recipients' => $other
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

            // Validate phone
            $smsService = app(KenyaSMS::class);
            $phone = $smsService->formatPhoneNumber($recipient->phone_number);
            if (empty($phone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number'
                ], 400);
            }

            // --- Get message (regenerate if needed) ---
            $message = null;

            // 1. Try stored message
            if (!empty(trim($recipient->message))) {
                $message = $recipient->message;
            }

            // 2. If stored message is missing or generic "Imported", render from template
            if (empty(trim($message)) || strpos($message, 'Imported') !== false) {
                if ($campaign->template_id) {
                    $template = SmsTemplate::find($campaign->template_id);
                    if ($template && $recipient->tenant) {
                        $message = $this->campaignService->renderMessage($template->content, $recipient->tenant);
                    }
                }
            }

            // 3. If still empty, set a fallback default message
            if (empty(trim($message))) {
                $unitNumber = $recipient->tenant?->activeTenancy?->unit?->unit_number ?? 'N/A';
                $message = "Your payment is due. Paybill: 7263733 Acc: {$unitNumber}";
                Log::info('Using fallback message for resend', [
                    'recipient_id' => $recipient->id,
                    'phone' => $phone,
                    'message' => $message
                ]);
            }

            // ✅ Always update the stored message with the regenerated one
            $recipient->message = $message;
            $recipient->save();

            // Send the message
            $messageType = ($campaign->campaign_type === 'promotional') ? 'promotional' : 'transactional';
            $result = $smsService->sendOne(
                $phone,
                $message,
                $messageType,
                $campaign->id
            );

            if ($result['success']) {
                $recipient->status = 'sent';
                $recipient->sent_at = now();
                $recipient->message_id = $result['message_id'] ?? null;
                $recipient->error_message = null;
                $recipient->failure_reason = null;
                $recipient->save();

                // Update campaign counts
                $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'sent')
                    ->count();
                $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'failed')
                    ->count();
                $campaign->save();

                return response()->json([
                    'success' => true,
                    'message' => 'SMS resent successfully to ' . $phone,
                    'data' => $result
                ]);
            } else {
                $recipient->error_message = $result['error'] ?? 'Resend failed';
                $recipient->failure_reason = $result['error'] ?? 'Resend failed';
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

/**
 * Import all campaigns from KenyaSMS (dispatched to queue).
 */
public function importKenyaSmsCampaigns(Request $request)
{
    try {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 100);
        
        // Dispatch the job to the queue
        ImportKenyaSmsCampaignsJob::dispatch($page, $limit);
        
        return response()->json([
            'success' => true,
            'message' => 'Import started in background. You will be notified when completed.',
            'data' => [
                'queued' => true,
                'page' => $page,
                'limit' => $limit,
            ]
        ]);
        
    } catch (\Exception $e) {
        \Log::error('❌ Failed to dispatch import job: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to start import: ' . $e->getMessage(),
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
            $remoteId = $remoteCampaign['id'];
            $existing = SmsCampaign::where('kenyasms_campaign_id', $remoteId)->first();
            if ($existing) {
                $skipped++;
                continue;
            }
            
            // Extract mock data
            $name = $remoteCampaign['name'];
            $recipients = $remoteCampaign['recipients'] ?? 0;
            $delivered = $remoteCampaign['delivered'] ?? 0;
            $failed = $remoteCampaign['failed'] ?? 0;
            $status = $remoteCampaign['status'] ?? 'completed';
            $messageType = $remoteCampaign['message_type'] ?? 'transactional';
            $createdAt = $remoteCampaign['created_at'] ?? now();
            
            $campaign = SmsCampaign::create([
                'name' => $name,
                'description' => 'Imported from mock KenyaSMS data on ' . now()->format('Y-m-d H:i:s'),
                'template_id' => null,
                'filters' => json_encode(['source' => 'kenyasms_import']),
                'status' => $status,
                'campaign_type' => $messageType,
                'created_by' => auth()->id(),
                'total_recipients' => $recipients,
                'sent_count' => 0,
                'failed_count' => 0,
                'delivered_count' => 0,
                'kenyasms_campaign_id' => $remoteId,
                'source' => 'kenyasms_imported',
                'created_at' => Carbon::parse($createdAt),
            ]);
            
            // Sync mock recipients with exact delivered/failed counts
            $syncResult = $this->fetchAndSyncRecipientsFromKenyaSMS($campaign, $remoteId, true, $delivered, $failed);
            
            // Update counts
            $campaign->total_recipients = CampaignRecipient::where('campaign_id', $campaign->id)->count();
            $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->whereIn('status', ['sent', 'delivered'])->count();
            $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'failed')->count();
            $campaign->delivered_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'delivered')->count();
            $campaign->save();
            
            $campaignsData[] = [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'kenyasms_id' => $remoteId,
                'total_recipients' => $campaign->total_recipients,
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
/**
 * Import a single campaign from KenyaSMS and sync its recipients.
 */
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
        $delivered = 0;
        $failed = 0;

        // Fetch campaign metadata
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
            $delivered = $campaignData['delivered'] ?? 0;
            $failed = $campaignData['failed'] ?? 0;
        } else {
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
            $delivered = $statusData['delivered'] ?? 0;
            $failed = $statusData['failed'] ?? 0;
        }

        // Create the campaign (without recipients yet)
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

        \Log::info('✅ Campaign created with ID: ' . $campaign->id);

        // Now sync recipients from KenyaSMS (or mock) with the actual delivered/failed counts
        $syncResult = $this->fetchAndSyncRecipientsFromKenyaSMS($campaign, $campaignId, $isMock, $delivered, $failed);

        // Update campaign counts based on synced recipients
        $campaign->total_recipients = CampaignRecipient::where('campaign_id', $campaign->id)->count();
        $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)->whereIn('status', ['sent', 'delivered'])->count();
        $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'failed')->count();
        $campaign->delivered_count = CampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'delivered')->count();
        $campaign->save();

        return response()->json([
            'success' => true,
            'message' => 'Campaign imported successfully with ' . $syncResult['created'] . ' recipients',
            'campaign_id' => $campaign->id,
            'recipients_imported' => $syncResult['created'],
            'matched_tenants' => $syncResult['matched'],
            'unmatched_phones' => $syncResult['unmatched'],
            'errors' => $syncResult['errors'] ?? [],
        ]);

    } catch (\Exception $e) {
        \Log::error('❌ importFromKenyaSMS error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to import campaign: ' . $e->getMessage(),
        ], 500);
    }
}

/**
 * Fetch recipients from KenyaSMS and sync them to the campaign.
 * For mock campaigns, generate recipients with statuses matching the provided counts.
 */
private function fetchAndSyncRecipientsFromKenyaSMS($campaign, $kenyaSmsCampaignId, $isMock = false, $deliveredCount = 0, $failedCount = 0)
{
    $created = 0;
    $matched = 0;
    $unmatched = 0;
    $errors = [];

    // Mock failure reasons for testing
    $mockFailureReasons = [
        'Network error: Delivery failed',
        'Invalid phone number format',
        'Number not reachable',
        'Insufficient balance on sender account',
        'Message rejected by network provider',
        'Recipient phone switched off',
        'Message expired in queue',
        'Spam filter blocked message',
        'Invalid sender ID configured',
        'Request timed out',
    ];

    if ($isMock) {
        // Use the campaign's total recipients (or fallback)
        $totalRecipients = $campaign->total_recipients ?: 10;
        // Ensure delivered + failed do not exceed total
        $deliveredCount = min($deliveredCount, $totalRecipients);
        $failedCount = min($failedCount, $totalRecipients - $deliveredCount);
        $pendingCount = $totalRecipients - $deliveredCount - $failedCount;

        // Build a status array with exact counts
        $statuses = [];
        for ($i = 0; $i < $deliveredCount; $i++) {
            $statuses[] = 'delivered';
        }
        for ($i = 0; $i < $failedCount; $i++) {
            $statuses[] = 'failed';
        }
        for ($i = 0; $i < $pendingCount; $i++) {
            $statuses[] = 'pending';
        }
        // Shuffle to randomize order
        shuffle($statuses);

        // Shuffle failure reasons for variety
        $mockFailureReasons = array_slice($mockFailureReasons, 0, $failedCount);
        shuffle($mockFailureReasons);

        $tenants = Tenant::with(['user', 'activeTenancy.unit.estate'])
            ->whereHas('user', function($q) {
                $q->whereNotNull('phone')->where('phone', '!=', '');
            })
            ->get();

        $failureIndex = 0;
        for ($i = 1; $i <= $totalRecipients; $i++) {
            if ($i <= count($tenants)) {
                $tenant = $tenants[$i - 1];
                $phone = preg_replace('/[^0-9]/', '', $tenant->user->phone);
                if (substr($phone, 0, 1) === '0') $phone = substr($phone, 1);
                if (substr($phone, 0, 3) !== '254') $phone = '254' . $phone;
                $tenantId = $tenant->id;
                $matched++;
            } else {
                $phone = '2547' . str_pad($i, 8, '0', STR_PAD_LEFT);
                $tenantId = null;
                $unmatched++;
            }

            // Get status from the shuffled array
            $randStatus = $statuses[$i - 1] ?? 'pending';
            
            // Get a realistic failure reason for failed statuses
            $errorReason = null;
            if ($randStatus === 'failed') {
                $errorReason = $mockFailureReasons[$failureIndex % count($mockFailureReasons)] ?? 'Unknown error occurred';
                $failureIndex++;
            }
            
            $internalStatus = $this->mapProviderStatus($randStatus);

            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'tenant_id' => $tenantId,
                'phone_number' => $phone,
                'message' => 'Imported from mock campaign: ' . $campaign->name,
                'status' => $internalStatus,
                'sent_at' => ($randStatus === 'delivered' || $randStatus === 'sent') ? now()->subDays(rand(1, 5)) : null,
                'error_message' => $errorReason,
                'failure_reason' => $errorReason,
                'provider_status' => $randStatus,
                'provider_response' => json_encode(['status' => $randStatus, 'error' => $errorReason]),
            ]);
            $created++;
        }
    } else {
        // Real KenyaSMS: fetch logs
        $kenyaSms = app(KenyaSMS::class);
        $logsResult = $kenyaSms->getCampaignLogs($kenyaSmsCampaignId);

        if (!$logsResult['success']) {
            throw new \Exception('Failed to fetch logs: ' . ($logsResult['error'] ?? 'Unknown error'));
        }

        $logs = $logsResult['logs'] ?? [];

        if (empty($logs)) {
            throw new \Exception('No logs found for this campaign.');
        }

        foreach ($logs as $log) {
            $phone = $log['recipient'] ?? '';
            if (empty($phone)) {
                $errors[] = 'Skipped log with empty phone: ' . json_encode($log);
                continue;
            }

            // Clean phone
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (substr($phone, 0, 1) === '0') $phone = substr($phone, 1);
            if (substr($phone, 0, 3) !== '254') $phone = '254' . $phone;

            // Find tenant by phone
            $tenant = Tenant::whereHas('user', function($q) use ($phone) {
                $q->where('phone', 'like', '%' . substr($phone, -9));
            })->with(['user', 'activeTenancy.unit.estate'])->first();

            $tenantId = $tenant ? $tenant->id : null;
            if ($tenantId) $matched++; else $unmatched++;

            // Map status
            $internalStatus = $this->mapProviderStatus($log['status'] ?? 'pending');

            // --- IMPROVED ERROR EXTRACTION ---
            $errorReason = null;
            if ($internalStatus === 'failed' || in_array($log['status'], ['failed', 'cancelled', 'undelivered', 'rejected', 'expired'])) {
                // Try multiple possible field names from KenyaSMS logs
                $possibleErrorFields = [
                    'error',
                    'error_message',
                    'failure_reason',
                    'reason',
                    'status_description',
                    'description',
                    'message',
                    'details',
                    'error_description',
                    'failure_description',
                ];
                
                foreach ($possibleErrorFields as $field) {
                    if (!empty($log[$field])) {
                        $errorReason = is_array($log[$field]) 
                            ? json_encode($log[$field]) 
                            : (string) $log[$field];
                        break;
                    }
                }
                
                // If no error found, use a generic message
                if (empty($errorReason)) {
                    $errorReason = 'Delivery failed (provider status: ' . ($log['status'] ?? 'unknown') . ')';
                }
                
                // Truncate if too long
                if (strlen($errorReason) > 500) {
                    $errorReason = substr($errorReason, 0, 497) . '...';
                }
            }

            // Check if recipient already exists (should not, but just in case)
            $recipient = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('phone_number', $phone)
                ->first();

            if ($recipient) {
                $recipient->tenant_id = $tenantId;
                $recipient->status = $internalStatus;
                $recipient->sent_at = isset($log['sent']) ? Carbon::parse($log['sent']) : null;
                $recipient->error_message = $errorReason;
                $recipient->failure_reason = $errorReason;
                $recipient->provider_status = $log['status'] ?? null;
                $recipient->provider_response = json_encode($log);
                $recipient->save();
            } else {
                CampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $tenantId,
                    'phone_number' => $phone,
                    'message' => null,
                    'status' => $internalStatus,
                    'sent_at' => isset($log['sent']) ? Carbon::parse($log['sent']) : null,
                    'error_message' => $errorReason,
                    'failure_reason' => $errorReason,
                    'provider_status' => $log['status'] ?? null,
                    'provider_response' => json_encode($log),
                ]);
                $created++;
            }
        }
    }

    return [
        'created' => $created,
        'matched' => $matched,
        'unmatched' => $unmatched,
        'errors' => $errors,
    ];
}

    // ============================================================
    // SYNC RECIPIENTS FROM KENYASMS – API (with Sandbox & Mock support)
    // ============================================================
    public function syncRecipientsFromKenyaSMS($id)
    {
        try {
            $campaign = SmsCampaign::findOrFail($id);

            if (!$campaign->kenyasms_campaign_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign has no KenyaSMS campaign ID.'
                ], 400);
            }

            // Check if this is a sandbox or mock campaign
            $kenyaId = $campaign->kenyasms_campaign_id;
            $isSandbox = (strpos($kenyaId, 'sandbox-') === 0 || strpos($kenyaId, 'mock-') === 0);

            if ($isSandbox) {
                // For sandbox/mock, generate mock recipients from existing local data
                $recipients = CampaignRecipient::where('campaign_id', $campaign->id)->get();
                $updated = 0;
                $skipped = 0;

                $statuses = ['pending', 'sent', 'delivered', 'failed'];
                foreach ($recipients as $recipient) {
                    $newStatus = $statuses[array_rand($statuses)];
                    $recipient->status = $newStatus;
                    $recipient->provider_status = $newStatus;
                    if ($newStatus === 'failed') {
                        $recipient->error_message = 'Mock failure (sandbox mode)';
                        $recipient->failure_reason = 'Mock failure (sandbox mode)';
                    } else {
                        $recipient->error_message = null;
                        $recipient->failure_reason = null;
                    }
                    $recipient->save();
                    $updated++;
                }

                // Recalculate campaign totals
                $campaign->total_recipients = $recipients->count();
                $campaign->sent_count = $recipients->whereIn('status', ['sent', 'delivered'])->count();
                $campaign->failed_count = $recipients->where('status', 'failed')->count();
                $campaign->delivered_count = $recipients->where('status', 'delivered')->count();
                $campaign->save();

                return response()->json([
                    'success' => true,
                    'message' => "Sandbox/Mock mode: Synced {$updated} recipients with random statuses.",
                    'data' => [
                        'created' => 0,
                        'updated' => $updated,
                        'skipped' => 0,
                        'sandbox' => true,
                    ]
                ]);
            }

            // If NOT sandbox/mock, proceed with real API call
            $logsResult = app(KenyaSMS::class)->getCampaignLogs($campaign->kenyasms_campaign_id);
            if (!$logsResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch logs: ' . ($logsResult['error'] ?? 'Unknown error')
                ], 400);
            }

            $logs = $logsResult['logs'] ?? [];

            if (empty($logs)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No logs found for this campaign in KenyaSMS.'
                ], 404);
            }

            // Get the campaign template
            $template = SmsTemplate::find($campaign->template_id);
            $templateContent = $template ? $template->content : '';

            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($logs as $log) {
                $phone = $log['recipient'] ?? '';
                if (empty($phone)) {
                    $skipped++;
                    continue;
                }

                // Clean phone number
                $phone = preg_replace('/[^0-9]/', '', $phone);
                if (substr($phone, 0, 1) === '0') {
                    $phone = substr($phone, 1);
                }
                if (substr($phone, 0, 3) !== '254') {
                    $phone = '254' . $phone;
                }

                // Find tenant by phone
                $tenant = Tenant::whereHas('user', function($q) use ($phone) {
                    $q->where('phone', 'like', '%' . substr($phone, -9));
                })->with(['user', 'activeTenancy.unit.estate'])->first();

                $tenantId = $tenant ? $tenant->id : null;

                // Render personalised message
                $message = '';
                if ($tenant && $templateContent) {
                    $placeholders = $this->campaignService->buildPlaceholders($tenant);
                    $message = $templateContent;
                    foreach ($placeholders as $key => $value) {
                        if ($value !== null) {
                            $message = str_replace('{{' . $key . '}}', $value, $message);
                        }
                    }
                    $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);
                    $message = $this->cleanAndTruncateMessage($message);
                } else {
                    $message = $log['message'] ?? '';
                }

                // Map status
                $status = $this->mapProviderStatus($log['status'] ?? 'pending');

                // Extract error/failure reason
                $errorReason = null;
                if ($status === 'failed' || $log['status'] === 'cancelled' || $log['status'] === 'failed') {
                    $errorReason = $log['error'] ?? $log['failure_reason'] ?? $log['error_message'] ?? $log['reason'] ?? null;
                    if (empty($errorReason)) {
                        $errorReason = 'Provider status: ' . ($log['status'] ?? 'failed');
                    }
                }

                // Check if recipient already exists
                $recipient = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('phone_number', $phone)
                    ->first();

                if ($recipient) {
                    $recipient->tenant_id = $tenantId;
                    $recipient->status = $status;
                    $recipient->sent_at = isset($log['sent']) ? Carbon::parse($log['sent']) : null;
                    $recipient->error_message = $errorReason;
                    $recipient->failure_reason = $errorReason;
                    $recipient->provider_status = $log['status'] ?? null;
                    $recipient->provider_response = json_encode($log);
                    if (!empty($message)) {
                        $recipient->message = $message;
                    }
                    $recipient->save();
                    $updated++;
                } else {
                    CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'tenant_id' => $tenantId,
                        'phone_number' => $phone,
                        'message' => $message ?: ($log['message'] ?? ''),
                        'status' => $status,
                        'sent_at' => isset($log['sent']) ? Carbon::parse($log['sent']) : null,
                        'error_message' => $errorReason,
                        'failure_reason' => $errorReason,
                        'provider_status' => $log['status'] ?? null,
                        'provider_response' => json_encode($log),
                    ]);
                    $created++;
                }
            }

            // Recalculate campaign totals
            $campaign->total_recipients = CampaignRecipient::where('campaign_id', $campaign->id)->count();
            $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->whereIn('status', ['sent', 'delivered'])->count();
            $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'failed')->count();
            $campaign->delivered_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'delivered')->count();
            $campaign->save();

            return response()->json([
                'success' => true,
                'message' => "Synced {$created} new recipients and updated {$updated}. Skipped {$skipped} invalid logs.",
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'sandbox' => false,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('syncRecipientsFromKenyaSMS error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync recipients: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // REGENERATE MESSAGES FOR ALL RECIPIENTS (with fallback)
    // ============================================================
    public function regenerateMessages($id)
    {
        try {
            $campaign = SmsCampaign::with('template')->findOrFail($id);

            // If campaign has no template, try to assign the first available template
            if (!$campaign->template) {
                $firstTemplate = SmsTemplate::first();
                if (!$firstTemplate) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No templates found in the system. Please create a template first.'
                    ], 400);
                }
                // Assign the first template to the campaign
                $campaign->template_id = $firstTemplate->id;
                $campaign->save();
                $campaign->load('template');
                Log::info('Assigned template ' . $firstTemplate->id . ' to campaign ' . $campaign->id);
            }

            $templateContent = $campaign->template->content;
            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)->get();
            $updated = 0;
            $skipped = 0;

            foreach ($recipients as $recipient) {
                // Try to find or match tenant
                $tenant = $recipient->tenant;
                if (!$tenant && !empty($recipient->phone_number)) {
                    $phone = preg_replace('/[^0-9]/', '', $recipient->phone_number);
                    if (substr($phone, 0, 1) === '0') $phone = substr($phone, 1);
                    if (substr($phone, 0, 3) !== '254') $phone = '254' . $phone;

                    $user = \App\Models\User::where('phone', 'like', '%' . substr($phone, -9))
                        ->orWhere('phone', $phone)
                        ->first();

                    if ($user) {
                        $tenant = Tenant::where('user_id', $user->id)->first();
                        if ($tenant) {
                            $recipient->tenant_id = $tenant->id;
                            $recipient->save();
                            $tenant->load(['user', 'activeTenancy.unit.estate']);
                        }
                    }
                }

                if ($tenant) {
                    try {
                        $placeholders = $this->campaignService->buildPlaceholders($tenant);
                        $message = $templateContent;

                        foreach ($placeholders as $key => $value) {
                            if ($value !== null) {
                                $message = str_replace('{{' . $key . '}}', $value, $message);
                            }
                        }

                        $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);
                        $message = $this->cleanAndTruncateMessage($message);

                        if (!empty($message)) {
                            $recipient->message = $message;
                            $recipient->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Exception $e) {
                        Log::error('Regenerate error for recipient ' . $recipient->id . ': ' . $e->getMessage());
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Regenerated {$updated} messages. Skipped {$skipped} recipients (no tenant match or error).",
                'data' => [
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'total' => $recipients->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('regenerateMessages error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate messages: ' . $e->getMessage()
            ], 500);
        }
    }

    // 🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕
    // 🆕 SYNC & GENERATE – Combines status sync and message generation
    // 🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕🆕
    public function syncAndGenerate(Request $request, $id)
    {
        try {
            $campaign = SmsCampaign::with('template')->findOrFail($id);
            Log::info('🔁 Sync & Generate started for campaign: ' . $campaign->id);

            // ------------------------------------------------------------
            // 1. SYNCHRONISE STATUSES (reuse existing sync logic)
            // ------------------------------------------------------------
            $syncResult = ['synced' => 0];
            $sandbox = config('sms.kenyasms.sandbox', true);

            if (!$sandbox && $campaign->kenyasms_campaign_id) {
                try {
                    $result = $this->campaignService->syncCampaignStatus($campaign->id);
                    if (isset($result['synced'])) {
                        $syncResult['synced'] = $result['synced'];
                    } elseif (isset($result['data']['synced'])) {
                        $syncResult['synced'] = $result['data']['synced'];
                    } else {
                        $syncResult['synced'] = 0;
                    }
                    Log::info('🔄 Status sync completed, synced: ' . $syncResult['synced']);
                } catch (\Exception $e) {
                    Log::error('Error during status sync: ' . $e->getMessage());
                    // Continue with message generation even if sync fails
                }
            } else {
                Log::info('⏭️ Skipping status sync (sandbox mode or no KenyaSMS ID)');
            }

            // ------------------------------------------------------------
            // 2. DETERMINE TEMPLATE TO USE
            // ------------------------------------------------------------
            $templateId = $request->input('template_id');
            $template = null;

            if ($templateId) {
                $template = SmsTemplate::find($templateId);
                if (!$template) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected template not found.'
                    ], 404);
                }
            } else {
                $template = $campaign->template;
                if (!$template) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Campaign has no template. Please select one.'
                    ], 400);
                }
            }

            $templateContent = $template->content;
            Log::info('📝 Using template: ' . $template->name . ' (ID: ' . $template->id . ')');

            // ------------------------------------------------------------
            // 3. GENERATE MESSAGES FOR MISSING RECIPIENTS
            // ------------------------------------------------------------
            $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->whereNull('message')
                ->orWhere('message', '')
                ->orWhere('message', 'like', '%Imported%') // also replace generic imported messages
                ->get();

            Log::info('📋 Found ' . $recipients->count() . ' recipients with missing or generic messages');

            $generated = 0;
            $failed = 0;
            $skipped = 0;

            foreach ($recipients as $recipient) {
                // Try to find or match tenant
                $tenant = $recipient->tenant;
                if (!$tenant && !empty($recipient->phone_number)) {
                    $phone = preg_replace('/[^0-9]/', '', $recipient->phone_number);
                    if (substr($phone, 0, 1) === '0') $phone = substr($phone, 1);
                    if (substr($phone, 0, 3) !== '254') $phone = '254' . $phone;

                    $user = \App\Models\User::where('phone', 'like', '%' . substr($phone, -9))
                        ->orWhere('phone', $phone)
                        ->first();

                    if ($user) {
                        $tenant = Tenant::where('user_id', $user->id)->first();
                        if ($tenant) {
                            $recipient->tenant_id = $tenant->id;
                            $recipient->save();
                            $tenant->load(['user', 'activeTenancy.unit.estate']);
                        }
                    }
                }

                if (!$tenant) {
                    Log::warning('Skipping recipient ' . $recipient->id . ' – no tenant found for phone ' . $recipient->phone_number);
                    $failed++;
                    continue;
                }

                try {
                    // Use the existing service to build placeholders
                    $placeholders = $this->campaignService->buildPlaceholders($tenant);
                    $message = $templateContent;

                    foreach ($placeholders as $key => $value) {
                        if ($value !== null) {
                            $message = str_replace('{{' . $key . '}}', $value, $message);
                        }
                    }

                    // Clean leftover placeholders
                    $message = preg_replace('/\{\{[^}]*\}\}/', '', $message);
                    $message = $this->cleanAndTruncateMessage($message);

                    if (!empty($message)) {
                        $recipient->message = $message;
                        $recipient->save();
                        $generated++;
                        Log::debug('Generated message for recipient ' . $recipient->id);
                    } else {
                        $skipped++;
                        Log::warning('Empty message generated for recipient ' . $recipient->id);
                    }
                } catch (\Exception $e) {
                    Log::error('Generation error for recipient ' . $recipient->id . ': ' . $e->getMessage());
                    $failed++;
                }
            }

            // ------------------------------------------------------------
            // 4. UPDATE CAMPAIGN COUNTS (just in case)
            // ------------------------------------------------------------
            $campaign->total_recipients = CampaignRecipient::where('campaign_id', $campaign->id)->count();
            $campaign->sent_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->whereIn('status', ['sent', 'delivered'])->count();
            $campaign->failed_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'failed')->count();
            $campaign->delivered_count = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'delivered')->count();
            $campaign->save();

            Log::info('✅ Sync & Generate completed', [
                'generated' => $generated,
                'failed' => $failed,
                'skipped' => $skipped,
                'synced' => $syncResult['synced'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Synced statuses and generated {$generated} messages. Failed: {$failed}, Skipped: {$skipped}.",
                'data' => [
                    'generated' => $generated,
                    'failed' => $failed,
                    'skipped' => $skipped,
                    'synced' => $syncResult['synced'],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ syncAndGenerate error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync and generate: ' . $e->getMessage()
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
                'sender_id' => 'DANAFFKENYA',
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
                'sender_id' => 'DANAFFKENYA',
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
                'sender_id' => 'DANAFFKENYA',
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
                'sender_id' => 'DANAFFKENYA',
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

/**
 * Map provider status to internal status (allowed in DB)
 */
protected function mapProviderStatus($providerStatus)
{
    $map = [
        'delivered'   => 'sent',
        'sent'        => 'sent',
        'failed'      => 'failed',
        'undelivered' => 'failed',
        'rejected'    => 'failed',
        'queued'      => 'pending',
        'pending'     => 'pending',
        'completed'   => 'sent',
        'cancelled'   => 'failed',
        'expired'     => 'failed',
        'unknown'     => 'pending',
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