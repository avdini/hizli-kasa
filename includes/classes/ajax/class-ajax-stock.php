<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Ajax_Stock {
    public static function init() {
        add_action('wp_ajax_hizli_kasa_get_admin_stock_list', [self::class, 'get_list']);
        add_action('wp_ajax_hizli_kasa_admin_update_stock', [self::class, 'update']);
        add_action('wp_ajax_hizli_kasa_batch_update_stock', [self::class, 'batch_update']);
        add_action('wp_ajax_hizli_kasa_clear_stock_reservation', [self::class, 'clear_reservation']);
    }

public static function get_list() {
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    nocache_headers(); // Cache engelle
    try {
        $start_time = microtime(true);
        $queries_before = get_num_queries();

        hizli_kasa_admin_log("ADMIN_STOCK_LIST START");
        if (!current_user_can('manage_options')) {
            hizli_kasa_admin_log("Access denied for current user");
            wp_send_json_error(['message' => 'Yetkisiz erişim']);
        }

        global $wpdb;
        $stok_table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];
        $depo_table = Hizli_Kasa_Database::get_tables()['depolar'];
        $s = sanitize_text_field($_POST['s'] ?? '');
        $filter_mismatch = (isset($_POST['filter_mismatch']) && $_POST['filter_mismatch'] === 'true');
        $filter_zero_stock = (isset($_POST['filter_zero_stock']) && $_POST['filter_zero_stock'] === 'true');
        $filter_reserved = (isset($_POST['filter_reserved']) && $_POST['filter_reserved'] === 'true');
        $filter_depo_id = intval($_POST['depo_id'] ?? 0);
        $filter_depo_status = sanitize_text_field($_POST['depo_stock_status'] ?? 'all');
        $filter_min_stock = (isset($_POST['min_stock']) && $_POST['min_stock'] !== '') ? floatval($_POST['min_stock']) : null;
        $filter_max_stock = (isset($_POST['max_stock']) && $_POST['max_stock'] !== '') ? floatval($_POST['max_stock']) : null;
        $filter_product_type = sanitize_text_field($_POST['product_type'] ?? 'all');
        $paged = max(1, intval($_POST['paged'] ?? 1));
        $per_page = 24;
        $offset = ($paged - 1) * $per_page;

        $params = [];
        $where_sql = "p.post_type IN ('product', 'product_variation') AND p.post_status IN ('publish', 'private')";

        if ($s) {
            $like = '%' . $wpdb->esc_like($s) . '%';
            $where_sql .= " AND (p.post_title LIKE %s OR EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_bc 
                WHERE pm_bc.post_id = p.ID 
                AND pm_bc.meta_key IN ('_sku', '_barcode', '_gtin', '_ean') 
                AND pm_bc.meta_value LIKE %s
            ))";
            $params[] = $like; $params[] = $like;
        }

        if ($filter_product_type === 'simple') {
            $where_sql .= " AND (p.post_type = 'product' AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} as p_child WHERE p_child.post_parent = p.ID AND p_child.post_type = 'product_variation'))";
        } elseif ($filter_product_type === 'variation') {
            $where_sql .= " AND p.post_type = 'product_variation'";
        }

        $has_complex_filters = $filter_mismatch || $filter_zero_stock || $filter_reserved ||
                               ($filter_depo_id > 0) || ($filter_depo_status !== 'all') ||
                               ($filter_min_stock !== null) || ($filter_max_stock !== null) ||
                               ($filter_product_type !== 'all');

        if ($has_complex_filters) {
            $where_sql .= " AND (p.post_type = 'product_variation' OR (p.post_type = 'product' AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} as p_child WHERE p_child.post_parent = p.ID AND p_child.post_type = 'product_variation')))";
            
            if ($filter_zero_stock) {
                $where_sql .= " AND IF(p.post_type = 'product_variation', p.post_parent, p.ID) NOT IN (
                    SELECT IF(p2.post_type = 'product_variation', p2.post_parent, p2.ID)
                    FROM {$wpdb->posts} p2
                    INNER JOIN {$wpdb->postmeta} pm2 ON (p2.ID = pm2.post_id AND pm2.meta_key = '_stock')
                    WHERE p2.post_type IN ('product', 'product_variation') AND p2.post_status IN ('publish', 'private')
                    AND CAST(pm2.meta_value AS DECIMAL(15,4)) > 0
                )";
            }

            $having_clauses = [];
            if ($filter_mismatch) {
                $having_clauses[] = "(total_wh_stock != wc_stock OR min_wh_stock < 0)";
            }
            if ($filter_reserved) {
                $having_clauses[] = "total_reserved_stock > 0";
            }
            if ($filter_depo_status === 'in_stock' || ($filter_depo_id > 0 && $filter_depo_status === 'all')) {
                $having_clauses[] = "total_wh_stock > 0";
            } elseif ($filter_depo_status === 'out_of_stock') {
                $having_clauses[] = "total_wh_stock <= 0";
            } elseif ($filter_depo_status === 'negative') {
                $having_clauses[] = "total_wh_stock < 0";
            }
            if ($filter_min_stock !== null) {
                $having_clauses[] = "total_wh_stock >= " . floatval($filter_min_stock);
            }
            if ($filter_max_stock !== null) {
                $having_clauses[] = "total_wh_stock <= " . floatval($filter_max_stock);
            }

            $having_sql = $having_clauses === [] ? "" : "HAVING " . implode(" AND ", $having_clauses);
            
            $depo_join_sql = "";
            if ($filter_depo_id > 0) {
                $depo_join_sql = $wpdb->prepare(" AND sk.location_id = %d", $filter_depo_id);
            }

            $base_sql = "
                SELECT 
                    (CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END) as main_id,
                    COALESCE(CAST(pm_stock.meta_value AS DECIMAL(15,4)), 0) as wc_stock,
                    COALESCE(SUM(sk.quantity - sk.reserved), 0) as total_wh_stock,
                    COALESCE(MIN(sk.quantity - sk.reserved), 0) as min_wh_stock,
                    COALESCE(SUM(sk.reserved), 0) as total_reserved_stock
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_sku ON (p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku')
                LEFT JOIN {$wpdb->postmeta} pm_stock ON (p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock')
                LEFT JOIN $stok_table sk ON sk.variation_id = IF(p.post_type = 'product_variation', p.ID, 0) AND sk.product_id = IF(p.post_type = 'product_variation', p.post_parent, p.ID) {$depo_join_sql}
                WHERE $where_sql
                GROUP BY p.ID
                $having_sql";
        } else {
            $base_sql = "
                SELECT DISTINCT (CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END) as main_id
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_sku ON (p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku')
                WHERE $where_sql";
        }

        // Toplam sayıyı bul
        $total_items = $wpdb->get_var($params === [] ? "SELECT COUNT(DISTINCT main_id) FROM ($base_sql) as t" : $wpdb->prepare("SELECT COUNT(DISTINCT main_id) FROM ($base_sql) as t", $params));
        
        // Sayfalanmış ana ID'leri çek
        $main_ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT main_id FROM ($base_sql) as ids ORDER BY main_id DESC LIMIT %d OFFSET %d", array_merge($params, [$per_page, $offset])));
        
        hizli_kasa_admin_log("Main IDs Found: " . count($main_ids));
        if (!empty($main_ids)) {
            hizli_kasa_admin_log("Main IDs: " . implode(',', $main_ids));
        }

        if ($wpdb->last_error) {
            hizli_kasa_admin_log("SQL Error: " . $wpdb->last_error);
            wp_send_json_error(['message' => 'Veritabanı hatası: ' . $wpdb->last_error]);
        }

        $t_ids_fetched = microtime(true);

        if (empty($main_ids)) {
            wp_send_json_success(['products' => [], 'total_pages' => 0]);
        }

        // ADIM 2: Detayları Topla (Ana ürünler + Onların tüm varyasyonları)
        $main_placeholders = implode(',', array_fill(0, count($main_ids), '%d'));
        
        // Varyasyonları bul
        $variation_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_parent IN ($main_placeholders)", $main_ids));
        
        $all_target_ids = array_unique(array_merge($main_ids, $variation_ids));
        $all_placeholders = implode(',', array_fill(0, count($all_target_ids), '%d'));

        // Metataları çek (Nitelikler ve Fiyatlar dahil)
        $meta_results = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
            WHERE post_id IN ($all_placeholders) 
            AND (meta_key IN ('_sku', '_stock', '_price', '_regular_price', '_sale_price', '_thumbnail_id', '_product_attributes') OR meta_key LIKE 'attribute_%%')
        ", $all_target_ids));
        
        $metas_by_id = [];
        $tax_slug_map = [];
        foreach ($meta_results as $m) { 
            $metas_by_id[$m->post_id][$m->meta_key] = $m->meta_value; 
            if (strpos($m->meta_key, 'attribute_') === 0 && $m->meta_value) {
                $tax = str_replace('attribute_', '', $m->meta_key);
                $tax_slug_map[$tax][] = $m->meta_value;
            }
        }

        $t_meta_fetched = microtime(true);

        // Term isimlerini çöz (Sıralama için) - Toplu Batch Çekim
        $term_names = [];
        foreach ($tax_slug_map as $tax => $slugs) {
            $slugs = array_values(array_unique($slugs));
            if (!empty($slugs)) {
                $terms = get_terms([
                    'taxonomy'   => $tax,
                    'slug'       => $slugs,
                    'hide_empty' => false,
                ]);
                if (!is_wp_error($terms)) {
                    foreach ($terms as $t) {
                        $term_names[$tax][$t->slug] = $t->name;
                    }
                }
            }
        }

        // Post detaylarını çek
        $p_details = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_type, post_parent FROM {$wpdb->posts} WHERE ID IN ($all_placeholders)", $all_target_ids));
        $details_by_id = [];
        foreach ($p_details as $pd) { $details_by_id[$pd->ID] = $pd; }

        // ADIM 3: Depo Stoklarını Topla
        $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
        $stock_results = $wpdb->get_results($wpdb->prepare("SELECT location_id, product_id, variation_id, quantity, reserved FROM $stok_table WHERE (product_id IN ($all_placeholders) OR variation_id IN ($all_placeholders))", array_merge($all_target_ids, $all_target_ids)));

        $stocks_by_loc = [];
        $reserved_by_loc = [];
        foreach ($stock_results as $sr) {
            $key = ($sr->variation_id > 0) ? "v_{$sr->variation_id}" : "p_{$sr->product_id}";
            $stocks_by_loc[$sr->location_id][$key] = (float)$sr->quantity;
            $reserved_by_loc[$sr->location_id][$key] = (float)$sr->reserved;
        }
        $t_stocks_fetched = microtime(true);

    $output = [];
    foreach ($main_ids as $m_id) {
        $parent = $details_by_id[$m_id] ?? null;
        if (!$parent) {
            continue;
        }

        $m = $metas_by_id[$m_id] ?? [];
        $thumb_id = $m['_thumbnail_id'] ?? 0;
        $thumbnail = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : wc_placeholder_img_src();

        // Varyasyonları yapılandır
        $children = [];
        foreach ($variation_ids as $v_id) {
            $v_post = $details_by_id[$v_id] ?? null;
            if (!$v_post || $v_post->post_parent != $m_id) {
                continue;
            }

            $vm = $metas_by_id[$v_id] ?? [];
            $v_thumb_id = $vm['_thumbnail_id'] ?? 0;
            $v_thumbnail = $v_thumb_id ? wp_get_attachment_image_url($v_thumb_id, 'thumbnail') : wc_placeholder_img_src();

            $clean_attrs = [];
            foreach ($vm as $ak => $av) {
                if (strpos($ak, 'attribute_') === 0) {
                    $tax = str_replace('attribute_', '', $ak);
                    $clean_k = str_replace('pa_', '', $tax);
                    $clean_attrs[$clean_k] = $term_names[$tax][$av] ?? $av;
                }
            }

            $v_item = [
                'id' => $m_id,
                'variation_id' => $v_id,
                'name' => $v_post->post_title,
                'sku' => $vm['_sku'] ?? '',
                'price' => (float)($vm['_price'] ?? 0),
                'regular_price' => (float)($vm['_regular_price'] ?? $vm['_price'] ?? 0),
                'sale_price' => (isset($vm['_sale_price']) && $vm['_sale_price'] !== '') ? (float)$vm['_sale_price'] : null,
                'wc_stock' => (float)($vm['_stock'] ?? 0),
                'thumbnail' => $v_thumbnail,
                'attributes' => $clean_attrs,
                'warehouse_stocks' => []
            ];
            foreach ($depolar as $d) {
                $qty = $stocks_by_loc[$d->id]["v_{$v_id}"] ?? 0;
                $res = $reserved_by_loc[$d->id]["v_{$v_id}"] ?? 0;
                $v_item['warehouse_stocks'][] = [
                    'depo_id' => $d->id,
                    'qty' => (float)$qty,
                    'reserved' => (float)$res
                ];
            }
            
            // Mismatch kontrolü (Net stok = Toplam fiziksel - Toplam rezerve)
            $v_total_wh = array_sum(array_column($v_item['warehouse_stocks'], 'qty'));
            $v_total_reserved = array_sum(array_column($v_item['warehouse_stocks'], 'reserved'));
            $v_net_wh = $v_total_wh - $v_total_reserved;
            $v_item['total_warehouse_stock'] = $v_total_wh;
            $v_item['total_reserved_stock'] = $v_total_reserved;
            $v_item['has_mismatch'] = (round((float)$v_net_wh, 4) !== round($v_item['wc_stock'], 4));

            $children[] = $v_item;
        }

        if ($children !== []) {
            hizli_kasa_sort_variations($children, $s);
        }

        $item = [
            'id' => $m_id,
            'variation_id' => 0,
            'name' => $parent->post_title,
            'sku' => $m['_sku'] ?? '',
            'price' => (float)($m['_price'] ?? 0),
            'regular_price' => (float)($m['_regular_price'] ?? $m['_price'] ?? 0),
            'sale_price' => (isset($m['_sale_price']) && $m['_sale_price'] !== '') ? (float)$m['_sale_price'] : null,
            'wc_stock' => (float)($m['_stock'] ?? 0),
            'thumbnail' => $thumbnail,
            'type' => $children === [] ? 'simple' : 'variable',
            'variations' => $children,
            'warehouse_stocks' => []
        ];

        foreach ($depolar as $d) {
            $qty = $stocks_by_loc[$d->id]["p_{$m_id}"] ?? 0;
            $res = $reserved_by_loc[$d->id]["p_{$m_id}"] ?? 0;
            $item['warehouse_stocks'][] = [
                'depo_id' => $d->id,
                'qty' => (float)$qty,
                'reserved' => (float)$res
            ];
        }

        // Mismatch kontrolü (Basit ürün için veya değişken ürünün genel durumu için)
        $total_wh = array_sum(array_column($item['warehouse_stocks'], 'qty'));
        $total_reserved = array_sum(array_column($item['warehouse_stocks'], 'reserved'));
        $net_wh = $total_wh - $total_reserved;
        $item['total_warehouse_stock'] = $total_wh;
        $item['total_reserved_stock'] = $total_reserved;
        
        if ($item['type'] === 'simple') {
            $item['has_mismatch'] = (round((float)$net_wh, 4) !== round($item['wc_stock'], 4));
        } else {
            // Değişken üründe herhangi bir varyasyonda uyuşmazlık varsa true dön
            $item['has_mismatch'] = false;
            foreach($children as $child) {
                if ($child['has_mismatch']) {
                    $item['has_mismatch'] = true;
                    break;
                }
            }
        }

        $output[] = $item;
    }

    $t_tree_assembled = microtime(true);
    $boot_time = defined('HIZLI_KASA_BOOT_TIME') ? HIZLI_KASA_BOOT_TIME : $start_time;

    $stats = self::get_stock_stats();
    $t_stats_fetched = microtime(true);

    $perf_breakdown = [
        'wp_bootup_ms'         => round(($start_time - $boot_time) * 1000, 2),
        'db_product_ids_ms'    => round(($t_ids_fetched - $start_time) * 1000, 2),
        'db_postmeta_ms'       => round(($t_meta_fetched - $t_ids_fetched) * 1000, 2),
        'db_stocks_terms_ms'   => round(($t_stocks_fetched - $t_meta_fetched) * 1000, 2),
        'php_tree_assembly_ms' => round(($t_tree_assembled - $t_stocks_fetched) * 1000, 2),
        'db_stats_ms'          => round(($t_stats_fetched - $t_tree_assembled) * 1000, 2),
        'total_server_time_ms' => round(($t_stats_fetched - $boot_time) * 1000, 2),
        'db_queries'           => get_num_queries() - $queries_before
    ];

    hizli_kasa_admin_log("Final Output Prepared. Count: " . count($output));

    wp_send_json_success([
        'products'    => $output,
        'total_pages' => ceil($total_items / $per_page),
        'stats'       => $stats,
        'perf'        => $perf_breakdown
    ]);
    hizli_kasa_admin_log("Response Sent Successfully. Total: {$total_time}ms | Exec: {$exec_time}ms");

    } catch (Exception $e) {
        hizli_kasa_admin_log("AJAX Hatası: " . $e->getMessage());
        wp_send_json_error(['message' => 'İstisnai bir hata oluştu: ' . $e->getMessage()]);
    }
}

