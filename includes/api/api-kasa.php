<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/search', [
        'methods' => 'GET',
        'callback' => 'hizli_kasa_ozel_arama',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

    register_rest_route('hizli-kasa/v1', '/warehouse-stock-check', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_warehouse_stock_check',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

    register_rest_route('hizli-kasa/v1', '/barcode/label-data', [
        'methods' => 'GET',
        'callback' => 'hizli_kasa_api_get_barcode_data',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

});

/**
 * Özel ürün arama fonksiyonu (Performans Optimizasyonlu).
 */
function hizli_kasa_ozel_arama($data)
{
    global $wpdb;
    $s = sanitize_text_field($data['s']);
    if (empty($s)) {
        return [];
    }

    $depo_id = $data->get_param('depo_id');
    $exact = $data->get_param('exact');

    $found_ids = [];
    $cache_aktif = get_option('hizli_kasa_cache_aktif', '1') === '1';
    $cache_version = get_option('hizli_kasa_search_cache_version', '1');
    $cache_key = 'hk_search_v2_' . $cache_version . '_' . md5($s . '_' . $exact . '_' . (int) $depo_id);

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
                AND p.post_status IN ('publish', 'private')
                LIMIT 10", $s));
        } else {
            // AWS public indeksinden gelenleri, Hızlı Kasa'ya özel private-aware yerel aramayla birleştir.
            $found_ids = hizli_kasa_get_terminal_search_product_ids($s, (int) $depo_id);
        }

        // Sadece ID listesini önbelleğe al (Stoklar hariç)
        if ($cache_aktif) {
            $ttl_mins = (int) get_option('hizli_kasa_search_cache_ttl', 5);
            set_transient($cache_key, $found_ids, $ttl_mins * MINUTE_IN_SECONDS);
        }
    }

    if (empty($found_ids)) {
        return [];
    }

    // 3. Batch Hydration (Tek seferde verileri çek)
    $results_map = hizli_kasa_hydrate_products_batch($found_ids, $depo_id);

    // 4. In-Memory Resolution & Sorting
    $final_flat = [];
    $seen_parents = [];

    foreach ($found_ids as $fid) {
        if (!isset($results_map[$fid])) {
            continue;
        }

        $item = $results_map[$fid];
        $target_id = ($item['type'] === 'variation') ? $item['parent_id'] : $item['id'];

        // Ana ürünü bul ve ekle
        if ($target_id > 0 && !isset($seen_parents[$target_id]) && isset($results_map[$target_id])) {
            $parent_item = $results_map[$target_id];
            $final_flat[] = $parent_item;
            $seen_parents[$target_id] = true;
            // Bu ana ürünün TÜM varyasyonlarını ekle (aranan/eşleşen varyasyon en üstte çıksın)
            $exact_vars = [];
            $other_vars = [];
            foreach ($results_map as $v) {
                if ($v['parent_id'] === $target_id) {
                    $v_sku = (string) ($v['sku'] ?? '');
                    if ((int)$v['id'] === (int)$fid || ($s !== '' && strcasecmp($v_sku, $s) === 0)) {
                        $exact_vars[] = $v;
                    } else {
                        $other_vars[] = $v;
                    }
                }
            }
            foreach (array_merge($exact_vars, $other_vars) as $v) {
                $final_flat[] = $v;
            }
        }
    }

    return array_values($final_flat);
}

/**
 * Depo Stok Kontrolü — Sipariş Onayı Öncesi Toplu Kontrol
 *
 * Sepetteki tüm ürünlerin hem WooCommerce site stoğunu hem de aktif depo
 * stoğunu BATCH SQL ile tek seferde kontrol eder.
 *
 * Önceki implementasyon: N*3 DB sorgusu (her ürün için ayrı ayrı)
 * Bu implementasyon : sabit 3 DB sorgusu (ürün sayısından bağımsız)
 *
 * POST /hizli-kasa/v1/warehouse-stock-check
 * Body: { items: [{product_id, variation_id, qty}], depo_id: X }
 */
