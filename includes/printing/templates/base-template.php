<?php
if (!defined('ABSPATH')) exit;

/**
 * Base Template Helper Functions for Print Rendering
 */
if (!function_exists('hk_format_price')) {
    function hk_format_price($amount) {
        return number_format((float)$amount, 2, ',', '.') . ' TL';
    }
}
