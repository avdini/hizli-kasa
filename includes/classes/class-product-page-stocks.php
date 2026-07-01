<?php
if (!defined('ABSPATH')) exit;

class Hizli_Kasa_Product_Page_Stocks {
    public static function init() {
        add_action('woocommerce_single_product_summary', [self::class, 'render_stocks_container'], 35);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('wp_ajax_hk_get_product_warehouse_stocks', [self::class, 'ajax_get_product_stocks']);
        add_shortcode('hk_depo_stoklari', [self::class, 'shortcode_render_container']);
    }

    public static function enqueue_assets() {
        if (!is_product() || !is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $allowed_depos = Hizli_Kasa_User_Warehouse_Permissions::get_view_depos($user_id);
        if (empty($allowed_depos)) {
            return;
        }

        wp_enqueue_style(
            'hk-product-page-stocks',
            HIZLI_KASA_URL . 'assets/css/product-page-stocks.css',
            [],
            HIZLI_KASA_VERSION
        );

        wp_enqueue_script(
            'hk-product-page-stocks',
            HIZLI_KASA_URL . 'assets/js/product-page-stocks.js',
            ['jquery'],
            HIZLI_KASA_VERSION,
            true
        );

        wp_localize_script('hk-product-page-stocks', 'hk_product_stocks_obj', [
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('hk_product_stocks_nonce'),
            'product_id' => get_the_ID(),
            'i18n'       => [
                'title'              => __('Depo Stok Durumu', 'hizli-kasa'),
                'badge'              => __('Sadece Yetkili Personel', 'hizli-kasa'),
                'search_placeholder' => __('Varyasyon ara...', 'hizli-kasa'),
                'th_variation'       => __('Varyasyon', 'hizli-kasa'),
                'th_physical'        => __('Fiziksel', 'hizli-kasa'),
                'th_reserved'        => __('Rezerve', 'hizli-kasa'),
                'th_net'             => __('Net Stok', 'hizli-kasa'),
                'loading'            => __('Yükleniyor...', 'hizli-kasa'),
                'no_stock'           => __('Stok kaydı bulunamadı.', 'hizli-kasa'),
                'no_permission'      => __('Bu veriyi görme yetkiniz yok.', 'hizli-kasa')
            ]
        ]);
    }

    public static function render_stocks_container() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $allowed_depos = Hizli_Kasa_User_Warehouse_Permissions::get_view_depos($user_id);
        if (empty($allowed_depos)) {
            return;
        }

        echo '<div id="hk-product-warehouse-stocks-container" data-product-id="' . esc_attr(get_the_ID()) . '"></div>';
    }

    public static function shortcode_render_container($atts) {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();
        $allowed_depos = Hizli_Kasa_User_Warehouse_Permissions::get_view_depos($user_id);
        if (empty($allowed_depos)) {
            return '';
        }

        $product_id = get_the_ID();
        if (isset($atts['id'])) {
            $product_id = intval($atts['id']);
        }

        if (!$product_id) {
            return '';
        }

        return '<div id="hk-product-warehouse-stocks-container" data-product-id="' . esc_attr($product_id) . '"></div>';
    }

    public static function ajax_get_product_stocks() {
        check_ajax_referer('hk_product_stocks_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Oturum açmanız gerekiyor.', 401);
        }

        $user_id = get_current_user_id();
        $allowed_depo_ids = Hizli_Kasa_User_Warehouse_Permissions::get_view_depos($user_id);
        if (empty($allowed_depo_ids)) {
            wp_send_json_error('Bu veriyi görme yetkiniz yok.', 403);
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error('Ürün bulunamadı.', 404);
        }

        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $depo_table = $tables['depolar'];
        $stok_table = $tables['stok_konumlari'];

        $allowed_depo_ids = array_map('intval', $allowed_depo_ids);
        $placeholders = implode(',', array_fill(0, count($allowed_depo_ids), '%d'));

        $warehouses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name FROM $depo_table WHERE id IN ($placeholders) ORDER BY priority DESC, name ASC",
                $allowed_depo_ids
            ),
            ARRAY_A
        );

        if (empty($warehouses)) {
            wp_send_json_success([
                'is_variable' => false,
                'warehouses'  => [],
                'variations'  => []
            ]);
        }

        $stocks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT location_id, variation_id, quantity, reserved FROM $stok_table WHERE product_id = %d AND location_id IN ($placeholders)",
                array_merge([$product_id], $allowed_depo_ids)
            )
        );

        $is_variable = $product->is_type('variable');
        $variation_data = [];

        if ($is_variable) {
            $variation_ids = $product->get_children();
            foreach ($variation_ids as $v_id) {
                $variation = wc_get_product($v_id);
                if (!$variation) continue;

                $attributes = $variation->get_variation_attributes();
                $formatted = [];
                foreach ($attributes as $taxonomy => $value) {
                    $clean_taxonomy = str_replace('attribute_', '', $taxonomy);
                    $label = wc_attribute_label($clean_taxonomy, $product);
                    if (taxonomy_exists($clean_taxonomy)) {
                        $term = get_term_by('slug', $value, $clean_taxonomy);
                        $val_label = ($term && !is_wp_error($term)) ? $term->name : $value;
                    } else {
                        $val_label = $value;
                    }
                    $formatted[] = $label . ': ' . $val_label;
                }
                $name = !empty($formatted) ? implode(', ', $formatted) : '#' . $v_id;

                $variation_data[$v_id] = [
                    'id'     => $v_id,
                    'name'   => $name,
                    'stocks' => []
                ];

                foreach ($warehouses as $wh) {
                    $variation_data[$v_id]['stocks'][$wh['id']] = [
                        'qty'   => 0.0,
                        'res'   => 0.0,
                        'avail' => 0.0
                    ];
                }
            }
        } else {
            $variation_data[0] = [
                'id'     => 0,
                'name'   => $product->get_name(),
                'stocks' => []
            ];
            foreach ($warehouses as $wh) {
                $variation_data[0]['stocks'][$wh['id']] = [
                    'qty'   => 0.0,
                    'res'   => 0.0,
                    'avail' => 0.0
                ];
            }
        }

        foreach ($stocks as $s) {
            $v_id = (int)$s->variation_id;
            $loc_id = (int)$s->location_id;
            $key = $is_variable ? $v_id : 0;

            if (isset($variation_data[$key]['stocks'][$loc_id])) {
                $qty = (float)$s->quantity;
                $res = (float)$s->reserved;
                $variation_data[$key]['stocks'][$loc_id] = [
                    'qty'   => $qty,
                    'res'   => $res,
                    'avail' => max(0.0, $qty - $res)
                ];
            }
        }

        wp_send_json_success([
            'is_variable' => $is_variable,
            'warehouses'  => $warehouses,
            'variations'  => array_values($variation_data)
        ]);
    }
}
