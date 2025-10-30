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
            return true;
        }

        // ISBN-10
        if ($length === 10) {
            return true;
        }

        // ISBN-13 (treated like EAN-13)
        if ($length === 13) {
            return true;
        }

        return false;
    }
}