function hizli_kasa_warehouse_stock_check($request)
{
    $data    = $request->get_json_params();
    $items   = $data['items'] ?? [];
    $depo_id = intval($data['depo_id'] ?? 0);
    $order_id = intval($data['order_id'] ?? 0);

    if (empty($items)) {
        return new WP_Error('no_items', 'Kontrol edilecek ürün yok.', ['status' => 400]);
    }

    $order_qtys = [];
    if ($order_id > 0) {
        $order = wc_get_order($order_id);
        if ($order) {
            foreach ($order->get_items() as $item) {
                if ($item instanceof WC_Order_Item_Product) {
                    $pid = (int) $item->get_product_id();
                    $vid = (int) $item->get_variation_id();
                    $k = $pid . '_' . $vid;
                    $order_qtys[$k] = (float) $item->get_quantity();
                }
            }
        }
    }

    global $wpdb;
    $tables     = Hizli_Kasa_Database::get_tables();
    $stok_table = $tables['stok_konumlari'];

    // --- Girdi Normalizasyonu ---
    // target_id: variation_id varsa variation, yoksa product_id (WC meta'sı bu ID'ye yazılır)
    $target_ids = [];
    $item_map   = []; // target_id => [product_id, variation_id, qty]

    foreach ($items as $item) {
        $pid = intval($item['product_id'] ?? 0);
        $vid = intval($item['variation_id'] ?? 0);
        $qty = intval($item['qty'] ?? 0);
        if ($pid === 0) {
            continue;
        }
        $tid          = $vid ?: $pid;
        $target_ids[] = $tid;
        $item_map[$tid] = ['product_id' => $pid, 'variation_id' => $vid, 'qty' => $qty];
    }

    if ($target_ids === []) {
        return [];
    }

    $ids_placeholder = implode(',', array_map('intval', $target_ids));

    // =========================================================
    // BATCH 1 — Ürün başlıkları (posts tablosu)
    // Önceden: wc_get_product() ile her ürün için ayrı sorgu
    // =========================================================
    $name_rows = $wpdb->get_results(
        "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($ids_placeholder)"
    );
    $name_map = [];
    foreach ($name_rows as $row) {
        $name_map[(int) $row->ID] = $row->post_title;
    }

    // =========================================================
    // BATCH 2 — WooCommerce stok meta verileri (postmeta)
    // _manage_stock, _stock, _stock_status — tek sorguda hepsi
    // Önceden: her ürün için wc_get_product() içinde N meta sorgusu
    // =========================================================
    $meta_rows = $wpdb->get_results(
        "SELECT post_id, meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE post_id IN ($ids_placeholder)
           AND meta_key IN ('_manage_stock', '_stock', '_stock_status')"
    );
    $meta_map = []; // [post_id][meta_key] = meta_value
    foreach ($meta_rows as $row) {
        $meta_map[(int) $row->post_id][$row->meta_key] = $row->meta_value;
    }

    // =========================================================
    // BATCH 3 — Tüm depo stoklarını tek sorguda çek
    // PHP'de aktif depo / diğer depolar olarak ayır
    // Önceden: her ürün için 2 ayrı DB sorgusu (aktif + diğerleri)
    // =========================================================
    $all_product_ids = array_unique(array_column(array_values($item_map), 'product_id'));
    $all_pids_ph     = implode(',', array_map('intval', $all_product_ids));

    $depo_rows = $wpdb->get_results(
        "SELECT product_id, variation_id, location_id, quantity, reserved
         FROM $stok_table
         WHERE product_id IN ($all_pids_ph)"
    );

    $depo_stock_map = []; // aktif depo: "pid_vid" => {quantity, reserved}
    $other_depo_agg = []; // diğer depolar toplam: "pid_vid" => {total_qty, total_res}

    foreach ($depo_rows as $row) {
        $k = $row->product_id . '_' . $row->variation_id;
        if ((int) $row->location_id === $depo_id) {
            $depo_stock_map[$k] = [
                'quantity' => (float) $row->quantity,
                'reserved' => (float) $row->reserved,
            ];
        } else {
            if (!isset($other_depo_agg[$k])) {
                $other_depo_agg[$k] = ['total_qty' => 0.0, 'total_res' => 0.0];
            }
            $other_depo_agg[$k]['total_qty'] += (float) $row->quantity;
            $other_depo_agg[$k]['total_res'] += (float) $row->reserved;
        }
    }

    // =========================================================
    // In-memory değerlendirme — sıfır ek DB sorgusu
    // =========================================================
    $results = [];

    foreach ($item_map as $tid => $info) {
        $pid           = $info['product_id'];
        $vid           = $info['variation_id'];
        $requested_qty = $info['qty'];
        $k             = $pid . '_' . $vid;

        $name         = $name_map[$tid] ?? "Ürün #$tid";
        $meta         = $meta_map[$tid] ?? [];
        $manage_stock = isset($meta['_manage_stock']) && $meta['_manage_stock'] === 'yes';
        $site_stock   = $manage_stock ? (float) ($meta['_stock'] ?? 0) : null;
        $stock_status = $meta['_stock_status'] ?? 'instock';

        $depo_data     = $depo_stock_map[$k] ?? ['quantity' => 0.0, 'reserved' => 0.0];
        $depo_stock    = $depo_data['quantity'];
        $depo_reserved = $depo_data['reserved'];

        $other_data  = $other_depo_agg[$k] ?? ['total_qty' => 0.0, 'total_res' => 0.0];
        $other_stock = $other_data['total_qty'];
        $other_res   = $other_data['total_res'];

        $available_depo = $depo_stock - $depo_reserved;

        $site_ok = true;
        $depo_ok = true;
        $warning = null;

        $original_qty = $order_qtys[$k] ?? 0.0;
        $net_qty = $requested_qty - $original_qty;

        if ($net_qty > 0) {
            if ($manage_stock && $site_stock !== null) {
                $site_ok = ($net_qty <= $site_stock);
            } elseif ($stock_status === 'outofstock') {
                $site_ok = false;
            }

            if ($depo_id !== 0) {
                $depo_ok = ($net_qty <= $available_depo);
            }
        }

        if ($site_ok && !$depo_ok) {
            if (($depo_stock + $other_stock - $depo_reserved - $other_res) >= $net_qty) {
                $warning = "Sitede var ama bu depoda rezerve/yok — başka depoda gözüküyor!";
            } elseif ($depo_reserved > 0) {
                $warning = "⚠️ Kritik Stok Uyarısı! Ürünün {$depo_reserved} adedi internet siparişleri için ayırtılmıştır. (Depoda Toplam: " . (int) $depo_stock . ")";
            } else {
                $warning = "Depoda yetersiz stok! (Depo: " . (int) $depo_stock . ", İlave İhtiyaç: $net_qty)";
            }
        } elseif (!$site_ok) {
            $warning = "Site stoğu yetersiz! (Site: " . ($site_stock !== null ? (int) $site_stock : 'N/A') . ", İlave İhtiyaç: $net_qty)";
        }

        $results[] = [
            'product_id'       => $pid,
            'variation_id'     => $vid,
            'name'             => $name,
            'site_stock'       => $site_stock,
            'depo_stock'       => $depo_stock,
            'depo_reserved'    => $depo_reserved,
            'available_stock'  => $available_depo,
            'other_depo_stock' => $other_stock,
            'requested_qty'    => $requested_qty,
            'site_ok'          => $site_ok,
            'depo_ok'          => $depo_ok,
            'warning'          => $warning,
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
    $product_id    = intval($request->get_param('product_id'));
    $variation_id  = intval($request->get_param('variation_id'));
    $variation_ids = $request->get_param('variation_ids');

    if (!empty($variation_ids) && is_array($variation_ids)) {
        $results = [];
        foreach ($variation_ids as $vid) {
            $data = Hizli_Kasa_Barcode_Helper::prepare_label_data($product_id, intval($vid));
            if ($data) {
                $results[] = $data;
            }
        }
        return $results;
    }

    $data = Hizli_Kasa_Barcode_Helper::prepare_label_data($product_id, $variation_id);
    if (!$data) {
        return new WP_Error('not_found', 'Ürün verisi bulunamadı.', ['status' => 404]);
    }

    return $data;
}
