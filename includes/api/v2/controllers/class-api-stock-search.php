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
        $sort_by               = sanitize_text_field($request->get_param('sort_by') ?: 'date_desc');
        $page                  = max(1, absint($request->get_param('page') ?: 1));
        $per_page              = min(100, max(1, absint($request->get_param('per_page') ?: 20)));

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
            $where[] = "p.post_type IN ('product', 'product_variation')";
        }
        $where[] = "p.post_status = 'publish'";

        // Text Search (Name, SKU, Barcode)
        if (!empty($search)) {
            $s_like = '%' . $wpdb->esc_like($search) . '%';
            $search_conds = [
                $wpdb->prepare("p.post_title LIKE %s", $s_like),
                $wpdb->prepare("p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s)", $s_like),
                $wpdb->prepare("p.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_barcode', '_gtin', '_ean') AND meta_value LIKE %s)", $s_like),
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

        // Stock quantity filter
        $join[] = "LEFT JOIN {$wpdb->postmeta} pm_stock ON (p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock')";
        $join[] = "LEFT JOIN {$wpdb->postmeta} pm_stock_status ON (p.ID = pm_stock_status.post_id AND pm_stock_status.meta_key = '_stock_status')";

        if ($stock_status !== 'all') {
            if ($stock_status === 'instock') {
                $where[] = "pm_stock_status.meta_value = 'instock'";
            } elseif ($stock_status === 'outofstock') {
                $where[] = "pm_stock_status.meta_value = 'outofstock'";
            } elseif ($stock_status === 'lowstock') {
                $where[] = "(CAST(pm_stock.meta_value AS SIGNED) <= 2 AND pm_stock_status.meta_value = 'instock')";
            }
        }

        if ($scope === 'parent_sum') {
            // Calculate parent total stock sum across variations
            $where[] = "p.post_type = 'product'";
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
            elseif ($sort_by === 'stock_asc') $order_sql = "ORDER BY total_calculated_stock ASC";
            elseif ($sort_by === 'stock_desc') $order_sql = "ORDER BY total_calculated_stock DESC";

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
            if ($has_min_stock) {
                $where[] = $wpdb->prepare("CAST(pm_stock.meta_value AS SIGNED) >= %f", $min_stock);
            }
            if ($has_max_stock) {
                $where[] = $wpdb->prepare("CAST(pm_stock.meta_value AS SIGNED) <= %f", $max_stock);
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
            elseif ($sort_by === 'stock_asc') $order_sql = "ORDER BY CAST(pm_stock.meta_value AS SIGNED) ASC";
            elseif ($sort_by === 'stock_desc') $order_sql = "ORDER BY CAST(pm_stock.meta_value AS SIGNED) DESC";

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
                $items[] = $this->format_product_item($row, $scope);
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
        ]);
    }

    /**
     * Formats a single product/variation row into lightweight API data payload.
     *
     * @param object $row
     * @param string $scope
     * @return array
     */
    protected function format_product_item($row, $scope) {
        $post_id = intval($row->ID);
        $product = wc_get_product($post_id);

        if (!$product) {
            return [
                'id'         => $post_id,
                'title'      => $row->post_title,
                'type'       => 'unknown',
                'stock'      => 0,
                'categories' => [],
                'brands'     => [],
            ];
        }

        $is_variation = $product->is_type('variation');
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

        // Variation attributes
        $variation_attributes = [];
        if ($is_variation) {
            $attributes = $product->get_variation_attributes();
            foreach ($attributes as $key => $val) {
                $clean_key = str_replace('attribute_', '', $key);
                $variation_attributes[$clean_key] = $val;
            }
        }

        return [
            'id'                   => $post_id,
            'parent_id'            => $parent_id,
            'type'                 => $product->get_type(),
            'title'                => $product->get_name(),
            'sku'                  => $sku,
            'barcode'              => $barcode,
            'price'                => floatval($product->get_price()),
            'stock_quantity'       => $stock_qty,
            'stock_status'         => $product->get_stock_status(),
            'categories'           => $categories,
            'brands'               => $brands,
            'variation_attributes' => $variation_attributes,
            'image_url'            => $image_url,
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
