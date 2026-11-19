<?php

namespace App\Services\Connectors;

use App\Models\CompanyIntegration;
use App\Services\Integrations;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Integrations › Email from your address: tenant emails (bills, receipts, gate passes) leave from the company's own
 * mailbox through its provider, instead of scharge's. Without the card (or when it is off) scharge's mailer is used.
 */
class CompanyMail
{
    private const PRESETS = [
        'google' => ['smtp.gmail.com', 587, 'tls'],
        'microsoft' => ['smtp.office365.com', 587, 'tls'],
        'zoho' => ['smtp.zoho.com', 587, 'tls'],
    ];

    /** Send a tenant-facing email for a company: from its own address when set up, else scharge's. */
    public static function send(?int $companyId, string $to, Mailable $mail): void
    {
        $row = $companyId ? Integrations::for($companyId, 'company_email') : null;
        if (! $row || blank($row->get('from_address'))) {
            Mail::to($to)->send($mail);

            return;
        }
        Mail::mailer(self::register($row))->to($to)->send(self::brand($row, $mail));
    }

    public static function test(CompanyIntegration $row): array
    {
        $from = (string) $row->get('from_address');
        if (! filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return [false, '"Send from" must be an email address.'];
        }
        $to = auth()->user()?->email;
        try {
            if ($row->get('provider') !== 'mailgun') {
                [$host, $port, $enc] = self::server($row);
                $t = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport($host, $port, $enc === 'ssl');
                $t->setUsername((string) ($row->get('username') ?: $from));
                $t->setPassword((string) $row->get('password'));
                $t->start();
                $t->stop();
            }
            if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return [true, 'Signed in to the mail server. Add an email to your own profile to receive a test message.'];
            }
            Mail::mailer(self::register($row))->raw(
                "This is a test from scharge. Your tenants' bills and receipts will now come from {$from}.",
                fn ($m) => $m->to($to)->from($from, $row->get('from_name') ?: null)->subject('scharge test email')
            );

            return [true, "Sent a test email from {$from} to {$to}. Check that inbox (and spam)."];
        } catch (\Throwable $e) {
            return [false, 'The mail provider refused: '.mb_substr($e->getMessage(), 0, 180)];
        }
    }

    /** Defines this company's mailer for the request and returns its name. */
    private static function register(CompanyIntegration $row): string
    {
        $name = 'company_'.$row->company_id;
        if ($row->get('provider') === 'mailgun') {
            Mail::extend('scharge_mailgun', fn (array $c) => new MailgunApiTransport($c['domain'], $c['secret'], $c['region']));
            config(["mail.mailers.$name" => ['transport' => 'scharge_mailgun', 'domain' => $row->get('mailgun_domain'),
                'secret' => $row->get('mailgun_key'), 'region' => $row->get('mailgun_region', 'us')]]);
        } else {
            [$host, $port, $enc] = self::server($row);
            config(["mail.mailers.$name" => ['transport' => 'smtp', 'host' => $host, 'port' => $port, 'scheme' => $enc === 'ssl' ? 'smtps' : null,
                'username' => $row->get('username') ?: $row->get('from_address'), 'password' => $row->get('password'), 'timeout' => 20]]);
        }
        Mail::purge($name);

        return $name;
    }

    private static function server(CompanyIntegration $row): array
    {
        if ($preset = self::PRESETS[$row->get('provider')] ?? null) {
            return $preset;
        }
        $port = (int) $row->get('port', 587);

        return [(string) $row->get('host'), $port, $row->get('encryption', $port === 465 ? 'ssl' : 'tls')];
    }

    private static function brand(CompanyIntegration $row, Mailable $mail): Mailable
    {
        $name = $row->get('from_name') ?: \App\Support\Brand::forCompany((int) $row->company_id)?->display_name
            ?: \App\Models\Company::find($row->company_id)?->name;
        $mail->from((string) $row->get('from_address'), $name);
        if (filter_var($row->get('reply_to'), FILTER_VALIDATE_EMAIL)) {
            $mail->replyTo((string) $row->get('reply_to'), $name);
        }

        return $mail;
    }
}
