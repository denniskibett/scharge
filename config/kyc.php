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
