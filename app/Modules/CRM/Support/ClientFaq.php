<?php

namespace App\Modules\CRM\Support;

/**
 * The account manager's FAQ (CRM › Client FAQ): how to read a client, the order of a sales call, what to say about
 * price, vacant units and white label, and what clients ask. Answers are plain text; **bold** is allowed.
 */
class ClientFaq
{
    /** @return array<int, array{id:string, title:string, intro:string, items:array<int, array{q:string, a:array<int,string>}>}> */
    public static function sections(): array
    {
        $price = Playbook::price();

        return [
            [
                'id' => 'call',
                'title' => 'The call: what to ask, in this order',
                'intro' => 'Understand the client before you sell. Ask first, listen, then match scharge to what they told you.',
                'items' => [
                    ['q' => 'What do I ask first?', 'a' => [
                        '**How many units do they have in total?** Across every building they manage, occupied and vacant. Write it on their CRM page.',
                        'Then how many buildings, where they are, and how many are occupied today.',
                    ]],
                    ['q' => 'What do I ask next?', 'a' => [
                        '**How they want to receive and send information.** How do tenants pay today (M-Pesa paybill, till, bank, cash)? How do bills, receipts and notices reach tenants (SMS, WhatsApp, email, paper)? How does the landlord or board get statements?',
                        'Their answers tell you what to show: payments matching themselves, bills by SMS, owner statements, the tenant app.',
                    ]],
                    ['q' => 'What else should I find out?', 'a' => [
                        'What they use now (Excel, a notebook, another system) and what annoys them about it.',
                        'Who decides and who pays the bill: the owner, the manager, the accountant or a board.',
                        'Water and electricity: do they bill sub-meters? Service charge? A gate or guards?',
                        'Their budget, if price comes up. Never guess it; ask.',
                    ]],
                    ['q' => 'When do I pitch, and what do I lead with?', 'a' => [
                        'Only after the questions. Lead with the **free tenant app and staff app**: tenants see bills, pay and get receipts, send repair requests; staff (caretakers, guards, accountants) work from their phones. Both apps are free and sit outside the client\'s fee.',
                        'Then the fee: full price for units in use, and vacant units get cheaper the longer they stay with us (see Price below).',
                    ]],
                    ['q' => 'When do I bring up white label?', 'a' => [
                        '**Last.** Never open with it. Put it on the table only after units, how they work, the free apps and the price are settled, or when they ask for their own branding.',
                    ]],
                ],
            ],
            [
                'id' => 'price',
                'title' => 'Price and billing',
                'intro' => 'Know the numbers before you dial. Work out their monthly figure from their unit count.',
                'items' => [
                    ['q' => 'What is the price?', 'a' => [
                        'Quote: **'.$price.'**.',
                        'Example: 100 units in use × KES 40 = KES 4,000 a month.',
                    ]],
                    ['q' => 'Which units are charged?', 'a' => [
                        '**Units in use pay the full price.** Vacant units get a discount that grows the longer they stay with us, counted from the day they became a client:',
                        '**First 6 months: no discount** on vacant units. **After 6 months: 25% off** vacant units. **After the first year: 50% off** vacant units.',
                        'Example at KES 40, with 80 units in use and 20 vacant: KES 4,000 a month at the start, KES 3,800 after 6 months, KES 3,600 after a year. The bill works this out by itself.',
                    ]],
                    ['q' => 'How do they pay us, and what happens if they don\'t?', 'a' => [
                        'The first bill is one month. Paying it opens **three months** of access.',
                        'When a bill passes its due date unpaid, the whole company is locked except the payment page. The owner and accountant see the amount; everyone else is told to contact their admin. Say this plainly at sign-up so it is never a surprise.',
                        'Payments go through **Sharet Pay**. Always say Sharet Pay to clients.',
                    ]],
                    ['q' => 'The client says it is too expensive or over budget. What now?', 'a' => [
                        'Do not drop the price on the spot, and do not let them cancel quietly. **Ask for their budget** for the units and how many units are actually in use.',
                        'Then rebuild the offer at the same features: show how vacant units get 25% off after 6 months and 50% off after a year, and start with one building if that fits. Book a proper demo call around that plan.',
                        'Per unit, a 20-unit block costs about KES 800 a month: less than one trip to chase late rent.',
                    ]],
                    ['q' => 'Do they have to do the setup themselves?', 'a' => [
                        'No. We import their buildings and tenants from one Excel sheet for them. They can run scharge next to their current way of working for the first month.',
                    ]],
                ],
            ],
            [
                'id' => 'white-label',
                'title' => 'White label',
                'intro' => 'The last option on the table, for clients who want scharge to look like their own system.',
                'items' => [
                    ['q' => 'What does white label mean?', 'a' => [
                        '**We give them everything in their branding.** Tenants and staff see the client\'s name, not scharge.',
                        'Their logo and colour on the dashboard, bills, receipts and the tenant app; SMS from their own sender ID; emails from their own address; their own M-Pesa paybill or till; their own payment rails.',
                    ]],
                    ['q' => 'Who is it for?', 'a' => [
                        'Larger managers and agencies who want their brand in front of tenants and landlords, and clients who already have a paybill, a sender ID or a domain they want to keep.',
                    ]],
                    ['q' => 'How is it set up?', 'a' => [
                        'On their company: Brand (logo, colour, SMS name, email address) and Integrations (their paybill or till). Each item shows its status on the White label page. The SMS sender ID needs the business verified first (ID, KRA PIN, certificate).',
                    ]],
                ],
            ],
            [
                'id' => 'product',
                'title' => 'What clients ask about scharge',
                'intro' => 'Short answers you can give on the phone.',
                'items' => [
                    ['q' => 'What does scharge do?', 'a' => [
                        'It sends rent, service charge, water and electricity bills by itself, matches M-Pesa and bank payments to the right tenant, sends receipts, and gives landlords live statements and payouts.',
                    ]],
                    ['q' => 'Do tenants need to download anything?', 'a' => [
                        'No. Every bill comes by SMS or email with a link to pay and a receipt. The free tenant app adds repair requests, notices and gate passes for those who want it.',
                    ]],
                    ['q' => 'Can they keep their own paybill?', 'a' => [
                        'Yes. Payments to their own paybill or till match themselves to tenants. Or they can collect through Sharet Pay and get payouts at 08:00, 12:00 and 16:00.',
                    ]],
                    ['q' => 'Can it bill water and electricity?', 'a' => [
                        'Yes. Caretakers enter sub-meter readings (or smart meters send them) and the charge lands on each tenant\'s bill.',
                    ]],
                    ['q' => 'How do we move their data in?', 'a' => [
                        'One Excel template: buildings, units, tenants and what each tenant owes. They fill it, we import it, and they check a preview before anything is saved.',
                    ]],
                    ['q' => 'What about their landlords?', 'a' => [
                        'Landlords get a live statement and see every payment. That usually wins them over.',
                    ]],
                    ['q' => 'Can they list vacant houses?', 'a' => [
                        'Yes. A vacant unit can go on scharge Open listings with photos, and renter enquiries come to them by SMS.',
                    ]],
                    ['q' => 'Is their data safe and private?', 'a' => [
                        'Each company sees only its own records. Staff logins are per person, with roles, and they can be removed at any time.',
                    ]],
                ],
            ],
            [
                'id' => 'objections',
                'title' => 'Objections',
                'intro' => 'What you will hear, and what to say.',
                'items' => [
                    ['q' => '"We use Excel" or "we have a system"', 'a' => [
                        'Import is done for them. Run both side by side for a month and compare the month-end.',
                    ]],
                    ['q' => '"Landlords won\'t agree"', 'a' => [
                        'Landlords get live statements and their payouts on time. Offer to show the landlord view on the demo.',
                    ]],
                    ['q' => '"We\'re too small"', 'a' => [
                        'Price is per unit in use, so a small block costs little. Small businesses matter to us as much as big ones.',
                    ]],
                    ['q' => '"Cancel my demo"', 'a' => [
                        'Reply the same day, thank them, and ask what changed. If it is price, ask for their budget and offer a call around a plan that fits (see Price).',
                    ]],
                ],
            ],
            [
                'id' => 'after',
                'title' => 'After the call',
                'intro' => 'Keep the CRM true, so the team can step in for you at any time.',
                'items' => [
                    ['q' => 'What do I record?', 'a' => [
                        'Units in total and in use, how they pay and communicate today, who decides, their budget, and what you promised. Write it on their CRM page straight after the call.',
                        'Move the stage: Demo booked, Proposal, Negotiation, Won. Always leave the next follow-up with a date.',
                    ]],
                    ['q' => 'They said yes. What next?', 'a' => [
                        'Send the Excel template. Set the stage to Won (the record becomes a customer in your book). Import their buildings, verify the business, set up how they get paid, then switch on rent automation, and white label if they took it.',
                    ]],
                    ['q' => 'They went quiet.', 'a' => [
                        'Call again in two days, then once a week. Mark it Lost only with a reason.',
                    ]],
                    ['q' => 'How am I paid?', 'a' => [
                        'A share of the per-unit fee on the clients in your book, once the client has paid. Your rate and totals are on the Sales tracker.',
                    ]],
                ],
            ],
        ];
    }
}