/**
 * Canlı Stok Metrik İstatistikleri (Toplam SKU, Uyuşmazlık)
 */
public static function get_stock_stats() {
    $cached = get_transient('hk_admin_stock_stats_v2');
    if ($cached !== false && is_array($cached) && isset($cached['reserved_sku'])) {
        return $cached;
    }

    global $wpdb;
    $stok_table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];

    $total_sku = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} 
         WHERE post_type IN ('product', 'product_variation') 
         AND post_status IN ('publish', 'private')"
    );

    $mismatch = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM (
            SELECT p.ID,
                   CAST(COALESCE(pm.meta_value, 0) AS SIGNED) AS wc_stock,
                   COALESCE(wh.total_wh_stock, 0) AS total_wh_stock
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_stock')
            LEFT JOIN (
                SELECT 
                    (CASE WHEN variation_id > 0 THEN variation_id ELSE product_id END) AS item_id,
                    COALESCE(SUM(quantity - reserved), 0) AS total_wh_stock
                FROM {$stok_table}
                GROUP BY item_id
            ) wh ON p.ID = wh.item_id
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status IN ('publish', 'private')
            AND (p.post_type = 'product_variation' OR (p.post_type = 'product' AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} p_child WHERE p_child.post_parent = p.ID AND p_child.post_type = 'product_variation')))
            HAVING wc_stock != total_wh_stock
        ) AS mismatch_subquery"
    );

    $reserved_data = $wpdb->get_row(
        "SELECT 
            COUNT(DISTINCT (CASE WHEN variation_id > 0 THEN variation_id ELSE product_id END)) as reserved_sku,
            COALESCE(SUM(reserved), 0) as reserved_qty
         FROM {$stok_table}
         WHERE reserved > 0"
    );

    $stats = [
        'total'        => $total_sku,
        'mismatch'     => $mismatch,
        'reserved_sku' => $reserved_data ? (int) $reserved_data->reserved_sku : 0,
        'reserved_qty' => $reserved_data ? (float) $reserved_data->reserved_qty : 0,
    ];

    set_transient('hk_admin_stock_stats_v2', $stats, 120);
    return $stats;
}

