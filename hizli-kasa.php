<?php

/**
 * Plugin Name: Hızlı Kasa
 * Description: avdini için hızlı POS sistemi.
 * Version: 11.5
 * Author: Seyfullah Kurt
 */

if (!defined('ABSPATH'))
    exit;

// Sabitler
define('HIZLI_KASA_VERSION', '11.5');
define('HIZLI_KASA_PATH', plugin_dir_path(__FILE__));
define('HIZLI_KASA_URL', plugin_dir_url(__FILE__));

function hizli_kasa_log($message, $filename = 'hizli-kasa-debug.log')
{
    // Production'da log tamamen devre dışı — Ayarlar > Hızlı Kasa > "Debug Logu Aktif" ile açılabilir.
    // Her sipariş onayında onlarca senkron disk I/O işlemi yapılmasını engeller.
    $debug_aktif = get_option('hizli_kasa_debug_log_aktif', '0') === '1';
    if (!$debug_aktif) {
        return;
    }

    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    $file = HIZLI_KASA_PATH . $filename;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";

    error_log("HK Log: " . $message);

    $result = @file_put_contents($file, $log_entry, FILE_APPEND);

    if ($result === false) {
        error_log("HK ERROR: Could not write to $file. Check directory permissions.");
    }
}

/**
 * Admin işlemleri için ayrı log
 */
function hizli_kasa_admin_log($message)
{
    hizli_kasa_log($message, 'hizli-kasa-admin.log');
}

// Sınıfları Yükle
require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-hooks.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-user-warehouse-permissions.php';
require_once HIZLI_KASA_PATH . 'includes/classes/stock/class-stock-manager.php';
require_once HIZLI_KASA_PATH . 'includes/classes/stock/class-stock-order-handler.php';
require_once HIZLI_KASA_PATH . 'includes/classes/stock/class-stock-import-export.php';
require_once HIZLI_KASA_PATH . 'includes/classes/stock/class-stock-allocation.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-menu.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-settings-register.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-settings-page.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-depo-controller.php';
require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-mismatch-bubble.php';
require_once HIZLI_KASA_PATH . 'includes/classes/ajax/class-ajax-stock.php';
require_once HIZLI_KASA_PATH . 'includes/classes/ajax/class-ajax-import-export.php';
require_once HIZLI_KASA_PATH . 'includes/classes/ajax/class-ajax-unmatched.php';
require_once HIZLI_KASA_PATH . 'includes/classes/ajax/class-ajax-tools.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-admin-settings.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-mismatch-notifier.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-rest-api.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-shortcode.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-barcode-helper.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-menu-filter.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-mobile-handler.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-user-handler.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-admin-order-tools.php';
require_once HIZLI_KASA_PATH . 'includes/classes/class-email-modifier.php';


// Başlatıcılar
// Hizli_Kasa_Database::init(); // Performans ve SEO için her istekte çalıştırılması engellendi (aktivasyon kancası kullanılmalıdır).
Hizli_Kasa_Hooks::init();
Hizli_Kasa_Admin_Menu::init();
Hizli_Kasa_Admin_Settings_Register::init();
Hizli_Kasa_Admin_Depo_Controller::init();
Hizli_Kasa_Admin_Mismatch_Bubble::init();
Hizli_Kasa_User_Warehouse_Permissions::init();
Hizli_Kasa_Ajax_Stock::init();
Hizli_Kasa_Ajax_Import_Export::init();
Hizli_Kasa_Ajax_Unmatched::init();
Hizli_Kasa_Ajax_Tools::init();
Hizli_Kasa_Stock_Order_Handler::listen();
Hizli_Kasa_Mismatch_Notifier::init();
Hizli_Kasa_Mobile_Handler::init();
Hizli_Kasa_User_Handler::init();
Hizli_Kasa_Admin_Order_Tools::init();
Hizli_Kasa_Email_Modifier::init();


// Canary Log: Sadece WP hazır olduğunda çalıştır
add_action('init', function () {
    hizli_kasa_log("--- Eklenti Başarıyla Başlatıldı (init) ---");
    if (get_option('hizli_kasa_db_version_sayim') !== '2.0') {
        Hizli_Kasa_Database::init();
        update_option('hizli_kasa_db_version_sayim', '2.0');
    }
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

$hizli_kasa_update_checker->setBranch('main');

// Laragon gibi yerel ortamlarda DNS çözümleme gecikmelerini (cURL error 28) önlemek için zaman aşımını artırıyoruz.
add_filter('http_request_args', function ($args, $url) {
    if (strpos($url, 'api.github.com') !== false || strpos($url, 'github.com') !== false) {
        $args['timeout'] = 60; // 60 saniyeye çıkarıyoruz
    }
    return $args;
}, 10, 2);

