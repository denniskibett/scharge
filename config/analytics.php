<?php

/*
| Website analytics. First-party tracking (our own tables, no cookies, no third parties) is on by
| default. Google Analytics 4 and the Meta Pixel are OFF unless an ID is set, and even then they
| only load after the visitor accepts the cookie notice (Kenya Data Protection Act, 2019).
*/
return [
    // First-party page views, clicks and conversions (database/schema/2026_11_09_site_analytics.sql)
    'first_party' => (bool) env('SITE_ANALYTICS', true),

    // Count signed-in users' visits? They are always flagged is_staff and hidden from the report by default.
    'record_signed_in' => (bool) env('SITE_ANALYTICS_SIGNED_IN', false),

    // Optional third-party tags, e.g. G-XXXXXXXXXX and 123456789012345. Empty = not loaded at all.
    'ga4_id' => env('GA4_MEASUREMENT_ID'),
    'meta_pixel_id' => env('META_PIXEL_ID'),

    // Google Search Console / Bing Webmaster verification tokens (meta tag content only)
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'bing_site_verification' => env('BING_SITE_VERIFICATION'),
];
