<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SmsService
{
    protected $baseUrl;
    protected $apiKey;
    protected $senderId;

    public function __construct()
    {
        $this->baseUrl = config('sms.base_url');
        $this->apiKey = config('sms.api_key');
        $this->senderId = config('sms.sender_id');
        $this->useSender(Integrations::smsSender()); // the signed-in user's company sender, if set
    }

    /** The same service, sending as a given company (null = scharge's own sender). */
    public function forCompany(?int $companyId): static
    {
        $copy = clone $this;
        $copy->useSender(Integrations::smsSender($companyId));

        return $copy;
    }

    protected function useSender(array $sender): void
    {
        $this->senderId = $sender['sender_id'] ?: $this->senderId;
        $this->apiKey = $sender['api_key'] ?: $this->apiKey;
    }

    public function send($phone, $message, $type = 'transactional')
    {
        // KenyaSMS only reaches Kenyan numbers; foreign ones go by WhatsApp or email instead.
        if (\App\Helpers\PhoneHelper::isForeign($phone)) {
            // Send it on WhatsApp instead when a WhatsApp number is set up (company's own, else scharge's).
            $wa = \App\Services\WhatsApp::forCompany(auth()->user()?->company_id);
            if ($wa->canSend()) {
                return $wa->send($phone, $message) + ['channel' => 'whatsapp', 'foreign' => true];
            }

            return ['success' => false, 'status' => 'skipped', 'foreign' => true, 'whatsapp_link' => \App\Services\WhatsApp::link($phone, $message),
                'message' => 'International number ('.\App\Helpers\PhoneHelper::foreignCountry($phone).'): SMS is for Kenyan numbers. Set up WhatsApp under Integrations, or use the WhatsApp link.'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/sms/send', [
            'sender_id' => $this->senderId,
            'recipient' => $this->formatPhone($phone),
            'message' => $message,
            'message_type' => $type,
        ]);

        return $response->json();
    }

    private function formatPhone($phone)
    {
        // Normalize to 2547XXXXXXXX
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '07')) {
            return '254' . substr($phone, 1);
        }

        if (str_starts_with($phone, '+254')) {
            return substr($phone, 1);
        }

        return $phone;
    }
}