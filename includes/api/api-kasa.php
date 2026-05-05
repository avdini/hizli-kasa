<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/search', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_ozel_arama',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/warehouse-stock-check', array(
        'methods' => 'POST',
        'callback' => 'hizli_kasa_warehouse_stock_check',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

    register_rest_route('hizli-kasa/v1', '/barcode/label-data', array(
        'methods' => 'GET',
        'callback' => 'hizli_kasa_api_get_barcode_data',
        'permission_callback' => function () {
            return hizli_kasa_can_access_app();
        }
    ));

});

/**
 * Özel ürün arama fonksiyonu (Performans Optimizasyonlu).
 */
function hizli_kasa_ozel_arama($data)
{
    global $wpdb;
    $s = sanitize_text_field($data['s']);
    if (empty($s))
        return [];

    $depo_id = $data->get_param('depo_id');
    $exact = $data->get_param('exact');

    $found_ids = [];
    $cache_aktif = get_option('hizli_kasa_cache_aktif', '1') === '1';
    $cache_version = get_option('hizli_kasa_search_cache_version', '1');
    $cache_key = 'hk_search_' . $cache_version . '_' . md5($s . '_' . $exact);
    
    if ($cache_aktif) {
        $found_ids = get_transient($cache_key);
    }
    
    if (false === $found_ids || !$cache_aktif) {
        $found_ids = [];

        if ($exact) {
            // Barkod okuyucu için tam SKU eşleşmesi
            $found_ids = $wpdb->get_col($wpdb->prepare("
                SELECT pm.post_id FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_sku' AND pm.meta_value = %s 
                AND p.post_status = 'publish'
                LIMIT 10", $s));
        } else {
            // 1. Advanced Woo Search Entegrasyonu
            if (class_exists('AWS_Search')) {
                $aws_search = new AWS_Search();
                $aws_results = $aws_search->search($s);
                if (!empty($aws_results['products'])) {
                    foreach ($aws_results['products'] as $p_item) {
                        $found_ids[] = (int) $p_item['id'];
                    }
                }
            } else {
                // 2. Fallback Arama (Post Title ve SKU)
                $words = explode(' ', $s);
                $where_parts = [];
                foreach ($words as $word) {
                    if (empty($word) || mb_strlen($word) < 2)
                        continue;
                    $like = '%' . $wpdb->esc_like($word) . '%';
                    $where_parts[] = $wpdb->prepare("(p.post_title LIKE %s OR pm.meta_value LIKE %s)", $like, $like);
                }
                if (!empty($where_parts)) {
                    $where_clause = implode(' AND ', $where_parts);
                    $found_ids = $wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p 
                        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
                        WHERE p.post_status = 'publish' AND p.post_type IN ('product', 'product_variation') AND ($where_clause) LIMIT 30");
                }
            }
        }

        // Sadece ID listesini önbelleğe al (Stoklar hariç)
        if ($cache_aktif) {
            $ttl_mins = (int) get_option('hizli_kasa_search_cache_ttl', 5);
            set_transient($cache_key, $found_ids, $ttl_mins * MINUTE_IN_SECONDS);
        }
    }

    if (empty($found_ids))
        return [];

    // 3. Batch Hydration (Tek seferde verileri çek)
    $results_map = hizli_kasa_hydrate_products_batch($found_ids, $depo_id);

    // 4. In-Memory Resolution & Sorting
    $final_flat = [];
    $seen_parents = [];

    foreach ($found_ids as $fid) {
        if (!isset($results_map[$fid]))
            continue;

        $item = $results_map[$fid];
        $target_id = ($item['type'] === 'variation') ? $item['parent_id'] : $item['id'];

        if ($target_id > 0 && !isset($seen_parents[$target_id])) {
            // Ana ürünü bul ve ekle
            if (isset($results_map[$target_id])) {
                $parent_item = $results_map[$target_id];
                $final_flat[] = $parent_item;
                $seen_parents[$target_id] = true;

                // Bu ana ürünün TÜM varyasyonlarını hemen arkasına ekle (JS gruplama için flat liste gerekiyor)
                foreach ($results_map as $v) {
                    if ($v['parent_id'] === $target_id) {
                        $final_flat[] = $v;
                    }
                }
            }
        }
    }

    return array_values($final_flat);
}

/**
 * Depo Stok Kontrolü — Sipariş Onayı Öncesi Toplu Kontrol
 * 
 * Sepetteki tüm ürünlerin hem WooCommerce site stoğunu hem de
 * aktif depo stoğunu tek seferde kontrol eder.
 * 
 * POST /hizli-kasa/v1/warehouse-stock-check
 * Body: { items: [{product_id, variation_id, qty}], depo_id: X }
 */
function hizli_kasa_warehouse_stock_check($request)
{
    $data = $request->get_json_params();
    $items = $data['items'] ?? [];
    $depo_id = intval($data['depo_id'] ?? 0);

    if (empty($items)) {
        return new WP_Error('no_items', 'Kontrol edilecek ürün yok.', ['status' => 400]);
    }

    global $wpdb;
    $tables = Hizli_Kasa_Database::get_tables();
    $stok_table = $tables['stok_konumlari'];

    $results = [];

    foreach ($items as $item) {
        $product_id = intval($item['product_id'] ?? 0);
        $variation_id = intval($item['variation_id'] ?? 0);
        $requested_qty = intval($item['qty'] ?? 0);
        $target_id = $variation_id ?: $product_id;

        if (!$target_id)
            continue;

        // Ürün adını al
        $product = wc_get_product($target_id);
        $name = $product ? $product->get_name() : "Ürün #$target_id";

        // 1. WooCommerce site stoğu
        $site_stock = null;
        $manage_stock = false;
        $stock_status = 'instock';

        if ($product) {
            $manage_stock = $product->get_manage_stock();
            $site_stock = $manage_stock ? (float) $product->get_stock_quantity() : null;
            $stock_status = $product->get_stock_status();
        }

        // 2. Aktif depo stoğu ve rezervasyonu
        $depo_stock = 0;
        $depo_reserved = 0;
        if ($depo_id) {
            $stock_row = $wpdb->get_row($wpdb->prepare(
                "SELECT quantity, reserved FROM $stok_table WHERE product_id = %d AND variation_id = %d AND location_id = %d",
                $product_id,
                $variation_id,
                $depo_id
            ));
            if ($stock_row) {
                $depo_stock = (float) $stock_row->quantity;
                $depo_reserved = (float) $stock_row->reserved;
            }
        }

        // 3. Diğer depolardaki toplam stok ve rezervasyon
        $other_depo_stock = 0;
        $other_depo_reserved = 0;
        if ($depo_id) {
            $other_rows = $wpdb->get_row($wpdb->prepare(
                "SELECT COALESCE(SUM(quantity), 0) as total_qty, COALESCE(SUM(reserved), 0) as total_res FROM $stok_table WHERE product_id = %d AND variation_id = %d AND location_id != %d",
                $product_id,
                $variation_id,
                $depo_id
            ));
            if ($other_rows) {
                $other_depo_stock = (float) $other_rows->total_qty;
                $other_depo_reserved = (float) $other_rows->total_res;
            }
        }

        // 4. Kontrol sonuçları (Rezervasyon dahil)
        $site_ok = true;
        $depo_ok = true;
        $warning = null;

        $available_depo = $depo_stock - $depo_reserved;

        if ($manage_stock && $site_stock !== null) {
            $site_ok = ($requested_qty <= $site_stock);
        } elseif ($stock_status === 'outofstock') {
            $site_ok = false;
        }

        if ($depo_id) {
            $depo_ok = ($requested_qty <= $available_depo);
        }

        // Uyarı mesajları
        if ($site_ok && !$depo_ok) {
            if (($depo_stock + $other_depo_stock - $depo_reserved - $other_depo_reserved) >= $requested_qty) {
                $warning = "Sitede var ama bu depoda rezerve/yok — başka depoda gözüküyor!";
            } else {
                if ($depo_reserved > 0) {
                    $warning = "⚠️ Kritik Stok Uyarısı! Ürünün {$depo_reserved} adedi internet siparişleri için ayırtılmıştır. (Depoda Toplam: " . (int)$depo_stock . ")";
                } else {
                    $warning = "Depoda yetersiz stok! (Depo: " . (int) $depo_stock . ", İhtiyaç: $requested_qty)";
                }
            }
        } elseif (!$site_ok) {
            $warning = "Site stoğu yetersiz! (Site: " . ($site_stock !== null ? (int) $site_stock : 'N/A') . ", İhtiyaç: $requested_qty)";
        }

        $results[] = [
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'name' => $name,
            'site_stock' => $site_stock,
            'depo_stock' => (float) $depo_stock,
            'depo_reserved' => (float) $depo_reserved,
            'available_stock' => (float) $available_depo,
            'other_depo_stock' => (float) $other_depo_stock,
            'requested_qty' => $requested_qty,
            'site_ok' => $site_ok,
            'depo_ok' => $depo_ok,
            'warning' => $warning,
        ];
    }

    return $results;
}

/**
 * Barkod etiket verilerini döner.
 * Query: ?product_id=123&variation_id=456 (veya toplu için variation_ids=[1,2,3])
 */
function hizli_kasa_api_get_barcode_data($request)
{
    $product_id = intval($request->get_param('product_id'));
    $variation_id = intval($request->get_param('variation_id'));
    $variation_ids = $request->get_param('variation_ids');

    if (!empty($variation_ids) && is_array($variation_ids)) {
        $results = [];
        foreach ($variation_ids as $vid) {
            $data = Hizli_Kasa_Barcode_Helper::prepare_label_data($product_id, intval($vid));
            if ($data)
                $results[] = $data;
        }
        return $results;
    }

    $data = Hizli_Kasa_Barcode_Helper::prepare_label_data($product_id, $variation_id);
    if (!$data) {
        return new WP_Error('not_found', 'Ürün verisi bulunamadı.', ['status' => 404]);
    }

    return $data;
}

