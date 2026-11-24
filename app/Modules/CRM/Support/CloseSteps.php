<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;
use App\Support\UnitPricing;
use Illuminate\Support\Carbon;

/**
 * The Playbook's "Close the deal" tab: once a client has answered (replied, booked a demo, or moved past Contacted),
 * the account manager needs the steps to close, not research and a cold email. Steps follow the stage; after Won they
 * become the onboarding steps. Sales rules are the team's (Client FAQ): units first, how they send and receive
 * information, free tenant and staff apps, units in use in full with the vacant-unit discount, white label last.
 */
class CloseSteps
{
    public const ENGAGED_STAGES = ['qualified', 'demo_booked', 'proposal', 'negotiation', 'won'];

    /** Has the client answered us in any way? Then the Playbook opens on closing, not prospecting. */
    public static function engaged(Company $company): bool
    {
        return in_array($company->crm_stage, self::ENGAGED_STAGES, true)
            || (bool) self::lastReply($company)
            || $company->followUps()->where('type', 'demo')->whereIn('status', ['open', 'scheduled', 'done'])->exists();
    }

    /** Their latest real reply (not an automatic one). */
    public static function lastReply(Company $company): ?CrmFollowUp
    {
        return $company->followUps()->where('type', 'email')->where('details->direction', 'in')->whereNull('details->auto')->latest('id')->first();
    }

    /**
     * @return array{stage: string, said: ?array{at: string, from: string, text: string, answered: bool, pending: bool}, demo: ?CrmFollowUp,
     *               quote: ?array{units: int, in_use: ?int, vacant: ?int, price: float, start: float, six: float, year: float},
     *               steps: array<int, array{title: string, text: string, url?: ?string, done?: bool}>, onboarding: bool}
     */
    public static function for(Company $company): array
    {
        $stage = (string) $company->crm_stage;
        $reply = self::lastReply($company);
        $answeredAfter = $reply && $company->followUps()->where('type', 'email')->where('id', '>', $reply->id)
            ->where(fn ($q) => $q->where('details->direction', 'out')->orWhereNotNull('details->answer_to'))->exists();
        $said = $reply ? [
            'at' => ($reply->done_at ?? $reply->created_at)->timezone('Africa/Nairobi')->format('D j M, H:i'),
            'from' => (string) ($reply->detail('from_name') ?: $reply->detail('from')),
            'text' => \Illuminate\Support\Str::limit(trim((string) $reply->notes), 600),
            'answered' => $answeredAfter,
            'pending' => (bool) $company->profile('reply_pending'),
        ] : null;
        $demo = $company->followUps()->where('type', 'demo')->whereIn('status', ['open', 'scheduled'])->orderBy('id')->first();
        $quote = self::quote($company, $demo);
        $page = route('crm.companies.show', $company);
        $price = ! $quote ? null : ($quote['start'] === $quote['year'] ? 'KES '.number_format($quote['start']).' a month' : 'KES '.number_format($quote['start']).' a month at the start, KES '.number_format($quote['six']).' after 6 months and KES '.number_format($quote['year']).' after a year');

        $steps = match ($stage) {
            'won' => self::onboarding($company),
            'negotiation' => [
                ['title' => 'Find what is still in the way', 'text' => 'Price, timing, the landlord, or the decision-maker. Ask plainly what would make it a yes this week.'],
                ['title' => 'Rebuild the offer, same features', 'text' => 'Count units in use in full; vacant units get 25% off after 6 months and 50% off after a year. Starting with one building is fine.'.($price ? ' Their figure: '.$price.'.' : '')],
                ['title' => 'Offer white label only now', 'text' => 'If they want their own brand: their logo, SMS name, email address and paybill on everything. It is the last option, never the opener.'],
                ['title' => 'Get the yes from the person who pays', 'text' => 'Confirm the start date and who signs off the first bill: paying one month opens three.'],
                ['title' => 'Mark it Won', 'text' => 'Set the stage to Won on their page; the onboarding steps appear here.', 'url' => $page],
            ],
            'proposal' => [
                ['title' => 'Send the figure in writing', 'text' => 'Units in use × price, the vacant-unit discount schedule, and the first bill: pay one month, get three.'.($price ? ' Their figure: '.$price.'.' : '')],
                ['title' => 'Say what is free', 'text' => 'The tenant app and the staff app cost nothing and sit outside the fee.'],
                ['title' => 'Follow up in two days', 'text' => 'Call, do not wait for an email. Ask what is left to decide and who decides.'],
                ['title' => 'Move to Negotiation or Won', 'text' => 'As soon as they push back or say yes.', 'url' => $page],
            ],
            'demo_booked' => [
                ['title' => 'Confirm the time the day before', 'text' => self::askedFor($demo)],
                ['title' => 'Prepare from their request', 'text' => 'Open the demo request PDF: what they ticked and their properties. Work out their monthly figure before the call.'.($price ? ' '.$price.'.' : '')],
                ['title' => 'On the call, ask before you show', 'text' => 'Total units → how they receive and send information → who decides → their budget. Then show what they ticked, lead with the free tenant and staff apps, then the price.'],
                ['title' => 'Leave with a next step', 'text' => 'Write the outcome on the demo, move the stage to Proposal, and book the follow-up with a date.', 'url' => $page],
            ],
            default => [
                ['title' => $said && ! $said['answered'] ? 'Answer their reply today' : 'Keep the thread warm', 'text' => $said && ! $said['answered'] ? 'Answer in the same thread from their page, so it is logged and their next reply comes back to you.' : 'They answered and you replied. Call to move it forward rather than waiting for email.', 'url' => $page.'#reply', 'done' => $said['answered'] ?? false],
                ['title' => 'Qualify them', 'text' => 'Total units, how they receive and send information, who decides, and their budget. Write each on their page.'],
                ['title' => 'Book the demo', 'text' => 'Agree a time and channel (phone, WhatsApp or video). Send them their demo link if they prefer to pick a time themselves.'],
                ['title' => 'Move the stage', 'text' => 'Qualified once you know units and the decision-maker; Demo booked once the time is set.', 'url' => $page],
            ],
        };

        return ['stage' => $stage, 'said' => $said, 'demo' => $demo, 'quote' => $quote, 'steps' => $steps, 'onboarding' => $stage === 'won'];
    }

