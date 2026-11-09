<?php

return [
    /*
     * Email one-time code: everyone signed in must confirm their email before using the app.
     * Switches itself on only when real mail is configured (MAIL_MAILER or Integrations › Email is not
     * "log"/"array"), so nobody is locked out while email is not working. Set EMAIL_OTP=false to turn off.
     */
    'email_otp' => [
        'enabled' => env('EMAIL_OTP', true),
        'digits' => 6,
        'expires_minutes' => 10,
        'max_attempts' => 5,
        'resend_seconds' => 60,
    ],

    /*
     * Brute-force guard (App\Services\KycLog). Every code / sign-up attempt is logged to
     * storage/logs/kyc/attempts-YYYY-MM-DD.jsonl; past these limits the network or account is refused
     * new sign-ups and code checks until the window passes. Kenyan mobile data shares IPs (CGNAT), so the
     * per-network limits are deliberately loose; the per-account one is the tight one.
     */
    'guard' => [
        'enabled' => env('KYC_GUARD', true),
        'ip_wrong_per_hour' => 30,       // wrong codes from one IP
        'ip_accounts_per_hour' => 5,     // different accounts with wrong codes from one IP
        'ip_lockouts_per_day' => 6,      // "too many wrong tries" from one IP
        'ip_signups_per_hour' => 10,     // new accounts from one IP
        'account_wrong_per_day' => 15,   // wrong codes on one account / email (stops resend-and-retry)
        'log_dir' => storage_path('logs/kyc'),
        'trusted_ips' => array_filter(explode(',', (string) env('KYC_TRUSTED_IPS', ''))),
    ],

    /* Addresses that cannot be real people's mailboxes (test domains and throwaway inboxes). */
    'blocked_email_domains' => [
        'example.com', 'example.org', 'example.net', 'test.com', 'test.co.ke', 'email.com', 'domain.com', 'mail.test',
        'mailinator.com', 'yopmail.com', 'guerrillamail.com', 'sharklasers.com', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'trashmail.com', 'dispostable.com', 'getnada.com', 'maildrop.cc', 'throwawaymail.com',
        'fakeinbox.com', 'mintemail.com', 'mohmal.com', 'emailondeck.com', 'spamgourmet.com',
    ],
    /* Top-level domains that never receive mail. */
    'blocked_email_tlds' => ['test', 'example', 'invalid', 'localhost', 'local', 'internal'],
];
