<?php
require_once('wp-load.php');
$request = new WP_REST_Request('GET', '/hizli-kasa/v1/terminal/products');
$request->set_param('limit', 1);
$request->set_param('depo_id', 1); // Assuming 1 exists
$response = rest_do_request($request);
$data = $response->get_data();
print_r($data['products'][0]);
