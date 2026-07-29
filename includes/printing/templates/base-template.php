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

if (!function_exists('hk_render_barcode_svg')) {
    function hk_render_barcode_svg($text, $height = 38) {
        $text = (string)$text;
        if ($text === '') return '';

        // Standard Code128 pattern table (0..106)
        $patterns = [
            '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
            '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
            '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
            '212123', '212321', '222121', '111224', '112214', '112412', '114212', '121124', '121421', '141122',
            '141221', '112241', '112421', '122141', '122411', '142112', '142211', '241211', '221114', '411122',
            '411221', '421112', '421211', '212141', '214121', '412121', '111143', '111341', '113141', '114113',
            '114311', '411113', '411311', '113114', '114131', '311141', '411131', '211412', '211214', '211232',
            '233111', '211133', '211331', '221114', '221411', '241112', '131114', '131411', '141113', '411113',
            '411311', '113114', '114131', '311141', '411131', '211412', '211214', '211232', '233111', '200000',
            '321312', '111422', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211',
            '212411', '214112', '412112', '104411', '104141', '101441', '2331112'
        ];

        // Start B symbol is 104
        $code_values = [104];
        $checksum = 104;

        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $char_code = ord($text[$i]);
            $val = $char_code - 32;
            if ($val < 0 || $val > 95) $val = 0;
            $code_values[] = $val;
            $checksum += $val * ($i + 1);
        }

        $check_symbol = $checksum % 103;
        $code_values[] = $check_symbol;
        $code_values[] = 106; // Stop symbol

        $barcode_str = '';
        foreach ($code_values as $val) {
            $barcode_str .= $patterns[$val] ?? '111111';
        }

        $total_modules = 0;
        for ($i = 0; $i < strlen($barcode_str); $i++) {
            $total_modules += (int)$barcode_str[$i];
        }

        $bar_width = 2;
        $svg_width = $total_modules * $bar_width;

        $svg = '<svg class="hk-print-barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $svg_width . ' ' . $height . '" preserveAspectRatio="none" style="width:100%; max-width:250px; height:' . $height . 'px; display:block; margin:3px auto 0 auto;" data-barcode="' . esc_attr($text) . '">';
        $x = 0;
        $is_bar = true;
        for ($i = 0; $i < strlen($barcode_str); $i++) {
            $w = (int)$barcode_str[$i] * $bar_width;
            if ($is_bar && $w > 0) {
                $svg .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '" fill="#000000" />';
            }
            $x += $w;
            $is_bar = !$is_bar;
        }
        $svg .= '</svg>';

        return $svg;
    }
}
