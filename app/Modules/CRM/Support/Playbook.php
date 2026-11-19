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
            $company->kind === 'agency' => 'agency',
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
            'Real Estate Agency' => 'agency',
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
            // Their sharet.africa work address when they have one (config/crm.php work_copies), else their login email.
            '{manager_email}' => $manager ? (collect($manager->mailboxes())->first(fn ($e) => str_ends_with($e, '@sharet.africa')) ?? $manager->email) : config('mail.from.address'),
            '{manager_phone}' => $manager?->phone ?: '',
            '{demo_link}' => self::demoUrl($company),
        ]);
    }

    /** The company's private demo-form reference (created once, kept in crm_profile.demo_ref). */
    /** Cold emails go out from this address (replies go to the account manager). */
    public const SALES_FROM = 'sales@sharet.africa';

    /** Once a prospect has filled the demo form (demo booked onwards), their emails come from onboarding instead. */
    public const ONBOARDING_FROM = 'onboarding@sharet.africa';

    public const ONBOARDING_STAGES = ['demo_booked', 'proposal', 'negotiation', 'won'];

    public static function onboarding(?\App\Models\Company $company): bool
    {
        return $company && in_array($company->crm_stage, self::ONBOARDING_STAGES, true);
    }

    /** The From address for an email to this company: sales@ while cold, onboarding@ once they want to onboard. */
    public static function fromFor(?\App\Models\Company $company): string
    {
        return self::onboarding($company) ? self::ONBOARDING_FROM : self::SALES_FROM;
    }

    /**
     * Reply-To for an email to this company: the account manager's inboxes (login and work copy) and, once the
     * prospect is onboarding, the onboarding inbox too.
     *
     * @return \Illuminate\Mail\Mailables\Address[]
     */
    public static function replyTo(?\App\Modules\Users\Models\User $manager, bool $onboarding): array
    {
        $to = $manager ? $manager->mailboxes() : [];
        // sales@ (on our own mail server) gets a copy of every reply, so crm:mail-replies can raise the CRM alert.
        $to[] = self::SALES_FROM;
        if ($onboarding) {
            $to[] = self::ONBOARDING_FROM;
        }

        return array_map(fn ($e) => new \Illuminate\Mail\Mailables\Address($e, match ($e) { self::ONBOARDING_FROM => 'scharge onboarding', self::SALES_FROM => 'scharge sales', default => $manager?->name ?? 'scharge' }), array_values(array_unique($to)));
    }

    /** Demo refs start with the property type in full, e.g. BNB-7KQ2MA, RESIDENTIAL-4HD8PQ; older refs start SC-. */
    public const REF_PREFIX = ['Short-stay' => 'BNB', 'Residential' => 'RESIDENTIAL', 'Commercial' => 'COMMERCIAL', 'Utilities' => 'UTILITIES', 'Mixed use' => 'MIXED', 'Mixed portfolio' => 'MIXED', 'Land & other' => 'LAND', 'Agency' => 'AGENCY'];

    public const REF_PATTERN = '[A-Za-z]{2,12}-[A-Za-z0-9]{6}';

    public static function refPrefix(Company $company): string
    {
        return self::REF_PREFIX[PropertyClass::of($company)] ?? 'SC';
    }

    public static function demoRef(Company $company): string
    {
        if ($ref = $company->profile('demo_ref')) {
            return $ref;
        }
        do {
            $ref = self::refPrefix($company).'-'.strtoupper(Str::random(6));
        } while (Company::withoutGlobalScope('customers')->where('crm_profile->demo_ref', $ref)->exists());
        $company->forceFill(['crm_profile' => array_merge($company->crm_profile ?? [], ['demo_ref' => $ref])])->saveQuietly();

        return $ref;
    }

    public static function demoUrl(Company $company): string
    {
        return rtrim(preg_replace('#^http://(?!localhost|127\.)#', 'https://', (string) config('app.url')), '/').'/demo/'.self::demoRef($company);
    }

    /** Likely problems for this company's segment: key => [problem, how scharge fixes it]. */
    public static function pains(Company $company): array
    {
        return self::PAINS[self::segmentOf($company)] ?? self::PAINS['manager'];
    }

    /** What we already know about them from the CRM (facts to open with). @return array<string> */
    public static function signals(Company $company): array
    {
        $p = fn ($k) => trim((string) $company->profile($k));
        $where = collect([$p('area'), $p('city')])->filter()->unique()->join(', ');

        return array_values(array_filter([
            $p('portfolio') ? 'Portfolio / listings: '.$p('portfolio') : null,
            $p('estimated_units') ? 'About '.number_format((int) $p('estimated_units')).' units' : null,
            $where ? 'Based in '.$where : null,
            $p('business_type') ? 'Registered as: '.$p('business_type') : null,
            $p('clientele') ? 'Clientele they state: '.$p('clientele') : null,
            $p('scale_signal') ? 'Scale: '.$p('scale_signal') : null,
            $p('website') ? 'Website: '.$p('website') : null,
        ]));
    }

    /**
     * A researched email: what we noticed about them, the problems that come with it, how scharge fixes each, and the
     * demo link. $research is the account manager's own finding (one or two sentences).
     */
    public static function compose(Company $company, ?User $manager, array $painKeys, ?string $research): array
    {
        $pains = array_intersect_key(self::pains($company), array_flip($painKeys)) ?: array_slice(self::pains($company), 0, 2, true);
        $name = preg_replace('/\s*\([^()]*\)\s*$/', '', $company->name);
        $city = $company->profile('city') ?: 'Kenya';
        $noticed = trim((string) $research) !== ''
            ? rtrim(trim($research), '.').'.'
            : 'We were looking at '.$name.' and the properties you handle in '.$city.'.';

        $lines = ["Hello {$name} team,", "We are from scharge. {$noticed}"];
        $lines[] = 'Teams in your position usually tell us about '.(count($pains) > 1 ? 'a few headaches' : 'one headache').":\n"
            .collect($pains)->map(fn ($x) => '• '.$x[0].' → '.$x[1])->join("\n");
        $lines[] = 'If any of that sounds familiar, we can show you how it would work on one of your properties in 20 minutes.';
        $first = array_values($pains)[0][0] ?? 'Month-end billing';
        $hook = preg_split('/ (and|until|that|by|on|in) /', rtrim($first, '.'))[0];
        $subject = $name.': '.lcfirst($hook).'?';

        return [
            'subject' => self::fill(\Illuminate\Support\Str::limit($subject, 110, '…'), $company, $manager),
            'body' => self::fill(implode("\n\n", $lines).self::SIGN, $company, $manager),
        ];
    }

    private const PAINS = [
        // Agencies: wording approved by the user before the AGENCY campaign is sent.
        'agency' => [
            'listings' => ['Vacant homes scattered across portals and WhatsApp groups, with landlords forgetting to tell you what is free', 'a landlord adds your agency to their building once; you see which homes are vacant and put them on scharge Open listings with photos'],
            'enquiries' => ['Renter enquiries lost between calls, DMs and portal inboxes', 'every viewing request reaches your agent dashboard and your phone by SMS straight away'],
            'commission' => ['Commission agreed by phone and argued about after the tenant moves in', 'your commission % is recorded with the landlord when they give you the building'],
            'duplicates' => ['The same house posted by five agents at five prices', 'each building exists once on scharge and every agent links to it, so renters see one honest listing'],
        ],
        'manager' => [
            'chase' => ['Chasing tenants for rent every month and confirming M-Pesa messages by hand', 'invoices go out automatically by SMS/email and every M-Pesa or bank payment is matched to the tenant'],
            'statements' => ['Landlords asking for statements and wondering where their money is', 'each landlord gets a live statement and payouts are recorded'],
            'water' => ['Water and service charge worked out on spreadsheets', 'meter readings turn into bills on the same invoice'],
            'arrears' => ['Arrears that pile up unnoticed until a tenant moves out', 'an arrears list with automatic reminders'],
            'vacancy' => ['Vacant units that take too long to fill', 'vacant units are listed publicly with photos, straight from the system'],
        ],
        'shortstay' => [
            'commission' => ['Paying 15–18% platform commission on every booking', 'repeat and local guests book on your own page with no commission'],
            'calendar' => ['Double bookings and calendars kept in several places', 'one calendar with minimum stays, rates and cleaning fees'],
            'utilities' => ['Electricity and water eating into each unit\'s margin', 'per-unit utility tracking so you know what each stay really earned'],
            'owners' => ['Owners of co-hosted units asking for payouts and statements', 'owner statements and payouts are generated for you'],
        ],
        'hotel' => [
            'longstay' => ['Long-stay guests and serviced units billed by hand every month', 'monthly billing for long stays, paid by M-Pesa'],
            'subm' => ['No clear view of power and water used per unit', 'sub-metering with readings turned into bills'],
            'staff' => ['Staff housing rent and utilities tracked on paper', 'staff housing billed like any other unit'],
        ],
        'developer' => [
            'handover' => ['Service charge collections that fall apart after hand-over', 'the estate is set up on scharge before hand-over, so residents pay from day one'],
            'utilities' => ['Disputes over water and electricity in new estates', 'metered billing per unit, with readings residents can see'],
            'instalments' => ['Off-plan or tenant-purchase instalments tracked in Excel', 'instalments are tracked per buyer with M-Pesa matching and SMS statements'],
            'unsold' => ['Unsold or rental units that need marketing', 'units are listed publicly from the system'],
        ],
        'affordable' => [
            'scale' => ['Thousands of small monthly payments to reconcile', 'every M-Pesa payment is matched to its unit automatically'],
            'arrears' => ['Arrears that are hard to see across a large scheme', 'arrears per unit with SMS reminders and statements'],
            'utilities' => ['Water, power and service charge billed separately', 'one bill per unit covering all of them'],
        ],
        'building' => [
            'chase' => ['Collecting rent door to door or from M-Pesa messages', 'bills go out by SMS and payments are matched to each unit'],
            'water' => ['Water bills worked out by hand from meter readings', 'the caretaker enters readings on a phone and bills are sent'],
            'visibility' => ['Not knowing who has paid without calling the caretaker', 'a live view of paid and unpaid units'],
        ],
        'commercial' => [
            'recharges' => ['Power and water recharges to tenants done on spreadsheets', 'sub-meter readings become recharge invoices automatically'],
            'servicecharge' => ['Service charge invoices and reconciliations taking days', 'service charge is invoiced and reconciled with rent'],
            'tickets' => ['Tenant maintenance requests lost in WhatsApp groups', 'requests become tracked tickets'],
        ],
        'industrial' => [
            'allocation' => ['No clear split of power and water costs across units or tenants', 'sub-metering with a cost split per unit'],
            'housing' => ['Staff housing rent and utilities deducted by hand', 'staff housing billed automatically'],
        ],
        'water_utility' => [
            'bulk' => ['Estates on bulk meters paying late or disputing bills', 'scharge bills each unit behind the bulk meter so the estate pays on time'],
            'reading' => ['Meter reading capture that is slow and error-prone', 'a reading app and API with photos and checks'],
        ],
        'water_private' => [
            'tracking' => ['Deliveries and meter readings tracked in a book', 'customer accounts with delivery and meter billing'],
            'collections' => ['Chasing customers for payment', 'bills by SMS with M-Pesa payment matched to each customer'],
        ],
        'power' => [
            'billing' => ['Clients who install meters but still bill tenants by hand', 'scharge turns your meter data into tenant bills'],
            'referrals' => ['Every installation ends at the hardware sale', 'a referral arrangement that earns on the billing'],
        ],
    ];

    public const OPT_OUT = "If you'd rather not hear from us, reply \"stop\" and we won't email you again.";

    private const SIGN = "\n\nYou can pick a time and tell us about your properties here (2 minutes): {demo_link}\n\nKind regards,\n{manager}\nAccount Manager, scharge\n{manager_email}  {manager_phone}\nhttps://scharge.sharet.africa";

    private const BOOKS = [
        'agency' => [
            'label' => 'Real estate agency / agent',
            'approach' => [
                'Ask which areas and home types they let or sell, and how many landlords they work with.',
                'Lead with listings and enquiries: their landlords add the agency once and vacant homes go on Open listings with photos.',
                'Show the agent dashboard: vacant homes, photos, renter enquiries by SMS, commission % recorded per building.',
                'Close on setting up the agency account today and inviting two of their landlords.',
            ],
            'offers' => ['Agency account and agent logins (Team › Agent role)', 'Buildings landlords give you, with live vacancy', 'Open listings with photos', 'Renter enquiries on the dashboard and by SMS', 'Commission % recorded per building', 'One record per building: no duplicate listings'],
            'objections' => ['We already use the portals' => 'Keep them; scharge adds a listing that stays in sync with real vacancy, and the landlord sees it too.', 'Landlords won\'t join' => 'Landlords join to collect rent; adding your agency is one step on their property page.'],
            'subject' => '{company}: vacant homes, enquiries and commission in one place',
            'body' => "Hello {company} team,\n\nWe are from scharge, a Kenyan property platform used by landlords and managers in {city}. Agencies can now work inside scharge: when a landlord gives your agency their building, you see which homes are vacant, put them on scharge Open listings with photos, and get every renter enquiry by SMS straight away, with your commission recorded against the building.\n\nCould we set up your agency account this week? It takes about 10 minutes.".self::SIGN,
        ],
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
            'body' => "Hello {company} team,\n\nWe are from scharge, a Kenyan property-management platform used by managers in {city}. We send rent and service-charge invoices automatically, match M-Pesa and bank payments to each tenant, bill water and electricity from meter readings, and give your landlords live statements.\n\nWould you have 20 minutes this week for a short demo on one of your buildings? We can import your tenant list for you, and pricing starts at KES 40 per unit per month.".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. We help short-stay hosts in {city} take bookings on their own website with no commission, collect payment by M-Pesa or card, and keep cleaning fees, utilities and owner payouts in one place.\n\nCould we show you how it would look for your units in a 15-minute call this week?".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. For hotels and serviced apartments in {city} we handle the part a front-desk system usually doesn't: monthly billing for long-stay guests and staff housing, electricity and water sub-metering per unit, and payments by M-Pesa.\n\nWould a short call this week be useful?".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. We help developers in {city} hand over estates that run smoothly from day one: service-charge billing, water and electricity metering, a resident app with notices and gate passes, and M-Pesa collections, priced per unit.\n\nIs there a project coming up for hand-over that we could set up for you? We'd be glad to walk you through it in 20 minutes.".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. For affordable-housing schemes we handle thousands of small monthly payments: rent or tenant-purchase instalments matched automatically from M-Pesa, SMS statements and arrears reminders, plus water, power and service-charge billing per unit.\n\nCould we set up a short call to see if it fits your projects in {city}?".self::SIGN,
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
            'body' => "Hello,\n\nWe are from scharge. We help apartment owners and managing agents in {city} send rent and water bills by SMS, get paid by M-Pesa with every payment matched to its unit, and see who owes what at a glance.\n\nIf you manage {company}, could I show you how it works in 15 minutes?".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. For commercial buildings and malls in {city} we automate tenant rent and service-charge invoices, bill electricity and water recharges from sub-meters, and track facilities requests in one place.\n\nWould a 20-minute demo this week be useful?".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. We help sites in {city} track and bill electricity and water per unit, whether that's warehouse tenants, staff housing or departments, from manual or smart-meter readings, with payments by M-Pesa.\n\nWould a short call with your facilities team be useful?".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. We run water billing inside estates and apartment blocks: readings for each unit, bills by SMS and payments by M-Pesa, behind the bulk meters your company supplies.\n\nThat usually means fewer disputes and faster bulk payments to the utility. Could we arrange a short meeting with your commercial team to explore a pilot in {city}?".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. We help water suppliers in {city} bill customers per delivery or per meter reading, send bills by SMS and get paid by M-Pesa, with every payment matched to its customer.\n\nWould a 15-minute call be useful?".self::SIGN,
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
            'body' => "Hello {company} team,\n\nWe are from scharge. We bill tenants and residents for electricity and water from sub-meter readings, entered manually or pulled from smart meters through our API, and collect by M-Pesa.\n\nIf you install meters or solar in estates and buildings, we'd like to explore a referral or joint offer. Could we have a short call?".self::SIGN,
        ],
    ];
}
