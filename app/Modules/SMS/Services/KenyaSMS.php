<?php

namespace App\Modules\SMS\Services;

use Illuminate\Support\Facades\Http;
use App\Modules\SMS\Models\SmsLog;
use App\Modules\SMS\Helpers\PhoneHelper;

class KenyaSMS
{
    protected $apiKey;
    protected $baseUrl;
    protected $senderId;
    protected $defaultType;
    protected $sandbox;

    public function __construct()
    {
        $config = config('sms');
        $kenyaSmsConfig = $config['kenyasms'] ?? $config;
        
        $this->apiKey = $kenyaSmsConfig['api_key'] ?? env('KENYASMS_KEY');
        $this->baseUrl = $kenyaSmsConfig['base_url'] ?? 'https://kenyasms.com/api/v1';
        $this->senderId = $kenyaSmsConfig['sender_id'] ?? 'SHARETENT';
        $this->defaultType = $kenyaSmsConfig['default_type'] ?? 'transactional';
        $this->sandbox = $kenyaSmsConfig['sandbox'] ?? true;
    }

    public function sendOne(string $phone, string $message, ?string $messageType = null): array
    {
        $phone = PhoneHelper::clean($phone);
        if (!$phone) {
            return ['success' => false, 'error' => 'Invalid Kenyan phone number'];
        }

        // For testing without API
        if ($this->sandbox) {
            SmsLog::create([
                'recipient_phone' => $phone,
                'message' => $message,
                'status' => 'sent',
                'meta' => ['sandbox' => true],
            ]);
            return ['success' => true, 'data' => ['message_id' => 'sandbox_' . time()]];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/sms/send', [
                'sender_id' => $this->senderId,
                'recipient' => $phone,
                'message' => $message,
                'message_type' => $messageType ?? $this->defaultType,
            ]);

            $body = $response->json();

            if ($response->successful()) {
                SmsLog::create([
                    'recipient_phone' => $phone,
                    'message' => $message,
                    'status' => 'sent',
                    'provider_message_id' => $body['data']['message_id'] ?? null,
                ]);
                return ['success' => true, 'data' => $body['data'] ?? []];
            } else {
                SmsLog::create([
                    'recipient_phone' => $phone,
                    'message' => $message,
                    'status' => 'failed',
                    'failure_reason' => $body['error']['message'] ?? 'Unknown error',
                ]);
                return ['success' => false, 'error' => $body['error']['message'] ?? 'API error'];
            }
        } catch (\Exception $e) {
            SmsLog::create([
                'recipient_phone' => $phone,
                'message' => $message,
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendPersonalized(string $template, array $recipients, ?string $messageType = null): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $phone = PhoneHelper::clean($recipient['phone'] ?? null);
            if (!$phone) {
                $failed++;
                continue;
            }

            $message = $this->renderTemplate($template, $recipient['variables'] ?? []);
            $result = $this->sendOne($phone, $message, $messageType);
            
            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $sent > 0,
            'data' => ['sent' => $sent, 'failed' => $failed],
        ];
    }

    protected function renderTemplate(string $template, array $variables): string
    {
        $rendered = $template;
        foreach ($variables as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', $value, $rendered);
        }
        return $rendered;
    }
}
