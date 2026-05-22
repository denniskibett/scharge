<?php

namespace App\Modules\SMS\Helpers;

class PhoneHelper
{
    /**
     * Normalize a Kenyan phone number to 2547XXXXXXXX format.
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-digit characters
        $cleaned = preg_replace('/\D/', '', $phone);

        // Case 1: 0712345678 (10 digits, starts with 0)
        if (preg_match('/^0([7-9][0-9]{8})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        // Case 2: 254712345678 (12 digits, already has country code)
        if (preg_match('/^254([7-9][0-9]{8})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        // Case 3: 712345678 (9 digits)
        if (preg_match('/^([7-9][0-9]{8})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        return null;
    }

    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return false;
        }
        return preg_match('/^2547[0-9]{8}$/', $normalized) === 1;
    }

    public static function clean(?string $phone): ?string
    {
        $normalized = self::normalize($phone);
        if ($normalized && self::isValid($normalized)) {
            return $normalized;
        }
        return null;
    }
}
