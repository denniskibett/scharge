<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Normalize a Kenyan phone number to 2547XXXXXXXX format.
     * Returns null if the number is not a valid Kenyan mobile format.
     *
     * @param string|null $phone
     * @return string|null
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

        // Case 3: 712345678 (9 digits, missing leading 0 – rare but possible)
        if (preg_match('/^([7-9][0-9]{8})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        // Not a valid Kenyan number
        return null;
    }

    /**
     * Check if a phone number is a valid Kenyan mobile number.
     *
     * @param string|null $phone
     * @return bool
     */
    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return false;
        }
        // Must be 12 digits and start with 2547
        return preg_match('/^2547[0-9]{8}$/', $normalized) === 1;
    }

    /**
     * Clean and normalize a phone number. Returns normalized number if valid, null otherwise.
     *
     * @param string|null $phone
     * @return string|null
     */
    public static function clean(?string $phone): ?string
    {
        $normalized = self::normalize($phone);
        if ($normalized && self::isValid($normalized)) {
            return $normalized;
        }
        return null;
    }
}