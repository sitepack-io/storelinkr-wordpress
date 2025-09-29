<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}
class StoreLinkrEanHelper
{
    public static function validateBarcode(?string $code): bool
    {
        $code = str_replace([' ', '-'], '', $code); // Remove spaces/hyphens
        $length = strlen($code);

        if (!preg_match('/^\d+$/', $code) && !($length === 10 && preg_match('/^\d{9}[\dXx]$/', $code))) {
            return false; // Only digits, except ISBN-10 can end with X
        }

        // EAN/GTIN (8, 12, 13, 14 digits)
        if (in_array($length, [8, 12, 13, 14])) {
            $sum = 0;
            $weights = ($length % 2 === 0) ? [3, 1] : [1, 3]; // Alternating weights
            for ($i = 0; $i < $length - 1; $i++) {
                $weight = $weights[$i % 2];
                $sum += $code[$i] * $weight;
            }
            $checksum = (10 - ($sum % 10)) % 10;
            return $checksum == $code[$length - 1];
        }

        // ISBN-10
        if ($length === 10) {
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += ((int)$code[$i]) * ($i + 1);
            }
            $check = strtoupper($code[9]) === 'X' ? 10 : (int)$code[9];
            return ($sum % 11) === $check;
        }

        // ISBN-13 (treated like EAN-13)
        if ($length === 13) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += $code[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            $checksum = (10 - ($sum % 10)) % 10;
            return $checksum == $code[12];
        }

        return false;
    }
}

