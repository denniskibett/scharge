<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\CRM\Models\CrmFollowUp;

/**
 * What a prospect's reply is telling us, read with plain rules (no AI): they sell rather than let, they are not
 * interested, they already have a system, price, "later", or they are interested. Each intent comes with the next
 * step and a suggested answer for Actions › Close the deal and the reply box. A seller is moved to the Land & home
 * sales playbook. Add a phrase to RULES when a reply is read wrong; rules are tried in order, first match wins.
 */
class ReplyIntent
{
    public const LABELS = [
        'seller' => 'Sells plots or houses, not rentals',
        'partner' => 'Offers to partner or advertise with us',
        'not_interested' => 'Not interested',
        'has_system' => 'Already has a system',
        'price' => 'Worried about price',
        'later' => 'Asked us to come back later',
        'interested' => 'Interested',
    ];

    /** Rules per intent: any one phrase matches (case-insensitive, on the new part of the reply). */
    private const RULES = [
        'seller' => [
            '/\b(sell(ing|s)?|sale of|deal(s|ing)? in)\b[^.\n]{0,40}\b(plots?|land|houses?|homes?|properties|apartments?)\b/i',
            '/\bnot\b[^.\n]{0,25}\b(rental|renting|letting|lettings|rent(ed)? out)\b/i',
            '/\b(we|i) (don\'?t|do not) (manage|rent|let)\b/i',
            '/\b(land|plots?) (sales?|selling|company|dealers?)\b/i',
        ],
        'partner' => [
            '/\b(we can|we could|happy to|we\'d like to|we would like to) (assist|help|partner|collaborate|work with you)\b/i',
            '/\b(partnership|collaborat(e|ion)|referral|advertis(e|ing|ement)|marketing partner)\b/i',
        ],
        'not_interested' => [
            '/\b(do not|don\'t|does not|doesn\'t) (have|need|require)\b[^.\n]{0,40}\b(requirement|need|services?|system)\b/i',
            '/\b(no (requirement|need) for|not in need of|not require)\b/i',
            '/\b(not interested|no,? thank(s| you)|not for us|not (at this time|right now) interested|we (are|\'re) not looking)\b/i',
            '/\b(please )?(remove|take) (me|us) (from|off)\b/i',
        ],
        'has_system' => [
            '/\b(already (have|use|using)|we use|we are using|currently using)\b[^.\n]{0,40}\b(system|software|app|platform|erp|solution)\b/i',
        ],
        'price' => [
            '/\b(budget|expensive|costly|too much|price|pricing|cost|charges?|per unit)\b/i',
        ],
        'later' => [
            '/\b(next (week|month|quarter|year)|later in the year|not now|another time|in (january|february|march|april|may|june|july|august|september|october|november|december)|after the holidays)\b/i',
        ],
        'interested' => [
            '/\b(interested|call me|give me a call|send (me|us) (more )?(info|information|details|a proposal|the price)|let\'?s (talk|meet|discuss)|book (a|the) demo|share (more|details))\b/i',
        ],
    ];

    public static function read(string $text): ?string
    {
        $text = mb_substr(preg_replace('/\s+/', ' ', $text), 0, 3000);
        foreach (self::RULES as $intent => $patterns) {
            foreach ($patterns as $p) {
                if (preg_match($p, $text)) {
                    return $intent;
                }
            }
        }

        return null;
    }

    /** Record what the reply says on the company, and act on it (a seller moves to the Land & home sales playbook). */
    public static function apply(Company $company, CrmFollowUp $reply, ?int $authorId = null): ?string
    {
        $intent = self::read((string) $reply->notes);
        if (! $intent) {
            return null;
        }
        $company = $company->fresh();
        $profile = $company->crm_profile ?? [];
        $profile['reply_intent'] = ['intent' => $intent, 'follow_up_id' => $reply->id, 'at' => now()->toDateTimeString()];
        $note = 'Their reply reads as: '.strtolower(self::LABELS[$intent]).'.';
        if ($intent === 'seller' && ($profile['segment'] ?? null) !== 'Land & Home Sales') {
            $profile['segment_before'] = $profile['segment'] ?? null;
            $profile['segment'] = 'Land & Home Sales';
            $profile['segment_from'] = 'reply '.now()->toDateString();
            $note .= ' Moved to the Land & home sales playbook (buyer instalments and listings, not rent).';
        }
        $company->forceFill(['crm_profile' => $profile])->saveQuietly();
        app(\App\Modules\CRM\Services\ContactService::class)->log($company, 'note', 'Reply read: '.self::LABELS[$intent], $note, ['reply_intent' => $intent, 'reply_id' => $reply->id], null, $authorId);

        return $intent;
    }

