<?php
if (!defined('ABSPATH')) exit;

function hizli_kasa_is_manual_discount_fee($fee)
{
    if (!$fee) {
        return false;
    }

    $manual_flag = $fee->get_meta('_hk_manual_discount', true);
    if ($manual_flag === 'yes') {
        return true;
    }

    $name = trim((string) $fee->get_name());
    return in_array($name, ['İskonto', 'Düzenlenmiş İskonto'], true);
}

function hizli_kasa_get_order_manual_discount($order)
{
    // Try reading order metadata first (product-level discount representation)
    $meta_discount = $order->get_meta('_hk_toplam_iskonto');
    if ($meta_discount !== '' && floatval($meta_discount) > 0) {
        return round(floatval($meta_discount), 2);
    }

    $manual_discount = 0;

    foreach ($order->get_fees() as $fee) {
        if (!hizli_kasa_is_manual_discount_fee($fee)) {
            continue;
        }

        $total = (float) $fee->get_total();
        if ($total < 0) {
            $manual_discount += abs($total);
        }
    }

    return round($manual_discount, 2);
}

function hizli_kasa_get_order_total_discount($order)
{
    // 1. WooCommerce'in standart indirim toplamını al (Kuponlar vb.)
    $total_discount = (float) $order->get_discount_total();

    // 2. Try using the meta value for manual discount if available
    $meta_discount = $order->get_meta('_hk_toplam_iskonto');
    if ($meta_discount !== '' && floatval($meta_discount) > 0) {
        $total_discount += floatval($meta_discount);
        
        // Also look at other fees (non-manual ones, e.g. shipping discounts or other custom fees)
        foreach ($order->get_fees() as $fee) {
            if (hizli_kasa_is_manual_discount_fee($fee)) {
                continue;
            }
            $name = $fee->get_name();
            $total = (float) $fee->get_total();
            if (preg_match('/iskonto|indirim/ui', $name) || $total < 0) {
                $total_discount += abs($total);
            }
        }
    } else {
        // Fallback for old orders that only have fees
        foreach ($order->get_fees() as $fee) {
            $name = $fee->get_name();
            $total = (float) $fee->get_total();

            if (preg_match('/iskonto|indirim/ui', $name) || $total < 0) {
                $total_discount += abs($total);
            }
        }
    }

    return $total_discount;
}

/**
 * Ürünleri toplu halde ve çok hızlı şekilde doldurur (Hydration).
 */
