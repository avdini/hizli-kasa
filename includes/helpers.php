<?php

if (!defined('ABSPATH'))
    exit;

/**
 * Genel Loglama Fonksiyonu
 */
function hizli_kasa_log($message, $filename = 'hizli-kasa-debug.log')
{
    $context = [];
    if (is_array($message) || is_object($message)) {
        $context = (array) $message;
        $message = wp_json_encode($message, JSON_UNESCAPED_UNICODE);
    }

    if (class_exists('Hizli_Kasa_Logger')) {
        $channel = 'system';
        $msg_lower = mb_strtolower((string) $message);
        if (strpos($msg_lower, 'stok') !== false || strpos($msg_lower, 'depo') !== false || strpos($msg_lower, 'rezervasyon') !== false) {
            $channel = 'stock';
        } elseif (strpos($msg_lower, 'sipariş') !== false || strpos($msg_lower, 'iade') !== false || strpos($msg_lower, 'kasiyer') !== false) {
            $channel = 'pos';
        } elseif (strpos($msg_lower, 'sku') !== false || strpos($msg_lower, 'barkod') !== false) {
            $channel = 'sku';
        }

        $level = 'info';
        if (strpos($msg_lower, 'hata') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'çatışma') !== false || strpos($msg_lower, 'çakışma') !== false || strpos($msg_lower, 'uyarı') !== false) {
            $level = 'warning';
        }

        Hizli_Kasa_Logger::log($message, $channel, $level, $context);
    }
}

/**
 * Admin işlemleri için ayrı log
 */
function hizli_kasa_admin_log($message)
{
    hizli_kasa_log($message, 'hizli-kasa-admin.log');
}
