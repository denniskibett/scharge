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

    /** The first closing step and a suggested answer for this intent. @return array{title: string, text: string, answer: string} */
    public static function next(string $intent, string $first): array
    {
        $hi = 'Hi '.($first !== '' ? $first : 'there').",\n\n";

        return match ($intent) {
            'seller' => ['title' => 'Pivot to what fits a seller', 'text' => 'They sell plots or houses. Thank them for setting us straight and offer buyer instalment collections and listings instead of rent collection.',
                'answer' => $hi."Thank you for setting us straight, and sorry for the mix-up.\n\nSince you sell plots and houses, the part of scharge that fits is buyer instalments: each buyer has a payment plan, M-Pesa payments match themselves to the right buyer, and buyers get an SMS statement and a reminder before each instalment is due. What is still unsold can be listed publicly from the same place.\n\nIf you sell on instalments, would a short call this week be useful? And if any of your buyers rent out what they buy, we would be glad to help them too."],
            'partner' => ['title' => 'Explore working together', 'text' => 'They offered to help (advertising, referrals). Ask who they reach and book a short call about a partnership; agree terms with the admin before promising any fee.',
                'answer' => $hi."Thank you, that is kind of you. We would be glad to explore working together: you reach landlords, managers and buyers who could use scharge, and we can help the people you work with collect rent and instalments.\n\nCould we have a short call this week to see what would work for both of us?"],
            'not_interested' => ['title' => 'Close politely and mark it Lost', 'text' => 'Thank them, leave the door open, and set the stage to Lost with their reason.',
                'answer' => $hi."Thank you for letting us know. We will leave it there; if anything changes, just reply to this email and we will pick it up."],
            'has_system' => ['title' => 'Find the gap in what they use', 'text' => 'Ask what their system does not do well (M-Pesa matching, statements, water). Offer to run scharge beside it for a month; import is done for them.',
                'answer' => $hi."Thanks for telling us. Many teams we work with had a system too; what moved them was M-Pesa payments matching themselves and landlord statements going out on their own.\n\nWhat does your current system leave you doing by hand? If it helps, we can import your data and run scharge beside it for a month so you can compare."],
            'price' => ['title' => 'Ask for their budget', 'text' => 'Do not drop the price. Ask for their budget and units in use, then rebuild with the vacant-unit discount or one building to start.',
                'answer' => $hi."Thank you for being open about the cost. Could you share the monthly budget you have in mind and how many units are occupied today? Vacant units get cheaper the longer you are with us, and starting with one building is fine, so we can put together a plan that fits."],
            'later' => ['title' => 'Book the follow-up for when they said', 'text' => 'Agree the date they gave and schedule a call for it, so it lands on your calendar.',
                'answer' => $hi."No problem at all. I will get back to you then; if a specific day suits you better, just let me know."],
            default => ['title' => 'Book the demo now', 'text' => 'They are interested. Call today and agree a time, or send their demo link so they pick one.',
                'answer' => $hi."Great to hear from you. Could we set up a 20-minute call this week? I will show you how it would work on one of your properties."],
        };
    }
}
