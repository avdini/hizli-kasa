<?php
/**
 * Hızlı Kasa - REST API Loader
 *
 * Modüler API dosyalarını yükler.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

$api_dir = HIZLI_KASA_PATH . 'includes/api/';

require_once $api_dir . 'api-helpers.php';
require_once $api_dir . 'api-user.php';
require_once $api_dir . 'api-kasa.php';
require_once $api_dir . 'api-siparis.php';
require_once $api_dir . 'api-iade.php';
require_once $api_dir . 'api-masraf.php';
require_once $api_dir . 'api-raporlar.php';
require_once $api_dir . 'api-terminal.php';
require_once $api_dir . 'api-istatistik.php';
require_once $api_dir . 'api-sayim.php';

// Load V2 API Classes
require_once $api_dir . 'v2/core/class-api-response.php';
require_once $api_dir . 'v2/core/class-api-controller-base.php';
require_once $api_dir . 'v2/controllers/class-api-product-codes.php';
require_once $api_dir . 'v2/controllers/class-api-suppliers.php';
require_once $api_dir . 'v2/controllers/class-api-purchase-orders.php';
require_once $api_dir . 'v2/controllers/class-api-validate-coupon.php';
require_once $api_dir . 'v2/controllers/class-api-shipments.php';
require_once $api_dir . 'v2/controllers/class-api-user-sound.php';
require_once $api_dir . 'v2/controllers/class-api-user-favorites.php';
require_once $api_dir . 'v2/controllers/class-api-product-statistics.php';
require_once $api_dir . 'v2/controllers/class-api-auto-sku.php';

// Register V2 REST Routes
add_action('rest_api_init', function () {
    $product_codes_controller = new Hizli_Kasa_API_Product_Codes();
    $product_codes_controller->register_routes();

    $suppliers_controller = new Hizli_Kasa_API_Suppliers();
    $suppliers_controller->register_routes();

    $purchase_orders_controller = new Hizli_Kasa_API_Purchase_Orders();
    $purchase_orders_controller->register_routes();

    $validate_coupon_controller = new Hizli_Kasa_API_Validate_Coupon();
    $validate_coupon_controller->register_routes();

    $shipments_controller = new Hizli_Kasa_API_Shipments();
    $shipments_controller->register_routes();

    $user_sound_controller = new Hizli_Kasa_API_User_Sound();
    $user_sound_controller->register_routes();

    $user_favorites_controller = new Hizli_Kasa_API_User_Favorites();
    $user_favorites_controller->register_routes();

    $product_statistics_controller = new Hizli_Kasa_API_Product_Statistics();
    $product_statistics_controller->register_routes();

    $auto_sku_controller = new Hizli_Kasa_API_Auto_Sku();
    $auto_sku_controller->register_routes();
});


