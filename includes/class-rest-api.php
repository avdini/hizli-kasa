<?php
/**
 * Hızlı Kasa - REST API
 *
 * Özel ürün arama endpoint'i (varyant desteği dahil).
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH'))
    exit;

// REST API Route Kaydı
add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/search', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_ozel_arama',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));
});

/**
 * Özel ürün arama fonksiyonu.
 *
 * Hem ana ürünleri hem varyantları isim ve SKU'dan arar.
 * Varyant ürünlerde nitelik bilgilerini isme ekler.
 *
 * @param WP_REST_Request $data İstek verisi
 * @return array Formatlı ürün listesi
 */
function hizli_kasa_ozel_arama($data) {
    global $wpdb;
    $s = sanitize_text_field($data['s']);
    if (empty($s)) return [];

    // Hem ana ürünleri hem varyantları isim ve SKU'dan arayan SQL
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_type, p.post_parent,
               MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
               MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
               MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
               MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
               MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) as manage_stock,
               MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_status = 'publish'
          AND p.post_type IN ('product', 'product_variation')
          AND (
              p.post_title LIKE %s 
              OR p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s)
          )
        GROUP BY p.ID
        LIMIT 20
    ", '%' . $wpdb->esc_like($s) . '%', '%' . $wpdb->esc_like($s) . '%'));

    $formatted = [];
    foreach ($results as $row) {
        $urun = wc_get_product($row->ID);
        if (!$urun) continue;

        $is_variable = $urun->is_type('variable');
        if ($is_variable) {
            $children = $urun->get_children();
            if (empty($children)) {
                $is_variable = false;
            }
        }
        $name = $urun->get_name();
        if ($row->post_type === 'product_variation') {
            $parent = wc_get_product($row->post_parent);
            if ($parent) {
                // Varyant niteliklerini isme ekle: "Ürün Adı - M, Kırmızı"
                $attributes = $urun->get_variation_attributes();
                $attr_label = implode(', ', array_map('wc_attribute_label', $attributes));
                // attributes dizisindeki değerleri de ekleyelim
                $attr_values = implode(', ', array_values($attributes));
                $name = $parent->get_name() . ' - ' . $attr_values;
            }
        }

        $image_id = $urun->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

        $formatted[] = [
            'id' => (int)$row->ID,
            'parent_id' => (int)$row->post_parent,
            'type' => $row->post_type === 'product_variation' ? 'variation' : 'product',
            'name' => $name,
            'sku' => $row->sku,
            'price' => $row->price,
            'regular_price' => $row->regular_price,
            'stock_status' => $row->stock_status,
            'manage_stock' => $row->manage_stock === 'yes',
            'stock_quantity' => (float)$row->stock_quantity,
            'images' => $image_url ? [['src' => $image_url]] : [],
            'is_variable' => $is_variable
        ];
    }

    return $formatted;
}
