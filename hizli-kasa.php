<?php

/**
 * Plugin Name: Hızlı Kasa
 * Description: avdini için hızlı POS sistemi.
 * Version: 12.51.1
 * Author: Seyfullah Kurt
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: hizli-kasa
 */

if (!defined('ABSPATH'))
    exit;

// Sabitler
if (!defined('HIZLI_KASA_BOOT_TIME')) {
    define('HIZLI_KASA_BOOT_TIME', microtime(true));
}
define('HIZLI_KASA_VERSION', '12.51.1');
define('HIZLI_KASA_PATH', plugin_dir_path(__FILE__));
define('HIZLI_KASA_URL', plugin_dir_url(__FILE__));

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

// Sınıfları Yükle ve Başlat (WooCommerce Yüklendikten Sonra)
add_action('plugins_loaded', 'hizli_kasa_init');

function hizli_kasa_init() {
    // WooCommerce Dependency Check
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('Hızlı Kasa eklentisinin çalışabilmesi için WooCommerce aktif olmalıdır.', 'hizli-kasa') . '</p></div>';
        });
        return;
    }

    // Sınıfları Yükle
    require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
    require_once HIZLI_KASA_PATH . 'includes/classes/class-logger.php';
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
    require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-admin-product-export.php';
    require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-catalog-share-manager.php';
    require_once HIZLI_KASA_PATH . 'includes/classes/admin/class-catalog-public-handler.php';
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
    require_once HIZLI_KASA_PATH . 'includes/classes/class-product-page-stocks.php';
    require_once HIZLI_KASA_PATH . 'includes/classes/class-auto-sku-manager.php';
    require_once HIZLI_KASA_PATH . 'includes/classes/class-qr-checkout-handler.php';

    // Başlatıcılar
    Hizli_Kasa_Hooks::init();
    Hizli_Kasa_Admin_Menu::init();
    Hizli_Kasa_Admin_Settings_Register::init();
    Hizli_Kasa_Admin_Depo_Controller::init();
    Hizli_Kasa_Admin_Mismatch_Bubble::init();
    Hizli_Kasa_Admin_Product_Export::init();
    Hizli_Kasa_Catalog_Public_Handler::init();
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
    Hizli_Kasa_Product_Page_Stocks::init();
    Hizli_Kasa_Auto_Sku_Manager::init();
    Hizli_Kasa_QR_Checkout_Handler::init();

    add_action('init', function () {
        if (get_option('hizli_kasa_db_version_sayim') !== '2.2') {
            Hizli_Kasa_Database::init();
            update_option('hizli_kasa_db_version_sayim', '2.2');
        }
    });
}

// Veritabanı Aktivasyonu
register_activation_hook(__FILE__, 'hizli_kasa_db_activation');

function hizli_kasa_db_activation() {
    require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
    require_once HIZLI_KASA_PATH . 'includes/classes/class-shortcode.php';
    Hizli_Kasa_Database::init();

    if (function_exists('hizli_kasa_ensure_pos_page')) {
        hizli_kasa_ensure_pos_page();
    }
}

// Otomatik Güncelleme Sistemi (Plugin Update Checker)
// POS AJAX ve REST isteklerinde GitHub cURL kilitlenmesini önlemek için PUC'ı sadece WP Admin, Cron veya WP Eklenti Güncelleme AJAX isteğinde çalıştırıyoruz.
$is_wp_update_ajax = wp_doing_ajax() && isset($_REQUEST['action']) && $_REQUEST['action'] === 'update-plugin';

if ((!wp_doing_ajax() || $is_wp_update_ajax) && (!defined('REST_REQUEST') || !REST_REQUEST)) {
    require_once HIZLI_KASA_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';

    // PUC'ın Release/Tag aramayı bırakıp doğrudan hedef branch'i (main/master) takip etmesini sağlıyoruz
    add_filter('puc_vcs_update_detection_strategies-hizli-kasa', function ($strategies) {
        unset($strategies['latest_release']);
        unset($strategies['latest_tag']);
        return $strategies;
    });

    $repo_url   = defined('HIZLI_KASA_UPDATE_REPO') ? HIZLI_KASA_UPDATE_REPO : 'https://github.com/Seyfullahkurt9/hizli-kasa/';
    $repo_branch = defined('HIZLI_KASA_UPDATE_BRANCH') ? HIZLI_KASA_UPDATE_BRANCH : 'main';

    $hizli_kasa_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        $repo_url,
        __FILE__,
        'hizli-kasa'
    );

    $hizli_kasa_update_checker->setBranch($repo_branch);

    // WP Admin'de "Güncellemeleri Kontrol Et" isteğinde önbelleği temizleyip zorla kontrol ettiriyoruz
    if (is_admin() && isset($_GET['force-check'])) {
        $hizli_kasa_update_checker->requestUpdate();
    }

    // DNS ve yavaş ağ kilitlenmelerine karşı 15 saniyelik dengeli cURL zaman aşımı
    add_filter('http_request_args', function ($args, $url) {
        if (strpos($url, 'api.github.com') !== false || strpos($url, 'github.com') !== false || strpos($url, 'codeload.github.com') !== false) {
            $args['timeout'] = 15;
        }
        return $args;
    }, 10, 2);
}

