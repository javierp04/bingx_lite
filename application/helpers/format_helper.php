<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Format Helper
 * Functions for number and price formatting
 */

// Format price with intelligent decimal handling
if (!function_exists('format_price'))
{
    function format_price($price) {
        if ($price === null) return 'N/A';
        
        // For very small numbers, show more decimal places
        if (abs($price) < 0.01) {
            // No more than 8 decimal places
            return number_format($price, 8);
        }
        
        // Default: 2 decimal places
        return number_format($price, 2);
    }
}

// Format quantity without trailing zeros
if (!function_exists('format_quantity'))
{
    function format_quantity($quantity) {
        if ($quantity === null) return 'N/A';
        
        // Format to string and remove trailing zeros
        $formatted = number_format($quantity, 8);
        return rtrim(rtrim($formatted, '0'), '.');
    }
}