<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSService
{
    protected $apiKey;
    protected $senderId;
    protected $baseUrl;
    protected $sandbox;

    public function __construct()
    {
        $this->apiKey = config('services.sms.api_key', '');
        $this->senderId = config('services.sms.sender_id', 'WATERBILL');
        $this->baseUrl = config('services.sms.base_url', 'https://api.africastalking.com/version1');
        $this->sandbox = config('services.sms.sandbox', true);
    }

    public function sendSms($phone, $message, $type = 'transactional')
    {
        $phone = $this->formatPhone($phone);
        
        Log::info('Sending SMS', [
            'phone' => $phone,
            'message' => $message,
            'type' => $type,
            'sandbox' => $this->sandbox
        ]);

        if ($this->sandbox) {
            return [
                'success' => true,
                'message' => 'Sandbox mode - SMS not sent',
                'phone' => $phone,
                'sandbox' => true
            ];
        }

        try {
            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->post($this->baseUrl . '/messaging', [
                'username' => 'sandbox',
                'to' => $phone,
                'message' => $message,
                'from' => $this->senderId,
                'bulkSMSMode' => 1,
                'options' => ['enqueue' => 1]
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['SMSMessageData']['Recipients'])) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'data' => $data['SMSMessageData']['Recipients'][0] ?? null
                ];
            }

            return [
                'success' => false,
                'message' => $data['error'] ?? 'Failed to send SMS'
            ];

        } catch (\Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    protected function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        if (substr($phone, 0, 2) === '07') {
            $phone = '254' . substr($phone, 1);
        }
        
        return $phone;
    }

    public function isValidPhone($phone)
    {
        $phone = $this->formatPhone($phone);
        return preg_match('/^254[17]\d{8}$/', $phone);
    }
}