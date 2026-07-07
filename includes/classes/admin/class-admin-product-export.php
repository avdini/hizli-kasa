<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Admin_Product_Export {

    public static function init() {
        add_filter('bulk_actions-edit-product', [self::class, 'add_bulk_action']);
        add_filter('handle_bulk_actions-edit-product', [self::class, 'handle_bulk_action'], 10, 3);
        add_action('admin_menu', [self::class, 'register_hidden_page']);
    }

    public static function add_bulk_action($bulk_actions) {
        $bulk_actions['hk_export_products'] = __('Hızlı Kasa - Müşteri Bilgi Tablosu Dışa Aktar', 'hizli-kasa');
        return $bulk_actions;
    }

    public static function handle_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== 'hk_export_products') {
            return $redirect_to;
        }

        $redirect_to = admin_url('admin.php?page=hk-product-export&product_ids=' . implode(',', array_map('intval', $post_ids)));
        return $redirect_to;
    }

    public static function register_hidden_page() {
        add_submenu_page(
            null,
            __('Ürün Dışa Aktarma', 'hizli-kasa'),
            __('Ürün Dışa Aktarma', 'hizli-kasa'),
            'manage_options',
            'hk-product-export',
            [self::class, 'render_export_page']
        );
    }

    public static function render_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Yetkiniz yetersiz.', 'hizli-kasa'));
        }

        $product_ids_raw = isset($_GET['product_ids']) ? sanitize_text_field($_GET['product_ids']) : '';
        if (empty($product_ids_raw)) {
            wp_die(__('Lütfen en az bir ürün seçin.', 'hizli-kasa'));
        }

        $product_ids = array_filter(array_map('intval', explode(',', $product_ids_raw)));
        if (empty($product_ids)) {
            wp_die(__('Geçersiz Ürün ID\'leri.', 'hizli-kasa'));
        }

        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        $stok_table = $tables['stok_konumlari'];
        $depolar_table = $tables['depolar'];

        $warehouses = $wpdb->get_results("SELECT id, name FROM {$depolar_table} ORDER BY priority DESC, id ASC");

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $stock_rows = $wpdb->get_results($wpdb->prepare("
            SELECT product_id, variation_id, location_id, quantity
            FROM {$stok_table}
            WHERE product_id IN ($placeholders)
        ", ...$product_ids));

        $stock_map = [];
        foreach ($stock_rows as $row) {
            $p_id = (int)$row->product_id;
            $v_id = (int)$row->variation_id;
            $l_id = (int)$row->location_id;
            $qty = floatval($row->quantity);
            $stock_map[$p_id][$v_id][$l_id] = $qty;
        }

        include HIZLI_KASA_PATH . 'includes/views/product-export-template.php';
    }
}
