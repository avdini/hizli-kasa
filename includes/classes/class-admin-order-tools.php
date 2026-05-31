<?php
if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_Admin_Order_Tools
{
    const NONCE_ACTION = 'hizli_kasa_admin_order_tools';

    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes'], 20, 2);
        add_action('add_meta_boxes_shop_order', [__CLASS__, 'add_meta_box_to_current_screen'], 20, 1);
        add_action('add_meta_boxes_woocommerce_page_wc-orders', [__CLASS__, 'add_meta_box_to_current_screen'], 20, 1);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_hk_admin_order_tools_save', [__CLASS__, 'ajax_save_order']);
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
                [__CLASS__, 'render_meta_box'],
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
            [__CLASS__, 'render_meta_box'],
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
        ?>
        <div class="hk-admin-order-tools" data-order-id="<?php echo esc_attr($order_id); ?>">
            <div class="hk-aot-toolbar">
                <div>
                    <strong>#<?php echo esc_html($order_id); ?></strong>
                    <span class="hk-aot-pill"><?php echo esc_html($order->get_formatted_order_total()); ?></span>
                    <span class="hk-aot-pill"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span>
                </div>
                <label class="hk-aot-check">
                    <input type="checkbox" id="hk-aot-recalculate" checked>
                    Toplamlari yeniden hesapla
                </label>
            </div>

            <div class="hk-aot-tabs" role="tablist">
                <button type="button" class="is-active" data-hk-aot-tab="items">Sepet</button>
                <button type="button" data-hk-aot-tab="fees">Ucret / Kargo</button>
                <button type="button" data-hk-aot-tab="meta">Metalar</button>
            </div>

            <section class="hk-aot-panel is-active" data-hk-aot-panel="items">
                <div class="hk-aot-section-head">
                    <div>
                        <h4>Sepet kalemleri</h4>
                        <p>Adet, ara toplam, toplam ve kaleme bagli depo/iskonto metalarini duzenler.</p>
                    </div>
                </div>
                <table class="widefat striped hk-aot-table">
                    <thead>
                        <tr>
                            <th>Urun</th>
                            <th>Kalem metalari</th>
                            <th>Adet</th>
                            <th>Ara toplam</th>
                            <th>Toplam</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="hk-aot-items">
                        <?php foreach ($order->get_items('line_item') as $item_id => $item) : ?>
                            <tr data-item-id="<?php echo esc_attr($item_id); ?>">
                                <td>
                                    <strong><?php echo esc_html($item->get_name()); ?></strong>
                                    <small>ID: <?php echo esc_html($item_id); ?> | Urun: <?php echo esc_html($item->get_product_id()); ?><?php echo $item->get_variation_id() ? ' | Varyasyon: ' . esc_html($item->get_variation_id()) : ''; ?></small>
                                </td>
                                <td><textarea class="hk-aot-item-meta" rows="3"><?php echo esc_textarea(self::format_item_meta($item)); ?></textarea></td>
                                <td><input type="number" step="1" min="0" class="hk-aot-qty" value="<?php echo esc_attr($item->get_quantity()); ?>"></td>
                                <td><input type="number" step="0.01" class="hk-aot-subtotal" value="<?php echo esc_attr(wc_format_decimal($item->get_subtotal(), 2)); ?>"></td>
                                <td><input type="number" step="0.01" class="hk-aot-total" value="<?php echo esc_attr(wc_format_decimal($item->get_total(), 2)); ?>"></td>
                                <td><label><input type="checkbox" class="hk-aot-remove"> Sil</label></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="hk-aot-add-row">
                    <input type="number" id="hk-aot-add-product-id" min="1" placeholder="Urun/Varyasyon ID">
                    <input type="number" id="hk-aot-add-product-qty" min="1" step="1" value="1" placeholder="Adet">
                    <input type="number" id="hk-aot-add-product-total" step="0.01" placeholder="Toplam (bos ise fiyat)">
                    <button type="button" class="button" id="hk-aot-add-product">Urun ekle</button>
                </div>
                <div id="hk-aot-new-products"></div>
            </section>

            <section class="hk-aot-panel" data-hk-aot-panel="fees">
                <div class="hk-aot-section-head">
                    <div>
                        <h4>Ucretler ve kargo</h4>
                        <p>Negatif tutar indirim, pozitif tutar ek ucret olarak hesaplanir.</p>
                    </div>
                </div>
                <div id="hk-aot-fees">
                    <?php foreach ($order->get_fees() as $fee_id => $fee) : ?>
                        <div class="hk-aot-grid-row" data-fee-id="<?php echo esc_attr($fee_id); ?>">
                            <input type="text" class="hk-aot-fee-name" value="<?php echo esc_attr($fee->get_name()); ?>" placeholder="Ad">
                            <input type="number" step="0.01" class="hk-aot-fee-total" value="<?php echo esc_attr(wc_format_decimal($fee->get_total(), 2)); ?>" placeholder="Toplam">
                            <label><input type="checkbox" class="hk-aot-fee-remove"> Sil</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="hk-aot-add-fee">Ucret ekle</button>

                <h4 class="hk-aot-subtitle">Kargo</h4>
                <div id="hk-aot-shipping">
                    <?php foreach ($order->get_shipping_methods() as $shipping_id => $shipping) : ?>
                        <div class="hk-aot-grid-row" data-shipping-id="<?php echo esc_attr($shipping_id); ?>">
                            <input type="text" class="hk-aot-shipping-title" value="<?php echo esc_attr($shipping->get_method_title()); ?>" placeholder="Baslik">
                            <input type="number" step="0.01" class="hk-aot-shipping-total" value="<?php echo esc_attr(wc_format_decimal($shipping->get_total(), 2)); ?>" placeholder="Toplam">
                            <label><input type="checkbox" class="hk-aot-shipping-remove"> Sil</label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hk-aot-panel" data-hk-aot-panel="meta">
                <div class="hk-aot-section-head">
                    <div>
                        <h4>Siparis metalari</h4>
                        <p>Hizli Kasa'nin kullandigi siparis metalarini listeden secip hizlica ekleyebilirsiniz.</p>
                    </div>
                    <div class="hk-aot-meta-picker">
                        <select id="hk-aot-meta-template">
                            <option value="">Meta sec</option>
                            <?php foreach ($meta_catalog['order'] as $meta_key => $meta_info) : ?>
                                <option value="<?php echo esc_attr($meta_key); ?>" data-default="<?php echo esc_attr($meta_info['default']); ?>">
                                    <?php echo esc_html($meta_info['label'] . ' - ' . $meta_key); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button" id="hk-aot-add-selected-meta">Secileni ekle</button>
                    </div>
                </div>
                <details class="hk-aot-meta-reference">
                    <summary>Kalem/depo meta anahtarlarini goster</summary>
                    <div>
                        <?php foreach ($meta_catalog['item'] as $meta_key => $meta_info) : ?>
                            <code title="<?php echo esc_attr($meta_info['label']); ?>"><?php echo esc_html($meta_key); ?></code>
                        <?php endforeach; ?>
                    </div>
                </details>
                <div class="hk-aot-meta-list" id="hk-aot-meta-list">
                    <?php foreach ($order->get_meta_data() as $meta) : ?>
                        <?php
                        $data = $meta->get_data();
                        $value = self::format_meta_value($data['value']);
                        ?>
                        <div class="hk-aot-meta-row" data-meta-id="<?php echo esc_attr($data['id']); ?>">
                            <input type="text" class="hk-aot-meta-key" value="<?php echo esc_attr($data['key']); ?>">
                            <textarea class="hk-aot-meta-value" rows="2"><?php echo esc_textarea($value); ?></textarea>
                            <label><input type="checkbox" class="hk-aot-meta-remove"> Sil</label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="hk-aot-add-meta">Bos meta ekle</button>
            </section>

            <div class="hk-aot-actions">
                <span class="hk-aot-currency">Para birimi: <?php echo esc_html($currency); ?></span>
                <span id="hk-aot-message" aria-live="polite"></span>
                <button type="button" class="button button-primary" id="hk-aot-save">Kaydet ve hesapla</button>
            </div>
        </div>
        <?php
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
            $item->set_subtotal($total);
            $item->set_total($total);
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
            $fee->set_total($total);
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
            $fee->set_total($total);
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
}
