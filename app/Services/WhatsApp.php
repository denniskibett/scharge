<?php

namespace App\Services;

use App\Helpers\PhoneHelper;
use App\Models\CompanyIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business through Meta's Cloud API (Integrations › WhatsApp).
 *
 * A company's own number ("whatsapp_business") wins over scharge's ("whatsapp"). Messages to people who have not
 * written in the last 24 hours must use an approved template; the message text goes in as its {{1}} variable.
 * Without a configured number, link() still gives a wa.me link to open the chat by hand.
 */
class WhatsApp
{
    public function __construct(private ?CompanyIntegration $row = null)
    {
    }

    /** The number to send from for a company (null = scharge's own). */
    public static function forCompany(?int $companyId): self
    {
        $own = $companyId ? Integrations::for($companyId, 'whatsapp_business') : null;

        return new self(self::usable($own) ? $own : Integrations::for(null, 'whatsapp'));
    }

    private static function usable(?CompanyIntegration $row): bool
    {
        return $row && $row->get('mode', 'cloud') === 'cloud' && filled($row->get('phone_number_id')) && filled($row->get('access_token'));
    }

    public function canSend(): bool
    {
        return self::usable($this->row);
    }

    /** wa.me link that opens a chat with the message typed in (works without any setup). */
    public static function link(?string $phone, string $message = ''): ?string
    {
        $e164 = PhoneHelper::e164($phone);

        return $e164 ? 'https://wa.me/'.ltrim($e164, '+').($message !== '' ? '?text='.rawurlencode($message) : '') : null;
    }

    /**
     * Send a message. Uses the template when one is set (works any time), otherwise plain text
     * (only delivered inside the 24-hour window after the person last wrote to you).
     *
     * @return array{success: bool, status: string, message: string, id?: string}
     */
    public function send(?string $phone, string $message): array
    {
        $to = PhoneHelper::e164($phone);
        if (! $to) {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Not a valid phone number.'];
        }
        if (! $this->canSend()) {
            return ['success' => false, 'status' => 'not_configured', 'message' => 'WhatsApp is not set up. Open the chat instead: '.self::link($phone, $message)];
        }

        $template = trim((string) $this->row->get('template'));
        $payload = ['messaging_product' => 'whatsapp', 'to' => ltrim($to, '+')] + ($template !== ''
            ? ['type' => 'template', 'template' => [
                'name' => $template,
                'language' => ['code' => $this->row->get('language') ?: 'en'],
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr($message, 0, 1000)]]]],
            ]]
            : ['type' => 'text', 'text' => ['body' => mb_substr($message, 0, 4096), 'preview_url' => true]]);

        try {
            $res = Http::withToken((string) $this->row->get('access_token'))->acceptJson()->timeout(20)
                ->post($this->url($this->row->get('phone_number_id').'/messages'), $payload);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'status' => 'error', 'message' => 'Could not reach WhatsApp.'];
        }

        if ($res->successful()) {
            return ['success' => true, 'status' => 'sent', 'message' => 'Sent on WhatsApp.', 'id' => $res->json('messages.0.id')];
        }

        return ['success' => false, 'status' => 'failed', 'message' => 'WhatsApp: '.($res->json('error.message') ?? 'HTTP '.$res->status())];
    }

    /** Integrations › Test connection. @return array{0: bool, 1: string} */
    public static function test(CompanyIntegration $row): array
    {
        if ($row->get('mode', 'cloud') === 'links') {
            return [true, 'Share links only: chats open in WhatsApp with the message typed in. Nothing is sent automatically.'];
        }
        if (! self::usable($row)) {
            return [false, 'Add the phone number ID and access token first.'];
        }
        $res = Http::withToken((string) $row->get('access_token'))->acceptJson()->timeout(20)
            ->get((new self($row))->url((string) $row->get('phone_number_id')), ['fields' => 'display_phone_number,verified_name,quality_rating']);

        return $res->successful()
            ? [true, 'Connected: '.$res->json('verified_name').' ('.$res->json('display_phone_number').'), quality '.strtolower((string) $res->json('quality_rating', 'unknown')).'.']
            : [false, 'WhatsApp rejected the details: '.($res->json('error.message') ?? 'HTTP '.$res->status())];
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.($this->row?->get('api_version') ?: 'v21.0').'/'.ltrim($path, '/');
    }
}