function hizli_kasa_hydrate_products_batch($ids, $depo_id)
{
    global $wpdb;
    if (empty($ids))
        return [];

    $raw_ids_str = implode(',', array_map('intval', $ids));

    // Adım 1: Gelen ID'lerin varyasyonlarını, parent'larını ve o parent'ların TÜM çocuklarını belirle
    // Bu sayede dropdown'ların her zaman dolu olduğundan emin oluruz.
    $all_ids = array_map('intval', $ids);
    if (!empty($all_ids)) {
        $raw_ids_str = implode(',', $all_ids);
        $relations = $wpdb->get_results("SELECT ID, post_parent, post_type FROM {$wpdb->posts} WHERE ID IN ($raw_ids_str)");

        $parents_to_expand = [];
        foreach ($relations as $r) {
            if ($r->post_type === 'product_variation' && $r->post_parent > 0) {
                $parents_to_expand[] = (int) $r->post_parent;
            } else {
                $parents_to_expand[] = (int) $r->ID;
            }
        }

        if (!empty($parents_to_expand)) {
            $parents_to_expand = array_unique($parents_to_expand);
            $parents_str = implode(',', $parents_to_expand);

            // Bu parent'ların TÜM çocuklarını (varyasyonlarını) listeye ekle
            $sibling_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_parent IN ($parents_str) AND post_type = 'product_variation' AND post_status IN ('publish', 'private')");
            if ($sibling_ids) {
                $all_ids = array_merge($all_ids, $parents_to_expand, array_map('intval', $sibling_ids));
            } else {
                $all_ids = array_merge($all_ids, $parents_to_expand);
            }
        }
    }

    $all_ids = array_unique($all_ids);
    $ids_str = implode(',', $all_ids);

    $stok_table = Hizli_Kasa_Database::get_tables()['stok_konumlari'];

    // Ana veri çekme işlemi
    $posts = $wpdb->get_results("SELECT ID, post_title, post_type, post_parent FROM {$wpdb->posts} WHERE ID IN ($ids_str)");
    $meta_raw = $wpdb->get_results("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($ids_str)");
    $meta_map = [];
    if (!empty($meta_raw)) {
        foreach ($meta_raw as $m) {
            $meta_map[$m->post_id][$m->meta_key] = $m->meta_value;
        }
    }

    $stok_raw = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $stok_table WHERE location_id = %d AND (product_id IN ($ids_str) OR variation_id IN ($ids_str))", $depo_id));
    $stok_map = [];
    $code_map = [];
    if (!empty($stok_raw)) {
        foreach ($stok_raw as $s) {
            $key = ($s->variation_id > 0) ? 'v_' . $s->variation_id : 'p_' . $s->product_id;
            $stok_map[$key] = (float) $s->quantity;
            $code_map[$key] = $s->depo_kodu;
        }
    }

    $types_raw = $wpdb->get_results("
        SELECT tr.object_id, t.slug FROM {$wpdb->term_relationships} tr
        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE tr.object_id IN ($ids_str) AND tt.taxonomy = 'product_type'");
    $type_map = [];
    if (!empty($types_raw)) {
        foreach ($types_raw as $t) {
            $type_map[$t->object_id] = $t->slug;
        }
    }

    $final = [];
    foreach ($posts as $p) {
        $pid = (int) $p->ID;
        $m = $meta_map[$pid] ?? [];
        $p_type = $type_map[$pid] ?? '';
        $stok_key = ($p->post_type === 'product_variation') ? 'v_' . $pid : 'p_' . $pid;
        $w_stock = $stok_map[$stok_key] ?? 0;
        $d_code = $code_map[$stok_key] ?? null;
        $thumb_id = $m['_thumbnail_id'] ?? '';
        $img_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';

        $final[$pid] = [
            'id' => $pid,
            'parent_id' => (int) $p->post_parent,
            'type' => ($p->post_type === 'product_variation') ? 'variation' : 'product',
            'name' => $p->post_title,
            'sku' => $m['_sku'] ?? '',
            'price' => $m['_price'] ?? 0,
            'regular_price' => $m['_regular_price'] ?? 0,
            'stock_status' => $m['_stock_status'] ?? 'instock',
            'manage_stock' => ($m['_manage_stock'] ?? 'no') === 'yes',
            'stock_quantity' => (float) ($m['_stock'] ?? 0),
            'warehouse_stock' => $w_stock,
            'depo_kodu' => $d_code,
            'images' => $img_url ? [['src' => $img_url]] : [],
            'is_variable' => $p_type === 'variable',
            'variations' => []
        ];
    }
    return $final;
}

/**
 * Veritabanı row'unu JSON formatına çevirir (Performans Optimizasyonlu).
 */
function hizli_kasa_format_urun_row($row, $depo_id = null, $variations_by_parent = [])
{
    try {
        $parent_id = (int) $row->ID;
        $is_variable = (isset($row->product_type) && $row->product_type === 'variable');

        $active_children_data = [];
        if ($is_variable && isset($variations_by_parent[$parent_id])) {
            foreach ($variations_by_parent[$parent_id] as $v) {
                $var_img = '';
                if (!empty($v->thumbnail_id)) {
                    $var_img = wp_get_attachment_image_url($v->thumbnail_id, 'thumbnail');
                }

                $active_children_data[] = [
                    'id' => (int) $v->ID,
                    'parent_id' => $parent_id,
                    'type' => 'variation',
                    'name' => $v->post_title,
                    'sku' => $v->sku ?: '',
                    'price' => $v->price,
                    'regular_price' => $v->regular_price,
                    'warehouse_stock' => (float) $v->warehouse_stock,
                    'stock_quantity' => (float) $v->stock_quantity,
                    'all_stocks' => $v->all_stocks ?? [],
                    'all_codes' => $v->all_codes ?? [],
                    'images' => $var_img ? [['src' => $var_img]] : [],
                    'attributes' => $v->attributes ?? []
                ];
            }
        }

        $image_url = '';
        if (!empty($row->thumbnail_id)) {
            $image_url = wp_get_attachment_image_url($row->thumbnail_id, 'thumbnail');
        }

        return [
            'id' => $parent_id,
            'parent_id' => (int) $row->post_parent,
            'type' => $row->post_type === 'product_variation' ? 'variation' : 'product',
            'name' => $row->post_title,
            'sku' => $row->sku,
            'price' => $row->price,
            'regular_price' => $row->regular_price,
            'stock_status' => $row->stock_status,
            'manage_stock' => $row->manage_stock === 'yes',
            'stock_quantity' => (float) $row->stock_quantity,
            'warehouse_stock' => (float) $row->warehouse_stock,
            'all_stocks' => $row->all_stocks ?? [],
            'all_codes' => $row->all_codes ?? [],
            'images' => $image_url ? [['src' => $image_url]] : [],
            'permalink' => get_permalink($parent_id),
            'is_variable' => $is_variable,
            'variations' => $active_children_data
        ];
    } catch (Exception $e) {
        error_log('Hızlı Kasa Ürün Formatlama Hatası (ID: ' . $row->ID . '): ' . $e->getMessage());
        return null;
    }
}

/**
 * Arama metnini kelimelere ayırır.
 */
function hizli_kasa_prepare_search_terms($search)
{
    $search = trim((string) $search);
    if ($search === '') {
        return [];
    }

    $parts = preg_split('/\s+/u', $search);
    $terms = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        if (function_exists('mb_strlen')) {
            if (mb_strlen($part) < 2) {
                continue;
            }
        } elseif (strlen($part) < 2) {
            continue;
        }

        $terms[] = $part;
    }

    return array_values(array_unique($terms));
}

