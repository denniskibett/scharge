<?php

namespace App\Modules\SMS\Services;

use App\Models\CampaignRecipient;
use App\Modules\SMS\Models\SmsLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class KenyaSMS
{
    protected $apiKey;
    protected $senderId;
    protected $sandbox;
    protected $baseUrl;
    protected $defaultType;

    public function __construct()
    {
        // Load from config
        $this->apiKey = config('sms.kenyasms.api_key', '');
        $this->senderId = config('sms.kenyasms.sender_id', 'SHARETENT');
        $this->sandbox = config('sms.kenyasms.sandbox', true);
        $this->baseUrl = config('sms.kenyasms.base_url', 'https://kenyasms.com/api/v1');
        $this->defaultType = config('sms.kenyasms.default_type', 'transactional');
        
        Log::info('KenyaSMS initialized', [
            'sender_id' => $this->senderId,
            'sandbox' => $this->sandbox,
            'base_url' => $this->baseUrl
        ]);
    }

    /**
     * Send a single SMS
     */
    public function sendOne($phone, $message, $type = null, $campaignId = null)
    {
        $type = $type ?? $this->defaultType;
        
        Log::info('KenyaSMS: Sending SMS', [
            'phone' => $phone,
            'message_length' => strlen($message),
            'type' => $type,
            'campaign_id' => $campaignId,
            'sandbox' => $this->sandbox
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
                ->timeout(30)
                ->post($this->baseUrl . '/sms/send', $payload);

            Log::info('KenyaSMS: Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['data']['message_id'] ?? $data['message_id'] ?? null;
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
     * Send personalized SMS messages
     */
    public function sendPersonalized($template, $recipients, $type = null, $campaignId = null)
    {
        $type = $type ?? $this->defaultType;
        
        Log::info('KenyaSMS: Sending personalized SMS', [
            'recipients_count' => count($recipients),
            'campaign_id' => $campaignId,
            'type' => $type
        ]);

        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? $recipient['phone_number'] ?? '';
            $message = $recipient['message'] ?? $template;

            if (empty($phone)) {
                Log::warning('KenyaSMS: Skipping recipient - no phone', [
                    'recipient' => $recipient
                ]);
                $failed++;
                continue;
            }

            $result = $this->sendOne($phone, $message, $type, $campaignId);

            if ($result['success']) {
                $sent++;
                // Update recipient with message_id if exists
                if (isset($recipient['id']) && isset($result['message_id'])) {
                    try {
                        CampaignRecipient::where('id', $recipient['id'])
                            ->update([
                                'message_id' => $result['message_id'],
                                'status' => $result['status'] ?? 'queued',
                                'provider_status' => $result['status'] ?? 'queued',
                                'provider_response' => json_encode($result['data'] ?? [])
                            ]);
                    } catch (\Exception $e) {
                        Log::error('KenyaSMS: Failed to update recipient', [
                            'recipient_id' => $recipient['id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } else {
                $failed++;
                // Update recipient with error
                if (isset($recipient['id'])) {
                    try {
                        CampaignRecipient::where('id', $recipient['id'])
                            ->update([
                                'status' => 'failed',
                                'error_message' => $result['error'] ?? 'Unknown error'
                            ]);
                    } catch (\Exception $e) {
                        Log::error('KenyaSMS: Failed to update recipient error', [
                            'recipient_id' => $recipient['id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            $results[] = $result;
        }

        return [
            'success' => true,
            'data' => [
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($recipients),
                'results' => $results
            ]
        ];
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

        if ($this->sandbox) {
            // In sandbox mode, simulate status progression
            $statuses = ['queued', 'sent', 'delivered', 'failed'];
            $randomStatus = $statuses[array_rand($statuses)];
            
            return [
                'success' => true,
                'status' => $randomStatus,
                'response' => 'Sandbox mode: ' . $randomStatus
            ];
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($this->baseUrl . '/sms/status/' . $messageId);

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['data']['status'] ?? $data['status'] ?? 'unknown';
                
                return [
                    'success' => true,
                    'status' => $status,
                    'response' => $data
                ];
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
        if ($this->sandbox) {
            return [
                'success' => true,
                'balance' => '1000.00',
                'currency' => 'KES'
            ];
        }

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];

            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($this->baseUrl . '/wallet/balance');

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'balance' => $data['data']['balance'] ?? $data['balance'] ?? '0.00',
                    'currency' => $data['data']['currency'] ?? $data['currency'] ?? 'KES'
                ];
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
     * Format phone number to international format
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
     * Validate phone number
     */
    public function validatePhone($phone)
    {
        $formatted = $this->formatPhoneNumber($phone);
        
        if ($formatted && preg_match('/^254[7-9][0-9]{8}$/', $formatted)) {
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
        
        // Rates (can be made configurable)
        $rates = [
            'transactional' => 0.45,
            'promotional' => 0.45
        ];
        
        $rate = $rates[$type] ?? 0.45;
        
        return $parts * $rate;
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
}