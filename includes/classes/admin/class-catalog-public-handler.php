<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Catalog_Public_Handler {

    public static function init() {
        add_action('template_redirect', [self::class, 'maybe_serve_catalog']);
        add_action('wp_ajax_hk_create_catalog_share', [self::class, 'ajax_create_share']);
        add_action('wp_ajax_hk_delete_catalog_share', [self::class, 'ajax_delete_share']);
        add_action('wp_ajax_hk_get_catalog_dynamic_data', [self::class, 'ajax_get_catalog_dynamic_data']);
        add_action('wp_ajax_nopriv_hk_get_catalog_dynamic_data', [self::class, 'ajax_get_catalog_dynamic_data']);
    }

    public static function maybe_serve_catalog() {
        $token = isset($_GET['hk_catalog']) ? sanitize_text_field(wp_unslash($_GET['hk_catalog'])) : '';
        if (empty($token)) {
            return;
        }

        $share = Hizli_Kasa_Catalog_Share_Manager::get_share($token);

        if (!$share) {
            wp_die(
                '<h2 style="font-family:sans-serif;text-align:center;margin-top:80px;">Bu katalog linki geçersiz veya süresi dolmuş.</h2>',
                'Katalog Bulunamadı',
                ['response' => 410]
            );
        }

        $product_ids = $share['product_ids'];
        $options     = $share['options'];

        global $wpdb;
        $tables       = Hizli_Kasa_Database::get_tables();
        $warehouses   = $wpdb->get_results("SELECT id, name FROM {$tables['depolar']} ORDER BY priority DESC, id ASC");

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $stock_rows   = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, variation_id, location_id, quantity FROM {$tables['stok_konumlari']} WHERE product_id IN ($placeholders)",
            ...$product_ids
        ));

        $stock_map = [];
        foreach ($stock_rows as $row) {
            $stock_map[(int)$row->product_id][(int)$row->variation_id][(int)$row->location_id] = floatval($row->quantity);
        }

        $is_public = true;
        include HIZLI_KASA_PATH . 'includes/views/product-export-template.php';
        exit;
    }

    public static function ajax_create_share() {
        check_ajax_referer('hk_catalog_share_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Yetkiniz yok.', 'hizli-kasa')]);
        }

        $product_ids = array_filter(array_map('intval', explode(',', sanitize_text_field($_POST['product_ids'] ?? ''))));
        $ttl_days    = max(1, min(90, (int) ($_POST['ttl_days'] ?? 7)));
        $title       = sanitize_text_field($_POST['catalog_title'] ?? '');

        if (empty($product_ids)) {
            wp_send_json_error(['message' => __('Ürün seçilmedi.', 'hizli-kasa')]);
        }

        $options = [
            'ttl_days' => $ttl_days,
            'title'    => $title,
        ];

        $token = Hizli_Kasa_Catalog_Share_Manager::create_share($product_ids, $options);

        if (is_wp_error($token)) {
            wp_send_json_error(['message' => $token->get_error_message()]);
        }

        wp_send_json_success([
            'url'        => Hizli_Kasa_Catalog_Share_Manager::get_public_url($token),
            'token'      => $token,
            'expires_in' => $ttl_days . ' gün',
        ]);
    }

    public static function ajax_delete_share() {
        check_ajax_referer('hk_catalog_share_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Yetkiniz yok.', 'hizli-kasa')]);
        }

        $token = sanitize_text_field($_POST['token'] ?? '');
        if (empty($token)) {
            wp_send_json_error(['message' => __('Token gerekli.', 'hizli-kasa')]);
        }

        Hizli_Kasa_Catalog_Share_Manager::delete_share($token);
        wp_send_json_success(['message' => __('Link silindi.', 'hizli-kasa')]);
    }

    public static function ajax_get_catalog_dynamic_data() {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        
        do_action('litespeed_control_force_nocache');

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            header('X-LiteSpeed-Cache-Control: no-cache');
        }

        $token       = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $product_ids = [];

        if (!empty($token)) {
            $share = Hizli_Kasa_Catalog_Share_Manager::get_share($token);
            if ($share) {
                $product_ids = $share['product_ids'];
            }
        } elseif (current_user_can('manage_options')) {
            $product_ids = array_filter(array_map('intval', explode(',', sanitize_text_field($_GET['product_ids'] ?? ''))));
        }

        if (empty($product_ids)) {
            wp_send_json_error(['message' => __('Ürün bulunamadı veya yetkisiz erişim.', 'hizli-kasa')]);
        }

        global $wpdb;
        $tables       = Hizli_Kasa_Database::get_tables();
        $warehouses   = $wpdb->get_results("SELECT id, name FROM {$tables['depolar']} ORDER BY priority DESC, id ASC");

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $stock_rows   = $wpdb->get_results($wpdb->prepare(
            "SELECT product_id, variation_id, location_id, quantity FROM {$tables['stok_konumlari']} WHERE product_id IN ($placeholders)",
            ...$product_ids
        ));

        $stock_map = [];
        foreach ($stock_rows as $row) {
            $stock_map[(int)$row->product_id][(int)$row->variation_id][(int)$row->location_id] = floatval($row->quantity);
        }

        $dynamic_data = [];
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            $price_clean = html_entity_decode(strip_tags($product->get_price_html()), ENT_QUOTES, 'UTF-8');

            $stocks = [];
            $total_stock = 0;
            foreach ($warehouses as $wh) {
                $qty = isset($stock_map[$product_id][0][$wh->id]) ? $stock_map[$product_id][0][$wh->id] : 0;
                $stocks[$wh->id] = $qty;
                $total_stock += $qty;
            }

            $item = [
                'price'       => $price_clean,
                'stocks'      => $stocks,
                'total_stock' => $total_stock,
                'variations'  => [],
            ];

            if ($product->is_type('variable')) {
                $variation_ids   = $product->get_children();
                $var_total_stock = 0;
                foreach ($variation_ids as $var_id) {
                    $variation = wc_get_product($var_id);
                    if (!$variation) {
                        continue;
                    }

                    $var_price = html_entity_decode(strip_tags(wc_price($variation->get_price())), ENT_QUOTES, 'UTF-8');

                    $v_stocks = [];
                    $v_total_stock = 0;
                    foreach ($warehouses as $wh) {
                        $qty = isset($stock_map[$product_id][$var_id][$wh->id]) ? $stock_map[$product_id][$var_id][$wh->id] : 0;
                        $v_stocks[$wh->id] = $qty;
                        $v_total_stock += $qty;
                    }
                    $var_total_stock += $v_total_stock;

                    $item['variations'][$var_id] = [
                        'price'       => $var_price,
                        'stocks'      => $v_stocks,
                        'total_stock' => $v_total_stock,
                    ];
                }

                if (!empty($item['variations'])) {
                    $item['total_stock'] = $var_total_stock;
                    foreach ($warehouses as $wh) {
                        $wh_sum = 0;
                        foreach ($item['variations'] as $v) {
                            $wh_sum += $v['stocks'][$wh->id];
                        }
                        $item['stocks'][$wh->id] = $wh_sum;
                    }
                }
            }

            $dynamic_data[$product_id] = $item;
        }

        wp_send_json_success([
            'products' => $dynamic_data
        ]);
    }
}
