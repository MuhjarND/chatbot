<?php

namespace App\Services;

class WhatsappNumberService
{
    /**
     * Normalize a WhatsApp phone number to 62xxx format.
     *
     * Rules:
     * - Remove all non-digit characters (spaces, +, -, parentheses, etc.)
     * - 0812xxx  → 62812xxx
     * - 812xxx   → 62812xxx
     * - 62812xxx → 62812xxx (unchanged)
     *
     * @param string $number
     * @return string
     */
    public function normalize(string $number): string
    {
        // Remove all non-digit characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Handle leading zero → replace with 62
        if (substr($number, 0, 1) === '0') {
            $number = '62' . substr($number, 1);
        }
        // Handle numbers that don't start with 62 (e.g., 812xxx)
        elseif (substr($number, 0, 2) !== '62') {
            $number = '62' . $number;
        }

        return $number;
    }
}
