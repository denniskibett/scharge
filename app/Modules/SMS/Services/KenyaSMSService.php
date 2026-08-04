<?php
// app/Modules/SMS/Services/KenyaSMSService.php

namespace App\Modules\SMS\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class KenyaSMSService
{
    protected $client;
    protected $apiKey;
    protected $baseUrl;
    protected $senderId;
    protected $defaultType;
    protected $sandbox;
    
    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false, // For development only
        ]);
        
        $this->baseUrl = rtrim(env('KENYASMS_URL', 'https://kenyasms.com'), '/');
        $this->apiKey = env('KENYASMS_KEY');
        $this->senderId = env('KENYASMS_SENDER_ID', 'TextSMS');
        $this->defaultType = env('KENYASMS_DEFAULT_TYPE', 'transactional');
        $this->sandbox = env('KENYASMS_SANDBOX', true);
    }
    
    /**
     * Send personalized SMS (template with variables) – Legacy method
     * Sends each message individually via /sms/send
     */
    public function sendPersonalized(array $messages, array $options = []): array
    {
        $url = $this->baseUrl . '/sms/send';
        
        $allResponses = [];
        
        foreach ($messages as $msg) {
            $payload = [
                'sender_id' => $options['sender_id'] ?? $this->senderId,
                'recipient' => $msg['phone'],
                'message' => $msg['message'],
                'message_type' => $options['message_type'] ?? $this->defaultType,
                'schedule_at' => $options['schedule_at'] ?? null,
                'callback_url' => $options['callback_url'] ?? null,
            ];
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];
            
            if ($this->sandbox) {
                $headers['X-Sandbox-Mode'] = 'true';
            }
            
            try {
                Log::info('Sending SMS to KenyaSMS', [
                    'url' => $url,
                    'recipient' => $msg['phone'],
                ]);
                
                $response = $this->client->post($url, [
                    'headers' => $headers,
                    'json' => $payload,
                ]);
                
                $result = json_decode($response->getBody(), true);
                
                Log::info('KenyaSMS Response', ['response' => $result]);
                
                $allResponses[] = $result;
                
            } catch (\Exception $e) {
                Log::error('KenyaSMS Error', [
                    'error' => $e->getMessage(),
                    'recipient' => $msg['phone'],
                ]);
                throw $e;
            }
        }
        
        return [
            'success' => true,
            'responses' => $allResponses,
        ];
    }
    
    /**
     * Send bulk SMS
     */
    public function sendBulk(array $recipients, string $message, array $options = []): array
    {
        $phoneNumbers = array_map(function($recipient) {
            return $this->formatPhone($recipient);
        }, $recipients);
        
        $url = $this->baseUrl . '/sms/bulk';
        
        $payload = [
            'sender_id' => $options['sender_id'] ?? $this->senderId,
            'recipients' => $phoneNumbers,
            'message' => $message,
            'message_type' => $options['message_type'] ?? $this->defaultType,
            'schedule_at' => $options['schedule_at'] ?? null,
            'callback_url' => $options['callback_url'] ?? null,
        ];
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        
        if ($this->sandbox) {
            $headers['X-Sandbox-Mode'] = 'true';
        }
        
        try {
            Log::info('Sending bulk SMS to KenyaSMS', [
                'url' => $url,
                'recipients' => count($recipients),
            ]);
            
            $response = $this->client->post($url, [
                'headers' => $headers,
                'json' => $payload,
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            Log::info('KenyaSMS Bulk Response', ['response' => $result]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('KenyaSMS Bulk Error', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);
            throw $e;
        }
    }
    
    /**
     * Check wallet balance
     */
    public function getBalance(): ?array
    {
        $url = $this->baseUrl . '/wallet/balance';
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ];
        
        try {
            Log::info('Checking KenyaSMS balance', ['url' => $url]);
            
            $response = $this->client->get($url, [
                'headers' => $headers,
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            Log::info('KenyaSMS Balance Success', ['result' => $result]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('KenyaSMS Balance Error', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);
            return null;
        }
    }
    
    /**
     * Check status of a specific message with KenyaSMS
     * Uses GET /sms/status/{message_id}
     */
    public function checkStatus($messageId)
    {
        try {
            $url = $this->baseUrl . '/sms/status/' . $messageId;
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ];
            
            if ($this->sandbox) {
                $headers['X-Sandbox-Mode'] = 'true';
            }
            
            Log::info('Checking message status with KenyaSMS', [
                'message_id' => $messageId,
                'url' => $url
            ]);
            
            $response = $this->client->get($url, [
                'headers' => $headers,
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            Log::info('KenyaSMS Status Response', [
                'message_id' => $messageId,
                'response' => $data
            ]);
            
            $status = $data['data']['status'] ?? $data['status'] ?? 'unknown';
            
            return [
                'success' => true,
                'status' => $status,
                'error' => $data['error'] ?? null,
                'data' => $data
            ];
            
        } catch (\Exception $e) {
            Log::error('KenyaSMS Status Check Error', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to check status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send single SMS (wrapper for sendOne - for compatibility with CampaignService)
     */
    public function sendOne($phone, $message, $messageType = 'transactional', $campaignId = null)
    {
        $url = $this->baseUrl . '/sms/send';
        
        $phone = $this->formatPhone($phone);
        
        $payload = [
            'sender_id' => $this->senderId,
            'recipient' => $phone,
            'message' => $message,
            'message_type' => $messageType,
            'callback_url' => env('KENYASMS_WEBHOOK_URL'),
        ];
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        
        if ($this->sandbox) {
            $headers['X-Sandbox-Mode'] = 'true';
        }
        
        try {
            Log::info('Sending single SMS via KenyaSMS', [
                'phone' => $phone,
                'campaign_id' => $campaignId,
                'message_length' => strlen($message)
            ]);
            
            $response = $this->client->post($url, [
                'headers' => $headers,
                'json' => $payload,
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            Log::info('KenyaSMS sendOne response', [
                'phone' => $phone,
                'result' => $result
            ]);
            
            $success = isset($result['status']) && $result['status'] === 'success';
            
            return [
                'success' => $success,
                'message_id' => $result['message_id'] ?? $result['id'] ?? null,
                'status' => $result['status'] ?? 'unknown',
                'error' => $result['error'] ?? $result['message'] ?? null,
                'raw' => $result
            ];
            
        } catch (\Exception $e) {
            Log::error('KenyaSMS sendOne error', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send personalized campaign to multiple recipients in one API call
     * Uses POST /sms/personalized (mail merge)
     */
    public function sendPersonalizedCampaign($senderId, $messageType, $template, $recipients, $scheduleAt = null, $callbackUrl = null)
    {
        $url = $this->baseUrl . '/sms/personalized';
        
        $payload = [
            'sender_id' => $senderId,
            'message_type' => $messageType,
            'template' => $template,
            'recipients' => $recipients,
        ];
        
        if ($scheduleAt) {
            $payload['schedule_at'] = $scheduleAt;
        }
        
        if ($callbackUrl) {
            $payload['dlr_webhook_url'] = $callbackUrl;
        }
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        
        if ($this->sandbox) {
            $headers['X-Sandbox-Mode'] = 'true';
        }
        
        try {
            Log::info('Sending personalized campaign to KenyaSMS', [
                'sender_id' => $senderId,
                'recipients' => count($recipients),
                'sandbox' => $this->sandbox
            ]);
            
            $response = $this->client->post($url, [
                'headers' => $headers,
                'json' => $payload,
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            Log::info('KenyaSMS personalized campaign response', ['result' => $result]);
            
            return [
                'success' => true,
                'campaign_id' => $result['data']['campaign_id'] ?? $result['campaign_id'] ?? null,
                'data' => $result['data'] ?? $result,
            ];
        } catch (\Exception $e) {
            Log::error('KenyaSMS personalized campaign failed', [
                'error' => $e->getMessage(),
                'recipients' => count($recipients),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get campaign status from KenyaSMS
     * Uses GET /campaigns (list) with pagination and search by ID or name
     */
    public function getCampaignStatus($campaignId)
    {
        try {
            $url = $this->baseUrl . '/campaigns';
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ];
            
            if ($this->sandbox) {
                $headers['X-Sandbox-Mode'] = 'true';
            }
            
            $campaignModel = \App\Modules\SMS\Models\SmsCampaign::where('kenyasms_campaign_id', $campaignId)->first();
            $campaignName = $campaignModel ? $campaignModel->name : null;
            $searchId = str_replace('sandbox_', '', $campaignId);
            
            $page = 1;
            $found = false;
            $campaign = null;
            
            for ($page = 1; $page <= 5; $page++) {
                $response = $this->client->get($url, [
                    'headers' => $headers,
                    'query' => ['page' => $page, 'per_page' => 100],
                ]);
                $result = json_decode($response->getBody(), true);
                
                if (!isset($result['data']['campaigns'])) {
                    break;
                }
                
                foreach ($result['data']['campaigns'] as $c) {
                    if ($c['id'] === $searchId || $c['id'] === $campaignId) {
                        $campaign = $c;
                        $found = true;
                        break 2;
                    }
                    
                    if ($campaignName && strcasecmp($c['name'], $campaignName) === 0) {
                        $campaign = $c;
                        $found = true;
                        break 2;
                    }
                }
                
                if (!isset($result['data']['pagination']['has_more']) || !$result['data']['pagination']['has_more']) {
                    break;
                }
            }
            
            if (!$found || !$campaign) {
                return [
                    'success' => true,
                    'data' => [
                        'sent' => 0,
                        'delivered' => 0,
                        'failed' => 0,
                        'status' => 'unknown',
                        'total_recipients' => 0,
                        'cost' => 0,
                    ],
                    'warning' => 'Campaign not found in KenyaSMS list (searched by ID: ' . $searchId . ' and name: ' . ($campaignName ?? 'N/A') . ')'
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'sent' => $campaign['sent_count'] ?? 0,
                    'delivered' => $campaign['delivered_count'] ?? 0,
                    'failed' => $campaign['failed_count'] ?? 0,
                    'status' => $campaign['status'] ?? 'unknown',
                    'total_recipients' => $campaign['total_recipients'] ?? 0,
                    'cost' => $campaign['actual_cost'] ?? 0,
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('KenyaSMS campaign status failed', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'sent' => 0,
                    'delivered' => 0,
                    'failed' => 0,
                    'status' => 'unknown',
                    'total_recipients' => 0,
                    'cost' => 0,
                ],
                'warning' => 'Error fetching campaign status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get per-recipient delivery logs for a campaign
     * Uses GET /campaigns/{id}/logs (works in production)
     */
    public function getCampaignLogs($campaignId, $page = 1, $perPage = 100)
    {
        $url = $this->baseUrl . '/campaigns/' . $campaignId . '/logs';
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ];
        
        if ($this->sandbox) {
            $headers['X-Sandbox-Mode'] = 'true';
        }
        
        try {
            $response = $this->client->get($url, [
                'headers' => $headers,
                'query' => ['page' => $page, 'per_page' => $perPage],
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            if (!isset($result['data']['logs'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response: missing logs data'
                ];
            }
            
            return [
                'success' => true,
                'logs' => $result['data']['logs'],
                'pagination' => $result['data']['pagination'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('KenyaSMS campaign logs failed', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Format phone number for KenyaSMS
     */
    public function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }
        
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        
        if (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}