<?php
/**
 * Hızlı Kasa - QR Checkout Handler
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_QR_Checkout_Handler {

    public static function init() {
        add_filter('woocommerce_order_pay_page_title', [__CLASS__, 'customize_pay_page_title'], 10, 2);
        add_filter('woocommerce_pay_order_button_text', [__CLASS__, 'customize_pay_button_text'], 10, 1);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_qr_checkout_assets']);
        add_action('init', [__CLASS__, 'inject_posted_address_data'], 5);
        add_action('wp', [__CLASS__, 'inject_posted_address_data'], 5);
        add_action('template_redirect', [__CLASS__, 'inject_posted_address_data'], 5);
        add_action('woocommerce_before_checkout_process', [__CLASS__, 'inject_posted_address_data'], 5);
        add_action('woocommerce_pay_order_before_submit', [__CLASS__, 'render_hidden_address_inputs']);
        add_filter('woocommerce_payment_gateway_title', [__CLASS__, 'customize_gateway_title'], 10, 2);

        add_filter('woocommerce_order_needs_shipping_address', [__CLASS__, 'filter_needs_shipping_address'], 10, 3);
        add_filter('woocommerce_cart_needs_shipping_address', [__CLASS__, 'filter_cart_needs_shipping_address'], 10, 1);
        add_filter('woocommerce_order_item_is_virtual', [__CLASS__, 'filter_order_item_is_virtual'], 10, 3);
        add_filter('woocommerce_product_is_virtual', [__CLASS__, 'filter_product_is_virtual'], 10, 2);
        add_filter('woocommerce_shipping_enabled', [__CLASS__, 'filter_shipping_enabled'], 10, 1);
        add_filter('option_woocommerce_ship_to_destination', [__CLASS__, 'filter_ship_to_destination'], 10, 1);
        add_filter('woocommerce_order_get_shipping_address_1', [__CLASS__, 'filter_shipping_address_1'], 10, 2);
        add_filter('woocommerce_order_get_shipping_address_2', [__CLASS__, 'filter_shipping_address_2'], 10, 2);
        add_filter('woocommerce_order_get_shipping_city', [__CLASS__, 'filter_shipping_city'], 10, 2);
        add_filter('woocommerce_order_get_shipping_state', [__CLASS__, 'filter_shipping_state'], 10, 2);
        add_filter('woocommerce_order_get_shipping_country', [__CLASS__, 'filter_shipping_country'], 10, 2);
        add_filter('woocommerce_order_get_shipping_postcode', [__CLASS__, 'filter_shipping_postcode'], 10, 2);
        add_filter('woocommerce_order_get_shipping_first_name', [__CLASS__, 'filter_shipping_first_name'], 10, 2);
        add_filter('woocommerce_order_get_shipping_last_name', [__CLASS__, 'filter_shipping_last_name'], 10, 2);
        add_filter('woocommerce_order_get_shipping_company', [__CLASS__, 'filter_shipping_company'], 10, 2);
        add_filter('woocommerce_order_get_shipping_phone', [__CLASS__, 'filter_shipping_phone'], 10, 2);
        add_filter('woocommerce_order_get_address', [__CLASS__, 'filter_order_get_address'], 10, 3);
        add_filter('woocommerce_checkout_posted_data', [__CLASS__, 'filter_checkout_posted_data'], 10, 1);
    }

    public static function customize_pay_page_title($title, $order) {
        if ($order && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
            return 'Mağaza Taksitli Ödeme (# ' . $order->get_order_number() . ')';
        }
        return $title;
    }

    public static function customize_pay_button_text($text) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order    = wc_get_order($order_id);
            if ($order && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
                return '🔒 Güvenli Ödeme Yap';
            }
        }
        return $text;
    }

    public static function customize_gateway_title($title, $gateway_id) {
        if ($gateway_id === 'qr_sanal_pos') {
            return 'QR Taksitli Ödeme (Sanal POS)';
        }
        return $title;
    }

    private static function get_order_address_fields($order) {
        $store_address  = get_option('woocommerce_store_address') ?: 'Merkez Mahallesi Atatürk Caddesi No 1';
        $store_city     = get_option('woocommerce_store_city') ?: 'Istanbul';
        $store_postcode = get_option('woocommerce_store_postcode') ?: '34000';
        $raw_country    = get_option('woocommerce_default_country') ?: 'TR';
        $country_parts  = explode(':', $raw_country);
        $store_country  = !empty($country_parts[0]) ? $country_parts[0] : 'TR';
        $store_state    = !empty($country_parts[1]) ? $country_parts[1] : '34';

        $b_first = trim(preg_replace('/[0-9]/', '', $order->get_billing_first_name() ?: 'Kasa')) ?: 'Kasa';
        $b_last  = trim(preg_replace('/[0-9]/', '', $order->get_billing_last_name() ?: 'Müşterisi')) ?: 'Müşterisi';

        $raw_phone = preg_replace('/[^0-9]/', '', $order->get_billing_phone() ?: '5555555555');
        if (substr($raw_phone, 0, 1) === '0') {
            $raw_phone = substr($raw_phone, 1);
        }
        $b_phone = (!empty($raw_phone) && strlen($raw_phone) >= 10) ? $raw_phone : '5555555555';
        $b_email = $order->get_billing_email() ?: 'kasa@magaza.com';

        $b_addr = $order->get_billing_address_1();
        if (empty($b_addr) || mb_strlen(trim($b_addr)) < 10 || $b_addr === 'POS Satış') {
            $b_addr = $store_address;
        }

        $b_city = $order->get_billing_city();
        if (empty($b_city) || $b_city === 'Mağaza') {
            $b_city = $store_city;
        }

        $s_first = trim(preg_replace('/[0-9]/', '', $order->get_shipping_first_name() ?: $b_first)) ?: $b_first;
        $s_last  = trim(preg_replace('/[0-9]/', '', $order->get_shipping_last_name() ?: $b_last)) ?: $b_last;

        $s_addr = $order->get_shipping_address_1();
        if (empty($s_addr) || mb_strlen(trim($s_addr)) < 10 || $s_addr === 'POS Satış') {
            $s_addr = $b_addr;
        }

        $s_city = $order->get_shipping_city();
        if (empty($s_city) || $s_city === 'Mağaza') {
            $s_city = $b_city;
        }

        $s_phone_raw = preg_replace('/[^0-9]/', '', $order->get_shipping_phone() ?: $b_phone);
        if (substr($s_phone_raw, 0, 1) === '0') {
            $s_phone_raw = substr($s_phone_raw, 1);
        }
        $s_phone = (!empty($s_phone_raw) && strlen($s_phone_raw) >= 10) ? $s_phone_raw : $b_phone;

        return [
            'billing_first_name'         => $b_first,
            'billing_last_name'          => $b_last,
            'billing_company'            => trim(preg_replace('/[0-9]/', '', $order->get_billing_company() ?: get_bloginfo('name'))),
            'billing_address_1'          => $b_addr,
            'billing_address_2'          => $order->get_billing_address_2() ?: '',
            'billing_city'               => $b_city,
            'billing_state'              => $order->get_billing_state() ?: $store_state,
            'billing_postcode'           => $order->get_billing_postcode() ?: $store_postcode,
            'billing_country'            => $order->get_billing_country() ?: $store_country,
            'billing_phone'              => $b_phone,
            'billing_email'              => $b_email,

            'shipping_first_name'        => $s_first,
            'shipping_last_name'         => $s_last,
            'shipping_company'           => trim(preg_replace('/[0-9]/', '', $order->get_shipping_company() ?: get_bloginfo('name'))),
            'shipping_address_1'         => $s_addr,
            'shipping_address_2'         => $order->get_shipping_address_2() ?: '',
            'shipping_city'              => $s_city,
            'shipping_state'             => $order->get_shipping_state() ?: $store_state,
            'shipping_postcode'          => $order->get_shipping_postcode() ?: $store_postcode,
            'shipping_country'           => $order->get_shipping_country() ?: $store_country,
            'shipping_phone'             => $s_phone,

            'ship_to_different_address' => '1',
        ];
    }

    public static function render_hidden_address_inputs() {
        global $wp;
        if (!isset($wp->query_vars['order-pay'])) {
            return;
        }

        $order_id = absint($wp->query_vars['order-pay']);
        $order    = wc_get_order($order_id);

        if (!$order || $order->get_meta('_hizli_kasa_qr_payment') !== 'yes') {
            return;
        }

        $fields = self::get_order_address_fields($order);

        foreach ($fields as $name => $val) {
            echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" />' . "\n";
        }
    }

    public static function inject_posted_address_data() {
        global $wp;
        $order_id = 0;

        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
        } elseif (isset($_GET['order-pay'])) {
            $order_id = absint($_GET['order-pay']);
        } elseif (isset($_POST['order_id'])) {
            $order_id = absint($_POST['order_id']);
        } elseif (preg_match('#/pay/(\d+)#', $_SERVER['REQUEST_URI'] ?? '', $matches)) {
            $order_id = absint($matches[1]);
        }

        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order || $order->get_meta('_hizli_kasa_qr_payment') !== 'yes') {
            return;
        }

        $fields = self::get_order_address_fields($order);

        foreach ($fields as $key => $val) {
            if (empty($_POST[$key])) {
                $_POST[$key] = $val;
            }
            if (empty($_REQUEST[$key])) {
                $_REQUEST[$key] = $val;
            }
        }

        if (function_exists('WC') && WC()->customer) {
            WC()->customer->set_billing_first_name($fields['billing_first_name']);
            WC()->customer->set_billing_last_name($fields['billing_last_name']);
            WC()->customer->set_billing_address_1($fields['billing_address_1']);
            WC()->customer->set_billing_city($fields['billing_city']);
            WC()->customer->set_billing_state($fields['billing_state']);
            WC()->customer->set_billing_postcode($fields['billing_postcode']);
            WC()->customer->set_billing_country($fields['billing_country']);
            WC()->customer->set_billing_phone($fields['billing_phone']);
            WC()->customer->set_billing_email($fields['billing_email']);

            WC()->customer->set_shipping_first_name($fields['shipping_first_name']);
            WC()->customer->set_shipping_last_name($fields['shipping_last_name']);
            WC()->customer->set_shipping_address_1($fields['shipping_address_1']);
            WC()->customer->set_shipping_city($fields['shipping_city']);
            WC()->customer->set_shipping_state($fields['shipping_state']);
            WC()->customer->set_shipping_postcode($fields['shipping_postcode']);
            WC()->customer->set_shipping_country($fields['shipping_country']);
        }
    }

    public static function enqueue_qr_checkout_assets() {
        global $wp;
        if (!isset($wp->query_vars['order-pay'])) {
            return;
        }

        $order_id = absint($wp->query_vars['order-pay']);
        $order    = wc_get_order($order_id);

        if (!$order || $order->get_meta('_hizli_kasa_qr_payment') !== 'yes') {
            return;
        }

        $custom_css = "
            .woocommerce-billing-fields,
            .woocommerce-shipping-fields,
            .woocommerce-additional-fields,
            #customer_details,
            .wc-bacs-bank-details {
                display: none !important;
            }

            body.woocommerce-order-pay {
                background-color: #f8f9fa;
            }
            body.woocommerce-order-pay #page,
            body.woocommerce-order-pay #content {
                max-width: 600px;
                margin: 20px auto;
                padding: 20px;
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            }
            .woocommerce-order-pay table.shop_table {
                border-radius: 8px;
                overflow: hidden;
            }
            #order_review #payment {
                background: #f1f3f5;
                border-radius: 8px;
                padding: 15px;
            }
            #order_review #payment button#place_order {
                width: 100%;
                font-size: 18px;
                padding: 14px;
                border-radius: 8px;
                background: #6C5CE7;
                border: none;
                color: #fff;
                font-weight: 700;
            }
            #order_review #payment button#place_order:hover {
                background: #5a4bcf;
            }
        ";

        wp_add_inline_style('woocommerce-general', $custom_css);
    }

    public static function filter_needs_shipping_address($needs_shipping, $arg2 = null, $arg3 = null) {
        $order = null;
        if ($arg2 instanceof WC_Order) {
            $order = $arg2;
        } elseif ($arg3 instanceof WC_Order) {
            $order = $arg3;
        }

        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
            return true;
        }
        return $needs_shipping;
    }

    public static function filter_cart_needs_shipping_address($needs_shipping) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order    = wc_get_order($order_id);
            if ($order && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
                return true;
            }
        }
        return $needs_shipping;
    }

    public static function filter_order_item_is_virtual($is_virtual, $item = null, $order = null) {
        if ($order instanceof WC_Order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
            return true;
        }
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $current_order = wc_get_order($order_id);
            if ($current_order && $current_order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
                return true;
            }
        }
        return $is_virtual;
    }

    public static function filter_product_is_virtual($is_virtual, $product = null) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order    = wc_get_order($order_id);
            if ($order && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
                return true;
            }
        }
        return $is_virtual;
    }

    public static function filter_shipping_enabled($enabled) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order    = wc_get_order($order_id);
            if ($order && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
                return true;
            }
        }
        return $enabled;
    }

    public static function filter_ship_to_destination($value) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order    = wc_get_order($order_id);
            if ($order && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
                return 'shipping';
            }
        }
        return $value;
    }

    public static function filter_shipping_address_1($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
            if (empty($value) || mb_strlen(trim($value)) < 10 || $value === 'POS Satış') {
                return get_option('woocommerce_store_address') ?: 'Merkez Mahallesi Atatürk Caddesi No 1';
            }
        }
        return $value;
    }

    public static function filter_shipping_address_2($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && empty($value)) {
            return '';
        }
        return $value;
    }

    public static function filter_shipping_city($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
            if (empty($value) || $value === 'Mağaza') {
                return get_option('woocommerce_store_city') ?: 'Istanbul';
            }
        }
        return $value;
    }

    public static function filter_shipping_state($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && empty($value)) {
            $raw_country   = get_option('woocommerce_default_country') ?: 'TR:34';
            $country_parts = explode(':', $raw_country);
            return !empty($country_parts[1]) ? $country_parts[1] : '34';
        }
        return $value;
    }

    public static function filter_shipping_country($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && empty($value)) {
            $raw_country   = get_option('woocommerce_default_country') ?: 'TR';
            $country_parts = explode(':', $raw_country);
            return !empty($country_parts[0]) ? $country_parts[0] : 'TR';
        }
        return $value;
    }

    public static function filter_shipping_postcode($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && empty($value)) {
            return get_option('woocommerce_store_postcode') ?: '34000';
        }
        return $value;
    }

    public static function filter_shipping_first_name($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && empty($value)) {
            return (method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : '') ?: 'Kasa';
        }
        return $value;
    }

    public static function filter_shipping_last_name($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && empty($value)) {
            return (method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : '') ?: 'Müşterisi';
        }
        return $value;
    }

    public static function filter_shipping_company($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && empty($value)) {
            return get_bloginfo('name') ?: 'Hızlı Kasa';
        }
        return $value;
    }

    public static function filter_shipping_phone($value, $order = null) {
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && empty($value)) {
            $phone = (method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : '') ?: '5555555555';
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (substr($phone, 0, 1) === '0') {
                $phone = substr($phone, 1);
            }
            return (!empty($phone) && strlen($phone) >= 10) ? $phone : '5555555555';
        }
        return $value;
    }

    public static function filter_order_get_address($address = [], $type = 'shipping', $order = null) {
        if (is_object($type)) {
            $order = $type;
            $type  = 'shipping';
        }
        if (!is_array($address)) {
            $address = [];
        }
        if ($order && method_exists($order, 'get_meta') && $order->get_meta('_hizli_kasa_qr_payment') === 'yes' && ($type === 'shipping' || empty($type))) {
            if (empty($address['address_1']) || mb_strlen(trim($address['address_1'])) < 10 || $address['address_1'] === 'POS Satış') {
                $address['address_1'] = get_option('woocommerce_store_address') ?: 'Merkez Mahallesi Atatürk Caddesi No 1';
            }
            if (empty($address['city']) || $address['city'] === 'Mağaza') {
                $address['city'] = get_option('woocommerce_store_city') ?: 'Istanbul';
            }
            if (empty($address['country'])) {
                $raw_country   = get_option('woocommerce_default_country') ?: 'TR';
                $country_parts = explode(':', $raw_country);
                $address['country'] = !empty($country_parts[0]) ? $country_parts[0] : 'TR';
            }
            if (empty($address['postcode'])) {
                $address['postcode'] = get_option('woocommerce_store_postcode') ?: '34000';
            }
            if (empty($address['first_name'])) {
                $fname = $order->get_billing_first_name() ?: 'Kasa';
                $address['first_name'] = trim(preg_replace('/[0-9]/', '', $fname)) ?: 'Kasa';
            }
            if (empty($address['last_name'])) {
                $lname = $order->get_billing_last_name() ?: 'Müşterisi';
                $address['last_name'] = trim(preg_replace('/[0-9]/', '', $lname)) ?: 'Müşterisi';
            }
            if (empty($address['phone'])) {
                $raw_phone = preg_replace('/[^0-9]/', '', $order->get_billing_phone() ?: '5555555555');
                if (substr($raw_phone, 0, 1) === '0') {
                    $raw_phone = substr($raw_phone, 1);
                }
                $address['phone'] = (!empty($raw_phone) && strlen($raw_phone) >= 10) ? $raw_phone : '5555555555';
            }
            if (empty($address['state'])) {
                $raw_country   = get_option('woocommerce_default_country') ?: 'TR:34';
                $country_parts = explode(':', $raw_country);
                $address['state'] = !empty($country_parts[1]) ? $country_parts[1] : '34';
            }
        }
        return $address;
    }

    public static function filter_checkout_posted_data($data) {
        if (is_array($data)) {
            $data['ship_to_different_address'] = 1;
            if (empty($data['shipping_address_1']) && !empty($data['billing_address_1'])) {
                $data['shipping_address_1'] = $data['billing_address_1'];
            }
            if (empty($data['shipping_city']) && !empty($data['billing_city'])) {
                $data['shipping_city'] = $data['billing_city'];
            }
            if (empty($data['shipping_country']) && !empty($data['billing_country'])) {
                $data['shipping_country'] = $data['billing_country'];
            }
            if (empty($data['shipping_postcode']) && !empty($data['billing_postcode'])) {
                $data['shipping_postcode'] = $data['billing_postcode'];
            }
            if (empty($data['shipping_first_name']) && !empty($data['billing_first_name'])) {
                $data['shipping_first_name'] = $data['billing_first_name'];
            }
            if (empty($data['shipping_last_name']) && !empty($data['billing_last_name'])) {
                $data['shipping_last_name'] = $data['billing_last_name'];
            }
            if (empty($data['shipping_phone']) && !empty($data['billing_phone'])) {
                $data['shipping_phone'] = $data['billing_phone'];
            }
        }
        return $data;
    }
}
