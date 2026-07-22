<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\SMS\Models\SmsCampaign;
use App\Models\SmsTemplate;
use App\Models\Estate;
use App\Models\Company;
use App\Models\Tenant;

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
    
    // Get single campaign
    Route::get('/campaigns/{id}', function ($id) {
        try {
            $campaign = SmsCampaign::with(['recipients.tenant', 'template', 'creator'])
                ->findOrFail($id);
            return response()->json([
                'success' => true,
                'campaign' => $campaign
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
            if (!$request->name || !$request->template_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign name and template are required'
                ], 422);
            }
            
            $campaign = SmsCampaign::create([
                'name' => $request->name,
                'description' => $request->description,
                'template_id' => $request->template_id,
                'filters' => $request->filters ?? [],
                'total_recipients' => 0,
                'status' => $request->scheduled_at ? 'scheduled' : 'draft',
                'scheduled_at' => $request->scheduled_at,
                'created_by' => auth()->id() ?? 1,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully!',
                'campaign' => $campaign
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Send campaign
    Route::post('/campaigns/{id}/send', function ($id) {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $campaign->update(['status' => 'sending']);
            // Simulate sending
            $campaign->update([
                'status' => 'completed',
                'sent_at' => now(),
                'sent_count' => $campaign->total_recipients ?? 0,
            ]);
            return response()->json(['success' => true]);
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
            $campaign->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Preview recipients
    Route::post('/campaigns/preview', function (Request $request) {
        try {
            $query = Tenant::query();
            
            if (!empty($request->filters['estate_id'])) {
                $query->where('estate_id', $request->filters['estate_id']);
            }
            if (!empty($request->filters['company_id'])) {
                $query->where('company_id', $request->filters['company_id']);
            }
            
            $tenants = $query->get();
            $valid = 0;
            $invalid = 0;
            
            foreach ($tenants as $tenant) {
                if (preg_match('/^(07|01|2547|2541)\d{8}$/', preg_replace('/[^0-9]/', '', $tenant->phone_number))) {
                    $valid++;
                } else {
                    $invalid++;
                }
            }
            
            return response()->json([
                'success' => true,
                'total' => $tenants->count(),
                'valid' => $valid,
                'invalid' => $invalid,
                'tenants' => $tenants->take(20)->map(function($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'phone' => $tenant->phone_number,
                        'estate' => $tenant->estate->name ?? 'N/A'
                    ];
                })
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
                'name' => $template->name
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    });
    
    // Retry campaign
    Route::post('/campaigns/{id}/retry', function ($id) {
        try {
            $campaign = SmsCampaign::findOrFail($id);
            $failedRecipients = $campaign->recipients()->where('status', 'failed')->get();
            foreach ($failedRecipients as $recipient) {
                $recipient->update(['status' => 'pending']);
            }
            $campaign->update([
                'status' => 'sending',
            ]);
            return response()->json(['success' => true]);
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