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