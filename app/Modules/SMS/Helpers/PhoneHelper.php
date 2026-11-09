<?php

namespace App\Modules\SMS\Helpers;

use App\Models\NetworkPrefix;

class PhoneHelper
{
    /**
     * Normalize any phone number to 254XXXXXXXXX format
     * Handles: +2547XXXXXXXX, 2547XXXXXXXX, 07XXXXXXXX, 7XXXXXXXX
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-digit characters (+, spaces, dashes, etc.)
        $cleaned = preg_replace('/\D/', '', $phone);

        // Case 1: Already has 254 prefix (12 digits starting with 254)
        if (preg_match('/^254([0-9]{9})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        // Case 2: Starts with 0 (0712345678)
        if (preg_match('/^0([0-9]{9})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        // Case 3: Starts with 7 (712345678)
        if (preg_match('/^([0-9]{9})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        return null;
    }

    /** Kenyan mobile: 07xx / 01xx, written with 0, 254, +254 or 00254 (spaces and dashes allowed). */
    public const KENYAN_REGEX = '/^(?:\+?254|00254|0)?([71]\d{8})$/';

    /** International number with another country code: starts with + or 00, then 7–15 digits, not 254. */
    public const FOREIGN_REGEX = '/^(?:\+|00)(?!254)([1-9]\d{6,14})$/';

    /** Calling codes for the countries scharge's people most often come from (longest match wins). */
    public const CALLING_CODES = [
        '1' => 'US/Canada', '20' => 'Egypt', '27' => 'South Africa', '30' => 'Greece', '31' => 'Netherlands', '32' => 'Belgium',
        '33' => 'France', '34' => 'Spain', '39' => 'Italy', '41' => 'Switzerland', '44' => 'United Kingdom', '45' => 'Denmark',
        '46' => 'Sweden', '47' => 'Norway', '49' => 'Germany', '61' => 'Australia', '65' => 'Singapore', '7' => 'Russia',
        '81' => 'Japan', '82' => 'South Korea', '86' => 'China', '90' => 'Turkey', '91' => 'India', '92' => 'Pakistan',
        '211' => 'South Sudan', '212' => 'Morocco', '233' => 'Ghana', '234' => 'Nigeria', '243' => 'DR Congo', '250' => 'Rwanda',
        '251' => 'Ethiopia', '252' => 'Somalia', '253' => 'Djibouti', '255' => 'Tanzania', '256' => 'Uganda', '257' => 'Burundi',
        '260' => 'Zambia', '263' => 'Zimbabwe', '966' => 'Saudi Arabia', '971' => 'UAE', '972' => 'Israel', '974' => 'Qatar',
        '353' => 'Ireland', '351' => 'Portugal', '358' => 'Finland', '880' => 'Bangladesh', '94' => 'Sri Lanka',
    ];

    private static function compact(?string $phone): string
    {
        return preg_replace('/[\s\-().]/', '', (string) $phone);
    }

    public static function isKenyan(?string $phone): bool
    {
        return preg_match(self::KENYAN_REGEX, self::compact($phone)) === 1;
    }

    public static function isForeign(?string $phone): bool
    {
        return preg_match(self::FOREIGN_REGEX, self::compact($phone)) === 1;
    }

    /** 'kenyan', 'foreign' or 'invalid'. */
    public static function kind(?string $phone): string
    {
        return self::isKenyan($phone) ? 'kenyan' : (self::isForeign($phone) ? 'foreign' : 'invalid');
    }

    /** Country of a foreign number by calling code (e.g. 'United Kingdom'), or null. */
    public static function foreignCountry(?string $phone): ?string
    {
        if (! preg_match(self::FOREIGN_REGEX, self::compact($phone), $m)) {
            return null;
        }
        foreach ([3, 2, 1] as $len) {
            if (isset(self::CALLING_CODES[substr($m[1], 0, $len)])) {
                return self::CALLING_CODES[substr($m[1], 0, $len)];
            }
        }

        return 'Abroad';
    }

    /** International form: +2547… for Kenyan, +<code>… for foreign, null if neither. */
    public static function e164(?string $phone): ?string
    {
        $c = self::compact($phone);
        if (preg_match(self::KENYAN_REGEX, $c, $m)) {
            return '+254'.$m[1];
        }

        return preg_match(self::FOREIGN_REGEX, $c, $m) ? '+'.$m[1] : null;
    }

    /**
     * Check if phone number is a valid Kenyan mobile number
     * Must be 12 digits and start with 254
     */
    public static function isValidKenyanNumber(?string $phone): bool
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return false;
        }
        return preg_match('/^254[0-9]{9}$/', $normalized) === 1;
    }

    /**
     * Check if phone number is a valid Safaricom number
     */
    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return false;
        }
        
        // Extract the 3-digit prefix (after 254)
        $prefix = substr($normalized, 3, 3);
        
        return NetworkPrefix::isSafaricom($prefix);
    }

    /**
     * Clean and format phone number
     */
    public static function clean(?string $phone): ?string
    {
        $normalized = self::normalize($phone);
        if ($normalized && self::isValid($normalized)) {
            return $normalized;
        }
        return null;
    }

    /**
     * Format phone number for display (e.g., 0712345678)
     */
    public static function formatDisplay(?string $phone): string
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return $phone ?? 'N/A';
        }
        // Convert 2547XXXXXXXX to 07XXXXXXXX
        if (preg_match('/^254([0-9]{9})$/', $normalized, $matches)) {
            return '0' . $matches[1];
        }
        return $normalized;
    }

    /**
     * Check if phone is empty/null
     */
    public static function isEmpty(?string $phone): bool
    {
        return empty($phone) || $phone === null || $phone === '';
    }

    /**
     * Get network name from database
     */
    public static function getNetwork(?string $phone): string
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return 'Invalid';
        }
        
        $prefix = substr($normalized, 3, 3);
        $network = NetworkPrefix::getNetwork($prefix);
        
        return $network ?? 'Unknown';
    }

    /**
     * Get phone status for categorization
     * Returns: 'pending' (valid Safaricom), 
     *          'invalid' (no phone or invalid), 
     *          'other_network' (other Kenyan networks)
     */
    public static function getStatus(?string $phone): string
    {
        // Empty phone = INVALID
        if (self::isEmpty($phone)) {
            return 'invalid';
        }

        // International number: SMS through KenyaSMS will not reach it (use WhatsApp or email).
        if (self::isForeign($phone)) {
            return 'foreign';
        }
        
        $normalized = self::normalize($phone);
        
        // If normalization fails, it's INVALID
        if (empty($normalized)) {
            return 'invalid';
        }
        
        // Check if it's a valid Kenyan number
        if (!self::isValidKenyanNumber($normalized)) {
            return 'invalid';
        }
        
        // Extract the 3-digit prefix (after 254)
        $prefix = substr($normalized, 3, 3);
        
        // Look up the network in database
        $network = NetworkPrefix::getNetwork($prefix);
        
        if (!$network) {
            return 'invalid';
        }
        
        if ($network === 'Safaricom') {
            return 'pending';
        }
        
        return 'other_network';
    }
}