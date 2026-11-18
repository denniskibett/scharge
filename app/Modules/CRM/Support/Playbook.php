<?php

namespace App\Modules\CRM\Support;

use App\Models\Company;
use App\Modules\Users\Models\User;
use Illuminate\Support\Str;

/**
 * Sales playbook per CRM segment: how to approach the prospect, what to sell them, the objections to expect,
 * and a starter cold email. Shown in the company page's "Playbook" modal. Placeholders in the email:
 * {company} {city} {manager} {manager_email} {manager_phone}.
 */
class Playbook
{
    private const PRICE = 'from KES 40 per unit per month, billed only for the units you manage';

    public static function segmentOf(Company $company): string
    {
        $segment = (string) $company->profile('segment');
        if ($segment !== '' && isset(self::BOOKS[self::key($segment)])) {
            return self::key($segment);
        }
        $hint = Str::lower($segment.' '.$company->profile('business_type').' '.$company->kind.' '.$company->name);

        return match (true) {
            str_contains($hint, 'affordable') || str_contains($hint, 'housing co') || str_contains($hint, 'sacco') => 'affordable',
            str_contains($hint, 'developer') => 'developer',
            str_contains($hint, 'airbnb') || str_contains($hint, 'short') || str_contains($hint, 'bnb') || str_contains($hint, 'serviced') => 'shortstay',
            str_contains($hint, 'hotel') || str_contains($hint, 'lodge') || str_contains($hint, 'resort') => 'hotel',
            str_contains($hint, 'water company') || str_contains($hint, 'sewerage') => 'water_utility',
            str_contains($hint, 'water') => 'water_private',
            str_contains($hint, 'electric') || str_contains($hint, 'kplc') || str_contains($hint, 'solar') || str_contains($hint, 'power') => 'power',
            str_contains($hint, 'factory') || str_contains($hint, 'industrial') || str_contains($hint, 'warehouse') => 'industrial',
            str_contains($hint, 'mall') || str_contains($hint, 'commercial') || str_contains($hint, 'office') => 'commercial',
            str_contains($hint, 'named building') || str_contains($hint, 'apartment block') => 'building',
            default => 'manager',
        };
    }

    private static function key(string $segment): string
    {
        return [
            'Property Manager / Agency' => 'manager', 'Short-stay / Airbnb Manager' => 'shortstay', 'Short-stay / Airbnb' => 'shortstay',
            'Hotel / Serviced Accommodation' => 'hotel', 'Developer' => 'developer', 'Affordable Housing' => 'affordable',
            'Residential Property (named building/estate)' => 'building', 'Commercial Property / Mall' => 'commercial',
            'Industrial / Warehouse Park' => 'industrial', 'Industrial / Factory (utility & metering)' => 'industrial',
            'Water Company' => 'water_utility', 'Private Water Supplier' => 'water_private', 'Electricity / KPLC & Metering' => 'power',
            'Office / Commercial' => 'commercial', 'Retail / Mall' => 'commercial', 'Industrial / Logistics' => 'industrial',
            'High-end Residential' => 'manager', 'Serviced Apartments' => 'shortstay', 'Gated Community' => 'building', 'Upmarket Agency' => 'manager',
        ][$segment] ?? 'manager';
    }

    /** @return array{label:string, approach:array<string>, offers:array<string>, objections:array<string,string>, subject:string, body:string} */
    public static function for(Company $company): array
    {
        return self::BOOKS[self::segmentOf($company)];
    }

    /** The email to send: the saved draft for this company, else the segment template, with placeholders filled. */
    public static function draft(Company $company, ?User $manager): array
    {
        $book = self::for($company);
        $subject = $company->profile('email_subject') ?: $book['subject'];
        $body = $company->profile('email_body') ?: $book['body'];

        return ['subject' => self::fill($subject, $company, $manager), 'body' => self::fill($body, $company, $manager)];
    }

