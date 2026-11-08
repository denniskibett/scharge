<?php

// Defaults for the Sharet Africa integration. The API key, webhook secret, fee and minimum
// are set by a sysadmin on Settings › Integrations › Sharet Africa (company_integrations).
return [
    'base_url' => env('SHARET_BASE_URL', 'https://sharet.africa/api/v1'),

    // scharge's cut of rail-mode collections, taken at each sweep.
    'fee_percent' => (float) env('SHARET_FEE_PERCENT', 0),

    // Balances below this roll over to the next sweep.
    'sweep_minimum' => (float) env('SHARET_SWEEP_MINIMUM', 100),

    // How long a tenant pay link stays open.
    'pay_link_minutes' => (int) env('SHARET_PAY_LINK_MINUTES', 60 * 24 * 7),
];
