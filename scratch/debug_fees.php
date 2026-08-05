<?php
require_once('wp-load.php');

$args = array(
    'limit' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
);
$orders = wc_get_orders($args);

echo "Sipariş Fee Kontrolü:\n";
foreach ($orders as $order) {
    echo "ID: #" . $order->get_id() . " | Toplam: " . $order->get_total() . "\n";
    foreach ($order->get_fees() as $fee) {
        echo "  - Fee Name: '" . $fee->get_name() . "' | Total: " . $fee->get_total() . "\n";
    }
}
