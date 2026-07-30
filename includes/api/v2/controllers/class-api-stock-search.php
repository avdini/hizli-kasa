<?php
/**
 * Hızlı Kasa V2 API Stock Search Controller
 *
 * Handles advanced stock and product searching, filtering by variation stock,
 * parent total stock sum, attributes, categories, brands, tags, and date criteria.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Stock_Search extends Hizli_Kasa_API_Controller_Base {

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/products/stock-search', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_stock_search_callback'],
                'permission_callback' => [$this, 'check_permission'],
                'args'                => [
                    'scope' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'all',
                    ],
                    'min_stock' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'max_stock' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'stock_status' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'all',
                    ],
                    'category_ids' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'brand_ids' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'tag_ids' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'attribute_slug' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'attribute_term_ids' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'search' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                    'days_since_last_sale' => [
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                        'default'           => 0,
                    ],
                    'depo_id' => [
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                        'default'           => 0,
                    ],
                    'sort_by' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => 'date_desc',
                    ],
                    'page' => [
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                        'default'           => 1,
                    ],
                    'per_page' => [
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                        'default'           => 20,
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/products/filter-options', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_filter_options_callback'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    /**
     * Callback for stock search endpoint.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_stock_search_callback($request) {
        return $this->handle_request([$this, 'get_stock_search'], $request);
    }

    /**
     * Callback for filter options endpoint.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_filter_options_callback($request) {
        return $this->handle_request([$this, 'get_filter_options'], $request);
    }

    /**
     * Inner logic for stock search query.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    protected function get_stock_search($request) {
        global $wpdb;

        $scope                 = sanitize_text_field($request->get_param('scope') ?: 'all');
        $min_stock_raw         = $request->get_param('min_stock');
        $max_stock_raw         = $request->get_param('max_stock');
        $stock_status          = sanitize_text_field($request->get_param('stock_status') ?: 'all');
        $category_ids_raw      = $request->get_param('category_ids');
        $brand_ids_raw         = $request->get_param('brand_ids');
        $tag_ids_raw           = $request->get_param('tag_ids');
        $attribute_slug        = sanitize_text_field($request->get_param('attribute_slug') ?: '');
        $attribute_term_ids_raw= $request->get_param('attribute_term_ids');
        $search                = sanitize_text_field($request->get_param('search') ?: '');
        $days_since_last_sale  = absint($request->get_param('days_since_last_sale') ?: 0);
        $depo_id               = absint($request->get_param('depo_id') ?: 0);
        $sort_by               = sanitize_text_field($request->get_param('sort_by') ?: 'date_desc');
        $page                  = max(1, absint($request->get_param('page') ?: 1));
        $per_page              = min(100, max(1, absint($request->get_param('per_page') ?: 20)));

        $stok_table     = $wpdb->prefix . 'hizli_kasa_stok_konumlari';
        $has_stok_table = ($depo_id > 0 && $wpdb->get_var("SHOW TABLES LIKE '{$stok_table}'") === $stok_table);

        $has_min_stock = ($min_stock_raw !== '' && $min_stock_raw !== null);
        $has_max_stock = ($max_stock_raw !== '' && $max_stock_raw !== null);
        $min_stock     = $has_min_stock ? floatval($min_stock_raw) : null;
        $max_stock     = $has_max_stock ? floatval($max_stock_raw) : null;

        $category_ids       = $this->parse_id_list($category_ids_raw);
        $brand_ids          = $this->parse_id_list($brand_ids_raw);
        $tag_ids            = $this->parse_id_list($tag_ids_raw);
        $attribute_term_ids = $this->parse_id_list($attribute_term_ids_raw);

        $brand_taxonomy = $this->detect_brand_taxonomy();

        $where = [];
        $join  = [];

        // Base post type criteria
        if ($scope === 'variation') {
            $where[] = "p.post_type = 'product_variation'";
        } elseif ($scope === 'simple') {
            $where[] = "p.post_type = 'product'";
            $where[] = "p.ID NOT IN (SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'product_variation')";
        } else {
            $where[] = "p.post_type = 'product'";
        }
        $where[] = "p.post_status = 'publish'";

        // Text Search (Name, SKU, Barcode)
        $exact_sku_match      = false;
        $exact_sku_product_id = 0;
        $search_strategy      = 'NO_SEARCH';

        if (!empty($search)) {
            $exact_match_id = $wpdb->get_var($wpdb->prepare(
                "SELECT (CASE WHEN p_m.post_type = 'product_variation' THEN p_m.post_parent ELSE pm.post_id END) 
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p_m ON pm.post_id = p_m.ID
                 WHERE pm.meta_key IN ('_sku', '_barcode', '_gtin', '_ean') AND pm.meta_value = %s LIMIT 1",
                $search
            ));

            if ($exact_match_id) {
                $exact_sku_match      = true;
                $exact_sku_product_id = intval($exact_match_id);
                $search_strategy      = 'EXACT_SKU_MATCH';
            } else {
                $search_strategy      = 'PARTIAL_LIKE_SEARCH';
            }

            $s_like = '%' . $wpdb->esc_like($search) . '%';
            $search_conds = [
                $wpdb->prepare("p.post_title LIKE %s", $s_like),
                $wpdb->prepare("p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s
                    UNION
                    SELECT p_v.post_parent FROM {$wpdb->posts} p_v
                    INNER JOIN {$wpdb->postmeta} pm_v ON p_v.ID = pm_v.post_id
                    WHERE p_v.post_type = 'product_variation' AND pm_v.meta_key = '_sku' AND pm_v.meta_value LIKE %s
                )", $s_like, $s_like),
                $wpdb->prepare("p.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_barcode', '_gtin', '_ean') AND meta_value LIKE %s
                    UNION
                    SELECT p_v.post_parent FROM {$wpdb->posts} p_v
                    INNER JOIN {$wpdb->postmeta} pm_v ON p_v.ID = pm_v.post_id
                    WHERE p_v.post_type = 'product_variation' AND pm_v.meta_key IN ('_barcode', '_gtin', '_ean') AND pm_v.meta_value LIKE %s
                )", $s_like, $s_like),
            ];
            $where[] = "(" . implode(" OR ", $search_conds) . ")";
        }

        // Category filter
        if (!empty($category_ids)) {
            $cat_ids_str = implode(',', array_map('absint', $category_ids));
            $where[] = "p.ID IN (
                SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'product_cat' AND tt.term_id IN ({$cat_ids_str})
                UNION
                SELECT p_child.ID FROM {$wpdb->posts} p_child
                INNER JOIN {$wpdb->term_relationships} tr_p ON p_child.post_parent = tr_p.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt_p ON tr_p.term_taxonomy_id = tt_p.term_taxonomy_id
                WHERE tt_p.taxonomy = 'product_cat' AND tt_p.term_id IN ({$cat_ids_str}) AND p_child.post_type = 'product_variation'
            )";
        }

        // Brand filter
        if (!empty($brand_ids) && $brand_taxonomy) {
            $brand_ids_str = implode(',', array_map('absint', $brand_ids));
            $where[] = "p.ID IN (
                SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = %s AND tt.term_id IN ({$brand_ids_str})
                UNION
                SELECT p_child.ID FROM {$wpdb->posts} p_child
                INNER JOIN {$wpdb->term_relationships} tr_p ON p_child.post_parent = tr_p.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt_p ON tr_p.term_taxonomy_id = tt_p.term_taxonomy_id
                WHERE tt_p.taxonomy = %s AND tt_p.term_id IN ({$brand_ids_str}) AND p_child.post_type = 'product_variation'
            )";
            $where[count($where) - 1] = $wpdb->prepare($where[count($where) - 1], $brand_taxonomy, $brand_taxonomy);
        }

        // Tag filter
        if (!empty($tag_ids)) {
            $tag_ids_str = implode(',', array_map('absint', $tag_ids));
            $where[] = "p.ID IN (
                SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'product_tag' AND tt.term_id IN ({$tag_ids_str})
            )";
        }

        // Attribute Filter (e.g., pa_size with term IDs)
        if (!empty($attribute_slug)) {
            $tax_name = (strpos($attribute_slug, 'pa_') === 0) ? $attribute_slug : 'pa_' . $attribute_slug;
            if (!empty($attribute_term_ids)) {
                $term_ids_str = implode(',', array_map('absint', $attribute_term_ids));
                $where[] = "p.ID IN (
                    SELECT tr.object_id FROM {$wpdb->term_relationships} tr
                    INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tt.taxonomy = %s AND tt.term_id IN ({$term_ids_str})
                )";
                $where[count($where) - 1] = $wpdb->prepare($where[count($where) - 1], $tax_name);
            }
        }

        // Days since last sale
        if ($days_since_last_sale > 0) {
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_since_last_sale} days"));
            $where[] = "p.ID NOT IN (
                SELECT DISTINCT oim.meta_value 
                FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
                INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim.woocommerce_order_item_id = oi.order_item_id
                INNER JOIN {$wpdb->posts} o ON oi.order_id = o.ID
                WHERE oim.meta_key IN ('_product_id', '_variation_id')
                  AND o.post_type = 'shop_order'
                  AND o.post_date >= %s
            )";
            $where[count($where) - 1] = $wpdb->prepare($where[count($where) - 1], $cutoff_date);
        }

        // Stock quantity & status filter
        $join[] = "LEFT JOIN {$wpdb->postmeta} pm_stock ON (p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock')";
        $join[] = "LEFT JOIN {$wpdb->postmeta} pm_stock_status ON (p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status')";

        $depo_stock_sql = "";
        if ($has_stok_table) {
            $depo_stock_sql = $wpdb->prepare("COALESCE(
                (SELECT SUM(sk_v.quantity) FROM {$stok_table} sk_v WHERE sk_v.product_id = p.ID AND sk_v.location_id = %d AND sk_v.variation_id > 0),
                (SELECT sk_s.quantity FROM {$stok_table} sk_s WHERE sk_s.product_id = p.ID AND sk_s.location_id = %d AND sk_s.variation_id = 0 LIMIT 1),
                0
            )", $depo_id, $depo_id);
        }

        if ($stock_status !== 'all') {
            if ($has_stok_table) {
                if ($stock_status === 'instock') {
                    $where[] = "({$depo_stock_sql}) > 0";
                } elseif ($stock_status === 'outofstock') {
                    $where[] = "({$depo_stock_sql}) <= 0";
                } elseif ($stock_status === 'lowstock') {
                    $where[] = "({$depo_stock_sql}) > 0 AND ({$depo_stock_sql}) <= 2";
                }
            } else {
                if ($stock_status === 'instock') {
                    $where[] = "(pm_stock_status.meta_value = 'instock' OR p.ID IN (SELECT DISTINCT post_parent FROM {$wpdb->posts} p_v INNER JOIN {$wpdb->postmeta} pm_vs ON p_v.ID = pm_vs.post_id WHERE p_v.post_type = 'product_variation' AND pm_vs.meta_key = '_stock_status' AND pm_vs.meta_value = 'instock'))";
                } elseif ($stock_status === 'outofstock') {
                    $where[] = "(pm_stock_status.meta_value = 'outofstock' OR p.ID IN (SELECT DISTINCT post_parent FROM {$wpdb->posts} p_v INNER JOIN {$wpdb->postmeta} pm_vs ON p_v.ID = pm_vs.post_id WHERE p_v.post_type = 'product_variation' AND pm_vs.meta_key = '_stock_status' AND pm_vs.meta_value = 'outofstock'))";
                } elseif ($stock_status === 'lowstock') {
                    $where[] = "((CAST(pm_stock.meta_value AS SIGNED) <= 2 AND pm_stock_status.meta_value = 'instock') OR p.ID IN (SELECT DISTINCT post_parent FROM {$wpdb->posts} p_v INNER JOIN {$wpdb->postmeta} pm_vq ON p_v.ID = pm_vq.post_id INNER JOIN {$wpdb->postmeta} pm_vs ON p_v.ID = pm_vs.post_id WHERE p_v.post_type = 'product_variation' AND pm_vq.meta_key = '_stock' AND pm_vs.meta_key = '_stock_status' AND CAST(pm_vq.meta_value AS SIGNED) <= 2 AND pm_vs.meta_value = 'instock'))";
                }
            }
        }

        if ($scope === 'parent_sum') {
            // Calculate parent total stock sum across variations
            if ($has_min_stock || $has_max_stock) {
                $sum_having = [];
                if ($has_min_stock) {
                    $sum_having[] = $wpdb->prepare("total_calculated_stock >= %f", $min_stock);
                }
                if ($has_max_stock) {
                    $sum_having[] = $wpdb->prepare("total_calculated_stock <= %f", $max_stock);
                }
                $having_clause = "HAVING " . implode(" AND ", $sum_having);
            } else {
                $having_clause = "";
            }

            $where_sql = implode(' AND ', $where);
            $join_sql  = implode(' ', $join);

            $count_query = "
                SELECT COUNT(*) FROM (
                    SELECT p.ID, 
                           COALESCE(SUM(CAST(pm_child_stock.meta_value AS SIGNED)), CAST(pm_stock.meta_value AS SIGNED), 0) AS total_calculated_stock
                    FROM {$wpdb->posts} p
                    {$join_sql}
                    LEFT JOIN {$wpdb->posts} p_child ON (p.ID = p_child.post_parent AND p_child.post_type = 'product_variation' AND p_child.post_status = 'publish')
                    LEFT JOIN {$wpdb->postmeta} pm_child_stock ON (p_child.ID = pm_child_stock.post_id AND pm_child_stock.meta_key = '_stock')
                    WHERE {$where_sql}
                    GROUP BY p.ID
                    {$having_clause}
                ) as sum_subquery
            ";

            $total_items = intval($wpdb->get_var($count_query));
            $offset = ($page - 1) * $per_page;

            $order_sql = "ORDER BY p.ID DESC";
            if ($sort_by === 'title_asc') $order_sql = "ORDER BY p.post_title ASC";
            elseif ($sort_by === 'title_desc') $order_sql = "ORDER BY p.post_title DESC";
            elseif ($sort_by === 'date_asc') $order_sql = "ORDER BY p.post_date ASC";
            elseif ($sort_by === 'stock_asc') $order_sql = $has_stok_table ? "ORDER BY ({$depo_stock_sql}) ASC" : "ORDER BY total_calculated_stock ASC";
            elseif ($sort_by === 'stock_desc') $order_sql = $has_stok_table ? "ORDER BY ({$depo_stock_sql}) DESC" : "ORDER BY total_calculated_stock DESC";

            if (!empty($search)) {
                $exact_order_prefix = $wpdb->prepare(
                    "ORDER BY (CASE WHEN p.ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_sku', '_barcode', '_gtin', '_ean') AND meta_value = %s
                        UNION
                        SELECT p_v.post_parent FROM {$wpdb->posts} p_v
                        INNER JOIN {$wpdb->postmeta} pm_v ON p_v.ID = pm_v.post_id
                        WHERE p_v.post_type = 'product_variation' AND pm_v.meta_key IN ('_sku', '_barcode', '_gtin', '_ean') AND pm_v.meta_value = %s
                    ) THEN 0 WHEN p.post_title = %s THEN 1 ELSE 2 END) ASC, ",
                    $search,
                    $search,
                    $search
                );
                $order_sql = $exact_order_prefix . substr($order_sql, 9);
            }

            $items_query = "
                SELECT p.ID, p.post_title, p.post_date,
                       COALESCE(SUM(CAST(pm_child_stock.meta_value AS SIGNED)), CAST(pm_stock.meta_value AS SIGNED), 0) AS total_calculated_stock
                FROM {$wpdb->posts} p
                {$join_sql}
                LEFT JOIN {$wpdb->posts} p_child ON (p.ID = p_child.post_parent AND p_child.post_type = 'product_variation' AND p_child.post_status = 'publish')
                LEFT JOIN {$wpdb->postmeta} pm_child_stock ON (p_child.ID = pm_child_stock.post_id AND pm_child_stock.meta_key = '_stock')
                WHERE {$where_sql}
                GROUP BY p.ID
                {$having_clause}
                {$order_sql}
                LIMIT %d OFFSET %d
            ";

            $rows = $wpdb->get_results($wpdb->prepare($items_query, $per_page, $offset));

        } else {
            // Standard variation / simple / all scope
            if ($has_min_stock || $has_max_stock) {
                if ($has_stok_table) {
                    if ($has_min_stock) {
                        $where[] = $wpdb->prepare("({$depo_stock_sql}) >= %f", $min_stock);
                    }
                    if ($has_max_stock) {
                        $where[] = $wpdb->prepare("({$depo_stock_sql}) <= %f", $max_stock);
                    }
                } else {
                    $stock_sub_conds = [];
                    $var_sub_conds   = [];

                    if ($has_min_stock) {
                        $stock_sub_conds[] = $wpdb->prepare("CAST(pm_stock.meta_value AS SIGNED) >= %f", $min_stock);
                        $var_sub_conds[]   = $wpdb->prepare("CAST(pm_v_stock.meta_value AS SIGNED) >= %f", $min_stock);
                    }
                    if ($has_max_stock) {
                        $stock_sub_conds[] = $wpdb->prepare("CAST(pm_stock.meta_value AS SIGNED) <= %f", $max_stock);
                        $var_sub_conds[]   = $wpdb->prepare("CAST(pm_v_stock.meta_value AS SIGNED) <= %f", $max_stock);
                    }

                    $direct_stock_sql = implode(' AND ', $stock_sub_conds);
                    $var_stock_sql    = implode(' AND ', $var_sub_conds);

                    $where[] = "(
                        ({$direct_stock_sql})
                        OR
                        p.ID IN (
                            SELECT DISTINCT p_v.post_parent
                            FROM {$wpdb->posts} p_v
                            INNER JOIN {$wpdb->postmeta} pm_v_stock ON (p_v.ID = pm_v_stock.post_id AND pm_v_stock.meta_key = '_stock')
                            WHERE p_v.post_type = 'product_variation'
                              AND ({$var_stock_sql})
                        )
                    )";
                }
            }

            $where_sql = implode(' AND ', $where);
            $join_sql  = implode(' ', $join);

            $count_query = "
                SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                {$join_sql}
                WHERE {$where_sql}
            ";

            $total_items = intval($wpdb->get_var($count_query));
            $offset = ($page - 1) * $per_page;

            $order_sql = "ORDER BY p.ID DESC";
            if ($sort_by === 'title_asc') $order_sql = "ORDER BY p.post_title ASC";
            elseif ($sort_by === 'title_desc') $order_sql = "ORDER BY p.post_title DESC";
            elseif ($sort_by === 'date_asc') $order_sql = "ORDER BY p.post_date ASC";
            elseif ($sort_by === 'stock_asc') $order_sql = $has_stok_table ? "ORDER BY ({$depo_stock_sql}) ASC" : "ORDER BY CAST(pm_stock.meta_value AS SIGNED) ASC";
            elseif ($sort_by === 'stock_desc') $order_sql = $has_stok_table ? "ORDER BY ({$depo_stock_sql}) DESC" : "ORDER BY CAST(pm_stock.meta_value AS SIGNED) DESC";

            if (!empty($search)) {
                $exact_order_prefix = $wpdb->prepare(
                    "ORDER BY (CASE WHEN p.ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_sku', '_barcode', '_gtin', '_ean') AND meta_value = %s
                        UNION
                        SELECT p_v.post_parent FROM {$wpdb->posts} p_v
                        INNER JOIN {$wpdb->postmeta} pm_v ON p_v.ID = pm_v.post_id
                        WHERE p_v.post_type = 'product_variation' AND pm_v.meta_key IN ('_sku', '_barcode', '_gtin', '_ean') AND pm_v.meta_value = %s
                    ) THEN 0 WHEN p.post_title = %s THEN 1 ELSE 2 END) ASC, ",
                    $search,
                    $search,
                    $search
                );
                $order_sql = $exact_order_prefix . substr($order_sql, 9);
            }

            $items_query = "
                SELECT DISTINCT p.ID, p.post_title, p.post_type, p.post_parent, p.post_date
                FROM {$wpdb->posts} p
                {$join_sql}
                WHERE {$where_sql}
                {$order_sql}
                LIMIT %d OFFSET %d
            ";

            $rows = $wpdb->get_results($wpdb->prepare($items_query, $per_page, $offset));
        }

        // Format items response
        $items = [];
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $items[] = $this->format_product_item($row, $scope, $depo_id);
            }
        }

        $total_pages = ceil($total_items / $per_page);

        return Hizli_Kasa_API_Response::success([
            'items'      => $items,
            'pagination' => [
                'total_items'  => $total_items,
                'total_pages'  => max(1, $total_pages),
                'current_page' => $page,
                'per_page'     => $per_page,
            ],
            'meta'       => [
                'search'               => $search,
                'search_strategy'      => $search_strategy,
                'exact_sku_match'      => $exact_sku_match,
                'exact_sku_product_id' => $exact_sku_product_id,
            ],
        ]);
    }

    /**
     * Formats a single product/variation row into lightweight API data payload.
     *
     * @param object $row
     * @param string $scope
     * @param int $depo_id
     * @return array
     */
    protected function format_product_item($row, $scope, $depo_id = 0) {
        global $wpdb;

        $post_id = intval($row->ID);
        $product = wc_get_product($post_id);

        if (!$product) {
            return [
                'id'              => $post_id,
                'name'            => $row->post_title ?: 'Ürün #' . $post_id,
                'title'           => $row->post_title ?: 'Ürün #' . $post_id,
                'type'            => 'unknown',
                'stock_quantity'  => 0,
                'warehouse_stock' => 0,
                'images'          => [],
                'image_url'       => wc_placeholder_img_src(),
                'categories'      => [],
                'brands'          => [],
                'variations'      => [],
                'is_variable'     => false,
            ];
        }

        $is_variation = $product->is_type('variation');
        $is_variable  = $product->is_type('variable');
        $parent_id    = $is_variation ? $product->get_parent_id() : $post_id;
        $parent_product = $is_variation ? wc_get_product($parent_id) : $product;

        // Categories
        $categories = [];
        $cat_terms  = get_the_terms($parent_id, 'product_cat');
        if ($cat_terms && !is_wp_error($cat_terms)) {
            foreach ($cat_terms as $term) {
                $categories[] = [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                ];
            }
        }

        // Brands
        $brands = [];
        $brand_tax = $this->detect_brand_taxonomy();
        if ($brand_tax) {
            $brand_terms = get_the_terms($parent_id, $brand_tax);
            if ($brand_terms && !is_wp_error($brand_terms)) {
                foreach ($brand_terms as $b_term) {
                    $brands[] = [
                        'id'   => $b_term->term_id,
                        'name' => $b_term->name,
                    ];
                }
            }
        }

        // Barcode / SKU
        $sku     = $product->get_sku();
        $barcode = get_post_meta($post_id, '_barcode', true) 
                ?: get_post_meta($post_id, '_gtin', true) 
                ?: get_post_meta($post_id, '_ean', true) 
                ?: $sku;

        // Image
        $image_id  = $product->get_image_id() ?: ($parent_product ? $parent_product->get_image_id() : 0);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();

        // Calculate Stock
        if ($scope === 'parent_sum' && isset($row->total_calculated_stock)) {
            $stock_qty = floatval($row->total_calculated_stock);
        } else {
            $stock_qty = floatval($product->get_stock_quantity() ?: 0);
        }

        // Warehouse Stock & Shelf Codes Map
        $stok_table = $wpdb->prefix . 'hizli_kasa_stok_konumlari';
        $all_stocks = [];
        $all_codes  = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$stok_table}'") === $stok_table) {
            if ($is_variable) {
                $stock_rows = $wpdb->get_results($wpdb->prepare("
                    SELECT location_id, SUM(quantity) as quantity 
                    FROM {$stok_table}
                    WHERE product_id = %d AND variation_id > 0
                    GROUP BY location_id
                ", $post_id));
            } elseif ($is_variation) {
                $stock_rows = $wpdb->get_results($wpdb->prepare("
                    SELECT location_id, quantity, depo_kodu 
                    FROM {$stok_table}
                    WHERE variation_id = %d
                ", $post_id));
            } else {
                $stock_rows = $wpdb->get_results($wpdb->prepare("
                    SELECT location_id, quantity, depo_kodu 
                    FROM {$stok_table}
                    WHERE product_id = %d AND variation_id = 0
                ", $post_id));
            }
            if (!empty($stock_rows)) {
                foreach ($stock_rows as $sr) {
                    $all_stocks[(string)$sr->location_id] = floatval($sr->quantity);
                    if (!empty($sr->depo_kodu)) {
                        $all_codes[(string)$sr->location_id] = $sr->depo_kodu;
                    }
                }
            }
        }

        // Variation attributes
        $variation_attributes = [];
        if ($is_variation) {
            $attributes = $product->get_variation_attributes();
            foreach ($attributes as $key => $val) {
                $clean_key = str_replace('attribute_', '', $key);
                $clean_key = str_replace('pa_', '', $clean_key);
                $variation_attributes[$clean_key] = $val;
            }
        }

        // Children Variations for Variable Products
        $formatted_variations = [];
        if ($is_variable) {
            $child_ids = $product->get_children();
            if (!empty($child_ids)) {
                foreach ($child_ids as $cid) {
                    $child_var = wc_get_product($cid);
                    if (!$child_var) continue;

                    $c_img_id  = $child_var->get_image_id() ?: $image_id;
                    $c_img_url = $c_img_id ? wp_get_attachment_image_url($c_img_id, 'thumbnail') : $image_url;

                    $c_all_stocks = [];
                    $c_all_codes  = [];
                    if ($wpdb->get_var("SHOW TABLES LIKE '{$stok_table}'") === $stok_table) {
                        $c_stock_rows = $wpdb->get_results($wpdb->prepare("
                            SELECT location_id, quantity, depo_kodu FROM {$stok_table} WHERE variation_id = %d", $cid
                        ));
                        if (!empty($c_stock_rows)) {
                            foreach ($c_stock_rows as $csr) {
                                $c_all_stocks[(string)$csr->location_id] = floatval($csr->quantity);
                                if (!empty($csr->depo_kodu)) {
                                    $c_all_codes[(string)$csr->location_id] = $csr->depo_kodu;
                                }
                            }
                        }
                    }

                    $c_attrs = [];
                    foreach ($child_var->get_variation_attributes() as $ckey => $cval) {
                        $clean_k = str_replace(['attribute_', 'pa_'], '', $ckey);
                        $c_attrs[$clean_k] = $cval;
                    }

                    $formatted_variations[] = [
                        'id'              => $cid,
                        'parent_id'       => $post_id,
                        'type'            => 'variation',
                        'name'            => $child_var->get_name(),
                        'title'           => $child_var->get_name(),
                        'sku'             => $child_var->get_sku() ?: '',
                        'price'           => floatval($child_var->get_price()),
                        'regular_price'   => floatval($child_var->get_regular_price() ?: $child_var->get_price()),
                        'warehouse_stock' => ($depo_id > 0 && isset($c_all_stocks[(string)$depo_id])) ? floatval($c_all_stocks[(string)$depo_id]) : floatval($child_var->get_stock_quantity() ?: 0),
                        'stock_quantity'  => floatval($child_var->get_stock_quantity() ?: 0),
                        'all_stocks'      => (object)$c_all_stocks,
                        'all_codes'       => (object)$c_all_codes,
                        'images'          => [['src' => $c_img_url]],
                        'image_url'       => $c_img_url,
                        'attributes'      => $c_attrs,
                    ];
                }
            }
        }

        $title_name = $product->get_name();
        $regular_price = floatval($product->get_regular_price() ?: $product->get_price());

        $active_warehouse_stock = ($depo_id > 0 && isset($all_stocks[(string)$depo_id])) 
            ? floatval($all_stocks[(string)$depo_id]) 
            : $stock_qty;

        return [
            'id'                   => $post_id,
            'parent_id'            => $parent_id,
            'type'                 => $product->get_type(),
            'name'                 => $title_name,
            'title'                => $title_name,
            'sku'                  => $sku,
            'barcode'              => $barcode,
            'price'                => floatval($product->get_price()),
            'regular_price'        => $regular_price,
            'stock_quantity'       => $stock_qty,
            'warehouse_stock'      => $active_warehouse_stock,
            'stock_status'         => $product->get_stock_status(),
            'categories'           => $categories,
            'brands'               => $brands,
            'variation_attributes' => $variation_attributes,
            'image_url'            => $image_url,
            'images'               => [['src' => $image_url]],
            'all_stocks'           => (object)$all_stocks,
            'all_codes'            => (object)$all_codes,
            'permalink'            => get_permalink($post_id),
            'is_variable'          => $is_variable,
            'variations'           => $formatted_variations,
            'created_at'           => get_the_date('Y-m-d H:i:s', $post_id),
        ];
    }

    /**
     * Inner logic for getting filter dropdown options.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    protected function get_filter_options($request) {
        // Categories
        $categories = [];
        $cat_terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);
        if (!is_wp_error($cat_terms)) {
            foreach ($cat_terms as $ct) {
                $categories[] = [
                    'id'     => $ct->term_id,
                    'name'   => $ct->name,
                    'count'  => $ct->count,
                    'parent' => $ct->parent,
                ];
            }
        }

        // Brands
        $brands = [];
        $brand_tax = $this->detect_brand_taxonomy();
        if ($brand_tax) {
            $b_terms = get_terms([
                'taxonomy'   => $brand_tax,
                'hide_empty' => false,
            ]);
            if (!is_wp_error($b_terms)) {
                foreach ($b_terms as $bt) {
                    $brands[] = [
                        'id'    => $bt->term_id,
                        'name'  => $bt->name,
                        'count' => $bt->count,
                    ];
                }
            }
        }

        // Attributes & Terms
        $attributes = [];
        if (function_exists('wc_get_attribute_taxonomies')) {
            $taxonomies = wc_get_attribute_taxonomies();
            foreach ($taxonomies as $tax) {
                $tax_name = wc_attribute_taxonomy_name($tax->attribute_name);
                $terms = get_terms([
                    'taxonomy'   => $tax_name,
                    'hide_empty' => false,
                ]);

                $terms_list = [];
                if (!is_wp_error($terms)) {
                    foreach ($terms as $t) {
                        $terms_list[] = [
                            'id'   => $t->term_id,
                            'name' => $t->name,
                            'slug' => $t->slug,
                        ];
                    }
                }

                $attributes[] = [
                    'slug'  => $tax_name,
                    'label' => $tax->attribute_label,
                    'terms' => $terms_list,
                ];
            }
        }

        // Tags
        $tags = [];
        $tag_terms = get_terms([
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
        ]);
        if (!is_wp_error($tag_terms)) {
            foreach ($tag_terms as $tt) {
                $tags[] = [
                    'id'    => $tt->term_id,
                    'name'  => $tt->name,
                    'count' => $tt->count,
                ];
            }
        }

        return Hizli_Kasa_API_Response::success([
            'categories' => $categories,
            'brands'     => $brands,
            'attributes' => $attributes,
            'tags'       => $tags,
        ]);
    }

    /**
     * Parses comma-separated ID strings or arrays into array of positive integers.
     *
     * @param mixed $input
     * @return array
     */
    protected function parse_id_list($input) {
        if (empty($input)) {
            return [];
        }

        if (is_string($input)) {
            $parts = explode(',', $input);
        } elseif (is_array($input)) {
            $parts = $input;
        } else {
            return [];
        }

        $ids = [];
        foreach ($parts as $p) {
            $id = absint(trim($p));
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_unique($ids);
    }

    /**
     * Auto-detects the active brand taxonomy in the WooCommerce installation.
     *
     * @return string|false
     */
    protected function detect_brand_taxonomy() {
        $candidates = ['product_brand', 'pwb-brand', 'brand', 'pbr_brand'];
        foreach ($candidates as $tax) {
            if (taxonomy_exists($tax)) {
                return $tax;
            }
        }
        return false;
    }
}
