<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

$sku = '10919';
global $wpdb;

$product_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s", $sku));

if (!$product_id) {
    echo "Product with SKU $sku not found.\n";
    exit;
}

$product = wc_get_product($product_id);
echo "ID: " . $product->get_id() . "\n";
echo "Name: " . $product->get_name() . "\n";
echo "Type: " . $product->get_type() . "\n";
echo "Manage Stock: " . ($product->get_manage_stock() ? 'Yes' : 'No') . "\n";
echo "Stock Quantity: " . $product->get_stock_quantity() . "\n";
echo "Stock Status: " . $product->get_stock_status() . "\n";

// Check warehouse stock
$tables = Hizli_Kasa_Database::get_tables();
$stok_table = $tables['stok_konumlari'];
$warehouse_stocks = $wpdb->get_results($wpdb->prepare("SELECT * FROM $stok_table WHERE (product_id = %d OR variation_id = %d)", $product_id, $product_id));

echo "Warehouse Stocks:\n";
foreach ($warehouse_stocks as $ws) {
    echo "  Location ID: {$ws->location_id}, Qty: {$ws->quantity}\n";
}
