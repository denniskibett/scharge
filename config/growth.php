<?php

/*
| Growth hub (Sales CRM › Growth): the six Sharet-family sites that send page views and conversions to
| scharge's first-party collector (public/js/sharet-analytics.js → POST /t/x), their SEO audit and the
| Ad center. Keys are fixed: they are what each site puts in data-site="…".
*/
return [
    'sites' => [
        'sharet' => [
            'name' => 'Sharet',
            'url' => 'https://sharet.africa',
            'origins' => ['https://sharet.africa', 'https://www.sharet.africa'],
            'product' => 'Payments API for Kenya',
            'color' => '#DC2626',
        ],
        'breve' => [
            'name' => 'Breve',
            'url' => 'https://breve.sharet.africa',
            'origins' => ['https://breve.sharet.africa'],
            'product' => 'Invoicing & M-Pesa payments for SMEs',
            'color' => '#2563EB',
        ],
        'aiyotickets' => [
            'name' => 'Aiyotickets',
            'url' => 'https://aiyotickets.com',
            'origins' => ['https://aiyotickets.com', 'https://www.aiyotickets.com'],
            'product' => 'Events & tickets in Nairobi',
            'color' => '#9333EA',
        ],
        'eserviz' => [
            'name' => 'eServiz',
            'url' => 'https://eserviz.aiyotickets.com',
            'origins' => ['https://eserviz.aiyotickets.com'],
            'product' => 'Home services in Nairobi',
            'color' => '#059669',
        ],
        'scharge' => [
            'name' => 'Scharge',
            'url' => 'https://scharge.sharet.africa',
            'origins' => ['https://scharge.sharet.africa'],
            'product' => 'Property & rent management',
            'color' => '#1A1214',
        ],
        'loans' => [
            'name' => 'Sharet Loans',
            'url' => 'https://loans.sharet.africa',
            'origins' => ['https://loans.sharet.africa'],
            'product' => 'Boda boda & e-bike loans',
            'color' => '#D97706',
        ],
    ],

    // Extra origins allowed to post as a site, e.g. "scharge=http://127.0.0.1:8000,breve=http://breve.test"
    // (local testing only; leave empty on live).
    'extra_origins' => env('SHARET_ANALYTICS_EXTRA_ORIGINS', ''),

    // Conversion names the collector accepts (window.sa.push(['conversion', '<name>'])), with report labels.
    // The first seven are the cross-site contract; the rest are scharge's own forms and obvious extras.
    'conversions' => [
        'signup' => 'Sign-up',
        'lead' => 'Lead (contact / demo / quote)',
        'purchase' => 'Purchase (ticket order paid)',
        'booking' => 'Service booking',
        'loan_application' => 'Loan application',
        'api_keys' => 'API keys created',
        'invoice_created' => 'First invoice created',
        'demo' => 'Book a demo',
        'waitlist' => 'Waitlist',
        'contact' => 'Contact form',
        'quote' => 'Quote request',
        'subscribe' => 'Newsletter / alerts',
        'order' => 'Order placed',
        'event_created' => 'Event created (organiser)',
        'organiser_signup' => 'Organiser sign-up',
        'provider_signup' => 'Service provider sign-up',
        'listing_request' => 'Listing viewing / booking request',
        'listing_alert' => 'Listing SMS alert',
        'host_register' => 'BnB host sign-up',
    ],

    // Ad center thresholds: below these the hub says "not enough data" instead of guessing.
    'min_visitors' => (int) env('GROWTH_MIN_VISITORS', 100),
    'min_conversions' => (int) env('GROWTH_MIN_CONVERSIONS', 10),

    // SEO audit (php artisan growth:seo-audit): sitemap URLs sampled per site, and the HTTP timeout.
    'audit_sample' => 5,
    'audit_timeout' => 15,
];
