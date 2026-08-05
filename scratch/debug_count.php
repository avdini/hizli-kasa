<?php
require_once('../../../wp-load.php');
global $wpdb;

$depo_id = 1; // Test ID
$threshold = 5;
$stok_table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';

$where = "p.post_status = 'publish' AND p.post_type = 'product'";
$join  = "LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'";
$join .= $wpdb->prepare(" INNER JOIN $stok_table sk_filter ON (sk_filter.product_id = p.ID AND sk_filter.location_id = %d)", $depo_id);

$params = [];

$sql = $wpdb->prepare("
    SELECT COUNT(sk.id)
    FROM $stok_table sk
    INNER JOIN {$wpdb->posts} p ON (
        (p.post_type = 'product' AND sk.product_id = p.ID AND sk.variation_id = 0)
        OR
        (p.post_type = 'product_variation' AND sk.variation_id = p.ID)
    )
    WHERE sk.location_id = %d 
      AND (
          p.ID IN (SELECT ID FROM (SELECT p.ID FROM {$wpdb->posts} p $join WHERE $where) as matched_ids)
          OR 
          p.post_parent IN (SELECT ID FROM (SELECT p.ID FROM {$wpdb->posts} p $join WHERE $where) as matched_ids)
      )
", array_merge([$depo_id], $params, $params));

echo "SQL: " . $sql . "\n";
$result = $wpdb->get_var($sql);
echo "Result: " . $result . "\n";
echo "Last Error: " . $wpdb->last_error . "\n";
