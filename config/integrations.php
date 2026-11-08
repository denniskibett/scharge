<?php

/*
| Providers configured from Settings › Integrations instead of .env (pattern from Sharet Africa).
| Stored in `company_integrations`, credentials encrypted with APP_KEY. `scope`:
|   platform = scharge-wide, only sysadmins see it (company_id NULL);
|   company  = each customer company sets its own.
| `secret` fields are write-only: never sent back to the browser, blank keeps the saved value.
| `sandbox` is the provider's published test value, offered by "Use sandbox test values".
*/

return [
    'sharet' => [
        'name' => 'Sharet Africa',
        'scope' => 'platform',
        'category' => 'Payments',
        'description' => "scharge's own Sharet business account. Tenants pay through Sharet pay links into this wallet; landlords are paid out at the 08:00, 12:00 and 16:00 sweeps.",
        'color' => '#111827',
        'fields' => [
            'environment' => ['label' => 'Environment', 'type' => 'select', 'options' => ['test' => 'Sandbox (sk_test_)', 'live' => 'Live (sk_live_)'], 'required' => true],
            'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true, 'help' => 'Sharet › Developers › API keys. Scopes: collect, payout, connect. Live payouts need this server\'s IP on the key.'],
            'webhook_secret' => ['label' => 'Webhook signing secret', 'secret' => true, 'help' => 'The whsec_… value Sharet returns once when the webhook is registered. Use "Test connection" to register it automatically.'],
            'base_url' => ['label' => 'API base URL', 'type' => 'url', 'placeholder' => 'https://sharet.africa/api/v1', 'help' => 'Leave blank for https://sharet.africa/api/v1.'],
            'fee_percent' => ['label' => 'scharge fee on rail collections (%)', 'placeholder' => '2.5', 'help' => 'Deducted at each sweep before the landlord is paid out.'],
            'sweep_minimum' => ['label' => 'Minimum payout per sweep (KES)', 'placeholder' => '100', 'help' => 'Smaller balances roll over to the next sweep.'],
        ],
    ],

    'sharet_payouts' => [
        'name' => 'Rent collection & payouts',
        'scope' => 'company',
        'category' => 'Payments',
        'description' => 'How rent collected through scharge reaches you: swept from the scharge wallet at 08:00, 12:00 and 16:00 to your M-Pesa, paybill, till or bank, or straight into your own connected Sharet account.',
        'color' => '#DC2626',
        'fields' => [
            'mode' => ['label' => 'Collection mode', 'type' => 'select', 'required' => true, 'options' => [
                'rail' => 'Through scharge (swept to me 3× a day)',
                'connected' => 'Into my own Sharet account (connect below)',
            ]],
            'payout_type' => ['label' => 'Pay me to', 'type' => 'select', 'options' => [
                'mpesa' => 'M-Pesa number', 'paybill' => 'Paybill', 'till' => 'Till (Buy Goods)', 'bank' => 'Bank account (PesaLink)',
            ], 'help' => 'Used in "Through scharge" mode.'],
            'phone' => ['label' => 'M-Pesa number', 'placeholder' => '0712 345 678'],
            'paybill' => ['label' => 'Paybill number'],
            'account' => ['label' => 'Paybill account number'],
            'till' => ['label' => 'Till number'],
            'bank' => ['label' => 'Bank code', 'help' => 'As listed by Sharet (e.g. equity, kcb, coop).'],
            'bank_account' => ['label' => 'Bank account number'],
            'sharet_account' => ['label' => 'Connected Sharet ID', 'placeholder' => 'SH-…', 'help' => 'Filled in automatically when you connect your Sharet account.'],
        ],
    ],

    'mpesa_daraja' => [
        'name' => 'M-Pesa Daraja (your own paybill)',
        'scope' => 'company',
        'category' => 'Payments',
        'description' => 'Already have a Daraja app? Tenants are prompted on their phone and pay straight into your own paybill or till. scharge only records the payment.',
        'color' => '#00A651',
        'fields' => [
            'environment' => ['label' => 'Environment', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox (testing)', 'production' => 'Live'], 'required' => true],
            'consumer_key' => ['label' => 'Consumer key', 'secret' => true, 'required' => true, 'help' => 'From your app on developer.safaricom.co.ke.'],
            'consumer_secret' => ['label' => 'Consumer secret', 'secret' => true, 'required' => true],
            'shortcode' => ['label' => 'Paybill / store number', 'required' => true, 'sandbox' => '174379'],
            'kind' => ['label' => 'Shortcode type', 'type' => 'select', 'options' => ['paybill' => 'Paybill', 'till' => 'Till (Buy Goods)']],
            'party_b' => ['label' => 'Till number', 'help' => 'Only for a till whose number differs from the store number.'],
            'passkey' => ['label' => 'Lipa na M-Pesa passkey', 'secret' => true, 'required' => true, 'sandbox' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'],
        ],
    ],
];
