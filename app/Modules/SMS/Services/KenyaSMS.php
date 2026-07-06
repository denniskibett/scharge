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
        $kenyaSmsConfig = config('services.kenyasms', []);

        $this->apiKey = $kenyaSmsConfig['key'] ?? null;
        $this->baseUrl = $kenyaSmsConfig['url'] ?? 'https://kenyasms.com/api/v1';
        $this->senderId = $kenyaSmsConfig['sender_id'] ?? 'SHARETENT';
        $this->defaultType = $kenyaSmsConfig['default_type'] ?? 'transactional';
        $this->sandbox = $kenyaSmsConfig['sandbox'] ?? false;
    }

    /**
     * Send a single SMS, optionally linked to a campaign.
     */
    public function sendOne(string $phone, string $message, ?string $messageType = null, ?int $campaignId = null): array
    {
        $phone = PhoneHelper::clean($phone);
        if (!$phone) {
            return ['success' => false, 'error' => 'Invalid Kenyan phone number'];
        }

        // ✅ ADD DEBUG LOGGING
        \Log::info('📤 KenyaSMS Request', [
            'phone' => $phone,
            'url' => $this->baseUrl . '/sms/send',
            'sender_id' => $this->senderId,
            'sandbox' => $this->sandbox,
        ]);

        if ($this->sandbox) {
            SmsLog::create([
                'recipient_phone' => $phone,
                'message' => $message,
                'status' => 'sent',
                'campaign_id' => $campaignId,
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

            // ✅ ADD DEBUG LOGGING
            \Log::info('📥 KenyaSMS Response', [
                'status' => $response->status(),
                'body' => $body,
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                SmsLog::create([
                    'recipient_phone' => $phone,
                    'message' => $message,
                    'status' => 'sent',
                    'provider_message_id' => $body['data']['message_id'] ?? null,
                    'campaign_id' => $campaignId,
                ]);
                return ['success' => true, 'data' => $body['data'] ?? []];
            } else {
                SmsLog::create([
                    'recipient_phone' => $phone,
                    'message' => $message,
                    'status' => 'failed',
                    'failure_reason' => $body['error']['message'] ?? 'Unknown error',
                    'campaign_id' => $campaignId,
                ]);
                return ['success' => false, 'error' => $body['error']['message'] ?? 'API error'];
            }
        } catch (\Exception $e) {
            \Log::error('❌ KenyaSMS Exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            
            SmsLog::create([
                'recipient_phone' => $phone,
                'message' => $message,
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'campaign_id' => $campaignId,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send personalized bulk SMS, optionally linked to a campaign.
     */
    public function sendPersonalized(string $template, array $recipients, ?string $messageType = null, ?int $campaignId = null): array
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
            $result = $this->sendOne($phone, $message, $messageType, $campaignId);
            
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

    /**
     * Get account balance from KenyaSMS (real endpoint)
     */
    public function getBalance(): array
    {
        if ($this->sandbox) {
            return ['success' => true, 'balance' => 9999.00, 'currency' => 'KES'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/account/balance');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['balance_kes'])) {
                    $balance = (float) $data['data']['balance_kes'];
                    $currency = $data['data']['currency'] ?? 'KES';
                    return ['success' => true, 'balance' => $balance, 'currency' => $currency];
                } else {
                    \Log::error('KenyaSMS balance response missing balance_kes', ['response' => $data]);
                    return ['success' => false, 'error' => 'Balance not found in response'];
                }
            } else {
                $error = $response->json()['error']['message'] ?? 'Failed to fetch balance';
                return ['success' => false, 'error' => $error];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
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