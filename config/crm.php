<?php

return [
    /*
     * Work copies: an account manager whose login is a personal address (e.g. Gmail) also gets every CRM email
     * (demo and form notices, prospect replies) at their sharet.africa mailbox. Keyed by the login email, lower case.
     */
    'work_copies' => [
        'waithakakamau44@gmail.com' => ['wkamau@sharet.africa'],
        'sidiannete@gmail.com' => ['akenga@sharet.africa'],
    ],

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
