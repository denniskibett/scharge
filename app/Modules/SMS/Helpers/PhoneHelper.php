<?php

namespace App\Modules\SMS\Helpers;

class PhoneHelper
{
    /**
     * Normalize any phone number to 2547XXXXXXXX format
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
        if (preg_match('/^254([7-9][0-9]{8})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        // Case 2: Starts with 0 (0712345678)
        if (preg_match('/^0([7-9][0-9]{8})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        // Case 3: Starts with 7 (712345678)
        if (preg_match('/^([7-9][0-9]{8})$/', $cleaned, $matches)) {
            return '254' . $matches[1];
        }

        // Case 4: Starts with 254 but has extra digits
        if (preg_match('/^254([0-9]{9})$/', $cleaned, $matches)) {
            // Check if it's a valid Safaricom number
            $number = '254' . $matches[1];
            if (preg_match('/^2547[0-9]{8}$/', $number)) {
                return $number;
            }
        }

        return null;
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
        // Safaricom numbers start with 2547
        return preg_match('/^2547[0-9]{8}$/', $normalized) === 1;
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
        if (preg_match('/^254([7-9][0-9]{8})$/', $normalized, $matches)) {
            return '0' . $matches[1];
        }
        return $normalized;
    }

    /**
     * Get network name
     */
    public static function getNetwork(?string $phone): string
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return 'Invalid';
        }
        
        if (preg_match('/^2547[0-9]{8}$/', $normalized)) {
            return 'Safaricom';
        }
        
        if (preg_match('/^254[7-9][0-9]{8}$/', $normalized)) {
            return 'Other Network';
        }
        
        return 'Invalid';
    }

    /**
     * Check if phone is empty/null
     */
    public static function isEmpty(?string $phone): bool
    {
        return empty($phone) || $phone === null || $phone === '';
    }

    /**
     * Get phone status for categorization
     * Returns: 'pending' (valid Safaricom), 'invalid' (no phone or invalid), 'other_network' (other Kenyan networks)
     */
    public static function getStatus(?string $phone): string
    {
        // 🔴 CRITICAL: Empty phone = INVALID
        if (self::isEmpty($phone)) {
            return 'invalid';
        }
        
        $normalized = self::normalize($phone);
        
        // If normalization fails, it's INVALID
        if (empty($normalized)) {
            return 'invalid';
        }
        
        // Check if it's a valid Safaricom number
        if (self::isValid($normalized)) {
            return 'pending';
        }
        
        // Check if it's a valid Kenyan number but not Safaricom (Airtel, Telkom, Equitel)
        // Kenyan networks: 2547 (Safaricom), 2541 (Airtel), 2542 (Telkom), 2543 (Equitel)
        if (preg_match('/^254[1-6,8-9][0-9]{8}$/', $normalized)) {
            return 'other_network';
        }
        
        return 'invalid';
    }
}