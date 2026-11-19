<?php

namespace App\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/** Mailgun's HTTP API (no extra package): the finished MIME message goes to /messages.mime. */
class MailgunApiTransport extends AbstractTransport
{
    public function __construct(private string $domain, private string $key, private string $region = 'us')
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $to = collect($message->getEnvelope()->getRecipients())->map(fn ($a) => $a->getAddress())->implode(',');
        $res = Http::withBasicAuth('api', $this->key)->timeout(20)->asMultipart()
            ->attach('message', $message->toString(), 'message.mime')
            ->post('https://'.($this->region === 'eu' ? 'api.eu.mailgun.net' : 'api.mailgun.net').'/v3/'.$this->domain.'/messages.mime', ['to' => $to]);
        if (! $res->successful()) {
            throw new \RuntimeException('Mailgun answered HTTP '.$res->status().': '.mb_substr((string) ($res->json('message') ?? $res->body()), 0, 150));
        }
    }

    public function __toString(): string
    {
        return 'mailgun+api://'.$this->domain;
    }
}
