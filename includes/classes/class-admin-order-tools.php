<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Admin_Order_Tools
{
    const NONCE_ACTION = 'hizli_kasa_admin_order_tools';

    public static function init()
    {
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes'], 20, 2);
        add_action('add_meta_boxes_shop_order', [self::class, 'add_meta_box_to_current_screen'], 20, 1);
        add_action('add_meta_boxes_woocommerce_page_wc-orders', [self::class, 'add_meta_box_to_current_screen'], 20, 1);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('wp_ajax_hk_admin_order_tools_save', [self::class, 'ajax_save_order']);
    }

    public static function add_meta_boxes($screen_id, $post_or_order_object)
    {
        if (!self::can_manage_orders()) {
            return;
        }

        $screens = ['shop_order'];
        if (function_exists('wc_get_page_screen_id')) {
            $screens[] = wc_get_page_screen_id('shop-order');
        } else {
            $screens[] = 'woocommerce_page_wc-orders';
        }

        foreach (array_unique($screens) as $screen) {
            add_meta_box(
                'hizli-kasa-admin-order-tools',
                'Hizli Kasa Gelismis Siparis Paneli',
                [self::class, 'render_meta_box'],
                $screen,
                'normal',
                'low'
            );
        }
    }

    public static function add_meta_box_to_current_screen($post_or_order_object = null)
    {
        if (!self::can_manage_orders() || !function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !self::is_order_screen_id($screen->id)) {
            return;
        }

        add_meta_box(
            'hizli-kasa-admin-order-tools',
            'Hizli Kasa Gelismis Siparis Paneli',
            [self::class, 'render_meta_box'],
            $screen->id,
            'normal',
            'low'
        );
    }

    public static function enqueue_assets($hook)
    {
        if (!self::is_order_edit_screen()) {
            return;
        }

        wp_enqueue_style(
            'hizli-kasa-admin-order-tools',
            HIZLI_KASA_URL . 'assets/css/admin-order-tools.css',
            [],
            HIZLI_KASA_VERSION
        );

        wp_enqueue_script(
            'hizli-kasa-admin-order-tools',
            HIZLI_KASA_URL . 'assets/js/admin-order-tools.js',
            [],
            HIZLI_KASA_VERSION,
            true
        );

        wp_localize_script('hizli-kasa-admin-order-tools', 'hkAdminOrderTools', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'metaCatalog' => self::get_meta_catalog(),
            'depolar' => self::get_depolar(),
            'paymentMethods' => [
                'cod' => 'Nakit',
                'other' => 'Kredi Karti',
                'bacs' => 'IBAN / Havale',
                'split' => 'Bolunmus Odeme',
            ],
            'kaynaklar' => [
                'pos_satis' => 'POS Satis',
                'web' => 'Web',
                'telefon' => 'Telefon',
                'magaza' => 'Magaza',
                'diger' => 'Diger',
            ],
            'labels' => [
                'saving' => 'Kaydediliyor...',
                'saved' => 'Siparis guncellendi.',
                'error' => 'Islem sirasinda hata olustu.',
                'confirm' => 'Siparis kalemleri ve metalari guncellenecek. Devam edilsin mi?',
            ],
        ]);
    }

    public static function render_meta_box($post_or_order_object)
    {
        $order = self::resolve_order($post_or_order_object);
        if (!$order) {
            echo '<p>Siparis bulunamadi.</p>';
            return;
        }

        $order_id = $order->get_id();
        $currency = $order->get_currency();
        $meta_catalog = self::get_meta_catalog();
        include HIZLI_KASA_PATH . 'includes/views/admin-order-meta-box.php';
    }

    public static function ajax_save_order()
    {
        if (!self::can_manage_orders()) {
            wp_send_json_error(['message' => 'Yetkiniz yok.'], 403);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $payload = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            wp_send_json_error(['message' => 'Gecersiz veri.'], 400);
        }

        $order_id = absint($data['order_id'] ?? 0);
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Siparis bulunamadi.'], 404);
        }

        try {
            self::save_line_items($order, $data['items'] ?? []);
            self::save_new_products($order, $data['new_products'] ?? []);
            self::save_fees($order, $data['fees'] ?? []);
            self::save_new_fees($order, $data['new_fees'] ?? []);
            self::save_shipping($order, $data['shipping'] ?? []);
            self::save_meta($order, $data['meta'] ?? []);
            self::save_new_meta($order, $data['new_meta'] ?? []);

            if (!empty($data['recalculate'])) {
                $order->calculate_totals(true);
            }

            // Siparis Bilgileri panelinden gelen verileri kaydet
            // (recalculate_totals'dan sonra calisir, cunku odeme yontemi
            // degisikliginde guncel toplam gerekir)
            self::save_order_info($order, $data['order_info'] ?? []);

            $order->add_order_note('Hizli Kasa gelismis admin paneli ile siparis guncellendi.');
            $order->save();
            hizli_kasa_invalidate_reports_cache();

            wp_send_json_success([
                'message' => 'Siparis guncellendi.',
                'total' => $order->get_formatted_order_total(),
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    private static function save_line_items($order, $items)
    {
        foreach ((array) $items as $row) {
            $item_id = absint($row['id'] ?? 0);
            $item = $item_id ? $order->get_item($item_id) : false;
            if (!$item || !is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            if (!empty($row['remove'])) {
                $order->remove_item($item_id);
                continue;
            }

            $item->set_quantity(max(0, wc_stock_amount($row['qty'] ?? 0)));
            $item->set_subtotal(wc_format_decimal($row['subtotal'] ?? 0));
            $item->set_total(wc_format_decimal($row['total'] ?? 0));
            self::save_item_meta($item, $row['item_meta'] ?? null);
            $item->save();
        }
    }

    private static function save_new_products($order, $rows)
    {
        foreach ((array) $rows as $row) {
            $product_id = absint($row['product_id'] ?? 0);
            $qty = max(1, wc_stock_amount($row['qty'] ?? 1));
            $product = $product_id ? wc_get_product($product_id) : false;
            if (!$product) {
                continue;
            }

            $total = isset($row['total']) && $row['total'] !== '' ? (float) wc_format_decimal($row['total']) : ((float) $product->get_price() * $qty);
            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity($qty);
            $item->set_subtotal(wc_format_decimal($total));
            $item->set_total(wc_format_decimal($total));
            $item->add_meta_data('_hk_admin_added', 'yes', true);
            $order->add_item($item);
        }
    }

    private static function save_fees($order, $fees)
    {
        foreach ((array) $fees as $row) {
            $fee_id = absint($row['id'] ?? 0);
            $fee = $fee_id ? $order->get_item($fee_id) : false;
            if (!$fee || !is_a($fee, 'WC_Order_Item_Fee')) {
                continue;
            }

            if (!empty($row['remove'])) {
                $order->remove_item($fee_id);
                continue;
            }

            $total = wc_format_decimal($row['total'] ?? 0);
            $fee->set_name(sanitize_text_field($row['name'] ?? $fee->get_name()));
            $fee->set_amount($total);
            $fee->set_total(wc_format_decimal($total));
            $fee->save();
        }
    }

    private static function save_new_fees($order, $fees)
    {
        foreach ((array) $fees as $row) {
            $name = sanitize_text_field($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $total = wc_format_decimal($row['total'] ?? 0);
            $fee = new WC_Order_Item_Fee();
            $fee->set_name($name);
            $fee->set_amount($total);
            $fee->set_total(wc_format_decimal($total));
            $order->add_item($fee);
        }
    }

    private static function save_shipping($order, $rows)
    {
        foreach ((array) $rows as $row) {
            $shipping_id = absint($row['id'] ?? 0);
            $shipping = $shipping_id ? $order->get_item($shipping_id) : false;
            if (!$shipping || !is_a($shipping, 'WC_Order_Item_Shipping')) {
                continue;
            }

            if (!empty($row['remove'])) {
                $order->remove_item($shipping_id);
                continue;
            }

            $shipping->set_method_title(sanitize_text_field($row['title'] ?? $shipping->get_method_title()));
            $shipping->set_total(wc_format_decimal($row['total'] ?? 0));
            $shipping->save();
        }
    }

    private static function save_meta($order, $rows)
    {
        foreach ((array) $rows as $row) {
            $meta_id = absint($row['id'] ?? 0);
            $key = sanitize_text_field($row['key'] ?? '');
            if ($key === '') {
                continue;
            }

            if (!empty($row['remove'])) {
                if ($meta_id && method_exists($order, 'delete_meta_data_by_mid')) {
                    $order->delete_meta_data_by_mid($meta_id);
                } else {
                    $order->delete_meta_data($key);
                }
                continue;
            }

            $order->update_meta_data($key, self::parse_meta_value($row['value'] ?? ''), $meta_id ?: '');
        }
    }

    private static function save_new_meta($order, $rows)
    {
        foreach ((array) $rows as $row) {
            $key = sanitize_text_field($row['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $order->update_meta_data($key, self::parse_meta_value($row['value'] ?? ''));
        }
    }

    private static function get_meta_catalog()
    {
        return [
            'order' => [
                '_hizli_kasa_kasiyer' => ['label' => 'Kasiyer', 'default' => ''],
                '_hizli_kasa_kasa_no' => ['label' => 'Kasa no', 'default' => ''],
                '_hizli_kasa_musteri_telefon' => ['label' => 'Musteri telefonu', 'default' => ''],
                '_hizli_kasa_kaynak' => ['label' => 'Kaynak', 'default' => 'pos_satis'],
                '_hizli_kasa_is_refund' => ['label' => 'Iade kaydi', 'default' => 'yes'],
                '_hizli_kasa_manual_refund' => ['label' => 'Manuel iade', 'default' => 'yes'],
                '_hizli_kasa_original_order' => ['label' => 'Orijinal siparis ID', 'default' => ''],
                '_hk_cikis_depo_id' => ['label' => 'Cikis depo ID', 'default' => ''],
                '_hk_cikis_depo_adi' => ['label' => 'Cikis depo adi', 'default' => ''],
                '_hk_toplam_iskonto' => ['label' => 'Toplam iskonto', 'default' => '0.00'],
                '_hk_otomatik_indirim' => ['label' => 'Otomatik indirim', 'default' => '0.00'],
                '_hk_exchange_refund_total' => ['label' => 'Degisim iade toplami', 'default' => '0.00'],
                '_hk_customer_paid_total' => ['label' => 'Musteri odedi', 'default' => '0.00'],
                '_hk_refunded_discount' => ['label' => 'Iade edilen iskonto', 'default' => '0.00'],
                '_hk_has_refund' => ['label' => 'Iade var', 'default' => 'yes'],
                '_hk_is_fully_refunded' => ['label' => 'Tam iade', 'default' => 'yes'],
                '_hk_iade_depo_ozet' => ['label' => 'Iade depo ozeti', 'default' => '[]'],
                '_hk_kaynak' => ['label' => 'Rapor kaynak etiketi', 'default' => ''],
                '_odeme_nakit' => ['label' => 'Nakit odeme', 'default' => '0.00'],
                '_odeme_kart' => ['label' => 'Kart odeme', 'default' => '0.00'],
                '_odeme_iban' => ['label' => 'IBAN odeme', 'default' => '0.00'],
                '_ara_toplam' => ['label' => 'Ara toplam', 'default' => '0.00'],
                '_etiket_toplami' => ['label' => 'Etiket toplami', 'default' => '0.00'],
                'Ödeme (Nakit)' => ['label' => 'Gorunen nakit odeme', 'default' => '0.00 TL'],
                'Ödeme (Kredi Kartı)' => ['label' => 'Gorunen kredi karti odeme', 'default' => '0.00 TL'],
                'Ödeme (Kart)' => ['label' => 'Gorunen kart odeme', 'default' => '0.00 TL'],
                'Ödeme (IBAN)' => ['label' => 'Gorunen IBAN odeme', 'default' => '0.00 TL'],
            ],
            'item' => [
                '_hk_item_discount' => ['label' => 'Kalem iskontosu', 'default' => '0.00'],
                '_hk_cikis_depo_id' => ['label' => 'Kalem cikis depo ID', 'default' => ''],
                '_hk_cikis_depo_adet' => ['label' => 'Kalem cikis depo adedi', 'default' => ''],
                '_hk_cikis_depo_adi' => ['label' => 'Kalem cikis depo adi', 'default' => ''],
                '_hk_refunded_qty' => ['label' => 'Iade edilen adet', 'default' => '0'],
                '_hk_reservations' => ['label' => 'Stok rezervasyonlari', 'default' => '[]'],
                '_hk_deductions' => ['label' => 'Stok dusumleri', 'default' => '[]'],
                '_hk_restocked_on_cancel' => ['label' => 'Iptalde stoga dondu', 'default' => 'yes'],
                '_hk_manual_discount' => ['label' => 'Manuel indirim ucreti', 'default' => 'yes'],
                '_hk_admin_added' => ['label' => 'Admin panelden eklendi', 'default' => 'yes'],
            ],
        ];
    }

    private static function format_meta_value($value)
    {
        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return (string) $value;
    }

    private static function format_item_meta($item)
    {
        $rows = [];
        foreach ($item->get_meta_data() as $meta) {
            $data = $meta->get_data();
            $rows[] = [
                'key' => $data['key'],
                'value' => $data['value'],
            ];
        }

        return wp_json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private static function save_item_meta($item, $raw_meta)
    {
        if ($raw_meta === null || trim((string) $raw_meta) === '') {
            return;
        }

        $rows = json_decode(wp_unslash((string) $raw_meta), true);
        if (!is_array($rows)) {
            throw new RuntimeException($item->get_name() . ' kalem metasi gecerli JSON degil.');
        }

        foreach ($item->get_meta_data() as $meta) {
            $data = $meta->get_data();
            $key = sanitize_text_field($data['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $item->delete_meta_data($key);
        }

        foreach ($rows as $row) {
            $key = sanitize_text_field($row['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $item->add_meta_data($key, self::parse_meta_value($row['value'] ?? ''), false);
        }
    }

    private static function parse_meta_value($value)
    {
        $value = is_string($value) ? trim(wp_unslash($value)) : $value;
        if (is_string($value) && $value !== '' && in_array($value[0], ['{', '['], true)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return is_scalar($value) ? sanitize_textarea_field((string) $value) : '';
    }

    private static function resolve_order($post_or_order_object = null)
    {
        if ($post_or_order_object instanceof WC_Order) {
            return $post_or_order_object;
        }

        if (is_object($post_or_order_object) && !empty($post_or_order_object->ID)) {
            return wc_get_order($post_or_order_object->ID);
        }

        $order_id = absint($_GET['id'] ?? ($_GET['post'] ?? 0));
        return $order_id ? wc_get_order($order_id) : false;
    }

    private static function can_manage_orders()
    {
        return current_user_can('edit_shop_orders') || current_user_can('manage_woocommerce');
    }

    private static function is_order_edit_screen()
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        return self::is_order_screen_id($screen->id);
    }

    private static function is_order_screen_id($screen_id)
    {
        $order_screens = ['shop_order', 'woocommerce_page_wc-orders'];
        if (function_exists('wc_get_page_screen_id')) {
            $order_screens[] = wc_get_page_screen_id('shop-order');
        }

        return in_array($screen_id, array_unique($order_screens), true);
    }

    /**
     * Depo listesini veritabanından çeker.
     */
    public static function get_depolar()
    {
        global $wpdb;
        $tables = Hizli_Kasa_Database::get_tables();
        if (empty($tables['depolar'])) {
            return [];
        }

        $rows = $wpdb->get_results("SELECT id, name FROM {$tables['depolar']} ORDER BY name ASC");
        $depolar = [];
        foreach ((array) $rows as $row) {
            $depolar[] = [
                'id' => (int) $row->id,
                'name' => $row->name,
            ];
        }

        return $depolar;
    }

    /**
     * Siparis Bilgileri panelini render eder.
     */
    public static function render_order_info_panel($order)
    {
        $payment_method = $order->get_payment_method();
        $payment_methods = [
            'cod' => 'Nakit',
            'other' => 'Kredi Karti',
            'bacs' => 'IBAN / Havale',
            'split' => 'Bolunmus Odeme',
        ];
        $kaynaklar = [
            'pos_satis' => 'POS Satis',
            'web' => 'Web',
            'telefon' => 'Telefon',
            'magaza' => 'Magaza',
            'diger' => 'Diger',
        ];
        $depolar = self::get_depolar();
        $current_depo_id = (int) $order->get_meta('_hk_cikis_depo_id');
        $current_kaynak = $order->get_meta('_hizli_kasa_kaynak') ?: '';
        $is_refund = $order->get_meta('_hizli_kasa_is_refund') === 'yes';
        $is_fully_refunded = $order->get_meta('_hk_is_fully_refunded') === 'yes';
        $has_refund = $order->get_meta('_hk_has_refund') === 'yes';
        $order_total = (float) $order->get_total();
        $is_split = ($payment_method === 'split');
        include HIZLI_KASA_PATH . 'includes/views/admin-order-info-panel.php';
    }

    /**
     * Siparis Bilgileri panelinden gelen verileri kaydeder.
     */
    private static function save_order_info($order, $info)
    {
        if (!is_array($info)) {
            return;
        }

        // Genel Bilgiler
        if (isset($info['kasiyer'])) {
            $order->update_meta_data('_hizli_kasa_kasiyer', sanitize_text_field($info['kasiyer']));
        }
        if (isset($info['kasa_no'])) {
            $order->update_meta_data('_hizli_kasa_kasa_no', sanitize_text_field($info['kasa_no']));
        }
        if (isset($info['kaynak'])) {
            $order->update_meta_data('_hizli_kasa_kaynak', sanitize_text_field($info['kaynak']));
        }
        if (isset($info['rapor_kaynak'])) {
            $order->update_meta_data('_hk_kaynak', sanitize_text_field($info['rapor_kaynak']));
        }
        if (!empty($info['date_created'])) {
            $date_created = str_replace('T', ' ', sanitize_text_field($info['date_created']));
            if (strlen($date_created) === 16) {
                $date_created .= ':00';
            }
            $order->set_date_created($date_created);
        }

        // Musteri Bilgileri
        if (isset($info['telefon'])) {
            $telefon = sanitize_text_field($info['telefon']);
            $order->update_meta_data('_hizli_kasa_musteri_telefon', $telefon);
            $order->set_billing_phone($telefon);
        }
        if (isset($info['note'])) {
            $order->set_customer_note(sanitize_textarea_field($info['note']));
        }

        // Odeme Yontemi
        if (!empty($info['payment_method'])) {
            $new_payment = sanitize_text_field($info['payment_method']);
            $payment_titles = [
                'cod' => 'Nakit',
                'other' => 'Kredi Kartı',
                'bacs' => 'IBAN / Havale',
                'split' => 'Bölünmüş Ödeme',
            ];

            $order->set_payment_method($new_payment);
            $order->set_payment_method_title($payment_titles[$new_payment] ?? $new_payment);

            // Odeme tutarlarini guncelle
            $final_total = (float) $order->get_total();

            if ($new_payment === 'split') {
                // Bolunmus odeme — tutarlar frontend'den gelir
                $nakit = isset($info['odeme_nakit']) ? (float) wc_format_decimal($info['odeme_nakit']) : 0;
                $kart = isset($info['odeme_kart']) ? (float) wc_format_decimal($info['odeme_kart']) : 0;
                $iban = isset($info['odeme_iban']) ? (float) wc_format_decimal($info['odeme_iban']) : 0;

                $order->update_meta_data('_odeme_nakit', $nakit);
                $order->update_meta_data('_odeme_kart', $kart);
                $order->update_meta_data('_odeme_iban', $iban);

                // Gorunen odeme metalarini guncelle
                $order->delete_meta_data('Ödeme (Nakit)');
                $order->delete_meta_data('Ödeme (Kredi Kartı)');
                $order->delete_meta_data('Ödeme (Kart)');
                $order->delete_meta_data('Ödeme (IBAN)');

                if ($nakit > 0) {
                    $order->update_meta_data('Ödeme (Nakit)', number_format($nakit, 2, '.', '') . ' TL');
                }
                if ($kart > 0) {
                    $order->update_meta_data('Ödeme (Kredi Kartı)', number_format($kart, 2, '.', '') . ' TL');
                }
                if ($iban > 0) {
                    $order->update_meta_data('Ödeme (IBAN)', number_format($iban, 2, '.', '') . ' TL');
                }
            } else {
                // Tek kanal odeme — tum tutar secilen kanala atanir
                $order->update_meta_data('_odeme_nakit', 0);
                $order->update_meta_data('_odeme_kart', 0);
                $order->update_meta_data('_odeme_iban', 0);
                $order->delete_meta_data('Ödeme (Nakit)');
                $order->delete_meta_data('Ödeme (Kredi Kartı)');
                $order->delete_meta_data('Ödeme (Kart)');
                $order->delete_meta_data('Ödeme (IBAN)');

                if ($new_payment === 'cod') {
                    $order->update_meta_data('_odeme_nakit', $final_total);
                    $order->update_meta_data('Ödeme (Nakit)', number_format($final_total, 2, '.', '') . ' TL');
                } elseif ($new_payment === 'other') {
                    $order->update_meta_data('_odeme_kart', $final_total);
                    $order->update_meta_data('Ödeme (Kredi Kartı)', number_format($final_total, 2, '.', '') . ' TL');
                } elseif ($new_payment === 'bacs') {
                    $order->update_meta_data('_odeme_iban', $final_total);
                    $order->update_meta_data('Ödeme (IBAN)', number_format($final_total, 2, '.', '') . ' TL');
                }
            }
        }

        // Depo Bilgileri
        if (isset($info['depo_id'])) {
            $depo_id = absint($info['depo_id']);
            $order->update_meta_data('_hk_cikis_depo_id', $depo_id);

            // Depo adini bul
            if ($depo_id > 0 && !empty($info['depo_adi'])) {
                $order->update_meta_data('_hk_cikis_depo_adi', sanitize_text_field($info['depo_adi']));
            } elseif ($depo_id === 0) {
                $order->update_meta_data('_hk_cikis_depo_adi', '');
            }
        }

        // Iade/Iskonto Bilgileri
        if (isset($info['original_order'])) {
            $val = sanitize_text_field($info['original_order']);
            if ($val !== '') {
                $order->update_meta_data('_hizli_kasa_original_order', $val);
            } else {
                $order->delete_meta_data('_hizli_kasa_original_order');
            }
        }
        if (isset($info['toplam_iskonto'])) {
            $order->update_meta_data('_hk_toplam_iskonto', wc_format_decimal($info['toplam_iskonto'], 2));
        }
    }
}