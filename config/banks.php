<?php

/*
 * Paying a bank account through M-Pesa: each bank's paybill, with the bank account number as the account.
 * Copied from Sharet Africa's config (config/pricing.php). CHECK each number with the bank before relying on it.
 * Bank names and logos: resources/data/banks-kenya.txt and public/images/partners (App\Support\Partners).
 */
return [
    'paybills' => [
        'equity' => '247247', 'kcb' => '522522', 'ncba' => '880100', 'imbank' => '542542',
        'coop' => '400200', 'absa' => '303030', 'stanbic' => '600100', 'family' => '222111',
    ],
];
