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

    $results = [];
    $exact = $data->get_param('exact');

    if ($exact) {
        // Barkod okuyucu için tam SKU eşleşmesi
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
              AND p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s)
            GROUP BY p.ID
            LIMIT 20
        ", $s));
    } else {
        // --- AKILLI ARAMA MANTIĞI ---
        $found_ids = [];

        // 1. Advanced Woo Search Entegrasyonu (Eğer kuruluysa)
        if (class_exists('AWS_Search')) {
            $aws_search = new AWS_Search();
            // AWS 'search' metodu ürün ID'lerini içeren bir dizi döner.
            $aws_results = $aws_search->search($s);
            if (!empty($aws_results['products'])) {
                foreach ($aws_results['products'] as $p_item) {
                     $found_ids[] = $p_item['id'];
                }
            }
        }

        // 2. Eğer AWS yoksa veya sonuç bulamadıysa, Gelişmiş Yedek Arama (Fallback)
        if (empty($found_ids)) {
            $words = explode(' ', $s);
            $where_parts = [];
            foreach ($words as $word) {
                if (empty($word)) continue;
                $like = '%' . $wpdb->esc_like($word) . '%';
                $where_parts[] = $wpdb->prepare("(p.post_title LIKE %s OR pm.meta_value LIKE %s)", $like, $like);
            }
            $where_clause = implode(' AND ', $where_parts);

            $fallback_results = $wpdb->get_results("
                SELECT p.ID
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                WHERE p.post_status = 'publish'
                  AND p.post_type IN ('product', 'product_variation')
                  AND ($where_clause)
                GROUP BY p.ID
                LIMIT 20
            ");
            
            foreach ($fallback_results as $fr) {
                $found_ids[] = $fr->ID;
            }
        }

        // 3. Bulunan ID'lerin detaylarını getir (Hydration)
        if (!empty($found_ids)) {
            $ids_str = implode(',', array_map('intval', $found_ids));
            $results = $wpdb->get_results("
                SELECT p.ID, p.post_title, p.post_type, p.post_parent,
                       MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                       MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
                       MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
                       MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
                       MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) as manage_stock,
                       MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.ID IN ($ids_str)
                GROUP BY p.ID
                ORDER BY FIELD(p.ID, $ids_str)
            ");
        }
    }

    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = hizli_kasa_format_urun_row($row);
    }

    // Eğer sadece tek bir sonuç geldiyse ve bu bir variable ürün (veya varyantı) ise,
    // tüm kardeşlerini ve ana ürünü de listeye ekle.
    if (count($formatted) === 1) {
        $tek_sonuc = $formatted[0];
        $parent_id = ($tek_sonuc['type'] === 'variation') ? $tek_sonuc['parent_id'] : $tek_sonuc['id'];

        $ana_urun = wc_get_product($parent_id);
        if ($ana_urun && $ana_urun->is_type('variable')) {
            $genis_results = $wpdb->get_results($wpdb->prepare("
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
                  AND (p.ID = %d OR p.post_parent = %d)
                GROUP BY p.ID
                ORDER BY (CASE WHEN p.ID = %d THEN 0 ELSE 1 END) ASC, p.post_type ASC, p.ID ASC
            ", $parent_id, $parent_id, $tek_sonuc['id']));

            if (!empty($genis_results)) {
                $formatted = [];
                foreach ($genis_results as $grow) {
                    $formatted[] = hizli_kasa_format_urun_row($grow);
                }
            }
        }
    }

    return $formatted;
}

/**
 * Veritabanından gelen ürün satırını formatlar.
 */
function hizli_kasa_format_urun_row($row) {
    $urun = wc_get_product($row->ID);
    if (!$urun) return null;

    $is_variable = $urun->is_type('variable');
    if ($is_variable) {
        $children = $urun->get_children();
        // Sadece yayında (publish) olan varyasyonları sayalım
        $active_children = array_filter($children, function($child_id) {
            return get_post_status($child_id) === 'publish';
        });
        if (empty($active_children)) {
            $is_variable = false;
        }
    }

    $name = $urun->get_name();
    if ($row->post_type === 'product_variation') {
        $parent = wc_get_product($row->post_parent);
        if ($parent) {
            $attributes = $urun->get_variation_attributes();
            $attr_values = implode(', ', array_values($attributes));
            $name = $parent->get_name() . ' - ' . $attr_values;
        }
    }

    $image_id = $urun->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

    return [
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
