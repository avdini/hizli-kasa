<?php
require_once('wp-load.php');

global $wpdb;
$results = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE 'attribute_%' LIMIT 50");

echo "Meta Key Samples:\n";
foreach ($results as $row) {
    echo "ID: {$row->post_id} | Key: {$row->meta_key} | Value: {$row->meta_value}\n";
}
