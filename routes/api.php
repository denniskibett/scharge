<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\SMS\Models\SmsCampaign;
use App\Models\SmsTemplate;
use App\Models\Estate;
use App\Models\Company;
use App\Models\Tenant;
use App\Modules\SMS\Controllers\WebhookController;
use App\Services\CampaignService;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================
// KENYASMS WEBHOOK ROUTES (PUBLIC - NO AUTH)
// ============================================
Route::prefix('sms/webhook')->group(function () {
    // Main webhook endpoint (handles both DLR and inbound)
    Route::post('/kenyasms', [WebhookController::class, 'handleWebhook'])
        ->name('sms.webhook.kenyasms');
    
    // Delivery Report webhook (specific)
    Route::post('/kenyasms/delivery', [WebhookController::class, 'handleDLR'])
        ->name('sms.webhook.delivery');
    
    // Inbound SMS webhook (specific)
    Route::post('/kenyasms/inbound', [WebhookController::class, 'handleInbound'])
        ->name('sms.webhook.inbound');
});

// ============================================
// SMS CAMPAIGNS API ROUTES
// ============================================
Route::prefix('sms')->middleware(['auth:sanctum'])->group(function () {
    
    // Get all campaigns with stats
    Route::get('/campaigns', function () {
        try {
            $campaigns = SmsCampaign::with(['template', 'creator'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            $stats = [
                'total' => $campaigns->count(),
                'sent' => $campaigns->sum('sent_count'),
                'pending' => $campaigns->where('status', 'draft')->count(),
                'failed' => $campaigns->sum('failed_count'),
                'delivered' => $campaigns->sum('delivered_count'),
            ];
            
            return response()->json([
                'success' => true,
                'campaigns' => $campaigns,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Get single campaign with full details
    Route::get('/campaigns/{id}', function ($id) {
        try {
            $campaign = SmsCampaign::with([
                'recipients.tenant.user',
                'recipients.tenant.tenancies.unit.estate',
                'template',
                'creator'
            ])->findOrFail($id);
            
            // Get recipient counts by status
            $statusCounts = $campaign->recipients()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');
            
            return response()->json([
                'success' => true,
                'campaign' => $campaign,
                'status_counts' => $statusCounts,
                'total_recipients' => $campaign->recipients()->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    });
    
    // Create campaign
    Route::post('/campaigns', function (Request $request) {
        try {
            // Validate request
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'template_id' => 'required|exists:sms_templates,id',
                'filters' => 'nullable|array',
                'campaign_type' => 'nullable|in:transactional,promotional',
                'scheduled_at' => 'nullable|date|after:now',
            ]);
            
            $campaign = SmsCampaign::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'template_id' => $validated['template_id'],
                'filters' => json_encode($validated['filters'] ?? []),
                'campaign_type' => $validated['campaign_type'] ?? 'transactional',
                'total_recipients' => 0,
                'sent_count' => 0,
                'failed_count' => 0,
                'delivered_count' => 0,
                'status' => $validated['scheduled_at'] ? 'scheduled' : 'draft',
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'created_by' => auth()->id() ?? 1,
            ]);
            
            // Create recipients for the campaign
            $campaignService = app(CampaignService::class);
            $result = $campaignService->createRecipientsForCampaign($campaign);
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully!',
                'campaign' => $campaign->fresh(['template', 'creator']),
                'recipients_created' => $result['created'] ?? 0,
                'recipients_failed' => $result['failed'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Send campaign via KenyaSMS
    Route::post('/campaigns/{id}/send', function ($id) {
        try {
            $campaignService = app(CampaignService::class);
            $result = $campaignService->sendCampaign($id);
            
            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign sent successfully via KenyaSMS',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Retry failed recipients
    Route::post('/campaigns/{id}/retry', function ($id) {
        try {
            $campaignService = app(CampaignService::class);
            $result = $campaignService->retryFailed($id);
            
            if (isset($result['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Retry completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Sync campaign status from KenyaSMS
    Route::post('/campaigns/{id}/sync-status', function ($id) {
        try {
            $campaignService = app(CampaignService::class);
            $result = $campaignService->syncCampaignStatus($id);
            
            if (isset($result['error'])) {
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Delete campaign
    Route::delete('/campaigns/{id}', function ($id) {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            // Delete associated recipients first (cascade should handle this)
            $campaign->recipients()->delete();
            $campaign->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Preview recipients with rendered messages
    Route::post('/campaigns/preview', function (Request $request) {
        try {
            $validated = $request->validate([
                'template_id' => 'required|exists:sms_templates,id',
                'filters' => 'nullable|array',
            ]);
            
            $campaignService = app(CampaignService::class);
            $previews = $campaignService->previewCampaign(
                $validated['template_id'],
                $validated['filters'] ?? []
            );
            
            if (isset($previews['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $previews['error']
                ], 400);
            }
            
            // Calculate summary
            $total = count($previews);
            $valid = 0;
            $invalid = 0;
            $otherNetwork = 0;
            
            foreach ($previews as $preview) {
                if (!empty($preview['phone']) && preg_match('/^2547[0-9]{8}$/', $preview['phone'])) {
                    $valid++;
                } elseif (!empty($preview['phone']) && preg_match('/^254[7-9][0-9]{8}$/', $preview['phone'])) {
                    $otherNetwork++;
                } else {
                    $invalid++;
                }
            }
            
            return response()->json([
                'success' => true,
                'previews' => $previews,
                'summary' => [
                    'total' => $total,
                    'valid' => $valid,
                    'invalid' => $invalid,
                    'other_network' => $otherNetwork,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Get campaign status summary
    Route::get('/campaigns/{id}/status-summary', function ($id) {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            $summary = $campaign->recipients()
                ->selectRaw('
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = "pending" THEN 1 END) as pending,
                    COUNT(CASE WHEN status = "sent" THEN 1 END) as sent,
                    COUNT(CASE WHEN status = "delivered" THEN 1 END) as delivered,
                    COUNT(CASE WHEN status = "failed" THEN 1 END) as failed,
                    COUNT(CASE WHEN status = "sending" THEN 1 END) as sending
                ')
                ->first();
            
            return response()->json([
                'success' => true,
                'campaign_id' => $id,
                'summary' => $summary,
                'status' => $campaign->status,
                'total_recipients' => $campaign->total_recipients,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'delivered_count' => $campaign->delivered_count,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Get invalid recipients for a campaign
    Route::get('/campaigns/{id}/invalid-recipients', function ($id) {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            $invalidRecipients = $campaign->recipients()
                ->with('tenant.user')
                ->where('status', 'invalid')
                ->orWhereNull('phone_number')
                ->orWhere('phone_number', '')
                ->get();
            
            return response()->json([
                'success' => true,
                'count' => $invalidRecipients->count(),
                'recipients' => $invalidRecipients
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Get other network recipients
    Route::get('/campaigns/{id}/other-network-recipients', function ($id) {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            
            $otherNetwork = $campaign->recipients()
                ->with('tenant.user')
                ->where('status', 'other_network')
                ->get();
            
            return response()->json([
                'success' => true,
                'count' => $otherNetwork->count(),
                'recipients' => $otherNetwork
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Update tenant phone number
    Route::put('/tenants/{tenantId}/phone', function (Request $request, $tenantId) {
        try {
            $validated = $request->validate([
                'phone' => 'required|string|regex:/^2547[0-9]{8}$/'
            ]);
            
            $tenant = Tenant::findOrFail($tenantId);
            
            // Update user phone
            $user = $tenant->user;
            if ($user) {
                $user->phone = $validated['phone'];
                $user->save();
            }
            
            // Also update any related campaign recipients
            $tenant->campaignRecipients()->update([
                'phone_number' => $validated['phone'],
                'status' => 'pending',
                'error_message' => null,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Phone number updated successfully',
                'phone' => $validated['phone']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Get templates
    Route::get('/templates', function () {
        try {
            $templates = SmsTemplate::all();
            return response()->json([
                'success' => true,
                'templates' => $templates
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Get template preview
    Route::get('/templates/{id}/preview', function ($id) {
        try {
            $template = SmsTemplate::find($id);
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'content' => $template->content,
                'name' => $template->name,
                'variables' => [
                    '{{name}}', '{{unit}}', '{{unit_number}}', '{{water_bill}}',
                    '{{water_consumption}}', '{{due_date}}', '{{month}}',
                    '{{estate_name}}', '{{prev_read}}', '{{curr_read}}',
                    '{{payment_status}}', '{{status}}'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Get KenyaSMS balance
    Route::get('/balance', function () {
        try {
            $campaignService = app(CampaignService::class);
            $balance = $campaignService->getBalance();
            
            return response()->json([
                'success' => true,
                'balance' => $balance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Get message parts and cost estimate
    Route::post('/estimate-cost', function (Request $request) {
        try {
            $validated = $request->validate([
                'message' => 'required|string',
                'type' => 'nullable|in:transactional,promotional'
            ]);
            
            $campaignService = app(CampaignService::class);
            
            return response()->json([
                'success' => true,
                'message' => $validated['message'],
                'parts' => $campaignService->getMessageParts($validated['message']),
                'estimated_cost' => $campaignService->getEstimatedCost(
                    $validated['message'],
                    $validated['type'] ?? null
                ),
                'type' => $validated['type'] ?? 'transactional'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
});

// ============================================
// ESTATES API
// ============================================
Route::get('/estates', function () {
    try {
        $estates = Estate::all();
        return response()->json([
            'success' => true,
            'estates' => $estates
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');

// ============================================
// COMPANIES API
// ============================================
Route::get('/companies', function () {
    try {
        $companies = Company::all();
        return response()->json([
            'success' => true,
            'companies' => $companies
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
})->middleware('auth:sanctum');