<?php
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

global $wpdb;
$table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';

$duplicates = $wpdb->get_results("
    SELECT product_id, variation_id, location_id, COUNT(*) as count, SUM(quantity) as total_qty
    FROM $table 
    GROUP BY product_id, variation_id, location_id 
    HAVING count > 1
");

if (empty($duplicates)) {
    echo "NO_DUPLICATES_FOUND";
} else {
    echo "DUPLICATES_FOUND:\n";
    foreach ($duplicates as $d) {
        echo "P:{$d->product_id} V:{$d->variation_id} L:{$d->location_id} Count:{$d->count} TotalQty:{$d->total_qty}\n";
    }
}