/**
 * Manuel Stok Güncelleme
 */
public static function update() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz erişim!']);
    }

    $pid    = intval($_POST['product_id']);
    $vid    = intval($_POST['variation_id']);
    $did    = intval($_POST['depo_id']);
    $change = intval($_POST['change']);

    if (!$did || !$pid) {
        wp_send_json_error(['message' => 'Eksik veri!']);
    }

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    
    // Stok Güncelle (Stock Manager metodunu kullan ki log tutulsun)
    // variation_id 0 ise basit ürün, değilse varyasyondur.
    // product_id her zaman parent ID (veya basit ürün ID) olmalıdır.
    
    $change = isset($_POST['change']) ? floatval($_POST['change']) : 0;
    $set_qty = isset($_POST['set_qty']) ? sanitize_text_field($_POST['set_qty']) : null;

    global $wpdb;
    $tables = Hizli_Kasa_Database::get_tables();
    $table = $tables['stok_konumlari'];

    $current = $wpdb->get_var($wpdb->prepare(
        "SELECT quantity FROM $table WHERE location_id = %d AND product_id = %d AND variation_id = %d",
        $did, ($vid > 0 ? get_post_field('post_parent', $vid) : $pid), $vid
    )) ?: 0;
    $current = floatval($current);

    // Akıllı miktar belirleme (Smart Syntax)
    if ($set_qty !== null && $set_qty !== '') {
        $new_val = floatval($set_qty);
        $change = $new_val - $current;
    }

    $new_qty = $current + $change;
    if ($new_qty < 0) {
        $new_qty = 0;
    }

    $user = wp_get_current_user();
    $reason = "Admin Manuel Müdahale (Kullanıcı: " . $user->display_name . ")";

    Hizli_Kasa_Stock_Manager::update_warehouse_stock(
        ($vid > 0 ? get_post_field('post_parent', $vid) : $pid), 
        $vid, 
        $did, 
        $change, 
        $reason
    );

    wp_send_json_success(['new_qty' => $new_qty]);
}

