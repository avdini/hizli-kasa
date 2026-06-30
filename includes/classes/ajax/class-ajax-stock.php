<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Ajax_Stock {
    public static function init() {
        add_action('wp_ajax_hizli_kasa_get_admin_stock_list', [self::class, 'get_list']);
        add_action('wp_ajax_hizli_kasa_admin_update_stock', [self::class, 'update']);
        add_action('wp_ajax_hizli_kasa_batch_update_stock', [self::class, 'batch_update']);
    }

public static function get_list() {
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    nocache_headers(); // Cache engelle
    try {
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
        $paged = max(1, intval($_POST['paged'] ?? 1));
        $per_page = 24;
        $offset = ($paged - 1) * $per_page;

        $params = [];
        $where_sql = "p.post_type IN ('product', 'product_variation') AND p.post_status IN ('publish', 'private')";

        if ($s) {
            $like = '%' . $wpdb->esc_like($s) . '%';
            $where_sql .= " AND (p.post_title LIKE %s OR pm_sku.meta_value LIKE %s)";
            $params[] = $like; $params[] = $like;
        }

        if ($filter_mismatch || $filter_zero_stock) {
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
            $having_sql = $having_clauses === [] ? "" : "HAVING " . implode(" AND ", $having_clauses);
            
            $base_sql = "
                SELECT 
                    (CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END) as main_id,
                    COALESCE(CAST(pm_stock.meta_value AS DECIMAL(15,4)), 0) as wc_stock,
                    COALESCE(SUM(sk.quantity), 0) as total_wh_stock,
                    COALESCE(MIN(sk.quantity), 0) as min_wh_stock
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_sku ON (p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku')
                LEFT JOIN {$wpdb->postmeta} pm_stock ON (p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock')
                LEFT JOIN $stok_table sk ON sk.variation_id = IF(p.post_type = 'product_variation', p.ID, 0) AND sk.product_id = IF(p.post_type = 'product_variation', p.post_parent, p.ID)
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
        $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT main_id) FROM ($base_sql) as t", $params));
        
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

    if (empty($main_ids)) {
        wp_send_json_success(['products' => [], 'total_pages' => 0]);
    }

    // ADIM 2: Detayları Topla (Ana ürünler + Onların tüm varyasyonları)
    $main_placeholders = implode(',', array_fill(0, count($main_ids), '%d'));
    
    // Varyasyonları bul
    $variation_ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_parent IN ($main_placeholders)", $main_ids));
    
    $all_target_ids = array_unique(array_merge($main_ids, $variation_ids));
    $all_placeholders = implode(',', array_fill(0, count($all_target_ids), '%d'));

    // Metataları çek (Nitelikler dahil)
    $meta_results = $wpdb->get_results($wpdb->prepare("
        SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
        WHERE post_id IN ($all_placeholders) 
        AND (meta_key IN ('_sku', '_stock', '_thumbnail_id', '_product_attributes') OR meta_key LIKE 'attribute_%%')
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

    // Term isimlerini çöz (Sıralama için)
    $term_names = [];
    foreach ($tax_slug_map as $tax => $slugs) {
        $slugs = array_unique($slugs);
        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, $tax);
            $term_names[$tax][$slug] = $term ? $term->name : $slug;
        }
    }

    // Post detaylarını çek
    $p_details = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_type, post_parent FROM {$wpdb->posts} WHERE ID IN ($all_placeholders)", $all_target_ids));
    $details_by_id = [];
    foreach ($p_details as $pd) { $details_by_id[$pd->ID] = $pd; }

    // ADIM 3: Depo Stoklarını Topla
    $depolar = $wpdb->get_results("SELECT id, name FROM $depo_table ORDER BY priority DESC");
    $stock_results = $wpdb->get_results($wpdb->prepare("SELECT location_id, product_id, variation_id, quantity FROM $stok_table WHERE (product_id IN ($all_placeholders) OR variation_id IN ($all_placeholders))", array_merge($all_target_ids, $all_target_ids)));

    $stocks_by_loc = [];
    foreach ($stock_results as $sr) {
        $key = ($sr->variation_id > 0) ? "v_{$sr->variation_id}" : "p_{$sr->product_id}";
        $stocks_by_loc[$sr->location_id][$key] = $sr->quantity;
    }
    hizli_kasa_admin_log("Step 3 Complete (Stocks Fetched)");

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
                'wc_stock' => (float)($vm['_stock'] ?? 0),
                'thumbnail' => $v_thumbnail,
                'attributes' => $clean_attrs,
                'warehouse_stocks' => []
            ];
            foreach ($depolar as $d) {
                $qty = $stocks_by_loc[$d->id]["v_{$v_id}"] ?? 0;
                $v_item['warehouse_stocks'][] = ['depo_id' => $d->id, 'qty' => (float)$qty];
            }
            
            // Mismatch kontrolü
            $v_total_wh = array_sum(array_column($v_item['warehouse_stocks'], 'qty'));
            $v_item['total_warehouse_stock'] = $v_total_wh;
            $v_item['has_mismatch'] = (round((float)$v_total_wh, 4) !== round($v_item['wc_stock'], 4));

            $children[] = $v_item;
        }

        if ($children !== []) {
            usort($children, function ($a, $b) use ($s) {
                // 1. Arama Puanı (Eğer arama yapılıyorsa)
                if (!empty($s)) {
                    $needle = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
                    $a_name = function_exists('mb_strtolower') ? mb_strtolower((string) $a['name']) : strtolower((string) $a['name']);
                    $b_name = function_exists('mb_strtolower') ? mb_strtolower((string) $b['name']) : strtolower((string) $b['name']);
                    $a_sku = function_exists('mb_strtolower') ? mb_strtolower((string) $a['sku']) : strtolower((string) $a['sku']);
                    $b_sku = function_exists('mb_strtolower') ? mb_strtolower((string) $b['sku']) : strtolower((string) $b['sku']);

                    $a_score = ((strpos($a_sku, $needle) !== false) ? 20 : 0) + ((strpos($a_name, $needle) !== false) ? 10 : 0);
                    $b_score = ((strpos($b_sku, $needle) !== false) ? 20 : 0) + ((strpos($b_name, $needle) !== false) ? 10 : 0);

                    if ($a_score !== $b_score) {
                        return $b_score <=> $a_score;
                    }
                }

                // 2. Renk ve Beden/Numara Bazlı Sıralama
                $attrs_a = $a['attributes'] ?? [];
                $attrs_b = $b['attributes'] ?? [];

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
                return strnatcasecmp((string) $a['name'], (string) $b['name']);
            });
        }

        $item = [
            'id' => $m_id,
            'variation_id' => 0,
            'name' => $parent->post_title,
            'sku' => $m['_sku'] ?? '',
            'wc_stock' => (float)($m['_stock'] ?? 0),
            'thumbnail' => $thumbnail,
            'type' => $children === [] ? 'simple' : 'variable',
            'variations' => $children,
            'warehouse_stocks' => []
        ];

        foreach ($depolar as $d) {
            $qty = $stocks_by_loc[$d->id]["p_{$m_id}"] ?? 0;
            $item['warehouse_stocks'][] = ['depo_id' => $d->id, 'qty' => (float)$qty];
        }

        // Mismatch kontrolü (Basit ürün için veya değişken ürünün genel durumu için)
        $total_wh = array_sum(array_column($item['warehouse_stocks'], 'qty'));
        $item['total_warehouse_stock'] = $total_wh;
        
        if ($item['type'] === 'simple') {
            $item['has_mismatch'] = (round((float)$total_wh, 4) !== round($item['wc_stock'], 4));
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

    hizli_kasa_admin_log("Final Output Prepared. Count: " . count($output));

    wp_send_json_success([
        'products'    => $output,
        'total_pages' => ceil($total_items / $per_page)
    ]);
    hizli_kasa_admin_log("Response Sent Successfully");

    } catch (Exception $e) {
        hizli_kasa_admin_log("AJAX Hatası: " . $e->getMessage());
        wp_send_json_error(['message' => 'İstisnai bir hata oluştu: ' . $e->getMessage()]);
    }
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
    
    wp_send_json_success(['updated' => $updated, 'errors' => $errors]);
}
}
