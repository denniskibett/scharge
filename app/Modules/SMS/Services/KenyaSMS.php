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
        // Load from config
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
            // Format phone number
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

            // Add schedule if provided
            if ($scheduleAt) {
                $payload['schedule_at'] = $scheduleAt;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            // Add sandbox header if enabled
            if ($this->sandbox) {
                $headers['X-Sandbox-Mode'] = 'true';
            }

            Log::info('KenyaSMS: Request', [
                'url' => $this->baseUrl . '/sms/send',
                'payload' => $payload
            ]);

            $response = Http::withHeaders($headers)
                ->withoutVerifying()  // SSL fix for development
                ->timeout(30)
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

                // Log the SMS
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

        // Format all phone numbers
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

            // Add schedule if provided
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
                ->withoutVerifying()  // SSL fix for development
                ->timeout(60)
                ->post($this->baseUrl . '/sms/bulk', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                // Log each recipient
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

        // Prepare recipients with variables
        $preparedRecipients = [];
        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? $recipient['phone_number'] ?? '';
            $formatted = $this->formatPhoneNumber($phone);
            
            if ($formatted) {
                $variables = $recipient['variables'] ?? [];
                $preparedRecipients[] = [
                    'phone' => $formatted,      // FIXED: Changed from 'recipient' to 'phone'
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
                ->withoutVerifying()  // SSL fix for development
                ->timeout(60)
                ->post($this->baseUrl . '/sms/personalized', $payload);

            Log::info('KenyaSMS: Personalized Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Log each recipient
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

    /**
     * Get message status from provider
     */
    public function getMessageStatus($messageId)
    {
        if (empty($messageId)) {
            return [
                'success' => false,
                'error' => 'Message ID is required'
            ];
        }

        // Try cache first
        $cacheKey = 'kenyasms_status_' . $messageId;
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        if ($this->sandbox) {
            // In sandbox mode, simulate status progression
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
                ->withoutVerifying()  // SSL fix for development
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

    /**
     * Get delivery report for a message
     */
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
                ->withoutVerifying()  // SSL fix for development
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

    /**
     * Get account balance
     */
    public function getBalance()
    {
        // Try cache first
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
                ->withoutVerifying()  // SSL fix for development
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

    /**
     * Get sender IDs
     */
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
                ->withoutVerifying()  // SSL fix for development
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

    /**
     * Check if a message is promotional (subject to quiet hours)
     */
    public function isPromotional($messageType)
    {
        return $messageType === 'promotional';
    }

    /**
     * Check if currently in quiet hours
     */
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

    /**
     * Log SMS to database
     */
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

    /**
     * Format phone number to international format (254XXXXXXXXX)
     */
    public function formatPhoneNumber($phone)
    {
        if (empty($phone)) {
            return null;
        }
        
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading 0
        if (substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }
        
        // Add 254 if starting with 7
        if (substr($phone, 0, 1) === '7') {
            $phone = '254' . $phone;
        }
        
        // If already 254 and length is 12, it's valid
        if (substr($phone, 0, 3) === '254' && strlen($phone) === 12) {
            return $phone;
        }
        
        // If length is 10 and starts with 7, add 254
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '7') {
            return '254' . $phone;
        }
        
        // If length is 9, add 2547
        if (strlen($phone) === 9) {
            return '2547' . $phone;
        }
        
        Log::warning('KenyaSMS: Invalid phone number format', [
            'original' => $phone,
            'cleaned' => $phone
        ]);
        
        return null;
    }

    /**
     * Validate phone number (Safaricom only)
     */
    public function validatePhone($phone)
    {
        $formatted = $this->formatPhoneNumber($phone);
        
        // Check if it's a Safaricom number (2547XXXXXXXX)
        if ($formatted && preg_match('/^2547[0-9]{8}$/', $formatted)) {
            return $formatted;
        }
        
        return null;
    }

    /**
     * Get message parts count
     */
    public function getMessageParts($message)
    {
        $length = strlen($message);
        
        // Check if message contains Unicode characters
        $isUnicode = preg_match('/[^\x00-\x7F]/', $message);
        
        if ($isUnicode) {
            // Unicode: 70 chars per part, 67 chars after first
            if ($length <= 70) return 1;
            return ceil(($length - 70) / 67) + 1;
        } else {
            // GSM-7: 160 chars per part, 153 chars after first
            if ($length <= 160) return 1;
            return ceil(($length - 160) / 153) + 1;
        }
    }

    /**
     * Get estimated cost for a message
     */
    public function getEstimatedCost($message, $type = null)
    {
        $parts = $this->getMessageParts($message);
        $type = $type ?? $this->defaultType;
        
        // Rates from KenyaSMS
        $rates = [
            'transactional' => 0.45,
            'promotional' => 0.45
        ];
        
        $rate = $rates[$type] ?? 0.45;
        
        return number_format($parts * $rate, 2);
    }

    /**
     * Map provider status to internal status
     */
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

    /**
     * Check if sandbox mode is enabled
     */
    public function isSandbox()
    {
        return $this->sandbox;
    }

    /**
     * Get sender ID
     */
    public function getSenderId()
    {
        return $this->senderId;
    }

    /**
     * Get default message type
     */
    public function getDefaultType()
    {
        return $this->defaultType;
    }

    /**
     * Set sender ID
     */
    public function setSenderId($senderId)
    {
        $this->senderId = $senderId;
        return $this;
    }

    /**
     * Set sandbox mode
     */
    public function setSandbox($sandbox)
    {
        $this->sandbox = (bool) $sandbox;
        return $this;
    }
}