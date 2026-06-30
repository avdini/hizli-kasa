<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {
    register_rest_route('hizli-kasa/v1', '/terminal/products', [
        'methods' => 'GET',
        'callback' => 'hizli_kasa_terminal_products',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

    register_rest_route('hizli-kasa/v1', '/terminal/update-stock', [
        'methods' => 'POST',
        'callback' => 'hizli_kasa_terminal_update_stock',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

    register_rest_route('hizli-kasa/v1', '/terminal/filters', [
        'methods' => 'GET',
        'callback' => 'hizli_kasa_terminal_get_filters',
        'permission_callback' => fn() => hizli_kasa_can_access_app()
    ]);

});

/**
 * Terminal/Stok Yönetimi sayfası için ürünleri listeler.
 */
function hizli_kasa_terminal_products($request)
{
    // Önbelleği kesin olarak engelle (Tarayıcı ve Sunucu tarafı)
    if (!headers_sent()) {
        nocache_headers();
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
    }

    global $wpdb;
    $limit = intval($request->get_param('limit') ?: 24);
    $offset = intval($request->get_param('offset') ?: 0);
    $depo_id = intval($request->get_param('depo_id'));
    $s = sanitize_text_field($request->get_param('s'));
    $cat_id = intval($request->get_param('cat'));
    $brand_id = intval($request->get_param('brand'));
    $stock_status = sanitize_text_field($request->get_param('stock_status'));

    $threshold = (int) get_option('hizli_kasa_kritik_stok_esigi', 5);
    $stok_table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';

    $where = "p.post_status IN ('publish', 'private') AND p.post_type = 'product'";
    $join_extra = "";

    // --- Kategori Filtresi ---
    if ($cat_id > 0) {
        $join_extra .= " INNER JOIN {$wpdb->term_relationships} tr_cat ON (p.ID = tr_cat.object_id)";
        $join_extra .= $wpdb->prepare(" INNER JOIN {$wpdb->term_taxonomy} tt_cat ON (tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id AND tt_cat.taxonomy = 'product_cat' AND tt_cat.term_id = %d)", $cat_id);
    }

    // --- Marka Filtresi ---
    if ($brand_id > 0) {
        $brand_tax = 'product_brand';
        if (!taxonomy_exists($brand_tax)) {
            if (taxonomy_exists('pwb-brand')) {
                $brand_tax = 'pwb-brand';
            } elseif (taxonomy_exists('brand')) {
                $brand_tax = 'brand';
            }
        }
        $join_extra .= " INNER JOIN {$wpdb->term_relationships} tr_brand ON (p.ID = tr_brand.object_id)";
        $join_extra .= $wpdb->prepare(" INNER JOIN {$wpdb->term_taxonomy} tt_brand ON (tr_brand.term_taxonomy_id = tt_brand.term_taxonomy_id AND tt_brand.taxonomy = %s AND tt_brand.term_id = %d)", $brand_tax, $brand_id);
    }

    if ($depo_id !== 0) {
        $stock_join_type = (!empty($s) || $cat_id > 0 || $brand_id > 0) ? 'LEFT JOIN' : 'INNER JOIN';
        
        // Stok Durumu Filtresi
        if (!empty($stock_status) && $stock_status !== 'all') {
            $stock_join_type = 'INNER JOIN'; // Filtre varsa eşleşme zorunlu
            switch($stock_status) {
                case 'instock':
                    $where .= " AND sk_filter.quantity > 0";
                    break;
                case 'lowstock':
                    $where .= $wpdb->prepare(" AND sk_filter.quantity > 0 AND sk_filter.quantity <= %d", $threshold);
                    break;
                case 'outofstock':
                    $where .= " AND (sk_filter.quantity <= 0 OR sk_filter.quantity IS NULL)";
                    break;
            }
        }
        
        $join_extra .= $wpdb->prepare(" $stock_join_type $stok_table sk_filter ON (sk_filter.product_id = p.ID AND sk_filter.location_id = %d)", $depo_id);
    }

    $params = [];
    $search_ids = [];

    // --- Sıralama Ayarları ---
    $orderby = $request->get_param('orderby');
    $order   = strtoupper($request->get_param('order') ?: 'DESC');
    if (!in_array($order, ['ASC', 'DESC'])) {
        $order = 'DESC';
    }

    // Varsayılan: Yayın Tarihi (Yeni -> Eski)
    $order_by = "p.post_date DESC";

    if (!empty($s)) {
        $search_ids = hizli_kasa_get_terminal_search_product_ids($s, $depo_id);

        if (!empty($search_ids)) {
            $ids_ph = implode(',', array_map('intval', $search_ids));
            $where .= " AND p.ID IN ($ids_ph)";
            // Arama yapıldığında ve özel sıralama seçilmediğinde alaka düzeyine göre sırala
            if (empty($orderby)) {
                $order_by = "FIELD(p.ID, $ids_ph)";
            }
        } else {
            $where .= " AND p.ID = 0";
        }
    }

    // Özel sıralama seçilmişse (veya arama yoksa varsayılanlar)
    if (!empty($orderby)) {
        switch ($orderby) {
            case 'title':
                $order_by = "p.post_title $order";
                break;
            case 'stock':
                $order_by = "sk_filter.quantity $order";
                break;
            case 'price':
                $join_extra .= " LEFT JOIN {$wpdb->postmeta} pm_price ON (pm_price.post_id = p.ID AND pm_price.meta_key = '_price')";
                $order_by = "pm_price.meta_value+0 $order";
                break;
            case 'date':
                $order_by = "p.post_date $order";
                break;
        }
    }

    $total_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p $join_extra WHERE $where";
    $total = $wpdb->get_var($params === [] ? $total_query : $wpdb->prepare($total_query, ...$params));
    if (!$total) {
        return ['products' => [], 'total' => 0, 'has_more' => false, 'simple_count' => 0, 'variable_count' => 0, 'grand_total_items' => 0, 'critical_count' => 0];
    }

    $id_query = $wpdb->prepare("
        SELECT DISTINCT p.ID FROM {$wpdb->posts} p $join_extra WHERE $where ORDER BY $order_by LIMIT %d OFFSET %d", array_merge($params, [$limit, $offset]));
    $target_ids = $wpdb->get_col($id_query);

    if (empty($target_ids)) {
        return ['products' => [], 'total' => (int) $total, 'has_more' => false, 'simple_count' => 0, 'variable_count' => 0, 'grand_total_items' => 0, 'critical_count' => 0];
    }

    $placeholders = implode(',', array_fill(0, count($target_ids), '%d'));
    $sql = $wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_type, p.post_parent,
               tt_type.slug as product_type, pm_thumb.meta_value as thumbnail_id, sk_main.quantity as warehouse_stock,
               MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
               MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
               MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
               MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) as stock_status,
               MAX(CASE WHEN pm.meta_key = '_manage_stock' THEN pm.meta_value END) as manage_stock,
               MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity
        FROM {$wpdb->posts} p
        LEFT JOIN $stok_table sk_main ON (sk_main.product_id = p.ID AND sk_main.location_id = %d AND sk_main.variation_id = 0)
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        LEFT JOIN {$wpdb->postmeta} pm_thumb ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
        LEFT JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id
        LEFT JOIN {$wpdb->term_taxonomy} tt_tax ON tr_type.term_taxonomy_id = tt_tax.term_taxonomy_id AND tt_tax.taxonomy = 'product_type'
        LEFT JOIN {$wpdb->terms} tt_type ON tt_tax.term_id = tt_type.term_id
        WHERE p.ID IN ($placeholders)
        GROUP BY p.ID
        ORDER BY FIELD(p.ID, " . implode(',', array_map('intval', $target_ids)) . ")
    ", array_merge([$depo_id], $target_ids));

    $results = $wpdb->get_results($sql);
    $parent_ids = wp_list_pluck($results, 'ID');
    $variations_by_parent = [];
    if (!empty($parent_ids)) {
        $ids_placeholders = implode(',', array_fill(0, count($parent_ids), '%d'));
        $v_results = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_parent, p.post_title, sk.quantity as warehouse_stock,
                   MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
                   MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
                   MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) as regular_price,
                   MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock_quantity,
                   MAX(CASE WHEN pm.meta_key = '_thumbnail_id' THEN pm.meta_value END) as thumbnail_id
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            LEFT JOIN $stok_table sk ON (sk.variation_id = p.ID AND sk.location_id = %d)
            WHERE p.post_type = 'product_variation' AND p.post_status IN ('publish', 'private') AND p.post_parent IN ($ids_placeholders)
            GROUP BY p.ID
        ", array_merge([$depo_id], $parent_ids)));

        // --- Multi-Warehouse Stock & Code Fetch ---
        $all_warehouses = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}hizli_kasa_depolar ORDER BY priority DESC");
        $all_item_ids = array_merge($parent_ids, empty($v_results) ? [] : wp_list_pluck($v_results, 'ID'));
        $all_stocks = [];
        $all_codes = [];
        if ($all_item_ids !== []) {
            $ids_ph_all = implode(',', array_fill(0, count($all_item_ids), '%d'));
            $stocks_raw = $wpdb->get_results($wpdb->prepare("
                SELECT location_id, product_id, variation_id, quantity, depo_kodu 
                FROM $stok_table 
                WHERE (product_id IN ($ids_ph_all) OR variation_id IN ($ids_ph_all))
            ", array_merge($all_item_ids, $all_item_ids)));

            if (!empty($stocks_raw)) {
                foreach ($stocks_raw as $sr) {
                    $item_id = ($sr->variation_id > 0) ? (int)$sr->variation_id : (int)$sr->product_id;
                    $all_stocks[$item_id][$sr->location_id] = (float)$sr->quantity;
                    if (!empty($sr->depo_kodu)) {
                        $all_codes[$item_id][$sr->location_id] = $sr->depo_kodu;
                    }
                }
            }
        }

        if (!empty($v_results)) {
            // --- Özellikleri Toplu Çek ve İsimlerini Çöz ---
            $v_ids = wp_list_pluck($v_results, 'ID');
            $v_ids_ph = implode(',', array_fill(0, count($v_ids), '%d'));
            $v_meta_raw = $wpdb->get_results($wpdb->prepare("
                SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
                WHERE post_id IN ($v_ids_ph) AND meta_key LIKE 'attribute_%'
            ", $v_ids));

            $v_meta_map = [];
            $tax_slug_map = [];
            foreach ($v_meta_raw as $m) {
                $v_meta_map[$m->post_id][$m->meta_key] = $m->meta_value;
                $tax = str_replace('attribute_', '', $m->meta_key);
                if ($tax && $m->meta_value) {
                    $tax_slug_map[$tax][] = $m->meta_value;
                }
            }

            // Term isimlerini çöz
            $term_names = [];
            foreach ($tax_slug_map as $tax => $slugs) {
                $slugs = array_unique($slugs);
                foreach ($slugs as $slug) {
                    $term = get_term_by('slug', $slug, $tax);
                    $term_names[$tax][$slug] = $term ? $term->name : $slug;
                }
            }

            foreach ($v_results as $v) {
                $raw_attrs = $v_meta_map[$v->ID] ?? [];
                $clean_attrs = [];
                foreach ($raw_attrs as $ak => $av) {
                    $tax = str_replace('attribute_', '', $ak);
                    $clean_k = str_replace('pa_', '', $tax); // pa_renk -> renk
                    $clean_attrs[$clean_k] = $term_names[$tax][$av] ?? $av;
                }
                $v->attributes = $clean_attrs;
                $v->all_stocks = (object) ($all_stocks[$v->ID] ?? []);
                $v->all_codes = (object) ($all_codes[$v->ID] ?? []);
                $variations_by_parent[$v->post_parent][] = $v;
            }

            // --- Sıralama Mantığı: Renk -> Beden/Numara -> Başlık ---
            foreach ($variations_by_parent as &$variation_rows) {
                usort($variation_rows, function ($a, $b) use ($s) {
                    // 1. Arama Puanı (Eğer arama yapılıyorsa)
                    if (!empty($s)) {
                        $needle = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
                        $a_name = function_exists('mb_strtolower') ? mb_strtolower((string) $a->post_title) : strtolower((string) $a->post_title);
                        $b_name = function_exists('mb_strtolower') ? mb_strtolower((string) $b->post_title) : strtolower((string) $b->post_title);
                        $a_sku = function_exists('mb_strtolower') ? mb_strtolower((string) $a->sku) : strtolower((string) $a->sku);
                        $b_sku = function_exists('mb_strtolower') ? mb_strtolower((string) $b->sku) : strtolower((string) $b->sku);

                        $a_score = ((strpos($a_sku, $needle) !== false) ? 20 : 0) + ((strpos($a_name, $needle) !== false) ? 10 : 0);
                        $b_score = ((strpos($b_sku, $needle) !== false) ? 20 : 0) + ((strpos($b_name, $needle) !== false) ? 10 : 0);

                        if ($a_score !== $b_score) {
                            return $b_score <=> $a_score;
                        }
                    }

                    // 2. Renk ve Beden/Numara Bazlı Sıralama
                    $attrs_a = $a->attributes ?? [];
                    $attrs_b = $b->attributes ?? [];

                    $color_a = ''; $size_a = '';
                    $color_b = ''; $size_b = '';

                    foreach ($attrs_a as $k => $val) {
                        $k_low = strtolower($k);
                        if (strpos($k_low, 'renk') !== false || strpos($k_low, 'color') !== false) {
                            $color_a = $val;
                        }
                        if (strpos($k_low, 'beden') !== false || strpos($k_low, 'size') !== false || strpos($k_low, 'numara') !== false) {
                            $size_a = $val;
                        }
                    }
                    foreach ($attrs_b as $k => $val) {
                        $k_low = strtolower($k);
                        if (strpos($k_low, 'renk') !== false || strpos($k_low, 'color') !== false) {
                            $color_b = $val;
                        }
                        if (strpos($k_low, 'beden') !== false || strpos($k_low, 'size') !== false || strpos($k_low, 'numara') !== false) {
                            $size_b = $val;
                        }
                    }

                    // Önce Renk
                    if ($color_a !== $color_b) {
                        return strnatcasecmp($color_a, $color_b);
                    }

                    // Sonra Beden/Numara (Özel Sıralama: XS, S, M, L, XL...)
                    if ($size_a !== $size_b) {
                        $size_map = [
                            'xs'  => 1,
                            's'   => 2,
                            'm'   => 3,
                            'l'   => 4,
                            'xl'  => 5,
                            'xxl' => 6, '2xl' => 6,
                            '3xl' => 7,
                            '4xl' => 8,
                            '5xl' => 9,
                            '6xl' => 10
                        ];

                        $get_weight = function($val) use ($size_map) {
                            $v = strtolower(trim((string)$val));
                            if (is_numeric($v)) {
                                return (float)$v;
                            }
                            return isset($size_map[$v]) ? (float)$size_map[$v] : 999;
                        };

                        $weight_a = $get_weight($size_a);
                        $weight_b = $get_weight($size_b);

                        if ($weight_a !== $weight_b) {
                            return $weight_a <=> $weight_b;
                        }

                        return strnatcasecmp((string)$size_a, (string)$size_b);
                    }

                    // Fallback: Başlık
                    return strnatcasecmp((string) $a->post_title, (string) $b->post_title);
                });
            }
            unset($variation_rows);
        }
    }

    $formatted = [];
    foreach ($results as $row) {
        $row->all_stocks = (object) ($all_stocks[$row->ID] ?? []);
        $row->all_codes = (object) ($all_codes[$row->ID] ?? []);
        $item = hizli_kasa_format_urun_row($row, $depo_id, $variations_by_parent);
        if ($item) {
            $formatted[] = $item;
        }
    }

    // --- İstatistikleri Hesapla (Performans için sadece ilk sayfa yüklemesinde) ---
    $simple_count = 0;
    $variable_count = 0;
    $grand_total_items = 0;
    $critical_count = 0;

    if ($offset === 0) {
        // 1. Basit ve Değişken Ürün Sayıları (Parent Seviyesinde)
        $type_stats_query = "
            SELECT tt_type.slug as p_type, COUNT(DISTINCT p.ID) as cnt
            FROM {$wpdb->posts} p
            $join_extra
            LEFT JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id
            LEFT JOIN {$wpdb->term_taxonomy} tt_tax ON tr_type.term_taxonomy_id = tt_tax.term_taxonomy_id AND tt_tax.taxonomy = 'product_type'
            LEFT JOIN {$wpdb->terms} tt_type ON tt_tax.term_id = tt_type.term_id
            WHERE $where
            GROUP BY tt_type.slug
        ";
        $type_stats = $wpdb->get_results($params === [] ? $type_stats_query : $wpdb->prepare($type_stats_query, ...$params));

        if ($type_stats) {
            foreach ($type_stats as $ts) {
                if ($ts->p_type === 'simple') {
                    $simple_count = (int) $ts->cnt;
                }
                if ($ts->p_type === 'variable') {
                    $variable_count = (int) $ts->cnt;
                }
            }
        }

        // 2. Toplam Kalem Sayısı (Basit + Varyasyonların her biri)
        // Optimizasyon: p.ID = p2.ID OR p.ID = p2.post_parent yerine iki ayrı hızlı sorgu

        // 2a. Basit Ürünler (Variable olmayanlar)
        $grand_query_simple = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            $join_extra
            LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
            LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_type')
            LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id AND t.slug = 'variable')
            WHERE $where 
            AND p.post_type = 'product'
            AND t.term_id IS NULL
        ";
        $count_simple = (int) $wpdb->get_var($params === [] ? $grand_query_simple : $wpdb->prepare($grand_query_simple, ...$params));

        // 2b. Varyasyonlar
        $grand_query_vars = "
            SELECT COUNT(DISTINCT p2.ID)
            FROM {$wpdb->posts} p
            $join_extra
            INNER JOIN {$wpdb->posts} p2 ON p.ID = p2.post_parent
            WHERE $where 
            AND p2.post_status IN ('publish', 'private') 
            AND p2.post_type = 'product_variation'
        ";
        $count_vars = (int) $wpdb->get_var($params === [] ? $grand_query_vars : $wpdb->prepare($grand_query_vars, ...$params));

        $grand_total_items = $count_simple + $count_vars;

        // 3. Kritik Stok Sayısı (Aktif depoda threshold altında olanlar)
        // Optimizasyon: Yine ikiye bölüyoruz (Ana Ürünler ve Varyasyonlar)

        // 3a. Ana Ürünler (Basit)
        $crit_query_simple = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            $join_extra
            INNER JOIN $stok_table sk_crit ON (sk_crit.product_id = p.ID AND sk_crit.variation_id = 0)
            WHERE $where 
            AND sk_crit.location_id = %d 
            AND sk_crit.quantity > 0 AND sk_crit.quantity <= %d
        ";
        $count_crit_simple = (int) $wpdb->get_var($wpdb->prepare($crit_query_simple, array_merge($params, [$depo_id, $threshold])));

        // 3b. Varyasyonlar
        $crit_query_vars = "
            SELECT COUNT(DISTINCT p2.ID)
            FROM {$wpdb->posts} p
            $join_extra
            INNER JOIN {$wpdb->posts} p2 ON p.ID = p2.post_parent
            INNER JOIN $stok_table sk_crit ON (sk_crit.variation_id = p2.ID)
            WHERE $where 
            AND p2.post_status IN ('publish', 'private') 
            AND p2.post_type = 'product_variation'
            AND sk_crit.location_id = %d 
            AND sk_crit.quantity > 0 AND sk_crit.quantity <= %d
        ";
        $count_crit_vars = (int) $wpdb->get_var($wpdb->prepare($crit_query_vars, array_merge($params, [$depo_id, $threshold])));

        $critical_count = $count_crit_simple + $count_crit_vars;
    }

    return [
        'products' => $formatted,
        'warehouses' => $all_warehouses ?? [],
        'total' => (int) $total,
        'has_more' => ($offset + $limit) < $total,
        'simple_count' => $simple_count,
        'variable_count' => $variable_count,
        'grand_total_items' => $grand_total_items,
        'critical_count' => $critical_count
    ];
}

/**
 * Terminal üzerinden stok güncelleme.
 */
function hizli_kasa_terminal_update_stock($request)
{
    $data = $request->get_json_params();
    $product_id = intval($data['product_id']);
    $variation_id = intval($data['variation_id'] ?? 0);
    $change = floatval($data['change']);
    $reason = sanitize_text_field($data['reason'] ?: "Terminal Manuel Güncelleme");
    $depo_id = intval($data['active_depo_id'] ?? 0);

    $user_id = get_current_user_id();

    if ($depo_id === 0) {
        return new WP_Error('no_depo', 'active_depo_id belirtilmedi.', ['status' => 400]);
    }

    if (!hizli_kasa_can_user_manage_depo($user_id, $depo_id)) {
        return new WP_Error('no_permission', 'Bu depoda stok değiştirme yetkiniz yok.', ['status' => 403]);
    }

    require_once HIZLI_KASA_PATH . 'includes/classes/class-stock-manager.php';
    $new_qty = Hizli_Kasa_Stock_Manager::update_warehouse_stock($product_id, $variation_id, $depo_id, $change, $reason);

    return [
        'success' => true,
        'new_qty' => $new_qty,
        'message' => 'Stok başarıyla güncellendi.'
    ];
}

/**
 * Terminal için kategori ve marka listesini döner.
 */
function hizli_kasa_terminal_get_filters($request) {
    // Kategorileri ağaç yapısında getir
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);

    $formatted_cats = [];
    if (!is_wp_error($categories)) {
        // Yardımcı fonksiyon: Kategorileri hiyerarşik olarak dizer
        $formatted_cats = hizli_kasa_sort_terms_hierarchicaly($categories);
    }

    // Markalar (Çeşitli eklenti destekleri)
    $brand_tax = 'product_brand';
    if (!taxonomy_exists($brand_tax)) {
        if (taxonomy_exists('pwb-brand')) {
            $brand_tax = 'pwb-brand';
        } elseif (taxonomy_exists('brand')) {
            $brand_tax = 'brand';
        }
    }

    $formatted_brands = [];
    if (taxonomy_exists($brand_tax)) {
        $brands = get_terms([
            'taxonomy' => $brand_tax,
            'hide_empty' => false,
        ]);
        if (!is_wp_error($brands)) {
            foreach ($brands as $brand) {
                $formatted_brands[] = [
                    'id'   => $brand->term_id,
                    'name' => $brand->name
                ];
            }
        }
    }

    return [
        'categories' => $formatted_cats,
        'brands'     => $formatted_brands
    ];
}

/**
 * Kategorileri hiyerarşik olarak sıralar ve önüne tire ekler.
 */
function hizli_kasa_sort_terms_hierarchicaly(array &$terms, $parentId = 0, $depth = 0) {
    $branch = [];

    foreach ($terms as $term) {
        if ($term->parent == $parentId) {
            $prefix = str_repeat('— ', $depth);
            $branch[] = [
                'id'   => $term->term_id,
                'name' => $prefix . $term->name
            ];
            
            $children = hizli_kasa_sort_terms_hierarchicaly($terms, $term->term_id, $depth + 1);
            if ($children) {
                $branch = array_merge($branch, $children);
            }
        }
    }

    return $branch;
}

