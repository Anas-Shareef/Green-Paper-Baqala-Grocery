<?php

namespace App\Services;

class PhoneNumberService
{
    /**
     * Normalize mobile phone number to canonical format +97150XXXXXXX
     */
    public static function normalize(?string $phone): string
    {
        if (!$phone) {
            return '';
        }

        // Remove all non-digit characters except leading +
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));

        // If it starts with +, return cleaned digits with +
        if (str_starts_with($cleaned, '+')) {
            $digits = preg_replace('/[^\d]/', '', $cleaned);
            return '+' . $digits;
        }

        $digits = preg_replace('/[^\d]/', '', $cleaned);

        // UAE local format e.g. 0501234567 -> +971501234567
        if (str_starts_with($digits, '05')) {
            return '+971' . substr($digits, 1);
        }

        // UAE format without leading + e.g. 971501234567 -> +971501234567
        if (str_starts_with($digits, '9715')) {
            return '+' . $digits;
        }

        // Standard fallback (assume UAE +971 if 9 digits starting with 5)
        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '+971' . $digits;
        }

        return '+' . $digits;
    }

    /**
     * Format phone number for WhatsApp wa.me link (digits only without +)
     */
    public static function formatForWhatsApp(?string $phone): string
    {
        $normalized = self::normalize($phone);
        return ltrim($normalized, '+');
    }
}
