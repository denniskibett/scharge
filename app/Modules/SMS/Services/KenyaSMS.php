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

        $response = $this->callApi('/sms/send', [
            'sender_id' => $this->senderId,
            'recipient' => $phone,
            'message' => $message,
            'message_type' => $messageType ?? $this->defaultType,
        ]);

        SmsLog::create([
            'recipient_phone' => $phone,
            'message' => $message,
            'status' => $response['success'] ? 'sent' : 'failed',
            'provider_message_id' => $response['data']['message_id'] ?? null,
            'failure_reason' => $response['error'] ?? null,
            'meta' => ['api_response' => $response],
        ]);

        return $response;
    }

    public function sendBulk(array $phones, string $message, ?string $messageType = null): array
    {
        $validPhones = [];
        $invalidPhones = [];
        foreach ($phones as $phone) {
            $cleaned = PhoneHelper::clean($phone);
            if ($cleaned) {
                $validPhones[] = $cleaned;
            } else {
                $invalidPhones[] = $phone;
            }
        }

        if (empty($validPhones)) {
            return ['success' => false, 'error' => 'No valid phone numbers provided'];
        }

        $response = $this->callApi('/sms/bulk', [
            'sender_id' => $this->senderId,
            'recipients' => $validPhones,
            'message' => $message,
            'message_type' => $messageType ?? $this->defaultType,
        ]);

        if ($response['success']) {
            $campaignId = $response['data']['campaign_id'] ?? null;
            foreach ($validPhones as $phone) {
                SmsLog::create([
                    'recipient_phone' => $phone,
                    'message' => $message,
                    'status' => 'sent',
                    'provider_message_id' => $campaignId,
                    'meta' => ['campaign' => true, 'invalid_phones' => $invalidPhones],
                ]);
            }
        } else {
            foreach ($validPhones as $phone) {
                SmsLog::create([
                    'recipient_phone' => $phone,
                    'message' => $message,
                    'status' => 'failed',
                    'failure_reason' => $response['error'] ?? 'Unknown API error',
                    'meta' => ['invalid_phones' => $invalidPhones],
                ]);
            }
        }

        return $response;
    }

    public function sendPersonalized(string $template, array $recipients, ?string $messageType = null): array
    {
        $apiRecipients = [];
        $invalidEntries = [];

        foreach ($recipients as $recipient) {
            $phone = PhoneHelper::clean($recipient['phone'] ?? null);
            if (!$phone) {
                $invalidEntries[] = $recipient;
                continue;
            }
            $apiRecipients[] = [
                'phone' => $phone,
                'variables' => $recipient['variables'] ?? [],
            ];
        }

        if (empty($apiRecipients)) {
            return ['success' => false, 'error' => 'No valid recipients with phone numbers'];
        }

        $response = $this->callApi('/sms/personalized', [
            'sender_id' => $this->senderId,
            'template' => $template,
            'message_type' => $messageType ?? $this->defaultType,
            'recipients' => $apiRecipients,
        ]);

        if ($response['success']) {
            $campaignId = $response['data']['campaign_id'] ?? null;
            foreach ($apiRecipients as $apiRecipient) {
                $renderedMessage = $this->renderTemplate($template, $apiRecipient['variables']);
                SmsLog::create([
                    'recipient_phone' => $apiRecipient['phone'],
                    'message' => $renderedMessage,
                    'status' => 'sent',
                    'provider_message_id' => $campaignId,
                    'meta' => ['personalized' => true, 'variables' => $apiRecipient['variables'], 'invalid_entries' => $invalidEntries],
                ]);
            }
        } else {
            foreach ($apiRecipients as $apiRecipient) {
                $renderedMessage = $this->renderTemplate($template, $apiRecipient['variables']);
                SmsLog::create([
                    'recipient_phone' => $apiRecipient['phone'],
                    'message' => $renderedMessage,
                    'status' => 'failed',
                    'failure_reason' => $response['error'] ?? 'Unknown API error',
                    'meta' => ['personalized' => true, 'variables' => $apiRecipient['variables'], 'invalid_entries' => $invalidEntries],
                ]);
            }
        }

        return $response;
    }

    protected function renderTemplate(string $template, array $variables): string
    {
        $rendered = $template;
        foreach ($variables as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', $value, $rendered);
        }
        return $rendered;
    }

    protected function callApi(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
        if ($this->sandbox) {
            $headers['X-Sandbox-Mode'] = 'true';
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->post($url, $payload);

            $body = $response->json();

            if ($response->successful() && isset($body['success']) && $body['success'] === true) {
                return ['success' => true, 'data' => $body['data']];
            } else {
                $error = $body['error']['message'] ?? 'Unknown API error';
                return ['success' => false, 'error' => $error];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function checkBalance(): ?float
    {
        $response = $this->callApi('/account/balance', []);
        if ($response['success']) {
            return $response['data']['balance_kes'] ?? null;
        }
        return null;
    }
}