    /** "They asked for a phone call 12:00–14:00 on Thu 26 Nov. Call or WhatsApp to confirm." (old requests carry no channel or time) */
    private static function askedFor(?CrmFollowUp $demo): string
    {
        $how = trim(strtolower((string) $demo?->detail('contact.channel')).' '.(string) $demo?->detail('contact.window'));
        $day = $demo?->due_at?->timezone('Africa/Nairobi')->format('D j M');
        $what = match (true) {
            $how !== '' && $day !== null => 'They asked for a '.$how.' on '.$day.'.',
            $how !== '' => 'They asked for a '.$how.'.',
            $day !== null => 'The demo is set for '.$day.'.',
            default => 'No time is set yet.',
        };

        return $what.' Call or WhatsApp to confirm.';
    }

    /** Their monthly figure at today's price (KES 40 unless staff set one): start, after 6 months, after a year. */
    public static function quote(Company $company, ?CrmFollowUp $demo = null): ?array
    {
        $units = (int) ($demo?->detail('units') ?: $company->profile('estimated_units') ?: 0);
        if (! $units) {
            return null;
        }
        $price = (float) ($company->price_per_unit ?? 40) ?: 40.0;
        $counts = UnitPricing::unitCounts($company->id);
        $known = ($counts['in_use'] + $counts['vacant']) > 0;
        $inUse = $known ? $counts['in_use'] : $units;
        $vacant = $known ? $counts['vacant'] : 0;
        $at = fn (float $pct) => round($inUse * $price + $vacant * $price * (100 - $pct) / 100);
        $since = Carbon::now();

        return ['units' => $units, 'in_use' => $known ? $inUse : null, 'vacant' => $known ? $vacant : null, 'price' => $price,
            'start' => $at(UnitPricing::vacantDiscount($since, $since)),
            'six' => $at(UnitPricing::vacantDiscount($since, $since->copy()->addMonths(6))),
            'year' => $at(UnitPricing::vacantDiscount($since, $since->copy()->addYear()))];
    }

    /** After Won: the onboarding steps (as in the demo guide). */
    private static function onboarding(Company $company): array
    {
        $scope = ['company' => $company->id];
        $url = fn (string $name, array $p = []) => \Illuminate\Support\Facades\Route::has($name) ? route($name, $p) : null;

        return [
            ['title' => 'Send them the template', 'text' => 'One Excel sheet: buildings, units, tenants and what each tenant owes. Ask for it back before you set anything up.', 'url' => url('/onboarding-template.xlsx')],
            ['title' => 'Bring in their buildings', 'text' => 'Properties › Import: upload the filled template, check the preview with them, then confirm.', 'url' => $url('properties.onboard')],
            ['title' => 'Verify the business', 'text' => 'ID, KRA PIN and the business certificate. Needed for their SMS sender name and the Verified badge.', 'url' => $url('kyc.show', $scope)],
            ['title' => 'Set up how they get paid', 'text' => 'Rent collection and payouts (M-Pesa, bank or Sharet Pay), or their own M-Pesa paybill. Press Test once it is saved.', 'url' => $url('integrations.index', $scope)],
            ['title' => 'Switch on bills and their name', 'text' => 'Rent automation sends bills by themselves. White label puts their logo, SMS name and email on everything, if they took it.', 'url' => $url('brand.edit', $scope)],
        ];
    }
}
