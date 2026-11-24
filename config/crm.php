<?php

return [
    /*
     * Work copies: an account manager whose login is a personal address (e.g. Gmail) also gets every CRM email
     * (demo and form notices, prospect replies) at their sharet.africa mailbox. Keyed by the login email, lower case.
     */
    'work_copies' => [
        'waithakakamau44@gmail.com' => ['wkamau@sharet.africa'],
        'sidiannete@gmail.com' => ['akenga@sharet.africa'],
        'aggreyoluoch007@gmail.com' => ['aoluoch@sharet.africa'],
    ],

    /* Dated CRM tasks are emailed to their account manager as calendar invitations (Support\CalendarInvite). */
    'calendar_invites' => (bool) env('CRM_CALENDAR_INVITES', true),

    /*
     * New customers (sign-ups, deals marked Won) get an account manager at once (Support\Onboarding): this user id
     * when set, otherwise the account manager with the fewest customers.
     */
    'onboarding_owner' => env('CRM_ONBOARDING_OWNER'),

    /* Extra addresses copied on every prospect reply and on our answers, besides sysadmins and the account manager (comma-separated). */
    'reply_cc' => array_filter(array_map('trim', explode(',', (string) env('CRM_REPLY_CC', '')))),

    /*
     * CRM → Inbox: the sharet.africa mail server (same machine as scharge on live). Local certificates are for
     * mail.sharet.africa, so a localhost connection skips the name check.
     */
    'mail' => [
        'imap_host' => env('CRM_IMAP_HOST', '127.0.0.1'),
        'imap_port' => (int) env('CRM_IMAP_PORT', 993),
        'smtp_host' => env('CRM_SMTP_HOST', '127.0.0.1'),
        'smtp_port' => (int) env('CRM_SMTP_PORT', 587),
    ],
];
