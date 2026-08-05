<?php
/**
 * REST API İstatistik Doğrulama Testi
 */

// WordPress yükle
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../wp-load.php');

if (!current_user_can('manage_options')) {
    // Test amaçlı yetkiyi simüle et veya direkt çalıştır
    // wp_set_current_user(1); 
}

// REST Request simülasyonu
$request = new WP_REST_Request('GET', '/hizli-kasa/v1/terminal/products');
$request->set_param('limit', 10);
$request->set_param('offset', 0);

// İlk bulabildiğimiz depoyu seçelim
global $wpdb;
$depo_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}hizli_kasa_depolar LIMIT 1");
if (!$depo_id) {
    echo "Hata: Hiç depo bulunamadı.\n";
    exit;
}
$request->set_param('depo_id', $depo_id);

echo "Depo ID: $depo_id için istatistikler çekiliyor...\n";

// Fonksiyonu manuel çağıralım (class-rest-api.php zaten inklüde edilmiş olmalı ama garantiye alalım)
require_once(HIZLI_KASA_PATH . 'includes/classes/class-rest-api.php');

$response = hizli_kasa_terminal_products($request);

echo "--- SONUÇ ---\n";
echo "Toplam Ürün (Parent): " . $response['total'] . "\n";
echo "Basit Ürün: " . $response['simple_count'] . "\n";
echo "Varyasyonlu (Ana): " . $response['variable_count'] . "\n";
echo "Toplam Kalem: " . $response['grand_total_items'] . "\n";
echo "Kritik Stok: " . $response['critical_count'] . "\n";
echo "-------------\n";

if ($response['grand_total_items'] > 0 || $response['total'] > 0) {
    echo "BAŞARILI: İstatistikler 0'dan farklı.\n";
} else {
    echo "UYARI: Tüm istatistikler 0. Bu gerçekten böyle olabilir mi?\n";
}
