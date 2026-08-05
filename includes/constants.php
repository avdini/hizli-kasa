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
    $hk_ver = '';
    if (function_exists('get_file_data') && file_exists($hk_main_file)) {
        $hk_header = get_file_data($hk_main_file, ['Version' => 'Version']);
        $hk_ver = $hk_header['Version'] ?? '';
    }
    if (empty($hk_ver) && file_exists($hk_main_file)) {
        $hk_content = file_get_contents($hk_main_file, false, null, 0, 500);
        if (preg_match('/Version:\s*([0-9\.]+)/i', $hk_content, $m)) {
            $hk_ver = trim($m[1]);
        }
    }
    define('HIZLI_KASA_VERSION', !empty($hk_ver) ? $hk_ver : '1.0.0');
}

// Harici İndirme ve Servis URL'leri
define('HIZLI_KASA_HELPER_DOWNLOAD_URL', 'https://github.com/Seyfullahkurt9/web-print-helper/releases/latest/download/web-print-helper.exe');
