<?php
/**
 * Plugin Name: Hızlı Kasa
 * Description: avdini için hızlı POS sistemi.
 * Version: 4.4.13
 * Author: Seyfullah Kurt
 */

if (!defined('ABSPATH'))
    exit;

// Sabitler
define('HIZLI_KASA_VERSION', '4.4.13');
define('HIZLI_KASA_PATH', plugin_dir_path(__FILE__));
define('HIZLI_KASA_URL', plugin_dir_url(__FILE__));

function hizli_kasa_log($message, $filename = 'hizli-kasa-debug.log')
{
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    $file = HIZLI_KASA_PATH . $filename;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";

    // Her zaman sistem loguna da yaz (WP_DEBUG_LOG aktifse oraya gider)
    error_log("HK Log: " . $message);

    // Dosya yazmayı dene
    $result = @file_put_contents($file, $log_entry, FILE_APPEND);

    if ($result === false) {
        // Yazma başarısızsa sistem loguna hata mesajı bırak
        error_log("HK ERROR: Could not write to $file. Check directory permissions.");
    }
}

/**
 * Admin işlemleri için ayrı log
 */
function hizli_kasa_admin_log($message) {
    hizli_kasa_log($message, 'hizli-kasa-admin.log');
}

// Sınıfları Yükle
require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-admin-settings.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-mismatch-notifier.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-rest-api.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-shortcode.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-barcode-helper.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-menu-filter.php';

// Başlatıcılar
Hizli_Kasa_Database::init();
Hizli_Kasa_Stock_Manager::listen();
Hizli_Kasa_Mismatch_Notifier::init();

// Canary Log: Sadece WP hazır olduğunda çalıştır
add_action('init', function () {
    hizli_kasa_log("--- Eklenti Başarıyla Başlatıldı (init) ---");
});

// Veritabanı Aktivasyonu
register_activation_hook(__FILE__, ['Hizli_Kasa_Database', 'init']);

// Otomatik Güncelleme Sistemi (Plugin Update Checker)
require_once HIZLI_KASA_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// PUC'ın sadece branch'i takip etmesi için stratejileri filtreliyoruz (Release/Tag aranmasını engeller)
add_filter('puc_vcs_update_detection_strategies-hizli-kasa', function ($strategies) {
    unset($strategies['latest_release']);
    unset($strategies['latest_tag']);
    return $strategies;
});

$hizli_kasa_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Seyfullahkurt9/hizli-kasa/',
    __FILE__,
    'hizli-kasa'
);

// Private Repo izinleri için GitHub Token
// Hangi branch'i takip edeceğini belirliyoruz (main)
$hizli_kasa_update_checker->setAuthentication('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');
$hizli_kasa_update_checker->setBranch('main');

// Laragon gibi yerel ortamlarda DNS çözümleme gecikmelerini (cURL error 28) önlemek için zaman aşımını artırıyoruz.
add_filter('http_request_args', function ($args, $url) {
    if (strpos($url, 'api.github.com') !== false || strpos($url, 'github.com') !== false) {
        $args['timeout'] = 30; // 30 saniyeye çıkarıyoruz
    }
    return $args;
}, 10, 2);
