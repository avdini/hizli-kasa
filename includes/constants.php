<?php

if (!defined('ABSPATH'))
    exit;

// Çalışma Zamanı ve Sürüm Sabitleri
if (!defined('HIZLI_KASA_BOOT_TIME')) {
    define('HIZLI_KASA_BOOT_TIME', microtime(true));
}

// Sürüm Sabiti (Ana hizli-kasa.php başlığından otomatik okunur)
if (!defined('HIZLI_KASA_VERSION')) {
    $hk_main_file = dirname(__DIR__) . '/hizli-kasa.php';
    $hk_ver = '1.0.0';
    if (file_exists($hk_main_file)) {
        $hk_header = file_get_contents($hk_main_file, false, null, 0, 500);
        if (preg_match('/Version:\s*([0-9\.]+)/i', $hk_header, $hk_matches)) {
            $hk_ver = trim($hk_matches[1]);
        }
    }
    define('HIZLI_KASA_VERSION', $hk_ver);
}

// Harici İndirme ve Servis URL'leri
define('HIZLI_KASA_HELPER_DOWNLOAD_URL', 'https://github.com/Seyfullahkurt9/web-print-helper/releases/latest/download/web-print-helper.exe');
