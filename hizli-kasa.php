<?php

/**
 * Plugin Name: Hızlı Kasa
 * Description: avdini için hızlı POS sistemi.
 * Version: 12.57.1
 * Author: Seyfullah Kurt
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: hizli-kasa
 */

if (!defined('ABSPATH'))
    exit;

// Temel Yol Sabitleri (__FILE__ bağlamlı)
define('HIZLI_KASA_PATH', plugin_dir_path(__FILE__));
define('HIZLI_KASA_URL', plugin_dir_url(__FILE__));

// Modül Dosyalarını Yükle
require_once HIZLI_KASA_PATH . 'includes/constants.php';
require_once HIZLI_KASA_PATH . 'includes/helpers.php';
require_once HIZLI_KASA_PATH . 'includes/updater.php';

// Sınıfları Yükle ve Başlat (WooCommerce Yüklendikten Sonra)
add_action('plugins_loaded', 'hizli_kasa_init');

if (!function_exists('hizli_kasa_init')) {
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
}

// Veritabanı Aktivasyonu
register_activation_hook(__FILE__, 'hizli_kasa_db_activation');

if (!function_exists('hizli_kasa_db_activation')) {
    function hizli_kasa_db_activation() {
        require_once HIZLI_KASA_PATH . 'includes/classes/class-database.php';
        require_once HIZLI_KASA_PATH . 'includes/classes/class-shortcode.php';
        Hizli_Kasa_Database::init();

        if (function_exists('hizli_kasa_ensure_pos_page')) {
            hizli_kasa_ensure_pos_page();
        }
    }
}

// Otomatik Güncelleme Sistemini Başlat (PUC)
hizli_kasa_init_updater(__FILE__);
