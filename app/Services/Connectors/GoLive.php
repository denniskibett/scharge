<?php

namespace App\Services\Connectors;

use App\Models\CompanyIntegration;

/**
 * The "Go live" checklist at the top of a company's Integrations page: everything a landlord or agency sets up alone
 * to run on scharge under its own name (money in, SMS name, email, WhatsApp, brand, automatic bills).
 */
class GoLive
{
    /** @return array<int, array{label: string, done: bool, state: string, hint: string, url: ?string, optional: bool}> */
    public static function steps(int $companyId): array
    {
        $rows = CompanyIntegration::where('company_id', $companyId)->get()->keyBy('provider');
        $ok = fn (string $p) => ($r = $rows->get($p)) && $r->enabled && $r->last_test_ok;
        $kyc = \App\Models\CompanyKyc::where('company_id', $companyId)->value('status');
        $brand = \App\Models\CompanyBrand::where('company_id', $companyId)->first();
        $sms = $rows->get('kenyasms_sender');
        $wa = $rows->get('whatsapp_business');
        $integrations = route('integrations.index', ['company' => $companyId]);

        return [
            ['label' => 'Verify your business', 'done' => $kyc === 'verified', 'optional' => false, 'url' => route('kyc.show', ['company' => $companyId]),
                'state' => \App\Models\CompanyKyc::STATUSES[$kyc] ?? 'Not started', 'hint' => 'ID, KRA PIN and business certificate. Needed for your SMS name and the Verified badge.'],
            ['label' => 'Get paid', 'done' => $ok('sharet_payouts') || $ok('mpesa_daraja'), 'optional' => false, 'url' => $integrations,
                'state' => $ok('mpesa_daraja') ? 'Your own M-Pesa paybill' : ($ok('sharet_payouts') ? 'Rent collection & payouts' : 'Not set up'),
                'hint' => 'Rent collection & payouts (M-Pesa, bank or Sharet Pay), or your own M-Pesa Daraja paybill. Press Test once it is saved.'],
            ['label' => 'SMS under your name', 'done' => $sms?->enabled && $sms->get('approval') === 'approved', 'optional' => false, 'url' => $integrations,
                'state' => match ($sms?->get('approval')) { 'approved' => 'Live as '.$sms->get('sender_id'), 'submitted' => 'With the networks', 'requested' => 'Upload the signed letter', default => 'Not set up' },
                'hint' => 'SMS sender ID: paid by scharge in your plan.'],
            ['label' => 'Email from your address', 'done' => $ok('company_email'), 'optional' => false, 'url' => $integrations,
                'state' => $ok('company_email') ? 'From '.$rows['company_email']->get('from_address') : 'Not set up', 'hint' => 'Bills and receipts from your own mailbox.'],
            ['label' => 'Your brand', 'done' => (bool) ($brand?->display_name && $brand?->logo_path), 'optional' => false, 'url' => route('brand.edit', ['company' => $companyId]),
                'state' => $brand?->display_name ?: 'Not set up', 'hint' => 'Logo, colours and name on bills, emails, the app and your website.'],
            ['label' => 'Bills go out by themselves', 'done' => (bool) $rows->get('rent_automation')?->enabled, 'optional' => false, 'url' => $integrations,
                'state' => $rows->get('rent_automation')?->enabled ? 'On' : 'Off', 'hint' => 'Rent automation: monthly bills and late reminders.'],
            ['label' => 'WhatsApp from your number', 'done' => $wa?->enabled && ($wa->get('mode') === 'managed' ? $wa->get('approval') === 'approved' : (bool) $wa->last_test_ok), 'optional' => true, 'url' => $integrations,
                'state' => $wa ? ($wa->get('mode') === 'managed' ? ($wa->get('approval') === 'approved' ? 'Live' : 'Being set up') : ($wa->last_test_ok ? 'Live' : 'Not tested')) : 'Not set up', 'hint' => 'Optional.'],
        ];
    }
}
