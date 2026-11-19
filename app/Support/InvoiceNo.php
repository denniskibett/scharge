<?php

namespace App\Support;

/** The short number people see for a scharge bill (subscription_invoices): its id in 5 digits, e.g. 00004. */
class InvoiceNo
{
    public static function of(int|string|null $id): string
    {
        return str_pad((string) (int) $id, 5, '0', STR_PAD_LEFT);
    }
}
