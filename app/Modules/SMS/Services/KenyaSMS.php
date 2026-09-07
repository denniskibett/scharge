<?php

namespace App\Modules\SMS\Services;

use App\Models\CampaignRecipient;
use App\Modules\SMS\Models\SmsLog;
use App\Modules\SMS\Models\SmsCampaign;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class KenyaSMS
{
    protected $apiKey;
    protected $senderId;
    protected $sandbox;
    protected $baseUrl;
    protected $defaultType;
    protected $webhookUrl;
    protected $inboundWebhookUrl;

    public function __construct()
    {
        $this->apiKey = config('sms.kenyasms.api_key', '');
        $this->senderId = config('sms.kenyasms.sender_id', 'SHARETENT');
        $this->sandbox = config('sms.kenyasms.sandbox', true);
        $this->baseUrl = config('sms.kenyasms.base_url', 'https://kenyasms.com/api/v1');
        $this->defaultType = config('sms.kenyasms.default_type', 'transactional');
        $this->webhookUrl = config('sms.kenyasms.webhook_url');
        $this->inboundWebhookUrl = config('sms.kenyasms.inbound_webhook_url');
        
        Log::info('KenyaSMS initialized', [
            'sender_id' => $this->senderId,
            'sandbox' => $this->sandbox,
            'base_url' => $this->baseUrl
        ]);
    }

    /**
     * Send a single SMS
     */
    public function sendOne($phone, $message, $type = null, $campaignId = null, $scheduleAt = null)
    {
        $type = $type ?? $this->defaultType;
        
        Log::info('KenyaSMS: Sending SMS', [
            'phone' => $phone,
            'message_length' => strlen($message),
            'type' => $type,
            'campaign_id' => $campaignId,
            'sandbox' => $this->sandbox,
            'scheduled' => $scheduleAt ? true : false
        ]);

        try {
            $phone = $this->formatPhoneNumber($phone);
            
            if (empty($phone)) {
                return [
                    'success' => false,
                    'error' => 'Invalid phone number'
                ];
            }

            $payload = [
                'sender_id' => $this->senderId,
                'recipient' => $phone,
                'message' => $message,
                'message_type' => $type
            ];

            if ($scheduleAt) {
                $payload['schedule_at'] = $scheduleAt;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            if ($this->sandbox) {
                $headers['X-Sandbox-Mode'] = 'true';
            }

            Log::info('KenyaSMS: Request', [
                'url' => $this->baseUrl . '/sms/send',
                'payload' => $payload
            ]);

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(60)
                ->post($this->baseUrl . '/sms/send', $payload);

            Log::info('KenyaSMS: Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['data']['message_id'] ?? $data['message_id'] ?? $data['request_id'] ?? null;
                $status = $data['data']['status'] ?? $data['status'] ?? 'queued';
                $cost = $data['data']['cost'] ?? $data['cost'] ?? null;

                $this->logSms($phone, $message, $type, $campaignId, $status, null, $messageId, $cost);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'status' => $status,
                    'cost' => $cost,
                    'data' => $data
                ];
            }

            $error = $response->body() ?? 'Unknown error';
            $this->logSms($phone, $message, $type, $campaignId, 'failed', $error, null, null);

            return [
                'success' => false,
                'error' => $error,
                'status_code' => $response->status(),
                'response' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: Send failed', [
                'error' => $e->getMessage(),
                'phone' => $phone,
                'trace' => $e->getTraceAsString()
            ]);

            $this->logSms($phone, $message, $type, $campaignId, 'failed', $e->getMessage(), null, null);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send bulk SMS (up to 100,000 recipients per call)
     */
    public function sendBulk($recipients, $message, $type = null, $campaignId = null, $scheduleAt = null)
    {
        $type = $type ?? $this->defaultType;
        
        Log::info('KenyaSMS: Sending bulk SMS', [
            'recipients_count' => count($recipients),
            'campaign_id' => $campaignId,
            'type' => $type
        ]);

        $formattedRecipients = [];
        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? $recipient['phone_number'] ?? $recipient;
            $formatted = $this->formatPhoneNumber($phone);
            if ($formatted) {
                $formattedRecipients[] = $formatted;
            }
        }

        if (empty($formattedRecipients)) {
            return [
                'success' => false,
                'error' => 'No valid recipients found'
            ];
        }

        try {
            $payload = [
                'sender_id' => $this->senderId,
                'recipients' => $formattedRecipients,
                'message' => $message,
                'message_type' => $type
            ];

            if ($scheduleAt) {
                $payload['schedule_at'] = $scheduleAt;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            if ($this->sandbox) {
                $headers['X-Sandbox-Mode'] = 'true';
            }

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(60)
                ->post($this->baseUrl . '/sms/bulk', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                foreach ($formattedRecipients as $index => $phone) {
                    $messageId = $data['data']['messages'][$index]['message_id'] ?? null;
                    $this->logSms($phone, $message, $type, $campaignId, 'queued', null, $messageId, null);
                }

                return [
                    'success' => true,
                    'data' => $data,
                    'recipients_count' => count($formattedRecipients)
                ];
            }

            return [
                'success' => false,
                'error' => $response->body() ?? 'Failed to send bulk SMS',
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: Bulk send failed', [
                'error' => $e->getMessage(),
                'campaign_id' => $campaignId
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send personalized SMS messages (mail merge)
     */
    public function sendPersonalized($template, $recipients, $type = null, $campaignId = null, $scheduleAt = null)
    {
        $type = $type ?? $this->defaultType;
        
        Log::info('KenyaSMS: Sending personalized SMS', [
            'recipients_count' => count($recipients),
            'campaign_id' => $campaignId,
            'type' => $type
        ]);

        $preparedRecipients = [];
        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? $recipient['phone_number'] ?? '';
            $formatted = $this->formatPhoneNumber($phone);
            
            if ($formatted) {
                $variables = $recipient['variables'] ?? [];
                $preparedRecipients[] = [
                    'phone' => $formatted,
                    'variables' => $variables
                ];
            }
        }

        if (empty($preparedRecipients)) {
            return [
                'success' => false,
                'error' => 'No valid recipients found'
            ];
        }

        try {
            $payload = [
                'sender_id' => $this->senderId,
                'recipients' => $preparedRecipients,
                'template' => $template,
                'message_type' => $type
            ];

            if ($scheduleAt) {
                $payload['schedule_at'] = $scheduleAt;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            if ($this->sandbox) {
                $headers['X-Sandbox-Mode'] = 'true';
            }

            Log::info('KenyaSMS: Personalized Request', [
                'url' => $this->baseUrl . '/sms/personalized',
                'payload' => $payload
            ]);

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(60)
                ->post($this->baseUrl . '/sms/personalized', $payload);

            Log::info('KenyaSMS: Personalized Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                foreach ($preparedRecipients as $index => $recipient) {
                    $messageId = $data['data']['messages'][$index]['message_id'] ?? null;
                    $this->logSms(
                        $recipient['phone'], 
                        $template, 
                        $type, 
                        $campaignId, 
                        'queued', 
                        null, 
                        $messageId, 
                        null
                    );
                }

                return [
                    'success' => true,
                    'data' => $data,
                    'recipients_count' => count($preparedRecipients)
                ];
            }

            Log::error('KenyaSMS: Personalized send failed', [
                'status_code' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $response->body() ?? 'Failed to send personalized SMS',
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: Personalized send failed', [
                'error' => $e->getMessage(),
                'campaign_id' => $campaignId
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // =========================================================
    // 🚀 CAMPAIGN METHODS (Added for CampaignService)
    // =========================================================

    /**
     * Send a personalized campaign (creates a campaign in KenyaSMS)
     */
    public function sendPersonalizedCampaign($senderId, $messageType, $template, $recipients, $scheduleAt = null, $callbackUrl = null)
    {
        $type = $messageType ?? $this->defaultType;
        
        Log::info('KenyaSMS: Sending personalized campaign', [
            'sender_id' => $senderId,
            'recipients_count' => count($recipients),
            'message_type' => $type,
            'scheduled' => $scheduleAt ? true : false
        ]);

        $preparedRecipients = [];
        foreach ($recipients as $recipient) {
            $phone = $this->formatPhoneNumber($recipient['phone'] ?? $recipient['phone_number'] ?? '');
            if ($phone) {
                $preparedRecipients[] = [
                    'phone' => $phone,
                    'variables' => $recipient['variables'] ?? []
                ];
            }
        }

        if (empty($preparedRecipients)) {
            return [
                'success' => false,
                'error' => 'No valid recipients'
            ];
        }

        try {
            $payload = [
                'sender_id' => $senderId,
                'recipients' => $preparedRecipients,
                'template' => $template,
                'message_type' => $type
            ];

            if ($scheduleAt) {
                $payload['schedule_at'] = $scheduleAt;
            }

            if ($callbackUrl) {
                $payload['callback_url'] = $callbackUrl;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            if ($this->sandbox) {
                $headers['X-Sandbox-Mode'] = 'true';
            }

            Log::info('KenyaSMS: Campaign Request', [
                'url' => $this->baseUrl . '/sms/personalized',
                'payload' => array_merge($payload, ['recipients' => '...'])
            ]);

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(60)
                ->post($this->baseUrl . '/sms/personalized', $payload);

            Log::info('KenyaSMS: Campaign Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $campaignId = $data['data']['campaign_id'] ?? $data['campaign_id'] ?? null;
                
                return [
                    'success' => true,
                    'campaign_id' => $campaignId,
                    'data' => $data
                ];
            }

            Log::error('KenyaSMS: Campaign send failed', [
                'status_code' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $response->body() ?? 'Failed to send campaign'
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: Campaign send exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get aggregated status of a campaign from KenyaSMS
     */
    public function getCampaignStatus($campaignId)
    {
        if (empty($campaignId)) {
            return [
                'success' => false,
                'error' => 'Campaign ID is required'
            ];
        }

        if ($this->sandbox) {
            return [
                'success' => true,
                'data' => [
                    'sent' => 10,
                    'failed' => 2,
                    'delivered' => 8,
                    'status' => 'completed'
                ]
            ];
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(30)
                ->get($this->baseUrl . '/campaigns/' . $campaignId . '/status');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data['data'] ?? $data
                ];
            }

            return [
                'success' => false,
                'error' => $response->body() ?? 'Failed to get campaign status',
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: getCampaignStatus failed', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get per‑recipient logs for a campaign from KenyaSMS
     */
    public function getCampaignLogs($campaignId)
    {
        if (empty($campaignId)) {
            return [
                'success' => false,
                'error' => 'Campaign ID is required'
            ];
        }

        if ($this->sandbox) {
            $mockLogs = [];
            for ($i = 1; $i <= 5; $i++) {
                $mockLogs[] = [
                    'recipient' => '2547' . rand(10000000, 99999999),
                    'status' => ['delivered', 'sent', 'queued', 'failed'][array_rand(['delivered', 'sent', 'queued', 'failed'])],
                    'sent' => now()->subMinutes(rand(1, 60))->toISOString(),
                    'delivered' => now()->subMinutes(rand(0, 30))->toISOString(),
                    'error_code' => null
                ];
            }
            return [
                'success' => true,
                'logs' => $mockLogs
            ];
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(30)
                ->get($this->baseUrl . '/campaigns/' . $campaignId . '/logs');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'logs' => $data['data']['logs'] ?? $data['logs'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => $response->body() ?? 'Failed to get campaign logs',
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: getCampaignLogs failed', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * List all campaigns from KenyaSMS - with improved error handling
     */
    public function listCampaigns($page = 1, $limit = 20, $status = null)
    {
        // ✅ Check if API key is configured
        if (empty($this->apiKey)) {
            Log::warning('KenyaSMS: API key not configured, returning mock data');
            return $this->getMockCampaigns();
        }

        // ✅ If sandbox is enabled, return mock data
        if ($this->sandbox) {
            Log::info('KenyaSMS: Sandbox mode enabled, returning mock campaigns');
            return $this->getMockCampaigns();
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $query = http_build_query([
                'page' => $page,
                'limit' => $limit,
                'status' => $status,
            ]);

            Log::info('KenyaSMS: Listing campaigns', [
                'url' => $this->baseUrl . '/campaigns?' . $query
            ]);

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(10)
                ->get($this->baseUrl . '/campaigns?' . $query);

            Log::info('KenyaSMS: List campaigns response', [
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 200)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'campaigns' => $data['data'] ?? [],
                    'total' => $data['total'] ?? 0,
                    'page' => $data['page'] ?? 1,
                    'limit' => $data['limit'] ?? 20,
                ];
            }

            // ✅ If API returns an error, log it and return mock data
            Log::error('KenyaSMS: Failed to list campaigns', [
                'status_code' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->getMockCampaigns();

        } catch (\Exception $e) {
            Log::error('KenyaSMS: listCampaigns exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // ✅ Return mock data on any exception
            return $this->getMockCampaigns();
        }
    }

    /**
     * Get mock campaigns for development/testing
     */
    private function getMockCampaigns()
    {
        return [
            'success' => true,
            'campaigns' => [
                [
                    'id' => 'mock-1',
                    'name' => 'API Single SMS - 5136',
                    'sender_id' => $this->senderId ?? 'SHARETENT',
                    'message_type' => 'transactional',
                    'recipients' => 1,
                    'delivered' => 1,
                    'failed' => 0,
                    'status' => 'completed',
                    'cost' => '0.90',
                    'created_at' => now()->subDays(2)->toISOString(),
                ],
                [
                    'id' => 'mock-2',
                    'name' => 'Personalized API Campaign 1',
                    'sender_id' => $this->senderId ?? 'SHARETENT',
                    'message_type' => 'transactional',
                    'recipients' => 76,
                    'delivered' => 64,
                    'failed' => 9,
                    'status' => 'completed',
                    'cost' => '65.70',
                    'created_at' => now()->subDays(3)->toISOString(),
                ],
                [
                    'id' => 'mock-3',
                    'name' => 'Personalized API Campaign 2',
                    'sender_id' => $this->senderId ?? 'SHARETENT',
                    'message_type' => 'transactional',
                    'recipients' => 215,
                    'delivered' => 180,
                    'failed' => 20,
                    'status' => 'completed',
                    'cost' => '180.00',
                    'created_at' => now()->subDays(4)->toISOString(),
                ],
                [
                    'id' => 'mock-4',
                    'name' => 'Personalized API Campaign 3',
                    'sender_id' => $this->senderId ?? 'SHARETENT',
                    'message_type' => 'transactional',
                    'recipients' => 2,
                    'delivered' => 2,
                    'failed' => 0,
                    'status' => 'completed',
                    'cost' => '1.80',
                    'created_at' => now()->subDays(5)->toISOString(),
                ],
            ],
            'total' => 4,
            'page' => 1,
            'limit' => 20,
            'mock' => true,
            'message' => 'Using mock data (API unavailable)'
        ];
    }

    // =========================================================
    // END OF NEW METHODS
    // =========================================================

    public function getMessageStatus($messageId)
    {
        if (empty($messageId)) {
            return [
                'success' => false,
                'error' => 'Message ID is required'
            ];
        }

        $cacheKey = 'kenyasms_status_' . $messageId;
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        if ($this->sandbox) {
            $statuses = ['queued', 'sent', 'delivered', 'failed'];
            $randomStatus = $statuses[array_rand($statuses)];
            
            $result = [
                'success' => true,
                'status' => $randomStatus,
                'response' => 'Sandbox mode: ' . $randomStatus
            ];
            
            Cache::put($cacheKey, $result, 60);
            return $result;
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(30)
                ->get($this->baseUrl . '/sms/status/' . $messageId);

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['data']['status'] ?? $data['status'] ?? 'unknown';
                
                $result = [
                    'success' => true,
                    'status' => $status,
                    'response' => $data
                ];
                
                Cache::put($cacheKey, $result, 60);
                return $result;
            }

            return [
                'success' => false,
                'error' => $response->body() ?? 'Failed to get status',
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: Failed to get message status', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getDeliveryReport($messageId)
    {
        if (empty($messageId)) {
            return [
                'success' => false,
                'error' => 'Message ID is required'
            ];
        }

        if ($this->sandbox) {
            return [
                'success' => true,
                'status' => 'delivered',
                'delivered_at' => now()->toDateTimeString(),
                'response' => 'Sandbox mode: delivered'
            ];
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(30)
                ->get($this->baseUrl . '/sms/dlr/' . $messageId);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'status' => $data['data']['status'] ?? 'unknown',
                    'delivered_at' => $data['data']['delivered_at'] ?? null,
                    'response' => $data
                ];
            }

            return [
                'success' => false,
                'error' => $response->body() ?? 'Failed to get delivery report'
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: Failed to get delivery report', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getBalance()
    {
        $cacheKey = 'kenyasms_balance';
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        if ($this->sandbox) {
            $result = [
                'success' => true,
                'balance' => '1000.00',
                'currency' => 'KES'
            ];
            Cache::put($cacheKey, $result, 300);
            return $result;
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(30)
                ->get($this->baseUrl . '/wallet/balance');

            if ($response->successful()) {
                $data = $response->json();
                
                $result = [
                    'success' => true,
                    'balance' => $data['data']['balance'] ?? $data['balance'] ?? '0.00',
                    'currency' => $data['data']['currency'] ?? $data['currency'] ?? 'KES'
                ];
                
                Cache::put($cacheKey, $result, 300);
                return $result;
            }

            return [
                'success' => false,
                'error' => 'Failed to get balance',
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: Failed to get balance', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getSenderIds()
    {
        if ($this->sandbox) {
            return [
                'success' => true,
                'data' => [
                    ['sender_id' => $this->senderId, 'status' => 'active'],
                    ['sender_id' => 'TextSMS', 'status' => 'active']
                ]
            ];
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::withHeaders($headers)
                ->withoutVerifying()
                ->timeout(30)
                ->get($this->baseUrl . '/sender-ids');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data['data'] ?? []
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get sender IDs'
            ];

        } catch (\Exception $e) {
            Log::error('KenyaSMS: Failed to get sender IDs', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function isPromotional($messageType)
    {
        return $messageType === 'promotional';
    }

    public function isQuietHours()
    {
        $quietHours = config('sms.kenyasms.quiet_hours', [
            'start' => '20:00',
            'end' => '08:00'
        ]);
        
        $now = now()->setTimezone('EAT');
        $start = \Carbon\Carbon::parse($quietHours['start'], 'EAT');
        $end = \Carbon\Carbon::parse($quietHours['end'], 'EAT');
        
        if ($start < $end) {
            return $now->between($start, $end);
        } else {
            return $now->greaterThan($start) || $now->lessThan($end);
        }
    }

    protected function logSms($phone, $message, $type, $campaignId, $status, $error = null, $messageId = null, $cost = null)
    {
        try {
            SmsLog::create([
                'recipient_phone' => $phone,
                'message' => $message,
                'message_type' => $type,
                'status' => $status,
                'error_message' => $error,
                'campaign_id' => $campaignId,
                'provider_message_id' => $messageId,
                'cost' => $cost,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log SMS: ' . $e->getMessage());
        }
    }

    public function formatPhoneNumber($phone)
    {
        if (empty($phone)) {
            return null;
        }
        
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }
        
        if (substr($phone, 0, 1) === '7') {
            $phone = '254' . $phone;
        }
        
        if (substr($phone, 0, 3) === '254' && strlen($phone) === 12) {
            return $phone;
        }
        
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '7') {
            return '254' . $phone;
        }
        
        if (strlen($phone) === 9) {
            return '2547' . $phone;
        }
        
        Log::warning('KenyaSMS: Invalid phone number format', [
            'original' => $phone,
            'cleaned' => $phone
        ]);
        
        return null;
    }

    public function validatePhone($phone)
    {
        $formatted = $this->formatPhoneNumber($phone);
        
        if ($formatted && preg_match('/^2547[0-9]{8}$/', $formatted)) {
            return $formatted;
        }
        
        return null;
    }

    public function getMessageParts($message)
    {
        $length = strlen($message);
        
        $isUnicode = preg_match('/[^\x00-\x7F]/', $message);
        
        if ($isUnicode) {
            if ($length <= 70) return 1;
            return ceil(($length - 70) / 67) + 1;
        } else {
            if ($length <= 160) return 1;
            return ceil(($length - 160) / 153) + 1;
        }
    }

    public function getEstimatedCost($message, $type = null)
    {
        $parts = $this->getMessageParts($message);
        $type = $type ?? $this->defaultType;
        
        $rates = [
            'transactional' => 0.45,
            'promotional' => 0.45
        ];
        
        $rate = $rates[$type] ?? 0.45;
        
        return number_format($parts * $rate, 2);
    }

    public function mapStatus($providerStatus)
    {
        $mapping = config('sms.status_mapping', [
            '200' => 'delivered',
            '1001' => 'failed',
            '1002' => 'failed',
            '1003' => 'failed',
            '1004' => 'failed',
            '1005' => 'failed',
            '1006' => 'failed',
            '1007' => 'failed',
            '1008' => 'failed',
        ]);
        
        return $mapping[$providerStatus] ?? 'unknown';
    }

    public function isSandbox()
    {
        return $this->sandbox;
    }

    public function getSenderId()
    {
        return $this->senderId;
    }

    public function getDefaultType()
    {
        return $this->defaultType;
    }

    public function setSenderId($senderId)
    {
        $this->senderId = $senderId;
        return $this;
    }

    public function setSandbox($sandbox)
    {
        $this->sandbox = (bool) $sandbox;
        return $this;
    }
}