<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Sharet\GrowthHub;
use App\Support\SeoLandings;
use Illuminate\Http\Request;

/**
 * Sales CRM › Website analytics › Sharet growth hub: the public website's visits, conversions, channels and
 * SEO checks as seen by the Sharet Africa growth hub, plus a small ad center (what to push, ready ad copy,
 * UTM link builder). Sits next to our own first-party report (SiteAnalyticsController), same access.
 */
class GrowthController extends Controller
{
    public const RANGES = [7 => '7 days', 30 => '30 days', 90 => '90 days'];

    /** Ready-to-use ad copy for scharge's real offer (pricing and features as on the website). */
    public const AD_COPY = [
        'Google Search' => [
            'Headlines' => ['Property Management Software Kenya', 'Collect Rent on M-Pesa', 'From KES 40 per Unit a Month', 'Know Who Paid Before Lunch', 'Free Import From Excel'],
            'Descriptions' => [
                'Rent, service charge and water bills on M-Pesa, matched to each unit. Repairs and owner statements in one app.',
                'Landlords and agents in Kenya: SMS rent reminders with a pay link, arrears list and payouts. Priced per unit.',
            ],
        ],
        'Meta (Facebook / Instagram)' => [
            'Primary text' => ['Still checking M-Pesa statements to see who paid rent? scharge matches every payment to the right unit, sends SMS reminders with a pay link and gives owners a clean statement. From KES 40 per unit a month.'],
            'Headline' => ['Collect rent on M-Pesa, without the chasing'],
        ],
        'TikTok' => [
            'Hook (first 2 seconds)' => ['Landlord, how many hours did you spend chasing rent this month?'],
            'Caption' => ['Rent, water and service charge on M-Pesa, matched to every unit. Built in Kenya. #landlord #nairobi #mpesa #realestatekenya'],
        ],
        'WhatsApp status / broadcast' => [
            'Message' => ['Managing rentals? scharge collects rent, water and service charge on M-Pesa, matches each payment to the unit and sends reminders for you. From KES 40 per unit a month. Free setup from your Excel list: {link}'],
        ],
    ];

    public function index(Request $request, GrowthHub $hub)
    {
        $days = array_key_exists((int) $request->query('days'), self::RANGES) ? (int) $request->query('days') : 30;
        $data = $hub->summary($days);
        $site = $data['site'];

        $urls = collect(['Home' => route('home'), 'Open listings' => route('home.listings'), 'Airbnb hosts' => route('home.airbnb'),
            'Developers' => route('home.developers'), 'API & white label' => route('home.api')])
            ->merge(collect(SeoLandings::LINK_TEXT)->mapWithKeys(fn ($text, $slug) => [$text => route('landing.'.$slug)]));

        return view('admin.site-analytics.growth', [
            'days' => $days,
            'ranges' => self::RANGES,
            'data' => $data,
            'site' => $site,
            'advice' => GrowthHub::advice($site),
            'adCopy' => self::AD_COPY,
            'urls' => $urls,
            'expectedId' => GrowthHub::EXPECTED_SHARET_ID,
        ]);
    }
}
