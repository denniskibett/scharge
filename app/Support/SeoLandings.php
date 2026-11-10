<?php

namespace App\Support;

/**
 * Landing pages for what Kenyan landlords, agents and renters actually search for
 * (resources/views/home/landing.blade.php, routes "landing.<slug>").
 *
 * Every claim here repeats what the rest of the website already says (pricing, M-Pesa, reminders,
 * water billing, repairs, payouts); keep them in step when the product or prices change.
 * Section bodies and FAQ answers may contain simple HTML; FAQ answers are also used for FAQPage JSON-LD.
 */
class SeoLandings
{
    public const PAGES = [
        'property-management-software-kenya' => [
            'title' => 'Property management software in Kenya | Rent, water, M-Pesa | scharge',
            'description' => 'Property management software for Kenyan landlords, agents and management companies: rent and service charge on M-Pesa, water billing, repairs and owner statements. From KES 40 per unit a month.',
            'kicker' => 'Property management software, Kenya',
            'h1' => 'Property management software built for how Kenya pays rent',
            'lead' => 'scharge runs the money side of your buildings: invoices for rent, service charge and water, collection on M-Pesa and bank, reminders, receipts, repairs and owner statements. One app for one block or a whole portfolio, priced per unit.',
            'cta' => ['Price my property', 'home', '#pricing'],
            'sections' => [
                ['What property management software should do in Kenya', [
                    'Most Kenyan buildings are still run from an exercise book, a WhatsApp group and an M-Pesa statement. Software is worth it when it removes the three jobs that eat the month: <b>working out who paid</b>, <b>chasing arrears</b> and <b>reporting to the owner</b>.',
                    'scharge takes the weight off all three. Each payment is matched to the right unit and invoice, reminders go out by SMS with a link to pay, and the owner sees the same numbers you do.',
                ]],
                ['What you get', [
                    ['Rent and service charge invoicing', 'Monthly invoices per unit, with service charge, parking and other charges on the same bill.'],
                    ['M-Pesa collection', 'STK push prompts, SMS pay links and Paybill or Till payments, matched to the unit and invoice.'],
                    ['Water billing from meter readings', 'Caretakers record meter readings; each tenant’s bill shows the units used and the rate.'],
                    ['Arrears and reminders', 'A clear arrears list per building, and SMS rent reminders with a payment link.'],
                    ['Repairs and caretakers', 'Tenants report a repair; you approve the work and track it to done.'],
                    ['Owner statements and payouts', 'Collections, expenses and payouts per property, exported to Excel or sent to the owner in one tap.'],
                ]],
                ['What it costs', [
                    'One flat price per unit per month, set by the property type and the area it is in, the same whatever rent you charge. Apartments start at <b>KES 40 per unit a month</b> in affordable areas; vacant or unlisted units are billed at half price. There is 10% off from 100 units and 20% off from 300.',
                    'Tenants, guests and caretakers never pay to use scharge, and we import your existing tenant list from Excel for free.',
                ]],
            ],
            'faqs' => [
                ['Is scharge for landlords or for property management companies?', 'Both. A landlord with one block sees who has paid and what is owed; a management company runs many buildings for many owners, with a statement per owner.'],
                ['Do I need my own Paybill or Till?', 'No. If you have an M-Pesa Paybill or Till with a Daraja app, tenants pay straight into it. If not, rent is collected through Sharet Africa and paid out to your M-Pesa number, Paybill, Till or bank account several times a day.'],
                ['Can it handle service charge for an estate or HOA?', 'Yes. Service charge is billed per unit next to rent or on its own, and owners can see what came in and where it was spent.'],
                ['How long does setup take?', 'Most buildings are live in an afternoon: we import your units and tenants from Excel, you set rent, service charge and water rates, and tenants get an SMS invite.'],
                ['Does it work outside Nairobi?', 'Yes, anywhere in Kenya. Pricing follows the area band your property is in, from prime Nairobi suburbs to affordable towns.'],
            ],
            'related' => ['rent-collection-mpesa', 'landlord-app-kenya', 'tenant-management-software'],
        ],

        'rent-collection-mpesa' => [
            'title' => 'Rent collection on M-Pesa: Paybill, Till & STK push | scharge Kenya',
            'description' => 'Collect rent, service charge and water bills on M-Pesa with STK push, SMS pay links or your Paybill and Till. Each payment is matched to the right unit and invoice.',
            'kicker' => 'Rent collection, M-Pesa',
            'h1' => 'Collect rent on M-Pesa and know who paid before lunch',
            'lead' => 'Tenants pay rent the way they already pay for everything: M-Pesa. scharge sends the request, matches the payment to the right unit and issues the receipt, so you stop scrolling through statements to work out who paid.',
            'cta' => ['Start collecting', 'home', '#pricing'],
            'sections' => [
                ['Why matching is the hard part', [
                    'Taking M-Pesa is easy. Knowing that “J. Mwangi, KES 14,500” is the tenant in B4 who still owes last month’s water bill is not. When tenants type the wrong account number, pay in two parts or a relative pays for them, a manual statement goes wrong fast.',
                    'scharge matches each payment to the unit and invoice it belongs to, carries part-payments forward and flags the few it cannot place, so you check exceptions instead of every line.',
                ]],
                ['Three ways tenants pay', [
                    ['STK push prompt', 'With your own M-Pesa Daraja app, the tenant gets a prompt on their phone for the exact amount due and enters their PIN.'],
                    ['Pay link by SMS', 'The tenant gets an SMS with a link for the invoice and pays on M-Pesa in a few taps.'],
                    ['Paybill or Till', 'Payments to your own Paybill or Till are matched to the unit and invoice they belong to.'],
                ]],
                ['Where the money goes', [
                    'With your own M-Pesa Daraja app, rent goes straight into your Paybill or Till. Without one, rent is collected through Sharet Africa, our payments partner, and paid out to your M-Pesa number, Paybill, Till or bank account at 8am, noon and 4pm every day.',
                    'Every matched payment shows on the tenant’s account and the owner’s report, which ends the “I paid, check again” conversation.',
                ]],
            ],
            'faqs' => [
                ['Do I need a Paybill to collect rent on M-Pesa?', 'No. Without your own Paybill or Till, tenants pay through an SMS pay link and the rent is paid out to your M-Pesa number, Paybill, Till or bank account. With your own Daraja app, tenants get a payment prompt straight to your Paybill or Till.'],
                ['What happens when a tenant pays only part of the rent?', 'The payment is recorded against the invoice and the balance stays on the tenant’s account, visible in arrears and in the next reminder.'],
                ['How fast do I get the rent?', 'With your own Daraja app, immediately. Through Sharet Africa, payouts run at 8am, noon and 4pm Nairobi time every day.'],
                ['Can I collect water and service charge the same way?', 'Yes. Water bills from meter readings and service charge go on the same invoice and are paid the same way.'],
                ['Where do I see who has paid?', 'On your dashboard: collections and arrears per unit and per building, updated as payments are matched.'],
            ],
            'related' => ['property-management-software-kenya', 'tenant-management-software', 'landlord-app-kenya'],
        ],

        'landlord-app-kenya' => [
            'title' => 'Landlord app for Kenya: rent, arrears, water & repairs | scharge',
            'description' => 'A landlord app for Kenya: see who has paid, chase arrears by SMS, bill water from meter readings, approve repairs and get statements and payouts, on your phone.',
            'kicker' => 'Landlord app, Kenya',
            'h1' => 'The landlord app that tells you who has paid',
            'lead' => 'Whether you self-manage one block or keep an eye on an agent, scharge puts every payment, expense and payout for your buildings on your phone, with SMS reminders and pay links for tenants.',
            'cta' => ['See my price', 'home', '#pricing'],
            'sections' => [
                ['Self-managing landlords', [
                    'Stop chasing tenants on the phone. Invoices go out monthly, SMS reminders carry a link to pay, payments are matched to units and the arrears list is always current.',
                    'Water bills come from the caretaker’s meter readings, so each tenant sees the units used and the rate instead of a round figure.',
                ]],
                ['Landlords with an agent', [
                    'If an agent or management company runs the building, scharge shows you the same collections, expenses and payouts they see. Owner statements come from the record, not a spreadsheet typed up at month end.',
                ]],
                ['On every plan', [
                    ['Who paid, who owes', 'Collections and arrears per unit and per building, live.'],
                    ['Payout to your bank', 'With a statement of every shilling in and out.'],
                    ['Fill vacancies faster', 'List vacant units free on the scharge listings page and book viewings.'],
                    ['Deposits tracked', 'Deposits recorded per tenant, with refunds worked out at move-out.'],
                    ['Repairs', 'Tenants report a repair; you approve it and track the fundi.'],
                ]],
            ],
            'faqs' => [
                ['Is there a mobile app for landlords?', 'scharge works in the browser on any phone and computer, so landlords, caretakers and tenants can use it without a laptop.'],
                ['Can I use it for just one building?', 'Yes. Pricing is per unit, with a minimum of KES 1,500 per property a month.'],
                ['Will my tenants have to pay to use it?', 'No. Tenants, guests and caretakers never pay for scharge.'],
                ['Can I move my tenants from Excel?', 'Yes, free. Send us your Excel sheet or an export from your current system and we set it up.'],
                ['Is tenant data protected?', 'Tenant data is handled under Kenya’s Data Protection Act, 2019. IDs are encrypted and visible only to people you choose.'],
            ],
            'related' => ['rent-collection-mpesa', 'property-management-software-kenya', 'tenant-management-software'],
        ],

        'tenant-management-software' => [
            'title' => 'Tenant management software & SMS rent reminders | scharge Kenya',
            'description' => 'Tenant management for Kenyan landlords and agents: tenant records, monthly invoices, SMS rent reminders with a payment link, deposits and move-outs in one place.',
            'kicker' => 'Tenant management & rent reminders',
            'h1' => 'Tenant management and rent reminders, without the phone calls',
            'lead' => 'Keep every tenant’s unit, deposit, invoices and payments on one record, and send rent reminders by SMS with a link to pay, so arrears get paid without awkward phone calls.',
            'cta' => ['Get started', 'home', '#pricing'],
            'sections' => [
                ['One record per tenant', [
                    'Contact details, unit, rent, deposit, every invoice and every payment sit on the tenant’s record. When a tenant moves to another unit or leaves, the history moves with them and the deposit refund is worked out against what is still owed.',
                ]],
                ['Rent reminders that get paid', [
                    ['Ready-made templates', 'Rent reminder, water bill, payment confirmation and lease expiry messages you can edit.'],
                    ['One tenant or a whole building', 'Send a reminder to a single tenant or broadcast to a building at once.'],
                    ['A link to pay', 'A tenant can be texted a payment link for their invoice and pay on M-Pesa in a few taps.'],
                    ['Receipts and arrears', 'When the payment lands, the invoice is marked paid and the arrears list updates.'],
                ]],
                ['For tenants it is free', [
                    'Tenants never pay for scharge. They can see what they are charged for, pay on M-Pesa and report repairs.',
                ]],
            ],
            'faqs' => [
                ['How are rent reminders sent?', 'By SMS to the tenant’s phone, from a template with the amount due and how to pay, to one tenant or a whole building.'],
                ['Can I change the wording of reminders?', 'Yes. SMS templates can be edited.'],
                ['Does it track tenant deposits?', 'Yes. Deposits and refunds are recorded per tenant, so the balance at move-out is clear.'],
                ['Can tenants report repairs?', 'Yes. Tenants report a repair, and you approve and track it to done.'],
                ['Do I need to train my caretaker?', 'Caretakers use scharge on their phone for meter readings, repairs and gate passes; most are comfortable after one walk-through.'],
            ],
            'related' => ['rent-collection-mpesa', 'landlord-app-kenya', 'property-management-software-kenya'],
        ],

        'houses-to-let-nairobi' => [
            'title' => 'Houses & apartments to let in Nairobi, no agent fees | scharge',
            'description' => 'Houses, apartments and bedsitters to let in Nairobi and nearby towns, listed by the owners on scharge. Rent, water, service charge and deposit shown up front; book a viewing free.',
            'kicker' => 'Houses to let, Nairobi',
            'h1' => 'Houses and apartments to let in Nairobi',
            'lead' => 'Vacant units listed by the owners and managers who run them on scharge, with every cost shown before you visit: rent, water rate, service charge and deposit. Viewings are free and there are no agent fees.',
            'cta' => ['Browse all vacancies', 'home.listings', '#browse'],
            'listings' => true,
            'sections' => [
                ['How to rent safely in Nairobi', [
                    ['See the full monthly cost', 'Water per cubic metre, service charge and deposit matter as much as the rent. Every listing here shows them.'],
                    ['Never pay a viewing fee', 'Viewings booked through scharge are free; the caretaker confirms a time on WhatsApp.'],
                    ['Pay deposits to the property', 'Never pay a deposit to a personal number. On scharge, deposits go to the property’s account and you get a receipt.'],
                ]],
                ['Landlords: list a vacancy free', [
                    'Vacant units on any scharge plan are listed here free, with viewings and the first deposit landing in your dashboard. <a href="'.'/listings#owners'.'">List my vacancy</a>.',
                ]],
            ],
            'faqs' => [
                ['Do I pay an agent fee to rent through scharge?', 'No. Listings are posted by the owners and managers of the buildings, and booking a viewing is free.'],
                ['Is water included in the rent?', 'Each listing shows the water rate per cubic metre, service charge and deposit next to the rent, so you can work out the full monthly cost.'],
                ['How do I book a viewing?', 'Open a listing and send a viewing request with your name and WhatsApp number; the caretaker replies with a time.'],
                ['Can I get an alert for new houses?', 'Yes. On the listings page, leave your number and we send an SMS when a matching vacancy is listed.'],
            ],
            'related' => ['landlord-app-kenya', 'property-management-software-kenya', 'rent-collection-mpesa'],
        ],
    ];

    /** Short anchor text for links between landing pages and from the footer. */
    public const LINK_TEXT = [
        'property-management-software-kenya' => 'Property management software Kenya',
        'rent-collection-mpesa' => 'Rent collection on M-Pesa',
        'landlord-app-kenya' => 'Landlord app',
        'tenant-management-software' => 'Tenant management & rent reminders',
        'houses-to-let-nairobi' => 'Houses to let in Nairobi',
    ];

    public static function get(string $slug): ?array
    {
        return self::PAGES[$slug] ?? null;
    }
}
