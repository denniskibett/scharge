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
        ]);
        
        $this->baseUrl = rtrim(env('KENYASMS_URL', 'https://kenyasms.com'), '/');
        $this->apiKey = env('KENYASMS_KEY');
        $this->senderId = env('KENYASMS_SENDER_ID', 'TextSMS');
        $this->defaultType = env('KENYASMS_DEFAULT_TYPE', 'transactional');
        $this->sandbox = env('KENYASMS_SANDBOX', true);
    }
    
    /**
     * Send personalized SMS (template with variables)
     */
    public function sendPersonalized(array $messages, array $options = []): array
    {
        $url = $this->baseUrl . '/sms/send';
        
        $allResponses = [];
        
        foreach ($messages as $msg) {
            // Send each message individually
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
     * Format phone number for KenyaSMS
     */
    public function formatPhone(string $phone): string
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with 254
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }
        
        // If starts with +, remove it
        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }
        
        // Ensure it starts with 254
        if (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}