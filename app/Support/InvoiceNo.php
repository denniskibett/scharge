<?php

namespace App\Support;

/** The short number people see for a scharge bill (subscription_invoices): its id in 6 digits, e.g. 000004; label() adds the # (#000004). */
class InvoiceNo
{
    public static function of(int|string|null $id): string
    {
        return str_pad((string) (int) $id, 6, '0', STR_PAD_LEFT);
    }

    public static function label(int|string|null $id): string
    {
        return '#'.self::of($id);
    }
}