    /**
     * What the client told us, turned into what they save: their units, the hours a month their team spends
     * confirming and recording payments by hand (crm.minutes_per_payment each), and their monthly fee.
     *
     * @return array{units: int, hours: int, fee: ?int}
     */
    public static function facts(Company $company): array
    {
        $demo = $company->followUps()->where('type', 'demo')->whereIn('status', ['open', 'scheduled', 'done'])->latest('id')->first();
        $units = (int) ($demo?->detail('units') ?: $company->profile('estimated_units') ?: 0);
        $quote = $units ? CloseSteps::quote($company, $demo) : null;

        return ['units' => $units, 'hours' => (int) round($units * max(1, (int) config('crm.minutes_per_payment', 3)) / 60), 'fee' => $quote ? (int) $quote['start'] : null];
    }

    /**
     * The first closing step and a suggested answer for this intent. Answers sell the saving, not the features:
     * the hours their team gets back from their own unit count, the first payment covering three months, and vacant
     * units getting cheaper over time.
     *
     * @param  array{units?: int, hours?: int, fee?: ?int}  $facts
     * @return array{title: string, text: string, answer: string}
     */
    public static function next(string $intent, string $first, array $facts = []): array
    {
        $hi = 'Hi '.($first !== '' ? $first : 'there').",\n\n";
        $units = (int) ($facts['units'] ?? 0);
        $hours = (int) ($facts['hours'] ?? 0);
        $fee = $facts['fee'] ?? null;
        // The saving in their own numbers, when they gave us units; otherwise the same point without a figure.
        $time = $units && $hours
            ? 'With '.number_format($units).' units, confirming each M-Pesa message and updating the records by hand takes a team about '.$hours.' hours a month. That time comes back to you: payments match themselves to the right tenant and receipts go out on their own.'
            : 'The hours your team spends confirming M-Pesa messages and updating records by hand come back to you: payments match themselves to the right tenant and receipts go out on their own.';
        $start = $fee ? 'Your first payment of KES '.number_format($fee).' covers three months, so the next two are on us' : 'Your first payment covers three months, so the next two are on us';

        return match ($intent) {
            'seller' => ['title' => 'Pivot to what fits a seller', 'text' => 'They sell plots or houses. Thank them for setting us straight, then sell the time and follow-up calls they save on buyer instalments, not rent collection.',
                'answer' => $hi."Thank you for setting us straight, and sorry for the mix-up.\n\nWhere sellers like you save is on instalments: every buyer payment you confirm from an M-Pesa message, and every call from a buyer asking what is left, is time your team gets back. Each payment lands on the right buyer by itself, and buyers get their balance by SMS before each instalment is due, so the calls stop.\n\nIf you sell on instalments, would a short call this week be useful? I can show you what it saves on one of your projects."],
            'partner' => ['title' => 'Explore working together', 'text' => 'They offered to help (advertising, referrals). Ask who they reach and book a short call about a partnership; agree terms with the admin before promising any fee.',
                'answer' => $hi."Thank you, that is kind of you. We would be glad to explore working together: the landlords, managers and sellers you reach lose hours every month confirming payments by hand, and that is the time we give back to them.\n\nCould we have a short call this week to see what would work for both of us?"],
            'not_interested' => ['title' => 'Close politely and mark it Lost', 'text' => 'Thank them, leave one line on the saving, and set the stage to Lost with their reason.',
                'answer' => $hi."Thank you for letting us know. We will leave it there.\n\n".$time."\n\nIf that ever becomes worth a look, just reply to this email and we will pick it up."],
            'has_system' => ['title' => 'Find what their system still leaves by hand', 'text' => 'Ask what is still done by hand (confirming M-Pesa, statements, water). Sell the hours back, and offer to run beside their system for a month; import is done for them.',
                'answer' => $hi."Thanks for telling us. Most teams we speak to have a system too; what they still do by hand is the expensive part.\n\n".$time."\n\nWhat does yours still leave you doing by hand? We can import your data and run beside it for a month so you can see the difference in your own numbers."],
            'price' => ['title' => 'Sell the saving, then ask for their budget', 'text' => 'Do not drop the price. Show the hours their team gets back and that the first payment covers three months, then ask for their budget and units in use.',
                'answer' => $hi."Thank you for being open about the cost.\n\n".$time."\n\n".$start.", and vacant units get cheaper the longer you are with us.\n\nCould you share the monthly budget you have in mind and how many units are occupied today? We will put together a plan that fits that figure."],
            'later' => ['title' => 'Book the follow-up for when they said', 'text' => 'Agree the date they gave and schedule a call for it, so it lands on your calendar.',
                'answer' => $hi."No problem at all. I will get back to you then.\n\nWhen you are ready, ".lcfirst($start).", so there is nothing to lose in starting early. If a specific day suits you better, just let me know."],
            default => ['title' => 'Book the demo now', 'text' => 'They are interested. Call today and agree a time, or send their demo link so they pick one.',
                'answer' => $hi."Great to hear from you.\n\n".$time."\n\nCould we set up a 20-minute call this week? I will show you where that time comes back on one of your properties."],
        };
    }
}