/**
 * AWS sonuçlarını parent ürün ID listesine çözümler.
 */
function hizli_kasa_get_aws_ranked_product_ids($search, $depo_id = 0)
{
    global $wpdb;

    if (!class_exists('AWS_Search')) {
        return [];
    }

    try {
        $aws_search = new AWS_Search();
        $aws_results = $aws_search->search($search);
    } catch (Throwable $e) {
        return [];
    }

    if (empty($aws_results['products']) || !is_array($aws_results['products'])) {
        return [];
    }

    $raw_ids = [];
    foreach ($aws_results['products'] as $item) {
        $candidate_id = isset($item['id']) ? (int) $item['id'] : 0;
        if ($candidate_id > 0) {
            $raw_ids[] = $candidate_id;
        }
    }

    if (empty($raw_ids)) {
        return [];
    }

    $resolved_rows = $wpdb->get_results(
        "SELECT ID, post_parent, post_type FROM {$wpdb->posts} WHERE ID IN (" . implode(',', array_map('intval', $raw_ids)) . ")"
    );

    if (empty($resolved_rows)) {
        return [];
    }

    $resolved_map = [];
    foreach ($resolved_rows as $row) {
        $resolved_map[(int) $row->ID] = $row;
    }

    $ranked_ids = [];
    foreach ($raw_ids as $raw_id) {
        if (empty($resolved_map[$raw_id])) {
            continue;
        }

        $row = $resolved_map[$raw_id];
        $parent_id = ($row->post_type === 'product_variation') ? (int) $row->post_parent : (int) $row->ID;

        if ($parent_id > 0 && !in_array($parent_id, $ranked_ids, true)) {
            $ranked_ids[] = $parent_id;
        }
    }

    if (empty($ranked_ids) || !$depo_id) {
        return $ranked_ids;
    }

    $stok_table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';
    $allowed_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT product_id FROM $stok_table WHERE location_id = %d AND product_id IN (" . implode(',', array_map('intval', $ranked_ids)) . ")",
            $depo_id
        )
    );

    if (empty($allowed_ids)) {
        return [];
    }

    $allowed_map = array_fill_keys(array_map('intval', $allowed_ids), true);
    return array_values(array_filter($ranked_ids, function ($id) use ($allowed_map) {
        return isset($allowed_map[(int) $id]);
    }));
}

/**
 * Shadow stock tarafında depo uyumlu yerel ürün araması yapar.
 */
