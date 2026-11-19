<?php

namespace App\Services\Connectors;

use App\Models\CompanyIntegration;
use App\Models\CompanyKyc;
use App\Models\KycDocument;
use App\Services\Integrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * SMS sender IDs that scharge registers and pays for (Integrations › SMS sender ID), with no scharge staff step:
 *  1. the company types the name and uploads the signed authorisation letter (pre-filled PDF from letterPdf());
 *  2. request() emails the letter + the company's KYC documents to KenyaSMS's sender ID desk (platform KenyaSMS card,
 *     "Send companies' sender ID requests to"), scharge's admins copied; status → submitted;
 *  3. sync() (hourly, and on Test) reads scharge's KenyaSMS sender IDs (GET /account/sender-ids); a name listed as
 *     active/approved switches on for that company (status → approved) and the company's admins are told.
 * SMS always go through scharge's KenyaSMS account, so they come out of the company's per-unit plan.
 */
class SenderIds
{
    public const DOCS = ['kra_pin', 'business_registration', 'cr12', 'national_id', 'passport'];

    public static function letterPdf(int $companyId, string $senderId): string
    {
        $company = \App\Models\Company::find($companyId);
        $kyc = CompanyKyc::where('company_id', $companyId)->first();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('integrations.pdf.sender-letter', [
            'company' => $company, 'kyc' => $kyc, 'senderId' => $senderId, 'date' => now('Africa/Nairobi')->format('j F Y'),
        ])->setPaper('a4')->output();
    }

    /** The company uploaded its signed letter: send the request. Returns a message for the card. */
    public static function request(CompanyIntegration $row): string
    {
        $companyId = (int) $row->company_id;
        $company = \App\Models\Company::find($companyId)?->name ?? "Company {$companyId}";
        $sender = (string) $row->get('sender_id');
        $kyc = CompanyKyc::where('company_id', $companyId)->first();
        $docs = KycDocument::where('company_id', $companyId)->whereIn('kind', self::DOCS)->latest('id')->get()->unique('kind');
        $platform = Integrations::for(null, 'kenyasms');
        $desk = filter_var($platform?->get('sender_requests_to'), FILTER_VALIDATE_EMAIL) ? $platform->get('sender_requests_to') : null;
        $admins = rescue(fn () => \App\Models\User::role('sysadmin')->pluck('email')->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values()->all(), [], false);
        $to = $desk ? [$desk] : $admins;
        $account = $platform?->get('sender_id') ?: config('sms.kenyasms.sender_id');

        $row->mergeCredentials(['approval' => 'submitted', 'submitted_at' => now()->toIso8601String()]);
        $row->save();
        if (! $to || ! \App\Services\EmailOtp::mailWorks()) {
            return 'Letter received. scharge will send your request to the networks.';
        }
        $letter = (string) $row->get('letter_path');
        $body = "Hello,\n\nPlease register the sender ID \"{$sender}\" on scharge's KenyaSMS account (the account sending as {$account}) for:\n\n"
            ."Business: ".($kyc?->business_name ?: $company)."\n"
            ."Registration number: ".($kyc?->registration_number ?: '-')."\n"
            ."KRA PIN: ".($kyc?->kra_pin ?: '-')."\n"
            ."Contact: ".($kyc?->owner_name ?: '-')."\n"
            ."Messages: transactional (rent bills, receipts and reminders to the business's own tenants).\n"
            ."Networks: Safaricom, Airtel, Telkom.\n\n"
            ."Attached: the signed authorisation letter and the business documents.\n\nThank you,\nscharge";
        rescue(fn () => Mail::raw($body, function ($m) use ($to, $desk, $admins, $sender, $company, $letter, $docs) {
            $m->to($to)->subject("Sender ID request: {$sender} ({$company})");
            if ($desk && $admins) {
                $m->cc($admins);
            }
            if ($letter && Storage::disk('local')->exists($letter)) {
                $m->attach(Storage::disk('local')->path($letter), ['as' => 'authorisation-letter-'.$sender.'.'.pathinfo($letter, PATHINFO_EXTENSION)]);
            }
            foreach ($docs as $d) {
                if (Storage::disk('local')->exists($d->path)) {
                    $m->attach(Storage::disk('local')->path($d->path), ['as' => $d->kind.'-'.($d->original_name ?: basename($d->path))]);
                }
            }
        }));

        return $desk
            ? "Sent to the networks for \"{$sender}\". It switches on by itself once approved (usually 5 to 10 working days)."
            : 'Letter received. scharge will send your request to the networks.';
    }

    /** Switch on every requested name that scharge's KenyaSMS account now lists as approved. Returns how many. */
    public static function sync(?CompanyIntegration $only = null): int
    {
        $rows = $only ? collect([$only]) : CompanyIntegration::where('provider', 'kenyasms_sender')->whereNotNull('company_id')->get()
            ->filter(fn ($r) => $r->get('setup') === 'managed' && in_array($r->get('approval'), ['requested', 'submitted'], true));
        if ($rows->isEmpty() || ! ($approved = self::approvedNames())) {
            return 0;
        }
        $n = 0;
        foreach ($rows as $row) {
            if (in_array(strtoupper((string) $row->get('sender_id')), $approved, true)) {
                $row->mergeCredentials(['approval' => 'approved', 'approved_at' => now()->toIso8601String()]);
                $row->save();
                Integrations::forget((int) $row->company_id, 'kenyasms_sender');
                self::tellCompany($row);
                $n++;
            }
        }

        return $n;
    }

    /** @return string[] upper-case sender IDs that are active on scharge's account (null when it cannot be read) */
    public static function approvedNames(): ?array
    {
        $key = config('sms.kenyasms.api_key') ?: config('sms.api_key');
        if (blank($key)) {
            return null;
        }
        $base = rtrim((string) config('sms.kenyasms.base_url', 'https://kenyasms.com/api/v1'), '/');
        foreach (['/account/sender-ids', '/sender-ids'] as $path) {
            $res = rescue(fn () => Http::withToken((string) $key)->acceptJson()->timeout(20)->get($base.$path), null, false);
            if ($res?->successful()) {
                $list = $res->json('data') ?? $res->json();

                return collect(is_array($list) ? $list : [])
                    ->filter(fn ($s) => is_array($s) && in_array(strtolower((string) ($s['status'] ?? 'active')), ['active', 'approved', 'live'], true))
                    ->map(fn ($s) => strtoupper((string) ($s['sender_id'] ?? $s['name'] ?? $s['senderId'] ?? '')))->filter()->values()->all();
            }
        }

        return null;
    }

    private static function tellCompany(CompanyIntegration $row): void
    {
        $to = rescue(fn () => \App\Models\User::where('company_id', $row->company_id)->role(['admin'])->pluck('email')
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values()->all(), [], false);
        if ($to && \App\Services\EmailOtp::mailWorks()) {
            rescue(fn () => Mail::raw("Good news: the networks approved your SMS name \"{$row->get('sender_id')}\". From now on your tenants get bills, receipts and reminders from {$row->get('sender_id')}.",
                fn ($m) => $m->to($to)->subject("Your SMS name {$row->get('sender_id')} is live")));
        }
    }
}
