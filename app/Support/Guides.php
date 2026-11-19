<?php

namespace App\Support;

/**
 * Answer-style guides for the public website (/guides/{slug}): the questions Kenyan landlords, agents
 * and hosts ask search engines and AI assistants. Each guide opens with a short, quotable answer,
 * then detail and FAQs. Used by the guide pages, sitemap.xml, llms.txt and FAQPage/Article JSON-LD.
 *
 * Keep facts true to the product and pricing (resources/views/welcome.blade.php #pricing-data):
 * apartments KES 500 / 300 / 150 / 40 per unit a month (prime / upmarket / mid-market / affordable),
 * short stays KES 1,500 / 1,000 / 700 / 400 per listing, offices KES 2,000 / 1,500 / 1,000 / 600 per suite,
 * minimum KES 1,500 per property, vacant units half price.
 */
class Guides
{
    public const UPDATED = '2026-11-19';

    public static function all(): array
    {
        return [
            'rent-collection-software-kenya' => [
                'title' => 'Best rent collection software in Kenya (2026 buyer’s checklist) | scharge',
                'h1' => 'What is the best rent collection software in Kenya?',
                'description' => 'How to choose rent collection software in Kenya: M-Pesa Paybill matching, water billing, SMS reminders, owner reports and fair per-unit pricing. A checklist for landlords and agents.',
                'answer' => 'The best rent collection software for a Kenyan landlord is one that takes rent on your own M-Pesa Paybill or Till, matches every payment to the right unit automatically, sends invoices, receipts and SMS reminders, bills water from meter readings, and gives owners a monthly statement. scharge does all of this and is priced per unit by area, from KES 40 per unit a month, so a 20-unit block in Ruiru costs the KES 1,500 minimum and a 20-unit block in Kilimani costs KES 10,000.',
                'sections' => [
                    ['What to check before you choose', '<ul>
<li><b>Money goes to you, not the software company.</b> Tenants should pay into your own Paybill, Till or bank account. Avoid systems that collect into their own account and pay you later.</li>
<li><b>Automatic matching.</b> Tenants type the house number (e.g. <i>B4</i>) as the account number; the system should match it to the unit, mark the invoice paid and send a receipt without anyone reading an M-Pesa statement.</li>
<li><b>Water and service charge on the same invoice.</b> Most Nairobi apartments sub-meter water. The software should take meter readings, apply your rate per m³ and add it to the month’s rent invoice.</li>
<li><b>SMS reminders and arrears.</b> Reminders before the due date, a polite follow-up after, and an arrears list you can act on.</li>
<li><b>Owner statements and payouts.</b> If you manage for owners, each owner needs a monthly statement: rent collected, expenses, management fee, payout.</li>
<li><b>Repairs and caretakers.</b> Tenants report repairs from their phone; caretakers and plumbers see the jobs.</li>
<li><b>Fair pricing.</b> A flat price per unit is easier to budget than a percentage of rent. Ask how vacant units are billed.</li>
<li><b>Your data.</b> The provider should follow Kenya’s Data Protection Act, 2019, and let you export everything.</li>
</ul>'],
                    ['How scharge prices it', '<p>One flat price per unit per month, set by property type and the area band the property is in, the same whatever rent you charge:</p>
<table><thead><tr><th>Area band</th><th>Examples</th><th>Apartments, per unit</th></tr></thead><tbody>
<tr><td>Prime</td><td>Westlands, Kilimani, Upper Hill, Karen</td><td>KES 500</td></tr>
<tr><td>Upmarket</td><td>Kileleshwa, Lavington, Lang’ata, Syokimau</td><td>KES 300</td></tr>
<tr><td>Mid-market</td><td>Ruaka, Kasarani, South B, Thindigua</td><td>KES 150</td></tr>
<tr><td>Affordable</td><td>Ruiru, Kitengela, Rongai, Umoja</td><td>KES 40</td></tr>
</tbody></table>
<p>Minimum KES 1,500 per property a month. Vacant units are billed at half price. Moving your tenant list from Excel or another system is free.</p>'],
                    ['Who it suits', '<p>Individual landlords with one block, family-owned estates, property management agencies with hundreds of units, developers handing over new apartments, and Airbnb hosts who also have long lets. Residential apartments in Kileleshwa, Kilimani, Lavington, Lang’ata, Upper Hill, Westlands, Ruaka and Ruiru are our core market; short stays, offices and warehouses run on the same account.</p>'],
                ],
                'faqs' => [
                    ['Can tenants pay rent with M-Pesa?', 'Yes. Tenants pay to your Paybill (house number as the account number), your Till, or by M-Pesa prompt on their phone. Card and bank transfer also work.'],
                    ['Does scharge hold my rent money?', 'No. Money goes straight into your own M-Pesa or bank account. scharge matches and receipts each payment but never holds it.'],
                    ['How much does rent collection software cost in Kenya?', 'On scharge, from KES 40 to KES 500 per apartment unit a month depending on the area, with a KES 1,500 minimum per property. Vacant units are half price.'],
                    ['Can I move my tenants from Excel?', 'Yes, free. Send the sheet and we set up units, tenants, rents and opening balances.'],
                ],
                'cta' => ['Price my property', 'home', '#pricing'],
            ],

            'collect-rent-mpesa-paybill' => [
                'title' => 'How to collect rent on M-Pesa Paybill and match it to units | scharge',
                'h1' => 'How do I collect rent on M-Pesa and match payments to each house?',
                'description' => 'Set up an M-Pesa Paybill or Till for rent in Kenya, use the house number as the account number, and let software match payments, send receipts and chase arrears.',
                'answer' => 'Get a Paybill (or Till) in the property’s or your company’s name, tell tenants to pay with their house number as the account number, and connect the Paybill to rent software. Each payment is then matched to the unit, the invoice is marked paid, the tenant gets an SMS receipt and the arrears list updates on its own, so nobody reconciles M-Pesa statements by hand.',
                'sections' => [
                    ['Step by step', '<ol>
<li><b>Open a Paybill or Till</b> through Safaricom (business or individual). A Paybill lets tenants enter an account number, which is what makes automatic matching work.</li>
<li><b>Label every unit clearly</b> (A1, A2, B4…). That label is the account number tenants type.</li>
<li><b>Connect it to scharge.</b> We register the confirmation link with Safaricom so each payment arrives in your dashboard within seconds.</li>
<li><b>Send invoices on the 1st</b> by SMS with the Paybill, account number and amount, including water and service charge.</li>
<li><b>Let reminders run.</b> Gentle reminder before the due date, follow-up after. Tenants can also pay by an M-Pesa prompt sent to their phone.</li>
<li><b>Handle wrong account numbers.</b> Payments with a typo land in an “unmatched” list for you to assign to the right unit in one click.</li>
</ol>'],
                    ['Common mistakes', '<ul><li>Letting tenants pay to a caretaker’s personal number: no record, and disputes later.</li><li>Using a Till for a large block without account numbers: every payment has to be matched by name.</li><li>Separate water collection in cash: put water on the same invoice so it is paid with rent.</li></ul>'],
                ],
                'faqs' => [
                    ['Paybill or Till for rent?', 'Paybill, for anything over a few units: the account number (house number) is what lets software match payments automatically. A Till works for very small properties.'],
                    ['How fast does a payment show?', 'Within seconds of the M-Pesa confirmation, with an SMS receipt to the tenant.'],
                    ['What if a tenant pays part of the rent?', 'The payment is recorded against the unit and the balance stays on the arrears list until it is cleared.'],
                ],
                'cta' => ['See how it works', 'home', '#who'],
            ],

            'water-billing-tenants-nairobi' => [
                'title' => 'How to bill tenants for water in Nairobi (sub-meters, rates, disputes) | scharge',
                'h1' => 'How should a landlord bill tenants for water in Nairobi?',
                'description' => 'Water billing for apartments in Nairobi and Kenya: sub-meter each unit, read meters monthly, charge a clear rate per cubic metre, and show readings on the invoice to avoid disputes.',
                'answer' => 'Fit a sub-meter to every unit, read all meters on the same day each month, multiply each unit’s consumption (current minus previous reading) by a published rate per cubic metre, and put the readings and the amount on the rent invoice. Showing both readings, and a photo of the meter when there is a dispute, is what stops arguments. scharge records readings from the caretaker’s phone and adds water to each invoice automatically.',
                'sections' => [
                    ['Setting the rate', '<p>Most landlords charge a flat rate per m³ that covers what the property pays the water company plus boreholes, bowsers and pumping. Write the rate in the tenancy agreement and show it on every invoice. If you change it, tell tenants a month ahead.</p>'],
                    ['A monthly routine that works', '<ol><li>Caretaker reads every meter on the same date (e.g. the 25th) and enters readings in the app, with a photo for unusual jumps.</li><li>The system works out each unit’s consumption; check any reading that is lower than last month or far higher than normal before invoicing.</li><li>Water is added to the next rent invoice with both readings shown.</li><li>Tenants pay rent and water together on M-Pesa.</li></ol>'],
                    ['Shared or unmetered units', '<p>Where units are not metered, split the bulk bill by a fixed method (equal shares or by bedrooms) and state it in the agreement. Metering is fairer and usually pays for itself in reduced waste.</p>'],
                ],
                'faqs' => [
                    ['Can I charge tenants more than the water company charges me?', 'You can set a rate that recovers your real costs (bulk water, borehole, pumping, bowsers). State it in the tenancy agreement and on invoices so it is transparent.'],
                    ['How do I stop water billing disputes?', 'Read meters on a fixed date, show previous and current readings on the invoice, and keep a meter photo for any reading a tenant questions.'],
                    ['Does scharge bill water for commercial buildings too?', 'Yes. The same readings-to-invoice flow works for offices, shops and warehouses, and for other metered utilities.'],
                ],
                'cta' => ['Price my property', 'home', '#pricing'],
            ],

            'service-charge-management-kenya' => [
                'title' => 'Service charge in Kenya: how to collect and account for it | scharge',
                'h1' => 'How do you manage service charge for apartments in Kenya?',
                'description' => 'Service charge for apartments and gated communities in Kenya: set a budget, bill monthly with rent or separately, collect on M-Pesa, and report spending to owners and residents.',
                'answer' => 'Set an annual budget (security, cleaning, garbage, common power and water, lifts, insurance, repairs), divide it by unit or by size, bill it monthly on M-Pesa, and publish what was spent. Owners and residents pay more willingly when they can see collection rates and spending. scharge bills service charge alongside rent or on its own, collects on M-Pesa and produces the statements.',
                'sections' => [
                    ['Who pays', '<p>In rented apartments the landlord usually includes service charge in the rent or bills it separately to the tenant. In sold apartments (sectional titles) each owner pays the management company or residents’ association, and may pass it on to their tenant.</p>'],
                    ['For developers at handover', '<p>Set service charge up before the first buyer moves in: buyers then move into a building that is already collecting, with security and cleaning paid from day one. See <a href="/developers">scharge for developers</a>.</p>'],
                ],
                'faqs' => [
                    ['Can service charge be paid on M-Pesa?', 'Yes, to the management company’s or association’s Paybill with the unit number as the account number.'],
                    ['How do I show residents what the money was spent on?', 'Record expenses against the budget lines and share a monthly or quarterly statement; scharge generates it from the payments and expenses you record.'],
                ],
                'cta' => ['Plan my handover', 'home.developers', '#talk'],
            ],

            'airbnb-short-stay-management-nairobi' => [
                'title' => 'Airbnb and short-stay management in Nairobi: tools for hosts | scharge',
                'h1' => 'How do I manage Airbnb and short-stay apartments in Nairobi?',
                'description' => 'Short-stay management in Nairobi (Kilimani, Kileleshwa, Westlands, Lavington): one calendar, smart-lock codes, cleaner checklists, M-Pesa payouts. Flat fee per listing, no commission.',
                'answer' => 'Hosts in Kilimani, Kileleshwa, Westlands and Lavington run short stays well with four things: one calendar so nights are never double-booked, a door code or key-handover routine per guest, a cleaning checklist after every checkout, and payments and payouts on M-Pesa with a monthly report per listing. scharge provides these for a flat fee from KES 400 to KES 1,500 per listing a month by area, with no percentage of bookings.',
                'sections' => [
                    ['What a stay looks like on scharge', '<ol><li>Each booking goes on one calendar per listing, so nights are never sold twice (automatic Airbnb and Booking.com calendar sync is coming soon).</li><li>Guest gets the check-in details and, with a smart lock, a door code that works only for their dates.</li><li>After checkout the cleaner gets a checklist with photos to tick off.</li><li>Payments, deposits and the owner’s payout are on one statement.</li></ol>'],
                    ['Hosts in managed apartment buildings', '<p>If your unit is in a building run by a management company, register just your unit: the building is added once, your unit stays yours, and the management company can bill you service charge and water in the same system.</p>'],
                ],
                'faqs' => [
                    ['Does scharge take a commission on bookings?', 'No. You pay a flat monthly fee per listing based on the area.'],
                    ['Which smart locks work in Kenya?', 'Wi-Fi smart locks on the TTLock, igloohome or Yale apps, the brands most sold in Kenya. Without one, use the key-handover checklist.'],
                    ['Do I have to pay tax on Airbnb income in Kenya?', 'Short-stay income is taxable in Kenya. Speak to a tax adviser or KRA about which tax applies to you; scharge’s reports give you the income figures per listing.'],
                ],
                'cta' => ['List my BnB', 'host.register', ''],
            ],

            'property-management-software-nairobi-apartments' => [
                'title' => 'Property management software for Nairobi apartments: Kileleshwa, Kilimani, Westlands, Ruaka | scharge',
                'h1' => 'Property management software for apartments in Kileleshwa, Kilimani, Lavington, Westlands, Upper Hill, Lang’ata, Ruaka and Ruiru',
                'description' => 'Rent, water and service charge collection on M-Pesa for apartment blocks in Kileleshwa, Kilimani, Lavington, Lang’ata, Upper Hill, Westlands, Ruaka and Ruiru. Prices per unit by area.',
                'answer' => 'scharge is property management software built in Nairobi for apartment blocks. It collects rent, water and service charge on the landlord’s own M-Pesa Paybill, matches payments to units, sends SMS receipts and reminders, and reports to owners. Per-unit prices follow the area: KES 500 in prime areas such as Westlands, Kilimani and Upper Hill; KES 300 in upmarket areas such as Kileleshwa, Lavington and Lang’ata; KES 150 in mid-market areas such as Ruaka; KES 40 in affordable areas such as Ruiru.',
                'sections' => [
                    ['By area', '<table><thead><tr><th>Area</th><th>Typical property</th><th>Price band</th></tr></thead><tbody>
<tr><td>Kilimani, Westlands, Upper Hill</td><td>High-rise 1–3 bedroom apartments, serviced apartments, short stays</td><td>Prime: KES 500 per unit</td></tr>
<tr><td>Kileleshwa, Lavington, Lang’ata</td><td>Mid-rise family apartments, maisonettes in gated compounds</td><td>Upmarket: KES 300 per unit</td></tr>
<tr><td>Ruaka</td><td>Dense 1–2 bedroom blocks near the bypass</td><td>Mid-market: KES 150 per unit</td></tr>
<tr><td>Ruiru</td><td>Bedsitters, 1 bedroom blocks, student housing</td><td>Affordable: KES 40 per unit</td></tr>
</tbody></table><p>Minimum KES 1,500 per property a month; vacant units half price. Short-stay units, offices and warehouses in the same portfolio are priced by their own type.</p>'],
                    ['What managers in these areas use most', '<ul><li>Water billing from sub-meters (common in Kileleshwa, Kilimani and Ruaka blocks with boreholes and bowsers).</li><li>Service charge for lifts, generators and security in high-rises.</li><li>Vacancy listings with every cost shown, at <a href="/listings">Open listings</a>.</li><li>Short-stay calendars for units run as Airbnbs in Kilimani and Westlands.</li></ul>'],
                ],
                'faqs' => [
                    ['Is scharge used outside Nairobi?', 'Yes. It works anywhere in Kenya; pricing follows the area band of each property.'],
                    ['Can one account hold apartments and Airbnb units?', 'Yes. Each property is priced by its own type and area, on one bill and one dashboard.'],
                ],
                'cta' => ['Price my property', 'home', '#pricing'],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        $g = self::all()[$slug] ?? null;

        return $g ? $g + ['slug' => $slug] : null;
    }

    public static function url(string $slug): string
    {
        return route('home.guide', $slug);
    }

    public static function ctaUrl(array $guide): string
    {
        [, $route, $hash] = $guide['cta'];

        return route($route).$hash;
    }

    /** Article + FAQPage + BreadcrumbList for one guide. */
    public static function jsonLd(array $g): array
    {
        $url = self::url($g['slug']);

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $g['h1'],
                'description' => $g['description'],
                'abstract' => $g['answer'],
                'url' => $url,
                'mainEntityOfPage' => $url,
                'inLanguage' => 'en-KE',
                'datePublished' => '2026-11-19',
                'dateModified' => self::UPDATED,
                'author' => ['@id' => url('/').'#organization'],
                'publisher' => ['@id' => url('/').'#organization'],
                'image' => asset('assets/images/og-image.png'),
                'about' => ['@type' => 'Thing', 'name' => 'Property management in Kenya'],
                'spatialCoverage' => ['@type' => 'Place', 'name' => 'Nairobi, Kenya'],
            ],
            Seo::faq(array_merge([[$g['h1'], $g['answer']]], $g['faqs'])),
            Seo::breadcrumbs([['Home', route('home')], ['Guides', route('home.guides')], [$g['h1'], $url]]),
        ];
    }
}
