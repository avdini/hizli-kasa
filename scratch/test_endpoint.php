<?php
define('WP_USE_THEMES', false);
// Find wp-load.php
$wp_load = dirname(__FILE__, 5) . '/wp-load.php';
if (!file_exists($wp_load)) {
    $wp_load = dirname(__FILE__, 6) . '/wp-load.php';
}
if (!file_exists($wp_load)) {
    die("wp-load.php not found at $wp_load");
}
require_once $wp_load;

// Mock request
$request = new WP_REST_Request('GET', '/hizli-kasa/v1/terminal/products');
$request->set_query_params([
    'limit' => 24,
    'offset' => 0,
    'depo_id' => '1:1',
    's' => ''
]);

// Call the function
try {
    $response = hizli_kasa_terminal_products($request);
    print_r($response);
} catch (Throwable $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
