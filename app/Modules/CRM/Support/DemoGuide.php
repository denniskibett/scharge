<?php

namespace App\Modules\CRM\Support;

use App\Modules\CRM\Models\CrmFollowUp;
use App\Modules\CRM\Services\DemoRequestService as Demo;
use Illuminate\Support\Carbon;

/**
 * "How to handle this request": what the account manager does with a demo request, from the first call to the
 * customer being live. Built from what the prospect asked for (DemoRequestService) and the segment's playbook.
 * Sent as its own email right after the demo notice (Mail\DemoHandlingGuide).
 */
class DemoGuide
{
    /** What to show for each thing they ticked on the form (DemoRequestService::INTERESTS). */
    private const SHOW = [
        'rent' => 'Invoices: run one month of bills for a building, then open a tenant\'s bill link and the receipt.',
        'mpesa' => 'Payments: show a paybill payment matching itself to a unit\'s bill. Mention their own paybill or till, or Sharet Pay.',
        'water' => 'Water: enter readings on the bulk table and show the water line landing on the bill.',
        'power' => 'Electricity: show sub-meter readings feeding the bill, and the smart-meter connection.',
        'statements' => 'Reports and Budgets: the owner statement, and payouts at 08:00, 12:00 and 16:00.',
        'tenant_app' => 'Tenant app: bill, receipt, repair request and gate pass, on a phone.',
        'gate' => 'Gate: issue a visitor pass and check the visitor in from the guard\'s screen.',
        'shortstay' => 'Bookings: the booking calendar, channel sync and their own booking website.',
        'listings' => 'Open listings: mark a unit vacant and show it listed with photos and enquiries.',
        'api' => 'Developers: the API pages and a sandbox key.',
        'migration' => 'Import: download the Excel template and show the preview that appears before anything is saved.',
    ];

    /**
     * @return array{when:string, channel:string, before:array<int,string>, show:array<int,string>,
     *               objections:array<string,string>, onboard:array<int,array{title:string,text:string,url:?string}>, after:array<int,string>}
     */
    public static function for(CrmFollowUp $demo): array
    {
        $company = $demo->company;
        $book = $company ? Playbook::for($company) : ['approach' => [], 'objections' => []];
        $units = (int) $demo->detail('units');
        $properties = count($demo->detail('properties', []));
        $channel = (string) ($demo->detail('contact.channel') ?: 'phone');
        $when = $demo->detail('contact.date')
            ? Carbon::parse($demo->detail('contact.date'))->format('D j M').', '.$demo->detail('contact.window')
            : ($demo->due_at?->timezone('Africa/Nairobi')->format('D j M, H:i') ?? 'the time they chose');
        $id = $demo->company_id;
        $scope = $id ? ['company' => $id] : [];

        $show = collect($demo->detail('interests', []))->map(fn ($i) => self::SHOW[$i] ?? null)->filter()->values()->all();

        return [
            'when' => $when,
            'channel' => $channel === 'WhatsApp' ? 'WhatsApp' : lcfirst($channel),
            'before' => array_values(array_filter([
                'Read the attached request in the first email: '.($properties ?: 'their').' '.($properties === 1 ? 'property' : 'properties')
                    .($units ? ', '.number_format($units).' units' : '').($demo->detail('billing_now') ? '. They bill today with: '.$demo->detail('billing_now') : '').'.',
                'Contact them on '.$when.' by '.($channel === 'WhatsApp' ? 'WhatsApp' : lcfirst($channel)).'. That is the time they picked: do not be late.',
                $book['approach'][0] ?? null,
                $book['approach'][1] ?? null,
                'Price: '.Playbook::price().'.'.($units ? ' For '.number_format($units).' units, work out the monthly figure before the call.' : ''),
            ])),
            'show' => $show ?: ['They ticked nothing in particular: show bills going out, a payment matching itself, and the tenant\'s receipt.'],
            'objections' => array_slice($book['objections'] ?? [], 0, 3, true),
            'onboard' => [
                ['title' => 'Set up their account straight after the demo', 'url' => $id ? url('/crm/companies/'.$id) : null,
                    'text' => 'On their CRM page press Onboarding › Set up their account. It makes the login for the person who booked, creates their properties from the form, books your setup tasks and emails them the onboarding to-do PDF. They add units; you do the rest. Nothing is billed until you mark the deal Won.'],
                ['title' => 'Get their tenant list', 'url' => url('/onboarding-template.xlsx'),
                    'text' => 'One Excel sheet (or a photo of their book): units, tenants and what each tenant owes. Load it for them with Properties › Import.'],
                ['title' => 'Bring in their buildings', 'url' => route('properties.onboard'),
                    'text' => 'Properties › Import: upload the filled template, check the preview with them, then confirm.'],
                ['title' => 'Verify the business', 'url' => route('kyc.show', $scope),
                    'text' => 'ID, KRA PIN and the business certificate. Needed for their SMS sender name and the Verified badge.'],
                ['title' => 'Set up how they get paid', 'url' => route('integrations.index', $scope),
                    'text' => 'Rent collection and payouts (M-Pesa, bank or Sharet Pay), or their own M-Pesa paybill. Press Test once it is saved.'],
                ['title' => 'Switch on bills and their name', 'url' => route('brand.edit', $scope),
                    'text' => 'Rent automation on Integrations sends bills by themselves. White label puts their logo, colour, SMS name and email address on everything.'],
                ['title' => 'Open full access, then mark Won', 'url' => $id ? url('/crm/companies/'.$id) : null,
                    'text' => 'When tenants, payments and KYC are done, press Open full access on their CRM page. Mark the deal Won and set their price per unit on Admin › Companies: that starts their first bill.'],
            ],
            'after' => [
                'Straight after the call, write the outcome on the demo and move the stage: Demo booked, Proposal, Negotiation, Won.',
                'Always leave a next follow-up with a date. It shows on your calendar and in your Gmail calendar.',
                'If they go quiet, call again in two days, then once a week. Mark it Lost only with a reason.',
            ],
        ];
    }
}
