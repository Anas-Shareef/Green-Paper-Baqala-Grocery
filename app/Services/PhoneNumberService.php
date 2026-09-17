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

    /**
     * Build canonical address string without duplicated villa or zone labels (PRD Section 8)
     */
    public static function formatCanonicalAddress(?string $villa, ?string $street, ?string $zone = null): string
    {
        $vRaw = trim($villa ?? '');
        $sRaw = trim($street ?? '');
        $zRaw = trim($zone ?? '');

        // Extract clean villa number or format Villa label
        $vDigits = preg_replace('/[^\d]/', '', $vRaw);
        if (!empty($vDigits)) {
            $vLabel = "Villa {$vDigits}";
        } elseif (!empty($vRaw)) {
            $vLabel = (str_ireplace('villa', '', $vRaw) === $vRaw) ? "Villa {$vRaw}" : $vRaw;
        } else {
            $vLabel = '';
        }

        // Clean street address by stripping repeated "Villa XX" or "Villa" or "Zone XX"
        $sClean = $sRaw;
        if (!empty($vLabel)) {
            // Strip "Villa 94", "Villa94", "Villa 94," etc.
            $sClean = preg_replace('/(?:\bVilla\s*\d+\b|\bVilla\b|' . preg_quote($vRaw, '/') . ')[,\s]*/i', '', $sClean);
        }
        if (!empty($vDigits)) {
            // Strip standalone villa number e.g. "94," if at start of street
            $sClean = preg_replace('/^\s*' . preg_quote($vDigits, '/') . '[,\s]*/i', '', $sClean);
        }
        if (!empty($zRaw)) {
            // Strip duplicate zone if already present in street
            $sClean = preg_replace('/' . preg_quote($zRaw, '/') . '[,\s]*/i', '', $sClean);
        }

        $sClean = trim($sClean, ", \t\n\r\0\x0B");

        $parts = [];
        if (!empty($vLabel)) {
            $parts[] = $vLabel;
        }
        if (!empty($sClean)) {
            $parts[] = $sClean;
        }
        if (!empty($zRaw)) {
            $parts[] = $zRaw;
        }

        return implode(', ', array_filter($parts));
    }

    /**
     * Find existing Customer record matching any format variant of raw or normalized phone number
     */
    public static function findCustomer(?string $rawPhone): ?\App\Models\Customer
    {
        if (empty($rawPhone)) {
            return null;
        }

        try {
            $raw = trim($rawPhone);
            $normalized = self::normalize($raw);
            $digitsOnly = preg_replace('/[^\d]/', '', $raw);

            $candidates = array_values(array_unique(array_filter([
                $normalized,
                $raw,
                $digitsOnly,
                str_starts_with($normalized, '+971') ? '0' . substr($normalized, 4) : null,
                ltrim($normalized, '+'),
                str_starts_with($normalized, '+971') ? substr($normalized, 4) : null,
            ])));

            $last7 = strlen($digitsOnly) >= 7 ? substr($digitsOnly, -7) : $digitsOnly;

            $hasWhatsappCol = false;
            try {
                $hasWhatsappCol = \Illuminate\Support\Facades\Schema::hasColumn('customers', 'whatsapp_number');
            } catch (\Throwable $e) {
                $hasWhatsappCol = false;
            }

            return \App\Models\Customer::where(function ($query) use ($candidates, $last7, $hasWhatsappCol) {
                $query->whereIn('phone', $candidates);
                if ($hasWhatsappCol) {
                    $query->orWhereIn('whatsapp_number', $candidates);
                }

                if (strlen($last7) >= 7) {
                    $query->orWhere('phone', 'LIKE', "%{$last7}");
                    if ($hasWhatsappCol) {
                        $query->orWhere('whatsapp_number', 'LIKE', "%{$last7}");
                    }
                }
            })->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