    public static function fill(string $text, Company $company, ?User $manager): string
    {
        $city = $company->profile('city') ?: $company->profile('area') ?: 'Kenya';

        return strtr($text, [
            '{company}' => preg_replace('/\s*\((?:[^()]*)\)\s*$/', '', $company->name),
            '{city}' => $city,
            '{manager}' => $manager?->name ?? 'The scharge team',
            '{manager_email}' => $manager?->email ?? config('mail.from.address'),
            '{manager_phone}' => $manager?->phone ?: '',
        ]);
    }

    public const OPT_OUT = "If you'd rather not hear from us, reply \"stop\" and we won't email you again.";

    private const SIGN = "\n\nKind regards,\n{manager}\nAccount Manager, scharge\n{manager_email}  {manager_phone}\nhttps://scharge.sharet.africa";

    private const BOOKS = [
        'manager' => [
            'label' => 'Property manager / agency',
            'approach' => [
                'Open with their managed-letting book: how many units, how rent is collected today (paybill, bank, cash).',
                'Ask how long month-end takes: invoices, reconciling M-Pesa, landlord statements.',
                'Offer a 20-minute demo on one of their buildings; import their tenant list for them.',
                'Close on a pilot: one building for 30 days, then the whole book per unit.',
            ],
            'offers' => ['Automatic rent + service charge invoices by SMS/email', 'M-Pesa & bank collections matched to tenants (Sharet Pay)', 'Landlord statements and payouts', 'Water/electricity meter billing', 'Tenant app: receipts, notices, maintenance requests', 'Public listings for vacant units', 'Pricing '.self::PRICE],
            'objections' => ['We use Excel / another system' => 'Import is done for you; run both side by side for a month.', 'Too expensive' => 'Per unit, so a 20-unit block costs less than one late-rent follow-up trip.', 'Landlords won\'t agree' => 'Landlords get live statements; that usually sells it.'],
            'subject' => 'Rent collection for {company} without the month-end chase',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge, a Kenyan property-management platform used by managers in {city}. We send rent and service-charge invoices automatically, match M-Pesa and bank payments to each tenant, bill water and electricity from meter readings, and give your landlords live statements.\n\nWould you have 20 minutes this week for a short demo on one of your buildings? We can import your tenant list for you, and pricing starts at KES 40 per unit per month.".self::SIGN,
        ],
        'shortstay' => [
            'label' => 'Short-stay / Airbnb',
            'approach' => [
                'Ask how many units they run and on which platforms (Airbnb, Booking.com, direct).',
                'Lead with direct bookings: their own booking page with no platform commission.',
                'Show cleaning fees, utilities per unit and owner payouts handled in one place.',
                'Offer to list two units on a direct-booking page for free during the trial.',
            ],
            'offers' => ['Direct-booking website + listings (no commission)', 'Calendar, nightly rates, min/max stay, cleaning fees', 'Guest payments by M-Pesa / card', 'Per-unit electricity + water tracking', 'Owner statements and payouts for co-hosted units', 'Visitor / gate passes for guests', 'Pricing '.self::PRICE],
            'objections' => ['Airbnb already works' => 'Keep Airbnb; repeat and local guests book direct and you keep the 15%.', 'Too small' => 'Per unit, so one apartment is cheap to start with.'],
            'subject' => 'Direct bookings for {company}, with no platform commission',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. We help short-stay hosts in {city} take bookings on their own website with no commission, collect payment by M-Pesa or card, and keep cleaning fees, utilities and owner payouts in one place.\n\nCould I show you how it would look for your units in a 15-minute call this week?".self::SIGN,
        ],
        'hotel' => [
            'label' => 'Hotel / serviced accommodation',
            'approach' => [
                'Target the serviced-apartment and long-stay side, not the front desk system they already have.',
                'Ask how they bill long-stay guests and staff housing for power and water.',
                'Pitch sub-metering + monthly billing for long-stay units and staff quarters.',
            ],
            'offers' => ['Long-stay / serviced unit billing', 'Electricity + water sub-metering per unit', 'Direct-booking page for apartments', 'Staff-housing rent and utilities', 'Maintenance tickets', 'Pricing '.self::PRICE],
            'objections' => ['We have a PMS' => 'scharge covers long-stay billing and utilities, which hotel PMSs handle badly.'],
            'subject' => 'Long-stay and utility billing for {company}',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. For hotels and serviced apartments in {city} we handle the part a front-desk system usually doesn't: monthly billing for long-stay guests and staff housing, electricity and water sub-metering per unit, and payments by M-Pesa.\n\nWould a short call this week be useful?".self::SIGN,
        ],
        'developer' => [
            'label' => 'Developer',
            'approach' => [
                'Find the project nearest hand-over; that is the buying moment.',
                'Pitch scharge as the estate system from day one: service charge, utilities, resident app.',
                'Offer to set up the estate before hand-over so buyers/tenants onboard on the app.',
                'Mention sales listings for unsold units.',
            ],
            'offers' => ['Estate set-up before hand-over', 'Service charge + sinking fund billing', 'Water/electricity metering (manual + smart meters)', 'Resident app, notices, gate/visitor management', 'Off-plan / tenant-purchase instalment tracking', 'Listings for unsold or rental units', 'Pricing '.self::PRICE],
            'objections' => ['The management company will choose' => 'Set the standard now; their manager can run it on scharge.', 'Not yet' => 'Agree a date tied to hand-over and follow up then.'],
            'subject' => 'Running {company}\'s estates after hand-over',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. We help developers in {city} hand over estates that run smoothly from day one: service-charge billing, water and electricity metering, a resident app with notices and gate passes, and M-Pesa collections, priced per unit.\n\nIs there a project coming up for hand-over that we could set up for you? I'd be glad to walk you through it in 20 minutes.".self::SIGN,
        ],
        'affordable' => [
            'label' => 'Affordable housing',
            'approach' => [
                'Lead with collections at scale: thousands of small monthly payments matched automatically.',
                'Ask how tenant-purchase / rent-to-own balances are tracked today.',
                'Pitch SMS statements and arrears reminders to every resident.',
            ],
            'offers' => ['Tenant-purchase and rent collections at scale', 'Automatic M-Pesa matching per unit', 'Arrears reminders + SMS statements', 'Water/electricity metering', 'Service charge billing', 'Pricing '.self::PRICE],
            'objections' => ['Government system' => 'scharge can run the estate level and export to the programme\'s reports.'],
            'subject' => 'Collections and estate billing for {company}',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. For affordable-housing schemes we handle thousands of small monthly payments: rent or tenant-purchase instalments matched automatically from M-Pesa, SMS statements and arrears reminders, plus water, power and service-charge billing per unit.\n\nCould we set up a short call to see if it fits your projects in {city}?".self::SIGN,
        ],
        'building' => [
            'label' => 'Apartment block / estate',
            'approach' => [
                'Find who runs the building: owner, managing agent or residents\' association (ask the caretaker or the sign at the gate).',
                'Pitch simple monthly rent + water billing by SMS, paid by M-Pesa.',
                'Offer the caretaker app for meter readings and notices.',
            ],
            'offers' => ['Rent + water bills by SMS', 'M-Pesa payments matched to each unit', 'Caretaker app: meter readings, notices, visitors', 'Owner statements', 'Pricing '.self::PRICE],
            'objections' => ['We do it by hand' => 'A 20-unit block costs about KES 800 a month and saves the month-end chase.'],
            'subject' => 'Rent and water billing for {company}',
            'body' => "Hello,\n\nI'm {manager} from scharge. We help apartment owners and managing agents in {city} send rent and water bills by SMS, get paid by M-Pesa with every payment matched to its unit, and see who owes what at a glance.\n\nIf you manage {company}, could I show you how it works in 15 minutes?".self::SIGN,
        ],
        'commercial' => [
            'label' => 'Commercial building / mall',
            'approach' => [
                'Ask how tenant power and water recharges are billed today.',
                'Pitch sub-metering + automatic recharge invoices with service charge.',
                'Offer facilities tickets for tenants.',
            ],
            'offers' => ['Tenant rent + service charge invoicing', 'Electricity/water sub-metering and recharges', 'Facilities / maintenance tickets', 'Arrears tracking', 'Pricing '.self::PRICE],
            'objections' => ['Accounts team handles it' => 'scharge feeds their accounts; it removes the manual recharge spreadsheet.'],
            'subject' => 'Tenant billing and utility recharges for {company}',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. For commercial buildings and malls in {city} we automate tenant rent and service-charge invoices, bill electricity and water recharges from sub-meters, and track facilities requests in one place.\n\nWould a 20-minute demo this week be useful?".self::SIGN,
        ],
        'industrial' => [
            'label' => 'Industrial / factory',
            'approach' => [
                'Find the facilities or plant manager.',
                'Pitch sub-metering and cost allocation for power and water across units, tenants or staff housing.',
                'For parks: tenant billing and recharges.',
            ],
            'offers' => ['Power + water sub-metering and cost allocation', 'Staff-housing rent and utilities', 'Warehouse tenant billing and recharges', 'Meter readings API for smart meters', 'Pricing '.self::PRICE],
            'objections' => ['Not a property company' => 'Any site with staff housing or multiple meters has the same billing problem.'],
            'subject' => 'Utility metering and billing for {company}',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. We help sites in {city} track and bill electricity and water per unit, whether that's warehouse tenants, staff housing or departments, from manual or smart-meter readings, with payments by M-Pesa.\n\nWould a short call with your facilities team be useful?".self::SIGN,
        ],
        'water_utility' => [
            'label' => 'Water company',
            'approach' => [
                'Pitch as a partner, not a replacement: bulk supply to estates and meter-reading capture.',
                'Ask about estates and apartment blocks on bulk meters, and how sub-billing is done.',
                'Offer an estates pilot: scharge bills residents behind a bulk meter, the utility gets paid on time.',
            ],
            'offers' => ['Sub-billing behind bulk meters in estates', 'Meter-reading capture app + API', 'M-Pesa collections and arrears SMS', 'Data exports for the utility', 'Partnership / referral'],
            'objections' => ['We have a billing system' => 'This is for estates behind bulk meters, which your system bills as one customer.'],
            'subject' => 'Bulk-metered estates in {city}: a partnership idea for {company}',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. We run water billing inside estates and apartment blocks: readings for each unit, bills by SMS and payments by M-Pesa, behind the bulk meters your company supplies.\n\nThat usually means fewer disputes and faster bulk payments to the utility. Could we arrange a short meeting with your commercial team to explore a pilot in {city}?".self::SIGN,
        ],
        'water_private' => [
            'label' => 'Private water supplier',
            'approach' => [
                'Ask how customers are billed: per delivery, per meter, monthly.',
                'Pitch customer accounts, meter or delivery billing and M-Pesa collections.',
            ],
            'offers' => ['Customer accounts + delivery/meter billing', 'M-Pesa collections and reminders', 'Estate sub-metering partnerships', 'Pricing '.self::PRICE],
            'objections' => ['Small business' => 'Start with your estate customers only.'],
            'subject' => 'Billing your water customers by M-Pesa, {company}',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. We help water suppliers in {city} bill customers per delivery or per meter reading, send bills by SMS and get paid by M-Pesa, with every payment matched to its customer.\n\nWould a 15-minute call be useful?".self::SIGN,
        ],
        'power' => [
            'label' => 'Electricity / KPLC & metering',
            'approach' => [
                'Position as a partner channel: they install meters or solar, scharge bills tenants and residents.',
                'Ask which estates or buildings they have installed sub-meters in.',
                'Offer a referral / reseller arrangement and the meter-readings API.',
            ],
            'offers' => ['Tenant electricity billing from sub-meters (prepaid or postpaid)', 'Meter-readings API for smart meters', 'Referral / reseller partnership', 'Joint estate projects'],
            'objections' => ['We sell hardware' => 'Your clients still need to bill tenants; scharge makes the meters pay off.'],
            'subject' => 'Billing tenants from your meters: a partnership idea for {company}',
            'body' => "Hello {company} team,\n\nI'm {manager} from scharge. We bill tenants and residents for electricity and water from sub-meter readings, entered manually or pulled from smart meters through our API, and collect by M-Pesa.\n\nIf you install meters or solar in estates and buildings, we'd like to explore a referral or joint offer. Could we have a short call?".self::SIGN,
        ],
    ];
}
