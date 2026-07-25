<?php
/**
 * Hızlı Kasa - QR Checkout Handler
 *
 * WooCommerce checkout/order-pay sayfasını QR Taksitli Ödeme siparişleri için özelleştirir.
 * Adres ve ek bilgi formlarını gizler, doğrudan sanal POS kart / taksit ekranını sunar.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_QR_Checkout_Handler {

    public static function init() {
        // 1. Ödeme Sayfası Başlığı & Buton Metni
        add_filter('woocommerce_order_pay_page_title', [__CLASS__, 'customize_pay_page_title'], 10, 2);
        add_filter('woocommerce_pay_order_button_text', [__CLASS__, 'customize_pay_button_text'], 10, 1);

        // 2. Ödeme Sayfasına Özel CSS/JS Inject (Adres formlarını gizler)
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_qr_checkout_assets']);

        // 3. Fiş / Rapor Ödeme Yöntemi Adı Unvanı
        add_filter('woocommerce_payment_gateway_title', [__CLASS__, 'customize_gateway_title'], 10, 2);
    }

    /**
     * QR Ödeme Siparişlerinde Sayfa Başlığı
     */
    public static function customize_pay_page_title($title, $order) {
        if ($order && $order->get_meta('_hizli_kasa_qr_payment') === 'yes') {
            return 'Mağaza Taksitli Ödeme (# ' . $order->get_order_number() . ')';
        }
        return $title;
    }

    /**
     * QR Ödeme Siparişlerinde Ödeme Butonu Metni
     */
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

    /**
     * Gateway Başlığı Özelleştirmesi
     */
    public static function customize_gateway_title($title, $gateway_id) {
        if ($gateway_id === 'qr_sanal_pos') {
            return 'QR Taksitli Ödeme (Sanal POS)';
        }
        return $title;
    }

    /**
     * QR Ödeme Sayfası İçin Temiz Görünüm CSS
     */
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

        // QR ödeme sayfasına özel CSS ekle
        $custom_css = "
            /* Adres ve gereksiz bölümleri gizle */
            .woocommerce-billing-fields,
            .woocommerce-shipping-fields,
            .woocommerce-additional-fields,
            #customer_details,
            .wc-bacs-bank-details {
                display: none !important;
            }

            /* Sayfa Tasarımını Mağaza Ödemesi İçin Şıklaştır */
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
}
