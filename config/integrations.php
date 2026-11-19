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
        'logo' => 'sharet.svg', // public/images/integrations
        'fields' => [
            'environment' => ['label' => 'Environment', 'type' => 'select', 'options' => ['test' => 'Sandbox (sk_test_)', 'live' => 'Live (sk_live_)'], 'required' => true],
            'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true, 'help' => 'Sharet › Developers › API keys. Scopes: collect, payout, connect. Live payouts need this server\'s IP on the key.'],
            'webhook_secret' => ['label' => 'Webhook signing secret', 'secret' => true, 'help' => 'The whsec_… value Sharet returns once when the webhook is registered. Use "Test connection" to register it automatically.'],
            'base_url' => ['label' => 'API base URL', 'type' => 'url', 'placeholder' => 'https://sharet.africa/api/v1', 'help' => 'Leave blank for https://sharet.africa/api/v1.'],
            'fee_percent' => ['label' => 'scharge fee on rail collections (%)', 'placeholder' => '2.5', 'help' => 'Deducted at each sweep before the landlord is paid out.'],
            'sweep_minimum' => ['label' => 'Minimum payout per sweep (KES)', 'placeholder' => '100', 'help' => 'Smaller balances roll over to the next sweep.'],
        ],
    ],

    'mpesa' => [
        'name' => 'M-Pesa Daraja (scharge)',
        'scope' => 'platform',
        'category' => 'Payments',
        'description' => "scharge's own Safaricom Daraja app: STK prompts for companies without their own app, and subscription payments. Overrides the MPESA_* values in .env when switched on.",
        'color' => '#00A651',
        'logo' => 'mpesa.svg', // public/images/integrations
        'fields' => [
            'environment' => ['label' => 'Environment', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox (testing)', 'production' => 'Live'], 'required' => true],
            'consumer_key' => ['label' => 'Consumer key', 'secret' => true, 'required' => true, 'help' => 'From your app on developer.safaricom.co.ke.'],
            'consumer_secret' => ['label' => 'Consumer secret', 'secret' => true, 'required' => true],
            'shortcode' => ['label' => 'Paybill / store number', 'required' => true, 'sandbox' => '174379'],
            'kind' => ['label' => 'Shortcode type', 'type' => 'select', 'options' => ['paybill' => 'Paybill', 'till' => 'Till (Buy Goods)']],
            'party_b' => ['label' => 'Till number', 'help' => 'Only for a till whose number differs from the store number.'],
            'passkey' => ['label' => 'Lipa na M-Pesa passkey', 'secret' => true, 'required' => true, 'sandbox' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'],
            'initiator_name' => ['label' => 'Initiator name', 'help' => 'Only for B2C/B2B payouts.', 'sandbox' => 'testapi'],
            'security_credential' => ['label' => 'Security credential', 'secret' => true, 'help' => 'Only for B2C/B2B payouts.'],
        ],
    ],

    'airtel_money' => [
        'name' => 'Airtel Money',
        'scope' => 'platform',
        'category' => 'Payments',
        'description' => 'Airtel Africa Open API credentials for Airtel Money collections and disbursements. Saved and tested here; Airtel payments are taken through Sharet Africa until a direct Airtel flow is switched on.',
        'color' => '#E40000',
        'logo' => 'airtel-money.svg', // public/images/integrations
        'fields' => [
            'environment' => ['label' => 'Environment', 'type' => 'select', 'options' => ['staging' => 'Staging (testing)', 'production' => 'Live'], 'required' => true],
            'client_id' => ['label' => 'Client ID', 'secret' => true, 'required' => true, 'help' => 'From developers.airtel.africa (your app).'],
            'client_secret' => ['label' => 'Client secret', 'secret' => true, 'required' => true],
            'country' => ['label' => 'Country', 'placeholder' => 'KE', 'sandbox' => 'KE'],
            'currency' => ['label' => 'Currency', 'placeholder' => 'KES', 'sandbox' => 'KES'],
            'disbursement_pin' => ['label' => 'Disbursement PIN', 'secret' => true, 'help' => 'Only for payouts.'],
        ],
    ],

    'kenyasms' => [
        'name' => 'KenyaSMS',
        'scope' => 'platform',
        'category' => 'Messaging',
        'description' => 'Bulk and transactional SMS: bills, reminders, receipts and campaigns. Overrides the KENYASMS_* values in .env when switched on.',
        'color' => '#1D4ED8',
        'logo' => 'kenyasms.png', // public/images/integrations
        'fields' => [
            'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true],
            'sender_id' => ['label' => 'Sender ID', 'required' => true, 'placeholder' => 'SHARETENT', 'help' => 'The approved name tenants see.'],
            'base_url' => ['label' => 'API base URL', 'type' => 'url', 'placeholder' => 'https://kenyasms.com/api/v1'],
            'mode' => ['label' => 'Mode', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox (no real SMS)', 'live' => 'Live'], 'required' => true],
        ],
    ],

    'whatsapp' => [
        'name' => 'WhatsApp Business',
        'scope' => 'platform',
        'category' => 'Messaging',
        'description' => "scharge's WhatsApp number (Meta Cloud API). Reaches tenants on international numbers, which SMS cannot, and anyone you choose to message on WhatsApp.",
        'color' => '#25D366',
        'logo' => 'whatsapp.svg', // public/images/integrations
        'fields' => [
            'mode' => ['label' => 'Use', 'type' => 'select', 'required' => true, 'options' => ['cloud' => 'Send through the WhatsApp Cloud API', 'links' => 'Share links only (wa.me, no sending)']],
            'phone_number_id' => ['label' => 'Phone number ID', 'help' => 'Meta Business › WhatsApp Manager › API setup (the ID of the sending number, not the number itself).'],
            'access_token' => ['label' => 'Access token', 'secret' => true, 'help' => 'A permanent system-user token with whatsapp_business_messaging.'],
            'business_account_id' => ['label' => 'WhatsApp Business account ID', 'help' => 'Optional; shown for reference.'],
            'template' => ['label' => 'Message template', 'placeholder' => 'scharge_notice', 'help' => 'An approved template with one body variable {{1}}. Needed to message people who have not written to you in the last 24 hours.'],
            'language' => ['label' => 'Template language', 'placeholder' => 'en', 'help' => 'Language code of the template, e.g. en or en_US.'],
            'api_version' => ['label' => 'Graph API version', 'placeholder' => 'v21.0'],
        ],
    ],

    'whatsapp_business' => [
        'name' => 'WhatsApp (your number)',
        'scope' => 'company',
        'category' => 'Messaging',
        'description' => "Bills and reminders go out on WhatsApp from your own business number and name, not scharge's. We can set the number up for you inside your plan, or you connect your own Meta app. Tenants with international numbers get WhatsApp instead of SMS.",
        'color' => '#25D366',
        'logo' => 'whatsapp.svg', // public/images/integrations
        'fields' => [
            'mode' => ['label' => 'Use', 'type' => 'select', 'required' => true, 'options' => ['managed' => 'scharge sets up my WhatsApp Business number (included in my plan)', 'cloud' => 'My own Meta app (WhatsApp Cloud API)', 'links' => 'Share links only (wa.me, no sending)']],
            'display_name' => ['label' => 'Name tenants see', 'placeholder' => 'Greenview Apartments', 'show_if' => ['mode' => ['managed']], 'help' => 'Your business name as it should show in WhatsApp. Meta checks it matches your business.'],
            'number_to_use' => ['label' => 'Phone number for WhatsApp', 'placeholder' => '0712 345 678', 'show_if' => ['mode' => ['managed']], 'help' => 'A number that is NOT already on the WhatsApp app (or that you will move off it). We send it a code once to verify.'],
            'phone_number_id' => ['label' => 'Phone number ID', 'show_if' => ['mode' => ['cloud', 'managed']], 'help' => 'Meta Business › WhatsApp Manager › API setup (the ID of the sending number). With scharge set-up, we fill this in.'],
            'access_token' => ['label' => 'Access token', 'secret' => true, 'show_if' => ['mode' => ['cloud']], 'help' => 'A permanent system-user token with whatsapp_business_messaging.'],
            'approval' => ['label' => 'Set-up status', 'type' => 'select', 'admin_only' => true, 'show_if' => ['mode' => ['managed']], 'options' => ['requested' => 'Requested', 'verifying' => 'Verifying with Meta', 'approved' => 'Live', 'rejected' => 'Rejected'], 'help' => 'scharge staff only. Messages go from this number once this says Live.'],
            'business_account_id' => ['label' => 'WhatsApp Business account ID', 'help' => 'Optional; shown for reference.'],
            'template' => ['label' => 'Message template', 'placeholder' => 'scharge_notice', 'help' => 'An approved template with one body variable {{1}}. Needed to message people who have not written to you in the last 24 hours.'],
            'language' => ['label' => 'Template language', 'placeholder' => 'en', 'help' => 'Language code of the template, e.g. en or en_US.'],
            'api_version' => ['label' => 'Graph API version', 'placeholder' => 'v21.0'],
        ],
    ],

    'rent_automation' => [
        'name' => 'Rent automation',
        'scope' => 'company',
        'category' => 'Automation',
        'description' => 'Bill every active tenancy each month (rent, service charge, garbage, security and water) and remind tenants who are late. Switch it on to start; "Test" shows what the next run would bill without sending anything.',
        'color' => '#0F766E',
        'fields' => [
            'invoice_day' => ['label' => 'Bill on day of the month', 'type' => 'select', 'required' => true, 'options' => array_combine(array_map('strval', range(1, 28)), array_map('strval', range(1, 28))), 'help' => 'Invoices are created on this day (or the first run after it). A month is never billed twice.'],
            'due_day' => ['label' => 'Rent due on day', 'type' => 'select', 'required' => true, 'options' => array_combine(array_map('strval', range(1, 28)), array_map('strval', range(1, 28))), 'help' => 'Most landlords use the 5th. Reminders count the days late from this day.'],
            'send_bill' => ['label' => 'Send the bill to tenants', 'type' => 'select', 'required' => true, 'options' => ['yes' => 'Yes, by SMS with a pay link', 'no' => 'No, only create the invoices']],
            'reminder_days' => ['label' => 'Remind when this many days late', 'placeholder' => '1, 7, 14', 'help' => 'Comma-separated, up to 6. Leave blank for 1, 7, 14. Write 0 to remind on the due day. One message per tenant per stage, with the total owed.'],
            'channel' => ['label' => 'Send reminders by', 'type' => 'select', 'required' => true, 'options' => ['sms' => 'SMS', 'whatsapp' => 'WhatsApp (your number, else SMS)']],
            'penalty' => ['label' => 'Late payment penalty', 'type' => 'select', 'required' => true, 'options' => ['off' => 'Off', 'percent' => 'A percentage of the unpaid amount', 'fixed' => 'A fixed amount (KES)'], 'help' => 'Your choice: scharge never charges a penalty unless you switch it on here. Added once per bill, and never for a tenant whose reminders you paused.'],
            'penalty_amount' => ['label' => 'Penalty (percent or KES)', 'placeholder' => '5', 'help' => 'E.g. 5 for 5%, or 500 for KES 500.'],
            'penalty_after_days' => ['label' => 'Charge the penalty when this many days late', 'placeholder' => '5'],
        ],
    ],

    'data_backup' => [
        'name' => 'Nightly data copy',
        'scope' => 'company',
        'category' => 'Your data',
        'description' => 'Every night at 02:30 scharge puts a full copy of your data (all properties, tenants, bills and payments, as spreadsheets) on storage you control: your own SFTP server or an S3-compatible bucket (Amazon S3, Google Cloud Storage, Wasabi, Backblaze, DigitalOcean Spaces). Test writes and deletes a small file.',
        'color' => '#0F172A',
        'fields' => [
            'method' => ['label' => 'Send copies to', 'type' => 'select', 'required' => true, 'options' => ['sftp' => 'SFTP server', 's3' => 'S3-compatible bucket']],
            'path' => ['label' => 'Folder', 'placeholder' => 'scharge-backups', 'help' => 'Created if missing.'],
            'keep_days' => ['label' => 'Keep copies for (days)', 'placeholder' => '30'],
            'host' => ['label' => 'SFTP host', 'placeholder' => 'backup.example.co.ke', 'help' => 'SFTP only.'],
            'port' => ['label' => 'SFTP port', 'placeholder' => '22'],
            'username' => ['label' => 'SFTP username'],
            'password' => ['label' => 'SFTP password', 'secret' => true],
            's3_bucket' => ['label' => 'Bucket', 'help' => 'S3 only.'],
            's3_region' => ['label' => 'Region', 'placeholder' => 'us-east-1'],
            's3_endpoint' => ['label' => 'Endpoint (not Amazon)', 'type' => 'url', 'placeholder' => 'https://storage.googleapis.com', 'help' => 'Leave blank for Amazon S3.'],
            's3_key' => ['label' => 'Access key', 'secret' => true],
            's3_secret' => ['label' => 'Secret key', 'secret' => true],
        ],
    ],

    'mobile_app' => [
        'name' => 'scharge app',
        'scope' => 'platform',
        'category' => 'App',
        'description' => 'Push notifications for the scharge app (Firebase Cloud Messaging, free per message): bills, reminders and receipts go to the phone first, SMS only when the app is not installed. Also the oldest app version still allowed.',
        'color' => '#FFA000',
        'fields' => [
            'firebase_service_account' => ['label' => 'Firebase service account (JSON)', 'secret' => true, 'help' => 'Firebase console › Project settings › Service accounts › Generate new private key. Paste the whole file content on one line.'],
            'min_version' => ['label' => 'Oldest app version allowed', 'placeholder' => '1.0.0', 'help' => 'Older apps are asked to update. Leave blank to allow all.'],
        ],
    ],

    'document_reader' => [
        'name' => 'Document reading (KYC)',
        'scope' => 'platform',
        'category' => 'Verification',
        'description' => 'Reads uploaded IDs, KRA PIN certificates, business registrations, CR12s and title deeds with Claude (Anthropic), fills in the details and flags mismatches for review. Documents are sent to Anthropic\'s API only while this is on.',
        'color' => '#D97757',
        'fields' => [
            'api_key' => ['label' => 'Anthropic API key', 'secret' => true, 'required' => true, 'help' => 'From console.anthropic.com › API keys.'],
        ],
    ],

    'channel_sync' => [
        'name' => 'Channel sync (Airbnb, Booking.com)',
        'scope' => 'platform',
        'category' => 'Bookings',
        'description' => 'Two-way calendar sync for short-stay units: stays booked on Airbnb, Booking.com or Vrbo appear on scharge, and nights booked on scharge are blocked on those channels, so a unit is never booked twice. Built and ready; switch it on when we launch it. Until then hosts see "Coming soon" and nothing is fetched.',
        'color' => '#FF5A5F',
        'fields' => [
            'note' => ['label' => 'Note', 'placeholder' => 'Launched to all hosts', 'help' => 'Optional. Switching this on starts the 30-minute calendar sync for every unit with a channel link.'],
        ],
    ],

    'africastalking' => [
        'name' => "Africa's Talking",
        'scope' => 'platform',
        'category' => 'Messaging',
        'description' => "Backup SMS gateway (and USSD/airtime later). Saved and tested here; KenyaSMS stays the sender unless it is turned off.",
        'color' => '#F97316',
        'fields' => [
            'username' => ['label' => 'Username', 'required' => true, 'sandbox' => 'sandbox'],
            'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true],
            'sender_id' => ['label' => 'Sender ID / short code'],
        ],
    ],

    'smtp' => [
        'name' => 'Email (SMTP)',
        'scope' => 'platform',
        'category' => 'Messaging',
        'description' => 'Outgoing email: invoices, receipts, password resets, CRM email. Replaces the MAIL_* values in .env (currently "log", so no email is sent) when switched on.',
        'color' => '#7C3AED',
        'logo' => 'email.svg', // public/images/integrations
        'fields' => [
            'host' => ['label' => 'SMTP host', 'required' => true, 'placeholder' => 'smtp.example.com'],
            'port' => ['label' => 'Port', 'required' => true, 'placeholder' => '587'],
            'encryption' => ['label' => 'Encryption', 'type' => 'select', 'options' => ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None']],
            'username' => ['label' => 'Username'],
            'password' => ['label' => 'Password', 'secret' => true],
            'from_address' => ['label' => 'From address', 'type' => 'email', 'required' => true, 'placeholder' => 'hello@scharge.com'],
            'from_name' => ['label' => 'From name', 'placeholder' => 'scharge'],
        ],
    ],

    'kenyasms_sender' => [
        'name' => 'SMS sender ID',
        'scope' => 'company',
        'category' => 'Messaging',
        'description' => 'Bills, reminders and receipts reach your tenants under your own name, not scharge\'s. We register the name for you and it is included in your plan, so your tenants only ever see your brand.',
        'color' => '#1D4ED8',
        'logo' => 'kenyasms.png', // public/images/integrations
        'fields' => [
            'setup' => ['label' => 'Who sets it up', 'type' => 'select', 'required' => true, 'options' => ['managed' => 'scharge registers it for me (included in my plan)', 'own' => 'I have my own KenyaSMS account'], 'help' => 'With scharge, we apply for the name with the mobile networks for you (about 5 to 10 working days) and your SMS are part of your per-unit plan. Nothing to sign up for.'],
            'sender_id' => ['label' => 'Sender ID', 'required' => true, 'placeholder' => 'MYAPARTMNT', 'help' => 'Up to 11 letters or numbers: usually your agency or building name. Until it is approved, your tenants keep getting messages from scharge\'s name.'],
            'api_key' => ['label' => 'Your own KenyaSMS API key', 'secret' => true, 'show_if' => ['setup' => ['own']], 'help' => 'You pay for your SMS on your KenyaSMS account.'],
            'approval' => ['label' => 'Registration status', 'type' => 'select', 'admin_only' => true, 'options' => ['requested' => 'Requested', 'submitted' => 'Submitted to networks', 'approved' => 'Approved', 'rejected' => 'Rejected'], 'help' => 'scharge staff only. Messages use the name once this says Approved.'],
        ],
    ],

    'absa_bank' => [
        'name' => 'Absa Bank',
        'scope' => 'company',
        'category' => 'Banking',
        'description' => 'Your Absa Bank Kenya account. Choose "My Absa Bank account" under Rent collection & payouts and each sweep (08:00, 12:00, 16:00) pays straight into it.',
        'color' => '#AF144B',
        'logo' => 'absa.svg', // public/images/integrations
        'fields' => [
            'account_name' => ['label' => 'Account name', 'required' => true, 'placeholder' => 'As on your Absa statement'],
            'account_number' => ['label' => 'Account number', 'required' => true, 'placeholder' => '2041234567'],
            'branch' => ['label' => 'Branch', 'placeholder' => 'e.g. Absa Plaza, Loita Street'],
        ],
    ],

    'sharet_payouts' => [
        'name' => 'Rent collection & payouts',
        'scope' => 'company',
        'category' => 'Payments',
        'description' => 'How rent collected through scharge reaches you: swept from the scharge wallet at 08:00, 12:00 and 16:00 to your M-Pesa, paybill, till or bank, or straight into your own connected Sharet account.',
        'color' => '#DC2626',
        'logo' => 'sharet.svg', // public/images/integrations
        'fields' => [
            'mode' => ['label' => 'Collection mode', 'type' => 'select', 'required' => true, 'options' => [
                'rail' => 'Through scharge (swept to me 3× a day)',
                'connected' => 'Into my own Sharet account (connect below)',
            ]],
            'payout_type' => ['label' => 'Pay me to', 'type' => 'select', 'options' => [
                'mpesa' => 'M-Pesa number', 'paybill' => 'Paybill', 'till' => 'Till (Buy Goods)', 'bank' => 'Bank account (PesaLink)', 'absa' => 'My Absa Bank account (Absa Bank card)',
            ], 'help' => 'Used in "Through scharge" mode.'],
            'phone' => ['label' => 'M-Pesa number', 'placeholder' => '0712 345 678'],
            'paybill' => ['label' => 'Paybill number'],
            'account' => ['label' => 'Paybill account number'],
            'till' => ['label' => 'Till number'],
            'bank' => ['label' => 'Bank', 'type' => 'select', 'options_from' => 'banks', 'help' => 'Kenyan banks licensed by the CBK (resources/data/banks-kenya.txt). Paid by PesaLink.'],
            'bank_account' => ['label' => 'Bank account number'],
            'sharet_account' => ['label' => 'Connected Sharet ID', 'placeholder' => 'SH-…', 'help' => 'Filled in automatically when you connect your Sharet account.'],
        ],
    ],

    'mpesa_daraja' => [
        'name' => 'M-Pesa Daraja (your own paybill)',
        'scope' => 'company',
        'category' => 'Payments',
        'description' => 'Already have a Daraja app? Tenants are prompted on their phone and pay straight into your own paybill or till. scharge only records the payment. "Test" also connects your paybill, so rent paid straight to it (with the unit as the account number) is recorded automatically.',
        'color' => '#00A651',
        'logo' => 'mpesa.svg', // public/images/integrations
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

    // Shown to tenants in the app's Pay screen (docs/MOBILE_APP.md). Standing-order payments arrive at the bank, not
    // through scharge: the landlord records them (or they come in through the bank integration when it is connected).
    'bank_standing_order' => [
        'name' => 'Bank standing order (for tenants)',
        'scope' => 'company',
        'category' => 'Payments',
        'description' => 'Your bank account for tenants who pay rent by standing order. The scharge app shows these details on the Pay screen, with the unit as the reference.',
        'color' => '#0F766E',
        'logo' => null,
        'fields' => [
            'bank' => ['label' => 'Bank', 'type' => 'select', 'options_from' => 'banks', 'required' => true],
            'account_name' => ['label' => 'Account name', 'required' => true],
            'account_number' => ['label' => 'Account number', 'required' => true],
            'branch' => ['label' => 'Branch'],
            'note' => ['label' => 'Note for tenants', 'placeholder' => 'Set it for the 1st of each month.'],
        ],
    ],

    // ── More integrations (docs/INTEGRATIONS.md) ─────────────────────────────────────────────────────────────

    'webhooks' => [
        'name' => 'Webhooks',
        'scope' => 'company',
        'category' => 'Developers',
        'description' => 'scharge tells your own systems (or Zapier, Make, n8n) the moment rent is paid, a bill is issued or paid, a tenant moves in or out, or a booking comes in: a signed JSON POST to your URL, retried for 15 hours if it fails. Test sends a signed "ping".',
        'color' => '#7C3AED',
        'fields' => [
            'url' => ['label' => 'Your endpoint (https)', 'type' => 'url', 'required' => true, 'placeholder' => 'https://hooks.zapier.com/hooks/catch/…'],
            'secret' => ['label' => 'Signing secret', 'secret' => true, 'required' => true, 'help' => 'Any long random text. Put the same text in your receiver and check the X-Scharge-Signature header: t=<time>,v1=HMAC-SHA256(secret, "<time>.<body>").'],
            'events' => ['label' => 'Events', 'placeholder' => 'payment.received, invoice.paid', 'help' => 'Comma-separated; blank sends all: payment.received, invoice.issued, invoice.paid, tenancy.started, tenancy.ended, booking.created.'],
        ],
    ],

    'company_email' => [
        'name' => 'Email from your address',
        'scope' => 'company',
        'category' => 'Messaging',
        'description' => 'Bills, receipts and gate passes are emailed to your tenants from your own address (e.g. accounts@youragency.co.ke) through your mail provider, so replies come to you. Test signs in and sends you a test email.',
        'color' => '#DC2626',
        'fields' => [
            'provider' => ['label' => 'Mail provider', 'type' => 'select', 'required' => true, 'options' => ['google' => 'Google Workspace / Gmail', 'microsoft' => 'Microsoft 365 / Outlook', 'zoho' => 'Zoho Mail', 'mailgun' => 'Mailgun (API key)', 'smtp' => 'Other mail server (SMTP)']],
            'from_address' => ['label' => 'Send from', 'required' => true, 'placeholder' => 'accounts@youragency.co.ke'],
            'from_name' => ['label' => 'Sender name', 'placeholder' => 'Greenview Apartments'],
            'reply_to' => ['label' => 'Replies go to', 'placeholder' => 'Leave blank for the address above'],
            'host' => ['label' => 'SMTP server', 'placeholder' => 'mail.youragency.co.ke', 'show_if' => ['provider' => ['smtp']]],
            'port' => ['label' => 'Port', 'placeholder' => '587', 'show_if' => ['provider' => ['smtp']]],
            'encryption' => ['label' => 'Encryption', 'type' => 'select', 'show_if' => ['provider' => ['smtp']], 'options' => ['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)']],
            'username' => ['label' => 'Username', 'placeholder' => 'Usually the full email address', 'show_if' => ['provider' => ['smtp', 'google', 'microsoft', 'zoho']]],
            'password' => ['label' => 'Password or app password', 'secret' => true, 'show_if' => ['provider' => ['smtp', 'google', 'microsoft', 'zoho']], 'help' => 'Google and Microsoft accounts with 2-step sign-in need an app password, not your normal one.'],
            'mailgun_domain' => ['label' => 'Mailgun domain', 'placeholder' => 'mg.youragency.co.ke', 'show_if' => ['provider' => ['mailgun']]],
            'mailgun_key' => ['label' => 'Mailgun API key', 'secret' => true, 'show_if' => ['provider' => ['mailgun']]],
            'mailgun_region' => ['label' => 'Mailgun region', 'type' => 'select', 'show_if' => ['provider' => ['mailgun']], 'options' => ['us' => 'US (api.mailgun.net)', 'eu' => 'EU (api.eu.mailgun.net)']],
        ],
    ],

    'data_feeds' => [
        'name' => 'Google Sheets & Calendar feeds',
        'scope' => 'company',
        'category' => 'Your data',
        'description' => 'Private live links: paste a CSV link into Google Sheets (=IMPORTDATA) or Excel (Data › From web) for payments, bills and arrears, or tenants, always up to date. The calendar link adds bookings, move-ins and move-outs to Google Calendar, Outlook or your phone. Save to get the links.',
        'color' => '#0F9D58',
        'fields' => [
            'months' => ['label' => 'How far back', 'type' => 'select', 'required' => true, 'options' => ['3' => 'Last 3 months', '12' => 'Last 12 months', '36' => 'Last 3 years']],
            'reset' => ['label' => 'Make new links', 'type' => 'select', 'options' => ['no' => 'No, keep the links', 'yes' => 'Yes: the old links stop working'], 'help' => 'Anyone with a link can read that list, so make new ones if a link was shared by mistake.'],
        ],
    ],

    'bank_import' => [
        'name' => 'Bank statement import',
        'scope' => 'company',
        'category' => 'Banking',
        'description' => 'Tenants who pay straight into your bank account (Equity, KCB, Co-op, Absa, NCBA, Stanbic, I&M, DTB, Family…): upload the statement as CSV or Excel and scharge matches each deposit to the tenant\'s bill by reference, unit and amount. You confirm, then receipts go out.',
        'color' => '#0E7490',
        'link' => ['label' => 'Upload a statement', 'route' => 'bank-import.index'],
        'fields' => [
            'bank' => ['label' => 'Your bank', 'type' => 'select', 'options_from' => 'banks'],
            'auto_post' => ['label' => 'Sure matches', 'type' => 'select', 'required' => true, 'options' => ['review' => 'Let me confirm each one', 'post' => 'Record them at once (bill number or unit + exact amount)']],
        ],
    ],

    'jenga' => [
        'name' => 'Equity Bank (Jenga API)',
        'scope' => 'company',
        'category' => 'Banking',
        'description' => 'Connect your Equity account through Jenga: every morning scharge reads the deposits into your account and matches them to tenants\' bills, with no statement to download. Test signs in and reads your balance.',
        'color' => '#A32A29',
        'fields' => [
            'environment' => ['label' => 'Environment', 'type' => 'select', 'required' => true, 'options' => ['sandbox' => 'Sandbox (testing)', 'production' => 'Live']],
            'merchant_code' => ['label' => 'Merchant code', 'required' => true, 'help' => 'From jengahq.io › Settings › API keys.'],
            'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true],
            'consumer_secret' => ['label' => 'Consumer secret', 'secret' => true, 'required' => true],
            'account_number' => ['label' => 'Equity account number', 'required' => true],
            'private_key' => ['label' => 'Private key (PEM)', 'type' => 'textarea', 'secret' => true, 'help' => 'The RSA private key whose public half you uploaded to JengaHQ. Needed to read balances and statements.'],
        ],
    ],

    'etims' => [
        'name' => 'KRA eTIMS',
        'scope' => 'company',
        'category' => 'Tax',
        'description' => 'Every rent or stay bill is sent to KRA through eTIMS (OSCU) and gets a KRA receipt number and QR code on the invoice, so corporate tenants can claim it. Test registers your device with KRA and checks the connection.',
        'color' => '#B91C1C',
        'fields' => [
            'environment' => ['label' => 'Environment', 'type' => 'select', 'required' => true, 'options' => ['sandbox' => 'KRA sandbox (testing)', 'production' => 'Live']],
            'tin' => ['label' => 'KRA PIN', 'required' => true, 'placeholder' => 'P051234567X'],
            'bhf_id' => ['label' => 'Branch ID', 'placeholder' => '00', 'help' => '00 for the head office.'],
            'device_serial' => ['label' => 'Device serial number', 'required' => true, 'help' => 'The OSCU serial KRA gave you when you applied for eTIMS (iTax › eTIMS › Online Sales Control Unit).'],
            'when' => ['label' => 'Send to KRA when', 'type' => 'select', 'required' => true, 'options' => ['paid' => 'The bill is fully paid', 'issued' => 'The bill is issued']],
            'tax_type' => ['label' => 'VAT on rent', 'type' => 'select', 'required' => true, 'options' => ['A' => 'Exempt (residential rent)', 'B' => 'VAT 16% (commercial, VAT-registered)', 'C' => 'Zero-rated', 'D' => 'Not VAT-registered', 'E' => 'VAT 8%']],
            'item_class' => ['label' => 'KRA item class code', 'placeholder' => '80131500', 'help' => 'Leave blank for 80131500 (renting of property).'],
        ],
    ],

    'quickbooks' => [
        'name' => 'QuickBooks Online',
        'scope' => 'company',
        'category' => 'Accounting',
        'description' => 'Each tenant becomes a customer, each bill an invoice and each payment a payment against it in QuickBooks, as they happen. No double entry. Connect, then Test shows your QuickBooks company.',
        'color' => '#2CA01C',
        'oauth' => true,
        'fields' => [
            'item_name' => ['label' => 'Product/Service for rent', 'placeholder' => 'Rent', 'help' => 'A Service item in QuickBooks (Sales › Products and services). Its income account is where rent is booked.'],
            'deposit_account' => ['label' => 'Deposit payments to (account ID)', 'placeholder' => 'Blank = Undeposited Funds'],
            'from_date' => ['label' => 'Copy records from', 'placeholder' => 'YYYY-MM-DD', 'help' => 'Blank: only new bills and payments from now on.'],
        ],
    ],

    'xero' => [
        'name' => 'Xero',
        'scope' => 'company',
        'category' => 'Accounting',
        'description' => 'Each tenant becomes a contact, each bill a sales invoice and each payment a payment in Xero, as they happen. Connect, then Test shows your Xero organisation.',
        'color' => '#13B5EA',
        'oauth' => true,
        'fields' => [
            'income_account' => ['label' => 'Income account code', 'placeholder' => '200', 'help' => 'Where rent is booked (Accounting › Chart of accounts).'],
            'bank_account' => ['label' => 'Bank account code for payments', 'placeholder' => '090', 'help' => 'Must be a bank account in Xero with payments enabled.'],
            'from_date' => ['label' => 'Copy records from', 'placeholder' => 'YYYY-MM-DD', 'help' => 'Blank: only new bills and payments from now on.'],
        ],
    ],

    'zoho_books' => [
        'name' => 'Zoho Books',
        'scope' => 'company',
        'category' => 'Accounting',
        'description' => 'Each tenant becomes a customer, each bill an invoice and each payment a customer payment in Zoho Books, as they happen. Connect, then Test shows your Zoho organisation.',
        'color' => '#E42527',
        'oauth' => true,
        'fields' => [
            'item_name' => ['label' => 'Line name for rent', 'placeholder' => 'Rent'],
            'deposit_account' => ['label' => 'Deposit to (account ID)', 'placeholder' => 'Blank = Undeposited Funds'],
            'from_date' => ['label' => 'Copy records from', 'placeholder' => 'YYYY-MM-DD', 'help' => 'Blank: only new bills and payments from now on.'],
        ],
    ],

    'smart_meters' => [
        'name' => 'Smart meters & IoT',
        'scope' => 'company',
        'category' => 'Meters',
        'description' => 'Smart water and power meters (or a meter vendor\'s cloud) send readings straight to scharge, which bills them with the rent. Each meter gets its own device token on the Meters page; a vendor cloud can post for all your meters by serial number with the gateway key here. Test shows which meters reported in the last day.',
        'color' => '#0369A1',
        'fields' => [
            'vendor' => ['label' => 'Meter make / vendor', 'placeholder' => 'Stron, Hexing, Calin, Kamstrup…', 'help' => 'For our records and support.'],
            'gateway_key' => ['label' => 'Gateway key', 'secret' => true, 'help' => 'Leave blank and save to get one (shown once in the Test result). The vendor sends it as "Authorization: Bearer …" to POST /api/v1/meter-readings with {serial, reading, read_at}.'],
        ],
    ],

    // scharge's own developer apps for the accounting connections above (registered once by scharge; companies just click Connect).
    'quickbooks_app' => [
        'name' => 'QuickBooks app (scharge)',
        'scope' => 'platform',
        'category' => 'Accounting',
        'description' => 'scharge\'s Intuit developer app, used when a company clicks Connect on its QuickBooks card. Redirect URI to register: /integrations/oauth/quickbooks/callback.',
        'color' => '#2CA01C',
        'fields' => [
            'environment' => ['label' => 'Environment', 'type' => 'select', 'required' => true, 'options' => ['sandbox' => 'Sandbox (development keys)', 'production' => 'Live (production keys)']],
            'client_id' => ['label' => 'Client ID', 'required' => true],
            'client_secret' => ['label' => 'Client secret', 'secret' => true, 'required' => true],
        ],
    ],
    'xero_app' => [
        'name' => 'Xero app (scharge)',
        'scope' => 'platform',
        'category' => 'Accounting',
        'description' => 'scharge\'s Xero developer app (Web app), used when a company clicks Connect on its Xero card. Redirect URI to register: /integrations/oauth/xero/callback.',
        'color' => '#13B5EA',
        'fields' => [
            'client_id' => ['label' => 'Client ID', 'required' => true],
            'client_secret' => ['label' => 'Client secret', 'secret' => true, 'required' => true],
            'scopes' => ['label' => 'Scopes', 'placeholder' => 'offline_access accounting.transactions accounting.contacts accounting.settings.read', 'help' => 'Leave blank for the default. Apps created after March 2026 may need the newer granular scopes (accounting.invoices accounting.payments accounting.contacts).'],
        ],
    ],
    'zoho_app' => [
        'name' => 'Zoho Books app (scharge)',
        'scope' => 'platform',
        'category' => 'Accounting',
        'description' => 'scharge\'s Zoho API console client (Server-based app), used when a company clicks Connect on its Zoho Books card. Redirect URI to register: /integrations/oauth/zoho_books/callback.',
        'color' => '#E42527',
        'fields' => [
            'client_id' => ['label' => 'Client ID', 'required' => true],
            'client_secret' => ['label' => 'Client secret', 'secret' => true, 'required' => true],
            'region' => ['label' => 'Zoho data centre', 'type' => 'select', 'required' => true, 'options' => ['com' => 'Global (zoho.com)', 'eu' => 'Europe (zoho.eu)', 'in' => 'India (zoho.in)', 'com.au' => 'Australia (zoho.com.au)']],
        ],
    ],
];