/**
 * Toplu (Batch) Stok Güncelleme
 */
public static function batch_update() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz erişim!']);
    }
    
    $changes = json_decode(stripslashes($_POST['changes']), true);
    if (!is_array($changes)) {
        wp_send_json_error(['message' => 'Geçersiz veri']);
    }
    
    $updated = 0;
    $errors  = [];
    
    foreach ($changes as $c) {
        $type   = sanitize_text_field($c['type']); // 'warehouse' | 'wc_stock'
        $pid    = intval($c['pid']);
        $vid    = intval($c['vid']);
        $newQty = floatval($c['new_qty']);
        
        if ($type === 'wc_stock') {
            // WooCommerce site stoğunu güncelle — log tutulmaz, WC kendi hook'larını çalıştırır
            $target_id = $vid > 0 ? $vid : $pid;
            wc_update_product_stock($target_id, $newQty, 'set');
            $updated++;
        } elseif ($type === 'warehouse') {
            $did = intval($c['did']);
            // Mevcut stok değerini al, farkı hesapla
            global $wpdb;
            $table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];
            $parent_id = $vid > 0 ? get_post_field('post_parent', $vid) : $pid;
            $current = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT quantity FROM $table WHERE location_id=%d AND product_id=%d AND variation_id=%d",
                $did, $parent_id, $vid
            ));
            $change = $newQty - $current;
            // Depo stok güncellemesi — stok_hareketleri tablosuna log düşer
            Hizli_Kasa_Stock_Manager::update_warehouse_stock(
                $parent_id, $vid, $did, $change,
                "Admin Batch Güncelleme"
            );
            $updated++;
        }
    }
    
    delete_transient('hk_admin_stock_stats_v2');
    wp_send_json_success(['updated' => $updated, 'errors' => $errors]);
}

