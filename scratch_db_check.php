<?php
require_once('wp-load.php');
global $wpdb;
$table_name = $wpdb->prefix . 'hizli_kasa_depolar';
$results = $wpdb->get_results("DESCRIBE $table_name");
print_r($results);
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "\nTotal rows: $count\n";
