<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Catalog_Public_Handler {

    public static function init() {
        add_action('template_redirect', [self::class, 'maybe_serve_catalog']);
        add_action('wp_ajax_hk_create_catalog_share', [self::class, 'ajax_create_share']);
        add_action('wp_ajax_hk_delete_catalog_share', [self::class, 'ajax_delete_share']);
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
}