/**
 * Askıda Kalan Stok Rezervasyonunu Manuel Sıfırla
 */
public static function clear_reservation() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Yetkisiz erişim!']);
    }

    global $wpdb;
    $stok_table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];

    $product_id   = intval($_POST['product_id'] ?? 0);
    $variation_id = intval($_POST['variation_id'] ?? 0);
    $location_id  = intval($_POST['location_id'] ?? 0);

    if (!$product_id) {
        wp_send_json_error(['message' => 'Geçersiz ürün ID']);
    }

    $where = [
        'product_id'   => $product_id,
        'variation_id' => $variation_id
    ];
    if ($location_id > 0) {
        $where['location_id'] = $location_id;
    }

    $updated = $wpdb->update($stok_table, ['reserved' => 0], $where);

    if ($updated !== false) {
        hizli_kasa_log("Manuel Rezervasyon Sıfırlandı: P:$product_id, V:$variation_id, L:$location_id (User: " . get_current_user_id() . ")");
        if (class_exists('Hizli_Kasa_Mismatch_Notifier')) {
            Hizli_Kasa_Mismatch_Notifier::reset_status();
        }
        delete_transient('hk_admin_stock_stats_v2');
        wp_send_json_success(['message' => 'Stok rezervasyonu başarıyla temizlendi.']);
    } else {
        wp_send_json_error(['message' => 'Veritabanı güncelleme hatası.']);
    }
}
}