function hizli_kasa_get_local_ranked_product_ids($search, $depo_id = 0, $limit = 250)
{
    global $wpdb;

    $search = trim((string) $search);
    if ($search === '') {
        return [];
    }

    $terms = hizli_kasa_prepare_search_terms($search);
    $stok_table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';
    $join_stock = '';
    $stock_params = [];
    if ($depo_id > 0) {
        $join_stock = " INNER JOIN $stok_table sk_search ON (sk_search.product_id = p.ID AND sk_search.location_id = %d)";
        $stock_params[] = $depo_id;
    }

    $or_parts = [];
    $score_parts = [];
    $where_params = [];
    $score_params = [];
    $exact_search = $search;
    $prefix_like = $wpdb->esc_like($search) . '%';
    $contains_like = '%' . $wpdb->esc_like($search) . '%';

    $or_parts[] = "parent_sku.meta_value = %s";
    $where_params[] = $exact_search;
    $score_parts[] = "MAX(CASE WHEN parent_sku.meta_value = %s THEN 1000 ELSE 0 END)";
    $score_params[] = $exact_search;

    $or_parts[] = "var_sku.meta_value = %s";
    $where_params[] = $exact_search;
    $score_parts[] = "MAX(CASE WHEN var_sku.meta_value = %s THEN 950 ELSE 0 END)";
    $score_params[] = $exact_search;

    $or_parts[] = "p.post_title LIKE %s";
    $where_params[] = $prefix_like;
    $score_parts[] = "MAX(CASE WHEN p.post_title LIKE %s THEN 700 ELSE 0 END)";
    $score_params[] = $prefix_like;

    $or_parts[] = "v.post_title LIKE %s";
    $where_params[] = $prefix_like;
    $score_parts[] = "MAX(CASE WHEN v.post_title LIKE %s THEN 650 ELSE 0 END)";
    $score_params[] = $prefix_like;

    $or_parts[] = "p.post_title LIKE %s";
    $where_params[] = $contains_like;
    $score_parts[] = "MAX(CASE WHEN p.post_title LIKE %s THEN 400 ELSE 0 END)";
    $score_params[] = $contains_like;

    $or_parts[] = "var_sku.meta_value LIKE %s";
    $where_params[] = $contains_like;
    $score_parts[] = "MAX(CASE WHEN var_sku.meta_value LIKE %s THEN 375 ELSE 0 END)";
    $score_params[] = $contains_like;

    $or_parts[] = "parent_sku.meta_value LIKE %s";
    $where_params[] = $contains_like;
    $score_parts[] = "MAX(CASE WHEN parent_sku.meta_value LIKE %s THEN 350 ELSE 0 END)";
    $score_params[] = $contains_like;

    $or_parts[] = "v.post_title LIKE %s";
    $where_params[] = $contains_like;
    $score_parts[] = "MAX(CASE WHEN v.post_title LIKE %s THEN 325 ELSE 0 END)";
    $score_params[] = $contains_like;

    foreach ($terms as $term) {
        $like = '%' . $wpdb->esc_like($term) . '%';
        $or_parts[] = "p.post_title LIKE %s";
        $where_params[] = $like;
        $score_parts[] = "MAX(CASE WHEN p.post_title LIKE %s THEN 80 ELSE 0 END)";
        $score_params[] = $like;

        $or_parts[] = "parent_sku.meta_value LIKE %s";
        $where_params[] = $like;
        $score_parts[] = "MAX(CASE WHEN parent_sku.meta_value LIKE %s THEN 75 ELSE 0 END)";
        $score_params[] = $like;

        $or_parts[] = "v.post_title LIKE %s";
        $where_params[] = $like;
        $score_parts[] = "MAX(CASE WHEN v.post_title LIKE %s THEN 70 ELSE 0 END)";
        $score_params[] = $like;

        $or_parts[] = "var_sku.meta_value LIKE %s";
        $where_params[] = $like;
        $score_parts[] = "MAX(CASE WHEN var_sku.meta_value LIKE %s THEN 65 ELSE 0 END)";
        $score_params[] = $like;
    }

    if (empty($or_parts)) {
        return [];
    }

    $where_or = implode(' OR ', $or_parts);
    $score_sql = implode(' + ', $score_parts);

    $sql = "
        SELECT p.ID, ($score_sql) AS relevance_score
        FROM {$wpdb->posts} p
        $join_stock
        LEFT JOIN {$wpdb->postmeta} parent_sku ON (parent_sku.post_id = p.ID AND parent_sku.meta_key = '_sku')
        LEFT JOIN {$wpdb->posts} v ON (v.post_parent = p.ID AND v.post_type = 'product_variation' AND v.post_status IN ('publish', 'private'))
        LEFT JOIN {$wpdb->postmeta} var_sku ON (var_sku.post_id = v.ID AND var_sku.meta_key = '_sku')
        WHERE p.post_status IN ('publish', 'private')
          AND p.post_type = 'product'
          AND ($where_or)
        GROUP BY p.ID
        ORDER BY relevance_score DESC, p.post_title ASC
        LIMIT %d
    ";

    $params = array_merge($score_params, $stock_params, $where_params, [(int) $limit]);
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));

    if (empty($rows)) {
        return [];
    }

    return array_map(function ($row) {
        return (int) $row->ID;
    }, $rows);
}

/**
 * Terminal ürün araması için AWS ve yerel shadow-stock sonuçlarını birleştirir.
 */
function hizli_kasa_get_terminal_search_product_ids($search, $depo_id = 0)
{
    $search = trim((string) $search);
    if ($search === '') {
        return [];
    }

    $aws_ids = hizli_kasa_get_aws_ranked_product_ids($search, $depo_id);
    $local_ids = hizli_kasa_get_local_ranked_product_ids($search, $depo_id);

    $merged = [];
    foreach ([$aws_ids, $local_ids] as $id_list) {
        foreach ($id_list as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $merged, true)) {
                $merged[] = $id;
            }
        }
    }

    return $merged;
}

