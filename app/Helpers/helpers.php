<?php

use App\Models\System;

if (!function_exists('system_settings')) {
    /**
     * Get system settings
     */
    function system_settings($key = null, $default = null)
    {
        try {
            $settings = System::first();
            
            if (!$settings) {
                // Create default settings if none exist
                $settings = System::create([
                    'name' => config('app.name', 'Laravel'),
                    'timezone' => config('app.timezone', 'UTC'),
                    'currency' => 'USD',
                    'currency_symbol' => '$',
                ]);
            }
            
            if ($key === null) {
                return $settings;
            }
            
            return $settings->{$key} ?? $default;
        } catch (\Exception $e) {
            // Fallback to config values if database isn't ready
            if ($key === 'name') {
                return config('app.name', 'Laravel');
            }
            if ($key === 'timezone') {
                return config('app.timezone', 'UTC');
            }
            return $default;
        }
    }
}

if (!function_exists('getStatusBadge')) {
    /**
     * Generate HTML badge for campaign/recipient status
     *
     * @param string $status
     * @return string
     */
    function getStatusBadge($status)
    {
        $badges = [
            'pending'   => '<span class="badge badge-warning">⏳ Pending</span>',
            'sending'   => '<span class="badge badge-info">📤 Sending</span>',
            'completed' => '<span class="badge badge-success">✅ Completed</span>',
            'failed'    => '<span class="badge badge-danger">❌ Failed</span>',
            'cancelled' => '<span class="badge badge-secondary">⛔ Cancelled</span>',
        ];
        return $badges[$status] ?? '<span class="badge badge-secondary">' . $status . '</span>';
    }
}